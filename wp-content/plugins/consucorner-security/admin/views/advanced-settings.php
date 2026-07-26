<?php
/**
 * Advanced security settings page.
 *
 * @package Consucorner_Security
 *
 * @var array        $whitelist_ips
 * @var array        $whitelist_domains
 * @var array        $whitelist_users
 * @var array<string,string> $country_rules
 * @var array        $rate_limit_rules
 * @var string       $nginx_rules
 * @var array        $logs_settings
 * @var array        $blocked_ips
 */

defined( 'ABSPATH' ) || exit;

$roles = wp_roles()->get_names();
?>
<div class="wrap ccs-wrap ccs-wrap--advanced">
	<?php CCS_Admin::render_live_widget(); ?>

	<div class="ccs-header ccs-header--compact">
		<div>
			<h1><?php esc_html_e( 'Advanced Settings', 'consucorner-security' ); ?></h1>
			<p class="ccs-header__sub">
				<?php esc_html_e( 'Whitelists, blocklists, country rules, custom rate limits, and log retention — all editable from here.', 'consucorner-security' ); ?>
			</p>
		</div>
	</div>

	<!-- IP Whitelist -->
	<section class="ccs-card ccs-card--padded" data-section="whitelist-ips">
		<header class="ccs-card__header">
			<h2><?php esc_html_e( 'IP Whitelist', 'consucorner-security' ); ?></h2>
		</header>
		<form class="ccs-inline-form" data-form="add-whitelist-ip" onsubmit="return false;">
			<input type="text" data-field="ip" placeholder="<?php esc_attr_e( 'IP or CIDR (e.g. 1.2.3.0/24)', 'consucorner-security' ); ?>" />
			<input type="text" data-field="label" placeholder="<?php esc_attr_e( 'Label / reason', 'consucorner-security' ); ?>" />
			<button type="button" class="ccs-btn ccs-btn--primary" data-action="add-whitelist-ip">+ <?php esc_html_e( 'Add', 'consucorner-security' ); ?></button>
		</form>
		<div class="ccs-table-wrap">
			<table class="ccs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'IP / Range', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Label', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Added', 'consucorner-security' ); ?></th>
						<th style="text-align:right;width:90px"></th>
					</tr>
				</thead>
				<tbody data-table="whitelist-ips">
					<?php if ( empty( $whitelist_ips ) ) : ?>
						<tr><td colspan="4" class="ccs-table__empty"><?php esc_html_e( 'No whitelisted IPs yet.', 'consucorner-security' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $whitelist_ips as $row ) : ?>
							<tr data-ip="<?php echo esc_attr( $row['ip_address'] ); ?>">
								<td><code><?php echo esc_html( $row['ip_address'] ); ?></code></td>
								<td><?php echo esc_html( $row['label'] ); ?></td>
								<td><?php echo esc_html( $row['created_at'] ); ?></td>
								<td style="text-align:right">
									<button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--danger" data-action="remove-whitelist-ip"><?php esc_html_e( 'Remove', 'consucorner-security' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>

	<!-- Blocked IPs -->
	<section class="ccs-card ccs-card--padded" data-section="blocked-ips">
		<header class="ccs-card__header">
			<h2><?php esc_html_e( 'Blocked IPs', 'consucorner-security' ); ?></h2>
			<div class="ccs-card__header-actions">
				<button type="button" class="ccs-btn ccs-btn--ghost" data-action="clear-expired"><?php esc_html_e( 'Clear Expired', 'consucorner-security' ); ?></button>
			</div>
		</header>
		<div class="ccs-table-wrap">
			<table class="ccs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'IP', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Country', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Source', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Blocked at', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Expires', 'consucorner-security' ); ?></th>
						<th style="text-align:right;width:110px"></th>
					</tr>
				</thead>
				<tbody data-table="blocked-ips">
					<?php if ( empty( $blocked_ips['rows'] ) ) : ?>
						<tr><td colspan="7" class="ccs-table__empty"><?php esc_html_e( 'No blocked IPs.', 'consucorner-security' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $blocked_ips['rows'] as $row ) : ?>
							<tr data-ip="<?php echo esc_attr( $row['ip_address'] ); ?>">
								<td><code><?php echo esc_html( $row['ip_address'] ); ?></code></td>
								<td><?php echo esc_html( (string) $row['reason'] ); ?></td>
								<td><?php echo esc_html( (string) $row['country_code'] ); ?></td>
								<td><?php echo esc_html( $row['source'] ); ?></td>
								<td><?php echo esc_html( $row['created_at'] ); ?></td>
								<td><?php echo esc_html( $row['blocked_until'] ? $row['blocked_until'] : __( 'Permanent', 'consucorner-security' ) ); ?></td>
								<td style="text-align:right">
									<button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--danger" data-action="unblock-ip"><?php esc_html_e( 'Unblock', 'consucorner-security' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>

	<!-- Domain Whitelist -->
	<section class="ccs-card ccs-card--padded" data-section="whitelist-domains">
		<header class="ccs-card__header">
			<h2><?php esc_html_e( 'Domain Whitelist', 'consucorner-security' ); ?></h2>
			<button type="button" class="ccs-btn ccs-btn--ghost" data-action="add-domain">+ <?php esc_html_e( 'Add Domain', 'consucorner-security' ); ?></button>
		</header>
		<div class="ccs-table-wrap">
			<table class="ccs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Domain', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'consucorner-security' ); ?></th>
						<th style="text-align:right;width:90px"></th>
					</tr>
				</thead>
				<tbody data-table="whitelist-domains">
					<?php if ( empty( $whitelist_domains ) ) : ?>
						<tr data-empty><td colspan="3" class="ccs-table__empty"><?php esc_html_e( 'No domains.', 'consucorner-security' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $whitelist_domains as $i => $r ) : ?>
							<tr data-row-index="<?php echo (int) $i; ?>">
								<td><input type="text" data-field="domain" value="<?php echo esc_attr( $r['domain'] ); ?>" /></td>
								<td><input type="text" data-field="reason" value="<?php echo esc_attr( $r['reason'] ); ?>" /></td>
								<td style="text-align:right"><button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--danger" data-action="remove-row"><?php esc_html_e( 'Remove', 'consucorner-security' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<footer class="ccs-card__footer">
			<button type="button" class="ccs-btn ccs-btn--primary" data-action="save-domains"><?php esc_html_e( 'Save Domains', 'consucorner-security' ); ?></button>
		</footer>
	</section>

	<!-- User Whitelist -->
	<section class="ccs-card ccs-card--padded" data-section="whitelist-users">
		<header class="ccs-card__header">
			<h2><?php esc_html_e( 'User Whitelist', 'consucorner-security' ); ?> <span class="ccs-card__hint"><?php esc_html_e( '(skip security checks for these accounts)', 'consucorner-security' ); ?></span></h2>
			<button type="button" class="ccs-btn ccs-btn--ghost" data-action="add-user">+ <?php esc_html_e( 'Add User', 'consucorner-security' ); ?></button>
		</header>
		<div class="ccs-table-wrap">
			<table class="ccs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Username', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Role', 'consucorner-security' ); ?></th>
						<th style="text-align:right;width:90px"></th>
					</tr>
				</thead>
				<tbody data-table="whitelist-users">
					<?php if ( empty( $whitelist_users ) ) : ?>
						<tr data-empty><td colspan="3" class="ccs-table__empty"><?php esc_html_e( 'No whitelisted users.', 'consucorner-security' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $whitelist_users as $i => $r ) : ?>
							<tr data-row-index="<?php echo (int) $i; ?>">
								<td><input type="text" data-field="username" value="<?php echo esc_attr( $r['username'] ); ?>" /></td>
								<td>
									<select data-field="role">
										<option value=""><?php esc_html_e( 'Any role', 'consucorner-security' ); ?></option>
										<?php foreach ( $roles as $slug => $label ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $r['role'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td style="text-align:right"><button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--danger" data-action="remove-row"><?php esc_html_e( 'Remove', 'consucorner-security' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<footer class="ccs-card__footer">
			<button type="button" class="ccs-btn ccs-btn--primary" data-action="save-users"><?php esc_html_e( 'Save Users', 'consucorner-security' ); ?></button>
		</footer>
	</section>

	<!-- Country Rules -->
	<section class="ccs-card ccs-card--padded" data-section="country-rules">
		<header class="ccs-card__header">
			<h2><?php esc_html_e( 'Country Rules', 'consucorner-security' ); ?></h2>
		</header>
		<div class="ccs-warning">
			<?php esc_html_e( 'Warning: Never block Google IPs (automatically enforced).', 'consucorner-security' ); ?>
		</div>
		<div class="ccs-presets">
			<button type="button" class="ccs-btn ccs-btn--ghost" data-action="preset-egypt-only"><?php esc_html_e( 'Egypt-Only Mode', 'consucorner-security' ); ?></button>
			<button type="button" class="ccs-btn ccs-btn--ghost" data-action="preset-high-risk"><?php esc_html_e( 'Block High-Risk Countries', 'consucorner-security' ); ?></button>
			<button type="button" class="ccs-btn ccs-btn--ghost" data-action="preset-allow-all"><?php esc_html_e( 'Allow All', 'consucorner-security' ); ?></button>
		</div>
		<div class="ccs-table-wrap">
			<table class="ccs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Country', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Action', 'consucorner-security' ); ?></th>
						<th style="text-align:right;width:90px"></th>
					</tr>
				</thead>
				<tbody data-table="country-rules">
					<?php
					$country_rows = $country_rules;
					if ( empty( $country_rows ) ) :
					?>
						<tr data-empty><td colspan="3" class="ccs-table__empty"><?php esc_html_e( 'No country rules.', 'consucorner-security' ); ?></td></tr>
					<?php else : foreach ( $country_rows as $code => $action ) : ?>
						<tr data-country="<?php echo esc_attr( $code ); ?>">
							<td><input type="text" data-field="code" maxlength="2" value="<?php echo esc_attr( $code ); ?>" style="width:80px;text-transform:uppercase" /></td>
							<td>
								<select data-field="action">
									<option value="allow" <?php selected( $action, 'allow' ); ?>><?php esc_html_e( 'Allow', 'consucorner-security' ); ?></option>
									<option value="block" <?php selected( $action, 'block' ); ?>><?php esc_html_e( 'Block', 'consucorner-security' ); ?></option>
									<option value="challenge" <?php selected( $action, 'challenge' ); ?>><?php esc_html_e( 'Challenge (CAPTCHA)', 'consucorner-security' ); ?></option>
								</select>
							</td>
							<td style="text-align:right"><button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--danger" data-action="remove-row"><?php esc_html_e( 'Remove', 'consucorner-security' ); ?></button></td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<footer class="ccs-card__footer">
			<button type="button" class="ccs-btn ccs-btn--ghost" data-action="add-country">+ <?php esc_html_e( 'Add Country', 'consucorner-security' ); ?></button>
			<button type="button" class="ccs-btn ccs-btn--primary" data-action="save-country-rules"><?php esc_html_e( 'Save Country Rules', 'consucorner-security' ); ?></button>
		</footer>
	</section>

	<!-- Rate-limit rules -->
	<section class="ccs-card ccs-card--padded" data-section="rate-rules">
		<header class="ccs-card__header">
			<h2><?php esc_html_e( 'Rate Limiting Rules', 'consucorner-security' ); ?></h2>
			<button type="button" class="ccs-btn ccs-btn--ghost" data-action="add-rate-rule">+ <?php esc_html_e( 'Add Rule', 'consucorner-security' ); ?></button>
		</header>
		<div class="ccs-table-wrap">
			<table class="ccs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'URL Pattern', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Req / min', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Burst', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Whitelist Roles', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Action', 'consucorner-security' ); ?></th>
						<th style="text-align:right;width:90px"></th>
					</tr>
				</thead>
				<tbody data-table="rate-rules">
					<?php if ( empty( $rate_limit_rules ) ) : ?>
						<tr data-empty><td colspan="6" class="ccs-table__empty"><?php esc_html_e( 'No custom rules. Default Nginx limits still apply.', 'consucorner-security' ); ?></td></tr>
					<?php else : foreach ( $rate_limit_rules as $i => $rule ) : ?>
						<tr data-row-index="<?php echo (int) $i; ?>">
							<td><input type="text" data-field="url_pattern" value="<?php echo esc_attr( $rule['url_pattern'] ); ?>" /></td>
							<td><input type="number" min="0" data-field="requests_per_min" value="<?php echo (int) $rule['requests_per_min']; ?>" /></td>
							<td><input type="number" min="0" data-field="burst" value="<?php echo (int) $rule['burst']; ?>" /></td>
							<td><input type="text" data-field="whitelist_roles" value="<?php echo esc_attr( implode( ',', (array) $rule['whitelist_roles'] ) ); ?>" placeholder="customer,admin" /></td>
							<td>
								<select data-field="action">
									<option value="block" <?php selected( $rule['action'], 'block' ); ?>><?php esc_html_e( 'Block', 'consucorner-security' ); ?></option>
									<option value="allow" <?php selected( $rule['action'], 'allow' ); ?>><?php esc_html_e( 'Allow', 'consucorner-security' ); ?></option>
									<option value="challenge" <?php selected( $rule['action'], 'challenge' ); ?>><?php esc_html_e( 'Challenge', 'consucorner-security' ); ?></option>
								</select>
							</td>
							<td style="text-align:right"><button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--danger" data-action="remove-row"><?php esc_html_e( 'Remove', 'consucorner-security' ); ?></button></td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<footer class="ccs-card__footer">
			<button type="button" class="ccs-btn ccs-btn--primary" data-action="save-rate-rules"><?php esc_html_e( 'Save Rules', 'consucorner-security' ); ?></button>
		</footer>
	</section>

	<!-- Custom Nginx rules -->
	<section class="ccs-card ccs-card--padded" data-section="nginx-rules">
		<header class="ccs-card__header">
			<h2><?php esc_html_e( 'Custom Nginx Rules', 'consucorner-security' ); ?></h2>
			<span class="ccs-card__hint"><?php esc_html_e( 'Advanced users only — these are appended to your generated server block.', 'consucorner-security' ); ?></span>
		</header>
		<textarea data-field="nginx-rules" rows="10" class="ccs-monospace" style="width:100%"><?php echo esc_textarea( $nginx_rules ); ?></textarea>
		<footer class="ccs-card__footer">
			<button type="button" class="ccs-btn ccs-btn--ghost" data-action="validate-nginx"><?php esc_html_e( 'Validate', 'consucorner-security' ); ?></button>
			<button type="button" class="ccs-btn ccs-btn--primary" data-action="save-nginx"><?php esc_html_e( 'Save Rules', 'consucorner-security' ); ?></button>
		</footer>
	</section>

	<!-- Logs management -->
	<section class="ccs-card ccs-card--padded" data-section="logs-management">
		<header class="ccs-card__header">
			<h2><?php esc_html_e( 'Logs Management', 'consucorner-security' ); ?></h2>
		</header>
		<div class="ccs-fields-grid">
			<label class="ccs-field">
				<span><?php esc_html_e( 'Retention (days)', 'consucorner-security' ); ?></span>
				<input type="number" min="7" max="365" data-field="retention_days" value="<?php echo (int) $logs_settings['retention_days']; ?>" />
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Max log size (MB)', 'consucorner-security' ); ?></span>
				<input type="number" min="5" max="1024" data-field="max_size_mb" value="<?php echo (int) $logs_settings['max_size_mb']; ?>" />
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Log level', 'consucorner-security' ); ?></span>
				<select data-field="level">
					<option value="all" <?php selected( $logs_settings['level'], 'all' ); ?>><?php esc_html_e( 'All Events', 'consucorner-security' ); ?></option>
					<option value="warnings" <?php selected( $logs_settings['level'], 'warnings' ); ?>><?php esc_html_e( 'Warnings & Critical', 'consucorner-security' ); ?></option>
					<option value="critical" <?php selected( $logs_settings['level'], 'critical' ); ?>><?php esc_html_e( 'Critical Only', 'consucorner-security' ); ?></option>
				</select>
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Sample rate (%)', 'consucorner-security' ); ?></span>
				<select data-field="sample_rate">
					<option value="100" <?php selected( (int) $logs_settings['sample_rate'], 100 ); ?>>100%</option>
					<option value="50" <?php selected( (int) $logs_settings['sample_rate'], 50 ); ?>>50%</option>
					<option value="10" <?php selected( (int) $logs_settings['sample_rate'], 10 ); ?>>10%</option>
				</select>
			</label>
			<label class="ccs-field ccs-field--toggle">
				<span><?php esc_html_e( 'Auto-clean old logs', 'consucorner-security' ); ?></span>
				<input type="checkbox" data-field="auto_clean" <?php checked( ! empty( $logs_settings['auto_clean'] ) ); ?> />
			</label>
			<label class="ccs-field ccs-field--toggle">
				<span><?php esc_html_e( 'Async logging (recommended)', 'consucorner-security' ); ?></span>
				<input type="checkbox" data-field="async_logging" <?php checked( ! empty( $logs_settings['async_logging'] ) ); ?> />
			</label>
		</div>
		<footer class="ccs-card__footer">
			<button type="button" class="ccs-btn ccs-btn--primary" data-action="save-logs-mgmt"><?php esc_html_e( 'Save Logs Settings', 'consucorner-security' ); ?></button>
			<button type="button" class="ccs-btn ccs-btn--ghost" data-action="clean-logs"><?php esc_html_e( 'Clean Old Logs Now', 'consucorner-security' ); ?></button>
			<button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--danger" data-action="clear-all-logs"><?php esc_html_e( 'Clear ALL Logs', 'consucorner-security' ); ?></button>
		</footer>
	</section>
</div>
