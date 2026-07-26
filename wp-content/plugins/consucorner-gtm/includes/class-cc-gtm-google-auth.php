<?php
/**
 * Google OAuth for Tag Manager API.
 *
 * @package ConsuCorner_GTM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Google OAuth handler.
 */
final class CC_GTM_Google_Auth {

	const TOKEN_OPTION = 'cc_gtm_oauth_tokens';
	const STATE_TRANSIENT = 'cc_gtm_oauth_state';
	const SCOPE = 'https://www.googleapis.com/auth/tagmanager.edit.containers';

	/**
	 * OAuth redirect URI (single query param — no &action= to avoid leaking into the auth URL).
	 *
	 * Register this exact URI in Google Cloud Console → OAuth client → Authorized redirect URIs.
	 *
	 * @return string
	 */
	public static function redirect_uri() {
		return admin_url( 'admin.php?page=cc-gtm-google-auth' );
	}

	/**
	 * Whether the current request is Google's OAuth callback (code + state on our auth page).
	 *
	 * @return bool
	 */
	public static function is_oauth_callback_request() {
		if ( ! is_admin() ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'cc-gtm-google-auth' !== $page ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ! empty( $_GET['code'] ) && ! empty( $_GET['state'] );
	}

	/**
	 * @return bool
	 */
	public static function is_configured() {
		$client_id     = (string) CC_GTM_Settings::get( 'google_client_id', '' );
		$client_secret = (string) CC_GTM_Settings::get( 'google_client_secret', '' );
		return '' !== $client_id && '' !== $client_secret;
	}

	/**
	 * Build authorization URL.
	 *
	 * @return string|WP_Error
	 */
	public static function get_auth_url() {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'cc_gtm_oauth', __( 'Google OAuth is not configured yet. Please enter Client ID and Client Secret.', 'consucorner-gtm' ) );
		}
		$state = wp_generate_password( 32, false );
		set_transient( self::STATE_TRANSIENT, $state, 15 * MINUTE_IN_SECONDS );

		$redirect_uri = self::redirect_uri();
		$params       = array(
			'client_id'     => (string) CC_GTM_Settings::get( 'google_client_id' ),
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => self::SCOPE,
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		);

