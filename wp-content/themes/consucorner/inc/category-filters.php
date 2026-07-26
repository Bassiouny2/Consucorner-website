<?php

/**
 * Category Archive — Dynamic Attribute Filters
 *
 * Powers the WooCommerce product category archive page (woocommerce/archive-product.php).
 * Reads `pa_*` global attributes + `product_brand` taxonomy attached to products in the
 * current category and renders one filter group per attribute (label + checkbox terms).
 *
 * Also provides the AJAX endpoint that re-runs the filter query and returns rendered cards.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/* =====================================================================
   Stock ordering — in-stock & on-backorder first, out-of-stock last
   Applies to WP_Query executions wrapped with the filter helpers below.
   ===================================================================== */

/**
 * Registers SQL modifiers so `_stock_status` controls sort priority:
 * instock (+ unknown/empty treated as saleable first) → onbackorder → outofstock last.
 *
 * Wrap `new WP_Query` calls between cc_begin_product_stock_order() and
 * cc_end_product_stock_order().
 */
function cc_begin_product_stock_order()
{
	add_filter('posts_clauses', 'cc_product_query_posts_clauses_stock_priority', 5, 2);
}

/** @see cc_begin_product_stock_order() */
function cc_end_product_stock_order()
{
	remove_filter('posts_clauses', 'cc_product_query_posts_clauses_stock_priority', 5);
}

/**
 * @param array $clauses WP_Query clauses.
 */
function cc_product_query_posts_clauses_stock_priority($clauses, $_wp_query)
{
	global $wpdb;
	if (strpos($clauses['join'], 'cc_pm_stock_priority') !== false) {
		return $clauses;
	}

	$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS cc_pm_stock_priority ON (
		{$wpdb->posts}.ID = cc_pm_stock_priority.post_id
		AND cc_pm_stock_priority.meta_key = '_stock_status'
	)";

	/*
	 * WordPress builds ORDER BY from orderby + order; we must prepend our CASE to the
	 * existing orderby string (which already includes menu_order, title, meta_value, etc.).
	 * 0 = in stock / presumed available, 1 = on backorder, 2 = out of stock — ASC keeps OOS last.
	 */
	$stock_order = '( CASE
		WHEN cc_pm_stock_priority.meta_value = \'\' OR cc_pm_stock_priority.meta_value IS NULL THEN 0
		WHEN cc_pm_stock_priority.meta_value = \'instock\' THEN 0
		WHEN cc_pm_stock_priority.meta_value = \'onbackorder\' THEN 1
		WHEN cc_pm_stock_priority.meta_value = \'outofstock\' THEN 2
		ELSE 0
	END ) ASC';

	if (! empty($clauses['orderby'])) {
		$clauses['orderby'] = $stock_order . ', ' . $clauses['orderby'];
	} else {
		$clauses['orderby'] = $stock_order;
	}

	return $clauses;
}

/**
 * Format a product price amount for theme display (amount only, no currency).
 *
 * Uses WooCommerce currency settings for decimals and separators when
 * $decimals is omitted (null). Defaults match typical EGP formatting: 28,000.50.
 *
 * @param float|int|string $price    Raw amount.
 * @param int|null         $decimals Decimal places; null = wc_get_price_decimals().
 * @return string
 */
if (! function_exists('cc_format_product_price_amount')) {
	function cc_format_product_price_amount($price, $decimals = null) {
		if (null === $decimals) {
			$decimals = function_exists('wc_get_price_decimals') ? (int) wc_get_price_decimals() : 2;
		} else {
			$decimals = max(0, (int) $decimals);
		}

		$decimal_sep  = function_exists('wc_get_price_decimal_separator') ? wc_get_price_decimal_separator() : '.';
		$thousand_sep = function_exists('wc_get_price_thousand_separator') ? wc_get_price_thousand_separator() : ',';

		return number_format((float) $price, $decimals, $decimal_sep, $thousand_sep);
	}
}

/**
 * Build a shop URL pre-filtered by a taxonomy term slug.
 *
 * Example: /shop/?product_brand=stille or /shop/?country_of_origin=germany
 *
 * @param string $taxonomy  Taxonomy slug.
 * @param string $term_slug Term slug.
 * @return string Empty string when the taxonomy cannot be filtered on the shop.
 */
if (! function_exists('cc_build_shop_filter_url')) {
	function cc_build_shop_filter_url($taxonomy, $term_slug)
	{
		$taxonomy  = sanitize_key($taxonomy);
		$term_slug = sanitize_title($term_slug);

		if ('' === $taxonomy || '' === $term_slug || ! taxonomy_exists($taxonomy)) {
			return '';
		}

		if (function_exists('cc_get_filterable_taxonomies')) {
			if (! in_array($taxonomy, cc_get_filterable_taxonomies(), true)) {
				return '';
			}
		}

		$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
		if (! $shop_url) {
			return '';
		}

		return add_query_arg($taxonomy, $term_slug, $shop_url);
	}
}

/**
 * Build an archive URL pre-filtered by a taxonomy term slug.
 *
 * Keeps origin links on the current product_cat / specialty archive instead of
 * sending users back to the main shop.
 *
 * @param WP_Term|null $archive_term Current archive term.
 * @param string       $taxonomy     Filter taxonomy slug.
 * @param string       $term_slug    Filter term slug.
 * @return string Empty string when no safe URL can be built.
 */
if (! function_exists('cc_build_archive_filter_url')) {
	function cc_build_archive_filter_url($archive_term, $taxonomy, $term_slug)
	{
		$taxonomy  = sanitize_key($taxonomy);
		$term_slug = sanitize_title($term_slug);

		if ('' === $taxonomy || '' === $term_slug || ! taxonomy_exists($taxonomy)) {
			return '';
		}

		if (function_exists('cc_get_filterable_taxonomies') && ! in_array($taxonomy, cc_get_filterable_taxonomies(), true)) {
			return '';
		}

		$base_url = '';
		if ($archive_term instanceof WP_Term) {
			$term_link = get_term_link($archive_term);
			if (! is_wp_error($term_link)) {
				$base_url = (string) $term_link;
			}
		}

		if ('' === $base_url && function_exists('cc_build_shop_filter_url')) {
			return cc_build_shop_filter_url($taxonomy, $term_slug);
		}

		return '' !== $base_url ? add_query_arg($taxonomy, $term_slug, $base_url) : '';
	}
}

/**
 * Build a shareable shop campaign URL from taxonomy slugs (specialty excluded).
 *
 * @param array<string,string[]> $filter_slugs Taxonomy => slug list.
 * @param array{min?:float,max?:float,sort?:string} $price_args Optional price/sort query args.
 * @return string
 */
if (! function_exists('cc_build_shop_campaign_filter_url')) {
	/**
	 * @param array<string,string[]>                         $filter_slugs Taxonomy => slug list (specialty excluded).
	 * @param array{min?:float,max?:float,sort?:string}      $price_args   Optional price/sort query args.
	 * @param string                                         $base_url     Shop permalink or specialty archive URL.
	 */
	function cc_build_shop_campaign_filter_url(array $filter_slugs, array $price_args = array(), $base_url = '')
	{
		if ('' === $base_url) {
			$base_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
		}
		$base_url = esc_url_raw((string) $base_url);
		if (! $base_url) {
			return '';
		}

		$query = array();

		foreach ($filter_slugs as $taxonomy => $slugs) {
			$taxonomy = sanitize_key((string) $taxonomy);
			if ('specialty' === $taxonomy || '' === $taxonomy || ! taxonomy_exists($taxonomy)) {
				continue;
			}

			if (function_exists('cc_get_filterable_taxonomies') && ! in_array($taxonomy, cc_get_filterable_taxonomies(), true)) {
				continue;
			}

			$clean_slugs = array();
			foreach ((array) $slugs as $slug) {
				$slug = sanitize_title((string) $slug);
				if ('' !== $slug) {
					$clean_slugs[] = $slug;
				}
			}

			$clean_slugs = array_values(array_unique($clean_slugs));
			if ($clean_slugs) {
				$query[$taxonomy] = implode(',', $clean_slugs);
			}
		}

		$min_price = isset($price_args['min']) ? (float) $price_args['min'] : 0;
		$max_price = isset($price_args['max']) ? (float) $price_args['max'] : 0;
		$sort      = isset($price_args['sort']) ? sanitize_key((string) $price_args['sort']) : '';

		if ($min_price > 0) {
			$query['min_price'] = (string) $min_price;
		}
		if ($max_price > 0) {
			if (function_exists('cc_clamp_price_filter_max')) {
				$max_price = cc_clamp_price_filter_max($max_price);
			}
			$query['max_price'] = (string) $max_price;
		}
		if ('' !== $sort && 'default' !== $sort) {
			$query['sort'] = $sort;
		}

		return $query ? add_query_arg($query, $base_url) : $base_url;
	}
}

/**
 * Count published products matching a shop campaign filter URL (specialty excluded).
 *
 * @param array<string,string[]>                    $filter_slugs      Taxonomy => slug list.
 * @param array{min?:float,max?:float,sort?:string} $price_args        Optional price args.
 * @param array{taxonomy?:string,term_id?:int}      $archive_context   Optional archive scope (e.g. specialty base path).
 * @return int
 */
