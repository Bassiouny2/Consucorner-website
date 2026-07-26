<?php
/**
 * Phase 4 — Module 1: Bot Protection Engine.
 *
 * Runtime detection layer that runs on every front-end request at the
 * earliest possible point (`init`, priority 1). Honors the dashboard
 * toggles defined in CCS_Options: the module stays completely silent
 * if the admin disabled all recommended bot options.
 *
 * Hard guarantees:
 *  - Never blocks Google bots that pass reverse + forward DNS.
 *  - Never inspects Dokan / WooCommerce / GeIdeA / sitemap / robots
 *    / wp-cron / wp-admin / wp-json/wc-auth paths.
 *  - Never runs in admin, AJAX, CLI, or cron context.
 *  - Uses transients for Google DNS verification (6h TTL) so DNS
 *    lookups never happen on every request.
 *
 * @package Consucorner_Security
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CCS_Bot_Protection
 */
class CCS_Bot_Protection {

	/**
	 * Hard-coded Google user-agent fragments. Never editable from the UI.
	 */
	const GOOGLE_WHITELIST = array(
		'Googlebot',
		'Googlebot-Image',
		'Googlebot-Video',
		'Googlebot-News',
		'Google-InspectionTool',
		'AdsBot-Google',
		'AdsBot-Google-Mobile',
		'APIs-Google',
		'Mediapartners-Google',
		'Google-Read-Aloud',
		'Storebot-Google',
		'Google-Site-Verification',
		'Google Favicon',
		'GoogleProducer',
		'Google-Safety',
	);

	/**
	 * Reverse DNS suffixes that prove a request is a real Google crawler.
	 */
	const GOOGLE_DOMAINS = array(
		'.googlebot.com',
		'.google.com',
		'.googleusercontent.com',
	);

	/**
	 * Default scraper / abusive UA list. Stored in options so the admin
	 * can extend it later via an editor on the Bot Protection settings page.
	 */
	const DEFAULT_SCRAPERS = array(
		'AhrefsBot', 'AhrefsSiteAudit', 'SemrushBot',
		'DotBot', 'MJ12bot', 'BLEXBot', 'DataForSeoBot',
		'PetalBot', 'Baiduspider', 'YandexBot', 'YandexImages',
		'ia_archiver', 'HTTrack', 'WebCopier', 'Offline Explorer',
		'SiteSnagger', 'BlackWidow', 'WebStripper', 'WebSauger',
		'EmailCollector', 'EmailSiphon', 'WebBandit', 'EmailWolf',
		'ExtractorPro', 'ChinaClaw', 'Teleport', 'NinjaBot',
		'Scrapy', 'python-requests', 'Go-http-client',
		'libwww-perl', 'lwp-trivial', 'curl', 'wget',
		'PHP/', 'Java/', 'zgrab',
	);

	/**
	 * Paths the module must never touch. These are the hard golden rules
	 * from `.cursorrules` (GeIdeA, Dokan, WC, SEO, cron, well-known).
	 */
	const ALWAYS_ALLOW_PATHS = array(
		'/wc-api/',
		'/wp-json/dokan/',
		'/wp-json/wc/',
		'/wp-json/wc-auth/',
		'/wp-cron.php',
		'/wp-admin/',
		'/sitemap',
		'/robots.txt',
		'/feed',
		'/.well-known/',
	);

	/**
	 * Tool UAs that are sometimes legitimate. They are allowed when the
	 * request targets a known integration path (e.g. GeIdeA webhook).
	 */
	const TOOL_UAS = array( 'curl', 'wget', 'Go-http-client', 'python-requests', 'PHP/', 'Java/' );

	/**
	 * Meta/Facebook crawlers used for Messenger and Facebook link previews.
	 * These must be allowed to fetch public marketing URLs so previews do not
	 * fail with 403, but IP blocklist and honeypot rules still run before this.
	 */
	const META_PREVIEW_WHITELIST = array(
		'facebookexternalhit',
		'Facebot',
		'meta-externalagent',
		'Meta-ExternalAgent',
		'Meta-ExternalFetcher',
	);

