<?php
/**
 * Shop / specialty filter campaign link builder (WordPress admin).
 *
 * @package Consucorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register Link Builder under Products.
 *
 * @return void
 */
function cc_offers_link_builder_register_menu() {
	add_submenu_page(
		'edit.php?post_type=product',
		__( 'Link Builder', 'consucorner' ),
		__( 'Link Builder', 'consucorner' ),
		'edit_products',
		'cc-offers-link-builder',
		'cc_offers_link_builder_render_page'
	);
}
add_action( 'admin_menu', 'cc_offers_link_builder_register_menu', 25 );

/**
 * Place Link Builder directly after Campaigns in the Products submenu.
 *
 * @return void
 */
function cc_offers_link_builder_reorder_menu() {
	global $submenu;

	$parent = 'edit.php?post_type=product';
	if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
		return;
	}

	$items          = $submenu[ $parent ];
	$builder_index  = null;
	$builder_item   = null;
	$campaign_index = null;

	foreach ( $items as $index => $item ) {
		if ( ! isset( $item[2] ) ) {
			continue;
		}
		if ( 'cc-offers-link-builder' === $item[2] ) {
			$builder_index = $index;
			$builder_item  = $item;
		}
		if ( 'edit.php?post_type=cc_bundle' === $item[2] ) {
			$campaign_index = $index;
		}
	}

	if ( null === $builder_index || null === $builder_item || null === $campaign_index ) {
		return;
	}

	unset( $items[ $builder_index ] );
	$items = array_values( $items );

	$campaign_index = null;
	foreach ( $items as $index => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=cc_bundle' === $item[2] ) {
			$campaign_index = $index;
			break;
		}
	}

	if ( null === $campaign_index ) {
		$submenu[ $parent ] = array_merge( $items, array( $builder_item ) );
		return;
	}

	array_splice( $items, $campaign_index + 1, 0, array( $builder_item ) );
	$submenu[ $parent ] = $items;
}
add_action( 'admin_menu', 'cc_offers_link_builder_reorder_menu', 999 );

/**
 * Add a quick link on the Offers page row in Pages list.
 *
 * @param string[] $actions Row actions.
 * @param WP_Post  $post    Post object.
 * @return string[]
 */
function cc_offers_link_builder_row_action( $actions, $post ) {
	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return $actions;
	}

	if ( 'page-offers.php' !== get_page_template_slug( $post->ID ) ) {
		return $actions;
	}

	if ( ! current_user_can( 'edit_products' ) ) {
		return $actions;
	}

	$url = admin_url( 'admin.php?page=cc-offers-link-builder' );

	$actions['cc_offers_link_builder'] = sprintf(
		'<a href="%s">%s</a>',
		esc_url( $url ),
		esc_html__( 'Link Builder', 'consucorner' )
	);

	return $actions;
}
add_filter( 'page_row_actions', 'cc_offers_link_builder_row_action', 12, 2 );

/**
 * Taxonomies available in the link builder (specialty is base path only).
 *
 * @return string[]
 */
function cc_offers_link_builder_shop_taxonomies() {
	if ( ! function_exists( 'cc_get_filterable_taxonomies' ) ) {
		return array( 'product_cat', 'product_brand' );
	}

	return array_values(
		array_filter(
			cc_get_filterable_taxonomies(),
			static function ( $tax ) {
				return 'specialty' !== $tax;
			}
		)
	);
}

/**
 * Human label for a filter taxonomy in the admin UI.
 *
 * @param string $taxonomy Taxonomy slug.
 * @return string
 */
function cc_offers_link_builder_taxonomy_label( $taxonomy ) {
	$taxonomy = sanitize_key( $taxonomy );

	switch ( $taxonomy ) {
		case 'product_cat':
			return __( 'Category', 'consucorner' );
		case 'product_brand':
			return __( 'Brand', 'consucorner' );
		case 'country_of_origin':
			return __( 'Country of origin', 'consucorner' );
		case 'procedure':
			return __( 'Procedure', 'consucorner' );
		default:
			if ( 0 === strpos( $taxonomy, 'pa_' ) ) {
				$attribute = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $taxonomy ) : $taxonomy;
				return is_string( $attribute ) ? $attribute : $taxonomy;
			}

			$object = get_taxonomy( $taxonomy );
			return ( $object && ! empty( $object->labels->singular_name ) )
				? (string) $object->labels->singular_name
				: $taxonomy;
	}
}

/**
 * Resolve the base URL for a link builder selection.
 *
 * @param string $specialty_slug Specialty term slug, or empty for main shop.
 * @return string
 */
