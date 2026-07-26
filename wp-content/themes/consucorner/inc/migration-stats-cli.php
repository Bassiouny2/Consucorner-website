<?php
/**
 * One-off migration stats for documentation. Run via WP-CLI eval-file.
 *
 * @package ConsuCorner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$stats = array(
	'products_publish'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'" ),
	'products_all'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status != 'trash'" ),
	'product_variations' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product_variation' AND post_status != 'trash'" ),
	'orders'             => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='shop_order'" ),
	'pages'              => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish'" ),
	'users'              => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ),
	'sellers'            => (int) count( get_users( array( 'role' => 'seller', 'fields' => 'ID' ) ) ),
	'customers'          => (int) count( get_users( array( 'role' => 'customer', 'fields' => 'ID' ) ) ),
);

$authors = $wpdb->get_results(
	"SELECT post_author, COUNT(*) AS c FROM {$wpdb->posts} WHERE post_type='product' AND post_status != 'trash' GROUP BY post_author ORDER BY c DESC",
	ARRAY_A
);

$product_authors = array();
foreach ( $authors as $row ) {
	$user = get_userdata( (int) $row['post_author'] );
	$product_authors[] = array(
		'author_id'   => (int) $row['post_author'],
		'user_login'  => $user ? $user->user_login : null,
		'product_count' => (int) $row['c'],
	);
}

$hpos = false;
if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
	$hpos = Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

$out = array(
	'stats'           => $stats,
	'product_authors' => $product_authors,
	'woocommerce'     => array(
		'currency'        => get_option( 'woocommerce_currency' ),
		'default_country' => get_option( 'woocommerce_default_country' ),
		'hpos_enabled'    => $hpos,
	),
	'term_counts'     => array(
		'product_cat'    => (int) wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ),
		'specialty'      => (int) wp_count_terms( array( 'taxonomy' => 'specialty', 'hide_empty' => false ) ),
		'procedure'      => (int) wp_count_terms( array( 'taxonomy' => 'procedure', 'hide_empty' => false ) ),
		'product_brand'  => (int) wp_count_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => false ) ),
		'product_tag'    => (int) wp_count_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false ) ),
	),
);

$types = $wpdb->get_results(
	"SELECT t.slug, COUNT(*) AS c FROM {$wpdb->term_relationships} tr
	INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
	INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
	WHERE tt.taxonomy = 'product_type' GROUP BY t.slug",
	ARRAY_A
);
$out['product_types'] = $types;
$out['products_with_sku'] = (int) $wpdb->get_var(
	"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} pm
	INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
	WHERE p.post_type = 'product' AND p.post_status != 'trash' AND pm.meta_key = '_sku' AND pm.meta_value != ''"
);

echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
