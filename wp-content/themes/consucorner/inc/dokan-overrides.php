<?php
/**
 * Dokan integration overrides
 * ---------------------------------------------------------------------------
 * Dokan Lite hooks into `woocommerce_is_purchasable` in three places, any
 * of which can force a product to non-purchasable for guest visitors:
 *
 *   1. dokan-lite/includes/CatalogMode/Hooks.php  (priority 20)
 *      "Catalog Mode" feature — vendors / store admin can hide the
 *      Add-to-Cart button entirely. Provides a documented opt-out
 *      filter (`dokan_catalog_mode_hide_add_to_cart_button`).
 *
 *   2. dokan-lite/includes/ReverseWithdrawal/Hooks.php  (priority 20)
 *      Auto-enables catalog mode for vendors with unpaid reverse-
 *      withdrawal balances. Has NO documented opt-out.
 *
 *   3. dokan-lite/includes/wc-functions.php
 *      `dokan_vendor_own_product_purchase_restriction()` (priority 10)
 *      Blocks vendors from buying their own products — but has a
 *      buggy `false === $is_purchasable` short-circuit that can lock
 *      a product as non-purchasable for *every* visitor once any
 *      upstream filter has returned false.
 *
 * Project policy: every in-stock product that has a valid price MUST be
 * purchasable for ALL visitors, including guests in incognito sessions.
 * Without this override, archive cards display "Read more" and the
 * single product page renders "This product is currently unavailable."
 * even though the product is in stock and priced.
 *
 * Strategy: run our own callback at a very late priority on
 * `woocommerce_is_purchasable`. It re-asserts purchasability for any
 * product that WooCommerce itself would consider buyable (in stock +
 * has a price + published or current user can edit). This bypasses
 * the three Dokan filters above without removing them, so any future
 * Dokan update that renames internal hooks won't break our override.
 *
 * We also explicitly opt out of the documented Catalog Mode hooks so
 * the price isn't blanked-out in the UI.
 *
 * @package consucorner
 */

defined('ABSPATH') || exit;

if (! function_exists('consucorner_force_in_stock_products_purchasable')) {
	/**
	 * Force any in-stock, priced, published product to be purchasable —
	 * regardless of vendor-level catalog mode or reverse-withdrawal
	 * holds applied by Dokan.
	 *
	 * @param bool       $is_purchasable Current value from upstream filters.
	 * @param WC_Product $product        Product being evaluated.
	 * @return bool
	 */
	function consucorner_force_in_stock_products_purchasable($is_purchasable, $product) {
		if (! $product instanceof WC_Product) {
			return $is_purchasable;
		}

		// Already purchasable? Nothing to do.
		if (true === $is_purchasable) {
			return true;
		}

		// Don't override the WC native rejection for actually out-of-stock
		// or unpriced products — those genuinely should not be buyable.
		if (! $product->is_in_stock()) {
			return false;
		}

		if ('' === $product->get_price()) {
			return $is_purchasable;
		}

		// Don't override unpublished/draft products. Public visitors
		// shouldn't see them anyway; admins editing in the dashboard
		// keep the WC native behaviour.
		$status = $product->get_status();
		if ('publish' !== $status && ! current_user_can('edit_post', $product->get_id())) {
			return $is_purchasable;
		}

		return true;
	}
}

// Priority 9999: run AFTER every Dokan hook (priorities 10 and 20) and
// after any other plugin that might pile on later.
add_filter('woocommerce_is_purchasable', 'consucorner_force_in_stock_products_purchasable', 9999, 2);

if (! function_exists('consucorner_return_string_no')) {
	/**
	 * Helper returning the literal string 'no'. Dokan compares against
	 * `'no' === $value` rather than a boolean, so the built-in
	 * `__return_false` would not work here.
	 *
	 * @return string
	 */
	function consucorner_return_string_no() {
		return 'no';
	}
}

/**
 * Disable Dokan Catalog Mode "hide add to cart button" behaviour
 * globally. Documented Dokan opt-out — kept for completeness so the
 * intent is explicit even though the priority 9999 filter above would
 * already cover it.
 */
add_filter('dokan_catalog_mode_hide_add_to_cart_button', 'consucorner_return_string_no', 99);

/**
 * Disable Dokan Catalog Mode "hide product price" behaviour
 * globally. Prevents prices from being blanked-out for guests.
 */
add_filter('dokan_catalog_mode_hide_product_price', 'consucorner_return_string_no', 99);
