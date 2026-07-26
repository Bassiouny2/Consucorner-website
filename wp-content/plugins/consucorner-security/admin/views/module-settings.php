<?php
/**
 * Single module settings view.
 *
 * @package Consucorner_Security
 * @var string $module_id
 * @var array  $module
 * @var int    $score
 * @var int    $issues
 * @var string $status
 */

defined( 'ABSPATH' ) || exit;

$dashboard_url = admin_url( 'admin.php?page=' . CCS_Admin::MENU_SLUG );
$module_on     = CCS_Options::is_module_fully_enabled( $module_id );
?>
<div class="wrap ccs-wrap ccs-wrap--module">
	<p class="ccs-back">
		<a href="<?php echo esc_url( $dashboard_url ); ?>">&larr; <?php esc_html_e( 'Back to Security Dashboard', 'consucorner-security' ); ?></a>
	</p>

	<header class="ccs-header ccs-header--compact">
		<div class="ccs-header__brand">
			<h1><?php echo esc_html( $module['label'] ); ?></h1>
			<p class="ccs-header__sub"><?php echo esc_html( $module['description'] ); ?></p>
		</div>
		<div class="ccs-header__status">
			<span class="ccs-score__value ccs-score__value--inline" data-ccs-score><?php echo esc_html( (string) $score ); ?></span>
			<span class="ccs-score__label"><?php esc_html_e( 'Score', 'consucorner-security' ); ?></span>
		</div>
	</header>

	<div class="ccs-module-toolbar">
		<label class="ccs-toggle ccs-toggle--master">
			<input
				type="checkbox"
				class="ccs-toggle__input"
				data-ccs-module-toggle
				data-module-id="<?php echo esc_attr( $module_id ); ?>"
				<?php checked( $module_on ); ?> />
			<span class="ccs-toggle__track" aria-hidden="true"></span>
			<span class="ccs-toggle__label"><?php esc_html_e( 'Enable entire module', 'consucorner-security' ); ?></span>
		</label>
	</div>

	<div class="ccs-options-list">
		<?php foreach ( $module['options'] as $key => $meta ) : ?>
			<?php
			$enabled  = CCS_Options::get( $key );
			$is_crit  = ! empty( $meta['critical'] );
			$badge    = isset( $meta['badge'] ) ? $meta['badge'] : '';
			?>
			<div class="ccs-option-row<?php echo $is_crit ? ' ccs-option-row--critical' : ''; ?>" data-ccs-option-row="<?php echo esc_attr( $key ); ?>">
				<div class="ccs-option-row__body">
					<h3 class="ccs-option-row__title">
						<?php echo esc_html( $meta['label'] ); ?>
						<?php if ( $badge ) : ?>
							<?php echo CCS_Admin::render_badge( $badge ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</h3>
					<?php if ( ! empty( $meta['desc'] ) ) : ?>
						<p class="ccs-option-row__desc"><?php echo esc_html( $meta['desc'] ); ?></p>
					<?php endif; ?>
					<?php if ( $is_crit ) : ?>
						<p class="ccs-option-row__warn"><?php esc_html_e( 'Disabling this may break Google indexing, Dokan vendors, or GeIdeA payments.', 'consucorner-security' ); ?></p>
					<?php endif; ?>
				</div>
				<label class="ccs-toggle">
					<input
						type="checkbox"
						class="ccs-toggle__input"
						data-ccs-option-toggle
						data-option-key="<?php echo esc_attr( $key ); ?>"
						data-critical="<?php echo $is_crit ? '1' : '0'; ?>"
						<?php checked( $enabled ); ?>
						<?php disabled( $is_crit && $enabled ); ?> />
					<span class="ccs-toggle__track" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php echo esc_html( $meta['label'] ); ?></span>
				</label>
			</div>
		<?php endforeach; ?>
	</div>

	<section class="ccs-module-config-card" data-ccs-module-config data-module-id="<?php echo esc_attr( $module_id ); ?>">
		<header class="ccs-module-config-card__header">
			<div>
				<h2><?php esc_html_e( 'Rules & Configuration', 'consucorner-security' ); ?></h2>
				<p><?php esc_html_e( 'Edit the behavior behind this module without touching code. Changes are saved in wp_options and used by the runtime engines as they are implemented.', 'consucorner-security' ); ?></p>
			</div>
			<button type="button" class="button button-primary" data-ccs-save-module-settings>
				<?php esc_html_e( 'Save Configuration', 'consucorner-security' ); ?>
			</button>
		</header>

		<?php include CCS_PLUGIN_DIR . 'admin/views/module-extra-settings.php'; ?>
	</section>

	<section class="ccs-module-config-card ccs-module-chart-card" data-ccs-module-chart-wrap data-module-id="<?php echo esc_attr( $module_id ); ?>">
		<header class="ccs-module-config-card__header">
			<div>
				<h2><?php esc_html_e( 'Module Activity', 'consucorner-security' ); ?></h2>
				<p><?php esc_html_e( 'A quick 7-day view of security events so you can see whether this module is catching anything.', 'consucorner-security' ); ?></p>
			</div>
		</header>
		<div class="ccs-module-chart">
			<canvas data-ccs-module-chart height="150"></canvas>
		</div>
	</section>

	<p class="ccs-toast" data-ccs-toast hidden role="status"></p>
</div>
