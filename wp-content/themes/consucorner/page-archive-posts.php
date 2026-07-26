<?php
/**
 * Blog Archive Page Template
 *
 * Template Name: Blog Archive Page
 *
 * Mirrors front-end/archive-posts.html while keeping page labels/headings
 * editable via custom fields and all article content editable via Gutenberg.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'consucorner_archive_reading_time' ) ) {
	/**
	 * Estimate reading time from post content.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	function consucorner_archive_reading_time( $post_id ) {
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

if ( ! function_exists( 'consucorner_archive_category_label' ) ) {
	/**
	 * Get first category name for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	function consucorner_archive_category_label( $post_id ) {
		$categories = get_the_category( $post_id );

		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			return $categories[0]->name;
		}

		return __( 'Category', 'consucorner' );
	}
}

if ( ! function_exists( 'consucorner_archive_post_image' ) ) {
	/**
	 * Render post thumbnail image with sensible defaults.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $size    Image size.
	 * @param array  $attrs   Extra image attributes.
	 * @return string
	 */
	function consucorner_archive_post_image( $post_id, $size = 'large', $attrs = array() ) {
		$defaults = array(
			'alt'      => get_the_title( $post_id ),
			'decoding' => 'async',
			'loading'  => 'lazy',
		);
		$attrs    = array_merge( $defaults, $attrs );
		$thumb_id = get_post_thumbnail_id( $post_id );

		if ( $thumb_id ) {
			return wp_get_attachment_image( $thumb_id, $size, false, $attrs );
		}

		$fallback = get_template_directory_uri() . '/assets/images/blog-image.webp';
		$output   = '<img src="' . esc_url( $fallback ) . '" width="640" height="640"';

		foreach ( $attrs as $name => $value ) {
			if ( '' === $value || false === $value || null === $value ) {
				continue;
			}
			$output .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}

		return $output . '>';
	}
}

get_header();

