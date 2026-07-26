<?php
/**
 * Dynamic offers landing — campaign slug URLs with auto-index on /offers/.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default hero copy for the offers page (matches marketing mockup).
 *
 * @return array{badge:string,title:string,description:string}
 */
function cc_offers_get_default_meta() {
	return array(
		'badge'       => 'Certified Excellence',
		'title'       => '<span class="cc-offers-hero__brand">ConsuCorner</span> <span class="cc-offers-hero__deal">Flash Deals – Premium</span><br />Medical Supplies at Unbeatable Prices.',
		'description' => 'Exclusive access to professional surgical instruments, high-grade consumables, and specialty-specific equipment. Certified global quality standards for clinical precision.',
	);
}

/**
 * Seed offers page hero meta when empty or still on the old short defaults.
 *
 * @param int $page_id Page ID.
 * @return void
 */
function cc_offers_seed_page_meta( $page_id ) {
	$page_id = absint( $page_id );
	if ( $page_id <= 0 ) {
		return;
	}

	$defaults = cc_offers_get_default_meta();
	$badge    = (string) get_post_meta( $page_id, '_cc_offers_badge', true );
	$title    = (string) get_post_meta( $page_id, '_cc_offers_title', true );
	$desc     = (string) get_post_meta( $page_id, '_cc_offers_description', true );

	if ( '' === $badge ) {
		update_post_meta( $page_id, '_cc_offers_badge', $defaults['badge'] );
	}

	if ( '' === $title || ( false !== strpos( $title, 'Flash Deals</span>' ) && false === strpos( $title, 'Premium' ) ) ) {
		update_post_meta( $page_id, '_cc_offers_title', $defaults['title'] );
	}

	if ( '' === $desc || false === strpos( $desc, 'clinical precision' ) ) {
		update_post_meta( $page_id, '_cc_offers_description', $defaults['description'] );
	}
}

/**
 * One-time seed of hero defaults on the published offers page.
 *
 * @return void
 */
function cc_offers_maybe_seed_defaults() {
	if ( get_option( 'consucorner_offers_meta_defaults_v2' ) ) {
		return;
	}

	$page_id = cc_offers_get_page_id();
	if ( $page_id > 0 ) {
		cc_offers_seed_page_meta( $page_id );
	}

	update_option( 'consucorner_offers_meta_defaults_v2', true );
}
add_action( 'after_setup_theme', 'cc_offers_maybe_seed_defaults', 30 );

/**
 * Return the published Offers page ID using the offers template.
 *
 * @return int
 */
function cc_offers_get_page_id() {
	static $page_id = null;

	if ( null !== $page_id ) {
		return (int) $page_id;
	}

	$page_id = 0;
	$by_path = get_page_by_path( 'offers', OBJECT, 'page' );

	if ( $by_path && 'publish' === $by_path->post_status ) {
		$page_id = (int) $by_path->ID;
	} else {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_wp_page_template',
				'meta_value'     => 'page-offers.php',
			)
		);

		if ( ! empty( $pages[0] ) ) {
			$page_id = (int) $pages[0];
		}
	}

	return (int) $page_id;
}

/**
 * Base permalink for the offers page.
 *
 * @return string
 */
function cc_offers_get_base_url() {
	$page_id = cc_offers_get_page_id();

	if ( $page_id > 0 ) {
		$url = get_permalink( $page_id );
		if ( $url ) {
			return $url;
		}
	}

	return home_url( '/offers/' );
}

/**
 * Dokan sellers for admin dropdowns.
 *
 * @return array<int,array{id:int,label:string,username:string}>
 */
