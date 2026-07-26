<?php

/**
 * Checkout Form (override of woocommerce/checkout/form-checkout.php)
 *
 * Renders the ConsuCorner custom checkout design (mirrors front-end/checkout.html)
 * while preserving full WooCommerce compatibility:
 *   - Standard <form name="checkout"> with WC field IDs (#billing_email, #billing_state, …).
 *   - Real <input name="payment_method"> radios kept in DOM so all gateway plugins work.
 *   - All standard WC hooks fired (woocommerce_before_checkout_form, _checkout_billing,
 *     _checkout_shipping, _review_order_before/after_submit, _after_checkout_form).
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

if (! is_ajax()) {
	do_action('woocommerce_before_checkout_form', $checkout);
}

if (! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in()) {
	echo esc_html(
		apply_filters(
			'woocommerce_checkout_must_be_logged_in_message',
			__('You must be logged in to checkout.', 'consucorner')
		)
	);
	return;
}

$cc_default_country = WC()->countries ? WC()->countries->get_base_country() : 'EG';
$cc_states          = WC()->countries ? WC()->countries->get_states($cc_default_country) : array();

$cc_cart  = WC()->cart;
$cc_total = $cc_cart ? $cc_cart->get_total() : '';

$cc_user = wp_get_current_user();
$cc_get  = function ($key, $fallback = '') {
	$val = WC()->checkout()->get_value($key);
	return (null !== $val && '' !== $val) ? $val : $fallback;
};

$cc_is_card_gw = static function ($gateway) {
	$id    = is_object($gateway) && isset($gateway->id) ? (string) $gateway->id : (string) $gateway;
	$title = is_object($gateway) && method_exists($gateway, 'get_title') ? (string) wp_strip_all_tags($gateway->get_title()) : '';
	$haystack = trim($id . ' ' . $title);

	return ! preg_match('/(cod|cash\\s*on\\s*delivery|cash|cheque|bacs|bank\\s*transfer)/i', $haystack);
};

$cc_assets    = get_template_directory_uri() . '/assets/images/';
$cc_paymob    = $cc_assets . 'paymobLogo.png';
$cc_geidea    = $cc_assets . 'geidea_logo.svg';
$cc_wallet_balance = (is_user_logged_in() && function_exists('cc_get_custom_wallet_balance')) ? cc_get_custom_wallet_balance(get_current_user_id()) : 0;
$cc_wallet_enabled = function_exists('cc_checkout_wallet_is_enabled') ? cc_checkout_wallet_is_enabled() : false;
$cc_wallet_credit  = function_exists('cc_get_checkout_wallet_credit_amount') ? cc_get_checkout_wallet_credit_amount($cc_cart) : 0;
$cc_needs_payment  = $cc_cart && $cc_cart->needs_payment();
$cc_shipping_packages = array();
$cc_chosen_shipping_methods = (function_exists('WC') && WC()->session)
	? (array) WC()->session->get('chosen_shipping_methods', array())
	: array();

if ($cc_cart && $cc_cart->needs_shipping() && function_exists('WC') && WC()->shipping()) {
	$cc_shipping_packages = WC()->shipping()->get_packages();

	if (empty($cc_shipping_packages)) {
		$cc_cart->calculate_shipping();
		$cc_shipping_packages = WC()->shipping()->get_packages();
	}
}
?>

<script>
	window.ccCheckoutWallet = <?php echo wp_json_encode(array(
															'ajaxUrl' => admin_url('admin-ajax.php'),
															'nonce'   => wp_create_nonce('cc_checkout_wallet'),
															'action'  => 'cc_toggle_checkout_wallet_credit',
														)); ?>;
</script>

<section class="shop-page-head checkout-page-head" aria-label="Checkout page heading">
	<div class="shop-page-head-inner">
		<h1 class="shop-page-title"><?php esc_html_e('Checkout', 'consucorner'); ?></h1>
		<p class="shop-page-breadcrumbs">
			<?php consucorner_render_breadcrumbs(__('Home / Cart / Checkout', 'consucorner'), function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/')); ?>
		</p>
	</div>
</section>

<section class="checkout-section" aria-label="Checkout">

	<?php
	/*
	 * WC notices placed below the hero, above the grid.
	 * woocommerce_output_all_notices is unhooked from
	 * woocommerce_before_checkout_form in functions.php so this is the
	 * single, correctly positioned output.
	 */
	if (function_exists('woocommerce_output_all_notices')) {
		echo '<div class="checkout-notices-wrap" role="status" aria-live="polite">';
		woocommerce_output_all_notices();
		echo '</div>';
	}
	?>

	<div class="checkout-container">

		<form
			name="checkout"
			method="post"
			class="checkout woocommerce-checkout checkout-form-col"
			action="<?php echo esc_url(wc_get_checkout_url()); ?>"
			enctype="multipart/form-data"
			novalidate>

			<!-- LEFT: Form -->
			<div class="checkout-card">
				<h2 class="checkout-heading"><?php esc_html_e('Secure Checkout', 'consucorner'); ?></h2>

				<div class="checkout-form" id="checkout-form">

					<!-- Email -->
					<div class="co-field">
						<label class="co-label" for="billing_email"><?php esc_html_e('Email', 'consucorner'); ?></label>
						<input
							type="email"
							id="billing_email"
							name="billing_email"
							class="co-input input-text"
							placeholder="ahmed.mohamed@clinic.com"
							required
							autocomplete="email"
							inputmode="email"
							value="<?php echo esc_attr($cc_get('billing_email', $cc_user->user_email)); ?>" />
					</div>

					<!-- Phone (Egypt +20 locked) -->
					<?php
					$cc_phone_stored     = $cc_get('billing_phone');
					$cc_phone_normalized = function_exists('consucorner_normalize_egypt_mobile')
						? consucorner_normalize_egypt_mobile($cc_phone_stored)
						: '';
					$cc_phone_local      = function_exists('consucorner_egypt_mobile_local_digits')
						? consucorner_egypt_mobile_local_digits($cc_phone_stored)
						: '';
					$cc_phone_display    = function_exists('consucorner_format_egypt_mobile_local_display')
						? consucorner_format_egypt_mobile_local_display($cc_phone_local)
						: '';
					$cc_phone_strings    = function_exists('consucorner_get_checkout_phone_strings')
						? consucorner_get_checkout_phone_strings()
						: array();
					?>
					<div class="co-field co-field--phone">
						<label class="co-label" for="billing_phone_local"><?php esc_html_e('Phone Number', 'consucorner'); ?></label>
						<div class="co-phone-input" id="co-phone-input-wrap">
							<span class="co-phone-prefix" aria-hidden="true">
								<span class="co-phone-flag" aria-hidden="true">🇪🇬</span>
								<span class="co-phone-code">+20</span>
							</span>
							<input
								type="tel"
								id="billing_phone_local"
								class="co-input co-phone-local input-text"
								placeholder="<?php echo esc_attr($cc_phone_strings['placeholder'] ?? __('10 1234 5678', 'consucorner')); ?>"
								required
								autocomplete="tel-national"
								inputmode="numeric"
								aria-describedby="billing_phone_hint"
								maxlength="14"
								value="<?php echo esc_attr($cc_phone_display); ?>" />
						</div>
						<input
							type="hidden"
							id="cc_billing_phone"
							name="billing_phone"
							value="<?php echo esc_attr($cc_phone_normalized); ?>" />
						<p class="co-field-hint" id="billing_phone_hint">
							<?php echo esc_html($cc_phone_strings['helperText'] ?? __('Egypt mobile only (+20). Used for delivery updates and WhatsApp order confirmations.', 'consucorner')); ?>
						</p>
					</div>

					<!-- Name row -->
					<div class="co-row">
						<div class="co-field">
							<label class="co-label" for="billing_first_name"><?php esc_html_e('First Name', 'consucorner'); ?></label>
							<input
								type="text"
								id="billing_first_name"
								name="billing_first_name"
								class="co-input input-text"
								placeholder="Ahmed"
								required
								autocomplete="given-name"
								value="<?php echo esc_attr($cc_get('billing_first_name', $cc_user->first_name)); ?>" />
						</div>
						<div class="co-field">
							<label class="co-label" for="billing_last_name"><?php esc_html_e('Last Name', 'consucorner'); ?></label>
							<input
								type="text"
								id="billing_last_name"
								name="billing_last_name"
								class="co-input input-text"
								placeholder="Mohamed"
								required
								autocomplete="family-name"
								value="<?php echo esc_attr($cc_get('billing_last_name', $cc_user->last_name)); ?>" />
						</div>
					</div>

					<!-- Shipping Address -->
					<div class="co-field">
						<label class="co-label" for="billing_address_1"><?php esc_html_e('Shipping Address', 'consucorner'); ?></label>
						<input
							type="text"
							id="billing_address_1"
							name="billing_address_1"
							class="co-input input-text"
							placeholder="12 El Tahrir St, Dokki"
							required
							autocomplete="shipping street-address"
							value="<?php echo esc_attr($cc_get('billing_address_1')); ?>" />
					</div>

					<!-- Governorate -->
					<div class="co-field">
						<label class="co-label" for="billing_state"><?php esc_html_e('Governorate', 'consucorner'); ?></label>
						<div class="co-select-wrap">
							<?php if (! empty($cc_states)) :
								$cc_current_state = $cc_get('billing_state');
							?>
								<select
									id="billing_state"
									name="billing_state"
									class="co-input co-select"
									required
									autocomplete="shipping address-level1">
									<option value=""><?php esc_html_e('Select', 'consucorner'); ?></option>
									<?php foreach ($cc_states as $cc_code => $cc_name) : ?>
										<option value="<?php echo esc_attr($cc_code); ?>" <?php selected($cc_current_state, $cc_code); ?>>
											<?php echo esc_html($cc_name); ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<input
									type="text"
									id="billing_state"
									name="billing_state"
									class="co-input co-select input-text"
									placeholder="<?php esc_attr_e('State', 'consucorner'); ?>"
									autocomplete="shipping address-level1"
									value="<?php echo esc_attr($cc_get('billing_state')); ?>" />
							<?php endif; ?>
							<svg class="co-select-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
								<path d="M6 9l6 6 6-6" />
							</svg>
						</div>
					</div>

					<input type="hidden" id="billing_country" name="billing_country" value="<?php echo esc_attr($cc_default_country); ?>" />
					<input type="hidden" name="ship_to_different_address" value="0" />
					<?php if (! empty($cc_shipping_packages)) : ?>
						<?php foreach ($cc_shipping_packages as $cc_package_index => $cc_package) : ?>
							<?php
							$cc_available_rates = isset($cc_package['rates']) && is_array($cc_package['rates']) ? $cc_package['rates'] : array();
							$cc_chosen_method   = isset($cc_chosen_shipping_methods[$cc_package_index]) ? $cc_chosen_shipping_methods[$cc_package_index] : '';

							if (! $cc_chosen_method && ! empty($cc_available_rates)) {
								$cc_first_rate    = reset($cc_available_rates);
								$cc_chosen_method = $cc_first_rate && method_exists($cc_first_rate, 'get_id') ? $cc_first_rate->get_id() : '';
							}
							?>
							<?php if ($cc_chosen_method) : ?>
								<input type="hidden" name="shipping_method[<?php echo esc_attr($cc_package_index); ?>]" value="<?php echo esc_attr($cc_chosen_method); ?>" />
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php
					// WC's own billing/shipping form renderers are unhooked in functions.php
					// (we render fields manually above).  Plugins still receive both actions
					// so they can inject their own custom fields if needed.
					do_action('woocommerce_checkout_billing');
					do_action('woocommerce_checkout_shipping');
					?>

					<?php if ($cc_needs_payment) : ?>
						<!-- Payment Method -->
						<div class="co-field co-payment-field">
							<label class="co-label"><?php esc_html_e('Payment Method', 'consucorner'); ?></label>

							<?php
							$cc_gateways = WC()->payment_gateways()->get_available_payment_gateways();

							if (! empty($cc_gateways)) :
								WC()->payment_gateways()->set_current_gateway($cc_gateways);

								// Currently chosen gateway (used to set initial UI state).
								$cc_chosen = '';
								foreach ($cc_gateways as $cc_g) {
									if ($cc_g->chosen) {
										$cc_chosen = $cc_g->id;
										break;
									}
								}
								if (! $cc_chosen) {
									$cc_first  = reset($cc_gateways);
									$cc_chosen = $cc_first ? $cc_first->id : '';
								}

								/*
								 * Group all available gateways under TWO buttons (matches design):
								 *   1) "Visa / MasterCard / Meeza"  → first card-style gateway
								 *      (anything that's not COD / cheque / BACS — typically
								 *      Paymob, Stripe, PayMob Card, Geidea, etc.)
								 *   2) "Cash on Delivery"            → first COD-style gateway
								 */
								$cc_card_gw = null;
								$cc_cod_gw  = null;
								foreach ($cc_gateways as $cc_g) {
									if ($cc_is_card_gw($cc_g)) {
										if (! $cc_card_gw) {
											$cc_card_gw = $cc_g;
										}
									} else {
										if (! $cc_cod_gw) {
											$cc_cod_gw = $cc_g;
										}
									}
								}

								$cc_posted_payment_method = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
								if ($cc_posted_payment_method && isset($cc_gateways[$cc_posted_payment_method])) {
									$cc_chosen = $cc_posted_payment_method;
								} elseif ($cc_cod_gw) {
									$cc_chosen = $cc_cod_gw->id;
									if (function_exists('WC') && WC()->session) {
										WC()->session->set('chosen_payment_method', $cc_chosen);
									}
								}

								if ($cc_wallet_enabled && $cc_cod_gw) {
									$cc_chosen = $cc_cod_gw->id;
								}

								$cc_current_gw     = isset($cc_gateways[$cc_chosen]) ? $cc_gateways[$cc_chosen] : $cc_chosen;
								$cc_card_selected = $cc_is_card_gw($cc_current_gw);
							?>
								<div class="co-payment-methods" id="co-payment-methods">
									<?php if ($cc_card_gw) : ?>
										<button
											type="button"
											class="co-pay-btn<?php echo $cc_card_selected ? ' co-pay-btn--active' : ''; ?>"
											data-method="<?php echo esc_attr($cc_card_gw->id); ?>"
											data-is-card="1">
											<span class="co-pay-icon">💳</span>
											<?php esc_html_e('Visa / MasterCard / Meeza', 'consucorner'); ?>
										</button>
									<?php endif; ?>
									<?php if ($cc_cod_gw) : ?>
										<button
											type="button"
											class="co-pay-btn<?php echo ! $cc_card_selected ? ' co-pay-btn--active' : ''; ?>"
											data-method="<?php echo esc_attr($cc_cod_gw->id); ?>"
											data-is-card="0">
											<span class="co-pay-icon">🚚</span>
											<?php esc_html_e('Cash on Delivery', 'consucorner'); ?>
										</button>
									<?php endif; ?>
								</div>

								<!-- Card details panel: hidden when COD-style gateway is selected -->
								<div
									class="co-card-details<?php echo $cc_card_selected ? '' : ' co-card-details--hidden'; ?>"
									id="co-card-details">
									<div class="co-paymob-badge">
										<img class="co-paymob-logo" src="<?php echo esc_url($cc_paymob); ?>" alt="Paymob" loading="lazy" decoding="async" />
										<img class="co-paymob-logo co-geidea-logo" src="<?php echo esc_url($cc_geidea); ?>" alt="Geidea" loading="lazy" decoding="async" />
									</div>

									<!--
										Static card detail inputs (visual fidelity with the design).
										These are decorative only — they do NOT submit to WC.  Real card
										data is captured by the gateway plugin on its hosted/iframe page
										(Paymob/Geidea redirect; Stripe iframe inside the bridge below).
									-->
									<div class="co-row co-row--card">
										<div class="co-field co-field--card-num">
											<label class="co-label" for="co-card-number"><?php esc_html_e('Card Number', 'consucorner'); ?></label>
											<input
												type="text"
												id="co-card-number"
												class="co-input"
												placeholder="4111 1111 1111 1111"
												maxlength="19"
												autocomplete="off"
												inputmode="numeric" />
										</div>
										<div class="co-field co-field--expiry">
											<label class="co-label" for="co-expiry"><?php esc_html_e('MM/YY', 'consucorner'); ?></label>
											<input
												type="text"
												id="co-expiry"
												class="co-input"
												placeholder="08/27"
												maxlength="5"
												autocomplete="off"
												inputmode="numeric" />
										</div>
										<div class="co-field co-field--cvv">
											<label class="co-label" for="co-cvv"><?php esc_html_e('CVV', 'consucorner'); ?></label>
											<input
												type="text"
												id="co-cvv"
												class="co-input"
												placeholder="123"
												maxlength="4"
												autocomplete="off"
												inputmode="numeric" />
										</div>
									</div>

								</div>

								<div class="cc-payment-radios" aria-hidden="true">
									<?php foreach ($cc_gateways as $cc_g) :
										$cc_active = ($cc_chosen === $cc_g->id);
									?>
										<input
											id="payment_method_<?php echo esc_attr($cc_g->id); ?>"
											type="radio"
											class="input-radio"
											name="payment_method"
											value="<?php echo esc_attr($cc_g->id); ?>"
											<?php checked($cc_active, true); ?>
											data-order_button_text="<?php echo esc_attr($cc_g->order_button_text); ?>" />
									<?php endforeach; ?>
								</div>

							<?php else : ?>
								<div class="co-payment-methods">
									<div class="woocommerce-info"><?php esc_html_e('No payment method available right now.', 'consucorner'); ?></div>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php do_action('woocommerce_review_order_before_submit'); ?>

					<!-- Submit -->
					<button
						type="submit"
						class="co-submit-btn button alt"
						id="co-submit-btn"
						name="woocommerce_checkout_place_order"
						data-pay-label="<?php echo esc_attr(sprintf(__('Pay %s', 'consucorner'), wp_strip_all_tags($cc_total))); ?>"
						data-cod-label="<?php esc_attr_e('Place Order – Cash on Delivery', 'consucorner'); ?>"
						data-place-label="<?php esc_attr_e('Place Order', 'consucorner'); ?>"
						value="<?php esc_attr_e('Place order', 'consucorner'); ?>">
						<?php
						echo esc_html(
							$cc_needs_payment
								? sprintf(
									/* translators: %s: total price */
									__('Pay %s', 'consucorner'),
									wp_strip_all_tags($cc_total)
								)
								: __('Place Order', 'consucorner')
						);
						?>
					</button>

					<?php do_action('woocommerce_review_order_after_submit'); ?>

					<?php
					// Renders WC's terms checkbox + privacy text (empty if site has no terms set).
					if (function_exists('wc_get_template')) {
						wc_get_template('checkout/terms.php');
					}
					?>

					<?php wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce'); ?>
					<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr(wc_get_checkout_url()); ?>" />
				</div><!-- /.checkout-form -->
			</div><!-- /.checkout-card -->
		</form><!-- /form col -->

		<!-- RIGHT: Order Summary + Reviews -->
		<div class="checkout-summary-col">

			<!-- Order Summary -->
			<div class="co-summary-card">
				<div class="co-summary-card__toolbar">
					<button type="button" class="co-share-cart-btn" data-cc-cart-share>
						<?php esc_html_e('Share cart', 'consucorner'); ?>
					</button>
				</div>
				<div class="co-summary-lines">
					<?php foreach (WC()->cart->get_cart() as $cc_key => $cc_item) :
						$cc_product = $cc_item['data'];
						if (! $cc_product || ! $cc_product->exists()) {
							continue;
						}
						$cc_product_image = $cc_product->get_image(
							'woocommerce_thumbnail',
							array(
								'class'   => 'co-summary-product-img',
								'loading' => 'lazy',
							)
						);
						$cc_bulk_display = function_exists('cc_get_cart_item_bulk_price_display_data')
							? cc_get_cart_item_bulk_price_display_data($cc_item)
							: null;
					?>
						<div class="co-summary-row co-summary-row--product">
							<span class="co-summary-product-media">
								<?php echo wp_kses_post($cc_product_image); ?>
							</span>
							<span class="co-summary-label co-summary-product-label">
								<span class="co-summary-product-name"><?php echo esc_html($cc_product->get_name()); ?></span>
								<?php if ((int) $cc_item['quantity'] > 1) : ?>
									<span class="co-summary-product-qty"><?php echo esc_html('× ' . $cc_item['quantity']); ?></span>
								<?php endif; ?>
								<?php if ($cc_bulk_display && ! empty($cc_bulk_display['note'])) : ?>
									<span class="cc-bulk-unit-note"><?php echo esc_html($cc_bulk_display['note']); ?></span>
								<?php endif; ?>
							</span>
							<span class="co-summary-value co-summary-product-price"><?php echo wp_kses_post(wc_price($cc_product->get_price() * $cc_item['quantity'])); ?></span>
						</div>
					<?php endforeach; ?>

					<?php if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) : ?>
						<div class="co-summary-row">
							<span class="co-summary-label"><?php esc_html_e('Shipping', 'consucorner'); ?></span>
							<span class="co-summary-value"><?php echo wp_kses_post(wc_price(WC()->cart->get_shipping_total())); ?></span>
						</div>
					<?php endif; ?>

					<?php if (wc_tax_enabled() && ! WC()->cart->display_prices_including_tax()) : ?>
						<div class="co-summary-row">
							<span class="co-summary-label"><?php esc_html_e('VAT', 'consucorner'); ?></span>
							<span class="co-summary-value"><?php echo wp_kses_post(wc_price(WC()->cart->get_total_tax())); ?></span>
						</div>
					<?php endif; ?>

					<?php if (function_exists('wc_coupons_enabled') && wc_coupons_enabled()) :
						$cc_applied_codes = WC()->cart->get_applied_coupons();
						$cc_has_coupon    = ! empty($cc_applied_codes);
						$cc_coupon_label  = $cc_has_coupon ? strtoupper($cc_applied_codes[0]) : '';
						$cc_discount_val  = (float) WC()->cart->get_discount_total();
					?>
						<div class="co-coupon-card<?php echo $cc_has_coupon ? ' co-coupon-card--applied' : ''; ?>">
							<?php if (! $cc_has_coupon) : ?>
								<form class="checkout_coupon co-coupon-form" method="post" action="<?php echo esc_url(wc_get_checkout_url()); ?>" aria-label="<?php esc_attr_e('Apply coupon', 'consucorner'); ?>">
									<div class="co-coupon-row">
										<label class="screen-reader-text" for="coupon_code"><?php esc_html_e('Coupon code', 'consucorner'); ?></label>
										<input
											type="text"
											name="coupon_code"
											id="coupon_code"
											class="co-coupon-input"
											placeholder="<?php esc_attr_e('Enter coupon code', 'consucorner'); ?>"
											autocomplete="off"
											spellcheck="false"
											maxlength="32" />
										<button type="submit" class="co-coupon-apply" name="apply_coupon" value="<?php esc_attr_e('Apply coupon', 'consucorner'); ?>">
											<?php esc_html_e('Apply', 'consucorner'); ?>
										</button>
									</div>
								</form>
							<?php else : ?>
								<div class="co-coupon-chip" role="status" aria-label="<?php echo esc_attr(sprintf(__('Coupon %s applied', 'consucorner'), $cc_coupon_label)); ?>">
									<span class="co-coupon-chip__icon" aria-hidden="true">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
											<polyline points="20 6 9 17 4 12" />
										</svg>
									</span>
									<span class="co-coupon-chip__code"><?php echo esc_html($cc_coupon_label); ?></span>
									<span class="co-coupon-chip__badge"><?php esc_html_e('Applied', 'consucorner'); ?></span>
									<span class="co-coupon-chip__savings">-<?php echo wp_kses_post(wc_price($cc_discount_val)); ?></span>
									<a
										href="<?php echo esc_url(wp_nonce_url(add_query_arg('remove_coupon', rawurlencode($cc_applied_codes[0]), wc_get_checkout_url()), 'woocommerce-remove-coupon')); ?>"
										class="co-coupon-chip__remove"
										aria-label="<?php esc_attr_e('Remove coupon', 'consucorner'); ?>">
										<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
											<line x1="18" y1="6" x2="6" y2="18" />
											<line x1="6" y1="6" x2="18" y2="18" />
										</svg>
									</a>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if (is_user_logged_in() && $cc_wallet_balance > 0) : ?>
						<div class="co-wallet-card<?php echo $cc_wallet_enabled ? ' co-wallet-card--active' : ''; ?>">
							<label class="co-wallet-toggle">
								<input
									type="checkbox"
									class="co-wallet-toggle__input"
									<?php checked($cc_wallet_enabled, true); ?>>
								<span class="co-wallet-toggle__content">
									<strong><?php esc_html_e('Use wallet balance', 'consucorner'); ?></strong>
									<small>
										<?php
										printf(
											/* translators: 1: wallet balance */
											esc_html__('Available: %s. We will use the wallet first, then any remaining total can be paid by Cash on Delivery.', 'consucorner'),
											wp_strip_all_tags(wc_price($cc_wallet_balance))
										);
										?>
									</small>
								</span>
							</label>
						</div>
					<?php elseif (! is_user_logged_in()) : ?>
						<div class="co-wallet-card co-wallet-card--muted">
							<strong><?php esc_html_e('Wallet credit', 'consucorner'); ?></strong>
							<small><?php esc_html_e('Log in to use wallet balance on this order.', 'consucorner'); ?></small>
						</div>
					<?php endif; ?>

					<?php if (WC()->cart->get_discount_total() > 0) : ?>
						<div class="co-summary-row co-summary-row--discount">
							<span class="co-summary-label"><?php esc_html_e('Discount', 'consucorner'); ?></span>
							<span class="co-summary-value">-<?php echo wp_kses_post(wc_price(WC()->cart->get_discount_total())); ?></span>
						</div>
					<?php endif; ?>

					<?php foreach (WC()->cart->get_fees() as $cc_fee) : ?>
						<div class="co-summary-row co-summary-row--wallet">
							<span class="co-summary-label"><?php echo esc_html($cc_fee->name); ?></span>
							<span class="co-summary-value"><?php echo wp_kses_post(wc_price($cc_fee->amount)); ?></span>
						</div>
					<?php endforeach; ?>

					<div class="co-summary-row co-summary-row--total">
						<span class="co-summary-label"><?php esc_html_e('Total', 'consucorner'); ?></span>
						<span class="co-summary-value"><?php echo wp_kses_post($cc_total); ?></span>
					</div>
				</div>

				<p class="co-secure-note">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
						<path d="M7 11V7a5 5 0 0 1 10 0v4" />
					</svg>
					<?php esc_html_e('Secure checkout · Paymob supported', 'consucorner'); ?>
				</p>
			</div>

			<!-- Reviews -->
			<div class="co-reviews">
				<h3 class="co-reviews-title">
					<?php esc_html_e('What Our Customers', 'consucorner'); ?><br />
					<?php esc_html_e('Say About this', 'consucorner'); ?>
				</h3>
				<div class="co-reviews-rating">
					<div class="co-stars" aria-label="5 out of 5 stars">
						<?php for ($cc_i = 0; $cc_i < 5; $cc_i++) : ?>
							<svg class="co-star co-star--filled" viewBox="0 0 24 24" aria-hidden="true">
								<polygon points="12,2 15,9 22,10 17,15 18,22 12,18 6,22 7,15 2,10 9,9" fill="currentColor" />
							</svg>
						<?php endfor; ?>
					</div>
					<span class="co-rating-score">5.0</span>
				</div>
				<p class="co-reviews-sub"><?php esc_html_e('Trusted by Healthcare professionals', 'consucorner'); ?></p>

				<?php
				$cc_reviews = array(
					array(
						'name'  => 'DR. Khalid Elbeltagui',
						'text'  => 'اول مره اري موقع مصري مشرف ومحترم متخصص في هذا المجال. الاسعار مقبوله. طريقة عرض الآلات سلسه وواضحة الخصائص. اتمني أن ينجح هذا الموقع لانه فعلا رائد في هذا المجال. شكرا لاصحاب هذا الموقع',
						'rated' => '4.9/5',
					),
					array(
						'name'  => 'DR. Shady Abd Elsalam',
						'text'  => 'تجربة الشراء كانت مريحة وسريعة. قدرت أوصل للمنتج اللي محتاجه بسهولة. الأسعار معقولة والخدمة محترمة',
						'rated' => '5.0/5',
					),
					array(
						'name'  => 'DR. Salah Helmy',
						'text'  => 'أكتر حاجة عجبتني البساطة. الموقع مرتب والمعلومات واضحة. وكمان الأسعار معقولة جدا',
						'rated' => '4.8/5',
					),
				);
				?>
				<div class="co-reviews-list">
					<?php foreach ($cc_reviews as $cc_r) : ?>
						<div class="co-review-card">
							<h4 class="co-review-name"><?php echo esc_html($cc_r['name']); ?></h4>
							<p class="co-review-text"><?php echo esc_html($cc_r['text']); ?></p>
							<div class="co-review-footer">
								<svg class="co-star co-star--gold-sm" viewBox="0 0 24 24" aria-hidden="true">
									<polygon points="12,2 15,9 22,10 17,15 18,22 12,18 6,22 7,15 2,10 9,9" fill="currentColor" />
								</svg>
								<span class="co-review-rated"><?php echo esc_html(sprintf(__('Rated %s', 'consucorner'), $cc_r['rated'])); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

		</div><!-- /summary col -->

	</div><!-- /.checkout-container -->
</section>

<?php do_action('woocommerce_after_checkout_form', $checkout); ?>