if (! function_exists('cc_count_shop_campaign_filter_products')) {
	function cc_count_shop_campaign_filter_products(array $filter_slugs, array $price_args = array(), array $archive_context = array())
	{
		$filters = array();

		foreach ($filter_slugs as $taxonomy => $slugs) {
			$taxonomy = sanitize_key((string) $taxonomy);
			if ('specialty' === $taxonomy || '' === $taxonomy || ! taxonomy_exists($taxonomy)) {
				continue;
			}

			$ids = array();
			foreach ((array) $slugs as $slug) {
				$term = function_exists('cc_resolve_url_filter_term')
					? cc_resolve_url_filter_term((string) $slug, $taxonomy)
					: get_term_by('slug', sanitize_title((string) $slug), $taxonomy);
				if ($term instanceof WP_Term) {
					$ids[] = (int) $term->term_id;
				}
			}

			$ids = array_values(array_unique(array_filter($ids)));
			if ($ids) {
				$filters[$taxonomy] = $ids;
			}
		}

		$archive_tax = isset($archive_context['taxonomy']) ? sanitize_key((string) $archive_context['taxonomy']) : '';
		$archive_id  = isset($archive_context['term_id']) ? absint($archive_context['term_id']) : 0;

		if (empty($filters) && empty($price_args['min']) && empty($price_args['max']) && ! $archive_id) {
			$counts = wp_count_posts('product');
			return isset($counts->publish) ? (int) $counts->publish : 0;
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'tax_query'      => array('relation' => 'AND'),
			'meta_query'     => array(),
		);

		if ($archive_id > 0 && '' !== $archive_tax && taxonomy_exists($archive_tax)) {
			$args['tax_query'][] = array(
				'taxonomy'         => $archive_tax,
				'field'            => 'term_id',
				'terms'            => $archive_id,
				'include_children' => true,
			);
		}

		foreach ($filters as $tax => $term_ids) {
			if ('product_cat' === $tax && function_exists('cc_split_product_cat_filter_ids')) {
				$split = cc_split_product_cat_filter_ids($term_ids);
				if (! empty($split['top'])) {
					$args['tax_query'][] = array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => $split['top'],
						'operator'         => 'IN',
						'include_children' => true,
					);
				}
				if (! empty($split['child'])) {
					$args['tax_query'][] = array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => $split['child'],
						'operator'         => 'IN',
						'include_children' => true,
					);
				}
				continue;
			}

			$args['tax_query'][] = array(
				'taxonomy' => $tax,
				'field'    => 'term_id',
				'terms'    => $term_ids,
				'operator' => 'IN',
			);
		}

		$min_price = isset($price_args['min']) ? (float) $price_args['min'] : 0;
		$max_price = isset($price_args['max']) ? (float) $price_args['max'] : 0;
		if ($max_price > 0 && function_exists('cc_clamp_price_filter_max')) {
			$max_price = cc_clamp_price_filter_max($max_price);
		}

		if ($min_price > 0 || $max_price > 0) {
			$args['meta_query'][] = array(
				'key'     => '_price',
				'value'   => array($min_price > 0 ? $min_price : 0, $max_price > 0 ? $max_price : PHP_FLOAT_MAX),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			);
		}

		if (function_exists('cc_price_filter_exclude_quote_meta_query')) {
			$args['meta_query'][] = cc_price_filter_exclude_quote_meta_query();
		}

		if (function_exists('cc_begin_product_stock_order')) {
			cc_begin_product_stock_order();
		}

		$query = new WP_Query($args);

		if (function_exists('cc_end_product_stock_order')) {
			cc_end_product_stock_order();
		}

		return (int) $query->found_posts;
	}
}

/**
 * Return adaptive Country of Origin data for an archive promo banner.
 *
 * @param int[]        $product_ids  Published product IDs in the archive scope.
 * @param WP_Term|null $archive_term Current archive term for in-archive filter links.
 * @return array{total:int,visible:array<int,array<string,mixed>>,mode:string,hidden_count:int,names:string}
 */
if (! function_exists('cc_get_archive_country_origins')) {
	function cc_get_archive_country_origins(array $product_ids, $archive_term = null)
	{
		global $wpdb;

		$out = array(
			'total'        => 0,
			'visible'      => array(),
			'all'          => array(),
			'mode'         => 'none',
			'hidden_count' => 0,
		);

		$taxonomy    = function_exists('consucorner_country_origin_taxonomy') ? consucorner_country_origin_taxonomy() : 'country_of_origin';
		$product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));

		if (! $taxonomy || ! taxonomy_exists($taxonomy) || empty($product_ids)) {
			return $out;
		}

		$ids_csv = implode(',', $product_ids);
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug, COUNT(DISTINCT tr.object_id) AS product_count
				 FROM {$wpdb->term_relationships} tr
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				 INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				 WHERE tt.taxonomy = %s
				   AND tr.object_id IN ({$ids_csv})
				 GROUP BY t.term_id, t.name, t.slug
				 ORDER BY product_count DESC, t.name ASC",
				$taxonomy
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if (empty($rows)) {
			return $out;
		}

		$items = array();
		foreach ($rows as $row) {
			$term = new WP_Term((object) array(
				'term_id'  => (int) $row->term_id,
				'name'     => (string) $row->name,
				'slug'     => (string) $row->slug,
				'taxonomy' => $taxonomy,
			));

			$image_url = function_exists('cc_get_country_origin_term_image_url')
				? cc_get_country_origin_term_image_url($term, 'thumbnail')
				: '';

			$items[] = array(
				'term_id'       => (int) $row->term_id,
				'name'          => (string) $row->name,
				'slug'          => (string) $row->slug,
				'product_count' => (int) $row->product_count,
				'image_url'     => $image_url,
				'filter_url'    => cc_build_archive_filter_url($archive_term, $taxonomy, (string) $row->slug),
			);
		}

		$total = count($items);
		$mode  = 'single';
		if ($total > 4) {
			$mode = 'overflow';
		} elseif ($total > 1) {
			$mode = 'cluster';
		}

		$visible = 'overflow' === $mode ? array_slice($items, 0, 3) : $items;

		$out['total']        = $total;
		$out['visible']      = $visible;
		$out['all']          = $items;
		$out['mode']         = $mode;
		$out['hidden_count'] = max(0, $total - count($visible));

		return $out;
	}
}

/**
 * Brand name + logo URL from product_brand taxonomy.
 *
 * @param int $product_id Product ID.
 * @return array{name:string,logo_url:string,term_id:int,slug:string,taxonomy:string,shop_url:string}
 */
if (! function_exists('cc_get_product_brand_info')) {
	function cc_get_product_brand_info($product_id) {
		$info = array(
			'name'     => '',
			'logo_url' => '',
			'term_id'  => 0,
			'slug'     => '',
			'taxonomy' => '',
			'shop_url' => '',
		);

		$product_id = (int) $product_id;
		if ($product_id <= 0 || ! taxonomy_exists('product_brand')) {
			return $info;
		}

		$brand_terms = get_the_terms($product_id, 'product_brand');
		if (is_wp_error($brand_terms) || empty($brand_terms)) {
			return $info;
		}

		$term = $brand_terms[0];
		$info['name']     = $term->name;
		$info['term_id']  = (int) $term->term_id;
		$info['slug']     = $term->slug;
		$info['taxonomy'] = 'product_brand';

		if (function_exists('cc_get_filter_term_image_url')) {
			$info['logo_url'] = cc_get_filter_term_image_url($term);
		}

		if (in_array(strtolower(trim($info['name'])), array('brand name', 'brand'), true)) {
			$info['name']     = '';
			$info['logo_url'] = '';
			$info['term_id']  = 0;
			$info['slug']     = '';
			$info['taxonomy'] = '';
			return $info;
		}

		if (function_exists('cc_build_shop_filter_url')) {
			$info['shop_url'] = cc_build_shop_filter_url('product_brand', $term->slug);
		}

		return $info;
	}
}

/**
 * Return a shop filter URL for the product's brand, when resolvable to a filterable term.
 *
 * @param int $product_id Product ID.
 * @return string
 */
if (! function_exists('cc_get_product_brand_shop_url')) {
	function cc_get_product_brand_shop_url($product_id)
	{
		$product_id = (int) $product_id;
		if ($product_id <= 0) {
			return '';
		}

		$brand_info = cc_get_product_brand_info($product_id);
		if (! empty($brand_info['shop_url'])) {
			return $brand_info['shop_url'];
		}

		$product = wc_get_product($product_id);
		if (! $product) {
			return '';
		}

		$country_slugs = function_exists('consucorner_country_origin_legacy_taxonomies')
			? consucorner_country_origin_legacy_taxonomies()
			: array('pa_country-of-origin', 'pa_country_of_origin', 'pa_country', 'pa_origin');

		$brand_slugs = array(
			'pa_brand',
			'pa_brands',
			'pa_brand-name',
			'pa_brand_name',
			'pa_manufacturer',
			'pa_product-brand',
			'pa_product_brand',
		);

		foreach ($product->get_attributes() as $attr_slug => $attribute) {
			if ($attribute->get_variation()) {
				continue;
			}

			$slug  = strtolower($attr_slug);
			$label = strtolower(wc_attribute_label($attribute->get_name(), $product));

			if (in_array($slug, $country_slugs, true)) {
				continue;
			}

			$is_brand = in_array($slug, $brand_slugs, true)
				|| (strpos($label, 'brand') !== false && strpos($label, 'country') === false)
				|| strpos($label, 'manufacturer') !== false
				|| (strpos($slug, 'brand') !== false && strpos($slug, 'country') === false);

			if (! $is_brand || ! $attribute->is_taxonomy()) {
				continue;
			}

			$terms = wc_get_product_terms($product_id, $attribute->get_name(), array('orderby' => 'term_id'));
			if (empty($terms) || is_wp_error($terms)) {
				continue;
			}

			$shop_url = cc_build_shop_filter_url($attribute->get_name(), $terms[0]->slug);
			if ($shop_url) {
				return $shop_url;
			}
		}

		$fallback_taxonomies = array('pwb-brand', 'yith_product_brand', 'berocket_brand');
		foreach ($fallback_taxonomies as $taxonomy) {
			if (! taxonomy_exists($taxonomy)) {
				continue;
			}

			$terms = get_the_terms($product_id, $taxonomy);
			if (is_wp_error($terms) || empty($terms)) {
				continue;
			}

			$shop_url = cc_build_shop_filter_url($taxonomy, $terms[0]->slug);
			if ($shop_url) {
				return $shop_url;
			}
		}

		return '';
	}
}

/* =====================================================================
   1.  PRODUCT CARD RENDERER
   ---------------------------------------------------------------------
   Outputs a single product card matching the design system used across
   the site (.card-shop class, used in browse-specialty / archive grid).
   ===================================================================== */