function cc_offers_get_vendors_list() {
	$users = array();

	if ( class_exists( 'Consucorner_Vendor_Ledger' ) && method_exists( 'Consucorner_Vendor_Ledger', 'get_vendors' ) ) {
		$users = Consucorner_Vendor_Ledger::get_vendors();
	} else {
		$users = get_users(
			array(
				'role__in' => array( 'seller', 'vendor' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'number'   => 1000,
			)
		);
	}

	$list = array();

	foreach ( $users as $user ) {
		$user_id  = 0;
		$username = '';
		$label    = '';

		if ( $user instanceof WP_User ) {
			$user_id  = (int) $user->ID;
			$username = (string) $user->user_login;
			$label    = (string) $user->display_name;
		} elseif ( is_object( $user ) && isset( $user->ID ) ) {
			$user_id = (int) $user->ID;
			$full    = get_userdata( $user_id );
			if ( $full instanceof WP_User ) {
				$username = (string) $full->user_login;
				$label    = (string) $full->display_name;
			} else {
				$label = isset( $user->display_name ) ? (string) $user->display_name : '';
			}
		} else {
			continue;
		}

		if ( $user_id <= 0 ) {
			continue;
		}

		if ( function_exists( 'dokan_get_store_info' ) ) {
			$store = dokan_get_store_info( $user_id );
			if ( ! empty( $store['store_name'] ) ) {
				$label = (string) $store['store_name'];
			}
		}

		if ( '' === $username ) {
			$fallback = get_userdata( $user_id );
			if ( $fallback instanceof WP_User ) {
				$username = (string) $fallback->user_login;
				if ( '' === $label ) {
					$label = (string) $fallback->display_name;
				}
			}
		}

		$list[] = array(
			'id'       => $user_id,
			'label'    => $label,
			'username' => $username,
		);
	}

	return $list;
}

/**
 * Product tags for admin dropdowns.
 *
 * @return WP_Term[]
 */
function cc_offers_get_product_tags_list() {
	if ( ! taxonomy_exists( 'product_tag' ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'product_tag',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
			'number'     => 500,
		)
	);

	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Resolve vendor query value to a user ID (Dokan author).
 *
 * @param string|int $raw Vendor username or user ID.
 * @return int
 */
function cc_offers_resolve_vendor_id( $raw ) {
	$raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';

	if ( '' === $raw ) {
		return 0;
	}

	if ( ctype_digit( $raw ) ) {
		$user = get_user_by( 'id', (int) $raw );
		return $user instanceof WP_User ? (int) $user->ID : 0;
	}

	$user = get_user_by( 'login', $raw );
	if ( $user instanceof WP_User ) {
		return (int) $user->ID;
	}

	// Backward compatibility for older links that used store nicename/slug.
	$slug = sanitize_title( $raw );
	$user = get_user_by( 'slug', $slug );
	if ( $user instanceof WP_User ) {
		return (int) $user->ID;
	}

	return 0;
}

/**
 * Vendor username for shareable URLs (WordPress user_login).
 *
 * @param int $vendor_id Vendor user ID.
 * @return string
 */
function cc_offers_get_vendor_username_for_url( $vendor_id ) {
	$vendor_id = absint( $vendor_id );
	if ( ! $vendor_id ) {
		return '';
	}

	$user = get_userdata( $vendor_id );
	return $user instanceof WP_User ? (string) $user->user_login : '';
}

/**
 * @deprecated 2.3.4 Use cc_offers_get_vendor_username_for_url().
 */
function cc_offers_get_vendor_slug_for_url( $vendor_id ) {
	return cc_offers_get_vendor_username_for_url( $vendor_id );
}

/**
 * Resolve and validate a product_tag slug.
 *
 * @param string $raw Tag slug.
 * @return string Valid slug or empty string.
 */
function cc_offers_resolve_tag_slug( $raw ) {
	$slug = sanitize_title( is_scalar( $raw ) ? (string) $raw : '' );

	if ( '' === $slug || ! taxonomy_exists( 'product_tag' ) ) {
		return '';
	}

	$term = get_term_by( 'slug', $slug, 'product_tag' );

	return ( $term instanceof WP_Term ) ? (string) $term->slug : '';
}

/**
 * Read vendor + tag filters from the current request.
 *
 * @return array{vendor_id:int,tag_slug:string,vendor_username:string}
 */
function cc_offers_get_filters_from_request() {
	$vendor_raw = isset( $_GET['vendor'] ) ? wp_unslash( $_GET['vendor'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tag_raw    = isset( $_GET['tag'] ) ? wp_unslash( $_GET['tag'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$vendor_id       = cc_offers_resolve_vendor_id( $vendor_raw );
	$tag_slug        = cc_offers_resolve_tag_slug( $tag_raw );
	$vendor_username = $vendor_id ? cc_offers_get_vendor_username_for_url( $vendor_id ) : '';

	return array(
		'vendor_id'       => $vendor_id,
		'tag_slug'        => $tag_slug,
		'vendor_username' => $vendor_username,
	);
}

/**
 * Build a shareable offers URL.
 *
 * @param string|int $vendor Vendor username or user ID.
 * @param string     $tag    Product tag slug.
 * @return string
 */
function cc_offers_build_url( $vendor, $tag ) {
	$vendor_id = cc_offers_resolve_vendor_id( $vendor );
	$tag_slug  = cc_offers_resolve_tag_slug( $tag );
	$base      = cc_offers_get_base_url();

	if ( ! $vendor_id || '' === $tag_slug ) {
		return $base;
	}

	return add_query_arg(
		array(
			'vendor' => cc_offers_get_vendor_username_for_url( $vendor_id ),
			'tag'    => $tag_slug,
		),
		$base
	);
}

/**
 * Human-readable vendor label.
 *
 * @param int $vendor_id Vendor user ID.
 * @return string
 */
function cc_offers_get_vendor_label( $vendor_id ) {
	$vendor_id = absint( $vendor_id );
	if ( ! $vendor_id ) {
		return '';
	}

	if ( function_exists( 'dokan_get_store_info' ) ) {
		$store = dokan_get_store_info( $vendor_id );
		if ( ! empty( $store['store_name'] ) ) {
			return (string) $store['store_name'];
		}
	}

	$user = get_userdata( $vendor_id );
	return $user instanceof WP_User ? $user->display_name : '';
}

/**
 * Query products for vendor + tag.
 *
 * @param int    $vendor_id Vendor user ID.
 * @param string $tag_slug  Product tag slug.
 * @return WP_Query
 */
function cc_offers_query_products( $vendor_id, $tag_slug ) {
	$vendor_id = absint( $vendor_id );
	$tag_slug  = cc_offers_resolve_tag_slug( $tag_slug );

	$args = array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => 48,
		'ignore_sticky_posts' => true,
		'orderby'             => 'title',
		'order'               => 'ASC',
		'no_found_rows'       => false,
	);

	if ( $vendor_id > 0 ) {
		$args['author'] = $vendor_id;
	}

	if ( '' !== $tag_slug ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'product_tag',
				'field'    => 'slug',
				'terms'    => $tag_slug,
			),
		);
	}

	return new WP_Query( $args );
}

/**
 * Offers page meta with defaults.
 *
 * @param int $page_id Page ID.
 * @return array{badge:string,title:string,description:string}
 */
function cc_offers_get_page_meta( $page_id ) {
	$page_id  = absint( $page_id );
	$defaults = cc_offers_get_default_meta();

	return array(
		'badge'       => (string) get_post_meta( $page_id, '_cc_offers_badge', true ) ?: $defaults['badge'],
		'title'       => (string) get_post_meta( $page_id, '_cc_offers_title', true ) ?: $defaults['title'],
		'description' => (string) get_post_meta( $page_id, '_cc_offers_description', true ) ?: $defaults['description'],
	);
}

/**
 * Per-product bundle deal for the offers page (exact qty for a fixed total).
 *
 * @param WC_Product|int $product       Product object or ID.
 * @param array          $args          Optional. require_stock (bool) — skip stock checks for cart pricing.
 * @return array{qty:int,total:float,unit:float,regular_total:float,save_percent:int}|null
 */
function cc_offers_get_product_deal( $product, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'require_stock' => true,
		)
	);

	if ( ! ( $product instanceof WC_Product ) ) {
		$product = wc_get_product( $product );
	}
	if ( ! $product instanceof WC_Product ) {
		return null;
	}

	if ( 'simple' !== $product->get_type() ) {
		return null;
	}

	if ( function_exists( 'cc_is_quote_product' ) && cc_is_quote_product( $product ) ) {
		return null;
	}

	if ( ! $product->is_purchasable() ) {
		return null;
	}

	$pid     = $product->get_id();
	$enabled = (string) get_post_meta( $pid, '_cc_offer_deal_enabled', true );

	if ( '1' !== $enabled ) {
		return null;
	}

	$qty   = absint( get_post_meta( $pid, '_cc_offer_deal_qty', true ) );
	$total = (float) wc_format_decimal( get_post_meta( $pid, '_cc_offer_deal_price', true ) );

	if ( $qty < 2 || $total <= 0 ) {
		return null;
	}

	if ( ! empty( $args['require_stock'] ) ) {
		if ( ! $product->is_in_stock() ) {
			return null;
		}

		if ( $product->managing_stock() ) {
			$stock = $product->get_stock_quantity();
			if ( null === $stock || (int) $stock < $qty ) {
				return null;
			}
		}
	}

	$regular_piece = (float) $product->get_regular_price();
	if ( $regular_piece <= 0 ) {
		$regular_piece = (float) $product->get_price();
	}

	if ( $regular_piece <= 0 ) {
		return null;
	}

	$regular_total = $regular_piece * $qty;

	if ( $total >= $regular_total ) {
		return null;
	}

	$save_percent = max( 1, (int) round( ( ( $regular_total - $total ) / $regular_total ) * 100 ) );

	return array(
		'qty'           => $qty,
		'total'         => $total,
		'unit'          => $total / $qty,
		'regular_total' => $regular_total,
		'save_percent'  => $save_percent,
	);
}

/**
 * Site-wide bulk pricing tiers for a product (quantity ranges -> per-unit price).
 *
 * @param WC_Product|int $product Product object or ID.
 * @param array          $args    Optional. require_stock (bool) — skip the in-stock check for cart pricing.
 * @return array<int,array{min:int,max:int,price:float,regular_unit:float,save_percent:int,label:string}> Empty when none apply.
 */
function cc_get_product_bulk_tiers( $product, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'require_stock' => true,
		)
	);

	if ( ! ( $product instanceof WC_Product ) ) {
		$product = wc_get_product( $product );
	}
	if ( ! $product instanceof WC_Product ) {
		return array();
	}

	if ( 'simple' !== $product->get_type() ) {
		return array();
	}

	if ( function_exists( 'cc_is_quote_product' ) && cc_is_quote_product( $product ) ) {
		return array();
	}

	if ( ! $product->is_purchasable() ) {
		return array();
	}

	if ( ! empty( $args['require_stock'] ) && ! $product->is_in_stock() ) {
		return array();
	}

	$pid     = $product->get_id();
	$enabled = (string) get_post_meta( $pid, '_cc_bulk_enabled', true );

	if ( '1' !== $enabled ) {
		return array();
	}

	$raw_tiers = get_post_meta( $pid, '_cc_bulk_tiers', true );
	if ( ! is_array( $raw_tiers ) || empty( $raw_tiers ) ) {
		return array();
	}

	$regular_unit = (float) $product->get_regular_price();
	if ( $regular_unit <= 0 ) {
		$regular_unit = (float) $product->get_price();
	}

	$tiers = array();

	foreach ( $raw_tiers as $raw_tier ) {
		$min   = isset( $raw_tier['min'] ) ? absint( $raw_tier['min'] ) : 0;
		$max   = isset( $raw_tier['max'] ) ? absint( $raw_tier['max'] ) : 0;
		$price = isset( $raw_tier['price'] ) ? (float) $raw_tier['price'] : 0.0;

		if ( $min < 1 || $price <= 0 ) {
			continue;
		}

		$save_percent = 0;
		if ( $regular_unit > 0 && $price < $regular_unit ) {
			$save_percent = max( 1, (int) round( ( ( $regular_unit - $price ) / $regular_unit ) * 100 ) );
		}

		$label = $max > 0
			/* translators: 1: min qty, 2: max qty */
			? sprintf( __( '%1$d-%2$d units', 'consucorner' ), $min, $max )
			/* translators: %d: min qty */
			: sprintf( __( '%d+ units', 'consucorner' ), $min );

		$tiers[] = array(
			'min'          => $min,
			'max'          => $max,
			'price'        => $price,
			'regular_unit' => $regular_unit,
			'save_percent' => $save_percent,
			'label'        => $label,
		);
	}

	usort(
		$tiers,
		function ( $a, $b ) {
			return $a['min'] <=> $b['min'];
		}
	);

	return $tiers;
}

