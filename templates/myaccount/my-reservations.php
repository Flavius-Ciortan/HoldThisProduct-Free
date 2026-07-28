<?php
/**
 * My Account - Reservations
 *
 * @package HoldThisProduct
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

<div class="htp-reservations-container">
    <div class="htp-reservations-wrapper">
        <div class="htp-reservations-header">
            <h2><?php esc_html_e( 'My Reserved Products', 'hold-this-product' ); ?></h2>
            <p><?php esc_html_e( 'View your reservation history and manage active reservations.', 'hold-this-product' ); ?></p>
        </div>
        
        <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table htp-reservations-table">
    <thead>
        <tr>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-product">
                <span class="nobr"><?php esc_html_e( 'Product', 'hold-this-product' ); ?></span>
            </th>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-status">
                <span class="nobr"><?php esc_html_e( 'Status', 'hold-this-product' ); ?></span>
            </th>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-expires">
                <span class="nobr"><?php esc_html_e( 'Expires', 'hold-this-product' ); ?></span>
            </th>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-time-left">
                <span class="nobr"><?php esc_html_e( 'Time Left', 'hold-this-product' ); ?></span>
            </th>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-actions">
                <span class="nobr"><?php esc_html_e( 'Actions', 'hold-this-product' ); ?></span>
            </th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $reservations as $reservation ) : ?>
            <?php
            $product_id = (int) get_post_meta( $reservation->ID, '_htp_product_id', true );
            $status = (string) get_post_meta( $reservation->ID, '_htp_status', true );
            $expires_ts = (int) get_post_meta( $reservation->ID, '_htp_expires_at', true );
            $product = wc_get_product( $product_id );
            
            if ( ! $product ) {
                continue;
            }

            $is_pending = ( $status === 'pending_approval' );
            $is_active  = ( $status === 'active' );
            $is_expired = ( $status === 'expired' );

            $expires_disp = ( ( $is_active || $is_expired ) && $expires_ts )
				? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires_ts )
                : '—';
            
            // Calculate time left
            $time_left = '';
            $urgency_class = '';
            if ( $is_active && $expires_ts ) {
				$diff = $expires_ts - time();
                if ( $diff > 0 ) {
                    $days = floor( $diff / DAY_IN_SECONDS );
                    $hours = floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
                    $minutes = floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
                    
					if ( $days > 0 ) {
						/* translators: %d: number of days remaining. */
						$time_left = sprintf( _n( '%d day', '%d days', $days, 'hold-this-product' ), $days );
                        if ( $hours > 0 ) {
                            $time_left .= sprintf( ', %d hours', $hours );
                        }
					} elseif ( $hours > 0 ) {
						/* translators: %d: number of hours remaining. */
						$time_left = sprintf( _n( '%d hour', '%d hours', $hours, 'hold-this-product' ), $hours );
                        if ( $minutes > 0 ) {
                            $time_left .= sprintf( ', %d minutes', $minutes );
                        }
					} else {
						/* translators: %d: number of minutes remaining. */
						$time_left = sprintf( _n( '%d minute', '%d minutes', $minutes, 'hold-this-product' ), $minutes );
                    }
                    
                    // Add urgency class for styling
                    if ( $diff < 2 * HOUR_IN_SECONDS ) {
                        $urgency_class = 'urgent';
                    } elseif ( $diff < 6 * HOUR_IN_SECONDS ) {
                        $urgency_class = 'warning';
                    }
                } else {
                    $time_left = esc_html__( 'Expired', 'hold-this-product' );
                    $urgency_class = 'expired';
                }
            }
            
            if ( $is_pending ) {
                $time_left = esc_html__( 'Awaiting approval', 'hold-this-product' );
                $urgency_class = 'pending';
            } elseif ( $status === 'fulfilled' ) {
                $time_left = esc_html__( 'Purchased', 'hold-this-product' );
                $urgency_class = 'fulfilled';
            } elseif ( $status === 'denied' ) {
                $time_left = esc_html__( 'Denied', 'hold-this-product' );
                $urgency_class = 'denied';
            } elseif ( $status === 'cancelled' ) {
                $time_left = esc_html__( 'Cancelled', 'hold-this-product' );
                $urgency_class = 'cancelled';
            } elseif ( $is_expired ) {
                $time_left = esc_html__( 'Expired', 'hold-this-product' );
                $urgency_class = 'expired';
            }

            $add_to_cart_url = esc_url( wc_get_cart_url() . '?add-to-cart=' . $product_id );
			$cancel_nonce = wp_create_nonce( 'htp_cancel_res_' . $reservation->ID );

            $status_map = array(
                'active'           => esc_html__( 'Active', 'hold-this-product' ),
                'pending_approval' => esc_html__( 'Pending approval', 'hold-this-product' ),
                'fulfilled'        => esc_html__( 'Purchased', 'hold-this-product' ),
                'expired'          => esc_html__( 'Expired', 'hold-this-product' ),
                'cancelled'        => esc_html__( 'Cancelled', 'hold-this-product' ),
                'denied'           => esc_html__( 'Denied', 'hold-this-product' ),
            );
            $status_label = $status_map[ $status ] ?? esc_html__( 'Unknown', 'hold-this-product' );

            switch ( $status ) {
                case 'active':
                    $badge_variant = 'active';
                    break;
                case 'pending_approval':
                    $badge_variant = 'pending';
                    break;
                case 'fulfilled':
                    $badge_variant = 'fulfilled';
                    break;
                case 'denied':
                    $badge_variant = 'denied';
                    break;
                case 'cancelled':
                    $badge_variant = 'cancelled';
                    break;
                case 'expired':
                    $badge_variant = 'expired';
                    break;
                default:
                    $badge_variant = 'unknown';
                    break;
            }

            if ( $time_left === '' ) {
                $time_left = '—';
            }
            ?>
            
            <tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $status ?: 'unknown' ); ?> order">
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-product" data-title="<?php esc_attr_e( 'Product', 'hold-this-product' ); ?>">
                    <a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="woocommerce-LoopProduct-link">
                        <?php echo esc_html( $product->get_name() ); ?>
                    </a>
                </td>
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status" data-title="<?php esc_attr_e( 'Status', 'hold-this-product' ); ?>">
                    <span class="htp-status-badge htp-status-badge--<?php echo esc_attr( $badge_variant ); ?>">
                        <?php echo esc_html( $status_label ); ?>
                    </span>
                </td>
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-expires" data-title="<?php esc_attr_e( 'Expires', 'hold-this-product' ); ?>">
                    <?php if ( ( $is_active || $is_expired ) && $expires_ts ) : ?>
						<time datetime="<?php echo esc_attr( gmdate( DATE_ATOM, $expires_ts ) ); ?>">
                            <?php echo esc_html( $expires_disp ); ?>
                        </time>
                    <?php else : ?>
                        <?php echo esc_html( $expires_disp ); ?>
                    <?php endif; ?>
                </td>
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-time-left <?php echo esc_attr( $urgency_class ); ?>" data-title="<?php esc_attr_e( 'Time Left', 'hold-this-product' ); ?>">
                    <span class="time-left <?php echo esc_attr( $urgency_class ); ?>">
                        <?php echo esc_html( $time_left ); ?>
                    </span>
                </td>
                <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions" data-title="<?php esc_attr_e( 'Actions', 'hold-this-product' ); ?>">
                    <?php if ( $is_active ) : ?>
                        <a href="<?php echo esc_url( $add_to_cart_url ); ?>" class="woocommerce-button button add-to-cart">
                            <?php esc_html_e( 'Add to Cart', 'hold-this-product' ); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ( $is_active || $is_pending ) : ?>
						<a href="#" class="woocommerce-button button cancel-reservation" data-reservation-id="<?php echo esc_attr( $reservation->ID ); ?>" data-cancel-nonce="<?php echo esc_attr( $cancel_nonce ); ?>" data-confirm="<?php esc_attr_e( 'Are you sure you want to cancel this reservation?', 'hold-this-product' ); ?>">
                            <?php esc_html_e( 'Cancel', 'hold-this-product' ); ?>
                        </a>
                    <?php else : ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
            
        <?php endforeach; ?>
    </tbody>
</table>
    </div>
	<?php if ( isset( $total_pages, $current_page ) && $total_pages > 1 ) : ?>
		<nav class="woocommerce-pagination" aria-label="<?php esc_attr_e( 'Reservation history pagination', 'hold-this-product' ); ?>">
			<?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'reservation-page', '%#%', wc_get_account_endpoint_url( 'htp-reservations' ) ), 'current' => $current_page, 'total' => $total_pages ) ) ); ?>
		</nav>
	<?php endif; ?>
</div>
