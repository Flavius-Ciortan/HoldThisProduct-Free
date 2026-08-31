<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared admin notice queue for compatible add-on dependency failures. */
final class HTP_Dependency_Notices {
	private $notices = array();

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	public function add( $id, $message, $type = 'error', $dismissible = false ) {
		$id      = sanitize_key( $id );
		$type    = in_array( $type, array( 'error', 'warning', 'success', 'info' ), true ) ? $type : 'error';
		$message = sanitize_text_field( $message );
		if ( ! $id || ! $message ) {
			return false;
		}
		$this->notices[ $id ] = array(
			'message'     => $message,
			'type'        => $type,
			'dismissible' => (bool) $dismissible,
		);
		return true;
	}

	public function all() {
		return $this->notices;
	}

	public function remove( $id ) {
		unset( $this->notices[ sanitize_key( $id ) ] );
	}

	public function render() {
		foreach ( $this->notices as $notice ) {
			$classes = 'notice notice-' . $notice['type'] . ( $notice['dismissible'] ? ' is-dismissible' : '' );
			echo '<div class="' . esc_attr( $classes ) . '"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
	}
}
