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
            htp_get_manage_capability(),
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
            htp_get_manage_capability(),
            'holdthisproduct-settings',
            array( $this, 'settings_page' )
        );

        // Add reservations management submenu
        add_submenu_page(
            'holdthisproduct-settings',
			__( 'Reservations', 'hold-this-product' ),
			__( 'Reservations', 'hold-this-product' ),
            htp_get_manage_capability(),
            'holdthisproduct-manage-reservations',
            $this->reservations_admin ? array( $this->reservations_admin, 'render_page' ) : '__return_null'
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting(
            'holdthisproduct_options_group',
            'holdthisproduct_options',
            array(
                'sanitize_callback' => array( $this, 'sanitize_options' ),
                'default'           => array(),
            )
        );
        
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
			'holdthisproduct_pending_duration' => __( 'Approval Request Duration (hours)', 'hold-this-product' ),
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

    /**
     * Sanitize plugin options before saving.
     *
     * @param array $input Raw option input.
     * @return array
     */
    public function sanitize_options( $input ) {
        $input     = is_array( $input ) ? $input : array();
        $current   = get_option( 'holdthisproduct_options', array() );
        $current   = is_array( $current ) ? $current : array();
        $sanitized = array();

        $sanitized['enable_reservation']              = ! empty( $input['enable_reservation'] ) ? 1 : 0;
        $sanitized['enable_email_notifications']      = ! empty( $input['enable_email_notifications'] ) ? 1 : 0;
        $sanitized['require_admin_approval']          = ! empty( $input['require_admin_approval'] ) ? 1 : 0;
        $sanitized['enable_popup_customization_logged_in'] = ! empty( $input['enable_popup_customization_logged_in'] ) ? 1 : 0;

        $max_reservations = isset( $input['max_reservations'] ) ? (int) $input['max_reservations'] : 1;
        if ( $max_reservations < 1 ) {
            $max_reservations = 1;
            add_settings_error( 'holdthisproduct_options', 'htp_max_reservations', __( 'Max reservations must be at least 1.', 'hold-this-product' ) );
        } elseif ( $max_reservations > 100 ) {
            $max_reservations = 100;
            add_settings_error( 'holdthisproduct_options', 'htp_max_reservations_max', __( 'Max reservations cannot exceed 100.', 'hold-this-product' ) );
        }
        $sanitized['max_reservations'] = $max_reservations;

        $reservation_duration = isset( $input['reservation_duration'] ) ? (int) $input['reservation_duration'] : 24;
        if ( $reservation_duration < 1 ) {
            $reservation_duration = 1;
            add_settings_error( 'holdthisproduct_options', 'htp_duration_min', __( 'Reservation duration must be at least 1 hour.', 'hold-this-product' ) );
        } elseif ( $reservation_duration > 168 ) {
            $reservation_duration = 168;
            add_settings_error( 'holdthisproduct_options', 'htp_duration_max', __( 'Reservation duration cannot exceed 168 hours.', 'hold-this-product' ) );
        }
        $sanitized['reservation_duration'] = $reservation_duration;

	        $pending_duration = isset( $input['pending_duration'] )
	            ? (int) $input['pending_duration']
	            : ( isset( $current['pending_duration'] ) ? (int) $current['pending_duration'] : $reservation_duration );
		if ( $pending_duration < 1 ) {
			$pending_duration = 1;
			add_settings_error( 'holdthisproduct_options', 'htp_pending_duration_min', __( 'Approval request duration must be at least 1 hour.', 'hold-this-product' ) );
		} elseif ( $pending_duration > 168 ) {
			$pending_duration = 168;
			add_settings_error( 'holdthisproduct_options', 'htp_pending_duration_max', __( 'Approval request duration cannot exceed 168 hours.', 'hold-this-product' ) );
		}
	        $sanitized['pending_duration'] = $pending_duration;

        $sanitized['popup_customization_logged_in'] = $this->sanitize_popup_customization(
            isset( $input['popup_customization_logged_in'] ) ? $input['popup_customization_logged_in'] : array()
        );

        return $sanitized;
    }

    /**
     * Sanitize popup customization settings.
     *
     * @param array $settings Raw popup settings.
     * @return array
     */
    private function sanitize_popup_customization( $settings ) {
        $settings      = is_array( $settings ) ? $settings : array();
        $allowed_fonts = $this->get_popup_font_choices();

        $raw_border_radius = isset( $settings['border_radius'] ) ? (int) $settings['border_radius'] : 12;
        $raw_font_size     = isset( $settings['font_size'] ) ? (int) $settings['font_size'] : 16;
        $border_radius = isset( $settings['border_radius'] ) ? (int) $settings['border_radius'] : 12;
        $font_size     = isset( $settings['font_size'] ) ? (int) $settings['font_size'] : 16;
        $border_radius = max( 0, min( 50, $border_radius ) );
        $font_size     = max( 10, min( 40, $font_size ) );

        if ( $raw_border_radius !== $border_radius ) {
            add_settings_error( 'holdthisproduct_options', 'htp_popup_border_radius', __( 'Popup border radius must be between 0 and 50 pixels.', 'hold-this-product' ) );
        }

        if ( $raw_font_size !== $font_size ) {
            add_settings_error( 'holdthisproduct_options', 'htp_popup_font_size', __( 'Popup font size must be between 10 and 40 pixels.', 'hold-this-product' ) );
        }

        $background_color = $this->sanitize_hex_color_or_default( $settings['background_color'] ?? '', '#ffffff' );
        $text_color       = $this->sanitize_hex_color_or_default( $settings['text_color'] ?? '', '#222222' );
        $font_family      = isset( $settings['font_family'] ) ? sanitize_text_field( $settings['font_family'] ) : '';

        if ( isset( $settings['background_color'] ) && $background_color !== $settings['background_color'] ) {
            add_settings_error( 'holdthisproduct_options', 'htp_popup_background_color', __( 'Popup background color was invalid and has been reset to the default.', 'hold-this-product' ) );
        }

        if ( isset( $settings['text_color'] ) && $text_color !== $settings['text_color'] ) {
            add_settings_error( 'holdthisproduct_options', 'htp_popup_text_color', __( 'Popup text color was invalid and has been reset to the default.', 'hold-this-product' ) );
        }

        if ( '' !== $font_family && ! isset( $allowed_fonts[ $font_family ] ) ) {
            add_settings_error( 'holdthisproduct_options', 'htp_popup_font_family', __( 'Popup font family was invalid and has been reset to the default.', 'hold-this-product' ) );
        }

        return array(
            'border_radius'   => $border_radius,
            'background_color'=> $background_color,
            'font_family'     => isset( $allowed_fonts[ $font_family ] ) ? $font_family : 'Arial, Helvetica, sans-serif',
            'font_size'       => $font_size,
            'text_color'      => $text_color,
        );
    }

    /**
     * Sanitize a color value with fallback.
     *
     * @param string $value Raw color.
     * @param string $default Default color.
     * @return string
     */
    private function sanitize_hex_color_or_default( $value, $default ) {
        $sanitized = sanitize_hex_color( $value );
        return $sanitized ? $sanitized : $default;
    }

    /**
     * Get supported popup font choices.
     *
     * @return array<string,string>
     */
    private function get_popup_font_choices() {
        return array(
            'Arial, Helvetica, sans-serif'        => 'Arial',
            'Verdana, Geneva, sans-serif'         => 'Verdana',
            'Georgia, serif'                      => 'Georgia',
            'Times New Roman, Times, serif'       => 'Times New Roman',
            'Tahoma, Geneva, sans-serif'          => 'Tahoma',
            'Trebuchet MS, Helvetica, sans-serif' => 'Trebuchet MS',
            'Courier New, Courier, monospace'     => 'Courier New',
            'Roboto, sans-serif'                  => 'Roboto (Google)',
            'Open Sans, sans-serif'               => 'Open Sans (Google)',
            'Lato, sans-serif'                    => 'Lato (Google)',
            'Montserrat, sans-serif'              => 'Montserrat (Google)',
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
                    <input type="number" name="holdthisproduct_options[max_reservations]" value="' . esc_attr( $value ) . '" class="holdthisproduct-small-input" />
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
                        <input type="number" name="holdthisproduct_options[reservation_duration]" value="' . esc_attr( $value ) . '" class="holdthisproduct-small-input" />
                    </div>
                </div>
                <p class="description">How long reservations last (1-168 hours, default: 24)</p>
              </div>';
    }

    /** Approval request duration field callback. */
    public function holdthisproduct_pending_duration_callback() {
        $options = get_option( 'holdthisproduct_options', array() );
        $value = isset( $options['pending_duration'] ) ? absint( $options['pending_duration'] ) : 24;
        echo '<div class="htp-setting-field">
                <div class="htp-setting-control">
                    <div class="htp-input-right-align">
                        <input type="number" min="1" max="168" name="holdthisproduct_options[pending_duration]" value="' . esc_attr( $value ) . '" class="holdthisproduct-small-input" />
                    </div>
                </div>
                <p class="description">How long an approval request remains open. The active reservation duration starts when it is approved.</p>
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
                <?php settings_errors( 'holdthisproduct_options' ); ?>
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
                                                <td><input type="number" name="holdthisproduct_options[popup_customization_logged_in][border_radius]" value="<?php echo esc_attr($popup_settings_logged_in['border_radius'] ?? '12'); ?>" class="htp-input-right-align"></td>
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
                                                        $fonts = $this->get_popup_font_choices();
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
                                                <td><input type="number" name="holdthisproduct_options[popup_customization_logged_in][font_size]" value="<?php echo esc_attr($popup_settings_logged_in['font_size'] ?? '16'); ?>" class="htp-input-right-align"></td>
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
				array( 'key' => HTP_Reservation_Meta::STATUS, 'value' => HTP_Reservation_Status::ACTIVE ),
				array( 'key' => HTP_Reservation_Meta::PRODUCT_ID, 'value' => $product_id ),
				array( 'key' => HTP_Reservation_Meta::EXPIRES_AT, 'value' => time(), 'type' => 'NUMERIC', 'compare' => '>' )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        ) );
    }
    
    /**
     * Display single product reservation row
     */
    private function display_product_reservation_row( $reservation ) {
		$email = HTP_Reservation_Meta::get( $reservation->ID, HTP_Reservation_Meta::EMAIL );
		$name = HTP_Reservation_Meta::get( $reservation->ID, HTP_Reservation_Meta::NAME );
		$surname = HTP_Reservation_Meta::get( $reservation->ID, HTP_Reservation_Meta::SURNAME );
		$expires_ts = (int) HTP_Reservation_Meta::get( $reservation->ID, HTP_Reservation_Meta::EXPIRES_AT );
        
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
			wp_enqueue_style( 'wp-components' );
			wp_enqueue_style( 'holdthisproduct-admin-style', HTP_PLUGIN_URL . 'assets/css/admin-style.css', array(), HTP_VERSION );
			wp_enqueue_script( 'holdthisproduct-admin-settings', HTP_PLUGIN_URL . 'assets/js/admin-settings.js', array( 'jquery' ), HTP_VERSION, true );
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
				wp_enqueue_script( 'holdthisproduct-admin-product', HTP_PLUGIN_URL . 'assets/js/admin-product.js', array( 'jquery' ), HTP_VERSION, true );
				wp_localize_script(
					'holdthisproduct-admin-product',
					'htpProductReservations',
					array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'htp_admin_cancel' ),
						'strings' => array(
							/* translators: %s: customer name. */
							'confirmCancel' => __( 'Cancel the reservation for %s?', 'hold-this-product' ),
							'cancelling'    => __( 'Cancelling...', 'hold-this-product' ),
							'cancelled'     => __( 'Reservation cancelled successfully.', 'hold-this-product' ),
							'cancel'        => __( 'Cancel', 'hold-this-product' ),
							'failed'        => __( 'Reservation could not be cancelled.', 'hold-this-product' ),
							'requestFailed' => __( 'Request failed. Please try again.', 'hold-this-product' ),
						),
					)
				);
			}
		}
    }
}
