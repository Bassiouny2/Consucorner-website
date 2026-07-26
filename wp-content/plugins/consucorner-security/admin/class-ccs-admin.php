<?php
/**
 * Admin UI, menu registration, and AJAX handlers for ConsucCorner Security.
 *
 * @package Consucorner_Security
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CCS_Admin
 */
class CCS_Admin {

	const MENU_SLUG       = 'ccs-security';
	const SLUG_LOGS       = 'ccs-logs';
	const SLUG_ANALYTICS  = 'ccs-analytics';
	const SLUG_NOTIFY     = 'ccs-notifications';
	const SLUG_ADVANCED   = 'ccs-advanced-settings';
	const SLUG_DOCS       = 'ccs-documentation';
	const NONCE_NAME      = 'ccs_admin_nonce';

	/**
	 * Singleton.
	 *
	 * @var CCS_Admin|null
	 */
	private static $instance = null;

	/**
	 * Plugin admin hook suffixes registered via add_menu_page / add_submenu_page.
	 *
	 * @var string[]
	 */
	private $hook_suffixes = array();

	/**
	 * Get instance.
	 *
	 * @return CCS_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_ccs_toggle_option', array( $this, 'ajax_toggle_option' ) );
		add_action( 'wp_ajax_ccs_toggle_module', array( $this, 'ajax_toggle_module' ) );
		add_action( 'wp_ajax_ccs_save_module_settings', array( $this, 'ajax_save_module_settings' ) );
	}

	/**
	 * Register top-level menu + module submenus.
	 */
	public function register_menu() {
		$this->hook_suffixes = array();

		$top = add_menu_page(
			__( 'ConsucCorner Security', 'consucorner-security' ),
			__( 'ConsucCorner', 'consucorner-security' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' ),
			'dashicons-shield-alt',
			58
		);
		if ( $top ) {
			$this->hook_suffixes[] = $top;
		}

		$dash = add_submenu_page(
			self::MENU_SLUG,
			__( 'Security Dashboard', 'consucorner-security' ),
			__( 'Security', 'consucorner-security' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' )
		);
		if ( $dash ) {
			$this->hook_suffixes[] = $dash;
		}

		foreach ( CCS_Options::get_registry() as $module_id => $module ) {
			$slug = 'ccs-' . $module['slug'];
			$hook = add_submenu_page(
				self::MENU_SLUG,
				$module['label'],
				$module['label'],
				'manage_options',
				$slug,
				$this->make_module_renderer( $module_id )
			);
			if ( $hook ) {
				$this->hook_suffixes[] = $hook;
			}
		}

		// Phase 3 pages.
		$logs = add_submenu_page(
			self::MENU_SLUG,
			__( 'Security Logs', 'consucorner-security' ),
			__( 'Logs', 'consucorner-security' ),
			'manage_options',
			self::SLUG_LOGS,
			array( $this, 'render_logs_page' )
		);
		if ( $logs ) {
			$this->hook_suffixes[] = $logs;
		}

		$analytics = add_submenu_page(
			self::MENU_SLUG,
			__( 'Security Analytics', 'consucorner-security' ),
			__( 'Analytics', 'consucorner-security' ),
			'manage_options',
			self::SLUG_ANALYTICS,
			array( $this, 'render_analytics_page' )
		);
		if ( $analytics ) {
			$this->hook_suffixes[] = $analytics;
		}

		$notify = add_submenu_page(
			self::MENU_SLUG,
			__( 'Security Notifications', 'consucorner-security' ),
			__( 'Notifications', 'consucorner-security' ),
			'manage_options',
			self::SLUG_NOTIFY,
			array( $this, 'render_notifications_page' )
		);
		if ( $notify ) {
			$this->hook_suffixes[] = $notify;
		}

		$advanced = add_submenu_page(
			self::MENU_SLUG,
			__( 'Advanced Security Settings', 'consucorner-security' ),
			__( 'Advanced Settings', 'consucorner-security' ),
			'manage_options',
			self::SLUG_ADVANCED,
			array( $this, 'render_advanced_settings_page' )
		);
		if ( $advanced ) {
			$this->hook_suffixes[] = $advanced;
		}

		$docs = add_submenu_page(
			self::MENU_SLUG,
			__( 'Security Documentation', 'consucorner-security' ),
			__( 'Documentation', 'consucorner-security' ),
			'manage_options',
			self::SLUG_DOCS,
			array( $this, 'render_documentation_page' )
		);
		if ( $docs ) {
			$this->hook_suffixes[] = $docs;
		}
	}

	/**
	 * Build a render callback bound to a specific module id.
	 *
	 * @param string $module_id Module key.
	 * @return callable
	 */
	private function make_module_renderer( $module_id ) {
		$self = $this;
		return static function () use ( $self, $module_id ) {
			$self->render_module_page( $module_id );
		};
	}

	/**
	 * Enqueue admin assets only on plugin screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, $this->hook_suffixes, true ) ) {
			return;
		}

		wp_enqueue_style(
			'ccs-admin',
			CCS_PLUGIN_URL . 'admin/assets/css/ccs-admin.css',
			array(),
			CCS_VERSION
		);

		wp_enqueue_script(
			'ccs-admin',
			CCS_PLUGIN_URL . 'admin/assets/js/ccs-admin.js',
			array(),
			CCS_VERSION,
			true
		);

		wp_localize_script(
			'ccs-admin',
			'ccsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_NAME ),
				'restUrl' => esc_url_raw( rest_url( 'ccs/v1/' ) ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'saved'    => __( 'Saved', 'consucorner-security' ),
					'error'    => __( 'Could not save. Please try again.', 'consucorner-security' ),
					'blocked'  => __( 'This protection cannot be disabled — it keeps Google, Dokan, or GeIdeA working.', 'consucorner-security' ),
					'issues'   => __( 'Issues Found', 'consucorner-security' ),
					'protected' => __( 'Protected', 'consucorner-security' ),
				),
			)
		);

		// Phase 3 page-specific assets.
		$screen_map = array(
			self::SLUG_LOGS      => 'logs',
			self::SLUG_ANALYTICS => 'analytics',
			self::SLUG_NOTIFY    => 'notifications',
			self::SLUG_ADVANCED  => 'advanced',
			self::SLUG_DOCS      => 'documentation',
		);

		$current_slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$screen       = isset( $screen_map[ $current_slug ] ) ? $screen_map[ $current_slug ] : '';
		$is_module_screen = false;
		foreach ( CCS_Options::get_registry() as $module ) {
			if ( 'ccs-' . $module['slug'] === $current_slug ) {
				$is_module_screen = true;
				break;
			}
		}

		// Shared Phase 3 styles for logs/analytics/notifications/advanced + dashboard widget.
		wp_enqueue_style(
			'ccs-logs',
			CCS_PLUGIN_URL . 'admin/assets/css/ccs-logs.css',
			array( 'ccs-admin' ),
			CCS_VERSION
		);

		// Logs page bundle.
		if ( 'logs' === $screen ) {
			wp_enqueue_script(
				'ccs-logs',
				CCS_PLUGIN_URL . 'admin/assets/js/ccs-logs.js',
				array( 'ccs-admin', 'wp-i18n' ),
				CCS_VERSION,
				true
			);
		}

		// Analytics page — Chart.js + chart bootstrap.
		if ( 'analytics' === $screen ) {
			wp_enqueue_script(
				'ccs-chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
				array(),
				'4.4.1',
				true
			);
			wp_enqueue_script(
				'ccs-charts',
				CCS_PLUGIN_URL . 'admin/assets/js/ccs-charts.js',
				array( 'ccs-admin', 'ccs-chartjs' ),
				CCS_VERSION,
				true
			);
		}

		// Module settings pages: editable rules + compact module chart.
		if ( $is_module_screen ) {
			wp_enqueue_script(
				'ccs-chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
				array(),
				'4.4.1',
				true
			);
			wp_enqueue_script(
				'ccs-module-settings',
				CCS_PLUGIN_URL . 'admin/assets/js/ccs-module-settings.js',
				array( 'ccs-admin', 'ccs-chartjs' ),
				CCS_VERSION,
				true
			);
		}

		// Notifications + advanced settings share ccs-settings.js.
		if ( in_array( $screen, array( 'notifications', 'advanced' ), true ) ) {
			wp_enqueue_script(
				'ccs-settings',
				CCS_PLUGIN_URL . 'admin/assets/js/ccs-settings.js',
				array( 'ccs-admin' ),
				CCS_VERSION,
				true
			);
		}
	}

	/**
	 * Render the dashboard view.
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'consucorner-security' ) );
		}

		$registry = CCS_Options::get_registry();
		$score    = CCS_Options::get_security_score();
		$issues   = CCS_Options::count_critical_issues();
		$status   = $issues > 0 ? 'issues' : 'protected';

		include CCS_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render the Logs viewer page.
	 */
	public function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'consucorner-security' ) );
		}
		$summary = CCS_Stats::summary();
		include CCS_PLUGIN_DIR . 'admin/views/logs.php';
	}

	/**
	 * Render the Analytics / Charts page.
	 */
	public function render_analytics_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'consucorner-security' ) );
		}
		$score = CCS_Options::get_security_score();
		include CCS_PLUGIN_DIR . 'admin/views/analytics.php';
	}

	/**
	 * Render the Notifications settings page.
	 */
	public function render_notifications_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'consucorner-security' ) );
		}
		$recipients = CCS_Notifications::get_recipients();
		$thresholds = CCS_Notifications::get_thresholds();
		$templates  = CCS_Notifications::get_templates();
		$telegram   = CCS_Notifications::get_telegram();
		include CCS_PLUGIN_DIR . 'admin/views/notifications.php';
	}

	/**
	 * Render the Advanced Settings page.
	 */
	public function render_advanced_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'consucorner-security' ) );
		}
		$whitelist_ips     = CCS_IP_Manager::list_whitelist();
		$whitelist_domains = get_option( CCS_OPTION_PREFIX . 'whitelist_domains', array() );
		$whitelist_users   = get_option( CCS_OPTION_PREFIX . 'whitelist_users', array() );
		$country_rules     = get_option( CCS_OPTION_PREFIX . 'country_rules', array() );
		$rate_limit_rules  = get_option( CCS_OPTION_PREFIX . 'rate_limit_rules', array() );
		$nginx_rules       = get_option( CCS_OPTION_PREFIX . 'custom_nginx_rules', '' );
		$logs_settings     = array(
			'retention_days' => (int) get_option( CCS_OPTION_PREFIX . 'logs_retention_days', 90 ),
			'auto_clean'     => (bool) get_option( CCS_OPTION_PREFIX . 'logs_auto_clean', true ),
			'max_size_mb'    => (int) get_option( CCS_OPTION_PREFIX . 'logs_max_size_mb', 50 ),
			'async_logging'  => (bool) get_option( CCS_OPTION_PREFIX . 'logs_async', true ),
			'level'          => (string) get_option( CCS_OPTION_PREFIX . 'logs_level', 'all' ),
			'sample_rate'    => (int) get_option( CCS_OPTION_PREFIX . 'logs_sample_rate', 100 ),
		);
		$blocked_ips       = CCS_IP_Manager::list_blocked( array( 'per_page' => 50 ) );
		include CCS_PLUGIN_DIR . 'admin/views/advanced-settings.php';
	}

	/**
	 * Render the in-dashboard plugin documentation page.
	 */
	public function render_documentation_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'consucorner-security' ) );
		}
		$registry = CCS_Options::get_registry();
		include CCS_PLUGIN_DIR . 'admin/views/documentation.php';
	}

	/**
	 * Render the live-feed widget partial.
	 */
	public static function render_live_widget() {
		include CCS_PLUGIN_DIR . 'admin/views/partial-live-widget.php';
	}

	/**
	 * Render a single module settings page.
	 *
	 * @param string $module_id Module registry key.
	 */
	public function render_module_page( $module_id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'consucorner-security' ) );
		}

		$registry = CCS_Options::get_registry();
		if ( ! isset( $registry[ $module_id ] ) ) {
			wp_die( esc_html__( 'Invalid security module.', 'consucorner-security' ) );
		}

		$module = $registry[ $module_id ];
		$score  = CCS_Options::get_security_score();
		$issues = CCS_Options::count_critical_issues();
		$status = $issues > 0 ? 'issues' : 'protected';
		$module_settings = self::get_module_settings( $module_id );

		include CCS_PLUGIN_DIR . 'admin/views/module-settings.php';
	}

	/**
	 * AJAX: toggle a single option.
	 */
	public function ajax_toggle_option() {
		check_ajax_referer( self::NONCE_NAME, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'consucorner-security' ) ), 403 );
			return;
		}

		$key = isset( $_POST['option_key'] ) ? sanitize_key( wp_unslash( $_POST['option_key'] ) ) : '';
		$val = isset( $_POST['value'] ) ? (int) $_POST['value'] : 0;

		$meta = CCS_Options::get_option_meta( $key );
		if ( null === $meta ) {
			wp_send_json_error( array( 'message' => __( 'Unknown option.', 'consucorner-security' ) ) );
			return;
		}

		if ( ! $val && ! empty( $meta['critical'] ) ) {
			wp_send_json_error(
				array(
					'message'  => __( 'This protection cannot be turned off.', 'consucorner-security' ),
					'critical' => true,
				)
			);
			return;
		}

		CCS_Options::set( $key, (bool) $val );

		wp_send_json_success( $this->build_status_payload() );
	}

	/**
	 * AJAX: save editable rules/configuration for a module page.
	 */
	public function ajax_save_module_settings() {
		check_ajax_referer( self::NONCE_NAME, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'consucorner-security' ) ), 403 );
			return;
		}

		$module_id = isset( $_POST['module_id'] ) ? sanitize_key( wp_unslash( $_POST['module_id'] ) ) : '';
		$raw_json  = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '{}';
		$data      = json_decode( $raw_json, true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid settings payload.', 'consucorner-security' ) ), 400 );
			return;
		}

		$saved = self::save_module_settings( $module_id, $data );
		if ( is_wp_error( $saved ) ) {
			wp_send_json_error( array( 'message' => $saved->get_error_message() ), 400 );
			return;
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Settings saved.', 'consucorner-security' ),
				'settings' => $saved,
			)
		);
	}

	/**
	 * AJAX: master toggle for a module.
	 */
	public function ajax_toggle_module() {
		check_ajax_referer( self::NONCE_NAME, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'consucorner-security' ) ), 403 );
			return;
		}

		$module_id = isset( $_POST['module_id'] ) ? sanitize_key( wp_unslash( $_POST['module_id'] ) ) : '';
		$val       = isset( $_POST['value'] ) ? (int) $_POST['value'] : 0;

		$registry = CCS_Options::get_registry();
		if ( ! isset( $registry[ $module_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown module.', 'consucorner-security' ) ) );
			return;
		}

		CCS_Options::set_module_enabled( $module_id, (bool) $val );

		wp_send_json_success( $this->build_status_payload() );
	}

	/**
	 * Build status payload sent after each AJAX save.
	 *
	 * @return array<string, mixed>
	 */
	private function build_status_payload() {
		$registry = CCS_Options::get_registry();
		$options  = array();

		foreach ( $registry as $module ) {
			foreach ( array_keys( $module['options'] ) as $key ) {
				$options[ $key ] = CCS_Options::get( $key );
			}
		}

		$modules = array();
		foreach ( array_keys( $registry ) as $module_id ) {
			$modules[ $module_id ] = CCS_Options::is_module_fully_enabled( $module_id );
		}

		$issues = CCS_Options::count_critical_issues();

		return array(
			'score'   => CCS_Options::get_security_score(),
			'status'  => $issues > 0 ? 'issues' : 'protected',
			'issues'  => $issues,
			'options' => $options,
			'modules' => $modules,
		);
	}

	/**
	 * Module configuration defaults used by the editable rule panels.
	 *
	 * @param string $module_id Module id.
	 * @return array<string, mixed>
	 */
	public static function get_module_settings( $module_id ) {
		$defaults = array(
			'bot'      => array(
				'scrapers_list'       => class_exists( 'CCS_Bot_Protection' ) ? CCS_Bot_Protection::get_scraper_list() : array(),
				'curl_whitelist_paths'=> array( '/wc-api/', '/wp-json/dokan/', '/wp-json/wc/', '/wp-json/wc-auth/' ),
				'empty_ua_action'     => 'block',
			),
			'server'   => array(
				'general_rate'        => 60,
				'login_rate'          => 5,
				'checkout_rate'       => 10,
				'api_rate'            => 30,
				'blocked_countries'   => array(),
				'custom_nginx_rules'  => get_option( CCS_OPTION_PREFIX . 'custom_nginx_rules', '' ),
			),
			'login'    => array(
				'custom_url'          => '',
				'max_attempts'        => 5,
				'lockout_minutes'     => 30,
				'permanent_after'     => 10,
				'twofa_roles'         => array( 'administrator', 'shop_manager', 'dokan_vendor' ),
			),
			'firewall' => array(
				'max_upload_size'     => 20,
				'sql_patterns'        => array( 'UNION SELECT', 'DROP TABLE', 'SLEEP(', 'BENCHMARK(' ),
				'xss_patterns'        => array( '<script', 'javascript:', 'onerror=', '<iframe' ),
				'traversal_patterns'  => array( '../', '..\\\\', 'wp-config.php', '.env', '/etc/passwd' ),
			),
			'database' => array(
				'scan_frequency'      => 'daily',
				'extra_monitor_paths' => array(),
				'retention_days'      => (int) get_option( CCS_OPTION_PREFIX . 'logs_retention_days', 90 ),
				'activity_events'     => array( 'plugin_changes', 'theme_changes', 'user_changes' ),
			),
			'audit'    => array(
				'admin_events'        => array( 'settings_change', 'plugin_change', 'user_role_change', 'order_status_change' ),
				'critical_email_to'   => get_option( 'admin_email' ),
				'weekly_report_day'   => 'monday',
			),
		);

		$base  = isset( $defaults[ $module_id ] ) ? $defaults[ $module_id ] : array();
		$saved = get_option( CCS_OPTION_PREFIX . 'module_settings_' . $module_id, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		// Preserve standalone options already used elsewhere.
		if ( 'bot' === $module_id ) {
			$list = get_option( CCS_OPTION_PREFIX . 'bot_scrapers_list', null );
			if ( is_array( $list ) ) {
				$base['scrapers_list'] = $list;
			}
		}

		return wp_parse_args( $saved, $base );
	}

	/**
	 * Save a module's editable rules/configuration.
	 *
	 * @param string              $module_id Module id.
	 * @param array<string,mixed> $data      Raw settings.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function save_module_settings( $module_id, array $data ) {
		$registry = CCS_Options::get_registry();
		if ( ! isset( $registry[ $module_id ] ) ) {
			return new WP_Error( 'invalid_module', __( 'Invalid security module.', 'consucorner-security' ) );
		}

		$clean = array();

		switch ( $module_id ) {
			case 'bot':
				$clean['scrapers_list'] = self::sanitize_lines( isset( $data['scrapers_list'] ) ? $data['scrapers_list'] : '' );
				$clean['curl_whitelist_paths'] = self::sanitize_lines( isset( $data['curl_whitelist_paths'] ) ? $data['curl_whitelist_paths'] : '' );
				$clean['empty_ua_action'] = isset( $data['empty_ua_action'] ) && in_array( $data['empty_ua_action'], array( 'block', 'log' ), true ) ? $data['empty_ua_action'] : 'block';
				update_option( CCS_OPTION_PREFIX . 'bot_scrapers_list', $clean['scrapers_list'], false );
				break;

			case 'server':
				$clean['general_rate']       = max( 1, min( 1000, (int) ( $data['general_rate'] ?? 60 ) ) );
				$clean['login_rate']         = max( 1, min( 100, (int) ( $data['login_rate'] ?? 5 ) ) );
				$clean['checkout_rate']      = max( 1, min( 100, (int) ( $data['checkout_rate'] ?? 10 ) ) );
				$clean['api_rate']           = max( 1, min( 1000, (int) ( $data['api_rate'] ?? 30 ) ) );
				$clean['blocked_countries']  = self::sanitize_country_list( isset( $data['blocked_countries'] ) ? $data['blocked_countries'] : '' );
				$clean['custom_nginx_rules'] = sanitize_textarea_field( (string) ( $data['custom_nginx_rules'] ?? '' ) );
				update_option( CCS_OPTION_PREFIX . 'custom_nginx_rules', $clean['custom_nginx_rules'], false );
				break;

			case 'login':
				$clean['custom_url']      = sanitize_title( (string) ( $data['custom_url'] ?? '' ) );
				$clean['max_attempts']    = max( 1, min( 50, (int) ( $data['max_attempts'] ?? 5 ) ) );
				$clean['lockout_minutes'] = max( 1, min( 1440, (int) ( $data['lockout_minutes'] ?? 30 ) ) );
				$clean['permanent_after'] = max( 1, min( 100, (int) ( $data['permanent_after'] ?? 10 ) ) );
				$clean['twofa_roles']     = array_map( 'sanitize_key', (array) ( $data['twofa_roles'] ?? array() ) );
				break;

			case 'firewall':
				$clean['max_upload_size']    = max( 1, min( 256, (int) ( $data['max_upload_size'] ?? 20 ) ) );
				$clean['sql_patterns']       = self::sanitize_lines( isset( $data['sql_patterns'] ) ? $data['sql_patterns'] : '' );
				$clean['xss_patterns']       = self::sanitize_lines( isset( $data['xss_patterns'] ) ? $data['xss_patterns'] : '' );
				$clean['traversal_patterns'] = self::sanitize_lines( isset( $data['traversal_patterns'] ) ? $data['traversal_patterns'] : '' );
				break;

			case 'database':
				$clean['scan_frequency']      = isset( $data['scan_frequency'] ) && in_array( $data['scan_frequency'], array( 'hourly', 'twicedaily', 'daily', 'weekly' ), true ) ? $data['scan_frequency'] : 'daily';
				$clean['extra_monitor_paths'] = self::sanitize_lines( isset( $data['extra_monitor_paths'] ) ? $data['extra_monitor_paths'] : '' );
				$clean['retention_days']      = max( 7, min( 365, (int) ( $data['retention_days'] ?? 90 ) ) );
				$clean['activity_events']     = array_map( 'sanitize_key', (array) ( $data['activity_events'] ?? array() ) );
				update_option( CCS_OPTION_PREFIX . 'logs_retention_days', $clean['retention_days'], false );
				break;

			case 'audit':
				$clean['admin_events']      = array_map( 'sanitize_key', (array) ( $data['admin_events'] ?? array() ) );
				$clean['critical_email_to'] = sanitize_email( (string) ( $data['critical_email_to'] ?? get_option( 'admin_email' ) ) );
				$clean['weekly_report_day'] = sanitize_key( (string) ( $data['weekly_report_day'] ?? 'monday' ) );
				break;
		}

		update_option( CCS_OPTION_PREFIX . 'module_settings_' . $module_id, $clean, false );
		return $clean;
	}

	/**
	 * Sanitize one-value-per-line input.
	 *
	 * @param mixed $value Value.
	 * @return array<int,string>
	 */
	private static function sanitize_lines( $value ) {
		$raw   = is_array( $value ) ? implode( "\n", $value ) : (string) $value;
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$out   = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Sanitize comma/line-separated country codes.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int,string>
	 */
	private static function sanitize_country_list( $value ) {
		$raw   = is_array( $value ) ? implode( ',', $value ) : (string) $value;
		$parts = preg_split( '/[\s,]+/', $raw );
		$out   = array();
		foreach ( (array) $parts as $code ) {
			$code = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $code ), 0, 2 ) );
			if ( 2 === strlen( $code ) ) {
				$out[] = $code;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Render a compatibility badge.
	 *
	 * @param string $badge Badge type (google|dokan|geidea).
	 * @return string Escaped HTML.
	 */
	public static function render_badge( $badge ) {
		$labels = array(
			'google' => __( 'Safe for Google', 'consucorner-security' ),
			'dokan'  => __( 'Safe for Dokan', 'consucorner-security' ),
			'geidea' => __( 'GeIdeA OK', 'consucorner-security' ),
		);

		if ( ! isset( $labels[ $badge ] ) ) {
			return '';
		}

		return '<span class="ccs-badge ccs-badge--' . esc_attr( $badge ) . '">' . esc_html( $labels[ $badge ] ) . '</span>';
	}
}
