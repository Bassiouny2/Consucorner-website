<?php

/**
 * Search experience helpers and AJAX endpoint.
 *
 * Relevance-aware catalog search: matches specialties, categories, procedures,
 * product titles, and SKUs — without noisy substring hits (e.g. "ent" inside
 * "instrument"). Supports paginated full results on the search page.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/* =====================================================================
   Relevance scoring
   ===================================================================== */

if (! function_exists('consucorner_search_score_text')) {
	/**
	 * Score how well a piece of text matches a search query (0–100).
	 *
	 * Short queries (≤3 chars) require word-boundary or exact matches so
	 * "ent" does not match "instrum**ent**".
	 *
	 * @param string $text  Candidate text (term name, title, SKU, …).
	 * @param string $query Normalized search query.
	 * @return int
	 */
	function consucorner_search_score_text($text, $query)
	{
		$text  = mb_strtolower(trim((string) $text));
		$query = mb_strtolower(trim((string) $query));

		if ('' === $query || '' === $text) {
			return 0;
		}

		if ($text === $query) {
			return 100;
		}

		$query_slug = sanitize_title($query);
		if ($text === $query_slug || sanitize_title($text) === $query_slug) {
			return 95;
		}

		$quoted = preg_quote($query, '/');

		// Word-boundary match (space, hyphen, slash, brackets …).
		if (preg_match('/(?:^|[\s\-\/\(\[,])' . $quoted . '(?:$|[\s\-\/\)\],])/iu', ' ' . $text . ' ')) {
			return 85;
		}

		if (preg_match('/^' . $quoted . '/iu', $text)) {
			return 82;
		}

		// Multi-word: every word must match on a word boundary.
		$words = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
		if (count($words) > 1) {
			$all_match = true;
			foreach ($words as $word) {
				$word_quoted = preg_quote($word, '/');
				if (! preg_match('/(?:^|[\s\-\/\(\[,])' . $word_quoted . '(?:$|[\s\-\/\)\],])/iu', ' ' . $text . ' ')) {
					$all_match = false;
					break;
				}
			}
			if ($all_match) {
				return 75;
			}
		}

		// Short queries: reject bare substring noise.
		if (mb_strlen($query) <= 3) {
			return 0;
		}

		if (false !== mb_strpos($text, $query)) {
			return 45;
		}

		return 0;
	}
}

if (! function_exists('consucorner_get_search_term_taxonomies')) {
	/**
	 * Taxonomies included in catalog search term matching.
	 *
	 * @return string[]
	 */
	function consucorner_get_search_term_taxonomies()
	{
		$taxonomies = array('product_cat');

		if (taxonomy_exists('specialty')) {
			$taxonomies[] = 'specialty';
		}
		if (taxonomy_exists('procedure')) {
			$taxonomies[] = 'procedure';
		}
		if (taxonomy_exists('product_brand')) {
			$taxonomies[] = 'product_brand';
		}

		return $taxonomies;
	}
}

if (! function_exists('consucorner_get_search_term_candidates')) {
	/**
	 * Load searchable taxonomy terms once per request.
	 *
	 * @return WP_Term[]
	 */
	function consucorner_get_search_term_candidates()
	{
		static $cache = null;

		if (null !== $cache) {
			return $cache;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => consucorner_get_search_term_taxonomies(),
				'hide_empty' => true,
				'number'     => 0,
			)
		);

		$cache = is_wp_error($terms) ? array() : array_values($terms);

		return $cache;
	}
}

