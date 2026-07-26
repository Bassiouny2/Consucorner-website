<?php
/**
 * Logs viewer page.
 *
 * @package Consucorner_Security
 *
 * @var array $summary Top-card summary from CCS_Stats::summary().
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ccs-wrap ccs-wrap--logs">
	<?php CCS_Admin::render_live_widget(); ?>

	<div class="ccs-header ccs-header--compact">
		<div>
			<h1><?php esc_html_e( 'Security Logs', 'consucorner-security' ); ?></h1>
			<p class="ccs-header__sub">
				<?php esc_html_e( 'Live stream of every blocked attack, suspicious request, and security event on your store.', 'consucorner-security' ); ?>
			</p>
		</div>
		<div class="ccs-header__actions">
			<button type="button" class="ccs-btn ccs-btn--ghost" data-action="refresh">
				<?php esc_html_e( 'Refresh', 'consucorner-security' ); ?>
			</button>
			<label class="ccs-autorefresh">
				<input type="checkbox" data-action="autorefresh" />
				<?php esc_html_e( 'Auto-refresh', 'consucorner-security' ); ?>
			</label>
		</div>
	</div>

	<div class="ccs-summary-grid">
		<div class="ccs-summary-card">
			<span class="ccs-summary-card__label"><?php esc_html_e( "Today's Events", 'consucorner-security' ); ?></span>
			<span class="ccs-summary-card__value" data-summary="today_events"><?php echo (int) $summary['today_events']; ?></span>
		</div>
		<div class="ccs-summary-card ccs-summary-card--warning">
			<span class="ccs-summary-card__label"><?php esc_html_e( 'Blocked Attacks (24h)', 'consucorner-security' ); ?></span>
			<span class="ccs-summary-card__value" data-summary="blocked_24h"><?php echo (int) $summary['blocked_24h']; ?></span>
		</div>
		<div class="ccs-summary-card">
			<span class="ccs-summary-card__label"><?php esc_html_e( 'Unique IPs (24h)', 'consucorner-security' ); ?></span>
			<span class="ccs-summary-card__value" data-summary="unique_ips"><?php echo (int) $summary['unique_ips']; ?></span>
		</div>
		<div class="ccs-summary-card ccs-summary-card--critical">
			<span class="ccs-summary-card__label"><?php esc_html_e( 'Top Threat', 'consucorner-security' ); ?></span>
			<span class="ccs-summary-card__value ccs-summary-card__value--small" data-summary="top_threat">
				<?php echo esc_html( $summary['top_threat'] ? CCS_REST_API::event_label( $summary['top_threat'] ) : '—' ); ?>
			</span>
		</div>
	</div>

	<form class="ccs-filters" data-ccs-logs-filters>
		<div class="ccs-filters__row">
			<label class="ccs-field">
				<span><?php esc_html_e( 'From', 'consucorner-security' ); ?></span>
				<input type="date" name="from" />
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'To', 'consucorner-security' ); ?></span>
				<input type="date" name="to" />
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Severity', 'consucorner-security' ); ?></span>
				<select name="severity">
					<option value=""><?php esc_html_e( 'All', 'consucorner-security' ); ?></option>
					<option value="info"><?php esc_html_e( 'Info', 'consucorner-security' ); ?></option>
					<option value="warning"><?php esc_html_e( 'Warning', 'consucorner-security' ); ?></option>
					<option value="critical"><?php esc_html_e( 'Critical', 'consucorner-security' ); ?></option>
				</select>
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Category', 'consucorner-security' ); ?></span>
				<select name="category">
					<option value=""><?php esc_html_e( 'All', 'consucorner-security' ); ?></option>
					<option value="bot"><?php esc_html_e( 'Bot', 'consucorner-security' ); ?></option>
					<option value="login"><?php esc_html_e( 'Login', 'consucorner-security' ); ?></option>
					<option value="firewall"><?php esc_html_e( 'Firewall', 'consucorner-security' ); ?></option>
					<option value="api"><?php esc_html_e( 'API', 'consucorner-security' ); ?></option>
					<option value="file"><?php esc_html_e( 'File', 'consucorner-security' ); ?></option>
					<option value="db"><?php esc_html_e( 'Database', 'consucorner-security' ); ?></option>
				</select>
			</label>
			<label class="ccs-field">
				<span><?php esc_html_e( 'Country', 'consucorner-security' ); ?></span>
				<input type="text" name="country" maxlength="2" placeholder="EG" />
			</label>
			<label class="ccs-field ccs-field--grow">
				<span><?php esc_html_e( 'IP / Search', 'consucorner-security' ); ?></span>
				<input type="search" name="search" placeholder="<?php esc_attr_e( 'IP, URI, user agent…', 'consucorner-security' ); ?>" />
			</label>
		</div>
		<div class="ccs-filters__row ccs-filters__row--actions">
			<button type="submit" class="ccs-btn ccs-btn--primary"><?php esc_html_e( 'Apply Filters', 'consucorner-security' ); ?></button>
			<button type="reset" class="ccs-btn ccs-btn--ghost"><?php esc_html_e( 'Reset', 'consucorner-security' ); ?></button>
			<button type="button" class="ccs-btn ccs-btn--ghost" data-action="export-csv"><?php esc_html_e( 'Export CSV', 'consucorner-security' ); ?></button>
		</div>
	</form>

	<div class="ccs-table-wrap">
		<table class="ccs-table widefat striped">
			<thead>
				<tr>
					<th class="ccs-col-sev"><?php esc_html_e( 'Severity', 'consucorner-security' ); ?></th>
					<th class="ccs-col-time"><?php esc_html_e( 'Time', 'consucorner-security' ); ?></th>
					<th><?php esc_html_e( 'Event', 'consucorner-security' ); ?></th>
					<th><?php esc_html_e( 'IP Address', 'consucorner-security' ); ?></th>
					<th><?php esc_html_e( 'Country', 'consucorner-security' ); ?></th>
					<th><?php esc_html_e( 'User Agent', 'consucorner-security' ); ?></th>
					<th><?php esc_html_e( 'Details', 'consucorner-security' ); ?></th>
					<th><?php esc_html_e( 'Action', 'consucorner-security' ); ?></th>
				</tr>
			</thead>
			<tbody data-ccs-logs-body>
				<tr><td colspan="8" class="ccs-table__empty"><?php esc_html_e( 'Loading logs…', 'consucorner-security' ); ?></td></tr>
			</tbody>
		</table>
	</div>

	<div class="ccs-pagination" data-ccs-pagination>
		<button type="button" class="ccs-btn ccs-btn--ghost" data-action="prev" disabled>← <?php esc_html_e( 'Previous', 'consucorner-security' ); ?></button>
		<span class="ccs-pagination__info" data-pagination-info>—</span>
		<button type="button" class="ccs-btn ccs-btn--ghost" data-action="next" disabled><?php esc_html_e( 'Next', 'consucorner-security' ); ?> →</button>
	</div>

	<!-- IP details drawer -->
	<aside class="ccs-drawer" data-ccs-ip-drawer aria-hidden="true">
		<header class="ccs-drawer__header">
			<h2 data-drawer-title>—</h2>
			<button type="button" class="ccs-drawer__close" data-action="close-drawer" aria-label="<?php esc_attr_e( 'Close', 'consucorner-security' ); ?>">×</button>
		</header>
		<div class="ccs-drawer__body" data-drawer-body>
			<p><?php esc_html_e( 'Loading IP info…', 'consucorner-security' ); ?></p>
		</div>
	</aside>

	<!-- Details modal -->
	<div class="ccs-modal" data-ccs-details-modal hidden>
		<div class="ccs-modal__panel">
			<header class="ccs-modal__header">
				<h2><?php esc_html_e( 'Event Details', 'consucorner-security' ); ?></h2>
				<button type="button" class="ccs-modal__close" data-action="close-modal" aria-label="<?php esc_attr_e( 'Close', 'consucorner-security' ); ?>">×</button>
			</header>
			<pre class="ccs-modal__pre" data-modal-body></pre>
		</div>
	</div>
</div>
