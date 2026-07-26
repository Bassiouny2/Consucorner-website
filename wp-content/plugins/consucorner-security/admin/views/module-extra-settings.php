<?php
/**
 * Editable rule panels for each module page.
 *
 * @package Consucorner_Security
 *
 * @var string $module_id
 * @var array  $module_settings
 */

defined( 'ABSPATH' ) || exit;

/**
 * Helper for textarea values that may be stored as arrays.
 *
 * @param mixed $value Value.
 * @return string
 */
$ccs_lines = static function ( $value ) {
	return is_array( $value ) ? implode( "\n", $value ) : (string) $value;
};

switch ( $module_id ) :
	case 'bot':
		?>
		<div class="ccs-config-grid">
			<label class="ccs-field ccs-field--full">
				<span><?php esc_html_e( 'Scrapers blacklist', 'consucorner-security' ); ?></span>
				<textarea rows="11" data-config-field="scrapers_list"><?php echo esc_textarea( $ccs_lines( $module_settings['scrapers_list'] ) ); ?></textarea>
				<small><?php esc_html_e( 'One user-agent fragment per line. Example: AhrefsBot, SemrushBot, python-requests. Google bots are hard-coded safe and cannot be removed.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field ccs-field--full">
				<span><?php esc_html_e( 'Tool whitelist paths', 'consucorner-security' ); ?></span>
				<textarea rows="4" data-config-field="curl_whitelist_paths"><?php echo esc_textarea( $ccs_lines( $module_settings['curl_whitelist_paths'] ) ); ?></textarea>
				<small><?php esc_html_e( 'curl/wget/PHP SDK requests are allowed on these paths. Keep /wc-api/ for GeIdeA and /wp-json/dokan/ for vendors.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Empty User-Agent action', 'consucorner-security' ); ?></span>
				<select data-config-field="empty_ua_action">
					<option value="block" <?php selected( $module_settings['empty_ua_action'], 'block' ); ?>><?php esc_html_e( 'Block and log', 'consucorner-security' ); ?></option>
					<option value="log" <?php selected( $module_settings['empty_ua_action'], 'log' ); ?>><?php esc_html_e( 'Only log', 'consucorner-security' ); ?></option>
				</select>
				<small><?php esc_html_e( 'Blocking is safer. Use log-only temporarily if a legitimate integration is being identified.', 'consucorner-security' ); ?></small>
			</label>
		</div>
		<?php
		break;

	case 'server':
		?>
		<div class="ccs-config-grid">
			<label class="ccs-field">
				<span><?php esc_html_e( 'General requests / minute', 'consucorner-security' ); ?></span>
				<input type="number" min="1" max="1000" data-config-field="general_rate" value="<?php echo esc_attr( (string) $module_settings['general_rate'] ); ?>" />
				<small><?php esc_html_e( 'Nginx zone for normal pages. Conservative default: 60/min.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Login requests / minute', 'consucorner-security' ); ?></span>
				<input type="number" min="1" max="100" data-config-field="login_rate" value="<?php echo esc_attr( (string) $module_settings['login_rate'] ); ?>" />
				<small><?php esc_html_e( 'Strict limit for wp-login.php. Default: 5/min.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Checkout requests / minute', 'consucorner-security' ); ?></span>
				<input type="number" min="1" max="100" data-config-field="checkout_rate" value="<?php echo esc_attr( (string) $module_settings['checkout_rate'] ); ?>" />
				<small><?php esc_html_e( 'Anti-carding throttle. Do not set too low for campaigns.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'API requests / minute', 'consucorner-security' ); ?></span>
				<input type="number" min="1" max="1000" data-config-field="api_rate" value="<?php echo esc_attr( (string) $module_settings['api_rate'] ); ?>" />
				<small><?php esc_html_e( 'Dokan, WooCommerce, and GeIdeA API paths remain excluded.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field ccs-field--full">
				<span><?php esc_html_e( 'Blocked countries', 'consucorner-security' ); ?></span>
				<input type="text" data-config-field="blocked_countries" value="<?php echo esc_attr( implode( ', ', (array) $module_settings['blocked_countries'] ) ); ?>" placeholder="RU, CN, KP" />
				<small><?php esc_html_e( 'Comma-separated ISO codes. Do not block EG unless you really mean it.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field ccs-field--full">
				<span><?php esc_html_e( 'Custom Nginx rules', 'consucorner-security' ); ?></span>
				<textarea rows="10" data-config-field="custom_nginx_rules"><?php echo esc_textarea( $module_settings['custom_nginx_rules'] ); ?></textarea>
				<small><?php esc_html_e( 'Advanced: appended to generated Cloudways/Nginx guidance. Validate before applying on the server.', 'consucorner-security' ); ?></small>
			</label>
		</div>
		<?php
		break;

	case 'login':
		?>
		<div class="ccs-config-grid">
			<label class="ccs-field">
				<span><?php esc_html_e( 'Custom login URL slug', 'consucorner-security' ); ?></span>
				<input type="text" data-config-field="custom_url" value="<?php echo esc_attr( $module_settings['custom_url'] ); ?>" placeholder="my-store-enter" />
				<small><?php esc_html_e( 'Example: my-store-enter. Avoid obvious words like admin, login, signin.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Max failed attempts', 'consucorner-security' ); ?></span>
				<input type="number" min="1" max="50" data-config-field="max_attempts" value="<?php echo esc_attr( (string) $module_settings['max_attempts'] ); ?>" />
				<small><?php esc_html_e( 'Number of failed logins before temporary lockout.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Lockout minutes', 'consucorner-security' ); ?></span>
				<input type="number" min="1" max="1440" data-config-field="lockout_minutes" value="<?php echo esc_attr( (string) $module_settings['lockout_minutes'] ); ?>" />
				<small><?php esc_html_e( 'How long the login lockout lasts after too many attempts.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Permanent block after', 'consucorner-security' ); ?></span>
				<input type="number" min="1" max="100" data-config-field="permanent_after" value="<?php echo esc_attr( (string) $module_settings['permanent_after'] ); ?>" />
				<small><?php esc_html_e( 'Escalates repeated abuse to the blocked IP manager.', 'consucorner-security' ); ?></small>
			</label>
			<div class="ccs-field ccs-field--full">
				<span><?php esc_html_e( 'Roles requiring 2FA', 'consucorner-security' ); ?></span>
				<div class="ccs-checkbox-grid" data-config-array="twofa_roles">
					<?php foreach ( wp_roles()->get_names() as $role => $label ) : ?>
						<label class="ccs-chip-check">
							<input type="checkbox" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $module_settings['twofa_roles'], true ) ); ?> />
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<small><?php esc_html_e( 'Recommended: administrator, shop manager, and vendor roles.', 'consucorner-security' ); ?></small>
			</div>
		</div>
		<?php
		break;

	case 'firewall':
		?>
		<div class="ccs-config-grid">
			<label class="ccs-field">
				<span><?php esc_html_e( 'Max upload size (MB)', 'consucorner-security' ); ?></span>
				<input type="number" min="1" max="256" data-config-field="max_upload_size" value="<?php echo esc_attr( (string) $module_settings['max_upload_size'] ); ?>" />
				<small><?php esc_html_e( 'Used by malicious upload protection. Keep realistic for product images/docs.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field ccs-field--full">
				<span><?php esc_html_e( 'SQL injection patterns', 'consucorner-security' ); ?></span>
				<textarea rows="6" data-config-field="sql_patterns"><?php echo esc_textarea( $ccs_lines( $module_settings['sql_patterns'] ) ); ?></textarea>
				<small><?php esc_html_e( 'One pattern or keyword per line. Use carefully to avoid false positives in product descriptions.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field ccs-field--full">
				<span><?php esc_html_e( 'XSS patterns', 'consucorner-security' ); ?></span>
				<textarea rows="6" data-config-field="xss_patterns"><?php echo esc_textarea( $ccs_lines( $module_settings['xss_patterns'] ) ); ?></textarea>
				<small><?php esc_html_e( 'Script/iframe/event-handler markers. Users with unfiltered_html should be exempt in runtime code.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field ccs-field--full">
				<span><?php esc_html_e( 'Path traversal patterns', 'consucorner-security' ); ?></span>
				<textarea rows="6" data-config-field="traversal_patterns"><?php echo esc_textarea( $ccs_lines( $module_settings['traversal_patterns'] ) ); ?></textarea>
				<small><?php esc_html_e( 'Protects against ../, .env, wp-config.php, and server file probing.', 'consucorner-security' ); ?></small>
			</label>
		</div>
		<?php
		break;

	case 'database':
		?>
		<div class="ccs-config-grid">
			<label class="ccs-field">
				<span><?php esc_html_e( 'File scan frequency', 'consucorner-security' ); ?></span>
				<select data-config-field="scan_frequency">
					<?php foreach ( array( 'hourly', 'twicedaily', 'daily', 'weekly' ) as $freq ) : ?>
						<option value="<?php echo esc_attr( $freq ); ?>" <?php selected( $module_settings['scan_frequency'], $freq ); ?>><?php echo esc_html( ucfirst( $freq ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<small><?php esc_html_e( 'Daily is best for performance. Hourly can be heavy on shared resources.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Log retention days', 'consucorner-security' ); ?></span>
				<input type="number" min="7" max="365" data-config-field="retention_days" value="<?php echo esc_attr( (string) $module_settings['retention_days'] ); ?>" />
				<small><?php esc_html_e( 'Controls how long security logs are kept before cleanup.', 'consucorner-security' ); ?></small>
			</label>
			<label class="ccs-field ccs-field--full">
				<span><?php esc_html_e( 'Extra files/directories to monitor', 'consucorner-security' ); ?></span>
				<textarea rows="6" data-config-field="extra_monitor_paths"><?php echo esc_textarea( $ccs_lines( $module_settings['extra_monitor_paths'] ) ); ?></textarea>
				<small><?php esc_html_e( 'One absolute path per line. Use only paths inside this WordPress install unless you know the server layout.', 'consucorner-security' ); ?></small>
			</label>
			<div class="ccs-field ccs-field--full">
				<span><?php esc_html_e( 'Activity events to monitor', 'consucorner-security' ); ?></span>
				<div class="ccs-checkbox-grid" data-config-array="activity_events">
					<?php
					$events = array(
						'plugin_changes' => __( 'Plugin changes', 'consucorner-security' ),
						'theme_changes'  => __( 'Theme changes', 'consucorner-security' ),
						'user_changes'   => __( 'User/role changes', 'consucorner-security' ),
						'order_changes'  => __( 'Order changes', 'consucorner-security' ),
					);
					foreach ( $events as $event_key => $event_label ) :
						?>
						<label class="ccs-chip-check">
							<input type="checkbox" value="<?php echo esc_attr( $event_key ); ?>" <?php checked( in_array( $event_key, (array) $module_settings['activity_events'], true ) ); ?> />
							<span><?php echo esc_html( $event_label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
		break;

	default:
		?>
		<p class="ccs-empty-config"><?php esc_html_e( 'This module currently uses the toggle settings above. More editable controls will appear here as the runtime engine expands.', 'consucorner-security' ); ?></p>
		<?php
		break;
endswitch;
?>
