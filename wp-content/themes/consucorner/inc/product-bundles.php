<?php
/**
 * Product Bundles — mix-and-match packs.
 *
 * Admin defines a pool of eligible products, a bundle size N, a flat price P,
 * and (optionally) a vendor. Customers pick any mix of pool products totaling
 * exactly N items on the /bundles/ page; each pool line they choose is added
 * to the cart as its own line, tagged with a shared "instance" id so the
 * group can be priced at P/N per unit, framed together in cart/mini-cart,
 * and still list individual products at checkout.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

const CC_BUNDLE_POST_TYPE       = 'cc_bundle';
const CC_BUNDLE_TAG_TAXONOMY    = 'cc_bundle_tag';
const CC_BUNDLE_POOL_META       = '_cc_bundle_pool';
const CC_BUNDLE_SIZE_META       = '_cc_bundle_size';
const CC_BUNDLE_PRICE_META      = '_cc_bundle_price';
const CC_BUNDLE_VENDOR_META     = '_cc_bundle_vendor';
const CC_BUNDLE_ACTIVE_META     = '_cc_bundle_active';
const CC_CAMPAIGN_BUNDLES_META  = '_cc_campaign_bundles';
const CC_BUNDLE_PACK_KEY_META   = '_cc_bundle_pack_key';

/**
 * Generate a stable pack key slug.
 *
 * @return string
 */
function cc_campaign_generate_pack_key() {
	return 'pack-' . substr( md5( uniqid( '', true ) ), 0, 8 );
}

/**
 * Normalize one bundle pack array from storage.
 *
 * @param array<string,mixed> $raw     Raw pack row.
 * @param int                 $post_id Campaign post ID (for key fallback).
 * @return array<string,mixed>
 */
function cc_campaign_normalize_pack( $raw, $post_id = 0 ) {
	$raw = is_array( $raw ) ? $raw : array();

	$key = sanitize_key( (string) ( $raw['key'] ?? '' ) );
	if ( '' === $key ) {
		$key = cc_campaign_generate_pack_key();
	}

	$pool = array();
	foreach ( (array) ( $raw['pool'] ?? array() ) as $pid ) {
		$pid = absint( $pid );
		if ( $pid > 0 ) {
			$pool[] = $pid;
		}
	}

	$banner_images = array();
	foreach ( array_slice( (array) ( $raw['banner_images'] ?? array() ), 0, 3 ) as $img_id ) {
		$img_id = absint( $img_id );
		$banner_images[] = ( $img_id > 0 && wp_attachment_is_image( $img_id ) ) ? $img_id : 0;
	}
	while ( count( $banner_images ) < 3 ) {
		$banner_images[] = 0;
	}

	$featured_image = absint( $raw['featured_image'] ?? 0 );
	if ( $featured_image > 0 && ! wp_attachment_is_image( $featured_image ) ) {
		$featured_image = 0;
	}

	return array(
		'key'             => $key,
		'title'           => sanitize_text_field( (string) ( $raw['title'] ?? '' ) ),
		'subtitle'        => sanitize_text_field( (string) ( $raw['subtitle'] ?? '' ) ),
		'cta_label'       => sanitize_text_field( (string) ( $raw['cta_label'] ?? '' ) ),
		'featured_image'  => $featured_image,
		'banner_images'   => $banner_images,
		'pool'            => array_values( array_unique( $pool ) ),
		'size'            => absint( $raw['size'] ?? 0 ),
		'price'           => (float) wc_format_decimal( $raw['price'] ?? 0 ),
		'active'          => ! empty( $raw['active'] ),
	);
}

/**
 * Build one pack from legacy flat bundle meta.
 *
 * @param int $campaign_id Campaign post ID.
 * @return array<int,array<string,mixed>>
 */
function cc_campaign_migrate_legacy_bundles( $campaign_id ) {
	$campaign_id = absint( $campaign_id );
	$pool        = get_post_meta( $campaign_id, CC_BUNDLE_POOL_META, true );
	$size        = absint( get_post_meta( $campaign_id, CC_BUNDLE_SIZE_META, true ) );
	$price       = (float) wc_format_decimal( get_post_meta( $campaign_id, CC_BUNDLE_PRICE_META, true ) );
	$active      = '1' === (string) get_post_meta( $campaign_id, CC_BUNDLE_ACTIVE_META, true );

	if ( ! is_array( $pool ) ) {
		$pool = array();
	}

	$normalized_pool = array();
	foreach ( $pool as $pid ) {
		$pid = absint( $pid );
		if ( $pid > 0 ) {
			$normalized_pool[] = $pid;
		}
	}

	if ( empty( $normalized_pool ) && $size < 1 && $price <= 0 ) {
		return array();
	}

	return array(
		cc_campaign_normalize_pack(
			array(
				'key'    => 'pack-default',
				'pool'   => $normalized_pool,
				'size'   => $size,
				'price'  => $price,
				'active' => $active,
			),
			$campaign_id
		),
	);
}

/**
 * All bundle packs for a campaign (migrates legacy meta when needed).
 *
 * @param int $campaign_id Campaign post ID.
 * @return array<int,array<string,mixed>>
 */
function cc_campaign_get_bundles( $campaign_id ) {
	$campaign_id = absint( $campaign_id );
	$stored      = get_post_meta( $campaign_id, CC_CAMPAIGN_BUNDLES_META, true );

	if ( ! is_array( $stored ) || empty( $stored ) ) {
		return cc_campaign_migrate_legacy_bundles( $campaign_id );
	}

	$packs = array();
	foreach ( $stored as $raw ) {
		$packs[] = cc_campaign_normalize_pack( $raw, $campaign_id );
	}

	return $packs;
}

/**
 * Active, complete bundle packs for frontend display.
 *
 * @param int $campaign_id Campaign post ID.
 * @return array<int,array<string,mixed>>
 */
function cc_campaign_get_active_bundles( $campaign_id ) {
	$active = array();
	foreach ( cc_campaign_get_bundles( $campaign_id ) as $pack ) {
		if ( empty( $pack['active'] ) || empty( $pack['pool'] ) || (int) $pack['size'] < 1 || (float) $pack['price'] <= 0 ) {
			continue;
		}
		$active[] = $pack;
	}
	return $active;
}

/**
 * Find one pack by key on a campaign.
 *
 * @param int    $campaign_id Campaign post ID.
 * @param string $pack_key    Pack key.
 * @return array<string,mixed>|null
 */
function cc_campaign_find_pack( $campaign_id, $pack_key ) {
	$pack_key = sanitize_key( (string) $pack_key );
	if ( '' === $pack_key ) {
		return null;
	}
	foreach ( cc_campaign_get_bundles( $campaign_id ) as $pack ) {
		if ( $pack['key'] === $pack_key ) {
			return $pack;
		}
	}
	return null;
}

/**
 * Resolve a pack for builder/cart — explicit key or first active pack.
 *
 * @param int    $campaign_id Campaign post ID.
 * @param string $pack_key    Optional pack key.
 * @return array<string,mixed>|null
 */
function cc_campaign_resolve_pack( $campaign_id, $pack_key = '' ) {
	$pack_key = sanitize_key( (string) $pack_key );
	if ( '' !== $pack_key ) {
		$pack = cc_campaign_find_pack( $campaign_id, $pack_key );
		return ( $pack && ! empty( $pack['active'] ) ) ? $pack : null;
	}

	$active = cc_campaign_get_active_bundles( $campaign_id );
	return ! empty( $active ) ? $active[0] : null;
}

/**
 * Sync first pack + any-active flag into legacy flat meta for backward compat.
 *
 * @param int   $campaign_id Campaign post ID.
 * @param array $packs       Normalized packs.
 * @return void
 */
function cc_campaign_sync_legacy_meta( $campaign_id, $packs ) {
	$campaign_id = absint( $campaign_id );
	$packs       = is_array( $packs ) ? $packs : array();
	$first       = ! empty( $packs ) ? $packs[0] : null;
	$any_active  = false;

	foreach ( $packs as $pack ) {
		if ( ! empty( $pack['active'] ) ) {
			$any_active = true;
			break;
		}
	}

	update_post_meta( $campaign_id, CC_BUNDLE_ACTIVE_META, $any_active ? '1' : '0' );

	if ( ! is_array( $first ) ) {
		delete_post_meta( $campaign_id, CC_BUNDLE_POOL_META );
		delete_post_meta( $campaign_id, CC_BUNDLE_SIZE_META );
		delete_post_meta( $campaign_id, CC_BUNDLE_PRICE_META );
		return;
	}

	update_post_meta( $campaign_id, CC_BUNDLE_POOL_META, (array) $first['pool'] );
	if ( (int) $first['size'] > 0 ) {
		update_post_meta( $campaign_id, CC_BUNDLE_SIZE_META, (int) $first['size'] );
	} else {
		delete_post_meta( $campaign_id, CC_BUNDLE_SIZE_META );
	}
	if ( (float) $first['price'] > 0 ) {
		update_post_meta( $campaign_id, CC_BUNDLE_PRICE_META, (float) $first['price'] );
	} else {
		delete_post_meta( $campaign_id, CC_BUNDLE_PRICE_META );
	}
}

/**
 * Register CPT + taxonomy.
 */
