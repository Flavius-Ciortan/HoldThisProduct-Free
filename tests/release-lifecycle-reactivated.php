<?php

if ( ! defined( 'ABSPATH' ) || '1' !== getenv( 'HTP_ALLOW_DESTRUCTIVE_RELEASE_TEST' ) ) {
	exit( 1 );
}

$product_id     = (int) getenv( 'HTP_RELEASE_PRODUCT_ID' );
$reservation_id = (int) getenv( 'HTP_RELEASE_RESERVATION_ID' );
$product        = wc_get_product( $product_id );
$plugin         = HoldThisProduct::get_instance();
$valid          = $plugin->get_service( 'lifecycle' ) instanceof HTP_Reservation_Lifecycle_Interface
	&& $product
	&& 'htp_reservation' === get_post_type( $reservation_id )
	&& 1 === (int) $product->get_stock_quantity( 'edit' )
	&& (bool) wp_next_scheduled( HTP_Reservations::CRON_HOOK );

if ( ! $valid ) {
	exit( 1 );
}

$original_timezone = get_option( 'timezone_string', '' );
$original_offset   = get_option( 'gmt_offset', 0 );
update_option( 'timezone_string', 'Europe/Bucharest' );
update_option( 'gmt_offset', 3 );
$offset         = wp_timezone()->getOffset( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
$legacy_expires = time() + HOUR_IN_SECONDS + $offset;
update_post_meta( $reservation_id, HTP_Reservation_Meta::EXPIRES_AT, $legacy_expires );
delete_post_meta( $reservation_id, HTP_Reservation_Meta::TIMESTAMP_MODEL );
// Simulate a pre-release install so the 1.0.0 migration is exercised.
update_option( 'htp_version', '0.9.0', false );
// Replaying global admin hooks in WP-CLI also re-runs unrelated plugin callbacks.
$plugin->maybe_upgrade();
$plugin->maybe_migrate_inventory_states();

$migrated_expires = (int) get_post_meta( $reservation_id, HTP_Reservation_Meta::EXPIRES_AT, true );
$migration_valid  = 'utc' === get_post_meta( $reservation_id, HTP_Reservation_Meta::TIMESTAMP_MODEL, true )
	&& HTP_VERSION === get_option( 'htp_version' )
	&& abs( $migrated_expires - ( time() + HOUR_IN_SECONDS ) ) <= 5;

update_option( 'timezone_string', $original_timezone );
update_option( 'gmt_offset', $original_offset );

if ( ! $migration_valid ) {
	exit( 1 );
}

echo "PASS: Reactivation preserves data and the bounded legacy timestamp upgrade completes.\n";