	/**
	 * Paths where curl / wget / SDK UAs are allowed.
	 */
	const TOOL_WHITELIST_PATHS = array(
		'/wc-api/',
		'/wp-json/dokan/',
		'/wp-json/wc/',
		'/wp-json/wc-auth/',
	);

	/**
	 * Hook-binding guard.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Per-request memoization for is_real_google().
	 *
	 * @var array<string, bool>
	 */
	private static $google_cache = array();

	/**
	 * Wire the runtime hooks.
	 */
	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		// Priority 1 = right after WP fires `init`. We cannot run earlier
		// because options, plugin classes, and CCS_Logger must be loaded.
		add_action( 'init', array( __CLASS__, 'enforce' ), 1 );

		// Honeypot link rendering.
		add_action( 'wp_footer', array( __CLASS__, 'render_honeypot' ), 999 );
	}

	/**
	 * Main enforcement entry. Bound to `init` priority 1.
	 */
	public static function enforce() {
		// Never touch back-office, REST through admin-ajax, cron, CLI.
		if ( self::is_skip_context() ) {
			return;
		}

		// Master gate — silent if the user disabled the whole module via
		// the dashboard (all recommended bot options off).
		if ( ! self::is_module_enabled() ) {
			return;
		}

		$ip   = self::get_real_ip();
		$ua   = self::get_user_agent();
		$path = self::get_request_path();

		// 0. Honeypot trap URL — bot fell for it, block + auto-ban 30d.
		if ( CCS_Options::get( 'ccs_bot_honeypot' ) && 0 === strpos( $path, '/ccs-trap-' ) ) {
			self::trigger_honeypot( $ip, $ua, $path );
			// trigger_honeypot exits.
		}

		// 1. Admin whitelist wins everything.
		if ( $ip && CCS_IP_Manager::is_whitelisted( $ip ) ) {
			return;
		}

		// 2. Admin-explicit blocklist (still enforced even if scrapers
		// detection alone is off — IP block is an explicit admin action).
		if ( $ip && CCS_IP_Manager::is_blocked( $ip ) ) {
			self::log_and_block( $ip, $ua, $path, 'bot_blocked', 'critical', array( 'reason' => 'ip_blocked' ) );
		}

		// 3. Always-allow paths (GeIdeA, Dokan, WC, SEO, cron, etc.).
		foreach ( self::ALWAYS_ALLOW_PATHS as $allowed ) {
			if ( 0 === strpos( $path, $allowed ) ) {
				return;
			}
		}

		// 3b. Meta/Facebook link preview bots for Messenger/Facebook sharing.
		if ( self::is_meta_preview_crawler( $ua ) ) {
			if ( method_exists( 'CCS_Logger', 'log' ) ) {
				CCS_Logger::log( 'bot_allowed_meta', 'info', array(
					'ip'          => $ip,
					'user_agent'  => $ua,
					'request_uri' => $path,
					'details'     => array( 'reason' => 'meta_preview_crawler' ),
				) );
			}
			return;
		}

		// 4. Empty UA — only if scraper blocking is on.
		if ( CCS_Options::get( 'ccs_bot_block_scrapers' ) && '' === trim( (string) $ua ) ) {
			if ( 'log' === self::get_empty_ua_action() ) {
				CCS_Logger::log( 'bot_blocked', 'warning', array(
					'ip'          => $ip,
					'user_agent'  => $ua,
					'request_uri' => $path,
					'details'     => array( 'reason' => 'empty_user_agent_log_only' ),
				) );
				return;
			}
			self::log_and_block( $ip, $ua, $path, 'bot_blocked', 'warning', array( 'reason' => 'empty_user_agent' ) );
		}

		// 5. Google verification (RULE 1).
		if ( self::is_claiming_google( $ua ) ) {
			$verify = (bool) CCS_Options::get( 'ccs_bot_verify_reverse_dns' );

			if ( ! $verify ) {
				// Verification disabled → allow but log the visit.
				CCS_Logger::log( 'bot_allowed_google', 'info', array(
					'ip'          => $ip,
					'user_agent'  => $ua,
					'request_uri' => $path,
					'details'     => array( 'verified' => false ),
				) );
				return;
			}

			if ( self::is_real_google( $ip, $ua ) ) {
				CCS_Logger::log( 'bot_allowed_google', 'info', array(
					'ip'          => $ip,
					'user_agent'  => $ua,
					'request_uri' => $path,
					'details'     => array( 'verified' => true ),
				) );
				return;
			}

			// Fake Googlebot — block hard.
			self::log_and_block( $ip, $ua, $path, 'bot_blocked', 'critical', array( 'reason' => 'fake_google_bot' ) );
		}

		// 6. Scraper blacklist.
		if ( CCS_Options::get( 'ccs_bot_block_scrapers' ) ) {
			$match = self::match_scraper( $ua );

			if ( null !== $match ) {
				// Tool UAs allowed on integration paths only.
				if ( self::is_tool_ua( $match ) && self::is_tool_safe_path( $path ) ) {
					return;
				}

				self::log_and_block( $ip, $ua, $path, 'scraper_blocked', 'warning', array(
					'matched_ua' => $match,
				) );
			}
		}

		// 7. Everything else passes (CAPTCHA / behavioral check is Phase 5).
	}

	/* --------------------------------------------------------------------
	 * Module state
	 * ------------------------------------------------------------------ */

	/**
	 * The module is "on" if any of its recommended detection options is on.
	 * When the admin toggles the whole Bot Protection module OFF from the
	 * dashboard, every recommended option flips to false and this returns
	 * false — making the module completely silent.
	 *
	 * @return bool
	 */
	private static function is_module_enabled() {
		if ( ! class_exists( 'CCS_Options' ) ) {
			return false;
		}
		if ( CCS_Options::get( 'ccs_bot_block_scrapers' ) ) {
			return true;
		}
		if ( CCS_Options::get( 'ccs_bot_verify_reverse_dns' ) ) {
			return true;
		}
		if ( CCS_Options::get( 'ccs_bot_honeypot' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Skip non-front-end contexts. We never want to interfere with the
	 * admin UI, AJAX (admin-ajax is `is_admin()` in WP), wp-cli, or cron.
	 *
	 * @return bool
	 */
	private static function is_skip_context() {
		if ( is_admin() ) {
			return true;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		if ( 'cli' === PHP_SAPI ) {
			return true;
		}
		return false;
	}

	/* --------------------------------------------------------------------
	 * Request introspection
	 * ------------------------------------------------------------------ */

	/**
	 * Detect the visitor IP honoring Cloudways/Cloudflare proxies.
	 *
	 * @return string
	 */
	public static function get_real_ip() {
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
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return $ip;
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
	}

	/**
	 * Get the user-agent string (unslashed).
	 *
	 * @return string
	 */
	private static function get_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
	}

	/**
	 * Get the request path.
	 *
	 * @return string
	 */
	private static function get_request_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$p   = wp_parse_url( $uri, PHP_URL_PATH );
		return $p ? $p : '/';
	}

	/* --------------------------------------------------------------------
	 * Google verification
	 * ------------------------------------------------------------------ */

	/**
	 * Cheap UA-only check — does the request claim to be a Google bot?
	 *
	 * @param string $ua User-agent.
	 * @return bool
	 */
	public static function is_claiming_google( $ua ) {
		if ( '' === $ua ) {
			return false;
		}
		foreach ( self::GOOGLE_WHITELIST as $bot ) {
			if ( false !== stripos( $ua, $bot ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Cheap UA-only check for public Meta/Facebook link preview crawlers.
	 *
	 * @param string $ua User-agent.
	 * @return bool
	 */
	private static function is_meta_preview_crawler( $ua ) {
		if ( '' === $ua ) {
			return false;
		}

		foreach ( self::META_PREVIEW_WHITELIST as $bot ) {
			if ( false !== stripos( $ua, $bot ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Verify a request claiming Googlebot with reverse + forward DNS.
	 * Result is cached in a 6-hour transient per IP, plus a per-request
	 * memo so multiple checks in the same request hit no PHP work.
	 *
	 * @param string $ip Visitor IP.
	 * @param string $ua User-agent string.
	 * @return bool
	 */
	public static function is_real_google( $ip, $ua ) {
		if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		if ( ! self::is_claiming_google( $ua ) ) {
			return false;
		}
		if ( isset( self::$google_cache[ $ip ] ) ) {
			return self::$google_cache[ $ip ];
		}

		$cache_key = 'ccs_google_verify_' . md5( $ip );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			$bool                       = (bool) $cached;
			self::$google_cache[ $ip ]  = $bool;
			return $bool;
		}

		$result = false;

		$hostname = gethostbyaddr( $ip );
		if ( is_string( $hostname ) && $hostname !== $ip ) {
			$host_lower = strtolower( $hostname );
			foreach ( self::GOOGLE_DOMAINS as $domain ) {
				if ( self::ends_with( $host_lower, $domain ) ) {
					$forward = gethostbyname( $hostname );
					if ( $forward === $ip ) {
						$result = true;
					}
					break;
				}
			}
		}

		// Cache truthy for 6h, falsy for 1h to avoid amplifying bad lookups.
		set_transient( $cache_key, $result ? 1 : 0, $result ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		self::$google_cache[ $ip ] = $result;
		return $result;
	}

	/* --------------------------------------------------------------------
	 * Scraper detection
	 * ------------------------------------------------------------------ */

	/**
	 * Match the UA against the admin's scraper list.
	 *
	 * @param string $ua User-agent.
	 * @return string|null Matching pattern or null.
	 */
	private static function match_scraper( $ua ) {
		if ( '' === trim( $ua ) ) {
			return null;
		}
		foreach ( self::get_scraper_list() as $needle ) {
			$needle = trim( $needle );
			if ( '' === $needle ) {
				continue;
			}
			if ( false !== stripos( $ua, $needle ) ) {
				return $needle;
			}
		}
		return null;
	}

	/**
	 * Returns the scraper list. Admin-stored option overrides defaults.
	 *
	 * @return array<int, string>
	 */
	public static function get_scraper_list() {
		$stored = get_option( CCS_OPTION_PREFIX . 'bot_scrapers_list', null );
		if ( is_array( $stored ) && ! empty( $stored ) ) {
			return array_values( array_filter( array_map( 'strval', $stored ) ) );
		}
		return self::DEFAULT_SCRAPERS;
	}

	/**
	 * Is this UA a legitimate-when-correctly-used tool?
	 *
	 * @param string $needle Matched UA.
	 * @return bool
	 */
	private static function is_tool_ua( $needle ) {
		foreach ( self::TOOL_UAS as $tool ) {
			if ( 0 === strcasecmp( $tool, $needle ) || false !== stripos( $needle, $tool ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Is the request targeting an integration path where tools are OK?
	 *
	 * @param string $path Request path.
	 * @return bool
	 */
	private static function is_tool_safe_path( $path ) {
		foreach ( self::get_tool_whitelist_paths() as $allowed ) {
			if ( 0 === strpos( $path, $allowed ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Admin-editable safe paths for curl/wget/API tools.
	 *
	 * @return array<int,string>
	 */
	private static function get_tool_whitelist_paths() {
		$settings = get_option( CCS_OPTION_PREFIX . 'module_settings_bot', array() );
		if ( is_array( $settings ) && ! empty( $settings['curl_whitelist_paths'] ) && is_array( $settings['curl_whitelist_paths'] ) ) {
			return array_values( array_filter( array_map( 'strval', $settings['curl_whitelist_paths'] ) ) );
		}
		return self::TOOL_WHITELIST_PATHS;
	}

	/**
	 * Admin-editable empty User-Agent behavior.
	 *
	 * @return string block|log
	 */
	private static function get_empty_ua_action() {
		$settings = get_option( CCS_OPTION_PREFIX . 'module_settings_bot', array() );
		if ( is_array( $settings ) && ! empty( $settings['empty_ua_action'] ) && in_array( $settings['empty_ua_action'], array( 'block', 'log' ), true ) ) {
			return $settings['empty_ua_action'];
		}
		return 'block';
	}

	/* --------------------------------------------------------------------
	 * Honeypot
	 * ------------------------------------------------------------------ */

	/**
	 * Render the invisible honeypot link in the footer.
	 */
	public static function render_honeypot() {
		if ( ! CCS_Options::get( 'ccs_bot_honeypot' ) ) {
			return;
		}
		// Do not render for logged-in admins to avoid accidental clicks
		// during their own debugging sessions.
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}
		$url = esc_url( home_url( '/ccs-trap-' . self::honeypot_token() ) );
		echo '<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden">'
			. '<a href="' . $url . '" rel="nofollow noindex">Do not follow this link.</a>'
			. '</div>';
	}

	/**
	 * Stable per-site honeypot token derived from the site auth salt.
	 *
	 * @return string
	 */
	private static function honeypot_token() {
		return substr( wp_hash( 'ccs-honeypot-trap', 'auth' ), 0, 12 );
	}

	/**
	 * Bot fell into the honeypot — log + 30d auto block + 403.
	 *
	 * @param string $ip   Visitor IP.
	 * @param string $ua   User-agent.
	 * @param string $path Request path.
	 */
	private static function trigger_honeypot( $ip, $ua, $path ) {
		// Never auto-block whitelisted IPs (e.g. admin during testing).
		if ( $ip && ! CCS_IP_Manager::is_whitelisted( $ip ) ) {
			CCS_IP_Manager::block_ip( $ip, 'Honeypot triggered', 30 * DAY_IN_SECONDS, 'auto' );
		}

		CCS_Logger::log( 'bot_blocked', 'critical', array(
			'ip'          => $ip,
			'user_agent'  => $ua,
			'request_uri' => $path,
			'details'     => array( 'reason' => 'honeypot_triggered' ),
		) );

		self::send_403( 'honeypot_triggered' );
	}

	/* --------------------------------------------------------------------
	 * Block helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Log the event and stop the request with HTTP 403.
	 *
	 * @param string               $ip         Visitor IP.
	 * @param string               $ua         User-agent.
	 * @param string               $path       Request path.
	 * @param string               $event_type CCS_Logger event type.
	 * @param string               $severity   info|warning|critical.
	 * @param array<string, mixed> $details    Extra log context.
	 */
	private static function log_and_block( $ip, $ua, $path, $event_type, $severity, array $details = array() ) {
		CCS_Logger::log( $event_type, $severity, array(
			'ip'          => $ip,
			'user_agent'  => $ua,
			'request_uri' => $path,
			'details'     => $details,
		) );
		self::send_403( $event_type );
	}

	/**
	 * Send a generic 403 page that does not reveal the plugin/theme name.
	 * Forces the logger queue to flush so the event is persisted.
	 *
	 * @param string $tag Internal reason tag (header only, not body).
	 */
	private static function send_403( $tag ) {
		// Make sure pending log rows are written before we exit.
		if ( method_exists( 'CCS_Logger', 'flush' ) ) {
			CCS_Logger::flush();
		}

		if ( ! headers_sent() ) {
			status_header( 403 );
			nocache_headers();
			header( 'Content-Type: text/html; charset=UTF-8' );
			header( 'X-Robots-Tag: noindex, nofollow' );
			header( 'X-CCS-Block: ' . preg_replace( '/[^a-z0-9_\-]/i', '', (string) $tag ) );
		}

		echo "<!DOCTYPE html>\n";
		echo "<html><head><meta charset=\"utf-8\"><title>Access denied</title>";
		echo "<meta name=\"robots\" content=\"noindex,nofollow\"></head>";
		echo "<body style=\"font-family:system-ui,Arial,sans-serif;text-align:center;padding:60px 20px;color:#333\">";
		echo "<h1 style=\"font-size:28px;margin:0 0 8px\">403 Forbidden</h1>";
		echo "<p style=\"margin:0;color:#666\">Your request was denied.</p>";
		echo "</body></html>";
		exit;
	}

	/* --------------------------------------------------------------------
	 * Small utils
	 * ------------------------------------------------------------------ */

	/**
	 * Does `$haystack` end with `$needle` (case-insensitive)?
	 *
	 * @param string $haystack String to inspect.
	 * @param string $needle   Suffix.
	 * @return bool
	 */
	private static function ends_with( $haystack, $needle ) {
		$len = strlen( $needle );
		if ( 0 === $len ) {
			return true;
		}
		return substr( $haystack, -$len ) === $needle;
	}
}
