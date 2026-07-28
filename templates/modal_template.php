<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $product;

// If $product is not set, resolve it.
if ( ! $product instanceof WC_Product ) {
    $product_id = get_the_ID();
    if ( ! $product_id ) {
        $product_id = get_queried_object_id();
    }
    if ( $product_id ) {
        $product = wc_get_product( $product_id );
    }
}

if ( ! $product instanceof WC_Product ) {
    return;
}

$pid = $product->get_id();
$options = get_option( 'holdthisproduct_options' );

// Reservation duration (hours)
$duration_hours = isset( $options['reservation_duration'] ) ? absint( $options['reservation_duration'] ) : 24;
if ( $duration_hours < 1 ) {
    $duration_hours = 1;
} elseif ( $duration_hours > 168 ) {
    $duration_hours = 168;
}

// Popup customization settings (logged-in only).
$enable_popup_customization = ! empty( $options['enable_popup_customization_logged_in'] );
$popup_settings = $options['popup_customization_logged_in'] ?? array();

// Defaults
$border_radius = isset( $popup_settings['border_radius'] ) ? (int) $popup_settings['border_radius'] : 8;
$background_color = isset( $popup_settings['background_color'] ) ? $popup_settings['background_color'] : '#ffffff';
$font_family = isset( $popup_settings['font_family'] ) ? $popup_settings['font_family'] : 'Arial, Helvetica, sans-serif';
$font_size = isset( $popup_settings['font_size'] ) ? (int) $popup_settings['font_size'] : 16;
$text_color = isset( $popup_settings['text_color'] ) ? $popup_settings['text_color'] : '#222222';

$allowed_fonts = array( 'Arial, Helvetica, sans-serif', 'Verdana, Geneva, sans-serif', 'Georgia, serif', 'Times New Roman, Times, serif', 'Tahoma, Geneva, sans-serif', 'Trebuchet MS, Helvetica, sans-serif', 'Courier New, Courier, monospace', 'Roboto, sans-serif', 'Open Sans, sans-serif', 'Lato, sans-serif', 'Montserrat, sans-serif' );
$font_family = in_array( $font_family, $allowed_fonts, true ) ? $font_family : 'Arial, Helvetica, sans-serif';
$background_color = sanitize_hex_color( $background_color ) ?: '#ffffff';
$text_color = sanitize_hex_color( $text_color ) ?: '#222222';
$border_radius = max( 0, min( 50, $border_radius ) );
$font_size = max( 10, min( 40, $font_size ) );

// Build inline style for modal box - only apply if customization is enabled.
$modal_box_style = '';
if ( $enable_popup_customization ) {
    $modal_box_style = sprintf(
        'background-color: %s !important; border-radius: %dpx !important; font-family: %s !important; font-size: %dpx !important; color: %s !important;',
        esc_attr( $background_color ),
        esc_attr( $border_radius ),
        esc_attr( $font_family ),
        esc_attr( $font_size ),
        esc_attr( $text_color )
    );
}
?>

<div id="reservation-modal" class="modal-overlay htp-modal-overlay" title="<?php esc_attr_e( 'Reserve Product', 'hold-this-product' ); ?>" aria-hidden="true" style="display: none;">
    <div class="modal-box htp-modal-box<?php echo $enable_popup_customization ? ' htp-modal-box--custom' : ''; ?>" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Reserve Product', 'hold-this-product' ); ?>" tabindex="-1" style="<?php echo esc_attr( $modal_box_style ); ?>">
        <form id="reservation-form">
            <input type="hidden" name="action" value="holdthisproduct_reserve">
            <input type="hidden" name="security" value="<?php echo esc_attr( wp_create_nonce( 'holdthisproduct_nonce' ) ); ?>">
            <input type="hidden" name="product_id" value="<?php echo esc_attr( $pid ); ?>">

			<p><strong><?php esc_html_e( 'Reserve this product', 'hold-this-product' ); ?></strong></p>
            <p>
				<?php
                printf(
					/* translators: %d: reservation duration in hours. */
					esc_html( _n( 'Are you sure you want to reserve this product for %d hour?', 'Are you sure you want to reserve this product for %d hours?', $duration_hours, 'hold-this-product' ) ),
					(int) $duration_hours
                );
                ?>
            </p>

			<button type="submit" class="submit-btn htp-button-primary"><?php esc_html_e( 'Yes, Reserve', 'hold-this-product' ); ?></button>
        </form>
    </div>
</div>
