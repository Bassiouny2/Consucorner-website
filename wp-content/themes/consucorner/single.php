<?php
/**
 * The template for displaying single blog posts.
 *
 * Mirrors the static front-end/single-post.html design while keeping the
 * article body fully editable through the Gutenberg editor.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! function_exists( 'consucorner_single_post_reading_time' ) ) {
	/**
	 * Estimate reading time from post content.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	function consucorner_single_post_reading_time( $post_id ) {
		$content    = get_post_field( 'post_content', $post_id );
		$word_count = str_word_count( wp_strip_all_tags( strip_shortcodes( $content ) ) );
		$minutes    = max( 1, (int) ceil( $word_count / 220 ) );

		return sprintf(
			/* translators: %d: estimated reading minutes. */
			_n( '%d Min. To Read', '%d Min. To Read', $minutes, 'consucorner' ),
			$minutes
		);
	}
}

if ( ! function_exists( 'consucorner_single_post_category_label' ) ) {
	/**
	 * Return the first category name for card badges.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	function consucorner_single_post_category_label( $post_id ) {
		$categories = get_the_category( $post_id );

		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			return $categories[0]->name;
		}

		return __( 'Category', 'consucorner' );
	}
}

if ( ! function_exists( 'consucorner_single_post_image_url' ) ) {
	/**
	 * Resolve a post thumbnail URL with a theme fallback.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $size    Image size.
	 * @return string
	 */
	function consucorner_single_post_image_url( $post_id, $size = 'large' ) {
		$image = get_the_post_thumbnail_url( $post_id, $size );

		if ( $image ) {
			return $image;
		}

		return get_template_directory_uri() . '/assets/images/blog-iol.svg';
	}
}

if ( ! function_exists( 'consucorner_single_post_image' ) ) {
	/**
	 * Render a responsive post image with sensible loading attributes.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $size    WordPress image size.
	 * @param array  $attrs   Image attributes.
	 * @return string
	 */
	function consucorner_single_post_image( $post_id, $size = 'large', $attrs = array() ) {
		$default_attrs = array(
			'alt'      => get_the_title( $post_id ),
			'decoding' => 'async',
		);
		$attrs         = array_merge( $default_attrs, $attrs );
		$thumbnail_id  = get_post_thumbnail_id( $post_id );

		if ( $thumbnail_id ) {
			return wp_get_attachment_image( $thumbnail_id, $size, false, $attrs );
		}

		$image_url = consucorner_single_post_image_url( $post_id, $size );
		$output    = '<img src="' . esc_url( $image_url ) . '"';

		foreach ( $attrs as $name => $value ) {
			if ( '' === $value || false === $value || null === $value ) {
				continue;
			}

			$output .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}

		return $output . '>';
	}
}

if ( ! function_exists( 'consucorner_single_post_faqs' ) ) {
	/**
	 * Return custom FAQ rows for the single blog page.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	function consucorner_single_post_faqs( $post_id ) {
		$faqs = get_post_meta( $post_id, '_cc_blog_faqs', true );

		if ( ! is_array( $faqs ) ) {
			$faqs = array();
		}

		$faqs = array_values(
			array_filter(
				$faqs,
				function ( $faq ) {
					$question = isset( $faq['question'] ) ? trim( (string) $faq['question'] ) : '';
					$answer   = isset( $faq['answer'] ) ? trim( wp_strip_all_tags( (string) $faq['answer'] ) ) : '';

					return '' !== $question || '' !== $answer;
				}
			)
		);

		if ( $faqs ) {
			return $faqs;
		}

		return array(
			array(
				'question' => sprintf(
					/* translators: %s: post title. */
					__( 'What is the main takeaway from %s?', 'consucorner' ),
					get_the_title( $post_id )
				),
				'answer'   => __( 'This guide helps healthcare teams understand the essentials, compare practical considerations, and make more confident purchasing decisions.', 'consucorner' ),
			),
			array(
				'question' => __( 'Can I edit this article content in Gutenberg?', 'consucorner' ),
				'answer'   => __( 'Yes. The article body is rendered directly from the WordPress block editor, so headings, paragraphs, lists, tables, images, and other blocks can be managed from Gutenberg.', 'consucorner' ),
			),
		);
	}
}

