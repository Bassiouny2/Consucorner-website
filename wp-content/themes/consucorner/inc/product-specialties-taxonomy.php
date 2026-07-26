<?php

/**
 * Product Specialties taxonomy.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/**
 * Register the Specialties taxonomy for WooCommerce products only.
 */
function consucorner_register_product_specialties_taxonomy()
{
	$labels = array(
		'name'                       => _x('Specialties', 'taxonomy general name', 'consucorner'),
		'singular_name'              => _x('Specialty', 'taxonomy singular name', 'consucorner'),
		'search_items'               => __('Search Specialties', 'consucorner'),
		'popular_items'              => __('Popular Specialties', 'consucorner'),
		'all_items'                  => __('All Specialties', 'consucorner'),
		'parent_item'                => __('Parent Specialty', 'consucorner'),
		'parent_item_colon'          => __('Parent Specialty:', 'consucorner'),
		'edit_item'                  => __('Edit Specialty', 'consucorner'),
		'view_item'                  => __('View Specialty', 'consucorner'),
		'update_item'                => __('Update Specialty', 'consucorner'),
		'add_new_item'               => __('Add New Specialty', 'consucorner'),
		'new_item_name'              => __('New Specialty Name', 'consucorner'),
		'separate_items_with_commas' => __('Separate specialties with commas', 'consucorner'),
		'add_or_remove_items'        => __('Add or remove specialties', 'consucorner'),
		'choose_from_most_used'      => __('Choose from the most used specialties', 'consucorner'),
		'not_found'                  => __('No specialties found.', 'consucorner'),
		'no_terms'                   => __('No specialties', 'consucorner'),
		'items_list_navigation'      => __('Specialties list navigation', 'consucorner'),
		'items_list'                 => __('Specialties list', 'consucorner'),
		'back_to_items'              => __('Back to Specialties', 'consucorner'),
		'menu_name'                  => __('Specialty', 'consucorner'),
	);

	$args = array(
		'labels'                => $labels,
		'hierarchical'          => true,
		'public'                => true,
		'show_ui'               => true,
		'show_admin_column'     => true,
		'show_in_nav_menus'     => true,
		'show_tagcloud'         => false,
		'show_in_rest'          => true,
		'rest_base'             => 'specialties',
		'rest_controller_class' => 'WP_REST_Terms_Controller',
		'query_var'             => true,
		'rewrite'               => array(
			'slug'         => 'specialty',
			'with_front'   => false,
			'hierarchical' => true,
		),
	);

	register_taxonomy('specialty', array('product'), $args);
}
add_action('init', 'consucorner_register_product_specialties_taxonomy', 21);

/**
 * Add a Specialties filter dropdown to the WooCommerce Products admin list.
 *
 * @param string $post_type Current admin post type.
 */
