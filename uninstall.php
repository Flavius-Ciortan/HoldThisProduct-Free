<?php
/** Remove plugin data and release any outstanding stock holds. */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function hold_this_product_uninstall_site_data() {
	do {
		$ids = get_posts( array(
			'post_type' => 'htp_reservation', 'post_status' => 'any', 'fields' => 'ids',
			'posts_per_page' => 200, 'no_found_rows' => true,
		) );
		foreach ( $ids as $reservation_id ) {
			if ( 'active' === get_post_meta( $reservation_id, '_htp_status', true ) && function_exists( 'wc_update_product_stock' ) ) {
				$product = wc_get_product( (int) get_post_meta( $reservation_id, '_htp_product_id', true ) );
				if ( $product ) {
					wc_update_product_stock( $product, 1, 'increase' );
				}
			}
			wp_delete_post( $reservation_id, true );
		}
	} while ( count( $ids ) === 200 );
	delete_option( 'holdthisproduct_options' );
	wp_clear_scheduled_hook( 'htp_expire_reservations' );
}

if ( is_multisite() ) {
	$hold_this_product_site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $hold_this_product_site_ids as $hold_this_product_site_id ) {
		switch_to_blog( $hold_this_product_site_id );
		hold_this_product_uninstall_site_data();
		restore_current_blog();
	}
} else {
	hold_this_product_uninstall_site_data();
}
