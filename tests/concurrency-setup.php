<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

delete_option( 'htp_stress_context' );
delete_option( 'htp_stress_result_holder' );
delete_option( 'htp_stress_result_contender' );
$original_options = get_option( 'holdthisproduct_options', '__htp_missing_option__' );

$product = new WC_Product_Simple();
$product->set_name( 'HTP concurrency stress product' );
$product->set_status( 'publish' );
$product->set_regular_price( '10' );
$product->set_manage_stock( true );
$product->set_stock_quantity( 1 );
$product_id = $product->save();

$holder_id = wp_insert_user(
	array(
		'user_login' => 'htp-stress-holder-' . wp_generate_password( 8, false ),
		'user_pass'  => wp_generate_password( 24 ),
		'user_email' => 'htp-stress-holder@example.test',
		'role'       => 'customer',
	)
);
$contender_id = wp_insert_user(
	array(
		'user_login' => 'htp-stress-contender-' . wp_generate_password( 8, false ),
		'user_pass'  => wp_generate_password( 24 ),
		'user_email' => 'htp-stress-contender@example.test',
		'role'       => 'customer',
	)
);

if ( ! $product_id || is_wp_error( $holder_id ) || is_wp_error( $contender_id ) ) {
	exit( 1 );
}

update_option(
	'holdthisproduct_options',
	array(
		'enable_reservation'         => 1,
		'max_reservations'           => 5,
		'reservation_duration'       => 24,
		'pending_duration'           => 1,
		'require_admin_approval'     => 0,
		'enable_email_notifications' => 0,
	)
);
update_option(
	'htp_stress_context',
	array(
		'product_id'   => $product_id,
		'holder_id'    => $holder_id,
		'contender_id' => $contender_id,
		'options'      => $original_options,
	),
	false
);

echo 'PRODUCT_ID=' . (int) $product_id . "\n";
