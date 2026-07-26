<?php
/**
 * Plugin settings storage and validation.
 *
 * @package ConsuCorner_GTM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings handler.
 */
final class CC_GTM_Settings {

	const OPTION_KEY = 'cc_gtm_settings';

	/**
	 * @var array<string, mixed>|null
	 */
	private static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		$gtm_id = defined( 'CC_GTM_ID' ) ? CC_GTM_ID : 'GTM-NDB325C8';

		return array(
			'gtm_container_id'           => $gtm_id,
			'enable_gtm'               => true,
			'enable_noscript'          => true,
			'enable_datalayer'         => true,
			'enable_ecommerce'         => true,
			'debug_mode'               => false,
			'environment'              => 'production',
			'rankmath_guard'           => true,
			'conflict_guard'           => true,
			'server_container_enabled' => false,
			'server_container_url'     => '',
			'first_party_loader'       => false,
			'gtm_container_usage_context' => 'web',
			'ga4_enabled'              => false,
			'ga4_measurement_id'       => '',
			'google_ads_enabled'       => false,
			'google_ads_conversion_id' => '',
			'google_ads_purchase_label' => '',
			'google_ads_atc_label'     => '',
			'meta_enabled'             => false,
			'meta_pixel_id'            => '',
			'tiktok_enabled'           => false,
			'tiktok_pixel_id'          => '',
			'clarity_enabled'          => false,
			'clarity_id'               => '',
			'google_client_id'         => '',
			'google_client_secret'     => '',
			'gtm_account_id'           => '',
			'gtm_account_name'           => '',
			'gtm_container_api_id'       => '',
			'gtm_container_public_id'    => '',
			'gtm_workspace_id'           => '',
			'gtm_workspace_name'         => '',
			'allow_auto_publish'       => false,
			'last_setup_summary'         => array(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		self::$cache = array_merge( self::defaults(), $stored );
		return self::$cache;
	}

	/**
	 * @param string $key Setting key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::get_all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Effective GTM container ID (settings override constant).
	 *
	 * @return string
	 */
	public static function get_gtm_container_id() {
		if ( defined( 'CC_GTM_ID' ) && self::validate_gtm_id( CC_GTM_ID ) ) {
			return CC_GTM_ID;
		}
		$id = (string) self::get( 'gtm_container_id', '' );
		if ( $id && self::validate_gtm_id( $id ) ) {
			return $id;
		}
		return 'GTM-NDB325C8';
	}

	/**
	 * @param string $id GTM ID.
	 * @return bool
	 */
	public static function validate_gtm_id( $id ) {
		return (bool) preg_match( '/^GTM-[A-Z0-9]+$/i', (string) $id );
	}

	/**
	 * @param string $id GA4 ID.
	 * @return bool
	 */
	public static function validate_ga4_id( $id ) {
		return (bool) preg_match( '/^G-[A-Z0-9]+$/i', (string) $id );
	}

	/**
	 * @param string $id Ads ID.
	 * @return bool
	 */
	public static function validate_ads_id( $id ) {
		return (bool) preg_match( '/^AW-[0-9]+$/i', (string) $id );
	}

	/**
	 * @param string $id Meta pixel.
	 * @return bool
	 */
	public static function validate_meta_pixel_id( $id ) {
		return (bool) preg_match( '/^[0-9]+$/', (string) $id );
	}

	/**
	 * Validate a server-side GTM container URL (HTTPS origin).
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	public static function validate_server_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return false;
		}
		if ( ! preg_match( '#^https://#i', $url ) ) {
			return false;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return ! empty( $host );
	}

	/**
	 * Effective server container URL (only when server routing is enabled).
	 *
	 * @return string Normalized URL without trailing slash, or empty string.
	 */
	public static function get_server_container_url() {
		if ( ! self::get( 'server_container_enabled', false ) ) {
			return '';
		}
		$url = (string) self::get( 'server_container_url', '' );
		if ( ! self::validate_server_url( $url ) ) {
			return '';
		}
		return untrailingslashit( esc_url_raw( $url ) );
	}

	/**
	 * Sanitize and save settings from POST array.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array{ok:bool,errors:array<int,string>,saved:array<string,mixed>}
	 */
	public static function save_from_input( array $input ) {
		$errors = array();
		$current = self::get_all();
		$out     = $current;

		if ( isset( $input['gtm_container_id'] ) ) {
			$gtm = strtoupper( sanitize_text_field( wp_unslash( $input['gtm_container_id'] ) ) );
			if ( $gtm && ! self::validate_gtm_id( $gtm ) ) {
				$errors[] = __( 'GTM Container ID must match GTM-XXXXXXX.', 'consucorner-gtm' );
			} elseif ( $gtm ) {
				$out['gtm_container_id'] = $gtm;
			}
		}

		$bool_keys = array(
			'enable_gtm',
			'enable_noscript',
			'enable_datalayer',
			'enable_ecommerce',
			'debug_mode',
			'rankmath_guard',
			'conflict_guard',
			'server_container_enabled',
			'first_party_loader',
			'ga4_enabled',
			'google_ads_enabled',
			'meta_enabled',
			'tiktok_enabled',
			'clarity_enabled',
			'allow_auto_publish',
		);
		foreach ( $bool_keys as $key ) {
			$out[ $key ] = ! empty( $input[ $key ] );
		}

		if ( isset( $input['server_container_url'] ) ) {
			$server_url = trim( (string) wp_unslash( $input['server_container_url'] ) );
			if ( '' === $server_url ) {
				$out['server_container_url'] = '';
			} elseif ( ! self::validate_server_url( $server_url ) ) {
				$errors[] = __( 'Server Container URL must be a valid HTTPS URL (e.g. https://gtm.example.com).', 'consucorner-gtm' );
			} else {
				$out['server_container_url'] = untrailingslashit( esc_url_raw( $server_url ) );
			}
		}

		// Guard: first-party loader needs a server URL to point to.
		if ( ! empty( $out['first_party_loader'] ) && '' === (string) $out['server_container_url'] ) {
			$errors[] = __( 'First-party loading requires a valid Server Container URL.', 'consucorner-gtm' );
		}

		if ( isset( $input['environment'] ) ) {
			$env = sanitize_key( wp_unslash( $input['environment'] ) );
			$out['environment'] = in_array( $env, array( 'staging', 'production' ), true ) ? $env : 'production';
		}

		if ( isset( $input['ga4_measurement_id'] ) ) {
			$ga4 = strtoupper( sanitize_text_field( wp_unslash( $input['ga4_measurement_id'] ) ) );
			if ( $ga4 && ! self::validate_ga4_id( $ga4 ) ) {
				$errors[] = __( 'GA4 Measurement ID must match G-XXXXXXXX.', 'consucorner-gtm' );
			} else {
				$out['ga4_measurement_id'] = $ga4;
			}
		}

		if ( isset( $input['google_ads_conversion_id'] ) ) {
			$aw = strtoupper( sanitize_text_field( wp_unslash( $input['google_ads_conversion_id'] ) ) );
			if ( $aw && ! self::validate_ads_id( $aw ) ) {
				$errors[] = __( 'Google Ads Conversion ID must match AW-XXXXXXXX.', 'consucorner-gtm' );
			} else {
				$out['google_ads_conversion_id'] = $aw;
			}
		}

		$text_keys = array(
			'google_ads_purchase_label',
			'google_ads_atc_label',
			'meta_pixel_id',
			'tiktok_pixel_id',
			'clarity_id',
			'google_client_id',
		);
		foreach ( $text_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ) );
			}
		}

		if ( isset( $input['meta_pixel_id'] ) && $out['meta_pixel_id'] && ! self::validate_meta_pixel_id( $out['meta_pixel_id'] ) ) {
			$errors[] = __( 'Meta Pixel ID must be numeric.', 'consucorner-gtm' );
		}

		if ( isset( $input['google_client_secret'] ) ) {
			$secret = (string) wp_unslash( $input['google_client_secret'] );
			if ( '' !== $secret ) {
				$out['google_client_secret'] = $secret;
			}
		}

		if ( ! empty( $errors ) ) {
			return array(
				'ok'    => false,
				'errors' => $errors,
				'saved' => $current,
			);
		}

		update_option( self::OPTION_KEY, $out, false );
		self::$cache = $out;

		return array(
			'ok'     => true,
			'errors' => array(),
			'saved'  => $out,
		);
	}

	/**
	 * @param array<string, mixed> $partial Partial settings.
	 */
	public static function update( array $partial ) {
		$all = self::get_all();
		foreach ( $partial as $key => $value ) {
			$all[ $key ] = $value;
		}
		update_option( self::OPTION_KEY, $all, false );
		self::$cache = $all;
	}

	/**
	 * Import settings JSON.
	 *
	 * @param string $json JSON string.
	 * @return array{ok:bool,message:string}
	 */
	public static function import_json( $json ) {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Invalid JSON.', 'consucorner-gtm' ),
			);
		}
		unset( $data['google_client_secret'] );
		$result = self::save_from_input( $data );
		if ( ! $result['ok'] ) {
			return array(
				'ok'      => false,
				'message' => implode( ' ', $result['errors'] ),
			);
		}
		return array(
			'ok'      => true,
			'message' => __( 'Settings imported.', 'consucorner-gtm' ),
		);
	}

	/**
	 * Export settings (no secrets).
	 *
	 * @return string
	 */
	public static function export_json() {
		$all = self::get_all();
		unset( $all['google_client_secret'] );
		return wp_json_encode( $all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}
}
