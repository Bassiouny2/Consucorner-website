<?php

/**
 * Guided tours v2 — enqueue and localization.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

require get_template_directory() . '/inc/product-tour-state.php';
require get_template_directory() . '/inc/product-tour-rest.php';

/**
 * Asset version helper.
 *
 * @param string $relative_path Path relative to theme root.
 * @return string
 */
function consucorner_tours_asset_version($relative_path)
{
	$full = get_template_directory() . $relative_path;
	return file_exists($full) ? (string) filemtime($full) : (defined('_S_VERSION') ? _S_VERSION : '1');
}

/**
 * Enqueue tour-related scripts and styles.
 */
function consucorner_enqueue_product_tour()
{
	if (function_exists('is_checkout') && is_checkout()) {
		return;
	}

	$phase          = consucorner_product_tour_phase();
	$welcome        = consucorner_should_enqueue_welcome_assets();
	$driver         = consucorner_should_enqueue_driver_tour_assets();
	// Home + Welcome: load Driver on the same request so specialty path can run the home tour immediately.
	if ($welcome && 'home' === $phase) {
		$driver = true;
	} elseif ($welcome) {
		$driver = false;
	}
	$thankyou       = function_exists('is_order_received_page') && is_order_received_page();
	$needs_analytics = $welcome || $driver || $thankyou;

	if (! $welcome && ! $driver && ! $thankyou) {
		return;
	}

	$uri = get_template_directory_uri();

	if ($welcome) {
		wp_enqueue_style(
			'consucorner-welcome-modal',
			$uri . '/assets/css/welcome-modal.css',
			array(),
			consucorner_tours_asset_version('/assets/css/welcome-modal.css')
		);
		wp_enqueue_script(
			'consucorner-product-tour-analytics',
			$uri . '/assets/js/product-tour-analytics.js',
			array(),
			consucorner_tours_asset_version('/assets/js/product-tour-analytics.js'),
			true
		);
		wp_enqueue_script(
			'consucorner-welcome-modal',
			$uri . '/assets/js/welcome-modal.js',
			array('consucorner-product-tour-analytics'),
			consucorner_tours_asset_version('/assets/js/welcome-modal.js'),
			true
		);
	}

	if ($driver) {
		$driver_css = '/assets/vendor/driver.js/driver.css';
		$driver_js  = '/assets/vendor/driver.js/driver.min.js';

		wp_enqueue_style(
			'consucorner-driver',
			$uri . $driver_css,
			array(),
			consucorner_tours_asset_version($driver_css)
		);
		wp_enqueue_style(
			'consucorner-product-tour',
			$uri . '/assets/css/product-tour.css',
			array('consucorner-driver'),
			consucorner_tours_asset_version('/assets/css/product-tour.css')
		);
		wp_enqueue_script(
			'consucorner-driver',
			$uri . $driver_js,
			array(),
			consucorner_tours_asset_version($driver_js),
			true
		);
		wp_enqueue_script(
			'consucorner-product-tour-analytics',
			$uri . '/assets/js/product-tour-analytics.js',
			array(),
			consucorner_tours_asset_version('/assets/js/product-tour-analytics.js'),
			true
		);
		wp_enqueue_script(
			'consucorner-product-tour-phases',
			$uri . '/assets/js/product-tour-phases.js',
			array('consucorner-driver'),
			consucorner_tours_asset_version('/assets/js/product-tour-phases.js'),
			true
		);
		wp_enqueue_script(
			'consucorner-product-tour-core',
			$uri . '/assets/js/product-tour-core.js',
			array('consucorner-product-tour-phases', 'consucorner-product-tour-analytics'),
			consucorner_tours_asset_version('/assets/js/product-tour-core.js'),
			true
		);
	}

	if ($needs_analytics && ! $driver) {
		wp_enqueue_script(
			'consucorner-product-tour-analytics',
			$uri . '/assets/js/product-tour-analytics.js',
			array(),
			consucorner_tours_asset_version('/assets/js/product-tour-analytics.js'),
			true
		);
		if ($thankyou) {
			wp_enqueue_script(
				'consucorner-product-tour-core',
				$uri . '/assets/js/product-tour-core.js',
				array('consucorner-product-tour-analytics'),
				consucorner_tours_asset_version('/assets/js/product-tour-core.js'),
				true
			);
		}
	}

	$config = consucorner_get_product_tour_config($phase, $welcome, $driver, $thankyou);

	if ($welcome) {
		wp_localize_script('consucorner-welcome-modal', 'ccProductTour', $config);
	}
	if ($driver || $thankyou) {
		wp_localize_script(
			$driver ? 'consucorner-product-tour-core' : 'consucorner-product-tour-analytics',
			'ccProductTour',
			$config
		);
	} elseif ($welcome) {
		// welcome-only already localized above.
	}
}
add_action('wp_enqueue_scripts', 'consucorner_enqueue_product_tour', 25);

