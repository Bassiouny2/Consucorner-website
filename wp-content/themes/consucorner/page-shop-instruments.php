<?php
/**
 * Shop Instruments Page Template.
 *
 * Template Name: Shop Instruments Page
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'consucorner_shop_instruments_meta' ) ) {
	/**
	 * Return editable page meta with a fallback.
	 *
	 * @param int    $page_id Page ID.
	 * @param string $key     Meta key.
	 * @param string $default Default value.
	 * @return string
	 */
	function consucorner_shop_instruments_meta( $page_id, $key, $default = '' ) {
		$value = get_post_meta( $page_id, $key, true );
		return '' !== $value && false !== $value ? $value : $default;
	}
}

if ( ! function_exists( 'consucorner_shop_instruments_terms' ) ) {
	/**
	 * Return filter terms, optionally limited by saved term IDs.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $saved_ids Saved term IDs.
	 * @return WP_Term[]
	 */
	function consucorner_shop_instruments_terms( $taxonomy, array $saved_ids = array() ) {
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		if ( $saved_ids ) {
			$args['include'] = array_map( 'absint', $saved_ids );
			$args['orderby'] = 'include';
		}

		$terms = get_terms( $args );
		return is_wp_error( $terms ) ? array() : $terms;
	}
}

if ( ! function_exists( 'consucorner_shop_instruments_current_term' ) ) {
	/**
	 * Find the selected term from a query-string slug.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param string $query_key Query key.
	 * @return WP_Term|null
	 */
	function consucorner_shop_instruments_current_term( $taxonomy, $query_key ) {
		if ( empty( $_GET[ $query_key ] ) ) {
			return null;
		}

		$slug = sanitize_title( wp_unslash( $_GET[ $query_key ] ) );
		$term = get_term_by( 'slug', $slug, $taxonomy );

		return $term instanceof WP_Term ? $term : null;
	}
}

if ( ! function_exists( 'consucorner_shop_instruments_filter_url' ) ) {
	/**
	 * Build a filter URL while preserving supported filters.
	 *
	 * @param string $key   Query key.
	 * @param string $value Query value.
	 * @return string
	 */
	function consucorner_shop_instruments_filter_url( $key, $value ) {
		$url  = get_permalink();
		$args = array();

		foreach ( array( 'instrument', 'category', 'orderby' ) as $allowed_key ) {
			if ( isset( $_GET[ $allowed_key ] ) && '' !== $_GET[ $allowed_key ] ) {
				$args[ $allowed_key ] = sanitize_text_field( wp_unslash( $_GET[ $allowed_key ] ) );
			}
		}

		if ( '' === $value ) {
			unset( $args[ $key ] );
		} else {
			$args[ $key ] = $value;
		}

		unset( $args['paged'] );

		return add_query_arg( $args, $url );
	}
}

if ( ! function_exists( 'consucorner_shop_instruments_vendor_name' ) ) {
	/**
	 * Return a compact brand/vendor label for a product.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	function consucorner_shop_instruments_vendor_name( $product ) {
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

if ( ! function_exists( 'consucorner_shop_instruments_vendor_logo' ) ) {
	/**
	 * Return vendor/brand image URL for a product.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	function consucorner_shop_instruments_vendor_logo( $product ) {
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

if ( ! function_exists( 'consucorner_shop_instruments_query_products' ) ) {
	/**
	 * Query products for one of the static-design carousel sections.
	 *
	 * @param array  $tax_query Tax query.
	 * @param int    $count     Product count.
	 * @param string $mode      Query mode.
	 * @return WP_Query
	 */
	function consucorner_shop_instruments_query_products( array $tax_query, $count, $mode = 'date' ) {
		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => absint( $count ),
			'ignore_sticky_posts' => true,
		);

		if ( $tax_query ) {
			$args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax_query );
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

if ( ! function_exists( 'consucorner_shop_instruments_product_slide' ) ) {
	/**
	 * Build a New Arrivals slide object from a WooCommerce product.
	 *
	 * @param WC_Product $product Product object.
	 * @return array|null
	 */
	function consucorner_shop_instruments_product_slide( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return null;
		}

		$product_id = $product->get_id();
		$image_url  = $product->get_image_id()
			? wp_get_attachment_image_url( $product->get_image_id(), 'large' )
			: get_template_directory_uri() . '/assets/images/product-image-vendor.png';

		return array(
			'productImage' => $image_url,
			'vendorLogo'   => consucorner_shop_instruments_vendor_logo( $product ),
			'vendorName'   => consucorner_shop_instruments_vendor_name( $product ),
			'title'        => $product->get_name(),
			'link'         => get_permalink( $product_id ),
		);
	}
}

get_header();

