<?php

/**
 * My Account profile dashboard rendering + AJAX handlers.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/** User meta: attachment ID for custom profile photo (Media Library). */
const CONSUCORNER_PROFILE_AVATAR_META = 'consucorner_profile_avatar_id';

/**
 * Attachment URL for the user's ConsuCorner profile photo, if set.
 *
 * @param int $user_id User ID.
 * @param int $size    Requested square size in pixels.
 * @return string URL or empty string.
 */
function consucorner_user_custom_avatar_src($user_id, $size = 96)
{
	$user_id       = absint($user_id);
	$attachment_id = $user_id ? absint(get_user_meta($user_id, CONSUCORNER_PROFILE_AVATAR_META, true)) : 0;
	if (! $attachment_id) {
		return '';
	}
	$size  = max(32, min(512, absint($size)));
	$image = wp_get_attachment_image_src($attachment_id, array($size, $size));
	if (! empty($image[0])) {
		return $image[0];
	}
	$url = wp_get_attachment_url($attachment_id);
	return $url ? (string) $url : '';
}

/**
 * Avatar URL for front-end: custom attachment if set, otherwise Gravatar/default.
 *
 * @param int $user_id User ID.
 * @param int $size    Size in pixels.
 * @return string
 */
function consucorner_get_user_profile_avatar_url($user_id, $size = 120)
{
	$user_id = absint($user_id);
	if (! $user_id) {
		return '';
	}
	$custom = consucorner_user_custom_avatar_src($user_id, $size);
	if ($custom) {
		return $custom;
	}
	return get_avatar_url($user_id, array('size' => $size));
}

/**
 * Resolve a user ID from values passed to get_avatar().
 *
 * @param mixed $id_or_email User ID, email, WP_User, or comment object.
 * @return int
 */
function consucorner_avatar_resolve_user_id($id_or_email)
{
	if (is_numeric($id_or_email)) {
		$uid = (int) $id_or_email;
		return $uid > 0 ? $uid : 0;
	}
	if ($id_or_email instanceof WP_User) {
		return (int) $id_or_email->ID;
	}
	if (is_object($id_or_email) && isset($id_or_email->user_id)) {
		return absint($id_or_email->user_id);
	}
	if (is_string($id_or_email) && is_email($id_or_email)) {
		$user = get_user_by('email', $id_or_email);
		return $user ? (int) $user->ID : 0;
	}
	return 0;
}

/**
 * Use the uploaded profile image everywhere WordPress outputs an avatar (admin, toolbar, lists).
 *
 * @param array $args        Avatar arguments.
 * @param mixed $id_or_email User identifier.
 * @return array
 */
function consucorner_pre_get_avatar_data($args, $id_or_email)
{
	$user_id = consucorner_avatar_resolve_user_id($id_or_email);
	if (! $user_id) {
		return $args;
	}
	$size = isset($args['size']) ? (int) $args['size'] : 96;
	$url  = consucorner_user_custom_avatar_src($user_id, $size);
	if ($url) {
		$args['url']          = $url;
		$args['found_avatar'] = true;
	}
	return $args;
}
add_filter('pre_get_avatar_data', 'consucorner_pre_get_avatar_data', 99, 2);

/**
 * Output custom profile photo HTML early (wp-admin Profile Picture uses get_avatar()).
 *
 * Relying only on pre_get_avatar_data can fail if another layer short-circuits args; this
 * guarantees the uploaded ConsuCorner image replaces Gravatar when user meta is set.
 *
 * @param string|null $avatar      HTML or null to continue default handling.
 * @param mixed       $id_or_email User ID, email, etc.
 * @param array       $args        Arguments passed to get_avatar().
 * @return string|null
 */
function consucorner_pre_get_avatar($avatar, $id_or_email, $args)
{
	$user_id = consucorner_avatar_resolve_user_id($id_or_email);
	if (! $user_id) {
		return $avatar;
	}

	$size = isset($args['size']) ? (int) $args['size'] : 96;
	if ($size < 1) {
		$size = 96;
	}

	$url = consucorner_user_custom_avatar_src($user_id, $size);
	if (! $url) {
		return $avatar;
	}

	$url_2x = consucorner_user_custom_avatar_src($user_id, min(512, $size * 2));

	$alt = isset($args['alt']) ? $args['alt'] : '';
	if ('' === $alt) {
		$user = get_userdata($user_id);
		if ($user && $user->display_name) {
			$display = $user->display_name;
			/* translators: %s: User display name. */
			$alt = sprintf(__('Profile photo of %s', 'consucorner'), $display);
		}
	}

	$class = array('avatar', 'avatar-' . $size, 'photo');
	if (! empty($args['class'])) {
		if (is_array($args['class'])) {
			$class = array_merge($class, $args['class']);
		} else {
			$class[] = $args['class'];
		}
	}

	$extra_attr = isset($args['extra_attr']) ? trim((string) $args['extra_attr']) : '';
	if ($extra_attr) {
		$extra_attr .= ' ';
	}

	return sprintf(
		"<img alt='%s' src='%s' srcset='%s' class='%s' height='%d' width='%d' %s/>",
		esc_attr($alt),
		esc_url($url),
		esc_url($url_2x) . ' 2x',
		esc_attr(implode(' ', $class)),
		$size,
		$size,
		$extra_attr
	);
}
add_filter('pre_get_avatar', 'consucorner_pre_get_avatar', 999, 3);

/**
 * Append ConsuCorner profile photo preview to the core "Profile Picture" row in wp-admin.
 *
 * @param string  $description Default Gravatar help text.
 * @param WP_User $profile_user User being edited.
 * @return string
 */
function consucorner_user_profile_picture_description($description, $profile_user)
{
	if (! $profile_user instanceof WP_User) {
		return $description;
	}
	$url = consucorner_user_custom_avatar_src($profile_user->ID, 96);
	if (! $url) {
		$description .= '<p class="description" style="margin-top:8px;">' . esc_html__('Upload a photo from My Account (shop profile) to use it here and across the site. It replaces Gravatar once saved.', 'consucorner') . '</p>';
		return $description;
	}
	$description .= '<div class="consucorner-admin-profile-photo" style="margin-top:12px;padding:12px 14px;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;max-width:32rem;">';
	$description .= '<p style="margin:0 0 8px;"><strong>' . esc_html__('Site profile photo (ConsuCorner)', 'consucorner') . '</strong></p>';
	$description .= '<p style="margin:0 0 8px;"><img src="' . esc_url($url) . '" alt="" width="96" height="96" style="display:block;border-radius:50%;object-fit:cover;width:96px;height:96px;border:1px solid #c3c4c7;" loading="lazy" decoding="async" /></p>';
	$description .= '<p class="description" style="margin:0;">' . esc_html__('This image was uploaded from My Account and is used across the site and in the dashboard instead of Gravatar.', 'consucorner') . '</p>';
	$description .= '</div>';
	return $description;
}
add_filter('user_profile_picture_description', 'consucorner_user_profile_picture_description', 10, 2);

/**
 * Render My Account endpoint links that are not covered by the custom cards.
 *
 * WooCommerce extensions commonly register endpoints through
 * `woocommerce_account_menu_items` for features such as loyalty points,
 * memberships, subscriptions, store credit, or referrals. The custom dashboard
 * keeps those links available by appending unknown endpoints to the card grid.
 *
 * @return string
 */
