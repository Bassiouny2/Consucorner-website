<?php
/**
 * Custom My Account dashboard layout.
 *
 * Falls back to default WooCommerce endpoint output for non-dashboard routes.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	if ( function_exists( 'consucorner_get_guest_order_track_modal_markup' ) ) {
		echo consucorner_get_guest_order_track_modal_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	do_action( 'woocommerce_account_navigation' );
	?>
	<div class="woocommerce-MyAccount-content">
		<?php do_action( 'woocommerce_account_content' ); ?>
	</div>
	<?php
	return;
}

$cc_current_account_endpoint = function_exists( 'consucorner_get_current_account_endpoint' ) ? consucorner_get_current_account_endpoint() : '';

if ( $cc_current_account_endpoint ) {
	$my_account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );

	do_action( 'woocommerce_before_account_navigation' );
	do_action( 'woocommerce_after_account_navigation' );
	do_action( 'woocommerce_before_account_content' );
	?>
	<section class="profile-section cc-account-endpoint-section">
		<div class="profile-wrap">
			<div class="profile-card cc-account-endpoint-card">
				<div class="cc-account-endpoint-layout">
					<div class="woocommerce-MyAccount-content cc-account-endpoint-content">
						<div class="cc-account-endpoint-toolbar">
							<a class="cc-account-dashboard-link" href="<?php echo esc_url( $my_account_url ); ?>">
								<?php esc_html_e( 'Back to My Account Dashboard', 'consucorner' ); ?>
							</a>
						</div>

						<?php
						echo function_exists( 'consucorner_render_account_endpoint_content' )
							? consucorner_render_account_endpoint_content( $cc_current_account_endpoint ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							: '';
						?>

						<?php if ( in_array( $cc_current_account_endpoint, array( 'rdfw-referral', 'wt-smart-coupon', 'coupons', 'coupon' ), true ) ) : ?>
						<div class="cc-coupon-section" aria-label="<?php esc_attr_e( 'Apply coupon', 'consucorner' ); ?>">
							<h4 class="cc-coupon-title"><?php esc_html_e( 'Apply a Coupon', 'consucorner' ); ?></h4>
							<p class="cc-coupon-desc"><?php esc_html_e( 'Have a referral coupon or promotional code? Enter it below to add a discount to your next order.', 'consucorner' ); ?></p>
							<div class="cc-coupon-row">
								<input
									type="text"
									class="cc-coupon-input"
									placeholder="<?php esc_attr_e( 'Enter coupon code…', 'consucorner' ); ?>"
									aria-label="<?php esc_attr_e( 'Coupon code', 'consucorner' ); ?>"
									autocomplete="off"
									spellcheck="false"
								/>
								<button type="button" class="cc-coupon-apply-btn">
									<?php esc_html_e( 'Apply', 'consucorner' ); ?>
								</button>
							</div>
							<div class="cc-coupon-feedback" role="status" aria-live="polite"></div>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</section>
	<?php
	do_action( 'woocommerce_after_account_content' );
	return;
}

$profile_markup = function_exists( 'consucorner_get_profile_template_partial' ) ? consucorner_get_profile_template_partial() : '';
if ( '' === $profile_markup ) {
	do_action( 'woocommerce_account_navigation' );
	?>
	<div class="woocommerce-MyAccount-content">
		<?php do_action( 'woocommerce_account_content' ); ?>
	</div>
	<?php
	return;
}
?>

<?php do_action( 'woocommerce_before_account_navigation' ); ?>
<?php do_action( 'woocommerce_after_account_navigation' ); ?>
<?php do_action( 'woocommerce_before_account_content' ); ?>
<?php do_action( 'consucorner_before_account_dashboard' ); ?>

<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden">
	<symbol id="pi-user" viewBox="0 0 24 24">
		<circle cx="12" cy="8" r="4"/>
		<path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
	</symbol>
	<symbol id="pi-wallet" viewBox="0 0 24 24">
		<rect x="2" y="6" width="20" height="14" rx="2"/>
		<path d="M16 13a1 1 0 1 0 2 0 1 1 0 0 0-2 0z" fill="currentColor"/>
		<path d="M2 10h20"/>
	</symbol>
	<symbol id="pi-download" viewBox="0 0 24 24">
		<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
		<polyline points="7 10 12 15 17 10"/>
		<line x1="12" y1="15" x2="12" y2="3"/>
	</symbol>
	<symbol id="pi-card" viewBox="0 0 24 24">
		<rect x="2" y="5" width="20" height="14" rx="2"/>
		<line x1="2" y1="10" x2="22" y2="10"/>
		<line x1="6" y1="15" x2="10" y2="15"/>
	</symbol>
	<symbol id="pi-clock" viewBox="0 0 24 24">
		<circle cx="12" cy="12" r="9"/>
		<polyline points="12 7 12 12 15.5 14"/>
	</symbol>
	<symbol id="pi-shield" viewBox="0 0 24 24">
		<path d="M12 2l7 3v5c0 5-3.5 9.3-7 11C8.5 19.3 5 15 5 10V5l7-3z"/>
		<polyline points="9 12 11 14 15 10"/>
	</symbol>
	<symbol id="pi-heart" viewBox="0 0 24 24">
		<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0l-1 1-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.6z"/>
	</symbol>
	<symbol id="pi-bell" viewBox="0 0 24 24">
		<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
		<path d="M13.7 21a2 2 0 0 1-3.4 0"/>
	</symbol>
	<symbol id="pi-lock" viewBox="0 0 24 24">
		<rect x="3" y="11" width="18" height="11" rx="2"/>
		<path d="M7 11V7a5 5 0 0 1 10 0v4"/>
	</symbol>
	<symbol id="pi-flag" viewBox="0 0 24 24">
		<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/>
		<line x1="4" y1="22" x2="4" y2="15"/>
	</symbol>
	<symbol id="pi-logout" viewBox="0 0 24 24">
		<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
		<polyline points="16 17 21 12 16 7"/>
		<line x1="21" y1="12" x2="9" y2="12"/>
	</symbol>
	<symbol id="pi-camera" viewBox="0 0 24 24">
		<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
		<circle cx="12" cy="13" r="4"/>
	</symbol>
</svg>

<main>
	<?php echo $profile_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php if ( has_action( 'woocommerce_account_dashboard' ) || has_action( 'consucorner_account_dashboard_extensions' ) ) : ?>
		<section class="cc-account-plugin-content" aria-label="<?php esc_attr_e( 'Account extensions', 'consucorner' ); ?>">
			<?php
			/**
			 * Keep the custom dashboard compatible with extensions that add
			 * loyalty points, memberships, subscriptions, or wallet widgets to
			 * the standard WooCommerce account dashboard.
			 */
			do_action( 'woocommerce_account_dashboard' );
			do_action( 'consucorner_account_dashboard_extensions' );
			?>
		</section>
	<?php endif; ?>
</main>

<?php do_action( 'consucorner_after_account_dashboard' ); ?>
<?php do_action( 'woocommerce_after_account_content' ); ?>
