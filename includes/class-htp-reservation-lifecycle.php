<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Owns reservation creation and all customer/admin lifecycle transitions. */
final class HTP_Reservation_Lifecycle implements HTP_Reservation_Lifecycle_Interface {
	private $inventory;
	private $notifications;
	private $repository;
	private $cart_order;
	private $rules;
	private $locks;

	public function __construct(
		HTP_Inventory_Manager $inventory,
		HTP_Notification_Dispatcher $notifications,
		HTP_Reservation_Repository_Interface $repository,
		HTP_Cart_Order_Service $cart_order,
		HTP_Reservation_Rules $rules,
		HTP_Lock_Manager $locks
	) {
		$this->inventory     = $inventory;
		$this->notifications = $notifications;
		$this->repository    = $repository;
		$this->cart_order    = $cart_order;
		$this->rules         = $rules;
		$this->locks         = $locks;
	}

	/** Validate and create a customer reservation inside product and user locks. */
	public function request( $product_id, $user_id ) {
		$product_id = absint( $product_id );
		$user_id    = absint( $user_id );
		$product    = wc_get_product( $product_id );
		if ( ! $product || ! $this->rules->is_product_reservable( $product_id, $user_id ) ) {
			return new WP_Error( 'htp_not_reservable', __( 'Reservations are not available for this product.', 'hold-this-product' ) );
		}

		$locks = $this->locks->acquire( array( 'product_' . $product_id, 'user_' . $user_id ) );
		if ( is_wp_error( $locks ) ) {
			return $locks;
		}

		try {
			/**
			 * Fires after the product and customer request locks are acquired.
			 *
			 * Observers must not mutate reservation or inventory state here.
			 *
			 * @param int $product_id Product ID.
			 * @param int $user_id Customer user ID.
			 */
			do_action( 'htp_reservation_request_locked', $product_id, $user_id );

			$requires_approval = $this->rules->requires_approval( $product_id, $user_id );
			$limit             = $this->rules->get_max_reservations_per_user( $user_id );
			if ( $this->repository->count_open( $user_id ) >= $limit ) {
				/* translators: %d: maximum number of open reservations allowed. */
				return new WP_Error( 'htp_limit', sprintf( __( 'You have reached the maximum of %d open reservations.', 'hold-this-product' ), $limit ) );
			}
			if ( $this->repository->user_has_open_for_product( $product_id, $user_id ) ) {
				return new WP_Error( 'htp_duplicate', __( 'You already have a pending or active reservation for this product.', 'hold-this-product' ) );
			}
			if ( (int) $product->get_stock_quantity( 'edit' ) < 1 ) {
				return new WP_Error( 'htp_no_stock', __( 'No stock available.', 'hold-this-product' ) );
			}

			$reservation_id = $this->create( $product_id, $user_id );
			if ( ! $reservation_id ) {
				return new WP_Error( 'htp_create_failed', __( 'Could not create reservation.', 'hold-this-product' ) );
			}
			return array(
				'reservation_id'    => $reservation_id,
				'requires_approval' => $requires_approval,
			);
		} finally {
			$this->locks->release( $locks );
		}
	}

	public function create( $product_id, $user_id = 0, $guest_email = '' ) {
		unset( $guest_email );
		$product_id        = absint( $product_id );
		$user_id           = absint( $user_id );
		$requires_approval = $this->rules->requires_approval( $product_id, $user_id );
		$duration_hours    = $this->rules->get_duration_hours( $requires_approval ? 'pending' : 'active', $product_id, $user_id );
		$expires_at        = time() + ( $duration_hours * HOUR_IN_SECONDS );

		$reservation_id = wp_insert_post(
			array(
				'post_type'   => 'htp_reservation',
				'post_title'  => 'Reservation for product ' . $product_id,
				'post_status' => 'publish',
				'post_author' => $user_id,
			),
			true
		);
		if ( is_wp_error( $reservation_id ) ) {
			return false;
		}

		$initial_status     = $requires_approval ? HTP_Reservation_Status::PENDING : HTP_Reservation_Status::INITIALIZING;
		$meta_data          = array(
			HTP_Reservation_Meta::PRODUCT_ID      => $product_id,
			HTP_Reservation_Meta::STATUS          => $initial_status,
			HTP_Reservation_Meta::EXPIRES_AT      => $expires_at,
			HTP_Reservation_Meta::QUANTITY        => 1,
			HTP_Reservation_Meta::TIMESTAMP_MODEL => 'utc',
			HTP_Reservation_Meta::INVENTORY_STATE => HTP_Inventory_Manager::STATE_NONE,
		);
		$notification_email = '';
		if ( $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$notification_email                       = $user->user_email;
				$meta_data[ HTP_Reservation_Meta::EMAIL ] = $notification_email;
			}
		}

		foreach ( $meta_data as $key => $value ) {
			if ( false === HTP_Reservation_Meta::update( $reservation_id, $key, $value ) ) {
				wp_delete_post( $reservation_id, true );
				return false;
			}
		}

		if ( ! $requires_approval ) {
			$activated = $this->inventory->activate( $reservation_id, HTP_Reservation_Status::INITIALIZING, wc_get_product( $product_id ), 1, array(), 'create' );
			if ( is_wp_error( $activated ) ) {
				wp_delete_post( $reservation_id, true );
				return false;
			}
		}

