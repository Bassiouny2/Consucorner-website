<?php

/**
 * Tours v2 — REST state sync for logged-in users.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/**
 * Register tour state REST routes.
 */
function consucorner_tours_register_rest_routes()
{
	register_rest_route(
		'cc/v1',
		'/tours/state',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'consucorner_tours_rest_get_state',
				'permission_callback' => 'consucorner_tours_rest_permission',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'consucorner_tours_rest_post_state',
				'permission_callback' => 'consucorner_tours_rest_permission',
			),
		)
	);
}
add_action('rest_api_init', 'consucorner_tours_register_rest_routes');

/**
 * REST permission: logged in + valid REST nonce.
 *
 * @param WP_REST_Request $request Request.
 * @return bool|WP_Error
 */
function consucorner_tours_rest_permission(WP_REST_Request $request)
{
	if (! is_user_logged_in()) {
		return new WP_Error('cc_tours_rest_auth', __('You must be logged in.', 'consucorner'), array('status' => 401));
	}

	$nonce = $request->get_header('X-WP-Nonce');
	if (! $nonce) {
		$nonce = $request->get_param('_wpnonce');
	}

	if (! $nonce || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $nonce)), 'wp_rest')) {
		return new WP_Error('cc_tours_rest_nonce', __('Invalid security token.', 'consucorner'), array('status' => 403));
	}

	return true;
}

/**
 * GET /cc/v1/tours/state
 *
 * @return WP_REST_Response
 */
function consucorner_tours_rest_get_state()
{
	$state = consucorner_tours_get_user_state();
	return new WP_REST_Response(
		array(
			'state'       => $state,
			'merge_guest' => consucorner_tours_needs_login_merge(),
		),
		200
	);
}

/**
 * POST /cc/v1/tours/state
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function consucorner_tours_rest_post_state(WP_REST_Request $request)
{
	$body = $request->get_json_params();
	if (! is_array($body) || empty($body['state']) || ! is_array($body['state'])) {
		return new WP_Error('cc_tours_invalid', __('Invalid state payload.', 'consucorner'), array('status' => 400));
	}

	$incoming = consucorner_tours_normalize_state($body['state']);
	$current  = consucorner_tours_get_user_state();

	// Server wins on conflict unless guest merge flag is set and server is still default-empty phases.
	if (! empty($body['merge_guest']) && consucorner_tours_needs_login_merge()) {
		foreach ($incoming['phases'] as $phase => $status) {
			if ('pending' !== $current['phases'][ $phase ] && 'pending' === $status) {
				$incoming['phases'][ $phase ] = $current['phases'][ $phase ];
			}
		}
		if ($current['welcome_seen'] && ! $incoming['welcome_seen']) {
			$incoming['welcome_seen'] = true;
		}
		if (! empty($current['global_disabled'])) {
			$incoming['global_disabled'] = true;
		}
		consucorner_tours_clear_login_merge_flag();
	}

	consucorner_tours_save_user_state($incoming);

	return new WP_REST_Response(
		array(
			'state' => consucorner_tours_get_user_state(),
		),
		200
	);
}
