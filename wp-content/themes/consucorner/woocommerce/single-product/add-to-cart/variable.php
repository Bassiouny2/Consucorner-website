<?php
/**
 * Variable product add to cart — delegates to split ConsuCorner templates.
 *
 * @package ConsuCorner
 * @version 9.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product ) {
	return;
}

$GLOBALS['cc_variable_product_args'] = array(
	'available_variations' => $available_variations,
	'attributes'           => $attributes,
	'selected_attributes'  => isset( $selected_attributes ) ? $selected_attributes : array(),
	'product'              => $product,
);

cc_variable_product_form_start();
cc_variable_product_render_variations();
cc_variable_product_render_actions();
cc_variable_product_form_end();
