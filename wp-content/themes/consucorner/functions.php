<?php

/**
 * ConsuCorner functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

if (! defined('_S_VERSION')) {
	define('_S_VERSION', '2.9.9');
}

if (! defined('CONSUCCORNER_GET_QUOTE_FORMINATOR_ID')) {
	define('CONSUCCORNER_GET_QUOTE_FORMINATOR_ID', 10060);
}

if (! defined('CONSUCCORNER_GET_QUOTE_PRICE_THRESHOLD')) {
	define('CONSUCCORNER_GET_QUOTE_PRICE_THRESHOLD', 100000);
}

/**
 * Return the Forminator form ID used by the Get A Quote product flow.
 *
 * Change CONSUCCORNER_GET_QUOTE_FORMINATOR_ID above when the quote form changes.
 *
 * @return int
 */
function cc_get_quote_forminator_form_id()
{
	return absint(apply_filters('consucorner_get_quote_forminator_form_id', CONSUCCORNER_GET_QUOTE_FORMINATOR_ID));
}

/**
 * Return the minimum product price that switches products into quote-only mode.
 *
 * Change CONSUCCORNER_GET_QUOTE_PRICE_THRESHOLD above when the quote threshold changes.
 *
 * @return float
 */
function cc_get_quote_price_threshold()
{
	return max(0, (float) apply_filters('consucorner_get_quote_price_threshold', CONSUCCORNER_GET_QUOTE_PRICE_THRESHOLD));
}

/**
 * Determine whether a WooCommerce product should use the Get A Quote flow.
 *
 * @param WC_Product $product Product object.
 * @return bool
 */
function cc_is_quote_product($product)
{
	if (! is_a($product, 'WC_Product')) {
		return false;
	}

	return (float) $product->get_price() >= cc_get_quote_price_threshold();
}

/**
 * Upper bound for price-filterable products (_price must be strictly below this).
 *
 * @return float
 */
function cc_get_price_filter_ceiling()
{
	return cc_get_quote_price_threshold();
}

/**
 * meta_query clause excluding Get Quote / quote-tier products from price filtering.
 *
 * @return array<string, mixed>
 */
function cc_price_filter_exclude_quote_meta_query()
{
	return array(
		'key'     => '_price',
		'value'   => cc_get_price_filter_ceiling(),
		'type'    => 'DECIMAL(20,6)',
		'compare' => '<',
	);
}

/**
 * Cap a price-filter max value to the highest purchasable price (below quote threshold).
 *
 * @param float $max_price Requested max price.
 * @return float
 */
function cc_clamp_price_filter_max($max_price)
{
	$max_price = (float) $max_price;
	if ($max_price <= 0) {
		return 0.0;
	}

	$ceiling = cc_get_price_filter_ceiling();
	if ($ceiling <= 0) {
		return $max_price;
	}

	$purchasable_max = max(0.0, $ceiling - 0.01);
	return min($max_price, $purchasable_max);
}

/**
 * Quote-only products must never enter the WooCommerce cart.
 *
 * Archive cards use a modal trigger, but this also protects direct URLs,
 * stale cached markup, and any third-party/custom add-to-cart path.
 *
 * @param bool $passed     Whether validation passed.
 * @param int  $product_id Product ID being added.
 * @return bool
 */
function consucorner_block_quote_product_cart_add($passed, $product_id)
{
	$product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;

	if ($product && cc_is_quote_product($product)) {
		if (function_exists('wc_add_notice')) {
			wc_add_notice(__('Please request a quote for this product instead of adding it to the cart.', 'consucorner'), 'notice');
		}

		return false;
	}

	return $passed;
}
add_filter('woocommerce_add_to_cart_validation', 'consucorner_block_quote_product_cart_add', 10, 2);

/**
 * Remove any quote-only products already present in the cart session.
 *
 * This cleans up items added before the quote-flow guard was introduced and
 * keeps mini-cart, cart icon, and cart page totals aligned with the rule.
 *
 * @param WC_Cart $cart Cart instance.
 */
function consucorner_remove_quote_products_from_cart($cart)
{
	if (! is_a($cart, 'WC_Cart') || (is_admin() && ! wp_doing_ajax())) {
		return;
	}

	static $running = false;
	if ($running) {
		return;
	}

	$running = true;

	foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
		$product = isset($cart_item['data']) && is_a($cart_item['data'], 'WC_Product')
			? $cart_item['data']
			: null;

		if ($product && cc_is_quote_product($product)) {
			$cart->remove_cart_item($cart_item_key);
		}
	}

	$running = false;
}
add_action('woocommerce_cart_loaded_from_session', 'consucorner_remove_quote_products_from_cart', 20);
add_action('woocommerce_before_calculate_totals', 'consucorner_remove_quote_products_from_cart', 5);

/**
 * Enforce a minimum order quantity for products sold only in bulk.
 *
 * When a product has bulk pricing tiers enabled, single-piece (or otherwise
 * below-minimum) purchases are blocked, unless the requested quantity exactly
 * matches an active Offer Deal — a separate, intentionally smaller bundle.
 *
 * @param bool $passed     Whether validation currently passes.
 * @param int  $product_id Product being added.
 * @param int  $quantity   Requested quantity.
 * @return bool
 */
function consucorner_enforce_bulk_min_qty($passed, $product_id, $quantity)
{
	if (! $passed) {
		return $passed;
	}

	// Bundle builder adds pool products at whatever qty the customer picked;
	// bulk-only minimums don't apply to those lines.
	if (function_exists('cc_bundles_is_adding') && cc_bundles_is_adding()) {
		return $passed;
	}

	$product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
	if (! $product || ! function_exists('cc_get_product_bulk_min_qty')) {
		return $passed;
	}

	$min_qty = cc_get_product_bulk_min_qty($product);
	$blocked = false;

	if ($min_qty >= 2 && (int) $quantity < $min_qty) {
		$deal    = function_exists('cc_offers_get_product_deal') ? cc_offers_get_product_deal($product) : null;
		$blocked = ! ($deal && (int) $quantity >= (int) $deal['qty']);
	}

	if (! $blocked) {
		return $passed;
	}

	if (function_exists('wc_add_notice')) {
		wc_add_notice(
			sprintf(
				/* translators: %d: minimum bulk quantity */
				__('This product is sold in bulk only. Please add at least %d units.', 'consucorner'),
				$min_qty
			),
			'error'
		);
	}

	return false;
}
add_filter('woocommerce_add_to_cart_validation', 'consucorner_enforce_bulk_min_qty', 10, 3);

/**
 * Mirror consucorner_enforce_bulk_min_qty() for the WooCommerce cart page's
 * native "Update cart" quantity inputs (a separate validation hook from
 * add-to-cart).
 *
 * @param bool   $passed        Whether validation currently passes.
 * @param string $cart_item_key Cart item key being updated.
 * @param array  $values        Cart item values.
 * @param int    $quantity      Requested new quantity.
 * @return bool
 */
function consucorner_enforce_bulk_min_qty_on_update($passed, $cart_item_key, $values, $quantity)
{
	if (! $passed || ! isset($values['data']) || ! is_a($values['data'], 'WC_Product')) {
		return $passed;
	}

	$product = $values['data'];
	$min_qty = function_exists('cc_get_product_bulk_min_qty') ? cc_get_product_bulk_min_qty($product) : 0;

	if ($min_qty < 2 || (int) $quantity >= $min_qty) {
		return $passed;
	}

	$deal = function_exists('cc_offers_get_product_deal') ? cc_offers_get_product_deal($product) : null;
	if ($deal && (int) $quantity >= (int) $deal['qty']) {
		return $passed;
	}

	if (function_exists('wc_add_notice')) {
		wc_add_notice(
			sprintf(
				/* translators: %d: minimum bulk quantity */
				__('This product is sold in bulk only. Please keep at least %d units, or remove it from the cart.', 'consucorner'),
				$min_qty
			),
			'error'
		);
	}

	return false;
}
add_filter('woocommerce_update_cart_validation', 'consucorner_enforce_bulk_min_qty_on_update', 10, 4);

/**
 * Apply per-product pricing rules to cart line items: the Offer Deal
 * (deal unit price once qty reaches the deal threshold) and/or the
 * quantity-range Bulk tiers, always charging whichever per-unit
 * price is cheapest for the current line quantity.
 *
 * @param WC_Cart $cart Cart object.
 * @return void
 */
function cc_apply_offer_deal_pricing($cart)
{
	if (is_admin() && ! wp_doing_ajax()) {
		return;
	}

	if (! $cart instanceof WC_Cart || ! function_exists('cc_get_effective_unit_price')) {
		return;
	}

	static $running = false;
	if ($running) {
		return;
	}

	$running = true;

	foreach ($cart->get_cart() as $cart_item) {
		// Bundle lines are priced as a flat P/N group by cc_bundles_apply_group_pricing();
		// skip them here so this per-product logic doesn't override that price.
		if (! empty($cart_item['cc_bundle_instance'])) {
			continue;
		}

		$product = isset($cart_item['data']) && is_a($cart_item['data'], 'WC_Product')
			? $cart_item['data']
			: null;

		if (! $product) {
			continue;
		}

		$catalog  = wc_get_product($product->get_id());
		$pricing  = cc_get_effective_unit_price($product, (int) $cart_item['quantity'], array('require_stock' => false));

		if ($pricing) {
			$product->set_price((float) $pricing['price']);
			continue;
		}

		if ($catalog instanceof WC_Product) {
			$product->set_price((float) $catalog->get_price('edit'));
		}
	}

	$running = false;
}
add_action('woocommerce_before_calculate_totals', 'cc_apply_offer_deal_pricing', 20);

/**
 * Product IDs in the same specialty for single-product "Often Ordered With".
 *
 * Falls back to WooCommerce related products when no specialty terms exist.
 *
 * @param int $product_id Current product ID.
 * @param int $limit      Maximum number of related products.
 * @return int[]
 */
function cc_get_specialty_related_product_ids($product_id, $limit = 10)
{
	$product_id = absint($product_id);
	$limit      = max(1, absint($limit));

	if (! $product_id) {
		return array();
	}

	if (! taxonomy_exists('specialty')) {
		return array_map('absint', wc_get_related_products($product_id, $limit));
	}

	$specialty_terms = wp_get_post_terms($product_id, 'specialty', array('fields' => 'ids'));
	if (is_wp_error($specialty_terms) || empty($specialty_terms)) {
		return array_map('absint', wc_get_related_products($product_id, $limit));
	}

	$args = array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => $limit,
		'post__not_in'        => array($product_id),
		'ignore_sticky_posts' => true,
		'fields'              => 'ids',
		'meta_key'            => 'total_sales',
		'orderby'             => 'meta_value_num',
		'order'               => 'DESC',
		'tax_query'           => array(
			array(
				'taxonomy'         => 'specialty',
				'field'            => 'term_id',
				'terms'            => array_map('absint', $specialty_terms),
				'include_children' => true,
			),
		),
	);

	if (function_exists('cc_begin_product_stock_order')) {
		cc_begin_product_stock_order();
	}

	$query = new WP_Query($args);

	if (function_exists('cc_end_product_stock_order')) {
		cc_end_product_stock_order();
	}

	if (! empty($query->posts)) {
		return array_map('absint', $query->posts);
	}

	return array_map('absint', wc_get_related_products($product_id, $limit));
}

/* ============================================================
   SVG UPLOAD SUPPORT
   Allow administrators and editors to upload SVG/SVGZ files
   via the WordPress Media Library (used for category icons etc.)
   ============================================================ */

/**
 * Add SVG/SVGZ to the list of allowed upload MIME types.
 * Restricted to users who can manage options (admins) and
 * users who can upload files (editors/authors) separately.
 *
 * @param array $mimes Allowed MIME types.
 * @return array
 */
function cc_allow_svg_upload($mimes)
{
	if (current_user_can('upload_files')) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';
	}
	return $mimes;
}
add_filter('upload_mimes', 'cc_allow_svg_upload');

/**
 * Fix MIME-type detection for SVG files — WordPress core uses
 * finfo/getimagesize which cannot identify SVG as an image, so
 * we bypass the ext/type mismatch check for the svg extension.
 *
 * @param array       $data     {type, ext, proper_filename} data.
 * @param string      $file     Full path to the file.
 * @param string      $filename The name of the file.
 * @param array|null  $mimes    Allowed MIME types.
 * @param string|bool $real_mime Real MIME type detected by PHP.
 * @return array
 */
function cc_fix_svg_mime_detection($data, $file, $filename, $mimes, $real_mime = false)
{
	if (! $data['type']) {
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		if ('svg' === $ext || 'svgz' === $ext) {
			$data['type'] = 'image/svg+xml';
			$data['ext']  = $ext;
		}
	}
	return $data;
}
add_filter('wp_check_filetype_and_ext', 'cc_fix_svg_mime_detection', 10, 5);

/**
 * Render SVG files as <img> tags in the Media Library grid and list views
 * so admins can see the actual icon instead of a broken thumbnail.
 */
function cc_svg_media_library_styles()
{
	echo '<style>
		.attachment-266x266[src$=".svg"],
		.attachment-266x266[src$=".svgz"] {
			width: 100% !important;
			height: auto !important;
			object-fit: contain;
		}
		img[src$=".svg"],
		img[src$=".svgz"] {
			max-width: 100%;
			height: auto;
		}
	</style>';
}
add_action('admin_head', 'cc_svg_media_library_styles');

/* ============================================================
   THEME SETUP
   ============================================================ */
