<?php
/**
 * Live security widget shown above every Phase 3 page.
 *
 * @package Consucorner_Security
 */

defined( 'ABSPATH' ) || exit;

$ccs_feed = CCS_Stats::live_feed();
$totals    = $ccs_feed['totals'];
$is_alert  = $totals['blocked'] > 0;
?>
<div class="ccs-live-widget<?php echo $is_alert ? ' is-alert' : ''; ?>" data-ccs-live-widget>
	<div class="ccs-live-widget__main">
		<span class="ccs-live-widget__icon" aria-hidden="true">
			<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
		</span>
		<div class="ccs-live-widget__text">
			<strong data-live-summary>
				<?php
				/* translators: %d: number of blocked events in the last hour. */
				printf( esc_html( _n( 'Last hour: %d attack blocked', 'Last hour: %d attacks blocked', (int) $totals['blocked'], 'consucorner-security' ) ), (int) $totals['blocked'] );
				?>
			</strong>
			<span data-live-breakdown>
				<?php
				printf(
					/* translators: 1: bots, 2: brute force, 3: firewall */
					esc_html__( '%1$d bots · %2$d brute-force · %3$d firewall', 'consucorner-security' ),
					(int) $totals['bot_blocked'],
					(int) $totals['brute_force'],
					(int) $totals['firewall']
				);
				?>
			</span>
		</div>
	</div>
	<a class="ccs-live-widget__cta" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CCS_Admin::SLUG_LOGS ) ); ?>">
		<?php esc_html_e( 'View Details', 'consucorner-security' ); ?> →
	</a>
</div>
