<?php

/**
 * About Page Template
 *
 * Template Name: About Page
 *
 * To update page content, edit:
 *   inc/page-content/about-data.php
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

require get_template_directory() . '/inc/page-content/about-data.php';

$img = get_template_directory_uri() . '/assets/images/';

get_header();
?>

<main>

	<!-- ======================================================
	     PAGE HEAD
	     ====================================================== -->
	<section class="about-page-head" aria-label="About page heading">
		<div class="about-page-head-inner">
			<h1 class="about-page-title"><?php echo wp_kses_post($about_head['title']); ?></h1>
			<p class="about-page-breadcrumbs"><?php consucorner_render_breadcrumbs($about_head['breadcrumbs'], get_permalink()); ?></p>
		</div>
	</section>

	<!-- ======================================================
	     ABOUT US
	     ====================================================== -->
	<section class="about-us-section">
		<div class="about-us-inner">
			<div class="about-us-header">
				<span class="about-us-tag"><?php echo esc_html($about_us['tag']); ?></span>
				<h2 class="about-us-title"><?php echo wp_kses_post($about_us['title']); ?></h2>
			</div>
			<div class="about-us-grid">
				<?php foreach ($about_us['paragraphs'] as $para) : ?>
					<p class="about-us-text"><?php echo esc_html($para); ?></p>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- ======================================================
	     WHAT MAKES US DIFFERENT
	     ====================================================== -->
	<section class="about-diff-section">
		<div class="about-diff-inner">

			<div class="about-diff-header">
				<span class="about-us-tag"><?php echo esc_html($about_diff['tag']); ?></span>
				<h2 class="about-diff-title"><?php echo wp_kses_post($about_diff['title']); ?></h2>
			</div>

			<div class="about-diff-grid">
				<?php foreach ($about_diff['cards'] as $card) : ?>
					<div class="about-diff-card">
						<div class="about-diff-icon">
							<img
								src="<?php echo esc_url($img . $card['icon']); ?>"
								alt="<?php echo esc_attr($card['title']); ?>"
								width="80"
								height="80">
						</div>
						<h3 class="about-diff-card-title"><?php echo esc_html($card['title']); ?></h3>
						<p class="about-diff-card-desc"><?php echo esc_html($card['desc']); ?></p>
					</div>
				<?php endforeach; ?>
			</div>

		</div>
	</section>

	<!-- ======================================================
	     MISSION, VISION & CORE VALUES
	     ====================================================== -->
	<section class="about-mv-section">
		<div class="about-mv-inner">

			<!-- Mission & Vision -->
			<div class="about-mv-row">
				<div class="about-mv-card">
					<h3 class="about-mv-title"><?php echo wp_kses_post($about_mv['mission']['title']); ?></h3>
					<p class="about-mv-text"><?php echo esc_html($about_mv['mission']['text']); ?></p>
				</div>
				<div class="about-mv-card">
					<h3 class="about-mv-title"><?php echo wp_kses_post($about_mv['vision']['title']); ?></h3>
					<p class="about-mv-text"><?php echo esc_html($about_mv['vision']['text']); ?></p>
				</div>
			</div>

			<!-- Core Values -->
			<div class="about-cv-block">
				<div class="about-cv-header">
					<h2 class="about-cv-title"><?php echo wp_kses_post($about_values['title']); ?></h2>
					<p class="about-cv-subtitle"><?php echo esc_html($about_values['subtitle']); ?></p>
				</div>
				<div class="about-cv-grid">
					<?php foreach ($about_values['cards'] as $val) : ?>
						<div class="about-cv-card">
							<div class="about-cv-icon">
								<img
									src="<?php echo esc_url($img . $val['icon']); ?>"
									alt="<?php echo esc_attr($val['title']); ?>">
							</div>
							<h4 class="about-cv-card-title"><?php echo esc_html($val['title']); ?></h4>
							<p class="about-cv-card-desc"><?php echo esc_html($val['desc']); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

		</div>
	</section>

	<!-- ======================================================
	     BOTTOM BANNER
	     ====================================================== -->
	<section>
		<div class="medical-products-banner sp-banner"></div>
	</section>

</main>

<?php get_footer(); ?>