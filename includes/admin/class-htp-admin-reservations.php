<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin reservations management (list, filters, actions, AJAX).
 */
class HTP_Admin_Reservations {
	private $reservations;

	public function __construct( $reservations = null ) {
		$this->reservations = $reservations instanceof HTP_Reservations ? $reservations : null;
        add_action( 'wp_ajax_htp_cancel_admin_reservation', array( $this, 'handle_admin_cancel_reservation' ) );
        add_action( 'wp_ajax_htp_delete_admin_reservation', array( $this, 'handle_admin_delete_reservation' ) );
        add_action( 'wp_ajax_htp_approve_reservation', array( $this, 'handle_approve_reservation' ) );
        add_action( 'wp_ajax_htp_deny_reservation', array( $this, 'handle_deny_reservation' ) );
    }

	private function get_reservations_handler() {
		if ( ! $this->reservations ) {
			$this->reservations = new HTP_Reservations();
		}
		return $this->reservations;
	}

    public function enqueue_assets() {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_style( 'holdthisproduct-admin-style', HTP_PLUGIN_URL . 'assets/css/admin-style.css', array(), HTP_VERSION );
    }

    public function render_page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
		$search_query  = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$search_type   = isset( $_GET['search_type'] ) ? sanitize_key( wp_unslash( $_GET['search_type'] ) ) : 'email';
		$page          = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$status_filter = in_array( $status_filter, array( 'all', 'pending_approval', 'active', 'expired', 'cancelled', 'fulfilled', 'denied', 'order_cancelled' ), true ) ? $status_filter : 'all';
		$search_type = in_array( $search_type, array( 'email', 'product', 'product_id', 'customer_name' ), true ) ? $search_type : 'email';

		$query = $this->get_filtered_reservations( $status_filter, $search_query, $search_type, $page );
		$reservations = $query->posts;
        $stats        = $this->get_reservations_summary();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Manage Reservations', 'hold-this-product' ); ?></h1>

            <div class="htp-reservations-stats">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                    <div><strong>Pending Approval:</strong> <?php echo esc_html( $stats['pending_approval'] ); ?></div>
                    <div><strong>Active:</strong> <?php echo esc_html( $stats['active'] ); ?></div>
                    <div><strong>Expired:</strong> <?php echo esc_html( $stats['expired'] ); ?></div>
                    <div><strong>Cancelled:</strong> <?php echo esc_html( $stats['cancelled'] ); ?></div>
                    <div><strong>Fulfilled:</strong> <?php echo esc_html( $stats['fulfilled'] ); ?></div>
                    <div><strong>Denied:</strong> <?php echo esc_html( $stats['denied'] ); ?></div>
					<div><strong>Order Cancelled:</strong> <?php echo esc_html( $stats['order_cancelled'] ); ?></div>
                    <div><strong>Total:</strong> <?php echo esc_html( $stats['total'] ); ?></div>
                </div>
            </div>