while ( have_posts() ) :
	the_post();

	$page_id = get_the_ID();

	$get_meta = static function ( $key, $default = '' ) use ( $page_id ) {
		$value = get_post_meta( $page_id, $key, true );
		return '' !== $value ? $value : $default;
	};

	$featured_label = $get_meta( '_cc_blog_archive_featured_label', __( 'Featured', 'consucorner' ) );
	$featured_title = $get_meta( '_cc_blog_archive_featured_title', __( 'This Month', 'consucorner' ) );
	$recent_label   = $get_meta( '_cc_blog_archive_recent_label', __( 'Recently', 'consucorner' ) );
	$recent_title   = $get_meta( '_cc_blog_archive_recent_title', __( 'Posted', 'consucorner' ) );
	$recent_count   = max( 3, min( 12, absint( $get_meta( '_cc_blog_archive_recent_count', 8 ) ) ) );
	$grid_count     = max( 3, min( 24, absint( $get_meta( '_cc_blog_archive_grid_count', 6 ) ) ) );
	$read_text      = $get_meta( '_cc_blog_archive_read_link_text', __( 'Read Article', 'consucorner' ) );
	$banner_image   = $get_meta( '_cc_blog_archive_banner_image', get_template_directory_uri() . '/assets/images/blog-banner.webp' );
	$banner_position = $get_meta( '_cc_blog_archive_banner_position', '0 -500px' );

	$featured_post_id = absint( $get_meta( '_cc_blog_archive_featured_post_id', 0 ) );

	if ( $featured_post_id <= 0 || 'publish' !== get_post_status( $featured_post_id ) ) {
		$latest_ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		$featured_post_id = ! empty( $latest_ids ) ? (int) $latest_ids[0] : 0;
	}

	$recent_query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $recent_count,
			'post__not_in'        => $featured_post_id ? array( $featured_post_id ) : array(),
			'ignore_sticky_posts' => true,
		)
	);

	$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

	$grid_query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $grid_count,
			'paged'               => $paged,
			'post__not_in'        => $featured_post_id ? array( $featured_post_id ) : array(),
			'ignore_sticky_posts' => true,
		)
	);
	?>

	<main>
		<section class="blog-section">
			<div class="blog-inner">
				<div class="blog-featured">
					<h2 class="blog-heading">
						<span class="blog-heading-tag"><?php echo esc_html( $featured_label ); ?></span>
						<?php echo esc_html( $featured_title ); ?>
					</h2>

					<?php if ( $featured_post_id ) : ?>
						<div class="blog-featured-row">
							<div class="blog-featured-text">
								<span class="blog-category-badge"><?php echo esc_html( consucorner_archive_category_label( $featured_post_id ) ); ?></span>
								<h3 class="blog-featured-title"><?php echo esc_html( get_the_title( $featured_post_id ) ); ?></h3>
								<div class="blog-meta">
									<span>
										<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-tag-cpu" /></svg>
										<?php echo esc_html( get_the_date( 'd F Y', $featured_post_id ) ); ?>
									</span>
									<span class="blog-meta-sep">|</span>
									<span>
										<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-tag-clock" /></svg>
										<?php echo esc_html( consucorner_archive_reading_time( $featured_post_id ) ); ?>
									</span>
								</div>
								<p class="blog-featured-desc"><?php echo esc_html( wp_trim_words( get_the_excerpt( $featured_post_id ), 36 ) ); ?></p>
								<a href="<?php echo esc_url( get_permalink( $featured_post_id ) ); ?>" class="blog-read-link"><?php echo esc_html( $read_text ); ?></a>
							</div>
							<div class="blog-featured-img-col">
								<?php
								echo consucorner_archive_post_image(
									$featured_post_id,
									'large',
									array(
										'loading'       => 'eager',
										'fetchpriority' => 'high',
										'class'         => 'blog-featured-image',
									)
								);
								?>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<div class="blog-divider"></div>

				<div class="blog-recent">
					<h2 class="blog-recent-heading">
						<span class="blog-recent-heading-tag"><?php echo esc_html( $recent_label ); ?></span>
						<?php echo esc_html( $recent_title ); ?>
					</h2>
					<div class="blog-recent-list">
						<?php if ( $recent_query->have_posts() ) : ?>
							<?php while ( $recent_query->have_posts() ) : $recent_query->the_post(); ?>
								<a href="<?php the_permalink(); ?>" class="blog-recent-card">
									<div class="blog-recent-img">
										<?php echo consucorner_archive_post_image( get_the_ID(), 'medium' ); ?>
									</div>
									<div class="blog-recent-body">
										<span class="blog-recent-badge"><?php echo esc_html( consucorner_archive_category_label( get_the_ID() ) ); ?></span>
										<h4 class="blog-recent-title"><?php the_title(); ?></h4>
										<div class="blog-recent-meta">
											<span><?php bloginfo( 'name' ); ?></span>
											<span class="blog-recent-meta-sep">|</span>
											<span><?php echo esc_html( get_the_date( 'd F Y' ) ); ?></span>
											<span class="blog-recent-meta-sep">|</span>
											<span><?php echo esc_html( consucorner_archive_reading_time( get_the_ID() ) ); ?></span>
										</div>
										<p class="blog-recent-desc"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 14 ) ); ?></p>
									</div>
								</a>
							<?php endwhile; ?>
							<?php wp_reset_postdata(); ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</section>

		<?php if ( trim( (string) wp_strip_all_tags( get_the_content() ) ) !== '' ) : ?>
			<section class="blog-posts-section">
				<div class="blog-posts-inner">
					<div class="cc-page-gutenberg-content">
						<?php echo apply_filters( 'the_content', get_the_content() ); ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<section>
			<div
				class="medical-products-banner blog-banner"
				style="background-image:url('<?php echo esc_url( $banner_image ); ?>');background-position:<?php echo esc_attr( $banner_position ); ?>;"
			></div>
		</section>

		<section class="blog-posts-section">
			<div class="blog-posts-inner">
				<?php if ( $grid_query->have_posts() ) : ?>
					<?php while ( $grid_query->have_posts() ) : $grid_query->the_post(); ?>
						<a href="<?php the_permalink(); ?>" class="blog-post-card">
							<div class="blog-post-img">
								<?php echo consucorner_archive_post_image( get_the_ID(), 'large' ); ?>
							</div>
							<div class="blog-post-body">
								<span class="blog-post-badge"><?php echo esc_html( consucorner_archive_category_label( get_the_ID() ) ); ?></span>
								<h3 class="blog-post-title"><?php the_title(); ?></h3>
								<div class="blog-post-meta">
									<span><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-tag-cpu" /></svg> <?php echo esc_html( get_the_date( 'd F Y' ) ); ?></span>
									<span class="blog-post-meta-sep">|</span>
									<span><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#icon-tag-clock" /></svg> <?php echo esc_html( consucorner_archive_reading_time( get_the_ID() ) ); ?></span>
								</div>
								<p class="blog-post-desc"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30 ) ); ?></p>
							</div>
						</a>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php endif; ?>
			</div>

			<?php
			if ( $grid_query->max_num_pages > 1 ) {
				$pagination = paginate_links(
					array(
						'total'   => $grid_query->max_num_pages,
						'current' => $paged,
						'type'    => 'list',
					)
				);

				if ( $pagination ) {
					echo '<nav class="blog-grid-pagination" aria-label="' . esc_attr__( 'Blog posts pagination', 'consucorner' ) . '">' . wp_kses_post( $pagination ) . '</nav>';
				}
			}
			?>
		</section>
	</main>

<?php endwhile; ?>

<?php get_footer(); ?>