if (! function_exists('consucorner_get_search_matched_term_rows')) {
	/**
	 * Return taxonomy terms ranked by relevance to the query.
	 *
	 * @param string   $query      Search phrase.
	 * @param string[] $taxonomies Limit to these taxonomies (default: all searchable).
	 * @param int      $min_score  Minimum relevance score (0–100).
	 * @param int      $limit      Max rows (0 = unlimited).
	 * @return array<int, array{term: WP_Term, score: int}>
	 */
	function consucorner_get_search_matched_term_rows($query, $taxonomies = null, $min_score = 70, $limit = 0)
	{
		$query = trim((string) $query);
		if ('' === $query) {
			return array();
		}

		$taxonomies = null !== $taxonomies ? array_values((array) $taxonomies) : consucorner_get_search_term_taxonomies();
		$slug       = sanitize_title($query);
		$rows       = array();
		$seen       = array();

		foreach ($taxonomies as $taxonomy) {
			$term = get_term_by('slug', $slug, $taxonomy);
			if ($term instanceof WP_Term && ! is_wp_error($term)) {
				$key = $term->term_id . ':' . $term->taxonomy;
				$rows[] = array(
					'term'  => $term,
					'score' => 100,
				);
				$seen[$key] = true;
			}
		}

		foreach (consucorner_get_search_term_candidates() as $term) {
			if (! in_array($term->taxonomy, $taxonomies, true)) {
				continue;
			}

			$key = $term->term_id . ':' . $term->taxonomy;
			if (isset($seen[$key])) {
				continue;
			}

			$score = max(
				consucorner_search_score_text($term->name, $query),
				consucorner_search_score_text($term->slug, $query)
			);

			if ($score < (int) $min_score) {
				continue;
			}

			$rows[] = array(
				'term'  => $term,
				'score' => $score,
			);
			$seen[$key] = true;
		}

		usort(
			$rows,
			static function ($a, $b) {
				if ($a['score'] !== $b['score']) {
					return $b['score'] <=> $a['score'];
				}

				return strcasecmp($a['term']->name, $b['term']->name);
			}
		);

		if ($limit > 0) {
			$rows = array_slice($rows, 0, (int) $limit);
		}

		return $rows;
	}
}

if (! function_exists('consucorner_resolve_product_search_ids')) {
	/**
	 * Resolve all matching product IDs for a search query (cached per request).
	 *
	 * @param string $query Search phrase.
	 * @return array{
	 *     product_ids: int[],
	 *     term_rows: array<int, array{term: WP_Term, score: int}>
	 * }
	 */
	function consucorner_resolve_product_search_ids($query)
	{
		static $cache = array();

		$query = trim((string) $query);

		if (isset($cache[$query])) {
			return $cache[$query];
		}

		$empty = array(
			'product_ids' => array(),
			'term_rows'   => array(),
		);

		if ('' === $query || mb_strlen($query) < 3) {
			$cache[$query] = $empty;
			return $empty;
		}

		$term_rows   = consucorner_get_search_matched_term_rows($query, null, 70, 0);
		$product_ids = consucorner_search_product_ids_for_term_rows($term_rows);

		$title_ids = consucorner_search_product_ids_by_text($query, 82);
		if ($title_ids) {
			$product_ids = array_values(array_unique(array_merge($product_ids, $title_ids)));
		}

		if (empty($product_ids)) {
			$product_ids = consucorner_search_product_ids_by_text($query, 45);
		}

		if ($product_ids && $term_rows && function_exists('wc_get_product_visibility_term_ids')) {
			$visibility_ids = wc_get_product_visibility_term_ids();
			$exclude_id       = ! empty($visibility_ids['exclude-from-search'])
				? (int) $visibility_ids['exclude-from-search']
				: 0;

			if ($exclude_id) {
				$product_ids = array_values(
					array_filter(
						$product_ids,
						static function ($product_id) use ($exclude_id) {
							return ! has_term($exclude_id, 'product_visibility', $product_id);
						}
					)
				);
			}
		}

		$cache[$query] = array(
			'product_ids' => array_values(array_map('absint', $product_ids)),
			'term_rows'   => $term_rows,
		);

		return $cache[$query];
	}
}

