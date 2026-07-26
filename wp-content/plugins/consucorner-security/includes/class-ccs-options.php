<?php
/**
 * Plugin options registry, defaults, and persistence.
 *
 * @package Consucorner_Security
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CCS_Options
 *
 * Pure-static registry; all data is cached in static properties for the
 * lifetime of a request so we never rebuild it more than once.
 */
class CCS_Options {

	/**
	 * Memoized registry.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static $registry_cache = null;

	/**
	 * Memoized flat defaults map (key => bool).
	 *
	 * @var array<string, bool>|null
	 */
	private static $defaults_cache = null;

	/**
	 * Module + feature definitions for admin UI and scoring.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_registry() {
		if ( null !== self::$registry_cache ) {
			return self::$registry_cache;
		}

		self::$registry_cache = array(
			'bot'      => array(
				'label'       => __( 'Bot Protection', 'consucorner-security' ),
				'description' => __( 'Block harmful scrapers while keeping Google and GeIdeA fully accessible.', 'consucorner-security' ),
				'icon'        => 'shield',
				'slug'        => 'bot-protection',
				'options'     => array(
					'ccs_bot_allow_googlebot'       => array(
						'label'    => __( 'Allow Googlebot', 'consucorner-security' ),
						'desc'     => __( 'Never block official Google crawlers.', 'consucorner-security' ),
						'default'  => true,
						'badge'    => 'google',
						'critical' => true,
					),
					'ccs_bot_allow_google_merchant' => array(
						'label'    => __( 'Allow Google Merchant (Storebot)', 'consucorner-security' ),
						'desc'     => __( 'Required for Google Merchant Center product feeds.', 'consucorner-security' ),
						'default'  => true,
						'badge'    => 'google',
						'critical' => true,
					),
					'ccs_bot_verify_reverse_dns'    => array(
						'label'   => __( 'Verify Googlebot via reverse DNS', 'consucorner-security' ),
						'desc'    => __( 'Prevents fake Googlebot user-agents.', 'consucorner-security' ),
						'default' => true,
						'badge'   => 'google',
					),
					'ccs_bot_block_scrapers'        => array(
						'label'   => __( 'Block known SEO scrapers', 'consucorner-security' ),
						'desc'    => __( 'Ahrefs, Semrush, DotBot, and similar bots.', 'consucorner-security' ),
						'default' => true,
					),
					'ccs_bot_honeypot'              => array(
						'label'   => __( 'Honeypot trap link', 'consucorner-security' ),
						'desc'    => __( 'Hidden link in footer to catch aggressive bots.', 'consucorner-security' ),
						'default' => false,
					),
					'ccs_bot_geidea_whitelist'      => array(
						'label'    => __( 'GeIdeA payment whitelist', 'consucorner-security' ),
						'desc'     => __( 'Never block GeIdeA webhooks or payment callbacks.', 'consucorner-security' ),
						'default'  => true,
						'badge'    => 'geidea',
						'critical' => true,
					),
				),
			),
			'server'   => array(
				'label'       => __( 'Server Rules', 'consucorner-security' ),
				'description' => __( 'Nginx rate limits, Fail2Ban, and Cloudways-ready rule export.', 'consucorner-security' ),
				'icon'        => 'server',
				'slug'        => 'server-rules',
				'options'     => array(
					'ccs_server_fail2ban'        => array(
						'label'   => __( 'Fail2Ban integration', 'consucorner-security' ),
						'desc'    => __( 'Generates server-side guidance for banning repeated attackers from Nginx logs.', 'consucorner-security' ),
						'default' => true,
					),
					'ccs_server_rate_limiting'   => array(
						'label'   => __( 'Nginx rate limiting', 'consucorner-security' ),
						'desc'    => __( 'Prepares Cloudways/Nginx limits for login, checkout, API, and general traffic.', 'consucorner-security' ),
						'default' => true,
					),
					'ccs_server_geoip_block'     => array(
						'label'   => __( 'GeoIP country blocking', 'consucorner-security' ),
						'desc'    => __( 'Optional country-level blocking or challenge rules. Use carefully for international customers.', 'consucorner-security' ),
						'default' => false,
					),
					'ccs_server_export_htaccess' => array(
						'label'   => __( 'Export .htaccess rules', 'consucorner-security' ),
						'desc'    => __( 'Optional Apache-compatible rule export. Cloudways/Nginx setups usually do not need this.', 'consucorner-security' ),
						'default' => false,
					),
					'ccs_server_antiddos'        => array(
						'label'   => __( 'Anti-DDoS hardening', 'consucorner-security' ),
						'desc'    => __( 'Enables stricter limits and generated guidance for bursty abusive traffic.', 'consucorner-security' ),
						'default' => true,
					),
				),
			),
			'login'    => array(
				'label'       => __( 'Login Security', 'consucorner-security' ),
				'description' => __( 'Brute-force protection, custom login URL, and 2FA for staff.', 'consucorner-security' ),
				'icon'        => 'lock',
				'slug'        => 'login-security',
				'options'     => array(
					'ccs_login_brute_force'  => array(
						'label'   => __( 'Brute-force protection', 'consucorner-security' ),
						'desc'    => __( 'Tracks failed login attempts by IP and temporarily blocks repeated abuse.', 'consucorner-security' ),
						'default' => true,
					),
					'ccs_login_change_url'   => array(
						'label'   => __( 'Custom login URL', 'consucorner-security' ),
						'desc'    => __( 'Lets you choose a private login slug to reduce automated attacks on wp-login.php.', 'consucorner-security' ),
						'default' => false,
					),
					'ccs_login_2fa_admin'    => array(
						'label'   => __( '2FA for administrators', 'consucorner-security' ),
						'desc'    => __( 'Requires time-based one-time codes for administrator accounts when the 2FA engine is active.', 'consucorner-security' ),
						'default' => true,
					),
					'ccs_login_2fa_customer' => array(
						'label'   => __( '2FA for customers (optional)', 'consucorner-security' ),
						'desc'    => __( 'Optional customer 2FA. Keep off unless the store UX is ready for extra login steps.', 'consucorner-security' ),
						'default' => false,
					),
					'ccs_login_ip_whitelist' => array(
						'label'   => __( 'Admin IP whitelist', 'consucorner-security' ),
						'desc'    => __( 'Allows trusted admin IPs to bypass login lockouts and aggressive checks.', 'consucorner-security' ),
						'default' => true,
					),
				),
			),
			'firewall' => array(
				'label'       => __( 'Firewall', 'consucorner-security' ),
				'description' => __( 'WAF-style request filtering with Dokan API exclusions.', 'consucorner-security' ),
				'icon'        => 'firewall',
				'slug'        => 'firewall',
				'options'     => array(
					'ccs_firewall_sql_injection'   => array(
						'label'   => __( 'SQL injection protection', 'consucorner-security' ),
						'desc'    => __( 'Blocks suspicious query strings and request values that look like SQL injection attempts.', 'consucorner-security' ),
						'default' => true,
					),
					'ccs_firewall_xss'             => array(
						'label'   => __( 'XSS protection', 'consucorner-security' ),
						'desc'    => __( 'Detects script, iframe, javascript: and event-handler payloads in unsafe request data.', 'consucorner-security' ),
						'default' => true,
					),
					'ccs_firewall_file_upload'     => array(
						'label'   => __( 'Malicious file upload block', 'consucorner-security' ),
						'desc'    => __( 'Checks file extensions, MIME type, and early file contents for PHP/script payloads.', 'consucorner-security' ),
						'default' => true,
					),
					'ccs_firewall_headers'         => array(
						'label'   => __( 'Security headers module', 'consucorner-security' ),
						'desc'    => __( 'Adds browser security headers while keeping GeIdeA payment frames and Google resources working.', 'consucorner-security' ),
						'default' => true,
					),
					'ccs_firewall_dokan_exclusion' => array(
						'label'    => __( 'Dokan / WooCommerce API exclusion', 'consucorner-security' ),
						'desc'     => __( 'Never WAF-scan Dokan or WC REST endpoints.', 'consucorner-security' ),
						'default'  => true,
						'badge'    => 'dokan',
						'critical' => true,
					),
				),
			),
			'database' => array(
				'label'       => __( 'Database & Files', 'consucorner-security' ),
				'description' => __( 'File integrity monitoring and database hardening.', 'consucorner-security' ),
				'icon'        => 'database',
				'slug'        => 'database',
				'options'     => array(
					'ccs_db_table_prefix'      => array(
						'label'   => __( 'Custom table prefix', 'consucorner-security' ),
						'desc'    => __( 'Manual confirmation required before applying.', 'consucorner-security' ),
						'default' => false,
					),
					'ccs_db_file_monitor'      => array(
						'label'   => __( 'Daily file monitor (WP-Cron)', 'consucorner-security' ),
						'desc'    => __( 'Scans core, theme, and plugin files on a schedule and logs unexpected changes.', 'consucorner-security' ),
						'default' => true,
					),
					'ccs_db_disable_file_edit' => array(
						'label'   => __( 'Disable file editor in admin', 'consucorner-security' ),
						'desc'    => __( 'Prevents editing theme/plugin files from wp-admin, reducing damage from compromised admin accounts.', 'consucorner-security' ),
						'default' => true,
					),
					'ccs_db_activity_monitor'  => array(
						'label'   => __( 'Activity monitor', 'consucorner-security' ),
						'desc'    => __( 'Tracks important admin, user, plugin, file, and order changes for audit visibility.', 'consucorner-security' ),
						'default' => true,
					),
				),
			),
			'audit'    => array(
				'label'       => __( 'Audit Log', 'consucorner-security' ),
				'description' => __( 'Admin event logging and email alerts.', 'consucorner-security' ),
				'icon'        => 'audit',
				'slug'        => 'audit-log',
				'options'     => array(
					'ccs_log_admin_events'  => array(
						'label'   => __( 'Log admin events', 'consucorner-security' ),
						'desc'    => __( 'Records security-relevant admin actions so you can investigate changes later.', 'consucorner-security' ),
						'default' => true,
					),
					'ccs_log_email_alerts'  => array(
						'label'   => __( 'Email security alerts', 'consucorner-security' ),
						'desc'    => __( 'Sends configured email alerts for critical attacks and important security events.', 'consucorner-security' ),
						'default' => true,
					),
					'ccs_log_weekly_report' => array(
						'label'   => __( 'Weekly security report', 'consucorner-security' ),
						'desc'    => __( 'Optional weekly digest of attacks, blocked IPs, score changes, and file changes.', 'consucorner-security' ),
						'default' => false,
					),
				),
			),
		);

		return self::$registry_cache;
	}

	/**
	 * Resolve a registry option key to its wp_options row name.
	 *
	 * @param string $key Short key, e.g. ccs_bot_allow_googlebot.
	 * @return string
	 */
	public static function option_name( $key ) {
		$suffix = $key;
		if ( 0 === strpos( $key, 'ccs_' ) ) {
			$suffix = substr( $key, 4 );
		}
		return CCS_OPTION_PREFIX . $suffix;
	}