if (! function_exists('cc_render_product_card')) {
	function cc_render_product_card($product, $args = array())
	{
		$args = is_array($args) ? $args : array();
		$show_qty_bar = ! empty($args['show_qty_bar']);
		// Offer Deal is site-wide (like bulk): shown whenever a product has an
		// active bundle deal. Callers may still pass show_offer_deal => false to opt out.
		$show_offer_deal = ! array_key_exists('show_offer_deal', $args) || ! empty($args['show_offer_deal']);

		if (! ($product instanceof WC_Product)) {
			$product = wc_get_product($product);
		}
		if (! $product instanceof WC_Product) {
			return '';
		}

		$theme_uri       = get_template_directory_uri();
		$placeholder_img = function_exists('consucorner_get_product_placeholder_image_url')
			? consucorner_get_product_placeholder_image_url()
			: $theme_uri . '/assets/images/' . rawurlencode('consucorner icon-logo.jpg');
		$save_icon       = $theme_uri . '/assets/images/save-product-icon.svg';
		$view_icon       = $theme_uri . '/assets/images/Show-icon.svg';

		$pid       = $product->get_id();
		$name      = $product->get_name();
		$link      = get_permalink($pid);
		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : $placeholder_img;
		$price     = (float) $product->get_price();
		$is_quote_product = cc_is_quote_product($product);
		$currency  = get_woocommerce_currency();
		if ($product->is_type('variable')) {
			$price_display = trim(wp_strip_all_tags($product->get_price_html()));
		} else {
			$price_display = cc_format_product_price_amount($price);
		}
		$regular_price = (float) $product->get_regular_price();
		$sale_price    = (float) $product->get_sale_price();
		$has_sale      = $product->is_on_sale() && $regular_price > 0 && $sale_price > 0 && $sale_price < $regular_price;
		$discount      = $has_sale ? max(1, (int) round((($regular_price - $sale_price) / $regular_price) * 100)) : 0;
		$offer_deal    = ($show_offer_deal && function_exists('cc_offers_get_product_deal'))
			? cc_offers_get_product_deal($product, array('require_stock' => true))
			: null;

		// Bulk pricing is site-wide: shown as a compact badge on every card (grid + list).
		$bulk_tiers      = function_exists('cc_get_product_bulk_tiers')
			? cc_get_product_bulk_tiers($product, array('require_stock' => true))
			: array();

		// Bulk-only products (no Offer Deal) can't be added as a single piece —
		// the card's default "Add to cart" quantity must satisfy the minimum tier.
		$bulk_min_qty  = ($bulk_tiers && ! $offer_deal && function_exists('cc_get_product_bulk_min_qty'))
			? cc_get_product_bulk_min_qty($product)
			: 0;
		$card_cart_qty = $offer_deal ? (int) $offer_deal['qty'] : max(1, $bulk_min_qty);
		$bulk_from_price = 0.0;
		$bulk_best_save  = 0;
		foreach ($bulk_tiers as $bulk_tier) {
			$tier_price = (float) $bulk_tier['price'];
			if ($bulk_from_price <= 0 || $tier_price < $bulk_from_price) {
				$bulk_from_price = $tier_price;
			}
			$bulk_best_save = max($bulk_best_save, (int) $bulk_tier['save_percent']);
		}

		$brand_info     = cc_get_product_brand_info($pid);
		$brand_label    = $brand_info['name'];
		$brand_logo_url = $brand_info['logo_url'];

		$country_label = '';
		$country_logo_url = '';
		if (function_exists('cc_get_product_country_origin_info')) {
			$country_info = cc_get_product_country_origin_info($pid);
			$country_label = isset($country_info['name']) ? $country_info['name'] : '';
			$country_logo_url = isset($country_info['image_url']) ? $country_info['image_url'] : '';
		}

		$context_labels = array();
		$specialty_terms = get_the_terms($pid, 'specialty');
		if (! is_wp_error($specialty_terms) && ! empty($specialty_terms)) {
			foreach ($specialty_terms as $term) {
				$context_labels[] = $term->name;
			}
		}
		$category_terms = get_the_terms($pid, 'product_cat');
		if (! is_wp_error($category_terms) && ! empty($category_terms)) {
			foreach ($category_terms as $term) {
				if ('uncategorized' === $term->slug) {
					continue;
				}
				$context_labels[] = $term->name;
			}
		}
		$context_labels = array_values(array_unique(array_filter(array_map('trim', $context_labels))));
		$used_in_label  = implode(', ', $context_labels);

		$in_stock        = $product->is_in_stock();
		$is_purchasable  = $product->is_purchasable();
		$product_type    = $product->get_type();
		$add_to_cart_url = $product->add_to_cart_url();
		$is_simple    = 'simple' === $product_type;
		$is_external  = 'external' === $product_type;
		$is_variable  = 'variable' === $product_type;
		$is_grouped   = 'grouped' === $product_type;
		// AJAX add-to-cart only works for purchasable, in-stock simple
		// products that are not sold individually (WC restriction).
		$can_ajax     = $in_stock
			&& $is_purchasable
			&& $is_simple
			&& ! $product->is_sold_individually();
		// Derive a clear, user-friendly button label per product type.
		// We intentionally avoid `WC_Product::add_to_cart_text()` because
		// WooCommerce falls back to "Read more" whenever a filter (e.g.
		// Dokan's Catalog Mode for guests) forces `is_purchasable()` to
		// false — even though our store still treats the product as
		// in-stock and buyable.
		if (! $in_stock) {
			$add_to_cart_text = __('Out of stock', 'consucorner');
		} elseif ($is_variable) {
			$add_to_cart_text = __('Select options', 'consucorner');
		} elseif ($is_grouped) {
			$add_to_cart_text = __('View products', 'consucorner');
		} elseif ($is_external) {
			$ext_text = $product->single_add_to_cart_text();
			$add_to_cart_text = $ext_text ? $ext_text : __('Buy product', 'consucorner');
		} else {
			$add_to_cart_text = __('Add to cart', 'consucorner');
		}

		if ($offer_deal && $can_ajax) {
			$add_to_cart_text = sprintf(
				/* translators: 1: bundle quantity, 2: formatted bundle total with currency */
				__('Add %1$d for %2$s', 'consucorner'),
				(int) $offer_deal['qty'],
				cc_format_product_price_amount((float) $offer_deal['total']) . ' ' . $currency
			);
		} elseif ($bulk_min_qty > 0 && $can_ajax) {
			// Bulk-only product with no Offer Deal: the card must advertise the
			// minimum order quantity, since single-piece purchases are blocked.
			// Use the price actually charged at that exact quantity (not the
			// store-wide cheapest tier, which may require a higher quantity).
			$bulk_min_tier = function_exists('cc_find_bulk_tier_for_qty') ? cc_find_bulk_tier_for_qty($bulk_tiers, $bulk_min_qty) : null;
			if ($bulk_min_tier) {
				$add_to_cart_text = sprintf(
					/* translators: 1: minimum bulk quantity, 2: formatted per-unit price with currency */
					__('Add %1$d for %2$s each', 'consucorner'),
					$bulk_min_qty,
					cc_format_product_price_amount((float) $bulk_min_tier['price']) . ' ' . $currency
				);
			}
		}

		ob_start();
		$card_classes = 'card-shop' . ($is_quote_product ? ' card-shop--quote' : '');
?>
		<article class="<?php echo esc_attr($card_classes); ?>" data-href="<?php echo esc_url($link); ?>" tabindex="0" style="cursor:pointer"<?php
		if (function_exists('cc_gtm_print_product_data_attributes')) {
			cc_gtm_print_product_data_attributes($product);
		} else {
			echo ' data-product-id="' . esc_attr($pid) . '"';
		}
		?>>
			<div class="card-shop-img-wrapper">
				<a href="<?php echo esc_url($link); ?>" onclick="event.stopPropagation()">
					<img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($name); ?>" loading="lazy" onerror="this.onerror=null;this.src='<?php echo esc_js($placeholder_img); ?>';" />
				</a>
				<?php if ($brand_label && $brand_logo_url) : ?>
					<span class="fp-card-company-badge fp-card-company-badge--logo">
						<img src="<?php echo esc_url($brand_logo_url); ?>" alt="<?php echo esc_attr($brand_label); ?>" loading="lazy" />
					</span>
				<?php elseif ($brand_label) : ?>
					<span class="fp-card-company-badge"><?php echo esc_html($brand_label); ?></span>
				<?php endif; ?>
				<?php if (! $in_stock) : ?>
					<span class="ap-out-of-stock-badge"><?php esc_html_e('Out of stock', 'consucorner'); ?></span>
				<?php endif; ?>
				<div class="card-image-top-meta">
					<span class="card-stock-status card-stock-status--grid <?php echo $in_stock ? 'card-stock-status--in' : 'card-stock-status--out'; ?>" data-stock="<?php echo esc_attr($in_stock ? 'in' : 'out'); ?>">
						<span class="card-stock-dot" aria-hidden="true"></span>
						<?php echo esc_html($in_stock ? __('In stock', 'consucorner') : __('Out of stock', 'consucorner')); ?>
					</span>
					<a href="#" class="card-wish-icon" data-product-id="<?php echo esc_attr($pid); ?>" onclick="event.stopPropagation();event.preventDefault()" aria-label="<?php esc_attr_e('Save', 'consucorner'); ?>" aria-pressed="false">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
						</svg>
					</a>
				</div>
			</div>
			<div class="card-shop-body">
				<span class="card-stock-status card-stock-status--list <?php echo $in_stock ? 'card-stock-status--in' : 'card-stock-status--out'; ?>" data-stock="<?php echo esc_attr($in_stock ? 'in' : 'out'); ?>">
					<span class="card-stock-dot" aria-hidden="true"></span>
					<?php echo esc_html($in_stock ? __('In stock', 'consucorner') : __('Out of stock', 'consucorner')); ?>
				</span>
				<h3 class="product-card-title"><a class="product-card-title-link" href="<?php echo esc_url($link); ?>" onclick="event.stopPropagation()"><?php echo esc_html($name); ?></a></h3>
				<?php if ($offer_deal) : ?>
					<div class="cc-offer-deal">
						<p class="cc-offer-deal__qty">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: bundle quantity */
									_n('%d pc bundle', '%d pcs bundle', (int) $offer_deal['qty'], 'consucorner'),
									(int) $offer_deal['qty']
								)
							);
							?>
						</p>
						<div class="cc-offer-deal__prices">
							<span class="cc-offer-deal__regular">
								<?php echo esc_html(cc_format_product_price_amount((float) $offer_deal['regular_total']) . ' ' . $currency); ?>
							</span>
							<span class="cc-offer-deal__price">
								<?php echo esc_html(cc_format_product_price_amount((float) $offer_deal['total']) . ' ' . $currency); ?>
							</span>
							<span class="cc-offer-deal__save">
								<?php echo esc_html(sprintf(__('Saved %d%%', 'consucorner'), (int) $offer_deal['save_percent'])); ?>
							</span>
						</div>
					</div>
				<?php elseif (! $is_quote_product && $has_sale) : ?>
					<div class="card-sale-meta">
						<span class="card-regular-price"><?php echo esc_html(cc_format_product_price_amount($regular_price)); ?> <?php echo esc_html($currency); ?></span>
						<span class="card-sale-badge"><?php echo esc_html(sprintf(__('Saved %d%%', 'consucorner'), $discount)); ?></span>
					</div>
				<?php endif; ?>
				<?php if ($is_quote_product && ! $offer_deal) : ?>
					<div class="card-quote-price">
						<p class="card-quote-price__text"><?php esc_html_e('Price on request', 'consucorner'); ?></p>
					</div>
				<?php elseif (! $is_quote_product && ! $offer_deal) : ?>
					<div class="priceing">
						<?php if ($product->is_type('variable')) : ?>
							<p class="price price--variable"><?php echo wp_kses_post($product->get_price_html()); ?></p>
						<?php else : ?>
							<p class="price"><?php echo esc_html($price_display); ?></p>
							<p class="currency"><?php echo esc_html($currency); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if (! $is_quote_product && ! $offer_deal && $bulk_from_price > 0) : ?>
					<div class="cc-bulk-badge">
						<span class="cc-bulk-badge__icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M20 7h-3V5a3 3 0 0 0-3-3H10a3 3 0 0 0-3 3v2H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zM9 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2H9z" />
							</svg>
						</span>
						<span class="cc-bulk-badge__text">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: lowest bulk unit price with currency */
									__('Bulk pricing from %s', 'consucorner'),
									cc_format_product_price_amount($bulk_from_price) . ' ' . $currency
								)
							);
							?>
						</span>
						<?php if ($bulk_best_save > 0) : ?>
							<span class="cc-bulk-badge__save"><?php echo esc_html(sprintf(__('Up to %d%%', 'consucorner'), $bulk_best_save)); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if ($country_label || $brand_label) : ?>
					<div class="card-meta-pills">
						<?php if ($country_label) : ?>
							<span class="card-pill card-pill--country">
								<span class="card-pill-icon card-pill-icon--country" aria-hidden="true">
									<?php if ($country_logo_url) : ?>
										<img src="<?php echo esc_url($country_logo_url); ?>" alt="" loading="lazy" />
									<?php else : ?>
										<?php echo esc_html(mb_strtoupper(mb_substr($country_label, 0, 1))); ?>
									<?php endif; ?>
								</span>
								<span class="card-pill-text"><?php echo esc_html($country_label); ?></span>
							</span>
						<?php endif; ?>
						<?php if ($brand_label) : ?>
							<span class="card-pill card-pill--brand">
								<span class="card-pill-icon card-pill-icon--brand" aria-hidden="true">
									<?php if ($brand_logo_url) : ?>
										<img src="<?php echo esc_url($brand_logo_url); ?>" alt="" loading="lazy" />
									<?php else : ?>
										<?php echo esc_html(mb_strtoupper(mb_substr($brand_label, 0, 1))); ?>
									<?php endif; ?>
								</span>
								<span class="card-pill-text"><?php echo esc_html($brand_label); ?></span>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if ($used_in_label) : ?>
					<p class="card-used-in"><?php echo esc_html($used_in_label); ?></p>
				<?php endif; ?>
				<?php
				if ($show_qty_bar && $in_stock && $product->managing_stock()) {
					$offer_qty = $product->get_stock_quantity();
					if (null !== $offer_qty && $offer_qty > 0) {
						$offer_qty_int     = (int) $offer_qty;
						$offer_qty_percent = function_exists('cc_offers_get_qty_bar_percent')
							? cc_offers_get_qty_bar_percent($product)
							: (int) min(100, max(8, ($offer_qty_int / 20) * 100));
						?>
						<div class="cc-offer-qty-bar">
							<div class="cc-offer-qty-bar__top">
								<span><?php esc_html_e('Quantity left', 'consucorner'); ?></span>
								<span class="cc-offer-qty-bar__number">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: stock quantity */
											_n('%d pc', '%d pcs', $offer_qty_int, 'consucorner'),
											$offer_qty_int
										)
									);
									?>
								</span>
							</div>
							<div class="cc-offer-qty-bar__track" aria-hidden="true">
								<div class="cc-offer-qty-bar__fill" style="width: <?php echo esc_attr((string) $offer_qty_percent); ?>%;"></div>
							</div>
						</div>
						<?php
					}
				}
				?>
				<div class="product-card-btn">
					<div class="product-card-btn-left">
						<?php if ($is_quote_product && $in_stock) : ?>
							<a href="#ccQuoteModal"
								class="btn-quote-card btn-add-cart--quote js-cc-quote-trigger"
								role="button"
								data-product-id="<?php echo esc_attr($pid); ?>"
								data-product-name="<?php echo esc_attr($name); ?>">
								<span class="btn-add-cart-text"><?php esc_html_e('Get A Quote', 'consucorner'); ?></span>
							</a>
						<?php elseif (! $in_stock) : ?>
							<a href="<?php echo esc_url($link); ?>"
								class="btn-add-cart btn-add-cart--disabled"
								onclick="event.stopPropagation()"
								aria-disabled="true"
								tabindex="-1">
								<span class="btn-add-cart-text"><?php esc_html_e('Out of stock', 'consucorner'); ?></span>
							</a>
						<?php elseif ($can_ajax) : ?>
							<a href="<?php echo esc_url($add_to_cart_url); ?>"
								class="btn-add-cart ajax_add_to_cart add_to_cart_button"
								data-product_id="<?php echo esc_attr($pid); ?>"
								data-product-id="<?php echo esc_attr($pid); ?>"
								data-product-name="<?php echo esc_attr($name); ?>"
								data-product-price="<?php echo esc_attr($price); ?>"
								data-product-image="<?php echo esc_attr($image_url); ?>"
								data-quantity="<?php echo esc_attr((string) $card_cart_qty); ?>"
								<?php function_exists('cc_gtm_print_product_data_attributes') && cc_gtm_print_product_data_attributes($product); ?>
								rel="nofollow"
								onclick="event.stopPropagation()"><svg class="btn-cart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
									<use href="#icon-cart"></use>
								</svg><span class="btn-add-cart-text"><?php echo esc_html($add_to_cart_text); ?></span></a>
						<?php elseif ($is_variable && $in_stock) : ?>
							<?php
							$card_attributes       = $product->get_variation_attributes();
							$variation_threshold   = (int) apply_filters('woocommerce_ajax_variation_threshold', 30, $product);
							$get_card_variations   = count($product->get_children()) <= $variation_threshold;
							$card_variations       = $get_card_variations ? $product->get_available_variations() : false;
							$card_variations_json  = $card_variations ? wp_json_encode($card_variations) : '[]';
							?>
							<div class="card-variation-wrap">
								<button type="button"
									class="btn-add-cart btn-add-cart--variable"
									data-product-id="<?php echo esc_attr($pid); ?>"
									data-product-name="<?php echo esc_attr($name); ?>"
									data-product-image="<?php echo esc_attr($image_url); ?>"
									data-link="<?php echo esc_url($link); ?>"
									aria-expanded="false"
									aria-haspopup="dialog"
									onclick="event.stopPropagation()">
									<svg class="btn-cart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<use href="#icon-cart"></use>
									</svg>
									<span class="btn-add-cart-text"><?php echo esc_html($add_to_cart_text); ?></span>
								</button>
								<div class="card-variation-panel"
									id="card-variation-panel-<?php echo esc_attr($pid); ?>"
									hidden
									aria-hidden="true"
									data-product-id="<?php echo esc_attr($pid); ?>"
									data-product-variations="<?php echo esc_attr($card_variations_json); ?>">
									<div class="card-variation-panel__inner">
										<?php foreach ($card_attributes as $attribute_name => $options) : ?>
											<label class="card-variation-field" for="<?php echo esc_attr(sanitize_title($attribute_name) . '_' . $pid); ?>">
												<span class="card-variation-field__label"><?php echo esc_html(wc_attribute_label($attribute_name, $product)); ?></span>
												<?php
												wc_dropdown_variation_attribute_options(
													array(
														'options'   => $options,
														'attribute' => $attribute_name,
														'product'   => $product,
														'selected'  => $product->get_variation_default_attribute($attribute_name),
														'id'        => sanitize_title($attribute_name) . '_' . $pid,
														'class'     => 'card-variation-select',
													)
												);
												?>
											</label>
										<?php endforeach; ?>
										<div class="card-variation-price is-pending" aria-live="polite"></div>
										<button type="button" class="card-variation-add"<?php echo $card_variations ? '' : ' disabled'; ?>>
											<?php esc_html_e('Add to cart', 'consucorner'); ?>
										</button>
										<a href="<?php echo esc_url($link); ?>"
											class="card-variation-view"
											onclick="event.stopPropagation()">
											<?php esc_html_e('View product', 'consucorner'); ?>
										</a>
									</div>
								</div>
							</div>
						<?php else : ?>
							<?php
							// In stock but not directly add-to-cart-able from the
							// archive (variable / grouped / external / affiliate
							// / subscription, etc.). Send the user to either the
							// external URL or the product page so they can finish
							// the action there. Use WooCommerce's own button text
							// ("Select options", "View products", "Buy product",
							// etc.) so the label is always meaningful.
							$btn_href    = $is_external ? $add_to_cart_url : $link;
							$btn_target  = $is_external ? ' target="_blank" rel="nofollow noopener"' : '';
							?>
							<a href="<?php echo esc_url($btn_href); ?>"
								class="btn-add-cart"
								data-product-id="<?php echo esc_attr($pid); ?>"
								<?php echo $btn_target; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
								?>
								onclick="event.stopPropagation()"><svg class="btn-cart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
									<use href="#icon-cart"></use>
								</svg><span class="btn-add-cart-text"><?php echo esc_html($add_to_cart_text); ?></span></a>
						<?php endif; ?>
						<a href="#" class="btn-save" data-product-id="<?php echo esc_attr($pid); ?>" onclick="event.stopPropagation();event.preventDefault()" aria-label="<?php esc_attr_e('Save', 'consucorner'); ?>" aria-pressed="false">
							<img src="<?php echo esc_url($save_icon); ?>" alt="" />
						</a>
					</div>
					<a href="<?php echo esc_url($link); ?>" class="btn-compare" onclick="event.stopPropagation()" aria-label="<?php esc_attr_e('Quick view', 'consucorner'); ?>">
						<img src="<?php echo esc_url($view_icon); ?>" alt="" />
					</a>
				</div>
			</div>
		</article>