/**
 * Build ccProductTour localization object.
 *
 * @param string|false $phase Current phase.
 * @param bool         $welcome Welcome assets loading.
 * @param bool         $driver Driver assets loading.
 * @param bool         $thankyou Thank-you page.
 * @return array<string, mixed>
 */
function consucorner_get_product_tour_config($phase, $welcome, $driver, $thankyou)
{
	$shop_url  = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	$home_url  = home_url('/');
	$cart_count = 0;
	if (function_exists('WC') && WC()->cart) {
		$cart_count = (int) WC()->cart->get_cart_contents_count();
	}

	$order_id = 0;
	if ($thankyou) {
		global $wp;
		if (isset($wp->query_vars['order-received'])) {
			$order_id = absint($wp->query_vars['order-received']);
		}
	}

	$user_state = is_user_logged_in() ? consucorner_tours_get_user_state() : null;

	return array(
		'phase'              => $phase ? $phase : '',
		'storageKey'         => 'cc_site_tours_v2',
		'legacyStorageKey'   => 'cc_product_tour_v1',
		'idleCookie'         => CC_TOURS_IDLE_COOKIE,
		'welcomeSeenCookie'  => CC_TOURS_WELCOME_SEEN_COOKIE,
		'startDelayMs'       => 400,
		'mobileMaxWidth'     => 768,
		'isLoggedIn'         => is_user_logged_in(),
		'mergeOnLogin'       => consucorner_tours_needs_login_merge(),
		'restUrl'            => esc_url_raw(rest_url('cc/v1/tours/state')),
		'restNonce'          => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
		'serverState'        => $user_state,
		'welcomePending'     => $welcome,
		'driverEnabled'      => $driver,
		'thankyouPage'       => $thankyou,
		'orderId'            => $order_id,
		'isSpecialtyArchive' => consucorner_tours_is_specialty_archive(),
		'cartCount'          => $cart_count,
		'shopUrl'            => esc_url($shop_url),
		'homeUrl'            => esc_url($home_url),
		'accountUrl'         => function_exists('wc_get_page_permalink') ? esc_url(wc_get_page_permalink('myaccount')) : '',
		'strings'            => consucorner_get_product_tour_strings(),
	);
}

/**
 * Localized tour copy (v2 roadmap §5).
 *
 * @return array<string, mixed>
 */
