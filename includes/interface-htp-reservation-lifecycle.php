<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stable reservation transition contract for compatible add-ons. */
interface HTP_Reservation_Lifecycle_Interface {
	public function request( $product_id, $user_id );

	public function create( $product_id, $user_id = 0, $guest_email = '' );

	public function approve( $reservation_id );

	public function deny( $reservation_id, $reason = '' );

	public function cancel( $reservation_id );
}