		if ( $notification_email ) {
			$this->notifications->dispatch( $requires_approval ? 'pending' : 'created', $reservation_id, $notification_email );
		}
		if ( $requires_approval ) {
			$this->announce_transition( $reservation_id, '', HTP_Reservation_Status::PENDING, 'create' );
		}
		$this->cart_order->clear_cache();
		return $reservation_id;
	}

	public function approve( $reservation_id ) {
		$reservation_id = absint( $reservation_id );
		if ( 'htp_reservation' !== get_post_type( $reservation_id ) ) {
			return new WP_Error( 'htp_invalid_reservation', __( 'Invalid reservation.', 'hold-this-product' ) );
		}

		$product_id = (int) HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::PRODUCT_ID );
		$user_id    = (int) get_post_field( 'post_author', $reservation_id );
		$locks      = $this->locks->acquire( array( 'product_' . $product_id, 'user_' . $user_id ) );
		if ( is_wp_error( $locks ) ) {
			return $locks;
		}

		try {
			if ( HTP_Reservation_Status::PENDING !== HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::STATUS ) ) {
				return new WP_Error( 'htp_not_pending', __( 'Reservation is not pending approval.', 'hold-this-product' ) );
			}
			if ( ! $product_id ) {
				return new WP_Error( 'htp_missing_product', __( 'Reservation is missing product data.', 'hold-this-product' ) );
			}
			$product = wc_get_product( $product_id );
			if ( ! $product || ! $product->is_type( 'simple' ) || ! $product->managing_stock() ) {
				return new WP_Error( 'htp_stock_unmanaged', __( 'Product stock is not managed.', 'hold-this-product' ) );
			}
			if ( (int) $product->get_stock_quantity( 'edit' ) <= 0 ) {
				return new WP_Error( 'htp_no_stock', __( 'No stock available to approve this reservation.', 'hold-this-product' ) );
			}
			$expires_at = time() + ( $this->rules->get_duration_hours( 'active', $product_id, $user_id ) * HOUR_IN_SECONDS );
			$activation = $this->inventory->activate(
				$reservation_id,
				HTP_Reservation_Status::PENDING,
				$product,
				(int) HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::QUANTITY ),
				array( HTP_Reservation_Meta::EXPIRES_AT => $expires_at ),
				'approve'
			);
			if ( is_wp_error( $activation ) ) {
				return $activation;
			}
		} finally {
			$this->locks->release( $locks );
		}

		$email = HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::EMAIL );
		if ( $email ) {
			$this->notifications->dispatch( 'approved', $reservation_id, $email );
		}
		$this->cart_order->clear_cache();
		return true;
	}

	public function deny( $reservation_id, $reason = '' ) {
		$reservation_id = absint( $reservation_id );
		if ( 'htp_reservation' !== get_post_type( $reservation_id ) ) {
			return false;
		}

		$product_id = (int) HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::PRODUCT_ID );
		$user_id    = (int) get_post_field( 'post_author', $reservation_id );
		$locks      = $this->locks->acquire( array( 'product_' . $product_id, 'user_' . $user_id ) );
		if ( is_wp_error( $locks ) ) {
			return $locks;
		}

		try {
			if ( HTP_Reservation_Status::PENDING !== HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::STATUS ) ) {
				return false;
			}
			$result = $this->inventory->release(
				$reservation_id,
				HTP_Reservation_Status::PENDING,
				HTP_Reservation_Status::DENIED,
				null,
				(int) HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::QUANTITY ),
				'deny'
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( $reason ) {
				HTP_Reservation_Meta::update( $reservation_id, HTP_Reservation_Meta::DENIAL_REASON, sanitize_text_field( $reason ) );
			}
		} finally {
			$this->locks->release( $locks );
		}

		$email = HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::EMAIL );
		if ( $email ) {
			$this->notifications->dispatch( 'denied', $reservation_id, $email, array( 'reason' => $reason ) );
		}
		return true;
	}

	public function cancel( $reservation_id ) {
		$reservation_id = absint( $reservation_id );
		if ( 'htp_reservation' !== get_post_type( $reservation_id ) ) {
			return false;
		}

		$product_id = (int) HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::PRODUCT_ID );
		$user_id    = (int) get_post_field( 'post_author', $reservation_id );
		$locks      = $this->locks->acquire( array( 'product_' . $product_id, 'user_' . $user_id ) );
		if ( is_wp_error( $locks ) ) {
			return $locks;
		}

		try {
			$previous_status = HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::STATUS );
			if ( ! in_array( $previous_status, HTP_Reservation_Status::open(), true ) ) {
				return false;
			}
			$product = HTP_Reservation_Status::ACTIVE === $previous_status ? wc_get_product( $product_id ) : null;
			$result  = $this->inventory->release(
				$reservation_id,
				$previous_status,
				HTP_Reservation_Status::CANCELLED,
				$product,
				(int) HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::QUANTITY ),
				'cancel'
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		} finally {
			$this->locks->release( $locks );
		}

		$this->cart_order->clear_cache();
		return true;
	}

	private function announce_transition( $reservation_id, $from, $to, $source ) {
		do_action(
			'htp_reservation_transitioned',
			array(
				'reservation_id' => absint( $reservation_id ),
				'from'           => $from,
				'to'             => $to,
				'source'         => sanitize_key( $source ),
				'occurred_at'    => time(),
			)
		);
	}
}
