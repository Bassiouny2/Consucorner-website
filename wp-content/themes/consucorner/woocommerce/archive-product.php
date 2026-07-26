<?php

/**
 * The template for displaying product archives — including the main shop page,
 * product taxonomies (categories, tags, brands).
 *
 * Mirrors the design from front-end/archive-products.html.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

get_header();

$theme_uri = get_template_directory_uri();

/* ---------------------------------------------------------
   Resolve the queried object → category info
   --------------------------------------------------------- */
$queried_term  = is_tax() || is_product_category() || is_product_tag() ? get_queried_object() : null;
$category_id   = ($queried_term && ! is_wp_error($queried_term)) ? (int) $queried_term->term_id : 0;
$archive_taxonomy = ($queried_term && ! is_wp_error($queried_term) && ! empty($queried_term->taxonomy)) ? $queried_term->taxonomy : '';
$category_slug = $queried_term ? $queried_term->slug : '';
$category_name = $queried_term ? $queried_term->name : __('Shop', 'consucorner');
$category_desc = $queried_term ? wp_strip_all_tags($queried_term->description) : '';
$search_term   = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$is_specialty_archive = 'specialty' === $archive_taxonomy && $queried_term instanceof WP_Term;
$is_product_cat_archive = 'product_cat' === $archive_taxonomy && $queried_term instanceof WP_Term;
// Specialty archive: hide Specialty filter only. Category archive: hide Category filter only.
$hide_specialty_filter_controls = $is_specialty_archive;
$hide_category_filter_controls  = $is_product_cat_archive;

/* ---------------------------------------------------------
   Breadcrumbs (custom, lightweight)
   --------------------------------------------------------- */
