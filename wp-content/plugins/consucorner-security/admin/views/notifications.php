<?php
/**
 * Email + Telegram notifications settings page.
 *
 * @package Consucorner_Security
 *
 * @var array $recipients
 * @var array $thresholds
 * @var array $templates
 * @var array $telegram
 */

defined( 'ABSPATH' ) || exit;

$alert_types = array(
	'critical_attack'   => __( 'Critical Attacks', 'consucorner-security' ),
	'daily_summary'     => __( 'Daily Summary', 'consucorner-security' ),
	'weekly_report'     => __( 'Weekly Report', 'consucorner-security' ),
	'new_vendor'        => __( 'New Vendor Registration', 'consucorner-security' ),
	'suspicious_order'  => __( 'Suspicious Orders', 'consucorner-security' ),
	'file_change'       => __( 'File Changes', 'consucorner-security' ),
);
?>
<div class="wrap ccs-wrap ccs-wrap--notifications">
	<?php CCS_Admin::render_live_widget(); ?>

	<div class="ccs-header ccs-header--compact">
		<div>
			<h1><?php esc_html_e( 'Notifications', 'consucorner-security' ); ?></h1>
			<p class="ccs-header__sub">
				<?php esc_html_e( 'Choose who gets notified, when, and how. Email + Telegram, both with full template control.', 'consucorner-security' ); ?>
			</p>
		</div>
	</div>

	<!-- Recipients -->
	<section class="ccs-card ccs-card--padded" data-section="recipients">
		<header class="ccs-card__header">
			<h2><?php esc_html_e( 'Email Recipients', 'consucorner-security' ); ?></h2>
			<button type="button" class="ccs-btn ccs-btn--ghost" data-action="add-recipient">+ <?php esc_html_e( 'Add Recipient', 'consucorner-security' ); ?></button>
		</header>

		<div class="ccs-table-wrap">
			<table class="ccs-table">
				<thead>
					<tr>
						<th style="width:30%"><?php esc_html_e( 'Email', 'consucorner-security' ); ?></th>
						<th style="width:20%"><?php esc_html_e( 'Name', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Alert types', 'consucorner-security' ); ?></th>
						<th style="width:80px;text-align:right"></th>
					</tr>
				</thead>
				<tbody data-recipients-body>
					<?php if ( empty( $recipients ) ) : ?>
						<tr data-empty><td colspan="4" class="ccs-table__empty"><?php esc_html_e( 'No recipients yet. Add one to start receiving alerts.', 'consucorner-security' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $recipients as $i => $r ) : ?>
							<tr data-recipient-row data-index="<?php echo esc_attr( (string) $i ); ?>">
								<td><input type="email" data-field="email" value="<?php echo esc_attr( $r['email'] ); ?>" /></td>
								<td><input type="text" data-field="name" value="<?php echo esc_attr( $r['name'] ); ?>" /></td>
								<td>
									<div class="ccs-checkbox-grid">
										<?php foreach ( $alert_types as $type_key => $type_label ) : ?>
											<label class="ccs-chip-check">
												<input type="checkbox" data-type="<?php echo esc_attr( $type_key ); ?>" <?php checked( in_array( $type_key, $r['types'], true ) ); ?> />
												<span><?php echo esc_html( $type_label ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</td>
								<td style="text-align:right">
									<button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--danger" data-action="remove-recipient"><?php esc_html_e( 'Remove', 'consucorner-security' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<template data-recipient-template>
			<tr data-recipient-row data-index="__INDEX__">
				<td><input type="email" data-field="email" value="" placeholder="alerts@example.com" /></td>
				<td><input type="text" data-field="name" value="" /></td>
				<td>
					<div class="ccs-checkbox-grid">
						<?php foreach ( $alert_types as $type_key => $type_label ) : ?>
							<label class="ccs-chip-check">
								<input type="checkbox" data-type="<?php echo esc_attr( $type_key ); ?>" <?php echo 'critical_attack' === $type_key ? 'checked' : ''; ?> />
								<span><?php echo esc_html( $type_label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</td>
				<td style="text-align:right">
					<button type="button" class="ccs-btn ccs-btn--ghost ccs-btn--danger" data-action="remove-recipient"><?php esc_html_e( 'Remove', 'consucorner-security' ); ?></button>
				</td>
			</tr>
		</template>

		<footer class="ccs-card__footer">
			<button type="button" class="ccs-btn ccs-btn--primary" data-action="save-recipients"><?php esc_html_e( 'Save Recipients', 'consucorner-security' ); ?></button>
			<button type="button" class="ccs-btn ccs-btn--ghost" data-action="test-email"><?php esc_html_e( 'Send Test Email', 'consucorner-security' ); ?></button>
		</footer>
	</section>

	<!-- Thresholds -->
	<section class="ccs-card ccs-card--padded" data-section="thresholds">
		<header class="ccs-card__header">
			<h2><?php esc_html_e( 'Alert Thresholds', 'consucorner-security' ); ?></h2>
		</header>
		<div class="ccs-fields-grid">
			<label class="ccs-field">
				<span><?php esc_html_e( 'Brute-force attempts', 'consucorner-security' ); ?></span>
				<input type="number" min="1" max="100" data-field="brute_force_attempts" value="<?php echo (int) $thresholds['brute_force_attempts']; ?>" />
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Within (minutes)', 'consucorner-security' ); ?></span>
				<input type="number" min="1" max="1440" data-field="brute_force_minutes" value="<?php echo (int) $thresholds['brute_force_minutes']; ?>" />
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Rate-limit requests', 'consucorner-security' ); ?></span>
				<input type="number" min="1" max="10000" data-field="rate_limit_requests" value="<?php echo (int) $thresholds['rate_limit_requests']; ?>" />
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Within (minutes)', 'consucorner-security' ); ?></span>
				<input type="number" min="1" max="1440" data-field="rate_limit_minutes" value="<?php echo (int) $thresholds['rate_limit_minutes']; ?>" />
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Score drop below', 'consucorner-security' ); ?></span>
				<input type="number" min="0" max="100" data-field="score_drop_below" value="<?php echo (int) $thresholds['score_drop_below']; ?>" />
			</label>
			<label class="ccs-field ccs-field--toggle">
				<span><?php esc_html_e( 'Alert on new country', 'consucorner-security' ); ?></span>
				<input type="checkbox" data-field="new_country_alert" <?php checked( ! empty( $thresholds['new_country_alert'] ) ); ?> />
			</label>
		</div>
		<footer class="ccs-card__footer">
			<button type="button" class="ccs-btn ccs-btn--primary" data-action="save-thresholds"><?php esc_html_e( 'Save Thresholds', 'consucorner-security' ); ?></button>
		</footer>
	</section>

	<!-- Templates -->
	<section class="ccs-card ccs-card--padded" data-section="templates">
		<header class="ccs-card__header">
			<h2><?php esc_html_e( 'Email Templates', 'consucorner-security' ); ?></h2>
			<span class="ccs-card__hint"><?php esc_html_e( 'Available variables: {site_name}, {ip}, {event}, {time}, {details}', 'consucorner-security' ); ?></span>
		</header>

		<?php foreach ( $templates as $key => $tmpl ) : ?>
			<div class="ccs-template-block" data-template-key="<?php echo esc_attr( $key ); ?>">
				<h3><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></h3>
				<label class="ccs-field">
					<span><?php esc_html_e( 'Subject', 'consucorner-security' ); ?></span>
					<input type="text" data-field="subject" value="<?php echo esc_attr( $tmpl['subject'] ); ?>" />
				</label>
				<label class="ccs-field">
					<span><?php esc_html_e( 'Body', 'consucorner-security' ); ?></span>
					<textarea data-field="body" rows="5"><?php echo esc_textarea( $tmpl['body'] ); ?></textarea>
				</label>
			</div>
		<?php endforeach; ?>

		<footer class="ccs-card__footer">
			<button type="button" class="ccs-btn ccs-btn--primary" data-action="save-templates"><?php esc_html_e( 'Save Templates', 'consucorner-security' ); ?></button>
		</footer>
	</section>

	<!-- Telegram -->
	<section class="ccs-card ccs-card--padded" data-section="telegram">
		<header class="ccs-card__header">
			<h2><?php esc_html_e( 'Telegram Notifications', 'consucorner-security' ); ?> <span class="ccs-pill ccs-pill--soft"><?php esc_html_e( 'Optional', 'consucorner-security' ); ?></span></h2>
		</header>
		<div class="ccs-fields-grid">
			<label class="ccs-field ccs-field--toggle">
				<span><?php esc_html_e( 'Enable Telegram alerts', 'consucorner-security' ); ?></span>
				<input type="checkbox" data-field="enabled" <?php checked( ! empty( $telegram['enabled'] ) ); ?> />
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Bot Token', 'consucorner-security' ); ?></span>
				<input type="text" data-field="bot_token" value="<?php echo esc_attr( $telegram['bot_token'] ); ?>" autocomplete="off" />
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Chat ID', 'consucorner-security' ); ?></span>
				<input type="text" data-field="chat_id" value="<?php echo esc_attr( $telegram['chat_id'] ); ?>" autocomplete="off" />
			</label>
		</div>
		<div class="ccs-field">
			<span><?php esc_html_e( 'Forward these events to Telegram', 'consucorner-security' ); ?></span>
			<div class="ccs-checkbox-grid">
				<?php foreach ( array_keys( $templates ) as $event_key ) : ?>
					<label class="ccs-chip-check">
						<input type="checkbox" data-tg-event="<?php echo esc_attr( $event_key ); ?>" <?php checked( in_array( $event_key, (array) $telegram['events'], true ) ); ?> />
						<span><?php echo esc_html( ucwords( str_replace( '_', ' ', $event_key ) ) ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<footer class="ccs-card__footer">
			<button type="button" class="ccs-btn ccs-btn--primary" data-action="save-telegram"><?php esc_html_e( 'Save Telegram', 'consucorner-security' ); ?></button>
			<button type="button" class="ccs-btn ccs-btn--ghost" data-action="test-telegram"><?php esc_html_e( 'Test Connection', 'consucorner-security' ); ?></button>
		</footer>
	</section>
</div>
