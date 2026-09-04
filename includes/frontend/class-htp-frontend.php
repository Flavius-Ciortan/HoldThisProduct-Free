<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend functionality
 */
class HTP_Frontend {

	/**
	 * Reservations instance
	 */
	private $reservations;

	/**
	 * Prevent duplicate output when multiple hooks fire.
	 */
	private $did_render_form = false;

	/**
	 * Prevent duplicate modal output.
	 */
	private $did_render_modal = false;

	/**
	 * Constructor
	 */
	public function __construct( $reservations = null ) {
		$this->reservations = $reservations instanceof HTP_Reservations ? $reservations : new HTP_Reservations();
		$this->init();
	}

	/**
	 * Initialize frontend hooks
	 */
	private function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		// Render next to the Add to cart button on single product pages.
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'display_reservation_form' ) );
		// Sold-out products may not render an add-to-cart form; Pro waitlists use this fallback.
		add_action( 'woocommerce_single_product_summary', array( $this, 'display_reservation_fallback' ), 31 );
		add_filter( 'render_block_woocommerce/add-to-cart-form', array( $this, 'add_reservation_block_classes' ), 10, 2 );

		// Render modal markup outside WooCommerce's form.cart to avoid nested <form> issues.
		add_action( 'wp_footer', array( $this, 'display_reservation_modal' ) );
	}

	/**
	 * Display reservation form on product pages
	 */
	public function display_reservation_form() {
		if ( $this->did_render_form ) {
			return;
		}

		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( ! $product ) {
			return;
		}

		if ( ! $this->reservations->is_product_reservable( $product->get_id() ) ) {
			// Show message for non-logged-in users or when reservations are disabled
			if ( ! is_user_logged_in() ) {
				printf( '<p class="htp-reserve-unavailable" style="margin-top:8px;">%1$s <a href="%2$s">%3$s</a> %4$s <a href="%5$s">%6$s</a> %7$s</p>', esc_html__( 'Please', 'hold-this-product' ), esc_url( wp_login_url( get_permalink() ) ), esc_html__( 'log in', 'hold-this-product' ), esc_html__( 'or', 'hold-this-product' ), esc_url( wp_registration_url() ), esc_html__( 'create an account', 'hold-this-product' ), esc_html__( 'to reserve this product.', 'hold-this-product' ) );
			} else {
				echo '<p class="htp-reserve-unavailable" style="margin-top:8px;">' . esc_html__( 'Reservations are not available for this product.', 'hold-this-product' ) . '</p>';
			}
			return;
		}

		$this->did_render_form = true;
		$this->include_form_template();
	}

	/**
	 * Render only when WooCommerce has no normal in-stock Add to Cart form.
	 *
	 * Block themes render their dynamic Add to Cart block after the summary
	 * compatibility hook. Let that block trigger the standard button hook so the
	 * reservation action remains inside the cart form.
	 */
	public function display_reservation_fallback() {
		global $product;

		if ( $this->did_render_form ) {
			return;
		}

		if ( $product instanceof WC_Product && $product->is_purchasable() && $product->is_in_stock() ) {
			return;
		}

		$this->display_reservation_form();
	}

	/**
	 * Add stable classes to reservation-enabled WooCommerce Add to Cart blocks.
	 *
	 * @param string $block_content Rendered block markup.
	 * @param array  $block         Parsed block data.
	 * @return string
	 */
	public function add_reservation_block_classes( $block_content, $block ) {
		unset( $block );

		if ( false === strpos( $block_content, 'id="htp_reserve_product"' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $block_content;
		}

		$processor = new WP_HTML_Tag_Processor( $block_content );
		if ( $processor->next_tag(
			array(
				'tag_name'   => 'DIV',
				'class_name' => 'wc-block-add-to-cart-form',
			)
		) ) {
			$processor->add_class( 'htp-has-reserve-action' );
		}

		if ( $processor->next_tag(
			array(
				'tag_name'   => 'FORM',
				'class_name' => 'cart',
			)
		) ) {
			$processor->add_class( 'htp-cart-actions' );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		if ( ! is_product() && ! is_account_page() ) {
			return;
		}
		wp_enqueue_style(
			'holdthisproduct-style',
			HTP_PLUGIN_URL . 'assets/css/style.css',
			array(),
			HTP_VERSION
		);

		wp_enqueue_script(
			'holdthisproduct-js',
			HTP_PLUGIN_URL . 'assets/js/holdthisproduct.js',
			array( 'jquery' ),
			HTP_VERSION,
			true
		);

		wp_localize_script(
			'holdthisproduct-js',
			'holdthisproduct_ajax',
			array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'holdthisproduct_nonce' ),
				'is_logged_in' => is_user_logged_in() ? 1 : 0,
				'allow_guest'  => apply_filters( 'htp_guest_reservation_frontend_enabled', false ) ? 1 : 0,
				'i18n'         => array(
					'loginRequired'   => __( 'Please log in to reserve products.', 'hold-this-product' ),
					'selectVariation' => __( 'Please choose a product variation before reserving.', 'hold-this-product' ),
					'processing'      => __( 'Processing...', 'hold-this-product' ),
					'success'         => __( 'Reservation successful!', 'hold-this-product' ),
					'error'           => __( 'Error:', 'hold-this-product' ),
					'failed'          => __( 'Request failed. Please try again.', 'hold-this-product' ),
				),
			)
		);
	}

	/**
	 * Include the form template
	 */
	private function include_form_template() {
		include HTP_PLUGIN_PATH . 'templates/form-template.php';
	}

	/**
	 * Display reservation modal on product pages (footer output).
	 */
	public function display_reservation_modal() {
		if ( $this->did_render_modal ) {
			return;
		}

		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( ! $product ) {
			return;
		}

		if ( ! $this->reservations->is_product_reservable( $product->get_id() ) ) {
			return;
		}

		$this->did_render_modal = true;
		include HTP_PLUGIN_PATH . 'templates/modal-template.php';
	}
}