function consucorner_setup()
{
	load_theme_textdomain('consucorner', get_template_directory() . '/languages');
	add_theme_support('automatic-feed-links');
	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	register_nav_menus(
		array(
			'menu-1'              => esc_html__('Primary', 'consucorner'),
			'explore-important'   => esc_html__('Explore - Important', 'consucorner'),
			'explore-help'        => esc_html__('Explore - Help', 'consucorner'),
			'footer-explore'   => esc_html__('Footer - Explore', 'consucorner'),
			'footer-services'  => esc_html__('Footer - Services', 'consucorner'),
			'footer-legal'     => esc_html__('Footer - Legal', 'consucorner'),
			'footer-social'    => esc_html__('Footer - Social Icons', 'consucorner'),
		)
	);
	add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));
	add_theme_support('woocommerce');
	add_theme_support('custom-background', apply_filters('consucorner_custom_background_args', array('default-color' => 'ffffff', 'default-image' => '')));
	add_theme_support('customize-selective-refresh-widgets');
	add_theme_support('custom-logo', array('height' => 250, 'width' => 250, 'flex-width' => true, 'flex-height' => true));
	add_image_size('cc_shop_promo_banner', 1208, 390, true);
}
add_action('after_setup_theme', 'consucorner_setup');

function consucorner_content_width()
{
	$GLOBALS['content_width'] = apply_filters('consucorner_content_width', 640);
}
add_action('after_setup_theme', 'consucorner_content_width', 0);

function consucorner_widgets_init()
{
	register_sidebar(array(
		'name'          => esc_html__('Sidebar', 'consucorner'),
		'id'            => 'sidebar-1',
		'description'   => esc_html__('Add widgets here.', 'consucorner'),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	));
}
add_action('widgets_init', 'consucorner_widgets_init');

/* ============================================================
   HELPER: detect WooCommerce My Account pages
   ============================================================ */
function consucorner_is_account_page()
{
	if (! function_exists('is_account_page')) {
		return false;
	}
	return is_account_page();
}

/* ============================================================
   HELPER: render clickable page-head breadcrumbs
   ============================================================ */
function consucorner_get_breadcrumb_url($label)
{
	$label = trim(wp_strip_all_tags(html_entity_decode((string) $label, ENT_QUOTES, get_bloginfo('charset'))));
	$key   = strtolower(preg_replace('/\s+/', ' ', $label));

	$urls = array(
		'home'                 => home_url('/'),
		'shop'                 => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'),
		'cart'                 => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'),
		'checkout'             => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/'),
		'login or register'    => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/'),
		'my account'           => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/'),
		'about'                => home_url('/about/'),
		'contact'              => home_url('/contact/'),
		'contact us'           => home_url('/contact/'),
		'faq'                  => home_url('/faq/'),
		'help'                 => home_url('/help/'),
		'shop instruments'     => home_url('/shop-instruments/'),
		'privacy policy'       => home_url('/privacy-policy/'),
		'privacy & policy'     => home_url('/privacy-policy/'),
		'terms'                => home_url('/terms-and-conditions/'),
		'terms and conditions' => home_url('/terms-and-conditions/'),
	);

	if (isset($urls[$key])) {
		return $urls[$key] ? (string) $urls[$key] : '';
	}

	$page = get_page_by_path(sanitize_title($label));
	if ($page instanceof WP_Post) {
		$permalink = get_permalink($page);
		return $permalink ? (string) $permalink : '';
	}

	return '';
}

function consucorner_render_breadcrumbs($breadcrumbs, $current_url = '')
{
	$text  = wp_strip_all_tags(html_entity_decode((string) $breadcrumbs, ENT_QUOTES, get_bloginfo('charset')));
	$parts = preg_split('#\s*/\s*#', $text);
	$parts = array_values(array_filter(array_map('trim', (array) $parts)));

	if (empty($parts)) {
		return;
	}

	$last_index = count($parts) - 1;
	$output     = array();

	foreach ($parts as $index => $label) {
		$url = consucorner_get_breadcrumb_url($label);

		if ('' === $url && $index === $last_index && '' !== $current_url) {
			$url = $current_url;
		}

		$output[] = '' !== $url
			? '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>'
			: esc_html($label);
	}

	echo wp_kses_post(implode('<span class="breadcrumb-separator" aria-hidden="true">/</span>', $output));
}

/* ============================================================
   ENQUEUE SCRIPTS & STYLES  (page-specific)
   ============================================================ */
