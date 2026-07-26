<?php
/**
 * Functions which enhance the theme by hooking into WordPress
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds custom classes to the array of body classes.
 *
 * @param array $classes Classes for the body element.
 * @return array
 */
function consucorner_body_classes( $classes ) {
	// Adds a class of hfeed to non-singular pages.
	if ( ! is_singular() ) {
		$classes[] = 'hfeed';
	}

	// Adds a class of no-sidebar when there is no sidebar present.
	if ( ! is_active_sidebar( 'sidebar-1' ) ) {
		$classes[] = 'no-sidebar';
	}

	return $classes;
}
add_filter( 'body_class', 'consucorner_body_classes' );

/**
 * Return the shared fallback image URL used across product and vendor UI.
 *
 * @param string $context Optional logical context (product|vendor).
 * @return string
 */
function consucorner_get_fallback_image_url( $context = 'product' ) {
	$filename = 'consucorner icon-logo.jpg';

	return get_template_directory_uri() . '/assets/images/' . rawurlencode( $filename );
}

/**
 * Return product placeholder image URL.
 *
 * @return string
 */
function consucorner_get_product_placeholder_image_url() {
	return consucorner_get_fallback_image_url( 'product' );
}

/**
 * Return vendor placeholder image URL.
 *
 * @return string
 */
function consucorner_get_vendor_placeholder_image_url() {
	return consucorner_get_fallback_image_url( 'vendor' );
}

/**
 * Force WooCommerce default placeholders to use theme fallback.
 *
 * @return string
 */
function consucorner_wc_placeholder_image_src() {
	return consucorner_get_product_placeholder_image_url();
}
add_filter( 'woocommerce_placeholder_img_src', 'consucorner_wc_placeholder_image_src' );

/**
 * Add a pingback url auto-discovery header for single posts, pages, or attachments.
 */
function consucorner_pingback_header() {
	if ( is_singular() && pings_open() ) {
		printf( '<link rel="pingback" href="%s">', esc_url( get_bloginfo( 'pingback_url' ) ) );
	}
}
add_action( 'wp_head', 'consucorner_pingback_header' );