$crumbs = array(
	array('label' => __('Home', 'consucorner'), 'url' => home_url('/')),
	array('label' => __('Shop', 'consucorner'), 'url' => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/')),
);
if ($queried_term) {
	// Walk up the parent chain.
	$ancestors = $archive_taxonomy ? get_ancestors($category_id, $archive_taxonomy) : array();
	$ancestors = array_reverse($ancestors);
	foreach ($ancestors as $anc_id) {
		$anc_term = get_term($anc_id, $archive_taxonomy);
		if ($anc_term && ! is_wp_error($anc_term)) {
			$crumbs[] = array('label' => $anc_term->name, 'url' => get_term_link($anc_term));
		}
	}
	$crumbs[] = array('label' => $category_name, 'url' => get_term_link($queried_term));
}

/* ---------------------------------------------------------
   Collect filter taxonomies + price range for this category
   --------------------------------------------------------- */
if ($category_id) {
	$category_product_ids = cc_get_taxonomy_product_ids($archive_taxonomy, array($category_id));
} else {
	$category_product_ids = get_posts(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	$category_product_ids = array_map('absint', $category_product_ids);
}
$filter_taxes = cc_get_category_filter_taxonomies($category_product_ids);
$price_range  = cc_get_category_price_range($category_product_ids);

$min_price = isset($price_range['min']) ? floor($price_range['min']) : 0;
$max_price = isset($price_range['max']) ? ceil($price_range['max'])  : 0;
if ($max_price <= $min_price) {
	$max_price = $min_price;
}
$slider_max = (int) $max_price;

/* ---------------------------------------------------------
   First-page products (initial render — same data the AJAX
   endpoint will return; uses the main query so it respects
   any global query overrides).
   --------------------------------------------------------- */
$initial_per_page = 12;
$paged            = max(1, get_query_var('paged') ? get_query_var('paged') : 1);

/* ---------------------------------------------------------
   URL-driven filter SSR: parse ?taxonomy=slug1,slug2 params
   and apply them to the initial WP_Query for server-first
   rendering of shared/bookmarked filtered URLs.
   --------------------------------------------------------- */
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$url_filters   = function_exists('cc_parse_url_filters') ? cc_parse_url_filters() : array();
$url_sort      = isset($_GET['sort']) ? sanitize_key(wp_unslash($_GET['sort'])) : 'default';
$url_min_price = isset($_GET['min_price']) ? (float) wp_unslash($_GET['min_price']) : 0;
$url_max_price = isset($_GET['max_price']) ? (float) wp_unslash($_GET['max_price']) : 0;
if ($url_max_price > 0 && function_exists('cc_clamp_price_filter_max')) {
	$url_max_price = cc_clamp_price_filter_max($url_max_price);
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$url_filters_active = ! empty($url_filters)
	|| $url_min_price > 0
	|| $url_max_price > 0
	|| (! empty($url_sort) && 'default' !== $url_sort);

$initial_args = array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => $initial_per_page,
	'paged'          => $paged,
	/* Random default order — in-stock first (via cc_begin_product_stock_order),
	   then random within each stock group so customers see fresh products every visit.
	   Overridden below when a sort URL param is present. */
	'orderby'        => 'rand',
	'tax_query'      => array('relation' => 'AND'),
	'meta_query'     => array(),
);
if ($search_term) {
	$initial_args['s'] = $search_term;
}
if ($category_id) {
	$initial_args['tax_query'][] = array(
		'taxonomy'         => $archive_taxonomy,
		'field'            => 'term_id',
		'terms'            => $category_id,
		'include_children' => true,
	);
}

// Inject URL-parsed taxonomy filters — mirrors the logic in consucorner_ajax_filter_category_products.
foreach ($url_filters as $tax => $term_ids) {
	if ('product_cat' === $tax && function_exists('cc_split_product_cat_filter_ids')) {
		$split = cc_split_product_cat_filter_ids($term_ids);
		if (! empty($split['top'])) {
			$initial_args['tax_query'][] = array(
				'taxonomy'         => 'product_cat',
				'field'            => 'term_id',
				'terms'            => $split['top'],
				'operator'         => 'IN',
				'include_children' => true,
			);
		}
		if (! empty($split['child'])) {
			$initial_args['tax_query'][] = array(
				'taxonomy'         => 'product_cat',
				'field'            => 'term_id',
				'terms'            => $split['child'],
				'operator'         => 'IN',
				'include_children' => true,
			);
		}
		continue;
	}
	$initial_args['tax_query'][] = array(
		'taxonomy' => $tax,
		'field'    => 'term_id',
		'terms'    => $term_ids,
		'operator' => 'IN',
	);
}

// Apply sort from URL (mirrors AJAX handler sort logic).
switch ($url_sort) {
	case 'price_asc':
		$initial_args['orderby']  = 'meta_value_num';
		$initial_args['meta_key'] = '_price';
		$initial_args['order']    = 'ASC';
		break;
	case 'price_desc':
		$initial_args['orderby']  = 'meta_value_num';
		$initial_args['meta_key'] = '_price';
		$initial_args['order']    = 'DESC';
		break;
	case 'newest':
		$initial_args['orderby'] = 'date';
		$initial_args['order']   = 'DESC';
		break;
	case 'name_asc':
		$initial_args['orderby'] = 'title';
		$initial_args['order']   = 'ASC';
		break;
}

$initial_price_count_args                   = $initial_args;
$initial_price_count_args['fields']         = 'ids';
$initial_price_count_args['posts_per_page'] = -1;
$initial_price_count_args['no_found_rows']  = true;
unset($initial_price_count_args['paged']);
if (function_exists('cc_price_filter_exclude_quote_meta_query')) {
	if (! isset($initial_price_count_args['meta_query'])) {
		$initial_price_count_args['meta_query'] = array();
	}
	$initial_price_count_args['meta_query'][] = cc_price_filter_exclude_quote_meta_query();
}
cc_begin_product_stock_order();
$initial_price_count = count((array) get_posts($initial_price_count_args));
cc_end_product_stock_order();

// Apply price range from URL.
if ($url_min_price > 0 || $url_max_price > 0) {
	$range_max = $url_max_price > 0 ? $url_max_price : PHP_INT_MAX;
	if (function_exists('cc_clamp_price_filter_max')) {
		$range_max = cc_clamp_price_filter_max($range_max);
	}
	$initial_args['meta_query'][] = array(
		'key'     => '_price',
		'value'   => array(
			$url_min_price > 0 ? $url_min_price : 0,
			$range_max,
		),
		'type'    => 'DECIMAL(20,6)',
		'compare' => 'BETWEEN',
	);
	if (function_exists('cc_price_filter_exclude_quote_meta_query')) {
		$initial_args['meta_query'][] = cc_price_filter_exclude_quote_meta_query();
	}
}

cc_begin_product_stock_order();
$initial_query = new WP_Query($initial_args);
cc_end_product_stock_order();

/* ---------------------------------------------------------
   Common variables used by BOTH the shop page and category
   archive sections below.
   --------------------------------------------------------- */
$top_category_ids   = array();
$product_categories = array();
$category_terms     = array();
$subcategory_terms  = array();
$specialty_terms    = array();

if ($category_id) {
	// Taxonomy archive: only show filter options tied to products in this term.
	if (taxonomy_exists('specialty') && function_exists('cc_get_terms_for_product_ids')) {
		$specialty_terms = cc_get_terms_for_product_ids('specialty', $category_product_ids);
	}

	if (function_exists('cc_get_terms_for_product_ids')) {
		$scoped_product_cats = cc_get_terms_for_product_ids('product_cat', $category_product_ids);
		foreach ($scoped_product_cats as $product_category_term) {
			if (0 === (int) $product_category_term->parent) {
				$product_categories[] = $product_category_term;
				$top_category_ids[]   = (int) $product_category_term->term_id;
			} else {
				$subcategory_terms[] = $product_category_term;
			}
		}
	}

	$category_terms  = $product_categories;
	$subcategories   = ($is_product_cat_archive && $category_id)
		? cc_get_subcategories_for_tabs($category_id)
		: $subcategory_terms;
} else {
	$all_product_category_terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'orderby'    => 'menu_order',
			'order'      => 'ASC',
		)
	);
	$all_product_category_terms = is_wp_error($all_product_category_terms) ? array() : $all_product_category_terms;

	$specialty_terms = taxonomy_exists('specialty') ? get_terms(
		array(
			'taxonomy'   => 'specialty',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	) : array();
	$specialty_terms = is_wp_error($specialty_terms) ? array() : $specialty_terms;

	foreach ($all_product_category_terms as $product_category_term) {
		if (0 === (int) $product_category_term->parent) {
			$product_categories[] = $product_category_term;
			$top_category_ids[]   = (int) $product_category_term->term_id;
		}
	}

	foreach ($all_product_category_terms as $product_category_term) {
		if (0 === (int) $product_category_term->parent) {
			continue;
		}
		$subcategory_terms[] = $product_category_term;
	}

	$category_terms  = $product_categories;
	$subcategories   = $subcategory_terms;
}

if (! empty($product_categories) && function_exists('consucorner_sort_terms_by_order')) {
	$product_categories = consucorner_sort_terms_by_order($product_categories);
	$category_terms     = $product_categories;
}

if (! empty($subcategory_terms) && function_exists('consucorner_sort_terms_by_order')) {
	$subcategory_terms = consucorner_sort_terms_by_order($subcategory_terms);
	if (! ($is_product_cat_archive && $category_id)) {
		$subcategories = $subcategory_terms;
	}
}

// Apply the operations-team display order (specialty term meta) so the shop
// specialty filter matches the mega-menu specialty section.
if (! empty($specialty_terms) && function_exists('consucorner_sort_terms_by_order')) {
	$specialty_terms = consucorner_sort_terms_by_order($specialty_terms);
}

$dot_colors = array('#2597E0', '#27AE60', '#F39C12', '#E91E8C', '#9B59B6', '#2ECC71', '#3498DB', '#E74C3C', '#1ABC9C', '#8E44AD');
$shop_url   = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');

$shop_promo_benefits = array(
	array(
		'label' => __('Verified products', 'consucorner'),
		'icon'  => 'check',
	),
	array(
		'label' => __('Delivered at 48H', 'consucorner'),
		'icon'  => 'clock',
	),
	array(
		'label' => __('Secure payment', 'consucorner'),
		'icon'  => 'lock',
	),
);

$shop_promo_fallback_slides = array(
	array(
		'title'    => __('Professional Surgical Instruments Sale', 'consucorner'),
		'subtitle' => __('Up to 20% off for verified users', 'consucorner'),
		'button'   => __('Shop Now', 'consucorner'),
	),
	array(
		'title'    => __('Trusted Medical Equipment Deals', 'consucorner'),
		'subtitle' => __('Premium brands ready for fast delivery', 'consucorner'),
		'button'   => __('Explore Offers', 'consucorner'),
	),
	array(
		'title'    => __('Secure Checkout For Every Order', 'consucorner'),
		'subtitle' => __('Built for clinics, hospitals, and professionals', 'consucorner'),
		'button'   => __('Start Shopping', 'consucorner'),
	),
);

$shop_promo_banner_url = $theme_uri . '/assets/images/shop banner.png';
$shop_promo_slides     = array();
$shop_promo_mode       = 'banner';
$shop_promo_origins    = array('total' => 0, 'visible' => array(), 'all' => array(), 'mode' => 'none', 'hidden_count' => 0);

if ($is_product_cat_archive || $is_specialty_archive) {
	if (function_exists('cc_get_archive_country_origins') && $queried_term instanceof WP_Term) {
		$shop_promo_origins = cc_get_archive_country_origins($category_product_ids, $queried_term);
	}
	if (function_exists('consucorner_get_term_promo_banner') && $queried_term instanceof WP_Term) {
		$term_banner = consucorner_get_term_promo_banner($queried_term, $shop_url, $shop_promo_banner_url, $theme_uri);
		if ($term_banner) {
			$manual_country_ids = isset($term_banner['countries']) && is_array($term_banner['countries'])
				? $term_banner['countries']
				: array();
			if (! empty($manual_country_ids) && function_exists('consucorner_term_promo_build_manual_origins')) {
				$manual_origins = consucorner_term_promo_build_manual_origins($manual_country_ids, $queried_term);
				if (! empty($manual_origins['mode']) && 'none' !== $manual_origins['mode']) {
					$shop_promo_origins = $manual_origins;
				}
			}
			$term_banner['origins'] = $shop_promo_origins;
			$shop_promo_slides = array($term_banner);
		}
	}
	$shop_promo_mode = 'banner';
} elseif (function_exists('consucorner_is_main_shop_archive') && consucorner_is_main_shop_archive()) {
	if (function_exists('consucorner_shop_promo_get_slides')) {
		$shop_promo_slides = consucorner_shop_promo_get_slides(
			$shop_promo_fallback_slides,
			$shop_url,
			$shop_promo_banner_url,
			$theme_uri
		);
	}
	$shop_promo_mode = 'slider';
}

if (function_exists('is_shop') && is_shop()) :
?>

	<main class="page-all-products">
		<section class="shop-page-head shop-page-head--archive-compact" aria-label="<?php esc_attr_e('All Products heading', 'consucorner'); ?>">
			<div class="shop-page-head-inner">
				<h1 class="shop-page-title"><?php esc_html_e('All Products', 'consucorner'); ?></h1>
				<p class="shop-page-breadcrumbs">
					<a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'consucorner'); ?></a>/
					<?php esc_html_e('Shop', 'consucorner'); ?>
				</p>
			</div>
		</section>

		<section class="fp-products-section ap-products-section">
			<div class="fp-products-inner">
				<div class="fp-filter-bar ap-filter-bar">
					<div class="fp-filter-bar-left" data-cc-tour="shop-filter-bar">
						<button class="fp-mobile-filter-toggle cc-filter-chip" id="fpFilterToggle" type="button" data-cc-tour="all-filters" data-cc-sheet-target="all" data-cc-sheet-title="<?php esc_attr_e('All filters', 'consucorner'); ?>">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<line x1="4" y1="6" x2="20" y2="6" />
								<line x1="8" y1="12" x2="16" y2="12" />
								<line x1="11" y1="18" x2="13" y2="18" />
							</svg>
							<span class="fp-mobile-filter-text"><?php esc_html_e('Filters', 'consucorner'); ?></span>
						</button>
						<button class="cc-filter-chip" type="button" data-cc-tour="specialty-filter" data-cc-sheet-target="specialty" data-cc-sheet-title="<?php esc_attr_e('Specialty', 'consucorner'); ?>"><?php esc_html_e('Specialty', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
						<?php if ($category_terms) : ?>
							<button class="cc-filter-chip" type="button" data-cc-tour="category-filter" data-cc-sheet-target="category" data-cc-sheet-title="<?php esc_attr_e('Category', 'consucorner'); ?>"><?php esc_html_e('Category', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
						<?php endif; ?>
						<?php if ($subcategories) : ?>
							<button class="cc-filter-chip" type="button" data-cc-sheet-target="subcategory" data-cc-sheet-title="<?php esc_attr_e('Sub-Category', 'consucorner'); ?>"><?php esc_html_e('Sub-Category', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
						<?php endif; ?>
						<?php foreach ($filter_taxes as $ftx) : ?>
							<button class="cc-filter-chip"
								type="button"
								data-cc-sheet-target="<?php echo esc_attr($ftx['taxonomy']); ?>"
								data-cc-sheet-title="<?php echo esc_attr($ftx['label']); ?>"><?php echo esc_html($ftx['label']); ?><span aria-hidden="true">&rsaquo;</span></button>
						<?php endforeach; ?>
						<?php if ($max_price > 0) : ?>
							<button class="cc-filter-chip" type="button" data-cc-sheet-target="price" data-cc-sheet-title="<?php esc_attr_e('Price', 'consucorner'); ?>"><?php esc_html_e('Price', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
						<?php endif; ?>

					</div>

					<div class="cc-active-filter-strip" id="ccActiveFilters" aria-live="polite" hidden></div>

					<div class="fp-filter-bar-right">
						<div class="fp-view-toggle" role="group" aria-label="<?php esc_attr_e('Product layout', 'consucorner'); ?>">
							<button type="button" class="fp-view-toggle-btn is-active" data-cc-view="list" aria-pressed="true" aria-label="<?php esc_attr_e('List view', 'consucorner'); ?>">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
									<line x1="8" y1="6" x2="21" y2="6" />
									<line x1="8" y1="12" x2="21" y2="12" />
									<line x1="8" y1="18" x2="21" y2="18" />
									<line x1="3" y1="6" x2="3.01" y2="6" />
									<line x1="3" y1="12" x2="3.01" y2="12" />
									<line x1="3" y1="18" x2="3.01" y2="18" />
								</svg>
							</button>
							<button type="button" class="fp-view-toggle-btn" data-cc-view="grid" aria-pressed="false" aria-label="<?php esc_attr_e('Grid view', 'consucorner'); ?>">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
									<rect x="3" y="3" width="7" height="7" />
									<rect x="14" y="3" width="7" height="7" />
									<rect x="14" y="14" width="7" height="7" />
									<rect x="3" y="14" width="7" height="7" />
								</svg>
							</button>
						</div>
						<a href="#" class="fp-clear-all" id="fpClearAll"><?php esc_html_e('Clear All', 'consucorner'); ?></a>
					</div>
					<div class="cc-filter-dropdown-host" aria-hidden="true"></div>
				</div>

				<?php
				// load_template() no longer extracts $args into scope; pass context via query vars.
				set_query_var(
					'cc_shop_promo_context',
					array(
						'theme_uri'   => $theme_uri,
						'shop_url'    => $shop_url,
						'benefits'    => $shop_promo_benefits,
						'slides'      => $shop_promo_slides,
						'banner_url'  => $shop_promo_banner_url,
						'mode'        => $shop_promo_mode,
						'origins'     => $shop_promo_origins,
					)
				);
				get_template_part('template-parts/shop-promo-section');
				?>

				<div class="fp-sidebar-overlay" id="fpSidebarOverlay"></div>

				<div class="fp-layout" data-cc-sidebar-home>
					<aside class="fp-sidebar" id="fpSidebar">
						<div class="cc-filter-sheet-head">
							<span class="cc-filter-sheet-grip" aria-hidden="true"></span>
							<h2 class="cc-filter-sheet-title" id="ccFilterSheetTitle"><?php esc_html_e('Filters', 'consucorner'); ?></h2>
							<button class="cc-filter-sheet-close" id="ccFilterClose" type="button"><?php esc_html_e('Close', 'consucorner'); ?></button>
						</div>

						<div class="cc-filter-panel-menu" aria-label="<?php esc_attr_e('Choose filter type', 'consucorner'); ?>">
							<button class="cc-filter-panel-btn" type="button" data-cc-sheet-target="specialty" data-cc-sheet-title="<?php esc_attr_e('Specialty', 'consucorner'); ?>"><?php esc_html_e('Specialty', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
							<?php if ($category_terms) : ?>
								<button class="cc-filter-panel-btn" type="button" data-cc-sheet-target="category" data-cc-sheet-title="<?php esc_attr_e('Category', 'consucorner'); ?>"><?php esc_html_e('Category', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
							<?php endif; ?>
							<?php if ($subcategories) : ?>
								<button class="cc-filter-panel-btn" type="button" data-cc-sheet-target="subcategory" data-cc-sheet-title="<?php esc_attr_e('Sub-Category', 'consucorner'); ?>"><?php esc_html_e('Sub-Category', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
							<?php endif; ?>
							<?php foreach ($filter_taxes as $ftx) : ?>
								<button class="cc-filter-panel-btn" type="button" data-cc-sheet-target="<?php echo esc_attr($ftx['taxonomy']); ?>" data-cc-sheet-title="<?php echo esc_attr($ftx['label']); ?>"><?php echo esc_html($ftx['label']); ?><span aria-hidden="true">&rsaquo;</span></button>
							<?php endforeach; ?>
							<?php if ($max_price > 0) : ?>
								<button class="cc-filter-panel-btn" type="button" data-cc-sheet-target="price" data-cc-sheet-title="<?php esc_attr_e('Price', 'consucorner'); ?>"><?php esc_html_e('Price', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
							<?php endif; ?>
						</div>

						<?php if ($max_price > 0) : ?>
							<div class="fp-filter-group fp-price-group ap-price-group" data-cc-filter-panel="price">
								<h3 class="ap-filter-section-heading"><?php esc_html_e('Filter by price', 'consucorner'); ?></h3>
								<div class="cc-price-panel">
									<div class="cc-price-canvas-wrap">
										<canvas class="cc-price-canvas" aria-hidden="true"></canvas>
									</div>
									<div class="cc-price-dual-range" id="fpDualRange">
										<div class="cc-price-track">
											<div class="cc-price-track-fill" id="fpTrackFill"></div>
										</div>
										<input type="range" id="fpMinSlider"
											min="<?php echo esc_attr($min_price); ?>"
											max="<?php echo esc_attr($slider_max); ?>"
											value="<?php echo esc_attr($min_price); ?>"
											step="50" />
										<input type="range" id="fpMaxSlider"
											min="<?php echo esc_attr($min_price); ?>"
											max="<?php echo esc_attr($slider_max); ?>"
											value="<?php echo esc_attr($slider_max); ?>"
											step="50" />
									</div>
									<div class="cc-price-meta">
										<span><?php esc_html_e('Total products:', 'consucorner'); ?> <strong id="fpPriceCount"><?php echo esc_html((string) (int) $initial_price_count); ?></strong></span>
										<span id="fpPriceDisplay">
											<?php
											printf(
												/* translators: 1: min price, 2: max price, 3: currency. */
												esc_html__('Price: %1$s %3$s – %2$s %3$s', 'consucorner'),
												esc_html(number_format_i18n($min_price)),
												esc_html(number_format_i18n($slider_max)),
												esc_html(get_woocommerce_currency())
											);
											?>
										</span>
									</div>
									<div class="cc-price-inputs">
										<input type="number" id="fpMinPriceInput" min="0" max="<?php echo esc_attr($slider_max); ?>" step="1" inputmode="numeric" placeholder="<?php esc_attr_e('Min', 'consucorner'); ?>" value="" />
										<span aria-hidden="true">-</span>
										<input type="number" id="fpMaxPriceInput" min="0" max="<?php echo esc_attr($slider_max); ?>" step="1" inputmode="numeric" placeholder="<?php esc_attr_e('Max', 'consucorner'); ?>" value="<?php echo esc_attr($slider_max); ?>" />
									</div>
								</div>
								<button class="fp-price-filter-btn" type="button" id="fpPriceFilterBtn"><?php esc_html_e('Apply price', 'consucorner'); ?></button>
							</div>
						<?php endif; ?>

						<div class="fp-filter-group ap-filter-group" data-filter-tax="specialty" data-cc-filter-panel="specialty">
							<h3 class="ap-filter-section-heading ap-filter-section-heading--blue"><?php esc_html_e('Specialty', 'consucorner'); ?></h3>
							<ul class="ap-filter-list ap-filter-list--scroll">
								<?php foreach ($specialty_terms as $term) : ?>
									<?php
									$is_url_checked_mobile = isset($url_filters['specialty'])
										&& in_array((int) $term->term_id, $url_filters['specialty'], true);
									?>
									<li class="ap-filter-item<?php echo $is_url_checked_mobile ? ' is-selected' : ''; ?>" data-term-id="<?php echo esc_attr($term->term_id); ?>">
										<label class="ap-filter-label">
											<input type="checkbox"
												class="fp-checkbox"
												data-filter-tax="specialty"
												data-filter-term="<?php echo esc_attr($term->term_id); ?>"
												value="<?php echo esc_attr($term->slug); ?>"
												<?php checked($is_url_checked_mobile); ?> />
											<span class="ap-filter-text"><?php echo esc_html($term->name); ?></span>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>

						<?php if ($category_terms) : ?>
							<div class="fp-filter-group ap-filter-group" data-filter-tax="product_cat" data-cc-filter-panel="category">
								<h3 class="ap-filter-section-heading ap-filter-section-heading--blue"><?php esc_html_e('Category', 'consucorner'); ?></h3>
								<ul class="ap-filter-list ap-filter-list--scroll">
									<?php
									consucorner_render_cat_filter_items($category_terms, array(
										'show_dots'  => true,
										'dot_colors' => $dot_colors,
									));
									?>
								</ul>
							</div>
						<?php endif; ?>

						<?php if ($subcategories) : ?>
							<div class="fp-filter-group ap-filter-group" data-filter-tax="product_cat" data-cc-filter-panel="subcategory">
								<h3 class="ap-filter-section-heading ap-filter-section-heading--blue"><?php esc_html_e('Sub-Category', 'consucorner'); ?></h3>
								<ul class="ap-filter-list ap-filter-list--scroll">
									<?php
									consucorner_render_cat_filter_items($subcategories, array(
										'is_subcategory' => true,
									));
									?>
								</ul>
							</div>
						<?php endif; ?>

						<!-- All product attribute + brand filter groups -->
						<?php foreach ($filter_taxes as $ftx) : ?>
							<div class="fp-filter-group" data-filter-tax="<?php echo esc_attr($ftx['taxonomy']); ?>" data-cc-filter-panel="<?php echo esc_attr($ftx['taxonomy']); ?>">
								<h3 class="ap-filter-section-heading ap-filter-section-heading--blue"><?php echo esc_html($ftx['label']); ?></h3>
								<ul class="ap-filter-list ap-filter-list--scroll">
									<?php foreach ($ftx['terms'] as $term) : ?>
										<?php
										$is_brand_filter = 'product_brand' === $ftx['taxonomy'];
										$brand_logo_url  = ($is_brand_filter && function_exists('cc_get_filter_term_image_url')) ? cc_get_filter_term_image_url($term) : '';
										?>
										<li class="ap-filter-item" data-term-id="<?php echo esc_attr($term->term_id); ?>">
											<label class="ap-filter-label">
												<input type="checkbox"
													class="fp-checkbox"
													data-filter-tax="<?php echo esc_attr($ftx['taxonomy']); ?>"
													data-filter-term="<?php echo esc_attr($term->term_id); ?>"
													value="<?php echo esc_attr($term->slug); ?>" />
												<?php if ($is_brand_filter) : ?>
													<span class="ap-filter-brand-logo" aria-hidden="true">
														<?php if ($brand_logo_url) : ?>
															<img src="<?php echo esc_url($brand_logo_url); ?>" alt="" loading="lazy" />
														<?php else : ?>
															<?php echo esc_html(mb_strtoupper(mb_substr($term->name, 0, 1))); ?>
														<?php endif; ?>
													</span>
												<?php endif; ?>
												<span class="ap-filter-text"><?php echo esc_html($term->name); ?></span>
											</label>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endforeach; ?>

						<div class="cc-filter-sheet-actions">
							<button class="cc-sheet-reset" type="button"><?php esc_html_e('Reset', 'consucorner'); ?></button>
							<button class="cc-sheet-done" type="button"><?php esc_html_e('Done', 'consucorner'); ?></button>
						</div>

						<div class="hero-banner fp-sidebar-banner">
							<?php
							if ( function_exists( 'consucorner_render_hero_banner_slides' ) ) {
								consucorner_render_hero_banner_slides(
									consucorner_get_sidebar_hero_banner_slides( $theme_uri ),
									array(
										'title_class' => 'fp-banner-title',
										'image_class' => 'banner-product-image fp-sidebar-image',
									)
								);
							}
							?>
						</div>
					</aside>

					<div class="fp-products-main">
						<div
							class="fp-products-grid"
							id="fpGrid"
							data-total-pages="<?php echo esc_attr((string) max(1, (int) $initial_query->max_num_pages)); ?>"
							data-current-loaded-page="1">
							<?php
							if (! empty($initial_query->posts)) {
								foreach ($initial_query->posts as $post) {
									echo cc_render_product_card($post->ID); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped inside helper.
								}
							} else {
								echo '<p class="fp-no-results">' . esc_html__('No products found.', 'consucorner') . '</p>';
							}
							?>
						</div>

						<?php
						if (function_exists('cc_render_shop_product_pagination')) {
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
							echo cc_render_shop_product_pagination($paged, (int) $initial_query->max_num_pages);
						}
						?>
					</div>
				</div>
			</div>
		</section>
	</main>
<?php
	get_footer();
	return;
endif;
?>

<main class="page-archive-products-main">
	<section class="shop-page-head shop-page-head--archive-compact" aria-label="<?php esc_attr_e('Category heading', 'consucorner'); ?>">
		<div class="shop-page-head-inner">
			<h1 class="shop-page-title"><?php echo esc_html($category_name); ?></h1>
			<p class="shop-page-breadcrumbs">
				<?php
				$total_crumbs = count($crumbs);
				foreach ($crumbs as $i => $cr) {
					if ($cr['url'] && ! is_wp_error($cr['url'])) {
						echo '<a href="' . esc_url($cr['url']) . '">' . esc_html($cr['label']) . '</a>';
					} else {
						echo esc_html($cr['label']);
					}
					if ($i < $total_crumbs - 1) {
						echo '/';
					}
				}
				?>
			</p>
		</div>
	</section>

	<section class="fp-products-section">
		<div class="fp-products-inner">

			<!-- Filter Bar (top) -->
			<div class="fp-filter-bar ap-filter-bar">
				<div class="fp-filter-bar-left" data-cc-tour="shop-filter-bar">
					<button class="fp-mobile-filter-toggle cc-filter-chip" id="fpFilterToggle" type="button" data-cc-tour="all-filters" data-cc-sheet-target="all" data-cc-sheet-title="<?php esc_attr_e('All filters', 'consucorner'); ?>">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
							<line x1="4" y1="6" x2="20" y2="6" />
							<line x1="8" y1="12" x2="16" y2="12" />
							<line x1="11" y1="18" x2="13" y2="18" />
						</svg>
						<span class="fp-mobile-filter-text"><?php esc_html_e('Filters', 'consucorner'); ?></span>
					</button>

					<?php if (! $hide_specialty_filter_controls) : ?>
						<button class="cc-filter-chip" type="button" data-cc-tour="specialty-filter" data-cc-sheet-target="specialty" data-cc-sheet-title="<?php esc_attr_e('Specialty', 'consucorner'); ?>"><?php esc_html_e('Specialty', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
					<?php endif; ?>

					<?php if ($category_terms && ! $hide_category_filter_controls) : ?>
						<button class="cc-filter-chip" type="button" data-cc-tour="category-filter" data-cc-sheet-target="category" data-cc-sheet-title="<?php esc_attr_e('Category', 'consucorner'); ?>"><?php esc_html_e('Category', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
					<?php endif; ?>

					<?php if (! empty($subcategories)) : ?>
						<button class="cc-filter-chip" type="button" data-cc-sheet-target="subcategory" data-cc-sheet-title="<?php esc_attr_e('Sub-Category', 'consucorner'); ?>"><?php esc_html_e('Sub-Category', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
					<?php endif; ?>

					<?php if (! empty($filter_taxes)) : foreach ($filter_taxes as $ftx) : ?>
							<button class="cc-filter-chip"
								type="button"
								data-cc-sheet-target="<?php echo esc_attr($ftx['taxonomy']); ?>"
								data-cc-sheet-title="<?php echo esc_attr($ftx['label']); ?>"><?php echo esc_html($ftx['label']); ?><span aria-hidden="true">&rsaquo;</span></button>
					<?php endforeach;
					endif; ?>

					<?php if ($max_price > 0) : ?>
						<button class="cc-filter-chip" type="button" data-cc-sheet-target="price" data-cc-sheet-title="<?php esc_attr_e('Price', 'consucorner'); ?>"><?php esc_html_e('Price', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
					<?php endif; ?>
				</div>

				<div class="cc-active-filter-strip" id="ccActiveFilters" aria-live="polite" hidden></div>

				<div class="fp-filter-bar-right">
					<div class="fp-view-toggle" role="group" aria-label="<?php esc_attr_e('Product layout', 'consucorner'); ?>">
						<button type="button" class="fp-view-toggle-btn is-active" data-cc-view="list" aria-pressed="true" aria-label="<?php esc_attr_e('List view', 'consucorner'); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<line x1="8" y1="6" x2="21" y2="6" />
								<line x1="8" y1="12" x2="21" y2="12" />
								<line x1="8" y1="18" x2="21" y2="18" />
								<line x1="3" y1="6" x2="3.01" y2="6" />
								<line x1="3" y1="12" x2="3.01" y2="12" />
								<line x1="3" y1="18" x2="3.01" y2="18" />
							</svg>
						</button>
						<button type="button" class="fp-view-toggle-btn" data-cc-view="grid" aria-pressed="false" aria-label="<?php esc_attr_e('Grid view', 'consucorner'); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<rect x="3" y="3" width="7" height="7" />
								<rect x="14" y="3" width="7" height="7" />
								<rect x="14" y="14" width="7" height="7" />
								<rect x="3" y="14" width="7" height="7" />
							</svg>
						</button>
					</div>
					<a href="#" class="fp-clear-all" id="fpClearAll"><?php esc_html_e('Clear All', 'consucorner'); ?></a>
				</div>
				<div class="cc-filter-dropdown-host" aria-hidden="true"></div>
			</div>

			<?php
			set_query_var(
				'cc_shop_promo_context',
				array(
					'theme_uri'   => $theme_uri,
					'shop_url'    => $shop_url,
					'benefits'    => $shop_promo_benefits,
					'slides'      => $shop_promo_slides,
					'banner_url'  => $shop_promo_banner_url,
					'mode'        => $shop_promo_mode,
					'origins'     => $shop_promo_origins,
				)
			);
			get_template_part('template-parts/shop-promo-section');
			?>

			<!-- Sidebar overlay (mobile) -->
			<div class="fp-sidebar-overlay" id="fpSidebarOverlay"></div>

			<div class="fp-layout" data-cc-sidebar-home>

				<!-- ── Sidebar ── -->
				<aside class="fp-sidebar" id="fpSidebar">
					<div class="cc-filter-sheet-head">
						<span class="cc-filter-sheet-grip" aria-hidden="true"></span>
						<h2 class="cc-filter-sheet-title" id="ccFilterSheetTitle"><?php esc_html_e('Filters', 'consucorner'); ?></h2>
						<button class="cc-filter-sheet-close" id="ccFilterClose" type="button"><?php esc_html_e('Close', 'consucorner'); ?></button>
					</div>

					<div class="cc-filter-panel-menu" aria-label="<?php esc_attr_e('Choose filter type', 'consucorner'); ?>">
						<?php if (! $hide_specialty_filter_controls) : ?>
							<button class="cc-filter-panel-btn" type="button" data-cc-sheet-target="specialty" data-cc-sheet-title="<?php esc_attr_e('Specialty', 'consucorner'); ?>"><?php esc_html_e('Specialty', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
						<?php endif; ?>
						<?php if ($category_terms && ! $hide_category_filter_controls) : ?>
							<button class="cc-filter-panel-btn" type="button" data-cc-sheet-target="category" data-cc-sheet-title="<?php esc_attr_e('Category', 'consucorner'); ?>"><?php esc_html_e('Category', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
						<?php endif; ?>
						<?php if ($subcategories) : ?>
							<button class="cc-filter-panel-btn" type="button" data-cc-sheet-target="subcategory" data-cc-sheet-title="<?php esc_attr_e('Sub-Category', 'consucorner'); ?>"><?php esc_html_e('Sub-Category', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
						<?php endif; ?>
						<?php foreach ($filter_taxes as $ftx) : ?>
							<button class="cc-filter-panel-btn" type="button" data-cc-sheet-target="<?php echo esc_attr($ftx['taxonomy']); ?>" data-cc-sheet-title="<?php echo esc_attr($ftx['label']); ?>"><?php echo esc_html($ftx['label']); ?><span aria-hidden="true">&rsaquo;</span></button>
						<?php endforeach; ?>
						<?php if ($max_price > 0) : ?>
							<button class="cc-filter-panel-btn" type="button" data-cc-sheet-target="price" data-cc-sheet-title="<?php esc_attr_e('Price', 'consucorner'); ?>"><?php esc_html_e('Price', 'consucorner'); ?><span aria-hidden="true">&rsaquo;</span></button>
						<?php endif; ?>
					</div>

					<div class="fp-advanced-filters">
						<h3 class="fp-filters-heading"><?php esc_html_e('Advanced Filters', 'consucorner'); ?></h3>

						<?php if (! $hide_specialty_filter_controls) : ?>
							<!-- Specialty taxonomy filter -->
							<div class="fp-filter-group ap-filter-group" data-filter-tax="specialty" data-cc-filter-panel="specialty">
								<h4 class="fp-filter-group-title ap-filter-section-heading--blue"><?php esc_html_e('Specialty', 'consucorner'); ?></h4>
								<ul class="ap-filter-list ap-filter-list--scroll">
									<?php foreach ($specialty_terms as $term) : ?>
										<?php
										if ($is_specialty_archive && (int) $term->term_id !== (int) $category_id) {
											continue;
										}
										$is_url_checked = ! $is_specialty_archive
											&& isset($url_filters['specialty'])
											&& in_array((int) $term->term_id, $url_filters['specialty'], true);
										?>
										<li class="ap-filter-item<?php echo $is_url_checked ? ' is-selected' : ''; ?>" data-term-id="<?php echo esc_attr($term->term_id); ?>">
											<label class="ap-filter-label">
												<input type="checkbox"
													class="fp-checkbox"
													data-filter-tax="specialty"
													data-filter-term="<?php echo esc_attr($term->term_id); ?>"
													value="<?php echo esc_attr($term->slug); ?>"
													<?php checked($is_specialty_archive || $is_url_checked); ?>
													<?php disabled($is_specialty_archive); ?> />
												<span class="ap-filter-text"><?php echo esc_html($term->name); ?></span>
											</label>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>

						<?php if ($category_terms && ! $hide_category_filter_controls) : ?>
							<div class="fp-filter-group ap-filter-group" data-filter-tax="product_cat" data-cc-filter-panel="category">
								<h4 class="fp-filter-group-title ap-filter-section-heading--blue"><?php esc_html_e('Category', 'consucorner'); ?></h4>
								<ul class="ap-filter-list ap-filter-list--scroll">
									<?php
									consucorner_render_cat_filter_items($category_terms, array(
										'show_dots'  => true,
										'dot_colors' => $dot_colors,
									));
									?>
								</ul>
							</div>
						<?php endif; ?>

						<?php if (! empty($subcategories)) : ?>
							<div class="fp-filter-group ap-filter-group" data-filter-tax="product_cat" data-cc-filter-panel="subcategory">
								<h4 class="fp-filter-group-title ap-filter-section-heading--blue"><?php esc_html_e('Sub-Category', 'consucorner'); ?></h4>
								<ul class="ap-filter-list ap-filter-list--scroll">
									<?php
									consucorner_render_cat_filter_items($subcategories, array(
										'is_subcategory' => true,
									));
									?>
								</ul>
							</div>
						<?php endif; ?>

						<?php foreach ($filter_taxes as $ftx) : ?>
							<div class="fp-filter-group ap-filter-group" data-filter-tax="<?php echo esc_attr($ftx['taxonomy']); ?>" data-cc-filter-panel="<?php echo esc_attr($ftx['taxonomy']); ?>">
								<h4 class="fp-filter-group-title ap-filter-section-heading--blue"><?php echo esc_html($ftx['label']); ?></h4>
								<ul class="ap-filter-list ap-filter-list--scroll">
									<?php foreach ($ftx['terms'] as $term) : ?>
										<?php
										$is_brand_filter = 'product_brand' === $ftx['taxonomy'];
										$brand_logo_url  = ($is_brand_filter && function_exists('cc_get_filter_term_image_url')) ? cc_get_filter_term_image_url($term) : '';
										?>
										<li class="ap-filter-item" data-term-id="<?php echo esc_attr($term->term_id); ?>">
											<label class="ap-filter-label">
												<input type="checkbox"
													class="fp-checkbox"
													data-filter-tax="<?php echo esc_attr($ftx['taxonomy']); ?>"
													data-filter-term="<?php echo esc_attr($term->term_id); ?>"
													value="<?php echo esc_attr($term->slug); ?>" />
												<?php if ($is_brand_filter) : ?>
													<span class="ap-filter-brand-logo" aria-hidden="true">
														<?php if ($brand_logo_url) : ?>
															<img src="<?php echo esc_url($brand_logo_url); ?>" alt="" loading="lazy" />
														<?php else : ?>
															<?php echo esc_html(mb_strtoupper(mb_substr($term->name, 0, 1))); ?>
														<?php endif; ?>
													</span>
												<?php endif; ?>
												<span class="ap-filter-text"><?php echo esc_html($term->name); ?></span>
											</label>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endforeach; ?>

						<?php if ($max_price > 0) : ?>
							<div class="fp-filter-group fp-price-group" data-cc-filter-panel="price">
								<h4 class="fp-filter-group-title"><?php esc_html_e('Price Range', 'consucorner'); ?></h4>
								<div class="cc-price-panel">
									<div class="cc-price-canvas-wrap">
										<canvas class="cc-price-canvas" aria-hidden="true"></canvas>
									</div>
									<div class="cc-price-dual-range" id="fpDualRange">
										<div class="cc-price-track">
											<div class="cc-price-track-fill" id="fpTrackFill"></div>
										</div>
										<input type="range" id="fpMinSlider"
											min="<?php echo esc_attr($min_price); ?>"
											max="<?php echo esc_attr($slider_max); ?>"
											value="<?php echo esc_attr($min_price); ?>"
											step="50" />
										<input type="range" id="fpMaxSlider"
											min="<?php echo esc_attr($min_price); ?>"
											max="<?php echo esc_attr($slider_max); ?>"
											value="<?php echo esc_attr($slider_max); ?>"
											step="50" />
									</div>
									<div class="cc-price-meta">
										<span><?php esc_html_e('Total products:', 'consucorner'); ?> <strong id="fpPriceCount"><?php echo esc_html((string) (int) $initial_price_count); ?></strong></span>
										<span id="fpPriceDisplay">
											<?php
											printf(
												/* translators: 1: min price, 2: max price, 3: currency. */
												esc_html__('Price: %1$s %3$s – %2$s %3$s', 'consucorner'),
												esc_html(number_format_i18n($min_price)),
												esc_html(number_format_i18n($slider_max)),
												esc_html(get_woocommerce_currency())
											);
											?>
										</span>
									</div>
									<div class="cc-price-inputs">
										<input type="number" id="fpMinPriceInput" min="0" max="<?php echo esc_attr($slider_max); ?>" step="1" inputmode="numeric" placeholder="<?php esc_attr_e('Min', 'consucorner'); ?>" value="" />
										<span aria-hidden="true">-</span>
										<input type="number" id="fpMaxPriceInput" min="0" max="<?php echo esc_attr($slider_max); ?>" step="1" inputmode="numeric" placeholder="<?php esc_attr_e('Max', 'consucorner'); ?>" value="<?php echo esc_attr($slider_max); ?>" />
									</div>
								</div>
								<button class="fp-price-filter-btn" type="button" id="fpPriceFilterBtn"><?php esc_html_e('Apply price', 'consucorner'); ?></button>
							</div>
						<?php endif; ?>
					</div>

					<div class="cc-filter-sheet-actions">
						<button class="cc-sheet-reset" type="button"><?php esc_html_e('Reset', 'consucorner'); ?></button>
						<button class="cc-sheet-done" type="button"><?php esc_html_e('Done', 'consucorner'); ?></button>
					</div>

					<!-- Promo banner (slider) -->
					<div class="hero-banner fp-sidebar-banner">
						<?php
						if ( function_exists( 'consucorner_render_hero_banner_slides' ) ) {
							consucorner_render_hero_banner_slides(
								consucorner_get_sidebar_hero_banner_slides( $theme_uri ),
								array(
									'title_class' => 'fp-banner-title',
									'image_class' => 'banner-product-image fp-sidebar-image',
								)
							);
						}
						?>
					</div>
				</aside>

				<!-- ── Products Main ── -->
				<div class="fp-products-main">

					<div class="fp-results-row" style="display:flex;align-items:center;justify-content:space-between;font-family:Manrope,sans-serif;color:#6b7280;font-size:14px;margin-bottom:6px">
						<span id="fpResultsCount"><?php
																			/* translators: %d total products in current category */
																			printf(esc_html__('Showing %d products', 'consucorner'), (int) $initial_query->found_posts);
																			?></span>
					</div>

					<div
						class="fp-products-grid"
						id="fpGrid"
						data-total-pages="<?php echo esc_attr((string) max(1, (int) $initial_query->max_num_pages)); ?>"
						data-current-loaded-page="1">
						<?php
						if (! empty($initial_query->posts)) {
							foreach ($initial_query->posts as $post) {
								echo cc_render_product_card($post->ID); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped inside helper.
							}
						} else {
							echo '<p class="fp-no-results">' . esc_html__('No products found in this category.', 'consucorner') . '</p>';
						}
						?>
					</div>

					<?php
					if (function_exists('cc_render_shop_product_pagination')) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
						echo cc_render_shop_product_pagination($paged, (int) $initial_query->max_num_pages);
					}
					?>

				</div>
			</div>
		</div>
	</section>

</main>

<?php
get_footer();
