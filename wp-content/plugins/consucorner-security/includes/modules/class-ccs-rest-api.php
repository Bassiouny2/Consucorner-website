<?php
/**
 * REST API namespace: /wp-json/ccs/v1/*
 *
 * All endpoints require an authenticated user with `manage_options`
 * capability and a valid WP REST nonce ('X-WP-Nonce' header).
 *
 * @package Consucorner_Security
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CCS_REST_API
 */
class CCS_REST_API {

	const NS = 'ccs/v1';

	/**
	 * Bind routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Permission callback shared by every endpoint.
	 *
	 * @return bool|WP_Error
	 */
	public static function permission() {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', __( 'Insufficient permissions.', 'consucorner-security' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Register all routes.
	 */
	public static function register_routes() {
		$args_range = array(
			'range' => array(
				'type'              => 'string',
				'enum'              => array( '24h', '7d', '30d', '90d' ),
				'default'           => '7d',
				'sanitize_callback' => 'sanitize_key',
			),
		);

		register_rest_route( self::NS, '/stats/summary',  array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'stats_summary' ),  'permission_callback' => array( __CLASS__, 'permission' ) ) );
		register_rest_route( self::NS, '/stats/timeline', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'stats_timeline' ), 'args' => $args_range, 'permission_callback' => array( __CLASS__, 'permission' ) ) );
		register_rest_route( self::NS, '/stats/types',     array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'stats_types' ),    'args' => $args_range, 'permission_callback' => array( __CLASS__, 'permission' ) ) );
		register_rest_route( self::NS, '/stats/countries', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'stats_countries' ),'args' => $args_range, 'permission_callback' => array( __CLASS__, 'permission' ) ) );
		register_rest_route( self::NS, '/stats/heatmap',   array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'stats_heatmap' ),  'args' => $args_range, 'permission_callback' => array( __CLASS__, 'permission' ) ) );
		register_rest_route( self::NS, '/stats/top-ips',   array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'stats_top_ips' ),  'args' => $args_range, 'permission_callback' => array( __CLASS__, 'permission' ) ) );
		register_rest_route( self::NS, '/stats/score',     array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'stats_score' ),    'permission_callback' => array( __CLASS__, 'permission' ) ) );

		register_rest_route( self::NS, '/logs', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'logs_list' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );
		register_rest_route( self::NS, '/logs/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'logs_get' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );
		register_rest_route( self::NS, '/logs/export', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'logs_export' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );
		register_rest_route( self::NS, '/logs/clear', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'logs_clear' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );

		register_rest_route( self::NS, '/ip/block', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'ip_block' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );
		register_rest_route( self::NS, '/ip/whitelist', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'ip_whitelist' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );
		register_rest_route( self::NS, '/ip/block/(?P<ip>[^/]+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'ip_unblock' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );
		register_rest_route( self::NS, '/ip/whitelist/(?P<ip>[^/]+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'ip_unwhitelist' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );
		register_rest_route( self::NS, '/ip/(?P<ip>[^/]+)/info', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'ip_info' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );
		register_rest_route( self::NS, '/ip/blocked', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'ip_list_blocked' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );
		register_rest_route( self::NS, '/ip/whitelist', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'ip_list_whitelist' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );
		register_rest_route( self::NS, '/ip/clear-expired', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'ip_clear_expired' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );

		register_rest_route( self::NS, '/settings', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'settings_save' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );

		register_rest_route( self::NS, '/notifications/test-email', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'notifications_test_email' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );
		register_rest_route( self::NS, '/notifications/test-telegram', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'notifications_test_telegram' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );

		register_rest_route( self::NS, '/live-feed', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'live_feed' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
		) );
	}

	// ---------- Stats handlers ----------

	public static function stats_summary() {
		return rest_ensure_response( CCS_Stats::summary() );
	}
	public static function stats_timeline( WP_REST_Request $request ) {
		return rest_ensure_response( CCS_Stats::timeline( $request->get_param( 'range' ) ) );
	}
	public static function stats_types( WP_REST_Request $request ) {
		return rest_ensure_response( CCS_Stats::types( $request->get_param( 'range' ) ) );
	}
	public static function stats_countries( WP_REST_Request $request ) {
		return rest_ensure_response( CCS_Stats::countries( $request->get_param( 'range' ) ) );
	}
	public static function stats_heatmap( WP_REST_Request $request ) {
		return rest_ensure_response( CCS_Stats::heatmap( $request->get_param( 'range' ) ) );
	}
	public static function stats_top_ips( WP_REST_Request $request ) {
		return rest_ensure_response( CCS_Stats::top_ips( $request->get_param( 'range' ) ) );
	}
	public static function stats_score() {
		return rest_ensure_response( array(
			'current' => CCS_Options::get_security_score(),
			'history' => CCS_Stats::score_history(),
		) );
	}

	// ---------- Logs ----------

	public static function logs_list( WP_REST_Request $request ) {
		$args = array(
			'severity'       => $request->get_param( 'severity' ),
			'event_category' => $request->get_param( 'category' ),
			'country_code'   => $request->get_param( 'country' ),
			'ip_address'     => $request->get_param( 'ip' ),
			'search'         => $request->get_param( 'search' ),
			'from'           => $request->get_param( 'from' ),
			'to'             => $request->get_param( 'to' ),
			'page'           => (int) $request->get_param( 'page' ),
			'per_page'       => (int) $request->get_param( 'per_page' ),
		);

		$result = CCS_Logger::query( $args );

		$result['rows'] = array_map( array( __CLASS__, 'decorate_log_row' ), $result['rows'] );
		return rest_ensure_response( $result );
	}

	public static function logs_get( WP_REST_Request $request ) {
		$row = CCS_Logger::get( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Log entry not found.', 'consucorner-security' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::decorate_log_row( $row ) );
	}

	public static function logs_export( WP_REST_Request $request ) {
		// Stream CSV. Limited to 10k rows for sanity.
		$args = array(
			'severity'       => $request->get_param( 'severity' ),
			'event_category' => $request->get_param( 'category' ),
			'country_code'   => $request->get_param( 'country' ),
			'ip_address'     => $request->get_param( 'ip' ),
			'search'         => $request->get_param( 'search' ),
			'from'           => $request->get_param( 'from' ),
			'to'             => $request->get_param( 'to' ),
			'page'           => 1,
			'per_page'       => 10000,
		);
		$result = CCS_Logger::query( $args );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename=ccs-logs-' . gmdate( 'Y-m-d-His' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'ID', 'Time', 'Event', 'Category', 'Severity', 'IP', 'Country', 'User Agent', 'URI', 'User ID', 'Details' ) );
		foreach ( $result['rows'] as $row ) {
			fputcsv( $out, array(
				$row['id'],
				$row['created_at'],
				$row['event_type'],
				$row['event_category'],
				$row['severity'],
				$row['ip_address'],
				$row['country_code'],
				$row['user_agent'],
				$row['request_uri'],
				$row['user_id'],
				$row['details'],
			) );
		}
		fclose( $out );
		exit;
	}

	public static function logs_clear() {
		CCS_Logger::clear_all();
		CCS_Stats::flush_caches();
		return rest_ensure_response( array( 'ok' => true ) );
	}

	private static function decorate_log_row( $row ) {
		$row['details_parsed']     = $row['details'] ? json_decode( $row['details'], true ) : null;
		$row['severity_label']     = ucfirst( $row['severity'] );
		$row['event_label']        = self::event_label( $row['event_type'] );
		$row['time_diff']          = human_time_diff( strtotime( $row['created_at'] ), time() );
		$row['user_agent_compact'] = $row['user_agent'] ? mb_substr( (string) $row['user_agent'], 0, 50 ) : '';
		$row['is_blocked']         = ! empty( $row['ip_address'] ) && CCS_IP_Manager::is_blocked( $row['ip_address'] );
		$row['is_whitelisted']     = ! empty( $row['ip_address'] ) && CCS_IP_Manager::is_whitelisted( $row['ip_address'] );
		return $row;
	}

	public static function event_label( $type ) {
		$map = array(
			'bot_blocked'           => __( 'Bot blocked', 'consucorner-security' ),
			'bot_allowed_google'    => __( 'Googlebot allowed', 'consucorner-security' ),
			'scraper_blocked'       => __( 'Scraper blocked', 'consucorner-security' ),
			'brute_force_attempt'   => __( 'Brute-force attempt', 'consucorner-security' ),
			'login_success'         => __( 'Login success', 'consucorner-security' ),
			'login_failed'          => __( 'Login failed', 'consucorner-security' ),
			'login_blocked'         => __( 'Login blocked', 'consucorner-security' ),
			'sql_injection_attempt' => __( 'SQL injection attempt', 'consucorner-security' ),
			'xss_attempt'           => __( 'XSS attempt', 'consucorner-security' ),
			'file_upload_blocked'   => __( 'File upload blocked', 'consucorner-security' ),
			'rate_limit_triggered'  => __( 'Rate limit triggered', 'consucorner-security' ),
			'file_changed'          => __( 'File changed', 'consucorner-security' ),
			'suspicious_db_query'   => __( 'Suspicious DB query', 'consucorner-security' ),
			'2fa_success'           => __( '2FA success', 'consucorner-security' ),
			'2fa_failed'            => __( '2FA failed', 'consucorner-security' ),
			'user_registered'       => __( 'User registered', 'consucorner-security' ),
			'vendor_approved'       => __( 'Vendor approved', 'consucorner-security' ),
			'order_suspicious'      => __( 'Suspicious order', 'consucorner-security' ),
			'api_abuse'             => __( 'API abuse', 'consucorner-security' ),
			'ddos_attempt'          => __( 'DDoS attempt', 'consucorner-security' ),
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : $type;
	}

	// ---------- IP handlers ----------

	public static function ip_block( WP_REST_Request $request ) {
		$ip       = (string) $request->get_param( 'ip' );
		$reason   = (string) $request->get_param( 'reason' );
		$duration = $request->get_param( 'duration' );
		$secs     = null;
		if ( in_array( $duration, array( '24h', '7d', '30d' ), true ) ) {
			$secs = array( '24h' => DAY_IN_SECONDS, '7d' => 7 * DAY_IN_SECONDS, '30d' => 30 * DAY_IN_SECONDS )[ $duration ];
		}
		$res = CCS_IP_Manager::block_ip( $ip, $reason, $secs, 'manual' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return rest_ensure_response( array( 'ok' => true, 'ip' => $ip ) );
	}

	public static function ip_whitelist( WP_REST_Request $request ) {
		$ip    = (string) $request->get_param( 'ip' );
		$label = (string) $request->get_param( 'label' );
		$res   = CCS_IP_Manager::whitelist_ip( $ip, $label );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return rest_ensure_response( array( 'ok' => true, 'ip' => $ip ) );
	}

	public static function ip_unblock( WP_REST_Request $request ) {
		CCS_IP_Manager::unblock_ip( (string) $request['ip'] );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function ip_unwhitelist( WP_REST_Request $request ) {
		CCS_IP_Manager::unwhitelist_ip( (string) $request['ip'] );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function ip_info( WP_REST_Request $request ) {
		$ip = (string) $request['ip'];
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return new WP_Error( 'invalid_ip', __( 'Invalid IP address.', 'consucorner-security' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$table = CCS_Logger::table_name();
		$recent = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE ip_address = %s ORDER BY created_at DESC LIMIT 10", $ip ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$recent = array_map( array( __CLASS__, 'decorate_log_row' ), $recent ? $recent : array() );

		return rest_ensure_response( array(
			'ip'             => $ip,
			'geo'            => CCS_IP_Manager::geolocate( $ip ),
			'threat_score'   => CCS_IP_Manager::compute_threat_score( $ip ),
			'activity'       => CCS_IP_Manager::ip_activity_summary( $ip ),
			'is_blocked'     => CCS_IP_Manager::is_blocked( $ip ),
			'is_whitelisted' => CCS_IP_Manager::is_whitelisted( $ip ),
			'recent_events'  => $recent,
		) );
	}

	public static function ip_list_blocked( WP_REST_Request $request ) {
		return rest_ensure_response( CCS_IP_Manager::list_blocked( array(
			'filter'   => $request->get_param( 'filter' ),
			'search'   => $request->get_param( 'search' ),
			'page'     => (int) $request->get_param( 'page' ),
			'per_page' => (int) $request->get_param( 'per_page' ),
		) ) );
	}

	public static function ip_list_whitelist() {
		return rest_ensure_response( array( 'rows' => CCS_IP_Manager::list_whitelist() ) );
	}

	public static function ip_clear_expired() {
		return rest_ensure_response( array( 'cleared' => CCS_IP_Manager::clear_expired() ) );
	}

	// ---------- Settings ----------

	public static function settings_save( WP_REST_Request $request ) {
		$section = (string) $request->get_param( 'section' );
		$data    = $request->get_param( 'data' );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		switch ( $section ) {
			case 'recipients':
				return rest_ensure_response( array( 'recipients' => CCS_Notifications::save_recipients( $data ) ) );

			case 'thresholds':
				return rest_ensure_response( array( 'thresholds' => CCS_Notifications::save_thresholds( $data ) ) );

			case 'templates':
				return rest_ensure_response( array( 'templates' => CCS_Notifications::save_templates( $data ) ) );

			case 'telegram':
				return rest_ensure_response( array( 'telegram' => CCS_Notifications::save_telegram( $data ) ) );

			case 'whitelist_domains':
				$clean = array();
				foreach ( (array) $data as $row ) {
					$d = isset( $row['domain'] ) ? sanitize_text_field( $row['domain'] ) : '';
					if ( $d ) {
						$clean[] = array(
							'domain' => $d,
							'reason' => isset( $row['reason'] ) ? sanitize_text_field( $row['reason'] ) : '',
						);
					}
				}
				update_option( CCS_OPTION_PREFIX . 'whitelist_domains', $clean, false );
				return rest_ensure_response( array( 'rows' => $clean ) );

			case 'whitelist_users':
				$clean = array();
				foreach ( (array) $data as $row ) {
					$u = isset( $row['username'] ) ? sanitize_user( $row['username'], true ) : '';
					if ( $u ) {
						$clean[] = array(
							'username' => $u,
							'role'     => isset( $row['role'] ) ? sanitize_key( $row['role'] ) : '',
						);
					}
				}
				update_option( CCS_OPTION_PREFIX . 'whitelist_users', $clean, false );
				return rest_ensure_response( array( 'rows' => $clean ) );

			case 'country_rules':
				$allowed = array( 'allow', 'block', 'challenge' );
				$clean   = array();
				foreach ( (array) $data as $code => $action ) {
					$code = strtoupper( substr( sanitize_key( $code ), 0, 2 ) );
					if ( 2 === strlen( $code ) && in_array( $action, $allowed, true ) ) {
						$clean[ $code ] = $action;
					}
				}
				update_option( CCS_OPTION_PREFIX . 'country_rules', $clean, false );
				return rest_ensure_response( array( 'rules' => $clean ) );

			case 'rate_limit_rules':
				$clean = array();
				foreach ( (array) $data as $rule ) {
					$pattern = isset( $rule['url_pattern'] ) ? sanitize_text_field( $rule['url_pattern'] ) : '';
					if ( ! $pattern ) {
						continue;
					}
					$clean[] = array(
						'url_pattern'      => $pattern,
						'requests_per_min' => isset( $rule['requests_per_min'] ) ? max( 0, (int) $rule['requests_per_min'] ) : 0,
						'burst'            => isset( $rule['burst'] ) ? max( 0, (int) $rule['burst'] ) : 0,
						'whitelist_roles'  => isset( $rule['whitelist_roles'] ) ? array_map( 'sanitize_key', (array) $rule['whitelist_roles'] ) : array(),
						'action'           => isset( $rule['action'] ) && in_array( $rule['action'], array( 'allow', 'block', 'challenge' ), true ) ? $rule['action'] : 'block',
					);
				}
				update_option( CCS_OPTION_PREFIX . 'rate_limit_rules', $clean, false );
				return rest_ensure_response( array( 'rules' => $clean ) );

			case 'custom_nginx_rules':
				$raw = isset( $data['rules'] ) ? (string) $data['rules'] : '';
				$raw = wp_check_invalid_utf8( $raw );
				$raw = wp_kses( $raw, array() );
				update_option( CCS_OPTION_PREFIX . 'custom_nginx_rules', $raw, false );
				return rest_ensure_response( array( 'rules' => $raw ) );

			case 'logs_management':
				$retention   = isset( $data['retention_days'] ) ? max( 7, min( 365, (int) $data['retention_days'] ) ) : 90;
				$auto_clean  = ! empty( $data['auto_clean'] );
				$max_size    = isset( $data['max_size_mb'] ) ? max( 5, min( 1024, (int) $data['max_size_mb'] ) ) : 50;
				$async_log   = ! empty( $data['async_logging'] );
				$level       = isset( $data['level'] ) && in_array( $data['level'], array( 'all', 'warnings', 'critical' ), true ) ? $data['level'] : 'all';
				$sample      = isset( $data['sample_rate'] ) ? max( 10, min( 100, (int) $data['sample_rate'] ) ) : 100;

				update_option( CCS_OPTION_PREFIX . 'logs_retention_days', $retention, false );
				update_option( CCS_OPTION_PREFIX . 'logs_auto_clean', $auto_clean, false );
				update_option( CCS_OPTION_PREFIX . 'logs_max_size_mb', $max_size, false );
				update_option( CCS_OPTION_PREFIX . 'logs_async', $async_log, false );
				update_option( CCS_OPTION_PREFIX . 'logs_level', $level, false );
				update_option( CCS_OPTION_PREFIX . 'logs_sample_rate', $sample, false );

				return rest_ensure_response( array( 'ok' => true ) );

			default:
				return new WP_Error( 'unknown_section', __( 'Unknown settings section.', 'consucorner-security' ), array( 'status' => 400 ) );
		}
	}

	// ---------- Notifications ----------

	public static function notifications_test_email() {
		$ok = CCS_Notifications::send_test_email();
		return rest_ensure_response( array( 'ok' => (bool) $ok ) );
	}

	public static function notifications_test_telegram() {
		$res = CCS_Notifications::test_telegram();
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return rest_ensure_response( array( 'ok' => (bool) $res ) );
	}

	// ---------- Live feed ----------

	public static function live_feed() {
		return rest_ensure_response( CCS_Stats::live_feed() );
	}
}
