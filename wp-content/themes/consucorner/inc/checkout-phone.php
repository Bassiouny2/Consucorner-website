<?php

/**
 * Checkout Egypt mobile phone validation and normalization.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/**
 * Valid Egyptian mobile operator prefixes (without leading 0).
 */
function consucorner_egypt_mobile_operator_prefixes()
{
	return array('10', '11', '12', '15');
}

/**
 * Normalize a phone value to E.164 (+201XXXXXXXXX) or empty string.
 *
 * @param string $phone Raw phone input.
 * @return string
 */
function consucorner_normalize_egypt_mobile($phone)
{
	$digits = preg_replace('/\D+/', '', (string) $phone);

	if ('' === $digits) {
		return '';
	}

	if (0 === strpos($digits, '20')) {
		$digits = substr($digits, 2);
	}

	if ('0' === substr($digits, 0, 1)) {
		$digits = substr($digits, 1);
	}

	$digits = substr($digits, 0, 10);

	if (! consucorner_is_valid_egypt_mobile_local_digits($digits)) {
		return '';
	}

	return '+20' . $digits;
}

/**
 * Whether local digits (10 chars, no country code) are a valid Egypt mobile.
 *
 * @param string $local_digits Digits after +20.
 * @return bool
 */
function consucorner_is_valid_egypt_mobile_local_digits($local_digits)
{
	$local_digits = preg_replace('/\D+/', '', (string) $local_digits);

	if (10 !== strlen($local_digits)) {
		return false;
	}

	if ('1' !== substr($local_digits, 0, 1)) {
		return false;
	}

	$prefix = substr($local_digits, 0, 2);

	return in_array($prefix, consucorner_egypt_mobile_operator_prefixes(), true);
}

/**
 * Extract the local 10-digit part for display, best-effort.
 *
 * @param string $phone Stored or raw phone.
 * @return string
 */
function consucorner_egypt_mobile_local_digits($phone)
{
	$normalized = consucorner_normalize_egypt_mobile($phone);

	if ('' !== $normalized) {
		return substr($normalized, 3);
	}

	$digits = preg_replace('/\D+/', '', (string) $phone);

	if (0 === strpos($digits, '20')) {
		$digits = substr($digits, 2);
	}

	if ('0' === substr($digits, 0, 1)) {
		$digits = substr($digits, 1);
	}

	return substr($digits, 0, 10);
}

/**
 * Format local digits for the checkout mask (e.g. 10 1234 5678).
 *
 * @param string $local_digits Local mobile digits.
 * @return string
 */
function consucorner_format_egypt_mobile_local_display($local_digits)
{
	$digits = preg_replace('/\D+/', '', (string) $local_digits);
	$digits = substr($digits, 0, 10);
	$length = strlen($digits);

	if ($length <= 2) {
		return $digits;
	}

	if ($length <= 6) {
		return substr($digits, 0, 2) . ' ' . substr($digits, 2);
	}

	return substr($digits, 0, 2) . ' ' . substr($digits, 2, 4) . ' ' . substr($digits, 6);
}

/**
 * Sanitize billing phone to normalized E.164 on checkout.
 *
 * @param string $value Submitted phone.
 * @return string
 */
function consucorner_sanitize_checkout_billing_phone($value)
{
	$normalized = consucorner_normalize_egypt_mobile($value);

	return '' !== $normalized ? $normalized : sanitize_text_field((string) $value);
}
add_filter('woocommerce_process_checkout_field_billing_phone', 'consucorner_sanitize_checkout_billing_phone', 20);

/**
 * Validate Egypt mobile on checkout submit.
 */
function consucorner_validate_checkout_egypt_phone()
{
	$raw = isset($_POST['billing_phone']) ? wp_unslash($_POST['billing_phone']) : '';
	$normalized = consucorner_normalize_egypt_mobile($raw);

	if ('' === $normalized) {
		wc_add_notice(
			__('Please enter a valid Egyptian mobile number (e.g. 10 1234 5678). We use it for delivery updates and WhatsApp order confirmations.', 'consucorner'),
			'error'
		);
		return;
	}

	$_POST['billing_phone'] = $normalized;
}
add_action('woocommerce_checkout_process', 'consucorner_validate_checkout_egypt_phone', 10);

/**
 * Prevent Digits plugin from hijacking checkout billing_phone (#billing_phone).
 *
 * Digits merge_billing_field() looks up #billing_phone and injects intl-tel-input
 * with a default +1 country code, which breaks our Egypt-locked field.
 */
function consucorner_disable_digits_checkout_phone_merge()
{
	if (! function_exists('is_checkout') || ! is_checkout()) {
		return;
	}

	remove_action('wp_head', 'digits_wc_merge_hide_field');
	remove_filter('woocommerce_billing_fields', 'digits_wc_merge_remove_billing_phone_field', 100);
}
add_action('wp', 'consucorner_disable_digits_checkout_phone_merge', 20);

/**
 * Checkout CSS: hide any stray Digits country-code UI on our phone field.
 */
function consucorner_checkout_phone_digits_guard_css()
{
	if (! function_exists('is_checkout') || ! is_checkout()) {
		return;
	}
	?>
	<style>
		.page-checkout .co-field--phone .dig_wc_countrycodecontainer,
		.page-checkout .co-field--phone .digcon,
		.page-checkout .co-field--phone #username.mobile_field,
		.page-checkout .co-field--phone .iti,
		.page-checkout .co-field--phone .iti__country-container {
			display: none !important;
		}
	</style>
	<?php
}
add_action('wp_head', 'consucorner_checkout_phone_digits_guard_css', 99);

/**
 * Localized strings for checkout phone UX.
 *
 * @return array<string, string>
 */
function consucorner_get_checkout_phone_strings()
{
	return array(
		'invalidPhone' => __('Please enter a valid Egyptian mobile number (e.g. 10 1234 5678).', 'consucorner'),
		'requiredPhone' => __('Please enter your mobile number.', 'consucorner'),
		'helperText'   => __('Egypt mobile only (+20). Used for delivery updates and WhatsApp order confirmations.', 'consucorner'),
		'placeholder'  => __('10 1234 5678', 'consucorner'),
	);
}