function cc_offers_link_builder_resolve_base_url( $specialty_slug = '' ) {
	$specialty_slug = sanitize_title( (string) $specialty_slug );

	if ( '' === $specialty_slug ) {
		return function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
	}

	if ( ! taxonomy_exists( 'specialty' ) ) {
		return '';
	}

	$term = get_term_by( 'slug', $specialty_slug, 'specialty' );
	if ( ! $term instanceof WP_Term ) {
		return '';
	}

	$link = get_term_link( $term );
	return is_wp_error( $link ) ? '' : (string) $link;
}

/**
 * Build archive context for product counts from a specialty base slug.
 *
 * @param string $specialty_slug Specialty term slug.
 * @return array{taxonomy?:string,term_id?:int}
 */
function cc_offers_link_builder_archive_context( $specialty_slug = '' ) {
	$specialty_slug = sanitize_title( (string) $specialty_slug );
	if ( '' === $specialty_slug || ! taxonomy_exists( 'specialty' ) ) {
		return array();
	}

	$term = get_term_by( 'slug', $specialty_slug, 'specialty' );
	if ( ! $term instanceof WP_Term ) {
		return array();
	}

	return array(
		'taxonomy' => 'specialty',
		'term_id'  => (int) $term->term_id,
	);
}

/**
 * Enqueue admin assets for the Link Builder screen.
 *
 * @param string $hook Current admin hook.
 * @return void
 */
function cc_offers_link_builder_admin_assets( $hook ) {
	if ( 'product_page_cc-offers-link-builder' !== $hook ) {
		return;
	}

	wp_enqueue_script(
		'cc-offers-link-builder',
		get_template_directory_uri() . '/assets/js/offers-link-builder.js',
		array(),
		defined( '_S_VERSION' ) ? _S_VERSION : '1.0.0',
		true
	);

	$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );

	wp_localize_script(
		'cc-offers-link-builder',
		'ccOffersLinkBuilder',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'cc_offers_link_builder' ),
			'shopUrl'  => $shop_url,
			'specialtyBases' => cc_offers_link_builder_specialty_base_map(),
			'i18n'     => array(
				'copied'       => __( 'Copied!', 'consucorner' ),
				'copyLink'     => __( 'Copy link', 'consucorner' ),
				'previewCount' => __( '%d products match this link.', 'consucorner' ),
				'previewEmpty' => __( 'Select a base page or filters to preview a product count.', 'consucorner' ),
				'previewError' => __( 'Could not load the product count.', 'consucorner' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'cc_offers_link_builder_admin_assets' );

/**
 * Specialty slug => archive URL map for the admin JS base picker.
 *
 * @return array<string,string>
 */
function cc_offers_link_builder_specialty_base_map() {
	if ( ! taxonomy_exists( 'specialty' ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'specialty',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
			'number'     => 500,
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	$map = array();
	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$map[ $term->slug ] = (string) $link;
	}

	return $map;
}

/**
 * AJAX: product count for a filter campaign URL.
 *
 * @return void
 */
function cc_offers_link_builder_ajax_shop_count() {
	check_ajax_referer( 'cc_offers_link_builder', 'nonce' );

	if ( ! current_user_can( 'edit_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Forbidden.', 'consucorner' ) ), 403 );
	}

	$filters = cc_offers_link_builder_parse_shop_filters_from_request();
	$count   = function_exists( 'cc_count_shop_campaign_filter_products' )
		? cc_count_shop_campaign_filter_products( $filters['slugs'], $filters['price'], $filters['archive'] )
		: 0;

	wp_send_json_success( array( 'count' => (int) $count ) );
}
add_action( 'wp_ajax_cc_offers_link_builder_shop_count', 'cc_offers_link_builder_ajax_shop_count' );

/**
 * Parse posted shop filter fields into slug, price, and archive context.
 *
 * @return array{slugs:array<string,string[]>,price:array{min:float,max:float,sort:string},archive:array{taxonomy?:string,term_id?:int}}
 */