function cc_bundles_register() {
	register_post_type(
		CC_BUNDLE_POST_TYPE,
		array(
			'labels'              => array(
				'name'               => __( 'Campaigns', 'consucorner' ),
				'singular_name'      => __( 'Campaign', 'consucorner' ),
				'add_new'            => __( 'Add Campaign', 'consucorner' ),
				'add_new_item'       => __( 'Add New Campaign', 'consucorner' ),
				'edit_item'          => __( 'Edit Campaign', 'consucorner' ),
				'new_item'           => __( 'New Campaign', 'consucorner' ),
				'view_item'          => __( 'View Campaign', 'consucorner' ),
				'search_items'       => __( 'Search Campaigns', 'consucorner' ),
				'not_found'          => __( 'No campaigns found.', 'consucorner' ),
				'not_found_in_trash' => __( 'No campaigns found in Trash.', 'consucorner' ),
				'menu_name'          => __( 'Campaigns', 'consucorner' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=product',
			'capability_type'     => 'product',
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'thumbnail', 'excerpt' ),
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'show_in_rest'        => false,
		)
	);

	register_taxonomy(
		CC_BUNDLE_TAG_TAXONOMY,
		CC_BUNDLE_POST_TYPE,
		array(
			'labels'            => array(
				'name'          => __( 'Bundle Tags', 'consucorner' ),
				'singular_name' => __( 'Bundle Tag', 'consucorner' ),
				'search_items'  => __( 'Search Bundle Tags', 'consucorner' ),
				'all_items'     => __( 'All Bundle Tags', 'consucorner' ),
				'edit_item'     => __( 'Edit Bundle Tag', 'consucorner' ),
				'update_item'   => __( 'Update Bundle Tag', 'consucorner' ),
				'add_new_item'  => __( 'Add New Bundle Tag', 'consucorner' ),
				'new_item_name' => __( 'New Bundle Tag Name', 'consucorner' ),
				'menu_name'     => __( 'Bundle Tags', 'consucorner' ),
			),
			'public'            => false,
			'show_ui'           => false,
			'show_in_menu'      => false,
			'show_admin_column' => false,
			'hierarchical'      => false,
			'rewrite'           => false,
			'query_var'         => false,
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', 'cc_bundles_register', 20 );

/**
 * Public /bundles/ page URL.
 *
 * @return string
 */
function cc_bundles_get_base_url() {
	$page = get_page_by_path( 'bundles', OBJECT, 'page' );
	if ( $page instanceof WP_Post ) {
		$link = get_permalink( $page );
		if ( $link ) {
			return trailingslashit( $link );
		}
	}
	return trailingslashit( home_url( '/bundles/' ) );
}

/**
 * Resolve and validate a bundle tag slug.
 *
 * @param string $raw Raw slug.
 * @return string
 */
function cc_bundles_resolve_tag_slug( $raw ) {
	$slug = sanitize_title( (string) $raw );
	if ( '' === $slug || ! taxonomy_exists( CC_BUNDLE_TAG_TAXONOMY ) ) {
		return '';
	}
	$term = get_term_by( 'slug', $slug, CC_BUNDLE_TAG_TAXONOMY );
	return ( $term instanceof WP_Term ) ? $term->slug : '';
}

/**
 * Vendor + tag filters from the current request (?vendor=&tag=), mirroring
 * the Offers page pattern.
 *
 * @return array{vendor_id:int,tag_slug:string,vendor_username:string}
 */
function cc_bundles_get_filters_from_request() {
	$vendor_raw = isset( $_GET['vendor'] ) ? wp_unslash( $_GET['vendor'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tag_raw    = isset( $_GET['tag'] ) ? wp_unslash( $_GET['tag'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$vendor_id       = function_exists( 'cc_offers_resolve_vendor_id' ) ? cc_offers_resolve_vendor_id( $vendor_raw ) : 0;
	$tag_slug        = cc_bundles_resolve_tag_slug( $tag_raw );
	$vendor_username = ( $vendor_id && function_exists( 'cc_offers_get_vendor_username_for_url' ) )
		? cc_offers_get_vendor_username_for_url( $vendor_id )
		: '';

	return array(
		'vendor_id'       => $vendor_id,
		'tag_slug'        => $tag_slug,
		'vendor_username' => $vendor_username,
	);
}

/**
 * Build a shareable bundles campaign URL.
 *
 * @param string|int $vendor Vendor username or user ID.
 * @param string     $tag    Bundle tag slug.
 * @return string
 */
function cc_bundles_build_url( $vendor = '', $tag = '' ) {
	$vendor_id = function_exists( 'cc_offers_resolve_vendor_id' ) ? cc_offers_resolve_vendor_id( $vendor ) : 0;
	$tag_slug  = cc_bundles_resolve_tag_slug( $tag );
	$base      = cc_bundles_get_base_url();

	$args = array();
	if ( $vendor_id && function_exists( 'cc_offers_get_vendor_username_for_url' ) ) {
		$args['vendor'] = cc_offers_get_vendor_username_for_url( $vendor_id );
	}
	if ( '' !== $tag_slug ) {
		$args['tag'] = $tag_slug;
	}

	return empty( $args ) ? $base : add_query_arg( $args, $base );
}

/**
 * All bundle tags for admin builders.
 *
 * @return WP_Term[]
 */
function cc_bundles_get_tags_list() {
	if ( ! taxonomy_exists( CC_BUNDLE_TAG_TAXONOMY ) ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => CC_BUNDLE_TAG_TAXONOMY,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	return ( ! is_wp_error( $terms ) && is_array( $terms ) ) ? $terms : array();
}

/**
 * Eligible product pool for a bundle pack.
 *
 * @param int    $bundle_id Bundle/campaign post ID.
 * @param string $pack_key  Optional pack key.
 * @return int[]
 */
function cc_bundles_get_pool( $bundle_id, $pack_key = '' ) {
	$pack = cc_campaign_resolve_pack( absint( $bundle_id ), $pack_key );
	if ( is_array( $pack ) && ! empty( $pack['pool'] ) ) {
		return array_values( array_map( 'absint', $pack['pool'] ) );
	}

	$raw = get_post_meta( absint( $bundle_id ), CC_BUNDLE_POOL_META, true );
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$pool = array();
	foreach ( $raw as $pid ) {
		$pid = absint( $pid );
		if ( $pid > 0 ) {
			$pool[] = $pid;
		}
	}
	return array_values( array_unique( $pool ) );
}

/**
 * Bundle size (N) — how many items (by quantity) a customer must pick.
 *
 * @param int    $bundle_id Bundle post ID.
 * @param string $pack_key  Optional pack key.
 * @return int
 */
function cc_bundles_get_size( $bundle_id, $pack_key = '' ) {
	$pack = cc_campaign_resolve_pack( absint( $bundle_id ), $pack_key );
	if ( is_array( $pack ) ) {
		return absint( $pack['size'] );
	}
	return absint( get_post_meta( absint( $bundle_id ), CC_BUNDLE_SIZE_META, true ) );
}

/**
 * Bundle flat price (P) for a complete group of N items.
 *
 * @param int    $bundle_id Bundle post ID.
 * @param string $pack_key  Optional pack key.
 * @return float
 */
function cc_bundles_get_price( $bundle_id, $pack_key = '' ) {
	$pack = cc_campaign_resolve_pack( absint( $bundle_id ), $pack_key );
	if ( is_array( $pack ) ) {
		return (float) $pack['price'];
	}
	return (float) wc_format_decimal( get_post_meta( absint( $bundle_id ), CC_BUNDLE_PRICE_META, true ) );
}

/**
 * Vendor user ID assigned to a bundle (for campaign filtering).
 *
 * @param int $bundle_id Bundle post ID.
 * @return int
 */
function cc_bundles_get_vendor_id( $bundle_id ) {
	return absint( get_post_meta( absint( $bundle_id ), CC_BUNDLE_VENDOR_META, true ) );
}

/**
 * Whether the bundle (or any pack) is marked active.
 *
 * @param int $bundle_id Bundle post ID.
 * @return bool
 */
function cc_bundles_is_active( $bundle_id ) {
	$bundle_id = absint( $bundle_id );
	if ( ! empty( cc_campaign_get_active_bundles( $bundle_id ) ) ) {
		return true;
	}
	return '1' === (string) get_post_meta( $bundle_id, CC_BUNDLE_ACTIVE_META, true );
}

/**
 * Pool products with live availability data for the builder UI + validation.
 *
 * @param int    $bundle_id Bundle post ID.
 * @param string $pack_key  Optional pack key.
 * @return array<int,array{product_id:int,name:string,price:float,thumb:string,url:string,in_stock:bool,max_qty:int}>
 */
function cc_bundles_get_pool_availability( $bundle_id, $pack_key = '' ) {
	$items = array();

	foreach ( cc_bundles_get_pool( $bundle_id, $pack_key ) as $pid ) {
		$product = wc_get_product( $pid );
		if ( ! $product || ! $product->is_purchasable() ) {
			continue;
		}

		$in_stock = $product->is_in_stock();
		$max      = 0; // 0 = unlimited (only meaningful when in_stock is true).

		if ( $in_stock && $product->managing_stock() ) {
			$stock = $product->get_stock_quantity();
			$max   = ( null === $stock ) ? 0 : max( 0, (int) $stock );
		}

		$thumb = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );

		$items[] = array(
			'product_id' => $pid,
			'name'       => $product->get_name(),
			'price'      => (float) $product->get_price(),
			'thumb'      => $thumb ? $thumb : '',
			'url'        => get_permalink( $pid ),
			'in_stock'   => $in_stock,
			'max_qty'    => $max,
		);
	}

	return $items;
}

/**
 * Resolve the cover image URL for a bundle pack card.
 *
 * Priority: pack featured image → campaign featured image → empty (mosaic fallback).
 *
 * @param array<string,mixed> $pack      Normalized pack row.
 * @param int                 $bundle_id Campaign post ID.
 * @return string
 */
function cc_bundles_resolve_pack_cover_url( $pack, $bundle_id ) {
	$img_id = isset( $pack['featured_image'] ) ? absint( $pack['featured_image'] ) : 0;
	if ( $img_id > 0 && wp_attachment_is_image( $img_id ) ) {
		$url = (string) wp_get_attachment_image_url( $img_id, 'large' );
		if ( '' !== $url ) {
			return $url;
		}
	}

	$campaign_url = get_the_post_thumbnail_url( absint( $bundle_id ), 'large' );
	return $campaign_url ? (string) $campaign_url : '';
}

/**
 * Prepared display + builder data for a bundle card.
 *
 * @param int    $bundle_id Bundle/campaign ID.
 * @param string $pack_key  Optional pack key.
 * @return array<string,mixed>|null
 */
function cc_bundles_get_builder_data( $bundle_id, $pack_key = '' ) {
	$bundle_id = absint( $bundle_id );
	$bundle    = get_post( $bundle_id );
	if ( ! $bundle || CC_BUNDLE_POST_TYPE !== $bundle->post_type ) {
		return null;
	}

	$pack = cc_campaign_resolve_pack( $bundle_id, $pack_key );
	if ( ! is_array( $pack ) ) {
		return null;
	}
	if ( 'publish' !== $bundle->post_status ) {
		return null;
	}

	$size  = (int) $pack['size'];
	$price = (float) $pack['price'];
	$items = cc_bundles_get_pool_availability( $bundle_id, $pack['key'] );

	if ( $size < 1 || $price <= 0 || empty( $items ) ) {
		return null;
	}

	$achievable = 0;
	foreach ( $items as $item ) {
		if ( ! $item['in_stock'] ) {
			continue;
		}
		$achievable += ( 0 === $item['max_qty'] ) ? 999999 : $item['max_qty'];
	}

	$vendor_id = cc_bundles_get_vendor_id( $bundle_id );
	$title     = '' !== $pack['title'] ? (string) $pack['title'] : get_the_title( $bundle_id );
	$excerpt   = '' !== $pack['subtitle'] ? (string) $pack['subtitle'] : get_the_excerpt( $bundle_id );

	return array(
		'id'           => $bundle_id,
		'pack_key'     => (string) $pack['key'],
		'title'        => $title,
		'excerpt'      => $excerpt,
		'image'        => cc_bundles_resolve_pack_cover_url( $pack, $bundle_id ),
		'size'         => $size,
		'price'        => $price,
		'unit_price'   => $price / $size,
		'items'        => $items,
		'sellable'     => $achievable >= $size,
		'vendor_id'    => $vendor_id,
		'vendor_label' => ( $vendor_id && function_exists( 'cc_offers_get_vendor_label' ) ) ? cc_offers_get_vendor_label( $vendor_id ) : '',
	);
}

/**
 * Admin meta boxes.
 */
function cc_bundles_add_meta_boxes() {
	add_meta_box(
		'cc_bundle_details',
		__( 'Campaign bundles', 'consucorner' ),
		'cc_bundles_render_details_metabox',
		CC_BUNDLE_POST_TYPE,
		'normal',
		'high'
	);
	add_meta_box(
		'cc_bundle_campaign',
		__( 'Campaign link', 'consucorner' ),
		'cc_bundles_render_campaign_metabox',
		CC_BUNDLE_POST_TYPE,
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'cc_bundles_add_meta_boxes' );

/**
 * Enqueue WooCommerce product search on bundle edit screens.
 *
 * @param string $hook Admin hook.
 */
function cc_bundles_admin_assets( $hook ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || CC_BUNDLE_POST_TYPE !== $screen->post_type ) {
		return;
	}
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	wp_enqueue_style( 'woocommerce_admin_styles' );
	wp_enqueue_script( 'selectWoo' );
	wp_enqueue_script( 'wc-enhanced-select' );
	wp_enqueue_media();
	wp_enqueue_script(
		'cc-campaign-pack-images',
		get_template_directory_uri() . '/assets/js/admin-campaign-pack-images.js',
		array( 'jquery' ),
		defined( '_S_VERSION' ) ? _S_VERSION : '1.0.0',
		true
	);

	if ( ! wp_script_is( 'wc-enhanced-select', 'done' ) && ! wp_scripts()->get_data( 'wc-enhanced-select', 'data' ) ) {
		wp_localize_script(
			'wc-enhanced-select',
			'wc_enhanced_select_params',
			array(
				'i18n_no_matches'           => _x( 'No matches found', 'enhanced select', 'woocommerce' ),
				'i18n_ajax_error'           => _x( 'Loading failed', 'enhanced select', 'woocommerce' ),
				'i18n_input_too_short_1'    => _x( 'Please enter 1 or more characters', 'enhanced select', 'woocommerce' ),
				'i18n_input_too_short_n'    => _x( 'Please enter %qty% or more characters', 'enhanced select', 'woocommerce' ),
				'i18n_input_too_long_1'     => _x( 'Please delete 1 character', 'enhanced select', 'woocommerce' ),
				'i18n_input_too_long_n'     => _x( 'Please delete %qty% characters', 'enhanced select', 'woocommerce' ),
				'i18n_selection_too_long_1' => _x( 'You can only select 1 item', 'enhanced select', 'woocommerce' ),
				'i18n_selection_too_long_n' => _x( 'You can only select %qty% items', 'enhanced select', 'woocommerce' ),
				'i18n_load_more'            => _x( 'Loading more results&hellip;', 'enhanced select', 'woocommerce' ),
				'i18n_searching'            => _x( 'Searching&hellip;', 'enhanced select', 'woocommerce' ),
				'ajax_url'                  => admin_url( 'admin-ajax.php' ),
				'search_products_nonce'     => wp_create_nonce( 'search-products' ),
				'search_customers_nonce'    => wp_create_nonce( 'search-customers' ),
				'search_categories_nonce'   => wp_create_nonce( 'search-categories' ),
				'search_pages_nonce'        => wp_create_nonce( 'search-pages' ),
			)
		);
	}

}
add_action( 'admin_enqueue_scripts', 'cc_bundles_admin_assets' );

/**
 * Render one bundle pack row in the admin repeater.
 *
 * @param int                 $index    Row index.
 * @param array<string,mixed> $pack     Pack data.
 * @param int                 $post_id  Campaign post ID.
 * @return void
 */
function cc_bundles_render_pack_row( $index, $pack, $post_id = 0 ) {
	$index    = absint( $index );
	$post_id  = absint( $post_id );
	$key      = isset( $pack['key'] ) ? (string) $pack['key'] : cc_campaign_generate_pack_key();
	$title    = isset( $pack['title'] ) ? (string) $pack['title'] : '';
	$subtitle = isset( $pack['subtitle'] ) ? (string) $pack['subtitle'] : '';
	$cta      = isset( $pack['cta_label'] ) ? (string) $pack['cta_label'] : '';
	$pool     = isset( $pack['pool'] ) && is_array( $pack['pool'] ) ? $pack['pool'] : array();
	$size     = isset( $pack['size'] ) ? absint( $pack['size'] ) : 0;
	$price    = isset( $pack['price'] ) ? (float) $pack['price'] : 0;
	$active   = ! empty( $pack['active'] );
	$featured_image = isset( $pack['featured_image'] ) ? absint( $pack['featured_image'] ) : 0;
	$featured_url   = $featured_image ? (string) wp_get_attachment_image_url( $featured_image, 'medium' ) : '';
	$banner_images  = isset( $pack['banner_images'] ) && is_array( $pack['banner_images'] ) ? array_pad( array_slice( $pack['banner_images'], 0, 3 ), 3, 0 ) : array( 0, 0, 0 );
	$banner_labels  = array(
		__( 'Top circle', 'consucorner' ),
		__( 'Main circle', 'consucorner' ),
		__( 'Bottom circle', 'consucorner' ),
	);
	$currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'EGP';
	$pack_url = ( $post_id > 0 && function_exists( 'cc_offer_campaign_build_bundles_pack_url' ) && function_exists( 'cc_offer_campaign_get_slug' ) )
		? cc_offer_campaign_build_bundles_pack_url( cc_offer_campaign_get_slug( $post_id ), $key )
		: '';
	?>
	<div class="cc-campaign-pack" data-pack-index="<?php echo esc_attr( (string) $index ); ?>">
		<div class="cc-campaign-pack__head">
			<strong><?php esc_html_e( 'Bundle pack', 'consucorner' ); ?> <span class="cc-campaign-pack__num"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span></strong>
			<button type="button" class="button-link-delete cc-campaign-pack__remove"><?php esc_html_e( 'Remove', 'consucorner' ); ?></button>
		</div>
		<input type="hidden" name="cc_campaign_bundles[<?php echo esc_attr( (string) $index ); ?>][key]" value="<?php echo esc_attr( $key ); ?>" class="cc-campaign-pack__key" />

		<?php if ( '' !== $pack_url ) : ?>
			<p>
				<label><strong><?php esc_html_e( 'Pack link', 'consucorner' ); ?></strong></label><br />
				<input type="text" class="widefat cc-campaign-pack__url" readonly value="<?php echo esc_attr( $pack_url ); ?>" />
			</p>
		<?php endif; ?>

		<p>
			<label>
				<input type="checkbox" name="cc_campaign_bundles[<?php echo esc_attr( (string) $index ); ?>][active]" value="1" <?php checked( $active ); ?> />
				<?php esc_html_e( 'Active (visible on /bundles/)', 'consucorner' ); ?>
			</label>
		</p>

		<p>
			<label><strong><?php esc_html_e( 'Banner title', 'consucorner' ); ?></strong></label><br />
			<input type="text" class="widefat" name="cc_campaign_bundles[<?php echo esc_attr( (string) $index ); ?>][title]" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'Defaults to campaign title', 'consucorner' ); ?>" />
		</p>

		<p>
			<label><strong><?php esc_html_e( 'Banner subtitle', 'consucorner' ); ?></strong></label><br />
			<input type="text" class="widefat" name="cc_campaign_bundles[<?php echo esc_attr( (string) $index ); ?>][subtitle]" value="<?php echo esc_attr( $subtitle ); ?>" />
		</p>

		<p>
			<label><strong><?php esc_html_e( 'Banner CTA label', 'consucorner' ); ?></strong></label><br />
			<input type="text" class="widefat" name="cc_campaign_bundles[<?php echo esc_attr( (string) $index ); ?>][cta_label]" value="<?php echo esc_attr( $cta ); ?>" placeholder="<?php esc_attr_e( 'Shop the bundle', 'consucorner' ); ?>" />
		</p>

		<fieldset class="cc-campaign-pack__featured-image">
			<legend><strong><?php esc_html_e( 'Bundle card image (optional)', 'consucorner' ); ?></strong></legend>
			<p class="description"><?php esc_html_e( 'Featured image for this pack on the /bundles/ page. Leave empty to use the campaign featured image or a product mosaic.', 'consucorner' ); ?></p>
			<?php
			$featured_input_id  = 'cc-pack-featured-' . $index;
			$featured_preview_id = $featured_input_id . '-preview';
			$featured_remove_id  = $featured_input_id . '-remove';
			?>
			<div class="cc-campaign-pack__featured-slot">
				<input type="hidden" id="<?php echo esc_attr( $featured_input_id ); ?>" name="cc_campaign_bundles[<?php echo esc_attr( (string) $index ); ?>][featured_image]" value="<?php echo esc_attr( $featured_image > 0 ? (string) $featured_image : '' ); ?>" />
				<div class="cc-campaign-pack__featured-preview-wrap">
					<img id="<?php echo esc_attr( $featured_preview_id ); ?>" class="cc-campaign-pack__featured-preview" src="<?php echo esc_url( $featured_url ); ?>" alt="" <?php echo $featured_url ? '' : 'style="display:none"'; ?> />
				</div>
				<div class="cc-campaign-pack__featured-actions">
					<button type="button" class="button cc-campaign-pack-upload" data-input="<?php echo esc_attr( $featured_input_id ); ?>" data-preview="<?php echo esc_attr( $featured_preview_id ); ?>" data-remove="<?php echo esc_attr( $featured_remove_id ); ?>"><?php esc_html_e( 'Select image', 'consucorner' ); ?></button>
					<button type="button" class="button-link-delete cc-campaign-pack-remove" id="<?php echo esc_attr( $featured_remove_id ); ?>" data-input="<?php echo esc_attr( $featured_input_id ); ?>" data-preview="<?php echo esc_attr( $featured_preview_id ); ?>" <?php echo $featured_url ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Remove', 'consucorner' ); ?></button>
				</div>
			</div>
		</fieldset>

		<fieldset class="cc-campaign-pack__banner-images">
			<legend><strong><?php esc_html_e( 'Banner collage images (optional)', 'consucorner' ); ?></strong></legend>
			<p class="description"><?php esc_html_e( 'Override the three slider circles. Leave empty to auto-fill from the product pool and offer products.', 'consucorner' ); ?></p>
			<div class="cc-campaign-pack__banner-grid">
				<?php foreach ( $banner_labels as $slot => $label ) : ?>
					<?php
					$img_id      = isset( $banner_images[ $slot ] ) ? absint( $banner_images[ $slot ] ) : 0;
					$preview_url = $img_id ? (string) wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
					$input_id    = 'cc-pack-banner-' . $index . '-' . $slot;
					$preview_id  = $input_id . '-preview';
					$remove_id   = $input_id . '-remove';
					?>
					<div class="cc-campaign-pack__banner-slot">
						<span class="cc-campaign-pack__banner-slot-label"><?php echo esc_html( $label ); ?></span>
						<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" name="cc_campaign_bundles[<?php echo esc_attr( (string) $index ); ?>][banner_images][<?php echo esc_attr( (string) $slot ); ?>]" value="<?php echo esc_attr( $img_id > 0 ? (string) $img_id : '' ); ?>" />
						<div class="cc-campaign-pack__banner-preview-wrap">
							<img id="<?php echo esc_attr( $preview_id ); ?>" class="cc-campaign-pack__banner-preview" src="<?php echo esc_url( $preview_url ); ?>" alt="" <?php echo $preview_url ? '' : 'style="display:none"'; ?> />
						</div>
						<div class="cc-campaign-pack__banner-actions">
							<button type="button" class="button cc-campaign-pack-upload" data-input="<?php echo esc_attr( $input_id ); ?>" data-preview="<?php echo esc_attr( $preview_id ); ?>" data-remove="<?php echo esc_attr( $remove_id ); ?>"><?php esc_html_e( 'Select image', 'consucorner' ); ?></button>
							<button type="button" class="button-link-delete cc-campaign-pack-remove" id="<?php echo esc_attr( $remove_id ); ?>" data-input="<?php echo esc_attr( $input_id ); ?>" data-preview="<?php echo esc_attr( $preview_id ); ?>" <?php echo $preview_url ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Remove', 'consucorner' ); ?></button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<p>
			<label><strong><?php esc_html_e( 'Bundle size (N)', 'consucorner' ); ?></strong></label><br />
			<input type="number" min="1" step="1" name="cc_campaign_bundles[<?php echo esc_attr( (string) $index ); ?>][size]" value="<?php echo esc_attr( $size > 0 ? (string) $size : '' ); ?>" style="width:160px" />
		</p>

		<p>
			<label><strong><?php echo esc_html( sprintf( __( 'Bundle price (P) — %s', 'consucorner' ), $currency ) ); ?></strong></label><br />
			<input type="number" step="0.01" min="0" name="cc_campaign_bundles[<?php echo esc_attr( (string) $index ); ?>][price]" value="<?php echo esc_attr( $price > 0 ? (string) $price : '' ); ?>" style="width:160px" />
		</p>

		<p>
			<label><strong><?php esc_html_e( 'Product pool', 'consucorner' ); ?></strong></label><br />
			<select
				class="wc-product-search cc-campaign-pack__pool"
				multiple="multiple"
				style="width:100%"
				name="cc_campaign_bundles[<?php echo esc_attr( (string) $index ); ?>][pool][]"
				data-placeholder="<?php esc_attr_e( 'Search for products…', 'consucorner' ); ?>"
				data-action="woocommerce_json_search_products_and_variations"
			>
				<?php
				foreach ( $pool as $pid ) :
					$product = wc_get_product( $pid );
					if ( ! $product ) {
						continue;
					}
					?>
					<option value="<?php echo esc_attr( (string) $pid ); ?>" selected="selected"><?php echo esc_html( $product->get_formatted_name() ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
	</div>
	<?php
}

/**
 * Details meta box markup: multi-bundle repeater.
 *
 * @param WP_Post $post Post.
 */
function cc_bundles_render_details_metabox( $post ) {
	wp_nonce_field( 'cc_bundle_save_' . $post->ID, 'cc_bundle_nonce' );

	$packs = cc_campaign_get_bundles( $post->ID );
	if ( empty( $packs ) ) {
		$packs = array(
			cc_campaign_normalize_pack( array( 'key' => cc_campaign_generate_pack_key() ), $post->ID ),
		);
	}
	?>
	<p class="description"><?php esc_html_e( 'Add one or more mix-and-match packs to this campaign. Each pack has its own pool, size, flat price, and optional banner copy.', 'consucorner' ); ?></p>

	<div id="cc-campaign-packs" class="cc-campaign-packs">
		<?php foreach ( $packs as $index => $pack ) : ?>
			<?php cc_bundles_render_pack_row( $index, $pack, $post->ID ); ?>
		<?php endforeach; ?>
	</div>

	<p>
		<button type="button" class="button" id="cc-campaign-pack-add"><?php esc_html_e( 'Add bundle pack', 'consucorner' ); ?></button>
	</p>

	<script type="text/template" id="cc-campaign-pack-template">
		<?php cc_bundles_render_pack_row( '__INDEX__', cc_campaign_normalize_pack( array( 'key' => '__KEY__' ) ), $post->ID ); ?>
	</script>

	<style>
		.cc-campaign-packs { display: flex; flex-direction: column; gap: 16px; }
		.cc-campaign-pack { border: 1px solid #c3c4c7; border-radius: 4px; padding: 12px 16px; background: #fff; }
		.cc-campaign-pack__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
		.cc-campaign-pack__featured-image,
		.cc-campaign-pack__banner-images { margin: 12px 0; border: 0; padding: 0; }
		.cc-campaign-pack__featured-slot { border: 1px solid #dcdcde; border-radius: 4px; padding: 8px; background: #f6f7f7; max-width: 280px; margin-top: 8px; }
		.cc-campaign-pack__featured-preview-wrap { min-height: 120px; display: flex; align-items: center; justify-content: center; margin-bottom: 8px; background: #fff; border-radius: 4px; overflow: hidden; }
		.cc-campaign-pack__featured-preview { max-width: 100%; max-height: 160px; border-radius: 4px; object-fit: cover; }
		.cc-campaign-pack__featured-actions { display: flex; flex-direction: column; gap: 4px; align-items: flex-start; }
		.cc-campaign-pack__banner-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 8px; }
		.cc-campaign-pack__banner-slot { border: 1px solid #dcdcde; border-radius: 4px; padding: 8px; background: #f6f7f7; }
		.cc-campaign-pack__banner-slot-label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; }
		.cc-campaign-pack__banner-preview-wrap { min-height: 72px; display: flex; align-items: center; justify-content: center; margin-bottom: 8px; background: #fff; border-radius: 4px; }
		.cc-campaign-pack__banner-preview { max-width: 100%; max-height: 72px; border-radius: 4px; }
		.cc-campaign-pack__banner-actions { display: flex; flex-direction: column; gap: 4px; align-items: flex-start; }
	</style>

	<script>
	(function ($) {
		function reindexPacks() {
			$('#cc-campaign-packs .cc-campaign-pack').each(function (i) {
				var $row = $(this);
				$row.attr('data-pack-index', i);
				$row.find('.cc-campaign-pack__num').text(i + 1);
				$row.find('[name^="cc_campaign_bundles["]').each(function () {
					var name = $(this).attr('name');
					if (!name) return;
					$(this).attr('name', name.replace(/cc_campaign_bundles\[\d+]/, 'cc_campaign_bundles[' + i + ']'));
				});
			});
		}

		function initProductSearch($context) {
			if (!$.fn.selectWoo) return;
			$context.find('.wc-product-search').each(function () {
				var $el = $(this);
				if ($el.hasClass('select2-hidden-accessible')) return;
				$el.selectWoo({
					allowClear: true,
					placeholder: $el.data('placeholder') || '',
					minimumInputLength: $el.data('minimum_input_length') || 3,
					escapeMarkup: function (m) { return m; },
					ajax: {
						url: wc_enhanced_select_params.ajax_url,
						dataType: 'json',
						delay: 250,
						data: function (params) {
							return {
								term: params.term,
								action: $el.data('action') || 'woocommerce_json_search_products_and_variations',
								security: wc_enhanced_select_params.search_products_nonce
							};
						},
						processResults: function (data) {
							var terms = [];
							if (data) {
								$.each(data, function (id, text) {
									terms.push({ id: id, text: text });
								});
							}
							return { results: terms };
						},
						cache: true
					}
				});
			});
		}

		$('#cc-campaign-pack-add').on('click', function () {
			var tpl = $('#cc-campaign-pack-template').html();
			var index = $('#cc-campaign-packs .cc-campaign-pack').length;
			var key = 'pack-' + Math.random().toString(36).substr(2, 8);
			tpl = tpl.replace(/__INDEX__/g, index).replace(/__KEY__/g, key);
			var $row = $(tpl);
			$('#cc-campaign-packs').append($row);
			reindexPacks();
			initProductSearch($row);
		});

		$('#cc-campaign-packs').on('click', '.cc-campaign-pack__remove', function () {
			var $rows = $('#cc-campaign-packs .cc-campaign-pack');
			if ($rows.length <= 1) {
				window.alert('<?php echo esc_js( __( 'A campaign needs at least one bundle pack.', 'consucorner' ) ); ?>');
				return;
			}
			$(this).closest('.cc-campaign-pack').remove();
			reindexPacks();
		});

		initProductSearch($('#cc-campaign-packs'));
	})(jQuery);
	</script>
	<?php
}

/**
 * Campaign link builder meta box (vendor + tag, like Offers).
 *
 * @param WP_Post $post Post.
 */
function cc_bundles_render_campaign_metabox( $post ) {
	$tags        = cc_bundles_get_tags_list();
	$assigned    = wp_get_post_terms( $post->ID, CC_BUNDLE_TAG_TAXONOMY, array( 'fields' => 'slugs' ) );
	$default_tag = ( ! is_wp_error( $assigned ) && ! empty( $assigned ) ) ? (string) $assigned[0] : '';
	$vendor_id   = cc_bundles_get_vendor_id( $post->ID );
	$vendors     = function_exists( 'cc_offers_get_vendors_list' ) ? cc_offers_get_vendors_list() : array();
	$base        = cc_bundles_get_base_url();
	?>
	<p class="description"><?php esc_html_e( 'Tag and/or assign a vendor, then copy a shareable campaign URL (same pattern as the Offers page).', 'consucorner' ); ?></p>
	<p>
		<label for="cc-bundle-campaign-vendor"><strong><?php esc_html_e( 'Vendor', 'consucorner' ); ?></strong></label>
		<select id="cc-bundle-campaign-vendor" style="width:100%">
			<option value=""><?php esc_html_e( 'Any vendor', 'consucorner' ); ?></option>
			<?php foreach ( $vendors as $vendor ) : ?>
				<option value="<?php echo esc_attr( $vendor['username'] ); ?>" data-vendor-id="<?php echo esc_attr( (string) $vendor['id'] ); ?>" <?php selected( $vendor_id, $vendor['id'] ); ?>><?php echo esc_html( $vendor['label'] ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="cc-bundle-campaign-tag"><strong><?php esc_html_e( 'Bundle tag', 'consucorner' ); ?></strong></label>
		<select id="cc-bundle-campaign-tag" style="width:100%">
			<option value=""><?php esc_html_e( 'All active bundles', 'consucorner' ); ?></option>
			<?php foreach ( $tags as $tag ) : ?>
				<option value="<?php echo esc_attr( $tag->slug ); ?>" <?php selected( $default_tag, $tag->slug ); ?>><?php echo esc_html( $tag->name ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="cc-bundle-campaign-url"><strong><?php esc_html_e( 'Campaign URL', 'consucorner' ); ?></strong></label>
		<input type="text" id="cc-bundle-campaign-url" class="widefat" readonly value="<?php echo esc_attr( $base ); ?>" />
	</p>
	<p>
		<button type="button" class="button" id="cc-bundle-copy-link"><?php esc_html_e( 'Copy link', 'consucorner' ); ?></button>
	</p>
	<?php
}

/**
 * Save bundle meta (pool, size, price, and active state).
 *
 * @param int $post_id Post ID.
 */
function cc_bundles_save_post( $post_id ) {
	if ( ! isset( $_POST['cc_bundle_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cc_bundle_nonce'] ) ), 'cc_bundle_save_' . $post_id ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( CC_BUNDLE_POST_TYPE !== get_post_type( $post_id ) ) {
		return;
	}

	$old_pools = array();
	foreach ( cc_campaign_get_bundles( $post_id ) as $old_pack ) {
		$old_pools = array_merge( $old_pools, (array) $old_pack['pool'] );
	}

	$raw_rows = isset( $_POST['cc_campaign_bundles'] ) ? (array) wp_unslash( $_POST['cc_campaign_bundles'] ) : array();
	$packs    = array();

	foreach ( $raw_rows as $raw ) {
		if ( ! is_array( $raw ) ) {
			continue;
		}

		$pool = array();
		foreach ( (array) ( $raw['pool'] ?? array() ) as $raw_pid ) {
			$pid = absint( $raw_pid );
			if ( $pid < 1 || ! wc_get_product( $pid ) ) {
				continue;
			}
			$pool[] = $pid;
		}

		$banner_images = array();
		foreach ( array_slice( (array) ( $raw['banner_images'] ?? array() ), 0, 3 ) as $img_id ) {
			$banner_images[] = absint( $img_id );
		}

		$pack = cc_campaign_normalize_pack(
			array(
				'key'            => $raw['key'] ?? '',
				'title'          => $raw['title'] ?? '',
				'subtitle'       => $raw['subtitle'] ?? '',
				'cta_label'      => $raw['cta_label'] ?? '',
				'featured_image' => $raw['featured_image'] ?? 0,
				'banner_images'  => $banner_images,
				'pool'           => $pool,
				'size'           => $raw['size'] ?? 0,
				'price'          => $raw['price'] ?? 0,
				'active'         => ! empty( $raw['active'] ),
			),
			$post_id
		);

		if ( empty( $pack['pool'] ) && (int) $pack['size'] < 1 && (float) $pack['price'] <= 0 ) {
			continue;
		}

		$packs[] = $pack;
	}

	if ( empty( $packs ) ) {
		$packs[] = cc_campaign_normalize_pack( array( 'key' => cc_campaign_generate_pack_key() ), $post_id );
	}

	update_post_meta( $post_id, CC_CAMPAIGN_BUNDLES_META, $packs );
	cc_campaign_sync_legacy_meta( $post_id, $packs );

	if ( function_exists( 'cc_offer_campaign_clear_all_caches' ) ) {
		cc_offer_campaign_clear_all_caches( $post_id );
	}

	$new_pools = array();
	foreach ( $packs as $pack ) {
		$new_pools = array_merge( $new_pools, (array) $pack['pool'] );
	}
	cc_bundles_clear_product_promo_caches( array_merge( $old_pools, $new_pools ) );
}
add_action( 'save_post_' . CC_BUNDLE_POST_TYPE, 'cc_bundles_save_post' );

/**
 * Transient key for a product's best bundle promo.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function cc_bundles_product_promo_cache_key( $product_id ) {
	return 'cc_bundle_promo_' . absint( $product_id );
}

/**
 * Clear cached bundle promo for one product.
 *
 * @param int $product_id Product ID.
 * @return void
 */
function cc_bundles_clear_product_promo_cache( $product_id ) {
	delete_transient( cc_bundles_product_promo_cache_key( absint( $product_id ) ) );
}

/**
 * Clear cached bundle promos for many products.
 *
 * @param int[] $product_ids Product IDs.
 * @return void
 */
function cc_bundles_clear_product_promo_caches( $product_ids ) {
	foreach ( array_unique( array_map( 'absint', (array) $product_ids ) ) as $product_id ) {
		if ( $product_id > 0 ) {
			cc_bundles_clear_product_promo_cache( $product_id );
		}
	}
}

/**
 * Product IDs to match against bundle pools (parent + variation).
 *
 * @param WC_Product $product Product.
 * @return int[]
 */
function cc_bundles_get_product_match_ids( $product ) {
	if ( ! ( $product instanceof WC_Product ) ) {
		return array();
	}

	$ids = array( $product->get_id() );
	if ( $product->is_type( 'variation' ) ) {
		$ids[] = $product->get_parent_id();
	}

	return array_values( array_filter( array_unique( array_map( 'absint', $ids ) ) ) );
}

/**
 * Best active bundle promo for a product that is in a pack pool.
 *
 * @param int $product_id Product or variation ID.
 * @return array<string,mixed>|null
 */
function cc_bundles_get_product_promo( $product_id ) {
	$product_id = absint( $product_id );
	if ( $product_id < 1 ) {
		return null;
	}

	$cache_key = cc_bundles_product_promo_cache_key( $product_id );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return is_array( $cached ) && ! empty( $cached ) ? $cached : null;
	}

	$product    = wc_get_product( $product_id );
	$match_ids  = $product instanceof WC_Product ? cc_bundles_get_product_match_ids( $product ) : array( $product_id );
	$best       = null;
	$best_save  = -1;

	$query = new WP_Query(
		array(
			'post_type'              => CC_BUNDLE_POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => 50,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => CC_BUNDLE_ACTIVE_META,
					'value' => '1',
				),
			),
		)
	);

	foreach ( $query->posts as $bundle_id ) {
		$bundle_id = absint( $bundle_id );

		foreach ( cc_campaign_get_active_bundles( $bundle_id ) as $pack ) {
			$pool = array_map( 'absint', (array) $pack['pool'] );
			if ( ! array_intersect( $match_ids, $pool ) ) {
				continue;
			}

			$data = cc_bundles_get_builder_data( $bundle_id, $pack['key'] );
			if ( ! is_array( $data ) || empty( $data['sellable'] ) ) {
				continue;
			}

			$slug = function_exists( 'cc_offer_campaign_get_slug' ) ? cc_offer_campaign_get_slug( $bundle_id ) : '';
			if ( '' === $slug ) {
				continue;
			}

			$savings = function_exists( 'cc_offer_campaign_estimate_savings_percent' )
				? cc_offer_campaign_estimate_savings_percent( $data )
				: 0;

			if ( $savings <= $best_save ) {
				continue;
			}

			$best_save = $savings;
			$url       = function_exists( 'cc_offer_campaign_build_bundles_pack_url' )
				? cc_offer_campaign_build_bundles_pack_url( $slug, (string) $pack['key'] )
				: trailingslashit( cc_bundles_get_base_url() . $slug . '/' . (string) $pack['key'] );

			$best = array(
				'bundle_id'       => $bundle_id,
				'pack_key'        => (string) $pack['key'],
				'title'           => (string) $data['title'],
				'url'             => $url,
				'size'            => (int) $data['size'],
				'price'           => (float) $data['price'],
				'unit_price'      => (float) $data['unit_price'],
				'savings_percent' => (int) $savings,
			);
		}
	}

	set_transient( $cache_key, $best ? $best : array(), HOUR_IN_SECONDS );

	return $best;
}

/**
 * Render the single-product "Build your bundle" promo CTA.
 *
 * @param WC_Product|int $product Product object or ID.
 * @return string HTML or empty string.
 */
function cc_bundles_render_single_product_promo( $product ) {
	if ( ! ( $product instanceof WC_Product ) ) {
		$product = wc_get_product( $product );
	}
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	if ( function_exists( 'cc_is_quote_product' ) && cc_is_quote_product( $product ) ) {
		return '';
	}

	$promo = cc_bundles_get_product_promo( $product->get_id() );
	if ( ! is_array( $promo ) || empty( $promo['url'] ) ) {
		return '';
	}

	$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EGP';
	$fmt      = function_exists( 'cc_format_product_price_amount' ) ? 'cc_format_product_price_amount' : 'wc_format_localized_price';
	$price_label = $fmt( (float) $promo['price'] ) . ' ' . $currency;
	$unit_label  = $fmt( (float) $promo['unit_price'] ) . ' ' . $currency;
	$savings     = isset( $promo['savings_percent'] ) ? (int) $promo['savings_percent'] : 0;

	ob_start();
	?>
	<a
		class="sp-bundle-promo"
		href="<?php echo esc_url( (string) $promo['url'] ); ?>"
		aria-label="<?php
		echo esc_attr(
			sprintf(
				/* translators: 1: pack size, 2: pack price */
				__( 'Build your bundle — pick %1$d items for %2$s', 'consucorner' ),
				(int) $promo['size'],
				$price_label
			)
		);
		?>"
	>
		<span class="sp-bundle-promo__badge"><?php esc_html_e( 'Mix & Match Pack', 'consucorner' ); ?></span>
		<span class="sp-bundle-promo__title"><?php esc_html_e( 'Build your bundle', 'consucorner' ); ?></span>
		<p class="sp-bundle-promo__text">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: bundle size N, 2: flat pack price */
					__( 'Pick any %1$d items from this pack for %2$s total', 'consucorner' ),
					(int) $promo['size'],
					$price_label
				)
			);
			?>
		</p>
		<div class="sp-bundle-promo__meta">
			<?php if ( $savings > 0 ) : ?>
				<span class="sp-bundle-promo__save"><?php echo esc_html( sprintf( __( 'Save up to %d%%', 'consucorner' ), $savings ) ); ?></span>
			<?php endif; ?>
			<span class="sp-bundle-promo__unit"><?php echo esc_html( sprintf( __( 'From %s / item', 'consucorner' ), $unit_label ) ); ?></span>
		</div>
		<span class="sp-bundle-promo__cta"><?php esc_html_e( 'Build your bundle', 'consucorner' ); ?> &rarr;</span>
	</a>
	<?php
	return (string) ob_get_clean();
}

/**
 * Query active bundles, optionally filtered by vendor and/or tag.
 *
 * @param int    $vendor_id Optional vendor user ID.
 * @param string $tag_slug  Optional tag slug.
 * @param int    $limit     Max posts.
 * @return WP_Query
 */
function cc_bundles_query( $vendor_id = 0, $tag_slug = '', $limit = 48 ) {
	$vendor_id = absint( $vendor_id );
	$tag_slug  = cc_bundles_resolve_tag_slug( $tag_slug );

	$meta_query = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		array(
			'key'   => CC_BUNDLE_ACTIVE_META,
			'value' => '1',
		),
	);
	if ( $vendor_id > 0 ) {
		$meta_query[] = array(
			'key'   => CC_BUNDLE_VENDOR_META,
			'value' => $vendor_id,
		);
	}

	$args = array(
		'post_type'           => CC_BUNDLE_POST_TYPE,
		'post_status'         => 'publish',
		'posts_per_page'      => max( 1, absint( $limit ) ),
		'orderby'             => 'title',
		'order'               => 'ASC',
		'ignore_sticky_posts' => true,
		'meta_query'          => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	);

	if ( '' !== $tag_slug ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => CC_BUNDLE_TAG_TAXONOMY,
				'field'    => 'slug',
				'terms'    => $tag_slug,
			),
		);
	}

	return new WP_Query( $args );
}

/**
 * Render a bundle builder card (HTML string): pool steppers + Add bundle CTA.
 *
 * @param int    $bundle_id Bundle/campaign ID.
 * @param string $pack_key  Optional pack key.
 * @return string
 */
function cc_bundles_render_card( $bundle_id, $pack_key = '' ) {
	$data = cc_bundles_get_builder_data( $bundle_id, $pack_key );
	if ( ! $data ) {
		return '';
	}

	$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EGP';
	$fmt      = function_exists( 'cc_format_product_price_amount' )
		? 'cc_format_product_price_amount'
		: static function ( $n ) {
			return wc_format_localized_price( $n );
		};

	$item_count = is_array( $data['items'] ) ? count( $data['items'] ) : 0;
	$mosaic     = array();
	foreach ( (array) $data['items'] as $item ) {
		if ( count( $mosaic ) >= 4 ) {
			break;
		}
		$src = '';
		if ( ! empty( $item['product_id'] ) ) {
			$img_id = get_post_thumbnail_id( (int) $item['product_id'] );
			if ( $img_id ) {
				$src = (string) wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' );
			}
		}
		if ( '' === $src && ! empty( $item['thumb'] ) ) {
			$src = (string) $item['thumb'];
		}
		$mosaic[] = $src;
	}
	while ( count( $mosaic ) < 4 ) {
		$mosaic[] = '';
	}

	$price_label = $fmt( (float) $data['price'] ) . ' ' . $currency;

	ob_start();
	?>
	<article class="cc-bundle-card<?php echo $data['sellable'] ? '' : ' is-unavailable'; ?>" id="<?php echo esc_attr( 'cc-pack-' . (string) $data['pack_key'] ); ?>" data-bundle-id="<?php echo esc_attr( (string) $data['id'] ); ?>" data-pack-key="<?php echo esc_attr( (string) $data['pack_key'] ); ?>" data-bundle-size="<?php echo esc_attr( (string) $data['size'] ); ?>" data-bundle-price="<?php echo esc_attr( (string) $data['price'] ); ?>">
		<div class="cc-bundle-card__media">
			<?php if ( ! empty( $data['image'] ) ) : ?>
				<img class="cc-bundle-card__cover" src="<?php echo esc_url( $data['image'] ); ?>" alt="<?php echo esc_attr( $data['title'] ); ?>" loading="lazy" decoding="async" />
			<?php else : ?>
				<div class="cc-bundle-card__mosaic" aria-hidden="true">
					<?php foreach ( $mosaic as $src ) : ?>
						<span class="cc-bundle-card__mosaic-cell<?php echo '' === $src ? ' is-empty' : ''; ?>">
							<?php if ( '' !== $src ) : ?>
								<img src="<?php echo esc_url( $src ); ?>" alt="" loading="lazy" decoding="async" />
							<?php endif; ?>
						</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<span class="cc-bundle-card__price-pill">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: bundle size, 2: flat price */
						__( '%1$d for %2$s', 'consucorner' ),
						(int) $data['size'],
						$price_label
					)
				);
				?>
			</span>
		</div>

		<div class="cc-bundle-card__body">
			<header class="cc-bundle-card__intro">
				<?php if ( ! empty( $data['vendor_label'] ) ) : ?>
					<p class="cc-bundle-card__vendor"><?php echo esc_html( $data['vendor_label'] ); ?></p>
				<?php endif; ?>
				<h3 class="cc-bundle-card__title"><?php echo esc_html( $data['title'] ); ?></h3>
				<?php if ( ! empty( $data['excerpt'] ) ) : ?>
					<p class="cc-bundle-card__excerpt"><?php echo esc_html( wp_strip_all_tags( $data['excerpt'] ) ); ?></p>
				<?php endif; ?>
				<p class="cc-bundle-card__pool-meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of products in pool */
							_n( '%d product to choose from', '%d products to choose from', $item_count, 'consucorner' ),
							$item_count
						)
					);
					?>
				</p>
			</header>

			<?php if ( ! $data['sellable'] ) : ?>
				<p class="cc-bundle-card__unavailable"><?php esc_html_e( 'Not enough stock available for this pack right now.', 'consucorner' ); ?></p>
			<?php else : ?>
				<ul class="cc-bundle-card__pool">
					<?php
					foreach ( $data['items'] as $item ) :
						$disabled = ! $item['in_stock'];
						$thumb    = '';
						if ( ! empty( $item['product_id'] ) ) {
							$img_id = get_post_thumbnail_id( (int) $item['product_id'] );
							if ( $img_id ) {
								$thumb = (string) wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' );
							}
						}
						if ( '' === $thumb && ! empty( $item['thumb'] ) ) {
							$thumb = (string) $item['thumb'];
						}
						$product_url = ! empty( $item['url'] ) ? (string) $item['url'] : '';
						if ( '' === $product_url && ! empty( $item['product_id'] ) ) {
							$product_url = (string) get_permalink( (int) $item['product_id'] );
						}
						$product_label = ! empty( $item['name'] )
							? sprintf(
								/* translators: %s: product name */
								__( 'View product: %s', 'consucorner' ),
								(string) $item['name']
							)
							: __( 'View product details', 'consucorner' );
						?>
						<li class="cc-bundle-pool-item<?php echo $disabled ? ' is-disabled' : ''; ?>" data-product-id="<?php echo esc_attr( (string) $item['product_id'] ); ?>" data-max="<?php echo esc_attr( (string) $item['max_qty'] ); ?>">
							<div class="cc-bundle-pool-item__thumb">
								<?php if ( $product_url ) : ?>
									<a class="cc-bundle-pool-item__product-link cc-bundle-pool-item__product-link--thumb" href="<?php echo esc_url( $product_url ); ?>" aria-label="<?php echo esc_attr( $product_label ); ?>">
								<?php endif; ?>
								<?php if ( $thumb ) : ?>
									<img src="<?php echo esc_url( $thumb ); ?>" alt="" width="72" height="72" loading="lazy" decoding="async" />
								<?php else : ?>
									<span class="cc-bundle-pool-item__thumb-fallback" aria-hidden="true"></span>
								<?php endif; ?>
								<?php if ( $product_url ) : ?>
									</a>
								<?php endif; ?>
							</div>
							<div class="cc-bundle-pool-item__info">
								<?php if ( $product_url ) : ?>
									<a class="cc-bundle-pool-item__product-link cc-bundle-pool-item__product-link--name" href="<?php echo esc_url( $product_url ); ?>" aria-label="<?php echo esc_attr( $product_label ); ?>">
										<span class="cc-bundle-pool-item__name"><?php echo esc_html( $item['name'] ); ?></span>
									</a>
								<?php else : ?>
									<span class="cc-bundle-pool-item__name"><?php echo esc_html( $item['name'] ); ?></span>
								<?php endif; ?>
								<span class="cc-bundle-pool-item__price"><?php echo esc_html( $fmt( (float) $item['price'] ) . ' ' . $currency ); ?></span>
								<?php if ( $disabled ) : ?>
									<span class="cc-bundle-pool-item__oos"><?php esc_html_e( 'Out of stock', 'consucorner' ); ?></span>
								<?php endif; ?>
							</div>
							<div class="cc-bundle-pool-item__stepper">
								<button type="button" class="cc-bundle-qty-btn cc-bundle-qty-minus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'consucorner' ); ?>" <?php disabled( $disabled ); ?>>&minus;</button>
								<span class="cc-bundle-qty-val">0</span>
								<button type="button" class="cc-bundle-qty-btn cc-bundle-qty-plus" aria-label="<?php esc_attr_e( 'Increase quantity', 'consucorner' ); ?>" <?php disabled( $disabled ); ?>>+</button>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>

				<div class="cc-bundle-card__footer">
					<div class="cc-bundle-card__progress" aria-hidden="true">
						<span class="cc-bundle-card__progress-bar" style="width:0%"></span>
					</div>
					<div class="cc-bundle-card__counter">
						<span class="cc-bundle-selected">0</span> / <?php echo esc_html( (string) $data['size'] ); ?> <?php esc_html_e( 'selected', 'consucorner' ); ?>
					</div>
					<button type="button" class="cc-bundle-card__submit" disabled>
						<?php esc_html_e( 'Add pack to cart', 'consucorner' ); ?>
					</button>
					<p class="cc-bundle-card__msg" role="status" aria-live="polite"></p>
				</div>
			<?php endif; ?>
		</div>
	</article>
	<?php
	return (string) ob_get_clean();
}

/**
 * Render a grid of bundle builder cards from a WP_Query.
 *
 * @param WP_Query $query Query.
 * @return string
 */
function cc_bundles_render_grid( $query ) {
	if ( ! $query instanceof WP_Query || ! $query->have_posts() ) {
		return '';
	}

	$cards = array();
	while ( $query->have_posts() ) {
		$query->the_post();
		$card = cc_bundles_render_card( get_the_ID() );
		if ( '' !== $card ) {
			$cards[] = $card;
		}
	}
	wp_reset_postdata();

	if ( empty( $cards ) ) {
		return '';
	}

	return '<div class="cc-bundles-grid">' . implode( '', $cards ) . '</div>';
}

/**
 * Flag a bundle-driven add-to-cart in progress so other validation hooks
 * (e.g. bulk minimum quantity) can bypass their own restrictions for it.
 *
 * @param bool $flag Whether a bundle add is currently in progress.
 */
function cc_bundles_set_adding_flag( $flag ) {
	global $cc_bundles_adding_flag;
	$cc_bundles_adding_flag = (bool) $flag;
}

/**
 * Whether a bundle add-to-cart is currently in progress.
 *
 * @return bool
 */
function cc_bundles_is_adding() {
	global $cc_bundles_adding_flag;
	return ! empty( $cc_bundles_adding_flag );
}

/**
 * AJAX: add a customer-built bundle selection to the cart.
 *
 * Expects `bundle_id` and `selections` (JSON object of product_id => qty)
 * whose quantities sum to exactly the bundle size.
 */
function cc_bundle_add_to_cart_ajax() {
	check_ajax_referer( 'cc_bundle_add_to_cart', 'nonce' );

	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json_error( array( 'message' => __( 'Cart unavailable. Please refresh and try again.', 'consucorner' ) ) );
	}

	$bundle_id = isset( $_POST['bundle_id'] ) ? absint( $_POST['bundle_id'] ) : 0;
	$pack_key  = isset( $_POST['pack_key'] ) ? sanitize_key( wp_unslash( $_POST['pack_key'] ) ) : '';
	$raw       = isset( $_POST['selections'] ) ? wp_unslash( $_POST['selections'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$decoded   = is_string( $raw ) ? json_decode( $raw, true ) : null;
	$selections = is_array( $decoded ) ? $decoded : array();

	$bundle = get_post( $bundle_id );
	if ( ! $bundle || CC_BUNDLE_POST_TYPE !== $bundle->post_type || 'publish' !== $bundle->post_status ) {
		wp_send_json_error( array( 'message' => __( 'This bundle is no longer available.', 'consucorner' ) ) );
	}

	$pack = cc_campaign_resolve_pack( $bundle_id, $pack_key );
	if ( ! is_array( $pack ) ) {
		wp_send_json_error( array( 'message' => __( 'This bundle is no longer available.', 'consucorner' ) ) );
	}

	$pool  = array_map( 'absint', (array) $pack['pool'] );
	$size  = (int) $pack['size'];
	$price = (float) $pack['price'];
	$pack_key = (string) $pack['key'];

	if ( empty( $pool ) || $size < 1 || $price <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'This bundle is not configured correctly. Please contact support.', 'consucorner' ) ) );
	}

	$clean = array();
	$sum   = 0;

	foreach ( $selections as $raw_pid => $raw_qty ) {
		$pid = absint( $raw_pid );
		$qty = absint( $raw_qty );
		if ( $pid < 1 || $qty < 1 ) {
			continue;
		}
		if ( ! in_array( $pid, $pool, true ) ) {
			wp_send_json_error( array( 'message' => __( 'One of the selected products is not part of this bundle.', 'consucorner' ) ) );
		}

		$product = wc_get_product( $pid );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'One of the selected products is unavailable.', 'consucorner' ) ) );
		}

		if ( $product->managing_stock() ) {
			$stock = $product->get_stock_quantity();
			if ( null === $stock || (int) $stock < $qty ) {
				wp_send_json_error(
					array(
						/* translators: %s: product name */
						'message' => sprintf( __( 'Not enough stock for %s.', 'consucorner' ), $product->get_name() ),
					)
				);
			}
		}

		$clean[ $pid ] = $qty;
		$sum          += $qty;
	}

	if ( $sum !== $size ) {
		wp_send_json_error(
			array(
				/* translators: %d: required bundle size */
				'message' => sprintf( __( 'Please select exactly %d items for this bundle.', 'consucorner' ), $size ),
			)
		);
	}

	$instance = 'ccb_' . uniqid( '', true );
	$title    = '' !== $pack['title'] ? (string) $pack['title'] : get_the_title( $bundle_id );

	cc_bundles_set_adding_flag( true );

	$added_keys = array();
	$failed     = false;

	foreach ( $clean as $pid => $qty ) {
		$key = WC()->cart->add_to_cart(
			$pid,
			$qty,
			0,
			array(),
			array(
				'cc_bundle_id'       => $bundle_id,
				'cc_bundle_pack_key' => $pack_key,
				'cc_bundle_instance' => $instance,
				'cc_bundle_name'     => $title,
				'cc_bundle_size'     => $size,
				'cc_bundle_price'    => $price,
			)
		);

		if ( ! $key ) {
			$failed = true;
			break;
		}
		$added_keys[] = $key;
	}

	cc_bundles_set_adding_flag( false );

	if ( $failed ) {
		foreach ( $added_keys as $key ) {
			WC()->cart->remove_cart_item( $key );
		}
		if ( function_exists( 'wc_clear_notices' ) ) {
			wc_clear_notices();
		}
		wp_send_json_error( array( 'message' => __( 'Could not add the bundle to your cart. Please try again.', 'consucorner' ) ) );
	}

	if ( function_exists( 'wc_clear_notices' ) ) {
		wc_clear_notices();
	}

	WC()->cart->calculate_totals();
	if ( method_exists( WC()->cart, 'set_session' ) ) {
		WC()->cart->set_session();
	}

	wp_send_json_success( function_exists( 'cc_build_mini_cart_items' ) ? cc_build_mini_cart_items() : array() );
}
add_action( 'wp_ajax_cc_bundle_add_to_cart', 'cc_bundle_add_to_cart_ajax' );
add_action( 'wp_ajax_nopriv_cc_bundle_add_to_cart', 'cc_bundle_add_to_cart_ajax' );

