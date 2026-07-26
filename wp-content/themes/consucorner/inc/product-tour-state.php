<?php

/**
 * Tours v2 — state schema, persistence helpers, enqueue gates.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/** User meta key for logged-in tour state (JSON). */
const CC_TOURS_META_KEY = 'cc_site_tours_v2';

/** Guest idle cookie after global skip (30 days). */
const CC_TOURS_IDLE_COOKIE = 'cc_tours_v2_idle';

/** Guest cookie set when Welcome was dismissed or a path was chosen (30 days). */
const CC_TOURS_WELCOME_SEEN_COOKIE = 'cc_tours_welcome_seen';

/** Login merge flag (client reads on next page). */
const CC_TOURS_LOGIN_MERGE_FLAG = 'cc_tours_merge_on_login';

/**
 * Default v2 state array.
 *
 * @return array<string, mixed>
 */
function consucorner_tours_default_state()
{
	return array(
		'version'          => 2,
		'global_disabled'  => false,
		'welcome_seen'     => false,
		'welcome_path'     => null,
		'phases'           => array(
			'shop'     => 'pending',
			'home'     => 'pending',
			'cart'     => 'pending',
			'account'  => 'pending',
			'wishlist' => 'pending',
		),
		'synced_at'        => gmdate('c'),
	);
}

/**
 * Normalize arbitrary state to the v2 schema.
 *
 * @param array<string, mixed>|null $raw Raw state.
 * @return array<string, mixed>
 */
function consucorner_tours_normalize_state($raw)
{
	$defaults = consucorner_tours_default_state();
	if (! is_array($raw)) {
		return $defaults;
	}

	$out = $defaults;
	$out['version']         = 2;
	$out['global_disabled'] = ! empty($raw['global_disabled']);
	$out['welcome_seen']    = ! empty($raw['welcome_seen']);
	$out['welcome_path']    = isset($raw['welcome_path']) && is_string($raw['welcome_path']) && '' !== $raw['welcome_path']
		? sanitize_key($raw['welcome_path'])
		: null;

	$allowed_phase = array('pending', 'done', 'skipped');
	if (! empty($raw['phases']) && is_array($raw['phases'])) {
		foreach ($defaults['phases'] as $phase => $status) {
			if (isset($raw['phases'][ $phase ]) && in_array($raw['phases'][ $phase ], $allowed_phase, true)) {
				$out['phases'][ $phase ] = $raw['phases'][ $phase ];
			}
		}
	}

	if (! empty($raw['synced_at']) && is_string($raw['synced_at'])) {
		$out['synced_at'] = $raw['synced_at'];
	} else {
		$out['synced_at'] = gmdate('c');
	}

	return $out;
}

/**
 * Read tour state for the current logged-in user.
 *
 * @param int $user_id User ID.
 * @return array<string, mixed>
 */
function consucorner_tours_get_user_state($user_id = 0)
{
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if (! $user_id) {
		return consucorner_tours_default_state();
	}

	$stored = get_user_meta($user_id, CC_TOURS_META_KEY, true);
	if (! is_string($stored) || '' === $stored) {
		return consucorner_tours_default_state();
	}

	$decoded = json_decode($stored, true);
	return consucorner_tours_normalize_state(is_array($decoded) ? $decoded : null);
}

/**
 * Persist tour state for a logged-in user.
 *
 * @param array<string, mixed> $state State.
 * @param int                  $user_id User ID.
 * @return bool
 */
function consucorner_tours_save_user_state(array $state, $user_id = 0)
{
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if (! $user_id) {
		return false;
	}

	$normalized           = consucorner_tours_normalize_state($state);
	$normalized['synced_at'] = gmdate('c');

	return (bool) update_user_meta(
		$user_id,
		CC_TOURS_META_KEY,
		wp_json_encode($normalized)
	);
}

/**
 * Whether tours are globally disabled for the current visitor (server-side hint).
 *
 * @return bool
 */
function consucorner_tours_is_globally_disabled()
{
	if (consucorner_tours_visitor_is_idle()) {
		return true;
	}

	if (is_user_logged_in()) {
		$state = consucorner_tours_get_user_state();
		return ! empty($state['global_disabled']);
	}

	return false;
}

/**
 * Guest idle cookie set after skip-all (JS sets; PHP reads).
 *
 * @return bool
 */
function consucorner_tours_visitor_is_idle()
{
	if (empty($_COOKIE[ CC_TOURS_IDLE_COOKIE ])) {
		return false;
	}
	return '1' === (string) wp_unslash($_COOKIE[ CC_TOURS_IDLE_COOKIE ]);
}

/**
 * Guest cookie: Welcome modal already seen (set by JS).
 *
 * @return bool
 */
function consucorner_tours_guest_welcome_seen()
{
	if (empty($_COOKIE[ CC_TOURS_WELCOME_SEEN_COOKIE ])) {
		return false;
	}
	return '1' === (string) wp_unslash($_COOKIE[ CC_TOURS_WELCOME_SEEN_COOKIE ]);
}

/**
 * Current page tour phase slug, or false.
 *
 * @return string|false
 */
