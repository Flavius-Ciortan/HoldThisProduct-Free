<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin functionality
 */
class HTP_Admin {

    private $reservations_admin;
	private $reservations;
    
    /**
     * Constructor
     */
	public function __construct( $reservations = null ) {
		$this->reservations = $reservations instanceof HTP_Reservations ? $reservations : null;
		$this->reservations_admin = class_exists( 'HTP_Admin_Reservations' ) ? new HTP_Admin_Reservations( $this->reservations ) : null;
        $this->init();
    }
    
    /**
     * Initialize admin hooks
     */
    private function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'init_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'add_product_reservations_list' ) );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
			__( 'Hold This Product Settings', 'hold-this-product' ),
			__( 'Hold This Product', 'hold-this-product' ),
            'manage_options',
            'holdthisproduct-settings',
            array( $this, 'settings_page' ),
            HTP_PLUGIN_URL . 'assets/images/HTP-menu-icon.png',
            80
        );

        // Add Settings submenu (points to the same page as the main menu)
        add_submenu_page(
            'holdthisproduct-settings',
			__( 'Settings', 'hold-this-product' ),
			__( 'Settings', 'hold-this-product' ),
            'manage_options',
            'holdthisproduct-settings',
            array( $this, 'settings_page' )
        );

        // Add reservations management submenu
        add_submenu_page(
            'holdthisproduct-settings',
			__( 'Reservations', 'hold-this-product' ),
			__( 'Reservations', 'hold-this-product' ),
            'manage_options',
            'holdthisproduct-manage-reservations',
            $this->reservations_admin ? array( $this->reservations_admin, 'render_page' ) : '__return_null'
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
		register_setting( 'holdthisproduct_options_group', 'holdthisproduct_options', array( 'sanitize_callback' => array( $this, 'sanitize_options' ) ) );
        
        add_settings_section(
            'holdthisproduct_settings_section',
            '',
            '__return_false',
            'holdthisproduct-settings'
        );
        
        $fields = array(
			'holdthisproduct_enable_reservation' => __( 'Enable Reservation', 'hold-this-product' ),
			'holdthisproduct_max_reservations' => __( 'Max Reservations Per User', 'hold-this-product' ),
			'holdthisproduct_reservation_duration' => __( 'Reservation Duration (hours)', 'hold-this-product' ),
			'holdthisproduct_enable_email_notifications' => __( 'Enable Email Notifications', 'hold-this-product' ),
			'holdthisproduct_require_admin_approval' => __( 'Require Admin Approval for Reservations', 'hold-this-product' )
        );
        
        foreach ( $fields as $id => $title ) {
            add_settings_field(
                $id,
                $title,
                array( $this, $id . '_callback' ),
                'holdthisproduct-settings',
                'holdthisproduct_settings_section'
            );
        }
    }

	/** Validate the existing settings form without changing its fields or layout. */
	public function sanitize_options( $input ) {
		$input = is_array( $input ) ? $input : array();
		$popup = isset( $input['popup_customization_logged_in'] ) && is_array( $input['popup_customization_logged_in'] ) ? $input['popup_customization_logged_in'] : array();
		$allowed_fonts = array(
			'Arial, Helvetica, sans-serif', 'Verdana, Geneva, sans-serif', 'Georgia, serif',
			'Times New Roman, Times, serif', 'Tahoma, Geneva, sans-serif',
			'Trebuchet MS, Helvetica, sans-serif', 'Courier New, Courier, monospace',
			'Roboto, sans-serif', 'Open Sans, sans-serif', 'Lato, sans-serif', 'Montserrat, sans-serif',
		);
		$font = isset( $popup['font_family'] ) ? sanitize_text_field( $popup['font_family'] ) : 'Arial, Helvetica, sans-serif';
		if ( ! in_array( $font, $allowed_fonts, true ) ) {
			$font = 'Arial, Helvetica, sans-serif';
		}
		$background = sanitize_hex_color( isset( $popup['background_color'] ) ? $popup['background_color'] : '' );
		$text = sanitize_hex_color( isset( $popup['text_color'] ) ? $popup['text_color'] : '' );
		$reservation_duration = max( 1, min( 168, intval( isset( $input['reservation_duration'] ) ? $input['reservation_duration'] : 24 ) ) );

		return array(
			'enable_reservation' => empty( $input['enable_reservation'] ) ? 0 : 1,
			'max_reservations' => max( 1, min( 100, absint( isset( $input['max_reservations'] ) ? $input['max_reservations'] : 1 ) ) ),
			'reservation_duration' => $reservation_duration,
			'pending_duration' => max( 1, min( 168, intval( isset( $input['pending_duration'] ) ? $input['pending_duration'] : $reservation_duration ) ) ),
			'enable_email_notifications' => empty( $input['enable_email_notifications'] ) ? 0 : 1,
			'require_admin_approval' => empty( $input['require_admin_approval'] ) ? 0 : 1,
			'enable_popup_customization_logged_in' => empty( $input['enable_popup_customization_logged_in'] ) ? 0 : 1,
			'popup_customization_logged_in' => array(
				'border_radius' => max( 0, min( 50, absint( isset( $popup['border_radius'] ) ? $popup['border_radius'] : 12 ) ) ),
				'background_color' => $background ? $background : '#ffffff',
				'font_family' => $font,
				'font_size' => max( 10, min( 40, absint( isset( $popup['font_size'] ) ? $popup['font_size'] : 16 ) ) ),
				'text_color' => $text ? $text : '#222222',
			),
		);
	}
    
    /**
     * Enable reservation field callback
     */
    public function holdthisproduct_enable_reservation_callback() {
        $options = get_option( 'holdthisproduct_options' );
        $checked = ! empty( $options['enable_reservation'] ) ? 'checked' : '';
        echo '<div class="htp-setting-field">
                <div class="htp-setting-control">
                    <label class="toggle-switch">
                        <input type="checkbox" name="holdthisproduct_options[enable_reservation]" value="1" ' . esc_attr( $checked ) . '>
                        <span class="slider"></span>
                    </label>
                </div>
                <p class="description">Enable product reservations across your store.</p>
              </div>';
    }
    
    /**
     * Max reservations field callback
     */
    public function holdthisproduct_max_reservations_callback() {
        $options = get_option( 'holdthisproduct_options' );
        $value = isset( $options['max_reservations'] ) ? absint( $options['max_reservations'] ) : 1;
        echo '<div class="htp-setting-field">
                <div class="htp-setting-control">
                    <input type="number" min="1" name="holdthisproduct_options[max_reservations]" value="' . esc_attr( $value ) . '" class="holdthisproduct-small-input" />
                </div>
                <p class="description">Limit how many active reservations a user can have at once.</p>
              </div>';
    }
    
    /**
     * Reservation duration field callback
     */
    public function holdthisproduct_reservation_duration_callback() {
        $options = get_option( 'holdthisproduct_options' );
        $value = isset( $options['reservation_duration'] ) ? absint( $options['reservation_duration'] ) : 24;
        echo '<div class="htp-setting-field">
                <div class="htp-setting-control">
                    <div class="htp-input-right-align">
                        <input type="number" min="1" max="168" name="holdthisproduct_options[reservation_duration]" value="' . esc_attr( $value ) . '" class="holdthisproduct-small-input" />
                    </div>
                </div>
                <p class="description">How long reservations last (1-168 hours, default: 24)</p>
              </div>';
    }
    
    /**
     * Enable email notifications field callback
     */
    public function holdthisproduct_enable_email_notifications_callback() {
        $options = get_option( 'holdthisproduct_options' );
        $checked = ! empty( $options['enable_email_notifications'] ) ? 'checked' : '';
        echo '<div class="htp-setting-field">
                <div class="htp-setting-control">
                    <label class="toggle-switch">
                        <input type="checkbox" name="holdthisproduct_options[enable_email_notifications]" value="1" ' . esc_attr( $checked ) . '>
                        <span class="slider"></span>
                    </label>
                </div>
                <p class="description">Send email confirmations and status updates to customers.</p>
              </div>';
    }
    
    /**
     * Require admin approval field callback
     */
    public function holdthisproduct_require_admin_approval_callback() {
        $options = get_option( 'holdthisproduct_options' );
        $checked = ! empty( $options['require_admin_approval'] ) ? 'checked' : '';
        echo '<div class="htp-setting-field">
                <div class="htp-setting-control">
                    <label class="toggle-switch">
                        <input type="checkbox" name="holdthisproduct_options[require_admin_approval]" value="1" ' . esc_attr( $checked ) . '>
                        <span class="slider"></span>
                    </label>
                </div>
                <p class="description">Reservations require admin approval before becoming active.</p>
              </div>';
    }
    
    /**
     * Settings page HTML
     */
    public function settings_page() {
        ?>
        <div class="htp-admin-wrapper">
            <!-- Header with Logo -->
            <div class="htp-admin-header">
                <div class="htp-header-content">
                    <div class="htp-title-section">
						<h1 class="htp-main-title"><?php esc_html_e( 'Hold This Product Settings', 'hold-this-product' ); ?></h1>
						<p class="htp-subtitle"><?php esc_html_e( 'Manage your product reservation system', 'hold-this-product' ); ?></p>
                    </div>
                    <div class="htp-logo-section">
                        <?php
                        $logo_files = array('logo-transparent.png', 'HTP-menu-icon.png');
                        $logo_src = '';
                        $found_file = '';
                        
                        foreach ($logo_files as $logo_file) {
                            $logo_path = HTP_PLUGIN_PATH . 'assets/images/' . $logo_file;
                            if (file_exists($logo_path)) {
                                $logo_src = HTP_PLUGIN_URL . 'assets/images/' . rawurlencode($logo_file);
                                $found_file = $logo_file;
                                break;
                            }
                        }
                        
                        if ($logo_src): ?>
							<img src="<?php echo esc_url($logo_src); ?>" alt="<?php esc_attr_e( 'Hold This Product Logo', 'hold-this-product' ); ?>" class="htp-logo">
                        <?php else: ?>
                            <div class="htp-logo htp-logo-fallback" title="No logo file found. Checked: <?php echo esc_attr( implode( ', ', $logo_files ) ); ?>">HTP</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="htp-admin-content">
                <!-- Navigation Tabs -->
                <div class="htp-nav-wrapper">
                    <div class="htp-nav-tabs">
                        <button type="button" class="htp-nav-tab" data-target="general">
                            <span class="htp-tab-icon">⚙️</span>
							<span class="htp-tab-text"><?php esc_html_e( 'General Settings', 'hold-this-product' ); ?></span>
                        </button>
                        <button type="button" class="htp-nav-tab" data-target="logged-in">
                            <span class="htp-tab-icon">🎨</span>
							<span class="htp-tab-text"><?php esc_html_e( 'Pop-up Customization', 'hold-this-product' ); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Tab Content -->
                <form method="post" action="options.php" class="htp-settings-form">
                    <?php settings_fields( 'holdthisproduct_options_group' ); ?>
                    <div class="htp-tab-container">
                        <!-- General Settings Tab -->
                        <div id="htp-general" class="htp-tab-content">
                            <div class="htp-settings-card">
                                <div class="htp-card-header">
									<h3><?php esc_html_e( 'Configuration', 'hold-this-product' ); ?></h3>
									<p><?php esc_html_e( 'Configure the basic settings for your reservation system', 'hold-this-product' ); ?></p>
                                </div>
                                <div class="htp-card-body">
                                    <?php do_settings_sections( 'holdthisproduct-settings' ); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Pop-up Customization Tab -->
                        <div id="htp-logged-in" class="htp-tab-content">
                            <div class="htp-settings-card">
                                <div class="htp-card-header">
									<h3><?php esc_html_e( 'Pop-up Customization', 'hold-this-product' ); ?></h3>
									<p><?php esc_html_e( 'Customize the appearance of the reservation pop-up modal', 'hold-this-product' ); ?></p>
                                </div>
                                <div class="htp-card-body">
                                    <?php
									$options = get_option('holdthisproduct_options');
									$options = is_array( $options ) ? $options : array();
                                    $enable_popup_customization_logged_in = isset($options['enable_popup_customization_logged_in']) ? (bool)$options['enable_popup_customization_logged_in'] : false;
                                    $popup_settings_logged_in = isset($options['popup_customization_logged_in']) ? $options['popup_customization_logged_in'] : [];
                                    ?>
	                                    <table class="form-table">
	                                        <tr>
	                                            <th scope="row"><?php esc_html_e( 'Enable Pop-up Customization', 'hold-this-product' ); ?></th>
	                                            <td>
	                                                <div class="htp-setting-field">
	                                                    <div class="htp-setting-control">
	                                                        <label class="toggle-switch">
	                                                            <input type="checkbox" name="holdthisproduct_options[enable_popup_customization_logged_in]" value="1" <?php checked($enable_popup_customization_logged_in); ?>>
	                                                            <span class="slider"></span>
	                                                        </label>
	                                                    </div>
	                                                    <p class="description"><?php esc_html_e( 'Enable custom styling for the reservation pop-up modal.', 'hold-this-product' ); ?></p>
	                                                </div>
	                                            </td>
	                                        </tr>
	                                    </table>
                                    <div class="htp-popup-customization-fields-logged-in" style="display:<?php echo $enable_popup_customization_logged_in ? 'block' : 'none'; ?>;margin-top:1rem;">
                                        <table class="form-table">
                                            <tr>
												<th scope="row"><?php esc_html_e( 'Border Radius (px)', 'hold-this-product' ); ?></th>
                                                <td><input type="number" name="holdthisproduct_options[popup_customization_logged_in][border_radius]" value="<?php echo esc_attr($popup_settings_logged_in['border_radius'] ?? '12'); ?>" min="0" max="50" class="htp-input-right-align"></td>
                                            </tr>
                                            <tr>
												<th scope="row"><?php esc_html_e( 'Background Color', 'hold-this-product' ); ?></th>
                                                <td><input type="color" name="holdthisproduct_options[popup_customization_logged_in][background_color]" value="<?php echo esc_attr($popup_settings_logged_in['background_color'] ?? '#ffffff'); ?>" class="htp-input-right-align"></td>
                                            </tr>
                                            <tr>
												<th scope="row"><?php esc_html_e( 'Font Family', 'hold-this-product' ); ?></th>
                                                <td>
                                                    <select name="holdthisproduct_options[popup_customization_logged_in][font_family]" class="htp-input-right-align">
                                                        <?php
                                                        $fonts = [
                                                            'Arial, Helvetica, sans-serif' => 'Arial',
                                                            'Verdana, Geneva, sans-serif' => 'Verdana',
                                                            'Georgia, serif' => 'Georgia',
                                                            'Times New Roman, Times, serif' => 'Times New Roman',
                                                            'Tahoma, Geneva, sans-serif' => 'Tahoma',
                                                            'Trebuchet MS, Helvetica, sans-serif' => 'Trebuchet MS',
                                                            'Courier New, Courier, monospace' => 'Courier New',
                                                            'Roboto, sans-serif' => 'Roboto (Google)',
                                                            'Open Sans, sans-serif' => 'Open Sans (Google)',
                                                            'Lato, sans-serif' => 'Lato (Google)',
                                                            'Montserrat, sans-serif' => 'Montserrat (Google)'
                                                        ];
                                                        $selected_font = $popup_settings_logged_in['font_family'] ?? 'Arial, Helvetica, sans-serif';
                                                        foreach ($fonts as $value => $label) {
                                                            echo '<option value="' . esc_attr($value) . '"' . selected($selected_font, $value, false) . '>' . esc_html($label) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
												<th scope="row"><?php esc_html_e( 'Font Size (px)', 'hold-this-product' ); ?></th>
                                                <td><input type="number" name="holdthisproduct_options[popup_customization_logged_in][font_size]" value="<?php echo esc_attr($popup_settings_logged_in['font_size'] ?? '16'); ?>" min="10" max="40" class="htp-input-right-align"></td>
                                            </tr>
                                            <tr>
												<th scope="row"><?php esc_html_e( 'Text Color', 'hold-this-product' ); ?></th>
                                                <td><input type="color" name="holdthisproduct_options[popup_customization_logged_in][text_color]" value="<?php echo esc_attr($popup_settings_logged_in['text_color'] ?? '#222222'); ?>" class="htp-input-right-align"></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="htp-form-actions">
						<?php submit_button( __( 'Save Settings', 'hold-this-product' ), 'primary htp-save-btn', 'submit', false ); ?>
                    </div>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Check if there's a saved active tab after form submission
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('active_tab') || localStorage.getItem('htp_active_tab') || 'general';
            
            // Immediately set the correct tab without animation to prevent flash
            $('.htp-nav-tab').removeClass('htp-nav-tab-active');
            $('.htp-tab-content').removeClass('htp-tab-active').hide();
            
            // Set the active tab
            if (activeTab && $('#htp-' + activeTab).length) {
                $('.htp-nav-tab[data-target="' + activeTab + '"]').addClass('htp-nav-tab-active');
                $('#htp-' + activeTab).addClass('htp-tab-active').show();
            } else {
                // Fallback to general tab
                $('.htp-nav-tab[data-target="general"]').addClass('htp-nav-tab-active');
                $('#htp-general').addClass('htp-tab-active').show();
            }
            
            // Show form actions after tab is properly set
            $('.htp-form-actions').addClass('htp-ready');
            
            // Tab switching functionality
            $('.htp-nav-tab').on('click', function() {
                const target = $(this).data('target');
                
                // Save the active tab to localStorage
                localStorage.setItem('htp_active_tab', target);
                
                // Update active tab button
                $('.htp-nav-tab').removeClass('htp-nav-tab-active');
                $(this).addClass('htp-nav-tab-active');
                
                // Update active tab content with proper show/hide
                $('.htp-tab-content').removeClass('htp-tab-active').hide();
                $('#htp-' + target).addClass('htp-tab-active').show();
            });
            
            // Add hidden field to form to preserve active tab on submission
            $('.htp-settings-form').append('<input type="hidden" name="active_tab" id="htp-active-tab-field" value="' + activeTab + '">');
            
            // Update the hidden field when tabs are switched
            $('.htp-nav-tab').on('click', function() {
                $('#htp-active-tab-field').val($(this).data('target'));
            });
            
            // Popup customization sub-tabs functionality
            $('.htp-popup-tab').on('click', function(){
                var tab = $(this).data('popup-tab');
                $('.htp-popup-tab').removeClass('htp-popup-tab-active');
                $(this).addClass('htp-popup-tab-active');
                $('.htp-popup-tab-content').hide();
                $('.htp-popup-tab-content-' + tab).show();
            });
            
            // Toggle fields for logged in users
            var $toggleLoggedIn = $('input[name="holdthisproduct_options[enable_popup_customization_logged_in]"]');
            var $fieldsLoggedIn = $('.htp-popup-customization-fields-logged-in');
            $toggleLoggedIn.on('change', function(){
                if($(this).is(':checked')){
                    $fieldsLoggedIn.slideDown();
                }else{
                    $fieldsLoggedIn.slideUp();
                }
            });
            
            // Save button styling is handled via CSS (admin-style.css).
        });
        </script>
        <?php
    }
    
    /**
     * Add product reservations list in inventory tab
     */
    public function add_product_reservations_list() {
        global $post;
        
        if ( ! $post ) return;
        
        $reservations = $this->get_product_reservations( $post->ID );
        
        echo '<div class="options_group">';
        echo '<h4 style="padding-left: 12px;">' . esc_html__( 'Active Reservations', 'hold-this-product' ) . '</h4>';
        
        if ( empty( $reservations ) ) {
            echo '<p>' . esc_html__( 'No active reservations for this product.', 'hold-this-product' ) . '</p>';
        } else {
            echo '<table class="widefat striped" style="margin-top: 10px;">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Customer', 'hold-this-product' ) . '</th>';
            echo '<th>' . esc_html__( 'Expires', 'hold-this-product' ) . '</th>';
            echo '<th>' . esc_html__( 'Action', 'hold-this-product' ) . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ( $reservations as $reservation ) {
                $this->display_product_reservation_row( $reservation );
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }
    
    /**
     * Get active reservations for a specific product
     */
    private function get_product_reservations( $product_id ) {
        return get_posts( array(
            'post_type'      => 'htp_reservation',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'meta_query'     => array(
                array( 'key' => '_htp_status', 'value' => 'active' ),
                array( 'key' => '_htp_product_id', 'value' => $product_id ),
				array( 'key' => '_htp_expires_at', 'value' => time(), 'type' => 'NUMERIC', 'compare' => '>' )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        ) );
    }
    
    /**
     * Display single product reservation row
     */
    private function display_product_reservation_row( $reservation ) {
        $email = get_post_meta( $reservation->ID, '_htp_email', true );
        $name = get_post_meta( $reservation->ID, '_htp_name', true );
        $surname = get_post_meta( $reservation->ID, '_htp_surname', true );
        $expires_ts = (int) get_post_meta( $reservation->ID, '_htp_expires_at', true );
        
        // Determine customer display name
        if ( $reservation->post_author ) {
            $user = get_userdata( $reservation->post_author );
            $customer = $user ? $user->display_name : 'Unknown User';
        } else {
            $customer = trim( $name . ' ' . $surname );
            if ( empty( $customer ) ) {
                $customer = $email;
            } else {
                $customer .= ' (' . $email . ')';
            }
        }
        
		$expires_disp = $expires_ts ? wp_date( 'M j, Y @ H:i', $expires_ts ) : '—';
        
        echo '<tr>';
        echo '<td>' . esc_html( $customer ) . '</td>';
        echo '<td>' . esc_html( $expires_disp ) . '</td>';
        echo '<td>';
        echo '<button type="button" class="button htp-cancel-reservation" ';
        echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
        echo 'data-customer="' . esc_attr( $customer ) . '">';
        echo esc_html__( 'Cancel', 'hold-this-product' );
        echo '</button>';
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        // Hook suffix can vary depending on menu nesting; `page` is stable.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing.
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        // Apply menu icon sizing everywhere in wp-admin; scoped to our menu item only.
        wp_register_style( 'htp-admin-menu-inline', false, array(), HTP_VERSION );
        wp_enqueue_style( 'htp-admin-menu-inline' );
        wp_add_inline_style(
            'htp-admin-menu-inline',
            '#toplevel_page_holdthisproduct-settings .wp-menu-image img{box-sizing:content-box;width:30px;height:30px;padding:2px 0;object-fit:contain;vertical-align:top;}' .
            '#toplevel_page_holdthisproduct-settings .wp-menu-name{font-size:13px;white-space:nowrap;}'
        );

        if ( $hook === 'toplevel_page_holdthisproduct-settings' || $page === 'holdthisproduct-settings' ) {
            wp_enqueue_script( 'jquery' );
            wp_enqueue_style( 'wp-components' );
            wp_enqueue_script( 'wp-components' );
            wp_enqueue_style( 'holdthisproduct-admin-style', HTP_PLUGIN_URL . 'assets/css/admin-style.css', array(), HTP_VERSION );
            
            wp_add_inline_script( 'wp-components', $this->get_admin_inline_script() );
        }
        
        if (
            $hook === 'holdthisproduct_page_holdthisproduct-manage-reservations'
            || $hook === 'holdthisproduct-settings_page_holdthisproduct-manage-reservations'
            || $page === 'holdthisproduct-manage-reservations'
        ) {
            if ( $this->reservations_admin ) {
                $this->reservations_admin->enqueue_assets();
            } else {
                wp_enqueue_script( 'jquery' );
                wp_enqueue_style( 'holdthisproduct-admin-style', HTP_PLUGIN_URL . 'assets/css/admin-style.css', array(), HTP_VERSION );
            }
        }
        
        if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
            global $post;
            if ( $post && $post->post_type === 'product' ) {
                wp_enqueue_script( 'jquery' );
                wp_add_inline_script( 'jquery', $this->get_product_page_script() );
            }
        }
    }


    
    /**
     * Get admin inline script
     */
    private function get_admin_inline_script() {
        return "
            jQuery(document).ready(function($) {
                $('.nav-tab').click(function(e) {
                    e.preventDefault();
                    $('.nav-tab').removeClass('nav-tab-active');
                    $('.tab-content').removeClass('active');
                    $(this).addClass('nav-tab-active');
                    $($(this).attr('href')).addClass('active');
                });

                function toggleMaxReservations() {
                    $('#holdthisproduct-max-reservations-wrapper').toggle(
                        $('input[name=\"holdthisproduct_options[enable_reservation]\"]').is(':checked')
                    );
                }
                
                toggleMaxReservations();
                
                $('input[name=\"holdthisproduct_options[enable_reservation]\"]').on('change', function() {
                    toggleMaxReservations();
                });
            });
        ";
    }
    
    /**
     * Get product page inline script for reservation management
     */
    private function get_product_page_script() {
        return "
            jQuery(document).ready(function($) {
                $('.htp-cancel-reservation').on('click', function() {
                    var \$btn = $(this);
                    var reservationId = \$btn.data('reservation-id');
                    var customer = \$btn.data('customer');
                    
                    if (confirm('Are you sure you want to cancel the reservation for ' + customer + '?')) {
                        \$btn.prop('disabled', true).text('Cancelling...');
                        
                        $.post(ajaxurl, {
                            action: 'htp_cancel_admin_reservation',
                            reservation_id: reservationId,
                            nonce: '" . wp_create_nonce( 'htp_admin_cancel' ) . "'
                        })
                        .done(function(response) {
                            if (response.success) {
                                \$btn.closest('tr').fadeOut(function() {
                                    $(this).remove();
                                });
                                alert('Reservation cancelled successfully.');
                            } else {
                                alert('Error: ' + response.data);
                                \$btn.prop('disabled', false).text('Cancel');
                            }
                        })
                        .fail(function() {
                            alert('Request failed. Please try again.');
                            \$btn.prop('disabled', false).text('Cancel');
                        });
                    }
                });
            });
        ";
    }


}
