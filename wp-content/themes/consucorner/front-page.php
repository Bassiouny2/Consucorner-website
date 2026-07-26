<?php

/**
 * Front page template.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

get_header();

$cc_home_id   = (int) get_queried_object_id();
if (! $cc_home_id) {
	$cc_home_id = (int) get_option('page_on_front');
}
$cc_home_imgs = get_template_directory_uri() . '/assets/images/';

if (! function_exists('cc_front_meta')) {
	function cc_front_meta($post_id, $key, $default = '')
	{
		$value = $post_id ? get_post_meta($post_id, $key, true) : '';
		return ('' !== $value && false !== $value) ? $value : $default;
	}
}

if (! function_exists('cc_front_bg_style')) {
	function cc_front_bg_style($url)
	{
		$url = trim((string) $url);
		return $url ? ' style="background-image:url(' . esc_url($url) . ');"' : '';
	}
}
?>

<main id="primary" class="site-main">
	<!-- Hero + Product Banner Section -->
	<section class="hero-section">
		<div class="hero-bg" <?php echo cc_front_bg_style(cc_front_meta($cc_home_id, '_cc_home_hero_bg_image')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
													?>></div>
		<div class="hero-wrapper">
			<div class="hero-content">
				<h1 class="hero-title">
					<span class="hero-title-blue"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_hero_title_blue', "Egypt's Medical")); ?></span>
					<span class="hero-title-gradient"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_hero_title_gradient', 'Marketplace')); ?></span>
				</h1>
				<h2 class="hero-subtitle"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_hero_subtitle', 'Tools For Every Specialty')); ?></h2>

				<div class="hero-payment-block">
					<div class="payment-header-row">
						<ul class="payment-list">
							<li>
								<strong><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_payment_title', 'Flexible & Secure Payment Options')); ?></strong>
								<div class="payment-logos payment-logos-mobile">
									<img
										src="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_payment_logo_2', $cc_home_imgs . 'visa.png')); ?>"
										alt="Visa"
										class="payment-logo" />
									<img
										src="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_payment_logo_1', $cc_home_imgs . 'mastercard.png')); ?>"
										alt="Mastercard"
										class="payment-logo" />
								</div>
							</li>
						</ul>
						<div class="payment-logos payment-logos-desktop">
							<img
								src="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_payment_logo_1', $cc_home_imgs . 'mastercard.png')); ?>"
								alt="Mastercard"
								class="payment-logo" />
							<img
								src="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_payment_logo_2', $cc_home_imgs . 'visa.png')); ?>"
								alt="Visa"
								class="payment-logo" />
						</div>
					</div>
					<p class="payment-text">
						<?php echo wp_kses_post(cc_front_meta($cc_home_id, '_cc_home_payment_text', 'Choose the payment method that works best for you - including cash on delivery and online payment options - for a seamless checkout experience')); ?>
					</p>
				</div>
			</div>
			<div class="hero-banner">
				<?php
				$cc_hero_banner_slides = function_exists( 'consucorner_get_home_hero_banner_slides' )
					? consucorner_get_home_hero_banner_slides( $cc_home_id, $cc_home_imgs )
					: array();
				if ( function_exists( 'consucorner_render_hero_banner_slides' ) ) {
					consucorner_render_hero_banner_slides( $cc_hero_banner_slides );
				}
				$cc_hero_slide_total = count( $cc_hero_banner_slides );
				if ( $cc_hero_slide_total > 1 ) :
					?>
				<div class="hero-banner-controls" data-hero-banner-total="<?php echo esc_attr( (string) $cc_hero_slide_total ); ?>">
					<button
						type="button"
						class="hero-banner-controls__btn"
						data-hero-banner-prev
						aria-label="<?php esc_attr_e( 'Previous banner', 'consucorner' ); ?>"
					>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<use href="#icon-chevron-left" />
						</svg>
					</button>
					<div class="hero-banner-controls__track">
						<span class="hero-banner-controls__count" aria-live="polite">
							<span class="hero-banner-controls__current" data-hero-banner-current>01</span><span class="hero-banner-controls__total">/<?php echo esc_html( sprintf( '%02d', $cc_hero_slide_total ) ); ?></span>
						</span>
						<div class="hero-banner-controls__progress" aria-hidden="true">
							<span class="hero-banner-controls__progress-fill" data-hero-banner-progress style="width: <?php echo esc_attr( (string) ( 100 / $cc_hero_slide_total ) ); ?>%;"></span>
						</div>
					</div>
					<button
						type="button"
						class="hero-banner-controls__btn"
						data-hero-banner-next
						aria-label="<?php esc_attr_e( 'Next banner', 'consucorner' ); ?>"
					>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<use href="#icon-chevron-right" />
						</svg>
					</button>
				</div>
					<?php
				endif;
				?>
			</div>
		</div>
	</section>

	<section class="popular-categories">
		<?php
		$popular_taxonomy = taxonomy_exists('product_cat') ? 'product_cat' : 'category';
		$popular_categories = get_terms(
			array(
				'taxonomy'   => $popular_taxonomy,
				'hide_empty' => false,
				'parent'     => 0,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if (! is_wp_error($popular_categories) && ! empty($popular_categories) && function_exists('consucorner_sort_terms_by_order')) {
			$popular_categories = consucorner_sort_terms_by_order($popular_categories);
		}
		?>
		<div class="categories-header">
			<h2 class="categories-title"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_categories_title', 'Popular Categories')); ?></h2>
			<div class="categories-nav">
				<button
					type="button"
					class="slider-btn slider-prev"
					aria-label="Previous">
					<svg
						width="20"
						height="20"
						viewBox="0 0 24 24"
						fill="none"
						stroke="currentColor"
						stroke-width="2"
						stroke-linecap="round"
						stroke-linejoin="round">
						<use href="#icon-chevron-left" />
					</svg>
				</button>
				<button
					type="button"
					class="slider-btn slider-next"
					aria-label="Next">
					<svg
						width="20"
						height="20"
						viewBox="0 0 24 24"
						fill="none"
						stroke="currentColor"
						stroke-width="2"
						stroke-linecap="round"
						stroke-linejoin="round">
						<use href="#icon-chevron-right" />
					</svg>
				</button>
			</div>
		</div>
		<div class="categories-slider">
			<div class="slider-track">
				<?php if (! is_wp_error($popular_categories) && ! empty($popular_categories)) : ?>
					<?php foreach ($popular_categories as $category_term) : ?>
						<?php
						$category_link = get_term_link($category_term);
						if (is_wp_error($category_link)) {
							continue;
						}

						$category_image = cc_front_meta($cc_home_id, '_cc_home_category_fallback_image', $cc_home_imgs . 'product%20demo.png');
						if ('product_cat' === $popular_taxonomy) {
							$thumbnail_id = (int) get_term_meta($category_term->term_id, 'thumbnail_id', true);
							$thumbnail_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';
							if ($thumbnail_url) {
								$category_image = $thumbnail_url;
							}
						}
						?>
						<div class="card">
							<a href="<?php echo esc_url($category_link); ?>" class="card-img-container" aria-label="<?php echo esc_attr($category_term->name); ?>">
								<img src="<?php echo esc_url($category_image); ?>" alt="<?php echo esc_attr($category_term->name); ?>" />
							</a>
							<h3 class="card-title"><?php echo esc_html($category_term->name); ?></h3>
							<a href="<?php echo esc_url($category_link); ?>" class="card-btn"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_category_btn_text', 'Shop Now')); ?></a>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="card">
						<a href="<?php echo esc_url(home_url('/shop/')); ?>" class="card-img-container" aria-label="Shop">
							<img src="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_category_fallback_image', $cc_home_imgs . 'product%20demo.png')); ?>" alt="Category" />
						</a>
						<h3 class="card-title">No categories yet</h3>
						<a href="<?php echo esc_url(home_url('/shop/')); ?>" class="card-btn"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_category_btn_text', 'Shop Now')); ?></a>
					</div>
				<?php endif; ?>
			</div>

			<!-- Mobile Custom Slider Nav (hidden on desktop) -->
			<div class="mobile-slider-indicator">
				<button class="arrow arrow-prev" aria-label="Previous card">
					<svg
						width="16"
						height="16"
						viewBox="0 0 24 24"
						fill="none"
						stroke="currentColor"
						stroke-width="2.5"
						stroke-linecap="round"
						stroke-linejoin="round">
						<use href="#icon-chevron-left" />
					</svg>
				</button>
				<button class="arrow arrow-next" aria-label="Next card">
					<svg
						width="16"
						height="16"
						viewBox="0 0 24 24"
						fill="none"
						stroke="currentColor"
						stroke-width="2.5"
						stroke-linecap="round"
						stroke-linejoin="round">
						<use href="#icon-chevron-right" />
					</svg>
				</button>
			</div>
		</div>
	</section>
	<section>
		<div class="medical-products-banner" <?php echo cc_front_bg_style(cc_front_meta($cc_home_id, '_cc_home_mid_banner_bg', $cc_home_imgs . 'Banner Section.webp')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
																					?>></div>
	</section>
	<section class="browse-specialties-section">
		<?php
		$browse_taxonomy = taxonomy_exists('specialty') ? 'specialty' : (taxonomy_exists('product_cat') ? 'product_cat' : 'category');
		$browse_specialties = get_terms(
			array(
				'taxonomy'   => $browse_taxonomy,
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		if (is_wp_error($browse_specialties)) {
			$browse_specialties = array();
		}
		$default_specialty_slug = ! empty($browse_specialties) ? $browse_specialties[0]->slug : '';
		$default_specialty_name = ! empty($browse_specialties) ? $browse_specialties[0]->name : '';
		$default_specialty_link = get_post_type_archive_link('product') ?: home_url('/shop/');
		if (! empty($browse_specialties)) {
			$first_term_link = get_term_link($browse_specialties[0]);
			if (! is_wp_error($first_term_link)) {
				$default_specialty_link = $first_term_link;
			}
		}
		$browse_button_base_text = cc_front_meta($cc_home_id, '_cc_home_browse_btn_text', 'Shop All');
		$browse_button_text      = $default_specialty_name ? sprintf(
			/* translators: %s: specialty name. */
			__('Shop %s', 'consucorner'),
			$default_specialty_name
		) : $browse_button_base_text;
		?>
		<div class="browse-header">
			<h2 class="browse-title">
				<?php echo wp_kses_post(cc_front_meta($cc_home_id, '_cc_home_browse_title', 'Browse Medical Tools by<br />Your Specialty')); ?>
			</h2>
			<div class="browse-categories-carousel" data-browse-carousel>
				<button
					type="button"
					class="browse-categories-arrow browse-categories-arrow--prev"
					aria-label="<?php esc_attr_e('Previous specialties', 'consucorner'); ?>"
					disabled>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true" focusable="false">
						<use href="#icon-chevron-left" />
					</svg>
				</button>
				<div class="browse-categories-viewport">
					<div class="browse-categories-track" id="browse-categories" data-cc-tour="home-categories">
						<?php foreach ($browse_specialties as $index => $term) : ?>
							<?php $term_link = get_term_link($term); ?>
							<?php if (! is_wp_error($term_link)) : ?>
								<a
									href="<?php echo esc_url($term_link); ?>"
									class="specialty-pill <?php echo 0 === $index ? 'active' : ''; ?>"
									data-specialty="<?php echo esc_attr($term->slug); ?>"
									data-specialty-name="<?php echo esc_attr($term->name); ?>">
									<?php echo esc_html($term->name); ?>
								</a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
				<button
					type="button"
					class="browse-categories-arrow browse-categories-arrow--next"
					aria-label="<?php esc_attr_e('Next specialties', 'consucorner'); ?>">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true" focusable="false">
						<use href="#icon-chevron-right" />
					</svg>
				</button>
			</div>
		</div>
		<div class="browse-grid fp-products-grid" id="browse-grid" data-cc-tour="home-grid" data-default-specialty="<?php echo esc_attr($default_specialty_slug); ?>">
			<p class="fp-no-results" style="grid-column:1/-1;text-align:center;"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_browse_loading_text', 'Loading products...')); ?></p>
		</div>
		<div class="browse-actions">
			<a
				href="<?php echo esc_url($default_specialty_link); ?>"
				class="btn-all-specialties"
				data-default-label="<?php echo esc_attr($browse_button_base_text); ?>">
				<?php echo esc_html($browse_button_text); ?>
			</a>
		</div>
	</section>
	<?php
	$cc_new_arrivals_slides = function_exists('cc_get_home_new_arrivals_slides') ? cc_get_home_new_arrivals_slides($cc_home_id, $cc_home_imgs) : array();
	$cc_new_arrivals_first  = ! empty($cc_new_arrivals_slides) ? reset($cc_new_arrivals_slides) : array();
	?>
	<?php if (! empty($cc_new_arrivals_first)) : ?>
		<section class="best-saler-section custom-section">
			<!-- Your custom SVG Curve -->
			<div class="best-saler-section-content">
				<h3><?php echo wp_kses_post(cc_front_meta($cc_home_id, '_cc_home_new_arrivals_title', 'New <span>Arrivals</span>')); ?></h3>
				<div class="best-saler-section slider">
					<div class="best-saler-section-slider-left">
						<div class="product-slider-image-background-wrapper">
							<img
								src="<?php echo esc_url($cc_new_arrivals_first['bg1']); ?>"
								alt="Product Image Background" />
							<img
								src="<?php echo esc_url($cc_new_arrivals_first['bg2']); ?>"
								alt="Product Image Background" />
						</div>
						<div class="product-slider-image-wrapper">
							<img
								src="<?php echo esc_url($cc_new_arrivals_first['productImage']); ?>"
								alt="<?php echo esc_attr($cc_new_arrivals_first['title']); ?>" />
						</div>
					</div>
					<div class="best-saler-section-slider-right">
						<div class="vendor-slider-item">
							<img src="<?php echo esc_url($cc_new_arrivals_first['vendorLogo']); ?>" alt="<?php echo esc_attr($cc_new_arrivals_first['vendorName']); ?>" />
							<p class="vendor-slider-item-name"><?php echo esc_html($cc_new_arrivals_first['vendorName']); ?></p>
						</div>
						<h2 class="product-slider-item-title">
							<?php echo esc_html($cc_new_arrivals_first['title']); ?>
						</h2>
						<div class="product-slider-btn">
							<a class="btn-shop-now" href="<?php echo esc_url($cc_new_arrivals_first['link']); ?>"><?php echo esc_html($cc_new_arrivals_first['btnText']); ?></a>
							<div class="product-vendor-nav-btn-new-arrivals">
								<button
									type="button"
									class="slider-btn slider-prev"
									aria-label="Previous">
									<svg
										width="20"
										height="20"
										viewBox="0 0 24 24"
										fill="none"
										stroke="currentColor"
										stroke-width="2"
										stroke-linecap="round"
										stroke-linejoin="round">
										<use href="#icon-chevron-left" />
									</svg>
								</button>
								<button
									type="button"
									class="slider-btn slider-next"
									aria-label="Next">
									<svg
										width="20"
										height="20"
										viewBox="0 0 24 24"
										fill="none"
										stroke="currentColor"
										stroke-width="2"
										stroke-linecap="round"
										stroke-linejoin="round">
										<use href="#icon-chevron-right" />
									</svg>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="curve">
				<svg viewBox="0 0 1440 80" preserveAspectRatio="none">
					<defs>
						<linearGradient
							id="curveGradient"
							x1="0%"
							y1="0%"
							x2="100%"
							y2="0%">
							<stop offset="0%" stop-color="rgba(100, 233, 204, 0.8)" />
							<stop offset="100%" stop-color="rgba(177, 248, 232, 0.8)" />
						</linearGradient>
					</defs>
					<path
						d="M0,40 C360,70 1080,10 1440,40 L1440,80 L0,80 Z"
						fill="url(#curveGradient)"></path>
					<path
						d="M0,40 C360,70 1080,10 1440,40"
						fill="none"
						stroke="url(#curveGradient)"
						stroke-width="2"></path>
				</svg>
			</div>
		</section>
	<?php endif; ?>

	<!-- Bottom Fill Area -->
	<section class="bottom-fill">
		<div class="bestsellers-section">
			<div class="bestsellers-header">
				<h2 class="bestsellers-title"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_bestsellers_title', 'Bestsellers')); ?></h2>
				<div class="bestsellers-nav">
					<button
						type="button"
						class="bs-slider-btn bs-prev"
						aria-label="Previous">
						<svg
							width="20"
							height="20"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							stroke-linecap="round"
							stroke-linejoin="round">
							<use href="#icon-chevron-left" />
						</svg>
					</button>
					<button
						type="button"
						class="bs-slider-btn bs-next"
						aria-label="Next">
						<svg
							width="20"
							height="20"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							stroke-linecap="round"
							stroke-linejoin="round">
							<use href="#icon-chevron-right" />
						</svg>
					</button>
				</div>
			</div>
			<div class="bestsellers-slider">
				<!-- Cards are populated dynamically by bestsellers-overall-ajax.js -->
				<div class="bs-track">
					<p class="fp-no-results" style="grid-column:1/-1;text-align:center;width:100%;padding:24px;"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_bestsellers_loading_text', 'Loading bestsellers...')); ?></p>
				</div>
			</div>
		</div>
	</section>
	<section
		class="medical-products-banner"
		aria-label="Promotional medical products banner" <?php echo cc_front_bg_style(cc_front_meta($cc_home_id, '_cc_home_bottom_banner_bg', $cc_home_imgs . 'Banner Section.webp')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
																											?>></section>
	<section class="new-collection-section">
		<div class="new-collection-inner">
			<h2 class="new-collection-title">
				<?php echo wp_kses_post(cc_front_meta($cc_home_id, '_cc_home_collection_title', "DON'T MISS OUR <span>COLLECTION</span>")); ?>
			</h2>
			<?php
			$cc_collection_1_link = cc_front_meta($cc_home_id, '_cc_home_collection_1_link', home_url('/shop/'));
			$cc_collection_2_link = cc_front_meta($cc_home_id, '_cc_home_collection_2_link', home_url('/shop/'));
			$cc_collection_1_label = trim(wp_strip_all_tags(cc_front_meta($cc_home_id, '_cc_home_collection_1_title', 'Product Name')));
			$cc_collection_2_label = trim(wp_strip_all_tags(cc_front_meta($cc_home_id, '_cc_home_collection_2_title', 'Product Name')));
			?>
			<div class="new-collection-section-categories">
				<a
					href="<?php echo esc_url($cc_collection_1_link); ?>"
					class="new-collection-section-category-1"
					aria-label="<?php echo esc_attr($cc_collection_1_label ?: __('Collection card 1', 'consucorner')); ?>"
					style="text-decoration:none;">
					<span class="nc-badge nc-badge--orange"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_collection_1_badge', 'Category')); ?></span>
					<h3 class="nc-product-name"><?php echo wp_kses_post(cc_front_meta($cc_home_id, '_cc_home_collection_1_title', 'Product<br />Name')); ?></h3>
					<img
						src="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_collection_1_image', $cc_home_imgs . 'product 3.png')); ?>"
						alt="Product"
						class="nc-product-img" />
				</a>
				<a
					href="<?php echo esc_url($cc_collection_2_link); ?>"
					class="new-collection-section-category-2"
					aria-label="<?php echo esc_attr($cc_collection_2_label ?: __('Collection card 2', 'consucorner')); ?>"
					style="text-decoration:none;">
					<span class="nc-badge nc-badge--green"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_collection_2_badge', 'Category')); ?></span>
					<h3 class="nc-product-name"><?php echo wp_kses_post(cc_front_meta($cc_home_id, '_cc_home_collection_2_title', 'Product<br />Name')); ?></h3>
					<img
						src="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_collection_2_image', $cc_home_imgs . 'product 2.png')); ?>"
						alt="Product"
						class="nc-product-img" />
				</a>
				<div class="new-collection-section-category-3">
					<div class="nc-card3-text">
						<span class="nc-badge nc-badge--light"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_collection_3_badge', 'Category Name')); ?></span>
						<h3 class="nc-product-name-3"><?php echo wp_kses_post(cc_front_meta($cc_home_id, '_cc_home_collection_3_title', 'Product Name')); ?></h3>
						<a href="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_collection_btn_link', home_url('/shop/'))); ?>" class="nc-shop-btn"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_collection_btn_text', 'SHOP NOW →')); ?></a>
					</div>
					<img
						src="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_collection_3_image', $cc_home_imgs . 'product 1.png')); ?>"
						alt="Product"
						class="nc-product-img nc-product-img--hero" />
				</div>
			</div>
		</div>
	</section>

	<!-- Vector Banner -->
	<?php
	$cc_vector_banner_image = cc_front_meta($cc_home_id, '_cc_home_vector_banner_image', $cc_home_imgs . 'Vector (1).png');
	$cc_vector_banner_link  = trim((string) cc_front_meta($cc_home_id, '_cc_home_vector_banner_link', ''));
	?>
	<section class="vector-banner-section">
		<?php if ('' !== $cc_vector_banner_link) : ?>
			<a
				href="<?php echo esc_url($cc_vector_banner_link); ?>"
				class="vector-banner-link"
				aria-label="<?php esc_attr_e('View banner promotion', 'consucorner'); ?>">
				<img
					src="<?php echo esc_url($cc_vector_banner_image); ?>"
					alt="<?php esc_attr_e('Medical Products Banner', 'consucorner'); ?>"
					class="vector-banner-img" />
			</a>
		<?php else : ?>
			<img
				src="<?php echo esc_url($cc_vector_banner_image); ?>"
				alt="<?php esc_attr_e('Medical Products Banner', 'consucorner'); ?>"
				class="vector-banner-img" />
		<?php endif; ?>
	</section>

	<!-- Recommended For You -->
	<section class="recommended-section">
		<div class="recommended-inner">
			<div class="recommended-header">
				<h2 class="recommended-title"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_recommended_title', 'Recommended For You')); ?></h2>
				<div class="recommended-nav">
					<button
						type="button"
						class="rec-slider-btn rec-prev"
						aria-label="Previous">
						<svg
							width="20"
							height="20"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							stroke-linecap="round"
							stroke-linejoin="round">
							<use href="#icon-chevron-left" />
						</svg>
					</button>
					<button
						type="button"
						class="rec-slider-btn rec-next"
						aria-label="Next">
						<svg
							width="20"
							height="20"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							stroke-linecap="round"
							stroke-linejoin="round">
							<use href="#icon-chevron-right" />
						</svg>
					</button>
				</div>
			</div>
			<div class="recommended-slider">
				<!-- Cards are populated dynamically by recommended-for-you-ajax.js -->
				<div class="rec-track">
					<p class="fp-no-results" style="grid-column:1/-1;text-align:center;width:100%;padding:24px;"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_recommended_loading_text', 'Loading recommendations...')); ?></p>
				</div>
			</div>
			<div class="recommended-actions">
				<a href="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_recommended_btn_link', home_url('/shop/'))); ?>" class="btn-all-specialties"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_recommended_btn_text', 'Shop All')); ?></a>
			</div>
		</div>

		<!-- Arch curves DOWN in center -> teal section has convex bottom -->
		<div class="rec-curve">
			<svg viewBox="0 0 1440 80" preserveAspectRatio="none">
				<path
					d="M0,0 C480,80 960,80 1440,0 L1440,80 L0,80 Z"
					fill="#ffffff"></path>
			</svg>
		</div>
	</section>
	<!-- ===== FAST DELIVERY + TESTIMONIALS ===== -->

	<!-- Fast Delivery & Safe Packaging -->
	<section class="fast-delivery-section">
		<div class="fast-delivery-inner">
			<!-- Left: Text -->
			<div class="fast-delivery-text">
				<h2 class="fast-delivery-title">
					<span class="fast-delivery-title--teal"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_fast_title_teal', 'Fast Delivery &')); ?></span><br />
					<?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_fast_title_black', 'Safe Packaging')); ?>
				</h2>
				<p class="fast-delivery-desc">
					<?php echo wp_kses_post(cc_front_meta($cc_home_id, '_cc_home_fast_desc', 'At the heart of our brand lies a passion for redefining how we experience time. As the sculptors of time and innovation, we are dedicated to crafting smart watches that seamlessly blend artistry')); ?>
				</p>
				<a href="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_fast_btn_link', home_url('/shop/'))); ?>" class="fast-delivery-btn"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_fast_btn_text', 'Read More')); ?></a>
			</div>

			<!-- Right: Illustrated visual -->
			<div class="fast-delivery-visual">
				<img
					src="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_fast_shape_1', $cc_home_imgs . 'Rectangle baby blue left.svg')); ?>"
					alt=""
					class="fdv-rect fdv-rect--baby-left" />
				<img
					src="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_fast_shape_2', $cc_home_imgs . 'Rectangle blue location.svg')); ?>"
					alt=""
					class="fdv-rect fdv-rect--blue" />
				<img
					src="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_fast_shape_3', $cc_home_imgs . 'Rectangle baby blue right.svg')); ?>"
					alt=""
					class="fdv-rect fdv-rect--baby-right" />
				<img
					src="<?php echo esc_url(cc_front_meta($cc_home_id, '_cc_home_fast_image', $cc_home_imgs . 'location.png')); ?>"
					alt="Fast Delivery"
					class="fdv-main-img" />
			</div>
		</div>
	</section>

	<!-- Testimonials -->
	<section class="testimonials-section">
		<div class="testimonials-inner">
			<!-- Header row -->
			<div class="testimonials-header">
				<span class="testimonials-label"><?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_testimonials_label', 'Testimonials')); ?></span>
				<div class="testimonials-heading-block">
					<h2 class="testimonials-title">
						<?php echo wp_kses_post(cc_front_meta($cc_home_id, '_cc_home_testimonials_title', 'What Our Customers<br />Say About Us')); ?>
					</h2>
				</div>
				<div class="testimonials-rating-block">
					<div class="testimonials-stars">
						<?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_testimonials_stars', '★★★★★')); ?>
					</div>
					<p class="testimonials-rating-text">
						<?php echo esc_html(cc_front_meta($cc_home_id, '_cc_home_testimonials_rating_text', 'Trusted by Healthcare professional')); ?>
					</p>
				</div>
			</div>

			<!-- Slider -->
			<div class="testimonials-slider-wrapper">
				<div class="testimonials-track">
					<?php
					$cc_home_review_defaults = function_exists('cc_home_testimonial_defaults')
						? cc_home_testimonial_defaults()
						: array();
					for ($n = 1; $n <= 3; $n++) :
						$cc_review_default = isset($cc_home_review_defaults[$n])
							? $cc_home_review_defaults[$n]
							: array(
								'name'   => '',
								'text'   => '',
								'rating' => '★ Rated 4.8/5',
							);
						?>
						<div class="review-card">
							<h4 class="review-name"><?php echo esc_html(cc_front_meta($cc_home_id, "_cc_home_review_{$n}_name", $cc_review_default['name'])); ?></h4>
							<p class="review-text">
								<?php echo wp_kses_post(cc_front_meta($cc_home_id, "_cc_home_review_{$n}_text", $cc_review_default['text'])); ?>
							</p>
							<span class="review-rating"><?php echo esc_html(cc_front_meta($cc_home_id, "_cc_home_review_{$n}_rating", $cc_review_default['rating'])); ?></span>
						</div>
					<?php endfor; ?>
				</div>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