function consucorner_get_profile_extension_menu_markup()
{
	if (! function_exists('wc_get_account_menu_items') || ! function_exists('wc_get_account_endpoint_url')) {
		return '<div id="profile-menu-plugins" role="presentation"></div>';
	}

	$handled_endpoints = apply_filters(
		'consucorner_profile_handled_account_endpoints',
		array(
			'dashboard',
			'orders',
			'downloads',
			'payment-methods',
			'edit-account',
			'edit-address',
			'customer-logout',
		)
	);

	$items = array();
	foreach (wc_get_account_menu_items() as $endpoint => $label) {
		if (in_array($endpoint, $handled_endpoints, true)) {
			continue;
		}
		$items[$endpoint] = $label;
	}

	$items = apply_filters('consucorner_profile_extension_account_menu_items', $items);

	ob_start();
?>
	<div id="profile-menu-plugins" role="presentation">
		<?php echo consucorner_get_vendor_dashboard_profile_item_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php foreach ($items as $endpoint => $label) : ?>
			<a href="<?php echo esc_url(wc_get_account_endpoint_url($endpoint)); ?>" class="profile-item profile-item--endpoint" data-account-endpoint="<?php echo esc_attr($endpoint); ?>" role="listitem">
				<span class="profile-item-icon">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<use href="#pi-user" />
					</svg>
				</span>
				<?php echo esc_html($label); ?>
			</a>
		<?php endforeach; ?>
		<?php do_action('consucorner_profile_menu_extension_items'); ?>
	</div>
<?php

	return (string) ob_get_clean();
}

/**
 * Whether the current user is a Dokan vendor/seller.
 *
 * @param int $user_id Optional user ID.
 * @return bool
 */
function consucorner_is_dokan_vendor_user($user_id = 0)
{
	$user_id = $user_id ? absint($user_id) : get_current_user_id();
	if (! $user_id) {
		return false;
	}

	if (function_exists('dokan_is_user_seller')) {
		return (bool) dokan_is_user_seller($user_id);
	}

	$user = get_userdata($user_id);
	return $user instanceof WP_User && in_array('seller', (array) $user->roles, true);
}

/**
 * Profile grid link to the Dokan vendor dashboard (vendors only).
 *
 * @return string
 */
function consucorner_get_vendor_dashboard_profile_item_markup()
{
	if (! consucorner_is_dokan_vendor_user() || ! function_exists('dokan_get_navigation_url')) {
		return '';
	}

	$url = dokan_get_navigation_url();
	if (! $url) {
		return '';
	}

	$label = apply_filters(
		'dokan_set_go_to_vendor_dashboard_btn_text',
		__('Vendor Dashboard', 'consucorner')
	);

	ob_start();
	?>
	<a href="<?php echo esc_url($url); ?>" class="profile-item profile-item--vendor-dashboard" role="listitem">
		<span class="profile-item-icon profile-item-icon--custom" style="--plugin-icon-color:#0f766e;">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<rect x="3" y="3" width="7" height="7" rx="1"/>
				<rect x="14" y="3" width="7" height="7" rx="1"/>
				<rect x="3" y="14" width="7" height="7" rx="1"/>
				<rect x="14" y="14" width="7" height="7" rx="1"/>
			</svg>
		</span>
		<?php echo esc_html($label); ?>
	</a>
	<?php
	return (string) ob_get_clean();
}

/**
 * Remove Dokan / plugin clutter from the WooCommerce My Account menu.
 *
 * @param array<string,string> $items Endpoint => label.
 * @return array<string,string>
 */
function consucorner_filter_my_account_menu_items($items)
{
	$hidden = consucorner_get_hidden_account_menu_endpoints();
	foreach ($hidden as $endpoint) {
		unset($items[$endpoint]);
	}

	return $items;
}
add_filter('woocommerce_account_menu_items', 'consucorner_filter_my_account_menu_items', 999);
add_filter('consucorner_profile_extension_account_menu_items', 'consucorner_filter_my_account_menu_items', 999);

/**
 * Endpoints hidden from the custom profile card grid (and WC menu).
 *
 * @return string[]
 */
function consucorner_get_hidden_account_menu_endpoints()
{
	$hidden = array(
		'following',        // Dokan Follow Store — "Vendors" (followed stores).
		'support-tickets',  // Dokan Store Support — confusing "Seller Support Tickets" label.
	);

	/**
	 * Filter endpoints removed from My Account navigation for this site.
	 *
	 * @param string[] $hidden Endpoint slugs.
	 */
	return apply_filters('consucorner_hidden_account_menu_endpoints', $hidden);
}

/**
 * Strip Dokan wholesale signup and default vendor dashboard button from the
 * WooCommerce dashboard hook area (vendor dashboard is a profile card instead).
 */
function consucorner_customize_account_dashboard_hooks()
{
	if (! function_exists('is_account_page') || ! is_account_page() || ! is_user_logged_in()) {
		return;
	}

	remove_action('woocommerce_account_dashboard', 'dokan_set_go_to_vendor_dashboard_btn');

	if (! isset($GLOBALS['wp_filter']['woocommerce_account_dashboard'])) {
		return;
	}

	$hook = $GLOBALS['wp_filter']['woocommerce_account_dashboard'];
	if (! $hook instanceof WP_Hook) {
		return;
	}

	foreach ($hook->callbacks as $priority => $callbacks) {
		foreach ($callbacks as $callback) {
			$function = $callback['function'] ?? null;
			if (! is_array($function) || ! isset($function[0], $function[1])) {
				continue;
			}

			// Dokan Pro wholesale "Become a wholesale customer" block.
			if (is_object($function[0]) && 'render_migration_html' === $function[1]) {
				remove_action('woocommerce_account_dashboard', $function, (int) $priority);
			}
		}
	}
}
add_action('wp', 'consucorner_customize_account_dashboard_hooks', 20);

/**
 * My Account endpoints registered by plugins but omitted from the menu list.
 *
 * Dokan RMA uses `request-warranty` and `view-rma-requests` without adding them
 * to `woocommerce_account_menu_items`, so the theme must detect them manually.
 *
 * @return string[]
 */
function consucorner_get_unlisted_account_endpoints()
{
	$endpoints = array(
		'request-warranty',
		'view-rma-requests',
	);

	/**
	 * Filter unlisted WooCommerce account endpoints for custom layout routing.
	 *
	 * @param string[] $endpoints Endpoint slugs.
	 */
	return apply_filters('consucorner_unlisted_account_endpoints', $endpoints);
}

/**
 * Get the current account endpoint, including endpoints added by plugins.
 *
 * Some plugins register My Account tabs with `add_rewrite_endpoint()` and
 * `woocommerce_account_menu_items` but do not add their slug to WooCommerce's
 * query vars. The custom My Account template still needs to treat those as
 * endpoint pages so plugin content opens in the same ConsuCorner layout.
 *
 * @return string
 */
function consucorner_get_current_account_endpoint()
{
	if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) {
		if (function_exists('WC') && WC()->query && method_exists(WC()->query, 'get_current_endpoint')) {
			$current = WC()->query->get_current_endpoint();
			if ($current) {
				return (string) $current;
			}
		}
	}

	if (! function_exists('wc_get_account_menu_items')) {
		return '';
	}

	global $wp;
	$query_vars = isset($wp->query_vars) && is_array($wp->query_vars) ? $wp->query_vars : array();

	foreach (consucorner_get_unlisted_account_endpoints() as $endpoint) {
		if (array_key_exists($endpoint, $query_vars)) {
			return (string) $endpoint;
		}
	}

	foreach (wc_get_account_menu_items() as $endpoint => $label) {
		if (in_array($endpoint, array('dashboard', 'customer-logout'), true)) {
			continue;
		}
		if (isset($query_vars[$endpoint])) {
			return (string) $endpoint;
		}
		if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url($endpoint)) {
			return (string) $endpoint;
		}
	}

	return '';
}

/**
 * Whether an endpoint is registered in WooCommerce's own account query vars.
 *
 * @param string $endpoint Endpoint slug.
 * @return bool
 */
