<?php
/**
 * Admin activity logger.
 *
 * @package ConsuCorner_GTM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Logger.
 */
final class CC_GTM_Logger {

	const OPTION_KEY = 'cc_gtm_logs';
	const MAX_ENTRIES = 500;

	/**
	 * @param string               $action Action slug.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public static function log( $action, $message, array $context = array() ) {
		$logs   = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}
		$logs[] = array(
			'time'    => current_time( 'mysql' ),
			'action'  => sanitize_key( $action ),
			'message' => sanitize_text_field( $message ),
			'context' => self::sanitize_context( $context ),
			'user'    => get_current_user_id(),
		);
		if ( count( $logs ) > self::MAX_ENTRIES ) {
			$logs = array_slice( $logs, -self::MAX_ENTRIES );
		}
		update_option( self::OPTION_KEY, $logs, false );
	}

	/**
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed>
	 */
	private static function sanitize_context( array $context ) {
		$out = array();
		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( is_scalar( $value ) ) {
				$out[ $key ] = sanitize_text_field( (string) $value );
			} elseif ( is_array( $value ) ) {
				$out[ $key ] = self::sanitize_context( $value );
			}
		}
		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_logs() {
		$logs = get_option( self::OPTION_KEY, array() );
		return is_array( $logs ) ? array_reverse( $logs ) : array();
	}

	/**
	 * Clear all logs.
	 */
	public static function clear() {
		delete_option( self::OPTION_KEY );
	}
}