/**
 * Smallest bulk-tier minimum quantity for a product that is configured to
 * sell only in bulk (i.e. has bulk pricing tiers enabled).
 *
 * @param WC_Product|int $product Product object or ID.
 * @param array          $args    Optional. Passed through to cc_get_product_bulk_tiers().
 * @return int Minimum bulk quantity, or 0 when bulk pricing is not enabled/configured.
 */
function cc_get_product_bulk_min_qty( $product, $args = array() ) {
	$tiers = cc_get_product_bulk_tiers( $product, $args );
	if ( empty( $tiers ) ) {
		return 0;
	}

	$min = 0;
	foreach ( $tiers as $tier ) {
		if ( 0 === $min || (int) $tier['min'] < $min ) {
			$min = (int) $tier['min'];
		}
	}

	return $min;
}

/**
 * Quantity step (per +/- click) for a product's quantity stepper.
 *
 * Lets bulk-only products with a high minimum (e.g. "min 21, max 100") be
 * adjusted in bigger increments (e.g. +5 per click) instead of forcing the
 * customer to click one-by-one. Only meaningful when bulk pricing is enabled;
 * otherwise always the normal single-unit step.
 *
 * @param WC_Product|int $product Product object or ID.
 * @return int Step size, always >= 1.
 */
function cc_get_product_bulk_qty_step( $product ) {
	if ( ! ( $product instanceof WC_Product ) ) {
		$product = wc_get_product( $product );
	}
	if ( ! $product instanceof WC_Product ) {
		return 1;
	}

	if ( '1' !== (string) get_post_meta( $product->get_id(), '_cc_bulk_enabled', true ) ) {
		return 1;
	}

	$step = absint( get_post_meta( $product->get_id(), '_cc_bulk_qty_step', true ) );

	return $step > 1 ? $step : 1;
}

