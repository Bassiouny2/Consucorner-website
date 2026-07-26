<?php
/**
 * Security event logger.
 *
 * Logs are queued in memory during the request and flushed to the
 * database on `shutdown` to avoid slowing down the response. A daily
 * WP-Cron job prunes rows older than the configured retention window.
 *
 * @package Consucorner_Security
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CCS_Logger
 */
class CCS_Logger {

	const TABLE          = 'ccs_logs';
	const CRON_HOOK      = 'ccs_logs_cleanup_cron';
	const SCHEMA_VERSION = '1.0.0';
	const SCHEMA_OPTION  = 'ccs_logs_schema_version';

	/**
	 * Allowed event types.
	 */
	const EVENT_TYPES = array(
		'bot_blocked',
		'bot_allowed_google',
		'scraper_blocked',
		'brute_force_attempt',
		'login_success',
		'login_failed',
		'login_blocked',
		'sql_injection_attempt',
		'xss_attempt',
		'file_upload_blocked',
		'rate_limit_triggered',
		'file_changed',
		'suspicious_db_query',
		'2fa_success',
		'2fa_failed',
		'user_registered',
		'vendor_approved',
		'order_suspicious',
		'api_abuse',
		'ddos_attempt',
	);

	/**
	 * Allowed severities.
	 */
	const SEVERITIES = array( 'info', 'warning', 'critical' );

	/**
	 * In-memory queue, flushed at shutdown.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static $queue = array();

	/**
	 * Whether the shutdown hook has been registered for this request.
	 *
	 * @var bool
	 */
	private static $shutdown_bound = false;

	/**
	 * Whether cron hook has been registered.
	 *
	 * @var bool
	 */
	private static $cron_bound = false;

	/**
	 * Bind WP hooks (cleanup cron, etc.).
	 */
	public static function init() {
		if ( self::$cron_bound ) {
			return;
		}
		self::$cron_bound = true;

		add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Fully-qualified table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create or upgrade the logs table. Safe to call multiple times.
	 */
	public static function install_table() {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_type VARCHAR(50) NOT NULL,
			event_category VARCHAR(30) NOT NULL,
			severity VARCHAR(10) NOT NULL DEFAULT 'info',
			ip_address VARCHAR(45) NOT NULL DEFAULT '',
			country_code VARCHAR(2) DEFAULT NULL,
			user_agent TEXT NULL,
			request_uri VARCHAR(500) DEFAULT NULL,
			user_id BIGINT DEFAULT 0,
			details LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY severity (severity),
			KEY ip_address (ip_address),
			KEY created_at (created_at),
			KEY country_code (country_code)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * Drop the table (used on uninstall).
	 */
	public static function drop_table() {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		delete_option( self::SCHEMA_OPTION );
	}

	/**
	 * Map event_type → category for filtering.
	 *
	 * @param string $event_type Event type slug.
	 * @return string
	 */
	public static function categorize( $event_type ) {
		$map = array(
			'bot_blocked'          => 'bot',
			'bot_allowed_google'   => 'bot',
			'scraper_blocked'      => 'bot',
			'brute_force_attempt'  => 'login',
			'login_success'        => 'login',
			'login_failed'         => 'login',
			'login_blocked'        => 'login',
			'2fa_success'          => 'login',
			'2fa_failed'           => 'login',
			'sql_injection_attempt'=> 'firewall',
			'xss_attempt'          => 'firewall',
			'file_upload_blocked'  => 'firewall',
			'rate_limit_triggered' => 'firewall',
			'ddos_attempt'         => 'firewall',
			'file_changed'         => 'file',
			'suspicious_db_query'  => 'db',
			'user_registered'      => 'user',
			'vendor_approved'      => 'user',
			'order_suspicious'     => 'order',
			'api_abuse'            => 'api',
		);
		return isset( $map[ $event_type ] ) ? $map[ $event_type ] : 'misc';
	}

	/**
	 * Queue an event for write at shutdown.
	 *
	 * @param string               $event_type Event slug.
	 * @param string               $severity   info|warning|critical.
	 * @param array<string, mixed> $context    Optional context.
	 */
	public static function log( $event_type, $severity = 'info', array $context = array() ) {
		if ( ! in_array( $event_type, self::EVENT_TYPES, true ) ) {
			return;
		}
		if ( ! in_array( $severity, self::SEVERITIES, true ) ) {
			$severity = 'info';
		}

		// Optional sampling (Performance: large sites).
		$sample_rate = (int) get_option( CCS_OPTION_PREFIX . 'logs_sample_rate', 100 );
		if ( $sample_rate < 100 && wp_rand( 1, 100 ) > $sample_rate ) {
			return;
		}

		// Optional log level filter.
		$level = get_option( CCS_OPTION_PREFIX . 'logs_level', 'all' );
		if ( 'critical' === $level && 'critical' !== $severity ) {
			return;
		}
		if ( 'warnings' === $level && 'info' === $severity ) {
			return;
		}

		$ip = isset( $context['ip'] ) ? $context['ip'] : self::detect_ip();
		$ua = isset( $context['user_agent'] )
			? $context['user_agent']
			: ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '' );

		$uri = isset( $context['request_uri'] )
			? $context['request_uri']
			: ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' );

		$row = array(
			'event_type'     => $event_type,
			'event_category' => self::categorize( $event_type ),
			'severity'       => $severity,
			'ip_address'     => is_string( $ip ) ? substr( $ip, 0, 45 ) : '',
			'country_code'   => isset( $context['country_code'] ) ? strtoupper( substr( (string) $context['country_code'], 0, 2 ) ) : null,
			'user_agent'     => is_string( $ua ) ? substr( $ua, 0, 1000 ) : '',
			'request_uri'    => is_string( $uri ) ? substr( $uri, 0, 500 ) : '',
			'user_id'        => isset( $context['user_id'] ) ? (int) $context['user_id'] : ( is_user_logged_in() ? get_current_user_id() : 0 ),
			'details'        => isset( $context['details'] ) ? wp_json_encode( $context['details'] ) : null,
			'created_at'     => current_time( 'mysql', true ),
		);

		self::$queue[] = $row;

		if ( ! self::$shutdown_bound ) {
			self::$shutdown_bound = true;
			add_action( 'shutdown', array( __CLASS__, 'flush' ), 999 );
		}
	}

	/**
	 * Flush queued events.
	 */
	public static function flush() {
		if ( empty( self::$queue ) ) {
			return;
		}

		global $wpdb;
		$table = self::table_name();

		foreach ( self::$queue as $row ) {
			$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		self::$queue = array();

		// Bump the live-feed transient so the widget polls a fresh value.
		set_transient( 'ccs_live_feed_pulse', microtime( true ), MINUTE_IN_SECONDS * 5 );
	}

	/**
	 * Detect the visitor IP, honoring Cloudways/Cloudflare proxies.
	 *
	 * @return string
	 */
	public static function detect_ip() {
		$candidates = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $candidates as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}
			$raw = wp_unslash( $_SERVER[ $key ] );
			$ip  = trim( explode( ',', $raw )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '';
	}

	/**
	 * Delete logs older than the retention window.
	 */
	public static function cleanup() {
		$days = (int) get_option( CCS_OPTION_PREFIX . 'logs_retention_days', 90 );
		$days = max( 7, min( 365, $days ) );

		global $wpdb;
		$table  = self::table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $days ) );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB

		// Bump score history daily snapshot.
		self::record_score_snapshot();
	}