function consucorner_scripts()
{
	$uri = get_template_directory_uri();
	$dir = get_template_directory();

	// Common CSS loaded on every page  (responsive.css is NOT here — it is
	// appended last in each branch so it always loads after page-specific CSS,
	// matching the CSS cascade order used in the static HTML files).
	$common_css = array('fonts', 'variables', 'base', 'header', 'mega-menu', 'drawer', 'mini-cart', 'footer', 'auth-modal');

	// Common JS loaded on every page.
	$common_js = array('mega-menu-dynamic', 'cart-badge', 'drawer', 'mini-cart', 'auth-modal', 'site-search', 'consu-tracker');

	// File-mtime version helper — every save of the underlying asset
	// forces browsers to download the fresh copy (no stale cache).
	$asset_ver = function ($path) use ($dir) {
		$full = $dir . $path;
		return file_exists($full) ? (string) filemtime($full) : _S_VERSION;
	};

	// Helper to enqueue a list of CSS handles.
	$load_css = function ($handles) use ($uri, $asset_ver) {
		foreach ($handles as $h) {
			$rel = '/assets/css/' . $h . '.css';
			wp_enqueue_style('consucorner-' . $h, $uri . $rel, array(), $asset_ver($rel));
		}
	};

	// Helper to enqueue a list of JS handles.
	$load_js = function ($handles) use ($uri, $asset_ver) {
		foreach ($handles as $h) {
			$rel = '/assets/js/' . $h . '.js';
			wp_enqueue_script('consucorner-' . $h, $uri . $rel, array(), $asset_ver($rel), true);
		}
	};

	// Always load common assets.
	$load_css($common_css);

	/* ── Front page ─────────────────────────────────────────── */
	if (is_front_page()) {
		$load_css(array('hero', 'sections', 'cards', 'shop-page', 'get-quote-modal', 'responsive'));
		$load_js(array_merge($common_js, array(
			'slider',
			'new-arrival-slider',
			'bestsellers-slider',
			'recommended-slider',
			'testimonials-slider',
			'browse-specialty-ajax',
			'recommended-for-you-ajax',
			'bestsellers-overall-ajax',
			'fp-filter',
			'product-card-click',
			'get-quote-modal',
		)));

		$img_uri = $uri . '/assets/images/';
		$product_placeholder = function_exists('consucorner_get_product_placeholder_image_url')
			? consucorner_get_product_placeholder_image_url()
			: $img_uri . rawurlencode('consucorner icon-logo.jpg');

		wp_localize_script('consucorner-browse-specialty-ajax', 'consuBrowseData', array(
			'ajaxUrl'          => admin_url('admin-ajax.php'),
			'nonce'            => wp_create_nonce('consucorner_browse_nonce'),
			'placeholderImage' => $product_placeholder,
			'saveIcon'         => $img_uri . 'save-product-icon.svg',
			'viewIcon'         => $img_uri . 'Show-icon.svg',
			'saveIconFallback' => home_url('/wp-content/themes/consucorner/assets/images/save-product-icon.svg'),
			'viewIconFallback' => home_url('/wp-content/themes/consucorner/assets/images/Show-icon.svg'),
		));

		wp_localize_script('consucorner-recommended-for-you-ajax', 'consuRecommendedData', array(
			'ajaxUrl'          => admin_url('admin-ajax.php'),
			'nonce'            => wp_create_nonce('consucorner_recommended_nonce'),
			'placeholderImage' => $product_placeholder,
			'saveIcon'         => $img_uri . 'save-product-icon.svg',
			'viewIcon'         => $img_uri . 'Show-icon.svg',
			'saveIconFallback' => home_url('/wp-content/themes/consucorner/assets/images/save-product-icon.svg'),
			'viewIconFallback' => home_url('/wp-content/themes/consucorner/assets/images/Show-icon.svg'),
		));

		wp_localize_script('consucorner-bestsellers-overall-ajax', 'consuBestsellersData', array(
			'ajaxUrl'          => admin_url('admin-ajax.php'),
			'nonce'            => wp_create_nonce('consucorner_bestsellers_nonce'),
			'placeholderImage' => $product_placeholder,
			'saveIcon'         => $img_uri . 'save-product-icon.svg',
			'viewIcon'         => $img_uri . 'Show-icon.svg',
			'saveIconFallback' => home_url('/wp-content/themes/consucorner/assets/images/save-product-icon.svg'),
			'viewIconFallback' => home_url('/wp-content/themes/consucorner/assets/images/Show-icon.svg'),
		));

		/* ── Cart ───────────────────────────────────────────────── */
	} elseif (function_exists('is_cart') && is_cart()) {
		$load_css(array('shop-page', 'cart', 'responsive'));
		$load_js(array_merge($common_js, array('cart')));

		wp_localize_script(
			'consucorner-cart',
			'consuCartData',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('cc_cart_nonce'),
			)
		);

		/* ── Thank You / Order confirmation ─────────────────────── */
	} elseif (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
		$load_css(array('shop-page', 'thank-you', 'responsive'));
		$load_js($common_js);

		/* ── Checkout ───────────────────────────────────────────── */
	} elseif (function_exists('is_checkout') && is_checkout()) {
		$load_css(array('shop-page', 'checkout', 'responsive'));
		$load_js(array_merge($common_js, array('checkout')));

		if (function_exists('consucorner_get_checkout_phone_strings')) {
			wp_localize_script(
				'consucorner-checkout',
				'ccCheckoutPhone',
				consucorner_get_checkout_phone_strings()
			);
		}

		/* ── My Account / Profile ───────────────────────────────── */
	} elseif (consucorner_is_account_page()) {
		$load_css(array('shop-page', 'responsive', 'profile'));
		$load_js(array_merge($common_js, array('profile')));

		$account_user = wp_get_current_user();
		$member_since = '';
		if ($account_user && ! empty($account_user->user_registered)) {
			$member_since = mysql2date('F Y', $account_user->user_registered);
		}
		wp_localize_script('consucorner-profile', 'consuProfileData', array(
			'ajaxUrl'      => admin_url('admin-ajax.php'),
			'nonce'        => wp_create_nonce('consucorner_profile_nonce'),
			'couponNonce'  => wp_create_nonce('cc-apply-coupon'),
			'logoutUrl'    => function_exists('wc_logout_url') ? wc_logout_url(home_url('/')) : wp_logout_url(home_url('/')),
			'userId'       => is_user_logged_in() ? $account_user->ID : 0,
			'username'     => is_user_logged_in() ? $account_user->user_login : '',
			'displayName'  => is_user_logged_in() ? $account_user->display_name : '',
			'email'        => is_user_logged_in() ? $account_user->user_email : '',
			'firstName'    => is_user_logged_in() ? $account_user->first_name : '',
			'lastName'     => is_user_logged_in() ? $account_user->last_name : '',
			'avatarUrl'    => is_user_logged_in() ? consucorner_get_user_profile_avatar_url($account_user->ID, 120) : '',
			'memberSince'  => $member_since,
			'myAccountUrl' => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/'),
			'shopUrl'      => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'),
			'orderLinkArgs' => function_exists('consucorner_profile_order_link_query_args') ? consucorner_profile_order_link_query_args() : array('order' => 'cc_order', 'key' => 'cc_key'),
		));

		/* ── Shop / Category / Tag ──────────────────────────────── */
	} elseif (
		(function_exists('is_shop') && is_shop()) ||
		(function_exists('is_product_category') && is_product_category()) ||
		(function_exists('is_product_tag') && is_product_tag()) ||
		(function_exists('is_tax') && is_tax('specialty'))
	) {
		$shop_archive_css = (function_exists('is_shop') && is_shop())
			? array('hero', 'sections', 'cards', 'shop-page', 'ab-shop-promo', 'category-archive', 'all-products', 'get-quote-modal', 'responsive')
			: array('hero', 'sections', 'cards', 'shop-page', 'ab-shop-promo', 'category-archive', 'get-quote-modal', 'responsive');
		$load_css($shop_archive_css);
		$shop_archive_js = array_merge($common_js, array('category-filter', 'get-quote-modal', 'ab-shop-promo'));
		$load_js($shop_archive_js);

		// Provide AJAX context to the category-filter script.
		$queried = is_tax() || (function_exists('is_product_category') && is_product_category()) ? get_queried_object() : null;
		$cat_id  = ($queried && ! is_wp_error($queried) && isset($queried->term_id)) ? (int) $queried->term_id : 0;
		$cat_tax = ($queried && ! is_wp_error($queried) && ! empty($queried->taxonomy)) ? sanitize_key($queried->taxonomy) : 'product_cat';
		$locked_filters     = array();
		$auto_fetch_on_load = false;
		$is_product_cat_archive = function_exists('is_product_category') && is_product_category();
		if ('specialty' === $cat_tax && $cat_id > 0) {
			$locked_filters['specialty'] = array($cat_id);
			$auto_fetch_on_load          = true;
		} elseif ($is_product_cat_archive && $cat_id > 0 && 'product_cat' === $cat_tax) {
			$auto_fetch_on_load = true;
		}

		// Detect whether URL-driven filters are active so JS can skip the first-load AJAX fetch.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$url_filters             = array();
		$url_filters_active_flag = false;
		if (function_exists('cc_parse_url_filters')) {
			$url_filters             = cc_parse_url_filters();
			$url_filters_active_flag = ! empty($url_filters);
		}
		if (! $url_filters_active_flag) {
			$url_filters_active_flag = isset($_GET['sort']) || isset($_GET['min_price']) || isset($_GET['max_price']);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		// When SSR has already rendered filtered products, skip the first-load AJAX fetch.
		$auto_fetch_on_load = $auto_fetch_on_load && ! $url_filters_active_flag;

		$cat_min_price = 0;
		$cat_max_price = 0;
		$price_buckets = array();
		if ($cat_id && function_exists('cc_get_taxonomy_product_ids')) {
			$ids = cc_get_taxonomy_product_ids($cat_tax, array($cat_id));
			if ($ids) {
				$range         = cc_get_category_price_range($ids);
				$cat_min_price = isset($range['min']) ? (float) $range['min'] : 0;
				$cat_max_price = isset($range['max']) ? (float) $range['max'] : 0;
				if (function_exists('cc_get_price_buckets') && $cat_max_price > $cat_min_price) {
					$price_buckets = cc_get_price_buckets($ids, $cat_min_price, $cat_max_price);
				}
			}
		} elseif (function_exists('is_shop') && is_shop() && function_exists('cc_get_category_price_range')) {
			$ids = get_posts(array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			));
			if ($ids) {
				$all_ids       = array_map('absint', $ids);
				$range         = cc_get_category_price_range($all_ids);
				$cat_min_price = isset($range['min']) ? (float) $range['min'] : 0;
				$cat_max_price = isset($range['max']) ? (float) $range['max'] : 0;
				if (function_exists('cc_get_price_buckets') && $cat_max_price > $cat_min_price) {
					$price_buckets = cc_get_price_buckets($all_ids, $cat_min_price, $cat_max_price);
				}
			}
		}

		$archive_parent_term_id = ($is_product_cat_archive && 'product_cat' === $cat_tax && $cat_id > 0) ? $cat_id : 0;
		$initial_price_count = 0;
		$initial_count_args  = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array('relation' => 'AND'),
		);
		if ($cat_id) {
			$initial_count_args['tax_query'][] = array(
				'taxonomy'         => $cat_tax,
				'field'            => 'term_id',
				'terms'            => $cat_id,
				'include_children' => true,
			);
		}
		if (! empty($locked_filters)) {
			foreach ($locked_filters as $tax => $term_ids) {
				$initial_count_args['tax_query'][] = array(
					'taxonomy' => $tax,
					'field'    => 'term_id',
					'terms'    => array_map('absint', (array) $term_ids),
					'operator' => 'IN',
				);
			}
		}
		if (function_exists('cc_parse_url_filters')) {
			foreach (cc_parse_url_filters() as $tax => $term_ids) {
				if ('product_cat' === $tax && function_exists('cc_split_product_cat_filter_ids')) {
					$split_product_cats = cc_split_product_cat_filter_ids($term_ids);
					foreach (array('top', 'child') as $bucket) {
						if (empty($split_product_cats[$bucket])) {
							continue;
						}
						$initial_count_args['tax_query'][] = array(
							'taxonomy'         => 'product_cat',
							'field'            => 'term_id',
							'terms'            => $split_product_cats[$bucket],
							'operator'         => 'IN',
							'include_children' => true,
						);
					}
					continue;
				}

				$initial_count_args['tax_query'][] = array(
					'taxonomy' => $tax,
					'field'    => 'term_id',
					'terms'    => array_map('absint', (array) $term_ids),
					'operator' => 'IN',
				);
			}
		}
		if (! empty($_GET['s'])) {
			$initial_count_args['s'] = sanitize_text_field(wp_unslash($_GET['s']));
		}
		if (function_exists('cc_price_filter_exclude_quote_meta_query')) {
			if (! isset($initial_count_args['meta_query'])) {
				$initial_count_args['meta_query'] = array();
			}
			$initial_count_args['meta_query'][] = cc_price_filter_exclude_quote_meta_query();
		}
		cc_begin_product_stock_order();
		$initial_price_count = count((array) get_posts($initial_count_args));
		cc_end_product_stock_order();

		wp_localize_script('consucorner-category-filter', 'consuCategoryFilter', array(
			'ajaxUrl'              => admin_url('admin-ajax.php'),
			'nonce'                => wp_create_nonce('consucorner_category_filter'),
			'categoryId'           => $cat_id,
			'categoryTaxonomy'     => $cat_tax,
			'isProductCatArchive'  => $is_product_cat_archive,
			'archiveParentTermId'  => $archive_parent_term_id,
			'currency'             => get_woocommerce_currency(),
			'perPage'              => 12,
			'priceMinInitial'      => $cat_min_price,
			'priceMaxInitial'      => $cat_max_price,
			'initialPriceCount'    => $initial_price_count,
			'priceBuckets'         => $price_buckets,
			'priceFilterCeiling' => function_exists('cc_get_price_filter_ceiling') ? cc_get_price_filter_ceiling() : 0,
			'searchTerm'           => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
			'lockedFilters'        => $locked_filters,
			'urlFilters'           => $url_filters,
			'autoFetchOnLoad'      => $auto_fetch_on_load,
			'urlFiltersActive'     => $url_filters_active_flag,
		));

		/* ── Single Product ─────────────────────────────────────── */
	} elseif (function_exists('is_singular') && is_singular('product')) {
		// CSS order matches single-product.html: hero → sections → cards → shop-page → single-product → responsive (last).
		$load_css(array('hero', 'sections', 'cards', 'shop-page', 'single-product', 'get-quote-modal', 'responsive'));
		// JS order matches single-product.html: mega-menu, mini-cart, slider, shop-sections-slider, accordion, single-product.
		$load_js(array_merge($common_js, array('slider', 'shop-sections-slider', 'accordion', 'product-card-click', 'get-quote-modal', 'single-product')));

		$sp_product = function_exists('wc_get_product') ? wc_get_product(get_queried_object_id()) : null;
		if ($sp_product instanceof WC_Product) {
			$sp_script_deps = array('jquery');
			if ($sp_product->is_type('variable')) {
				wp_enqueue_script('wc-add-to-cart-variation');
				$sp_script_deps[] = 'wc-add-to-cart-variation';
			}

			$sp_js_rel = '/assets/js/single-product.js';
			wp_dequeue_script('consucorner-single-product');
			wp_deregister_script('consucorner-single-product');
			wp_enqueue_script(
				'consucorner-single-product',
				$uri . $sp_js_rel,
				$sp_script_deps,
				$asset_ver($sp_js_rel),
				true
			);

			wp_localize_script(
				'consucorner-single-product',
				'ccSingleProduct',
				array(
					'checkoutUrl'     => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/'),
					'parentId'        => $sp_product->get_id(),
					'parentPriceHtml' => $sp_product->get_price_html(),
					'isVariable'      => $sp_product->is_type('variable'),
				)
			);
		}

		/* ── Blog Archive Page Template ─────────────────────────── */
	} elseif (is_page_template('page-archive-posts.php')) {
		$load_css(array('blog', 'responsive'));
		$load_js(array_merge($common_js, array('slider', 'shop-sections-slider')));

		/* ── Shop Instruments Page Template ─────────────────────── */
	} elseif (is_page_template('page-shop-instruments.php')) {
		$load_css(array('hero', 'sections', 'cards', 'shop-page', 'category-archive', 'get-quote-modal', 'responsive'));
		$load_js(array_merge($common_js, array('slider', 'shop-sections-slider', 'new-arrival-slider', 'bestsellers-slider', 'recommended-slider', 'product-card-click', 'get-quote-modal')));

		/* ── Shop Specialty Page Template ───────────────────────── */
	} elseif (is_page_template('page-shop-specialty.php')) {
		$load_css(array('hero', 'sections', 'cards', 'shop-page', 'category-archive', 'get-quote-modal', 'responsive'));
		$load_js(array_merge($common_js, array('slider', 'shop-sections-slider', 'new-arrival-slider', 'bestsellers-slider', 'recommended-slider', 'product-card-click', 'get-quote-modal')));

		/* ── Offers Page Template ───────────────────────────────── */
	} elseif (is_page_template('page-offers.php')) {
		$load_css(array('hero', 'sections', 'cards', 'shop-page', 'category-archive', 'offers-page', 'get-quote-modal', 'responsive'));
		$load_js(array_merge($common_js, array('product-card-click', 'get-quote-modal', 'offers-banner-slider')));

		/* ── Bundles Page Template ──────────────────────────────── */
	} elseif (is_page_template('page-bundles.php')) {
		$load_css(array('hero', 'sections', 'cards', 'shop-page', 'category-archive', 'bundles-page', 'get-quote-modal', 'responsive'));
		$load_js(array_merge($common_js, array('product-card-click', 'get-quote-modal', 'bundle-builder')));

		wp_localize_script(
			'consucorner-bundle-builder',
			'ccBundleBuilderData',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('cc_bundle_add_to_cart'),
			)
		);

		/* ── Search Results ─────────────────────────────────────── */
	} elseif (is_search()) {
		$load_css(array('cards', 'shop-page', 'search', 'get-quote-modal', 'responsive'));
		$load_js(array_merge($common_js, array('product-card-click', 'get-quote-modal')));

		/* ── Blog Archive ───────────────────────────────────────── */
	} elseif (is_home() || is_archive()) {
		$load_css(array('blog', 'responsive'));
		$load_js($common_js);

		/* ── Single Post ────────────────────────────────────────── */
	} elseif (is_singular('post')) {
		$load_css(array('blog', 'responsive'));
		$load_js(array_merge($common_js, array('accordion')));

		/* ── About ──────────────────────────────────────────────── */
	} elseif (is_page('about')) {
		// CSS order matches about.html exactly: shop-page → about → responsive (last).
		$load_css(array('shop-page', 'about', 'responsive'));
		// JS order matches about.html: mega-menu, mini-cart, slider, shop-sections-slider, auth-modal.
		$load_js(array_merge($common_js, array('slider', 'shop-sections-slider')));

		/* ── Contact ────────────────────────────────────────────── */
	} elseif (is_page('contact')) {
		$load_css(array('contact', 'responsive'));
		$load_js(array_merge($common_js, array('slider', 'shop-sections-slider')));

		/* ── New Order Thank You ────────────────────────────────── */
	} elseif (is_page('new-order-thank-you') || is_page_template('page-new-order-thank-you.php')) {
		$load_css(array('new-order-thank-you', 'responsive'));
		$load_js($common_js);

		/* ── Editable Help Pages ────────────────────────────────── */
	} elseif (is_page_template('page-help.php') || is_page('help') || is_page(array('return-refund', 'track-order', 'shipping', 'payments', 'terms'))) {
		$load_css(array('shop-page', 'help-pages', 'responsive'));
		$load_js($common_js);

		/* ── FAQ ────────────────────────────────────────────────── */
	} elseif (is_page('faq') || is_page_template('page-faq.php')) {
		$load_css(array('shop-page', 'sections', 'faq', 'responsive'));
		$load_js(array_merge($common_js, array('faq')));

		/* ── Privacy Policy / Terms ─────────────────────────────── */
	} elseif (is_page('privacy-policy') || is_page('terms-and-conditions')) {
		// CSS order matches privacy-policy.html exactly: shop-page → privacy-policy → responsive (last).
		$load_css(array('shop-page', 'privacy-policy', 'responsive'));
		$load_js(array_merge($common_js, array('slider', 'shop-sections-slider')));

		/* ── Vendor ─────────────────────────────────────────────── */
	} elseif (is_page('vendor')) {
		$load_css(array('vendor', 'responsive'));
		$load_js(array_merge($common_js, array('slider', 'shop-sections-slider', 'vendor')));
	} else {
		// Default pages still need the responsive header/drawer rules and shared UI scripts.
		$load_css(array('responsive'));
		$load_js($common_js);
	}

	// Localize mini-cart with real WP/WooCommerce URLs so the drawer's
	// footer buttons (View Cart / Proceed to Checkout) and empty-state link
	// (Browse Products) point to actual pages instead of the static-site
	// fallbacks (cart.html / checkout.html / shop.html).
	if (wp_script_is('consucorner-mini-cart', 'enqueued')) {
		$cart_url     = function_exists('wc_get_cart_url')       ? wc_get_cart_url()       : home_url('/cart/');
		$checkout_url = function_exists('wc_get_checkout_url')   ? wc_get_checkout_url()   : home_url('/checkout/');
		$shop_url     = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
		$free_ship    = function_exists('consucorner_get_free_shipping_display')
			? consucorner_get_free_shipping_display()
			: array(
				'enabled'       => false,
				'has_threshold' => false,
				'min_amount'    => 0,
				'subtitle'      => '',
			);

		wp_localize_script(
			'consucorner-mini-cart',
			'consuMiniCartData',
			array(
				'cartUrl'          => $cart_url,
				'checkoutUrl'      => $checkout_url,
				'shopUrl'          => $shop_url,
				'placeholderImage' => function_exists('consucorner_get_product_placeholder_image_url')
					? consucorner_get_product_placeholder_image_url()
					: $uri . '/assets/images/' . rawurlencode('consucorner icon-logo.jpg'),
				'clearCartCache'   => function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received'),
				'freeShipping'     => array(
					'enabled'       => ! empty($free_ship['enabled']),
					'hasThreshold'  => ! empty($free_ship['has_threshold']),
					'minAmount'     => isset($free_ship['min_amount']) ? (float) $free_ship['min_amount'] : 0,
				),
				'ajaxUrl'          => admin_url('admin-ajax.php'),
				'nonce'            => wp_create_nonce('cc_cart_nonce'),
			)
		);
	}

	if (wp_script_is('consucorner-new-arrival-slider', 'enqueued')) {
		$front_page_id = absint(get_option('page_on_front'));
		$new_arrivals  = function_exists('cc_get_home_new_arrivals_slides') && $front_page_id
			? cc_get_home_new_arrivals_slides($front_page_id, $uri . '/assets/images/')
			: array();

		wp_localize_script(
			'consucorner-new-arrival-slider',
			'consuNewArrivalData',
			array(
				'assetBase' => $uri . '/assets/images/',
				'slides'    => $new_arrivals,
			)
		);
	}

	if (wp_script_is('consucorner-auth-modal', 'enqueued')) {
		$terms_page_id = function_exists('wc_terms_and_conditions_page_id') ? wc_terms_and_conditions_page_id() : 0;
		$terms_url    = $terms_page_id ? get_permalink($terms_page_id) : home_url('/terms-and-conditions/');
		$privacy_url  = get_privacy_policy_url();

		wp_localize_script(
			'consucorner-auth-modal',
			'consuAuthData',
			array(
				'ajaxUrl'       => admin_url('admin-ajax.php'),
				'nonce'         => wp_create_nonce('consucorner_auth_nonce'),
				'accountUrl'    => function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('dashboard') : home_url('/my-account/'),
				'termsUrl'      => $terms_url,
				'privacyUrl'    => $privacy_url ? $privacy_url : home_url('/privacy-policy/'),
				'registerOpen'  => true,
			)
		);
	}

	if (wp_script_is('consucorner-site-search', 'enqueued')) {
		wp_localize_script(
			'consucorner-site-search',
			'consuSearchData',
			array(
				'ajaxUrl'   => admin_url('admin-ajax.php'),
				'nonce'     => wp_create_nonce('consucorner_live_search'),
				'minLength' => 3,
				'searchUrl' => home_url('/'),
			)
		);
	}

	// ── Global site data ─────────────────────────────────────
	// Localized on every page that loads mega-menu (i.e. every page).
	// Consumed by mega-menu.js for:
	//   • add-to-cart AJAX endpoint URL (fixes the hardcoded Cloudways URL)
	//   • authoritative WC cart count   (fixes the stale localStorage badge)
	//   • login state                   (future JS use)
	// consuSiteData is consumed by both cart-badge.js and mega-menu.js.
	// Previously this was attached to 'consucorner-mega-menu' (never enqueued —
	// only 'consucorner-mega-menu-dynamic' is), so the data was never output.
	// Now attached to 'consucorner-cart-badge' which is always in $common_js.
	if (wp_script_is('consucorner-cart-badge', 'enqueued')) {
		$cc_user       = wp_get_current_user();
		$cc_logged_in  = is_user_logged_in();
		$cc_cart_count = (function_exists('WC') && WC()->cart)
			? (int) WC()->cart->get_cart_contents_count()
			: 0;
		$cc_acc_url = function_exists('wc_get_account_endpoint_url')
			? wc_get_account_endpoint_url('dashboard')
			: get_permalink((int) get_option('woocommerce_myaccount_page_id'));

		wp_localize_script(
			'consucorner-cart-badge',
			'consuSiteData',
			array(
				'siteUrl'         => home_url('/'),
				'cartCount'       => $cc_cart_count,
				'isLoggedIn'      => $cc_logged_in,
				'userDisplayName' => $cc_logged_in ? esc_html($cc_user->display_name) : '',
				'accountUrl'      => $cc_acc_url,
				'logoutUrl'       => wp_logout_url(home_url('/')),
			)
		);
	}

	if (wp_script_is('consucorner-consu-tracker', 'enqueued')) {
		wp_localize_script(
			'consucorner-consu-tracker',
			'consuTrackerContext',
			cc_build_tracker_context()
		);
	}

	if (is_singular() && comments_open() && get_option('thread_comments')) {
		wp_enqueue_script('comment-reply');
	}
}
add_action('wp_enqueue_scripts', 'consucorner_scripts');