            <div class="tablenav top" style="margin: 20px 0;">
                <div class="alignleft actions">
                    <select name="status_filter" id="status-filter">
                        <option value="all" <?php selected( $status_filter, 'all' ); ?>><?php esc_html_e( 'All Statuses', 'hold-this-product' ); ?></option>
                        <option value="pending_approval" <?php selected( $status_filter, 'pending_approval' ); ?>><?php esc_html_e( 'Pending Approval', 'hold-this-product' ); ?></option>
                        <option value="active" <?php selected( $status_filter, 'active' ); ?>><?php esc_html_e( 'Active', 'hold-this-product' ); ?></option>
                        <option value="expired" <?php selected( $status_filter, 'expired' ); ?>><?php esc_html_e( 'Expired', 'hold-this-product' ); ?></option>
                        <option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'hold-this-product' ); ?></option>
                        <option value="fulfilled" <?php selected( $status_filter, 'fulfilled' ); ?>><?php esc_html_e( 'Fulfilled', 'hold-this-product' ); ?></option>
                        <option value="denied" <?php selected( $status_filter, 'denied' ); ?>><?php esc_html_e( 'Denied', 'hold-this-product' ); ?></option>
						<option value="order_cancelled" <?php selected( $status_filter, 'order_cancelled' ); ?>><?php esc_html_e( 'Order Cancelled', 'hold-this-product' ); ?></option>
                    </select>

                    <select name="search_type" id="search-type">
                        <option value="email" <?php selected( $search_type, 'email' ); ?>><?php esc_html_e( 'Email', 'hold-this-product' ); ?></option>
                        <option value="product" <?php selected( $search_type, 'product' ); ?>><?php esc_html_e( 'Product Name', 'hold-this-product' ); ?></option>
                        <option value="product_id" <?php selected( $search_type, 'product_id' ); ?>><?php esc_html_e( 'Product ID', 'hold-this-product' ); ?></option>
                        <option value="customer_name" <?php selected( $search_type, 'customer_name' ); ?>><?php esc_html_e( 'Customer', 'hold-this-product' ); ?></option>
                    </select>

                    <input type="search" id="reservation-search" placeholder="<?php esc_attr_e( 'Search reservations...', 'hold-this-product' ); ?>" value="<?php echo esc_attr( $search_query ); ?>" style="width: 200px;">

                    <button type="button" class="button" id="filter-reservations"><?php esc_html_e( 'Filter', 'hold-this-product' ); ?></button>
                    <button type="button" class="button" id="clear-filters"><?php esc_html_e( 'Clear', 'hold-this-product' ); ?></button>
                </div>

                <div class="alignright">
					<span class="displaying-num"><?php /* translators: %d: number of reservations. */ printf( esc_html__( '%d reservations', 'hold-this-product' ), esc_html( number_format_i18n( $query->found_posts ) ) ); ?></span>
                </div>
            </div>

            <?php if ( empty( $reservations ) ) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'No reservations found matching your criteria.', 'hold-this-product' ); ?></p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 20%;"><?php esc_html_e( 'Product', 'hold-this-product' ); ?></th>
                            <th style="width: 20%;"><?php esc_html_e( 'Customer', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Status', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Reserved', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Expires', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Time Left', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Actions', 'hold-this-product' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $reservations as $reservation ) : ?>
                            <?php $this->render_row( $reservation ); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
				<?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array( 'paged' => '%#%', 'status' => $status_filter, 'search_type' => $search_type, 'search' => $search_query ) ), 'current' => $page, 'total' => max( 1, (int) $query->max_num_pages ) ) ) ); ?>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            function showAdminNotice(type, message) {
                $('.htp-inline-notice').remove();
                var $notice = $('<div class="notice htp-inline-notice is-dismissible"></div>').addClass('notice-' + type);
                $('<p></p>').text(message).appendTo($notice);
                $notice.insertAfter('.wrap h1');
            }

            function createActionButton(actionClass, label, reservationId, customer, product) {
                return $('<button></button>', {
                    type: 'button',
                    class: 'button button-small ' + actionClass,
                    text: label
                }).attr({
                    'data-reservation-id': reservationId,
                    'data-customer': customer,
                    'data-product': product
                });
            }

            $('#filter-reservations').on('click', function() {
                var status = $('#status-filter').val();
                var searchType = $('#search-type').val();
                var search = $('#reservation-search').val();

                var url = new URL(window.location);
                url.searchParams.set('status', status);
                url.searchParams.set('search_type', searchType);
                if (search) {
                    url.searchParams.set('search', search);
                } else {
                    url.searchParams.delete('search');
                }
                window.location.href = url.toString();
            });

            $('#clear-filters').on('click', function() {
                var url = new URL(window.location);
                url.searchParams.delete('status');
                url.searchParams.delete('search');
                url.searchParams.delete('search_type');
                window.location.href = url.toString();
            });

            $('#reservation-search').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#filter-reservations').click();
                }
            });

            $(document).on('click', '.htp-delete-reservation', function() {
                var $btn = $(this);
                var reservationId = $btn.data('reservation-id');
                var customer = $btn.data('customer');
                var product = $btn.data('product') || 'this product';

                if (confirm('Are you sure you want to permanently delete the reservation for ' + customer + ' on ' + product + '? This action cannot be undone.')) {
                    $btn.prop('disabled', true).text('Deleting...');

                    $.post(ajaxurl, {
                        action: 'htp_delete_admin_reservation',
                        reservation_id: reservationId,
                        nonce: '<?php echo esc_js( wp_create_nonce( 'htp_admin_delete' ) ); ?>'
                    })
                    .done(function(response) {
                        if (response.success) {
                            $btn.closest('tr').fadeOut(function() {
                                $(this).remove();
                                var $displayNum = $('.displaying-num');
                                if ($displayNum.length > 0) {
                                    var currentText = $displayNum.text();
                                    var currentNum = parseInt(currentText.match(/\\d+/));
                                    if (currentNum > 0) {
                                        $displayNum.text((currentNum - 1) + ' reservations');
                                    }
                                }
                            });

                            showAdminNotice('success', 'Reservation deleted successfully.');
                        } else {
                            showAdminNotice('error', response.data || 'Reservation could not be deleted.');
                            $btn.prop('disabled', false).text('Delete');
                        }
                    })
                    .fail(function() {
                        showAdminNotice('error', 'Request failed. Please try again.');
                        $btn.prop('disabled', false).text('Delete');
                    });
                }
            });

            $(document).on('click', '.htp-approve-reservation', function() {
                var $btn = $(this);
                var reservationId = $btn.data('reservation-id');
                var customer = $btn.data('customer');
                var product = $btn.data('product') || 'this product';

                if (confirm('Are you sure you want to approve the reservation for ' + customer + ' on ' + product + '?')) {
                    $btn.prop('disabled', true).text('Approving...');

                    $.post(ajaxurl, {
                        action: 'htp_approve_reservation',
                        reservation_id: reservationId,
                        nonce: '<?php echo esc_js( wp_create_nonce( 'htp_admin_approve' ) ); ?>'
                    })
                    .done(function(response) {
                        if (response.success) {
                            var $row = $btn.closest('tr');
                            var $actionsCell = $row.find('td:last-child');
                            $actionsCell.empty().append(
                                createActionButton('htp-cancel-reservation', 'Cancel', reservationId, customer, product)
                            );

                            var $statusCell = $row.find('td:nth-child(3) span');
                            $statusCell.removeClass('status-pending-approval').addClass('status-active').text('Active');

                            showAdminNotice('success', 'Reservation approved successfully.');
                        } else {
                            showAdminNotice('error', response.data || 'Reservation could not be approved.');
                            $btn.prop('disabled', false).text('Approve');
                        }
                    })
                    .fail(function() {
                        showAdminNotice('error', 'Request failed. Please try again.');
                        $btn.prop('disabled', false).text('Approve');
                    });
                }
            });

            $(document).on('click', '.htp-deny-reservation', function() {
                var $btn = $(this);
                var reservationId = $btn.data('reservation-id');
                var customer = $btn.data('customer');
                var product = $btn.data('product') || 'this product';

                var reason = prompt('Please provide a reason for denying this reservation (optional):');
                if (reason !== null) {
                    $btn.prop('disabled', true).text('Denying...');

                    $.post(ajaxurl, {
                        action: 'htp_deny_reservation',
                        reservation_id: reservationId,
                        reason: reason,
                        nonce: '<?php echo esc_js( wp_create_nonce( 'htp_admin_deny' ) ); ?>'
                    })
                    .done(function(response) {
                        if (response.success) {
                            var $row = $btn.closest('tr');
                            var $actionsCell = $row.find('td:last-child');
                            $actionsCell.empty().append(
                                createActionButton('button-link-delete htp-delete-reservation', 'Delete', reservationId, customer, product)
                            );

                            var $statusCell = $row.find('td:nth-child(3) span');
                            $statusCell.removeClass('status-pending-approval').addClass('status-denied').text('Denied');

                            $row.find('td:nth-child(6)').text('—').removeClass('time-left-critical time-left-warning');

                            showAdminNotice('success', 'Reservation denied successfully.');
                        } else {
                            showAdminNotice('error', response.data || 'Reservation could not be denied.');
                            $btn.prop('disabled', false).text('Deny');
                        }
                    })
                    .fail(function() {
                        showAdminNotice('error', 'Request failed. Please try again.');
                        $btn.prop('disabled', false).text('Deny');
                    });
                }
            });

            $(document).on('click', '.htp-cancel-reservation', function() {
                var $btn = $(this);
                var reservationId = $btn.data('reservation-id');
                var customer = $btn.data('customer');
                var product = $btn.data('product') || 'this product';

                if (!reservationId) {
                    showAdminNotice('error', 'Missing reservation ID.');
                    return;
                }

                if (confirm('Are you sure you want to cancel the reservation for ' + customer + ' on ' + product + '?')) {
                    $btn.prop('disabled', true).text('Cancelling...');

                    $.post(ajaxurl, {
                        action: 'htp_cancel_admin_reservation',
                        reservation_id: reservationId,
                        nonce: '<?php echo esc_js( wp_create_nonce( 'htp_admin_cancel' ) ); ?>'
                    })
                    .done(function(response) {
                        if (response.success) {
                            var $row = $btn.closest('tr');
                            $row.find('td:nth-child(3) span').removeClass().addClass('status-cancelled').text('Cancelled');
                            $row.find('td:nth-child(6)').text('—').removeClass('time-left-critical time-left-warning');
                            $row.find('td:last-child').empty().append(
                                createActionButton('button-link-delete htp-delete-reservation', 'Delete', reservationId, customer, product)
                            );
                            showAdminNotice('success', 'Reservation cancelled successfully.');
                        } else {
                            showAdminNotice('error', response.data || 'Reservation could not be cancelled.');
                            $btn.prop('disabled', false).text('Cancel');
                        }
                    })
                    .fail(function() {
                        showAdminNotice('error', 'Request failed. Please try again.');
                        $btn.prop('disabled', false).text('Cancel');
                    });
                }
            });
        });
        </script>
        <?php
    }

    public function handle_admin_cancel_reservation() {
	        if ( ! current_user_can( htp_get_manage_capability() ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        check_ajax_referer( 'htp_admin_cancel', 'nonce' );

		$reservation_id = isset( $_POST['reservation_id'] ) ? absint( wp_unslash( $_POST['reservation_id'] ) ) : 0;
		if ( ! $reservation_id || 'htp_reservation' !== get_post_type( $reservation_id ) ) {
            wp_send_json_error( 'Invalid reservation ID.' );
        }

        $status = get_post_meta( $reservation_id, '_htp_status', true );
        if ( $status !== 'active' ) {
            wp_send_json_error( 'Reservation is not active.' );
        }

			$result = $this->get_reservations_handler()->cancel_reservation( $reservation_id );
			if ( ! $result ) {
				wp_send_json_error( 'Reservation changed before it could be cancelled.' );
			}

		update_post_meta( $reservation_id, '_htp_cancelled_by_admin', time() );
        update_post_meta( $reservation_id, '_htp_cancelled_by_user', get_current_user_id() );

        wp_send_json_success( 'Reservation cancelled successfully.' );
    }

    public function handle_admin_delete_reservation() {
	        if ( ! current_user_can( htp_get_manage_capability() ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        check_ajax_referer( 'htp_admin_delete', 'nonce' );

		$reservation_id = isset( $_POST['reservation_id'] ) ? absint( wp_unslash( $_POST['reservation_id'] ) ) : 0;
		if ( ! $reservation_id || 'htp_reservation' !== get_post_type( $reservation_id ) ) {
            wp_send_json_error( 'Invalid reservation ID.' );
        }

        $status = get_post_meta( $reservation_id, '_htp_status', true );
        if ( $status === 'active' ) {
            wp_send_json_error( 'Cannot delete active reservations. Cancel them first.' );
        }

        $result = wp_delete_post( $reservation_id, true );
        if ( $result ) {
            wp_send_json_success( 'Reservation deleted successfully.' );
        }

        wp_send_json_error( 'Failed to delete reservation.' );
    }

    public function handle_approve_reservation() {
	        if ( ! current_user_can( htp_get_manage_capability() ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        check_ajax_referer( 'htp_admin_approve', 'nonce' );

		$reservation_id = isset( $_POST['reservation_id'] ) ? absint( wp_unslash( $_POST['reservation_id'] ) ) : 0;
        if ( ! $reservation_id ) {
            wp_send_json_error( 'Invalid reservation ID.' );
        }

        $post = get_post( $reservation_id );
        if ( ! $post || $post->post_type !== 'htp_reservation' ) {
            wp_send_json_error( 'Invalid reservation.' );
        }

		$result = $this->get_reservations_handler()->approve_reservation( $reservation_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        } elseif ( $result ) {
            wp_send_json_success( 'Reservation approved successfully.' );
        }

        wp_send_json_error( 'Failed to approve reservation.' );
    }

    public function handle_deny_reservation() {
	        if ( ! current_user_can( htp_get_manage_capability() ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        check_ajax_referer( 'htp_admin_deny', 'nonce' );

		$reservation_id = isset( $_POST['reservation_id'] ) ? absint( wp_unslash( $_POST['reservation_id'] ) ) : 0;
		$reason         = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

        if ( ! $reservation_id ) {
            wp_send_json_error( 'Invalid reservation ID.' );
        }

        $post = get_post( $reservation_id );
        if ( ! $post || $post->post_type !== 'htp_reservation' ) {
            wp_send_json_error( 'Invalid reservation.' );
        }

		$result = $this->get_reservations_handler()->deny_reservation( $reservation_id, $reason );

        if ( $result ) {
            wp_send_json_success( 'Reservation denied successfully.' );
        }

        wp_send_json_error( 'Failed to deny reservation.' );
    }

	private function get_filtered_reservations( $status_filter = 'all', $search_query = '', $search_type = 'email', $page = 1 ) {
	        global $wpdb;

	        $meta_query = array();
			$customer_reservation_ids = null;
			$force_empty = false;

        if ( $status_filter !== 'all' ) {
            $meta_query[] = array(
                'key'     => '_htp_status',
                'value'   => $status_filter,
                'compare' => '=',
            );
        }

        if ( $search_query !== '' ) {
            switch ( $search_type ) {
                case 'email':
                    $meta_query[] = array(
                        'key'     => '_htp_email',
                        'value'   => $search_query,
                        'compare' => 'LIKE',
                    );
                    break;

                case 'product':
                    $product_ids = $wpdb->get_col( $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_title LIKE %s",
                        '%' . $wpdb->esc_like( $search_query ) . '%'
                    ) );

	                    if ( empty( $product_ids ) ) {
	                        $force_empty = true;
	                        break;
                    }

                    $meta_query[] = array(
                        'key'     => '_htp_product_id',
                        'value'   => $product_ids,
                        'compare' => 'IN',
                    );
                    break;

                case 'product_id':
	                    if ( ! is_numeric( $search_query ) ) {
	                        $force_empty = true;
	                        break;
                    }
                    $meta_query[] = array(
                        'key'     => '_htp_product_id',
                        'value'   => absint( $search_query ),
                        'compare' => '=',
                    );
                    break;

                case 'customer_name':
					$customer_reservation_ids = $this->get_customer_match_ids( $search_query );
                    break;
            }
        }

        $args = array(
            'post_type'      => 'htp_reservation',
            'post_status'    => 'publish',
			'posts_per_page' => 25,
			'paged'          => max( 1, absint( $page ) ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( ! empty( $meta_query ) ) {
            $args['meta_query'] = $meta_query;
        }
			if ( null !== $customer_reservation_ids ) {
				$args['post__in'] = $customer_reservation_ids ? $customer_reservation_ids : array( 0 );
			}
			if ( $force_empty ) {
				$args['post__in'] = array( 0 );
			}

		return new WP_Query( $args );
    }

    /**
     * Get reservation IDs matching logged-in or guest customer data.
     *
     * @param string $search_query Search query.
     * @return int[]
     */
    private function get_customer_match_ids( $search_query ) {
        $search_query = trim( $search_query );
        if ( '' === $search_query ) {
            return array();
        }

        $user_query = new WP_User_Query( array(
            'search'         => '*' . $search_query . '*',
            'search_columns' => array( 'display_name', 'user_email', 'user_login' ),
            'fields'         => 'ID',
            'number'         => 100,
        ) );

        $author_ids = array_map( 'absint', $user_query->get_results() );
        $matches    = array();

        if ( $author_ids ) {
            $matches = get_posts( array(
                'post_type'      => 'htp_reservation',
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
                'author__in'     => $author_ids,
            ) );
        }

        $guest_matches = get_posts( array(
            'post_type'      => 'htp_reservation',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'author__in'      => array( 0 ),
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_htp_name',
                    'value'   => $search_query,
                    'compare' => 'LIKE',
                ),
                array(
                    'key'     => '_htp_surname',
                    'value'   => $search_query,
                    'compare' => 'LIKE',
                ),
            ),
        ) );

        return array_values( array_unique( array_map( 'absint', array_merge( $matches, $guest_matches ) ) ) );
    }

    private function get_reservations_summary() {
		$cached = wp_cache_get( 'admin_summary', 'holdthisproduct' );
		if ( false !== $cached ) {
			return $cached;
		}
        global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT pm.meta_value AS reservation_status, COUNT(*) AS reservation_count
			FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_htp_status'
			WHERE p.post_type = 'htp_reservation' AND p.post_status = 'publish' GROUP BY pm.meta_value",
			OBJECT_K
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No external values are interpolated.
			$summary = array( 'total' => 0, 'pending_approval' => 0, 'active' => 0, 'expired' => 0, 'cancelled' => 0, 'fulfilled' => 0, 'denied' => 0, 'order_cancelled' => 0 );
		foreach ( (array) $rows as $status => $row ) {
			if ( isset( $summary[ $status ] ) ) {
				$summary[ $status ] = (int) $row->reservation_count;
				$summary['total'] += (int) $row->reservation_count;
			}
		}
		wp_cache_set( 'admin_summary', $summary, 'holdthisproduct', MINUTE_IN_SECONDS );
		return $summary;
    }

    private function render_row( $reservation ) {
        $product_id  = (int) get_post_meta( $reservation->ID, '_htp_product_id', true );
        $email       = get_post_meta( $reservation->ID, '_htp_email', true );
        $name        = get_post_meta( $reservation->ID, '_htp_name', true );
        $surname     = get_post_meta( $reservation->ID, '_htp_surname', true );
        $expires_ts  = (int) get_post_meta( $reservation->ID, '_htp_expires_at', true );
        $status      = get_post_meta( $reservation->ID, '_htp_status', true );

        $product          = wc_get_product( $product_id );
        $product_name     = $product ? $product->get_name() : 'Unknown Product (ID: ' . $product_id . ')';
        $product_edit_url = $product ? admin_url( 'post.php?post=' . $product_id . '&action=edit' ) : '#';

        if ( $reservation->post_author ) {
            $user           = get_userdata( $reservation->post_author );
            $customer       = $user ? $user->display_name . ' (' . $user->user_email . ')' : 'Unknown User';
            $customer_short = $user ? $user->display_name : 'Unknown User';
        } else {
            $customer_full = trim( $name . ' ' . $surname );
            if ( $customer_full === '' ) {
                $customer       = $email ?: __( 'No email', 'hold-this-product' );
                $customer_short = $email ?: __( 'No email', 'hold-this-product' );
            } else {
                $customer       = $customer_full . ' (' . $email . ')';
                $customer_short = $customer_full;
            }
        }

        $reserved_date = get_the_date( 'M j, Y @ H:i', $reservation );
		$expires_disp  = $expires_ts ? wp_date( 'M j, Y @ H:i', $expires_ts ) : '—';

        $time_left  = '—';
        $time_class = '';
	        if ( $expires_ts && in_array( $status, array( 'active', 'pending_approval' ), true ) ) {
			$diff = $expires_ts - time();
            if ( $diff > 0 ) {
                $days    = floor( $diff / DAY_IN_SECONDS );
                $hours   = floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
                $minutes = floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

                if ( $days > 0 ) {
                    $time_left = sprintf( '%dd %dh', $days, $hours );
                } elseif ( $hours > 0 ) {
                    $time_left = sprintf( '%dh %dm', $hours, $minutes );
                } else {
                    $time_left = sprintf( '%dm', $minutes );
                }

                if ( $diff < 2 * HOUR_IN_SECONDS ) {
                    $time_class = 'time-left-critical';
                } elseif ( $diff < 6 * HOUR_IN_SECONDS ) {
                    $time_class = 'time-left-warning';
                }
            } else {
                $time_left  = 'Expired';
                $time_class = 'time-left-critical';
            }
        }

        $status_class   = 'status-' . str_replace( '_', '-', $status );
	        $status_display = ucwords( str_replace( '_', ' ', $status ) );

        echo '<tr>';
        echo '<td>';
        if ( $product ) {
            echo '<a href="' . esc_url( $product_edit_url ) . '" target="_blank">' . esc_html( $product_name ) . '</a>';
        } else {
            echo esc_html( $product_name );
        }
        echo '</td>';
        echo '<td title="' . esc_attr( $customer ) . '">' . esc_html( $customer ) . '</td>';
        echo '<td><span class="' . esc_attr( $status_class ) . '">' . esc_html( $status_display ) . '</span></td>';
        echo '<td>' . esc_html( $reserved_date ) . '</td>';
        echo '<td>' . esc_html( $expires_disp ) . '</td>';
        echo '<td class="' . esc_attr( $time_class ) . '">' . esc_html( $time_left ) . '</td>';
        echo '<td>';

        if ( $status === 'pending_approval' ) {
            echo '<button type="button" class="button button-small htp-approve-reservation" ';
            echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
            echo 'data-customer="' . esc_attr( $customer_short ) . '" ';
            echo 'data-product="' . esc_attr( $product_name ) . '" style="margin-right: 5px;">';
            echo esc_html__( 'Approve', 'hold-this-product' );
            echo '</button>';

            echo '<button type="button" class="button button-small button-link-delete htp-deny-reservation" ';
            echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
            echo 'data-customer="' . esc_attr( $customer_short ) . '" ';
            echo 'data-product="' . esc_attr( $product_name ) . '">';
            echo esc_html__( 'Deny', 'hold-this-product' );
            echo '</button>';
        } elseif ( $status === 'active' ) {
            echo '<button type="button" class="button button-small htp-cancel-reservation" ';
            echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
            echo 'data-customer="' . esc_attr( $customer_short ) . '" ';
            echo 'data-product="' . esc_attr( $product_name ) . '">';
            echo esc_html__( 'Cancel', 'hold-this-product' );
            echo '</button>';
        } else {
            echo '<button type="button" class="button button-small button-link-delete htp-delete-reservation" ';
            echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
            echo 'data-customer="' . esc_attr( $customer_short ) . '" ';
            echo 'data-product="' . esc_attr( $product_name ) . '">';
            echo esc_html__( 'Delete', 'hold-this-product' );
            echo '</button>';
        }

        echo '</td>';
        echo '</tr>';
    }
}