function consucorner_is_wc_registered_account_endpoint($endpoint)
{
	if (! $endpoint || ! function_exists('WC') || ! WC()->query || ! method_exists(WC()->query, 'get_query_vars')) {
		return false;
	}

	$query_vars = WC()->query->get_query_vars();
	return is_array($query_vars) && array_key_exists($endpoint, $query_vars);
}

/**
 * Render account endpoint content, including plugin endpoints Woo does not know.
 *
 * @param string $endpoint Endpoint slug.
 * @return string
 */
function consucorner_render_account_endpoint_content($endpoint)
{
	$endpoint = sanitize_key($endpoint);
	if (! $endpoint) {
		return '';
	}

	$hook = 'woocommerce_account_' . $endpoint . '_endpoint';

	if (! consucorner_is_wc_registered_account_endpoint($endpoint) && has_action($hook)) {
		ob_start();
		do_action($hook, get_query_var($endpoint));
		return (string) ob_get_clean();
	}

	ob_start();
	do_action('woocommerce_account_content');
	$content = (string) ob_get_clean();

	if ('' === trim(wp_strip_all_tags($content)) && has_action($hook)) {
		ob_start();
		do_action($hook, get_query_var($endpoint));
		$content = (string) ob_get_clean();
	}

	return $content;
}

/**
 * Render a WooCommerce account endpoint inside a ConsuCorner profile modal.
 *
 * @param string $hook WooCommerce account endpoint action name.
 * @param string $fallback Fallback copy when WooCommerce returns no markup.
 * @return string
 */
function consucorner_profile_render_wc_endpoint_modal_content($hook, $fallback)
{
	ob_start();
?>
	<div class="pwoocommerce-content woocommerce">
		<?php do_action($hook); ?>
	</div>
	<?php
	$content = trim((string) ob_get_clean());

	if ('' === wp_strip_all_tags($content)) {
		$content = sprintf(
			'<div class="pwoocommerce-content woocommerce"><p class="woocommerce-info">%s</p></div>',
			esc_html($fallback)
		);
	}

	return $content;
}

/**
 * Return the profile dashboard markup bundled inside the theme.
 *
 * The theme ships its own copy of the profile HTML at
 * `wp-content/themes/consucorner/front-end/profile.html`. Keeping the template
 * inside the theme means a theme-only deployment renders the custom My Account
 * dashboard without depending on any sibling design folder or plugin template.
 *
 * @return string
 */
