<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Core reservations functionality
 */
class HTP_Reservations {

	const CRON_HOOK = 'htp_expire_reservations';

	private $allowance_cache = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize hooks
     */
    private function init() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'register_endpoints' ) );
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'expire_old_reservations' ) );
        
        // WooCommerce account integration
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_menu_item' ) );
        add_action( 'woocommerce_account_htp-reservations_endpoint', array( $this, 'reservations_endpoint_content' ) );
        add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_reservation_actions' ) );
        add_filter( 'woocommerce_endpoint_htp-reservations_title', array( $this, 'reservations_endpoint_title' ) );
        add_filter( 'woocommerce_page_title', array( $this, 'change_reservations_page_title' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_holdthisproduct_reserve', array( $this, 'handle_reservation_ajax' ) );

		add_filter( 'woocommerce_product_get_stock_quantity', array( $this, 'include_owned_hold_in_stock' ), 20, 2 );
		add_filter( 'woocommerce_product_get_stock_status', array( $this, 'include_owned_hold_in_stock_status' ), 20, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'attach_reservation_to_cart_item' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'copy_reservation_to_order_item' ), 10, 4 );
		add_filter( 'woocommerce_order_item_quantity', array( $this, 'exclude_linked_hold_from_order_quantity' ), 10, 3 );
		add_filter( 'woocommerce_order_hold_stock_minutes', array( $this, 'skip_redundant_order_stock_hold' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'transfer_holds_to_order' ), 5 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'transfer_holds_to_order' ), 5 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_reservation_order_item_meta' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'restore_transferred_order_stock' ), 5 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'restore_transferred_order_stock' ), 5 );

		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_privacy_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_privacy_eraser' ) );
    }

	public function add_cron_schedule( $schedules ) {
		$schedules['htp_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes', 'hold-this-product' ),
		);
		return $schedules;
	}

	public function schedule_expiration() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'htp_five_minutes', self::CRON_HOOK );
		}
	}
    
    /**
     * Register custom post type for reservations
     */
    public function register_post_type() {
        register_post_type( 'htp_reservation', array(
			'labels' => array( 'name' => __( 'Reservations', 'hold-this-product' ) ),
            'public' => false,
            'show_ui' => false,
            'supports' => array( 'title', 'author' ),
            'capability_type' => 'post',
        ) );
    }
    
    /**
     * Register WooCommerce endpoints
     */
    public function register_endpoints() {
        add_rewrite_endpoint( 'htp-reservations', EP_ROOT | EP_PAGES );
    }
    
    /**
     * Add query vars for WooCommerce
     */
    public function add_query_vars( $vars ) {
        $vars['htp-reservations'] = 'htp-reservations';
        return $vars;
    }
    
    /**
     * Flush rewrite rules (call this on plugin activation)
     */
    public function flush_rewrite_rules() {
        $this->register_endpoints();
        flush_rewrite_rules();
    }
    
    /**
     * Handle reservation AJAX request
     */
    public function handle_reservation_ajax() {
        check_ajax_referer( 'holdthisproduct_nonce', 'security' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in to reserve products.', 'hold-this-product' ), 401 );
		}

        $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( __( 'Invalid product ID.', 'hold-this-product' ), 400 );
        }

        $user_id = get_current_user_id();
        $product = wc_get_product( $product_id );
		if ( ! $this->is_product_reservable( $product_id ) || ! $product || ! $product->is_type( 'simple' ) || ! $product->managing_stock() || ! $product->is_purchasable() || 'publish' !== get_post_status( $product_id ) ) {
			wp_send_json_error( __( 'Reservations are not available for this product.', 'hold-this-product' ), 400 );
		}

		$locks = $this->acquire_locks( array( 'product_' . $product_id, 'user_' . $user_id ) );
		if ( is_wp_error( $locks ) ) {
			wp_send_json_error( $locks->get_error_message(), 409 );
		}

		$result = null;
		try {
			$options          = $this->get_options();
			$require_approval = ! empty( $options['require_admin_approval'] );
			$limit            = $this->get_max_reservations_per_user();
			if ( $this->count_open_reservations( $user_id ) >= $limit ) {
				/* translators: %d: maximum number of open reservations allowed. */
				$result = new WP_Error( 'htp_limit', sprintf( __( 'You have reached the maximum of %d open reservations.', 'hold-this-product' ), $limit ) );
			} elseif ( $this->user_has_open_reservation_for_product( $product_id, $user_id ) ) {
				$result = new WP_Error( 'htp_duplicate', __( 'You already have a pending or active reservation for this product.', 'hold-this-product' ) );
			} elseif ( (int) $product->get_stock_quantity( 'edit' ) < 1 ) {
				$result = new WP_Error( 'htp_no_stock', __( 'No stock available.', 'hold-this-product' ) );
			} else {
				$stock_held = false;
				if ( ! $require_approval ) {
					$new_stock = wc_update_product_stock( $product, 1, 'decrease' );
					if ( null === $new_stock || (int) $new_stock < 0 ) {
						if ( null !== $new_stock ) {
							wc_update_product_stock( $product, 1, 'increase' );
						}
						$result = new WP_Error( 'htp_stock_changed', __( 'Stock changed while the reservation was being created. Please try again.', 'hold-this-product' ) );
					} else {
						$stock_held = true;
					}
				}

				if ( ! is_wp_error( $result ) ) {
					$reservation_id = $this->create_reservation( $product_id, $user_id );
					if ( ! $reservation_id ) {
						if ( $stock_held ) {
							wc_update_product_stock( $product, 1, 'increase' );
						}
						$result = new WP_Error( 'htp_create_failed', __( 'Could not create reservation.', 'hold-this-product' ) );
					} else {
						$result = $reservation_id;
					}
				}
			}
		} finally {
			$this->release_locks( $locks );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 400 );
		}
		$this->allowance_cache = array();
		wp_send_json_success( $require_approval ? __( 'Reservation request submitted for approval.', 'hold-this-product' ) : __( 'Reservation created successfully.', 'hold-this-product' ) );
    }
    
    /**
     * Create a new reservation
     */
    public function create_reservation( $product_id, $user_id = 0, $guest_email = '' ) {
		unset( $guest_email );
        $options = $this->get_options();
		$require_approval = ! empty( $options['require_admin_approval'] );
        $duration_hours = $require_approval ? absint( $options['pending_duration'] ) : absint( $options['reservation_duration'] );
        $expires_at = time() + ( $duration_hours * HOUR_IN_SECONDS );
        
        $reservation_id = wp_insert_post( array(
            'post_type'   => 'htp_reservation',
            'post_title'  => 'Reservation for product ' . $product_id,
            'post_status' => 'publish',
            'post_author' => $user_id ?: 0,
        ), true );
        
        if ( is_wp_error( $reservation_id ) ) {
            return false;
        }
        
        // Determine initial status based on admin approval setting
        $initial_status = $require_approval ? 'pending_approval' : 'active';
        
        // Save meta data
        $meta_data = array(
            '_htp_product_id' => $product_id,
            '_htp_status' => $initial_status,
            '_htp_expires_at' => $expires_at,
            '_htp_qty' => 1,
			'_htp_timestamp_model' => 'utc',
        );
        
        // Get logged-in user's email for notifications
        $notification_email = '';
        if ( $user_id ) {
            $user = get_userdata( $user_id );
            if ( $user ) {
                $notification_email = $user->user_email;
                $meta_data['_htp_email'] = $notification_email;
            }
        }
        
        foreach ( $meta_data as $key => $value ) {
			if ( false === update_post_meta( $reservation_id, $key, $value ) ) {
				wp_delete_post( $reservation_id, true );
				return false;
			}
        }
        
        // Trigger appropriate email notification
        if ( $notification_email ) {
            if ( $require_approval ) {
                do_action( 'htp_reservation_pending_approval', $reservation_id, $notification_email );
            } else {
                do_action( 'htp_reservation_created', $reservation_id, $notification_email );
            }
        }
        
        return $reservation_id;
    }
    
    /**
     * Check if product is reservable
     */
    public function is_product_reservable( $product_id ) {
        if ( ! $this->are_reservations_globally_enabled() ) {
            return false;
        }
        
        // Require user to be logged in
        if ( ! is_user_logged_in() ) {
            return false;
        }

		$product = wc_get_product( absint( $product_id ) );
		return $product instanceof WC_Product
			&& $product->is_type( 'simple' )
			&& $product->managing_stock()
			&& $product->is_purchasable()
			&& 'publish' === get_post_status( $product_id )
			&& (int) $product->get_stock_quantity( 'edit' ) > 0;
    }
    
    /**
     * Check if reservations are globally enabled
     */
    public function are_reservations_globally_enabled() {
        $options = get_option( 'holdthisproduct_options' );
        return ! empty( $options['enable_reservation'] );
    }
    
    /**
     * Get max reservations per user
     */
    public function get_max_reservations_per_user() {
		$options = $this->get_options();
        return max( 1, min( 100, absint( $options['max_reservations'] ) ) );
    }

	private function get_options() {
		$options = get_option( 'holdthisproduct_options', array() );
		$options = is_array( $options ) ? $options : array();
		return wp_parse_args(
			$options,
			array(
				'enable_reservation'         => 0,
				'max_reservations'           => 1,
				'reservation_duration'       => 24,
				'pending_duration'           => 24,
				'require_admin_approval'     => 0,
				'enable_email_notifications' => 0,
			)
		);
	}

	private function acquire_locks( $names ) {
		$locks = array();
		sort( $names, SORT_STRING );
		foreach ( array_unique( $names ) as $name ) {
			$key   = 'htp_lock_' . sanitize_key( $name );
			$token = wp_generate_uuid4() . '|' . time();
			if ( ! add_option( $key, $token, '', false ) ) {
				$parts = explode( '|', (string) get_option( $key, '' ) );
				if ( isset( $parts[1] ) && time() - (int) $parts[1] > 30 ) {
					delete_option( $key );
				}
				if ( ! add_option( $key, $token, '', false ) ) {
					$this->release_locks( $locks );
					return new WP_Error( 'htp_busy', __( 'Another reservation is being processed. Please try again.', 'hold-this-product' ) );
				}
			}
			$locks[ $key ] = $token;
		}
		return $locks;
	}

	private function release_locks( $locks ) {
		foreach ( (array) $locks as $key => $token ) {
			if ( hash_equals( (string) get_option( $key, '' ), (string) $token ) ) {
				delete_option( $key );
			}
		}
	}
    
    /**
     * Count active reservations for user
     */
    public function count_active_reservations( $user_id = 0, $email = '' ) {
        $args = array(
            'post_type'      => 'htp_reservation',
            'post_status'    => 'publish',
            'fields'         => 'ids',
			'posts_per_page' => 1,
            'meta_query'     => array(
                array( 'key' => '_htp_status', 'value' => 'active' ),
				array( 'key' => '_htp_expires_at', 'value' => time(), 'type' => 'NUMERIC', 'compare' => '>' )
            ),
        );
        
        if ( $user_id > 0 ) {
            $args['author'] = $user_id;
        } elseif ( $email !== '' ) {
            $args['meta_query'][] = array( 'key' => '_htp_email', 'value' => $email );
        } else {
            return 0;
        }
        
		$query = new WP_Query( $args );
		return (int) $query->found_posts;
    }
    
    /**
     * Check if user has active reservation for specific product
     */
    public function user_has_active_reservation_for_product( $product_id, $user_id = 0, $email = '' ) {
        $args = array(
            'post_type'      => 'htp_reservation',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array( 'key' => '_htp_status', 'value' => 'active' ),
                array( 'key' => '_htp_product_id', 'value' => $product_id ),
				array( 'key' => '_htp_expires_at', 'value' => time(), 'type' => 'NUMERIC', 'compare' => '>' )
            ),
        );
        
        if ( $user_id > 0 ) {
            $args['author'] = $user_id;
        } elseif ( $email !== '' ) {
            $args['meta_query'][] = array( 'key' => '_htp_email', 'value' => $email );
        } else {
            return false;
        }
        
        return ! empty( get_posts( $args ) );
    }
    
    /**
     * Expire old reservations
     */
    public function expire_old_reservations() {
        $expired = get_posts( array(
            'post_type'     => 'htp_reservation',
            'post_status'   => 'publish',
            'fields'        => 'ids',
			'posts_per_page'=> 500,
			'no_found_rows' => true,
            'meta_query'    => array(
				array( 'key' => '_htp_status', 'value' => array( 'active', 'pending_approval' ), 'compare' => 'IN' ),
				array( 'key' => '_htp_expires_at', 'value' => time(), 'type' => 'NUMERIC', 'compare' => '<=' )
            ),
        ) );
        
        foreach ( $expired as $reservation_id ) {
            $this->expire_reservation( $reservation_id );
        }
        
    }
    
    /**
     * Expire a single reservation
     */
    public function expire_reservation( $reservation_id ) {
        $previous_status = get_post_meta( $reservation_id, '_htp_status', true );
		if ( ! in_array( $previous_status, array( 'active', 'pending_approval' ), true ) || ! update_post_meta( $reservation_id, '_htp_status', 'expired', $previous_status ) ) {
			return false;
		}
		update_post_meta( $reservation_id, '_htp_expired_from', $previous_status );
        
        // Get email for notification
        $email = get_post_meta( $reservation_id, '_htp_email', true );
        
        // Restore stock only if this reservation was actually holding stock.
        if ( $previous_status === 'active' ) {
            $product_id = (int) get_post_meta( $reservation_id, '_htp_product_id', true );
            if ( $product_id ) {
                $product = wc_get_product( $product_id );
                if ( $product && $product->managing_stock() ) {
					wc_update_product_stock( $product, 1, 'increase' );
                }
            }
        }
        
        // Trigger expiration email notification
        if ( $email ) {
            do_action( 'htp_reservation_expired', $reservation_id, $email );
        }
		$this->allowance_cache = array();
		return true;
    }
    
    /**
     * Cancel a reservation
     */
    public function cancel_reservation( $reservation_id ) {
        $previous_status = get_post_meta( $reservation_id, '_htp_status', true );
		if ( ! in_array( $previous_status, array( 'active', 'pending_approval' ), true ) || ! update_post_meta( $reservation_id, '_htp_status', 'cancelled', $previous_status ) ) {
			return false;
		}
        
        // Restore stock only if this reservation was actually holding stock.
        if ( $previous_status === 'active' ) {
            $product_id = (int) get_post_meta( $reservation_id, '_htp_product_id', true );
            if ( $product_id ) {
                $product = wc_get_product( $product_id );
                if ( $product && $product->managing_stock() ) {
					wc_update_product_stock( $product, 1, 'increase' );
                }
            }
        }
		$this->allowance_cache = array();
		return true;
    }
    
    /**
     * Add reservations to WooCommerce account menu
     */
    public function add_account_menu_item( $items ) {
        $new = array();
        foreach ( $items as $key => $label ) {
            if ( $key === 'customer-logout' ) {
                $new['htp-reservations'] = __( 'Reserved products', 'hold-this-product' );
            }
            $new[$key] = $label;
        }
        if ( ! isset( $new['htp-reservations'] ) ) {
            $new['htp-reservations'] = __( 'Reserved products', 'hold-this-product' );
        }
        return $new;
    }
    
    /**
     * Change endpoint title for reservations
     */
    public function reservations_endpoint_title( $title ) {
        return __( 'Reserved products', 'hold-this-product' );
    }
    
    /**
     * Change page title when on reservations page
     */
    public function change_reservations_page_title( $title ) {
        global $wp_query;
        
        if ( ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
            // Check if we're on the reservations endpoint
            if ( isset( $wp_query->query_vars['htp-reservations'] ) || 
                 ( function_exists( 'wc_get_page_id' ) && is_wc_endpoint_url( 'htp-reservations' ) ) ) {
                return __( 'Reservations', 'hold-this-product' );
            }
        }
        
        return $title;
    }
    
    /**
     * Display reservations in My Account
     */
    public function reservations_endpoint_content() {
        // Prevent double rendering
        static $rendered = false;
        if ( $rendered ) {
            return;
        }
        $rendered = true;
        
        if ( ! is_user_logged_in() ) {
            wc_print_notice( __( 'Please log in to see your reservations.', 'hold-this-product' ), 'notice' );
            return;
        }
        
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$current_page = isset( $_GET['reservation-page'] ) ? max( 1, absint( wp_unslash( $_GET['reservation-page'] ) ) ) : 1;
		$query = new WP_Query( array(
            'post_type'      => 'htp_reservation',
            'post_status'    => 'publish',
            'author'         => get_current_user_id(),
			'posts_per_page' => 20,
			'paged'          => $current_page,
            'meta_query'     => array(
                array(
                    'key'     => '_htp_status',
                    'compare' => 'EXISTS',
                ),
            ),
            'orderby' => 'date',
            'order' => 'DESC'
		) );
		$reservations = $query->posts;
        
        if ( empty( $reservations ) ) {
            wc_print_notice( __( 'You have no reservations.', 'hold-this-product' ), 'notice' );
            return;
        }
        
        // Use WooCommerce's built-in table structure for consistency
        wc_get_template( 'myaccount/my-reservations.php', array(
            'reservations' => $reservations,
			'current_page' => $current_page,
			'total_pages'  => (int) $query->max_num_pages,
        ), '', HTP_PLUGIN_PATH . 'templates/' );
    }
    
    /**
     * Handle reservation actions (cancel, etc.)
     */
    public function handle_reservation_actions() {
		if ( ! is_user_logged_in() || 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) || ! isset( $_POST['htp_cancel_res'] ) ) {
            return;
        }

		$reservation_id = absint( wp_unslash( $_POST['htp_cancel_res'] ) );
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! $reservation_id || ! wp_verify_nonce( $nonce, 'htp_cancel_res_' . $reservation_id ) ) {
            return;
        }
        
        $post = get_post( $reservation_id );
		if ( ! $post || 'htp_reservation' !== $post->post_type || (int) $post->post_author !== get_current_user_id() ) {
            return;
        }
        
        $this->cancel_reservation( $reservation_id );
        wp_safe_redirect( wc_get_account_endpoint_url( 'htp-reservations' ) );
        exit;
    }
    
    /** Give the reservation owner access to the physical unit already held for them. */
	public function include_owned_hold_in_stock( $stock, $product ) {
		if ( ! is_user_logged_in() || ! $product instanceof WC_Product || null === $stock ) {
			return $stock;
		}
		return (int) $stock + $this->get_owned_hold_allowance( $product->get_id() );
	}

	public function include_owned_hold_in_stock_status( $status, $product ) {
		if ( 'outofstock' === $status && is_user_logged_in() && $product instanceof WC_Product && $this->get_owned_hold_allowance( $product->get_id() ) > 0 ) {
			return 'instock';
		}
		return $status;
	}

	private function get_owned_hold_allowance( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! array_key_exists( $product_id, $this->allowance_cache ) ) {
			$this->allowance_cache[ $product_id ] = $this->find_active_reservation( $product_id, get_current_user_id() ) ? 1 : 0;
		}
		return $this->allowance_cache[ $product_id ];
	}

	private function find_active_reservation( $product_id, $user_id ) {
		$ids = get_posts( array(
			'post_type' => 'htp_reservation', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1,
			'author' => absint( $user_id ), 'no_found_rows' => true,
			'meta_query' => array(
				array( 'key' => '_htp_product_id', 'value' => absint( $product_id ), 'type' => 'NUMERIC' ),
				array( 'key' => '_htp_status', 'value' => 'active' ),
				array( 'key' => '_htp_expires_at', 'value' => time(), 'type' => 'NUMERIC', 'compare' => '>' ),
			),
		) );
		return $ids ? (int) $ids[0] : 0;
	}

	public function attach_reservation_to_cart_item( $cart_item_data, $product_id, $variation_id, $quantity ) {
		unset( $variation_id, $quantity );
		if ( is_user_logged_in() ) {
			$reservation_id = $this->find_active_reservation( $product_id, get_current_user_id() );
			if ( $reservation_id ) {
				$cart_item_data['_htp_reservation_id'] = $reservation_id;
			}
		}
		return $cart_item_data;
	}

	public function copy_reservation_to_order_item( $item, $cart_item_key, $values, $order ) {
		unset( $cart_item_key, $order );
		if ( ! empty( $values['_htp_reservation_id'] ) ) {
			$item->add_meta_data( '_htp_reservation_id', absint( $values['_htp_reservation_id'] ), true );
		}
	}

	/**
	 * Exclude the unit already held by this plugin from WooCommerce's order-level
	 * stock reservation and reduction calculations.
	 */
	public function exclude_linked_hold_from_order_quantity( $quantity, $order, $item ) {
		if ( ! $order instanceof WC_Order || ! $item instanceof WC_Order_Item_Product ) {
			return $quantity;
		}

		$reservation_id = absint( $item->get_meta( '_htp_reservation_id', true ) );
		if ( ! $reservation_id || ! $this->reservation_is_available_to_order( $reservation_id, $order, $item ) ) {
			return $quantity;
		}

		return max( 0, (float) $quantity - 1 );
	}

	/** Skip WooCommerce's DB hold when this plugin already holds every stock-managed unit. */
	public function skip_redundant_order_stock_hold( $minutes, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $minutes;
		}

		$has_linked_hold = false;
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product || ! $product->managing_stock() || $product->backorders_allowed() ) {
				continue;
			}

			$quantity = (float) $item->get_quantity();
			$remaining_quantity = (float) $this->exclude_linked_hold_from_order_quantity( $quantity, $order, $item );
			if ( $remaining_quantity >= $quantity || $remaining_quantity > 0 ) {
				return $minutes;
			}
			$has_linked_hold = true;
		}

		return $has_linked_hold ? 0 : $minutes;
	}

	/** Check an active hold, or the same hold after it was transferred to this order. */
	private function reservation_is_available_to_order( $reservation_id, $order, $item ) {
		if ( 'htp_reservation' !== get_post_type( $reservation_id )
			|| (int) get_post_field( 'post_author', $reservation_id ) !== (int) $order->get_customer_id()
			|| (int) get_post_meta( $reservation_id, '_htp_product_id', true ) !== (int) $item->get_product_id() ) {
			return false;
		}

		$status = get_post_meta( $reservation_id, '_htp_status', true );
		if ( 'active' === $status ) {
			return (int) get_post_meta( $reservation_id, '_htp_expires_at', true ) > time();
		}

		return 'fulfilled' === $status && (int) get_post_meta( $reservation_id, '_htp_order_id', true ) === (int) $order->get_id();
	}

	private function reservation_matches_order_item( $reservation_id, $user_id, $product_id ) {
		return 'htp_reservation' === get_post_type( $reservation_id )
			&& (int) get_post_field( 'post_author', $reservation_id ) === (int) $user_id
			&& (int) get_post_meta( $reservation_id, '_htp_product_id', true ) === (int) $product_id
			&& 'active' === get_post_meta( $reservation_id, '_htp_status', true )
			&& (int) get_post_meta( $reservation_id, '_htp_expires_at', true ) > time();
	}

	public function transfer_holds_to_order( $order ) {
		if ( ! $order instanceof WC_Order || $order->get_meta( '_htp_holds_transferred', true ) ) {
			return;
		}
		$seen = array();
		foreach ( $order->get_items() as $item ) {
			$reservation_id = absint( $item->get_meta( '_htp_reservation_id', true ) );
			if ( ! $reservation_id ) {
				continue;
			}
			if ( isset( $seen[ $reservation_id ] ) || ! $this->reservation_matches_order_item( $reservation_id, $order->get_customer_id(), $item->get_product_id() ) ) {
				throw new WC_Data_Exception( 'htp_invalid_order_hold', esc_html__( 'A reservation could not be transferred to the order.', 'hold-this-product' ) );
			}
			$seen[ $reservation_id ] = true;
		}

		$applied = array();
		try {
			foreach ( $order->get_items() as $item ) {
				$reservation_id = absint( $item->get_meta( '_htp_reservation_id', true ) );
				if ( ! $reservation_id ) {
					continue;
				}
				$quantity = max( 1, (int) $item->get_quantity() );
				$remainder = max( 0, $quantity - 1 );
				if ( $remainder ) {
					$product = $item->get_product();
					$new_stock = $product ? wc_update_product_stock( $product, $remainder, 'decrease' ) : null;
					if ( null === $new_stock || (int) $new_stock < 0 ) {
						if ( null !== $new_stock ) wc_update_product_stock( $product, $remainder, 'increase' );
						throw new WC_Data_Exception( 'htp_insufficient_order_stock', __( 'Stock changed before the reservation could be transferred.', 'hold-this-product' ) );
					}
				}
				$item->update_meta_data( '_reduced_stock', $quantity );
				$item->save();
				if ( ! update_post_meta( $reservation_id, '_htp_status', 'fulfilled', 'active' ) ) {
					if ( $remainder && $item->get_product() ) wc_update_product_stock( $item->get_product(), $remainder, 'increase' );
					$item->delete_meta_data( '_reduced_stock' );
					$item->save();
					throw new WC_Data_Exception( 'htp_transfer_race', __( 'The reservation changed during checkout.', 'hold-this-product' ) );
				}
				update_post_meta( $reservation_id, '_htp_order_id', $order->get_id() );
				$applied[] = array( $reservation_id, $item, $remainder );
			}
		} catch ( Throwable $error ) {
			foreach ( array_reverse( $applied ) as $entry ) {
				list( $reservation_id, $item, $remainder ) = $entry;
				update_post_meta( $reservation_id, '_htp_status', 'active', 'fulfilled' );
				delete_post_meta( $reservation_id, '_htp_order_id' );
				if ( $remainder && $item->get_product() ) wc_update_product_stock( $item->get_product(), $remainder, 'increase' );
				$item->delete_meta_data( '_reduced_stock' );
				$item->save();
			}
			throw $error;
		}
		if ( $seen ) {
			$order->update_meta_data( '_htp_holds_transferred', 'yes' );
			$order->save();
		}
		$this->allowance_cache = array();
	}

	public function restore_transferred_order_stock( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( '_htp_holds_transferred', true ) ) return;
		foreach ( $order->get_items() as $item ) {
			$reservation_id = absint( $item->get_meta( '_htp_reservation_id', true ) );
			if ( ! $reservation_id || ! update_post_meta( $reservation_id, '_htp_status', 'order_cancelled', 'fulfilled' ) ) continue;
			$product = $item->get_product();
			$quantity = max( 1, (int) $item->get_meta( '_reduced_stock', true ) );
			if ( $product ) wc_update_product_stock( $product, $quantity, 'increase' );
			$item->delete_meta_data( '_reduced_stock' );
			$item->save();
		}
	}

	public function hide_reservation_order_item_meta( $keys ) {
		$keys[] = '_htp_reservation_id';
		return array_unique( $keys );
	}

    /** Auto-fulfill reservations when order is completed (legacy callback). */
    public function fulfill_reservation_on_purchase( $order_id ) {
        $order = wc_get_order( $order_id );
		if ( $order ) {
			$this->transfer_holds_to_order( $order );
		}
    }
    
    /**
     * Approve a pending reservation
     */
    public function approve_reservation( $reservation_id ) {
		if ( 'htp_reservation' !== get_post_type( $reservation_id ) ) {
			return new WP_Error( 'htp_invalid_reservation', __( 'Invalid reservation.', 'hold-this-product' ) );
		}
		$product_id = (int) get_post_meta( $reservation_id, '_htp_product_id', true );
		$user_id    = (int) get_post_field( 'post_author', $reservation_id );
		$locks      = $this->acquire_locks( array( 'product_' . $product_id, 'user_' . $user_id ) );
		if ( is_wp_error( $locks ) ) {
			return $locks;
		}

		try {
			if ( 'pending_approval' !== get_post_meta( $reservation_id, '_htp_status', true ) ) {
				return new WP_Error( 'htp_not_pending', __( 'Reservation is not pending approval.', 'hold-this-product' ) );
			}
        if ( ! $product_id ) {
				return new WP_Error( 'htp_missing_product', __( 'Reservation is missing product data.', 'hold-this-product' ) );
        }

        $product = wc_get_product( $product_id );
			if ( ! $product || ! $product->is_type( 'simple' ) || ! $product->managing_stock() ) {
				return new WP_Error( 'htp_stock_unmanaged', __( 'Product stock is not managed.', 'hold-this-product' ) );
        }

			$stock_quantity = (int) $product->get_stock_quantity( 'edit' );
        if ( $stock_quantity <= 0 ) {
				return new WP_Error( 'htp_no_stock', __( 'No stock available to approve this reservation.', 'hold-this-product' ) );
        }

			$new_stock = wc_update_product_stock( $product, 1, 'decrease' );
			if ( null === $new_stock || (int) $new_stock < 0 ) {
				if ( null !== $new_stock ) {
					wc_update_product_stock( $product, 1, 'increase' );
				}
				return new WP_Error( 'htp_no_stock', __( 'No stock available to approve this reservation.', 'hold-this-product' ) );
			}
			if ( ! update_post_meta( $reservation_id, '_htp_status', 'active', 'pending_approval' ) ) {
				wc_update_product_stock( $product, 1, 'increase' );
				return new WP_Error( 'htp_approval_race', __( 'The reservation changed while it was being approved.', 'hold-this-product' ) );
			}

        // Reset the expiration window from approval time (approval may happen later).
			$options = $this->get_options();
        $duration_hours = isset( $options['reservation_duration'] ) ? absint( $options['reservation_duration'] ) : 24;
			$expires_at = time() + ( $duration_hours * HOUR_IN_SECONDS );
        update_post_meta( $reservation_id, '_htp_expires_at', $expires_at );
		} finally {
			$this->release_locks( $locks );
		}
        
        // Send confirmation email
        $email = get_post_meta( $reservation_id, '_htp_email', true );
        if ( $email ) {
            do_action( 'htp_reservation_approved', $reservation_id, $email );
        }
        
		$this->allowance_cache = array();
		return true;
    }
    
    /**
     * Deny a pending reservation
     */
    public function deny_reservation( $reservation_id, $reason = '' ) {
        $current_status = get_post_meta( $reservation_id, '_htp_status', true );
        
		if ( 'htp_reservation' !== get_post_type( $reservation_id ) || $current_status !== 'pending_approval' ) {
            return false;
        }
        
        // Update status to denied
		if ( ! update_post_meta( $reservation_id, '_htp_status', 'denied', 'pending_approval' ) ) {
			return false;
		}
        
        // Store denial reason if provided
        if ( $reason ) {
            update_post_meta( $reservation_id, '_htp_denial_reason', sanitize_text_field( $reason ) );
        }
        
        // Send denial email
        $email = get_post_meta( $reservation_id, '_htp_email', true );
        if ( $email ) {
            do_action( 'htp_reservation_denied', $reservation_id, $email, $reason );
        }
        
        return true;
    }

    /**
     * Count "open" reservations for a user (active + pending approval).
     *
     * Pending approvals count towards limits to prevent spamming requests.
     */
    public function count_open_reservations( $user_id = 0 ) {
        if ( $user_id <= 0 ) {
            return 0;
        }

		$query = new WP_Query( array(
            'post_type'      => 'htp_reservation',
            'post_status'    => 'publish',
            'fields'         => 'ids',
			'posts_per_page' => 1,
            'author'         => $user_id,
            'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => '_htp_status', 'value' => 'pending_approval' ),
				array(
					'relation' => 'AND',
					array( 'key' => '_htp_status', 'value' => 'active' ),
					array( 'key' => '_htp_expires_at', 'value' => time(), 'type' => 'NUMERIC', 'compare' => '>' ),
				),
            ),
		) );
		return (int) $query->found_posts;
    }

    /**
     * Check if a user already has an open reservation request for a product (active + pending approval).
     */
    public function user_has_open_reservation_for_product( $product_id, $user_id = 0 ) {
        $product_id = absint( $product_id );
        $user_id = absint( $user_id );
        if ( ! $product_id || ! $user_id ) {
            return false;
        }

        $ids = get_posts( array(
            'post_type'      => 'htp_reservation',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => 10,
            'author'         => $user_id,
            'meta_query'     => array(
                array( 'key' => '_htp_product_id', 'value' => $product_id ),
                array( 'key' => '_htp_status', 'value' => array( 'active', 'pending_approval' ), 'compare' => 'IN' ),
            ),
        ) );

        if ( empty( $ids ) ) {
            return false;
        }

		$now = time();
        foreach ( $ids as $reservation_id ) {
            $status = get_post_meta( $reservation_id, '_htp_status', true );
            if ( $status === 'pending_approval' ) {
				if ( (int) get_post_meta( $reservation_id, '_htp_expires_at', true ) > $now ) {
					return true;
				}
				continue;
            }

            $expires = (int) get_post_meta( $reservation_id, '_htp_expires_at', true );
            if ( $expires > $now ) {
                return true;
            }
        }

        return false;
    }

	public function register_privacy_exporter( $exporters ) {
		$exporters['hold-this-product'] = array(
			'exporter_friendly_name' => __( 'Hold This Product reservations', 'hold-this-product' ),
			'callback' => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	public function register_privacy_eraser( $erasers ) {
		$erasers['hold-this-product'] = array(
			'eraser_friendly_name' => __( 'Hold This Product reservations', 'hold-this-product' ),
			'callback' => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	private function find_privacy_reservations( $email_address, $page ) {
		$user = get_user_by( 'email', $email_address );
		$args = array(
			'post_type' => 'htp_reservation', 'post_status' => 'publish', 'fields' => 'ids',
			'posts_per_page' => 100, 'paged' => max( 1, absint( $page ) ), 'orderby' => 'ID', 'order' => 'ASC',
		);
		if ( $user ) {
			$args['author'] = $user->ID;
		} else {
			$args['meta_query'] = array( array( 'key' => '_htp_email', 'value' => sanitize_email( $email_address ) ) );
		}
		return get_posts( $args );
	}

	public function export_personal_data( $email_address, $page = 1 ) {
		$ids = $this->find_privacy_reservations( $email_address, $page );
		$data = array();
		foreach ( $ids as $reservation_id ) {
			$data[] = array(
				'group_id' => 'hold-this-product-reservations',
				'group_label' => __( 'Product reservations', 'hold-this-product' ),
				'item_id' => 'htp-reservation-' . $reservation_id,
				'data' => array(
					array( 'name' => __( 'Product ID', 'hold-this-product' ), 'value' => (int) get_post_meta( $reservation_id, '_htp_product_id', true ) ),
					array( 'name' => __( 'Status', 'hold-this-product' ), 'value' => sanitize_text_field( get_post_meta( $reservation_id, '_htp_status', true ) ) ),
					array( 'name' => __( 'Email', 'hold-this-product' ), 'value' => sanitize_email( get_post_meta( $reservation_id, '_htp_email', true ) ) ),
					array( 'name' => __( 'Expires', 'hold-this-product' ), 'value' => wp_date( DATE_ATOM, (int) get_post_meta( $reservation_id, '_htp_expires_at', true ) ) ),
				),
			);
		}
		return array( 'data' => $data, 'done' => count( $ids ) < 100 );
	}

	public function erase_personal_data( $email_address, $page = 1 ) {
		$ids = $this->find_privacy_reservations( $email_address, $page );
		$removed = false;
		$retained = false;
		foreach ( $ids as $reservation_id ) {
			if ( in_array( get_post_meta( $reservation_id, '_htp_status', true ), array( 'active', 'pending_approval' ), true ) ) {
				$retained = true;
				continue;
			}
			update_post_meta( $reservation_id, '_htp_email', wp_privacy_anonymize_data( 'email', $email_address ) );
			wp_update_post( array( 'ID' => $reservation_id, 'post_author' => 0 ) );
			$removed = true;
		}
		return array(
			'items_removed' => $removed,
			'items_retained' => $retained,
			'messages' => $retained ? array( __( 'Open reservations were retained until their inventory obligation ends.', 'hold-this-product' ) ) : array(),
			'done' => count( $ids ) < 100,
		);
	}
}