/**
 * Determine whether the shared product-card quote modal should be printed.
 *
 * Single product pages render the same modal inside the product template so the
 * popup stays close to the product CTA markup. Archive pages need a shared copy
 * because product cards can be replaced by AJAX filters.
 *
 * @return bool
 */
function consucorner_should_render_archive_quote_modal()
{
	return (
		is_front_page() ||
		is_search() ||
		is_page_template('page-shop-instruments.php') ||
		is_page_template('page-shop-specialty.php') ||
		is_page_template('page-offers.php') ||
		is_page_template('page-bundles.php') ||
		(function_exists('is_shop') && is_shop()) ||
		(function_exists('is_product_category') && is_product_category()) ||
		(function_exists('is_product_tag') && is_product_tag()) ||
		(function_exists('is_tax') && is_tax('specialty'))
	);
}

/**
 * Print the shared Get A Quote modal for pages that render product cards.
 */
function consucorner_render_archive_quote_modal()
{
	if (! consucorner_should_render_archive_quote_modal()) {
		return;
	}

	$quote_form_id = function_exists('cc_get_quote_forminator_form_id') ? cc_get_quote_forminator_form_id() : 0;
?>
	<div
		id="ccQuoteModal"
		class="cc-quote-modal"
		role="dialog"
		aria-modal="true"
		aria-hidden="true"
		aria-label="<?php esc_attr_e('Get A Quote', 'consucorner'); ?>"
		hidden>
		<div class="cc-quote-modal__backdrop"></div>
		<div class="cc-quote-modal__box">
			<button type="button" class="cc-quote-modal__close" aria-label="<?php esc_attr_e('Close', 'consucorner'); ?>">
				&#x2715;
			</button>
			<div class="cc-quote-modal__header">
				<span class="cc-quote-modal__icon" aria-hidden="true">&#128196;</span>
				<h2 class="cc-quote-modal__title"><?php esc_html_e('Request a Quote', 'consucorner'); ?></h2>
				<p class="cc-quote-modal__subtitle"><?php esc_html_e("Fill in your details and we'll get back to you shortly.", 'consucorner'); ?></p>
			</div>
			<?php
			if ($quote_form_id && shortcode_exists('forminator_form')) {
				echo do_shortcode('[forminator_form id="' . absint($quote_form_id) . '"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<p class="cc-quote-modal__empty">' . esc_html__('The quote form is currently unavailable.', 'consucorner') . '</p>';
			}
			?>
		</div>
	</div>
<?php
}
add_action('wp_footer', 'consucorner_render_archive_quote_modal', 20);

/* ============================================================
   PERSONALIZATION: build per-request tracker context
   ============================================================
   Returns the payload that consu-tracker.js ingests on every page
   load to remember the doctor's interests (last viewed category,
   specialty, product, search term).
*/
function cc_build_tracker_context()
{
	$context = array('type' => 'none');

	if (function_exists('is_singular') && is_singular('product')) {
		$product_id  = get_queried_object_id();
		$cat_slugs   = array();
		$spec_slugs  = array();

		$cat_terms = get_the_terms($product_id, 'product_cat');
		if (is_array($cat_terms)) {
			foreach ($cat_terms as $term) {
				$cat_slugs[] = $term->slug;
			}
		}

		if (taxonomy_exists('specialty')) {
			$spec_terms = get_the_terms($product_id, 'specialty');
			if (is_array($spec_terms)) {
				foreach ($spec_terms as $term) {
					$spec_slugs[] = $term->slug;
				}
			}
		}

		$context = array(
			'type'        => 'product',
			'product_id'  => $product_id,
			'categories'  => array_values(array_unique($cat_slugs)),
			'specialties' => array_values(array_unique($spec_slugs)),
		);
	} elseif (function_exists('is_product_category') && is_product_category()) {
		$term = get_queried_object();
		if ($term && ! is_wp_error($term)) {
			$context = array(
				'type'     => 'category',
				'slug'     => $term->slug,
				'taxonomy' => 'product_cat',
			);
		}
	} elseif (function_exists('is_tax') && is_tax('specialty')) {
		$term = get_queried_object();
		if ($term && ! is_wp_error($term)) {
			$context = array(
				'type'     => 'specialty',
				'slug'     => $term->slug,
				'taxonomy' => 'specialty',
			);
		}
	} elseif (is_search()) {
		$query = trim((string) get_search_query());
		if ('' !== $query) {
			$context = array(
				'type'  => 'search',
				'query' => $query,
			);
		}
	}

	return $context;
}

/* ============================================================
   PERSONALIZATION: read interest profile from cookies / POST
   ============================================================
   Used by the Home page AJAX handlers (Bestsellers + Recommended)
   to bias product picks toward the doctor's recent interests.
*/
function cc_read_user_interest_profile()
{
	$profile = array(
		'categories'  => array(),
		'specialties' => array(),
		'searches'    => array(),
	);

	// 1) Prefer values posted by the JS payload (richest, most recent state).
	$post_map = array(
		'categories'  => 'pref_categories',
		'specialties' => 'pref_specialties',
		'searches'    => 'pref_searches',
	);
	foreach ($post_map as $bucket => $post_key) {
		if (! empty($_POST[$post_key])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller validates its own nonce.
			$value = $_POST[$post_key]; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$value = is_array($value) ? $value : explode(',', (string) wp_unslash($value));
			$profile[$bucket] = cc_sanitize_interest_list($bucket, $value);
		}
	}

	// 2) Fallback to cookies set by consu-tracker.js on previous page loads.
	$cookie_map = array(
		'categories'  => 'consu_pref_categories',
		'specialties' => 'consu_pref_specialties',
		'searches'    => 'consu_pref_searches',
	);
	foreach ($cookie_map as $bucket => $cookie_name) {
		if (empty($profile[$bucket]) && ! empty($_COOKIE[$cookie_name])) {
			$raw   = wp_unslash($_COOKIE[$cookie_name]);
			$parts = explode(',', (string) $raw);
			$profile[$bucket] = cc_sanitize_interest_list($bucket, $parts);
		}
	}

	// 3) Last-viewed slug fallbacks (always cookie-driven).
	if (empty($profile['categories']) && ! empty($_COOKIE['consu_last_category_slug'])) {
		$profile['categories'] = cc_sanitize_interest_list('categories', array(wp_unslash($_COOKIE['consu_last_category_slug'])));
	}
	if (empty($profile['specialties']) && ! empty($_COOKIE['consu_last_specialty_slug'])) {
		$profile['specialties'] = cc_sanitize_interest_list('specialties', array(wp_unslash($_COOKIE['consu_last_specialty_slug'])));
	}

	return $profile;
}

function cc_sanitize_interest_list($bucket, $values)
{
	$limit = ('searches' === $bucket) ? 5 : 10;
	$out   = array();

	foreach ((array) $values as $value) {
		$value = trim((string) $value);
		if ('' === $value) {
			continue;
		}
		if ('searches' === $bucket) {
			$value = mb_substr(sanitize_text_field($value), 0, 60);
		} else {
			$value = sanitize_title($value);
		}
		if ('' === $value || in_array($value, $out, true)) {
			continue;
		}
		$out[] = $value;
		if (count($out) >= $limit) {
			break;
		}
	}

	return $out;
}

/**
 * Build a WP_Query tax_query branch that matches products belonging to ANY
 * of the user's interest categories/specialties. Returns array() when no
 * interests are recorded (so callers can skip personalization entirely).
 */
function cc_build_interest_tax_query(array $profile)
{
	$queries = array();

	if (! empty($profile['categories'])) {
		$queries[] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $profile['categories'],
			'operator' => 'IN',
		);
	}

	if (! empty($profile['specialties']) && taxonomy_exists('specialty')) {
		$queries[] = array(
			'taxonomy' => 'specialty',
			'field'    => 'slug',
			'terms'    => $profile['specialties'],
			'operator' => 'IN',
		);
	}

	if (empty($queries)) {
		return array();
	}

	if (count($queries) === 1) {
		return $queries[0];
	}

	return array_merge(array('relation' => 'OR'), $queries);
}

/**
 * Optional: also bias toward the doctor's recent search terms by adding
 * an s='term1 term2' style query. We OR the keywords together using
 * WP_Query's "search" string with the post_title/excerpt match.
 *
 * Only used as a tie-breaker when no taxonomy matches exist.
 */
function cc_extract_interest_search_keyword(array $profile)
{
	if (empty($profile['searches'])) {
		return '';
	}
	$first = trim((string) reset($profile['searches']));
	return $first;
}

/* ============================================================
   AUTH MODAL AJAX
   ============================================================ */
function consucorner_auth_json_error($message, $status_code = 400)
{
	wp_send_json_error(
		array(
			'message' => $message,
		),
		$status_code
	);
}

function consucorner_ajax_auth_login()
{
	check_ajax_referer('consucorner_auth_nonce', 'nonce');

	$login    = isset($_POST['login']) ? sanitize_text_field(wp_unslash($_POST['login'])) : '';
	$password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
	$remember = ! empty($_POST['remember']);

	if ('' === $login || '' === $password) {
		consucorner_auth_json_error(__('Please enter your email/username and password.', 'consucorner'));
	}

	$user = wp_signon(
		array(
			'user_login'    => $login,
			'user_password' => $password,
			'remember'      => $remember,
		),
		is_ssl()
	);

	if (is_wp_error($user)) {
		consucorner_auth_json_error(wp_strip_all_tags($user->get_error_message()), 401);
	}

	wp_send_json_success(
		array(
			'message'      => __('Logged in successfully. Redirecting...', 'consucorner'),
			'redirectUrl'  => function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('dashboard') : home_url('/my-account/'),
			'displayName'  => $user->display_name,
		)
	);
}
add_action('wp_ajax_nopriv_consucorner_auth_login', 'consucorner_ajax_auth_login');

