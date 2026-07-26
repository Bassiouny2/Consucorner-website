<?php
/**
 * Product Procedures taxonomy.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Procedures taxonomy for WooCommerce products only.
 */
function consucorner_register_product_procedures_taxonomy() {
	$labels = array(
		'name'                       => _x( 'Procedures', 'taxonomy general name', 'consucorner' ),
		'singular_name'              => _x( 'Procedure', 'taxonomy singular name', 'consucorner' ),
		'search_items'               => __( 'Search Procedures', 'consucorner' ),
		'popular_items'              => __( 'Popular Procedures', 'consucorner' ),
		'all_items'                  => __( 'All Procedures', 'consucorner' ),
		'parent_item'                => __( 'Parent Procedure', 'consucorner' ),
		'parent_item_colon'          => __( 'Parent Procedure:', 'consucorner' ),
		'edit_item'                  => __( 'Edit Procedure', 'consucorner' ),
		'view_item'                  => __( 'View Procedure', 'consucorner' ),
		'update_item'                => __( 'Update Procedure', 'consucorner' ),
		'add_new_item'               => __( 'Add New Procedure', 'consucorner' ),
		'new_item_name'              => __( 'New Procedure Name', 'consucorner' ),
		'separate_items_with_commas' => __( 'Separate procedures with commas', 'consucorner' ),
		'add_or_remove_items'        => __( 'Add or remove procedures', 'consucorner' ),
		'choose_from_most_used'      => __( 'Choose from the most used procedures', 'consucorner' ),
		'not_found'                  => __( 'No procedures found.', 'consucorner' ),
		'no_terms'                   => __( 'No procedures', 'consucorner' ),
		'items_list_navigation'      => __( 'Procedures list navigation', 'consucorner' ),
		'items_list'                 => __( 'Procedures list', 'consucorner' ),
		'back_to_items'              => __( 'Back to Procedures', 'consucorner' ),
		'menu_name'                  => __( 'Procedures', 'consucorner' ),
	);

	$args = array(
		'labels'             => $labels,
		'hierarchical'       => true,
		'public'             => true,
		'show_ui'            => true,
		'show_admin_column'  => true,
		'show_in_nav_menus'  => true,
		'show_tagcloud'      => false,
		'show_in_rest'       => true,
		'rest_base'          => 'procedures',
		'rest_controller_class' => 'WP_REST_Terms_Controller',
		'query_var'          => true,
		'rewrite'            => array(
			'slug'         => 'procedure',
			'with_front'   => false,
			'hierarchical' => true,
		),
	);

	register_taxonomy( 'procedure', array( 'product' ), $args );
}
add_action( 'init', 'consucorner_register_product_procedures_taxonomy', 20 );

/**
 * Add a Procedures filter dropdown to the WooCommerce Products admin list.
 *
 * The taxonomy column appears via show_admin_column; this dropdown makes the
 * taxonomy directly filterable from Products > All Products.
 *
 * @param string $post_type Current admin post type.
 */
function consucorner_filter_products_by_procedure_dropdown( $post_type ) {
	if ( 'product' !== $post_type ) {
		return;
	}

	$taxonomy = 'procedure';
	$selected = isset( $_GET[ $taxonomy ] ) ? sanitize_text_field( wp_unslash( $_GET[ $taxonomy ] ) ) : '';

	wp_dropdown_categories(
		array(
			'show_option_all' => __( 'All Procedures', 'consucorner' ),
			'taxonomy'        => $taxonomy,
			'name'            => $taxonomy,
			'orderby'         => 'name',
			'selected'        => $selected,
			'hierarchical'    => true,
			'depth'           => 3,
			'show_count'      => true,
			'hide_empty'      => false,
			'value_field'     => 'slug',
		)
	);
}
add_action( 'restrict_manage_posts', 'consucorner_filter_products_by_procedure_dropdown' );

/**
 * Flush rewrite rules once after the taxonomy is introduced.
 */
function consucorner_maybe_flush_product_procedures_rewrites() {
	if ( get_option( 'consucorner_product_procedures_rewrites_flushed' ) ) {
		return;
	}

	consucorner_register_product_procedures_taxonomy();
	flush_rewrite_rules();
	update_option( 'consucorner_product_procedures_rewrites_flushed', true );
}
add_action( 'admin_init', 'consucorner_maybe_flush_product_procedures_rewrites' );
