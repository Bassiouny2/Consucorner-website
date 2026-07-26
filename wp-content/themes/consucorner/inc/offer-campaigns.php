<?php
/**
 * Bundle-driven offer campaigns.
 *
 * Each Campaign (cc_bundle) owns one custom slug, a discounted offer-product
 * selection, a separate bundle pool, one automatic collage banner, and URLs:
 *   /offers/{slug}/  → hero + bundle banner + discounted product grid
 *   /bundles/{slug}/ → that campaign's mix-and-match builder only
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

const CC_OFFER_CAMPAIGN_VENDOR_META     = '_cc_offer_campaign_vendor';
const CC_OFFER_CAMPAIGN_TAG_META        = '_cc_offer_campaign_tag';
const CC_OFFER_CAMPAIGN_BG_META         = '_cc_offer_campaign_bg_id';
const CC_OFFER_CAMPAIGN_TITLE_META      = '_cc_offer_campaign_title';
const CC_OFFER_CAMPAIGN_SUBTITLE_META   = '_cc_offer_campaign_subtitle';
const CC_OFFER_CAMPAIGN_CTA_META        = '_cc_offer_campaign_cta_label';
const CC_OFFER_CAMPAIGN_HERO_BADGE_META = '_cc_offer_campaign_hero_badge';
const CC_OFFER_CAMPAIGN_HERO_TITLE_META = '_cc_offer_campaign_hero_title';
const CC_OFFER_CAMPAIGN_HERO_DESC_META  = '_cc_offer_campaign_hero_desc';
const CC_OFFER_CAMPAIGN_ACTIVE_META     = '_cc_offer_campaign_active';
const CC_OFFER_CAMPAIGN_START_META      = '_cc_offer_campaign_start';
const CC_OFFER_CAMPAIGN_END_META        = '_cc_offer_campaign_end';
const CC_OFFER_CAMPAIGN_PRODUCTS_META   = '_cc_offer_campaign_products';
const CC_OFFER_CAMPAIGN_CACHE_TTL       = HOUR_IN_SECONDS;
const CC_OFFER_CAMPAIGN_REWRITE_VERSION = 3;
const CC_OFFER_CAMPAIGN_QUERY_VAR       = 'cc_campaign_slug';
const CC_OFFER_CAMPAIGN_PACK_QUERY_VAR  = 'cc_campaign_pack';

/**
 * Get a campaign meta string.
 *
 * @param int    $campaign_id Campaign post ID.
 * @param string $key         Meta key.
 * @return string
 */
function cc_offer_campaign_get_meta_string( $campaign_id, $key ) {
	return trim( (string) get_post_meta( absint( $campaign_id ), $key, true ) );
}

/**
 * Legacy offer-side vendor (kept for old-link redirects).
 *
 * @param int $campaign_id Campaign post ID.
 * @return int
 */
function cc_offer_campaign_get_vendor_id( $campaign_id ) {
	return absint( get_post_meta( absint( $campaign_id ), CC_OFFER_CAMPAIGN_VENDOR_META, true ) );
}

/**
 * Legacy offer product tag (kept for old-link redirects).
 *
 * @param int $campaign_id Campaign post ID.
 * @return string
 */
function cc_offer_campaign_get_tag_slug( $campaign_id ) {
	return sanitize_title( cc_offer_campaign_get_meta_string( $campaign_id, CC_OFFER_CAMPAIGN_TAG_META ) );
}

/**
 * Get the first assigned bundle tag (legacy helper).
 *
 * @param int $campaign_id Campaign post ID.
 * @return string
 */
function cc_offer_campaign_get_bundle_tag_slug( $campaign_id ) {
	if ( ! taxonomy_exists( CC_BUNDLE_TAG_TAXONOMY ) ) {
		return '';
	}

	$slugs = wp_get_post_terms( absint( $campaign_id ), CC_BUNDLE_TAG_TAXONOMY, array( 'fields' => 'slugs' ) );
	return ( ! is_wp_error( $slugs ) && ! empty( $slugs[0] ) ) ? sanitize_title( $slugs[0] ) : '';
}

/**
 * Campaign public slug (post_name).
 *
 * @param int $campaign_id Campaign post ID.
 * @return string
 */
function cc_offer_campaign_get_slug( $campaign_id ) {
	$post = get_post( absint( $campaign_id ) );
	return ( $post instanceof WP_Post && CC_BUNDLE_POST_TYPE === $post->post_type )
		? sanitize_title( $post->post_name )
		: '';
}

/**
 * Whether a campaign is enabled for the offers page.
 *
 * @param int $campaign_id Campaign post ID.
 * @return bool
 */
function cc_offer_campaign_is_active( $campaign_id ) {
	return '1' === cc_offer_campaign_get_meta_string( $campaign_id, CC_OFFER_CAMPAIGN_ACTIVE_META );
}

/**
 * Parse a stored campaign date in the WordPress timezone.
 *
 * @param string $value Stored datetime-local value.
 * @return DateTimeImmutable|null
 */
function cc_offer_campaign_parse_datetime( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return null;
	}

	$date = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );
	return $date instanceof DateTimeImmutable ? $date : null;
}

/**
 * Sanitize a datetime-local field.
 *
 * @param mixed $value Raw field value.
 * @return string
 */
function cc_offer_campaign_sanitize_datetime( $value ) {
	$value = sanitize_text_field( wp_unslash( (string) $value ) );
	$date  = cc_offer_campaign_parse_datetime( $value );
	return $date ? $date->format( 'Y-m-d\TH:i' ) : '';
}

/**
 * Whether the campaign's start/end window includes the current time.
 *
 * @param int $campaign_id Campaign post ID.
 * @return bool
 */
function cc_offer_campaign_is_in_schedule( $campaign_id ) {
	$now   = current_datetime();
	$start = cc_offer_campaign_parse_datetime( cc_offer_campaign_get_meta_string( $campaign_id, CC_OFFER_CAMPAIGN_START_META ) );
	$end   = cc_offer_campaign_parse_datetime( cc_offer_campaign_get_meta_string( $campaign_id, CC_OFFER_CAMPAIGN_END_META ) );

	if ( $start && $now < $start ) {
		return false;
	}
	if ( $end && $now > $end ) {
		return false;
	}
	return true;
}

/**
 * Whether a campaign has a usable bundle (active + pool + size + price).
 *
 * @param int $campaign_id Campaign post ID.
 * @return bool
 */
function cc_offer_campaign_has_bundle( $campaign_id ) {
	return function_exists( 'cc_campaign_get_active_bundles' )
		&& ! empty( cc_campaign_get_active_bundles( $campaign_id ) );
}

/**
 * Whether a published campaign is publicly viewable right now.
 *
 * @param int $campaign_id Campaign post ID.
 * @return bool
 */
function cc_offer_campaign_is_publicly_viewable( $campaign_id ) {
	$campaign_id = absint( $campaign_id );
	$post        = get_post( $campaign_id );
	if ( ! $post || CC_BUNDLE_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
		return false;
	}

	return cc_offer_campaign_is_active( $campaign_id )
		&& cc_offer_campaign_is_in_schedule( $campaign_id )
		&& cc_offer_campaign_has_bundle( $campaign_id );
}

/**
 * Transient key for a campaign slug.
 *
 * @param string $slug Campaign slug.
 * @return string
 */
function cc_offer_campaign_slug_cache_key( $slug ) {
	return 'cc_offer_campaign_slug_' . md5( sanitize_title( $slug ) );
}