<?php
		return ob_get_clean();
	}
}

/* =====================================================================
   2.  HELPERS — collect attribute filters for a category
   ===================================================================== */

if (! function_exists('cc_get_taxonomy_product_ids')) {
	/**
	 * Get published product IDs that belong to the given taxonomy terms.
	 *
	 * @param string    $taxonomy Taxonomy name.
	 * @param int|int[] $term_ids Term IDs.
	 * @return int[]
	 */
	function cc_get_taxonomy_product_ids($taxonomy, $term_ids)
	{
		$taxonomy = sanitize_key($taxonomy);
		$term_ids = array_filter(array_map('intval', (array) $term_ids));
		if (! $taxonomy || ! taxonomy_exists($taxonomy) || empty($term_ids)) {
			return array();
		}

		$query = new WP_Query(array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array(
				array(
					'taxonomy'         => $taxonomy,
					'field'            => 'term_id',
					'terms'            => $term_ids,
					'include_children' => true,
				),
			),
		));

		return array_map('intval', $query->posts);
	}
}

if (! function_exists('cc_get_category_product_ids')) {
	/**
	 * Get all published product IDs that belong to the given category
	 * (or any of its descendants) — used to derive available filter terms
	 * and the price range.
	 *
	 * @param int|int[] $category_term_ids
	 * @return int[]
	 */
	function cc_get_category_product_ids($category_term_ids)
	{
		return cc_get_taxonomy_product_ids('product_cat', $category_term_ids);
	}
}

