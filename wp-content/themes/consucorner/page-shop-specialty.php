<?php
/**
 * Shop Specialty Page Template.
 *
 * Template Name: Shop Specialty Page
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'consucorner_shop_specialty_meta' ) ) {
	/**
	 * Return editable page meta with a fallback.
	 *
	 * @param int    $page_id Page ID.
	 * @param string $key     Meta key.
	 * @param string $default Default value.
	 * @return string
	 */
	function consucorner_shop_specialty_meta( $page_id, $key, $default = '' ) {
		$value = get_post_meta( $page_id, $key, true );
		return '' !== $value && false !== $value ? $value : $default;
	}
}

if ( ! function_exists( 'consucorner_shop_specialty_terms' ) ) {
	/**
	 * Return terms, optionally limited by saved IDs and product IDs.
	 *
	 * @param string $taxonomy   Taxonomy name.
	 * @param array  $saved_ids  Saved term IDs.
	 * @param array  $object_ids Product IDs.
	 * @return WP_Term[]
	 */
	function consucorner_shop_specialty_terms( $taxonomy, array $saved_ids = array(), array $object_ids = array() ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		if ( $saved_ids ) {
			$args['include'] = array_map( 'absint', $saved_ids );
			$args['orderby'] = 'include';
		} elseif ( $object_ids ) {
			$args['object_ids'] = array_map( 'absint', $object_ids );
		}

		$terms = get_terms( $args );
		return is_wp_error( $terms ) ? array() : $terms;
	}
}

if ( ! function_exists( 'consucorner_shop_specialty_current_term' ) ) {
	/**
	 * Find the selected term from a query-string slug.
	 *
	 * @param string $taxonomy  Taxonomy name.
	 * @param string $query_key Query key.
	 * @return WP_Term|null
	 */
	function consucorner_shop_specialty_current_term( $taxonomy, $query_key ) {
		if ( empty( $_GET[ $query_key ] ) || ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}

		$slug = sanitize_title( wp_unslash( $_GET[ $query_key ] ) );
		$term = get_term_by( 'slug', $slug, $taxonomy );

		return $term instanceof WP_Term ? $term : null;
	}
}

if ( ! function_exists( 'consucorner_shop_specialty_filter_url' ) ) {
	/**
	 * Build a filter URL while preserving supported filters.
	 *
	 * @param string $key   Query key.
	 * @param string $value Query value.
	 * @return string
	 */
	function consucorner_shop_specialty_filter_url( $key, $value ) {
		$url  = get_permalink();
		$args = array();

		foreach ( array( 'instrument', 'specialty' ) as $allowed_key ) {
			if ( isset( $_GET[ $allowed_key ] ) && '' !== $_GET[ $allowed_key ] ) {
				$args[ $allowed_key ] = sanitize_text_field( wp_unslash( $_GET[ $allowed_key ] ) );
			}
		}

		if ( '' === $value ) {
			unset( $args[ $key ] );
		} else {
			$args[ $key ] = $value;
		}

		return add_query_arg( $args, $url );
	}
}

