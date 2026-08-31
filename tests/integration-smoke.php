<?php
if ( ! defined( 'HTP_INTEGRATION_TEST' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
	if ( ! file_exists( $wp_load ) ) {
		$wp_load = '/wordpress/wp-load.php';
	}
	require $wp_load;
}

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
$htp_original_options = get_option( 'holdthisproduct_options', false );
htp_assert( $htp_plugin->reservations instanceof HTP_Reservations, 'Reservation service initialized.' );
htp_assert( $htp_plugin->get_service( 'repository' ) instanceof HTP_Reservation_Repository, 'Reservation repository is registered.' );
htp_assert( $htp_plugin->get_service( 'cart_order' ) instanceof HTP_Cart_Order_Service, 'Cart and order service is registered.' );
htp_assert( $htp_plugin->get_service( 'expiration' ) instanceof HTP_Expiration_Service, 'Expiration service is registered.' );
htp_assert( $htp_plugin->get_service( 'repository' ) instanceof HTP_Reservation_Repository_Interface, 'Repository implements its extension contract.' );
htp_assert( $htp_plugin->get_service( 'lifecycle' ) instanceof HTP_Reservation_Lifecycle_Interface, 'Lifecycle implements its extension contract.' );
htp_assert( $htp_plugin->get_service( 'rules' ) instanceof HTP_Reservation_Rules, 'Reservation rules service is registered.' );
htp_assert( $htp_plugin->get_service( 'locks' ) instanceof HTP_Lock_Manager, 'Reservation lock service is registered.' );
$htp_dependency_notices = $htp_plugin->get_service( 'dependency_notices' );
htp_assert( $htp_dependency_notices instanceof HTP_Dependency_Notices, 'Add-on dependency notice service is registered.' );
htp_assert( $htp_dependency_notices->add( 'htp-contract-test', 'Dependency contract test.', 'warning' ) && isset( $htp_dependency_notices->all()['htp-contract-test'] ), 'Add-ons can register a dependency notice through the shared contract.' );
$htp_dependency_notices->remove( 'htp-contract-test' );
htp_assert( post_type_exists( 'htp_reservation' ), 'Reservation post type is registered during normal bootstrap.' );

wp_clear_scheduled_hook( HTP_Reservations::CRON_HOOK );
htp_assert( ! wp_next_scheduled( HTP_Reservations::CRON_HOOK ), 'Expiration schedule can be removed for recovery test.' );
$htp_plugin->reservations->schedule_expiration();
htp_assert( (bool) wp_next_scheduled( HTP_Reservations::CRON_HOOK ), 'Missing expiration schedule is recreated.' );

require_once HTP_PLUGIN_PATH . 'includes/admin/class-htp-admin-reservations.php';
require_once HTP_PLUGIN_PATH . 'includes/admin/class-htp-admin.php';
$htp_admin = new HTP_Admin( $htp_plugin->reservations );
$htp_admin_reservations = new HTP_Admin_Reservations( $htp_plugin->reservations );
$htp_admin_reservations->enqueue_assets();
htp_assert( wp_script_is( 'holdthisproduct-admin-reservations', 'enqueued' ), 'Reservation admin actions use a versioned external asset.' );
htp_assert( false !== strpos( (string) wp_scripts()->get_data( 'holdthisproduct-admin-reservations', 'data' ), 'htpReservationsAdmin' ), 'Reservation admin asset receives localized nonces and messages.' );
$htp_admin->enqueue_admin_scripts( 'toplevel_page_holdthisproduct-settings' );
htp_assert( wp_script_is( 'holdthisproduct-admin-settings', 'enqueued' ), 'Settings interactions use a versioned external asset.' );
$htp_filtered_method = new ReflectionMethod( $htp_admin_reservations, 'get_filtered_reservations' );
$htp_invalid_product_query = $htp_filtered_method->invoke( $htp_admin_reservations, 'all', 'not-a-number', 'product_id', 1 );
$htp_missing_product_query = $htp_filtered_method->invoke( $htp_admin_reservations, 'all', 'HTP product that cannot exist 19f546ef', 'product', 1 );
htp_assert( $htp_invalid_product_query instanceof WP_Query && 0 === (int) $htp_invalid_product_query->found_posts, 'Invalid product ID search returns an empty WP_Query.' );
htp_assert( $htp_missing_product_query instanceof WP_Query && 0 === (int) $htp_missing_product_query->found_posts, 'Missing product search returns an empty WP_Query.' );
htp_assert( get_role( 'shop_manager' ) && get_role( 'shop_manager' )->has_cap( htp_get_manage_capability() ), 'Shop Managers have the reservation management capability.' );
$htp_sanitized = $htp_admin->sanitize_options( array( 'max_reservations' => 999, 'reservation_duration' => -2, 'popup_customization_logged_in' => array( 'font_family' => 'Arial;background:url(x)', 'background_color' => 'bad' ) ) );
htp_assert( 100 === $htp_sanitized['max_reservations'], 'Reservation limit is bounded.' );
htp_assert( 1 === $htp_sanitized['reservation_duration'], 'Duration is bounded.' );
htp_assert( 'Arial, Helvetica, sans-serif' === $htp_sanitized['popup_customization_logged_in']['font_family'], 'Font value is allowlisted.' );
$htp_sanitized = $htp_admin->sanitize_options( array(
	'max_reservations' => 0,
	'reservation_duration' => 999,
	'popup_customization_logged_in' => array(
		'border_radius' => -10,
		'font_size' => 999,
		'background_color' => 'invalid',
		'text_color' => '#123456',
	),
) );
htp_assert( 1 === $htp_sanitized['max_reservations'], 'Zero reservation limit is raised to one.' );
htp_assert( 168 === $htp_sanitized['reservation_duration'], 'Excessive duration is capped.' );
htp_assert( 0 === $htp_sanitized['popup_customization_logged_in']['border_radius'], 'Negative border radius is raised to zero.' );
htp_assert( 40 === $htp_sanitized['popup_customization_logged_in']['font_size'], 'Excessive font size is capped.' );
htp_assert( '#ffffff' === $htp_sanitized['popup_customization_logged_in']['background_color'], 'Invalid popup color uses the default.' );
htp_assert( ! empty( get_settings_errors( 'holdthisproduct_options' ) ), 'Corrected settings produce validation feedback.' );

update_option( 'holdthisproduct_options', array( 'enable_reservation' => 1, 'max_reservations' => 3, 'reservation_duration' => 24, 'pending_duration' => 1, 'require_admin_approval' => 1, 'enable_email_notifications' => 0 ) );
$htp_preserved = $htp_admin->sanitize_options( array( 'max_reservations' => 3, 'reservation_duration' => 12 ) );
htp_assert( 1 === $htp_preserved['pending_duration'], 'Hidden pending duration is preserved when settings are saved.' );
$htp_user_id = wp_insert_user( array( 'user_login' => 'htp-test-user', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'htp@example.test', 'role' => 'customer' ) );
wp_set_current_user( $htp_user_id );
$htp_product = new WC_Product_Simple();
$htp_product->set_name( 'Reservation test product' );
$htp_product->set_status( 'publish' );
$htp_product->set_regular_price( '10' );
$htp_product->set_manage_stock( true );
$htp_product->set_stock_quantity( 1 );
$htp_product_id = $htp_product->save();

$htp_immediate_product = new WC_Product_Simple();
$htp_immediate_product->set_name( 'Immediate reservation test product' );
$htp_immediate_product->set_status( 'publish' );
$htp_immediate_product->set_regular_price( '10' );
$htp_immediate_product->set_manage_stock( true );
$htp_immediate_product->set_stock_quantity( 2 );
$htp_immediate_product_id = $htp_immediate_product->save();
$htp_original_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
$GLOBALS['post'] = get_post( $htp_immediate_product_id );
$htp_admin->enqueue_admin_scripts( 'post.php' );
htp_assert( wp_script_is( 'holdthisproduct-admin-product', 'enqueued' ), 'Product reservation actions use a versioned external asset.' );
htp_assert( false !== strpos( (string) wp_scripts()->get_data( 'holdthisproduct-admin-product', 'data' ), 'htpProductReservations' ), 'Product admin asset receives its localized nonce and messages.' );
$GLOBALS['post'] = $htp_original_post;
update_option( 'holdthisproduct_options', array( 'enable_reservation' => 1, 'max_reservations' => 3, 'reservation_duration' => 24, 'pending_duration' => 1, 'require_admin_approval' => 0, 'enable_email_notifications' => 0 ) );
$htp_eligibility_passthrough = static function ( $reservable ) {
	return $reservable;
};
$htp_reservable_before_filter = $htp_plugin->reservations->is_product_reservable( $htp_immediate_product_id );
add_filter( 'htp_product_is_reservable', $htp_eligibility_passthrough );
htp_assert( $htp_reservable_before_filter === $htp_plugin->reservations->is_product_reservable( $htp_immediate_product_id ), 'A no-op eligibility extension does not change Free behavior.' );
remove_filter( 'htp_product_is_reservable', $htp_eligibility_passthrough );
$htp_transitions = array();
$htp_transition_listener = static function ( $transition ) use ( &$htp_transitions ) {
	$htp_transitions[] = $transition;
};
add_action( 'htp_reservation_transitioned', $htp_transition_listener );
$htp_counts_before_immediate = $htp_plugin->reservations->get_status_counts();
$htp_immediate_id = $htp_plugin->reservations->create_reservation( $htp_immediate_product_id, $htp_user_id );
htp_assert( $htp_immediate_id && 'active' === get_post_meta( $htp_immediate_id, '_htp_status', true ), 'Immediate reservation activates through the inventory transaction.' );
$htp_product_name_query = $htp_filtered_method->invoke( $htp_admin_reservations, 'all', 'Immediate reservation test', 'product', 1 );
htp_assert( in_array( $htp_immediate_id, wp_list_pluck( $htp_product_name_query->posts, 'ID' ), true ), 'Product-name search uses the WordPress query API and returns matching reservations.' );
$htp_counts_after_immediate = $htp_plugin->reservations->get_status_counts();
htp_assert( $htp_counts_before_immediate[ HTP_Reservation_Status::ACTIVE ] + 1 === $htp_counts_after_immediate[ HTP_Reservation_Status::ACTIVE ], 'Status-count cache is invalidated when a reservation becomes active.' );
htp_assert( $htp_immediate_product_id === (int) HTP_Reservation_Meta::get( $htp_immediate_id, HTP_Reservation_Meta::PRODUCT_ID ), 'Canonical metadata accessor reads reservation product data.' );
htp_assert( ! empty( $htp_transitions ) && HTP_Reservation_Status::ACTIVE === $htp_transitions[0]['to'], 'Lifecycle transition action receives a stable transition payload.' );
htp_assert( HTP_Inventory_Manager::STATE_HELD === get_post_meta( $htp_immediate_id, HTP_Inventory_Manager::META_STATE, true ), 'Immediate reservation records held inventory ownership.' );
htp_assert( 1 === (int) wc_get_product( $htp_immediate_product_id )->get_stock_quantity( 'edit' ), 'Immediate reservation decreases stock once.' );
$htp_expiry_before_extension = (int) HTP_Reservation_Meta::get( $htp_immediate_id, HTP_Reservation_Meta::EXPIRES_AT );
$htp_extension_event = array();
$htp_extension_listener = static function ( $event ) use ( &$htp_extension_event ) {
	$htp_extension_event = $event;
};
add_action( 'htp_reservation_extended', $htp_extension_listener );
$htp_extended_expiry = $htp_plugin->get_service( 'lifecycle' )->extend( $htp_immediate_id, 2, 'contract_test' );
htp_assert( $htp_expiry_before_extension + ( 2 * HOUR_IN_SECONDS ) === $htp_extended_expiry, 'Lifecycle extends an open deadline by the exact requested duration.' );
htp_assert( $htp_immediate_id === $htp_extension_event['reservation_id'] && 'contract_test' === $htp_extension_event['source'], 'Deadline extension emits its stable result contract.' );
$htp_email_content_seen = array();
$htp_email_result_seen = array();
$htp_email_filter = static function ( $content, $event, $reservation_id ) use ( &$htp_email_content_seen, $htp_immediate_id ) {
	if ( $htp_immediate_id === $reservation_id && 'created' === $event ) {
		$content['subject']     = 'Contract-filtered subject';
		$htp_email_content_seen = $content;
	}
	return $content;
};
$htp_mail_short_circuit = static function () {
	return true;
};
$htp_email_result_listener = static function ( $sent, $event, $reservation_id ) use ( &$htp_email_result_seen, $htp_immediate_id ) {
	if ( $htp_immediate_id === $reservation_id && 'created' === $event ) {
		$htp_email_result_seen = array( $sent, $event, $reservation_id );
	}
};
add_filter( 'htp_email_content', $htp_email_filter, 10, 3 );
add_filter( 'pre_wp_mail', $htp_mail_short_circuit );
add_action( 'htp_email_sent', $htp_email_result_listener, 10, 3 );
update_option( 'holdthisproduct_options', array( 'enable_reservation' => 1, 'max_reservations' => 3, 'reservation_duration' => 24, 'pending_duration' => 1, 'require_admin_approval' => 0, 'enable_email_notifications' => 1 ) );
$htp_plugin->get_service( 'notifications' )->dispatch( 'created', $htp_immediate_id, 'htp@example.test' );
htp_assert( 'Contract-filtered subject' === $htp_email_content_seen['subject'], 'Transactional email content is filtered once before delivery.' );
htp_assert( true === $htp_email_result_seen[0], 'Transactional email result event reports the mail transport result.' );
remove_filter( 'htp_email_content', $htp_email_filter, 10 );
remove_filter( 'pre_wp_mail', $htp_mail_short_circuit );
remove_action( 'htp_email_sent', $htp_email_result_listener, 10 );
update_option( 'holdthisproduct_options', array( 'enable_reservation' => 1, 'max_reservations' => 3, 'reservation_duration' => 24, 'pending_duration' => 1, 'require_admin_approval' => 0, 'enable_email_notifications' => 0 ) );
htp_assert( $htp_plugin->reservations->cancel_reservation( $htp_immediate_id ), 'Immediate reservation can be cancelled.' );
htp_assert( is_wp_error( $htp_plugin->get_service( 'lifecycle' )->extend( $htp_immediate_id, 2 ) ), 'Terminal reservations cannot be extended.' );
$htp_counts_after_cancel = $htp_plugin->reservations->get_status_counts();
htp_assert( $htp_counts_before_immediate[ HTP_Reservation_Status::ACTIVE ] === $htp_counts_after_cancel[ HTP_Reservation_Status::ACTIVE ], 'Status-count cache is invalidated when an active reservation is cancelled.' );
htp_assert( HTP_Inventory_Manager::STATE_RELEASED === get_post_meta( $htp_immediate_id, HTP_Inventory_Manager::META_STATE, true ), 'Cancellation records released inventory ownership.' );
htp_assert( 2 === (int) wc_get_product( $htp_immediate_product_id )->get_stock_quantity( 'edit' ), 'Transactional cancellation restores immediate reservation stock.' );

$htp_quantity_filter = static function ( $quantity, $requested ) {
	return max( 1, min( 3, $requested ) );
};
add_filter( 'htp_reservation_quantity', $htp_quantity_filter, 10, 2 );
$htp_quantity_product = new WC_Product_Simple();
$htp_quantity_product->set_name( 'Quantity reservation test product' );
$htp_quantity_product->set_status( 'publish' );
$htp_quantity_product->set_regular_price( '10' );
$htp_quantity_product->set_manage_stock( true );
$htp_quantity_product->set_stock_quantity( 5 );
$htp_quantity_product_id = $htp_quantity_product->save();
$htp_quantity_result     = $htp_plugin->get_service( 'lifecycle' )->request( $htp_quantity_product_id, $htp_user_id, 2 );
$htp_quantity_id         = is_wp_error( $htp_quantity_result ) ? 0 : $htp_quantity_result['reservation_id'];
htp_assert( $htp_quantity_id && 2 === (int) HTP_Reservation_Meta::get( $htp_quantity_id, HTP_Reservation_Meta::QUANTITY ), 'Filtered request quantity is stored canonically.' );
htp_assert( 3 === (int) wc_get_product( $htp_quantity_product_id )->get_stock_quantity( 'edit' ), 'Multi-unit reservation decreases the exact held quantity.' );
htp_assert( 5 === (int) wc_get_product( $htp_quantity_product_id )->get_stock_quantity(), 'Owner stock allowance includes the exact held quantity.' );
$htp_quantity_order = wc_create_order( array( 'customer_id' => $htp_user_id ) );
$htp_quantity_item_id = $htp_quantity_order->add_product( wc_get_product( $htp_quantity_product_id ), 3 );
$htp_quantity_item = $htp_quantity_order->get_item( $htp_quantity_item_id );
$htp_quantity_item->add_meta_data( HTP_Reservation_Meta::LINKED_RESERVATION, $htp_quantity_id, true );
$htp_quantity_item->save();
$htp_quantity_order->save();
$htp_plugin->reservations->transfer_holds_to_order( $htp_quantity_order );
htp_assert( 2 === (int) wc_get_product( $htp_quantity_product_id )->get_stock_quantity( 'edit' ), 'Transfer reduces only the order quantity beyond the multi-unit hold.' );
$htp_quantity_order->update_status( 'cancelled' );
htp_assert( 5 === (int) wc_get_product( $htp_quantity_product_id )->get_stock_quantity( 'edit' ), 'Cancelling a multi-unit order restores the complete order quantity once.' );

update_option( 'holdthisproduct_options', array( 'enable_reservation' => 1, 'max_reservations' => 3, 'reservation_duration' => 24, 'pending_duration' => 1, 'require_admin_approval' => 1, 'enable_email_notifications' => 0 ) );
$htp_pending_quantity = $htp_plugin->get_service( 'lifecycle' )->request( $htp_quantity_product_id, $htp_user_id, 3 );
$htp_pending_quantity_id = is_wp_error( $htp_pending_quantity ) ? 0 : $htp_pending_quantity['reservation_id'];
wc_update_product_stock( wc_get_product( $htp_quantity_product_id ), 2, 'set' );
$htp_pending_quantity_approval = $htp_plugin->reservations->approve_reservation( $htp_pending_quantity_id );
htp_assert( is_wp_error( $htp_pending_quantity_approval ) && 'htp_no_stock' === $htp_pending_quantity_approval->get_error_code(), 'Approval rejects a multi-unit request when its full quantity is unavailable.' );
htp_assert( 2 === (int) wc_get_product( $htp_quantity_product_id )->get_stock_quantity( 'edit' ), 'Rejected multi-unit approval leaves stock unchanged.' );
htp_assert( true === $htp_plugin->reservations->cancel_reservation( $htp_pending_quantity_id ), 'Pending multi-unit request can be cancelled without changing stock.' );

$htp_variable = new WC_Product_Variable();
$htp_variable->set_name( 'Variation reservation parent' );
$htp_variable->set_status( 'publish' );
$htp_variable_id = $htp_variable->save();
$htp_variation = new WC_Product_Variation();
$htp_variation->set_parent_id( $htp_variable_id );
$htp_variation->set_status( 'publish' );
$htp_variation->set_regular_price( '10' );
$htp_variation->set_manage_stock( true );
$htp_variation->set_stock_quantity( 4 );
$htp_variation_id = $htp_variation->save();
$htp_variation_inventory_filter = static function ( $supported, $product ) {
	return $supported || $product instanceof WC_Product_Variation;
};
add_filter( 'htp_product_supports_reservation_inventory', $htp_variation_inventory_filter, 10, 2 );
update_option( 'holdthisproduct_options', array( 'enable_reservation' => 1, 'max_reservations' => 3, 'reservation_duration' => 24, 'pending_duration' => 1, 'require_admin_approval' => 0, 'enable_email_notifications' => 0 ) );
$htp_variation_result = $htp_plugin->get_service( 'lifecycle' )->request( $htp_variation_id, $htp_user_id, 2 );
$htp_variation_reservation_id = is_wp_error( $htp_variation_result ) ? 0 : $htp_variation_result['reservation_id'];
htp_assert( $htp_variation_reservation_id && $htp_variation_id === (int) HTP_Reservation_Meta::get( $htp_variation_reservation_id, HTP_Reservation_Meta::PRODUCT_ID ), 'Variation reservation stores the concrete variation identity.' );
htp_assert( 2 === (int) wc_get_product( $htp_variation_id )->get_stock_quantity( 'edit' ), 'Variation reservation holds stock from the variation.' );
$htp_variation_order = wc_create_order( array( 'customer_id' => $htp_user_id ) );
$htp_variation_item_id = $htp_variation_order->add_product( wc_get_product( $htp_variation_id ), 2 );
$htp_variation_item = $htp_variation_order->get_item( $htp_variation_item_id );
$htp_variation_item->add_meta_data( HTP_Reservation_Meta::LINKED_RESERVATION, $htp_variation_reservation_id, true );
$htp_variation_item->save();
$htp_variation_order->save();
$htp_plugin->reservations->transfer_holds_to_order( $htp_variation_order );
htp_assert( HTP_Reservation_Status::FULFILLED === HTP_Reservation_Meta::get( $htp_variation_reservation_id, HTP_Reservation_Meta::STATUS ), 'Variation order transfers the exact linked reservation.' );
htp_assert( 2 === (int) wc_get_product( $htp_variation_id )->get_stock_quantity( 'edit' ), 'Variation fulfillment does not reduce held stock twice.' );
$htp_variation_order->update_status( 'cancelled' );
htp_assert( 4 === (int) wc_get_product( $htp_variation_id )->get_stock_quantity( 'edit' ), 'Variation order cancellation restores its stock once.' );
remove_filter( 'htp_product_supports_reservation_inventory', $htp_variation_inventory_filter, 10 );
remove_filter( 'htp_reservation_quantity', $htp_quantity_filter, 10 );

$htp_guest_product = new WC_Product_Simple();
$htp_guest_product->set_name( 'Verified guest reservation product' );
$htp_guest_product->set_status( 'publish' );
$htp_guest_product->set_regular_price( '10' );
$htp_guest_product->set_manage_stock( true );
$htp_guest_product->set_stock_quantity( 2 );
$htp_guest_product_id = $htp_guest_product->save();
$htp_guest_key        = strtolower( wp_generate_password( 8, false ) );
$htp_guest_email      = 'htp-verified-guest-' . $htp_guest_key . '@example.test';
$htp_guest_frontend = static function () {
	return true;
};
wp_set_current_user( 0 );
add_filter( 'htp_guest_reservation_frontend_enabled', $htp_guest_frontend );
htp_assert( $htp_plugin->reservations->is_product_reservable( $htp_guest_product_id ), 'An add-on can expose anonymous reservation UI without inventing a verified identity.' );
remove_filter( 'htp_guest_reservation_frontend_enabled', $htp_guest_frontend );
wp_set_current_user( $htp_user_id );
$htp_guest_disabled   = $htp_plugin->get_service( 'lifecycle' )->request_guest( $htp_guest_product_id, $htp_guest_email );
htp_assert( is_wp_error( $htp_guest_disabled ) && 'htp_not_reservable' === $htp_guest_disabled->get_error_code(), 'Free guest lifecycle remains disabled without an explicit extension opt-in.' );
$htp_guest_opt_in = static function () {
	return true;
};
add_filter( 'htp_allow_guest_reservations', $htp_guest_opt_in );
$htp_guest_result         = $htp_plugin->get_service( 'lifecycle' )->request_guest( $htp_guest_product_id, $htp_guest_email );
$htp_guest_reservation_id = is_wp_error( $htp_guest_result ) ? 0 : $htp_guest_result['reservation_id'];
htp_assert( $htp_guest_reservation_id && 0 === (int) get_post_field( 'post_author', $htp_guest_reservation_id ), 'Verified guest reservation uses the canonical authorless Free record.' );
htp_assert( $htp_guest_email === HTP_Reservation_Meta::get( $htp_guest_reservation_id, HTP_Reservation_Meta::EMAIL ), 'Verified guest identity is stored on the canonical reservation.' );
htp_assert( 1 === $htp_plugin->reservations->count_open_reservations( 0, $htp_guest_email ), 'Guest email identity consumes the normal open-reservation quota.' );
htp_assert( 1 === (int) wc_get_product( $htp_guest_product_id )->get_stock_quantity( 'edit' ), 'Verified guest reservation holds stock through the Free inventory transaction.' );
$htp_guest_duplicate = $htp_plugin->get_service( 'lifecycle' )->request_guest( $htp_guest_product_id, $htp_guest_email );
htp_assert( is_wp_error( $htp_guest_duplicate ) && 'htp_duplicate' === $htp_guest_duplicate->get_error_code(), 'Repeated verified guest identity cannot create a duplicate hold.' );
$htp_guest_user_id = wp_insert_user(
	array(
		'user_login' => 'htp-verified-guest-' . $htp_guest_key,
		'user_pass'  => wp_generate_password( 24 ),
		'user_email' => $htp_guest_email,
		'role'       => 'customer',
	)
);
$htp_account_duplicate = $htp_plugin->get_service( 'lifecycle' )->request( $htp_guest_product_id, $htp_guest_user_id );
htp_assert( is_wp_error( $htp_account_duplicate ) && 'htp_duplicate' === $htp_account_duplicate->get_error_code(), 'Creating an account with a guest email cannot bypass duplicate protection.' );
htp_assert( true === $htp_plugin->reservations->cancel_reservation( $htp_guest_reservation_id ), 'Verified guest reservation cancels through the Free lifecycle.' );
htp_assert( 2 === (int) wc_get_product( $htp_guest_product_id )->get_stock_quantity( 'edit' ), 'Guest cancellation restores stock exactly once.' );
remove_filter( 'htp_allow_guest_reservations', $htp_guest_opt_in );
update_option( 'holdthisproduct_options', array( 'enable_reservation' => 1, 'max_reservations' => 3, 'reservation_duration' => 24, 'pending_duration' => 1, 'require_admin_approval' => 1, 'enable_email_notifications' => 0 ) );

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
$htp_vetoed_id = htp_test_reservation( $htp_product_id, $htp_user_id, 'pending_approval', time() + HOUR_IN_SECONDS );
$htp_approval_veto = static function ( $allowed, $reservation_id, $from, $to, $source ) use ( $htp_vetoed_id ) {
	return $htp_vetoed_id === $reservation_id && 'approve' === $source ? false : $allowed;
};
add_filter( 'htp_reservation_transition_allowed', $htp_approval_veto, 10, 5 );
$htp_veto_result = $htp_plugin->reservations->approve_reservation( $htp_vetoed_id );
htp_assert( is_wp_error( $htp_veto_result ) && HTP_Reservation_Status::PENDING === HTP_Reservation_Meta::get( $htp_vetoed_id, HTP_Reservation_Meta::STATUS ), 'Transition filters can safely veto approval before inventory changes.' );
htp_assert( 1 === (int) wc_get_product( $htp_product_id )->get_stock_quantity( 'edit' ), 'A vetoed approval does not change stock.' );
remove_filter( 'htp_reservation_transition_allowed', $htp_approval_veto, 10 );
htp_assert( true === $htp_plugin->reservations->deny_reservation( $htp_vetoed_id, 'Contract test' ), 'Pending reservation denial uses the lifecycle service.' );
htp_assert( HTP_Inventory_Manager::STATE_RELEASED === HTP_Reservation_Meta::get( $htp_vetoed_id, HTP_Reservation_Meta::INVENTORY_STATE ), 'Denial records terminal inventory ownership consistently.' );
htp_assert( true === $htp_plugin->reservations->approve_reservation( $htp_pending_id ), 'Pending reservation approves.' );
$htp_product = wc_get_product( $htp_product_id );
htp_assert( 0 === (int) $htp_product->get_stock_quantity( 'edit' ), 'Approval holds physical stock once.' );
htp_assert( HTP_Inventory_Manager::STATE_HELD === get_post_meta( $htp_pending_id, HTP_Inventory_Manager::META_STATE, true ), 'Approval records held inventory ownership.' );
htp_assert( 1 === (int) $htp_product->get_stock_quantity(), 'Owner can purchase the held last unit.' );
htp_assert( is_wp_error( $htp_plugin->reservations->approve_reservation( $htp_pending_id ) ), 'Repeated approval is rejected.' );
htp_assert( true === $htp_plugin->reservations->cancel_reservation( $htp_pending_id ), 'Active reservation cancels.' );
htp_assert( false === $htp_plugin->reservations->cancel_reservation( $htp_pending_id ), 'Repeated cancellation is rejected.' );
$htp_product = wc_get_product( $htp_product_id );
htp_assert( 1 === (int) $htp_product->get_stock_quantity( 'edit' ), 'Cancellation restores stock exactly once.' );
htp_assert( HTP_Inventory_Manager::STATE_RELEASED === get_post_meta( $htp_pending_id, HTP_Inventory_Manager::META_STATE, true ), 'Cancellation records released inventory ownership.' );

$htp_expired_pending = htp_test_reservation( $htp_product_id, $htp_user_id, 'pending_approval', time() - 1 );
htp_assert( 0 === $htp_plugin->reservations->count_open_reservations( $htp_user_id ), 'Expired pending requests do not consume reservation quota.' );
$htp_plugin->reservations->expire_old_reservations();
htp_assert( 'expired' === get_post_meta( $htp_expired_pending, '_htp_status', true ), 'Pending requests expire.' );
htp_assert( 1 === (int) wc_get_product( $htp_product_id )->get_stock_quantity( 'edit' ), 'Pending expiry does not change stock.' );

$htp_cart = new WC_Cart();
$htp_cart_item_key = $htp_cart->add_to_cart( $htp_product_id, 1 );
htp_assert( $htp_cart_item_key && empty( $htp_cart->cart_contents[ $htp_cart_item_key ]['_htp_reservation_id'] ), 'Cart item added before reservation starts unlinked.' );
$htp_active_id = htp_test_reservation( $htp_product_id, $htp_user_id, 'active', time() + HOUR_IN_SECONDS );
wc_update_product_stock( wc_get_product( $htp_product_id ), 1, 'decrease' );
$htp_plugin->reservations->sync_cart_reservations( $htp_cart );
htp_assert( $htp_active_id === (int) $htp_cart->cart_contents[ $htp_cart_item_key ]['_htp_reservation_id'], 'Existing cart item is linked after the reservation is created.' );
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
htp_assert( HTP_Inventory_Manager::STATE_TRANSFERRED === get_post_meta( $htp_active_id, HTP_Inventory_Manager::META_STATE, true ), 'Fulfillment transfers inventory ownership to the order.' );
htp_assert( 0 === (int) wc_get_product( $htp_product_id )->get_stock_quantity( 'edit' ), 'Checkout does not decrement the held unit twice.' );
$htp_order->update_status( 'cancelled' );
htp_assert( 1 === (int) wc_get_product( $htp_product_id )->get_stock_quantity( 'edit' ), 'Cancelled order restores stock exactly once.' );
htp_assert( 'order_cancelled' === get_post_meta( $htp_active_id, '_htp_status', true ), 'Cancelled order has an explicit reservation status.' );
htp_assert( HTP_Inventory_Manager::STATE_RELEASED === get_post_meta( $htp_active_id, HTP_Inventory_Manager::META_STATE, true ), 'Cancelled order records released inventory ownership.' );
$htp_plugin->reservations->restore_transferred_order_stock( $htp_order->get_id() );
htp_assert( 1 === (int) wc_get_product( $htp_product_id )->get_stock_quantity( 'edit' ), 'Repeated order restoration is idempotent.' );

require_once ABSPATH . 'wp-admin/includes/user.php';
$htp_privacy_user_id = wp_insert_user( array( 'user_login' => 'htp-privacy-user', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'htp-privacy@example.test', 'role' => 'customer' ) );
$htp_privacy_ids = array();
for ( $htp_i = 0; $htp_i < 101; $htp_i++ ) {
	$htp_privacy_ids[] = htp_test_reservation( $htp_product_id, $htp_privacy_user_id, 'expired', time() - HOUR_IN_SECONDS );
}
$htp_erase_first = $htp_plugin->reservations->erase_personal_data( 'htp-privacy@example.test', 1 );
$htp_erase_second = $htp_plugin->reservations->erase_personal_data( 'htp-privacy@example.test', 2 );
$htp_privacy_remaining = get_posts( array( 'post_type' => 'htp_reservation', 'post_status' => 'publish', 'author' => $htp_privacy_user_id, 'fields' => 'ids', 'posts_per_page' => -1 ) );
htp_assert( ! $htp_erase_first['done'] && $htp_erase_second['done'] && empty( $htp_privacy_remaining ), 'Privacy eraser processes a shrinking result set without skipping records.' );

$htp_delete_user_id = wp_insert_user( array( 'user_login' => 'htp-delete-user', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'htp-delete@example.test', 'role' => 'customer' ) );
$htp_delete_reservation_id = htp_test_reservation( $htp_product_id, $htp_delete_user_id, 'active', time() + HOUR_IN_SECONDS );
wp_delete_user( $htp_delete_user_id );
htp_assert( 'htp_reservation' === get_post_type( $htp_delete_reservation_id ), 'Deleting a customer does not delete a reservation with an inventory obligation.' );

wp_delete_post( $htp_pending_id, true );
wp_delete_post( $htp_vetoed_id, true );
wp_delete_post( $htp_expired_pending, true );
wp_delete_post( $htp_active_id, true );
wp_delete_post( $htp_immediate_id, true );
foreach ( $htp_privacy_ids as $htp_privacy_id ) {
	wp_delete_post( $htp_privacy_id, true );
}
wp_delete_post( $htp_delete_reservation_id, true );
wp_delete_post( $htp_product_id, true );
wp_delete_post( $htp_immediate_product_id, true );
$htp_quantity_order->delete( true );
$htp_variation_order->delete( true );
wp_delete_post( $htp_quantity_id, true );
wp_delete_post( $htp_pending_quantity_id, true );
wp_delete_post( $htp_variation_reservation_id, true );
wp_delete_post( $htp_variation_id, true );
wp_delete_post( $htp_variable_id, true );
wp_delete_post( $htp_quantity_product_id, true );
wp_delete_post( $htp_guest_reservation_id, true );
wp_delete_post( $htp_guest_product_id, true );
$htp_order->delete( true );
wp_delete_user( $htp_privacy_user_id );
wp_delete_user( $htp_guest_user_id );
wp_delete_user( $htp_user_id );
remove_action( 'htp_reservation_transitioned', $htp_transition_listener );
remove_action( 'htp_reservation_extended', $htp_extension_listener );
if ( false === $htp_original_options ) {
	delete_option( 'holdthisproduct_options' );
} else {
	update_option( 'holdthisproduct_options', $htp_original_options );
}
if ( $htp_failures ) exit( 1 );
echo esc_html( "All integration assertions passed.\n" );
