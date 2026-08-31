<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$context = get_option( 'htp_stress_context', array() );
if ( ! empty( $context['product_id'] ) ) {
	$reservation_ids = get_posts(
		array(
			'post_type'      => 'htp_reservation',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => HTP_Reservation_Meta::PRODUCT_ID,
			'meta_value'     => (int) $context['product_id'],
		)
	);
	foreach ( $reservation_ids as $reservation_id ) {
		wp_delete_post( $reservation_id, true );
	}
	wp_delete_post( (int) $context['product_id'], true );
}

require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( 'holder_id', 'contender_id' ) as $user_key ) {
	if ( ! empty( $context[ $user_key ] ) ) {
		wp_delete_user( (int) $context[ $user_key ] );
	}
}

if ( array_key_exists( 'options', $context ) ) {
	if ( '__htp_missing_option__' === $context['options'] ) {
		delete_option( 'holdthisproduct_options' );
	} else {
		update_option( 'holdthisproduct_options', $context['options'] );
	}
}

delete_option( 'htp_stress_context' );
delete_option( 'htp_stress_result_holder' );
delete_option( 'htp_stress_result_contender' );
