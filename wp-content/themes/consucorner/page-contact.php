<?php
/**
 * Contact Page Template
 *
 * Template Name: Contact Page
 *
 * To update page content, edit:
 *   inc/page-content/contact-data.php
 * Or via wp-admin → Pages → Contact → "Page Content — Contact" meta box.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

require get_template_directory() . '/inc/page-content/contact-data.php';

$img = get_template_directory_uri() . '/assets/images/';

get_header();
?>

<main>

	<!-- ======================================================
	     PAGE HEAD
	     ====================================================== -->
	<section class="contact-page-head" aria-label="Contact page heading">
		<div class="contact-page-head-inner">
			<h1 class="contact-page-title"><?php echo wp_kses_post( $contact_head['title'] ); ?></h1>
			<p class="contact-page-breadcrumbs"><?php consucorner_render_breadcrumbs( $contact_head['breadcrumbs'], get_permalink() ); ?></p>
		</div>
	</section>

	<!-- ======================================================
	     GET IN TOUCH
	     ====================================================== -->
	<section class="get-in-touch" aria-label="Get in touch">
		<div class="get-in-touch-card">

			<!-- Form column -->
			<div class="get-in-touch-form-col">
				<h2 class="get-in-touch-title"><?php echo wp_kses_post( $contact_form['title'] ); ?></h2>
				<p class="get-in-touch-desc"><?php echo esc_html( $contact_form['desc'] ); ?></p>
				<div class="get-in-touch-form cc-forminator-form cc-forminator-form--contact">
					<?php
					$cc_contact_form_id = absint( get_option( 'consucorner_forminator_contact_form_id' ) );
					if ( $cc_contact_form_id && shortcode_exists( 'forminator_form' ) ) {
						echo do_shortcode( '[forminator_form id="' . $cc_contact_form_id . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						echo '<p>' . esc_html__( 'Contact form is currently unavailable.', 'consucorner' ) . '</p>';
					}
					?>
				</div>
			</div>

			<!-- Map column -->
			<div class="get-in-touch-map-col">
				<iframe
					class="get-in-touch-map"
					src="<?php echo esc_url( $contact_form['map_src'] ); ?>"
					allowfullscreen
					loading="lazy"
					title="ConsuCorner location map"
				></iframe>
			</div>

			<!-- Contact info -->
			<div class="get-in-touch-info">
				<?php foreach ( $contact_info as $item ) : ?>
					<div class="git-info-item">
						<img class="git-info-icon"
							src="<?php echo esc_url( $img . $item['icon'] ); ?>"
							alt="<?php echo esc_attr( $item['label'] ); ?>"
							aria-hidden="true">
						<div>
							<p class="git-info-label"><?php echo esc_html( $item['label'] ); ?></p>
							<?php if ( $item['href'] ) : ?>
								<a class="git-info-value" href="<?php echo esc_url( $item['href'] ); ?>">
									<?php echo esc_html( $item['value'] ); ?>
								</a>
							<?php else : ?>
								<p class="git-info-value"><?php echo esc_html( $item['value'] ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

		</div>
	</section>

	<!-- Bottom banner -->
	<section class="contact-banner">
		<div class="medical-products-banner"></div>
	</section>

</main>

<?php get_footer(); ?>
