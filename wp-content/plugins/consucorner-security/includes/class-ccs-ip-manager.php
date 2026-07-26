<?php
/**
 * IP address management: block, whitelist, and geolocation lookup.
 *
 * @package Consucorner_Security
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CCS_IP_Manager
 */
class CCS_IP_Manager {

	const TABLE_BLOCKED   = 'ccs_blocked_ips';
	const TABLE_WHITELIST = 'ccs_whitelist_ips';
	const GEO_CACHE_TTL   = 30 * DAY_IN_SECONDS;
	const GEO_ENDPOINT    = 'http://ip-api.com/json/';

	/**
	 * Fully-qualified table names.
	 */
	public static function table_blocked() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_BLOCKED;
	}

	public static function table_whitelist() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_WHITELIST;
	}

	/**
	 * Create tables. Safe to call multiple times.
	 */
	public static function install_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$blocked   = self::table_blocked();
		$whitelist = self::table_whitelist();

		$sql_blocked = "CREATE TABLE {$blocked} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ip_address VARCHAR(45) NOT NULL,
			reason VARCHAR(255) DEFAULT NULL,
			country_code VARCHAR(2) DEFAULT NULL,
			blocked_until DATETIME DEFAULT NULL,
			created_by BIGINT DEFAULT 0,
			source VARCHAR(20) DEFAULT 'manual',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ip_address (ip_address),
			KEY blocked_until (blocked_until),
			KEY country_code (country_code)
		) {$charset};";

		$sql_whitelist = "CREATE TABLE {$whitelist} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ip_address VARCHAR(45) NOT NULL,
			label VARCHAR(200) DEFAULT NULL,
			created_by BIGINT DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ip_address (ip_address)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_blocked );
		dbDelta( $sql_whitelist );
	}

	/**
	 * Drop tables (uninstall).
	 */
	public static function drop_tables() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table_blocked() ); // phpcs:ignore WordPress.DB
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table_whitelist() ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Block an IP address.
	 *
	 * @param string      $ip            Valid IPv4/IPv6.
	 * @param string      $reason        Optional reason.
	 * @param int|null    $duration_secs Null = permanent, int = TTL in seconds.
	 * @param string      $source        manual|auto|api.
	 * @return bool|WP_Error
	 */
	public static function block_ip( $ip, $reason = '', $duration_secs = null, $source = 'manual' ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return new WP_Error( 'invalid_ip', __( 'Invalid IP address.', 'consucorner-security' ) );
		}

		if ( self::is_whitelisted( $ip ) ) {
			return new WP_Error( 'whitelisted', __( 'IP is whitelisted and cannot be blocked.', 'consucorner-security' ) );
		}

		global $wpdb;
		$table = self::table_blocked();

		$until = null;
		if ( null !== $duration_secs ) {
			$duration_secs = max( 60, (int) $duration_secs );
			$until         = gmdate( 'Y-m-d H:i:s', time() + $duration_secs );
		}

		$geo = self::geolocate( $ip );

		$wpdb->replace(
			$table,
			array(
				'ip_address'    => $ip,
				'reason'        => mb_substr( (string) $reason, 0, 255 ),
				'country_code'  => isset( $geo['country_code'] ) ? $geo['country_code'] : null,
				'blocked_until' => $until,
				'created_by'    => get_current_user_id(),
				'source'        => in_array( $source, array( 'manual', 'auto', 'api' ), true ) ? $source : 'manual',
				'created_at'    => current_time( 'mysql', true ),
			)
		);

		return true;
	}

	/**
	 * Remove an IP from the block list.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public static function unblock_ip( $ip ) {
		global $wpdb;
		return false !== $wpdb->delete( self::table_blocked(), array( 'ip_address' => $ip ), array( '%s' ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * True if currently blocked (not expired).
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public static function is_blocked( $ip ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT blocked_until FROM ' . self::table_blocked() . ' WHERE ip_address = %s LIMIT 1', $ip ) ); // phpcs:ignore WordPress.DB
		if ( ! $row ) {
			return false;
		}
		if ( null === $row->blocked_until ) {
			return true;
		}
		return strtotime( $row->blocked_until ) > time();
	}

	/**
	 * Whitelist an IP.
	 *
	 * @param string $ip    IP address.
	 * @param string $label Friendly label.
	 * @return bool|WP_Error
	 */
	public static function whitelist_ip( $ip, $label = '' ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return new WP_Error( 'invalid_ip', __( 'Invalid IP address.', 'consucorner-security' ) );
		}

		global $wpdb;
		$wpdb->replace(
			self::table_whitelist(),
			array(
				'ip_address' => $ip,
				'label'      => mb_substr( (string) $label, 0, 200 ),
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			)
		);

		// Whitelisting overrides any active block.
		self::unblock_ip( $ip );
		return true;
	}

	/**
	 * Remove from whitelist.
	 */
	public static function unwhitelist_ip( $ip ) {
		global $wpdb;
		return false !== $wpdb->delete( self::table_whitelist(), array( 'ip_address' => $ip ), array( '%s' ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * True if whitelisted.
	 */
	public static function is_whitelisted( $ip ) {
		global $wpdb;
		$row = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table_whitelist() . ' WHERE ip_address = %s LIMIT 1', $ip ) ); // phpcs:ignore WordPress.DB
		return ! empty( $row );
	}

	/**
	 * Paginated query of blocked IPs.
	 *
	 * @param array<string, mixed> $args Filters.
	 * @return array{rows: array, total: int}
	 */
	public static function list_blocked( array $args = array() ) {
		global $wpdb;
		$table = self::table_blocked();

		$where  = array( '1=1' );
		$params = array();

		$filter = isset( $args['filter'] ) ? $args['filter'] : 'all';
		if ( 'active' === $filter ) {
			$where[]  = '(blocked_until IS NULL OR blocked_until > %s)';
			$params[] = current_time( 'mysql', true );
		} elseif ( 'expired' === $filter ) {
			$where[]  = 'blocked_until IS NOT NULL AND blocked_until <= %s';
			$params[] = current_time( 'mysql', true );
		} elseif ( 'manual' === $filter ) {
			$where[] = "source = 'manual'";
		} elseif ( 'auto' === $filter ) {
			$where[] = "source = 'auto'";
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'ip_address LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$per_page = isset( $args['per_page'] ) ? max( 1, min( 200, (int) $args['per_page'] ) ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );
		$total_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = $params
			? (int) $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) ) // phpcs:ignore WordPress.DB
			: (int) $wpdb->get_var( $total_sql ); // phpcs:ignore WordPress.DB

		$rows_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$rows_params = array_merge( $params, array( $per_page, $offset ) );
		$rows        = $wpdb->get_results( $wpdb->prepare( $rows_sql, $rows_params ), ARRAY_A ); // phpcs:ignore WordPress.DB

		return array( 'rows' => $rows ? $rows : array(), 'total' => $total );
	}

	/**
	 * List whitelist entries.
	 */
	public static function list_whitelist() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table_whitelist() . ' ORDER BY created_at DESC', ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Remove all expired blocks.
	 *
	 * @return int Number of rows removed.
	 */
	public static function clear_expired() {
		global $wpdb;
		$table = self::table_blocked();
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE blocked_until IS NOT NULL AND blocked_until <= %s", current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Geolocate an IP. Cached per-IP for 30 days.
	 *
	 * @param string $ip IP.
	 * @return array{country: string, country_code: string, city: string, isp: string, type: string, threat_score: int}
	 */
	public static function geolocate( $ip ) {
		$empty = array(
			'country'      => '',
			'country_code' => '',
			'city'         => '',
			'isp'          => '',
			'type'         => '',
			'threat_score' => 0,
		);

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $empty;
		}

		// Skip lookup for private/reserved IPs.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return $empty;
		}

		$key    = 'ccs_geo_' . md5( $ip );
		$cached = get_transient( $key );
		if ( false !== $cached && is_array( $cached ) ) {
			return wp_parse_args( $cached, $empty );
		}

		$response = wp_remote_get(
			self::GEO_ENDPOINT . rawurlencode( $ip ) . '?fields=status,country,countryCode,city,isp,org,as,proxy,hosting',
			array(
				'timeout'   => 3,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			set_transient( $key, $empty, HOUR_IN_SECONDS ); // short cache on failure
			return $empty;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['status'] ) || 'success' !== $body['status'] ) {
			set_transient( $key, $empty, HOUR_IN_SECONDS );
			return $empty;
		}

		$type = 'residential';
		if ( ! empty( $body['hosting'] ) ) {
			$type = 'datacenter';
		} elseif ( ! empty( $body['proxy'] ) ) {
			$type = 'proxy';
		}

		$result = array(
			'country'      => isset( $body['country'] ) ? sanitize_text_field( $body['country'] ) : '',
			'country_code' => isset( $body['countryCode'] ) ? strtoupper( substr( $body['countryCode'], 0, 2 ) ) : '',
			'city'         => isset( $body['city'] ) ? sanitize_text_field( $body['city'] ) : '',
			'isp'          => isset( $body['isp'] ) ? sanitize_text_field( $body['isp'] ) : ( isset( $body['org'] ) ? sanitize_text_field( $body['org'] ) : '' ),
			'type'         => $type,
			'threat_score' => 0,
		);

		set_transient( $key, $result, self::GEO_CACHE_TTL );
		return $result;
	}

	/**
	 * Threat score for an IP based on its log history.
	 *
	 * @param string $ip IP.
	 * @return int 0-100.
	 */
	public static function compute_threat_score( $ip ) {
		global $wpdb;
		$table = CCS_Logger::table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total,
					SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) AS crits,
					SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) AS warns,
					SUM(CASE WHEN event_type = 'bot_allowed_google' THEN 1 ELSE 0 END) AS good
				FROM {$table} WHERE ip_address = %s",
				$ip
			)
		); // phpcs:ignore WordPress.DB

		if ( ! $row ) {
			return 0;
		}

		$total = (int) $row->total;
		$crits = (int) $row->crits;
		$warns = (int) $row->warns;
		$good  = (int) $row->good;

		if ( $good && ! $crits && ! $warns ) {
			return 0;
		}

		$score = ( $crits * 25 ) + ( $warns * 8 ) + min( 20, $total );
		return min( 100, max( 0, (int) $score ) );
	}

	/**
	 * IP activity summary across the logs table.
	 *
	 * @param string $ip IP.
	 * @return array<string, int|string>
	 */
	public static function ip_activity_summary( $ip ) {
		global $wpdb;
		$table = CCS_Logger::table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total,
					SUM(CASE WHEN severity IN ('warning','critical') THEN 1 ELSE 0 END) AS blocked,
					SUM(CASE WHEN event_type = 'login_success' THEN 1 ELSE 0 END) AS logins,
					MIN(created_at) AS first_seen,
					MAX(created_at) AS last_seen
				FROM {$table} WHERE ip_address = %s",
				$ip
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB

		if ( ! $row ) {
			return array(
				'total'      => 0,
				'blocked'    => 0,
				'logins'     => 0,
				'first_seen' => null,
				'last_seen'  => null,
			);
		}

		return array(
			'total'      => (int) $row['total'],
			'blocked'    => (int) $row['blocked'],
			'logins'     => (int) $row['logins'],
			'first_seen' => $row['first_seen'],
			'last_seen'  => $row['last_seen'],
		);
	}
}
