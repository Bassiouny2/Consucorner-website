<?php
/**
 * Plugin documentation page.
 *
 * @package Consucorner_Security
 *
 * @var array $registry
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ccs-wrap ccs-wrap--docs">
	<?php CCS_Admin::render_live_widget(); ?>

	<header class="ccs-header ccs-header--compact">
		<div class="ccs-header__brand">
			<h1><?php esc_html_e( 'ConsucCorner Security Documentation', 'consucorner-security' ); ?></h1>
			<p class="ccs-header__sub">
				<?php esc_html_e( 'A practical reference for changing rules, understanding toggles, and keeping GeIdeA, Dokan, Google, and SEO safe.', 'consucorner-security' ); ?>
			</p>
		</div>
	</header>

	<div class="ccs-docs-grid">
		<section class="ccs-doc-card">
			<h2><?php esc_html_e( 'Golden Rules', 'consucorner-security' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'Never block /wc-api/ or GeIdeA payment callbacks.', 'consucorner-security' ); ?></li>
				<li><?php esc_html_e( 'Never rate-limit /wp-json/dokan/, /wp-json/wc/, or /wp-json/wc-auth/.', 'consucorner-security' ); ?></li>
				<li><?php esc_html_e( 'Real Google bots must pass reverse + forward DNS and remain allowed.', 'consucorner-security' ); ?></li>
				<li><?php esc_html_e( 'Keep sitemap.xml, robots.txt, feeds, and .well-known paths crawlable.', 'consucorner-security' ); ?></li>
			</ul>
		</section>

		<section class="ccs-doc-card">
			<h2><?php esc_html_e( 'Where To Edit Rules', 'consucorner-security' ); ?></h2>
			<ul>
				<li><strong><?php esc_html_e( 'Bot Protection:', 'consucorner-security' ); ?></strong> <?php esc_html_e( 'Scraper user-agents, tool whitelist paths, empty user-agent behavior.', 'consucorner-security' ); ?></li>
				<li><strong><?php esc_html_e( 'Server Rules:', 'consucorner-security' ); ?></strong> <?php esc_html_e( 'Nginx rate values, blocked countries, custom Nginx snippets.', 'consucorner-security' ); ?></li>
				<li><strong><?php esc_html_e( 'Login Security:', 'consucorner-security' ); ?></strong> <?php esc_html_e( 'Custom login slug, brute-force attempts, lockout time, 2FA roles.', 'consucorner-security' ); ?></li>
				<li><strong><?php esc_html_e( 'Firewall:', 'consucorner-security' ); ?></strong> <?php esc_html_e( 'Upload size, SQL/XSS/path traversal patterns.', 'consucorner-security' ); ?></li>
				<li><strong><?php esc_html_e( 'Database & Files:', 'consucorner-security' ); ?></strong> <?php esc_html_e( 'Scan frequency, extra monitored paths, retention days, activity types.', 'consucorner-security' ); ?></li>
			</ul>
		</section>

		<section class="ccs-doc-card">
			<h2><?php esc_html_e( 'Implemented Pages', 'consucorner-security' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CCS_Admin::SLUG_LOGS ) ); ?>"><?php esc_html_e( 'Logs', 'consucorner-security' ); ?></a> — <?php esc_html_e( 'Security event table, filters, CSV export, IP drawer.', 'consucorner-security' ); ?></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CCS_Admin::SLUG_ANALYTICS ) ); ?>"><?php esc_html_e( 'Analytics', 'consucorner-security' ); ?></a> — <?php esc_html_e( 'Timeline, attack types, countries, heatmap, top IPs, score history.', 'consucorner-security' ); ?></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CCS_Admin::SLUG_NOTIFY ) ); ?>"><?php esc_html_e( 'Notifications', 'consucorner-security' ); ?></a> — <?php esc_html_e( 'Recipients, thresholds, templates, Telegram.', 'consucorner-security' ); ?></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CCS_Admin::SLUG_ADVANCED ) ); ?>"><?php esc_html_e( 'Advanced Settings', 'consucorner-security' ); ?></a> — <?php esc_html_e( 'Whitelists, blocked IPs, countries, rate rules, logs management.', 'consucorner-security' ); ?></li>
			</ul>
		</section>

		<section class="ccs-doc-card">
			<h2><?php esc_html_e( 'Runtime Engine Status', 'consucorner-security' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'Phase 1: Core dashboard and toggle system are active.', 'consucorner-security' ); ?></li>
				<li><?php esc_html_e( 'Phase 3: Logs, analytics, settings manager, notifications, IP manager, and REST endpoints are active.', 'consucorner-security' ); ?></li>
				<li><?php esc_html_e( 'Phase 4 Module 1: Bot Protection runtime engine is active when its toggles are enabled.', 'consucorner-security' ); ?></li>
				<li><?php esc_html_e( 'Remaining Phase 4 engines should be built one at a time and verified against GeIdeA checkout, Dokan vendor panel, and Google access.', 'consucorner-security' ); ?></li>
			</ul>
		</section>
	</div>

	<section class="ccs-doc-card ccs-doc-card--wide">
		<h2><?php esc_html_e( 'Module Toggle Reference', 'consucorner-security' ); ?></h2>
		<div class="ccs-table-wrap">
			<table class="ccs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Module', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Option', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Description', 'consucorner-security' ); ?></th>
						<th><?php esc_html_e( 'Default', 'consucorner-security' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $registry as $module ) : ?>
						<?php foreach ( $module['options'] as $option ) : ?>
							<tr>
								<td><?php echo esc_html( $module['label'] ); ?></td>
								<td><?php echo esc_html( $option['label'] ); ?></td>
								<td><?php echo esc_html( ! empty( $option['desc'] ) ? $option['desc'] : __( 'No description provided yet.', 'consucorner-security' ) ); ?></td>
								<td><?php echo ! empty( $option['default'] ) ? esc_html__( 'On', 'consucorner-security' ) : esc_html__( 'Off', 'consucorner-security' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
</div>
