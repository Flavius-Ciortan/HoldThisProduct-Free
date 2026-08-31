<?php
/** Remove plugin data and release any outstanding stock holds. */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-htp-reservation-status.php';
require_once __DIR__ . '/includes/class-htp-reservation-meta.php';

function hold_this_product_uninstall_site_data() {
	do {
		$ids = get_posts(
			array(
				'post_type'      => 'htp_reservation',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 200,
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $reservation_id ) {
			$status          = HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::STATUS );
			$inventory_state = HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::INVENTORY_STATE );
			$owns_stock      = 'held' === $inventory_state || ( ! $inventory_state && HTP_Reservation_Status::ACTIVE === $status );
			if ( $owns_stock && function_exists( 'wc_update_product_stock' ) ) {
				$product = wc_get_product( (int) HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::PRODUCT_ID ) );
				if ( $product ) {
					$quantity = max( 1, (int) HTP_Reservation_Meta::get( $reservation_id, HTP_Reservation_Meta::QUANTITY ) );
					wc_update_product_stock( $product, $quantity, 'increase' );
				}
			}
			wp_delete_post( $reservation_id, true );
		}
	} while ( count( $ids ) === 200 );
	delete_option( 'holdthisproduct_options' );
	delete_option( 'htp_version' );
	delete_option( 'htp_inventory_state_version' );

	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall must remove unknown dynamic lock keys and does not run during a cached request.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'htp_lock_' ) . '%' ) );
	wp_clear_scheduled_hook( 'htp_expire_reservations' );
}

if ( is_multisite() ) {
	$hold_this_product_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $hold_this_product_site_ids as $hold_this_product_site_id ) {
		switch_to_blog( $hold_this_product_site_id );
		hold_this_product_uninstall_site_data();
		restore_current_blog();
	}
} else {
	hold_this_product_uninstall_site_data();
}