if (! function_exists('cc_get_terms_for_product_ids')) {
	/**
	 * Return unique terms assigned to the given published product IDs.
	 *
	 * @param string $taxonomy   Taxonomy name.
	 * @param int[]  $product_ids Product post IDs.
	 * @return WP_Term[]
	 */
	function cc_get_terms_for_product_ids($taxonomy, array $product_ids)
	{
		$taxonomy    = sanitize_key($taxonomy);
		$product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));

		if (! $taxonomy || ! taxonomy_exists($taxonomy) || empty($product_ids)) {
			return array();
		}

		$terms = wp_get_object_terms(
			$product_ids,
			$taxonomy,
			array(
				'orderby' => 'product_cat' === $taxonomy ? 'menu_order' : 'name',
				'order'   => 'ASC',
			)
		);

		if (is_wp_error($terms) || empty($terms)) {
			return array();
		}

		$unique = array();
		foreach ($terms as $term) {
			if ($term instanceof WP_Term) {
				$unique[(int) $term->term_id] = $term;
			}
		}

		$terms = array_values($unique);

		if ('product_cat' === $taxonomy && function_exists('consucorner_sort_terms_by_order')) {
			$terms = consucorner_sort_terms_by_order($terms);
		}

		return $terms;
	}
}

if (! function_exists('cc_get_category_filter_taxonomies')) {
	/**
	 * Returns only the approved shop filters that are not product categories:
	 * "Made in" (Country of Origin taxonomy) and "Brand".
	 *
	 * Each entry: [
	 *   'taxonomy' => 'country_of_origin',
	 *   'label'    => 'Handle Type',
	 *   'terms'    => [ WP_Term, … ]
	 * ]
	 *
	 * @param int[] $product_ids When non-empty, only terms used by these products are returned.
	 * @return array<int, array>
	 */
	function cc_get_category_filter_taxonomies($product_ids = array())
	{
		$out          = array();
		$product_ids  = array_values(array_unique(array_filter(array_map('absint', (array) $product_ids))));
		$scope_by_ids = ! empty($product_ids);

		if ($scope_by_ids && ! function_exists('cc_get_terms_for_product_ids')) {
			return $out;
		}

		$country_taxonomy = function_exists('consucorner_country_origin_taxonomy') ? consucorner_country_origin_taxonomy() : 'country_of_origin';
		if (taxonomy_exists($country_taxonomy)) {
			if ($scope_by_ids) {
				$terms = cc_get_terms_for_product_ids($country_taxonomy, $product_ids);
			} else {
				$terms = get_terms(array(
					'taxonomy'   => $country_taxonomy,
					'hide_empty' => true,
				));
				if (is_wp_error($terms)) {
					$terms = array();
				}
			}

			if (! empty($terms)) {
				$out[] = array(
					'taxonomy' => $country_taxonomy,
					'label'    => __('Made in', 'consucorner'),
					'terms'    => $terms,
				);
			}
		}

		// Brand taxonomy (separate from pa_*).
		if (taxonomy_exists('product_brand')) {
			if ($scope_by_ids) {
				$brand_terms = cc_get_terms_for_product_ids('product_brand', $product_ids);
			} else {
				$brand_terms = get_terms(array(
					'taxonomy'   => 'product_brand',
					'hide_empty' => true,
				));
				if (is_wp_error($brand_terms)) {
					$brand_terms = array();
				}
			}

			if (! empty($brand_terms)) {
				$out[] = array(
					'taxonomy' => 'product_brand',
					'label'    => __('Brand', 'consucorner'),
					'terms'    => $brand_terms,
				);
			}
		}

		return $out;
	}
}

if (! function_exists('cc_get_filter_term_image_url')) {
	/**
	 * Return an optional image/logo URL for a filter term.
	 *
	 * Supports the theme's attribute image meta plus common WooCommerce brand
	 * image meta keys used by brand plugins.
	 *
	 * @param WP_Term $term Term object.
	 * @return string
	 */
	function cc_get_filter_term_image_url($term)
	{
		if (! $term instanceof WP_Term) {
			return '';
		}

		$meta_keys = array(
			'_cc_attribute_image',
			'thumbnail_id',
			'product_brand_thumbnail_id',
			'brand_thumbnail_id',
			'brand_image',
			'image',
		);

		if ('product_cat' === $term->taxonomy) {
			array_unshift($meta_keys, '_cc_product_cat_icon');
		}

		foreach ($meta_keys as $meta_key) {
			$image_id = absint(get_term_meta($term->term_id, $meta_key, true));
			if (! $image_id) {
				continue;
			}

			$image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
			if ($image_url) {
				return $image_url;
			}
		}

		return '';
	}
}

if (! function_exists('cc_get_category_price_range')) {
	/**
	 * Min / max product price across the given product IDs.
	 *
	 * @return array{min:float,max:float}
	 */
	function cc_get_category_price_range($product_ids)
	{
		global $wpdb;
		$out = array('min' => 0.0, 'max' => 0.0);

		if (empty($product_ids)) {
			return $out;
		}

		$ids_csv = implode(',', array_map('intval', $product_ids));
		$ceiling = function_exists('cc_get_price_filter_ceiling') ? (float) cc_get_price_filter_ceiling() : 0;
		$ceiling_sql = $ceiling > 0
			? $wpdb->prepare(' AND CAST(meta_value AS DECIMAL(20,6)) < %f', $ceiling)
			: '';
		$row = $wpdb->get_row(
			"SELECT MIN(CAST(meta_value AS DECIMAL(20,6))) AS min_price,
			        MAX(CAST(meta_value AS DECIMAL(20,6))) AS max_price
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_price' AND post_id IN ({$ids_csv}) AND meta_value <> ''{$ceiling_sql}"
		);

		if ($row) {
			$out['min'] = max(0.0, (float) $row->min_price);
			$out['max'] = max($out['min'], (float) $row->max_price);
		}
		return $out;
	}
}

if (! function_exists('cc_get_price_buckets')) {
	/**
	 * Distribute product prices across N equal-width buckets for a histogram.
	 *
	 * @param  int[]  $product_ids  Published product IDs to measure.
	 * @param  float  $min_price    Left edge of the histogram range.
	 * @param  float  $max_price    Right edge of the histogram range.
	 * @param  int    $num_buckets  Number of equal-width columns.
	 * @return int[]  Count of products per bucket (0-indexed, left → right).
	 */
	function cc_get_price_buckets(array $product_ids, $min_price, $max_price, $num_buckets = 20)
	{
		global $wpdb;
		$buckets = array_fill(0, $num_buckets, 0);
		if (empty($product_ids) || $max_price <= $min_price) {
			return $buckets;
		}
		// $ids_csv is safe: every element has been cast to int.
		$ids_csv = implode(',', array_map('intval', $product_ids));
		$ceiling = function_exists('cc_get_price_filter_ceiling') ? (float) cc_get_price_filter_ceiling() : 0;
		$ceiling_sql = $ceiling > 0
			? $wpdb->prepare(' AND CAST(meta_value AS DECIMAL(20,2)) < %f', $ceiling)
			: '';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$prices  = $wpdb->get_col(
			"SELECT CAST(meta_value AS DECIMAL(20,2))
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_price'
			   AND post_id IN ({$ids_csv})
			   AND meta_value <> ''
			   AND CAST(meta_value AS DECIMAL(20,2)) >= 0{$ceiling_sql}"
		);
		// phpcs:enable
		$range = (float) $max_price - (float) $min_price;
		foreach ($prices as $p) {
			$p = (float) $p;
			if ($p < $min_price || $p > $max_price) {
				continue;
			}
			$idx            = (int) floor((($p - $min_price) / $range) * $num_buckets);
			$idx            = max(0, min($idx, $num_buckets - 1));
			$buckets[$idx]++;
		}
		return $buckets;
	}
}

if (! function_exists('cc_get_subcategories_for_tabs')) {
	/**
	 * Get direct child categories (used for collection-style tabs at top of grid).
	 *
	 * @return WP_Term[]
	 */
	function cc_get_subcategories_for_tabs($parent_term_id)
	{
		$children = get_terms(array(
			'taxonomy'   => 'product_cat',
			'parent'     => (int) $parent_term_id,
			'hide_empty' => true,
			'orderby'    => 'menu_order',
			'order'      => 'ASC',
		));
		if (is_wp_error($children) || empty($children)) {
			return array();
		}

		return function_exists('consucorner_sort_terms_by_order')
			? consucorner_sort_terms_by_order($children)
			: $children;
	}
}

