<?php
/**
 * Specialty taxonomy archive.
 *
 * Reuses the WooCommerce product archive template so every URL like
 * /specialty/endoscopy/ gets the same shop grid, filters, sorting,
 * pagination, and product card UI as the main shop/category pages.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

if (function_exists('wc_get_template')) {
	wc_get_template('archive-product.php');
	return;
}

locate_template('woocommerce/archive-product.php', true);
