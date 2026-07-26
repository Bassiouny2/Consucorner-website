<?php
/**
 * Analytics / Charts page.
 *
 * @package Consucorner_Security
 *
 * @var int $score Current security score.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ccs-wrap ccs-wrap--analytics">
	<?php CCS_Admin::render_live_widget(); ?>

	<div class="ccs-header ccs-header--compact">
		<div>
			<h1><?php esc_html_e( 'Security Analytics', 'consucorner-security' ); ?></h1>
			<p class="ccs-header__sub">
				<?php esc_html_e( 'Interactive charts powered by your security logs. Spot patterns, attack windows, and threat sources.', 'consucorner-security' ); ?>
			</p>
		</div>
		<div class="ccs-header__actions ccs-range-switch" data-ccs-range>
			<button type="button" data-range="24h"><?php esc_html_e( '24h', 'consucorner-security' ); ?></button>
			<button type="button" data-range="7d" class="is-active"><?php esc_html_e( '7 Days', 'consucorner-security' ); ?></button>
			<button type="button" data-range="30d"><?php esc_html_e( '30 Days', 'consucorner-security' ); ?></button>
			<button type="button" data-range="90d"><?php esc_html_e( '90 Days', 'consucorner-security' ); ?></button>
		</div>
	</div>

	<div class="ccs-charts-grid">
		<section class="ccs-chart-card ccs-chart-card--wide">
			<header>
				<h2><?php esc_html_e( 'Events Timeline', 'consucorner-security' ); ?></h2>
				<span class="ccs-chart-card__hint"><?php esc_html_e( 'Critical / Warning / Info events over time.', 'consucorner-security' ); ?></span>
			</header>
			<div class="ccs-chart-canvas">
				<canvas data-chart="timeline" height="220"></canvas>
			</div>
		</section>

		<section class="ccs-chart-card">
			<header>
				<h2><?php esc_html_e( 'Attack Types', 'consucorner-security' ); ?></h2>
			</header>
			<div class="ccs-chart-canvas ccs-chart-canvas--donut">
				<canvas data-chart="types" height="220"></canvas>
			</div>
			<ul class="ccs-chart-legend" data-chart-legend="types"></ul>
		</section>

		<section class="ccs-chart-card">
			<header>
				<h2><?php esc_html_e( 'Top Attacking Countries', 'consucorner-security' ); ?></h2>
			</header>
			<div class="ccs-chart-canvas">
				<canvas data-chart="countries" height="260"></canvas>
			</div>
		</section>

		<section class="ccs-chart-card ccs-chart-card--wide">
			<header>
				<h2><?php esc_html_e( 'Hourly Heatmap (7 × 24)', 'consucorner-security' ); ?></h2>
				<span class="ccs-chart-card__hint"><?php esc_html_e( 'Darker cells = busier hours.', 'consucorner-security' ); ?></span>
			</header>
			<div class="ccs-heatmap" data-chart="heatmap"></div>
		</section>

		<section class="ccs-chart-card ccs-chart-card--wide">
			<header>
				<h2><?php esc_html_e( 'Top Attacking IPs', 'consucorner-security' ); ?></h2>
			</header>
			<div class="ccs-table-wrap">
				<table class="ccs-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'IP', 'consucorner-security' ); ?></th>
							<th><?php esc_html_e( 'Country', 'consucorner-security' ); ?></th>
							<th><?php esc_html_e( 'Attempts', 'consucorner-security' ); ?></th>
							<th><?php esc_html_e( 'Volume', 'consucorner-security' ); ?></th>
							<th><?php esc_html_e( 'Last seen', 'consucorner-security' ); ?></th>
							<th><?php esc_html_e( 'Status', 'consucorner-security' ); ?></th>
							<th><?php esc_html_e( 'Action', 'consucorner-security' ); ?></th>
						</tr>
					</thead>
					<tbody data-chart="top-ips">
						<tr><td colspan="7" class="ccs-table__empty"><?php esc_html_e( 'Loading…', 'consucorner-security' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</section>

		<section class="ccs-chart-card ccs-chart-card--wide">
			<header>
				<h2><?php esc_html_e( 'Security Score (30 days)', 'consucorner-security' ); ?></h2>
				<span class="ccs-chart-card__hint">
					<?php
					printf(
						/* translators: %d: current security score */
						esc_html__( 'Current: %d / 100', 'consucorner-security' ),
						(int) $score
					);
					?>
				</span>
			</header>
			<div class="ccs-chart-canvas">
				<canvas data-chart="score" height="180"></canvas>
			</div>
		</section>
	</div>
</div>
