<?php
/**
 * Terms and Conditions Page Template
 *
 * Template Name: Terms and Conditions Page
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="pp-main">
	<section class="shop-page-head cart-page-head" aria-label="<?php esc_attr_e( 'Terms and conditions heading', 'consucorner' ); ?>">
		<div class="shop-page-head-inner">
			<h1 class="shop-page-title"><?php esc_html_e( 'Terms and Conditions', 'consucorner' ); ?></h1>
			<p class="shop-page-breadcrumbs"><?php consucorner_render_breadcrumbs( __( 'Home / Terms and Conditions', 'consucorner' ), get_permalink() ); ?></p>
		</div>
	</section>

	<section class="pp-layout">
		<div class="pp-wrap">
			<article class="pp-content">
				<h2 class="pp-title"><?php esc_html_e( '1. Acceptance of Terms', 'consucorner' ); ?></h2>
				<div class="pp-para">
					<p><?php esc_html_e( 'By using ConsuCorner, creating an account, or placing an order, you agree to these terms and all applicable policies referenced on the website.', 'consucorner' ); ?></p>
				</div>

				<h2 class="pp-title"><?php esc_html_e( '2. Account Registration', 'consucorner' ); ?></h2>
				<div class="pp-para">
					<p><?php esc_html_e( 'You are responsible for keeping your account details accurate and for protecting your login credentials. Please contact us immediately if you believe your account has been accessed without permission.', 'consucorner' ); ?></p>
				</div>

				<h2 class="pp-title"><?php esc_html_e( '3. Orders and Product Information', 'consucorner' ); ?></h2>
				<div class="pp-para">
					<p><?php esc_html_e( 'We aim to keep product information, pricing, and availability accurate. Orders may be reviewed, adjusted, or cancelled if information is incorrect or stock is unavailable.', 'consucorner' ); ?></p>
				</div>

				<h2 class="pp-title"><?php esc_html_e( '4. Payments, Delivery, and Returns', 'consucorner' ); ?></h2>
				<div class="pp-para">
					<p><?php esc_html_e( 'Payment, delivery, return, and exchange terms are handled according to the checkout details and applicable ConsuCorner policies shown at the time of purchase.', 'consucorner' ); ?></p>
				</div>

				<h2 class="pp-title"><?php esc_html_e( '5. Privacy', 'consucorner' ); ?></h2>
				<div class="pp-para">
					<p><?php esc_html_e( 'Your use of the website is also governed by our Privacy Policy, which explains how we collect, use, and protect your information.', 'consucorner' ); ?></p>
				</div>
			</article>
		</div>
	</section>
</main>

<?php
get_footer();
