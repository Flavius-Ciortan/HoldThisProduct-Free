<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

// If $product is not set, resolve it
if ( ! $product instanceof WC_Product ) {
	$product_id = get_the_ID();
	if ( ! $product_id ) {
		$product_id = get_queried_object_id();
	}
	if ( $product_id ) {
		$product = wc_get_product( $product_id );
	}
}

// If we still don't have a product, stop
if ( ! $product instanceof WC_Product ) {
	return;
}

// Get settings
$pid         = $product->get_id();
$options     = get_option( 'holdthisproduct_options' );
$globally_on = ! empty( $options['enable_reservation'] );

// Free remains account-only; compatible add-ons may enable a verified guest flow.
$show_button = $globally_on && ( is_user_logged_in() || apply_filters( 'htp_guest_reservation_form_visible', false, $product ) );
?>

<?php if ( $show_button ) : ?>
	<button
		type="button"
		id="htp_reserve_product"
		class="single_add_to_cart_button button alt wp-element-button"
		data-productid="<?php echo esc_attr( $pid ); ?>"
		data-product-type="<?php echo esc_attr( $product->get_type() ); ?>"
	>
		<?php esc_html_e( 'Reserve Product', 'hold-this-product' ); ?>
	</button>
<?php endif; ?>