/**
 * Price every line in a bundle instance group so the group's subtotal totals
 * exactly the flat bundle price, in cents.
 *
 * A naive `price / size` per-unit price (e.g. 100 / 3 = 33.333...) rounds to
 * 33.33 per unit at checkout, so 3 units total 99.99 instead of 100 — the
 * customer would be short-charged by a cent or more. To guarantee the group
 * always sums to exactly P, every line except the last is priced at the
 * rounded per-unit price, and the last line absorbs whatever remainder is
 * needed so the group total lands on P exactly.
 *
 * @param WC_Cart $cart Cart object.
 */
function cc_bundles_apply_group_pricing( $cart ) {
	if ( ! $cart instanceof WC_Cart ) {
		return;
	}

	static $running = false;
	if ( $running ) {
		return;
	}
	$running = true;

	$groups = array();
	foreach ( $cart->get_cart() as $key => $item ) {
		$instance = isset( $item['cc_bundle_instance'] ) ? (string) $item['cc_bundle_instance'] : '';
		if ( '' === $instance ) {
			continue;
		}
		$groups[ $instance ][] = $key;
	}

	foreach ( $groups as $keys ) {
		if ( empty( $keys ) || ! isset( $cart->cart_contents[ $keys[0] ] ) ) {
			continue;
		}

		$first = $cart->cart_contents[ $keys[0] ];
		$size  = isset( $first['cc_bundle_size'] ) ? max( 1, (int) $first['cc_bundle_size'] ) : 0;
		$price = isset( $first['cc_bundle_price'] ) ? (float) $first['cc_bundle_price'] : 0;

		if ( $size < 1 || $price <= 0 ) {
			continue;
		}

		$valid_keys = array();
		foreach ( $keys as $key ) {
			if ( isset( $cart->cart_contents[ $key ]['data'] ) && is_a( $cart->cart_contents[ $key ]['data'], 'WC_Product' ) ) {
				$valid_keys[] = $key;
			}
		}
		if ( empty( $valid_keys ) ) {
			continue;
		}

		$unit_price   = round( $price / $size, 2 );
		$last_key     = array_pop( $valid_keys );
		$running_paid = 0.0;

		foreach ( $valid_keys as $key ) {
			$qty = max( 1, (int) $cart->cart_contents[ $key ]['quantity'] );
			$cart->cart_contents[ $key ]['data']->set_price( $unit_price );
			$running_paid += $unit_price * $qty;
		}

		$last_qty        = max( 1, (int) $cart->cart_contents[ $last_key ]['quantity'] );
		$last_unit_price = round( ( $price - $running_paid ) / $last_qty, 2 );
		$cart->cart_contents[ $last_key ]['data']->set_price( max( 0, $last_unit_price ) );
	}

	$running = false;
}
add_action( 'woocommerce_before_calculate_totals', 'cc_bundles_apply_group_pricing', 15 );

