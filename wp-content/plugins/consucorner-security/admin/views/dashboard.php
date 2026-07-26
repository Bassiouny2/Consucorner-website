<?php
/**
 * Security dashboard view.
 *
 * @package Consucorner_Security
 * @var int    $score
 * @var int    $issues
 * @var string $status
 * @var array  $registry
 */

defined( 'ABSPATH' ) || exit;

$status_label = 'issues' === $status
	? __( 'Issues Found', 'consucorner-security' )
	: __( 'Protected', 'consucorner-security' );
?>
<div class="wrap ccs-wrap" id="ccs-dashboard">
	<?php CCS_Admin::render_live_widget(); ?>
	<header class="ccs-header">
		<div class="ccs-header__brand">
			<h1><?php esc_html_e( 'ConsucCorner Security', 'consucorner-security' ); ?></h1>
			<p class="ccs-header__sub"><?php esc_html_e( 'WooCommerce marketplace protection — Dokan & GeIdeA safe.', 'consucorner-security' ); ?></p>
		</div>
		<div class="ccs-header__status">
			<span class="ccs-status-pill ccs-status-pill--<?php echo esc_attr( $status ); ?>" data-ccs-status-pill>
				<?php echo esc_html( $status_label ); ?>
			</span>
			<div class="ccs-score" data-ccs-score-ring>
				<span class="ccs-score__value" data-ccs-score><?php echo esc_html( (string) $score ); ?></span>
				<span class="ccs-score__label"><?php esc_html_e( 'Security Score', 'consucorner-security' ); ?></span>
			</div>
		</div>
	</header>

	<?php if ( $issues > 0 ) : ?>
		<div class="ccs-notice ccs-notice--warning">
			<?php
			printf(
				/* translators: %d: number of critical issues */
				esc_html( _n( '%d critical protection is disabled.', '%d critical protections are disabled.', $issues, 'consucorner-security' ) ),
				(int) $issues
			);
			?>
		</div>
	<?php endif; ?>

	<section class="ccs-cards" aria-label="<?php esc_attr_e( 'Security modules', 'consucorner-security' ); ?>">
		<?php foreach ( $registry as $module_id => $module ) : ?>
			<?php
			$module_on    = CCS_Options::is_module_fully_enabled( $module_id );
			$settings_url = admin_url( 'admin.php?page=ccs-' . $module['slug'] );
			?>
			<article class="ccs-card" data-ccs-module-card="<?php echo esc_attr( $module_id ); ?>">
				<div class="ccs-card__icon ccs-card__icon--<?php echo esc_attr( $module['icon'] ); ?>" aria-hidden="true"></div>
				<h2 class="ccs-card__title"><?php echo esc_html( $module['label'] ); ?></h2>
				<p class="ccs-card__desc"><?php echo esc_html( $module['description'] ); ?></p>

				<div class="ccs-card__actions">
					<label class="ccs-toggle ccs-toggle--master">
						<input
							type="checkbox"
							class="ccs-toggle__input"
							data-ccs-module-toggle
							data-module-id="<?php echo esc_attr( $module_id ); ?>"
							<?php checked( $module_on ); ?> />
						<span class="ccs-toggle__track" aria-hidden="true"></span>
						<span class="ccs-toggle__label"><?php esc_html_e( 'Enable module', 'consucorner-security' ); ?></span>
					</label>
					<a class="button button-secondary ccs-card__settings" href="<?php echo esc_url( $settings_url ); ?>">
						<?php esc_html_e( 'Settings', 'consucorner-security' ); ?>
					</a>
				</div>
			</article>
		<?php endforeach; ?>
	</section>

	<section class="ccs-guide">
		<h2><?php esc_html_e( 'Server Configuration Guide (Cloudways)', 'consucorner-security' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Enable Redis or Varnish in Cloudways → Application Settings.', 'consucorner-security' ); ?></li>
			<li><?php esc_html_e( 'Set PHP memory limit to at least 256MB.', 'consucorner-security' ); ?></li>
			<li><?php esc_html_e( 'Phase 2: generate Nginx rules from Server Rules and paste into Cloudways → Server → Nginx Configuration.', 'consucorner-security' ); ?></li>
			<li><?php esc_html_e( 'Phase 2: deploy Fail2Ban jail config via SSH when available.', 'consucorner-security' ); ?></li>
		</ol>
		<p class="ccs-guide__cta">
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CCS_Admin::SLUG_DOCS ) ); ?>">
				<?php esc_html_e( 'Open Plugin Documentation', 'consucorner-security' ); ?>
			</a>
		</p>
	</section>

	<p class="ccs-toast" data-ccs-toast hidden role="status"></p>
</div>
