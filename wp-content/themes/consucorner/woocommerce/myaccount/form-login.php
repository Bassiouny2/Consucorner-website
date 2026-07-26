<?php
/**
 * Custom My Account login/register screen.
 *
 * @package ConsuCorner
 * @version 9.9.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_customer_login_form' );

$registration_enabled = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
?>

<section class="shop-page-head cart-page-head cc-auth-page-head" aria-label="<?php esc_attr_e( 'My Account heading', 'consucorner' ); ?>">
	<div class="shop-page-head-inner">
		<p class="subtitle"><?php esc_html_e( 'Welcome to ConsuCorner', 'consucorner' ); ?></p>
		<h1 class="shop-page-title"><?php esc_html_e( 'My Account', 'consucorner' ); ?></h1>
		<p class="shop-page-breadcrumbs"><?php consucorner_render_breadcrumbs( __( 'Home / Login or Register', 'consucorner' ), function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ) ); ?></p>
	</div>
</section>

<section class="cc-auth-shell" aria-label="<?php esc_attr_e( 'Login and registration', 'consucorner' ); ?>">
	<div class="cc-auth-wrap">
		<aside class="cc-auth-intro" aria-label="<?php esc_attr_e( 'Account benefits', 'consucorner' ); ?>">
			<span class="cc-auth-kicker"><?php esc_html_e( 'Healthcare commerce made easier', 'consucorner' ); ?></span>
			<h2><?php esc_html_e( 'One account for orders, vendors, and invoices.', 'consucorner' ); ?></h2>
			<p><?php esc_html_e( 'Sign in to manage your profile, track medical supply orders, save favorite products, and keep billing details ready for faster checkout.', 'consucorner' ); ?></p>
			<div class="cc-auth-benefits" role="list">
				<div class="cc-auth-benefit" role="listitem">
					<span class="cc-auth-benefit-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h18v13H3z"/><path d="M16 3v4M8 3v4M3 11h18"/></svg>
					</span>
					<span><?php esc_html_e( 'Track orders and purchase history', 'consucorner' ); ?></span>
				</div>
				<div class="cc-auth-benefit" role="listitem">
					<span class="cc-auth-benefit-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.4-7-10a4 4 0 0 1 7-2.7A4 4 0 0 1 19 11c0 5.6-7 10-7 10z"/></svg>
					</span>
					<span><?php esc_html_e( 'Save favorite instruments for later', 'consucorner' ); ?></span>
				</div>
				<div class="cc-auth-benefit" role="listitem">
					<span class="cc-auth-benefit-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l7 3v5c0 5-3.5 9-7 11-3.5-2-7-6-7-11V5l7-3z"/><path d="m9 12 2 2 4-5"/></svg>
					</span>
					<span><?php esc_html_e( 'Secure checkout and account details', 'consucorner' ); ?></span>
				</div>
			</div>
		</aside>

		<div class="cc-auth-panels<?php echo $registration_enabled ? ' cc-auth-panels--unified' : ''; ?>" id="customer_login">
			<article class="cc-auth-card cc-auth-card--unified" data-auth-account-card>
				<?php if ( $registration_enabled ) : ?>
					<div class="cc-auth-switcher" role="tablist" aria-label="<?php esc_attr_e( 'Choose login or registration form', 'consucorner' ); ?>">
						<span class="cc-auth-switcher-indicator" aria-hidden="true"></span>
						<button type="button" class="cc-auth-switcher-btn is-active" id="cc-auth-tab-login" data-account-auth-tab="login" role="tab" aria-selected="true" aria-controls="cc-auth-panel-login">
							<?php esc_html_e( 'Login', 'woocommerce' ); ?>
						</button>
						<button type="button" class="cc-auth-switcher-btn" id="cc-auth-tab-register" data-account-auth-tab="register" role="tab" aria-selected="false" aria-controls="cc-auth-panel-register">
							<?php esc_html_e( 'Create Account', 'woocommerce' ); ?>
						</button>
					</div>
				<?php endif; ?>

				<div class="cc-auth-form-stage">
					<section class="cc-auth-form-panel is-active" id="cc-auth-panel-login" data-account-auth-panel="login" role="tabpanel" aria-labelledby="cc-auth-tab-login">
						<div class="cc-auth-card-head">
							<span class="cc-auth-card-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/></svg>
							</span>
							<div>
								<h2><?php esc_html_e( 'Log in', 'woocommerce' ); ?></h2>
								<p><?php esc_html_e( 'Access your ConsuCorner account.', 'consucorner' ); ?></p>
							</div>
						</div>

						<form class="woocommerce-form woocommerce-form-login login cc-auth-form" method="post" novalidate>
							<?php do_action( 'woocommerce_login_form_start' ); ?>

							<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
								<label for="username"><?php esc_html_e( 'Email, username, or phone number', 'consucorner' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
								<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
							</p>
							<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
								<label for="password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
								<input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
							</p>

							<?php do_action( 'woocommerce_login_form' ); ?>

							<div class="cc-auth-row">
								<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme cc-auth-remember">
									<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
								</label>
								<a class="cc-auth-link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost password?', 'woocommerce' ); ?></a>
							</div>

							<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
							<button type="submit" class="woocommerce-button button woocommerce-form-login__submit cc-auth-submit<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>">
								<?php esc_html_e( 'Log in', 'woocommerce' ); ?>
							</button>

							<?php do_action( 'woocommerce_login_form_end' ); ?>
						</form>
					</section>

					<?php if ( $registration_enabled ) : ?>
						<section class="cc-auth-form-panel" id="cc-auth-panel-register" data-account-auth-panel="register" role="tabpanel" aria-labelledby="cc-auth-tab-register" hidden>
							<div class="cc-auth-card-head">
								<span class="cc-auth-card-icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
								</span>
								<div>
									<h2><?php esc_html_e( 'Create account', 'woocommerce' ); ?></h2>
									<p><?php esc_html_e( 'Join ConsuCorner in seconds.', 'consucorner' ); ?></p>
								</div>
							</div>

							<form method="post" class="woocommerce-form woocommerce-form-register register cc-auth-form" <?php do_action( 'woocommerce_register_form_tag' ); ?>>
								<?php do_action( 'woocommerce_register_form_start' ); ?>

								<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
									<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
										<label for="reg_username"><?php esc_html_e( 'Username', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
										<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
									</p>
								<?php endif; ?>

								<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
									<label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
									<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" required aria-required="true" /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
								</p>

								<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
									<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
										<label for="reg_password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
										<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required aria-required="true" />
									</p>
								<?php else : ?>
									<p class="cc-auth-note"><?php esc_html_e( 'A secure password setup link will be sent to your email address.', 'woocommerce' ); ?></p>
								<?php endif; ?>

								<?php do_action( 'woocommerce_register_form' ); ?>

								<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
								<button type="submit" class="woocommerce-Button woocommerce-button button cc-auth-submit<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?> woocommerce-form-register__submit" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>">
									<?php esc_html_e( 'Create account', 'woocommerce' ); ?>
								</button>

								<?php do_action( 'woocommerce_register_form_end' ); ?>
							</form>
						</section>
					<?php endif; ?>
				</div>
			</article>
		</div>
	</div>
</section>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