if (! function_exists('consucorner_get_product_categories_from_ids')) {
	/**
	 * Rank product_cat terms by how many matching products belong to each.
	 *
	 * @param int[] $product_ids Matching product IDs.
	 * @return array<int, array{term: WP_Term, score: int, match_count: int}>
	 */
	function consucorner_get_product_categories_from_ids(array $product_ids)
	{
		$product_ids = array_values(array_filter(array_map('absint', $product_ids)));
		if (empty($product_ids)) {
			return array();
		}

		update_object_term_cache($product_ids, 'product');

		$term_counts = array();

		foreach ($product_ids as $product_id) {
			$terms = wp_get_post_terms($product_id, 'product_cat');
			if (is_wp_error($terms) || empty($terms)) {
				continue;
			}

			$seen_for_product = array();

			foreach ($terms as $term) {
				if (! $term instanceof WP_Term) {
					continue;
				}

				$term_id = (int) $term->term_id;
				if (isset($seen_for_product[$term_id])) {
					continue;
				}

				$seen_for_product[$term_id] = true;

				if (! isset($term_counts[$term_id])) {
					$term_counts[$term_id] = array(
						'term'        => $term,
						'match_count' => 0,
					);
				}

				++$term_counts[$term_id]['match_count'];
			}
		}

		$rows = array();

		foreach ($term_counts as $data) {
			$match_count = (int) $data['match_count'];
			$rows[]      = array(
				'term'        => $data['term'],
				'score'       => 50 + min($match_count, 20),
				'match_count' => $match_count,
			);
		}

		usort(
			$rows,
			static function ($a, $b) {
				if ($a['score'] !== $b['score']) {
					return $b['score'] <=> $a['score'];
				}

				if ($a['match_count'] !== $b['match_count']) {
					return $b['match_count'] <=> $a['match_count'];
				}

				return strcasecmp($a['term']->name, $b['term']->name);
			}
		);

		return $rows;
	}
}

if (! function_exists('consucorner_get_product_search_categories')) {
	/**
	 * Get relevant product categories for a search term.
	 *
	 * Combines name-matched categories with categories assigned to matching products.
	 *
	 * @param string $query Search phrase.
	 * @param int    $limit Maximum number of terms (0 = unlimited).
	 * @return WP_Term[]
	 */
	function consucorner_get_product_search_categories($query, $limit = 0)
	{
		$query = trim((string) $query);
		if ('' === $query || mb_strlen($query) < 3) {
			return array();
		}

		$name_rows    = consucorner_get_search_matched_term_rows($query, array('product_cat'), 70, 0);
		$resolved     = consucorner_resolve_product_search_ids($query);
		$product_rows = consucorner_get_product_categories_from_ids($resolved['product_ids']);

		$merged = array();
		$seen   = array();

		foreach ($name_rows as $row) {
			$term_id = (int) $row['term']->term_id;
			$merged[] = array(
				'term'  => $row['term'],
				'score' => (int) $row['score'],
			);
			$seen[ $term_id ] = true;
		}

		foreach ($product_rows as $row) {
			$term_id = (int) $row['term']->term_id;
			if (isset($seen[ $term_id ])) {
				continue;
			}

			$merged[] = array(
				'term'  => $row['term'],
				'score' => (int) $row['score'],
			);
			$seen[ $term_id ] = true;
		}

		usort(
			$merged,
			static function ($a, $b) {
				if ($a['score'] !== $b['score']) {
					return $b['score'] <=> $a['score'];
				}

				return strcasecmp($a['term']->name, $b['term']->name);
			}
		);

		if ($limit > 0) {
			$merged = array_slice($merged, 0, (int) $limit);
		}

		return array_map(
			static function ($row) {
				return $row['term'];
			},
			$merged
		);
	}
}

if (! function_exists('consucorner_get_product_search_specialties')) {
	/**
	 * Get relevant specialty terms for a search term.
	 *
	 * @param string $query Search phrase.
	 * @param int    $limit Maximum number of terms.
	 * @return WP_Term[]
	 */
	function consucorner_get_product_search_specialties($query, $limit = 6)
	{
		if (! taxonomy_exists('specialty')) {
			return array();
		}

		$rows = consucorner_get_search_matched_term_rows($query, array('specialty'), 70, max(1, (int) $limit));

		return array_map(
			static function ($row) {
				return $row['term'];
			},
			$rows
		);
	}
}

