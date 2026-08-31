<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Canonical reservation metadata keys and accessors. */
final class HTP_Reservation_Meta {
	const PRODUCT_ID         = '_htp_product_id';
	const STATUS             = '_htp_status';
	const EXPIRES_AT         = '_htp_expires_at';
	const QUANTITY           = '_htp_qty';
	const EMAIL              = '_htp_email';
	const NAME               = '_htp_name';
	const SURNAME            = '_htp_surname';
	const TIMESTAMP_MODEL    = '_htp_timestamp_model';
	const INVENTORY_STATE    = '_htp_inventory_state';
	const ORDER_ID           = '_htp_order_id';
	const EXPIRED_FROM       = '_htp_expired_from';
	const DENIAL_REASON      = '_htp_denial_reason';
	const CANCELLED_BY_ADMIN = '_htp_cancelled_by_admin';
	const CANCELLED_BY_USER  = '_htp_cancelled_by_user';
	const LINKED_RESERVATION = '_htp_reservation_id';
	const HOLDS_TRANSFERRED  = '_htp_holds_transferred';

	public static function get( $reservation_id, $key, $single = true ) {
		return get_post_meta( absint( $reservation_id ), $key, $single );
	}

	public static function update( $reservation_id, $key, $value, $previous_value = '' ) {
		return update_post_meta( absint( $reservation_id ), $key, $value, $previous_value );
	}

	public static function delete( $reservation_id, $key ) {
		return delete_post_meta( absint( $reservation_id ), $key );
	}
}