/**
 * Find the best (cheapest) bulk tier that applies to a given quantity.
 *
 * A tier applies as soon as the quantity reaches its minimum ("buy N or more").
 * Among every applicable tier we return the one with the lowest per-unit price, so a
 * customer whose quantity exceeds the highest tier's optional max still gets charged
 * the cheapest available unit price rather than reverting to the catalog price. The
 * tier `max` is used only for display labelling, never to disqualify pricing.
 *
 * @param array $tiers Tiers from cc_get_product_bulk_tiers().
 * @param int   $qty   Quantity to match.
 * @return array|null Cheapest applicable tier or null.
 */
function cc_find_bulk_tier_for_qty( $tiers, $qty ) {
	$qty  = absint( $qty );
	$best = null;

	foreach ( $tiers as $tier ) {
		if ( $qty < (int) $tier['min'] ) {
			continue;
		}
		if ( null === $best || (float) $tier['price'] < (float) $best['price'] ) {
			$best = $tier;
		}
	}

	return $best;
}

/**
 * Resolve the cheapest applicable per-unit price for a product at a given quantity,
 * considering the Offer Deal (deal unit price once qty reaches the deal threshold)
 * and/or the quantity-range Bulk tiers.
 *
 * @param WC_Product|int $product Product object or ID.
 * @param int             $qty     Cart/requested quantity.
 * @param array           $args    Optional. require_stock (bool).
 * @return array{price:float,source:string,deal:array|null,tier:array|null}|null
 *         Null when neither pricing rule applies (caller should use the catalog price).
 */