if ( ! function_exists( 'consucorner_shop_specialty_vendor_name' ) ) {
	/**
	 * Return a compact brand/vendor label for a product.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	function consucorner_shop_specialty_vendor_name( $product ) {
		$product_id  = $product instanceof WC_Product ? $product->get_id() : 0;
		$brand_terms = $product_id ? get_the_terms( $product_id, 'product_brand' ) : array();

		if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms ) ) {
			return $brand_terms[0]->name;
		}

		if ( $product_id && function_exists( 'dokan_get_store_info' ) ) {
			$vendor_id  = (int) get_post_field( 'post_author', $product_id );
			$store_info = $vendor_id ? dokan_get_store_info( $vendor_id ) : array();

			if ( ! empty( $store_info['store_name'] ) ) {
				return $store_info['store_name'];
			}
		}

		return __( 'ConsuCorner', 'consucorner' );
	}
}

if ( ! function_exists( 'consucorner_shop_specialty_vendor_logo' ) ) {
	/**
	 * Return vendor/brand image URL for a product.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	function consucorner_shop_specialty_vendor_logo( $product ) {
		$product_id = $product instanceof WC_Product ? $product->get_id() : 0;
		$fallback   = function_exists( 'consucorner_get_vendor_placeholder_image_url' )
			? consucorner_get_vendor_placeholder_image_url()
			: get_template_directory_uri() . '/assets/images/' . rawurlencode( 'consucorner icon-logo.jpg' );

		if ( ! $product_id ) {
			return $fallback;
		}

		if ( function_exists( 'dokan_get_store_info' ) ) {
			$vendor_id  = (int) get_post_field( 'post_author', $product_id );
			$store_info = $vendor_id ? dokan_get_store_info( $vendor_id ) : array();

			if ( ! empty( $store_info['gravatar'] ) ) {
				$vendor_logo = wp_get_attachment_url( (int) $store_info['gravatar'] );

				if ( $vendor_logo ) {
					return $vendor_logo;
				}
			}
		}

		$brand_terms = get_the_terms( $product_id, 'product_brand' );
		if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms ) ) {
			foreach ( array( 'thumbnail_id', 'brand_thumbnail_id', 'logo_id' ) as $meta_key ) {
				$brand_image_id = (int) get_term_meta( $brand_terms[0]->term_id, $meta_key, true );
				$brand_logo     = $brand_image_id ? wp_get_attachment_url( $brand_image_id ) : '';

				if ( $brand_logo ) {
					return $brand_logo;
				}
			}
		}

		return $fallback;
	}
}

if ( ! function_exists( 'consucorner_shop_specialty_query_products' ) ) {
	/**
	 * Query products for a carousel section.
	 *
	 * @param array  $tax_query Tax query.
	 * @param int    $count     Product count.
	 * @param string $mode      Query mode.
	 * @return WP_Query
	 */
	function consucorner_shop_specialty_query_products( array $tax_query, $count, $mode = 'date' ) {
		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => absint( $count ),
			'ignore_sticky_posts' => true,
		);

		if ( $tax_query ) {
			$args['tax_query'] = $tax_query;
		}

		if ( 'bestsellers' === $mode ) {
			$args['meta_key'] = 'total_sales';
			$args['orderby']  = 'meta_value_num';
			$args['order']    = 'DESC';
		} elseif ( 'recommended' === $mode ) {
			$args['orderby'] = 'rand';
		} else {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}

		return new WP_Query( $args );
	}
}

if ( ! function_exists( 'consucorner_shop_specialty_product_ids' ) ) {
	/**
	 * Return product IDs matching the active filters.
	 *
	 * @param array $tax_query Tax query.
	 * @return int[]
	 */
	function consucorner_shop_specialty_product_ids( array $tax_query ) {
		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => 120,
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		if ( $tax_query ) {
			$args['tax_query'] = $tax_query;
		}

		return array_map( 'absint', get_posts( $args ) );
	}
}

if ( ! function_exists( 'consucorner_shop_specialty_product_slide' ) ) {
	/**
	 * Build a New Arrivals slide object from a WooCommerce product.
	 *
	 * @param WC_Product $product Product object.
	 * @return array|null
	 */
	function consucorner_shop_specialty_product_slide( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return null;
		}

		$product_id = $product->get_id();
		$image_url  = $product->get_image_id()
			? wp_get_attachment_image_url( $product->get_image_id(), 'large' )
			: get_template_directory_uri() . '/assets/images/product-image-vendor.png';

		return array(
			'productImage' => $image_url,
			'vendorLogo'   => consucorner_shop_specialty_vendor_logo( $product ),
			'vendorName'   => consucorner_shop_specialty_vendor_name( $product ),
			'title'        => $product->get_name(),
			'link'         => get_permalink( $product_id ),
		);
	}
}

if ( ! function_exists( 'consucorner_shop_specialty_brand_logo' ) ) {
	/**
	 * Return image URL for a brand term.
	 *
	 * @param WP_Term $term Brand term.
	 * @return string
	 */
	function consucorner_shop_specialty_brand_logo( WP_Term $term ) {
		foreach ( array( 'thumbnail_id', 'brand_thumbnail_id', 'logo_id' ) as $meta_key ) {
			$image_id = (int) get_term_meta( $term->term_id, $meta_key, true );
			$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

			if ( $image ) {
				return $image;
			}
		}

		return get_template_directory_uri() . '/assets/images/consucorner.jpg';
	}
}