function consucorner_get_profile_template_partial()
{
	$template_file = get_template_directory() . '/front-end/profile.html';
	if (! file_exists($template_file)) {
		return '';
	}

	$html  = (string) file_get_contents($template_file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$profile_start = strpos($html, '<section class="profile-section"');
	$heading_class = strpos($html, 'class="shop-page-head cart-page-head"');
	$heading_start = false !== $heading_class ? strrpos(substr($html, 0, $heading_class), '<section') : false;
	$start         = false !== $heading_start && false !== $profile_start && $heading_start < $profile_start ? $heading_start : $profile_start;
	$end           = strpos($html, '<div id="profile-modals-plugins"></div>');
	if (false === $start || false === $profile_start || false === $end) {
		return '';
	}

	$end   += strlen('<div id="profile-modals-plugins"></div>');
	$chunk = substr($html, $start, $end - $start);
	$chunk = str_replace('assets/images/', esc_url(get_template_directory_uri() . '/assets/images/'), $chunk);
	$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	$chunk    = str_replace('href="shop.html"', 'href="' . esc_url($shop_url) . '"', $chunk);
	$chunk = str_replace('<div id="profile-menu-plugins" role="presentation"></div>', consucorner_get_profile_extension_menu_markup(), $chunk);
	$chunk = str_replace(
		'<div class="pwoocommerce-content" data-cc-wc-downloads></div>',
		consucorner_profile_render_wc_endpoint_modal_content('woocommerce_account_downloads_endpoint', __('No downloads are available yet.', 'consucorner')),
		$chunk
	);
	$chunk = str_replace(
		'<div class="pwoocommerce-content" data-cc-wc-payment-methods></div>',
		consucorner_profile_render_wc_endpoint_modal_content('woocommerce_account_payment-methods_endpoint', __('No saved payment methods are available yet.', 'consucorner')),
		$chunk
	);

	$chunk = str_replace(
		'<div class="cc-forminator-form cc-forminator-form--report" data-cc-forminator-report></div>',
		consucorner_profile_render_report_form_markup(),
		$chunk
	);

	return $chunk;
}

/**
 * Render the Forminator-powered Report & Support form for the My Account modal.
 *
 * The form ID is stored in the `consucorner_forminator_report_form_id` option,
 * editable from the meta box on the WooCommerce My Account page.
 *
 * @return string
 */
function consucorner_profile_render_report_form_markup()
{
	$form_id        = function_exists('consucorner_profile_get_report_form_id')
		? consucorner_profile_get_report_form_id()
		: absint(get_option('consucorner_forminator_report_form_id'));
	$has_forminator = shortcode_exists('forminator_form');

	ob_start();
	?>
	<div class="cc-forminator-form cc-forminator-form--report">
		<?php if ($form_id && $has_forminator) : ?>
			<?php echo do_shortcode('[forminator_form id="' . absint($form_id) . '"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
		<?php else : ?>
			<p class="cc-forminator-form-empty">
				<?php esc_html_e('The Report & Support form is not configured yet. An administrator can attach a Forminator form from the My Account page settings.', 'consucorner'); ?>
			</p>
		<?php endif; ?>
	</div>
<?php
	return (string) ob_get_clean();
}

/**
 * Validate the profile nonce and logged-in state.
 *
 * @return int Current user ID.
 */
function consucorner_profile_require_user()
{
	check_ajax_referer('consucorner_profile_nonce', 'nonce');
	if (! is_user_logged_in()) {
		wp_send_json_error(array('message' => __('Your session expired. Please log in again.', 'consucorner')), 401);
	}
	return get_current_user_id();
}

/**
 * Decode the JSON payload posted by profile.js.
 *
 * @return array
 */
function consucorner_profile_payload()
{
	$raw = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$data = json_decode((string) $raw, true);
	return is_array($data) ? $data : array();
}

/**
 * Build frontend profile data from WordPress/WooCommerce user fields.
 *
 * @param int $user_id User ID.
 * @return array
 */
function consucorner_profile_data($user_id)
{
	$user = get_userdata($user_id);
	if (! $user) {
		return array();
	}

	$notification_keys = consucorner_profile_notification_preference_keys();
	$keys = array(
		'billing_phone',
		'billing_company',
		'billing_first_name',
		'billing_last_name',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_state',
		'billing_postcode',
		'billing_country',
		'billing_email',
		'shipping_first_name',
		'shipping_last_name',
		'shipping_company',
		'shipping_address_1',
		'shipping_address_2',
		'shipping_city',
		'shipping_state',
		'shipping_postcode',
		'shipping_country',
		'shipping_phone',
		'meta_birth_date',
		'meta_gender',
		'meta_specialty',
		'meta_role_title',
		'marketing_email_consent',
	);
	$keys = array_merge($keys, $notification_keys);

	$profile = array(
		'user_id'      => $user_id,
		'username'     => $user->user_login,
		'email'        => $user->user_email,
		'first_name'   => get_user_meta($user_id, 'first_name', true),
		'last_name'    => get_user_meta($user_id, 'last_name', true),
		'display_name' => $user->display_name,
		'member_since' => mysql2date('F Y', $user->user_registered),
		'avatar_url'   => consucorner_get_user_profile_avatar_url($user_id, 120),
	);

	foreach ($keys as $key) {
		$profile[$key] = get_user_meta($user_id, $key, true);
	}

	foreach ($notification_keys as $key) {
		if ('' === $profile[$key]) {
			$profile[$key] = '1';
		}
	}

	if (empty($profile['billing_email'])) {
		$profile['billing_email'] = $user->user_email;
	}

	return $profile;
}

/**
 * Profile field choices shared by frontend labels and wp-admin user editing.
 *
 * @return array
 */
function consucorner_profile_admin_field_choices()
{
	return array(
		'meta_gender'     => array(
			''           => __('Select gender', 'consucorner'),
			'male'       => __('Male', 'consucorner'),
			'female'     => __('Female', 'consucorner'),
			'prefer_not' => __('Prefer not to say', 'consucorner'),
		),
		'meta_specialty'  => array(
			''                => __('Select specialty', 'consucorner'),
			'ophthalmology'   => __('Ophthalmology', 'consucorner'),
			'general_surgery' => __('General Surgery', 'consucorner'),
			'dental'          => __('Dental', 'consucorner'),
			'orthopedics'     => __('Orthopedics', 'consucorner'),
			'cardiology'      => __('Cardiology', 'consucorner'),
			'dermatology'     => __('Dermatology', 'consucorner'),
			'other'           => __('Other', 'consucorner'),
		),
		'meta_role_title' => array(
			''               => __('Select role', 'consucorner'),
			'doctor'         => __('Doctor', 'consucorner'),
			'nurse'          => __('Nurse', 'consucorner'),
			'pharmacist'     => __('Pharmacist', 'consucorner'),
			'clinic_manager' => __('Clinic Manager', 'consucorner'),
			'hospital_buyer' => __('Hospital Buyer', 'consucorner'),
			'distributor'    => __('Distributor', 'consucorner'),
			'other'          => __('Other', 'consucorner'),
		),
	);
}

/**
 * Render ConsuCorner account fields in wp-admin user profile screens.
 *
 * @param WP_User $user User being edited.
 */
function consucorner_profile_admin_fields($user)
{
	if (! $user instanceof WP_User) {
		return;
	}

	$choices = consucorner_profile_admin_field_choices();
?>
	<h2><?php esc_html_e('ConsuCorner Account Details', 'consucorner'); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="consucorner_billing_phone"><?php esc_html_e('Phone Number', 'consucorner'); ?></label></th>
			<td>
				<input type="tel" class="regular-text" name="consucorner_profile[billing_phone]" id="consucorner_billing_phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_phone', true)); ?>" autocomplete="tel" />
				<p class="description"><?php esc_html_e('Shown in the My Account personal information popup and used as the WooCommerce billing phone.', 'consucorner'); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="consucorner_meta_birth_date"><?php esc_html_e('Date of Birth', 'consucorner'); ?></label></th>
			<td>
				<input type="date" class="regular-text" name="consucorner_profile[meta_birth_date]" id="consucorner_meta_birth_date" value="<?php echo esc_attr(get_user_meta($user->ID, 'meta_birth_date', true)); ?>" />
			</td>
		</tr>
		<tr>
			<th><label for="consucorner_meta_gender"><?php esc_html_e('Gender', 'consucorner'); ?></label></th>
			<td>
				<select name="consucorner_profile[meta_gender]" id="consucorner_meta_gender">
					<?php foreach ($choices['meta_gender'] as $value => $label) : ?>
						<option value="<?php echo esc_attr($value); ?>" <?php selected(get_user_meta($user->ID, 'meta_gender', true), $value); ?>><?php echo esc_html($label); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="consucorner_meta_specialty"><?php esc_html_e('Medical Specialty', 'consucorner'); ?></label></th>
			<td>
				<select name="consucorner_profile[meta_specialty]" id="consucorner_meta_specialty">
					<?php foreach ($choices['meta_specialty'] as $value => $label) : ?>
						<option value="<?php echo esc_attr($value); ?>" <?php selected(get_user_meta($user->ID, 'meta_specialty', true), $value); ?>><?php echo esc_html($label); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="consucorner_meta_role_title"><?php esc_html_e('Role / Title', 'consucorner'); ?></label></th>
			<td>
				<select name="consucorner_profile[meta_role_title]" id="consucorner_meta_role_title">
					<?php foreach ($choices['meta_role_title'] as $value => $label) : ?>
						<option value="<?php echo esc_attr($value); ?>" <?php selected(get_user_meta($user->ID, 'meta_role_title', true), $value); ?>><?php echo esc_html($label); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="consucorner_billing_company"><?php esc_html_e('Company / Clinic Name', 'consucorner'); ?></label></th>
			<td>
				<input type="text" class="regular-text" name="consucorner_profile[billing_company]" id="consucorner_billing_company" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_company', true)); ?>" autocomplete="organization" />
				<p class="description"><?php esc_html_e('Synced with the WooCommerce billing company field.', 'consucorner'); ?></p>
			</td>
		</tr>
	</table>
<?php
}
add_action('show_user_profile', 'consucorner_profile_admin_fields');
add_action('edit_user_profile', 'consucorner_profile_admin_fields');

/**
 * Save ConsuCorner account fields from wp-admin user profile screens.
 *
 * @param int $user_id User ID.
 */
function consucorner_profile_admin_fields_save($user_id)
{
	if (! current_user_can('edit_user', $user_id)) {
		return;
	}

	if (! isset($_POST['consucorner_profile']) || ! is_array($_POST['consucorner_profile'])) {
		return;
	}

	$data    = wp_unslash($_POST['consucorner_profile']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$choices = consucorner_profile_admin_field_choices();

	$text_fields = array(
		'billing_phone',
		'billing_company',
	);

	foreach ($text_fields as $key) {
		if (array_key_exists($key, $data)) {
			update_user_meta($user_id, $key, sanitize_text_field($data[$key]));
		}
	}

	if (array_key_exists('meta_birth_date', $data)) {
		$date = sanitize_text_field($data['meta_birth_date']);
		update_user_meta($user_id, 'meta_birth_date', preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '');
	}

	foreach (array('meta_gender', 'meta_specialty', 'meta_role_title') as $key) {
		if (! array_key_exists($key, $data)) {
			continue;
		}

		$value = sanitize_key($data[$key]);
		update_user_meta($user_id, $key, array_key_exists($value, $choices[$key]) ? $value : '');
	}
}
add_action('personal_options_update', 'consucorner_profile_admin_fields_save');
add_action('edit_user_profile_update', 'consucorner_profile_admin_fields_save');

/**
 * AJAX: load latest profile data.
 */
function consucorner_profile_get_data()
{
	$user_id = consucorner_profile_require_user();
	wp_send_json_success(array('profile' => consucorner_profile_data($user_id)));
}
add_action('wp_ajax_consucorner_profile_get_data', 'consucorner_profile_get_data');

/**
 * AJAX: save profile photo from base64 data URL (from front-end My Account).
 */
function consucorner_profile_save_avatar()
{
	$user_id = consucorner_profile_require_user();
	$data    = consucorner_profile_payload();

	if (empty($data['avatar_data']) || ! is_string($data['avatar_data'])) {
		wp_send_json_error(array('message' => __('No image data received.', 'consucorner')), 400);
	}

	$raw = $data['avatar_data'];
	if (! preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,/i', $raw)) {
		wp_send_json_error(array('message' => __('Please use a JPG, PNG, GIF, or WebP image.', 'consucorner')), 400);
	}

	$binary = base64_decode(preg_replace('/^data:image\/\w+;base64,/i', '', $raw), true);
	if (false === $binary || strlen($binary) < 50) {
		wp_send_json_error(array('message' => __('Invalid image data.', 'consucorner')), 400);
	}
	$max_bytes = defined('MB_IN_BYTES') ? 3 * MB_IN_BYTES : 3145728;
	if (strlen($binary) > $max_bytes) {
		wp_send_json_error(array('message' => __('Image must be smaller than 3 MB.', 'consucorner')), 400);
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$mime = 'image/jpeg';
	$ext  = 'jpg';
	if (preg_match('/^data:image\/png;base64,/i', $raw)) {
		$mime = 'image/png';
		$ext  = 'png';
	} elseif (preg_match('/^data:image\/gif;base64,/i', $raw)) {
		$mime = 'image/gif';
		$ext  = 'gif';
	} elseif (preg_match('/^data:image\/webp;base64,/i', $raw)) {
		$mime = 'image/webp';
		$ext  = 'webp';
	}

	$filename = 'consucorner-profile-' . $user_id . '-' . wp_generate_password(8, false) . '.' . $ext;
	$upload   = wp_upload_bits($filename, null, $binary);

	if (! empty($upload['error'])) {
		wp_send_json_error(array('message' => $upload['error']), 500);
	}

	$wp_filetype = wp_check_filetype($upload['file'], null);
	if (empty($wp_filetype['type']) || 0 !== strpos($wp_filetype['type'], 'image/')) {
		wp_delete_file($upload['file']);
		wp_send_json_error(array('message' => __('The uploaded file is not a valid image.', 'consucorner')), 400);
	}

	$attachment = array(
		'post_mime_type' => $wp_filetype['type'],
		'post_title'     => sanitize_file_name(preg_replace('/\.[^.]+$/', '', $filename)),
		'post_content'   => '',
		'post_status'    => 'inherit',
		'post_author'    => $user_id,
	);

	$attach_id = wp_insert_attachment($attachment, $upload['file']);
	if (! $attach_id || is_wp_error($attach_id)) {
		wp_delete_file($upload['file']);
		wp_send_json_error(array('message' => __('Could not save the image.', 'consucorner')), 500);
	}

	$meta = wp_generate_attachment_metadata($attach_id, $upload['file']);
	wp_update_attachment_metadata($attach_id, $meta);

	$old_id = absint(get_user_meta($user_id, CONSUCORNER_PROFILE_AVATAR_META, true));
	if ($old_id && (int) $old_id !== (int) $attach_id) {
		wp_delete_attachment($old_id, true);
	}

	update_user_meta($user_id, CONSUCORNER_PROFILE_AVATAR_META, (int) $attach_id);

	wp_send_json_success(array(
		'message' => __('Profile photo saved.', 'consucorner'),
		'profile' => consucorner_profile_data($user_id),
	));
}
add_action('wp_ajax_consucorner_profile_save_avatar', 'consucorner_profile_save_avatar');

/**
 * AJAX: save account details and WooCommerce billing/shipping meta.
 */
function consucorner_profile_save_account()
{
	$user_id = consucorner_profile_require_user();
	$data    = consucorner_profile_payload();

	$email        = isset($data['email']) ? sanitize_email($data['email']) : '';
	$first_name   = isset($data['first_name']) ? sanitize_text_field($data['first_name']) : '';
	$last_name    = isset($data['last_name']) ? sanitize_text_field($data['last_name']) : '';
	$display_name = isset($data['display_name']) ? sanitize_text_field($data['display_name']) : trim($first_name . ' ' . $last_name);

	if (! $email || ! is_email($email)) {
		wp_send_json_error(array('message' => __('Please enter a valid email address.', 'consucorner')), 400);
	}

	$existing = email_exists($email);
	if ($existing && (int) $existing !== $user_id) {
		wp_send_json_error(array('message' => __('This email address is already used by another account.', 'consucorner')), 400);
	}

	$result = wp_update_user(array(
		'ID'           => $user_id,
		'user_email'   => $email,
		'first_name'   => $first_name,
		'last_name'    => $last_name,
		'display_name' => $display_name,
	));

	if (is_wp_error($result)) {
		wp_send_json_error(array('message' => $result->get_error_message()), 400);
	}

	$meta_keys = array(
		'billing_phone',
		'billing_company',
		'billing_first_name',
		'billing_last_name',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_state',
		'billing_postcode',
		'billing_country',
		'billing_email',
		'shipping_first_name',
		'shipping_last_name',
		'shipping_company',
		'shipping_address_1',
		'shipping_address_2',
		'shipping_city',
		'shipping_state',
		'shipping_postcode',
		'shipping_country',
		'shipping_phone',
		'meta_birth_date',
		'meta_gender',
		'meta_specialty',
		'meta_role_title',
	);

	foreach ($meta_keys as $key) {
		if (! array_key_exists($key, $data)) {
			continue;
		}
		$value = 'billing_email' === $key ? sanitize_email($data[$key]) : sanitize_text_field($data[$key]);
		update_user_meta($user_id, $key, $value);
	}

	wp_send_json_success(array(
		'message' => __('Account details saved.', 'consucorner'),
		'profile' => consucorner_profile_data($user_id),
	));
}
add_action('wp_ajax_consucorner_profile_save_account', 'consucorner_profile_save_account');

/**
 * Strip WooCommerce price HTML to plain text (fixes &nbsp; in JSON responses).
 *
 * @param string $html Formatted price HTML.
 * @return string
 */
function consucorner_profile_format_price_text($html)
{
	return html_entity_decode(wp_strip_all_tags((string) $html), ENT_QUOTES, get_bloginfo('charset'));
}

/**
 * Query arg names used for direct order links from emails and notifications.
 *
 * @return array{order: string, key: string}
 */
function consucorner_profile_order_link_query_args()
{
	return array(
		'order' => 'cc_order',
		'key'   => 'cc_key',
	);
}

/**
 * Build a My Account URL that opens a specific order in the profile popup.
 *
 * Uses the WooCommerce order key as the secret token (same model as view-order).
 *
 * @param WC_Order $order Order object.
 * @return string
 */
function consucorner_profile_get_order_track_url($order)
{
	if (! $order instanceof WC_Order) {
		return function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
	}

	$args = consucorner_profile_order_link_query_args();
	$base = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');

	return add_query_arg(
		array(
			$args['order'] => $order->get_id(),
			$args['key']   => $order->get_order_key(),
		),
		$base
	);
}

/**
 * Build the public Bosta shipment tracking URL for a tracking number.
 *
 * @param string $tracking_number Bosta tracking / shipment number.
 * @return string
 */
function consucorner_profile_get_bosta_tracking_url( $tracking_number ) {
	return function_exists( 'cc_get_bosta_tracking_url' )
		? cc_get_bosta_tracking_url( $tracking_number )
		: '';
}

/**
 * Whether the current viewer may access an order in the profile popup.
 *
 * @param WC_Order $order    Order.
 * @param int      $user_id  Logged-in user ID (0 for guests).
 * @param string   $order_key Optional order key from the tracking link.
 * @return bool
 */
function consucorner_profile_user_can_view_order($order, $user_id = 0, $order_key = '')
{
	if (! $order instanceof WC_Order) {
		return false;
	}

	$user_id   = absint($user_id);
	$order_key = sanitize_text_field((string) $order_key);

	if ($user_id > 0 && (int) $order->get_customer_id() === $user_id) {
		return true;
	}

	if ($order_key && hash_equals((string) $order->get_order_key(), $order_key)) {
		return true;
	}

	return false;
}

/**
 * Resolve an order from ID + optional access key.
 *
 * @param int    $order_id  Order ID.
 * @param string $order_key Optional order key.
 * @param int    $user_id   Current user ID.
 * @return WC_Order|false
 */
function consucorner_profile_resolve_viewable_order($order_id, $order_key = '', $user_id = 0)
{
	$order_id = absint($order_id);
	if (! $order_id || ! function_exists('wc_get_order')) {
		return false;
	}

	$order = wc_get_order($order_id);
	if (! $order || ! consucorner_profile_user_can_view_order($order, $user_id, $order_key)) {
		return false;
	}

	return $order;
}

/**
 * URL for the Dokan RMA return request form for an order.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function consucorner_profile_get_return_request_url($order)
{
	if (! $order instanceof WC_Order || ! function_exists('wc_get_account_endpoint_url')) {
		return '';
	}

	return (string) wc_get_account_endpoint_url('request-warranty') . $order->get_id();
}

/**
 * Whether a customer can open the Dokan return request form for this order.
 *
 * @param WC_Order $order Order.
 * @return array{allowed:bool,reason:string}
 */
function consucorner_profile_get_return_request_state($order)
{
	if (! $order instanceof WC_Order) {
		return array(
			'allowed' => false,
			'reason'  => __('Order not found.', 'consucorner'),
		);
	}

	if (! function_exists('dokan_get_option')) {
		return array(
			'allowed' => false,
			'reason'  => __('Returns are not available on this site yet.', 'consucorner'),
		);
	}

	if (! class_exists('Consucorner_Order_Return_Workflow') || ! Consucorner_Order_Return_Workflow::order_allows_customer_return($order)) {
		$reason = __('Returns are available after your order has been delivered.', 'consucorner');
		if (class_exists('Consucorner_Order_Return_Workflow') && Consucorner_Order_Return_Workflow::order_has_cancel_eligible_fulfillment($order) && ! $order->has_status(array('completed', 'cancelled', 'refunded', 'failed'))) {
			$reason = __('This order is still in progress. Please cancel it instead of requesting a return.', 'consucorner');
		}

		return array(
			'allowed' => false,
			'reason'  => $reason,
		);
	}

	if (class_exists('Consucorner_Returns_Rma_Config')) {
		Consucorner_Returns_Rma_Config::ensure_order_ready_for_returns($order);
	}

	$eligible_items = 0;
	foreach ($order->get_items('line_item') as $item) {
		if (! $item instanceof WC_Order_Item_Product) {
			continue;
		}

		if (class_exists('\WeDevs\DokanPro\Modules\RMA\WarrantyItem')) {
			try {
				$warranty_item = new \WeDevs\DokanPro\Modules\RMA\WarrantyItem((int) $item->get_id());
				if ($warranty_item->has_warranty()) {
					++$eligible_items;
				}
			} catch (Exception $exception) {
				continue;
			}
		}
	}

	if ($eligible_items > 0) {
		return array(
			'allowed' => true,
			'reason'  => '',
		);
	}

	return array(
		'allowed' => false,
		'reason'  => __('This order has no returnable quantity left (already fully returned).', 'consucorner'),
	);
}

/**
 * Operations fulfillment/return fields for profile order JSON.
 *
 * @param WC_Order $order Order.
 * @return array<string,string>
 */
function consucorner_profile_get_ops_status_fields($order)
{
	if (! $order instanceof WC_Order || ! class_exists('Consucorner_Order_Return_Workflow')) {
		return array(
			'fulfillment_status' => '',
			'fulfillment_label'  => '',
			'return_status'      => '',
			'return_label'       => '',
		);
	}

	$fulfillment = Consucorner_Order_Return_Workflow::get_customer_fulfillment_summary($order);
	$return      = Consucorner_Order_Return_Workflow::get_order_return_summary($order->get_id());

	return array(
		'fulfillment_status' => (string) ($fulfillment['status'] ?? ''),
		'fulfillment_label'  => (string) ($fulfillment['label'] ?? ''),
		'customer_status'    => (string) ($fulfillment['label'] ?? ''),
		'return_status'      => (string) ($return['status'] ?? ''),
		'return_label'       => (string) ($return['label'] ?? ''),
	);
}

/**
 * Return request fields for profile order JSON.
 *
 * @param WC_Order $order Order.
 * @return array<string,mixed>
 */
function consucorner_profile_get_return_request_fields($order)
{
	$state = consucorner_profile_get_return_request_state($order);

	return array(
		'can_request_return'   => ! empty($state['allowed']),
		'return_request_url'   => ! empty($state['allowed']) ? consucorner_profile_get_return_request_url($order) : '',
		'return_blocked_reason' => ! empty($state['allowed']) ? '' : (string) $state['reason'],
	);
}

/**
 * Build structured order detail payload for the profile popup.
 *
 * @param WC_Order $order Order.
 * @return array
 */
function consucorner_profile_format_order_detail($order)
{
	$items = array();

	foreach ($order->get_items('line_item') as $item) {
		if (! $item instanceof WC_Order_Item_Product) {
			continue;
		}

		$product = $item->get_product();
		$meta    = array();

		foreach ($item->get_formatted_meta_data() as $meta_entry) {
			$meta[] = array(
				'label' => wp_strip_all_tags((string) $meta_entry->display_key),
				'value' => wp_strip_all_tags((string) $meta_entry->display_value),
			);
		}

		$image = '';
		if ($product && $product->get_image_id()) {
			$image = (string) wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail');
		}

		$items[] = array(
			'name'  => wp_strip_all_tags($item->get_name()),
			'qty'   => (float) $item->get_quantity(),
			'total' => consucorner_profile_format_price_text($order->get_formatted_line_subtotal($item)),
			'image' => $image,
			'meta'  => $meta,
			'url'   => $product ? $product->get_permalink() : '',
		);
	}

	$totals = array();
	foreach ($order->get_order_item_totals() as $total_row) {
		$totals[] = array(
			'label' => wp_strip_all_tags((string) ($total_row['label'] ?? '')),
			'value' => consucorner_profile_format_price_text((string) ($total_row['value'] ?? '')),
		);
	}

	$shipping_address = $order->get_formatted_shipping_address();
	if (! $shipping_address) {
		$shipping_address = $order->get_formatted_billing_address();
	}

	$bosta_tracking = sanitize_text_field( (string) $order->get_meta( 'bosta_tracking_number', true ) );
	$customer_status = class_exists('Consucorner_Order_Return_Workflow')
		? Consucorner_Order_Return_Workflow::get_customer_fulfillment_summary($order)
		: array('status' => $order->get_status(), 'label' => wc_get_order_status_name($order->get_status()));

	return array(
		'id'              => $order->get_id(),
		'number'          => $order->get_order_number(),
		'status'          => $order->get_status(),
		'status_name'     => (string) ($customer_status['label'] ?? wc_get_order_status_name($order->get_status())),
		'date'            => $order->get_date_created() ? $order->get_date_created()->date_i18n('d M Y') : '',
		'date_full'       => $order->get_date_created() ? $order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format')) : '',
		'payment_method'  => $order->get_payment_method_title() ? $order->get_payment_method_title() : __('N/A', 'consucorner'),
		'items_count'     => $order->get_item_count(),
		'total'           => consucorner_profile_format_price_text($order->get_formatted_order_total()),
		'track_url'       => consucorner_profile_get_order_track_url($order),
		'bosta_tracking_number' => $bosta_tracking,
		'bosta_tracking_url'    => $bosta_tracking ? consucorner_profile_get_bosta_tracking_url( $bosta_tracking ) : '',
		'billing_name'    => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
		'billing_email'   => $order->get_billing_email(),
		'billing_phone'   => $order->get_billing_phone(),
		'billing_address' => wp_strip_all_tags($order->get_formatted_billing_address()),
		'shipping_name'   => trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),
		'shipping_address' => wp_strip_all_tags($shipping_address),
		'items'           => $items,
		'totals'          => $totals,
	) + consucorner_profile_get_return_request_fields($order) + consucorner_profile_get_ops_status_fields($order) + (
		class_exists('Consucorner_Order_Cancel_Requests')
			? Consucorner_Order_Cancel_Requests::get_customer_payload($order)
			: array()
	);
}

/**
 * AJAX: get customer orders.
 */
function consucorner_profile_get_orders()
{
	$user_id = consucorner_profile_require_user();
	if (! function_exists('wc_get_orders')) {
		wp_send_json_success(array('orders' => array()));
	}

	$orders = wc_get_orders(array(
		'customer_id' => $user_id,
		'limit'       => 20,
		'orderby'     => 'date',
		'order'       => 'DESC',
	));

	$rows = array();
	foreach ($orders as $order) {
		$customer_status = class_exists('Consucorner_Order_Return_Workflow')
			? Consucorner_Order_Return_Workflow::get_customer_fulfillment_summary($order)
			: array('status' => $order->get_status(), 'label' => wc_get_order_status_name($order->get_status()));

		$rows[] = array_merge(
			array(
				'id'          => $order->get_id(),
				'number'      => $order->get_order_number(),
				'date'        => $order->get_date_created() ? $order->get_date_created()->date_i18n('d M Y') : '',
				'items_count' => $order->get_item_count(),
				'status'      => $order->get_status(),
				'status_name' => (string) ($customer_status['label'] ?? wc_get_order_status_name($order->get_status())),
				'total'       => consucorner_profile_format_price_text($order->get_formatted_order_total()),
				'track_url'   => consucorner_profile_get_order_track_url($order),
			),
			consucorner_profile_get_return_request_fields($order),
			consucorner_profile_get_ops_status_fields($order),
			class_exists('Consucorner_Order_Cancel_Requests')
				? Consucorner_Order_Cancel_Requests::get_customer_payload($order)
				: array()
		);
	}

	wp_send_json_success(array('orders' => $rows));
}
add_action('wp_ajax_consucorner_profile_get_orders', 'consucorner_profile_get_orders');

/**
 * AJAX: get a single order for the profile popup (logged-in or key-based access).
 */
function consucorner_profile_get_order()
{
	$data      = consucorner_profile_payload();
	$order_id  = isset($data['order_id']) ? absint($data['order_id']) : 0;
	$order_key = isset($data['order_key']) ? sanitize_text_field((string) $data['order_key']) : '';

	if (is_user_logged_in()) {
		check_ajax_referer('consucorner_profile_nonce', 'nonce');
	} elseif (! $order_key) {
		wp_send_json_error(array('message' => __('Order access key is required.', 'consucorner')), 403);
	}

	$user_id = is_user_logged_in() ? get_current_user_id() : 0;
	$order   = consucorner_profile_resolve_viewable_order($order_id, $order_key, $user_id);

	if (! $order) {
		wp_send_json_error(array('message' => __('Order not found.', 'consucorner')), 404);
	}

	wp_send_json_success(array('order' => consucorner_profile_format_order_detail($order)));
}
add_action('wp_ajax_consucorner_profile_get_order', 'consucorner_profile_get_order');
add_action('wp_ajax_nopriv_consucorner_profile_get_order', 'consucorner_profile_get_order');

/**
 * Guest order-tracking modal shell (login page + email deep links).
 *
 * @return string
 */
function consucorner_get_guest_order_track_modal_markup()
{
	ob_start();
?>
	<div class="pmodal" id="modal-order-track" aria-hidden="true">
		<div class="pmodal-backdrop"></div>
		<div class="pmodal-scroll">
			<div class="pmodal-dialog pmodal-wide" role="dialog" aria-modal="true" aria-labelledby="modal-order-track-title">
				<header class="pmodal-header">
					<div class="pmodal-header-inner">
						<span class="pmodal-hicon pmodal-hicon--indigo">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<circle cx="12" cy="12" r="9" />
								<polyline points="12 7 12 12 15.5 14" />
							</svg>
						</span>
						<div>
							<h2 class="pmodal-title" id="modal-order-track-title"><?php esc_html_e('Order Details', 'consucorner'); ?></h2>
							<p class="pmodal-subtitle"><?php esc_html_e('Track status and details of your order', 'consucorner'); ?></p>
						</div>
					</div>
					<button class="pmodal-close" type="button" aria-label="<?php esc_attr_e('Close dialog', 'consucorner'); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
							<path d="M18 6 6 18M6 6l12 12" />
						</svg>
					</button>
				</header>
				<div class="pmodal-body">
					<div id="guest-order-detail-content" class="porder-detail-content" aria-live="polite">
						<p class="porder-detail-loading"><?php esc_html_e('Loading order details...', 'consucorner'); ?></p>
					</div>
				</div>
				<footer class="pmodal-footer">
					<button type="button" class="pmodal-btn-ghost" data-dismiss><?php esc_html_e('Close', 'consucorner'); ?></button>
				</footer>
			</div>
		</div>
	</div>
<?php
	return (string) ob_get_clean();
}

/**
 * Keep order-tracking query args when WooCommerce redirects after login.
 *
 * @param string $redirect Default redirect URL.
 * @return string
 */
function consucorner_profile_preserve_order_link_on_login_redirect($redirect)
{
	$args      = consucorner_profile_order_link_query_args();
	$order_id  = isset($_GET[$args['order']]) ? absint(wp_unslash($_GET[$args['order']])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$order_key = isset($_GET[$args['key']]) ? sanitize_text_field(wp_unslash($_GET[$args['key']])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if (! $order_id || ! $order_key) {
		return $redirect;
	}

	$base = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');

	return add_query_arg(
		array(
			$args['order'] => $order_id,
			$args['key']   => $order_key,
		),
		$base
	);
}
add_filter('woocommerce_login_redirect', 'consucorner_profile_preserve_order_link_on_login_redirect', 20);
add_filter('woocommerce_registration_redirect', 'consucorner_profile_preserve_order_link_on_login_redirect', 20);

/**
 * AJAX: legacy instant cancel — disabled in favor of cancellation requests.
 */
function consucorner_profile_cancel_order()
{
	wp_send_json_error(array('message' => __('Please use Request cancellation instead.', 'consucorner')), 400);
}
add_action('wp_ajax_consucorner_profile_cancel_order', 'consucorner_profile_cancel_order');

/**
 * AJAX: get current customer wallet balance and transaction history.
 */
function consucorner_profile_get_wallet_data()
{
	$user_id = consucorner_profile_require_user();

	if (! function_exists('cc_get_custom_wallet_balance') || ! function_exists('cc_get_custom_wallet_transactions')) {
		wp_send_json_success(array(
			'balance_html'  => function_exists('wc_price') ? wc_price(0) : '0 EGP',
			'transactions'  => array(),
		));
	}

	$balance      = cc_get_custom_wallet_balance($user_id);
	$transactions = cc_get_custom_wallet_transactions($user_id, 50);
	$rows         = array();

	foreach ($transactions as $index => $entry) {
		$amount      = (float) ($entry['amount'] ?? 0);
		$balance_row = (float) ($entry['balance'] ?? 0);
		$order_id    = absint($entry['order_id'] ?? 0);
		$txn_id      = ! empty($entry['transaction_id']) ? (string) $entry['transaction_id'] : 'wallet-' . ($index + 1);
		$note        = wp_strip_all_tags((string) ($entry['note'] ?? ''));
		$type        = sanitize_key((string) ($entry['type'] ?? 'manual'));

		if ('' === $note) {
			$note = __('Wallet balance updated.', 'consucorner');
		}

		if ($order_id) {
			$note .= ' #' . $order_id;
		}

		$amount_text  = html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES, get_bloginfo('charset'));
		$balance_text = html_entity_decode(wp_strip_all_tags(wc_price($balance_row)), ENT_QUOTES, get_bloginfo('charset'));

		$rows[] = array(
			'id'           => $txn_id,
			'type'         => $type,
			'description'  => $note,
			'date'         => ! empty($entry['created_at']) ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $entry['created_at']) : '',
			'amount_html'  => ($amount > 0 ? '+' : '') . $amount_text,
			'amount_class' => $amount < 0 ? 'ptable-negative' : 'ptable-positive',
			'balance_html' => $balance_text,
			'order_id'     => $order_id,
		);
	}

	wp_send_json_success(array(
		'balance_html' => wp_kses_post(wc_price($balance)),
		'transactions' => $rows,
	));
}
add_action('wp_ajax_consucorner_profile_get_wallet_data', 'consucorner_profile_get_wallet_data');

/**
 * AJAX: return real WooCommerce products for the browser-stored wishlist IDs.
 */
function consucorner_profile_get_wishlist_products()
{
	consucorner_profile_require_user();
	$data = consucorner_profile_payload();
	$ids  = isset($data['ids']) && is_array($data['ids']) ? $data['ids'] : array();
	$ids  = array_values(array_unique(array_filter(array_map('absint', $ids))));

	if (empty($ids) || ! function_exists('wc_get_product')) {
		wp_send_json_success(array('products' => array()));
	}

	$products = array();
	foreach ($ids as $product_id) {
		$product = wc_get_product($product_id);
		if (! $product) {
			continue;
		}

		if ($product->is_type('variation')) {
			$parent_id = $product->get_parent_id();
			$parent    = $parent_id ? wc_get_product($parent_id) : false;
			if ($parent) {
				$product_id = $parent_id;
				$product    = $parent;
			}
		}

		$category_names = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
		$category       = ! is_wp_error($category_names) && ! empty($category_names) ? $category_names[0] : __('Product', 'consucorner');
		$image_id       = $product->get_image_id();
		$image_url      = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : wc_placeholder_img_src('woocommerce_thumbnail');
		$attributes     = array();

		foreach ($product->get_attributes() as $attribute) {
			if (count($attributes) >= 2) {
				break;
			}

			if ($attribute->is_taxonomy()) {
				$values = wc_get_product_terms($product_id, $attribute->get_name(), array('fields' => 'names'));
				if (! is_wp_error($values) && ! empty($values)) {
					$attributes[] = $values[0];
				}
				continue;
			}

			$options = $attribute->get_options();
			if (! empty($options[0])) {
				$attributes[] = $options[0];
			}
		}

		$products[] = array(
			'id'             => $product_id,
			'name'           => $product->get_name(),
			'permalink'      => get_permalink($product_id),
			'image'          => $image_url,
			'category'       => $category,
			'meta'           => implode(' · ', array_map('wp_strip_all_tags', $attributes)),
			'price_html'     => $product->get_price_html(),
			'is_purchasable' => $product->is_purchasable() && $product->is_in_stock(),
		);
	}

	wp_send_json_success(array('products' => $products));
}
add_action('wp_ajax_consucorner_profile_get_wishlist_products', 'consucorner_profile_get_wishlist_products');

/**
 * Notification preference meta keys.
 *
 * @return array<int,string>
 */
function consucorner_profile_notification_preference_keys()
{
	return array(
		'notif_order_confirmed',
		'notif_shipping',
		'notif_delivered',
		'notif_refund',
		'notif_login_alert',
		'notif_password_change',
		'notif_offers',
		'notif_new_products',
	);
}

/**
 * Save a named set of checkbox preferences.
 *
 * @param array $keys Preference meta keys.
 */
function consucorner_profile_save_preference_keys($keys)
{
	$user_id = consucorner_profile_require_user();
	$data    = consucorner_profile_payload();

	foreach ($keys as $key) {
		update_user_meta($user_id, $key, isset($data[$key]) ? '1' : '0');
	}

	wp_send_json_success(array(
		'message' => __('Preferences saved.', 'consucorner'),
		'profile' => consucorner_profile_data($user_id),
	));
}

/**
 * AJAX: save privacy preferences.
 */
function consucorner_profile_save_privacy()
{
	consucorner_profile_save_preference_keys(array('marketing_email_consent'));
}
add_action('wp_ajax_consucorner_profile_save_privacy', 'consucorner_profile_save_privacy');

/**
 * AJAX: save notification preferences.
 */
function consucorner_profile_save_notifications()
{
	consucorner_profile_save_preference_keys(consucorner_profile_notification_preference_keys());
}
add_action('wp_ajax_consucorner_profile_save_notifications', 'consucorner_profile_save_notifications');

/**
 * AJAX: change current user's password.
 */
function consucorner_profile_change_password()
{
	$user_id = consucorner_profile_require_user();
	$data    = consucorner_profile_payload();
	$user    = get_userdata($user_id);

	$current = isset($data['current_password']) ? (string) $data['current_password'] : '';
	$new     = isset($data['new_password']) ? (string) $data['new_password'] : '';
	$confirm = isset($data['confirm_password']) ? (string) $data['confirm_password'] : '';

	if (! $user || ! wp_check_password($current, $user->user_pass, $user_id)) {
		wp_send_json_error(array('message' => __('Current password is incorrect.', 'consucorner')), 400);
	}
	if (strlen($new) < 8 || $new !== $confirm) {
		wp_send_json_error(array('message' => __('Please enter matching passwords with at least 8 characters.', 'consucorner')), 400);
	}

	wp_set_password($new, $user_id);
	wp_set_current_user($user_id);
	wp_set_auth_cookie($user_id);

	wp_send_json_success(array('message' => __('Password updated successfully.', 'consucorner')));
}
add_action('wp_ajax_consucorner_profile_change_password', 'consucorner_profile_change_password');

/**
 * AJAX placeholders for non-core features.
 */
function consucorner_profile_simple_success()
{
	consucorner_profile_require_user();
	wp_send_json_success(array('message' => __('Request received. Our team will follow up shortly.', 'consucorner')));
}
add_action('wp_ajax_consucorner_profile_wallet_topup', 'consucorner_profile_simple_success');
add_action('wp_ajax_consucorner_profile_request_delete', 'consucorner_profile_simple_success');
/* `consucorner_profile_submit_report` was removed: the Report & Support
   form is now rendered server-side via Forminator (see the placeholder
   injected by consucorner_get_profile_template_partial()), so the legacy
   AJAX stub is no longer needed. */

/**
 * AJAX: Apply a WooCommerce coupon from the account page.
 *
 * Accepts POST: coupon_code, nonce.
 * Returns JSON success/error with a human-readable message.
 */
function consucorner_ajax_apply_coupon()
{
	consucorner_profile_require_user();
	check_ajax_referer('cc-apply-coupon', 'nonce');

	$coupon_code = sanitize_text_field(wp_unslash(isset($_POST['coupon_code']) ? $_POST['coupon_code'] : ''));

	if ('' === $coupon_code) {
		wp_send_json_error(array('message' => __('Please enter a coupon code.', 'consucorner')));
	}

	// Ensure the WooCommerce cart is loaded (not always initialised on account pages).
	if (function_exists('wc_load_cart') && (! WC()->cart || WC()->cart->is_empty() === null)) {
		wc_load_cart();
	}

	if (WC()->cart && WC()->cart->apply_coupon($coupon_code)) {
		wc_clear_notices();
		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: coupon code */
					__('Coupon "%s" applied successfully! It will be active at checkout.', 'consucorner'),
					esc_html(strtoupper($coupon_code))
				),
			)
		);
	} else {
		$notices = wc_get_notices('error');
		wc_clear_notices();
		$msg = ! empty($notices) && isset($notices[0]['notice'])
			? wp_strip_all_tags($notices[0]['notice'])
			: __('This coupon code is not valid or has already been applied.', 'consucorner');
		wp_send_json_error(array('message' => $msg));
	}

	wp_die();
}
add_action('wp_ajax_consucorner_apply_coupon', 'consucorner_ajax_apply_coupon');
