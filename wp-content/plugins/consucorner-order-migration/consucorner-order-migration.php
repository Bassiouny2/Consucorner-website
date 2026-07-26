<?php
/**
 * Plugin Name: ConsuCorner Order Migration
 * Description: Import historical WooCommerce orders from a source site (e.g. Cloudways) into consucorner.com via REST API, with Dokan sub-order splitting and duplicate protection. Configure API keys and run "Import Now" from Tools → Order Migration.
 * Version: 1.2.4
 * Author: ConsuCorner
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Text Domain: consucorner-order-migration
 *
 * @package ConsuCorner_Order_Migration
 */

defined( 'ABSPATH' ) || exit;

define( 'CC_ORDER_MIGRATION_VERSION', '1.2.4' );
define( 'CC_ORDER_MIGRATION_FILE', __FILE__ );
define( 'CC_ORDER_MIGRATION_DIR', plugin_dir_path( __FILE__ ) );

require_once CC_ORDER_MIGRATION_DIR . 'includes/class-cc-order-migration-config.php';
require_once CC_ORDER_MIGRATION_DIR . 'includes/class-cc-order-migration-api.php';
require_once CC_ORDER_MIGRATION_DIR . 'includes/class-cc-order-migrator.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once CC_ORDER_MIGRATION_DIR . 'includes/class-cc-order-migration-cli.php';
	CC_Order_Migration_CLI::register();
}

if ( is_admin() ) {
	require_once CC_ORDER_MIGRATION_DIR . 'includes/class-cc-order-migration-admin.php';
	CC_Order_Migration_Admin::init();
}