if ( ! function_exists( 'consucorner_shop_specialty_country_taxonomy' ) ) {
	/**
	 * Return the Country of Origin taxonomy.
	 *
	 * @return string
	 */
	function consucorner_shop_specialty_country_taxonomy() {
		$taxonomy = function_exists( 'consucorner_country_origin_taxonomy' ) ? consucorner_country_origin_taxonomy() : 'country_of_origin';
		return taxonomy_exists( $taxonomy ) ? $taxonomy : '';
	}
}

if ( ! function_exists( 'consucorner_shop_specialty_country_image' ) ) {
	/**
	 * Return image URL for a country term.
	 *
	 * @param WP_Term $term Country term.
	 * @return string
	 */
	function consucorner_shop_specialty_country_image( WP_Term $term ) {
		$image_id = (int) get_term_meta( $term->term_id, '_cc_attribute_image', true );
		return $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : get_template_directory_uri() . '/assets/images/consucorner.jpg';
	}
}

get_header();

while ( have_posts() ) :
	the_post();

	$page_id       = get_the_ID();
	$procedure_ids = get_post_meta( $page_id, '_cc_shop_specialty_procedure_ids', true );
	$category_ids  = get_post_meta( $page_id, '_cc_shop_specialty_category_ids', true );
	$brand_ids     = get_post_meta( $page_id, '_cc_shop_specialty_brand_ids', true );
	$country_ids   = get_post_meta( $page_id, '_cc_shop_specialty_country_ids', true );

	$procedure_ids = is_array( $procedure_ids ) ? array_map( 'absint', $procedure_ids ) : array();
	$category_ids  = is_array( $category_ids ) ? array_map( 'absint', $category_ids ) : array();
	$brand_ids     = is_array( $brand_ids ) ? array_map( 'absint', $brand_ids ) : array();
	$country_ids   = is_array( $country_ids ) ? array_map( 'absint', $country_ids ) : array();

	$procedure_terms  = consucorner_shop_specialty_terms( 'procedure', $procedure_ids );
	$current_procedure = consucorner_shop_specialty_current_term( 'procedure', 'instrument' );
	$current_specialty = consucorner_shop_specialty_current_term( 'product_cat', 'specialty' );

	$products_per_section = absint( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_per_page', '8' ) );
	$products_per_section = $products_per_section ? min( 16, max( 4, $products_per_section ) ) : 8;

	$tax_query = array( 'relation' => 'AND' );

	if ( $current_specialty ) {
		$tax_query[] = array(
			'taxonomy'         => 'product_cat',
			'field'            => 'term_id',
			'terms'            => array( (int) $current_specialty->term_id ),
			'include_children' => true,
		);
	} elseif ( $category_ids ) {
		$tax_query[] = array(
			'taxonomy'         => 'product_cat',
			'field'            => 'term_id',
			'terms'            => $category_ids,
			'include_children' => true,
		);
	}

	if ( $current_procedure ) {
		$tax_query[] = array(
			'taxonomy' => 'procedure',
			'field'    => 'term_id',
			'terms'    => array( (int) $current_procedure->term_id ),
		);
	} elseif ( $procedure_terms ) {
		$tax_query[] = array(
			'taxonomy' => 'procedure',
			'field'    => 'term_id',
			'terms'    => wp_list_pluck( $procedure_terms, 'term_id' ),
		);
	}

	if ( 1 === count( $tax_query ) ) {
		$tax_query = array();
	}

	$filtered_product_ids = consucorner_shop_specialty_product_ids( $tax_query );
	$country_taxonomy     = consucorner_shop_specialty_country_taxonomy();
	$brand_terms          = consucorner_shop_specialty_terms( 'product_brand', $brand_ids, $filtered_product_ids );
	$country_terms        = $country_taxonomy ? consucorner_shop_specialty_terms( $country_taxonomy, $country_ids, $filtered_product_ids ) : array();

	$new_arrival_ids_raw = consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_new_arrival_product_ids', '' );
	$new_arrival_ids     = array_values(
		array_filter(
			array_map(
				'absint',
				preg_split( '/[\s,]+/', (string) $new_arrival_ids_raw )
			)
		)
	);

	if ( $new_arrival_ids ) {
		$new_arrival_args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => count( $new_arrival_ids ),
			'post__in'            => $new_arrival_ids,
			'orderby'             => 'post__in',
			'ignore_sticky_posts' => true,
		);

		if ( $tax_query ) {
			$new_arrival_args['tax_query'] = $tax_query;
		}

		$new_arrivals = new WP_Query( $new_arrival_args );
	} else {
		$new_arrivals = consucorner_shop_specialty_query_products( $tax_query, 4, 'date' );
	}

	$bestsellers = consucorner_shop_specialty_query_products( $tax_query, $products_per_section, 'bestsellers' );
	$recommended = consucorner_shop_specialty_query_products( $tax_query, $products_per_section, 'recommended' );

	$first_arrival = null;
	if ( $new_arrivals->have_posts() ) {
		$first_arrival = wc_get_product( $new_arrivals->posts[0]->ID );
	}

	$first_arrival_image = $first_arrival && $first_arrival->get_image_id()
		? wp_get_attachment_image_url( $first_arrival->get_image_id(), 'large' )
		: get_template_directory_uri() . '/assets/images/product-image-vendor.png';
	$first_arrival_title       = $first_arrival ? $first_arrival->get_name() : __( 'Illumination head for Disposable Proctoscopy', 'consucorner' );
	$first_arrival_link        = $first_arrival ? get_permalink( $first_arrival->get_id() ) : home_url( '/shop/' );
	$first_arrival_vendor      = $first_arrival ? consucorner_shop_specialty_vendor_name( $first_arrival ) : __( 'LifeCare Surgical', 'consucorner' );
	$first_arrival_vendor_logo = $first_arrival
		? consucorner_shop_specialty_vendor_logo( $first_arrival )
		: ( function_exists( 'consucorner_get_vendor_placeholder_image_url' )
			? consucorner_get_vendor_placeholder_image_url()
			: get_template_directory_uri() . '/assets/images/' . rawurlencode( 'consucorner icon-logo.jpg' ) );

	$new_arrival_slides = array();
	foreach ( $new_arrivals->posts as $arrival_post ) {
		$slide_product = wc_get_product( $arrival_post->ID );
		$slide         = consucorner_shop_specialty_product_slide( $slide_product );

		if ( $slide ) {
			$new_arrival_slides[] = $slide;
		}
	}

	if ( $new_arrival_slides ) {
		wp_add_inline_script(
			'consucorner-new-arrival-slider',
			'window.consuNewArrivalData = Object.assign({}, window.consuNewArrivalData || {}, { slides: ' . wp_json_encode( $new_arrival_slides ) . ' });',
			'before'
		);
	}
	?>

	<main class="shop-specialty-page">
		<section class="shop-page-head" aria-label="<?php esc_attr_e( 'Shop specialty page heading', 'consucorner' ); ?>">
			<div class="shop-page-head-inner">
				<h3 class="subtitle"><?php echo esc_html( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_head_subtitle', __( 'Every Thing you Need in', 'consucorner' ) ) ); ?></h3>
				<h1 class="shop-page-title"><?php echo esc_html( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_head_title', __( 'Ophthalmology', 'consucorner' ) ) ); ?></h1>
				<p class="shop-page-breadcrumbs"><?php consucorner_render_breadcrumbs( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_head_breadcrumbs', __( 'Home/Ophthalmology', 'consucorner' ) ), get_permalink() ); ?></p>
			</div>
		</section>

		<section class="shop-instrument-section">
			<div class="shop-specialty-inner">
				<div class="shop-section-header">
					<h2 class="shop-section-title"><?php echo esc_html( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_filter_title', __( 'Shop By Instrument', 'consucorner' ) ) ); ?></h2>
					<p class="shop-section-copy"><?php echo esc_html( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_filter_copy', __( 'Filter this specialty by instrument type and quickly find the right tools for your workflow.', 'consucorner' ) ) ); ?></p>
				</div>

				<div class="shop-slider-shell">
					<button class="shop-side-arrow" id="shop-inst-prev" type="button" aria-label="<?php esc_attr_e( 'Previous instrument', 'consucorner' ); ?>">‹</button>
					<div class="shop-slider-viewport">
						<div class="shop-specialty-track" id="shop-instrument-track">
							<a href="<?php echo esc_url( consucorner_shop_specialty_filter_url( 'instrument', '' ) ); ?>" class="shop-specialty-card shop-specialty-card-green<?php echo ! $current_procedure ? ' is-active' : ''; ?>">
								<?php echo esc_html( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_all_label', __( 'All Instruments', 'consucorner' ) ) ); ?>
							</a>
							<?php foreach ( $procedure_terms as $index => $term ) : ?>
								<a href="<?php echo esc_url( consucorner_shop_specialty_filter_url( 'instrument', $term->slug ) ); ?>" class="shop-specialty-card <?php echo 0 === $index % 2 ? 'shop-specialty-card-blue' : 'shop-specialty-card-green'; ?><?php echo $current_procedure && (int) $current_procedure->term_id === (int) $term->term_id ? ' is-active' : ''; ?>">
									<?php echo esc_html( $term->name ); ?>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
					<button class="shop-side-arrow" id="shop-inst-next" type="button" aria-label="<?php esc_attr_e( 'Next instrument', 'consucorner' ); ?>">›</button>
				</div>
			</div>
		</section>

		<div class="bestsellers-section">
			<div class="bestsellers-header">
				<h2 class="bestsellers-title"><?php echo esc_html( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_bestsellers_title', __( 'Bestsellers', 'consucorner' ) ) ); ?></h2>
				<div class="bestsellers-nav">
					<button type="button" class="bs-slider-btn bs-prev" aria-label="<?php esc_attr_e( 'Previous', 'consucorner' ); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-chevron-left" /></svg></button>
					<button type="button" class="bs-slider-btn bs-next" aria-label="<?php esc_attr_e( 'Next', 'consucorner' ); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-chevron-right" /></svg></button>
				</div>
			</div>
			<div class="bestsellers-slider">
				<div class="bs-track">
					<?php if ( $bestsellers->have_posts() ) : ?>
						<?php while ( $bestsellers->have_posts() ) : $bestsellers->the_post(); ?>
							<?php echo function_exists( 'cc_render_product_card' ) ? cc_render_product_card( get_the_ID() ) : ''; ?>
						<?php endwhile; wp_reset_postdata(); ?>
					<?php else : ?>
						<p class="fp-no-results" style="width:100%;padding:24px;text-align:center;"><?php echo esc_html( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_no_results', __( 'No products found for this specialty filter yet.', 'consucorner' ) ) ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<section class="medical-products-banner" aria-label="<?php esc_attr_e( 'Promotional medical products banner', 'consucorner' ); ?>"></section>

		<section class="recommended-for-you-section">
			<div class="recommended-for-you-header">
				<h2 class="recommended-for-you-title"><?php echo esc_html( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_recommended_title', __( 'Recommended for you', 'consucorner' ) ) ); ?></h2>
				<div class="recommended-for-you-nav">
					<button type="button" class="rec-slider-btn rec-prev" aria-label="<?php esc_attr_e( 'Previous', 'consucorner' ); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-chevron-left" /></svg></button>
					<button type="button" class="rec-slider-btn rec-next" aria-label="<?php esc_attr_e( 'Next', 'consucorner' ); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-chevron-right" /></svg></button>
				</div>
			</div>
			<div class="recommended-for-you-slider">
				<div class="rec-track">
					<?php if ( $recommended->have_posts() ) : ?>
						<?php while ( $recommended->have_posts() ) : $recommended->the_post(); ?>
							<?php echo function_exists( 'cc_render_product_card' ) ? cc_render_product_card( get_the_ID() ) : ''; ?>
						<?php endwhile; wp_reset_postdata(); ?>
					<?php else : ?>
						<p class="fp-no-results" style="width:100%;padding:24px;text-align:center;"><?php echo esc_html( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_no_results', __( 'No products found for this specialty filter yet.', 'consucorner' ) ) ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<section class="best-saler-section custom-section">
			<div class="best-saler-section-content">
				<h3><?php echo wp_kses_post( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_new_arrivals_title', 'New <span>Arrivals</span>' ) ); ?></h3>
				<div class="best-saler-section slider">
					<div class="best-saler-section-slider-left">
						<div class="product-slider-image-background-wrapper">
							<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/Rectangle blue.svg' ); ?>" alt="<?php esc_attr_e( 'Product Image Background', 'consucorner' ); ?>" />
							<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/Rectangle baby-blue.svg' ); ?>" alt="<?php esc_attr_e( 'Product Image Background', 'consucorner' ); ?>" />
						</div>
						<div class="product-slider-image-wrapper">
							<img src="<?php echo esc_url( $first_arrival_image ); ?>" alt="<?php echo esc_attr( $first_arrival_title ); ?>" />
						</div>
					</div>
					<div class="best-saler-section-slider-right">
						<div class="vendor-slider-item">
							<img src="<?php echo esc_url( $first_arrival_vendor_logo ); ?>" alt="<?php echo esc_attr( $first_arrival_vendor ); ?>" />
							<p class="vendor-slider-item-name"><?php echo esc_html( $first_arrival_vendor ); ?></p>
						</div>
						<h2 class="product-slider-item-title"><?php echo esc_html( $first_arrival_title ); ?></h2>
						<div class="product-slider-btn">
							<a class="btn-shop-now" href="<?php echo esc_url( $first_arrival_link ); ?>"><?php echo esc_html( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_shop_now_text', __( 'Shop Now', 'consucorner' ) ) ); ?></a>
							<div class="product-vendor-nav-btn-new-arrivals">
								<button type="button" class="slider-btn slider-prev" aria-label="<?php esc_attr_e( 'Previous', 'consucorner' ); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-chevron-left" /></svg></button>
								<button type="button" class="slider-btn slider-next" aria-label="<?php esc_attr_e( 'Next', 'consucorner' ); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-chevron-right" /></svg></button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>

		<?php if ( $brand_terms ) : ?>
			<section class="popular-brands-section" aria-label="<?php esc_attr_e( 'Popular Brands', 'consucorner' ); ?>">
				<div class="popular-brands-container">
					<div class="popular-brands-header">
						<h2><?php echo wp_kses_post( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_brands_title', '<span class="green-text">Popular</span> Brands' ) ); ?></h2>
						<div class="popular-brands-nav">
							<button type="button" class="rec-slider-btn popular-brands-prev" aria-label="<?php esc_attr_e( 'Previous', 'consucorner' ); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-chevron-left" /></svg></button>
							<button type="button" class="rec-slider-btn popular-brands-next" aria-label="<?php esc_attr_e( 'Next', 'consucorner' ); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-chevron-right" /></svg></button>
						</div>
					</div>
					<div class="popular-brands-slider">
						<div class="popular-brands-track">
							<?php foreach ( $brand_terms as $brand_term ) : ?>
								<div class="popular-brands-item">
									<img src="<?php echo esc_url( consucorner_shop_specialty_brand_logo( $brand_term ) ); ?>" alt="<?php echo esc_attr( $brand_term->name ); ?>" loading="lazy" />
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $country_terms ) : ?>
			<section class="country-origin-section" aria-label="<?php esc_attr_e( 'Country of Origin', 'consucorner' ); ?>">
				<div class="country-origin-container">
					<div class="country-origin-header">
						<h2><?php echo wp_kses_post( consucorner_shop_specialty_meta( $page_id, '_cc_shop_specialty_country_title', '<span class="teal-text">Country</span> of Origin' ) ); ?></h2>
						<div class="country-origin-nav">
							<button type="button" class="rec-slider-btn country-origin-prev" aria-label="<?php esc_attr_e( 'Previous', 'consucorner' ); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-chevron-left" /></svg></button>
							<button type="button" class="rec-slider-btn country-origin-next" aria-label="<?php esc_attr_e( 'Next', 'consucorner' ); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-chevron-right" /></svg></button>
						</div>
					</div>
					<div class="country-origin-slider">
						<div class="country-origin-track">
							<?php foreach ( $country_terms as $country_term ) : ?>
								<div class="country-origin-item">
									<img src="<?php echo esc_url( consucorner_shop_specialty_country_image( $country_term ) ); ?>" alt="<?php echo esc_attr( $country_term->name ); ?>" loading="lazy" />
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</section>
		<?php endif; ?>
	</main>
	<?php
endwhile;

get_footer();