function cc_get_effective_unit_price( $product, $qty, $args = array() ) {
	if ( ! ( $product instanceof WC_Product ) ) {
		$product = wc_get_product( $product );
	}
	if ( ! $product instanceof WC_Product ) {
		return null;
	}

	$qty = absint( $qty );

	$deal      = function_exists( 'cc_offers_get_product_deal' ) ? cc_offers_get_product_deal( $product, $args ) : null;
	$deal_unit = ( $deal && $qty >= (int) $deal['qty'] ) ? (float) $deal['unit'] : null;

	$tiers = cc_get_product_bulk_tiers( $product, $args );
	$tier  = $tiers ? cc_find_bulk_tier_for_qty( $tiers, $qty ) : null;
	$tier_unit = $tier ? (float) $tier['price'] : null;

	if ( null === $deal_unit && null === $tier_unit ) {
		return null;
	}

	if ( null !== $deal_unit && ( null === $tier_unit || $deal_unit <= $tier_unit ) ) {
		return array(
			'price'  => $deal_unit,
			'source' => 'offer_deal',
			'deal'   => $deal,
			'tier'   => null,
		);
	}

	return array(
		'price'  => $tier_unit,
		'source' => 'bulk',
		'deal'   => null,
		'tier'   => $tier,
	);
}

/**
 * Whether a product should show fractional bulk unit prices despite WC decimals = 0.
 *
 * @param WC_Product|int $product Product object or ID.
 * @return bool
 */
function cc_bulk_should_show_exact_unit_price( $product ) {
	if ( ! ( $product instanceof WC_Product ) ) {
		$product = wc_get_product( $product );
	}
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	$pid = $product->get_id();
	if ( '1' !== (string) get_post_meta( $pid, '_cc_bulk_enabled', true ) ) {
		return false;
	}

	return '1' === (string) get_post_meta( $pid, '_cc_bulk_show_exact_unit_price', true );
}

/**
 * Decimal places needed to display a bulk unit price without collapsing distinct tiers.
 *
 * When exact display is off, returns the global WooCommerce decimal count.
 * When on, returns the greater of (WC decimals, significant fractional digits up to 2).
 *
 * @param float|int|string   $price   Unit price amount.
 * @param WC_Product|int|null $product Product used to decide exact-display mode.
 * @return int
 */