function consucorner_ajax_auth_signup()
{
	check_ajax_referer('consucorner_auth_nonce', 'nonce');

	$display_name = isset($_POST['profile_name']) ? sanitize_text_field(wp_unslash($_POST['profile_name'])) : '';
	$username     = isset($_POST['user_name']) ? sanitize_user(wp_unslash($_POST['user_name']), true) : '';
	$email        = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
	$phone        = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
	$password     = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';

	if ('' === $display_name || '' === $username || '' === $email || '' === $password) {
		consucorner_auth_json_error(__('Please fill in name, username, email, and password.', 'consucorner'));
	}

	if (! is_email($email)) {
		consucorner_auth_json_error(__('Please enter a valid email address.', 'consucorner'));
	}

	if (strlen($password) < 8) {
		consucorner_auth_json_error(__('Password must be at least 8 characters.', 'consucorner'));
	}

	if (username_exists($username)) {
		consucorner_auth_json_error(__('This username is already taken.', 'consucorner'));
	}

	if (email_exists($email)) {
		consucorner_auth_json_error(__('This email is already registered. Please log in instead.', 'consucorner'));
	}

	$user_id = wp_create_user($username, $password, $email);
	if (is_wp_error($user_id)) {
		consucorner_auth_json_error(wp_strip_all_tags($user_id->get_error_message()));
	}

	wp_update_user(
		array(
			'ID'           => $user_id,
			'display_name' => $display_name,
			'nickname'     => $display_name,
			'role'         => 'customer',
		)
	);

	if ($phone) {
		update_user_meta($user_id, 'billing_phone', $phone);
	}

	wp_set_current_user($user_id);
	wp_set_auth_cookie($user_id, true, is_ssl());

	wp_send_json_success(
		array(
			'message'     => __('Account created successfully. Redirecting...', 'consucorner'),
			'redirectUrl' => function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('dashboard') : home_url('/my-account/'),
		)
	);
}
add_action('wp_ajax_nopriv_consucorner_auth_signup', 'consucorner_ajax_auth_signup');

function consucorner_ajax_auth_lost_password()
{
	check_ajax_referer('consucorner_auth_nonce', 'nonce');

	$user_login = isset($_POST['user_login']) ? sanitize_text_field(wp_unslash($_POST['user_login'])) : '';
	if ('' === $user_login) {
		consucorner_auth_json_error(__('Please enter your email address or username.', 'consucorner'));
	}

	$_POST['user_login'] = $user_login;
	$result = retrieve_password($user_login);

	if (is_wp_error($result)) {
		consucorner_auth_json_error(wp_strip_all_tags($result->get_error_message()));
	}

	wp_send_json_success(
		array(
			'message' => __('Password reset email sent. Please check your inbox.', 'consucorner'),
		)
	);
}
add_action('wp_ajax_nopriv_consucorner_auth_lost_password', 'consucorner_ajax_auth_lost_password');

/* ============================================================
   RESOURCE HINTS — preload critical fonts on every page and,
   on single posts, also preload the featured image so the LCP
   element starts fetching as early as possible.
   ============================================================ */
function consucorner_resource_hints()
{
	$fonts_uri = get_template_directory_uri() . '/assets/fonts/';

	// Preload the two fonts that render above-the-fold text.
	$critical_fonts = array(
		'poppins/poppins-latin-400-normal.woff2',
		'poppins/poppins-latin-500-normal.woff2',
	);

	foreach ($critical_fonts as $font) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin="anonymous">' . "\n",
			esc_url($fonts_uri . $font)
		);
	}

	// On single posts: also preload the featured image (LCP element).
	if (is_singular('post')) {
		$thumb_id  = get_post_thumbnail_id();
		$thumb_src = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : '';
		if ($thumb_src) {
			printf(
				'<link rel="preload" href="%s" as="image" fetchpriority="high">' . "\n",
				esc_url($thumb_src)
			);
		}
	}
}
add_action('wp_head', 'consucorner_resource_hints', 1);

/**
 * Emit a rel=canonical tag on filtered archive pages (URL-driven filters).
 *
 * When taxonomy / price / sort params are present in the URL, the canonical
 * points to the base archive URL without query params, preventing duplicate-
 * content issues from shared filter URLs in Google's index.
 *
 * Runs at priority 20 so Yoast / Rank Math canonical (priority 1–10) takes
 * precedence if active; this only fires if no SEO plugin has already output
 * a canonical for the current request.
 */
function consucorner_filter_canonical()
{
	if (
		! (function_exists('is_shop') && is_shop())
		&& ! (function_exists('is_product_category') && is_product_category())
		&& ! is_tax('specialty')
	) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$filter_params = array_intersect_key(
		$_GET,
		array_flip(array('specialty', 'product_cat', 'product_brand', 'product_condition', 'sort', 'min_price', 'max_price'))
	);
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if (empty($filter_params)) {
		return;
	}

	// Only emit if no SEO plugin has already output a canonical.
	if (did_action('wpseo_head') || did_action('rank_math/head') || did_action('seopress_pro_head')) {
		return;
	}

	$base_url = get_term_link(get_queried_object());
	if (is_wp_error($base_url)) {
		$base_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	}

	printf('<link rel="canonical" href="%s" />' . "\n", esc_url($base_url));

	// For paginated filtered results beyond page 1, also add noindex.
	$paged = max(1, get_query_var('paged') ? (int) get_query_var('paged') : 1);
	if ($paged > 1) {
		echo '<meta name="robots" content="noindex,follow" />' . "\n";
	}
}
add_action('wp_head', 'consucorner_filter_canonical', 20);

/**
 * Whether a blog post embeds WooCommerce product listing blocks.
 *
 * @param int $post_id Optional post ID.
 * @return bool
 */
function consucorner_post_has_wc_product_blocks($post_id = 0)
{
	$post_id = $post_id ? (int) $post_id : (int) get_queried_object_id();
	if (! $post_id) {
		return false;
	}

	$post = get_post($post_id);
	if (! $post) {
		return false;
	}

	$blocks = array(
		'woocommerce/product-collection',
		'woocommerce/product-category',
		'woocommerce/handpicked-products',
		'woocommerce/products-by-attribute',
		'woocommerce/all-products',
		'woocommerce/featured-product',
	);

	foreach ($blocks as $block_name) {
		if (has_block($block_name, $post)) {
			return true;
		}
	}

	return false !== strpos($post->post_content, 'wc-block-grid');
}

/**
 * Keep WooCommerce product-block layout assets on single posts that embed them.
 */
function consucorner_single_post_enqueue_wc_product_blocks()
{
	if (! is_singular('post') || ! consucorner_post_has_wc_product_blocks()) {
		return;
	}

	if (! function_exists('WC') || ! defined('WC_PLUGIN_FILE')) {
		return;
	}

	$base_url = plugins_url('assets/client/blocks/woocommerce/', WC_PLUGIN_FILE);
	$version  = defined('WC_VERSION') ? WC_VERSION : null;

	$styles = array(
		'consucorner-post-wc-product-collection' => 'product-collection-style.css',
		'consucorner-post-wc-product-template'   => 'product-template-style.css',
	);

	foreach ($styles as $handle => $file) {
		wp_enqueue_style($handle, $base_url . $file, array(), $version);
	}

	if (wp_style_is('wc-blocks-style', 'registered')) {
		wp_enqueue_style('wc-blocks-style');
	}

	if (wp_style_is('wc-blocks-vendors-style', 'registered')) {
		wp_enqueue_style('wc-blocks-vendors-style');
	}
}
add_action('wp_enqueue_scripts', 'consucorner_single_post_enqueue_wc_product_blocks', 201);

/* ============================================================
   STRIP UNUSED ASSETS ON SINGLE BLOG POSTS
   Removes ~127 KiB of render-blocking CSS and JS that has 0 %
   usage on blog-post pages (Dokan, WooCommerce, dashicons,
   payment-gateway, block-library).  Runs at priority 200 so it
   fires after all plugins have finished enqueueing.
   ============================================================ */
function consucorner_single_post_dequeue()
{
	if (! is_singular('post')) {
		return;
	}

	$has_wc_product_blocks = consucorner_post_has_wc_product_blocks();

	// ── Dokan marketplace plugin ─────────────────────────────
	// style.css, iziModal, font-awesome → 100 % unused on blog posts.
	wp_dequeue_style('dokan-style');
	wp_dequeue_style('dokan-modal');      // iziModal.min.css
	wp_dequeue_style('dokan-fontawesome');
	wp_dequeue_script('dokan-util-helper');  // helper.js (pulls in sweetalert2 + moment)
	wp_dequeue_script('dokan-sweetalert2');
	wp_dequeue_script('dokan-modal');         // iziModal.min.js

	// ── WooCommerce styles ───────────────────────────────────
	// woocommerce.css, layout, smallscreen, blocks → 99 % unused on blog posts.
	// Keep block layout assets when a post embeds product listing blocks.
	if (! $has_wc_product_blocks) {
		wp_dequeue_style('woocommerce-general');
		wp_dequeue_style('woocommerce-layout');
		wp_dequeue_style('woocommerce-smallscreen');
		wp_dequeue_style('wc-blocks-style');
		wp_dequeue_style('wc-blocks-vendors-style');
	}

	// ── WordPress core styles not needed on blog posts ───────
	// wp-block-library → 99 % unused; dashicons → 100 % unused.
	wp_dequeue_style('wp-block-library');
	wp_dequeue_style('wp-block-library-theme');
	wp_dequeue_style('classic-theme-styles');
	wp_dequeue_style('global-styles');
	if (! is_admin_bar_showing()) {
		wp_dequeue_style('dashicons');
	}

	// ── Payment gateway styles ───────────────────────────────
	wp_dequeue_style('gi-styles');      // Geidea payments
	wp_dequeue_style('paymob-style');

	// ── Unused JS on blog posts ──────────────────────────────
	wp_dequeue_script('jquery-migrate');
}
add_action('wp_enqueue_scripts', 'consucorner_single_post_dequeue', 200);

/* ============================================================
   DEQUEUE SELECTWOO ON CHECKOUT
   Lets us style the Governorate dropdown as a native <select>.
   ============================================================ */
function consucorner_dequeue_selectwoo_on_checkout()
{
	if (function_exists('is_checkout') && is_checkout()) {
		wp_dequeue_script('selectWoo');
		wp_dequeue_style('select2');
	}
}
add_action('wp_enqueue_scripts', 'consucorner_dequeue_selectwoo_on_checkout', 100);

/* ============================================================
   FORCE CLASSIC CART / CHECKOUT
   New WooCommerce installs default to the React Cart and
   Checkout *Blocks* (<!-- wp:woocommerce/cart /--> and
   <!-- wp:woocommerce/checkout /-->). Those blocks render their
   own React UI and do NOT respect template overrides such as
   woocommerce/cart/cart.php — which is why the ConsuCorner
   custom cart design wasn't loading.

   We swap the page content for the classic [woocommerce_cart]
   and [woocommerce_checkout] shortcodes whenever the cart or
   checkout page is rendered, so our template overrides take
   effect.
   ============================================================ */
function consucorner_force_classic_cart_checkout_post_content()
{
	if (is_admin()) {
		return;
	}
	if (! function_exists('is_cart') || ! function_exists('is_checkout')) {
		return;
	}

	global $post;
	if (! $post || empty($post->post_content)) {
		return;
	}

	if (is_cart()) {
		$post->post_content = '[woocommerce_cart]';
		return;
	}

	if (
		is_checkout()
		&& ! (function_exists('is_wc_endpoint_url')
			&& (is_wc_endpoint_url('order-received')
				|| is_wc_endpoint_url('order-pay')))
	) {
		$post->post_content = '[woocommerce_checkout]';
	}
}
/*
 * Run early, on `wp`, BEFORE any template / block parsing.  By mutating the
 * global $post->post_content directly we avoid the dual-render problem that
 * happens when both `the_content` and `render_block` filters fire on the
 * same page (FSE themes, page builders, REST renders, etc.).  The post
 * content is now literally `[woocommerce_checkout]` — there are no blocks
 * left to parse, so it can only render once, regardless of how the theme
 * outputs it.
 */
add_action('wp', 'consucorner_force_classic_cart_checkout_post_content', 0);

/* ============================================================
   CART: handle ?empty-cart=yes (Clear cart button on cart page).
   WooCommerce does not provide a built-in clear-cart URL, so we add one.
   ============================================================ */
function consucorner_handle_empty_cart_request()
{
	if (! function_exists('WC') || ! WC()->cart) {
		return;
	}
	if (empty($_GET['empty-cart']) || 'yes' !== $_GET['empty-cart']) {
		return;
	}
	if (! isset($_GET['_wpnonce']) || ! wp_verify_nonce(wp_unslash($_GET['_wpnonce']), 'woocommerce-cart')) {
		return;
	}

	WC()->cart->empty_cart();
	if (function_exists('wc_add_notice')) {
		wc_add_notice(__('Your cart was cleared.', 'consucorner'), 'success');
	}

	wp_safe_redirect(wc_get_cart_url());
	exit;
}
add_action('template_redirect', 'consucorner_handle_empty_cart_request');

