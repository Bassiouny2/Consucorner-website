<?php
/**
 * Plugin Name: ConsuCorner GTM
 * Description: Google Tag Manager, dataLayer ecommerce tracking, and GTM API auto-setup for ConsuCorner.
 * Version: 2.2.0
 * Author: ConsuCorner
 * Requires PHP: 7.4
 * Text Domain: consucorner-gtm
 *
 * @package ConsuCorner_GTM
 */

defined( 'ABSPATH' ) || exit;

define( 'CC_GTM_VERSION', '2.2.0' );
define( 'CC_GTM_PLUGIN_FILE', __FILE__ );
define( 'CC_GTM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CC_GTM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'CC_GTM_PURCHASE_META' ) ) {
	define( 'CC_GTM_PURCHASE_META', '_cc_gtm_purchase_tracked' );
}

if ( ! defined( 'CC_GTM_LIST_LIMIT' ) ) {
	define( 'CC_GTM_LIST_LIMIT', 20 );
}

require_once CC_GTM_PLUGIN_DIR . 'includes/class-cc-gtm-settings.php';
require_once CC_GTM_PLUGIN_DIR . 'includes/class-cc-gtm-logger.php';
require_once CC_GTM_PLUGIN_DIR . 'includes/class-cc-gtm-rankmath.php';
require_once CC_GTM_PLUGIN_DIR . 'includes/class-cc-gtm-conflicts.php';
require_once CC_GTM_PLUGIN_DIR . 'includes/class-cc-gtm-woocommerce.php';
require_once CC_GTM_PLUGIN_DIR . 'includes/class-cc-gtm-datalayer.php';
require_once CC_GTM_PLUGIN_DIR . 'includes/class-cc-gtm-google-auth.php';
require_once CC_GTM_PLUGIN_DIR . 'includes/class-cc-gtm-api.php';
require_once CC_GTM_PLUGIN_DIR . 'includes/class-cc-gtm-auto-setup.php';
require_once CC_GTM_PLUGIN_DIR . 'includes/class-cc-gtm-plugin.php';
require_once CC_GTM_PLUGIN_DIR . 'includes/class-cc-gtm-admin.php';

register_activation_hook(
	__FILE__,
	static function () {
		if ( false === get_option( CC_GTM_Settings::OPTION_KEY, false ) ) {
			update_option( CC_GTM_Settings::OPTION_KEY, CC_GTM_Settings::defaults(), false );
		}
	}
);

CC_GTM_Plugin::init();

/* Backward compatibility for theme and legacy code. */

/**
 * @param WC_Product $product Product.
 * @param int        $qty Qty.
 * @return array<string, mixed>
 */
function cc_gtm_product_to_item( $product, $qty = 1 ) {
	return CC_GTM_WooCommerce::product_to_item( $product, $qty );
}

/**
 * @param int    $product_id ID.
 * @param string $taxonomy Taxonomy.
 * @return string|null
 */
function cc_gtm_first_term_slug( $product_id, $taxonomy ) {
	return CC_GTM_WooCommerce::first_term_slug( $product_id, $taxonomy );
}

/**
 * @param int $product_id ID.
 * @return string|null
 */
function cc_gtm_product_vendor( $product_id ) {
	return CC_GTM_WooCommerce::product_vendor( $product_id );
}

/**
 * @param WC_Cart $cart Cart.
 * @return array<int, array<string, mixed>>
 */
function cc_gtm_cart_items( $cart ) {
	return CC_GTM_WooCommerce::cart_items( $cart );
}

/**
 * @param array<int, WP_Post|int> $posts Posts.
 * @param int                     $limit Limit.
 * @return array<int, array<string, mixed>>
 */
function cc_gtm_items_from_posts( $posts, $limit = 0 ) {
	return CC_GTM_WooCommerce::items_from_posts( $posts, $limit );
}

/**
 * @param WC_Product $product Product.
 * @return array<string, string>
 */
function cc_gtm_product_data_attributes( $product ) {
	return CC_GTM_WooCommerce::product_data_attributes( $product );
}

/**
 * @param WC_Product $product Product.
 */
function cc_gtm_print_product_data_attributes( $product ) {
	CC_GTM_WooCommerce::print_product_data_attributes( $product );
}

/**
 * @param array<int, WP_Post> $posts Posts.
 * @return array<string, mixed>
 */
function cc_gtm_filter_ajax_gtm_payload( $posts ) {
	return CC_GTM_WooCommerce::filter_ajax_payload( $posts );
}

/**
 * Legacy class name used by the theme (CC_GTM_Plugin is final — use alias, not extend).
 */
if ( ! class_exists( 'Consucorner_GTM_Plugin', false ) ) {
	class_alias( CC_GTM_Plugin::class, 'Consucorner_GTM_Plugin' );
}