function consucorner_get_product_tour_strings()
{
	return array(
		'skip'         => esc_html__('Skip', 'consucorner'),
		'skipAll'      => esc_html__('Skip', 'consucorner'),
		'next'         => esc_html__('Next', 'consucorner'),
		'back'         => esc_html__('Back', 'consucorner'),
		'done'         => esc_html__('Done', 'consucorner'),
		'progress'     => esc_html__('{{current}} / {{total}}', 'consucorner'),
		'welcome'      => array(
			'title'    => esc_html__('Welcome to ConsuCorner', 'consucorner'),
			'subtitle' => esc_html__('Find medical supplies fast. Choose how you want to start.', 'consucorner'),
			'paths'    => array(
				'specialty'  => esc_html__('Browse by specialty', 'consucorner'),
				'search'     => esc_html__('Search products', 'consucorner'),
				'categories' => esc_html__('Explore categories', 'consucorner'),
			),
			'skipAll'    => esc_html__('Skip', 'consucorner'),
		),
		'shop'         => array(
			'filterBar' => array(
				'title'       => esc_html__('Filter your results', 'consucorner'),
				'description' => esc_html__('Use these filters to narrow products by specialty, category, and more.', 'consucorner'),
			),
			'specialty' => array(
				'title'       => esc_html__('Find by specialty', 'consucorner'),
				'description' => esc_html__('Filter by your medical specialty to see relevant products immediately.', 'consucorner'),
			),
			'category'  => array(
				'title'       => esc_html__('Refine by category', 'consucorner'),
				'description' => esc_html__('Pick a product category to focus the list on the equipment you need.', 'consucorner'),
			),
			'allFilters' => array(
				'title'       => esc_html__('Find by specialty', 'consucorner'),
				'description' => esc_html__('Filter by your medical specialty to see relevant products immediately.', 'consucorner'),
			),
			'grid'      => array(
				'title'       => esc_html__('Refine or open a product', 'consucorner'),
				'description' => esc_html__('Narrow by category, then tap a product to view price, stock, and vendor.', 'consucorner'),
			),
		),
		'product'      => array(
			'details'   => array(
				'title'       => esc_html__('Check details', 'consucorner'),
				'description' => esc_html__('Price, stock, and vendor — confirm before you order.', 'consucorner'),
			),
			'addToCart' => array(
				'title'       => esc_html__('Add to cart', 'consucorner'),
				'description' => esc_html__('Set quantity and add to cart. Checkout when ready.', 'consucorner'),
			),
		),
		'home'         => array(
			'search'           => array(
				'title'       => esc_html__('Search products', 'consucorner'),
				'description' => esc_html__(
					'Type a product or category name — matching items appear as you search.',
					'consucorner'
				),
			),
			'specialtySection' => array(
				'title'       => esc_html__('Browse by your specialty', 'consucorner'),
				'description' => esc_html__(
					'This section shows tools matched to medical fields. Scroll through specialties and products below.',
					'consucorner'
				),
			),
			'carousel'         => array(
				'title'       => esc_html__('Pick your specialty', 'consucorner'),
				'description' => esc_html__(
					'Tap a specialty pill to load matching products in the grid.',
					'consucorner'
				),
			),
			'popularCategories' => array(
				'title'       => esc_html__('Explore categories', 'consucorner'),
				'description' => esc_html__(
					'Browse popular product categories and open any card to shop that range.',
					'consucorner'
				),
			),
			'categories'       => array(
				'title'       => esc_html__('Pick your specialty', 'consucorner'),
				'description' => esc_html__('Select your field to load matching products below.', 'consucorner'),
			),
			'grid'             => array(
				'title'       => esc_html__('Open a product', 'consucorner'),
				'description' => esc_html__('Tap any item to see full details and add to cart.', 'consucorner'),
			),
		),
		'cart'         => array(
			'list'     => array(
				'title'       => esc_html__('Review your order', 'consucorner'),
				'description' => esc_html__('Confirm items and quantities before checkout.', 'consucorner'),
			),
			'checkout' => array(
				'title'       => esc_html__('Continue to checkout', 'consucorner'),
				'description' => esc_html__('Proceed to delivery and secure payment.', 'consucorner'),
			),
			'share'    => array(
				'title'       => esc_html__('Share your cart', 'consucorner'),
				'description' => esc_html__('Send a link so someone else can load the same products into their cart.', 'consucorner'),
			),
			'coupon'   => array(
				'title'       => esc_html__('Have a coupon?', 'consucorner'),
				'description' => esc_html__('Enter your coupon code here to get a discount before checkout.', 'consucorner'),
			),
		),
		'account'      => array(
			'orders' => array(
				'title'       => esc_html__('Track orders', 'consucorner'),
				'description' => esc_html__('View order status and history anytime.', 'consucorner'),
			),
			'wallet' => array(
				'title'       => esc_html__('Wallet credit', 'consucorner'),
				'description' => esc_html__('See store credit and refunds applied to your account.', 'consucorner'),
			),
		),
		'wishlist'     => array(
			'save' => array(
				'title'       => esc_html__('Saved for later', 'consucorner'),
				'description' => esc_html__('This product is on your wishlist. Re-order quickly from one place.', 'consucorner'),
			),
			'grid' => array(
				'title'       => esc_html__('Find saved products', 'consucorner'),
				'description' => esc_html__('Open My Account → Saved products to add to cart in one tap.', 'consucorner'),
			),
		),
	);
}