if ( ! function_exists( 'consucorner_single_post_card' ) ) {
	/**
	 * Render a compact blog card used in sidebar and read-more sections.
	 *
	 * @param WP_Post $post_obj Post object.
	 * @param string  $variant  Card variant: sidebar|grid.
	 */
	function consucorner_single_post_card( $post_obj, $variant = 'grid' ) {
		$post_id      = $post_obj->ID;
		$category     = consucorner_single_post_category_label( $post_id );
		$reading_time = consucorner_single_post_reading_time( $post_id );
		$image_size   = 'sidebar' === $variant ? 'thumbnail' : 'medium_large';
		$excerpt      = wp_trim_words( get_the_excerpt( $post_id ), 'sidebar' === $variant ? 12 : 24 );

		if ( 'sidebar' === $variant ) :
			?>
			<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="sp-sidebar-card">
				<div class="sp-sidebar-card-img">
					<?php echo consucorner_single_post_image( $post_id, $image_size, array( 'loading' => 'lazy' ) ); ?>
				</div>
				<div class="sp-sidebar-card-body">
					<span class="sp-sidebar-card-badge"><?php echo esc_html( $category ); ?></span>
					<h4 class="sp-sidebar-card-title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h4>
					<div class="sp-sidebar-card-meta">
						<span><?php echo esc_html( get_the_author_meta( 'display_name', $post_obj->post_author ) ); ?></span>
						<span class="sp-sidebar-meta-sep">|</span>
						<span><?php echo esc_html( get_the_date( 'd F Y', $post_id ) ); ?></span>
						<span class="sp-sidebar-meta-sep">|</span>
						<span><?php echo esc_html( $reading_time ); ?></span>
					</div>
					<p class="sp-sidebar-card-desc"><?php echo esc_html( $excerpt ); ?></p>
				</div>
			</a>
			<?php
			return;
		endif;
		?>
		<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="blog-post-card">
			<div class="blog-post-img">
				<?php echo consucorner_single_post_image( $post_id, $image_size, array( 'loading' => 'lazy' ) ); ?>
			</div>
			<div class="blog-post-body">
				<span class="blog-post-badge"><?php echo esc_html( $category ); ?></span>
				<h3 class="blog-post-title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h3>
				<div class="blog-post-meta">
					<span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-tag-cpu"></use></svg> <?php echo esc_html( get_the_date( 'd F Y', $post_id ) ); ?></span>
					<span class="blog-post-meta-sep">|</span>
					<span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-tag-clock"></use></svg> <?php echo esc_html( $reading_time ); ?></span>
				</div>
				<p class="blog-post-desc"><?php echo esc_html( $excerpt ); ?></p>
			</div>
		</a>
		<?php
	}
}
?>