	/**
	 * Append today's security score into a rolling 90-day option.
	 */
	public static function record_score_snapshot() {
		$history   = get_option( CCS_OPTION_PREFIX . 'score_history', array() );
		$today_key = gmdate( 'Y-m-d' );

		$history[ $today_key ] = CCS_Options::get_security_score();

		if ( count( $history ) > 90 ) {
			$history = array_slice( $history, -90, 90, true );
		}

		update_option( CCS_OPTION_PREFIX . 'score_history', $history, false );
	}

	/**
	 * Run a filtered SELECT against the logs table.
	 *
	 * @param array<string, mixed> $args Filters.
	 *   - severity (string|array)
	 *   - event_category (string)
	 *   - country_code (string)
	 *   - ip_address (string)
	 *   - search (string)
	 *   - from (Y-m-d)
	 *   - to (Y-m-d)
	 *   - per_page (int)
	 *   - page (int)
	 *   - order (ASC|DESC).
	 * @return array{rows:array, total:int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table = self::table_name();

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['severity'] ) ) {
			$sev = (array) $args['severity'];
			$sev = array_intersect( $sev, self::SEVERITIES );
			if ( $sev ) {
				$placeholders = implode( ',', array_fill( 0, count( $sev ), '%s' ) );
				$where[]      = "severity IN ({$placeholders})";
				$params       = array_merge( $params, array_values( $sev ) );
			}
		}

		if ( ! empty( $args['event_category'] ) && 'all' !== $args['event_category'] ) {
			$where[]  = 'event_category = %s';
			$params[] = sanitize_key( $args['event_category'] );
		}

		if ( ! empty( $args['country_code'] ) && 'all' !== $args['country_code'] ) {
			$where[]  = 'country_code = %s';
			$params[] = strtoupper( substr( $args['country_code'], 0, 2 ) );
		}

		if ( ! empty( $args['ip_address'] ) ) {
			$where[]  = 'ip_address LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['ip_address'] ) . '%';
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(event_type LIKE %s OR user_agent LIKE %s OR request_uri LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( ! empty( $args['from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['from'] . ' 00:00:00';
		}
		if ( ! empty( $args['to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = $args['to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$order     = ( isset( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';

		$per_page = isset( $args['per_page'] ) ? max( 1, min( 200, (int) $args['per_page'] ) ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$total_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = $params
			? (int) $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) ) // phpcs:ignore WordPress.DB
			: (int) $wpdb->get_var( $total_sql ); // phpcs:ignore WordPress.DB

		$rows_sql        = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at {$order}, id {$order} LIMIT %d OFFSET %d";
		$rows_params     = array_merge( $params, array( $per_page, $offset ) );
		$rows            = $wpdb->get_results( $wpdb->prepare( $rows_sql, $rows_params ), ARRAY_A ); // phpcs:ignore WordPress.DB

		return array(
			'rows'  => $rows ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Fetch a single log row by ID.
	 *
	 * @param int $id Row id.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ? $row : null;
	}

	/**
	 * Truncate the entire logs table.
	 */
	public static function clear_all() {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB
	}
}