/**
 * Group cart items by bundle instance for framed display in cart/mini-cart.
 * Single (non-bundle) lines pass through unchanged; bundle lines with the
 * same `cc_bundle_instance` are grouped together, in first-seen order.
 *
 * @param WC_Cart $cart Cart object.
 * @return array<int,array<string,mixed>>
 */
function cc_bundles_group_cart_items( $cart ) {
	$groups = array();
	if ( ! $cart instanceof WC_Cart ) {
		return $groups;
	}

	$index_by_instance = array();
	foreach ( $cart->get_cart() as $key => $cart_item ) {
		$instance = isset( $cart_item['cc_bundle_instance'] ) ? (string) $cart_item['cc_bundle_instance'] : '';

		if ( '' === $instance ) {
			$groups[] = array(
				'type' => 'single',
				'key'  => $key,
				'item' => $cart_item,
			);
			continue;
		}

		if ( ! isset( $index_by_instance[ $instance ] ) ) {
			$index_by_instance[ $instance ] = count( $groups );
			$groups[]                       = array(
				'type'      => 'bundle',
				'instance'  => $instance,
				'bundle_id' => isset( $cart_item['cc_bundle_id'] ) ? absint( $cart_item['cc_bundle_id'] ) : 0,
				'items'     => array(),
			);
		}

		$groups[ $index_by_instance[ $instance ] ]['items'][] = array(
			'key'  => $key,
			'item' => $cart_item,
		);
	}

	return $groups;
}

