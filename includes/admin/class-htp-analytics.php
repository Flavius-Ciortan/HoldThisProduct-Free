<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reservation analytics and reporting
 */
class HTP_Analytics {
	private $reservations;

	/**
	 * Constructor
	 */
	public function __construct( $reservations = null ) {
		$this->reservations = $reservations instanceof HTP_Reservations ? $reservations : null;
		$this->init();
	}

	/**
	 * Initialize hooks
	 */
	private function init() {
		add_action( 'admin_menu', array( $this, 'add_analytics_submenu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_analytics_scripts' ) );
	}

	/**
	 * Add analytics submenu
	 */
	public function add_analytics_submenu() {
		add_submenu_page(
			'holdthisproduct-settings',
			__( 'Reservation Analytics', 'hold-this-product' ),
			__( 'Analytics', 'hold-this-product' ),
			htp_get_manage_capability(),
			'holdthisproduct-analytics',
			array( $this, 'analytics_page' )
		);
	}

	/**
	 * Enqueue analytics page scripts and styles
	 */
	public function enqueue_analytics_scripts( $hook ) {
		// Some WP setups generate different hook suffixes for submenu pages.
		// Prefer checking the page slug as a reliable fallback.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'holdthisproduct_page_holdthisproduct-analytics' === $hook || 'holdthisproduct-analytics' === $page ) {
			wp_enqueue_style(
				'holdthisproduct-admin-style',
				HTP_PLUGIN_URL . 'assets/css/admin-style.css',
				array(),
				HTP_VERSION
			);
		}
	}

	/**
	 * Display analytics page
	 */
	public function analytics_page() {
		// First, expire old reservations to get accurate stats
		$this->expire_old_reservations_for_analytics();

		$stats = $this->get_reservation_stats();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reservation Analytics', 'hold-this-product' ); ?></h1>

			<div class="htp-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
				<div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
					<h3><?php esc_html_e( 'Total Reservations', 'hold-this-product' ); ?></h3>
					<p style="font-size: 32px; margin: 0; color: #0073aa;"><?php echo esc_html( $stats['total'] ); ?></p>
				</div>

				<div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
					<h3><?php esc_html_e( 'Active Reservations', 'hold-this-product' ); ?></h3>
					<p style="font-size: 32px; margin: 0; color: #2F89F9;"><?php echo esc_html( $stats['active'] ); ?></p>
				</div>

				<div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
					<h3><?php esc_html_e( 'Pending Approval', 'hold-this-product' ); ?></h3>
					<p style="font-size: 32px; margin: 0; color: #f59e0b;"><?php echo esc_html( $stats['pending_approval'] ); ?></p>
				</div>

				<div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
					<h3><?php esc_html_e( 'Expired Reservations', 'hold-this-product' ); ?></h3>
					<p style="font-size: 32px; margin: 0; color: #ff8c00;"><?php echo esc_html( $stats['expired'] ); ?></p>
				</div>

				<div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
					<h3><?php esc_html_e( 'Cancelled Reservations', 'hold-this-product' ); ?></h3>
					<p style="font-size: 32px; margin: 0; color: #d63638;"><?php echo esc_html( $stats['cancelled'] ); ?></p>
				</div>

				<div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
					<h3><?php esc_html_e( 'Fulfilled Reservations', 'hold-this-product' ); ?></h3>
					<p style="font-size: 32px; margin: 0; color: #00a32a;"><?php echo esc_html( $stats['fulfilled'] ); ?></p>
				</div>

				<div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
					<h3><?php esc_html_e( 'Denied Reservations', 'hold-this-product' ); ?></h3>
					<p style="font-size: 32px; margin: 0; color: #991b1b;"><?php echo esc_html( $stats['denied'] ); ?></p>
				</div>

				<div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
					<h3><?php esc_html_e( 'Cancelled Orders', 'hold-this-product' ); ?></h3>
					<p style="font-size: 32px; margin: 0; color: #7c3aed;"><?php echo esc_html( $stats['order_cancelled'] ); ?></p>
				</div>

				<div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
					<h3><?php esc_html_e( 'Conversion Rate', 'hold-this-product' ); ?></h3>
					<p style="font-size: 32px; margin: 0; color: #0073aa;"><?php echo esc_html( $stats['conversion_rate'] ); ?>%</p>
				</div>
			</div>

			<h2><?php esc_html_e( 'Recent Reservations', 'hold-this-product' ); ?></h2>
			<?php $this->display_recent_reservations(); ?>
		</div>
		<?php
	}

	/**
	 * Expire old reservations for analytics accuracy
	 */
	private function expire_old_reservations_for_analytics() {
		if ( $this->reservations ) {
			$this->reservations->expire_old_reservations();
		}
	}

	/**
	 * Get reservation statistics
	 */
	private function get_reservation_stats() {
		global $wpdb;
		$rows           = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS reservation_status, COUNT(*) AS reservation_count FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s WHERE p.post_type = 'htp_reservation' AND p.post_status = 'publish' GROUP BY pm.meta_value",
				HTP_Reservation_Meta::STATUS
			),
			OBJECT_K
		);
		$stats          = array_fill_keys( HTP_Reservation_Status::all(), 0 );
		$stats['total'] = 0;
		foreach ( (array) $rows as $status => $row ) {
			if ( isset( $stats[ $status ] ) ) {
				$stats[ $status ] = (int) $row->reservation_count;
				$stats['total']  += (int) $row->reservation_count;
			}
		}
		$stats['conversion_rate'] = $stats['total'] ? round( ( $stats[ HTP_Reservation_Status::FULFILLED ] / $stats['total'] ) * 100, 1 ) : 0;
		return $stats;
	}

	/**
	 * Display recent reservations table
	 */
	private function display_recent_reservations() {
		$reservations = get_posts(
			array(
				'post_type'      => 'htp_reservation',
				'posts_per_page' => 20,
				'meta_key'       => HTP_Reservation_Meta::STATUS,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( empty( $reservations ) ) {
			echo '<p>' . esc_html__( 'No reservations found.', 'hold-this-product' ) . '</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>' . esc_html__( 'Product', 'hold-this-product' ) . '</th><th>' . esc_html__( 'Customer', 'hold-this-product' ) . '</th><th>' . esc_html__( 'Status', 'hold-this-product' ) . '</th><th>' . esc_html__( 'Created', 'hold-this-product' ) . '</th><th>' . esc_html__( 'Expires', 'hold-this-product' ) . '</th></tr></thead>';
		echo '<tbody>';

		foreach ( $reservations as $reservation ) {
			$product_id = HTP_Reservation_Meta::get( $reservation->ID, HTP_Reservation_Meta::PRODUCT_ID );
			$status     = HTP_Reservation_Meta::get( $reservation->ID, HTP_Reservation_Meta::STATUS );
			$email      = HTP_Reservation_Meta::get( $reservation->ID, HTP_Reservation_Meta::EMAIL );
			$expires_ts = HTP_Reservation_Meta::get( $reservation->ID, HTP_Reservation_Meta::EXPIRES_AT );

			$product      = wc_get_product( $product_id );
			$product_name = $product ? $product->get_name() : __( 'Unknown Product', 'hold-this-product' );

			// Determine customer display name
			if ( $reservation->post_author ) {
				$user     = get_userdata( $reservation->post_author );
				$customer = $user ? $user->display_name : $email;
			} else {
				$name      = HTP_Reservation_Meta::get( $reservation->ID, HTP_Reservation_Meta::NAME );
				$surname   = HTP_Reservation_Meta::get( $reservation->ID, HTP_Reservation_Meta::SURNAME );
				$full_name = trim( $name . ' ' . $surname );
				$customer  = ! empty( $full_name ) ? $full_name : $email;
			}

			$expires = $expires_ts ? wp_date( 'Y-m-d H:i', $expires_ts ) : '—';

			// Add CSS class for status styling with proper fallback
			// Mirror the reservations admin view: use hyphens for CSS class names.
			$status_slug    = $status ? str_replace( '_', '-', $status ) : 'unknown';
			$status_class   = 'status-' . $status_slug;
			$status_display = $status ? ucwords( str_replace( '_', ' ', $status ) ) : __( 'Unknown', 'hold-this-product' );

			echo '<tr>';
			echo '<td>' . esc_html( $product_name ) . '</td>';
			echo '<td>' . esc_html( $customer ) . '</td>';
			echo '<td><span class="' . esc_attr( $status_class ) . '">' . esc_html( $status_display ) . '</span></td>';
			echo '<td>' . esc_html( get_the_date( 'Y-m-d H:i', $reservation ) ) . '</td>';
			echo '<td>' . esc_html( $expires ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
