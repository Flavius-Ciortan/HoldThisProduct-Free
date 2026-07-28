<?php
if ( ! defined( 'HTP_INTEGRATION_TEST' ) ) {
	exit;
}

require '/wordpress/wp-load.php';

$htp_failures = array();
function htp_assert( $condition, $message ) {
	global $htp_failures;
	if ( ! $condition ) {
		$htp_failures[] = $message;
		echo esc_html( "FAIL: {$message}\n" );
	} else {
		echo esc_html( "PASS: {$message}\n" );
	}
}

$htp_plugin = HoldThisProduct::get_instance();
htp_assert( $htp_plugin->reservations instanceof HTP_Reservations, 'Reservation service initialized.' );

require_once HTP_PLUGIN_PATH . 'includes/admin/class-htp-admin-reservations.php';
require_once HTP_PLUGIN_PATH . 'includes/admin/class-htp-admin.php';
$htp_admin = new HTP_Admin( $htp_plugin->reservations );
$htp_sanitized = $htp_admin->sanitize_options( array( 'max_reservations' => 999, 'reservation_duration' => -2, 'popup_customization_logged_in' => array( 'font_family' => 'Arial;background:url(x)', 'background_color' => 'bad' ) ) );
htp_assert( 100 === $htp_sanitized['max_reservations'], 'Reservation limit is bounded.' );
htp_assert( 1 === $htp_sanitized['reservation_duration'], 'Duration is bounded.' );
htp_assert( 'Arial, Helvetica, sans-serif' === $htp_sanitized['popup_customization_logged_in']['font_family'], 'Font value is allowlisted.' );

update_option( 'holdthisproduct_options', array( 'enable_reservation' => 1, 'max_reservations' => 3, 'reservation_duration' => 24, 'pending_duration' => 1, 'require_admin_approval' => 1, 'enable_email_notifications' => 0 ) );
$htp_user_id = wp_insert_user( array( 'user_login' => 'htp-test-user', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'htp@example.test', 'role' => 'customer' ) );
wp_set_current_user( $htp_user_id );
$htp_product = new WC_Product_Simple();
$htp_product->set_name( 'Reservation test product' );
$htp_product->set_status( 'publish' );
$htp_product->set_regular_price( '10' );
$htp_product->set_manage_stock( true );
$htp_product->set_stock_quantity( 1 );
$htp_product_id = $htp_product->save();

function htp_test_reservation( $product_id, $user_id, $status, $expires ) {
	$id = wp_insert_post( array( 'post_type' => 'htp_reservation', 'post_status' => 'publish', 'post_author' => $user_id, 'post_title' => 'Test reservation' ) );
	update_post_meta( $id, '_htp_product_id', $product_id );
	update_post_meta( $id, '_htp_status', $status );
	update_post_meta( $id, '_htp_expires_at', $expires );
	update_post_meta( $id, '_htp_qty', 1 );
	update_post_meta( $id, '_htp_email', 'htp@example.test' );
	return $id;
}

$htp_pending_id = htp_test_reservation( $htp_product_id, $htp_user_id, 'pending_approval', time() + HOUR_IN_SECONDS );
htp_assert( true === $htp_plugin->reservations->approve_reservation( $htp_pending_id ), 'Pending reservation approves.' );
$htp_product = wc_get_product( $htp_product_id );
htp_assert( 0 === (int) $htp_product->get_stock_quantity( 'edit' ), 'Approval holds physical stock once.' );
htp_assert( 1 === (int) $htp_product->get_stock_quantity(), 'Owner can purchase the held last unit.' );
htp_assert( is_wp_error( $htp_plugin->reservations->approve_reservation( $htp_pending_id ) ), 'Repeated approval is rejected.' );
htp_assert( true === $htp_plugin->reservations->cancel_reservation( $htp_pending_id ), 'Active reservation cancels.' );
htp_assert( false === $htp_plugin->reservations->cancel_reservation( $htp_pending_id ), 'Repeated cancellation is rejected.' );
$htp_product = wc_get_product( $htp_product_id );
htp_assert( 1 === (int) $htp_product->get_stock_quantity( 'edit' ), 'Cancellation restores stock exactly once.' );

$htp_expired_pending = htp_test_reservation( $htp_product_id, $htp_user_id, 'pending_approval', time() - 1 );
$htp_plugin->reservations->expire_old_reservations();
htp_assert( 'expired' === get_post_meta( $htp_expired_pending, '_htp_status', true ), 'Pending requests expire.' );
htp_assert( 1 === (int) wc_get_product( $htp_product_id )->get_stock_quantity( 'edit' ), 'Pending expiry does not change stock.' );

$htp_active_id = htp_test_reservation( $htp_product_id, $htp_user_id, 'active', time() + HOUR_IN_SECONDS );
wc_update_product_stock( wc_get_product( $htp_product_id ), 1, 'decrease' );
$htp_order = wc_create_order( array( 'customer_id' => $htp_user_id ) );
$htp_item_id = $htp_order->add_product( wc_get_product( $htp_product_id ), 1 );
$htp_item = $htp_order->get_item( $htp_item_id );
$htp_item->add_meta_data( '_htp_reservation_id', $htp_active_id, true );
$htp_item->save();
$htp_order->save();
$htp_reserve_stock_succeeded = true;
$htp_reserve_stock_message = '';
try {
	wc_reserve_stock_for_order( $htp_order );
} catch ( Throwable $htp_stock_error ) {
	$htp_reserve_stock_succeeded = false;
	$htp_reserve_stock_message = $htp_stock_error->getMessage();
}
htp_assert( $htp_reserve_stock_succeeded, 'WooCommerce checkout can reserve stock when the last unit is already held. ' . $htp_reserve_stock_message );
do_action( 'woocommerce_store_api_checkout_order_processed', $htp_order );
htp_assert( 'fulfilled' === get_post_meta( $htp_active_id, '_htp_status', true ), 'Order fulfills the exact linked reservation.' );
htp_assert( 0 === (int) wc_get_product( $htp_product_id )->get_stock_quantity( 'edit' ), 'Checkout does not decrement the held unit twice.' );
$htp_order->update_status( 'cancelled' );
htp_assert( 1 === (int) wc_get_product( $htp_product_id )->get_stock_quantity( 'edit' ), 'Cancelled order restores stock exactly once.' );
$htp_plugin->reservations->restore_transferred_order_stock( $htp_order->get_id() );
htp_assert( 1 === (int) wc_get_product( $htp_product_id )->get_stock_quantity( 'edit' ), 'Repeated order restoration is idempotent.' );

wp_delete_post( $htp_pending_id, true );
wp_delete_post( $htp_expired_pending, true );
wp_delete_post( $htp_active_id, true );
wp_delete_post( $htp_product_id, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $htp_user_id );
if ( $htp_failures ) exit( 1 );
echo esc_html( "All integration assertions passed.\n" );