function cc_get_bulk_price_display_decimals( $price, $product = null ) {
	$wc_decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;

	if ( ! $product || ! cc_bulk_should_show_exact_unit_price( $product ) ) {
		return max( 0, $wc_decimals );
	}

	$amount = (float) $price;
	$scaled = round( abs( $amount ) * 100 );
	$cents  = (int) round( $scaled ) % 100;

	if ( 0 === $cents ) {
		return max( 0, $wc_decimals );
	}

	// Prefer one decimal when the second digit is zero (11.50 → 11.5).
	if ( 0 === ( $cents % 10 ) ) {
		return max( 1, $wc_decimals );
	}

	return max( 2, $wc_decimals );
}

/**
 * Format a bulk unit price amount (no currency symbol), respecting exact-display mode.
 *
 * @param float|int|string    $price   Unit price amount.
 * @param WC_Product|int|null $product Product object or ID.
 * @return string
 */
function cc_format_bulk_unit_price_amount( $price, $product = null ) {
	$decimals = cc_get_bulk_price_display_decimals( $price, $product );

	if ( function_exists( 'cc_format_product_price_amount' ) ) {
		return cc_format_product_price_amount( $price, $decimals );
	}

	$decimal_sep  = function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : '.';
	$thousand_sep = function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : ',';

	return number_format( (float) $price, $decimals, $decimal_sep, $thousand_sep );
}

/**
 * Format a bulk unit price with currency HTML, using exact decimals when enabled.
 *
 * @param float|int|string    $price    Unit price amount.
 * @param WC_Product|int|null $product  Product object or ID.
 * @param string|null         $currency Optional currency code.
 * @return string Safe HTML from wc_price() or a plain fallback.
 */
function cc_format_bulk_unit_price_html( $price, $product = null, $currency = null ) {
	$decimals = cc_get_bulk_price_display_decimals( $price, $product );
	$args     = array(
		'decimals' => $decimals,
	);

	if ( $currency ) {
		$args['currency'] = $currency;
	}

	if ( function_exists( 'wc_price' ) ) {
		return wc_price( (float) $price, $args );
	}

	$symbol = $currency ? $currency : ( function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'EGP' );
	return esc_html( cc_format_bulk_unit_price_amount( $price, $product ) . ' ' . $symbol );
}

/**
 * Build display payload for an active bulk-priced cart line.
 *
 * @param array $cart_item WooCommerce cart item.
 * @return array{
 *   enabled:bool,
 *   unit:float,
 *   unitFormatted:string,
 *   unitHtml:string,
 *   regularUnit:float,
 *   regularUnitFormatted:string,
 *   regularUnitHtml:string,
 *   tierLabel:string,
 *   savePercent:int,
 *   note:string
 * }|null
 */
function cc_get_cart_item_bulk_price_display_data( $cart_item ) {
	if ( empty( $cart_item['data'] ) || ! ( $cart_item['data'] instanceof WC_Product ) ) {
		return null;
	}

	// Bundle group lines use flat bundle pricing; skip bulk notes there.
	if ( ! empty( $cart_item['cc_bundle_instance'] ) ) {
		return null;
	}

	$product = $cart_item['data'];
	if ( ! cc_bulk_should_show_exact_unit_price( $product ) ) {
		return null;
	}

	$qty     = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 0;
	$pricing = function_exists( 'cc_get_effective_unit_price' )
		? cc_get_effective_unit_price( $product, $qty, array( 'require_stock' => false ) )
		: null;

	if ( ! $pricing || 'bulk' !== $pricing['source'] || empty( $pricing['tier'] ) ) {
		return null;
	}

	$tier          = $pricing['tier'];
	$unit          = (float) $pricing['price'];
	$regular_unit  = isset( $tier['regular_unit'] ) ? (float) $tier['regular_unit'] : (float) $product->get_regular_price();
	if ( $regular_unit <= 0 ) {
		$catalog = wc_get_product( $product->get_id() );
		if ( $catalog instanceof WC_Product ) {
			$regular_unit = (float) $catalog->get_regular_price();
			if ( $regular_unit <= 0 ) {
				$regular_unit = (float) $catalog->get_price( 'edit' );
			}
		}
	}

	$save_percent = isset( $tier['save_percent'] ) ? (int) $tier['save_percent'] : 0;
	$tier_label   = isset( $tier['label'] ) ? (string) $tier['label'] : '';
	$unit_fmt     = cc_format_bulk_unit_price_amount( $unit, $product );
	$regular_fmt  = cc_format_bulk_unit_price_amount( $regular_unit, $product );
	$currency     = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'EGP';

	$note = sprintf(
		/* translators: 1: formatted unit price, 2: currency symbol */
		__( 'Bulk price: %1$s %2$s / unit', 'consucorner' ),
		$unit_fmt,
		$currency
	);

	if ( $regular_unit > $unit + 0.00001 ) {
		$note .= ' · ' . sprintf(
			/* translators: 1: formatted regular unit price, 2: currency symbol */
			__( 'was %1$s %2$s', 'consucorner' ),
			$regular_fmt,
			$currency
		);
	}

	return array(
		'enabled'               => true,
		'unit'                  => $unit,
		'unitFormatted'         => $unit_fmt,
		'unitHtml'              => cc_format_bulk_unit_price_html( $unit, $product ),
		'regularUnit'           => $regular_unit,
		'regularUnitFormatted'  => $regular_fmt,
		'regularUnitHtml'       => cc_format_bulk_unit_price_html( $regular_unit, $product ),
		'tierLabel'             => $tier_label,
		'savePercent'           => $save_percent,
		'note'                  => $note,
	);
}

