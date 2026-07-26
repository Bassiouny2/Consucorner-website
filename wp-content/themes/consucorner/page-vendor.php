<?php
/**
 * Vendor Page Template
 *
 * Template Name: Vendor Page
 *
 * To update page content, edit:
 *   inc/page-content/vendor-data.php
 * Or via wp-admin → Pages → Vendor → "Page Content — Vendor" meta box.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

$vendor_hero     = array();
$vendor_why      = array();
$vendor_brochure = array();
$vendor_collab   = array();
$vendor_faq      = array();

require get_template_directory() . '/inc/page-content/vendor-data.php';

$img = get_template_directory_uri() . '/assets/images/';

get_header();
?>

<main>

	<!-- ======================================================
	     HERO
	     ====================================================== -->
	<section class="vendor-hero">
		<div class="vendor-hero-inner">

			<!-- Left: content -->
			<div class="vendor-hero-content">
				<h1 class="vendor-hero-title"><?php echo wp_kses_post( $vendor_hero['title'] ); ?></h1>
				<p class="vendor-hero-subtitle"><?php echo esc_html( $vendor_hero['subtitle'] ); ?></p>
				<p class="vendor-hero-desc"><?php echo esc_html( $vendor_hero['desc'] ); ?></p>
				<img class="vendor-color-container"
					src="<?php echo esc_url( $img . 'color-container.png' ); ?>"
					alt="Vendor highlights">
			</div>

			<!-- Right: registration form card -->
			<div class="vendor-form-card">
				<h2 class="vendor-form-title"><?php echo wp_kses_post( $vendor_hero['form_title'] ); ?></h2>
				<div class="vendor-form cc-forminator-form cc-forminator-form--vendor">
					<?php
					$cc_vendor_form_id = absint( get_option( 'consucorner_forminator_vendor_form_id' ) );
					if ( $cc_vendor_form_id && shortcode_exists( 'forminator_form' ) ) {
						echo do_shortcode( '[forminator_form id="' . $cc_vendor_form_id . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						echo '<p>' . esc_html__( 'Vendor application form is currently unavailable.', 'consucorner' ) . '</p>';
					}
					?>
				</div>
			</div>

		</div>
	</section>

	<div class="footer-top-border"></div>

	<!-- ======================================================
	     WHY JOIN
	     ====================================================== -->
	<section class="vendor-why">
		<div class="vendor-why-inner">
			<div class="vendor-why-header">
				<p class="vendor-why-tag"><?php echo esc_html( $vendor_why['tag'] ); ?></p>
				<h2 class="vendor-why-title"><?php echo wp_kses_post( $vendor_why['title'] ); ?></h2>
				<p class="vendor-why-desc"><?php echo esc_html( $vendor_why['desc'] ); ?></p>
			</div>
			<div class="vendor-why-grid">
				<?php foreach ( $vendor_why['items'] as $item ) : ?>
					<div class="vendor-why-item">
						<div class="vendor-why-icon">
							<img src="<?php echo esc_url( $img . $item['icon'] ); ?>"
								alt="<?php echo esc_attr( $item['title'] ); ?>">
						</div>
						<div class="vendor-why-text">
							<h3 class="vendor-why-item-title"><?php echo esc_html( $item['title'] ); ?></h3>
							<p class="vendor-why-item-desc"><?php echo esc_html( $item['desc'] ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- ======================================================
	     PARTNERS BROCHURE
	     ====================================================== -->
	<section class="vendor-brochure">
		<div class="vendor-brochure-inner">
			<div class="vendor-brochure-content">
				<h2 class="vendor-brochure-title"><?php echo esc_html( $vendor_brochure['title'] ); ?></h2>
				<a href="<?php echo esc_url( $vendor_brochure['btn_link'] ); ?>" class="vendor-brochure-btn">
					<?php echo esc_html( $vendor_brochure['btn_text'] ); ?>
				</a>
			</div>
			<div class="vendor-brochure-img-wrap">
				<img src="<?php echo esc_url( $img . 'partners-brochure.png' ); ?>" alt="Partners brochure">
			</div>
		</div>
	</section>

	<!-- ======================================================
	     HOW WE COLLABORATE
	     ====================================================== -->
	<section class="vendor-collab">
		<div class="vendor-collab-inner">
			<div class="vendor-collab-header">
				<p class="vendor-collab-tag"><?php echo esc_html( $vendor_collab['tag'] ); ?></p>
				<h2 class="vendor-collab-title"><?php echo wp_kses_post( $vendor_collab['title'] ); ?></h2>
				<p class="vendor-collab-desc"><?php echo esc_html( $vendor_collab['desc'] ); ?></p>
			</div>
			<div class="vendor-collab-timeline">
				<div class="vendor-collab-line"></div>
				<div class="vendor-collab-steps">
					<?php foreach ( $vendor_collab['steps'] as $step ) : ?>
						<div class="vendor-collab-step">
							<div class="vendor-collab-hex"><?php echo esc_html( $step['num'] ); ?></div>
							<h3 class="vendor-collab-step-title"><?php echo esc_html( $step['title'] ); ?></h3>
							<p class="vendor-collab-step-desc"><?php echo esc_html( $step['desc'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>

	<!-- ======================================================
	     FAQ
	     ====================================================== -->
	<section class="vendor-faq-section">
		<div class="vendor-faq-inner">
			<h2 class="vendor-faq-title"><?php echo wp_kses_post( $vendor_faq['title'] ); ?></h2>
			<div class="vendor-faq-container">
				<?php foreach ( $vendor_faq['items'] as $i => $faq ) :
					$num    = str_pad( $i + 1, 2, '0', STR_PAD_LEFT );
					$is_open = ( 0 === $i );
				?>
					<div class="vendor-faq-item<?php echo $is_open ? esc_attr( ' vendor-faq-item--open' ) : ''; ?>">
						<button class="vendor-faq-header" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
							<span class="vendor-faq-number"><?php echo esc_html( $num ); ?></span>
							<span class="vendor-faq-question"><?php echo esc_html( $faq['q'] ); ?></span>
							<span class="vendor-faq-toggle">
								<?php if ( $is_open ) : ?>
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
										<path d="M1 1L13 13M13 1L1 13" stroke="white" stroke-width="2" stroke-linecap="round"/>
									</svg>
								<?php else : ?>
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
										<path d="M7 1V13M1 7H13" stroke="#00C8B3" stroke-width="2" stroke-linecap="round"/>
									</svg>
								<?php endif; ?>
							</span>
						</button>
						<div class="vendor-faq-body">
							<?php echo wp_kses_post( wpautop( $faq['a'] ) ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

</main>

<?php get_footer(); ?>