if (! function_exists('cc_get_product_category_term_ids_for_specialties')) {
	/**
	 * Get unique product category IDs attached to products in any selected specialty.
	 *
	 * This powers the main shop's Specialty -> Category dependency without
	 * collapsing multi-select choices to a single specialty.
	 *
	 * @param int[] $specialty_ids Specialty term IDs.
	 * @return int[]
	 */
	function cc_get_product_category_term_ids_for_specialties(array $specialty_ids)
	{
		global $wpdb;

		$specialty_ids = array_values(array_unique(array_filter(array_map('absint', $specialty_ids))));
		if (empty($specialty_ids) || ! taxonomy_exists('specialty')) {
			return array();
		}

		foreach ($specialty_ids as $specialty_id) {
			$children = get_term_children($specialty_id, 'specialty');
			if (! is_wp_error($children) && ! empty($children)) {
				$specialty_ids = array_merge($specialty_ids, array_map('absint', $children));
			}
		}
		$specialty_ids = array_values(array_unique(array_filter($specialty_ids)));

		$placeholders = implode(',', array_fill(0, count($specialty_ids), '%d'));
		$sql = "
			SELECT DISTINCT tt_cat.term_id
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr_spec
				ON tr_spec.object_id = p.ID
			INNER JOIN {$wpdb->term_taxonomy} tt_spec
				ON tt_spec.term_taxonomy_id = tr_spec.term_taxonomy_id
				AND tt_spec.taxonomy = 'specialty'
				AND tt_spec.term_id IN ({$placeholders})
			INNER JOIN {$wpdb->term_relationships} tr_cat
				ON tr_cat.object_id = p.ID
			INNER JOIN {$wpdb->term_taxonomy} tt_cat
				ON tt_cat.term_taxonomy_id = tr_cat.term_taxonomy_id
				AND tt_cat.taxonomy = 'product_cat'
			WHERE p.post_type = 'product'
				AND p.post_status = 'publish'
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholder count is built from sanitized IDs.
		$term_ids = $wpdb->get_col($wpdb->prepare($sql, $specialty_ids));
		return array_values(array_unique(array_map('intval', $term_ids)));
	}
}

if (! function_exists('consucorner_group_filter_terms_by_name')) {
	/**
	 * Resolve the most relevant Specialty label for a product category term.
	 *
	 * Duplicate product_cat names are easier to understand when the child
	 * option says "Specialty > Sub-Category" instead of repeating the product
	 * category parent.
	 *
	 * @param WP_Term $term Product category term.
	 * @return string
	 */
	function consucorner_get_specialty_label_for_product_cat(WP_Term $term)
	{
		static $cache = array();

		$term_id = (int) $term->term_id;
		if (isset($cache[$term_id])) {
			return $cache[$term_id];
		}

		if (! taxonomy_exists('specialty')) {
			$cache[$term_id] = '';
			return '';
		}

		$transient_key = 'cc_filter_cat_specialty_' . $term_id;
		$cached        = get_transient($transient_key);
		if (false !== $cached) {
			$cache[$term_id] = (string) $cached;
			return $cache[$term_id];
		}

		$product_ids = function_exists('cc_get_category_product_ids') ? cc_get_category_product_ids(array($term_id)) : array();
		$specialties = function_exists('cc_get_terms_for_product_ids') ? cc_get_terms_for_product_ids('specialty', $product_ids) : array();
		$label       = '';

		if (! empty($specialties)) {
			$first = reset($specialties);
			if ($first instanceof WP_Term) {
				$label = $first->name;
			}
		}

		set_transient($transient_key, $label, HOUR_IN_SECONDS);
		$cache[$term_id] = $label;

		return $label;
	}

	/**
	 * Group product category filter terms by display name.
	 *
	 * Terms with unique names are returned as single-item groups so existing
	 * markup can remain unchanged. Duplicate names receive a disambiguated
	 * child label, usually "Parent > Child".
	 *
	 * @param WP_Term[] $terms Product category terms.
	 * @return array<int,array{key:string,name:string,is_group:bool,terms:WP_Term[]}>
	 */
	function consucorner_group_filter_terms_by_name(array $terms)
	{
		$by_name      = array();
		$group_order  = array();

		foreach ($terms as $term) {
			if (! $term instanceof WP_Term) {
				continue;
			}

			$key = function_exists('mb_strtolower')
				? mb_strtolower(trim($term->name))
				: strtolower(trim($term->name));

			if ('' === $key) {
				$key = 'term-' . (int) $term->term_id;
			}

			if (! isset($by_name[$key])) {
				$by_name[$key] = array(
					'name'  => $term->name,
					'terms' => array(),
				);
				$group_order[] = $key;
			}

			$by_name[$key]['terms'][] = $term;
		}

		$groups = array();
		foreach ($group_order as $key) {
			if (! isset($by_name[$key])) {
				continue;
			}

			$group = $by_name[$key];
			$is_group = count($group['terms']) > 1;

			if ($is_group) {
				foreach ($group['terms'] as $term) {
					$specialty_label = consucorner_get_specialty_label_for_product_cat($term);

					if ('' !== $specialty_label) {
						$term->_cc_disambig = $specialty_label . ' > ' . $term->name;
					} elseif ((int) $term->parent > 0) {
						$parent = get_term((int) $term->parent, 'product_cat');
						$term->_cc_disambig = (! is_wp_error($parent) && $parent instanceof WP_Term)
							? $parent->name . ' > ' . $term->name
							: $term->name;
					} else {
						$term->_cc_disambig = $term->name . ' (' . $term->slug . ')';
					}
				}
			}

			$groups[] = array(
				'key'      => $key,
				'name'     => $group['name'],
				'is_group' => $is_group,
				'terms'    => $group['terms'],
			);
		}

		return $groups;
	}
}

if (! function_exists('consucorner_render_cat_filter_items')) {
	/**
	 * Render product category filter items, grouping duplicate names.
	 *
	 * @param WP_Term[] $terms Product category terms.
	 * @param array     $opts  Render options.
	 * @return void
	 */
	function consucorner_render_cat_filter_items(array $terms, array $opts = array())
	{
		$show_dots      = ! empty($opts['show_dots']);
		$dot_colors     = ! empty($opts['dot_colors']) && is_array($opts['dot_colors']) ? array_values($opts['dot_colors']) : array();
		$is_subcategory = ! empty($opts['is_subcategory']);
		$groups         = consucorner_group_filter_terms_by_name($terms);
		$dot_index      = 0;

		foreach ($groups as $group) {
			if (empty($group['is_group'])) {
				$term = reset($group['terms']);
				if (! $term instanceof WP_Term) {
					continue;
				}

				$item_classes = array('ap-filter-item');
				?>
				<li class="<?php echo esc_attr(implode(' ', $item_classes)); ?>" data-term-id="<?php echo esc_attr($term->term_id); ?>"<?php echo $is_subcategory ? ' data-cc-parent-term="' . esc_attr((int) $term->parent) . '"' : ''; ?>>
					<label class="ap-filter-label">
						<input type="checkbox"
							class="fp-checkbox"
							data-filter-tax="product_cat"
							data-filter-term="<?php echo esc_attr($term->term_id); ?>"
							value="<?php echo esc_attr($term->slug); ?>" />
						<span class="ap-filter-text"><?php echo esc_html($term->name); ?></span>
					</label>
					<?php if ($show_dots && ! empty($dot_colors)) : ?>
						<span class="ap-filter-dot" style="background:<?php echo esc_attr($dot_colors[$dot_index % count($dot_colors)]); ?>"></span>
					<?php endif; ?>
				</li>
				<?php
				$dot_index++;
				continue;
			}

			$term_ids = array_map('absint', wp_list_pluck($group['terms'], 'term_id'));
			$group_id = sanitize_html_class($group['key']) . '_' . implode('_', $term_ids);
			?>
			<li class="ap-filter-item-group" data-cc-term-group>
				<button type="button" class="ap-filter-group-btn" aria-expanded="false" aria-controls="cc-tg-<?php echo esc_attr($group_id); ?>">
					<span class="ap-filter-text"><?php echo esc_html($group['name']); ?></span>
					<span class="cc-group-sel-count" hidden>0</span>
					<svg class="ap-filter-group-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<polyline points="6 9 12 15 18 9" />
					</svg>
				</button>
				<ul class="ap-filter-group-children" id="cc-tg-<?php echo esc_attr($group_id); ?>" hidden>
					<?php foreach ($group['terms'] as $term) : ?>
						<?php
						if (! $term instanceof WP_Term) {
							continue;
						}
						$item_classes = array('ap-filter-item');
						$label = isset($term->_cc_disambig) ? $term->_cc_disambig : $term->name;
						?>
						<li class="<?php echo esc_attr(implode(' ', $item_classes)); ?>" data-term-id="<?php echo esc_attr($term->term_id); ?>"<?php echo $is_subcategory ? ' data-cc-parent-term="' . esc_attr((int) $term->parent) . '"' : ''; ?>>
							<label class="ap-filter-label">
								<input type="checkbox"
									class="fp-checkbox"
									data-filter-tax="product_cat"
									data-filter-term="<?php echo esc_attr($term->term_id); ?>"
									value="<?php echo esc_attr($term->slug); ?>" />
								<span class="ap-filter-text"><?php echo esc_html($label); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</li>
			<?php
		}
	}
}

if (! function_exists('cc_split_product_cat_filter_ids')) {
	/**
	 * Split selected product_cat filter IDs into top-level categories and child terms.
	 *
	 * The UI exposes Category and Sub-Category as separate panels but both use
	 * product_cat IDs. Keeping them in one IN query broadens results; splitting
	 * lets PHP apply Category AND Sub-Category when both are selected.
	 *
	 * @param int[] $term_ids Selected product_cat term IDs.
	 * @return array{top:int[],child:int[]}
	 */
	function cc_split_product_cat_filter_ids(array $term_ids)
	{
		$out = array(
			'top'   => array(),
			'child' => array(),
		);

		foreach (array_values(array_unique(array_filter(array_map('absint', $term_ids)))) as $term_id) {
			$term = get_term($term_id, 'product_cat');
			if (! $term instanceof WP_Term || is_wp_error($term)) {
				continue;
			}

			if ((int) $term->parent > 0) {
				$out['child'][] = $term_id;
			} else {
				$out['top'][] = $term_id;
			}
		}

		return $out;
	}
}

/* =====================================================================
   3.  AJAX HANDLER — filter products
   ---------------------------------------------------------------------
   Accepts category (term_id) + a list of selected term_ids per taxonomy
   + price min/max + sort + page → returns rendered cards HTML.
   ===================================================================== */

add_action('wp_ajax_consucorner_filter_category_products',        'consucorner_ajax_filter_category_products');
add_action('wp_ajax_nopriv_consucorner_filter_category_products', 'consucorner_ajax_filter_category_products');