<main id="primary" class="site-main">
	<?php
	while ( have_posts() ) :
		the_post();

		$post_id       = get_the_ID();
		$is_arabic     = function_exists( 'consucorner_is_arabic_post' ) && consucorner_is_arabic_post( $post_id );
		$category      = consucorner_single_post_category_label( $post_id );
		$reading_time  = consucorner_single_post_reading_time( $post_id );
		$faq_items     = consucorner_single_post_faqs( $post_id );
		$current_terms = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'ids' ) );
		$shop_url      = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
		$is_mobile     = wp_is_mobile();

		$recent_posts = null;

		if ( ! $is_mobile ) {
			$recent_posts = new WP_Query(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'posts_per_page'      => 8,
					'post__not_in'        => array( $post_id ),
					'ignore_sticky_posts' => true,
				)
			);
		}

		$read_more_args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'post__not_in'        => array( $post_id ),
			'ignore_sticky_posts' => true,
		);

		if ( ! empty( $current_terms ) && ! is_wp_error( $current_terms ) ) {
			$read_more_args['category__in'] = $current_terms;
		}

		$read_more_posts = new WP_Query( $read_more_args );

		if ( ! $read_more_posts->have_posts() && ! empty( $read_more_args['category__in'] ) ) {
			unset( $read_more_args['category__in'] );
			$read_more_posts = new WP_Query( $read_more_args );
		}
		?>

		<section class="sp-layout-section">
			<div class="sp-layout-inner">
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'sp-post-content' ); ?><?php echo $is_arabic ? ' dir="rtl"' : ''; ?>>
					<div class="sp-post-meta">
						<span class="sp-post-badge"><?php echo esc_html( $category ); ?></span>
						<span class="sp-post-meta-item">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-tag-cpu"></use></svg>
							<?php echo esc_html( get_the_date( 'd F Y' ) ); ?>
						</span>
						<span class="sp-post-meta-sep">|</span>
						<span class="sp-post-meta-item">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-tag-clock"></use></svg>
							<?php echo esc_html( $reading_time ); ?>
						</span>
					</div>

					<?php the_title( '<h1 class="sp-post-title">', '</h1>' ); ?>

					<?php if ( has_excerpt() ) : ?>
						<p class="sp-post-lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
					<?php endif; ?>

					<div class="sp-gutenberg-content">
						<?php
						the_content();

						wp_link_pages(
							array(
								'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'consucorner' ),
								'after'  => '</div>',
							)
						);
						?>
					</div>
				</article>

				<div class="sp-right-col">
					<div class="sp-sidebar-img-col">
						<?php
						echo consucorner_single_post_image(
							$post_id,
							'large',
							array(
								'class'         => 'sp-featured-image',
								'loading'       => 'eager',
								'fetchpriority' => 'high',
								'sizes'         => '(max-width: 1024px) calc(100vw - 28px), 530px',
							)
						);
						?>
					</div>

					<?php if ( ! $is_mobile ) : ?>
						<aside class="sp-post-sidebar">
						<?php if ( $recent_posts instanceof WP_Query && $recent_posts->have_posts() ) : ?>
							<div class="sp-sidebar-recent">
								<h3 class="sp-sidebar-recent-heading">
									<span class="sp-sidebar-recent-tag"><?php esc_html_e( 'Recently', 'consucorner' ); ?></span> <?php esc_html_e( 'Posted', 'consucorner' ); ?>
								</h3>
								<div class="sp-sidebar-recent-list">
									<?php
									while ( $recent_posts->have_posts() ) :
										$recent_posts->the_post();
										consucorner_single_post_card( get_post(), 'sidebar' );
									endwhile;
									wp_reset_postdata();
									?>
								</div>
							</div>
						<?php endif; ?>

						<div class="hero-banner fp-sidebar-banner sp-sidebar-banner">
							<div class="hero-slide active">
								<div class="banner-overlay">
									<span class="banner-tag">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="#icon-tag-cpu"></use></svg>
										<?php esc_html_e( 'Future is here', 'consucorner' ); ?>
									</span>
									<h2 class="banner-title"><?php esc_html_e( 'Shop Now With', 'consucorner' ); ?><br><?php esc_html_e( 'Premium', 'consucorner' ); ?><br><?php esc_html_e( 'Quality', 'consucorner' ); ?></h2>
									<a href="<?php echo esc_url( $shop_url ); ?>" class="banner-btn">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="#icon-cart"></use></svg>
										<?php esc_html_e( 'Shop Now', 'consucorner' ); ?>
									</a>
									<div class="banner-bottom-info">
										<div class="banner-slider-indicator"><span class="active-slide">01</span><span class="total-slide">/03</span></div>
										<div class="slide-line"></div>
									</div>
								</div>
								<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/product banner.png' ); ?>" alt="<?php esc_attr_e( 'Shop Now With Premium Quality', 'consucorner' ); ?>" class="banner-product-image" loading="lazy" decoding="async">
							</div>

							<div class="hero-slide">
								<div class="banner-overlay">
									<span class="banner-tag">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="#icon-tag-clock"></use></svg>
										<?php esc_html_e( 'Fast Delivery', 'consucorner' ); ?>
									</span>
									<h2 class="banner-title"><?php esc_html_e( 'Explore Global', 'consucorner' ); ?><br><?php esc_html_e( 'Brands at', 'consucorner' ); ?><br><?php esc_html_e( 'Your Doorstep', 'consucorner' ); ?></h2>
									<a href="<?php echo esc_url( $shop_url ); ?>" class="banner-btn">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="#icon-cart"></use></svg>
										<?php esc_html_e( 'Shop Brands', 'consucorner' ); ?>
									</a>
									<div class="banner-bottom-info">
										<div class="banner-slider-indicator"><span class="active-slide">02</span><span class="total-slide">/03</span></div>
										<div class="slide-line"></div>
									</div>
								</div>
								<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/product banner.png' ); ?>" alt="<?php esc_attr_e( 'Fast Delivery', 'consucorner' ); ?>" class="banner-product-image" loading="lazy" decoding="async">
							</div>

							<div class="hero-slide">
								<div class="banner-overlay">
									<span class="banner-tag">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="#icon-tag-shield"></use></svg>
										<?php esc_html_e( 'Secure Payments', 'consucorner' ); ?>
									</span>
									<h2 class="banner-title"><?php esc_html_e( 'Reliable Checkouts', 'consucorner' ); ?><br><?php esc_html_e( 'For Every', 'consucorner' ); ?><br><?php esc_html_e( 'Order', 'consucorner' ); ?></h2>
									<a href="<?php echo esc_url( $shop_url ); ?>" class="banner-btn">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="#icon-cart"></use></svg>
										<?php esc_html_e( 'Order Now', 'consucorner' ); ?>
									</a>
									<div class="banner-bottom-info">
										<div class="banner-slider-indicator"><span class="active-slide">03</span><span class="total-slide">/03</span></div>
										<div class="slide-line"></div>
									</div>
								</div>
								<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/product banner.png' ); ?>" alt="<?php esc_attr_e( 'Secure Checkouts', 'consucorner' ); ?>" class="banner-product-image" loading="lazy" decoding="async">
							</div>
						</div>
						</aside>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<section class="blog-faq-section"<?php echo $is_arabic ? ' dir="rtl"' : ''; ?>>
			<div class="blog-faq-inner">
				<div class="blog-faq-container">
					<?php
					foreach ( $faq_items as $index => $faq_item ) :
						$is_open = 0 === $index;
						$answer  = isset( $faq_item['answer'] ) ? $faq_item['answer'] : '';
						?>
						<div class="blog-faq-item<?php echo $is_open ? esc_attr( ' blog-faq-item--open' ) : ''; ?>">
							<button class="blog-faq-header" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
								<span class="blog-faq-number"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
								<span class="blog-faq-question"><?php echo esc_html( $faq_item['question'] ); ?></span>
								<span class="blog-faq-toggle">
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
										<path d="<?php echo esc_attr( $is_open ? 'M1 1L13 13M13 1L1 13' : 'M7 1V13M1 7H13' ); ?>" stroke="<?php echo esc_attr( $is_open ? 'white' : '#00C8B3' ); ?>" stroke-width="2" stroke-linecap="round"></path>
									</svg>
								</span>
							</button>
							<div class="blog-faq-body">
								<?php echo wp_kses_post( wpautop( $answer ) ); ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<?php if ( $read_more_posts->have_posts() ) : ?>
			<section class="read-more-section">
				<div class="read-more-inner">
					<h2 class="read-more-heading"><?php esc_html_e( 'READ', 'consucorner' ); ?> <span><?php esc_html_e( 'MORE', 'consucorner' ); ?></span></h2>
					<div class="read-more-grid">
						<?php
						while ( $read_more_posts->have_posts() ) :
							$read_more_posts->the_post();
							consucorner_single_post_card( get_post(), 'grid' );
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ! $is_mobile ) : ?>
			<script>
				(function () {
					var slides = document.querySelectorAll('.sp-sidebar-banner .hero-slide');
					if (!slides.length) {
						return;
					}
					var idx = 0;
					window.setInterval(function () {
						slides[idx].classList.remove('active');
						idx = (idx + 1) % slides.length;
						slides[idx].classList.add('active');
					}, 4500);
				})();
			</script>
		<?php endif; ?>
		<?php
	endwhile;
	?>
</main>

<?php
get_footer();