function cc_offers_link_builder_parse_shop_filters_from_request() {
	$slugs = array();

	foreach ( cc_offers_link_builder_shop_taxonomies() as $taxonomy ) {
		$key = 'tax_' . $taxonomy;
		if ( empty( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			continue;
		}

		$raw       = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw       = is_array( $raw ) ? $raw : array( $raw );
		$tax_slugs = array();

		foreach ( $raw as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( '' !== $slug ) {
				$tax_slugs[] = $slug;
			}
		}

		if ( $tax_slugs ) {
			$slugs[ $taxonomy ] = array_values( array_unique( $tax_slugs ) );
		}
	}

	$min_price = isset( $_POST['min_price'] ) ? (float) wp_unslash( $_POST['min_price'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$max_price = isset( $_POST['max_price'] ) ? (float) wp_unslash( $_POST['max_price'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$sort      = isset( $_POST['sort'] ) ? sanitize_key( wp_unslash( $_POST['sort'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$base      = isset( $_POST['base_specialty'] ) ? sanitize_title( wp_unslash( $_POST['base_specialty'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( $max_price > 0 && function_exists( 'cc_clamp_price_filter_max' ) ) {
		$max_price = cc_clamp_price_filter_max( $max_price );
	}

	return array(
		'slugs'    => $slugs,
		'price'    => array(
			'min'  => max( 0, $min_price ),
			'max'  => max( 0, $max_price ),
			'sort' => $sort,
		),
		'archive'  => cc_offers_link_builder_archive_context( $base ),
	);
}

/**
 * Render the Link Builder admin page.
 *
 * @return void
 */
function cc_offers_link_builder_render_page() {
	if ( ! current_user_can( 'edit_products' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'consucorner' ) );
	}

	$shop_url          = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
	$specialty_terms   = taxonomy_exists( 'specialty' ) ? get_terms(
		array(
			'taxonomy'   => 'specialty',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
			'number'     => 500,
		)
	) : array();
	$specialty_terms   = is_wp_error( $specialty_terms ) ? array() : $specialty_terms;
	?>
	<div class="wrap cc-offers-link-builder">
		<h1><?php esc_html_e( 'Link Builder', 'consucorner' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Build shareable shop or specialty archive URLs with the same filters used on the storefront. Specialty scope uses the archive path only — never ?specialty= in the query string.', 'consucorner' ); ?>
		</p>

		<section class="postbox" style="padding:16px 20px;max-width:760px;margin-top:16px">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="cc-link-builder-base"><?php esc_html_e( 'Base page', 'consucorner' ); ?></label></th>
					<td>
						<select id="cc-link-builder-base" class="regular-text">
							<option value="" data-base-url="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Main shop', 'consucorner' ); ?></option>
							<?php foreach ( $specialty_terms as $term ) : ?>
								<?php
								$term_link = get_term_link( $term );
								if ( is_wp_error( $term_link ) ) {
									continue;
								}
								?>
								<option value="<?php echo esc_attr( $term->slug ); ?>" data-base-url="<?php echo esc_url( $term_link ); ?>">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: specialty name */
											__( 'Specialty: %s', 'consucorner' ),
											$term->name
										)
									);
									?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Pick main shop or a specialty archive as the link starting point.', 'consucorner' ); ?></p>
					</td>
				</tr>
				<?php foreach ( cc_offers_link_builder_shop_taxonomies() as $taxonomy ) : ?>
					<?php
					$terms = get_terms(
						array(
							'taxonomy'   => $taxonomy,
							'hide_empty' => false,
							'orderby'    => 'name',
							'order'      => 'ASC',
							'number'     => 500,
						)
					);
					if ( is_wp_error( $terms ) || empty( $terms ) ) {
						continue;
					}
					$field_id = 'cc-shop-builder-' . sanitize_html_class( $taxonomy );
					?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( cc_offers_link_builder_taxonomy_label( $taxonomy ) ); ?></label></th>
						<td>
							<select id="<?php echo esc_attr( $field_id ); ?>" class="regular-text" multiple size="6" data-cc-shop-tax="<?php echo esc_attr( $taxonomy ); ?>">
								<?php foreach ( $terms as $term ) : ?>
									<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Hold Ctrl / Cmd to select multiple terms.', 'consucorner' ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Price range', 'consucorner' ); ?></th>
					<td>
						<label for="cc-shop-builder-min-price" class="screen-reader-text"><?php esc_html_e( 'Minimum price', 'consucorner' ); ?></label>
						<input type="number" id="cc-shop-builder-min-price" class="small-text" min="0" step="1" placeholder="<?php esc_attr_e( 'Min', 'consucorner' ); ?>" />
						<span aria-hidden="true">—</span>
						<label for="cc-shop-builder-max-price" class="screen-reader-text"><?php esc_html_e( 'Maximum price', 'consucorner' ); ?></label>
						<input type="number" id="cc-shop-builder-max-price" class="small-text" min="0" step="1" placeholder="<?php esc_attr_e( 'Max', 'consucorner' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cc-shop-builder-url"><?php esc_html_e( 'Campaign URL', 'consucorner' ); ?></label></th>
					<td>
						<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
							<input type="text" id="cc-shop-builder-url" class="large-text code" readonly value="<?php echo esc_url( $shop_url ); ?>" data-cc-shop-base="<?php echo esc_url( $shop_url ); ?>" />
							<button type="button" class="button button-primary" id="cc-shop-copy-link"><?php esc_html_e( 'Copy link', 'consucorner' ); ?></button>
						</div>
						<p id="cc-shop-builder-preview" class="description" style="margin:8px 0 0" data-cc-shop-preview></p>
					</td>
				</tr>
			</table>
		</section>
	</div>
	<?php
}