function consucorner_ajax_filter_category_products()
{
	check_ajax_referer('consucorner_category_filter', 'nonce');

	// Category ID optional: 0 = main shop archive (no product_cat restriction).
	$category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
	$category_taxonomy = isset($_POST['category_taxonomy']) ? sanitize_key(wp_unslash($_POST['category_taxonomy'])) : 'product_cat';
	if (! taxonomy_exists($category_taxonomy)) {
		$category_taxonomy = 'product_cat';
	}

	$page    = isset($_POST['page'])    ? max(1, absint($_POST['page']))    : 1;
	$per_page = isset($_POST['per_page']) ? max(1, absint($_POST['per_page'])) : 12;
	$sort    = isset($_POST['sort'])    ? sanitize_text_field(wp_unslash($_POST['sort'])) : 'default';
	$search  = isset($_POST['search'])  ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

	$min_price = isset($_POST['min_price']) ? (float) wp_unslash($_POST['min_price']) : 0;
	$max_price = isset($_POST['max_price']) ? (float) wp_unslash($_POST['max_price']) : 0;
	if ($max_price > 0 && function_exists('cc_clamp_price_filter_max')) {
		$max_price = cc_clamp_price_filter_max($max_price);
	}
	$count_only = isset($_POST['count_only']) && '1' === (string) wp_unslash($_POST['count_only']);

	// Filters: { "pa_handle-type": [12,13], "product_brand": [84] }
	$filters_raw = isset($_POST['filters']) ? wp_unslash($_POST['filters']) : '';
	$filters     = array();
	if ($filters_raw) {
		$decoded = json_decode($filters_raw, true);
		if (is_array($decoded)) {
			foreach ($decoded as $tax => $term_ids) {
				$tax = sanitize_key($tax);
				if (! taxonomy_exists($tax)) {
					continue;
				}
				$ids = array_filter(array_map('intval', (array) $term_ids));
				if ($ids) {
					$filters[$tax] = $ids;
				}
			}
		}
	}

	// Back-compat + explicit main-shop dependency payload:
	// JS sends all selected specialty IDs here so category availability can be
	// recomputed from the full multi-select set, not only the most recent term.
	if (taxonomy_exists('specialty') && isset($_POST['specialties'])) {
		$specialties_raw = wp_unslash($_POST['specialties']);
		if (is_string($specialties_raw)) {
			$decoded_specialties = json_decode($specialties_raw, true);
			$specialties_raw     = is_array($decoded_specialties) ? $decoded_specialties : array($specialties_raw);
		}

		$specialty_ids = array();
		foreach ((array) $specialties_raw as $specialty_value) {
			if (is_numeric($specialty_value)) {
				$specialty_ids[] = absint($specialty_value);
				continue;
			}

			$specialty_term = get_term_by('slug', sanitize_title(wp_unslash($specialty_value)), 'specialty');
			if ($specialty_term instanceof WP_Term) {
				$specialty_ids[] = (int) $specialty_term->term_id;
			}
		}

		$specialty_ids = array_values(array_unique(array_filter($specialty_ids)));
		if ($specialty_ids) {
			$filters['specialty'] = isset($filters['specialty'])
				? array_values(array_unique(array_merge($filters['specialty'], $specialty_ids)))
				: $specialty_ids;
		}
	}

	// Optional sub-category override (for the "Collection / sub-category" tabs).
	$subcat_id = isset($_POST['subcategory_id']) ? absint($_POST['subcategory_id']) : 0;

	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => $page,
		'tax_query'      => array('relation' => 'AND'),
		'meta_query'     => array(),
	);
	if ($search) {
		$args['s'] = $search;
	}

	if ($category_id && ! ($subcat_id && 'product_cat' === $category_taxonomy)) {
		$args['tax_query'][] = array(
			'taxonomy'         => $category_taxonomy,
			'field'            => 'term_id',
			'terms'            => $category_id,
			'include_children' => true,
		);
	}

	if ($subcat_id) {
		$args['tax_query'][] = array(
			'taxonomy'         => 'product_cat',
			'field'            => 'term_id',
			'terms'            => $subcat_id,
			'include_children' => true,
		);
	}

	foreach ($filters as $tax => $term_ids) {
		if ('product_cat' === $tax) {
			$split_product_cats = cc_split_product_cat_filter_ids($term_ids);

			if (! empty($split_product_cats['top'])) {
				$args['tax_query'][] = array(
					'taxonomy'         => 'product_cat',
					'field'            => 'term_id',
					'terms'            => $split_product_cats['top'],
					'operator'         => 'IN',
					'include_children' => true,
				);
			}

			if (! empty($split_product_cats['child'])) {
				$args['tax_query'][] = array(
					'taxonomy'         => 'product_cat',
					'field'            => 'term_id',
					'terms'            => $split_product_cats['child'],
					'operator'         => 'IN',
					'include_children' => true,
				);
			}

			continue;
		}

		$args['tax_query'][] = array(
			'taxonomy' => $tax,
			'field'    => 'term_id',
			'terms'    => $term_ids,
			'operator' => 'IN',
		);
	}

	if ($min_price > 0 || $max_price > 0) {
		$range_max = $max_price > 0 ? $max_price : PHP_INT_MAX;
		if (function_exists('cc_clamp_price_filter_max')) {
			$range_max = cc_clamp_price_filter_max($range_max);
		}
		$args['meta_query'][] = array(
			'key'     => '_price',
			'value'   => array(
				$min_price > 0 ? $min_price : 0,
				$range_max,
			),
			'type'    => 'DECIMAL(20,6)',
			'compare' => 'BETWEEN',
		);
		if (function_exists('cc_price_filter_exclude_quote_meta_query')) {
			$args['meta_query'][] = cc_price_filter_exclude_quote_meta_query();
		}
	}

	switch ($sort) {
		case 'price_asc':
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = '_price';
			$args['order']    = 'ASC';
			break;
		case 'price_desc':
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = '_price';
			$args['order']    = 'DESC';
			break;
		case 'newest':
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
			break;
		case 'name_asc':
			$args['orderby'] = 'title';
			$args['order']   = 'ASC';
			break;
		default:
			/* Random order so each visit shows a fresh product arrangement.
			   In-stock / on-backorder items come first thanks to the
			   cc_begin_product_stock_order() stock-priority filter that wraps
			   this query — the final SQL becomes:
			     ORDER BY <stock_case> ASC, RAND()                           */
			$args['orderby'] = 'rand';
	}

	/*
	 * Compute the price range for products matching all non-price filters so the
	 * JS slider bounds can narrow in real-time when taxonomy filters change.
	 * We clone $args, strip any _price meta clause, and fetch IDs only.
	 */
	$args_price_range                   = $args;
	$args_price_range['fields']         = 'ids';
	$args_price_range['posts_per_page'] = -1;
	$args_price_range['no_found_rows']  = true;
	unset( $args_price_range['paged'] );
	$args_price_range['meta_query'] = array_values(
		array_filter(
			(array) ( $args['meta_query'] ?? array() ),
			function ( $clause ) {
				return ! ( is_array( $clause ) && isset( $clause['key'] ) && '_price' === $clause['key'] );
			}
		)
	);
	if ( empty( $args_price_range['meta_query'] ) ) {
		unset( $args_price_range['meta_query'] );
	}
	if ( function_exists( 'cc_price_filter_exclude_quote_meta_query' ) ) {
		if ( ! isset( $args_price_range['meta_query'] ) ) {
			$args_price_range['meta_query'] = array();
		}
		$args_price_range['meta_query'][] = cc_price_filter_exclude_quote_meta_query();
	}
	cc_begin_product_stock_order();
	$price_range_ids = get_posts( $args_price_range );
	cc_end_product_stock_order();
	$ajax_price_range = function_exists( 'cc_get_category_price_range' )
		? cc_get_category_price_range( $price_range_ids )
		: array( 'min' => 0.0, 'max' => 0.0 );
	$price_base_count = count( (array) $price_range_ids );

	cc_begin_product_stock_order();
	$query = new WP_Query($args);
	cc_end_product_stock_order();

	if ($count_only) {
		wp_send_json_success(
			array(
				'count'       => (int) $query->found_posts,
				'price_range' => $ajax_price_range,
				'price_count' => (int) $price_base_count,
			)
		);
	}

	$cards_html = '';
	foreach ($query->posts as $post) {
		$cards_html .= cc_render_product_card($post->ID);
	}

	$available_terms = cc_compute_available_filter_terms(
		$filters,
		$category_id,
		$subcat_id,
		$search,
		$args['meta_query'],
		$category_taxonomy
	);

	$response = array(
		'html'             => $cards_html,
		'count'            => (int) $query->found_posts,
		'page'             => $page,
		'per_page'         => $per_page,
		'total_pages'      => (int) $query->max_num_pages,
		'has_results'      => ! empty($query->posts),
		'have_more'        => $page < (int) $query->max_num_pages,
		'available_terms'  => $available_terms,
		'price_range'      => $ajax_price_range,
		'price_count'      => (int) $price_base_count,
	);

	if (function_exists('cc_gtm_filter_ajax_gtm_payload') && ! empty($query->posts)) {
		$response = array_merge($response, cc_gtm_filter_ajax_gtm_payload($query->posts));
	}

	wp_send_json_success($response);
}

if (! function_exists('cc_parse_url_filters')) {
	/**
	 * Resolve a public filter URL token to a taxonomy term.
	 *
	 * Campaign links use comma-separated, human-readable slugs. If an older
	 * campaign uses a term label-style token that is no longer the exact slug
	 * (for example, "orthopedic"), fall back to name lookup before dropping it.
	 *
	 * @param string $token    Raw URL token.
	 * @param string $taxonomy Taxonomy slug.
	 * @return WP_Term|null
	 */
	function cc_resolve_url_filter_term($token, $taxonomy)
	{
		$slug = sanitize_title($token);
		if ('' === $slug || ! taxonomy_exists($taxonomy)) {
			return null;
		}

		$term = get_term_by('slug', $slug, $taxonomy);
		if ($term instanceof WP_Term) {
			return $term;
		}

		$name = trim(str_replace('-', ' ', $slug));
		if ('' !== $name) {
			$term = get_term_by('name', $name, $taxonomy);
			if ($term instanceof WP_Term) {
				return $term;
			}
		}

		return null;
	}

	/**
	 * Parse URL filter parameters (e.g. ?specialty=ent,ophthalmology&product_cat=scissors)
	 * into a term-ID map identical to the AJAX $filters array.
	 *
	 * Keys are taxonomy slugs; values are arrays of integer term IDs resolved from the
	 * comma-separated term slugs supplied in the query string.
	 *
	 * @return array<string, int[]>  e.g. [ 'specialty' => [12, 34], 'product_cat' => [56] ]
	 */
	function cc_parse_url_filters()
	{
		if (! function_exists('cc_get_filterable_taxonomies')) {
			return array();
		}

		$result = array();

		foreach (cc_get_filterable_taxonomies() as $tax) {
			// Specialty scope belongs in the archive path (/specialty/{slug}/), not ?specialty=.
			if ('specialty' === $tax && function_exists('is_tax') && is_tax('specialty')) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL param, no state change.
			if (empty($_GET[$tax])) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw    = sanitize_text_field(wp_unslash($_GET[$tax]));
			$tokens = array_filter(array_map('trim', explode(',', $raw)));

			$ids = array();
			foreach ($tokens as $token) {
				$term = cc_resolve_url_filter_term($token, $tax);
				if ($term instanceof WP_Term) {
					$ids[] = (int) $term->term_id;
				}
			}

			if ($ids) {
				$result[$tax] = array_values(array_unique($ids));
			}
		}

		return $result;
	}
}