		// Build with RFC 3986 encoding so redirect_uri is never parsed as extra top-level params.
		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Handle OAuth callback.
	 *
	 * @return true|WP_Error
	 */
	public static function handle_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'cc_gtm_forbidden', __( 'Permission denied.', 'consucorner-gtm' ) );
		}
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$saved = get_transient( self::STATE_TRANSIENT );
		delete_transient( self::STATE_TRANSIENT );
		if ( ! $state || ! $saved || ! hash_equals( $saved, $state ) ) {
			return new WP_Error( 'cc_gtm_state', __( 'Invalid OAuth state. Please try again.', 'consucorner-gtm' ) );
		}
		if ( empty( $_GET['code'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return new WP_Error( 'cc_gtm_code', __( 'Authorization code missing.', 'consucorner-gtm' ) );
		}
		$code = sanitize_text_field( wp_unslash( $_GET['code'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return self::exchange_code( $code );
	}

	/**
	 * @param string $code Auth code.
	 * @return true|WP_Error
	 */
	public static function exchange_code( $code ) {
		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 30,
				'body'    => array(
					'code'          => $code,
					'client_id'     => (string) CC_GTM_Settings::get( 'google_client_id' ),
					'client_secret' => (string) CC_GTM_Settings::get( 'google_client_secret' ),
					'redirect_uri'  => self::redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$msg = isset( $body['error_description'] ) ? $body['error_description'] : __( 'Token exchange failed.', 'consucorner-gtm' );
			return new WP_Error( 'cc_gtm_token', $msg );
		}
		self::store_tokens( $body );
		CC_GTM_Logger::log( 'google_connected', __( 'Google account connected.', 'consucorner-gtm' ) );
		return true;
	}

	/**
	 * @param array<string, mixed> $body Token response.
	 */
	private static function store_tokens( array $body ) {
		$payload = array(
			'access_token'  => (string) $body['access_token'],
			'refresh_token' => isset( $body['refresh_token'] ) ? (string) $body['refresh_token'] : '',
			'expires_at'    => time() + (int) ( $body['expires_in'] ?? 3600 ),
			'email'         => '',
		);
		$user = self::fetch_userinfo( $payload['access_token'] );
		if ( ! is_wp_error( $user ) && ! empty( $user['email'] ) ) {
			$payload['email'] = (string) $user['email'];
		}
		update_option( self::TOKEN_OPTION, self::encrypt( wp_json_encode( $payload ) ), false );
	}

	/**
	 * @param string $access_token Token.
	 * @return array<string, string>|WP_Error
	 */
	private static function fetch_userinfo( $access_token ) {
		$response = wp_remote_get(
			'https://www.googleapis.com/oauth2/v2/userinfo',
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : array();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_tokens() {
		$raw = get_option( self::TOKEN_OPTION, '' );
		if ( ! $raw ) {
			return null;
		}
		$json = self::decrypt( $raw );
		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * @return bool
	 */
	public static function is_connected() {
		$tokens = self::get_tokens();
		return ! empty( $tokens['access_token'] );
	}

	/**
	 * @return string
	 */
	public static function get_connected_email() {
		$tokens = self::get_tokens();
		return $tokens && ! empty( $tokens['email'] ) ? (string) $tokens['email'] : '';
	}

	/**
	 * @return bool
	 */
	public static function is_token_expired() {
		$tokens = self::get_tokens();
		if ( ! $tokens || empty( $tokens['expires_at'] ) ) {
			return true;
		}
		return time() >= ( (int) $tokens['expires_at'] - 60 );
	}

	/**
	 * @return string|WP_Error Access token.
	 */
	public static function get_valid_access_token() {
		if ( ! self::is_connected() ) {
			return new WP_Error( 'cc_gtm_not_connected', __( 'Google connection expired. Please reconnect.', 'consucorner-gtm' ) );
		}
		if ( ! self::is_token_expired() ) {
			$tokens = self::get_tokens();
			return (string) $tokens['access_token'];
		}
		return self::refresh_access_token();
	}

	/**
	 * @return string|WP_Error
	 */
	public static function refresh_access_token() {
		$tokens = self::get_tokens();
		if ( empty( $tokens['refresh_token'] ) ) {
			return new WP_Error( 'cc_gtm_refresh', __( 'Google connection expired. Please reconnect.', 'consucorner-gtm' ) );
		}
		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 30,
				'body'    => array(
					'client_id'     => (string) CC_GTM_Settings::get( 'google_client_id' ),
					'client_secret' => (string) CC_GTM_Settings::get( 'google_client_secret' ),
					'refresh_token' => (string) $tokens['refresh_token'],
					'grant_type'    => 'refresh_token',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'cc_gtm_refresh', __( 'Google connection expired. Please reconnect.', 'consucorner-gtm' ) );
		}
		$tokens['access_token'] = (string) $body['access_token'];
		$tokens['expires_at']   = time() + (int) ( $body['expires_in'] ?? 3600 );
		update_option( self::TOKEN_OPTION, self::encrypt( wp_json_encode( $tokens ) ), false );
		return $tokens['access_token'];
	}

	/**
	 * Disconnect Google.
	 */
	public static function disconnect() {
		delete_option( self::TOKEN_OPTION );
		CC_GTM_Logger::log( 'google_disconnected', __( 'Google account disconnected.', 'consucorner-gtm' ) );
	}

	/**
	 * @param string $plain Plain text.
	 * @return string
	 */
	private static function encrypt( $plain ) {
		if ( ! function_exists( 'openssl_encrypt' ) || ! defined( 'AUTH_KEY' ) ) {
			return base64_encode( $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		$key = substr( hash( 'sha256', AUTH_KEY ), 0, 32 );
		$iv  = substr( hash( 'sha256', SECURE_AUTH_KEY ), 0, 16 );
		$enc = openssl_encrypt( $plain, 'AES-256-CBC', $key, 0, $iv );
		return base64_encode( false !== $enc ? $enc : $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * @param string $encoded Encoded.
	 * @return string
	 */
	private static function decrypt( $encoded ) {
		$raw = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw ) {
			return '';
		}
		if ( ! function_exists( 'openssl_decrypt' ) || ! defined( 'AUTH_KEY' ) ) {
			return $raw;
		}
		$key = substr( hash( 'sha256', AUTH_KEY ), 0, 32 );
		$iv  = substr( hash( 'sha256', SECURE_AUTH_KEY ), 0, 16 );
		$dec = openssl_decrypt( $raw, 'AES-256-CBC', $key, 0, $iv );
		return false !== $dec ? $dec : '';
	}
}
