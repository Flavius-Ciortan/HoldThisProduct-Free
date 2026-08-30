<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/** WordPress privacy export and erasure for reservation records. */
final class HTP_Privacy_Service {
	public function register_exporter( $exporters ) {
		$exporters['hold-this-product'] = array(
			'exporter_friendly_name' => __( 'Hold This Product reservations', 'hold-this-product' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	public function register_eraser( $erasers ) {
		$erasers['hold-this-product'] = array(
			'eraser_friendly_name' => __( 'Hold This Product reservations', 'hold-this-product' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	public function export_personal_data( $email_address, $page = 1 ) {
		$ids  = $this->find_reservations( $email_address, $page );
		$data = array();
		foreach ( $ids as $reservation_id ) {
			$data[] = array(
				'group_id'    => 'hold-this-product-reservations',
				'group_label' => __( 'Product reservations', 'hold-this-product' ),
				'item_id'     => 'htp-reservation-' . $reservation_id,
				'data'        => array(
					array( 'name' => __( 'Product ID', 'hold-this-product' ), 'value' => (int) get_post_meta( $reservation_id, '_htp_product_id', true ) ),
					array( 'name' => __( 'Status', 'hold-this-product' ), 'value' => sanitize_text_field( get_post_meta( $reservation_id, '_htp_status', true ) ) ),
					array( 'name' => __( 'Email', 'hold-this-product' ), 'value' => sanitize_email( get_post_meta( $reservation_id, '_htp_email', true ) ) ),
					array( 'name' => __( 'Expires', 'hold-this-product' ), 'value' => wp_date( DATE_ATOM, (int) get_post_meta( $reservation_id, '_htp_expires_at', true ) ) ),
				),
			);
		}
		return array( 'data' => $data, 'done' => count( $ids ) < 100 );
	}

	public function erase_personal_data( $email_address, $page = 1 ) {
		unset( $page );
		$ids      = $this->find_erasable_reservations( $email_address );
		$removed  = false;
		$retained = $this->has_retained_reservations( $email_address );
		foreach ( $ids as $reservation_id ) {
			update_post_meta( $reservation_id, '_htp_email', wp_privacy_anonymize_data( 'email', $email_address ) );
			wp_update_post( array( 'ID' => $reservation_id, 'post_author' => 0 ) );
			$removed = true;
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $retained ? array( __( 'Open reservations were retained until their inventory obligation ends.', 'hold-this-product' ) ) : array(),
			'done'           => count( $ids ) < 100,
		);
	}

	private function identity_args( $email_address ) {
		$user = get_user_by( 'email', $email_address );
		if ( $user ) {
			return array( 'author' => $user->ID );
		}
		return array( 'meta_query' => array( array( 'key' => '_htp_email', 'value' => sanitize_email( $email_address ) ) ) );
	}

	private function find_reservations( $email_address, $page ) {
		$args = array(
			'post_type' => 'htp_reservation', 'post_status' => 'publish', 'fields' => 'ids',
			'posts_per_page' => 100, 'paged' => max( 1, absint( $page ) ), 'orderby' => 'ID', 'order' => 'ASC',
		);
		return get_posts( array_merge( $args, $this->identity_args( $email_address ) ) );
	}

	private function find_erasable_reservations( $email_address ) {
		$args = array(
			'post_type'      => 'htp_reservation',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 100,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array( 'key' => '_htp_status', 'value' => HTP_Reservation_Status::open(), 'compare' => 'NOT IN' ),
			),
		);
		return get_posts( $this->merge_identity_meta_query( $args, $email_address ) );
	}

	private function has_retained_reservations( $email_address ) {
		$args = array(
			'post_type'      => 'htp_reservation',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'meta_query'     => array(
				array( 'key' => '_htp_status', 'value' => HTP_Reservation_Status::open(), 'compare' => 'IN' ),
			),
		);
		return ! empty( get_posts( $this->merge_identity_meta_query( $args, $email_address ) ) );
	}

	private function merge_identity_meta_query( $args, $email_address ) {
		$identity = $this->identity_args( $email_address );
		if ( isset( $identity['author'] ) ) {
			$args['author'] = $identity['author'];
		} else {
			$args['meta_query'][] = $identity['meta_query'][0];
		}
		return $args;
	}
}