if (! function_exists('consucorner_search_visibility_tax_query')) {
	/**
	 * Exclude products hidden from search (WooCommerce catalog visibility).
	 *
	 * @return array<string, mixed>|null
	 */
	function consucorner_search_visibility_tax_query()
	{
		if (! function_exists('wc_get_product_visibility_term_ids')) {
			return null;
		}

		$ids = wc_get_product_visibility_term_ids();
		if (empty($ids['exclude-from-search'])) {
			return null;
		}

		return array(
			'taxonomy' => 'product_visibility',
			'field'    => 'term_taxonomy_id',
			'terms'    => array((int) $ids['exclude-from-search']),
			'operator' => 'NOT IN',
		);
	}
}

if (! function_exists('consucorner_search_product_ids_for_term_rows')) {
	/**
	 * Product IDs belonging to any of the matched taxonomy terms.
	 *
	 * @param array<int, array{term: WP_Term, score: int}> $term_rows Matched terms.
	 * @return int[]
	 */
	function consucorner_search_product_ids_for_term_rows(array $term_rows)
	{
		if (empty($term_rows)) {
			return array();
		}

		$or_clauses = array('relation' => 'OR');

		foreach ($term_rows as $row) {
			if (empty($row['term']) || ! $row['term'] instanceof WP_Term) {
				continue;
			}

			$or_clauses[] = array(
				'taxonomy'         => $row['term']->taxonomy,
				'field'            => 'term_id',
				'terms'            => array((int) $row['term']->term_id),
				'include_children' => 'product_cat' === $row['term']->taxonomy,
			);
		}

		if (count($or_clauses) < 2) {
			return array();
		}

		$tax_query = array(
			'relation' => 'AND',
			$or_clauses,
		);

		$visibility = consucorner_search_visibility_tax_query();
		if ($visibility) {
			$tax_query[] = $visibility;
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);

		return array_map('absint', $query->posts);
	}
}

if (! function_exists('consucorner_search_product_ids_by_text')) {
	/**
	 * Product IDs whose title or SKU matches the query with sufficient relevance.
	 *
	 * @param string $query     Search phrase.
	 * @param int    $min_score Minimum title/SKU score.
	 * @return int[] Sorted by relevance (best first).
	 */
	function consucorner_search_product_ids_by_text($query, $min_score = 45)
	{
		global $wpdb;

		$query = trim((string) $query);
		if ('' === $query) {
			return array();
		}

		$like = '%' . $wpdb->esc_like($query) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$candidate_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
				WHERE p.post_type = 'product'
				AND p.post_status = 'publish'
				AND ( p.post_title LIKE %s OR pm.meta_value LIKE %s )
				LIMIT 800",
				$like,
				$like
			)
		);

		if (empty($candidate_ids)) {
			return array();
		}

		$ranked = array();

		foreach ($candidate_ids as $product_id) {
			$product_id = (int) $product_id;
			$title      = get_the_title($product_id);
			$sku        = (string) get_post_meta($product_id, '_sku', true);
			$score      = max(
				consucorner_search_score_text($title, $query),
				consucorner_search_score_text($sku, $query)
			);

			if ($score >= (int) $min_score) {
				$ranked[] = array(
					'id'    => $product_id,
					'score' => $score,
				);
			}
		}

		usort(
			$ranked,
			static function ($a, $b) {
				if ($a['score'] !== $b['score']) {
					return $b['score'] <=> $a['score'];
				}

				return $a['id'] <=> $b['id'];
			}
		);

		return array_column($ranked, 'id');
	}
}

