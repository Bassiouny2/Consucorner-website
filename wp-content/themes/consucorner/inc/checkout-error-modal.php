<?php

/**
 * Checkout error modal with WhatsApp support CTA.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

if (! defined('CONSUCORNER_CHECKOUT_ERROR_MODAL_ENABLED')) {
	define('CONSUCORNER_CHECKOUT_ERROR_MODAL_ENABLED', true);
}

/**
 * Whether the checkout error modal is active (false = default WooCommerce inline errors).
 *
 * @return bool
 */
function consucorner_checkout_error_modal_enabled()
{
	return (bool) apply_filters('consucorner_checkout_error_modal_enabled', CONSUCORNER_CHECKOUT_ERROR_MODAL_ENABLED);
}

/**
 * Whether the current request is the Geidea cart redirect flow.
 *
 * @return bool
 */
function consucorner_is_geidea_cart_flow()
{
	return function_exists('is_cart') && is_cart() && isset($_GET['geidea-session']);
}

/**
 * Whether the checkout error modal should load on this page.
 *
 * @return bool
 */
function consucorner_checkout_error_modal_is_active_page()
{
	if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
		return false;
	}

	if (function_exists('is_checkout') && is_checkout()) {
		return true;
	}

	return consucorner_is_geidea_cart_flow();
}

/**
 * Parse verified Geidea session query data for support context.
 *
 * @return array{orderId: string, amount: string, currency: string, validNonce: bool}
 */
function consucorner_get_geidea_session_context()
{
	$context = array(
		'orderId'    => '',
		'amount'     => '',
		'currency'   => '',
		'validNonce' => false,
	);

	if (! isset($_GET['geidea-session'])) {
		return $context;
	}

	if (
		! isset($_GET['geidea_session_nonce'])
		|| ! wp_verify_nonce(sanitize_key(wp_unslash($_GET['geidea_session_nonce'])), 'geidea_session_action')
	) {
		return $context;
	}

	$context['validNonce'] = true;

	$raw  = urldecode(wp_unslash($_GET['geidea-session']));
	$data = json_decode($raw, true);

	if (! is_array($data)) {
		return $context;
	}

	$session = isset($data['session']) && is_array($data['session']) ? $data['session'] : $data;

	if (! empty($session['merchantReferenceId'])) {
		$context['orderId'] = sanitize_text_field((string) $session['merchantReferenceId']);
	} elseif (! empty($data['merchantReferenceId'])) {
		$context['orderId'] = sanitize_text_field((string) $data['merchantReferenceId']);
	}

	if (isset($session['amount'])) {
		$context['amount'] = sanitize_text_field((string) $session['amount']);
	}

	if (! empty($session['currency'])) {
		$context['currency'] = sanitize_text_field((string) $session['currency']);
	}

	return $context;
}

/**
 * WhatsApp number in international format without + (default: 01555458555 → 201555458555).
 *
 * @return string
 */
function consucorner_support_whatsapp_number()
{
	$number = apply_filters('consucorner_support_whatsapp_number', '201555458555');
	$number = preg_replace('/\D+/', '', (string) $number);
	return $number;
}

/**
 * Build a WhatsApp deep link with optional prefilled message.
 *
 * @param string $message Prefill text.
 * @return string
 */
function consucorner_get_support_whatsapp_url($message = '')
{
	$url = 'https://wa.me/' . consucorner_support_whatsapp_number();
	if ('' !== trim((string) $message)) {
		$url = add_query_arg('text', rawurlencode((string) $message), $url);
	}
	return $url;
}

/**
 * Localized config for checkout error modal scripts.
 *
 * @return array<string, mixed>
 */
function consucorner_get_checkout_error_modal_config()
{
	$geidea = consucorner_get_geidea_session_context();

	return array(
		'whatsappBase'   => 'https://wa.me/' . consucorner_support_whatsapp_number(),
		'checkoutUrl'    => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/'),
		'whatsappPrefix' => __('Hi ConsuCorner, I need help with checkout:', 'consucorner'),
		'isGeideaFlow'   => consucorner_is_geidea_cart_flow(),
		'isCheckout'     => function_exists('is_checkout') && is_checkout(),
		'geideaContext'  => array(
			'orderId'  => $geidea['orderId'],
			'amount'   => $geidea['amount'],
			'currency' => $geidea['currency'],
		),
		'flowTtlMs'      => 15 * MINUTE_IN_SECONDS * 1000,
		'strings'        => array(
			'errorTitle'     => __('Something went wrong', 'consucorner'),
			'cancelTitle'    => __('Payment cancelled', 'consucorner'),
			'cancelMessage'  => __('You closed the payment window. Need help completing your order?', 'consucorner'),
			'requiredFields' => __('Please complete all required fields before placing your order.', 'consucorner'),
		),
	);
}

/**
 * Render checkout error modal markup (once per page).
 */
