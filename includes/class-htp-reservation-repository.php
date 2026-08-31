<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/** Reservation persistence queries shared by Free and compatible add-ons. */
final class HTP_Reservation_Repository {
	public function find_active( $product_id, $user_id ) {
		$ids = get_posts( array(
			'post_type' => 'htp_reservation', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1,
			'author' => absint( $user_id ), 'no_found_rows' => true,
			'meta_query' => array(
				array( 'key' => '_htp_product_id', 'value' => absint( $product_id ), 'type' => 'NUMERIC' ),
				array( 'key' => '_htp_status', 'value' => HTP_Reservation_Status::ACTIVE ),
				array( 'key' => '_htp_expires_at', 'value' => time(), 'type' => 'NUMERIC', 'compare' => '>' ),
			),
		) );
		return $ids ? (int) $ids[0] : 0;
	}

	public function count_active( $user_id = 0, $email = '' ) {
		$args = array(
			'post_type' => 'htp_reservation', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1,
			'meta_query' => array(
				array( 'key' => '_htp_status', 'value' => HTP_Reservation_Status::ACTIVE ),
				array( 'key' => '_htp_expires_at', 'value' => time(), 'type' => 'NUMERIC', 'compare' => '>' ),
			),
		);
		if ( $user_id > 0 ) {
			$args['author'] = absint( $user_id );
		} elseif ( $email ) {
			$args['meta_query'][] = array( 'key' => '_htp_email', 'value' => sanitize_email( $email ) );
		} else {
			return 0;
		}
		$query = new WP_Query( $args );
		return (int) $query->found_posts;
	}

	public function has_active( $product_id, $user_id = 0, $email = '' ) {
		$args = array(
			'post_type'      => 'htp_reservation',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'meta_query'     => array(
				array( 'key' => '_htp_status', 'value' => HTP_Reservation_Status::ACTIVE ),
				array( 'key' => '_htp_product_id', 'value' => absint( $product_id ), 'type' => 'NUMERIC' ),
				array( 'key' => '_htp_expires_at', 'value' => time(), 'type' => 'NUMERIC', 'compare' => '>' ),
			),
		);
		if ( $user_id > 0 ) {
			$args['author'] = absint( $user_id );
		} elseif ( $email ) {
			$args['meta_query'][] = array( 'key' => '_htp_email', 'value' => sanitize_email( $email ) );
		} else {
			return false;
		}
		return ! empty( get_posts( $args ) );
	}

	public function count_open( $user_id ) {
		if ( $user_id <= 0 ) return 0;
		$query = new WP_Query( array(
			'post_type' => 'htp_reservation', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1,
			'author' => absint( $user_id ),
			'meta_query' => array(
				'relation' => 'OR',
				array( 'relation' => 'AND', array( 'key' => '_htp_status', 'value' => HTP_Reservation_Status::PENDING ), array( 'key' => '_htp_expires_at', 'value' => time(), 'type' => 'NUMERIC', 'compare' => '>' ) ),
				array( 'relation' => 'AND', array( 'key' => '_htp_status', 'value' => HTP_Reservation_Status::ACTIVE ), array( 'key' => '_htp_expires_at', 'value' => time(), 'type' => 'NUMERIC', 'compare' => '>' ) ),
			),
		) );
		return (int) $query->found_posts;
	}

	public function user_has_open_for_product( $product_id, $user_id ) {
		if ( ! $product_id || ! $user_id ) return false;
		$ids = get_posts( array(
			'post_type' => 'htp_reservation', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 10,
			'author' => absint( $user_id ),
			'meta_query' => array(
				array( 'key' => '_htp_product_id', 'value' => absint( $product_id ) ),
				array( 'key' => '_htp_status', 'value' => HTP_Reservation_Status::open(), 'compare' => 'IN' ),
			),
		) );
		$now = time();
		foreach ( $ids as $reservation_id ) {
			if ( (int) get_post_meta( $reservation_id, '_htp_expires_at', true ) > $now ) return true;
		}
		return false;
	}
}