/* ============================================================
   CART: RELOCATE WOOCOMMERCE NOTICES
   ============================================================
   WooCommerce hooks woocommerce_output_all_notices to the
   woocommerce_before_cart action by default (priority 10).
   That causes a full-width notice bar to render BEFORE our
   custom cart-section, above the page heading.

   We unhook it early and render it ourselves inside the
   cart-top-pill section (cart.php / cart-empty.php) so notices
   appear in the right position with the correct brand styling.
   ============================================================ */
add_action('template_redirect', function () {
	if (function_exists('is_cart') && is_cart()) {
		remove_action('woocommerce_before_cart', 'woocommerce_output_all_notices', 10);
	}
}, 5);

/* ============================================================
   BODY CLASSES
   ============================================================ */
function consucorner_account_body_class($classes)
{
	if (consucorner_is_account_page()) {
		$classes[] = 'page-profile';
	}
	return $classes;
}
add_filter('body_class', 'consucorner_account_body_class');

function consucorner_cart_body_class($classes)
{
	if (function_exists('is_cart') && is_cart()) {
		$classes[] = 'page-cart';
		if (function_exists('WC') && WC()->cart && WC()->cart->is_empty()) {
			$classes[] = 'cart-is-empty';
		}
	}
	return $classes;
}
add_filter('body_class', 'consucorner_cart_body_class');

function consucorner_checkout_body_class($classes)
{
	if (function_exists('is_checkout') && is_checkout()) {
		$classes[] = 'page-checkout';
		if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
			$classes[] = 'page-thankyou';
		}
	}
	return $classes;
}
add_filter('body_class', 'consucorner_checkout_body_class');

function consucorner_single_product_body_class($classes)
{
	if (function_exists('is_singular') && is_singular('product')) {
		$classes[] = 'page-single-product';
	}
	return $classes;
}
add_filter('body_class', 'consucorner_single_product_body_class');

/**
 * Determine whether a single blog post contains Arabic text.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function consucorner_is_arabic_post($post_id)
{
	if (! $post_id || 'post' !== get_post_type($post_id)) {
		return false;
	}

	$text = get_the_title($post_id) . ' ' . get_the_excerpt($post_id) . ' ' . wp_strip_all_tags(strip_shortcodes(get_post_field('post_content', $post_id)));

	$faqs = get_post_meta($post_id, '_cc_blog_faqs', true);
	if (is_array($faqs)) {
		foreach ($faqs as $faq) {
			$text .= ' ' . (isset($faq['question']) ? (string) $faq['question'] : '');
			$text .= ' ' . wp_strip_all_tags(isset($faq['answer']) ? (string) $faq['answer'] : '');
		}
	}

	return 1 === preg_match('/[\x{0600}-\x{06FF}]/u', $text);
}

function consucorner_single_post_body_class($classes)
{
	if (function_exists('is_singular') && is_singular('post') && consucorner_is_arabic_post(get_queried_object_id())) {
		$classes[] = 'is-arabic-post';
	}

	return $classes;
}
add_filter('body_class', 'consucorner_single_post_body_class');

function consucorner_single_post_post_class($classes, $class, $post_id)
{
	if (function_exists('is_singular') && is_singular('post') && consucorner_is_arabic_post($post_id)) {
		$classes[] = 'is-arabic-post';
	}

	return $classes;
}
add_filter('post_class', 'consucorner_single_post_post_class', 10, 3);

function consucorner_category_archive_body_class($classes)
{
	if (
		(function_exists('is_shop') && is_shop()) ||
		(function_exists('is_product_category') && is_product_category()) ||
		(function_exists('is_product_tag') && is_product_tag()) ||
		(function_exists('is_tax') && is_tax('specialty'))
	) {
		$classes[] = 'page-category-archive';
	}
	return $classes;
}
add_filter('body_class', 'consucorner_category_archive_body_class');

/**
 * Body classes for static pages: about, contact, privacy-policy, vendor.
 */
function consucorner_static_page_body_classes($classes)
{
	if (is_page('about')) {
		$classes[] = 'page-about';
	} elseif (is_page('contact')) {
		$classes[] = 'page-contact';
	} elseif (is_page('new-order-thank-you') || is_page_template('page-new-order-thank-you.php')) {
		$classes[] = 'page-new-thankyou';
	} elseif (is_page('privacy-policy') || is_page('terms-and-conditions')) {
		$classes[] = 'page-privacy-policy';
	} elseif (is_page('vendor')) {
		$classes[] = 'page-vendor';
	}
	return $classes;
}
add_filter('body_class', 'consucorner_static_page_body_classes');

/* ============================================================
   WOOCOMMERCE: CHECKOUT BILLING FIELDS
   ============================================================ */
function consucorner_checkout_billing_fields($fields)
{
	unset($fields['billing_company']);
	unset($fields['billing_address_2']);
	unset($fields['billing_city']);
	unset($fields['billing_postcode']);

	$overrides = array(
		'billing_email'      => array('label' => 'Email',            'placeholder' => 'ahmed.mohamed@clinic.com', 'required' => true,  'priority' => 10, 'class' => array('form-row', 'form-row-wide')),
		'billing_phone'      => array('label' => 'Phone Number',     'placeholder' => '10 1234 5678',             'required' => true,  'priority' => 20, 'class' => array('form-row', 'form-row-wide')),
		'billing_first_name' => array('label' => 'First Name',       'placeholder' => 'Ahmed',                    'required' => true,  'priority' => 30, 'class' => array('form-row', 'form-row-first')),
		'billing_last_name'  => array('label' => 'Last Name',        'placeholder' => 'Mohamed',                  'required' => true,  'priority' => 40, 'class' => array('form-row', 'form-row-last')),
		'billing_address_1'  => array('label' => 'Shipping Address', 'placeholder' => '12 El Tahrir St, Dokki',  'required' => true,  'priority' => 50, 'class' => array('form-row', 'form-row-wide')),
		'billing_country'    => array('priority' => 60, 'class' => array('form-row', 'form-row-wide', 'co-hidden-field')),
		'billing_state'      => array('label' => 'Governorate',      'required' => true,  'priority' => 70, 'class' => array('form-row', 'form-row-wide', 'co-state-field')),
	);

	foreach ($overrides as $key => $props) {
		if (! isset($fields[$key])) {
			continue;
		}
		foreach ($props as $prop => $value) {
			$fields[$key][$prop] = $value;
		}
	}
	return $fields;
}
add_filter('woocommerce_billing_fields', 'consucorner_checkout_billing_fields', 20);

function consucorner_default_address_fields($fields)
{
	if (isset($fields['address_1'])) {
		$fields['address_1']['label'] = 'Shipping Address';
		$fields['address_1']['placeholder'] = '12 El Tahrir St, Dokki';
	}
	if (isset($fields['city'])) {
		$fields['city']['required'] = false;
		$fields['city']['hidden']   = true;
	}
	if (isset($fields['state'])) {
		$fields['state']['label']     = 'Governorate';
	}
	if (isset($fields['postcode'])) {
		$fields['postcode']['required'] = false;
		$fields['postcode']['hidden']   = true;
	}
	foreach ($fields as &$f) {
		$f['label_class'] = array();
	}
	return $fields;
}
add_filter('woocommerce_default_address_fields', 'consucorner_default_address_fields', 90);

add_filter('woocommerce_get_country_locale', function ($locale) {
	$locale['EG'] = array_merge(
		isset($locale['EG']) ? $locale['EG'] : array(),
		array(
			'address_1' => array('label' => 'Shipping Address', 'placeholder' => '12 El Tahrir St, Dokki'),
			'city'      => array('hidden' => true, 'required' => false),
			'state'     => array('label' => 'Governorate'),
			'postcode'  => array('hidden' => true, 'required' => false),
		)
	);
	return $locale;
}, 90);

add_filter('woocommerce_form_field', function ($field, $key) {
	$field = str_replace(
		array(' <abbr class="required" title="required">*</abbr>', '<abbr class="required" title="required">*</abbr>', '<span class="optional">(optional)</span>'),
		'',
		$field
	);

	$desired = array(
		'billing_email'      => 'Email',
		'billing_phone'      => 'Phone Number',
		'billing_first_name' => 'First Name',
		'billing_last_name'  => 'Last Name',
		'billing_address_1'  => 'Shipping Address',
		'billing_state'      => 'Governorate',
	);

	if (isset($desired[$key])) {
		$field = preg_replace(
			'/<label\b[^>]*>(.*?)<\/label>/s',
			'<label for="' . esc_attr($key) . '">' . esc_html($desired[$key]) . '</label>',
			$field
		);
	}

	return $field;
}, 10, 2);

/* ============================================================
   WOOCOMMERCE: PAYMENT BUTTON TEXT
   ============================================================ */
add_filter('woocommerce_order_button_text', function () {
	return 'Place Order';
});

/* ============================================================
   WOOCOMMERCE: REMOVE DEFAULT COUPON FORM FROM CHECKOUT
   (we render our own custom coupon UI in the template)
   ============================================================ */
add_action('init', function () {
	remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
});

/* ============================================================
   CHECKOUT: RELOCATE WOOCOMMERCE NOTICES
   ============================================================
   WooCommerce hooks woocommerce_output_all_notices to the
   woocommerce_before_checkout_form action (priority 10) by
   default.  That causes a full-width notice bar to render
   ABOVE the page hero.

   We unhook it here and render it ourselves inside the
   checkout section (form-checkout.php) so it appears in the
   right position with the correct brand styling.
   ============================================================ */
add_action('template_redirect', function () {
	if (function_exists('is_checkout') && is_checkout()) {
		remove_action('woocommerce_before_checkout_form', 'woocommerce_output_all_notices', 10);
	}
}, 5);

/* ============================================================
   CHECKOUT: PREVENT DUPLICATE BILLING FIELDS
   ============================================================
   WooCommerce hooks WC_Checkout::checkout_form_billing() to
   woocommerce_checkout_billing (priority 10).  Our template
   already renders all billing fields manually (so we can
   control the DOM/class names), so we remove WC's own
   callback.  Third-party plugins that hook their *extra*
   custom fields into woocommerce_checkout_billing (e.g.
   custom-field plugins) will still fire — only WC's own
   billing section renderer is removed.
   ============================================================ */
add_action('woocommerce_checkout_init', function ($checkout) {
	remove_action('woocommerce_checkout_billing', array($checkout, 'checkout_form_billing'));
	remove_action('woocommerce_checkout_shipping', array($checkout, 'checkout_form_shipping'));
}, 10);

/* ============================================================
   FRONT-PAGE AJAX: BROWSE BY SPECIALTY
   ============================================================ */
function consucorner_ajax_get_specialty_products()
{
	check_ajax_referer('consucorner_browse_nonce', 'nonce');

	$specialty = isset($_POST['specialty']) ? sanitize_text_field(wp_unslash($_POST['specialty'])) : '';
	$limit     = (int) apply_filters('consucorner_home_browse_products_limit', 8);

	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'orderby'        => 'rand',
		'tax_query'      => array(),
		'meta_query'     => array(
			array(
				'key'     => '_stock_status',
				'value'   => 'outofstock',
				'compare' => '!=',
			),
		),
	);

	if ($specialty) {
		$args['tax_query'][] = array(
			'taxonomy' => taxonomy_exists('specialty') ? 'specialty' : 'product_cat',
			'field'    => 'slug',
			'terms'    => $specialty,
		);
	}

	$query = new WP_Query($args);
	$products = array();
	$html     = '';
	$fallback = function_exists('consucorner_get_product_placeholder_image_url')
		? consucorner_get_product_placeholder_image_url()
		: get_template_directory_uri() . '/assets/images/' . rawurlencode('consucorner icon-logo.jpg');

	foreach ($query->posts as $post) {
		$product = wc_get_product($post->ID);
		if (! $product) {
			continue;
		}
		if (function_exists('cc_render_product_card')) {
			$html .= cc_render_product_card($product);
		}
		$img_id  = $product->get_image_id();
		$img_url = $img_id ? wp_get_attachment_image_url($img_id, 'woocommerce_thumbnail') : $fallback;
		$products[] = array(
			'id'    => $post->ID,
			'name'  => $product->get_name(),
			'price' => (float) $product->get_price(),
			'image' => $img_url,
			'link'  => get_permalink($post->ID),
		);
	}

	wp_send_json_success(array(
		'products' => $products,
		'html'     => $html,
	));
}
add_action('wp_ajax_consucorner_get_specialty_products',        'consucorner_ajax_get_specialty_products');
add_action('wp_ajax_nopriv_consucorner_get_specialty_products', 'consucorner_ajax_get_specialty_products');