while ( have_posts() ) :
	the_post();

	$page_id       = get_the_ID();
	$procedure_ids = get_post_meta( $page_id, '_cc_shop_instruments_procedure_ids', true );
	$category_ids  = get_post_meta( $page_id, '_cc_shop_instruments_category_ids', true );

	$procedure_ids = is_array( $procedure_ids ) ? array_map( 'absint', $procedure_ids ) : array();
	$category_ids  = is_array( $category_ids ) ? array_map( 'absint', $category_ids ) : array();

	$procedure_terms = consucorner_shop_instruments_terms( 'procedure', $procedure_ids );
	$category_terms  = consucorner_shop_instruments_terms( 'product_cat', $category_ids );

	$current_procedure = consucorner_shop_instruments_current_term( 'procedure', 'instrument' );
	$current_category  = consucorner_shop_instruments_current_term( 'product_cat', 'category' );

	$products_per_section = absint( consucorner_shop_instruments_meta( $page_id, '_cc_shop_instruments_per_page', '8' ) );
	$products_per_section = $products_per_section ? min( 16, max( 4, $products_per_section ) ) : 8;

	$tax_query = array();

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

	if ( $current_category ) {
		$tax_query[] = array(
			'taxonomy'         => 'product_cat',
			'field'            => 'term_id',
			'terms'            => array( (int) $current_category->term_id ),
			'include_children' => true,
		);
	}

	if ( $tax_query ) {
		$tax_query = array_merge( array( 'relation' => 'AND' ), $tax_query );
	}

	$new_arrival_ids_raw = consucorner_shop_instruments_meta( $page_id, '_cc_shop_instruments_new_arrival_product_ids', '' );
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
		$new_arrivals = consucorner_shop_instruments_query_products( $tax_query, 4, 'date' );
	}

	$bestsellers = consucorner_shop_instruments_query_products( $tax_query, $products_per_section, 'bestsellers' );
	$recommended = consucorner_shop_instruments_query_products( $tax_query, $products_per_section, 'recommended' );

	$first_arrival = null;
	if ( $new_arrivals->have_posts() ) {
		$first_arrival = wc_get_product( $new_arrivals->posts[0]->ID );
	}

	$first_arrival_image = $first_arrival && $first_arrival->get_image_id()
		? wp_get_attachment_image_url( $first_arrival->get_image_id(), 'large' )
		: get_template_directory_uri() . '/assets/images/product-image-vendor.png';
	$first_arrival_title = $first_arrival ? $first_arrival->get_name() : __( 'Illumination head for Disposable Proctoscopy', 'consucorner' );
	$first_arrival_link  = $first_arrival ? get_permalink( $first_arrival->get_id() ) : home_url( '/shop/' );
	$first_arrival_vendor = $first_arrival ? consucorner_shop_instruments_vendor_name( $first_arrival ) : __( 'LifeCare Surgical', 'consucorner' );
	$first_arrival_vendor_logo = $first_arrival
		? consucorner_shop_instruments_vendor_logo( $first_arrival )
		: ( function_exists( 'consucorner_get_vendor_placeholder_image_url' )
			? consucorner_get_vendor_placeholder_image_url()
			: get_template_directory_uri() . '/assets/images/' . rawurlencode( 'consucorner icon-logo.jpg' ) );

	$new_arrival_slides = array();
	foreach ( $new_arrivals->posts as $arrival_post ) {
		$slide_product = wc_get_product( $arrival_post->ID );
		$slide         = consucorner_shop_instruments_product_slide( $slide_product );

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

	<main class="shop-instruments-page">
		<section class="shop-page-head" aria-label="<?php esc_attr_e( 'Shop instruments page heading', 'consucorner' ); ?>">
			<div class="shop-page-head-inner">
				<h1 class="shop-page-title"><?php echo esc_html( consucorner_shop_instruments_meta( $page_id, '_cc_shop_instruments_head_title', __( 'Shop Instruments', 'consucorner' ) ) ); ?></h1>
				<p class="shop-page-breadcrumbs"><?php consucorner_render_breadcrumbs( consucorner_shop_instruments_meta( $page_id, '_cc_shop_instruments_head_breadcrumbs', __( 'Home/Shop Instruments', 'consucorner' ) ), get_permalink() ); ?></p>
			</div>
		</section>

		<section class="shop-specialty-section">
			<div class="shop-specialty-inner">
				<div class="shop-section-header">
					<h2 class="shop-section-title"><?php echo esc_html( consucorner_shop_instruments_meta( $page_id, '_cc_shop_instruments_filter_title', __( 'Shop By Instrument', 'consucorner' ) ) ); ?></h2>
					<p class="shop-section-copy"><?php echo esc_html( consucorner_shop_instruments_meta( $page_id, '_cc_shop_instruments_filter_copy', __( 'Filter medical products by procedure or instrument type and quickly find the right tools for your specialty.', 'consucorner' ) ) ); ?></p>
				</div>

				<div class="shop-slider-shell">
					<button class="shop-side-arrow" id="shop-spec-prev" type="button" aria-label="<?php esc_attr_e( 'Previous instrument', 'consucorner' ); ?>">‹</button>
					<div class="shop-slider-viewport">
						<div class="shop-specialty-track" id="shop-specialty-track">
							<a href="<?php echo esc_url( consucorner_shop_instruments_filter_url( 'instrument', '' ) ); ?>" class="shop-specialty-card shop-specialty-card-green<?php echo ! $current_procedure ? ' is-active' : ''; ?>">
								<?php echo esc_html( consucorner_shop_instruments_meta( $page_id, '_cc_shop_instruments_all_label', __( 'All Instruments', 'consucorner' ) ) ); ?>
							</a>
							<?php foreach ( $procedure_terms as $index => $term ) : ?>
								<a href="<?php echo esc_url( consucorner_shop_instruments_filter_url( 'instrument', $term->slug ) ); ?>" class="shop-specialty-card <?php echo 0 === $index % 2 ? 'shop-specialty-card-blue' : 'shop-specialty-card-green'; ?><?php echo $current_procedure && (int) $current_procedure->term_id === (int) $term->term_id ? ' is-active' : ''; ?>">
									<?php echo esc_html( $term->name ); ?>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
					<button class="shop-side-arrow" id="shop-spec-next" type="button" aria-label="<?php esc_attr_e( 'Next instrument', 'consucorner' ); ?>">›</button>
				</div>
			</div>
		</section>

		<section class="best-saler-section custom-section">
			<div class="best-saler-section-content">
				<h3><?php echo wp_kses_post( consucorner_shop_instruments_meta( $page_id, '_cc_shop_instruments_new_arrivals_title', 'New <span>Arrivals</span>' ) ); ?></h3>
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
							<a class="btn-shop-now" href="<?php echo esc_url( $first_arrival_link ); ?>"><?php echo esc_html( consucorner_shop_instruments_meta( $page_id, '_cc_shop_instruments_shop_now_text', __( 'Shop Now', 'consucorner' ) ) ); ?></a>
							<div class="product-vendor-nav-btn-new-arrivals">
								<button type="button" class="slider-btn slider-prev" aria-label="<?php esc_attr_e( 'Previous', 'consucorner' ); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-chevron-left" /></svg></button>
								<button type="button" class="slider-btn slider-next" aria-label="<?php esc_attr_e( 'Next', 'consucorner' ); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-chevron-right" /></svg></button>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="curve">
				<svg viewBox="0 0 1440 80" preserveAspectRatio="none">
					<defs><linearGradient id="curveGradient" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stop-color="rgba(100, 233, 204, 0.8)" /><stop offset="100%" stop-color="rgba(177, 248, 232, 0.8)" /></linearGradient></defs>
					<path d="M0,40 C360,70 1080,10 1440,40 L1440,80 L0,80 Z" fill="url(#curveGradient)"></path>
					<path d="M0,40 C360,70 1080,10 1440,40" fill="none" stroke="url(#curveGradient)" stroke-width="2"></path>
				</svg>
			</div>
		</section>

		<section class="bottom-fill">
			<div class="bestsellers-section">
				<div class="bestsellers-header">
					<h2 class="bestsellers-title"><?php echo esc_html( consucorner_shop_instruments_meta( $page_id, '_cc_shop_instruments_bestsellers_title', __( 'Bestsellers', 'consucorner' ) ) ); ?></h2>
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
							<p class="fp-no-results" style="width:100%;padding:24px;text-align:center;"><?php echo esc_html( consucorner_shop_instruments_meta( $page_id, '_cc_shop_instruments_no_results', __( 'No products found for this instrument filter yet.', 'consucorner' ) ) ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</section>

		<section class="medical-products-banner" aria-label="<?php esc_attr_e( 'Promotional medical products banner', 'consucorner' ); ?>"></section>

		<section class="recommended-for-you-section">
			<div class="recommended-for-you-header">
				<h2 class="recommended-for-you-title"><?php echo esc_html( consucorner_shop_instruments_meta( $page_id, '_cc_shop_instruments_recommended_title', __( 'Recommended for you', 'consucorner' ) ) ); ?></h2>
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
						<p class="fp-no-results" style="width:100%;padding:24px;text-align:center;"><?php echo esc_html( consucorner_shop_instruments_meta( $page_id, '_cc_shop_instruments_no_results', __( 'No products found for this instrument filter yet.', 'consucorner' ) ) ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="recommended-actions">
				<a href="<?php echo esc_url( get_permalink() ); ?>" class="btn-all-specialties"><?php echo esc_html( consucorner_shop_instruments_meta( $page_id, '_cc_shop_instruments_all_label', __( 'All Instruments', 'consucorner' ) ) ); ?></a>
			</div>
		</section>
	</main>
	<?php
endwhile;

get_footer();
