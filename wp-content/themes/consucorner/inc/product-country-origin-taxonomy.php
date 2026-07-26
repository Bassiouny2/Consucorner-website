<?php
/**
 * Country of Origin taxonomy.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the canonical Country of Origin taxonomy slug.
 *
 * @return string
 */
function consucorner_country_origin_taxonomy() {
	return 'country_of_origin';
}

/**
 * Return legacy WooCommerce attribute taxonomies used for country/origin.
 *
 * @return string[]
 */
function consucorner_country_origin_legacy_taxonomies() {
	return array(
		'pa_country-of-origin',
		'pa_country_of_origin',
		'pa_country',
		'pa_origin',
		'pa_made-in',
		'pa_made_in',
	);
}

/**
 * Register the Country of Origin taxonomy for WooCommerce products.
 *
 * @return void
 */
function consucorner_register_country_origin_taxonomy() {
	$labels = array(
		'name'                       => _x( 'Countries of Origin', 'taxonomy general name', 'consucorner' ),
		'singular_name'              => _x( 'Country of Origin', 'taxonomy singular name', 'consucorner' ),
		'search_items'               => __( 'Search Countries of Origin', 'consucorner' ),
		'popular_items'              => __( 'Popular Countries of Origin', 'consucorner' ),
		'all_items'                  => __( 'All Countries of Origin', 'consucorner' ),
		'parent_item'                => __( 'Parent Country of Origin', 'consucorner' ),
		'parent_item_colon'          => __( 'Parent Country of Origin:', 'consucorner' ),
		'edit_item'                  => __( 'Edit Country of Origin', 'consucorner' ),
		'view_item'                  => __( 'View Country of Origin', 'consucorner' ),
		'update_item'                => __( 'Update Country of Origin', 'consucorner' ),
		'add_new_item'               => __( 'Add New Country of Origin', 'consucorner' ),
		'new_item_name'              => __( 'New Country of Origin Name', 'consucorner' ),
		'separate_items_with_commas' => __( 'Separate countries with commas', 'consucorner' ),
		'add_or_remove_items'        => __( 'Add or remove countries', 'consucorner' ),
		'choose_from_most_used'      => __( 'Choose from the most used countries', 'consucorner' ),
		'not_found'                  => __( 'No countries of origin found.', 'consucorner' ),
		'no_terms'                   => __( 'No countries of origin', 'consucorner' ),
		'items_list_navigation'      => __( 'Countries of origin list navigation', 'consucorner' ),
		'items_list'                 => __( 'Countries of origin list', 'consucorner' ),
		'back_to_items'              => __( 'Back to Countries of Origin', 'consucorner' ),
		'menu_name'                  => __( 'Country of Origin', 'consucorner' ),
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
		'rest_base'             => 'countries-of-origin',
		'rest_controller_class' => 'WP_REST_Terms_Controller',
		'query_var'             => true,
		'rewrite'               => array(
			'slug'         => 'country-of-origin',
			'with_front'   => false,
			'hierarchical' => true,
		),
	);

	register_taxonomy( consucorner_country_origin_taxonomy(), array( 'product' ), $args );
}
add_action( 'init', 'consucorner_register_country_origin_taxonomy', 23 );

/**
 * Add Country of Origin filter dropdown to Products admin list.
 *
 * @param string $post_type Current admin post type.
 * @return void
 */