function consucorner_product_tour_phase()
{
	if (function_exists('is_checkout') && is_checkout()) {
		return false;
	}

	if (function_exists('is_front_page') && is_front_page()) {
		return 'home';
	}

	if (function_exists('is_cart') && is_cart()) {
		return 'cart';
	}

	if (is_user_logged_in() && function_exists('is_account_page') && is_account_page()) {
		$endpoint = function_exists('consucorner_get_current_account_endpoint')
			? consucorner_get_current_account_endpoint()
			: '';
		if ('' === $endpoint) {
			return 'account';
		}
	}

	if (
		(function_exists('is_shop') && is_shop()) ||
		(function_exists('is_product_category') && is_product_category()) ||
		(function_exists('is_product_tag') && is_product_tag()) ||
		(function_exists('is_tax') && is_tax('specialty'))
	) {
		return 'shop';
	}

	return false;
}

/**
 * Whether welcome modal should be offered on this request (server gate).
 *
 * @return bool
 */
function consucorner_tours_welcome_pending_server()
{
	if (function_exists('is_checkout') && is_checkout()) {
		return false;
	}

	if (! function_exists('is_front_page') || ! is_front_page()) {
		return false;
	}

	if (consucorner_tours_is_globally_disabled()) {
		return false;
	}

	if (is_user_logged_in()) {
		$state = consucorner_tours_get_user_state();
		return empty($state['welcome_seen']);
	}

	// Guests: show Welcome only until JS sets welcome-seen cookie or skip-all idle cookie.
	if (consucorner_tours_visitor_is_idle()) {
		return false;
	}

	return ! consucorner_tours_guest_welcome_seen();
}

/**
 * Whether any phase is still pending (optionally including current page phase).
 *
 * @param string|false $current_phase Current phase from consucorner_product_tour_phase().
 * @return bool
 */
function consucorner_tours_has_any_pending_phase($current_phase = false)
{
	if (consucorner_tours_is_globally_disabled()) {
		return false;
	}

	if (is_user_logged_in()) {
		$state = consucorner_tours_get_user_state();
		if (! empty($state['global_disabled'])) {
			return false;
		}
		foreach ($state['phases'] as $status) {
			if ('pending' === $status) {
				return true;
			}
		}
		return false;
	}

	// Guests: enqueue Driver when we have a page phase (client filters pending).
	if ($current_phase) {
		return true;
	}

	return consucorner_tours_welcome_pending_server();
}

/**
 * Whether Driver.js tour assets should load.
 *
 * @return bool
 */
function consucorner_should_enqueue_driver_tour_assets()
{
	if (function_exists('is_checkout') && is_checkout()) {
		return false;
	}

	if (consucorner_tours_is_globally_disabled()) {
		return false;
	}

	// Never load Driver on the same request as the Welcome Modal.
	if (consucorner_tours_welcome_pending_server()) {
		return false;
	}

	$phase = consucorner_product_tour_phase();
	if (! $phase) {
		return false;
	}

	if (is_user_logged_in()) {
		$state = consucorner_tours_get_user_state();
		if (! empty($state['global_disabled'])) {
			return false;
		}
		if (isset($state['phases'][ $phase ]) && 'pending' === $state['phases'][ $phase ]) {
			if ('cart' === $phase && function_exists('WC') && WC()->cart) {
				return (int) WC()->cart->get_cart_contents_count() > 0;
			}
			if ('account' === $phase) {
				return is_user_logged_in();
			}
			if ('home' === $phase) {
				$welcome_path = $state['welcome_path'] ?? '';
				if (! in_array($welcome_path, array( 'specialty', 'categories', 'search' ), true)) {
					return false;
				}
			}
			return true;
		}
		return false;
	}

	// Guest: load when page has a phase; JS gates on localStorage pending.
	if ('cart' === $phase && function_exists('WC') && WC()->cart) {
		return (int) WC()->cart->get_cart_contents_count() > 0;
	}

	return (bool) $phase;
}

/**
 * Whether welcome modal assets should load.
 *
 * @return bool
 */
function consucorner_should_enqueue_welcome_assets()
{
	return consucorner_tours_welcome_pending_server();
}

/**
 * Whether current request is specialty taxonomy archive.
 *
 * @return bool
 */
function consucorner_tours_is_specialty_archive()
{
	return function_exists('is_tax') && is_tax('specialty');
}

/**
 * Flag user for guest→server merge on next page load.
 *
 * @param string  $user_login Login.
 * @param WP_User $user User.
 */
function consucorner_tours_on_user_login($user_login, $user)
{
	if (! $user instanceof WP_User) {
		return;
	}
	update_user_meta($user->ID, CC_TOURS_LOGIN_MERGE_FLAG, '1');
}
add_action('wp_login', 'consucorner_tours_on_user_login', 10, 2);

/**
 * Whether login merge flag is set for current user.
 *
 * @return bool
 */
function consucorner_tours_needs_login_merge()
{
	if (! is_user_logged_in()) {
		return false;
	}
	return '1' === (string) get_user_meta(get_current_user_id(), CC_TOURS_LOGIN_MERGE_FLAG, true);
}

/**
 * Clear login merge flag.
 */
function consucorner_tours_clear_login_merge_flag()
{
	if (is_user_logged_in()) {
		delete_user_meta(get_current_user_id(), CC_TOURS_LOGIN_MERGE_FLAG);
	}
}