	/**
	 * Read a boolean option, falling back to the registry default.
	 *
	 * @param string $key Option key.
	 * @return bool
	 */
	public static function get( $key ) {
		$defaults = self::get_flat_defaults();
		$default  = isset( $defaults[ $key ] ) ? (bool) $defaults[ $key ] : false;

		return (bool) get_option( self::option_name( $key ), $default ? 1 : 0 );
	}

	/**
	 * Persist a boolean option (stored as 0|1).
	 *
	 * @param string $key   Option key.
	 * @param bool   $value Value.
	 * @return bool
	 */
	public static function set( $key, $value ) {
		return update_option( self::option_name( $key ), $value ? 1 : 0, false );
	}

	/**
	 * Flat map of every registry key => recommended default bool.
	 *
	 * @return array<string, bool>
	 */
	public static function get_flat_defaults() {
		if ( null !== self::$defaults_cache ) {
			return self::$defaults_cache;
		}

		self::$defaults_cache = array();
		foreach ( self::get_registry() as $module ) {
			foreach ( $module['options'] as $key => $meta ) {
				self::$defaults_cache[ $key ] = ! empty( $meta['default'] );
			}
		}

		return self::$defaults_cache;
	}

	/**
	 * Seed all defaults at activation. Uses autoload=no to avoid wp_options
	 * bloat in get_alloptions().
	 */
	public static function seed_defaults() {
		foreach ( self::get_flat_defaults() as $key => $default ) {
			$name = self::option_name( $key );
			if ( false === get_option( $name, false ) ) {
				add_option( $name, $default ? 1 : 0, '', 'no' );
			}
		}
	}

