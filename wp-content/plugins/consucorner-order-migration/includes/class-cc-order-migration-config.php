<?php
/**
 * Load migration settings from the database (dashboard inputs) with an
 * optional migrate-orders.config.php fallback.
 *
 * @package ConsuCorner_Order_Migration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Migration configuration loader.
 */
class CC_Order_Migration_Config {

	const OPTION_KEY = 'cc_order_migration_settings';

	/**
	 * Cached config array.
	 *
	 * @var array|null
	 */
	private static $config = null;

	/**
	 * Path to the optional config file (fallback only).
	 *
	 * @return string
	 */
	public static function config_path() {
		return CC_ORDER_MIGRATION_DIR . 'migrate-orders.config.php';
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'source_url'         => '',
			'consumer_key'       => '',
			'consumer_secret'    => '',
			'per_page'           => 50,
			'sleep_seconds'      => 1,
			'statuses'           => array( 'completed', 'processing', 'on-hold', 'cancelled', 'refunded', 'failed', 'pending' ),
			'dry_run'            => false,
			'max_orders'         => 0,
			'start_page'         => 1,
			'migration_meta_key' => '_cc_migrated_from_order_id',
			'skip_source_trash'  => true,
			'fetch_full_order'   => true,
			'parent_only'        => true,
			'source_site_label'  => '',
		);
	}

	/**
	 * Raw stored settings (DB first, then config file), without validation.
	 *
	 * @return array
	 */
	public static function get_raw() {
		$defaults = self::defaults();

		// 1) Config file fallback (lowest priority).
		$file = array();
		$path = self::config_path();
		if ( file_exists( $path ) ) {
			$loaded = require $path;
			if ( is_array( $loaded ) ) {
				$file = $loaded;
			}
		}

		// 2) DB settings entered from the dashboard (highest priority).
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, wp_parse_args( $file, $defaults ) );
	}

	/**
	 * Persist settings entered from the dashboard.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings saved.
	 */
	public static function save( array $input ) {
		$current = self::get_raw();

		$clean = array(
			'source_url'         => isset( $input['source_url'] ) ? esc_url_raw( trim( (string) $input['source_url'] ) ) : $current['source_url'],
			'consumer_key'       => isset( $input['consumer_key'] ) ? sanitize_text_field( trim( (string) $input['consumer_key'] ) ) : $current['consumer_key'],
			'consumer_secret'    => isset( $input['consumer_secret'] ) ? sanitize_text_field( trim( (string) $input['consumer_secret'] ) ) : $current['consumer_secret'],
			'per_page'           => isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : (int) $current['per_page'],
			'sleep_seconds'      => isset( $input['sleep_seconds'] ) ? max( 0, (float) $input['sleep_seconds'] ) : (float) $current['sleep_seconds'],
			'skip_source_trash'  => ! empty( $input['skip_source_trash'] ),
			'parent_only'        => ! empty( $input['parent_only'] ),
			'fetch_full_order'   => ! empty( $input['fetch_full_order'] ),
			'source_site_label'  => isset( $input['source_site_label'] ) ? sanitize_text_field( (string) $input['source_site_label'] ) : $current['source_site_label'],
			'migration_meta_key' => $current['migration_meta_key'],
		);

		if ( isset( $input['statuses'] ) && is_array( $input['statuses'] ) ) {
			$clean['statuses'] = array_values( array_map( 'sanitize_key', $input['statuses'] ) );
		} else {
			$clean['statuses'] = $current['statuses'];
		}

		// Keep a secret already saved if the field was submitted empty (masked field).
		if ( '' === $clean['consumer_secret'] && ! empty( $current['consumer_secret'] ) && empty( $input['consumer_secret_changed'] ) ) {
			$clean['consumer_secret'] = $current['consumer_secret'];
		}

		update_option( self::OPTION_KEY, $clean, false );
		self::reset();

		return $clean;
	}

	/**
	 * Load and validate configuration for running an import.
	 *
	 * @return array
	 * @throws RuntimeException When required credentials are missing.
	 */
	public static function get() {
		if ( null !== self::$config ) {
			return self::$config;
		}

		$config = wp_parse_args( self::get_raw(), self::defaults() );

		if ( empty( $config['source_url'] ) || empty( $config['consumer_key'] ) || empty( $config['consumer_secret'] ) ) {
			throw new RuntimeException( __( 'Source URL, Consumer key, and Consumer secret are required. Add them in Tools → Order Migration.', 'consucorner-order-migration' ) );
		}

		$config['per_page']      = max( 1, min( 100, (int) $config['per_page'] ) );
		$config['sleep_seconds'] = max( 0, (float) $config['sleep_seconds'] );
		$config['max_orders']    = max( 0, (int) $config['max_orders'] );
		$config['start_page']    = max( 1, (int) $config['start_page'] );

		self::$config = $config;

		return self::$config;
	}

	/**
	 * Whether required credentials are present.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$raw = self::get_raw();
		return ! empty( $raw['source_url'] ) && ! empty( $raw['consumer_key'] ) && ! empty( $raw['consumer_secret'] );
	}

	/**
	 * Reset cached config.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$config = null;
	}
}