function consucorner_render_checkout_error_modal_markup()
{
	static $rendered = false;

	if ($rendered || ! consucorner_checkout_error_modal_enabled()) {
		return;
	}

	if (! consucorner_checkout_error_modal_is_active_page()) {
		return;
	}

	$rendered = true;
	$error_title = __('Something went wrong', 'consucorner');
?>
	<div id="cc-checkout-error-modal" class="cc-checkout-error-modal" hidden aria-hidden="true">
		<div class="cc-checkout-error-modal__backdrop" data-cc-checkout-error-close></div>
		<div
			class="cc-checkout-error-modal__dialog"
			role="alertdialog"
			aria-modal="true"
			aria-labelledby="cc-checkout-error-modal-title"
			aria-describedby="cc-checkout-error-modal-body">
			<button type="button" class="cc-checkout-error-modal__close" data-cc-checkout-error-close aria-label="<?php esc_attr_e('Close', 'consucorner'); ?>">
				&times;
			</button>
			<h2 id="cc-checkout-error-modal-title" class="cc-checkout-error-modal__title">
				<?php echo esc_html($error_title); ?>
			</h2>
			<div id="cc-checkout-error-modal-body" class="cc-checkout-error-modal__body"></div>
			<div class="cc-checkout-error-modal__actions">
				<a
					href="#"
					class="cc-checkout-error-modal__whatsapp"
					id="cc-checkout-error-whatsapp"
					target="_blank"
					rel="noopener noreferrer">
					<?php esc_html_e('Chat on WhatsApp', 'consucorner'); ?>
				</a>
				<button type="button" class="cc-checkout-error-modal__dismiss" data-cc-checkout-error-close>
					<?php esc_html_e('Close', 'consucorner'); ?>
				</button>
			</div>
		</div>
	</div>
<?php
}

/**
 * Render modal after checkout form.
 */
function consucorner_render_checkout_error_modal_checkout()
{
	if (! function_exists('is_checkout') || ! is_checkout()) {
		return;
	}

	consucorner_render_checkout_error_modal_markup();
}

/**
 * Render modal in footer on Geidea cart flow (HPP runs before footer).
 */
function consucorner_render_checkout_error_modal_cart()
{
	if (! consucorner_is_geidea_cart_flow()) {
		return;
	}

	consucorner_render_checkout_error_modal_markup();
}

/**
 * Enqueue Geidea alert bridge in head (before Geidea plugin scripts).
 */
function consucorner_enqueue_geidea_alert_bridge()
{
	if (! consucorner_checkout_error_modal_enabled() || ! consucorner_checkout_error_modal_is_active_page()) {
		return;
	}

	$theme_uri = get_template_directory_uri();
	$theme_dir = get_template_directory();
	$bridge    = '/assets/js/geidea-alert-bridge.js';

	wp_enqueue_script(
		'consucorner-geidea-alert-bridge',
		$theme_uri . $bridge,
		array(),
		file_exists($theme_dir . $bridge) ? (string) filemtime($theme_dir . $bridge) : _S_VERSION,
		false
	);
}

/**
 * Enqueue checkout error modal assets.
 */
function consucorner_enqueue_checkout_error_modal_assets()
{
	if (! consucorner_checkout_error_modal_enabled() || ! consucorner_checkout_error_modal_is_active_page()) {
		return;
	}

	$theme_uri = get_template_directory_uri();
	$theme_dir = get_template_directory();

	if (consucorner_is_geidea_cart_flow()) {
		wp_enqueue_style(
			'consucorner-checkout-modal',
			$theme_uri . '/assets/css/checkout.css',
			array(),
			file_exists($theme_dir . '/assets/css/checkout.css')
				? (string) filemtime($theme_dir . '/assets/css/checkout.css')
				: _S_VERSION
		);
	}

	$deps = array('jquery');
	if (function_exists('is_checkout') && is_checkout()) {
		$deps[] = 'consucorner-checkout';
	}

	wp_enqueue_script(
		'consucorner-checkout-errors',
		$theme_uri . '/assets/js/checkout-errors.js',
		$deps,
		file_exists($theme_dir . '/assets/js/checkout-errors.js')
			? (string) filemtime($theme_dir . '/assets/js/checkout-errors.js')
			: _S_VERSION,
		true
	);

	wp_localize_script(
		'consucorner-checkout-errors',
		'ccCheckoutErrors',
		consucorner_get_checkout_error_modal_config()
	);
}

add_action('woocommerce_after_checkout_form', 'consucorner_render_checkout_error_modal_checkout', 50);
add_action('wp_footer', 'consucorner_render_checkout_error_modal_cart', 20);
add_action('wp_enqueue_scripts', 'consucorner_enqueue_geidea_alert_bridge', 5);
add_action('wp_enqueue_scripts', 'consucorner_enqueue_checkout_error_modal_assets', 30);