/**
 * Removing any one line of a bundle instance removes the whole group, so
 * the customer can never end up with a partial, mispriced bundle.
 *
 * @param string  $cart_item_key Removed cart item key.
 * @param WC_Cart $cart          Cart object.
 */
function cc_bundles_cascade_remove_instance( $cart_item_key, $cart ) {
	static $running = false;
	if ( $running || ! $cart instanceof WC_Cart ) {
		return;
	}
	if ( empty( $cart->removed_cart_contents[ $cart_item_key ] ) ) {
		return;
	}

	$removed  = $cart->removed_cart_contents[ $cart_item_key ];
	$instance = isset( $removed['cc_bundle_instance'] ) ? (string) $removed['cc_bundle_instance'] : '';
	if ( '' === $instance ) {
		return;
	}

	$running = true;
	foreach ( $cart->get_cart() as $key => $item ) {
		if ( isset( $item['cc_bundle_instance'] ) && (string) $item['cc_bundle_instance'] === $instance ) {
			$cart->remove_cart_item( $key );
		}
	}
	$running = false;
}
add_action( 'woocommerce_cart_item_removed', 'cc_bundles_cascade_remove_instance', 10, 2 );

/**
 * Persist bundle identity on the order line item. Individual products still
 * render as their own order lines (native WooCommerce behaviour); this only
 * tags each line with which bundle/instance it belonged to.
 *
 * @param WC_Order_Item_Product $item          Order item.
 * @param string                $cart_item_key Cart key.
 * @param array                 $values        Cart values.
 * @param WC_Order              $order         Order.
 */