/* ============================================================
   FRONT-PAGE AJAX: RECOMMENDED PRODUCTS
   ============================================================
   Personalized for each visiting doctor: the JS payload + cookies
   set by consu-tracker.js carry the recent categories, specialties
   and search terms the user has interacted with. We bias the WP_Query
   toward those terms (OR-relation) and fall back gracefully when
   nothing matches yet.
*/
function consucorner_ajax_get_recommended_products()
{
	check_ajax_referer('consucorner_recommended_nonce', 'nonce');

	$profile   = cc_read_user_interest_profile();
	$tax_query = cc_build_interest_tax_query($profile);
	$keyword   = cc_extract_interest_search_keyword($profile);

	// Back-compat: an older single "preferred_category" param still works,
	// it just gets pushed into the categories bucket if not already there.
	if (! empty($_POST['preferred_category'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$single = sanitize_title(wp_unslash($_POST['preferred_category'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ($single && ! in_array($single, $profile['categories'], true)) {
			$profile['categories'][] = $single;
			$tax_query = cc_build_interest_tax_query($profile);
		}
	}

	$base_args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 8,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	$args = $base_args;
	if (! empty($tax_query)) {
		$args['tax_query'] = isset($tax_query['relation']) ? $tax_query : array($tax_query);
	}

	$query = new WP_Query($args);

	// Soft-fallback chain: tax → search keyword → newest products.
	if (! $query->have_posts() && '' !== $keyword) {
		$args = array_merge($base_args, array('s' => $keyword));
		$query = new WP_Query($args);
	}
	if (! $query->have_posts()) {
		$query = new WP_Query($base_args);
	}

	$products = array();
	$html     = '';
	$fallback = function_exists('consucorner_get_product_placeholder_image_url')
		? consucorner_get_product_placeholder_image_url()
		: get_template_directory_uri() . '/assets/images/' . rawurlencode('consucorner icon-logo.jpg');

	foreach ($query->posts as $post) {
		$product = wc_get_product($post->ID);
		if (! $product) {
			continue;
		}
		if (function_exists('cc_render_product_card')) {
			$html .= cc_render_product_card($product);
		}
		$img_id  = $product->get_image_id();
		$img_url = $img_id ? wp_get_attachment_image_url($img_id, 'woocommerce_thumbnail') : $fallback;
		$products[] = array(
			'id'    => $post->ID,
			'name'  => $product->get_name(),
			'price' => (float) $product->get_price(),
			'image' => $img_url,
			'link'  => get_permalink($post->ID),
		);
	}

	wp_send_json_success(array(
		'products'      => $products,
		'html'          => $html,
		'personalized'  => ! empty($tax_query) || '' !== $keyword,
	));
}
add_action('wp_ajax_consucorner_get_recommended_products',        'consucorner_ajax_get_recommended_products');
add_action('wp_ajax_nopriv_consucorner_get_recommended_products', 'consucorner_ajax_get_recommended_products');

/* ============================================================
   FRONT-PAGE AJAX: OVERALL BESTSELLERS
   ============================================================
   Manual list on the Home page overrides auto ranking.
   Auto mode ranks by distinct order count (not total_sales units).
*/
function consucorner_ajax_get_overall_bestsellers()
{
	check_ajax_referer('consucorner_bestsellers_nonce', 'nonce');

	$per_page = 8;
	$profile  = cc_read_user_interest_profile();
	$result   = cc_get_home_bestsellers_product_ids($per_page, $profile);
	$matched_ids = isset($result['ids']) ? (array) $result['ids'] : array();

	$products = array();
	$html     = '';
	$fallback = function_exists('consucorner_get_product_placeholder_image_url')
		? consucorner_get_product_placeholder_image_url()
		: get_template_directory_uri() . '/assets/images/' . rawurlencode('consucorner icon-logo.jpg');

	foreach ($matched_ids as $pid) {
		$pid = absint($pid);
		if ($pid <= 0) {
			continue;
		}
		$product = wc_get_product($pid);
		if (! $product) {
			continue;
		}
		if (function_exists('cc_render_product_card')) {
			$html .= cc_render_product_card($product);
		}
		$img_id  = $product->get_image_id();
		$img_url = $img_id ? wp_get_attachment_image_url($img_id, 'woocommerce_thumbnail') : $fallback;
		$products[] = array(
			'id'    => $pid,
			'name'  => $product->get_name(),
			'price' => (float) $product->get_price(),
			'image' => $img_url,
			'link'  => get_permalink($pid),
		);
	}

	wp_send_json_success(array(
		'products'     => $products,
		'html'         => $html,
		'personalized' => ! empty($result['personalized']),
		'source'       => isset($result['source']) ? (string) $result['source'] : 'order_count',
	));
}
add_action('wp_ajax_consucorner_get_overall_bestsellers',        'consucorner_ajax_get_overall_bestsellers');
add_action('wp_ajax_nopriv_consucorner_get_overall_bestsellers', 'consucorner_ajax_get_overall_bestsellers');

/* ============================================================
   AUTO-CREATE WORDPRESS PAGES
   ============================================================ */

/**
 * Auto-create the "Home" page and set it as the static front page.
 */
function consucorner_setup_home_page()
{
	if (get_option('consucorner_home_page_ready')) {
		return;
	}

	$existing = get_page_by_path('home', OBJECT, 'page');

	if ($existing && 'publish' === $existing->post_status) {
		$page_id = (int) $existing->ID;
	} else {
		$page_id = wp_insert_post(array(
			'post_title'   => 'Home',
			'post_name'    => 'home',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
			'post_author'  => 1,
		));

		if (is_wp_error($page_id)) {
			return;
		}

		update_post_meta($page_id, '_wp_page_template', 'front-page.php');
	}

	update_option('show_on_front', 'page');
	update_option('page_on_front', $page_id);
	update_option('consucorner_home_page_ready', true);
}
add_action('after_setup_theme', 'consucorner_setup_home_page');

/**
 * Auto-create About, Contact, Privacy Policy, and Vendor pages.
 */
function consucorner_setup_static_pages()
{
	$pages = array(
		array('option' => 'consucorner_about_page_ready',   'title' => 'About',          'slug' => 'about',          'template' => 'page-about.php'),
		array('option' => 'consucorner_contact_page_ready', 'title' => 'Contact',         'slug' => 'contact',        'template' => 'page-contact.php'),
		array('option' => 'consucorner_privacy_page_ready', 'title' => 'Privacy Policy',  'slug' => 'privacy-policy', 'template' => 'page-privacy-policy.php'),
		array('option' => 'consucorner_terms_page_ready',   'title' => 'Terms and Conditions', 'slug' => 'terms-and-conditions', 'template' => 'page-terms-and-conditions.php'),
		array('option' => 'consucorner_vendor_page_ready',  'title' => 'Vendor',          'slug' => 'vendor',         'template' => 'page-vendor.php'),
		array('option' => 'consucorner_blogs_page_ready',   'title' => 'Blogs',           'slug' => 'blogs',          'template' => 'page-archive-posts.php'),
		array('option' => 'consucorner_faq_page_ready',     'title' => 'FAQ',             'slug' => 'faq',            'template' => 'page-faq.php'),
		array('option' => 'consucorner_shop_instruments_page_ready', 'title' => 'Shop Instruments', 'slug' => 'shop-instruments', 'template' => 'page-shop-instruments.php'),
		array('option' => 'consucorner_shop_specialty_page_ready', 'title' => 'Shop Specialty', 'slug' => 'shop-specialty', 'template' => 'page-shop-specialty.php'),
		array('option' => 'consucorner_offers_page_ready', 'title' => 'Offers', 'slug' => 'offers', 'template' => 'page-offers.php'),
		array('option' => 'consucorner_bundles_page_ready', 'title' => 'Bundles', 'slug' => 'bundles', 'template' => 'page-bundles.php'),
		array('option' => 'consucorner_new_order_thank_you_page_ready', 'title' => 'New Order Thank You', 'slug' => 'new-order-thank-you', 'template' => 'page-new-order-thank-you.php'),
	);

	foreach ($pages as $def) {
		if (get_option($def['option'])) {
			continue;
		}

		$existing = get_page_by_path($def['slug'], OBJECT, 'page');

		if ($existing && 'publish' === $existing->post_status) {
			update_post_meta($existing->ID, '_wp_page_template', $def['template']);
		} else {
			$page_id = wp_insert_post(array(
				'post_title'   => $def['title'],
				'post_name'    => $def['slug'],
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
				'post_author'  => 1,
			));

			if (is_wp_error($page_id)) {
				continue;
			}

			update_post_meta($page_id, '_wp_page_template', $def['template']);
		}

		if ('page-offers.php' === $def['template'] && function_exists('cc_offers_seed_page_meta')) {
			$offers_id = isset($existing) && $existing ? (int) $existing->ID : (int) $page_id;
			if ($offers_id > 0) {
				cc_offers_seed_page_meta($offers_id);
			}
		}

		update_option($def['option'], true);
	}
}
add_action('after_setup_theme', 'consucorner_setup_static_pages');

/**
 * Create editable footer menus and assign them to footer locations.
 */
function consucorner_setup_footer_menus()
{
	$version = '20260625-footer-social-customizer';
	if (get_option('consucorner_footer_menus_seeded_version') === $version) {
		return;
	}

	$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	$menus    = array(
		'footer-explore'  => array(
			'name'  => 'Footer - Explore',
			'items' => array(
				array('title' => 'About Us', 'url' => home_url('/about/')),
				array('title' => 'Our Shop', 'url' => $shop_url),
			),
		),
		'footer-services' => array(
			'name'  => 'Footer - Services',
			'items' => array(
				array('title' => 'Fast Delivery', 'url' => home_url('/help/shipping/')),
				array('title' => 'Returns & Exchanges', 'url' => home_url('/help/return-refund/')),
			),
		),
		'footer-legal'    => array(
			'name'  => 'Footer - Legal',
			'items' => array(
				array('title' => 'Terms of Service', 'url' => home_url('/help/terms/')),
				array('title' => 'Privacy Policy', 'url' => home_url('/privacy-policy/')),
				array('title' => 'Cookie Policy', 'url' => home_url('/help/cookies/')),
				array('title' => 'Refund Policy', 'url' => home_url('/help/return-refund/')),
			),
		),
	);

	$locations = (array) get_theme_mod('nav_menu_locations', array());
	$is_fresh  = empty(get_option('consucorner_footer_menus_seeded_version'));

	foreach ($menus as $location => $menu) {
		$menu_obj = wp_get_nav_menu_object($menu['name']);
		$menu_id  = $menu_obj ? (int) $menu_obj->term_id : wp_create_nav_menu($menu['name']);

		if (is_wp_error($menu_id)) {
			continue;
		}

		if (! $menu_obj && $is_fresh) {
			consucorner_replace_footer_nav_menu_items($menu_id, $menu['items']);
		}

		if (empty($locations[$location])) {
			$locations[$location] = $menu_id;
		}
	}

	$social     = consucorner_footer_social_menu_defaults();
	$social_loc = consucorner_footer_social_menu_location();
	$social_id  = ! empty($locations[$social_loc]) ? (int) $locations[$social_loc] : 0;

	if ($social_id < 1) {
		$social_obj = wp_get_nav_menu_object($social['name']);
		$social_id  = $social_obj ? (int) $social_obj->term_id : wp_create_nav_menu($social['name']);
	}

	if (! is_wp_error($social_id) && $social_id > 0) {
		if ($is_fresh) {
			consucorner_replace_footer_nav_menu_items($social_id, $social['items']);
		} else {
			consucorner_seed_footer_social_menu($social_id);
		}
		$locations[$social_loc] = $social_id;
	}

	set_theme_mod('nav_menu_locations', $locations);
	update_option('consucorner_footer_menus_seeded_version', $version);
}
add_action('after_setup_theme', 'consucorner_setup_footer_menus', 30);

/* ============================================================
   MINI-CART: WC CART JSON AJAX
   Returns the current WC session cart as JSON so the JS mini-cart
   drawer can stay in sync with the server-side WooCommerce cart.
   Endpoint: admin-ajax.php?action=cc_get_cart_json  (nonce: cc_cart_nonce)
   ============================================================ */
/**
 * Resolve the maximum purchasable quantity for a product.
 *
 * Returns 0 when there is no hard cap (stock not managed, or backorders
 * allowed), otherwise the remaining stock quantity. Used to stop customers
 * exceeding available inventory in the mini-cart / cart steppers.
 *
 * @param WC_Product $product Cart line product.
 * @return int
 */
function cc_get_product_max_qty($product)
{
	if (! $product || ! is_a($product, 'WC_Product')) {
		return 0;
	}

	if (! $product->managing_stock() || $product->backorders_allowed()) {
		return 0;
	}

	$stock = $product->get_stock_quantity();
	if (null === $stock) {
		return 0;
	}

	return max(0, (int) $stock);
}

/**
 * Format cart variation attributes for mini-cart display (human labels + term names).
 *
 * @param array<string, string> $variation Raw WC cart variation array.
 * @param int                   $parent_id Parent product ID.
 * @return array<string, string>
 */
function cc_format_cart_item_variation_labels($variation, $parent_id = 0)
{
	if (empty($variation) || ! is_array($variation)) {
		return array();
	}

	$product = $parent_id ? wc_get_product($parent_id) : null;
	$out     = array();

	foreach ($variation as $key => $value) {
		if ($value === '' || $value === null) {
			continue;
		}

		$taxonomy = str_replace('attribute_', '', (string) $key);
		$label    = function_exists('wc_attribute_label')
			? wc_attribute_label($taxonomy, $product)
			: $taxonomy;

		if (taxonomy_exists($taxonomy)) {
			$term          = get_term_by('slug', $value, $taxonomy);
			$display_value = ($term && ! is_wp_error($term)) ? $term->name : $value;
		} else {
			$display_value = $value;
		}

		$out[$label] = $display_value;
	}

	return $out;
}

/**
 * Build a normalised cart-item array from the current WC session cart.
 *
 * @return array{ items: list<array>, count: int }
 */
function cc_build_mini_cart_items()
{
	if (! function_exists('WC') || ! WC()->cart) {
		return array('items' => array(), 'count' => 0);
	}

	if (function_exists('consucorner_remove_quote_products_from_cart')) {
		consucorner_remove_quote_products_from_cart(WC()->cart);
		if (method_exists(WC()->cart, 'set_session')) {
			WC()->cart->set_session();
		}
	}

	$items = array();
	foreach (WC()->cart->get_cart() as $key => $cart_item) {
		/** @var WC_Product $product */
		$product = $cart_item['data'];
		if (! $product || ! $product->exists()) {
			continue;
		}

		$img_id  = $product->get_image_id();
		$img_url = $img_id
			? wp_get_attachment_image_url($img_id, 'woocommerce_thumbnail')
			: '';
		if (! $img_url) {
			$img_url = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src() : '';
		}

		$line_qty = (int) $cart_item['quantity'];

		/*
		 * Use the authoritative per-line subtotal (which already reflects offer-deal /
		 * bulk pricing applied in woocommerce_before_calculate_totals) divided by the
		 * quantity, rather than $product->get_price(). The cart-item price object is not
		 * always re-mutated on read-only requests (e.g. cc_get_cart_json), so get_price()
		 * can return the base catalog price and mismatch the real cart/checkout total.
		 */
		$unit_price = (float) (function_exists('wc_get_price_including_tax') ? wc_get_price_including_tax($product) : $product->get_price());
		if ($line_qty > 0 && isset($cart_item['line_subtotal'])) {
			$line_sub     = (float) $cart_item['line_subtotal'];
			$line_sub_tax = isset($cart_item['line_subtotal_tax']) ? (float) $cart_item['line_subtotal_tax'] : 0.0;
			$line_total   = $line_sub + $line_sub_tax;
			// line_subtotal can be 0 before calculate_totals(); never overwrite a real catalog price with 0.
			if ($line_total > 0) {
				$unit_price = $line_total / $line_qty;
			}
		}

		$bulk_min_qty = function_exists('cc_get_product_bulk_min_qty') ? cc_get_product_bulk_min_qty($product, array('require_stock' => false)) : 0;
		$bulk_step    = function_exists('cc_get_product_bulk_qty_step') ? cc_get_product_bulk_qty_step($product) : 1;
		$bulk_display = function_exists('cc_get_cart_item_bulk_price_display_data')
			? cc_get_cart_item_bulk_price_display_data($cart_item)
			: null;

		$items[] = array(
			'wcKey'            => $key,
			'id'               => (int) $cart_item['product_id'],
			'name'             => $product->get_name(),
			'price'            => $unit_price,
			'qty'              => (int) $cart_item['quantity'],
			'maxQty'           => cc_get_product_max_qty($product),
			'bulkMinQty'       => $bulk_min_qty,
			'bulkStep'         => $bulk_step,
			'bulkPriceDisplay' => $bulk_display,
			'image'            => $img_url,
			'permalink'        => (string) get_permalink($cart_item['product_id']),
			'variation'        => cc_format_cart_item_variation_labels(
				isset($cart_item['variation']) ? $cart_item['variation'] : array(),
				(int) $cart_item['product_id']
			),
			'bundleId'         => isset($cart_item['cc_bundle_id']) ? (int) $cart_item['cc_bundle_id'] : 0,
			'bundleInstance'   => isset($cart_item['cc_bundle_instance']) ? (string) $cart_item['cc_bundle_instance'] : '',
			'bundleTitle'      => isset($cart_item['cc_bundle_name']) ? (string) $cart_item['cc_bundle_name'] : '',
			'bundlePrice'      => isset($cart_item['cc_bundle_price']) ? (float) $cart_item['cc_bundle_price'] : 0,
			'bundleSize'       => isset($cart_item['cc_bundle_size']) ? (int) $cart_item['cc_bundle_size'] : 0,
		);
	}

	return array(
		'items' => $items,
		'count' => (int) WC()->cart->get_cart_contents_count(),
	);
}

/**
 * AJAX: return the WC cart as JSON.
 */
function cc_get_cart_json_ajax()
{
	check_ajax_referer('cc_cart_nonce', 'nonce');

	if (function_exists('WC') && WC()->cart) {
		WC()->cart->calculate_totals();
	}

	wp_send_json_success(cc_build_mini_cart_items());
}
add_action('wp_ajax_cc_get_cart_json',        'cc_get_cart_json_ajax');
add_action('wp_ajax_nopriv_cc_get_cart_json', 'cc_get_cart_json_ajax');

/**
 * AJAX: update a single cart item's quantity.
 */
function cc_update_cart_qty_ajax()
{
	check_ajax_referer('cc_cart_nonce', 'nonce');

	$key        = isset($_POST['cart_item_key']) ? sanitize_text_field(wp_unslash($_POST['cart_item_key'])) : '';
	$product_id = isset($_POST['product_id'])     ? absint($_POST['product_id'])                              : 0;
	$qty        = isset($_POST['quantity'])       ? max(0, (int) $_POST['quantity'])                         : 0;

	if (! function_exists('WC') || ! WC()->cart) {
		wp_send_json_error('Invalid request');
	}

	if (! $key && $product_id) {
		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			if (isset($cart_item['product_id']) && (int) $cart_item['product_id'] === $product_id) {
				$key = $cart_item_key;
				break;
			}
		}
	}

	if (! $key) {
		wp_send_json_error('Cart item not found');
	}

	$cart_item        = WC()->cart->get_cart_item($key);
	$bundle_instance  = ($cart_item && isset($cart_item['cc_bundle_instance'])) ? (string) $cart_item['cc_bundle_instance'] : '';

	if ('' !== $bundle_instance) {
		/* Bundle lines have a locked quantity (fixed at add-time). Removing
		   one (qty 0) cascades to the whole instance via the
		   woocommerce_cart_item_removed hook in inc/product-bundles.php;
		   any other quantity change request is simply ignored. */
		if ($qty === 0) {
			WC()->cart->remove_cart_item($key);
		}
	} elseif ($qty === 0) {
		WC()->cart->remove_cart_item($key);
	} else {
		if ($cart_item && isset($cart_item['data'])) {
			$product = $cart_item['data'];

			/* Clamp to available stock so the cart can never hold an
			   unfulfillable quantity that would error out at checkout. */
			$max = cc_get_product_max_qty($product);
			if ($max > 0 && $qty > $max) {
				$qty = $max;
			}

			/* Bulk-only products can't be dropped below their minimum bulk
			   quantity via +/- steppers, unless the qty already qualifies
			   for an Offer Deal (qty >= deal threshold). */
			$min_qty = function_exists('cc_get_product_bulk_min_qty') ? cc_get_product_bulk_min_qty($product) : 0;
			if ($min_qty >= 2 && $qty < $min_qty) {
				$deal = function_exists('cc_offers_get_product_deal') ? cc_offers_get_product_deal($product) : null;
				if (! ($deal && $qty >= (int) $deal['qty'])) {
					$qty = $min_qty;
				}
			}
		}
		WC()->cart->set_quantity($key, $qty, true);
	}

	WC()->cart->calculate_totals();
	if (method_exists(WC()->cart, 'set_session')) {
		WC()->cart->set_session();
	}

	wp_send_json_success(cc_build_mini_cart_items());
}
add_action('wp_ajax_cc_update_cart_qty',        'cc_update_cart_qty_ajax');
add_action('wp_ajax_nopriv_cc_update_cart_qty', 'cc_update_cart_qty_ajax');

/* ============================================================
   GET A QUOTE: FORMINATOR CAPTURE
   Stores quote data briefly so the thank-you page can render it
   after Forminator redirects to /quote-order-thank-you/.
   ============================================================ */
/**
 * Flatten a Forminator field value into readable text.
 *
 * @param mixed $value Raw Forminator field value.
 * @return string
 */
function cc_quote_form_flatten_value($value)
{
	if (is_array($value)) {
		$parts = array();
		array_walk_recursive(
			$value,
			function ($item) use (&$parts) {
				if ('' !== $item && null !== $item) {
					$parts[] = (string) $item;
				}
			}
		);
		return trim(implode(' ', $parts));
	}

	return trim((string) $value);
}

/**
 * Capture quote form data before Forminator finishes the AJAX response.
 *
 * @param object $entry            Forminator entry object.
 * @param int    $form_id          Submitted Forminator form ID.
 * @param array  $field_data_array Submitted field data.
 * @return void
 */
function cc_quote_form_capture_data($entry, $form_id, $field_data_array)
{
	$quote_form_id = cc_get_quote_forminator_form_id();
	if ((int) $form_id !== $quote_form_id) {
		return;
	}

	$name    = '';
	$email   = '';
	$phone   = '';
	$message = '';
	$product = '';

	foreach ((array) $field_data_array as $field) {
		$field_name  = isset($field['name']) ? sanitize_key($field['name']) : '';
		$field_label = isset($field['label']) ? strtolower(sanitize_text_field($field['label'])) : '';
		$field_value = isset($field['value']) ? cc_quote_form_flatten_value($field['value']) : '';
		$field_value = sanitize_text_field($field_value);

		if ('' === $field_name && '' === $field_label) {
			continue;
		}

		if (0 === strpos($field_name, 'name-') || false !== strpos($field_label, 'full name')) {
			$name = $field_value;
			continue;
		}

		if (0 === strpos($field_name, 'email-') || false !== strpos($field_label, 'email')) {
			$email = sanitize_email($field_value);
			continue;
		}

		if (0 === strpos($field_name, 'phone-') || false !== strpos($field_label, 'phone')) {
			$phone = $field_value;
			continue;
		}

		if (0 === strpos($field_name, 'textarea-') || 'text' === $field_label) {
			$message = $field_value;
			continue;
		}

		if (false !== strpos($field_name, 'page') || false !== strpos($field_label, 'page name') || 0 === strpos($field_name, 'hidden-')) {
			$product = $field_value;
		}
	}

	if (! $product && isset($_COOKIE['cc_quote_product'])) {
		$product = sanitize_text_field(wp_unslash($_COOKIE['cc_quote_product']));
	}

	$token = isset($_COOKIE['cc_quote_token']) ? sanitize_key(wp_unslash($_COOKIE['cc_quote_token'])) : '';
	if (! $token) {
		$token = sanitize_key(wp_generate_uuid4());
	}

	$key = 'cc_quote_' . $token;
	set_transient(
		$key,
		array(
			'name'    => $name,
			'email'   => $email,
			'phone'   => $phone,
			'message' => $message,
			'product' => $product,
			'time'    => wp_date('M j \a\t g:i A'),
			'entry'   => isset($entry->entry_id) ? $entry->entry_id : 0,
		),
		10 * MINUTE_IN_SECONDS
	);

	setcookie(
		'cc_quote_key',
		$key,
		array(
			'expires'  => time() + 600,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
	$_COOKIE['cc_quote_key'] = $key;
}
add_action('forminator_custom_form_submit_before_set_fields', 'cc_quote_form_capture_data', 10, 3);

/* ============================================================
   REQUIRED INCLUDES
   ============================================================ */
require get_template_directory() . '/inc/attribute-images.php';
require_once get_template_directory() . '/inc/attribute-swatches.php';
require_once get_template_directory() . '/inc/variable-product.php';
require get_template_directory() . '/inc/product-cat-icon.php';
require get_template_directory() . '/inc/category-filters.php';
require_once get_template_directory() . '/inc/offers-page.php';
require_once get_template_directory() . '/inc/offers-link-builder.php';
require_once get_template_directory() . '/inc/product-pricing-tabs.php';
require_once get_template_directory() . '/inc/product-bundles.php';
require_once get_template_directory() . '/inc/offer-campaigns.php';
require_once get_template_directory() . '/inc/wc-free-shipping.php';
require get_template_directory() . '/inc/product-tour.php';
require get_template_directory() . '/inc/product-procedures-taxonomy.php';
require get_template_directory() . '/inc/product-specialties-taxonomy.php';
require get_template_directory() . '/inc/product-condition-taxonomy.php';
require get_template_directory() . '/inc/product-country-origin-taxonomy.php';
require get_template_directory() . '/inc/product-mega-menu.php';
require get_template_directory() . '/inc/explore-mega-menu.php';
require get_template_directory() . '/inc/mobile-drawer-menu.php';
require get_template_directory() . '/inc/help-pages.php';
require get_template_directory() . '/inc/blog-shortcodes.php';
require_once get_template_directory() . '/inc/order-number.php';
require_once get_template_directory() . '/inc/order-email.php';
require get_template_directory() . '/inc/profile-account.php';
require get_template_directory() . '/inc/profile-report-form-installer.php';
require get_template_directory() . '/inc/template-tags.php';
require get_template_directory() . '/inc/theme-icons.php';
require get_template_directory() . '/inc/template-functions.php';
require get_template_directory() . '/inc/footer.php';
require get_template_directory() . '/inc/customizer.php';
require get_template_directory() . '/inc/shop-promo-customizer.php';
require get_template_directory() . '/inc/term-promo-banner.php';
require get_template_directory() . '/inc/mega-menu-customizer.php';
require get_template_directory() . '/inc/admin-icon-picker.php';
require get_template_directory() . '/inc/meta-boxes.php';
require_once get_template_directory() . '/inc/admin-vendor-ledger.php';
require_once get_template_directory() . '/inc/customer-wallet.php';
require_once get_template_directory() . '/inc/order-return-workflow.php';
require_once get_template_directory() . '/inc/returns-refund-service.php';
require_once get_template_directory() . '/inc/returns-rma-config.php';
require_once get_template_directory() . '/inc/admin-wallet-refunds.php';
require_once get_template_directory() . '/inc/returns-report.php';
require_once get_template_directory() . '/inc/order-cancel-requests.php';
require_once get_template_directory() . '/inc/dokan-overrides.php';
require_once get_template_directory() . '/inc/search-experience.php';
require_once get_template_directory() . '/inc/home-bestsellers.php';
require_once get_template_directory() . '/inc/checkout-phone.php';
require_once get_template_directory() . '/inc/checkout-error-modal.php';
require_once get_template_directory() . '/inc/cart-share.php';
