<?php
/**
 * WP-CLI commands for order migration.
 *
 * @package ConsuCorner_Order_Migration
 */

defined( 'ABSPATH' ) || exit;

/**
 * WP-CLI integration.
 */
class CC_Order_Migration_CLI {

	/**
	 * Register commands.
	 *
	 * @return void
	 */
	public static function register() {
		WP_CLI::add_command( 'cc-order-migrate', self::class );
	}

	/**
	 * Import orders from the live site via WooCommerce REST API.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Log what would be imported without creating orders.
	 *
	 * [--max=<number>]
	 * : Stop after importing N orders (0 = unlimited).
	 *
	 * [--page=<number>]
	 * : Start from API page number (default from config).
	 *
	 * [--order-id=<id>]
	 * : Import a single order by old site ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cc-order-migrate run --dry-run --max=5
	 *     wp cc-order-migrate run --max=50
	 *     wp cc-order-migrate run --order-id=6795
	 *     wp cc-order-migrate status
	 *     wp cc-order-migrate sync-order-dates
	 *     wp cc-order-migrate sync-sub-order-dates
	 *     wp cc-order-migrate sync-order-attribution
	 *     wp cc-order-migrate purge-trash
	 *     wp cc-order-migrate clear-lock
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 * @return void
	 */
	public function __invoke( $args, $assoc_args ) {
		$sub = isset( $args[0] ) ? $args[0] : 'run';

		if ( 'status' === $sub ) {
			$this->status();
			return;
		}

		if ( 'purge-trash' === $sub ) {
			$deleted = CC_Order_Migrator::purge_trashed_migrations();
			WP_CLI::success( sprintf( 'Permanently deleted %d trashed migrated order(s).', $deleted ) );
			return;
		}

		if ( 'clear-lock' === $sub ) {
			CC_Order_Migrator::clear_lock();
			WP_CLI::success( 'Migration lock cleared.' );
			return;
		}

		if ( 'sync-sub-order-dates' === $sub || 'sync-order-dates' === $sub ) {
			$limit = 0;
			if ( isset( $assoc_args['limit'] ) ) {
				$limit = absint( $assoc_args['limit'] );
			} elseif ( empty( $assoc_args['all'] ) ) {
				$limit = CC_Order_Migrator::DATE_REPAIR_BATCH_SIZE;
			}

			$stats = CC_Order_Migrator::sync_migrated_order_dates_from_source( $limit );
			WP_CLI::success(
				sprintf(
					'Order date repair batch complete. Processed: %1$d, parents updated: %2$d, sub-orders updated: %3$d, already correct: %4$d, source dates missing: %5$d, failed: %6$d, remaining: %7$d.',
					(int) $stats['processed'],
					(int) $stats['orders'],
					(int) $stats['children'],
					(int) $stats['unchanged'],
					(int) $stats['missing'],
					(int) $stats['failed'],
					(int) $stats['remaining']
				)
			);
			return;
		}

		if ( 'sync-order-attribution' === $sub ) {
			$limit = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 0;
			$stats = CC_Order_Migrator::sync_migrated_order_attribution( $limit );
			WP_CLI::success(
				sprintf(
					'Order attribution sync complete. Orders updated: %1$d, sub-orders updated: %2$d, source orders without attribution: %3$d, failed: %4$d.',
					(int) $stats['orders'],
					(int) $stats['children'],
					(int) $stats['missing'],
					(int) $stats['failed']
				)
			);
			return;
		}

		if ( 'run' !== $sub ) {
			WP_CLI::error( 'Usage: wp cc-order-migrate run|status|sync-order-dates|sync-sub-order-dates|sync-order-attribution|purge-trash|clear-lock' );
		}

		try {
			$config = CC_Order_Migration_Config::get();
		} catch ( RuntimeException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$api      = new CC_Order_Migration_API( $config );
		$migrator = new CC_Order_Migrator(
			$config,
			$api,
			function ( $level, $message ) {
				switch ( $level ) {
					case 'success':
						WP_CLI::success( $message );
						break;
					case 'warning':
						WP_CLI::warning( $message );
						break;
					case 'error':
						WP_CLI::error( $message, false );
						break;
					default:
						WP_CLI::log( $message );
				}
			}
		);

		$overrides = array();
		if ( isset( $assoc_args['dry-run'] ) ) {
			$overrides['dry_run'] = true;
		}
		if ( isset( $assoc_args['max'] ) ) {
			$overrides['max_orders'] = (int) $assoc_args['max'];
		}
		if ( isset( $assoc_args['page'] ) ) {
			$overrides['start_page'] = (int) $assoc_args['page'];
		}

		if ( ! empty( $assoc_args['order-id'] ) ) {
			$old_id = absint( $assoc_args['order-id'] );
			WP_CLI::log( sprintf( 'Fetching single order #%d from live API…', $old_id ) );
			try {
				$order = $api->fetch_order( $old_id );
			} catch ( RuntimeException $e ) {
				WP_CLI::error( $e->getMessage() );
			}
			$dry    = ! empty( $overrides['dry_run'] );
			$result = $migrator->import_order( $order, $dry );
			WP_CLI::log( 'Result: ' . $result );
			$this->print_stats( $migrator->get_stats() );
			return;
		}

		$stats = $migrator->run( $overrides );
		$this->print_stats( $stats );
	}

	/**
	 * Show migration status.
	 *
	 * @return void
	 */
	private function status() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			WP_CLI::error( 'WooCommerce is not active.' );
		}

		try {
			$config = CC_Order_Migration_Config::get();
		} catch ( RuntimeException $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$migrated = CC_Order_Migrator::count_migrated();

		WP_CLI::log( 'Source: ' . $config['source_url'] );
		WP_CLI::log( 'Migrated orders on this site: ' . $migrated );

		try {
			$api   = new CC_Order_Migration_API( $config );
			$batch = $api->fetch_orders_page( 1 );
			WP_CLI::log( sprintf( 'Source reports ~%d parent orders (API total, all statuses in config).', (int) $batch['total'] ) );
		} catch ( RuntimeException $e ) {
			WP_CLI::warning( 'Could not reach source API: ' . $e->getMessage() );
		}
	}

	/**
	 * @param array $stats Stats.
	 * @return void
	 */
	private function print_stats( array $stats ) {
		WP_CLI::log( '---' );
		WP_CLI::log( 'Imported: ' . (int) $stats['imported'] );
		WP_CLI::log( 'Skipped:  ' . (int) $stats['skipped'] );
		WP_CLI::log( 'Failed:   ' . (int) $stats['failed'] );
	}
}