if (! function_exists('consucorner_run_product_search')) {
	/**
	 * Run a paginated, relevance-aware product search.
	 *
	 * @param string               $query Search phrase.
	 * @param array<string, mixed> $args  {
	 *     @type int $per_page Posts per page.
	 *     @type int $page     Current page (1-based).
	 * }
	 * @return array{
	 *     products: WC_Product[],
	 *     total: int,
	 *     pages: int,
	 *     page: int,
	 *     per_page: int
	 * }
	 */
	function consucorner_run_product_search($query, array $args = array())
	{
		$query = trim((string) $query);
		$args  = wp_parse_args(
			$args,
			array(
				'per_page' => 24,
				'page'     => 1,
			)
		);

		$per_page = max(1, (int) $args['per_page']);
		$page     = max(1, (int) $args['page']);

		$empty = array(
			'products' => array(),
			'total'    => 0,
			'pages'    => 0,
			'page'     => $page,
			'per_page' => $per_page,
		);

		if ('' === $query || mb_strlen($query) < 3 || ! function_exists('wc_get_product')) {
			return $empty;
		}

		$resolved    = consucorner_resolve_product_search_ids($query);
		$product_ids = $resolved['product_ids'];

		if (empty($product_ids)) {
			return $empty;
		}

		$total  = count($product_ids);
		$pages  = (int) ceil($total / $per_page);
		$offset = ($page - 1) * $per_page;
		$slice  = array_slice($product_ids, $offset, $per_page);

		if (empty($slice)) {
			return array_merge(
				$empty,
				array(
					'total' => $total,
					'pages' => $pages,
				)
			);
		}

		$query_args = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'post__in'               => $slice,
			'orderby'                => 'post__in',
			'posts_per_page'         => count($slice),
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);

		if (function_exists('cc_begin_product_stock_order')) {
			cc_begin_product_stock_order();
		}

		$products_query = new WP_Query($query_args);

		if (function_exists('cc_end_product_stock_order')) {
			cc_end_product_stock_order();
		}

		$products = array();
		foreach ($products_query->posts as $product_post) {
			$product = wc_get_product($product_post->ID);
			if ($product) {
				$products[] = $product;
			}
		}

		wp_reset_postdata();

		return array(
			'products' => $products,
			'total'    => $total,
			'pages'    => $pages,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}
}

if (! function_exists('consucorner_get_product_search_results')) {
	/**
	 * Get matching products for a search term (paginated slice).
	 *
	 * @param string $query  Search phrase.
	 * @param int    $limit  Maximum number of products.
	 * @param int    $page   Page number (1-based).
	 * @return WC_Product[]
	 */
	function consucorner_get_product_search_results($query, $limit = 8, $page = 1)
	{
		$result = consucorner_run_product_search(
			$query,
			array(
				'per_page' => max(1, (int) $limit),
				'page'     => max(1, (int) $page),
			)
		);

		return $result['products'];
	}
}

if (! function_exists('consucorner_get_term_parent_context')) {
	/**
	 * Build a short parent path for a taxonomy term.
	 *
	 * @param WP_Term $term Taxonomy term.
	 * @return string
	 */
	function consucorner_get_term_parent_context($term)
	{
		if (! $term instanceof WP_Term || empty($term->parent)) {
			return '';
		}

		$ancestors = array_reverse(get_ancestors($term->term_id, $term->taxonomy, 'taxonomy'));
		$labels    = array();

		foreach ($ancestors as $ancestor_id) {
			$ancestor = get_term($ancestor_id, $term->taxonomy);
			if ($ancestor && ! is_wp_error($ancestor)) {
				$labels[] = $ancestor->name;
			}
		}

		return implode(' / ', $labels);
	}
}