/**
 * Transient key for a legacy vendor/tag pair.
 *
 * @param int    $vendor_id Vendor user ID.
 * @param string $tag_slug  Product tag slug.
 * @return string
 */
function cc_offer_campaign_cache_key( $vendor_id, $tag_slug ) {
	return 'cc_offer_campaign_' . md5( absint( $vendor_id ) . '|' . sanitize_title( $tag_slug ) );
}

/**
 * Clear the legacy vendor/tag resolver cache.
 *
 * @param int    $vendor_id Vendor user ID.
 * @param string $tag_slug  Product tag slug.
 * @return void
 */
function cc_offer_campaign_clear_cache( $vendor_id, $tag_slug ) {
	if ( absint( $vendor_id ) > 0 && '' !== sanitize_title( $tag_slug ) ) {
		delete_transient( cc_offer_campaign_cache_key( $vendor_id, $tag_slug ) );
	}
}

/**
 * Clear the slug resolver cache.
 *
 * @param string $slug Campaign slug.
 * @return void
 */
function cc_offer_campaign_clear_slug_cache( $slug ) {
	$slug = sanitize_title( $slug );
	if ( '' !== $slug ) {
		delete_transient( cc_offer_campaign_slug_cache_key( $slug ) );
		delete_transient( 'cc_offer_bundle_slug_' . md5( $slug ) );
	}
}

/**
 * Clear all known caches for a campaign post.
 *
 * @param int $post_id Campaign post ID.
 * @return void
 */
function cc_offer_campaign_clear_all_caches( $post_id ) {
	$post_id = absint( $post_id );
	cc_offer_campaign_clear_slug_cache( cc_offer_campaign_get_slug( $post_id ) );
	cc_offer_campaign_clear_cache(
		cc_offer_campaign_get_vendor_id( $post_id ),
		cc_offer_campaign_get_tag_slug( $post_id )
	);
	delete_transient( cc_offer_campaign_all_products_cache_key() );
}

/**
 * Transient key for the aggregated public offer product list.
 *
 * @return string
 */
function cc_offer_campaign_all_products_cache_key() {
	return 'cc_offer_campaign_all_products';
}

/**
 * Published campaign IDs that are publicly viewable right now.
 *
 * @return int[]
 */
function cc_offer_campaign_get_public_campaign_ids() {
	$cache_key = 'cc_offer_campaign_public_ids';
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return array_values( array_filter( array_map( 'absint', $cached ) ) );
	}

	$ids   = array();
	$posts = get_posts(
		array(
			'post_type'              => CC_BUNDLE_POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	foreach ( $posts as $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id > 0 && cc_offer_campaign_is_publicly_viewable( $post_id ) ) {
			$ids[] = $post_id;
		}
	}

	set_transient( $cache_key, $ids, CC_OFFER_CAMPAIGN_CACHE_TTL );

	return $ids;
}

/**
 * All offer product IDs from every public campaign (deduped, campaign order preserved).
 *
 * @return int[]
 */
function cc_offer_campaign_get_all_offer_product_ids() {
	$cached = get_transient( cc_offer_campaign_all_products_cache_key() );
	if ( is_array( $cached ) ) {
		return array_values( array_filter( array_map( 'absint', $cached ) ) );
	}

	$product_ids = array();
	foreach ( cc_offer_campaign_get_public_campaign_ids() as $campaign_id ) {
		foreach ( cc_offer_campaign_get_product_ids( $campaign_id ) as $product_id ) {
			$product_id = absint( $product_id );
			if ( $product_id > 0 ) {
				$product_ids[ $product_id ] = $product_id;
			}
		}
	}

	$product_ids = array_values( $product_ids );
	set_transient( cc_offer_campaign_all_products_cache_key(), $product_ids, CC_OFFER_CAMPAIGN_CACHE_TTL );

	return $product_ids;
}

/**
 * Public campaign banner payloads for the aggregated /offers/ page slider.
 *
 * @return array<int, array<string,mixed>>
 */
function cc_offer_campaign_get_public_banner_campaigns() {
	$campaigns = array();

	foreach ( cc_offer_campaign_get_public_campaign_ids() as $campaign_id ) {
		$data = cc_offer_campaign_get_data( $campaign_id );
		if ( ! is_array( $data ) || empty( $data['cta_url'] ) || empty( $data['offer_ids'] ) ) {
			continue;
		}
		$campaigns[] = $data;
	}

	return $campaigns;
}

/**
 * Build the public offers URL for a campaign slug.
 *
 * @param string $slug Campaign slug.
 * @return string
 */
function cc_offer_campaign_build_offers_url( $slug ) {
	$slug = sanitize_title( $slug );
	$base = function_exists( 'cc_offers_get_base_url' ) ? cc_offers_get_base_url() : trailingslashit( home_url( '/offers/' ) );
	return '' === $slug ? $base : trailingslashit( $base . $slug );
}

/**
 * Build the exact bundles URL for a campaign slug.
 *
 * @param string $slug Campaign slug.
 * @return string
 */
function cc_offer_campaign_build_bundles_url( $slug ) {
	$slug = sanitize_title( $slug );
	$base = function_exists( 'cc_bundles_get_base_url' ) ? cc_bundles_get_base_url() : trailingslashit( home_url( '/bundles/' ) );
	return '' === $slug ? $base : trailingslashit( $base . $slug );
}

/**
 * Build the exact bundles URL for one pack inside a campaign.
 *
 * @param string $slug     Campaign slug.
 * @param string $pack_key Pack key.
 * @return string
 */
function cc_offer_campaign_build_bundles_pack_url( $slug, $pack_key ) {
	$slug     = sanitize_title( $slug );
	$pack_key = sanitize_key( (string) $pack_key );
	if ( '' === $slug || '' === $pack_key ) {
		return cc_offer_campaign_build_bundles_url( $slug );
	}
	return trailingslashit( cc_offer_campaign_build_bundles_url( $slug ) . $pack_key );
}

/**
 * Register rewrite rules and query var for /offers/{slug}/ and /bundles/{slug}/.
 *
 * @return void
 */
function cc_offer_campaign_register_rewrites() {
	add_rewrite_tag( '%' . CC_OFFER_CAMPAIGN_QUERY_VAR . '%', '([^&]+)' );
	add_rewrite_tag( '%' . CC_OFFER_CAMPAIGN_PACK_QUERY_VAR . '%', '([^&]+)' );
	add_rewrite_rule(
		'^offers/([^/]+)/?$',
		'index.php?pagename=offers&' . CC_OFFER_CAMPAIGN_QUERY_VAR . '=$matches[1]',
		'top'
	);
	add_rewrite_rule(
		'^bundles/([^/]+)/([^/]+)/?$',
		'index.php?pagename=bundles&' . CC_OFFER_CAMPAIGN_QUERY_VAR . '=$matches[1]&' . CC_OFFER_CAMPAIGN_PACK_QUERY_VAR . '=$matches[2]',
		'top'
	);
	add_rewrite_rule(
		'^bundles/([^/]+)/?$',
		'index.php?pagename=bundles&' . CC_OFFER_CAMPAIGN_QUERY_VAR . '=$matches[1]',
		'top'
	);
}
add_action( 'init', 'cc_offer_campaign_register_rewrites', 30 );

/**
 * Expose the campaign slug query var.
 *
 * @param string[] $vars Query vars.
 * @return string[]
 */
function cc_offer_campaign_query_vars( $vars ) {
	$vars[] = CC_OFFER_CAMPAIGN_QUERY_VAR;
	$vars[] = CC_OFFER_CAMPAIGN_PACK_QUERY_VAR;
	return $vars;
}
add_filter( 'query_vars', 'cc_offer_campaign_query_vars' );