if (! function_exists('cc_get_filterable_taxonomies')) {
	/**
	 * Returns the list of taxonomies whose filter availability we want to
	 * recompute per request. This is the union of:
	 *   - specialty (always present)
	 *   - product_cat (Category / Sub-Category multi-select)
	 *   - product_brand (when registered)
	 *   - all visible product attribute taxonomies (pa_*)
	 *
	 * @return string[]
	 */
	function cc_get_filterable_taxonomies()
	{
		$taxes = array('specialty', 'product_cat');

		if (taxonomy_exists('country_of_origin')) {
			$taxes[] = 'country_of_origin';
		}

		if (taxonomy_exists('product_brand')) {
			$taxes[] = 'product_brand';
		}

		if (function_exists('wc_get_attribute_taxonomy_names')) {
			foreach (wc_get_attribute_taxonomy_names() as $att_tax) {
				if (taxonomy_exists($att_tax)) {
					$taxes[] = $att_tax;
				}
			}
		}

		return array_values(array_unique($taxes));
	}
}

if (! function_exists('cc_prevent_filter_query_var_archives')) {
	/**
	 * Stop query-string filter parameters from being routed as taxonomy archives.
	 *
	 * Filter taxonomies such as `specialty` and `product_brand` are registered with
	 * public query vars, so a shared campaign URL like
	 * `/shop/?specialty=anesthesia,colorectal-surgery,ent` would otherwise be resolved
	 * by WordPress as the *first* term's archive (locking that single term and scoping
	 * the filter panel to it) instead of the Shop page with a multi-select filter.
	 *
	 * Our shop/archive templates read these parameters straight from $_GET via
	 * {@see cc_parse_url_filters()}, so we strip them from the main query vars here and
	 * let that logic apply the full multi-select filter.
	 *
	 * Real taxonomy archives use pretty permalinks (e.g. `/specialty/anesthesia/`) where
	 * the term is injected through the rewrite path and never appears in $_GET, so those
	 * pages are left untouched.
	 *
	 * @param array $query_vars Public query vars parsed by WordPress.
	 * @return array
	 */
	function cc_prevent_filter_query_var_archives($query_vars)
	{
		if (is_admin() || ! function_exists('cc_get_filterable_taxonomies')) {
			return $query_vars;
		}

		foreach (cc_get_filterable_taxonomies() as $tax) {
			// Only act on values supplied via the query string; rewrite-based archive
			// permalinks never populate $_GET for the taxonomy.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing guard, no state change.
			if (isset($query_vars[$tax]) && isset($_GET[$tax])) {
				unset($query_vars[$tax]);
			}
		}

		return $query_vars;
	}

	add_filter('request', 'cc_prevent_filter_query_var_archives');
}

if (! function_exists('cc_compute_available_filter_terms')) {
	/**
	 * Compute the available term IDs per taxonomy using "exclude-self" facet
	 * logic: for each taxonomy T, we ignore T's own selected terms when
	 * resolving which terms still match. This lets users keep multi-selecting
	 * inside a taxonomy without losing siblings while cross-filter dependencies
	 * still narrow other facets.
	 *
	 * @param array  $filters     Map of taxonomy => array of selected term IDs.
	 * @param int    $category_id Current taxonomy archive ID (0 for shop).
	 * @param int    $subcat_id   Optional subcategory restriction.
	 * @param string $search      Search query string.
	 * @param array  $meta_query  Base meta_query (e.g. price range).
	 * @param string $category_taxonomy Current archive taxonomy.
	 * @return array              Map of taxonomy => array of available term IDs.
	 */
	function cc_compute_available_filter_terms(array $filters, $category_id, $subcat_id, $search, $meta_query, $category_taxonomy = 'product_cat')
	{
		$category_taxonomy = taxonomy_exists($category_taxonomy) ? $category_taxonomy : 'product_cat';
		$tracked = cc_get_filterable_taxonomies();
		$result  = array();

		foreach ($tracked as $tax) {
			if (
				'product_cat' === $tax
				&& isset($filters['specialty'])
				&& 1 === count($filters)
				&& ! $category_id
				&& ! $subcat_id
				&& '' === $search
				&& empty($meta_query)
			) {
				$result[$tax] = cc_get_product_category_term_ids_for_specialties($filters['specialty']);
				continue;
			}

			$tax_query = array('relation' => 'AND');

			// The URL-based archive restriction is always applied regardless of
			// which facet we are computing — it represents the page's scope.
			if ($category_id && ! ($subcat_id && 'product_cat' === $category_taxonomy)) {
				$tax_query[] = array(
					'taxonomy'         => $category_taxonomy,
					'field'            => 'term_id',
					'terms'            => $category_id,
					'include_children' => true,
				);
			}

			if ($subcat_id) {
				$tax_query[] = array(
					'taxonomy'         => 'product_cat',
					'field'            => 'term_id',
					'terms'            => $subcat_id,
					'include_children' => true,
				);
			}

			// Exclude-self facet: every selected filter is applied EXCEPT the
			// taxonomy we are currently resolving terms for, so users can keep
			// multi-selecting within a single facet.
			foreach ($filters as $other_tax => $term_ids) {
				if ($other_tax === $tax) {
					continue;
				}

				if ('product_cat' === $other_tax) {
					$split_product_cats = cc_split_product_cat_filter_ids($term_ids);

					if (! empty($split_product_cats['top'])) {
						$tax_query[] = array(
							'taxonomy'         => 'product_cat',
							'field'            => 'term_id',
							'terms'            => $split_product_cats['top'],
							'operator'         => 'IN',
							'include_children' => true,
						);
					}

					if (! empty($split_product_cats['child'])) {
						$tax_query[] = array(
							'taxonomy'         => 'product_cat',
							'field'            => 'term_id',
							'terms'            => $split_product_cats['child'],
							'operator'         => 'IN',
							'include_children' => true,
						);
					}

					continue;
				}

				$tax_query[] = array(
					'taxonomy' => $other_tax,
					'field'    => 'term_id',
					'terms'    => $term_ids,
					'operator' => 'IN',
				);
			}

			$other_args = array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => $tax_query,
			);

			if (! empty($meta_query)) {
				$other_args['meta_query'] = $meta_query;
			}
			if ($search) {
				$other_args['s'] = $search;
			}

			$other_query = new WP_Query($other_args);
			$other_ids   = $other_query->posts;

			if (empty($other_ids)) {
				$result[$tax] = array();
				continue;
			}

			$term_ids = wp_get_object_terms($other_ids, $tax, array('fields' => 'ids'));
			if (is_wp_error($term_ids)) {
				$result[$tax] = array();
				continue;
			}

			$result[$tax] = array_values(array_unique(array_map('intval', $term_ids)));
		}

		return $result;
	}
}

if (! function_exists('cc_get_shop_pagination_page_items')) {
	/**
	 * Build page numbers (and ellipsis markers) for shop archive pagination.
	 *
	 * @param int $current_page Current page (1-based).
	 * @param int $total_pages  Total pages.
	 * @return array<int|string>
	 */
	function cc_get_shop_pagination_page_items($current_page, $total_pages)
	{
		$current_page = max(1, (int) $current_page);
		$total_pages  = max(1, (int) $total_pages);

		if ($total_pages <= 7) {
			return range(1, $total_pages);
		}

		$items = array(1);
		$start = max(2, $current_page - 1);
		$end   = min($total_pages - 1, $current_page + 1);

		if ($start > 2) {
			$items[] = 'ellipsis';
		}

		for ($p = $start; $p <= $end; $p++) {
			$items[] = $p;
		}

		if ($end < $total_pages - 1) {
			$items[] = 'ellipsis';
		}

		$items[] = $total_pages;

		return $items;
	}
}

if (! function_exists('cc_render_shop_product_pagination')) {
	/**
	 * Render numbered pagination controls for shop / category archives.
	 *
	 * @param int $current_page Current page (1-based).
	 * @param int $total_pages  Total pages.
	 * @return string
	 */
	function cc_render_shop_product_pagination($current_page, $total_pages)
	{
		$current_page = max(1, (int) $current_page);
		$total_pages  = max(1, (int) $total_pages);

		if ($total_pages <= 1) {
			return '';
		}

		$items = cc_get_shop_pagination_page_items($current_page, $total_pages);

		ob_start();
		?>
		<nav class="fp-pagination cc-pagination" id="fpPagination" aria-label="<?php esc_attr_e('Products pagination', 'consucorner'); ?>">
			<button
				type="button"
				class="fp-page-btn"
				data-cc-page="prev"
				aria-label="<?php esc_attr_e('Previous page', 'consucorner'); ?>"
				<?php disabled($current_page <= 1); ?>>
				<?php esc_html_e('Prev', 'consucorner'); ?>
			</button>
			<?php foreach ($items as $item) : ?>
				<?php if ('ellipsis' === $item) : ?>
					<span class="fp-page-ellipsis" aria-hidden="true">…</span>
				<?php else : ?>
					<?php
					$page_num = (int) $item;
					$active   = $page_num === $current_page;
					?>
					<button
						type="button"
						class="fp-page-btn<?php echo $active ? ' fp-page-btn--active' : ''; ?>"
						data-cc-page="<?php echo esc_attr((string) $page_num); ?>"
						<?php echo $active ? ' aria-current="page"' : ''; ?>
						<?php disabled($active); ?>>
						<?php echo esc_html((string) $page_num); ?>
					</button>
				<?php endif; ?>
			<?php endforeach; ?>
			<button
				type="button"
				class="fp-page-btn"
				data-cc-page="next"
				aria-label="<?php esc_attr_e('Next page', 'consucorner'); ?>"
				<?php disabled($current_page >= $total_pages); ?>>
				<?php esc_html_e('Next', 'consucorner'); ?>
			</button>
		</nav>
		<?php
		return ob_get_clean();
	}
}
