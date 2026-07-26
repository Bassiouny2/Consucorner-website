<?php
/**
 * Plugin Name:       ConsucCorner Security
 * Plugin URI:        https://consucorner.com
 * Description:       Enterprise security for the ConsucCorner WooCommerce marketplace — Dokan, GeIdeA, and Google safe.
 * Version:           1.1.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            ConsucCorner
 * License:           GPL-2.0-or-later
 * Text Domain:       consucorner-security
 * Domain Path:       /languages
 *
 * @package Consucorner_Security
 */

defined( 'ABSPATH' ) || exit;

define( 'CCS_VERSION', '1.1.2' );
define( 'CCS_PLUGIN_FILE', __FILE__ );
define( 'CCS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CCS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CCS_OPTION_PREFIX', 'ccs_option_' );
define( 'CCS_VERSION_OPTION', 'ccs_plugin_version' );

require_once CCS_PLUGIN_DIR . 'includes/class-ccs-options.php';
require_once CCS_PLUGIN_DIR . 'includes/class-ccs-logger.php';
require_once CCS_PLUGIN_DIR . 'includes/class-ccs-ip-manager.php';
require_once CCS_PLUGIN_DIR . 'includes/class-ccs-stats.php';
require_once CCS_PLUGIN_DIR . 'includes/class-ccs-notifications.php';
require_once CCS_PLUGIN_DIR . 'includes/modules/class-ccs-rest-api.php';
require_once CCS_PLUGIN_DIR . 'includes/modules/class-ccs-bot-protection.php';
require_once CCS_PLUGIN_DIR . 'includes/class-ccs-core.php';
require_once CCS_PLUGIN_DIR . 'admin/class-ccs-admin.php';

/**
 * Bootstrap plugin on plugins_loaded.
 */
function ccs_init() {
	CCS_Core::instance()->init();
	CCS_Logger::init();
	CCS_REST_API::init();
}
add_action( 'plugins_loaded', 'ccs_init' );

/**
 * Activation: create tables, seed defaults, schedule cron.
 */
function ccs_activate() {
	CCS_Options::seed_defaults();
	CCS_Logger::install_table();
	CCS_IP_Manager::install_tables();

	if ( ! wp_next_scheduled( CCS_Logger::CRON_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', CCS_Logger::CRON_HOOK );
	}

	update_option( CCS_VERSION_OPTION, CCS_VERSION, false );
}
register_activation_hook( __FILE__, 'ccs_activate' );

/**
 * Deactivation: clear cron, keep data intact.
 */
function ccs_deactivate() {
	$timestamp = wp_next_scheduled( CCS_Logger::CRON_HOOK );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, CCS_Logger::CRON_HOOK );
	}
}
register_deactivation_hook( __FILE__, 'ccs_deactivate' );

/**
 * Ensure tables exist when the plugin is auto-loaded after a manual
 * file copy or git pull (no activation hook). Cheap option check.
 */
add_action( 'admin_init', function () {
	$stored = get_option( CCS_VERSION_OPTION, '0' );
	if ( version_compare( $stored, CCS_VERSION, '<' ) ) {
		CCS_Logger::install_table();
		CCS_IP_Manager::install_tables();
		update_option( CCS_VERSION_OPTION, CCS_VERSION, false );
	}
} );