function cc_bundles_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
	unset( $cart_item_key, $order );

	$instance = isset( $values['cc_bundle_instance'] ) ? (string) $values['cc_bundle_instance'] : '';
	if ( '' === $instance ) {
		return;
	}

	$bundle_id = isset( $values['cc_bundle_id'] ) ? absint( $values['cc_bundle_id'] ) : 0;
	$pack_key  = isset( $values['cc_bundle_pack_key'] ) ? sanitize_key( (string) $values['cc_bundle_pack_key'] ) : '';
	$name      = isset( $values['cc_bundle_name'] ) ? (string) $values['cc_bundle_name'] : '';

	$item->add_meta_data( '_cc_bundle_id', $bundle_id, true );
	$item->add_meta_data( '_cc_bundle_instance', $instance, true );
	if ( '' !== $pack_key ) {
		$item->add_meta_data( '_cc_bundle_pack_key', $pack_key, true );
	}

	if ( '' !== $name ) {
		$item->add_meta_data( __( 'Bundle', 'consucorner' ), $name, true );
	}
}
add_action( 'woocommerce_checkout_create_order_line_item', 'cc_bundles_checkout_create_order_line_item', 10, 4 );

/**
 * Shortcode [cc_bundles tag="slug" vendor="username"].
 *
 * @param array $atts Attributes.
 * @return string
 */
function cc_bundles_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'tag'    => '',
			'vendor' => '',
			'limit'  => 48,
		),
		$atts,
		'cc_bundles'
	);

	$vendor_id = function_exists( 'cc_offers_resolve_vendor_id' ) ? cc_offers_resolve_vendor_id( $atts['vendor'] ) : 0;
	$query     = cc_bundles_query( $vendor_id, $atts['tag'], (int) $atts['limit'] );
	$html      = cc_bundles_render_grid( $query );
	if ( '' === $html ) {
		return '<p class="cc-bundles-empty">' . esc_html__( 'No bundles available right now.', 'consucorner' ) . '</p>';
	}
	return $html;
}
add_shortcode( 'cc_bundles', 'cc_bundles_shortcode' );
