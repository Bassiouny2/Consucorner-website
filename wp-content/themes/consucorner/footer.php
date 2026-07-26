<?php

/**
 * Theme footer.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

$footer_logo          = consucorner_get_footer_setting( 'footer_logo' );
$footer_tagline       = consucorner_get_footer_setting( 'footer_tagline' );
$footer_description   = consucorner_get_footer_setting( 'footer_description' );
$footer_payment_image = consucorner_get_footer_setting( 'footer_payment_image' );
$footer_payment_alt   = consucorner_get_footer_setting( 'footer_payment_alt' );
$has_social_icons     = consucorner_footer_has_social_icons();
?>
<footer class="site-footer">
	<div class="footer-top-border" aria-hidden="true"></div>

	<div class="footer-main">
		<div class="footer-container">
			<div class="footer-grid">
			<div class="footer-brand-panel">
				<div class="footer-brand">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="footer-logo">
					<img
						src="<?php echo esc_url( $footer_logo ); ?>"
						alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
						width="204"
						height="32"
						loading="lazy" />
				</a>
				<p class="footer-tagline">
					<?php echo wp_kses( nl2br( esc_html( $footer_tagline ) ), array( 'br' => array() ) ); ?>
				</p>
				<div class="footer-description">
					<p><?php echo esc_html( $footer_description ); ?></p>
				</div>
				<?php if ( $has_social_icons ) : ?>
					<div class="footer-brand-social footer-socials">
						<span class="footer-brand-social__label"><?php esc_html_e( 'Follow us', 'consucorner' ); ?></span>
						<?php consucorner_render_footer_social_icons(); ?>
					</div>
				<?php endif; ?>
				</div>
			</div>

			<nav class="footer-nav-row" aria-label="<?php esc_attr_e( 'Footer navigation', 'consucorner' ); ?>">
				<?php foreach ( consucorner_get_footer_nav_columns() as $footer_col ) : ?>
					<?php if ( ! consucorner_footer_menu_has_items( $footer_col['location'] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<div class="footer-col">
						<h4 class="footer-col-heading">
							<?php echo esc_html( consucorner_get_footer_setting( $footer_col['heading_key'] ) ); ?>
						</h4>
						<?php
						wp_nav_menu(
							array(
								'theme_location' => $footer_col['location'],
								'container'      => false,
								'menu_class'     => 'footer-col-links',
								'depth'          => 1,
								'fallback_cb'    => false,
							)
						);
						?>
					</div>
				<?php endforeach; ?>
			</nav>
			</div>
		</div>
	</div>

	<div class="footer-bottom">
		<div class="footer-container footer-bottom-inner">
			<div class="footer-bottom-legal">
				<div class="footer-bottom-links">
					<a href="<?php echo esc_url( consucorner_get_footer_setting( 'footer_terms_url' ) ); ?>">
						<?php echo esc_html( consucorner_get_footer_setting( 'footer_terms_label' ) ); ?>
					</a>
					<a href="<?php echo esc_url( consucorner_get_footer_setting( 'footer_privacy_url' ) ); ?>">
						<?php echo esc_html( consucorner_get_footer_setting( 'footer_privacy_label' ) ); ?>
					</a>
					<a href="<?php echo esc_url( consucorner_get_footer_setting( 'footer_cookies_url' ) ); ?>">
						<?php echo esc_html( consucorner_get_footer_setting( 'footer_cookies_label' ) ); ?>
					</a>
				</div>
				<p class="footer-copyright">
					<?php echo esc_html( consucorner_get_footer_setting( 'footer_copyright' ) ); ?>
				</p>
			</div>

			<?php if ( $footer_payment_image ) : ?>
				<div class="footer-bottom-trust">
					<div class="footer-payments">
						<span class="footer-payments__label"><?php esc_html_e( 'We accept', 'consucorner' ); ?></span>
						<img
							src="<?php echo esc_url( $footer_payment_image ); ?>"
							alt="<?php echo esc_attr( $footer_payment_alt ); ?>"
							class="footer-payment-img"
							width="372"
							height="44"
							loading="lazy" />
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>

</html>
