<?php
/**
 * Plugin bootstrap.
 *
 * @package Consucorner_Security
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CCS_Core
 *
 * Loads admin UI and exposes the `ccs_loaded` hook for Phase 2 modules
 * to register their runtime behavior (bot protection, firewall, etc.).
 */
class CCS_Core {

	/**
	 * Singleton instance.
	 *
	 * @var CCS_Core|null
	 */
	private static $instance = null;

	/**
	 * Whether init has already run.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get instance.
	 *
	 * @return CCS_Core
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks. Safe to call multiple times.
	 */
	public function init() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// WP 6.7+ recommends translations on init; just-in-time loading
		// makes a separate call unnecessary, but we still register the
		// path so .mo files in /languages are discoverable.
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );

		if ( is_admin() ) {
			CCS_Admin::instance()->init();
		}

		// Phase 4 runtime engines. Each module honors its own toggles and
		// stays completely silent when the admin disables them.
		if ( class_exists( 'CCS_Bot_Protection' ) ) {
			CCS_Bot_Protection::init();
		}

		/**
		 * Fires after ConsucCorner Security core has wired its hooks.
		 * Additional Phase 4 modules attach here.
		 */
		do_action( 'ccs_loaded' );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'consucorner-security',
			false,
			dirname( plugin_basename( CCS_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
