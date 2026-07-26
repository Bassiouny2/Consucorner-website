<?php
/**
 * Admin UI under Tools → Order Migration.
 *
 * @package ConsuCorner_Order_Migration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard settings + import runner.
 */
class CC_Order_Migration_Admin {

	const PAGE_SLUG = 'cc-order-migration';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_cc_order_migration_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_cc_order_migration_run', array( __CLASS__, 'handle_run' ) );
		add_action( 'admin_post_cc_order_migration_test', array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_cc_order_migration_sync_dates', array( __CLASS__, 'handle_sync_dates' ) );
		add_action( 'admin_post_cc_order_migration_sync_attribution', array( __CLASS__, 'handle_sync_attribution' ) );
		add_action( 'admin_post_cc_order_migration_purge_trash', array( __CLASS__, 'handle_purge_trash' ) );
	}

	/**
	 * @return void
	 */
	public static function register_menu() {
		add_management_page(
			__( 'Order Migration', 'consucorner-order-migration' ),
			__( 'Order Migration', 'consucorner-order-migration' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * @return string
	 */
	private static function page_url() {
		return admin_url( 'tools.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'consucorner-order-migration' ) );
		}

		$settings     = CC_Order_Migration_Config::get_raw();
		$configured   = CC_Order_Migration_Config::is_configured();
		$migrated         = function_exists( 'wc_get_orders' ) ? CC_Order_Migrator::count_migrated() : 0;
		$dates_remaining  = function_exists( 'wc_get_orders' ) ? CC_Order_Migrator::count_date_repair_remaining() : 0;
		$all_statuses = array( 'completed', 'processing', 'on-hold', 'pending', 'cancelled', 'refunded', 'failed' );
		$has_secret   = ! empty( $settings['consumer_secret'] );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ConsuCorner Order Migration', 'consucorner-order-migration' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Import orders from the source WooCommerce site into this site. Products match by SKU, customers by billing email, and Dokan vendor sub-orders are rebuilt once per parent order (no duplicates).', 'consucorner-order-migration' ); ?>
			</p>

			<?php self::render_notice(); ?>

			<table class="widefat striped" style="max-width:640px;margin:16px 0;">
				<tbody>
					<tr>
						<th style="width:220px;"><?php esc_html_e( 'Connection', 'consucorner-order-migration' ); ?></th>
						<td>
							<?php if ( $configured ) : ?>
								<span style="color:#1a7f37;font-weight:600;">&#10003; <?php esc_html_e( 'Credentials saved', 'consucorner-order-migration' ); ?></span>
							<?php else : ?>
								<span style="color:#b32d2e;font-weight:600;">&#10007; <?php esc_html_e( 'Not configured yet', 'consucorner-order-migration' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr><th><?php esc_html_e( 'Source URL', 'consucorner-order-migration' ); ?></th><td><?php echo esc_html( $settings['source_url'] ? $settings['source_url'] : '—' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Orders imported on this site', 'consucorner-order-migration' ); ?></th><td><strong><?php echo esc_html( (string) $migrated ); ?></strong></td></tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( '1. API Settings', 'consucorner-order-migration' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cc_order_migration_save', 'cc_order_migration_save_nonce' ); ?>
				<input type="hidden" name="action" value="cc_order_migration_save" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cc_source_url"><?php esc_html_e( 'Source site URL', 'consucorner-order-migration' ); ?></label></th>
						<td>
							<input type="url" name="source_url" id="cc_source_url" class="regular-text" value="<?php echo esc_attr( $settings['source_url'] ); ?>" placeholder="https://example.cloudwaysapps.com" required />
							<p class="description"><?php esc_html_e( 'The old/source WooCommerce site you are importing FROM.', 'consucorner-order-migration' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cc_consumer_key"><?php esc_html_e( 'Consumer key', 'consucorner-order-migration' ); ?></label></th>
						<td>
							<input type="text" name="consumer_key" id="cc_consumer_key" class="regular-text" value="<?php echo esc_attr( $settings['consumer_key'] ); ?>" placeholder="ck_..." autocomplete="off" required />
							<p class="description"><?php esc_html_e( 'WooCommerce → Settings → Advanced → REST API on the source site (Read permission).', 'consucorner-order-migration' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cc_consumer_secret"><?php esc_html_e( 'Consumer secret', 'consucorner-order-migration' ); ?></label></th>
						<td>
							<input type="password" name="consumer_secret" id="cc_consumer_secret" class="regular-text" value="" placeholder="<?php echo $has_secret ? esc_attr__( '•••••••• (leave blank to keep saved secret)', 'consucorner-order-migration' ) : 'cs_...'; ?>" autocomplete="off" oninput="document.getElementById('cc_secret_changed').value='1';" />
							<input type="hidden" name="consumer_secret_changed" id="cc_secret_changed" value="" />
							<?php if ( $has_secret ) : ?>
								<p class="description"><?php esc_html_e( 'A secret is already saved. Leave blank to keep it, or type a new one to replace.', 'consucorner-order-migration' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Order statuses', 'consucorner-order-migration' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( $all_statuses as $status ) : ?>
									<label style="display:inline-block;min-width:150px;margin-bottom:4px;">
										<input type="checkbox" name="statuses[]" value="<?php echo esc_attr( $status ); ?>" <?php checked( in_array( $status, (array) $settings['statuses'], true ) ); ?> />
										<?php echo esc_html( $status ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Only orders with these statuses will be imported.', 'consucorner-order-migration' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cc_per_page"><?php esc_html_e( 'Per API request', 'consucorner-order-migration' ); ?></label></th>
						<td><input type="number" name="per_page" id="cc_per_page" min="1" max="100" class="small-text" value="<?php echo esc_attr( (string) $settings['per_page'] ); ?>" /> <span class="description"><?php esc_html_e( '(max 100)', 'consucorner-order-migration' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><label for="cc_sleep"><?php esc_html_e( 'Delay between pages (sec)', 'consucorner-order-migration' ); ?></label></th>
						<td><input type="number" name="sleep_seconds" id="cc_sleep" min="0" step="0.5" class="small-text" value="<?php echo esc_attr( (string) $settings['sleep_seconds'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'consucorner-order-migration' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="parent_only" value="1" <?php checked( ! empty( $settings['parent_only'] ) ); ?> /> <?php esc_html_e( 'Import parent orders only (rebuild Dokan sub-orders here) — recommended', 'consucorner-order-migration' ); ?></label>
							<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="skip_source_trash" value="1" <?php checked( ! empty( $settings['skip_source_trash'] ) ); ?> /> <?php esc_html_e( 'Skip trashed orders on source', 'consucorner-order-migration' ); ?></label>
							<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="fetch_full_order" value="1" <?php checked( ! empty( $settings['fetch_full_order'] ) ); ?> /> <?php esc_html_e( 'Fetch full order details (recommended)', 'consucorner-order-migration' ); ?></label>
						</td>
					</tr>
				</table>
				<p>
					<?php submit_button( __( 'Save settings', 'consucorner-order-migration' ), 'primary', 'submit', false ); ?>
					<button type="submit" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" name="cc_test_after_save" value="1" class="button button-secondary"><?php esc_html_e( 'Save & test connection', 'consucorner-order-migration' ); ?></button>
				</p>
			</form>

			<hr />

			<h2><?php esc_html_e( '2. Import Orders', 'consucorner-order-migration' ); ?></h2>
			<?php if ( ! $configured ) : ?>
				<p class="description" style="color:#b32d2e;"><?php esc_html_e( 'Save your API settings above before importing.', 'consucorner-order-migration' ); ?></p>
			<?php endif; ?>
			<div class="notice notice-warning inline" style="max-width:760px;padding:8px 12px;">
				<p><strong><?php esc_html_e( 'Avoid duplicates:', 'consucorner-order-migration' ); ?></strong>
				<?php esc_html_e( 'Already-imported orders are skipped automatically. If you trashed bad imports, purge them below before re-running.', 'consucorner-order-migration' ); ?></p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cc_order_migration_run', 'cc_order_migration_nonce' ); ?>
				<input type="hidden" name="action" value="cc_order_migration_run" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cc_max"><?php esc_html_e( 'Orders to import now', 'consucorner-order-migration' ); ?></label></th>
						<td>
							<input type="number" name="max_orders" id="cc_max" value="20" min="1" max="200" class="small-text" />
							<p class="description"><?php esc_html_e( 'Process this many orders per click. Repeat until all are imported. Use a small number first to test.', 'consucorner-order-migration' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Dry run', 'consucorner-order-migration' ); ?></th>
						<td><label><input type="checkbox" name="dry_run" value="1" /> <?php esc_html_e( 'Preview only — do not create orders', 'consucorner-order-migration' ); ?></label></td>
					</tr>
				</table>
				<p>
					<button type="submit" class="button button-primary button-hero" <?php disabled( ! $configured ); ?>>
						&#11015; <?php esc_html_e( 'Import Now', 'consucorner-order-migration' ); ?>
					</button>
				</p>
			</form>

			<hr />

			<h2><?php esc_html_e( '3. Repair existing imports', 'consucorner-order-migration' ); ?></h2>
			<p class="description" style="max-width:760px;">
				<?php
				printf(
					/* translators: 1: batch size, 2: remaining count */
					esc_html__( 'Re-fetch migrated orders from the source site and restore original created, paid, and completed dates. Processes %1$d orders per click — only orders imported by this plugin are updated. %2$d orders still waiting for date repair.', 'consucorner-order-migration' ),
					(int) CC_Order_Migrator::DATE_REPAIR_BATCH_SIZE,
					(int) $dates_remaining
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( sprintf( __( 'Repair dates for the next %d migrated orders from the source site?', 'consucorner-order-migration' ), (int) CC_Order_Migrator::DATE_REPAIR_BATCH_SIZE ) ); ?>');">
				<?php wp_nonce_field( 'cc_order_migration_sync_dates', 'cc_order_migration_sync_dates_nonce' ); ?>
				<input type="hidden" name="action" value="cc_order_migration_sync_dates" />
				<?php
				submit_button(
					sprintf(
						/* translators: %d: batch size */
						__( 'Fix next %d migrated order dates', 'consucorner-order-migration' ),
						(int) CC_Order_Migrator::DATE_REPAIR_BATCH_SIZE
					),
					'secondary',
					'submit',
					false,
					$dates_remaining > 0 ? array() : array( 'disabled' => 'disabled' )
				);
				?>
			</form>
			<p class="description" style="max-width:760px;margin-top:16px;">
				<?php esc_html_e( 'Use this once to fetch each old source order again and copy WooCommerce Origin / order attribution data to the migrated order and its Dokan sub-orders.', 'consucorner-order-migration' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Sync WooCommerce order attribution from source orders now? This may take time because it calls the source API for each migrated order.', 'consucorner-order-migration' ) ); ?>');">
				<?php wp_nonce_field( 'cc_order_migration_sync_attribution', 'cc_order_migration_sync_attribution_nonce' ); ?>
				<input type="hidden" name="action" value="cc_order_migration_sync_attribution" />
				<?php submit_button( __( 'Fix migrated order origin', 'consucorner-order-migration' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( '4. Clean up', 'consucorner-order-migration' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Permanently delete all TRASHED orders created by this migration?', 'consucorner-order-migration' ) ); ?>');">
				<?php wp_nonce_field( 'cc_order_migration_purge_trash', 'cc_order_migration_purge_nonce' ); ?>
				<input type="hidden" name="action" value="cc_order_migration_purge_trash" />
				<?php submit_button( __( 'Delete trashed migrated orders', 'consucorner-order-migration' ), 'delete', 'submit', false ); ?>
			</form>

			<details style="margin-top:24px;">
				<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e( 'WP-CLI (optional, for large migrations)', 'consucorner-order-migration' ); ?></summary>
				<pre style="background:#f6f7f7;padding:12px;max-width:720px;">wp cc-order-migrate status
wp cc-order-migrate run --dry-run --max=5
wp cc-order-migrate run --max=50
wp cc-order-migrate sync-order-dates
wp cc-order-migrate sync-sub-order-dates
wp cc-order-migrate sync-order-attribution
wp cc-order-migrate purge-trash
wp cc-order-migrate clear-lock</pre>
			</details>
		</div>
		<?php
	}

	/**
	 * Render flash notice from query args.
	 *
	 * @return void
	 */
	private static function render_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['cc_msg'] ) ) {
			$type  = isset( $_GET['cc_type'] ) ? sanitize_key( wp_unslash( $_GET['cc_type'] ) ) : 'success';
			$class = 'error' === $type ? 'notice-error' : ( 'warning' === $type ? 'notice-warning' : 'notice-success' );
			printf(
				'<div class="notice %s is-dismissible"><p>%s</p></div>',
				esc_attr( $class ),
				esc_html( wp_unslash( $_GET['cc_msg'] ) )
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * @param string $message Message.
	 * @param string $type    success|error|warning.
	 * @return void
	 */
	private static function redirect_with( $message, $type = 'success' ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'cc_msg'  => rawurlencode( $message ),
					'cc_type' => $type,
				),
				self::page_url()
			)
		);
		exit;
	}

	/**
	 * Save settings from the dashboard.
	 *
	 * @return void
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'consucorner-order-migration' ) );
		}
		check_admin_referer( 'cc_order_migration_save', 'cc_order_migration_save_nonce' );

		CC_Order_Migration_Config::save( wp_unslash( $_POST ) );

		if ( ! empty( $_POST['cc_test_after_save'] ) ) {
			self::run_connection_test();
		}

		self::redirect_with( __( 'Settings saved.', 'consucorner-order-migration' ) );
	}

	/**
	 * Test API connection (redirects with result).
	 *
	 * @return void
	 */
	public static function handle_test() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'consucorner-order-migration' ) );
		}
		check_admin_referer( 'cc_order_migration_run', 'cc_order_migration_nonce' );
		self::run_connection_test();
		self::redirect_with( __( 'Connection OK.', 'consucorner-order-migration' ) );
	}

	/**
	 * Perform a connection test and redirect on outcome.
	 *
	 * @return void
	 */
	private static function run_connection_test() {
		try {
			$config = CC_Order_Migration_Config::get();
			$api    = new CC_Order_Migration_API( $config );
			$batch  = $api->fetch_orders_page( 1 );
			self::redirect_with(
				sprintf(
					/* translators: %d: order count */
					__( 'Connection OK. Source reports ~%d order(s) matching your status filter.', 'consucorner-order-migration' ),
					(int) $batch['total']
				)
			);
		} catch ( Exception $e ) {
			self::redirect_with( __( 'Connection failed: ', 'consucorner-order-migration' ) . $e->getMessage(), 'error' );
		}
	}

	/**
	 * Run the import.
	 *
	 * @return void
	 */
	public static function handle_run() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'consucorner-order-migration' ) );
		}
		check_admin_referer( 'cc_order_migration_run', 'cc_order_migration_nonce' );

		@set_time_limit( 600 );

		$max     = isset( $_POST['max_orders'] ) ? max( 1, min( 200, (int) $_POST['max_orders'] ) ) : 20;
		$dry_run = ! empty( $_POST['dry_run'] );

		try {
			$config   = CC_Order_Migration_Config::get();
			$api      = new CC_Order_Migration_API( $config );
			$migrator = new CC_Order_Migrator( $config, $api );

			$stats = $migrator->run(
				array(
					'max_orders' => $max,
					'dry_run'    => $dry_run,
				)
			);
		} catch ( Exception $e ) {
			self::redirect_with( __( 'Import error: ', 'consucorner-order-migration' ) . $e->getMessage(), 'error' );
			return;
		}

		$summary = sprintf(
			/* translators: 1: run label, 2: imported, 3: skipped, 4: failed */
			__( '%1$s Imported: %2$d · Skipped: %3$d · Failed: %4$d', 'consucorner-order-migration' ),
			$dry_run ? __( '[Dry run]', 'consucorner-order-migration' ) : __( 'Done.', 'consucorner-order-migration' ),
			(int) $stats['imported'],
			(int) $stats['skipped'],
			(int) $stats['failed']
		);

		$type = ( (int) $stats['failed'] > 0 ) ? 'warning' : 'success';
		self::redirect_with( $summary, $type );
	}

