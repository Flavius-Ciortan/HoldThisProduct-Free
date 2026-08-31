<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Coordinates short reservation critical sections across web requests. */
final class HTP_Lock_Manager {
	const STALE_AFTER_SECONDS = 30;

	public function acquire( $names ) {
		$locks = array();
		sort( $names, SORT_STRING );
		foreach ( array_unique( $names ) as $name ) {
			$key   = 'htp_lock_' . sanitize_key( $name );
			$token = wp_generate_uuid4() . '|' . time();
			if ( ! add_option( $key, $token, '', false ) ) {
				$parts = explode( '|', (string) get_option( $key, '' ) );
				if ( isset( $parts[1] ) && time() - (int) $parts[1] > self::STALE_AFTER_SECONDS ) {
					delete_option( $key );
				}
				if ( ! add_option( $key, $token, '', false ) ) {
					$this->release( $locks );
					return new WP_Error( 'htp_busy', __( 'Another reservation is being processed. Please try again.', 'hold-this-product' ) );
				}
			}
			$locks[ $key ] = $token;
		}
		return $locks;
	}

	public function release( $locks ) {
		foreach ( (array) $locks as $key => $token ) {
			if ( hash_equals( (string) get_option( $key, '' ), (string) $token ) ) {
				delete_option( $key );
			}
		}
	}
}