	/**
	 * Master toggle: turn the *recommended* set of a module on/off.
	 * Critical-on options can never be forced off. Opt-in options
	 * (default false) are left alone so user preferences survive.
	 *
	 * @param string $module_id Module key.
	 * @param bool   $enabled   On or off.
	 */
	public static function set_module_enabled( $module_id, $enabled ) {
		$registry = self::get_registry();
		if ( ! isset( $registry[ $module_id ] ) ) {
			return;
		}

		foreach ( $registry[ $module_id ]['options'] as $key => $meta ) {
			$is_recommended = ! empty( $meta['default'] );
			$is_critical    = ! empty( $meta['critical'] );

			if ( ! $is_recommended ) {
				continue; // Never auto-toggle opt-in features.
			}

			if ( $is_critical && ! $enabled ) {
				continue; // Critical protections cannot be force-disabled.
			}

			self::set( $key, $enabled );
		}
	}

	/**
	 * Module master state — true when every *recommended* option is on.
	 *
	 * @param string $module_id Module id.
	 * @return bool
	 */
	public static function is_module_fully_enabled( $module_id ) {
		$registry = self::get_registry();
		if ( ! isset( $registry[ $module_id ] ) ) {
			return false;
		}

		foreach ( $registry[ $module_id ]['options'] as $key => $meta ) {
			if ( empty( $meta['default'] ) ) {
				continue; // Ignore opt-ins for master state.
			}
			if ( ! self::get( $key ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Security score 0–100 based on how many *recommended* options are
	 * actually enabled. Critical options weigh double. Opt-in features
	 * do not count against the score when off.
	 *
	 * @return int
	 */
	public static function get_security_score() {
		$total   = 0;
		$earned  = 0;

		foreach ( self::get_registry() as $module ) {
			foreach ( $module['options'] as $key => $meta ) {
				if ( empty( $meta['default'] ) ) {
					continue;
				}

				$weight = ! empty( $meta['critical'] ) ? 2 : 1;
				$total += $weight;

				if ( self::get( $key ) ) {
					$earned += $weight;
				}
			}
		}

		if ( $total < 1 ) {
			return 0;
		}

		return (int) round( ( $earned / $total ) * 100 );
	}

	/**
	 * Count critical protections currently disabled (red issues).
	 *
	 * @return int
	 */
	public static function count_critical_issues() {
		$issues = 0;
		foreach ( self::get_registry() as $module ) {
			foreach ( $module['options'] as $key => $meta ) {
				if ( ! empty( $meta['critical'] ) && ! self::get( $key ) ) {
					++$issues;
				}
			}
		}
		return $issues;
	}

	/**
	 * Registry meta for a single option.
	 *
	 * @param string $key Option key.
	 * @return array<string, mixed>|null
	 */
	public static function get_option_meta( $key ) {
		foreach ( self::get_registry() as $module ) {
			if ( isset( $module['options'][ $key ] ) ) {
				return $module['options'][ $key ];
			}
		}
		return null;
	}
}
