<?php
/**
 * Copy this file to migrate-orders.config.php and fill in your live-site credentials.
 * migrate-orders.config.php is gitignored — never commit API keys.
 *
 * @package ConsuCorner_Order_Migration
 */

defined( 'ABSPATH' ) || exit;

return array(
	// Source WooCommerce site (read-only via REST API).
	// Example: Cloudways old site importing INTO consucorner.com.
	'source_url'        => 'https://woocommerce-1495315-6472969.cloudwaysapps.com',

	// WooCommerce → Settings → Advanced → REST API.
	'consumer_key'      => 'ck_your_consumer_key_here',
	'consumer_secret'   => 'cs_your_consumer_secret_here',

	// Orders per API request (max 100).
	'per_page'          => 50,

	// Pause between API pages (seconds) to avoid rate limits.
	'sleep_seconds'     => 1,

	// WooCommerce order statuses to import. Use array( 'any' ) for all.
	'statuses'          => array( 'completed', 'processing', 'on-hold', 'cancelled', 'refunded', 'failed', 'pending' ),

	// Safety: set true to log actions without creating orders.
	'dry_run'           => false,

	// Stop after N orders (0 = no limit). Useful for testing.
	'max_orders'        => 0,

	// Resume from a specific API page (1-based).
	'start_page'        => 1,

	// Meta key used to track imported orders and prevent duplicates.
	'migration_meta_key' => '_cc_migrated_from_order_id',

	// Skip trashed orders on the source site.
	'skip_source_trash'  => true,

	// Fetch full order JSON (line items + meta) when the list endpoint is sparse.
	'fetch_full_order'   => true,

	// Only import parent checkout orders; skip Dokan/WC sub-orders (rebuilt on this site).
	'parent_only'        => true,

	// Label stored on imported orders (for debugging).
	'source_site_label'  => 'cloudways-staging',
);
