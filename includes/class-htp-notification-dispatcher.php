<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Dispatches lifecycle notifications through legacy and generic event contracts. */
final class HTP_Notification_Dispatcher {
	private $legacy_hooks = array(
		'created'  => 'htp_reservation_created',
		'pending'  => 'htp_reservation_pending_approval',
		'approved' => 'htp_reservation_approved',
		'expired'  => 'htp_reservation_expired',
		'denied'   => 'htp_reservation_denied',
	);

	public function dispatch( $event, $reservation_id, $email, $context = array() ) {
		$event          = sanitize_key( $event );
		$reservation_id = absint( $reservation_id );
		$email          = sanitize_email( $email );
		$context        = is_array( $context ) ? $context : array();
		if ( ! $reservation_id || ! $email || ! isset( $this->legacy_hooks[ $event ] ) ) {
			return false;
		}

		if ( 'denied' === $event ) {
			do_action( $this->legacy_hooks[ $event ], $reservation_id, $email, isset( $context['reason'] ) ? $context['reason'] : '' );
		} else {
			do_action( $this->legacy_hooks[ $event ], $reservation_id, $email );
		}
		do_action( 'htp_reservation_event', $event, $reservation_id, $email, $context );
		return true;
	}
}
