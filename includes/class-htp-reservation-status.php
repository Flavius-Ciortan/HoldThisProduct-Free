<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Canonical reservation statuses and allowed lifecycle transitions. */
final class HTP_Reservation_Status {
	const INITIALIZING    = 'initializing';
	const PENDING         = 'pending_approval';
	const ACTIVE          = 'active';
	const EXPIRED         = 'expired';
	const CANCELLED       = 'cancelled';
	const FULFILLED       = 'fulfilled';
	const DENIED          = 'denied';
	const ORDER_CANCELLED = 'order_cancelled';

	public static function all() {
		return array(
			self::PENDING,
			self::ACTIVE,
			self::EXPIRED,
			self::CANCELLED,
			self::FULFILLED,
			self::DENIED,
			self::ORDER_CANCELLED,
		);
	}

	public static function open() {
		return array( self::PENDING, self::ACTIVE );
	}

	public static function labels() {
		return array(
			self::PENDING         => __( 'Pending approval', 'hold-this-product' ),
			self::ACTIVE          => __( 'Active', 'hold-this-product' ),
			self::EXPIRED         => __( 'Expired', 'hold-this-product' ),
			self::CANCELLED       => __( 'Cancelled', 'hold-this-product' ),
			self::FULFILLED       => __( 'Purchased', 'hold-this-product' ),
			self::DENIED          => __( 'Denied', 'hold-this-product' ),
			self::ORDER_CANCELLED => __( 'Order cancelled', 'hold-this-product' ),
		);
	}

	public static function label( $status ) {
		$labels = self::labels();
		return isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Unknown', 'hold-this-product' );
	}

	public static function can_transition( $from, $to ) {
		$transitions = array(
			self::INITIALIZING => array( self::ACTIVE ),
			self::PENDING      => array( self::ACTIVE, self::DENIED, self::CANCELLED, self::EXPIRED ),
			self::ACTIVE       => array( self::CANCELLED, self::EXPIRED, self::FULFILLED ),
			self::FULFILLED    => array( self::ACTIVE, self::ORDER_CANCELLED ),
		);
		return isset( $transitions[ $from ] ) && in_array( $to, $transitions[ $from ], true );
	}
}