function consucorner_filter_products_by_country_origin_dropdown( $post_type ) {
	if ( 'product' !== $post_type ) {
		return;
	}

	$taxonomy = consucorner_country_origin_taxonomy();
	$selected = isset( $_GET[ $taxonomy ] ) ? sanitize_text_field( wp_unslash( $_GET[ $taxonomy ] ) ) : '';

	wp_dropdown_categories(
		array(
			'show_option_all' => __( 'All Countries of Origin', 'consucorner' ),
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
add_action( 'restrict_manage_posts', 'consucorner_filter_products_by_country_origin_dropdown' );

/**
 * Return a URL for a Country of Origin term image.
 *
 * @param WP_Term $term Term object.
 * @param string  $size Image size.
 * @return string
 */
function cc_get_country_origin_term_image_url( $term, $size = 'thumbnail' ) {
	if ( ! $term instanceof WP_Term ) {
		return '';
	}

	if ( function_exists( 'cc_get_filter_term_image_url' ) ) {
		$image_url = cc_get_filter_term_image_url( $term );
		if ( $image_url ) {
			return $image_url;
		}
	}

	$image_id = (int) get_term_meta( $term->term_id, '_cc_attribute_image', true );
	return $image_id ? (string) wp_get_attachment_image_url( $image_id, $size ) : '';
}

/**
 * Return Country of Origin info for a product.
 *
 * Resolution order: new taxonomy, legacy attributes, product fallback meta.
 *
 * @param int $product_id Product ID.
 * @return array{name:string,image_url:string,term_id:int,taxonomy:string,shop_url:string}
 */
function cc_get_product_country_origin_info( $product_id ) {
	$product_id = absint( $product_id );
	$default = array(
		'name'      => '',
		'image_url' => '',
		'term_id'   => 0,
		'taxonomy'  => '',
		'shop_url'  => '',
	);

	if ( ! $product_id ) {
		return $default;
	}

	$taxonomies = array_merge(
		array( consucorner_country_origin_taxonomy() ),
		consucorner_country_origin_legacy_taxonomies()
	);

	foreach ( $taxonomies as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		$terms = get_the_terms( $product_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}

		$first_term = reset( $terms );
		$names = wp_list_pluck( $terms, 'name' );

		$default['name']      = implode( ', ', array_filter( array_map( 'trim', $names ) ) );
		$default['image_url'] = cc_get_country_origin_term_image_url( $first_term );
		$default['term_id']   = (int) $first_term->term_id;
		$default['taxonomy']  = $taxonomy;
		if ( function_exists( 'cc_build_shop_filter_url' ) ) {
			$default['shop_url'] = cc_build_shop_filter_url( $taxonomy, $first_term->slug );
		}
		break;
	}

	$country_meta_img  = get_post_meta( $product_id, '_cc_country_origin_image', true );
	$country_meta_name = get_post_meta( $product_id, '_cc_country_name', true );

	if ( ! $default['name'] && $country_meta_name ) {
		$default['name'] = sanitize_text_field( $country_meta_name );
	}

	if ( $country_meta_img ) {
		$default['image_url'] = esc_url_raw( $country_meta_img );
	}

	if ( $default['name'] && ! $default['image_url'] ) {
		$default['image_url'] = get_template_directory_uri() . '/assets/images/country.webp';
	}

	return $default;
}

/**
 * Flush rewrite rules once after introducing Country of Origin taxonomy.
 *
 * @return void
 */
function consucorner_maybe_flush_country_origin_rewrites() {
	if ( get_option( 'consucorner_country_origin_rewrites_flushed' ) ) {
		return;
	}

	consucorner_register_country_origin_taxonomy();
	flush_rewrite_rules();
	update_option( 'consucorner_country_origin_rewrites_flushed', true );
}

/**
 * Return an existing/new term ID in the new taxonomy for a legacy term.
 *
 * @param WP_Term $legacy_term Legacy term.
 * @return int
 */
function consucorner_country_origin_get_or_create_term( WP_Term $legacy_term ) {
	$taxonomy = consucorner_country_origin_taxonomy();

	$existing = get_term_by( 'slug', $legacy_term->slug, $taxonomy );
	if ( ! $existing ) {
		$existing = get_term_by( 'name', $legacy_term->name, $taxonomy );
	}

	if ( $existing instanceof WP_Term ) {
		$new_term_id = (int) $existing->term_id;
	} else {
		$inserted = wp_insert_term(
			$legacy_term->name,
			$taxonomy,
			array(
				'slug'        => $legacy_term->slug,
				'description' => $legacy_term->description,
			)
		);

		if ( is_wp_error( $inserted ) || empty( $inserted['term_id'] ) ) {
			return 0;
		}

		$new_term_id = (int) $inserted['term_id'];
	}

	$image_id = (int) get_term_meta( $legacy_term->term_id, '_cc_attribute_image', true );
	if ( $image_id && ! get_term_meta( $new_term_id, '_cc_attribute_image', true ) ) {
		update_term_meta( $new_term_id, '_cc_attribute_image', $image_id );
	}

	return $new_term_id;
}

/**
 * Migrate legacy country attribute terms into the new taxonomy.
 *
 * @return void
 */
function consucorner_migrate_country_origin_attribute_data() {
	if ( get_option( 'consucorner_country_origin_migration_complete' ) ) {
		return;
	}

	if ( ! is_admin() || wp_doing_ajax() || ! taxonomy_exists( consucorner_country_origin_taxonomy() ) ) {
		return;
	}

	$term_map = array();

	foreach ( consucorner_country_origin_legacy_taxonomies() as $legacy_taxonomy ) {
		if ( ! taxonomy_exists( $legacy_taxonomy ) ) {
			continue;
		}

		$legacy_terms = get_terms(
			array(
				'taxonomy'   => $legacy_taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $legacy_terms ) || empty( $legacy_terms ) ) {
			continue;
		}

		foreach ( $legacy_terms as $legacy_term ) {
			if ( ! $legacy_term instanceof WP_Term ) {
				continue;
			}

			$new_term_id = consucorner_country_origin_get_or_create_term( $legacy_term );
			if ( ! $new_term_id ) {
				continue;
			}

			$term_map[ (int) $legacy_term->term_id ] = $new_term_id;

			$product_ids = get_objects_in_term( (int) $legacy_term->term_id, $legacy_taxonomy );
			if ( is_wp_error( $product_ids ) || empty( $product_ids ) ) {
				continue;
			}

			foreach ( $product_ids as $product_id ) {
				$product_id = absint( $product_id );
				if ( ! $product_id ) {
					continue;
				}

				$current_terms = wp_get_object_terms(
					$product_id,
					consucorner_country_origin_taxonomy(),
					array( 'fields' => 'ids' )
				);
				$current_terms = is_wp_error( $current_terms ) ? array() : array_map( 'absint', $current_terms );
				$current_terms[] = $new_term_id;
				wp_set_object_terms(
					$product_id,
					array_values( array_unique( array_filter( $current_terms ) ) ),
					consucorner_country_origin_taxonomy(),
					false
				);
			}
		}
	}

	if ( $term_map ) {
		$page_ids = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_cc_shop_specialty_country_ids',
			)
		);

		foreach ( $page_ids as $page_id ) {
			$old_ids = get_post_meta( $page_id, '_cc_shop_specialty_country_ids', true );
			if ( ! is_array( $old_ids ) ) {
				continue;
			}

			$new_ids = array();
			foreach ( array_map( 'absint', $old_ids ) as $old_id ) {
				$new_ids[] = isset( $term_map[ $old_id ] ) ? $term_map[ $old_id ] : $old_id;
			}

			update_post_meta( $page_id, '_cc_shop_specialty_country_ids', array_values( array_unique( array_filter( $new_ids ) ) ) );
		}
	}

	update_option( 'consucorner_country_origin_migration_complete', true );
	update_option( 'consucorner_country_origin_migration_map', $term_map, false );
}

/**
 * Run one-time Country of Origin setup tasks.
 *
 * @return void
 */
function consucorner_country_origin_one_time_setup() {
	consucorner_maybe_flush_country_origin_rewrites();
	consucorner_migrate_country_origin_attribute_data();
}
add_action( 'admin_init', 'consucorner_country_origin_one_time_setup' );