/**
 * Build display payload for an order line that stored bulk pricing meta.
 *
 * @param WC_Order_Item_Product $item Order line item.
 * @return array{
 *   enabled:bool,
 *   unit:float,
 *   unitFormatted:string,
 *   unitHtml:string,
 *   regularUnit:float,
 *   regularUnitFormatted:string,
 *   regularUnitHtml:string,
 *   tierLabel:string,
 *   savePercent:int,
 *   note:string
 * }|null
 */
function cc_get_order_item_bulk_price_display_data( $item ) {
	if ( ! ( $item instanceof WC_Order_Item_Product ) ) {
		return null;
	}

	if ( '1' !== (string) $item->get_meta( '_cc_bulk_exact_display', true ) ) {
		return null;
	}

	$unit = (float) $item->get_meta( '_cc_bulk_unit_price', true );
	if ( $unit <= 0 ) {
		return null;
	}

	$regular_unit = (float) $item->get_meta( '_cc_bulk_regular_unit', true );
	$tier_label   = (string) $item->get_meta( '_cc_bulk_tier_label', true );
	$save_percent = (int) $item->get_meta( '_cc_bulk_save_percent', true );

	$order    = $item->get_order();
	$currency = ( $order instanceof WC_Order ) ? $order->get_currency() : null;
	$product  = $item->get_product();

	// Exact decimals were opted in at checkout time; keep that display even if the
	// product flag is later turned off.
	$decimals         = cc_get_bulk_price_display_decimals( $unit, $product instanceof WC_Product ? $product : null );
	$regular_decimals = $regular_unit > 0
		? cc_get_bulk_price_display_decimals( $regular_unit, $product instanceof WC_Product ? $product : null )
		: $decimals;

	// If the product no longer has the flag (or was deleted), still expand decimals
	// from the stored fractional amount.
	if ( ! ( $product instanceof WC_Product ) || ! cc_bulk_should_show_exact_unit_price( $product ) ) {
		$decimals         = cc_resolve_exact_price_decimals( $unit );
		$regular_decimals = $regular_unit > 0 ? cc_resolve_exact_price_decimals( $regular_unit ) : $decimals;
	}

	$unit_fmt = function_exists( 'cc_format_product_price_amount' )
		? cc_format_product_price_amount( $unit, $decimals )
		: number_format( (float) $unit, $decimals, '.', ',' );

	$regular_fmt = function_exists( 'cc_format_product_price_amount' )
		? cc_format_product_price_amount( $regular_unit, $regular_decimals )
		: number_format( (float) $regular_unit, $regular_decimals, '.', ',' );

	$unit_html = function_exists( 'wc_price' )
		? wc_price( $unit, array( 'decimals' => $decimals, 'currency' => $currency ) )
		: esc_html( $unit_fmt );

	$regular_html = function_exists( 'wc_price' )
		? wc_price( $regular_unit, array( 'decimals' => $regular_decimals, 'currency' => $currency ) )
		: esc_html( $regular_fmt );

	$currency_symbol = $currency
		? ( function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $currency ) : $currency )
		: ( function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'EGP' );

	$note = sprintf(
		/* translators: 1: formatted unit price, 2: currency symbol */
		__( 'Bulk price: %1$s %2$s / unit', 'consucorner' ),
		$unit_fmt,
		wp_strip_all_tags( $currency_symbol )
	);

	if ( $regular_unit > $unit + 0.00001 ) {
		$note .= ' · ' . sprintf(
			/* translators: 1: formatted regular unit price, 2: currency symbol */
			__( 'was %1$s %2$s', 'consucorner' ),
			$regular_fmt,
			wp_strip_all_tags( $currency_symbol )
		);
	}

	return array(
		'enabled'              => true,
		'unit'                 => $unit,
		'unitFormatted'        => $unit_fmt,
		'unitHtml'             => $unit_html,
		'regularUnit'          => $regular_unit,
		'regularUnitFormatted' => $regular_fmt,
		'regularUnitHtml'      => $regular_html,
		'tierLabel'            => $tier_label,
		'savePercent'          => $save_percent,
		'note'                 => $note,
	);
}