function consucorner_filter_products_by_specialty_dropdown($post_type)
{
	if ('product' !== $post_type) {
		return;
	}

	$taxonomy = 'specialty';
	$selected = isset($_GET[$taxonomy]) ? sanitize_text_field(wp_unslash($_GET[$taxonomy])) : '';

	wp_dropdown_categories(
		array(
			'show_option_all' => __('All Specialties', 'consucorner'),
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
add_action('restrict_manage_posts', 'consucorner_filter_products_by_specialty_dropdown');

/**
 * Term meta key storing the manual display order for a specialty.
 */
if (! defined('CONSUCORNER_SPECIALTY_ORDER_META')) {
	define('CONSUCORNER_SPECIALTY_ORDER_META', 'order');
}

/**
 * Sort an array of WP_Term objects by their manual "order" term meta (ascending),
 * falling back to the term name for ties. Terms without an order value are treated
 * as 0, so they keep their alphabetical position until the operations team sets one.
 *
 * Used so the shop mega-menu, shop archive filter, mobile drawer, and homepage
 * category/specialty sliders share a single, editable ordering. Works for any
 * taxonomy that stores WooCommerce's standard `order` term meta (specialty,
 * product_cat).
 *
 * @param WP_Term[] $terms Terms to sort.
 * @return WP_Term[]
 */
function consucorner_sort_terms_by_order(array $terms)
{
	$indexed = array();

	foreach ($terms as $position => $term) {
		if (! $term instanceof WP_Term) {
			continue;
		}

		$indexed[] = array(
			'term'     => $term,
			'order'    => (int) get_term_meta($term->term_id, CONSUCORNER_SPECIALTY_ORDER_META, true),
			'name'     => $term->name,
			'position' => (int) $position,
		);
	}

	usort(
		$indexed,
		function ($a, $b) {
			if ($a['order'] !== $b['order']) {
				return $a['order'] <=> $b['order'];
			}

			$by_name = strcasecmp($a['name'], $b['name']);
			if (0 !== $by_name) {
				return $by_name;
			}

			return $a['position'] <=> $b['position'];
		}
	);

	return array_map(
		function ($row) {
			return $row['term'];
		},
		$indexed
	);
}

/**
 * Register the specialty taxonomy as a WooCommerce "sortable" taxonomy.
 *
 * This reuses WooCommerce's proven product-category drag-and-drop machinery for
 * the specialty taxonomy without re-implementing it:
 *
 *  - Admin: WooCommerce enqueues its term-ordering script (drag handles + jQuery
 *    UI sortable) on the Specialties list table and orders rows by the 'order'
 *    term meta via a LEFT JOIN, so terms without an order value are never hidden.
 *  - Persistence: the drag interaction posts to the nonce-protected
 *    `woocommerce_term_ordering` AJAX handler, which writes the 'order' meta.
 *
 * Because that 'order' meta is exactly CONSUCORNER_SPECIALTY_ORDER_META, the shop
 * mega-menu and shop archive specialty filter (both run through
 * consucorner_sort_terms_by_order()) pick up the new order automatically, and the
 * mega-menu transient is flushed by the updated_term_meta hook. No specialties are
 * created or deleted by sorting — only their 'order' meta value changes.
 *
 * @param string[] $taxonomies Taxonomies WooCommerce treats as sortable.
 * @return string[]
 */
function consucorner_register_specialty_sortable_taxonomy($taxonomies)
{
	if (! in_array('specialty', (array) $taxonomies, true)) {
		$taxonomies[] = 'specialty';
	}

	return $taxonomies;
}
add_filter('woocommerce_sortable_taxonomies', 'consucorner_register_specialty_sortable_taxonomy');

/**
 * Ensure drag-and-drop term ordering is available on Products > Specialties.
 *
 * WooCommerce normally loads this for sortable taxonomies, but some admin URLs
 * or load order combinations can skip the enqueue. Loading it explicitly on the
 * Specialty list table keeps the UI dependable and still uses WooCommerce's
 * nonce-protected AJAX handler, which only updates term 'order' meta.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function consucorner_enqueue_specialty_term_ordering($hook_suffix)
{
	if ('edit-tags.php' !== $hook_suffix) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin screen check.
	if (empty($_GET['taxonomy']) || 'specialty' !== sanitize_key(wp_unslash($_GET['taxonomy']))) {
		return;
	}

	if (! function_exists('WC')) {
		return;
	}

	$suffix  = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
	$version = defined('WC_VERSION') ? WC_VERSION : null;

	wp_enqueue_script(
		'woocommerce_term_ordering',
		WC()->plugin_url() . '/assets/js/admin/term-ordering' . $suffix . '.js',
		array('jquery-ui-sortable'),
		$version,
		true
	);

	wp_localize_script(
		'woocommerce_term_ordering',
		'woocommerce_term_ordering_params',
		array(
			'taxonomy' => 'specialty',
			'nonce'    => wp_create_nonce('term-ordering'),
		)
	);

	wp_add_inline_style(
		'common',
		'.taxonomy-specialty .wp-list-table .column-handle{display:table-cell!important;width:34px;cursor:move;vertical-align:middle}.taxonomy-specialty .wp-list-table .column-handle:before{content:"\f333";font-family:dashicons;color:#787c82;font-size:18px;line-height:1}.taxonomy-specialty .wp-list-table tr.ui-sortable-helper{display:table;background:#fff}'
	);
}
add_action('admin_enqueue_scripts', 'consucorner_enqueue_specialty_term_ordering', 20);

/**
 * Flush rewrite rules once after the taxonomy is introduced.
 */
function consucorner_maybe_flush_product_specialties_rewrites()
{
	if (get_option('consucorner_product_specialties_rewrites_flushed')) {
		return;
	}

	consucorner_register_product_specialties_taxonomy();
	flush_rewrite_rules();
	update_option('consucorner_product_specialties_rewrites_flushed', true);
}
add_action('admin_init', 'consucorner_maybe_flush_product_specialties_rewrites');
