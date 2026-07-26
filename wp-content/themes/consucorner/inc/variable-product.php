<?php
/**
 * Split variable-product form rendering for ConsuCorner PDP layout.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Holds template args between split form partials.
 *
 * @var array<string, mixed>
 */
$GLOBALS['cc_variable_product_args'] = array();

/**
 * Prepare variation data and enqueue scripts.
 *
 * @param WC_Product $product Variable product.
 * @return void
 */
function cc_variable_product_prepare( $product ) {
	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
		return;
	}

	wp_enqueue_script( 'wc-add-to-cart-variation' );

	$get_variations = count( $product->get_children() ) <= apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product );

	$GLOBALS['cc_variable_product_args'] = array(
		'available_variations' => $get_variations ? $product->get_available_variations() : false,
		'attributes'           => $product->get_variation_attributes(),
		'selected_attributes'  => $product->get_default_attributes(),
		'product'              => $product,
	);
}

/**
 * @return array<string, mixed>
 */
function cc_variable_product_get_args() {
	return isset( $GLOBALS['cc_variable_product_args'] ) ? (array) $GLOBALS['cc_variable_product_args'] : array();
}

/**
 * Output form opening markup.
 *
 * @return void
 */
function cc_variable_product_form_start() {
	$args = cc_variable_product_get_args();
	if ( empty( $args['product'] ) ) {
		return;
	}

	wc_get_template( 'single-product/add-to-cart/variable-form-start.php', $args );
}

/**
 * Output variation attribute rows (under price).
 *
 * @return void
 */
function cc_variable_product_render_variations() {
	$args = cc_variable_product_get_args();
	if ( empty( $args['product'] ) ) {
		return;
	}

	wc_get_template( 'single-product/add-to-cart/variable-variations.php', $args );
}

/**
 * Output qty + CTA row.
 *
 * @return void
 */
function cc_variable_product_render_actions() {
	$args = cc_variable_product_get_args();
	if ( empty( $args['product'] ) ) {
		return;
	}

	wc_get_template( 'single-product/add-to-cart/variable-actions.php', $args );
}

/**
 * Close variable product form.
 *
 * @return void
 */
function cc_variable_product_form_end() {
	$args = cc_variable_product_get_args();
	if ( empty( $args['product'] ) ) {
		return;
	}

	wc_get_template( 'single-product/add-to-cart/variable-form-end.php', $args );
}
