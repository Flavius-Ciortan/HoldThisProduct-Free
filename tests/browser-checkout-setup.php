<?php

if ( ! defined( 'ABSPATH' ) || '1' !== getenv( 'HTP_BROWSER_CHECKOUT_TEST' ) ) {
	exit( 1 );
}

foreach ( array( 'htp-browser-checkout', 'htp-browser-manager', 'htp-browser-admin' ) as $fixture_login ) {
	$existing = get_user_by( 'login', $fixture_login );
	if ( $existing ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $existing->ID );
	}
}

$original_options     = get_option( 'holdthisproduct_options', '__htp_missing_option__' );
$original_checkout    = get_option( 'woocommerce_checkout_page_id', 0 );
$original_cod         = get_option( 'woocommerce_cod_settings', '__htp_missing_option__' );
$original_coming_soon = get_option( 'woocommerce_coming_soon', '__htp_missing_option__' );
$original_store_pages = get_option( 'woocommerce_store_pages_only', '__htp_missing_option__' );
$user_id              = wp_insert_user(
	array(
		'user_login'   => 'htp-browser-checkout',
		'user_pass'    => 'htp-browser-local-only',
		'user_email'   => 'htp-browser-checkout@example.test',
		'display_name' => 'HTP Browser Checkout',
		'role'         => 'customer',
	)
);
if ( is_wp_error( $user_id ) ) {
	exit( 1 );
}

$shop_manager_id  = wp_insert_user(
	array(
		'user_login'   => 'htp-browser-manager',
		'user_pass'    => 'htp-browser-local-only',
		'user_email'   => 'htp-browser-manager@example.test',
		'display_name' => 'HTP Browser Manager',
		'role'         => 'shop_manager',
	)
);
$administrator_id = wp_insert_user(
	array(
		'user_login'   => 'htp-browser-admin',
		'user_pass'    => 'htp-browser-local-only',
		'user_email'   => 'htp-browser-admin@example.test',
		'display_name' => 'HTP Browser Administrator',
		'role'         => 'administrator',
	)
);
if ( is_wp_error( $shop_manager_id ) || is_wp_error( $administrator_id ) ) {
	exit( 1 );
}

$address = array(
	'billing_first_name' => 'Browser',
	'billing_last_name'  => 'Tester',
	'billing_address_1'  => '1 Local Test Street',
	'billing_city'       => 'Bucharest',
	'billing_postcode'   => '010101',
	'billing_country'    => 'RO',
	'billing_email'      => 'htp-browser-checkout@example.test',
);
foreach ( $address as $key => $value ) {
	update_user_meta( $user_id, $key, $value );
}

$product_ids = array();
foreach ( array(
	'classic' => 'HTP Classic Checkout Product',
	'blocks'  => 'HTP Blocks Checkout Product',
) as $key => $name ) {
	$product = new WC_Product_Simple();
	$product->set_name( $name );
	$product->set_status( 'publish' );
	$product->set_regular_price( '10' );
	$product->set_virtual( true );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( 2 );
	$product_ids[ $key ] = $product->save();
}

$classic_page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'HTP Classic Checkout',
		'post_name'    => 'htp-classic-checkout',
		'post_content' => '[woocommerce_checkout]',
	),
	true
);
if ( is_wp_error( $classic_page_id ) || ! $product_ids['classic'] || ! $product_ids['blocks'] ) {
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
	),
	false
);
$cod_settings            = is_array( $original_cod ) ? $original_cod : array();
$cod_settings['enabled'] = 'yes';
update_option( 'woocommerce_cod_settings', $cod_settings );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );

$context = array(
	'user_id'              => $user_id,
	'shop_manager_id'      => $shop_manager_id,
	'administrator_id'     => $administrator_id,
	'classic_product'      => $product_ids['classic'],
	'blocks_product'       => $product_ids['blocks'],
	'classic_page'         => $classic_page_id,
	'blocks_page'          => $original_checkout,
	'original_options'     => $original_options,
	'original_checkout'    => $original_checkout,
	'original_cod'         => $original_cod,
	'original_coming_soon' => $original_coming_soon,
	'original_store_pages' => $original_store_pages,
);
update_option( 'htp_browser_checkout_context', $context, false );

echo wp_json_encode(
	array(
		'user_login'       => 'htp-browser-checkout',
		'user_password'    => 'htp-browser-local-only',
		'manager_login'    => 'htp-browser-manager',
		'admin_login'      => 'htp-browser-admin',
		'classic_product'  => get_permalink( $product_ids['classic'] ),
		'blocks_product'   => get_permalink( $product_ids['blocks'] ),
		'classic_checkout' => get_permalink( $classic_page_id ),
		'blocks_checkout'  => get_permalink( $original_checkout ),
	)
) . "\n";