	/**
	 * Restore migrated order dates from the source site API.
	 *
	 * @return void
	 */
	public static function handle_sync_dates() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'consucorner-order-migration' ) );
		}
		check_admin_referer( 'cc_order_migration_sync_dates', 'cc_order_migration_sync_dates_nonce' );

		@set_time_limit( 300 );

		try {
			$stats = CC_Order_Migrator::sync_migrated_order_dates_from_source( CC_Order_Migrator::DATE_REPAIR_BATCH_SIZE );
		} catch ( Exception $e ) {
			self::redirect_with( __( 'Order date repair failed: ', 'consucorner-order-migration' ) . $e->getMessage(), 'error' );
			return;
		}

		if ( ! empty( $stats['complete'] ) ) {
			$message = sprintf(
				/* translators: 1: parent count, 2: child count, 3: unchanged count, 4: missing source dates, 5: failed count */
				__( 'All migrated order dates are repaired. This batch — updated: %1$d · sub-orders: %2$d · already correct: %3$d · source dates missing: %4$d · failed: %5$d', 'consucorner-order-migration' ),
				(int) $stats['orders'],
				(int) $stats['children'],
				(int) $stats['unchanged'],
				(int) $stats['missing'],
				(int) $stats['failed']
			);
			$type = ( (int) $stats['failed'] > 0 ) ? 'warning' : 'success';
		} else {
			$message = sprintf(
				/* translators: 1: processed count, 2: parent count, 3: child count, 4: unchanged, 5: failed, 6: remaining */
				__( 'Batch complete (%1$d processed). Updated: %2$d · sub-orders: %3$d · already correct: %4$d · failed: %5$d · %6$d remaining — click again to continue.', 'consucorner-order-migration' ),
				(int) $stats['processed'],
				(int) $stats['orders'],
				(int) $stats['children'],
				(int) $stats['unchanged'],
				(int) $stats['failed'],
				(int) $stats['remaining']
			);
			$type = ( (int) $stats['failed'] > 0 ) ? 'warning' : 'success';
		}

		self::redirect_with( $message, $type );
	}

	/**
	 * Sync already-imported order attribution meta from source orders.
	 *
	 * @return void
	 */
	public static function handle_sync_attribution() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'consucorner-order-migration' ) );
		}
		check_admin_referer( 'cc_order_migration_sync_attribution', 'cc_order_migration_sync_attribution_nonce' );

		@set_time_limit( 900 );

		try {
			$stats = CC_Order_Migrator::sync_migrated_order_attribution();
		} catch ( Exception $e ) {
			self::redirect_with( __( 'Order origin sync failed: ', 'consucorner-order-migration' ) . $e->getMessage(), 'error' );
			return;
		}

		self::redirect_with(
			sprintf(
				/* translators: 1: order count, 2: child order count, 3: missing source attribution, 4: failed count */
				__( 'Order origin sync complete. Orders updated: %1$d · Sub-orders updated: %2$d · Source orders without attribution: %3$d · Failed: %4$d', 'consucorner-order-migration' ),
				(int) $stats['orders'],
				(int) $stats['children'],
				(int) $stats['missing'],
				(int) $stats['failed']
			),
			( (int) $stats['failed'] > 0 ) ? 'warning' : 'success'
		);
	}

	/**
	 * Permanently delete trashed migrated orders.
	 *
	 * @return void
	 */
	public static function handle_purge_trash() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'consucorner-order-migration' ) );
		}
		check_admin_referer( 'cc_order_migration_purge_trash', 'cc_order_migration_purge_nonce' );

		$deleted = CC_Order_Migrator::purge_trashed_migrations();

		self::redirect_with(
			sprintf(
				/* translators: %d: number of orders */
				__( 'Deleted %d trashed migrated order(s).', 'consucorner-order-migration' ),
				$deleted
			)
		);
	}
}
