<?php
/**
 * Privacy Policy Page Template
 *
 * Template Name: Privacy Policy Page
 *
 * To update policy content, edit the main WordPress page editor.
 * Legacy meta/data sections are used only when the editor content is empty.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

require get_template_directory() . '/inc/page-content/privacy-policy-data.php';

$pp_head     = isset( $pp_head ) && is_array( $pp_head ) ? $pp_head : array();
$pp_sections = isset( $pp_sections ) && is_array( $pp_sections ) ? $pp_sections : array();
$pp_banners  = isset( $pp_banners ) && is_array( $pp_banners ) ? $pp_banners : array();

$img = get_template_directory_uri() . '/assets/images/';
$editor_content = trim( get_post_field( 'post_content', get_the_ID() ) );

get_header();
?>

<main class="pp-main">

	<!-- ======================================================
	     PAGE HEAD  (reuses shop-page-head shell styles)
	     ====================================================== -->
	<section class="shop-page-head cart-page-head" aria-label="Privacy policy heading">
		<div class="shop-page-head-inner">
			<h1 class="shop-page-title"><?php echo wp_kses_post( $pp_head['title'] ); ?></h1>
			<p class="shop-page-breadcrumbs"><?php consucorner_render_breadcrumbs( $pp_head['breadcrumbs'], get_permalink() ); ?></p>
		</div>
	</section>

	<!-- ======================================================
	     TWO-COLUMN LAYOUT
	     ====================================================== -->
	<section class="pp-layout">
		<div class="pp-wrap">

			<!-- LEFT: policy content -->
			<article class="pp-content">
				<?php if ( '' !== $editor_content ) : ?>
					<div class="pp-editor-content">
						<?php echo apply_filters( 'the_content', $editor_content ); ?>
					</div>
				<?php else : ?>
					<?php foreach ( $pp_sections as $section ) : ?>
						<h2 class="pp-title"><?php echo wp_kses_post( $section['title'] ); ?></h2>
						<div class="pp-para"><?php echo wp_kses_post( $section['content'] ); ?></div>
					<?php endforeach; ?>
				<?php endif; ?>
			</article>

			<!-- RIGHT: sidebar -->
			<div class="pp-sidebar-wrap">

				<!-- Promo banner slider -->
				<div class="pp-banner" aria-label="Promo banner">
					<?php foreach ( $pp_banners as $i => $slide ) :
						$active = ( 0 === $i ) ? ' pp-banner-slide--active' : '';
						$num    = str_pad( $i + 1, 2, '0', STR_PAD_LEFT );
						$total  = '/' . str_pad( count( $pp_banners ), 2, '0', STR_PAD_LEFT );
					?>
						<div class="pp-banner-slide<?php echo esc_attr( $active ); ?>">
							<div class="pp-banner-overlay">
								<span class="pp-banner-tag"><?php echo esc_html( $slide['tag'] ); ?></span>
								<h2 class="pp-banner-title"><?php echo wp_kses_post( $slide['title'] ); ?></h2>
								<a href="<?php echo esc_url( $slide['btn_link'] ); ?>" class="pp-banner-btn">
									<?php echo esc_html( $slide['btn_text'] ); ?>
								</a>
								<div class="pp-banner-foot">
									<div class="pp-banner-indicator">
										<span class="pp-banner-num"><?php echo esc_html( $num ); ?></span>
										<span class="pp-banner-total"><?php echo esc_html( $total ); ?></span>
									</div>
									<div class="pp-banner-line"></div>
								</div>
							</div>
							<img src="<?php echo esc_url( $img . 'product%20banner.png' ); ?>"
								alt="<?php echo esc_attr( $slide['btn_text'] ); ?>"
								class="pp-banner-img">
						</div>
					<?php endforeach; ?>
				</div>

				<!-- Recently Posted (dynamic from WordPress) -->
				<aside class="pp-recent">
					<h3 class="pp-recent-heading">
						<span class="pp-recent-tag">Recently</span> Posted
					</h3>
					<div class="pp-recent-list">
						<?php
						$recent = new WP_Query( array(
							'post_type'      => 'post',
							'posts_per_page' => 4,
							'post_status'    => 'publish',
						) );
						if ( $recent->have_posts() ) :
							while ( $recent->have_posts() ) :
								$recent->the_post();
								$cat  = get_the_category();
								$thumb = has_post_thumbnail()
									? get_the_post_thumbnail_url( null, 'thumbnail' )
									: esc_url( $img . 'demo-product-shop.png' );
						?>
							<a href="<?php the_permalink(); ?>" class="pp-recent-card">
								<div class="pp-recent-card-img">
									<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php the_title_attribute(); ?>">
								</div>
								<div class="pp-recent-card-body">
									<?php if ( $cat ) : ?>
										<span class="pp-recent-card-badge"><?php echo esc_html( $cat[0]->name ); ?></span>
									<?php endif; ?>
									<h4 class="pp-recent-card-title"><?php the_title(); ?></h4>
									<div class="pp-recent-card-meta">
										<span><?php echo esc_html( strtoupper( get_bloginfo( 'name' ) ) ); ?></span>
										<span class="pp-recent-card-sep">|</span>
										<span><?php echo get_the_date( 'd F Y' ); ?></span>
									</div>
									<p class="pp-recent-card-desc"><?php echo wp_trim_words( get_the_excerpt(), 12 ); ?></p>
								</div>
							</a>
						<?php
							endwhile;
							wp_reset_postdata();
						endif;
						?>
					</div>
				</aside>

			</div><!-- /.pp-sidebar-wrap -->

		</div><!-- /.pp-wrap -->
	</section>

</main>

<?php get_footer(); ?>