/**
 * Resolve display decimals for a known exact amount, independent of product flags.
 *
 * @param float|int|string $price Amount.
 * @return int
 */
function cc_resolve_exact_price_decimals( $price ) {
	$wc_decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 0;
	$amount      = (float) $price;
	$scaled      = round( abs( $amount ) * 100 );
	$cents       = (int) round( $scaled ) % 100;

	if ( 0 === $cents ) {
		return max( 0, $wc_decimals );
	}

	if ( 0 === ( $cents % 10 ) ) {
		return max( 1, $wc_decimals );
	}

	return max( 2, $wc_decimals );
}

/**
 * Persist active bulk pricing explanation onto an order line item at checkout.
 *
 * @param WC_Order_Item_Product $item          Order item being created.
 * @param string                $cart_item_key Cart item key.
 * @param array                 $values        Cart item values.
 * @param WC_Order              $order         Order object.
 * @return void
 */
function cc_store_bulk_price_order_item_meta( $item, $cart_item_key, $values, $order ) {
	unset( $cart_item_key, $order );

	if ( ! ( $item instanceof WC_Order_Item_Product ) || empty( $values['data'] ) || ! ( $values['data'] instanceof WC_Product ) ) {
		return;
	}

	if ( ! empty( $values['cc_bundle_instance'] ) ) {
		return;
	}

	$product = $values['data'];
	if ( ! cc_bulk_should_show_exact_unit_price( $product ) ) {
		return;
	}

	$qty     = isset( $values['quantity'] ) ? absint( $values['quantity'] ) : 0;
	$pricing = function_exists( 'cc_get_effective_unit_price' )
		? cc_get_effective_unit_price( $product, $qty, array( 'require_stock' => false ) )
		: null;

	if ( ! $pricing || 'bulk' !== $pricing['source'] || empty( $pricing['tier'] ) ) {
		return;
	}

	$tier         = $pricing['tier'];
	$unit         = (float) $pricing['price'];
	$regular_unit = isset( $tier['regular_unit'] ) ? (float) $tier['regular_unit'] : (float) $product->get_regular_price();
	if ( $regular_unit <= 0 ) {
		$catalog = wc_get_product( $product->get_id() );
		if ( $catalog instanceof WC_Product ) {
			$regular_unit = (float) $catalog->get_regular_price();
			if ( $regular_unit <= 0 ) {
				$regular_unit = (float) $catalog->get_price( 'edit' );
			}
		}
	}

	$item->add_meta_data( '_cc_bulk_exact_display', '1', true );
	$item->add_meta_data( '_cc_bulk_unit_price', wc_format_decimal( $unit, 6 ), true );
	$item->add_meta_data( '_cc_bulk_regular_unit', wc_format_decimal( $regular_unit, 6 ), true );
	$item->add_meta_data( '_cc_bulk_tier_label', isset( $tier['label'] ) ? (string) $tier['label'] : '', true );
	$item->add_meta_data( '_cc_bulk_save_percent', isset( $tier['save_percent'] ) ? (int) $tier['save_percent'] : 0, true );
}
add_action( 'woocommerce_checkout_create_order_line_item', 'cc_store_bulk_price_order_item_meta', 20, 4 );

/**
 * Stock qty bar fill percentage for offers cards.
 *
 * @param WC_Product $product Product.
 * @return int 0-100
 */
function cc_offers_get_qty_bar_percent( $product ) {
	if ( ! $product instanceof WC_Product || ! $product->managing_stock() ) {
		return 0;
	}

	$qty = $product->get_stock_quantity();
	if ( null === $qty || $qty <= 0 ) {
		return 0;
	}

	return (int) min( 100, max( 8, ( $qty / 20 ) * 100 ) );
}