/**
 * Flush rewrite rules once when the campaign routing version changes.
 *
 * @return void
 */
function cc_offer_campaign_maybe_flush_rewrites() {
	$current = (int) get_option( 'cc_offer_campaign_rewrite_version', 0 );
	if ( $current >= CC_OFFER_CAMPAIGN_REWRITE_VERSION ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( 'cc_offer_campaign_rewrite_version', CC_OFFER_CAMPAIGN_REWRITE_VERSION, false );
}
add_action( 'init', 'cc_offer_campaign_maybe_flush_rewrites', 99 );

/**
 * Read the routed campaign slug from the current request.
 *
 * @return string
 */
function cc_offer_campaign_get_request_slug() {
	$slug = get_query_var( CC_OFFER_CAMPAIGN_QUERY_VAR, '' );
	if ( '' === $slug && isset( $_GET[ CC_OFFER_CAMPAIGN_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$slug = wp_unslash( $_GET[ CC_OFFER_CAMPAIGN_QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	return sanitize_title( (string) $slug );
}

/**
 * Read the routed bundle pack key from the current request.
 *
 * @return string
 */
function cc_offer_campaign_get_request_pack_key() {
	$pack_key = get_query_var( CC_OFFER_CAMPAIGN_PACK_QUERY_VAR, '' );
	if ( '' === $pack_key && isset( $_GET[ CC_OFFER_CAMPAIGN_PACK_QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pack_key = wp_unslash( $_GET[ CC_OFFER_CAMPAIGN_PACK_QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	return sanitize_key( (string) $pack_key );
}

/**
 * Look up a published campaign post ID by slug (no visibility filters).
 *
 * @param string $slug Campaign slug.
 * @return int
 */
function cc_offer_campaign_find_id_by_slug( $slug ) {
	$slug = sanitize_title( $slug );
	if ( '' === $slug ) {
		return 0;
	}

	$posts = get_posts(
		array(
			'name'                   => $slug,
			'post_type'              => CC_BUNDLE_POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	return ! empty( $posts[0] ) ? absint( $posts[0] ) : 0;
}

/**
 * Resolve a published campaign by its public slug for the offers page.
 *
 * Requires campaign active + in schedule + complete bundle.
 *
 * @param string $slug Campaign slug.
 * @return int Campaign post ID, or zero.
 */
function cc_offer_campaign_resolve_by_slug( $slug ) {
	$slug = sanitize_title( $slug );
	if ( '' === $slug ) {
		return 0;
	}

	$cache_key = cc_offer_campaign_slug_cache_key( $slug );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) && array_key_exists( 'campaign_id', $cached ) ) {
		$cached_id = absint( $cached['campaign_id'] );
		if ( $cached_id > 0 && ! cc_offer_campaign_is_publicly_viewable( $cached_id ) ) {
			delete_transient( $cache_key );
		} else {
			return $cached_id;
		}
	}

	$campaign_id = cc_offer_campaign_find_id_by_slug( $slug );
	if ( $campaign_id && ! cc_offer_campaign_is_publicly_viewable( $campaign_id ) ) {
		$campaign_id = 0;
	}

	set_transient(
		$cache_key,
		array( 'campaign_id' => $campaign_id ),
		CC_OFFER_CAMPAIGN_CACHE_TTL
	);

	return $campaign_id;
}

/**
 * Resolve a published bundle by slug for the exact /bundles/{slug}/ page.
 *
 * Requires an active, complete bundle (does not require the offers-page active flag).
 *
 * @param string $slug Campaign slug.
 * @return int Campaign post ID, or zero.
 */
function cc_offer_campaign_resolve_bundle_by_slug( $slug ) {
	$slug = sanitize_title( $slug );
	if ( '' === $slug ) {
		return 0;
	}

	$cache_key = 'cc_offer_bundle_slug_' . md5( $slug );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) && array_key_exists( 'campaign_id', $cached ) ) {
		$cached_id = absint( $cached['campaign_id'] );
		if ( $cached_id > 0 && ( ! cc_offer_campaign_has_bundle( $cached_id ) || 'publish' !== get_post_status( $cached_id ) ) ) {
			delete_transient( $cache_key );
		} else {
			return $cached_id;
		}
	}

	$campaign_id = cc_offer_campaign_find_id_by_slug( $slug );
	if ( $campaign_id && ! cc_offer_campaign_has_bundle( $campaign_id ) ) {
		$campaign_id = 0;
	}

	set_transient(
		$cache_key,
		array( 'campaign_id' => $campaign_id ),
		CC_OFFER_CAMPAIGN_CACHE_TTL
	);

	return $campaign_id;
}

/**
 * Resolve the newest valid campaign for a legacy offer vendor/tag pair.
 *
 * @param int    $vendor_id Vendor user ID.
 * @param string $tag_slug  Product tag slug.
 * @return int Campaign post ID, or zero when none applies.
 */
function cc_offer_campaign_resolve( $vendor_id, $tag_slug ) {
	$vendor_id = absint( $vendor_id );
	$tag_slug  = function_exists( 'cc_offers_resolve_tag_slug' )
		? cc_offers_resolve_tag_slug( $tag_slug )
		: sanitize_title( $tag_slug );

	if ( $vendor_id <= 0 || '' === $tag_slug ) {
		return 0;
	}

	$cache_key = cc_offer_campaign_cache_key( $vendor_id, $tag_slug );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) && isset( $cached['campaign_id'] ) ) {
		$cached_id = absint( $cached['campaign_id'] );
		if ( $cached_id > 0 && ! cc_offer_campaign_is_publicly_viewable( $cached_id ) ) {
			delete_transient( $cache_key );
		} else {
			return $cached_id;
		}
	}

	$query = new WP_Query(
		array(
			'post_type'           => CC_BUNDLE_POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => 50,
			'fields'              => 'ids',
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => CC_OFFER_CAMPAIGN_VENDOR_META,
					'value'   => $vendor_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
				array(
					'key'   => CC_OFFER_CAMPAIGN_TAG_META,
					'value' => $tag_slug,
				),
				array(
					'key'   => CC_OFFER_CAMPAIGN_ACTIVE_META,
					'value' => '1',
				),
			),
		)
	);

	$campaign_id = 0;
	foreach ( $query->posts as $candidate_id ) {
		$candidate_id = absint( $candidate_id );
		if ( cc_offer_campaign_is_publicly_viewable( $candidate_id ) ) {
			$campaign_id = $candidate_id;
			break;
		}
	}

	set_transient(
		$cache_key,
		array( 'campaign_id' => $campaign_id ),
		CC_OFFER_CAMPAIGN_CACHE_TTL
	);

	return $campaign_id;
}

/**
 * Published product IDs from the campaign pool, preserving admin order.
 *
 * @param int $campaign_id Campaign post ID.
 * @return int[]
 */
function cc_offer_campaign_get_pool_product_ids( $campaign_id, $pack_key = '' ) {
	if ( ! function_exists( 'cc_bundles_get_pool' ) ) {
		return array();
	}

	$ids = array();
	foreach ( cc_bundles_get_pool( $campaign_id, $pack_key ) as $product_id ) {
		$product_id = absint( $product_id );
		$product    = $product_id ? wc_get_product( $product_id ) : false;
		if ( ! $product || 'publish' !== get_post_status( $product_id ) ) {
			continue;
		}
		$ids[] = $product_id;
	}

	return $ids;
}

/**
 * Build up to three collage images for a campaign banner.
 *
 * Order: pool products, remaining pool, offer products, then cycle existing.
 *
 * @param int[] $pool_ids  Pool product IDs.
 * @param int[] $offer_ids Offer product IDs.
 * @return array<int,array{src:string,name:string}>
 */
function cc_offer_campaign_build_banner_images( $pool_ids, $offer_ids = array() ) {
	$images      = array();
	$used_ids    = array();
	$pool_ids    = array_values( array_filter( array_map( 'absint', (array) $pool_ids ) ) );
	$offer_ids   = array_values( array_filter( array_map( 'absint', (array) $offer_ids ) ) );
	$candidates  = array_merge( $pool_ids, $offer_ids );

	$resolve_image = static function ( $product_id ) {
		$product_id = absint( $product_id );
		$product    = $product_id ? wc_get_product( $product_id ) : false;
		if ( ! $product ) {
			return null;
		}
		$src = '';
		$img_id = get_post_thumbnail_id( $product_id );
		if ( $img_id ) {
			$src = (string) wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' );
		}
		if ( '' === $src ) {
			$src = (string) wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
		}
		return array(
			'src'  => $src,
			'name' => $product->get_name(),
		);
	};

	foreach ( $pool_ids as $product_id ) {
		if ( count( $images ) >= 3 ) {
			break;
		}
		if ( in_array( $product_id, $used_ids, true ) ) {
			continue;
		}
		$image = $resolve_image( $product_id );
		if ( ! is_array( $image ) ) {
			continue;
		}
		$images[]   = $image;
		$used_ids[] = $product_id;
	}

	foreach ( $offer_ids as $product_id ) {
		if ( count( $images ) >= 3 ) {
			break;
		}
		if ( in_array( $product_id, $used_ids, true ) ) {
			continue;
		}
		$image = $resolve_image( $product_id );
		if ( ! is_array( $image ) || '' === $image['src'] ) {
			continue;
		}
		$images[]   = $image;
		$used_ids[] = $product_id;
	}

	while ( count( $images ) < 3 && ! empty( $candidates ) ) {
		$filled = false;
		foreach ( $candidates as $product_id ) {
			$image = $resolve_image( $product_id );
			if ( ! is_array( $image ) || '' === $image['src'] ) {
				continue;
			}
			$images[] = $image;
			$filled   = true;
			if ( count( $images ) >= 3 ) {
				break;
			}
		}
		if ( ! $filled ) {
			break;
		}
	}

	while ( count( $images ) < 3 ) {
		$images[] = array(
			'src'  => '',
			'name' => '',
		);
	}

	return array_slice( $images, 0, 3 );
}

/**
 * Resolve banner collage images for a pack (custom overrides + auto fallback).
 *
 * @param array<string,mixed> $pack      Normalized pack row.
 * @param int[]               $pool_ids  Pool product IDs.
 * @param int[]               $offer_ids Offer product IDs.
 * @return array<int,array{src:string,name:string}>
 */
function cc_offer_campaign_resolve_pack_banner_images( $pack, $pool_ids, $offer_ids = array() ) {
	$auto = cc_offer_campaign_build_banner_images( $pool_ids, $offer_ids );

	if ( ! is_array( $pack ) || empty( $pack['banner_images'] ) || ! is_array( $pack['banner_images'] ) ) {
		return $auto;
	}

	$has_custom = false;
	foreach ( $pack['banner_images'] as $img_id ) {
		if ( absint( $img_id ) > 0 ) {
			$has_custom = true;
			break;
		}
	}

	if ( ! $has_custom ) {
		return $auto;
	}

	$images = array();
	for ( $slot = 0; $slot < 3; $slot++ ) {
		$img_id = isset( $pack['banner_images'][ $slot ] ) ? absint( $pack['banner_images'][ $slot ] ) : 0;
		if ( $img_id > 0 && wp_attachment_is_image( $img_id ) ) {
			$images[] = array(
				'src'  => (string) wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ),
				'name' => (string) get_the_title( $img_id ),
			);
			continue;
		}

		$images[] = isset( $auto[ $slot ] ) && is_array( $auto[ $slot ] )
			? $auto[ $slot ]
			: array(
				'src'  => '',
				'name' => '',
			);
	}

	return $images;
}

/**
 * Banner payload for one bundle pack slide.
 *
 * @param int                 $campaign_id Campaign post ID.
 * @param array<string,mixed> $pack        Normalized pack row.
 * @return array<string,mixed>|null
 */
function cc_offer_campaign_get_bundle_banner_data( $campaign_id, $pack ) {
	$campaign_id = absint( $campaign_id );
	if ( $campaign_id <= 0 || ! is_array( $pack ) || empty( $pack['key'] ) ) {
		return null;
	}

	$slug       = cc_offer_campaign_get_slug( $campaign_id );
	$offer_ids  = cc_offer_campaign_get_product_ids( $campaign_id );
	$pool_ids   = array_map( 'absint', (array) ( $pack['pool'] ?? array() ) );
	$currency   = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EGP';
	$price      = (float) ( $pack['price'] ?? 0 );
	$size       = (int) ( $pack['size'] ?? 0 );
	$price_fmt  = '';

	if ( $price > 0 ) {
		$price_fmt = function_exists( 'cc_format_product_price_amount' )
			? cc_format_product_price_amount( $price )
			: ( function_exists( 'wc_format_localized_price' ) ? wc_format_localized_price( $price ) : (string) $price );
		$price_fmt = preg_replace( '/[.,]0+$/', '', trim( (string) $price_fmt ) );
	}

	$builder_stub = array(
		'items' => array_map(
			static function ( $pid ) {
				$product = wc_get_product( $pid );
				return array( 'product_id' => $pid, 'name' => $product ? $product->get_name() : '' );
			},
			$pool_ids
		),
		'size'  => $size,
		'price' => $price,
	);

	$title = '' !== (string) ( $pack['title'] ?? '' )
		? (string) $pack['title']
		: cc_offer_campaign_get_meta_string( $campaign_id, CC_OFFER_CAMPAIGN_TITLE_META );
	if ( '' === $title ) {
		$title = get_the_title( $campaign_id );
	}

	$subtitle = '' !== (string) ( $pack['subtitle'] ?? '' )
		? (string) $pack['subtitle']
		: cc_offer_campaign_get_meta_string( $campaign_id, CC_OFFER_CAMPAIGN_SUBTITLE_META );

	$cta_label = '' !== (string) ( $pack['cta_label'] ?? '' )
		? (string) $pack['cta_label']
		: cc_offer_campaign_get_meta_string( $campaign_id, CC_OFFER_CAMPAIGN_CTA_META );
	if ( '' === $cta_label ) {
		$cta_label = __( 'Shop the bundle', 'consucorner' );
	}

	return array(
		'id'              => $campaign_id,
		'pack_key'        => (string) $pack['key'],
		'slug'            => $slug,
		'title'           => $title,
		'subtitle'        => $subtitle,
		'cta_label'       => $cta_label,
		'cta_url'         => cc_offer_campaign_build_bundles_pack_url( $slug, (string) $pack['key'] ),
		'offers_url'      => cc_offer_campaign_build_offers_url( $slug ),
		'bundle_size'     => $size,
		'bundle_price'    => $price,
		'currency'        => $currency,
		'price_amount'    => $price_fmt,
		'price_label'     => $price_fmt ? ( $price_fmt . ' ' . $currency ) : '',
		'savings_percent' => cc_offer_campaign_estimate_savings_percent( $builder_stub ),
		'vendor_label'    => function_exists( 'cc_bundles_get_vendor_id' ) && function_exists( 'cc_offers_get_vendor_label' )
			? cc_offers_get_vendor_label( cc_bundles_get_vendor_id( $campaign_id ) )
			: '',
		'pool_images'     => cc_offer_campaign_resolve_pack_banner_images( $pack, $pool_ids, $offer_ids ),
		'pool_ids'        => $pool_ids,
		'offer_ids'       => $offer_ids,
	);

}

/**
 * Banner slide payloads for one campaign (one per active pack).
 *
 * @param int $campaign_id Campaign post ID.
 * @return array<int,array<string,mixed>>
 */
function cc_offer_campaign_get_campaign_banner_slides( $campaign_id ) {
	$campaign_id = absint( $campaign_id );
	if ( $campaign_id <= 0 || ! cc_offer_campaign_is_publicly_viewable( $campaign_id ) ) {
		return array();
	}

	$slides = array();
	foreach ( cc_campaign_get_active_bundles( $campaign_id ) as $pack ) {
		$slide = cc_offer_campaign_get_bundle_banner_data( $campaign_id, $pack );
		if ( is_array( $slide ) && ! empty( $slide['cta_url'] ) ) {
			$slides[] = $slide;
		}
	}

	return $slides;
}

/**
 * Published simple products selected for the discounted offers grid.
 *
 * @param int $campaign_id Campaign post ID.
 * @return int[]
 */
function cc_offer_campaign_get_product_ids( $campaign_id ) {
	$stored = get_post_meta( absint( $campaign_id ), CC_OFFER_CAMPAIGN_PRODUCTS_META, true );
	$ids    = array();

	foreach ( is_array( $stored ) ? $stored : array() as $product_id ) {
		$product_id = absint( $product_id );
		$product    = $product_id ? wc_get_product( $product_id ) : false;
		if ( ! $product || 'publish' !== get_post_status( $product_id ) ) {
			continue;
		}
		$ids[] = $product_id;
	}

	return array_values( array_unique( $ids ) );
}

/**
 * Estimated savings percent for a bundle versus the sum of regular prices.
 *
 * @param array<string,mixed> $bundle Builder data from cc_bundles_get_builder_data().
 * @return int Percent saved, or 0 when not calculable.
 */
function cc_offer_campaign_estimate_savings_percent( $bundle ) {
	if ( ! is_array( $bundle ) || empty( $bundle['items'] ) || empty( $bundle['size'] ) || empty( $bundle['price'] ) ) {
		return 0;
	}

	$prices = array();
	foreach ( (array) $bundle['items'] as $item ) {
		$product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : false;
		if ( ! $product ) {
			continue;
		}
		$regular = (float) $product->get_regular_price();
		if ( $regular <= 0 ) {
			$regular = (float) $product->get_price();
		}
		if ( $regular > 0 ) {
			$prices[] = $regular;
		}
	}

	if ( empty( $prices ) ) {
		return 0;
	}

	sort( $prices );
	$size          = (int) $bundle['size'];
	$regular_total = 0.0;
	$count         = count( $prices );
	for ( $i = 0; $i < $size; $i++ ) {
		$regular_total += $prices[ min( $i, $count - 1 ) ];
	}

	$deal = (float) $bundle['price'];
	if ( $regular_total <= $deal || $regular_total <= 0 ) {
		return 0;
	}

	return (int) max( 1, min( 99, round( ( ( $regular_total - $deal ) / $regular_total ) * 100 ) ) );
}

/**
 * Prepared campaign data for the offers banner, hero, and product grid.
 *
 * @param int $campaign_id Campaign post ID.
 * @return array<string,mixed>|null
 */
function cc_offer_campaign_get_data( $campaign_id ) {
	$campaign_id = absint( $campaign_id );
	if ( $campaign_id <= 0 || ! cc_offer_campaign_is_publicly_viewable( $campaign_id ) ) {
		return null;
	}

	$slides = cc_offer_campaign_get_campaign_banner_slides( $campaign_id );
	if ( empty( $slides ) ) {
		return null;
	}

	$slide = $slides[0];

	return array_merge(
		$slide,
		array(
			'hero_badge' => cc_offer_campaign_get_meta_string( $campaign_id, CC_OFFER_CAMPAIGN_HERO_BADGE_META ),
			'hero_title' => cc_offer_campaign_get_meta_string( $campaign_id, CC_OFFER_CAMPAIGN_HERO_TITLE_META ),
			'hero_desc'  => cc_offer_campaign_get_meta_string( $campaign_id, CC_OFFER_CAMPAIGN_HERO_DESC_META ),
		)
	);
}

/**
 * Redirect legacy ?vendor=&tag= offer links to the canonical campaign slug URL.
 *
 * @return void
 */
function cc_offer_campaign_redirect_legacy_offers() {
	if ( is_admin() || wp_doing_ajax() ) {
		return;
	}
	$is_offers = is_page_template( 'page-offers.php' ) || is_page( 'offers' );
	if ( ! $is_offers ) {
		return;
	}
	if ( '' !== cc_offer_campaign_get_request_slug() ) {
		return;
	}

	$filters = function_exists( 'cc_offers_get_filters_from_request' ) ? cc_offers_get_filters_from_request() : array();
	$vendor  = isset( $filters['vendor_id'] ) ? (int) $filters['vendor_id'] : 0;
	$tag     = isset( $filters['tag_slug'] ) ? (string) $filters['tag_slug'] : '';
	if ( $vendor <= 0 || '' === $tag ) {
		return;
	}

	$campaign_id = cc_offer_campaign_resolve( $vendor, $tag );
	if ( ! $campaign_id ) {
		return;
	}

	$slug = cc_offer_campaign_get_slug( $campaign_id );
	if ( '' === $slug ) {
		return;
	}

	wp_safe_redirect( cc_offer_campaign_build_offers_url( $slug ), 301 );
	exit;
}
add_action( 'template_redirect', 'cc_offer_campaign_redirect_legacy_offers', 5 );

/**
 * Register campaign-specific meta boxes.
 *
 * @return void
 */
function cc_offer_campaign_add_meta_boxes() {
	remove_meta_box( 'cc_bundle_campaign', CC_BUNDLE_POST_TYPE, 'side' );

	add_meta_box(
		'cc_offer_campaign_products_box',
		__( 'Discounted offer products', 'consucorner' ),
		'cc_offer_campaign_render_products_metabox',
		CC_BUNDLE_POST_TYPE,
		'normal',
		'high'
	);
	add_meta_box(
		'cc_offer_campaign_banner',
		__( 'Offers page banner', 'consucorner' ),
		'cc_offer_campaign_render_banner_metabox',
		CC_BUNDLE_POST_TYPE,
		'normal',
		'high'
	);
	add_meta_box(
		'cc_offer_campaign_hero',
		__( 'Offers hero override', 'consucorner' ),
		'cc_offer_campaign_render_hero_metabox',
		CC_BUNDLE_POST_TYPE,
		'normal',
		'default'
	);
	add_meta_box(
		'cc_offer_campaign_status',
		__( 'Campaign status & schedule', 'consucorner' ),
		'cc_offer_campaign_render_status_metabox',
		CC_BUNDLE_POST_TYPE,
		'side',
		'high'
	);
	add_meta_box(
		'cc_offer_campaign_links',
		__( 'Campaign links', 'consucorner' ),
		'cc_offer_campaign_render_links_metabox',
		CC_BUNDLE_POST_TYPE,
		'side',
		'default'
	);
	add_meta_box(
		'cc_offer_campaign_guide',
		__( 'Operations guide', 'consucorner' ),
		'cc_offer_campaign_render_guide_metabox',
		CC_BUNDLE_POST_TYPE,
		'side',
		'low'
	);
}
add_action( 'add_meta_boxes', 'cc_offer_campaign_add_meta_boxes', 20 );

/**
 * Public URL for the static Campaign operations guide.
 *
 * @return string
 */
function cc_offer_campaign_get_operations_guide_url() {
	return trailingslashit( get_template_directory_uri() ) . 'docs/campaign-operations-guide.html';
}

/**
 * Render the Campaign operations guide link.
 *
 * @return void
 */
function cc_offer_campaign_render_guide_metabox() {
	?>
	<p><?php esc_html_e( 'Open the step-by-step guide before creating, publishing, updating, or ending a campaign.', 'consucorner' ); ?></p>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( cc_offer_campaign_get_operations_guide_url() ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Open operations guide', 'consucorner' ); ?>
		</a>
	</p>
	<?php
}

/**
 * Show the guide on the Campaign list screen.
 *
 * @return void
 */
function cc_offer_campaign_operations_guide_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit-' . CC_BUNDLE_POST_TYPE !== $screen->id || ! current_user_can( 'edit_products' ) ) {
		return;
	}
	?>
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Campaign operations guide:', 'consucorner' ); ?></strong>
			<?php esc_html_e( 'Follow the documented workflow for offer products, bundle setup, publishing, testing, and campaign closure.', 'consucorner' ); ?>
			<a class="button button-secondary" href="<?php echo esc_url( cc_offer_campaign_get_operations_guide_url() ); ?>" target="_blank" rel="noopener noreferrer" style="margin-left:8px">
				<?php esc_html_e( 'Open guide', 'consucorner' ); ?>
			</a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'cc_offer_campaign_operations_guide_notice' );

/**
 * Render the campaign offer product picker.
 *
 * @param WP_Post $post Campaign post.
 * @return void
 */
function cc_offer_campaign_render_products_metabox( $post ) {
	$product_ids = cc_offer_campaign_get_product_ids( $post->ID );
	?>
	<p class="description">
		<?php esc_html_e( 'These products appear below the bundle banner on the Offers page. Their existing WooCommerce sale prices and product-level offer deals are used automatically.', 'consucorner' ); ?>
	</p>
	<p>
		<label for="cc_offer_campaign_products"><strong><?php esc_html_e( 'Offer products', 'consucorner' ); ?></strong></label><br />
		<select
			class="wc-product-search"
			multiple="multiple"
			style="width:100%"
			id="cc_offer_campaign_products"
			name="cc_offer_campaign_products[]"
			data-placeholder="<?php esc_attr_e( 'Search for products…', 'consucorner' ); ?>"
			data-action="woocommerce_json_search_products"
		>
			<?php foreach ( $product_ids as $product_id ) : ?>
				<?php $product = wc_get_product( $product_id ); ?>
				<?php if ( $product ) : ?>
					<option value="<?php echo esc_attr( (string) $product_id ); ?>" selected="selected"><?php echo esc_html( $product->get_formatted_name() ); ?></option>
				<?php endif; ?>
			<?php endforeach; ?>
		</select>
		<span class="description"><?php esc_html_e( 'Set each product discount in its product pricing settings. Bundle products remain managed separately in Bundle details below.', 'consucorner' ); ?></span>
	</p>
	<?php
}

/**
 * Render banner text fields (images are generated from the bundle pool).
 *
 * @param WP_Post $post Campaign post.
 * @return void
 */
function cc_offer_campaign_render_banner_metabox( $post ) {
	$title    = cc_offer_campaign_get_meta_string( $post->ID, CC_OFFER_CAMPAIGN_TITLE_META );
	$subtitle = cc_offer_campaign_get_meta_string( $post->ID, CC_OFFER_CAMPAIGN_SUBTITLE_META );
	$cta      = cc_offer_campaign_get_meta_string( $post->ID, CC_OFFER_CAMPAIGN_CTA_META );

	wp_nonce_field( 'cc_offer_campaign_save_' . $post->ID, 'cc_offer_campaign_nonce' );
	?>
	<p class="description">
		<?php esc_html_e( 'Banner images are generated automatically from the Bundle Details product pool. Leave title/subtitle blank to use the campaign title and excerpt.', 'consucorner' ); ?>
	</p>
	<p>
		<label for="cc_offer_campaign_title"><strong><?php esc_html_e( 'Banner title', 'consucorner' ); ?></strong></label><br />
		<input type="text" class="widefat" id="cc_offer_campaign_title" name="cc_offer_campaign_title" value="<?php echo esc_attr( $title ); ?>" />
	</p>
	<p>
		<label for="cc_offer_campaign_subtitle"><strong><?php esc_html_e( 'Banner subtitle', 'consucorner' ); ?></strong></label><br />
		<textarea class="widefat" rows="3" id="cc_offer_campaign_subtitle" name="cc_offer_campaign_subtitle"><?php echo esc_textarea( $subtitle ); ?></textarea>
	</p>
	<p>
		<label for="cc_offer_campaign_cta_label"><strong><?php esc_html_e( 'Button label', 'consucorner' ); ?></strong></label><br />
		<input type="text" class="widefat" id="cc_offer_campaign_cta_label" name="cc_offer_campaign_cta_label" value="<?php echo esc_attr( $cta ); ?>" placeholder="<?php esc_attr_e( 'Shop the bundle', 'consucorner' ); ?>" />
	</p>
	<?php
}

/**
 * Render optional per-campaign hero fields.
 *
 * @param WP_Post $post Campaign post.
 * @return void
 */
function cc_offer_campaign_render_hero_metabox( $post ) {
	?>
	<p class="description"><?php esc_html_e( 'Leave any field blank to use the global Offers page hero value.', 'consucorner' ); ?></p>
	<p>
		<label for="cc_offer_campaign_hero_badge"><strong><?php esc_html_e( 'Badge', 'consucorner' ); ?></strong></label><br />
		<input type="text" class="widefat" id="cc_offer_campaign_hero_badge" name="cc_offer_campaign_hero_badge" value="<?php echo esc_attr( cc_offer_campaign_get_meta_string( $post->ID, CC_OFFER_CAMPAIGN_HERO_BADGE_META ) ); ?>" />
	</p>
	<p>
		<label for="cc_offer_campaign_hero_title"><strong><?php esc_html_e( 'Hero title', 'consucorner' ); ?></strong></label><br />
		<textarea class="widefat" rows="3" id="cc_offer_campaign_hero_title" name="cc_offer_campaign_hero_title"><?php echo esc_textarea( cc_offer_campaign_get_meta_string( $post->ID, CC_OFFER_CAMPAIGN_HERO_TITLE_META ) ); ?></textarea>
	</p>
	<p>
		<label for="cc_offer_campaign_hero_desc"><strong><?php esc_html_e( 'Hero description', 'consucorner' ); ?></strong></label><br />
		<textarea class="widefat" rows="3" id="cc_offer_campaign_hero_desc" name="cc_offer_campaign_hero_desc"><?php echo esc_textarea( cc_offer_campaign_get_meta_string( $post->ID, CC_OFFER_CAMPAIGN_HERO_DESC_META ) ); ?></textarea>
	</p>
	<?php
}

/**
 * Render campaign status and schedule fields.
 *
 * @param WP_Post $post Campaign post.
 * @return void
 */
function cc_offer_campaign_render_status_metabox( $post ) {
	$active = cc_offer_campaign_is_active( $post->ID );
	$start  = cc_offer_campaign_get_meta_string( $post->ID, CC_OFFER_CAMPAIGN_START_META );
	$end    = cc_offer_campaign_get_meta_string( $post->ID, CC_OFFER_CAMPAIGN_END_META );
	?>
	<p>
		<label>
			<input type="checkbox" name="cc_offer_campaign_active" value="1" <?php checked( $active ); ?> />
			<strong><?php esc_html_e( 'Active on Offers page', 'consucorner' ); ?></strong>
		</label>
	</p>
	<p>
		<label for="cc_offer_campaign_start"><strong><?php esc_html_e( 'Starts', 'consucorner' ); ?></strong></label><br />
		<input type="datetime-local" id="cc_offer_campaign_start" name="cc_offer_campaign_start" value="<?php echo esc_attr( $start ); ?>" style="width:100%" />
	</p>
	<p>
		<label for="cc_offer_campaign_end"><strong><?php esc_html_e( 'Ends', 'consucorner' ); ?></strong></label><br />
		<input type="datetime-local" id="cc_offer_campaign_end" name="cc_offer_campaign_end" value="<?php echo esc_attr( $end ); ?>" style="width:100%" />
	</p>
	<p class="description"><?php esc_html_e( 'Empty dates mean no start or end restriction. The page also requires an active, complete bundle pool.', 'consucorner' ); ?></p>
	<?php
}

/**
 * Render editable slug + copyable clean URLs.
 *
 * @param WP_Post $post Campaign post.
 * @return void
 */
function cc_offer_campaign_render_links_metabox( $post ) {
	$slug   = cc_offer_campaign_get_slug( $post->ID );
	$packs  = function_exists( 'cc_campaign_get_bundles' ) ? cc_campaign_get_bundles( $post->ID ) : array();
	$offer_url = cc_offer_campaign_build_offers_url( $slug );
	?>
	<p>
		<label for="cc_offer_campaign_slug"><strong><?php esc_html_e( 'Campaign URL slug', 'consucorner' ); ?></strong></label>
		<input type="text" id="cc_offer_campaign_slug" name="cc_offer_campaign_slug" class="widefat" value="<?php echo esc_attr( $slug ); ?>" />
		<span class="description"><?php esc_html_e( 'Used for /offers/{slug}/ and /bundles/{slug}/{pack}/. Must be unique.', 'consucorner' ); ?></span>
	</p>
	<p>
		<label for="cc-offer-campaign-offer-url"><strong><?php esc_html_e( 'Offers URL', 'consucorner' ); ?></strong></label>
		<input type="text" id="cc-offer-campaign-offer-url" class="widefat" readonly value="<?php echo esc_attr( $offer_url ); ?>" />
		<button type="button" class="button cc-offer-campaign-copy" data-copy-target="cc-offer-campaign-offer-url" style="margin-top:6px"><?php esc_html_e( 'Copy offers link', 'consucorner' ); ?></button>
	</p>
	<?php if ( ! empty( $packs ) ) : ?>
		<p><strong><?php esc_html_e( 'Bundle pack links', 'consucorner' ); ?></strong></p>
		<?php foreach ( $packs as $index => $pack ) : ?>
			<?php
			$pack_key  = isset( $pack['key'] ) ? (string) $pack['key'] : '';
			$pack_url  = cc_offer_campaign_build_bundles_pack_url( $slug, $pack_key );
			$pack_label = '' !== (string) ( $pack['title'] ?? '' )
				? (string) $pack['title']
				: sprintf(
					/* translators: %d: pack number */
					__( 'Pack %d', 'consucorner' ),
					$index + 1
				);
			$field_id = 'cc-offer-campaign-pack-url-' . sanitize_html_class( $pack_key );
			?>
			<p>
				<label for="<?php echo esc_attr( $field_id ); ?>"><strong><?php echo esc_html( $pack_label ); ?></strong></label>
				<input type="text" id="<?php echo esc_attr( $field_id ); ?>" class="widefat" readonly value="<?php echo esc_attr( $pack_url ); ?>" />
				<button type="button" class="button cc-offer-campaign-copy" data-copy-target="<?php echo esc_attr( $field_id ); ?>" style="margin-top:6px"><?php esc_html_e( 'Copy pack link', 'consucorner' ); ?></button>
			</p>
		<?php endforeach; ?>
	<?php else : ?>
		<p class="description"><?php esc_html_e( 'Add bundle packs to generate shareable pack links.', 'consucorner' ); ?></p>
	<?php endif; ?>
	<?php
}

/**
 * Ensure a unique post_name for a campaign.
 *
 * @param string $slug     Desired slug.
 * @param int    $post_id  Current post ID.
 * @return string
 */
function cc_offer_campaign_unique_slug( $slug, $post_id ) {
	$slug = sanitize_title( $slug );
	if ( '' === $slug ) {
		$slug = 'campaign-' . absint( $post_id );
	}

	$base  = $slug;
	$index = 2;
	while ( true ) {
		$existing = get_posts(
			array(
				'name'                   => $slug,
				'post_type'              => CC_BUNDLE_POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'post__not_in'           => array( absint( $post_id ) ),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( empty( $existing ) ) {
			return $slug;
		}
		$slug = $base . '-' . $index;
		$index++;
		if ( $index > 100 ) {
			return $base . '-' . absint( $post_id );
		}
	}
}

/**
 * Save unified offer campaign metadata.
 *
 * @param int $post_id Campaign post ID.
 * @return void
 */
function cc_offer_campaign_save_post( $post_id ) {
	if ( ! isset( $_POST['cc_offer_campaign_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cc_offer_campaign_nonce'] ) ), 'cc_offer_campaign_save_' . $post_id ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) || CC_BUNDLE_POST_TYPE !== get_post_type( $post_id ) ) {
		return;
	}

	$old_slug   = cc_offer_campaign_get_slug( $post_id );
	$old_vendor = cc_offer_campaign_get_vendor_id( $post_id );
	$old_tag    = cc_offer_campaign_get_tag_slug( $post_id );

	$text_fields = array(
		CC_OFFER_CAMPAIGN_TITLE_META      => 'cc_offer_campaign_title',
		CC_OFFER_CAMPAIGN_SUBTITLE_META   => 'cc_offer_campaign_subtitle',
		CC_OFFER_CAMPAIGN_CTA_META        => 'cc_offer_campaign_cta_label',
		CC_OFFER_CAMPAIGN_HERO_BADGE_META => 'cc_offer_campaign_hero_badge',
		CC_OFFER_CAMPAIGN_HERO_DESC_META  => 'cc_offer_campaign_hero_desc',
	);
	foreach ( $text_fields as $meta_key => $field_name ) {
		$value = isset( $_POST[ $field_name ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $field_name ] ) ) : '';
		if ( '' !== $value ) {
			update_post_meta( $post_id, $meta_key, $value );
		} else {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	$hero_title = isset( $_POST['cc_offer_campaign_hero_title'] ) ? wp_kses_post( wp_unslash( $_POST['cc_offer_campaign_hero_title'] ) ) : '';
	if ( '' !== trim( $hero_title ) ) {
		update_post_meta( $post_id, CC_OFFER_CAMPAIGN_HERO_TITLE_META, $hero_title );
	} else {
		delete_post_meta( $post_id, CC_OFFER_CAMPAIGN_HERO_TITLE_META );
	}

	update_post_meta( $post_id, CC_OFFER_CAMPAIGN_ACTIVE_META, isset( $_POST['cc_offer_campaign_active'] ) ? '1' : '0' );

	$offer_products = array();
	$raw_products   = isset( $_POST['cc_offer_campaign_products'] ) ? (array) wp_unslash( $_POST['cc_offer_campaign_products'] ) : array();
	foreach ( $raw_products as $raw_product_id ) {
		$product = wc_get_product( absint( $raw_product_id ) );
		if ( $product ) {
			$offer_products[] = $product->get_id();
		}
	}
	update_post_meta( $post_id, CC_OFFER_CAMPAIGN_PRODUCTS_META, array_values( array_unique( $offer_products ) ) );

	$date_fields = array(
		CC_OFFER_CAMPAIGN_START_META => 'cc_offer_campaign_start',
		CC_OFFER_CAMPAIGN_END_META   => 'cc_offer_campaign_end',
	);
	foreach ( $date_fields as $meta_key => $field_name ) {
		$value = isset( $_POST[ $field_name ] ) ? cc_offer_campaign_sanitize_datetime( $_POST[ $field_name ] ) : '';
		if ( '' !== $value ) {
			update_post_meta( $post_id, $meta_key, $value );
		} else {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	$requested_slug = isset( $_POST['cc_offer_campaign_slug'] )
		? sanitize_title( wp_unslash( $_POST['cc_offer_campaign_slug'] ) )
		: $old_slug;
	if ( '' === $requested_slug ) {
		$requested_slug = sanitize_title( get_the_title( $post_id ) );
	}
	$unique_slug = cc_offer_campaign_unique_slug( $requested_slug, $post_id );
	if ( $unique_slug !== $old_slug ) {
		remove_action( 'save_post_' . CC_BUNDLE_POST_TYPE, 'cc_offer_campaign_save_post', 20 );
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => $unique_slug,
			)
		);
		add_action( 'save_post_' . CC_BUNDLE_POST_TYPE, 'cc_offer_campaign_save_post', 20 );
	}

	cc_offer_campaign_clear_slug_cache( $old_slug );
	cc_offer_campaign_clear_slug_cache( $unique_slug );
	cc_offer_campaign_clear_cache( $old_vendor, $old_tag );
	delete_transient( cc_offer_campaign_all_products_cache_key() );
	delete_transient( 'cc_offer_campaign_public_ids' );
}
add_action( 'save_post_' . CC_BUNDLE_POST_TYPE, 'cc_offer_campaign_save_post', 20 );

/**
 * Clear resolver caches before a campaign is trashed or deleted.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function cc_offer_campaign_clear_post_cache( $post_id ) {
	if ( CC_BUNDLE_POST_TYPE !== get_post_type( $post_id ) ) {
		return;
	}
	cc_offer_campaign_clear_all_caches( $post_id );
}
add_action( 'wp_trash_post', 'cc_offer_campaign_clear_post_cache' );
add_action( 'before_delete_post', 'cc_offer_campaign_clear_post_cache' );

/**
 * Campaign edit-screen live URL builders.
 *
 * @param string $hook Admin page hook.
 * @return void
 */
function cc_offer_campaign_admin_assets( $hook ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || CC_BUNDLE_POST_TYPE !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	wp_enqueue_script( 'jquery' );

	$script  = '(function($){$(function(){';
	$script .= 'var slug=$("#cc_offer_campaign_slug"),offerUrl=$("#cc-offer-campaign-offer-url"),bundleUrl=$("#cc-offer-campaign-bundle-url"),offerBase=' . wp_json_encode( function_exists( 'cc_offers_get_base_url' ) ? cc_offers_get_base_url() : home_url( '/offers/' ) ) . ',bundleBase=' . wp_json_encode( function_exists( 'cc_bundles_get_base_url' ) ? cc_bundles_get_base_url() : home_url( '/bundles/' ) ) . ';';
	$script .= 'function sanitizeSlug(v){return String(v||"").toLowerCase().replace(/[^a-z0-9-]+/g,"-").replace(/^-+|-+$/g,"");}';
	$script .= 'function withSlash(base){return base.slice(-1)==="/"?base:base+"/";}';
	$script .= 'function buildLinks(){var s=sanitizeSlug(slug.val());offerUrl.val(s?withSlash(offerBase)+s+"/":withSlash(offerBase));bundleUrl.val(s?withSlash(bundleBase)+s+"/":withSlash(bundleBase));}';
	$script .= 'slug.on("input change",buildLinks);buildLinks();';
	$script .= '$(".cc-offer-campaign-copy").on("click",function(){var input=document.getElementById($(this).data("copy-target"));if(!input)return;if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(input.value);}else{input.select();document.execCommand("copy");}});';
	$script .= '});})(jQuery);';

	wp_add_inline_script( 'jquery', $script );
}
add_action( 'admin_enqueue_scripts', 'cc_offer_campaign_admin_assets', 20 );

/**
 * Add useful campaign columns to the existing post list.
 *
 * @param array<string,string> $columns Existing columns.
 * @return array<string,string>
 */
function cc_offer_campaign_admin_columns( $columns ) {
	$columns['cc_campaign_slug']     = __( 'Slug', 'consucorner' );
	$columns['cc_campaign_offers']   = __( 'Offers', 'consucorner' );
	$columns['cc_campaign_bundle']   = __( 'Bundle', 'consucorner' );
	$columns['cc_campaign_schedule'] = __( 'Status / schedule', 'consucorner' );
	return $columns;
}
add_filter( 'manage_' . CC_BUNDLE_POST_TYPE . '_posts_columns', 'cc_offer_campaign_admin_columns' );

/**
 * Render campaign list-table columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Campaign post ID.
 * @return void
 */
function cc_offer_campaign_admin_column_content( $column, $post_id ) {
	if ( 'cc_campaign_slug' === $column ) {
		$slug = cc_offer_campaign_get_slug( $post_id );
		echo esc_html( $slug ?: __( '—', 'consucorner' ) );
	} elseif ( 'cc_campaign_offers' === $column ) {
		$count = count( cc_offer_campaign_get_product_ids( $post_id ) );
		echo esc_html(
			$count > 0
				? sprintf(
					/* translators: %d: product count */
					_n( '%d product', '%d products', $count, 'consucorner' ),
					$count
				)
				: __( 'Not configured', 'consucorner' )
		);
	} elseif ( 'cc_campaign_bundle' === $column ) {
		$size = function_exists( 'cc_bundles_get_size' ) ? cc_bundles_get_size( $post_id ) : 0;
		$pool = function_exists( 'cc_bundles_get_pool' ) ? count( cc_bundles_get_pool( $post_id ) ) : 0;
		echo esc_html(
			cc_offer_campaign_has_bundle( $post_id )
				? sprintf(
					/* translators: 1: bundle size, 2: pool count */
					__( '%1$d for pool of %2$d', 'consucorner' ),
					$size,
					$pool
				)
				: __( 'Incomplete', 'consucorner' )
		);
	} elseif ( 'cc_campaign_schedule' === $column ) {
		$status = cc_offer_campaign_is_active( $post_id ) && cc_offer_campaign_is_in_schedule( $post_id )
			? __( 'Active', 'consucorner' )
			: __( 'Inactive', 'consucorner' );
		echo esc_html( $status );
	}
}
add_action( 'manage_' . CC_BUNDLE_POST_TYPE . '_posts_custom_column', 'cc_offer_campaign_admin_column_content', 10, 2 );
