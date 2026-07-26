<?php
/**
 * Product Condition taxonomy.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the canonical Product Condition terms.
 *
 * @return string[]
 */
function consucorner_get_product_condition_default_terms() {
	return array(
		'New',
		'Open Box',
		'Refurbished',
		'Used',
	);
}

/**
 * Register the Product Condition taxonomy for WooCommerce products.
 */
function consucorner_register_product_condition_taxonomy() {
	$labels = array(
		'name'                       => _x( 'Product Conditions', 'taxonomy general name', 'consucorner' ),
		'singular_name'              => _x( 'Product Condition', 'taxonomy singular name', 'consucorner' ),
		'search_items'               => __( 'Search Product Conditions', 'consucorner' ),
		'popular_items'              => __( 'Popular Product Conditions', 'consucorner' ),
		'all_items'                  => __( 'All Product Conditions', 'consucorner' ),
		'parent_item'                => __( 'Parent Product Condition', 'consucorner' ),
		'parent_item_colon'          => __( 'Parent Product Condition:', 'consucorner' ),
		'edit_item'                  => __( 'Edit Product Condition', 'consucorner' ),
		'view_item'                  => __( 'View Product Condition', 'consucorner' ),
		'update_item'                => __( 'Update Product Condition', 'consucorner' ),
		'add_new_item'               => __( 'Add New Product Condition', 'consucorner' ),
		'new_item_name'              => __( 'New Product Condition Name', 'consucorner' ),
		'separate_items_with_commas' => __( 'Separate conditions with commas', 'consucorner' ),
		'add_or_remove_items'        => __( 'Add or remove product conditions', 'consucorner' ),
		'choose_from_most_used'      => __( 'Choose from the most used conditions', 'consucorner' ),
		'not_found'                  => __( 'No product conditions found.', 'consucorner' ),
		'no_terms'                   => __( 'No product conditions', 'consucorner' ),
		'items_list_navigation'      => __( 'Product conditions list navigation', 'consucorner' ),
		'items_list'                 => __( 'Product conditions list', 'consucorner' ),
		'back_to_items'              => __( 'Back to Product Conditions', 'consucorner' ),
		'menu_name'                  => __( 'Product Condition', 'consucorner' ),
	);

	$args = array(
		'labels'                => $labels,
		'hierarchical'          => true,
		'public'                => true,
		'show_ui'               => true,
		'show_admin_column'     => true,
		'show_in_nav_menus'     => false,
		'show_tagcloud'         => false,
		'show_in_rest'          => true,
		'rest_base'             => 'product-conditions',
		'rest_controller_class' => 'WP_REST_Terms_Controller',
		'query_var'             => true,
		'rewrite'               => array(
			'slug'         => 'product-condition',
			'with_front'   => false,
			'hierarchical' => true,
		),
	);

	register_taxonomy( 'product_condition', array( 'product' ), $args );
}
add_action( 'init', 'consucorner_register_product_condition_taxonomy', 22 );

/**
 * Add Product Condition filter dropdown to Products admin list.
 *
 * @param string $post_type Current admin post type.
 */
function consucorner_filter_products_by_condition_dropdown( $post_type ) {
	if ( 'product' !== $post_type ) {
		return;
	}

	$taxonomy = 'product_condition';
	$selected = isset( $_GET[ $taxonomy ] ) ? sanitize_text_field( wp_unslash( $_GET[ $taxonomy ] ) ) : '';

	wp_dropdown_categories(
		array(
			'show_option_all' => __( 'All Product Conditions', 'consucorner' ),
			'taxonomy'        => $taxonomy,
			'name'            => $taxonomy,
			'orderby'         => 'name',
			'selected'        => $selected,
			'hierarchical'    => true,
			'depth'           => 2,
			'show_count'      => true,
			'hide_empty'      => false,
			'value_field'     => 'slug',
		)
	);
}
add_action( 'restrict_manage_posts', 'consucorner_filter_products_by_condition_dropdown' );

/**
 * Ensure Product Condition default terms exist.
 */
function consucorner_seed_product_condition_terms() {
	if ( get_option( 'consucorner_product_condition_terms_seeded' ) ) {
		return;
	}

	$terms = consucorner_get_product_condition_default_terms();
	foreach ( $terms as $term_name ) {
		if ( ! term_exists( $term_name, 'product_condition' ) ) {
			wp_insert_term( $term_name, 'product_condition' );
		}
	}

	update_option( 'consucorner_product_condition_terms_seeded', true );
}

/**
 * Flush rewrite rules once after introducing Product Condition taxonomy.
 */
function consucorner_maybe_flush_product_condition_rewrites() {
	if ( get_option( 'consucorner_product_condition_rewrites_flushed' ) ) {
		return;
	}

	consucorner_register_product_condition_taxonomy();
	flush_rewrite_rules();
	update_option( 'consucorner_product_condition_rewrites_flushed', true );
}

/**
 * Assign Product Condition terms to a few products for one-time testing.
 */
function consucorner_assign_product_condition_test_data() {
	if ( get_option( 'consucorner_product_condition_test_assignments_done' ) ) {
		return;
	}

	$term_names = consucorner_get_product_condition_default_terms();
	$term_slugs = array();

	foreach ( $term_names as $term_name ) {
		$term = get_term_by( 'name', $term_name, 'product_condition' );
		if ( $term instanceof WP_Term ) {
			$term_slugs[] = $term->slug;
		}
	}

	if ( empty( $term_slugs ) ) {
		return;
	}

	$product_ids = get_posts(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => count( $term_slugs ),
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	if ( empty( $product_ids ) ) {
		return;
	}

	foreach ( $product_ids as $index => $product_id ) {
		if ( ! isset( $term_slugs[ $index ] ) ) {
			break;
		}

		wp_set_object_terms( (int) $product_id, $term_slugs[ $index ], 'product_condition', false );
	}

	update_option( 'consucorner_product_condition_test_assignments_done', true );
}

/**
 * Run Product Condition one-time setup tasks.
 */
function consucorner_product_condition_one_time_setup() {
	consucorner_seed_product_condition_terms();
	consucorner_maybe_flush_product_condition_rewrites();
	consucorner_assign_product_condition_test_data();
}
add_action( 'admin_init', 'consucorner_product_condition_one_time_setup' );