if (! function_exists('consucorner_format_search_term_card')) {
	/**
	 * Format a taxonomy term for search UI / JSON.
	 *
	 * @param WP_Term $term Taxonomy term.
	 * @return array<string, mixed>
	 */
	function consucorner_format_search_term_card($term)
	{
		$link = get_term_link($term);
		$type = $term->taxonomy;

		return array(
			'id'     => (int) $term->term_id,
			'title'  => $term->name,
			'parent' => consucorner_get_term_parent_context($term),
			'count'  => (int) $term->count,
			'url'    => is_wp_error($link) ? '' : $link,
			'type'   => $type,
			'label'  => 'specialty' === $type
				? __('Specialty', 'consucorner')
				: ('procedure' === $type ? __('Procedure', 'consucorner') : __('Category', 'consucorner')),
		);
	}
}

if (! function_exists('consucorner_format_live_search_product')) {
	/**
	 * Convert a WC product into compact JSON-ready data for live suggestions.
	 *
	 * @param WC_Product $product Product object.
	 * @return array<string, mixed>
	 */
	function consucorner_format_live_search_product($product)
	{
		$image_id    = $product->get_image_id();
		$image       = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : '';
		$placeholder = function_exists('consucorner_get_product_placeholder_image_url')
			? consucorner_get_product_placeholder_image_url()
			: get_template_directory_uri() . '/assets/images/' . rawurlencode('consucorner icon-logo.jpg');
		$terms       = get_the_terms($product->get_id(), 'product_cat');
		$category    = (! is_wp_error($terms) && ! empty($terms)) ? $terms[0]->name : '';
		$is_quote    = function_exists('cc_is_quote_product') && cc_is_quote_product($product);

		if ($is_quote) {
			// Match shop cards: never expose numeric price for quote-tier products.
			$price = __('Price on request', 'consucorner');
		} elseif ('' !== $product->get_price()) {
			$price = sprintf(
				/* translators: 1: product price, 2: currency code. */
				__('%1$s %2$s', 'consucorner'),
				function_exists('cc_format_product_price_amount')
					? cc_format_product_price_amount((float) $product->get_price())
					: number_format_i18n((float) $product->get_price(), wc_get_price_decimals()),
				get_woocommerce_currency()
			);
		} else {
			$price = '';
		}

		return array(
			'id'       => $product->get_id(),
			'title'    => $product->get_name(),
			'url'      => get_permalink($product->get_id()),
			'image'    => $image ? $image : $placeholder,
			'price'    => $price,
			'is_quote' => (bool) $is_quote,
			'category' => $category,
			'stock'    => $product->is_in_stock() ? __('In stock', 'consucorner') : __('Out of stock', 'consucorner'),
		);
	}
}

if (! function_exists('consucorner_ajax_live_search')) {
	/**
	 * Live search endpoint used by the header search suggestions.
	 *
	 * @return void
	 */
	function consucorner_ajax_live_search()
	{
		check_ajax_referer('consucorner_live_search', 'nonce');

		$query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
		$query = trim($query);

		if (mb_strlen($query) < 3) {
			wp_send_json_success(
				array(
					'query'        => $query,
					'categories'   => array(),
					'specialties'  => array(),
					'products'     => array(),
					'productTotal' => 0,
				)
			);
		}

		$categories = array_map(
			'consucorner_format_search_term_card',
			consucorner_get_product_search_categories($query, 8)
		);

		$specialties = array_map(
			'consucorner_format_search_term_card',
			consucorner_get_product_search_specialties($query, 4)
		);

		$search_result = consucorner_run_product_search(
			$query,
			array(
				'per_page' => 6,
				'page'     => 1,
			)
		);

		$products = array_map(
			'consucorner_format_live_search_product',
			$search_result['products']
		);

		wp_send_json_success(
			array(
				'query'        => $query,
				'categories' => $categories,
				'specialties' => $specialties,
				'products'   => $products,
				'productTotal' => (int) $search_result['total'],
				'searchUrl'  => add_query_arg('s', $query, home_url('/')),
			)
		);
	}
}
add_action('wp_ajax_consucorner_live_search', 'consucorner_ajax_live_search');
add_action('wp_ajax_nopriv_consucorner_live_search', 'consucorner_ajax_live_search');
