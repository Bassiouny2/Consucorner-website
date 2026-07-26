<?php
/**
 * Vendor Financial Ledger & Analytics.
 *
 * Custom WooCommerce admin screen that surfaces Dokan vendor performance,
 * admin commission and vendor earnings using the {$wpdb->prefix}dokan_orders
 * sync table joined with wp_posts (and wp_users for vendor names).
 *
 * Architecture:
 *  - All PHP logic, SQL and HTML rendering lives in this file.
 *  - Assets (CSS/JS/Chart.js CDN) are enqueued ONLY on this admin screen
 *    via the `admin_enqueue_scripts` hook, gated by the page hook suffix.
 *
 * @package Consucorner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Consucorner_Vendor_Ledger' ) ) :

	final class Consucorner_Vendor_Ledger {

		const MENU_SLUG = 'cc-vendor-ledger';
		const NONCE_KEY = 'cc_vlg_nonce';
		const CAPABILITY = 'manage_woocommerce';

		/**
		 * Hook suffix returned by add_submenu_page(). Used to gate enqueue.
		 *
		 * @var string
		 */
		private static $page_hook = '';

		public static function init() {
			add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 60 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
			add_action( 'admin_footer', array( __CLASS__, 'render_order_preview_template' ) );
			add_action( 'wp_ajax_cc_vlg_filter', array( __CLASS__, 'ajax_filter' ) );
			add_action( 'wp_ajax_cc_vlg_export', array( __CLASS__, 'ajax_export_csv' ) );
			add_action( 'wp_ajax_cc_vlg_order_preview', array( __CLASS__, 'ajax_order_preview' ) );
		}

		/**
		 * Register the submenu under WooCommerce.
		 */
		public static function register_menu() {
			self::$page_hook = (string) add_submenu_page(
				'woocommerce',
				__( 'Vendor Financial Ledger & Analytics', 'consucorner' ),
				__( 'Vendor Ledger', 'consucorner' ),
				self::CAPABILITY,
				self::MENU_SLUG,
				array( __CLASS__, 'render_page' )
			);
		}

		/**
		 * Strictly enqueue assets only on this admin screen.
		 *
		 * @param string $hook Current admin page hook suffix.
		 */
		public static function enqueue_assets( $hook ) {
			if ( empty( self::$page_hook ) || $hook !== self::$page_hook ) {
				return;
			}

			$base = get_template_directory_uri() . '/assets';
			$ver  = wp_get_theme()->get( 'Version' );

			wp_enqueue_style(
				'cc-vlg-select2',
				'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
				array(),
				'4.1.0-rc.0'
			);
			wp_enqueue_style(
				'cc-vlg-flatpickr',
				'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css',
				array(),
				'4.6.13'
			);

			wp_enqueue_script(
				'cc-vlg-chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
				array(),
				'4.4.4',
				true
			);
			wp_enqueue_script(
				'cc-vlg-select2',
				'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
				array( 'jquery' ),
				'4.1.0-rc.0',
				true
			);
			wp_enqueue_script(
				'cc-vlg-flatpickr',
				'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js',
				array(),
				'4.6.13',
				true
			);

			wp_enqueue_style(
				'cc-vlg-style',
				$base . '/css/admin-vendor-ledger.css',
				array( 'cc-vlg-select2', 'cc-vlg-flatpickr' ),
				$ver
			);
			wp_enqueue_script(
				'cc-vlg-script',
				$base . '/js/admin-vendor-ledger.js',
				array( 'jquery', 'cc-vlg-chartjs', 'cc-vlg-select2', 'cc-vlg-flatpickr' ),
				$ver,
				true
			);

			if ( function_exists( 'WC' ) ) {
				$wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : '1.0';
				wp_enqueue_style(
					'woocommerce_admin_styles',
					WC()->plugin_url() . '/assets/css/admin.css',
					array(),
					$wc_version
				);
				wp_enqueue_script( 'underscore' );
				wp_enqueue_script( 'wp-util' );
				wp_enqueue_script( 'backbone' );
				wp_enqueue_script( 'wc-backbone-modal' );
				wp_enqueue_script( 'wc-orders' );
			}

			wp_localize_script(
				'cc-vlg-script',
				'CC_VLG',
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( self::NONCE_KEY ),
					'currency' => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol() ) : '',
					'defaultCompleted' => self::get_default_completed_date_range(),
					'defaultCreated'   => self::get_default_date_range(),
					'i18n'     => array(
						'noResults'   => __( 'No data for the selected filters.', 'consucorner' ),
						'loading'     => __( 'Loading…', 'consucorner' ),
						'errorLoad'   => __( 'Could not load data. Please retry.', 'consucorner' ),
						'netSales'    => __( 'Net Sales', 'consucorner' ),
						'commission'  => __( 'Admin Commission', 'consucorner' ),
						'vendorPay'   => __( 'Vendor Earnings', 'consucorner' ),
						'orders'      => __( 'Orders', 'consucorner' ),
						'ordersLabelCompleted' => __( 'orders completed in range', 'consucorner' ),
						'ordersLabelCreated'   => __( 'orders created in range', 'consucorner' ),
						'subtitleCompleted'    => __( 'Track vendor performance, admin commission and vendor earnings by order completed date.', 'consucorner' ),
						'subtitleCreated'      => __( 'Track vendor performance, admin commission and vendor earnings by order created date.', 'consucorner' ),
						'filterBy'             => __( 'Filter by', 'consucorner' ),
						'completedDate'        => __( 'Completed Date', 'consucorner' ),
						'createdDate'          => __( 'Created Date', 'consucorner' ),
						'hintCompleted'        => __( 'Only orders with a completion date are included.', 'consucorner' ),
						'hintCreated'          => __( 'Includes processing and pending orders.', 'consucorner' ),
						'ofSales'     => __( 'of sales', 'consucorner' ),
						'showMore'    => __( 'Show more orders', 'consucorner' ),
						'showingRows' => __( 'showing', 'consucorner' ),
						'previewOrder'=> __( 'Preview', 'consucorner' ),
					),
				)
			);
		}

		/**
		 * Vendors for the Select2 dropdown.
		 *
		 * @return WP_User[]
		 */
		public static function get_vendors() {
			$vendors = get_users(
				array(
					'role__in' => array( 'seller', 'vendor' ),
					'fields'   => array( 'ID', 'display_name' ),
					'orderby'  => 'display_name',
					'order'    => 'ASC',
					'number'   => 1000,
				)
			);

			// Fallback: anyone who appears as a seller in dokan_orders.
			if ( empty( $vendors ) ) {
				global $wpdb;
				$ids = $wpdb->get_col( "SELECT DISTINCT seller_id FROM {$wpdb->prefix}dokan_orders WHERE seller_id IS NOT NULL AND seller_id > 0 LIMIT 1000" );
				if ( $ids ) {
					$vendors = get_users(
						array(
							'include' => array_map( 'absint', $ids ),
							'fields'  => array( 'ID', 'display_name' ),
							'orderby' => 'display_name',
							'order'   => 'ASC',
						)
					);
				}
			}

			return $vendors ? $vendors : array();
		}

		/**
		 * Detect whether WooCommerce HPOS order table exists.
		 *
		 * The requested report is based on Dokan's sync table joined with wp_posts.
		 * Some WooCommerce installs store active order data in wp_wc_orders, so the
		 * queries below still join wp_posts but can fall back to HPOS date/status.
		 *
		 * @return string Empty string when HPOS table does not exist.
		 */
		private static function get_hpos_orders_table() {
			global $wpdb;

			static $hpos_table = null;

			if ( null !== $hpos_table ) {
				return $hpos_table;
			}

			$table = $wpdb->prefix . 'wc_orders';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			$hpos_table = $found === $table ? $table : '';

			return $hpos_table;
		}

		/**
		 * Detect whether WooCommerce HPOS order meta table exists.
		 *
		 * @return string Empty string when HPOS meta table does not exist.
		 */
		private static function get_hpos_orders_meta_table() {
			global $wpdb;

			static $hpos_meta_table = null;

			if ( null !== $hpos_meta_table ) {
				return $hpos_meta_table;
			}

			$table = $wpdb->prefix . 'wc_orders_meta';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			$hpos_meta_table = $found === $table ? $table : '';

			return $hpos_meta_table;
		}

		/**
		 * Detect whether WooCommerce HPOS order addresses table exists.
		 *
		 * @return string Empty string when the table does not exist.
		 */
		private static function get_hpos_order_addresses_table() {
			global $wpdb;

			static $addresses_table = null;

			if ( null !== $addresses_table ) {
				return $addresses_table;
			}

			$table = $wpdb->prefix . 'wc_order_addresses';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			$addresses_table = $found === $table ? $table : '';

			return $addresses_table;
		}

		/**
		 * Whether the HPOS orders table exposes date_completed_gmt.
		 *
		 * Older WooCommerce HPOS schemas may omit this column; completion is then
		 * stored only in orders meta / legacy postmeta.
		 *
		 * @return bool
		 */
		private static function hpos_has_date_completed_column() {
			static $has_column = null;

			if ( null !== $has_column ) {
				return $has_column;
			}

			$op_table = self::get_hpos_operational_data_table();
			if ( ! $op_table ) {
				$has_column = false;
				return $has_column;
			}

			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$op_table}` LIKE %s", 'date_completed_gmt' ) );

			$has_column = ! empty( $column );

			return $has_column;
		}

		/**
		 * Whether the HPOS operational table exposes date_paid_gmt.
		 *
		 * @return bool
		 */
		private static function hpos_has_date_paid_column() {
			static $has_column = null;

			if ( null !== $has_column ) {
				return $has_column;
			}

			$op_table = self::get_hpos_operational_data_table();
			if ( ! $op_table ) {
				$has_column = false;
				return $has_column;
			}

			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$op_table}` LIKE %s", 'date_paid_gmt' ) );

			$has_column = ! empty( $column );

			return $has_column;
		}

		/**
		 * HPOS operational data table (date_completed_gmt lives here, not wc_orders).
		 *
		 * @return string
		 */
		private static function get_hpos_operational_data_table() {
			global $wpdb;

			static $op_table = null;

			if ( null !== $op_table ) {
				return $op_table;
			}

			if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore' ) ) {
				$table = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_operational_data_table_name();
			} else {
				$table = $wpdb->prefix . 'wc_order_operational_data';
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			$op_table = $found ? (string) $found : '';

			return $op_table;
		}

		/**
		 * Normalize WooCommerce completion meta to a SQL DATETIME expression.
		 *
		 * @param string $column_expr SQL column reference.
		 * @return string
		 */
		private static function sql_meta_to_datetime( $column_expr ) {
			return "CASE
				WHEN {$column_expr} REGEXP '^[0-9]+(\\.[0-9]+)?$' AND CAST({$column_expr} AS DECIMAL(20,6)) > 1000000000
					THEN FROM_UNIXTIME(FLOOR(CAST({$column_expr} AS DECIMAL(20,6))))
				WHEN {$column_expr} IS NOT NULL AND {$column_expr} <> '' AND {$column_expr} REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}[ T]'
					THEN CAST(REPLACE(SUBSTRING_INDEX({$column_expr}, '+', 1), 'T', ' ') AS DATETIME)
				WHEN {$column_expr} IS NOT NULL AND {$column_expr} <> '' AND {$column_expr} NOT REGEXP '^[0-9]+(\\.[0-9]+)?$'
					THEN CAST({$column_expr} AS DATETIME)
				ELSE NULL
			END";
		}

		/**
		 * JOIN + column expressions for HPOS operational paid/completed dates.
		 *
		 * @param string $order_id_expr Order ID SQL expression.
		 * @return array{joins:string,completed_expr:string,paid_expr:string}
		 */
		private static function get_hpos_operational_date_parts( $order_id_expr ) {
			$op_table = self::get_hpos_operational_data_table();
			if ( ! $op_table ) {
				return array(
					'joins'          => '',
					'completed_expr' => '',
					'paid_expr'      => '',
				);
			}

			return array(
				'joins'          => "LEFT JOIN {$op_table} AS cc_completed_opdata ON cc_completed_opdata.order_id = {$order_id_expr}\n\t\t\t\t",
				'completed_expr' => 'cc_completed_opdata.date_completed_gmt',
				'paid_expr'      => 'cc_completed_opdata.date_paid_gmt',
			);
		}

		/**
		 * Build a COALESCE expression from one or more SQL datetime expressions.
		 *
		 * @param string ...$parts Datetime SQL expressions (empty strings are skipped).
		 * @return string
		 */
		private static function build_completed_date_expr( ...$parts ) {
			$parts = array_values(
				array_filter(
					$parts,
					static function ( $part ) {
						return is_string( $part ) && '' !== $part;
					}
				)
			);

			if ( empty( $parts ) ) {
				return 'NULL';
			}
			if ( 1 === count( $parts ) ) {
				return $parts[0];
			}

			return 'COALESCE(' . implode( ', ', $parts ) . ')';
		}

		/**
		 * Build SELECT + JOIN fragments for WooCommerce order attribution meta.
		 *
		 * HPOS stores attribution in wc_orders_meta; legacy installs use postmeta.
		 * When HPOS is available, COALESCE both sources for backward compatibility.
		 *
		 * @param string $order_id_expr SQL expression for the order ID column.
		 * @param bool   $aggregate     Whether to wrap values in MAX() for grouped queries.
		 * @return array{select:string,joins:string}
		 */
		private static function get_attribution_sql_parts( $order_id_expr, $aggregate = false ) {
			global $wpdb;

			$meta_map = array(
				'attr_source_type'   => array( 'attribution_source_type', '_wc_order_attribution_source_type' ),
				'attr_utm_source'    => array( 'attribution_utm_source', '_wc_order_attribution_utm_source' ),
				'attr_utm_medium'    => array( 'attribution_utm_medium', '_wc_order_attribution_utm_medium' ),
				'attr_referrer'      => array( 'attribution_referrer', '_wc_order_attribution_referrer' ),
				'attr_session_entry' => array( 'attribution_session_entry', '_wc_order_attribution_session_entry' ),
				'attr_session_pages' => array( 'attribution_session_pages', '_wc_order_attribution_session_pages' ),
				'attr_session_count' => array( 'attribution_session_count', '_wc_order_attribution_session_count' ),
			);

			$hpos_meta    = self::get_hpos_orders_meta_table();
			$select_parts = array();
			$joins        = '';

			foreach ( $meta_map as $alias => $config ) {
				list( $column, $meta_key ) = $config;

				if ( $hpos_meta ) {
					$hpos_alias = $alias . '_hpos';
					$post_alias = $alias . '_post';
					$value_expr = "COALESCE({$hpos_alias}.meta_value, {$post_alias}.meta_value)";

					if ( $aggregate ) {
						$select_parts[] = "MAX({$value_expr}) AS {$column}";
					} else {
						$select_parts[] = "{$value_expr} AS {$column}";
					}

					$joins .= "LEFT JOIN {$hpos_meta} AS {$hpos_alias} ON {$hpos_alias}.order_id = {$order_id_expr} AND {$hpos_alias}.meta_key = '{$meta_key}'\n\t\t\t\t";
					$joins .= "LEFT JOIN {$wpdb->postmeta} AS {$post_alias} ON {$post_alias}.post_id = {$order_id_expr} AND {$post_alias}.meta_key = '{$meta_key}'\n\t\t\t\t";
				} else {
					if ( $aggregate ) {
						$select_parts[] = "MAX({$alias}.meta_value) AS {$column}";
					} else {
						$select_parts[] = "{$alias}.meta_value AS {$column}";
					}

					$joins .= "LEFT JOIN {$wpdb->postmeta} AS {$alias} ON {$alias}.post_id = {$order_id_expr} AND {$alias}.meta_key = '{$meta_key}'\n\t\t\t\t";
				}
			}

			return array(
				'select' => implode( ",\n\t\t\t\t\t", $select_parts ),
				'joins'  => $joins,
			);
		}

		/**
		 * Get a useful default date range from the real ledger data.
		 *
		 * @return array{start:string,end:string}
		 */
		private static function get_default_date_range() {
			global $wpdb;

			$today_ts = (int) current_time( 'timestamp' );
			$today    = gmdate( 'Y-m-d', $today_ts );
			$fallback = array(
				'start' => gmdate( 'Y-m-d', strtotime( '-89 days', strtotime( $today ) ) ),
				'end'   => $today,
			);

			$hpos_table = self::get_hpos_orders_table();
			if ( $hpos_table ) {
				$sql = "
					SELECT
						MIN(wco.date_created_gmt) AS first_order_date,
						MAX(wco.date_created_gmt) AS last_order_date
					FROM {$hpos_table} AS wco
					LEFT JOIN {$wpdb->posts} AS p ON p.ID = wco.id
					WHERE wco.date_created_gmt IS NOT NULL
						AND (p.ID IS NULL OR p.post_type = %s)
				";

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$prepared = $wpdb->prepare( $sql, 'shop_order' );
			} else {
				$sql = "
					SELECT
						MIN(p.post_date) AS first_order_date,
						MAX(p.post_date) AS last_order_date
					FROM {$wpdb->posts} AS p
					WHERE p.post_type = %s
						AND p.post_date IS NOT NULL
				";

				$prepared = $wpdb->prepare( $sql, 'shop_order' );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $prepared, ARRAY_A );

			if ( empty( $row['first_order_date'] ) || empty( $row['last_order_date'] ) ) {
				return $fallback;
			}

			$first_ts = (int) strtotime( $row['first_order_date'] );
			$last_ts  = (int) strtotime( $row['last_order_date'] );
			$start_ts = max( $first_ts, strtotime( '-89 days', $last_ts ) );

			return array(
				'start' => gmdate( 'Y-m-d', $start_ts ),
				'end'   => gmdate( 'Y-m-d', $last_ts ),
			);
		}

		/**
		 * Default completed-date range from real order completion data.
		 *
		 * @return array{start:string,end:string}
		 */
		private static function get_default_completed_date_range() {
			global $wpdb;

			$today_ts = (int) current_time( 'timestamp' );
			$today    = gmdate( 'Y-m-d', $today_ts );
			$fallback = array(
				'start' => gmdate( 'Y-m-d', strtotime( '-89 days', strtotime( $today ) ) ),
				'end'   => $today,
			);

			$hpos_table  = self::get_hpos_orders_table();
			$hpos_meta   = self::get_hpos_orders_meta_table();
			$op_parts    = $hpos_table ? self::get_hpos_operational_date_parts( 'wco.id' ) : array( 'joins' => '', 'completed_expr' => '', 'paid_expr' => '' );
			if ( $hpos_table ) {
				$hpos_meta_join = $hpos_meta
					? "LEFT JOIN {$hpos_meta} AS completed_hpos_meta ON completed_hpos_meta.order_id = wco.id AND completed_hpos_meta.meta_key = '_date_completed'"
					: '';
				$hpos_paid_meta_join = $hpos_meta
					? "LEFT JOIN {$hpos_meta} AS paid_hpos_meta ON paid_hpos_meta.order_id = wco.id AND paid_hpos_meta.meta_key = '_date_paid'"
					: '';
				$completed_expr = self::build_completed_date_expr(
					$op_parts['completed_expr'],
					$op_parts['paid_expr'],
					$hpos_meta ? self::sql_meta_to_datetime( 'completed_hpos_meta.meta_value' ) : '',
					$hpos_meta ? self::sql_meta_to_datetime( 'paid_hpos_meta.meta_value' ) : '',
					self::sql_meta_to_datetime( 'completed_meta.meta_value' ),
					self::sql_meta_to_datetime( 'paid_meta.meta_value' ),
					self::sql_meta_to_datetime( 'completed_legacy.meta_value' )
				);
				$sql            = "
					SELECT
						MIN({$completed_expr}) AS first_completed_date,
						MAX({$completed_expr}) AS last_completed_date
					FROM {$hpos_table} AS wco
					LEFT JOIN {$wpdb->posts} AS p ON p.ID = wco.id
					LEFT JOIN {$wpdb->postmeta} AS completed_meta ON completed_meta.post_id = wco.id AND completed_meta.meta_key = '_date_completed'
					LEFT JOIN {$wpdb->postmeta} AS completed_legacy ON completed_legacy.post_id = wco.id AND completed_legacy.meta_key = '_completed_date'
					LEFT JOIN {$wpdb->postmeta} AS paid_meta ON paid_meta.post_id = wco.id AND paid_meta.meta_key = '_date_paid'
					{$op_parts['joins']}
					{$hpos_meta_join}
					{$hpos_paid_meta_join}
					WHERE {$completed_expr} IS NOT NULL
						AND (p.ID IS NULL OR p.post_type = %s)
				";

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$prepared = $wpdb->prepare( $sql, 'shop_order' );
			} else {
				$sql = "
					SELECT
						MIN(" . self::build_completed_date_expr(
							self::sql_meta_to_datetime( 'completed_meta.meta_value' ),
							self::sql_meta_to_datetime( 'completed_legacy.meta_value' ),
							self::sql_meta_to_datetime( 'paid_meta.meta_value' )
						) . ") AS first_completed_date,
						MAX(" . self::build_completed_date_expr(
							self::sql_meta_to_datetime( 'completed_meta.meta_value' ),
							self::sql_meta_to_datetime( 'completed_legacy.meta_value' ),
							self::sql_meta_to_datetime( 'paid_meta.meta_value' )
						) . ") AS last_completed_date
					FROM {$wpdb->posts} AS p
					LEFT JOIN {$wpdb->postmeta} AS completed_meta ON completed_meta.post_id = p.ID AND completed_meta.meta_key = '_date_completed'
					LEFT JOIN {$wpdb->postmeta} AS completed_legacy ON completed_legacy.post_id = p.ID AND completed_legacy.meta_key = '_completed_date'
					LEFT JOIN {$wpdb->postmeta} AS paid_meta ON paid_meta.post_id = p.ID AND paid_meta.meta_key = '_date_paid'
					WHERE p.post_type = %s
						AND " . self::build_completed_date_expr(
							self::sql_meta_to_datetime( 'completed_meta.meta_value' ),
							self::sql_meta_to_datetime( 'completed_legacy.meta_value' ),
							self::sql_meta_to_datetime( 'paid_meta.meta_value' )
						) . ' IS NOT NULL
				';

				$prepared = $wpdb->prepare( $sql, 'shop_order' );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $prepared, ARRAY_A );

			if ( empty( $row['first_completed_date'] ) || empty( $row['last_completed_date'] ) ) {
				return $fallback;
			}

			$first_ts = (int) strtotime( $row['first_completed_date'] );
			$last_ts  = (int) strtotime( $row['last_completed_date'] );
			$start_ts = max( $first_ts, strtotime( '-89 days', $last_ts ) );

			return array(
				'start' => gmdate( 'Y-m-d', $start_ts ),
				'end'   => gmdate( 'Y-m-d', $last_ts ),
			);
		}

		/**
		 * SQL expression and JOIN fragments for order completed/paid datetime.
		 *
		 * @param string $order_id_expr SQL expression for order ID.
		 * @return array{expr:string,joins:string}
		 */
		private static function get_completed_date_expr_and_join( $order_id_expr ) {
			global $wpdb;

			$post_completed_ts = self::sql_meta_to_datetime( 'cc_completed_meta.meta_value' );
			$post_paid_ts      = self::sql_meta_to_datetime( 'cc_paid_meta.meta_value' );
			$legacy_meta_ts    = self::sql_meta_to_datetime( 'cc_completed_legacy.meta_value' );
			$hpos_meta_table   = self::get_hpos_orders_meta_table();
			$hpos_completed_ts = $hpos_meta_table
				? self::sql_meta_to_datetime( 'cc_completed_hpos_meta.meta_value' )
				: '';
			$hpos_paid_ts      = $hpos_meta_table
				? self::sql_meta_to_datetime( 'cc_paid_hpos_meta.meta_value' )
				: '';
			$op_parts          = self::get_hpos_operational_date_parts( $order_id_expr );

			$joins  = "LEFT JOIN {$wpdb->postmeta} AS cc_completed_meta ON cc_completed_meta.post_id = {$order_id_expr} AND cc_completed_meta.meta_key = '_date_completed'\n\t\t\t\t";
			$joins .= "LEFT JOIN {$wpdb->postmeta} AS cc_paid_meta ON cc_paid_meta.post_id = {$order_id_expr} AND cc_paid_meta.meta_key = '_date_paid'\n\t\t\t\t";
			$joins .= "LEFT JOIN {$wpdb->postmeta} AS cc_completed_legacy ON cc_completed_legacy.post_id = {$order_id_expr} AND cc_completed_legacy.meta_key = '_completed_date'\n\t\t\t\t";

			if ( $hpos_meta_table ) {
				$joins .= "LEFT JOIN {$hpos_meta_table} AS cc_completed_hpos_meta ON cc_completed_hpos_meta.order_id = {$order_id_expr} AND cc_completed_hpos_meta.meta_key = '_date_completed'\n\t\t\t\t";
				$joins .= "LEFT JOIN {$hpos_meta_table} AS cc_paid_hpos_meta ON cc_paid_hpos_meta.order_id = {$order_id_expr} AND cc_paid_hpos_meta.meta_key = '_date_paid'\n\t\t\t\t";
			}

			$joins .= $op_parts['joins'];

			$expr = self::build_completed_date_expr(
				$op_parts['completed_expr'],
				$op_parts['paid_expr'],
				$hpos_completed_ts,
				$hpos_paid_ts,
				$post_completed_ts,
				$post_paid_ts,
				$legacy_meta_ts
			);

			return array(
				'expr'  => $expr,
				'joins' => $joins,
			);
		}

		/**
		 * Normalize the mutually exclusive date filter mode.
		 *
		 * @param string $mode Raw mode from request.
		 * @return string 'completed' or 'created'
		 */
		private static function normalize_date_filter_mode( $mode ) {
			return 'created' === sanitize_key( (string) $mode ) ? 'created' : 'completed';
		}

		/**
		 * Parse and normalize a Y-m-d date range.
		 *
		 * @param string $start_raw     Start date (Y-m-d).
		 * @param string $end_raw       End date (Y-m-d).
		 * @param string $default_start Fallback start.
		 * @param string $default_end   Fallback end.
		 * @return array{start:string,end:string}
		 */
		private static function parse_date_range_bounds( $start_raw, $end_raw, $default_start, $default_end ) {
			$start_ts = strtotime( ( '' !== $start_raw ? $start_raw : $default_start ) . ' 00:00:00' );
			$end_ts   = strtotime( ( '' !== $end_raw ? $end_raw : $default_end ) . ' 23:59:59' );

			if ( ! $start_ts ) {
				$start_ts = strtotime( $default_start . ' 00:00:00' );
			}
			if ( ! $end_ts ) {
				$end_ts = strtotime( $default_end . ' 23:59:59' );
			}
			if ( $start_ts > $end_ts ) {
				list( $start_ts, $end_ts ) = array( $end_ts, $start_ts );
			}

			return array(
				'start' => gmdate( 'Y-m-d 00:00:00', $start_ts ),
				'end'   => gmdate( 'Y-m-d 23:59:59', $end_ts ),
			);
		}

		/**
		 * Append the active date filter (completed OR created — never both).
		 *
		 * @param array  $where           WHERE fragments.
		 * @param array  $params          Prepared params.
		 * @param array  $f               Parsed filters.
		 * @param string $created_expr    Created date SQL expression.
		 * @param string $completed_expr  Completed date SQL expression.
		 */
		private static function append_active_date_where( &$where, &$params, $f, $created_expr, $completed_expr ) {
			if ( 'created' === $f['date_filter_mode'] ) {
				$where[]  = "{$created_expr} IS NOT NULL";
				$where[]  = "{$created_expr} >= %s";
				$params[] = $f['created_start_date'];
				$where[]  = "{$created_expr} <= %s";
				$params[] = $f['created_end_date'];
				return;
			}

			$bounds = self::get_completed_sql_date_bounds( $f );
			$where[]  = "{$completed_expr} IS NOT NULL";
			$where[]  = "{$completed_expr} >= %s";
			$params[] = $bounds['start'];
			$where[]  = "{$completed_expr} <= %s";
			$params[] = $bounds['end'];
		}

		/**
		 * Convert completed filter bounds to GMT for HPOS datetime columns.
		 *
		 * @param array $f Parsed filters.
		 * @return array{start:string,end:string}
		 */
		private static function get_completed_sql_date_bounds( $f ) {
			$start = isset( $f['completed_start_date'] ) ? (string) $f['completed_start_date'] : '';
			$end   = isset( $f['completed_end_date'] ) ? (string) $f['completed_end_date'] : '';

			if ( ! self::get_hpos_orders_table() ) {
				return array(
					'start' => $start,
					'end'   => $end,
				);
			}

			$start_day = preg_match( '/^(\d{4}-\d{2}-\d{2})/', $start, $start_match ) ? $start_match[1] : $start;
			$end_day   = preg_match( '/^(\d{4}-\d{2}-\d{2})/', $end, $end_match ) ? $end_match[1] : $end;

			return array(
				'start' => get_gmt_from_date( $start_day . ' 00:00:00' ),
				'end'   => get_gmt_from_date( $end_day . ' 23:59:59' ),
			);
		}

		/**
		 * SQL ORDER BY expression for the active date mode.
		 *
		 * @param array  $f               Parsed filters.
		 * @param string $created_expr    Created date SQL expression.
		 * @param string $completed_expr  Completed date SQL expression.
		 * @return string
		 */
		private static function get_active_date_order_expr( $f, $created_expr, $completed_expr ) {
			return 'created' === $f['date_filter_mode'] ? $created_expr : $completed_expr;
		}

		/**
		 * Sortable date value from a merged order row.
		 *
		 * @param array $row Order row.
		 * @param array $f   Parsed filters.
		 * @return string
		 */
		private static function get_row_sort_date( $row, $f ) {
			if ( 'created' === $f['date_filter_mode'] ) {
				return ! empty( $row['order_date'] ) ? (string) $row['order_date'] : '';
			}

			return ! empty( $row['completed_date'] ) ? (string) $row['completed_date'] : ( ! empty( $row['order_date'] ) ? (string) $row['order_date'] : '' );
		}

		/**
		 * Chart bucket date from an order row.
		 *
		 * @param array $row Order row.
		 * @param array $f   Parsed filters.
		 * @return string
		 */
		private static function get_chart_bucket_date( $row, $f ) {
			if ( 'created' === $f['date_filter_mode'] ) {
				return ! empty( $row['order_date'] ) ? (string) $row['order_date'] : '';
			}

			return ! empty( $row['completed_date'] ) ? (string) $row['completed_date'] : '';
		}

		/**
		 * Billing name JOIN + SELECT fragments for order queries.
		 *
		 * @param string $order_id_expr SQL expression for order ID.
		 * @param bool   $aggregate     Wrap values in MAX() for grouped queries.
		 * @return array{joins:string,select:string,email_select:string}
		 */
		private static function get_billing_name_sql_parts( $order_id_expr, $aggregate = false ) {
			global $wpdb;

			$addr_table = self::get_hpos_order_addresses_table();

			$joins = "
				LEFT JOIN {$wpdb->postmeta} AS billing_first ON billing_first.post_id = {$order_id_expr} AND billing_first.meta_key = '_billing_first_name'
				LEFT JOIN {$wpdb->postmeta} AS billing_last ON billing_last.post_id = {$order_id_expr} AND billing_last.meta_key = '_billing_last_name'
				LEFT JOIN {$wpdb->postmeta} AS billing_company ON billing_company.post_id = {$order_id_expr} AND billing_company.meta_key = '_billing_company'
				LEFT JOIN {$wpdb->postmeta} AS billing_email_pm ON billing_email_pm.post_id = {$order_id_expr} AND billing_email_pm.meta_key = '_billing_email'
			";

			if ( $addr_table ) {
				$joins .= "
					LEFT JOIN {$addr_table} AS cc_billing_addr ON cc_billing_addr.order_id = {$order_id_expr} AND cc_billing_addr.address_type = 'billing'
				";
			}

			if ( $aggregate ) {
				$first_expr = $addr_table
					? "MAX(COALESCE(NULLIF(billing_first.meta_value, ''), cc_billing_addr.first_name, ''))"
					: 'MAX(billing_first.meta_value)';
				$last_expr  = $addr_table
					? "MAX(COALESCE(NULLIF(billing_last.meta_value, ''), cc_billing_addr.last_name, ''))"
					: 'MAX(billing_last.meta_value)';
				$company_expr = $addr_table
					? "MAX(COALESCE(NULLIF(billing_company.meta_value, ''), cc_billing_addr.company, ''))"
					: 'MAX(billing_company.meta_value)';
				$email_expr = $addr_table
					? "MAX(COALESCE(NULLIF(billing_email_pm.meta_value, ''), cc_billing_addr.email, ''))"
					: 'MAX(billing_email_pm.meta_value)';
			} else {
				$first_expr = $addr_table
					? "COALESCE(NULLIF(billing_first.meta_value, ''), cc_billing_addr.first_name, '')"
					: 'billing_first.meta_value';
				$last_expr  = $addr_table
					? "COALESCE(NULLIF(billing_last.meta_value, ''), cc_billing_addr.last_name, '')"
					: 'billing_last.meta_value';
				$company_expr = $addr_table
					? "COALESCE(NULLIF(billing_company.meta_value, ''), cc_billing_addr.company, '')"
					: 'billing_company.meta_value';
				$email_expr = $addr_table
					? "COALESCE(NULLIF(billing_email_pm.meta_value, ''), cc_billing_addr.email, '')"
					: 'billing_email_pm.meta_value';
			}

			$select = "
				{$first_expr} AS billing_first_name,
				{$last_expr} AS billing_last_name,
				{$company_expr} AS billing_company
			";

			return array(
				'joins'         => $joins,
				'select'        => $select,
				'email_select'  => $email_expr,
			);
		}

		/**
		 * Resolve a display-friendly billing/customer name from query row data.
		 *
		 * @param array<string,mixed> $row Order row.
		 * @return string
		 */
		private static function format_billing_name( $row ) {
			$first   = isset( $row['billing_first_name'] ) ? trim( (string) $row['billing_first_name'] ) : '';
			$last    = isset( $row['billing_last_name'] ) ? trim( (string) $row['billing_last_name'] ) : '';
			$company = isset( $row['billing_company'] ) ? trim( (string) $row['billing_company'] ) : '';
			$name    = trim( $first . ' ' . $last );

			if ( $name ) {
				return $name;
			}
			if ( $company ) {
				return $company;
			}

			$customer_id = isset( $row['customer_id'] ) ? absint( $row['customer_id'] ) : 0;
			if ( $customer_id > 0 ) {
				$user = get_userdata( $customer_id );
				if ( $user && $user->display_name ) {
					return $user->display_name;
				}
			}

			if ( ! empty( $row['billing_email'] ) ) {
				return (string) $row['billing_email'];
			}

			return __( 'Guest', 'consucorner' );
		}

		/**
		 * Format a GMT/UTC MySQL datetime for admin display in the site timezone.
		 *
		 * @param string $gmt_datetime Datetime string stored as GMT.
		 * @return string
		 */
		private static function format_gmt_datetime_for_display( $gmt_datetime ) {
			if ( empty( $gmt_datetime ) || '0000-00-00 00:00:00' === $gmt_datetime ) {
				return '—';
			}

			if ( function_exists( 'wc_string_to_datetime' ) && function_exists( 'wc_format_datetime' ) ) {
				try {
					$dt = wc_string_to_datetime( $gmt_datetime );
					if ( $dt ) {
						return wc_format_datetime( $dt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
					}
				} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
					// Fall through to mysql2date.
				}
			}

			return mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $gmt_datetime );
		}

		/**
		 * Convert a GMT/UTC MySQL datetime to a local Y-m-d key.
		 *
		 * @param string $gmt_datetime Datetime string stored as GMT.
		 * @return string
		 */
		private static function format_gmt_date_iso( $gmt_datetime ) {
			if ( empty( $gmt_datetime ) || '0000-00-00 00:00:00' === $gmt_datetime ) {
				return '';
			}

			if ( function_exists( 'wc_string_to_datetime' ) ) {
				try {
					$dt = wc_string_to_datetime( $gmt_datetime );
					if ( $dt ) {
						return $dt->setTimezone( wp_timezone() )->format( 'Y-m-d' );
					}
				} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
					// Fall through.
				}
			}

			return gmdate( 'Y-m-d', strtotime( $gmt_datetime ) );
		}

		private static function format_order_datetime_for_display( $datetime, $is_gmt = true ) {
			if ( empty( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
				return '—';
			}

			if ( $is_gmt ) {
				return self::format_gmt_datetime_for_display( $datetime );
			}

			return mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $datetime );
		}

		/**
		 * Local calendar day key for chart bucketing.
		 *
		 * @param string $datetime Datetime string.
		 * @param bool   $is_gmt   Whether the value is stored as GMT.
		 * @return string
		 */
		private static function format_order_date_iso( $datetime, $is_gmt = true ) {
			if ( empty( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
				return '';
			}

			if ( $is_gmt ) {
				return self::format_gmt_date_iso( $datetime );
			}

			return gmdate( 'Y-m-d', strtotime( $datetime ) );
		}

		/**
		 * Admin edit URL for an order (HPOS-safe).
		 *
		 * @param int $order_id Order ID.
		 * @return string
		 */
		private static function get_order_edit_url( $order_id ) {
			$order_id = absint( $order_id );
			if ( $order_id <= 0 ) {
				return '';
			}

			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					return $order->get_edit_order_url();
				}
			}

			return admin_url( 'post.php?post=' . $order_id . '&action=edit' );
		}

		/**
		 * Output WooCommerce order preview modal template on this screen.
		 */
		public static function render_order_preview_template() {
			if ( ! function_exists( 'wc_get_container' ) ) {
				return;
			}

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || empty( self::$page_hook ) || $screen->id !== self::$page_hook ) {
				return;
			}

			if ( ! class_exists( '\Automattic\WooCommerce\Internal\Admin\Orders\ListTable' ) ) {
				return;
			}

			// Bosta adds empty timeline/shipping blocks when its API key is missing.
			if ( has_action( 'woocommerce_admin_order_preview_start', 'bosta_custom_display_order_data_in_admin' ) ) {
				remove_action( 'woocommerce_admin_order_preview_start', 'bosta_custom_display_order_data_in_admin' );
			}

			$list_table = wc_get_container()->get( \Automattic\WooCommerce\Internal\Admin\Orders\ListTable::class );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $list_table->get_order_preview_template();
		}

		/**
		 * Available WooCommerce order statuses.
		 *
		 * @return array<string,string>
		 */
		public static function get_statuses() {
			if ( function_exists( 'wc_get_order_statuses' ) ) {
				return wc_get_order_statuses();
			}
			return array(
				'wc-pending'    => __( 'Pending payment', 'consucorner' ),
				'wc-processing' => __( 'Processing', 'consucorner' ),
				'wc-on-hold'    => __( 'On hold', 'consucorner' ),
				'wc-completed'  => __( 'Completed', 'consucorner' ),
				'wc-cancelled'  => __( 'Cancelled', 'consucorner' ),
				'wc-refunded'   => __( 'Refunded', 'consucorner' ),
				'wc-failed'     => __( 'Failed', 'consucorner' ),
			);
		}

		/**
		 * Sanitize and normalize the filter input from $_POST or $_GET.
		 *
		 * @param array $input Raw input array.
		 * @return array<string,mixed>
		 */
		private static function parse_filters( $input ) {
			$mode               = isset( $input['date_filter_mode'] )
				? self::normalize_date_filter_mode( wp_unslash( $input['date_filter_mode'] ) )
				: 'completed';
			$completed_defaults = self::get_default_completed_date_range();
			$created_defaults   = self::get_default_date_range();

			$vendor_id = isset( $input['vendor_id'] ) ? absint( $input['vendor_id'] ) : 0;

			$status = isset( $input['status'] ) ? sanitize_text_field( wp_unslash( $input['status'] ) ) : '';
			$status = preg_replace( '/[^a-z0-9_\-]/i', '', $status );

			if ( 'created' === $mode ) {
				$created_start_raw = isset( $input['created_start_date'] ) ? sanitize_text_field( wp_unslash( $input['created_start_date'] ) ) : '';
				$created_end_raw   = isset( $input['created_end_date'] ) ? sanitize_text_field( wp_unslash( $input['created_end_date'] ) ) : '';

				if ( '' === $created_start_raw && isset( $input['start_date'] ) ) {
					$created_start_raw = sanitize_text_field( wp_unslash( $input['start_date'] ) );
				}
				if ( '' === $created_end_raw && isset( $input['end_date'] ) ) {
					$created_end_raw = sanitize_text_field( wp_unslash( $input['end_date'] ) );
				}

				$created_bounds = self::parse_date_range_bounds(
					$created_start_raw,
					$created_end_raw,
					$created_defaults['start'],
					$created_defaults['end']
				);

				return array(
					'date_filter_mode'     => 'created',
					'completed_start_date' => '',
					'completed_end_date'   => '',
					'created_start_date'   => $created_bounds['start'],
					'created_end_date'     => $created_bounds['end'],
					'has_completed_filter' => false,
					'has_created_filter'   => true,
					'vendor_id'            => (int) $vendor_id,
					'status'               => (string) $status,
				);
			}

			$completed_start_raw = isset( $input['completed_start_date'] ) ? sanitize_text_field( wp_unslash( $input['completed_start_date'] ) ) : '';
			$completed_end_raw   = isset( $input['completed_end_date'] ) ? sanitize_text_field( wp_unslash( $input['completed_end_date'] ) ) : '';

			$completed_bounds = self::parse_date_range_bounds(
				$completed_start_raw,
				$completed_end_raw,
				$completed_defaults['start'],
				$completed_defaults['end']
			);

			return array(
				'date_filter_mode'     => 'completed',
				'completed_start_date' => $completed_bounds['start'],
				'completed_end_date'   => $completed_bounds['end'],
				'created_start_date'   => '',
				'created_end_date'     => '',
				'has_completed_filter' => true,
				'has_created_filter'   => false,
				'vendor_id'            => (int) $vendor_id,
				'status'               => (string) $status,
			);
		}

		/**
		 * Run the ledger SQL query.
		 *
		 * Joins dokan_orders → posts (for date/status) → users (for vendor name).
		 *
		 * @param array $f Parsed filters.
		 * @return array<int,array<string,mixed>>
		 */
		public static function query_orders( $f ) {
			$dokan_rows = self::query_dokan_order_rows( $f );
			$item_rows  = self::query_order_item_rows( $f );

			return self::merge_order_rows( $dokan_rows, $item_rows, $f );
		}

		/**
		 * Query Dokan's vendor order sync rows.
		 *
		 * @param array $f Parsed filters.
		 * @return array<int,array<string,mixed>>
		 */
		private static function query_dokan_order_rows( $f ) {
			global $wpdb;

			$where  = array();
			$params = array();

			$hpos_table  = self::get_hpos_orders_table();
			$hpos_join   = $hpos_table ? "LEFT JOIN {$hpos_table} AS wco ON wco.id = dko.order_id" : '';
			$date_expr   = $hpos_table ? 'wco.date_created_gmt' : 'p.post_date';
			$status_expr = $hpos_table ? 'COALESCE(p.post_status, wco.status)' : 'p.post_status';
			$customer_expr = $hpos_table ? 'COALESCE(wco.customer_id, customer_user.meta_value, 0)' : 'COALESCE(customer_user.meta_value, 0)';
			$completed_parts = self::get_completed_date_expr_and_join( 'dko.order_id' );

			$params[] = 'shop_order';

			self::append_active_date_where( $where, $params, $f, $date_expr, $completed_parts['expr'] );
			$order_by_expr = self::get_active_date_order_expr( $f, $date_expr, $completed_parts['expr'] );

			if ( $f['vendor_id'] > 0 ) {
				$where[]  = 'dko.seller_id = %d';
				$params[] = $f['vendor_id'];
			}

			if ( ! empty( $f['status'] ) ) {
				$bare     = preg_replace( '/^wc-/', '', $f['status'] );
				$where[]  = "(dko.order_status = %s OR {$status_expr} = %s)";
				$params[] = $bare;
				$params[] = $f['status'];
			}

			$where_sql   = implode( ' AND ', $where );
			$attribution = self::get_attribution_sql_parts( 'dko.order_id', false );
			$billing     = self::get_billing_name_sql_parts( 'dko.order_id', false );

			$sql = "
				SELECT
					dko.order_id     AS order_id,
					dko.seller_id    AS seller_id,
					dko.order_total  AS order_total,
					dko.net_amount   AS net_amount,
					dko.order_status AS order_status,
					{$date_expr}     AS order_date,
					{$completed_parts['expr']} AS completed_date,
					{$status_expr}   AS post_status,
					u.display_name   AS vendor_name,
					{$customer_expr} AS customer_id,
					{$billing['email_select']} AS billing_email,
					{$billing['select']},
					{$attribution['select']},
					0                AS calculated_admin_fee,
					0                AS line_gross,
					'dokan'          AS source
				FROM {$wpdb->prefix}dokan_orders AS dko
				LEFT JOIN {$wpdb->posts} AS p ON p.ID = dko.order_id AND p.post_type = %s
				{$hpos_join}
				LEFT JOIN {$wpdb->users} AS u ON u.ID = dko.seller_id
				LEFT JOIN {$wpdb->postmeta} AS customer_user ON customer_user.post_id = dko.order_id AND customer_user.meta_key = '_customer_user'
				{$billing['joins']}
				{$completed_parts['joins']}
				{$attribution['joins']}
				WHERE {$where_sql}
				ORDER BY {$order_by_expr} DESC
			";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared = $wpdb->prepare( $sql, $params );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $prepared, ARRAY_A );

			return $rows ? $rows : array();
		}

		/**
		 * Query WooCommerce order items as a fallback for orders that have not
		 * been synced into wp_dokan_orders. Product authors are treated as vendors.
		 *
		 * @param array $f Parsed filters.
		 * @return array<int,array<string,mixed>>
		 */
		private static function query_order_item_rows( $f ) {
			global $wpdb;

			$where  = array();
			$params = array();

			$hpos_table = self::get_hpos_orders_table();
			if ( $hpos_table ) {
				$from_order  = "{$hpos_table} AS wco LEFT JOIN {$wpdb->posts} AS p ON p.ID = wco.id";
				$order_id    = 'wco.id';
				$date_expr   = 'wco.date_created_gmt';
				$status_expr = 'COALESCE(p.post_status, wco.status)';
				$total_expr  = 'COALESCE(wco.total_amount, order_total_meta.meta_value, 0)';
				$customer_expr = 'COALESCE(wco.customer_id, customer_user.meta_value, 0)';
				$completed_parts = self::get_completed_date_expr_and_join( $order_id );
				$where[]     = '(p.ID IS NULL OR p.post_type = %s)';
				$params[]    = 'shop_order';
			} else {
				$from_order  = "{$wpdb->posts} AS p";
				$order_id    = 'p.ID';
				$date_expr   = 'p.post_date';
				$status_expr = 'p.post_status';
				$total_expr  = 'COALESCE(order_total_meta.meta_value, 0)';
				$customer_expr = 'COALESCE(customer_user.meta_value, 0)';
				$completed_parts = self::get_completed_date_expr_and_join( $order_id );
				$where[]     = 'p.post_type = %s';
				$params[]    = 'shop_order';
			}

			self::append_active_date_where( $where, $params, $f, $date_expr, $completed_parts['expr'] );
			$order_by_expr = self::get_active_date_order_expr( $f, $date_expr, $completed_parts['expr'] );

			if ( $f['vendor_id'] > 0 ) {
				$where[]  = 'vendor_product.post_author = %d';
				$params[] = $f['vendor_id'];
			}

			if ( ! empty( $f['status'] ) ) {
				$bare     = preg_replace( '/^wc-/', '', $f['status'] );
				$where[]  = "({$status_expr} = %s OR {$status_expr} = %s)";
				$params[] = $bare;
				$params[] = $f['status'];
			}

			$where_sql   = implode( ' AND ', $where );
			$attribution = self::get_attribution_sql_parts( $order_id, true );
			$billing     = self::get_billing_name_sql_parts( $order_id, true );

			$sql = "
				SELECT
					{$order_id} AS order_id,
					COALESCE(vendor_product.post_author, 0) AS seller_id,
					MAX(CAST({$total_expr} AS DECIMAL(20,6))) AS order_total_source,
					SUM(CAST(COALESCE(line_total.meta_value, 0) AS DECIMAL(20,6))) AS line_gross,
					SUM(
						CASE
							WHEN commission_rate.meta_value IS NULL OR commission_rate.meta_value = '' THEN 0
							WHEN commission_type.meta_value IN ('percentage', 'percent', 'combine') THEN
								(CAST(COALESCE(line_total.meta_value, 0) AS DECIMAL(20,6)) * CAST(commission_rate.meta_value AS DECIMAL(20,6)) / 100)
								+ CAST(COALESCE(additional_fee.meta_value, 0) AS DECIMAL(20,6))
							WHEN commission_type.meta_value IN ('fixed', 'flat') THEN
								(CAST(commission_rate.meta_value AS DECIMAL(20,6)) * GREATEST(1, CAST(COALESCE(qty.meta_value, 1) AS DECIMAL(20,6))))
								+ CAST(COALESCE(additional_fee.meta_value, 0) AS DECIMAL(20,6))
							ELSE 0
						END
					) AS calculated_admin_fee,
					{$status_expr} AS post_status,
					{$date_expr} AS order_date,
					{$completed_parts['expr']} AS completed_date,
					u.display_name AS vendor_name,
					MAX({$customer_expr}) AS customer_id,
					{$billing['email_select']} AS billing_email,
					{$billing['select']},
					{$attribution['select']},
					'wc_items' AS source
				FROM {$from_order}
				LEFT JOIN {$wpdb->postmeta} AS order_total_meta
					ON order_total_meta.post_id = {$order_id}
					AND order_total_meta.meta_key = '_order_total'
				LEFT JOIN {$wpdb->postmeta} AS customer_user
					ON customer_user.post_id = {$order_id}
					AND customer_user.meta_key = '_customer_user'
				{$billing['joins']}
				{$completed_parts['joins']}
				{$attribution['joins']}
				INNER JOIN {$wpdb->prefix}woocommerce_order_items AS oi
					ON oi.order_id = {$order_id}
					AND oi.order_item_type = 'line_item'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS product_id_meta
					ON product_id_meta.order_item_id = oi.order_item_id
					AND product_id_meta.meta_key = '_product_id'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS line_total
					ON line_total.order_item_id = oi.order_item_id
					AND line_total.meta_key = '_line_total'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS qty
					ON qty.order_item_id = oi.order_item_id
					AND qty.meta_key = '_qty'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS commission_rate
					ON commission_rate.order_item_id = oi.order_item_id
					AND commission_rate.meta_key = '_dokan_commission_rate'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS commission_type
					ON commission_type.order_item_id = oi.order_item_id
					AND commission_type.meta_key = '_dokan_commission_type'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS additional_fee
					ON additional_fee.order_item_id = oi.order_item_id
					AND additional_fee.meta_key = '_dokan_additional_fee'
				LEFT JOIN {$wpdb->posts} AS product_post
					ON product_post.ID = CAST(product_id_meta.meta_value AS UNSIGNED)
				LEFT JOIN {$wpdb->posts} AS vendor_product
					ON vendor_product.ID = CASE
						WHEN product_post.post_parent IS NOT NULL AND product_post.post_parent > 0 THEN product_post.post_parent
						ELSE product_post.ID
					END
				LEFT JOIN {$wpdb->users} AS u ON u.ID = vendor_product.post_author
				WHERE {$where_sql}
				GROUP BY {$order_id}, COALESCE(vendor_product.post_author, 0), {$status_expr}, {$date_expr}, {$completed_parts['expr']}, u.display_name
				ORDER BY {$order_by_expr} DESC
			";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared = $wpdb->prepare( $sql, $params );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $prepared, ARRAY_A );

			if ( ! $rows ) {
				return array();
			}

			$order_group_counts = array_count_values( array_map( 'absint', wp_list_pluck( $rows, 'order_id' ) ) );

			foreach ( $rows as &$row ) {
				$order_id     = absint( $row['order_id'] );
				$single_group = isset( $order_group_counts[ $order_id ] ) && 1 === (int) $order_group_counts[ $order_id ];
				$gross        = $single_group ? (float) $row['order_total_source'] : (float) $row['line_gross'];
				$admin_fee    = max( 0, (float) $row['calculated_admin_fee'] );

				$row['order_total']  = $gross > 0 ? $gross : (float) $row['line_gross'];
				$row['net_amount']   = max( 0, (float) $row['order_total'] - $admin_fee );
				$row['order_status'] = preg_replace( '/^wc-/', '', (string) $row['post_status'] );
			}
			unset( $row );

			return $rows;
		}

		/**
		 * Prefer Dokan rows, but supplement/restore migrated WooCommerce rows.
		 *
		 * @param array $dokan_rows Dokan rows.
		 * @param array $item_rows  WooCommerce item fallback rows.
		 * @param array $f          Parsed filters.
		 * @return array<int,array<string,mixed>>
		 */
		private static function merge_order_rows( $dokan_rows, $item_rows, $f ) {
			$merged = array();

			foreach ( $dokan_rows as $row ) {
				$key            = absint( $row['order_id'] ) . ':' . absint( $row['seller_id'] );
				$merged[ $key ] = $row;
			}

			foreach ( $item_rows as $row ) {
				$key = absint( $row['order_id'] ) . ':' . absint( $row['seller_id'] );

				if ( isset( $merged[ $key ] ) ) {
					$merged[ $key ]['calculated_admin_fee'] = max(
						(float) $merged[ $key ]['calculated_admin_fee'],
						(float) $row['calculated_admin_fee']
					);
					$merged[ $key ]['line_gross'] = max(
						(float) $merged[ $key ]['line_gross'],
						(float) $row['line_gross']
					);
					if ( empty( $merged[ $key ]['vendor_name'] ) && ! empty( $row['vendor_name'] ) ) {
						$merged[ $key ]['vendor_name'] = $row['vendor_name'];
					}
					foreach ( array( 'customer_id', 'billing_email', 'billing_first_name', 'billing_last_name', 'billing_company', 'attribution_source_type', 'attribution_utm_source', 'attribution_utm_medium', 'attribution_referrer', 'attribution_session_entry', 'attribution_session_pages', 'attribution_session_count' ) as $field ) {
						if ( empty( $merged[ $key ][ $field ] ) && ! empty( $row[ $field ] ) ) {
							$merged[ $key ][ $field ] = $row[ $field ];
						}
					}
					continue;
				}

				$merged[ $key ] = $row;
			}

			usort(
				$merged,
				function ( $a, $b ) use ( $f ) {
					$a_date = self::get_row_sort_date( $a, $f );
					$b_date = self::get_row_sort_date( $b, $f );
					return strtotime( $b_date ) <=> strtotime( $a_date );
				}
			);

			return array_values( $merged );
		}

		/**
		 * Resolve WooCommerce attribution meta into a business-friendly channel.
		 */
		private static function marketing_channel( $source_type, $utm_source, $utm_medium, $referrer ) {
			$source_type = strtolower( trim( (string) $source_type ) );
			$utm_source  = strtolower( trim( (string) $utm_source ) );
			$utm_medium  = strtolower( trim( (string) $utm_medium ) );
			$referrer    = strtolower( trim( (string) $referrer ) );
			$haystack    = $source_type . ' ' . $utm_source . ' ' . $utm_medium . ' ' . $referrer;

			if ( '' === trim( $haystack ) || false !== strpos( $source_type, 'direct' ) ) {
				return __( 'Direct', 'consucorner' );
			}

			if ( preg_match( '/google|adwords|gclid|googleads|cpc|ppc|paid_search/', $haystack ) && preg_match( '/cpc|ppc|paid|ads|adwords|gclid/', $haystack ) ) {
				return __( 'Google Ads', 'consucorner' );
			}

			if ( preg_match( '/facebook|instagram|meta|fbclid|igshid/', $haystack ) && preg_match( '/paid|ads|cpc|ppc|fbclid|instagram|facebook|meta/', $haystack ) ) {
				return __( 'Meta Ads', 'consucorner' );
			}

			if ( preg_match( '/organic|search|google|bing|yahoo|duckduckgo/', $haystack ) ) {
				return __( 'SEO', 'consucorner' );
			}

			if ( preg_match( '/email|newsletter|mailchimp|klaviyo/', $haystack ) ) {
				return __( 'Email', 'consucorner' );
			}

			if ( preg_match( '/facebook|instagram|twitter|x\\.com|linkedin|tiktok|youtube|social/', $haystack ) ) {
				return __( 'Social', 'consucorner' );
			}

			if ( false !== strpos( $source_type, 'referral' ) || '' !== $referrer ) {
				return __( 'Referral', 'consucorner' );
			}

			return __( 'Other', 'consucorner' );
		}

		/**
		 * Normalize a URL/source into a short referrer label.
		 */
		private static function referrer_label( $referrer, $utm_source = '', $session_entry = '' ) {
			$referrer = trim( (string) $referrer );
			$source   = trim( (string) $utm_source );
			$entry    = trim( (string) $session_entry );

			if ( '' !== $referrer ) {
				$host = wp_parse_url( $referrer, PHP_URL_HOST );
				if ( $host ) {
					return preg_replace( '/^www\./i', '', strtolower( $host ) );
				}
				return $referrer;
			}

			if ( '' !== $source ) {
				return strtolower( $source );
			}

			if ( '' !== $entry ) {
				$host = wp_parse_url( $entry, PHP_URL_HOST );
				if ( $host ) {
					return preg_replace( '/^www\./i', '', strtolower( $host ) );
				}
			}

			return __( 'Direct / unknown', 'consucorner' );
		}

		/**
		 * Get an order's shipping total with request-level caching.
		 */
		private static function get_order_shipping_total( $order_id ) {
			static $cache = array();

			$order_id = absint( $order_id );
			if ( ! $order_id ) {
				return 0.0;
			}
			if ( isset( $cache[ $order_id ] ) ) {
				return $cache[ $order_id ];
			}

			$shipping = 0.0;
			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$shipping = (float) $order->get_shipping_total();
				}
			}

			$cache[ $order_id ] = max( 0, $shipping );
			return $cache[ $order_id ];
		}

		/**
		 * Get an order's customer-facing total with request-level caching.
		 */
		private static function get_order_display_total( $order_id, $fallback_total ) {
			static $cache = array();

			$order_id = absint( $order_id );
			if ( ! $order_id ) {
				return (float) $fallback_total;
			}
			if ( isset( $cache[ $order_id ] ) ) {
				return $cache[ $order_id ];
			}

			$total = (float) $fallback_total;
			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$total = (float) $order->get_total();
				}
			}

			$cache[ $order_id ] = max( 0, $total );
			return $cache[ $order_id ];
		}

		/**
		 * Convert aggregate stats into compact bar-list payload rows.
		 */
		private static function stats_to_rows( $stats, $total_sales, $limit = 8 ) {
			uasort(
				$stats,
				function ( $a, $b ) {
					return $b['sales'] <=> $a['sales'];
				}
			);

			$rows = array();
			foreach ( array_slice( $stats, 0, $limit, true ) as $key => $stat ) {
				$orders = isset( $stat['orders'] ) ? (int) $stat['orders'] : 0;
				$sales  = isset( $stat['sales'] ) ? (float) $stat['sales'] : 0;
				$rows[] = array(
					'key'              => (string) $key,
					'label'            => isset( $stat['label'] ) ? $stat['label'] : (string) $key,
					'sales'            => round( $sales, 2 ),
					'sales_formatted'  => self::fmt( $sales ),
					'orders'           => $orders,
					'commission'       => round( isset( $stat['commission'] ) ? (float) $stat['commission'] : 0, 2 ),
					'commission_formatted' => self::fmt( isset( $stat['commission'] ) ? (float) $stat['commission'] : 0 ),
					'vendor'           => round( isset( $stat['vendor'] ) ? (float) $stat['vendor'] : 0, 2 ),
					'vendor_formatted' => self::fmt( isset( $stat['vendor'] ) ? (float) $stat['vendor'] : 0 ),
					'aov_formatted'    => self::fmt( $orders > 0 ? $sales / $orders : 0 ),
					'percent'          => $total_sales > 0 ? round( ( $sales / $total_sales ) * 100, 1 ) : 0,
				);
			}

			return $rows;
		}

		/**
		 * Find customers' first order month for cohort analysis.
		 */
		private static function get_customer_first_order_months( $customer_ids, $emails ) {
			global $wpdb;

			$customer_ids = array_values( array_filter( array_map( 'absint', $customer_ids ) ) );
			$emails       = array_values( array_filter( array_map( 'sanitize_email', $emails ) ) );
			$map          = array();

			if ( empty( $customer_ids ) && empty( $emails ) ) {
				return $map;
			}

			$hpos_table = self::get_hpos_orders_table();
			$where      = array();
			$params     = array();

			if ( $hpos_table ) {
				$date_expr = 'wco.date_created_gmt';
				$sql_from  = "{$hpos_table} AS wco LEFT JOIN {$wpdb->posts} AS p ON p.ID = wco.id LEFT JOIN {$wpdb->postmeta} AS billing_email ON billing_email.post_id = wco.id AND billing_email.meta_key = '_billing_email'";
				if ( $customer_ids ) {
					$where[] = 'wco.customer_id IN (' . implode( ',', array_fill( 0, count( $customer_ids ), '%d' ) ) . ')';
					$params  = array_merge( $params, $customer_ids );
				}
				if ( $emails ) {
					$where[] = 'billing_email.meta_value IN (' . implode( ',', array_fill( 0, count( $emails ), '%s' ) ) . ')';
					$params  = array_merge( $params, $emails );
				}
				$sql = "SELECT COALESCE(wco.customer_id, 0) AS customer_id, billing_email.meta_value AS billing_email, MIN({$date_expr}) AS first_order_date FROM {$sql_from} WHERE (" . implode( ' OR ', $where ) . ") GROUP BY COALESCE(wco.customer_id, 0), billing_email.meta_value";
			} else {
				$sql_from = "{$wpdb->posts} AS p LEFT JOIN {$wpdb->postmeta} AS customer_user ON customer_user.post_id = p.ID AND customer_user.meta_key = '_customer_user' LEFT JOIN {$wpdb->postmeta} AS billing_email ON billing_email.post_id = p.ID AND billing_email.meta_key = '_billing_email'";
				if ( $customer_ids ) {
					$where[] = 'CAST(customer_user.meta_value AS UNSIGNED) IN (' . implode( ',', array_fill( 0, count( $customer_ids ), '%d' ) ) . ')';
					$params  = array_merge( $params, $customer_ids );
				}
				if ( $emails ) {
					$where[] = 'billing_email.meta_value IN (' . implode( ',', array_fill( 0, count( $emails ), '%s' ) ) . ')';
					$params  = array_merge( $params, $emails );
				}
				$sql = "SELECT COALESCE(customer_user.meta_value, 0) AS customer_id, billing_email.meta_value AS billing_email, MIN(p.post_date) AS first_order_date FROM {$sql_from} WHERE p.post_type = 'shop_order' AND (" . implode( ' OR ', $where ) . ") GROUP BY COALESCE(customer_user.meta_value, 0), billing_email.meta_value";
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared = $wpdb->prepare( $sql, $params );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $prepared, ARRAY_A );

			foreach ( $rows as $row ) {
				$month = ! empty( $row['first_order_date'] ) ? gmdate( 'Y-m', strtotime( $row['first_order_date'] ) ) : '';
				if ( ! $month ) {
					continue;
				}
				if ( ! empty( $row['customer_id'] ) ) {
					$map[ 'id:' . absint( $row['customer_id'] ) ] = $month;
				}
				if ( ! empty( $row['billing_email'] ) ) {
					$map[ 'email:' . strtolower( sanitize_email( $row['billing_email'] ) ) ] = $month;
				}
			}

			return $map;
		}

		/**
		 * Product revenue and sell-through analytics for the selected completed-date range.
		 */
		private static function query_product_analytics( $f ) {
			global $wpdb;

			$where  = array();
			$params = array();

			$hpos_table = self::get_hpos_orders_table();
			if ( $hpos_table ) {
				$from_order  = "(
					SELECT p.ID AS order_id, p.post_date AS order_date, p.post_status AS order_status
					FROM {$wpdb->posts} AS p
					WHERE p.post_type = 'shop_order'
					UNION ALL
					SELECT wco.id AS order_id, wco.date_created_gmt AS order_date, wco.status AS order_status
					FROM {$hpos_table} AS wco
					LEFT JOIN {$wpdb->posts} AS legacy_order ON legacy_order.ID = wco.id
					WHERE legacy_order.ID IS NULL
				) AS orders_src";
				$order_id    = 'orders_src.order_id';
				$date_expr   = 'orders_src.order_date';
				$status_expr = 'orders_src.order_status';
				$completed_parts = self::get_completed_date_expr_and_join( $order_id );
			} else {
				$from_order  = "{$wpdb->posts} AS p";
				$order_id    = 'p.ID';
				$date_expr   = 'p.post_date';
				$status_expr = 'p.post_status';
				$completed_parts = self::get_completed_date_expr_and_join( $order_id );
				$where[]     = 'p.post_type = %s';
				$params[]    = 'shop_order';
			}

			self::append_active_date_where( $where, $params, $f, $date_expr, $completed_parts['expr'] );

			if ( $f['vendor_id'] > 0 ) {
				$where[]  = 'vendor_product.post_author = %d';
				$params[] = $f['vendor_id'];
			}

			if ( ! empty( $f['status'] ) ) {
				$bare     = preg_replace( '/^wc-/', '', $f['status'] );
				$where[]  = "({$status_expr} = %s OR {$status_expr} = %s)";
				$params[] = $bare;
				$params[] = $f['status'];
			}

			$rows = array();
			$lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$has_lookup = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $lookup_table ) );
			if ( $has_lookup ) {
				$lookup_sql = "
					SELECT
						COALESCE(vendor_product.ID, 0) AS product_id,
						COALESCE(NULLIF(vendor_product.post_title, ''), CONCAT('Product #', opl.product_id)) AS product_name,
						SUM(CAST(COALESCE(opl.product_net_revenue, 0) AS DECIMAL(20,6))) AS sales,
						SUM(CAST(COALESCE(opl.product_qty, 0) AS DECIMAL(20,6))) AS quantity,
						MAX(CAST(COALESCE(stock.meta_value, 0) AS DECIMAL(20,6))) AS current_stock,
						MAX(CASE WHEN stock.meta_value IS NULL THEN 0 ELSE 1 END) AS has_stock
					FROM {$from_order}
					{$completed_parts['joins']}
					INNER JOIN {$lookup_table} AS opl ON opl.order_id = {$order_id}
					LEFT JOIN {$wpdb->posts} AS product_post ON product_post.ID = CASE
						WHEN CAST(COALESCE(opl.product_id, 0) AS UNSIGNED) > 0 THEN CAST(opl.product_id AS UNSIGNED)
						ELSE CAST(COALESCE(opl.variation_id, 0) AS UNSIGNED)
					END
					LEFT JOIN {$wpdb->posts} AS vendor_product ON vendor_product.ID = CASE WHEN product_post.post_parent IS NOT NULL AND product_post.post_parent > 0 THEN product_post.post_parent ELSE product_post.ID END
					LEFT JOIN {$wpdb->postmeta} AS stock ON stock.post_id = vendor_product.ID AND stock.meta_key = '_stock'
					WHERE " . implode( ' AND ', $where ) . "
					GROUP BY COALESCE(vendor_product.ID, 0), COALESCE(NULLIF(vendor_product.post_title, ''), CONCAT('Product #', opl.product_id))
					HAVING product_name IS NOT NULL AND product_name <> ''
				";

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$lookup_prepared = $wpdb->prepare( $lookup_sql, $params );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$lookup_rows = $wpdb->get_results( $lookup_prepared, ARRAY_A );
				if ( $lookup_rows ) {
					$rows = $lookup_rows;
				}
			}

			if ( empty( $rows ) ) {
				$sql = "
				SELECT
					COALESCE(vendor_product.ID, 0) AS product_id,
					COALESCE(NULLIF(vendor_product.post_title, ''), oi.order_item_name) AS product_name,
					SUM(CAST(COALESCE(line_total.meta_value, 0) AS DECIMAL(20,6))) AS sales,
					SUM(CAST(COALESCE(qty.meta_value, 0) AS DECIMAL(20,6))) AS quantity,
					MAX(CAST(COALESCE(stock.meta_value, 0) AS DECIMAL(20,6))) AS current_stock,
					MAX(CASE WHEN stock.meta_value IS NULL THEN 0 ELSE 1 END) AS has_stock
				FROM {$from_order}
				{$completed_parts['joins']}
				INNER JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON oi.order_id = {$order_id} AND oi.order_item_type = 'line_item'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS product_id_meta ON product_id_meta.order_item_id = oi.order_item_id AND product_id_meta.meta_key = '_product_id'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS variation_id_meta ON variation_id_meta.order_item_id = oi.order_item_id AND variation_id_meta.meta_key = '_variation_id'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS line_total ON line_total.order_item_id = oi.order_item_id AND line_total.meta_key = '_line_total'
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS qty ON qty.order_item_id = oi.order_item_id AND qty.meta_key = '_qty'
				LEFT JOIN {$wpdb->posts} AS product_post ON product_post.ID = CASE
					WHEN CAST(COALESCE(product_id_meta.meta_value, 0) AS UNSIGNED) > 0 THEN CAST(product_id_meta.meta_value AS UNSIGNED)
					ELSE CAST(COALESCE(variation_id_meta.meta_value, 0) AS UNSIGNED)
				END
				LEFT JOIN {$wpdb->posts} AS vendor_product ON vendor_product.ID = CASE WHEN product_post.post_parent IS NOT NULL AND product_post.post_parent > 0 THEN product_post.post_parent ELSE product_post.ID END
				LEFT JOIN {$wpdb->postmeta} AS stock ON stock.post_id = vendor_product.ID AND stock.meta_key = '_stock'
				WHERE " . implode( ' AND ', $where ) . "
				GROUP BY COALESCE(vendor_product.ID, 0), COALESCE(NULLIF(vendor_product.post_title, ''), oi.order_item_name)
				HAVING product_name IS NOT NULL AND product_name <> ''
			";

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$prepared = $wpdb->prepare( $sql, $params );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_results( $prepared, ARRAY_A );
			}

			if ( ! $rows ) {
				$rows = self::query_product_analytics_via_wc_api( $f );
				if ( ! $rows ) {
					return array(
						'product_sales' => array(),
						'sell_through'  => array(),
					);
				}
			}

			$total_product_sales = array_sum( array_map( 'floatval', wp_list_pluck( $rows, 'sales' ) ) );
			usort(
				$rows,
				function ( $a, $b ) {
					return (float) $b['sales'] <=> (float) $a['sales'];
				}
			);

			$product_sales = array();
			$sell_through  = array();
			foreach ( $rows as $row ) {
				$sales = (float) $row['sales'];
				$sold  = (float) $row['quantity'];
				$stock = (float) $row['current_stock'];
				$rate  = ( ! empty( $row['has_stock'] ) && ( $sold + $stock ) > 0 ) ? ( $sold / ( $sold + $stock ) ) * 100 : null;

				$base = array(
					'id'              => (int) $row['product_id'],
					'label'           => $row['product_name'] ? $row['product_name'] : __( 'Unknown product', 'consucorner' ),
					'sales'           => round( $sales, 2 ),
					'sales_formatted' => self::fmt( $sales ),
					'quantity'        => (float) $sold,
					'orders'          => (float) $sold,
					'percent'         => $total_product_sales > 0 ? round( ( $sales / $total_product_sales ) * 100, 1 ) : 0,
				);

				$product_sales[] = $base;

				$base['stock']                = ! empty( $row['has_stock'] ) ? $stock : null;
				$base['sell_through_percent'] = null === $rate ? null : round( $rate, 1 );
				$base['percent']              = null === $rate ? 0 : round( $rate, 1 );
				$sell_through[]               = $base;
			}

			usort(
				$sell_through,
				function ( $a, $b ) {
					return (float) $b['percent'] <=> (float) $a['percent'];
				}
			);

			return array(
				'product_sales' => array_slice( $product_sales, 0, 8 ),
				'sell_through'  => array_slice( $sell_through, 0, 8 ),
			);
		}

		/**
		 * Product analytics fallback for stores where lookup tables or item meta
		 * were not populated by imports/seeding. Runs only after SQL returns empty.
		 */
		private static function query_product_analytics_via_wc_api( $f ) {
			if ( ! function_exists( 'wc_get_orders' ) ) {
				return array();
			}

			if ( 'created' === $f['date_filter_mode'] ) {
				$args = array(
					'limit'        => 500,
					'orderby'      => 'date',
					'order'        => 'DESC',
					'date_created' => gmdate( 'Y-m-d H:i:s', strtotime( $f['created_start_date'] ) ) . '...' . gmdate( 'Y-m-d H:i:s', strtotime( $f['created_end_date'] ) ),
				);
			} else {
				$args = array(
					'limit'          => 500,
					'orderby'        => 'date_completed',
					'order'          => 'DESC',
					'date_completed' => gmdate( 'Y-m-d H:i:s', strtotime( $f['completed_start_date'] ) ) . '...' . gmdate( 'Y-m-d H:i:s', strtotime( $f['completed_end_date'] ) ),
				);
			}

			if ( ! empty( $f['status'] ) ) {
				$args['status'] = preg_replace( '/^wc-/', '', $f['status'] );
			}

			$orders = wc_get_orders( $args );
			if ( ! $orders ) {
				return array();
			}

			$products = array();
			foreach ( $orders as $order ) {
				if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
					continue;
				}

				if ( 'completed' === $f['date_filter_mode'] && ! $order->get_date_completed() ) {
					continue;
				}

				foreach ( $order->get_items( 'line_item' ) as $item ) {
					if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
						continue;
					}

					$product = $item->get_product();
					$product_id = $item->get_product_id();
					$parent_id  = 0;

					if ( $product && $product->is_type( 'variation' ) && method_exists( $product, 'get_parent_id' ) ) {
						$parent_id = absint( $product->get_parent_id() );
					}

					$report_product_id = $parent_id > 0 ? $parent_id : absint( $product_id );
					$vendor_id         = $report_product_id > 0 ? (int) get_post_field( 'post_author', $report_product_id ) : 0;

					if ( $f['vendor_id'] > 0 && $vendor_id !== (int) $f['vendor_id'] ) {
						continue;
					}

					$key   = $report_product_id > 0 ? 'product:' . $report_product_id : 'name:' . sanitize_title( $item->get_name() );
					$name  = $report_product_id > 0 ? get_the_title( $report_product_id ) : $item->get_name();
					$stock = null;

					if ( $product ) {
						$stock_product = $parent_id > 0 ? wc_get_product( $parent_id ) : $product;
						if ( $stock_product && $stock_product->managing_stock() ) {
							$stock = max( 0, (float) $stock_product->get_stock_quantity() );
						}
					}

					if ( ! isset( $products[ $key ] ) ) {
						$products[ $key ] = array(
							'product_id'    => $report_product_id,
							'product_name'  => $name ? $name : $item->get_name(),
							'sales'         => 0.0,
							'quantity'      => 0.0,
							'current_stock' => 0.0,
							'has_stock'     => 0,
						);
					}

					$products[ $key ]['sales']    += (float) $item->get_total();
					$products[ $key ]['quantity'] += (float) $item->get_quantity();
					if ( null !== $stock ) {
						$products[ $key ]['current_stock'] = $stock;
						$products[ $key ]['has_stock']     = 1;
					}
				}
			}

			return array_values( $products );
		}

		/**
		 * Build summary, daily chart series, and table rows from raw query results.
		 *
		 * @param array $rows Raw rows.
		 * @param array $f    Parsed filters (for date range).
		 * @param int $table_limit Maximum rows to include in the JSON table. 0 = all rows.
		 * @return array
		 */
		public static function build_dataset( $rows, $f, $table_limit = 300 ) {
			$total_sales      = 0.0;
			$total_commission = 0.0;
			$total_vendor     = 0.0;
			$orders_count     = 0;
			$vendor_stats     = array();
			$status_stats     = array();
			$active_vendors   = array();
			$channel_stats    = array();
			$referrer_stats   = array();
			$session_stats    = array();
			$session_seen     = array();
			$cohort_entries   = array();
			$customer_ids     = array();
			$customer_emails  = array();
			$customer_stats   = array();

			$start_ts = 'created' === $f['date_filter_mode']
				? (int) strtotime( $f['created_start_date'] )
				: (int) strtotime( $f['completed_start_date'] );
			$end_ts   = 'created' === $f['date_filter_mode']
				? (int) strtotime( $f['created_end_date'] )
				: (int) strtotime( $f['completed_end_date'] );

			$series = array();
			for ( $ts = $start_ts; $ts <= $end_ts; $ts += DAY_IN_SECONDS ) {
				$series[ gmdate( 'Y-m-d', $ts ) ] = array(
					'sales'      => 0.0,
					'commission' => 0.0,
					'vendor'     => 0.0,
					'orders'     => 0,
				);
			}

			$table_rows = array();
			$dates_are_gmt = (bool) self::get_hpos_orders_table();

			foreach ( $rows as $r ) {
				$gross  = (float) $r['order_total'];
				$vendor = (float) $r['net_amount'];
				$admin  = max( 0, $gross - $vendor );
				$calculated_admin = isset( $r['calculated_admin_fee'] ) ? max( 0, (float) $r['calculated_admin_fee'] ) : 0;

				if ( $admin <= 0 && $calculated_admin > 0 ) {
					$admin  = min( $gross, $calculated_admin );
					$vendor = max( 0, $gross - $admin );
				}

				if ( $gross <= 0 && ! empty( $r['line_gross'] ) ) {
					$gross  = (float) $r['line_gross'];
					$admin  = min( $gross, $calculated_admin );
					$vendor = max( 0, $gross - $admin );
				}

				$raw_status = $r['order_status'] ? $r['order_status'] : (string) $r['post_status'];
				$status_key = preg_replace( '/^wc-/', '', (string) $raw_status );
				$vendor_id  = (int) $r['seller_id'];
				$v_name     = $r['vendor_name'] ? $r['vendor_name'] : __( 'Unknown vendor', 'consucorner' );
				$order_id   = (int) $r['order_id'];
				$channel    = self::marketing_channel(
					isset( $r['attribution_source_type'] ) ? $r['attribution_source_type'] : '',
					isset( $r['attribution_utm_source'] ) ? $r['attribution_utm_source'] : '',
					isset( $r['attribution_utm_medium'] ) ? $r['attribution_utm_medium'] : '',
					isset( $r['attribution_referrer'] ) ? $r['attribution_referrer'] : ''
				);
				$referrer   = self::referrer_label(
					isset( $r['attribution_referrer'] ) ? $r['attribution_referrer'] : '',
					isset( $r['attribution_utm_source'] ) ? $r['attribution_utm_source'] : '',
					isset( $r['attribution_session_entry'] ) ? $r['attribution_session_entry'] : ''
				);
				$customer_id = isset( $r['customer_id'] ) ? absint( $r['customer_id'] ) : 0;
				$customer_email = ! empty( $r['billing_email'] ) ? strtolower( sanitize_email( $r['billing_email'] ) ) : '';
				$customer_key = $customer_id > 0 ? 'id:' . $customer_id : ( $customer_email ? 'email:' . $customer_email : 'guest:' . $order_id );

				if ( ! isset( $customer_stats[ $customer_key ] ) ) {
					$customer_stats[ $customer_key ] = array(
						'customer_id' => $customer_id,
						'email'       => $customer_email,
						'orders'      => array(),
						'sales'       => 0.0,
						'vendors'     => array(),
						'first_order' => $r['order_date'],
						'last_order'  => $r['order_date'],
					);
				}
				$customer_stats[ $customer_key ]['orders'][ $order_id ] = true;
				$customer_stats[ $customer_key ]['sales'] += $gross;
				if ( $vendor_id > 0 ) {
					$customer_stats[ $customer_key ]['vendors'][ $vendor_id ] = true;
				}
				if ( strtotime( $r['order_date'] ) < strtotime( $customer_stats[ $customer_key ]['first_order'] ) ) {
					$customer_stats[ $customer_key ]['first_order'] = $r['order_date'];
				}
				if ( strtotime( $r['order_date'] ) > strtotime( $customer_stats[ $customer_key ]['last_order'] ) ) {
					$customer_stats[ $customer_key ]['last_order'] = $r['order_date'];
				}

				$total_sales      += $gross;
				$total_vendor     += $vendor;
				$total_commission += $admin;
				++$orders_count;

				$bucket_date = self::get_chart_bucket_date( $r, $f );
				$day         = $bucket_date ? self::format_order_date_iso( $bucket_date, $dates_are_gmt ) : '';
				if ( $day && isset( $series[ $day ] ) ) {
					$series[ $day ]['sales']      += $gross;
					$series[ $day ]['commission'] += $admin;
					$series[ $day ]['vendor']     += $vendor;
					$series[ $day ]['orders']++;
				}

				if ( $vendor_id > 0 ) {
					$active_vendors[ $vendor_id ] = true;
				}

				if ( ! isset( $vendor_stats[ $vendor_id ] ) ) {
					$vendor_stats[ $vendor_id ] = array(
						'name'       => $v_name,
						'sales'      => 0.0,
						'commission' => 0.0,
						'vendor'     => 0.0,
						'orders'     => 0,
					);
				}
				$vendor_stats[ $vendor_id ]['sales']      += $gross;
				$vendor_stats[ $vendor_id ]['commission'] += $admin;
				$vendor_stats[ $vendor_id ]['vendor']     += $vendor;
				$vendor_stats[ $vendor_id ]['orders']++;

				if ( ! isset( $status_stats[ $status_key ] ) ) {
					$status_stats[ $status_key ] = array(
						'label'  => self::status_label( $status_key ),
						'sales'  => 0.0,
						'orders' => 0,
					);
				}
				$status_stats[ $status_key ]['sales'] += $gross;
				$status_stats[ $status_key ]['orders']++;

				if ( ! isset( $channel_stats[ $channel ] ) ) {
					$channel_stats[ $channel ] = array(
						'label'      => $channel,
						'sales'      => 0.0,
						'commission' => 0.0,
						'vendor'     => 0.0,
						'orders'     => 0,
					);
				}
				$channel_stats[ $channel ]['sales']      += $gross;
				$channel_stats[ $channel ]['commission'] += $admin;
				$channel_stats[ $channel ]['vendor']     += $vendor;
				$channel_stats[ $channel ]['orders']++;

				if ( ! isset( $referrer_stats[ $referrer ] ) ) {
					$referrer_stats[ $referrer ] = array(
						'label'      => $referrer,
						'sales'      => 0.0,
						'commission' => 0.0,
						'vendor'     => 0.0,
						'orders'     => 0,
					);
				}
				$referrer_stats[ $referrer ]['sales']      += $gross;
				$referrer_stats[ $referrer ]['commission'] += $admin;
				$referrer_stats[ $referrer ]['vendor']     += $vendor;
				$referrer_stats[ $referrer ]['orders']++;

				$session_count = isset( $r['attribution_session_count'] ) ? absint( $r['attribution_session_count'] ) : 0;
				$session_count = max( 1, $session_count );
				if ( ! isset( $session_seen[ $referrer ][ $order_id ] ) ) {
					if ( ! isset( $session_stats[ $referrer ] ) ) {
						$session_stats[ $referrer ] = array(
							'label'    => $referrer,
							'sessions' => 0,
							'orders'   => 0,
						);
					}
					$session_stats[ $referrer ]['sessions'] += $session_count;
					$session_stats[ $referrer ]['orders']++;
					$session_seen[ $referrer ][ $order_id ] = true;
				}

				$cohort_entries[] = array(
					'customer_key' => $customer_key,
					'order_date'   => $r['order_date'],
					'gross'        => $gross,
					'order_id'     => $order_id,
				);
				if ( $customer_id > 0 ) {
					$customer_ids[ $customer_id ] = $customer_id;
				}
				if ( $customer_email ) {
					$customer_emails[ $customer_email ] = $customer_email;
				}

				if ( 0 === $table_limit || count( $table_rows ) < $table_limit ) {
					$shipping_total         = self::get_order_shipping_total( $order_id );
					$display_gross          = self::get_order_display_total( $order_id, $gross );
					$total_without_shipping = max( 0, $display_gross - $shipping_total );
					$return_deductions      = class_exists( 'Consucorner_Order_Return_Workflow' )
						? Consucorner_Order_Return_Workflow::get_vendor_return_deductions( $order_id, $vendor_id )
						: 0.0;
					$net_payable            = max( 0, $vendor );
					$table_rows[] = array(
						'order_id'       => (int) $r['order_id'],
						'order_url'      => self::get_order_edit_url( $order_id ),
						'order_number'   => '#' . cc_build_order_display_number( (int) $r['order_id'], strtotime( $r['order_date'] ) ),
						'billing_name'   => self::format_billing_name( $r ),
						'date'           => self::format_order_datetime_for_display( $r['order_date'], $dates_are_gmt ),
						'date_iso'       => self::format_order_date_iso( $r['order_date'], $dates_are_gmt ),
						'completed'      => ! empty( $r['completed_date'] ) ? self::format_order_datetime_for_display( $r['completed_date'], $dates_are_gmt ) : '—',
						'completed_iso'  => self::format_order_date_iso( isset( $r['completed_date'] ) ? $r['completed_date'] : '', $dates_are_gmt ),
						'vendor_name'    => $v_name,
						'vendor_id'      => $vendor_id,
						'gross_total'    => round( $display_gross, 2 ),
						'shipping_total' => round( $shipping_total, 2 ),
						'total_without_shipping' => round( $total_without_shipping, 2 ),
						'admin_fee'      => round( $admin, 2 ),
						'vendor_earning' => round( $vendor, 2 ),
						'return_deductions' => round( $return_deductions, 2 ),
						'net_payable'    => round( $net_payable, 2 ),
						'status'         => $status_key,
						'status_label'   => self::status_label( $status_key ),
					);
				}
			}

			$labels      = array_keys( $series );
			$sales_data  = array();
			$comm_data   = array();
			$vendor_data = array();
			$order_data  = array();
			$best_day    = array(
				'label' => '—',
				'sales' => 0.0,
			);
			foreach ( $series as $day_key => $bucket ) {
				$sales_data[] = round( $bucket['sales'], 2 );
				$comm_data[]  = round( $bucket['commission'], 2 );
				$vendor_data[] = round( $bucket['vendor'], 2 );
				$order_data[]  = (int) $bucket['orders'];

				if ( $bucket['sales'] > $best_day['sales'] ) {
					$best_day = array(
						'label' => mysql2date( get_option( 'date_format' ), $day_key ),
						'sales' => $bucket['sales'],
					);
				}
			}

			uasort(
				$vendor_stats,
				function ( $a, $b ) {
					return $b['sales'] <=> $a['sales'];
				}
			);
			uasort(
				$status_stats,
				function ( $a, $b ) {
					return $b['sales'] <=> $a['sales'];
				}
			);

			$top_vendors = array();
			foreach ( array_slice( $vendor_stats, 0, 7, true ) as $vendor_id => $stat ) {
				$top_vendors[] = array(
					'id'              => (int) $vendor_id,
					'name'            => $stat['name'],
					'sales'           => round( $stat['sales'], 2 ),
					'sales_formatted' => self::fmt( $stat['sales'] ),
					'orders'          => (int) $stat['orders'],
					'percent'         => $total_sales > 0 ? round( ( $stat['sales'] / $total_sales ) * 100, 1 ) : 0,
				);
			}

			$status_breakdown = array();
			foreach ( $status_stats as $status_key => $stat ) {
				$status_breakdown[] = array(
					'key'             => $status_key,
					'label'           => $stat['label'],
					'sales'           => round( $stat['sales'], 2 ),
					'sales_formatted' => self::fmt( $stat['sales'] ),
					'orders'          => (int) $stat['orders'],
					'percent'         => $total_sales > 0 ? round( ( $stat['sales'] / $total_sales ) * 100, 1 ) : 0,
				);
			}

			$sales_by_channel     = self::stats_to_rows( $channel_stats, $total_sales, 8 );
			$referring_channels   = self::stats_to_rows( $channel_stats, $total_sales, 8 );
			$sales_by_referrer    = self::stats_to_rows( $referrer_stats, $total_sales, 8 );
			$product_analytics    = self::query_product_analytics( $f );

			uasort(
				$session_stats,
				function ( $a, $b ) {
					return $b['sessions'] <=> $a['sessions'];
				}
			);
			$total_sessions = array_sum( array_map( 'absint', wp_list_pluck( $session_stats, 'sessions' ) ) );
			$sessions_by_referrer = array();
			foreach ( array_slice( $session_stats, 0, 8, true ) as $key => $stat ) {
				$sessions_by_referrer[] = array(
					'key'       => (string) $key,
					'label'     => $stat['label'],
					'sessions'  => (int) $stat['sessions'],
					'orders'    => (int) $stat['orders'],
					'percent'   => $total_sessions > 0 ? round( ( $stat['sessions'] / $total_sessions ) * 100, 1 ) : 0,
				);
			}

			$first_order_months = self::get_customer_first_order_months( $customer_ids, $customer_emails );
			$cohort_stats       = array();
			$cohort_order_seen  = array();
			foreach ( $cohort_entries as $entry ) {
				$cohort_month = isset( $first_order_months[ $entry['customer_key'] ] ) ? $first_order_months[ $entry['customer_key'] ] : gmdate( 'Y-m', strtotime( $entry['order_date'] ) );
				if ( ! isset( $cohort_stats[ $cohort_month ] ) ) {
					$cohort_stats[ $cohort_month ] = array(
						'label'     => mysql2date( 'M Y', $cohort_month . '-01' ),
						'sales'     => 0.0,
						'orders'    => 0,
						'customers' => array(),
					);
				}
				$cohort_stats[ $cohort_month ]['sales'] += (float) $entry['gross'];
				$cohort_stats[ $cohort_month ]['customers'][ $entry['customer_key'] ] = true;
				$order_key = $cohort_month . ':' . (int) $entry['order_id'];
				if ( ! isset( $cohort_order_seen[ $order_key ] ) ) {
					$cohort_stats[ $cohort_month ]['orders']++;
					$cohort_order_seen[ $order_key ] = true;
				}
			}
			krsort( $cohort_stats );
			$customer_cohorts = array();
			foreach ( array_slice( $cohort_stats, 0, 8, true ) as $month => $stat ) {
				$customer_cohorts[] = array(
					'key'              => $month,
					'label'            => $stat['label'],
					'sales'            => round( $stat['sales'], 2 ),
					'sales_formatted'  => self::fmt( $stat['sales'] ),
					'orders'           => (int) $stat['orders'],
					'customers'        => count( $stat['customers'] ),
					'percent'          => $total_sales > 0 ? round( ( $stat['sales'] / $total_sales ) * 100, 1 ) : 0,
				);
			}

			$average_order_value = $orders_count > 0 ? $total_sales / $orders_count : 0;
			$commission_rate     = $total_sales > 0 ? ( $total_commission / $total_sales ) * 100 : 0;
			$total_customers     = count( $customer_stats );
			$return_customers    = 0;
			foreach ( $customer_stats as $customer_stat ) {
				if ( count( $customer_stat['orders'] ) > 1 ) {
					++$return_customers;
				}
			}
			$retention_rate      = $total_customers > 0 ? ( $return_customers / $total_customers ) * 100 : 0;
			$average_ltv         = $total_customers > 0 ? $total_sales / $total_customers : 0;
			$customer_rows       = array();
			foreach ( $customer_stats as $customer_key => $customer_stat ) {
				$customer_order_count = count( $customer_stat['orders'] );
				$customer_id          = isset( $customer_stat['customer_id'] ) ? absint( $customer_stat['customer_id'] ) : 0;
				$customer_email       = isset( $customer_stat['email'] ) ? (string) $customer_stat['email'] : '';
				$customer_name        = '';

				if ( $customer_id > 0 ) {
					$user = get_userdata( $customer_id );
					if ( $user ) {
						$customer_name = $user->display_name;
					}
				}
				if ( '' === $customer_name ) {
					$customer_name = $customer_email ? $customer_email : __( 'Guest customer', 'consucorner' );
				}

				$customer_rows[] = array(
					'customer_key'      => $customer_key,
					'customer_id'       => $customer_id,
					'customer_name'     => $customer_name,
					'email'             => $customer_email ? $customer_email : '—',
					'type'              => $customer_id > 0 ? __( 'Registered', 'consucorner' ) : __( 'Guest', 'consucorner' ),
					'orders'            => $customer_order_count,
					'total_spent'       => round( $customer_stat['sales'], 2 ),
					'total_spent_label' => self::fmt( $customer_stat['sales'] ),
					'aov'               => $customer_order_count > 0 ? round( $customer_stat['sales'] / $customer_order_count, 2 ) : 0,
					'aov_label'         => self::fmt( $customer_order_count > 0 ? $customer_stat['sales'] / $customer_order_count : 0 ),
					'first_order'       => mysql2date( get_option( 'date_format' ), $customer_stat['first_order'] ),
					'last_order'        => mysql2date( get_option( 'date_format' ), $customer_stat['last_order'] ),
					'vendor_count'      => count( $customer_stat['vendors'] ),
					'is_returning'      => $customer_order_count > 1,
					'returning_label'   => $customer_order_count > 1 ? __( 'Returning', 'consucorner' ) : __( 'New', 'consucorner' ),
				);
			}
			usort(
				$customer_rows,
				function ( $a, $b ) {
					return $b['total_spent'] <=> $a['total_spent'];
				}
			);

			return array(
				'summary' => array(
					'total_sales'      => self::fmt( $total_sales ),
					'total_commission' => self::fmt( $total_commission ),
					'vendor_earnings'  => self::fmt( $total_vendor ),
					'orders_count'     => (int) $orders_count,
					'average_order'    => self::fmt( $average_order_value ),
					'active_vendors'   => count( $active_vendors ),
					'commission_rate'  => number_format_i18n( $commission_rate, 1 ) . '%',
					'best_day'         => $best_day['label'],
					'best_day_sales'   => self::fmt( $best_day['sales'] ),
					'retention_rate'   => number_format_i18n( $retention_rate, 1 ) . '%',
					'average_ltv'      => self::fmt( $average_ltv ),
					'return_customers' => (int) $return_customers,
					'total_customers'  => (int) $total_customers,
					'table_showing'    => count( $table_rows ),
				),
				'chart'   => array(
					'labels'     => $labels,
					'sales'      => $sales_data,
					'commission' => $comm_data,
					'vendor'     => $vendor_data,
					'orders'     => $order_data,
				),
				'analytics' => array(
					'top_vendors'       => $top_vendors,
					'status_breakdown'  => $status_breakdown,
					'earnings_split'    => array(
						'labels' => array( __( 'Admin Commission', 'consucorner' ), __( 'Vendor Earnings', 'consucorner' ) ),
						'data'   => array( round( $total_commission, 2 ), round( $total_vendor, 2 ) ),
					),
					'sales_by_channel'      => $sales_by_channel,
					'sales_by_product'      => $product_analytics['product_sales'],
					'customer_cohorts'      => $customer_cohorts,
					'customer_rows'         => $customer_rows,
					'referring_channels'    => $referring_channels,
					'sales_by_referrer'     => $sales_by_referrer,
					'sessions_by_referrer'  => $sessions_by_referrer,
					'product_sell_through'  => $product_analytics['sell_through'],
				),
				'rows'    => $table_rows,
				'filters' => $f,
			);
		}

		/**
		 * Format an amount using wc_price() with safe fallback.
		 */
		private static function fmt( $amount ) {
			if ( function_exists( 'wc_price' ) ) {
				return wc_price( $amount );
			}
			return number_format( (float) $amount, 2 );
		}

		/**
		 * Translate a bare status key into its label.
		 */
		private static function status_label( $key ) {
			$statuses = self::get_statuses();
			$with     = 'wc-' . preg_replace( '/^wc-/', '', (string) $key );
			if ( isset( $statuses[ $with ] ) ) {
				return $statuses[ $with ];
			}
			return ucwords( str_replace( array( 'wc-', '-', '_' ), array( '', ' ', ' ' ), (string) $key ) );
		}

		/**
		 * AJAX: WooCommerce order preview payload for the ledger table eye icon.
		 */
		public static function ajax_order_preview() {
			check_ajax_referer( self::NONCE_KEY, 'nonce' );

			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'consucorner' ) ), 403 );
			}

			$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
			if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid order.', 'consucorner' ) ), 400 );
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				wp_send_json_error( array( 'message' => __( 'Order not found.', 'consucorner' ) ), 404 );
			}

			if ( ! class_exists( 'WC_Admin_List_Table_Orders' ) ) {
				require_once WC_ABSPATH . 'includes/admin/list-tables/class-wc-admin-list-table-orders.php';
			}

			// Bosta plugin redirects to settings when API key is missing inside this filter.
			$bosta_filter_removed = false;
			if ( has_filter( 'woocommerce_admin_order_preview_get_order_details', 'bosta_admin_order_preview_add_custom_meta_data' ) ) {
				remove_filter( 'woocommerce_admin_order_preview_get_order_details', 'bosta_admin_order_preview_add_custom_meta_data', 10 );
				$bosta_filter_removed = true;
			}

			$details = WC_Admin_List_Table_Orders::order_preview_get_order_details( $order );

			if ( $bosta_filter_removed ) {
				add_filter( 'woocommerce_admin_order_preview_get_order_details', 'bosta_admin_order_preview_add_custom_meta_data', 10, 2 );
			}

			wp_send_json_success( $details );
		}

		/**
		 * AJAX: return JSON dataset (summary + chart + rows).
		 */
		public static function ajax_filter() {
			check_ajax_referer( self::NONCE_KEY, 'nonce' );

			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'consucorner' ) ), 403 );
			}

			$f    = self::parse_filters( $_POST );
			$rows = self::query_orders( $f );
			$data = self::build_dataset( $rows, $f, 50 );

			wp_send_json_success( $data );
		}

		/**
		 * AJAX: stream the current filter result as a CSV download.
		 */
		public static function ajax_export_csv() {
			check_ajax_referer( self::NONCE_KEY, 'nonce' );

			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'Permission denied.', 'consucorner' ), 403 );
			}

			$f    = self::parse_filters( $_GET );
			$rows = self::query_orders( $f );
			$data = self::build_dataset( $rows, $f, 0 );
			$type = isset( $_GET['export_type'] ) ? sanitize_key( wp_unslash( $_GET['export_type'] ) ) : 'orders';

			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . ( 'customers' === $type ? 'vendor-ledger-customers-' : 'vendor-ledger-' ) . gmdate( 'Ymd-His' ) . '.csv"' );

			$out = fopen( 'php://output', 'w' );
			// UTF-8 BOM so Excel recognizes encoding.
			fwrite( $out, "\xEF\xBB\xBF" );

			if ( 'customers' === $type ) {
				fputcsv(
					$out,
					array(
						__( 'Customer ID', 'consucorner' ),
						__( 'Customer Name', 'consucorner' ),
						__( 'Email', 'consucorner' ),
						__( 'Type', 'consucorner' ),
						__( 'Customer Status', 'consucorner' ),
						__( 'Orders', 'consucorner' ),
						__( 'Total Spent / LTV', 'consucorner' ),
						__( 'Average Order Value', 'consucorner' ),
						__( 'First Order', 'consucorner' ),
						__( 'Last Order', 'consucorner' ),
						__( 'Vendors Purchased From', 'consucorner' ),
					)
				);

				foreach ( $data['analytics']['customer_rows'] as $r ) {
					fputcsv(
						$out,
						array(
							$r['customer_id'] ? (int) $r['customer_id'] : '',
							wp_strip_all_tags( $r['customer_name'] ),
							'—' === $r['email'] ? '' : wp_strip_all_tags( $r['email'] ),
							wp_strip_all_tags( $r['type'] ),
							wp_strip_all_tags( $r['returning_label'] ),
							(int) $r['orders'],
							number_format( (float) $r['total_spent'], 2, '.', '' ),
							number_format( (float) $r['aov'], 2, '.', '' ),
							wp_strip_all_tags( $r['first_order'] ),
							wp_strip_all_tags( $r['last_order'] ),
							(int) $r['vendor_count'],
						)
					);
				}

				fclose( $out );
				exit;
			}

			fputcsv(
				$out,
				array(
					__( 'Order ID', 'consucorner' ),
					__( 'Billing Name', 'consucorner' ),
					__( 'Created Date', 'consucorner' ),
					__( 'Completed Date', 'consucorner' ),
					__( 'Vendor Name', 'consucorner' ),
					__( 'Gross Total', 'consucorner' ),
					__( 'Shipping Cost', 'consucorner' ),
					__( 'Total Without Shipping', 'consucorner' ),
					__( 'Admin Fee', 'consucorner' ),
					__( 'Vendor Earning', 'consucorner' ),
					__( 'Return Deductions', 'consucorner' ),
					__( 'Net Payable', 'consucorner' ),
					__( 'Status', 'consucorner' ),
				)
			);

			foreach ( $data['rows'] as $r ) {
				fputcsv(
					$out,
					array(
						$r['order_number'],
						wp_strip_all_tags( $r['billing_name'] ),
						$r['date_iso'],
						$r['completed_iso'],
						wp_strip_all_tags( $r['vendor_name'] ),
						number_format( (float) $r['gross_total'], 2, '.', '' ),
						number_format( (float) $r['shipping_total'], 2, '.', '' ),
						number_format( (float) $r['total_without_shipping'], 2, '.', '' ),
						number_format( (float) $r['admin_fee'], 2, '.', '' ),
						number_format( (float) $r['vendor_earning'], 2, '.', '' ),
						number_format( (float) ( $r['return_deductions'] ?? 0 ), 2, '.', '' ),
						number_format( (float) ( $r['net_payable'] ?? $r['vendor_earning'] ), 2, '.', '' ),
						wp_strip_all_tags( $r['status_label'] ),
					)
				);
			}

			fclose( $out );
			exit;
		}

		/**
		 * Render the admin page shell. Data is filled in via AJAX on load.
		 */
		public static function render_page() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'You do not have permission to view this page.', 'consucorner' ) );
			}

			$vendors  = self::get_vendors();
			$statuses = self::get_statuses();
			$defaults = self::get_default_completed_date_range();
			$completed_end   = $defaults['end'];
			$completed_start = $defaults['start'];
			?>
			<div class="wrap cc-vlg-wrap">
				<div class="cc-vlg-header">
					<div class="cc-vlg-header__text">
						<h1 class="cc-vlg-title"><?php esc_html_e( 'Vendor Financial Ledger & Analytics', 'consucorner' ); ?></h1>
						<p class="cc-vlg-subtitle" id="cc-vlg-subtitle"><?php esc_html_e( 'Track vendor performance, admin commission and vendor earnings by order completed date.', 'consucorner' ); ?></p>
					</div>
					<div class="cc-vlg-header__actions">
						<button type="button" class="cc-vlg-btn cc-vlg-btn--primary" id="cc-vlg-export">
							<span class="cc-vlg-btn__icon" aria-hidden="true">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
							</span>
							<?php esc_html_e( 'Export to CSV', 'consucorner' ); ?>
						</button>
					</div>
				</div>

				<form id="cc-vlg-filters" class="cc-vlg-filters" autocomplete="off" onsubmit="return false;">
					<div class="cc-vlg-field cc-vlg-field--full">
						<span class="cc-vlg-field__label"><?php esc_html_e( 'Filter by', 'consucorner' ); ?></span>
						<div class="cc-vlg-date-mode" role="radiogroup" aria-label="<?php esc_attr_e( 'Date filter mode', 'consucorner' ); ?>">
							<label class="cc-vlg-date-mode__option">
								<input type="radio" name="cc-vlg-date-mode" value="completed" checked />
								<span><?php esc_html_e( 'Completed Date', 'consucorner' ); ?></span>
							</label>
							<label class="cc-vlg-date-mode__option">
								<input type="radio" name="cc-vlg-date-mode" value="created" />
								<span><?php esc_html_e( 'Created Date', 'consucorner' ); ?></span>
							</label>
						</div>
					</div>

					<div class="cc-vlg-field cc-vlg-field--date-range" data-cc-date-field="completed">
						<label for="cc-vlg-completed-date"><?php esc_html_e( 'Completed Date Range', 'consucorner' ); ?></label>
						<input type="text" id="cc-vlg-completed-date" class="cc-vlg-input" placeholder="<?php esc_attr_e( 'Select completed date range', 'consucorner' ); ?>" />
						<input type="hidden" id="cc-vlg-completed-start" value="<?php echo esc_attr( $completed_start ); ?>" />
						<input type="hidden" id="cc-vlg-completed-end" value="<?php echo esc_attr( $completed_end ); ?>" />
						<p class="cc-vlg-field__mode-hint"><?php esc_html_e( 'Uses order completed or paid date from WooCommerce (HPOS-safe).', 'consucorner' ); ?></p>
					</div>

					<div class="cc-vlg-field cc-vlg-field--date-range is-inactive" data-cc-date-field="created" hidden>
						<label for="cc-vlg-date"><?php esc_html_e( 'Created Date Range', 'consucorner' ); ?></label>
						<input type="text" id="cc-vlg-date" class="cc-vlg-input" placeholder="<?php esc_attr_e( 'Select created date range', 'consucorner' ); ?>" disabled />
						<input type="hidden" id="cc-vlg-start" value="" />
						<input type="hidden" id="cc-vlg-end" value="" />
						<p class="cc-vlg-field__mode-hint"><?php esc_html_e( 'Includes processing and pending orders.', 'consucorner' ); ?></p>
					</div>

					<div class="cc-vlg-field">
						<label for="cc-vlg-vendor"><?php esc_html_e( 'Vendor', 'consucorner' ); ?></label>
						<select id="cc-vlg-vendor" class="cc-vlg-select cc-vlg-select2">
							<option value="0"><?php esc_html_e( 'All Vendors', 'consucorner' ); ?></option>
							<?php foreach ( $vendors as $v ) : ?>
								<option value="<?php echo (int) $v->ID; ?>"><?php echo esc_html( $v->display_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="cc-vlg-field">
						<label for="cc-vlg-status"><?php esc_html_e( 'Order Status', 'consucorner' ); ?></label>
						<select id="cc-vlg-status" class="cc-vlg-select cc-vlg-select2">
							<option value=""><?php esc_html_e( 'All Statuses', 'consucorner' ); ?></option>
							<?php foreach ( $statuses as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="cc-vlg-field cc-vlg-field--actions">
						<button type="button" class="cc-vlg-btn cc-vlg-btn--primary" id="cc-vlg-apply"><?php esc_html_e( 'Apply Filters', 'consucorner' ); ?></button>
						<button type="button" class="cc-vlg-btn cc-vlg-btn--ghost" id="cc-vlg-reset"><?php esc_html_e( 'Reset', 'consucorner' ); ?></button>
					</div>
				</form>

				<nav class="cc-vlg-tabs" aria-label="<?php esc_attr_e( 'Ledger sections', 'consucorner' ); ?>">
					<button type="button" class="cc-vlg-tab is-active" data-cc-tab="overview" aria-selected="true">
						<?php esc_html_e( 'Overview', 'consucorner' ); ?>
					</button>
					<button type="button" class="cc-vlg-tab" data-cc-tab="customers" aria-selected="false">
						<?php esc_html_e( 'Customer Analysis', 'consucorner' ); ?>
					</button>
				</nav>

				<div class="cc-vlg-tab-panel is-active" data-cc-tab-panel="overview">
				<section class="cc-vlg-summary" aria-label="<?php esc_attr_e( 'Summary statistics', 'consucorner' ); ?>">
					<article class="cc-vlg-card cc-vlg-card--sales">
						<span class="cc-vlg-card__icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
						</span>
						<span class="cc-vlg-card__label"><?php esc_html_e( 'Total Sales', 'consucorner' ); ?></span>
						<span class="cc-vlg-card__value" data-cc-stat="total_sales">—</span>
						<span class="cc-vlg-card__hint" data-cc-stat="orders_count_label"></span>
					</article>

					<article class="cc-vlg-card cc-vlg-card--commission">
						<span class="cc-vlg-card__icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><polyline points="7 14 12 9 16 13 21 8"/></svg>
						</span>
						<span class="cc-vlg-card__label"><?php esc_html_e( 'Total Admin Commission', 'consucorner' ); ?></span>
						<span class="cc-vlg-card__value" data-cc-stat="total_commission">—</span>
					</article>

					<article class="cc-vlg-card cc-vlg-card--vendor">
						<span class="cc-vlg-card__icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
						</span>
						<span class="cc-vlg-card__label"><?php esc_html_e( 'Vendor Earnings', 'consucorner' ); ?></span>
						<span class="cc-vlg-card__value" data-cc-stat="vendor_earnings">—</span>
					</article>
				</section>

				<section class="cc-vlg-insights" aria-label="<?php esc_attr_e( 'Operational insights', 'consucorner' ); ?>">
					<article class="cc-vlg-mini-card">
						<span class="cc-vlg-mini-card__label"><?php esc_html_e( 'Average Order Value', 'consucorner' ); ?></span>
						<strong class="cc-vlg-mini-card__value" data-cc-stat="average_order">—</strong>
						<span class="cc-vlg-mini-card__hint"><?php esc_html_e( 'Gross sales divided by orders', 'consucorner' ); ?></span>
					</article>
					<article class="cc-vlg-mini-card">
						<span class="cc-vlg-mini-card__label"><?php esc_html_e( 'Active Vendors', 'consucorner' ); ?></span>
						<strong class="cc-vlg-mini-card__value" data-cc-stat="active_vendors">—</strong>
						<span class="cc-vlg-mini-card__hint"><?php esc_html_e( 'Vendors with orders in range', 'consucorner' ); ?></span>
					</article>
					<article class="cc-vlg-mini-card">
						<span class="cc-vlg-mini-card__label"><?php esc_html_e( 'Commission Rate', 'consucorner' ); ?></span>
						<strong class="cc-vlg-mini-card__value" data-cc-stat="commission_rate">—</strong>
						<span class="cc-vlg-mini-card__hint"><?php esc_html_e( 'Admin fee as share of sales', 'consucorner' ); ?></span>
					</article>
					<article class="cc-vlg-mini-card">
						<span class="cc-vlg-mini-card__label"><?php esc_html_e( 'Best Order Day', 'consucorner' ); ?></span>
						<strong class="cc-vlg-mini-card__value" data-cc-stat="best_day">—</strong>
						<span class="cc-vlg-mini-card__hint" data-cc-stat="best_day_sales">—</span>
					</article>
				</section>

				<section class="cc-vlg-chart-wrap">
					<header class="cc-vlg-section-head">
						<h2><?php esc_html_e( 'Net Sales vs Admin Commission', 'consucorner' ); ?></h2>
						<span class="cc-vlg-loader" id="cc-vlg-loader" aria-hidden="true"></span>
					</header>
					<div class="cc-vlg-chart">
						<canvas id="cc-vlg-chart" role="img" aria-label="<?php esc_attr_e( 'Net sales vs admin commission line chart', 'consucorner' ); ?>"></canvas>
					</div>
				</section>

				<section class="cc-vlg-dashboard-grid" aria-label="<?php esc_attr_e( 'Detailed analytics', 'consucorner' ); ?>">
					<article class="cc-vlg-panel cc-vlg-panel--split">
						<header class="cc-vlg-section-head">
							<h2><?php esc_html_e( 'Earnings Split', 'consucorner' ); ?></h2>
						</header>
						<div class="cc-vlg-donut">
							<canvas id="cc-vlg-split-chart" role="img" aria-label="<?php esc_attr_e( 'Admin commission versus vendor earnings chart', 'consucorner' ); ?>"></canvas>
						</div>
					</article>

					<article class="cc-vlg-panel">
						<header class="cc-vlg-section-head">
							<h2><?php esc_html_e( 'Top Vendors', 'consucorner' ); ?></h2>
						</header>
						<div class="cc-vlg-list" data-cc-list="top_vendors">
							<p class="cc-vlg-empty"><?php esc_html_e( 'Loading…', 'consucorner' ); ?></p>
						</div>
					</article>

					<article class="cc-vlg-panel">
						<header class="cc-vlg-section-head">
							<h2><?php esc_html_e( 'Sales by Status', 'consucorner' ); ?></h2>
						</header>
						<div class="cc-vlg-list" data-cc-list="status_breakdown">
							<p class="cc-vlg-empty"><?php esc_html_e( 'Loading…', 'consucorner' ); ?></p>
						</div>
					</article>
				</section>

				<section class="cc-vlg-marketing-grid" aria-label="<?php esc_attr_e( 'Marketing and product analytics', 'consucorner' ); ?>">
					<article class="cc-vlg-panel">
						<header class="cc-vlg-section-head">
							<h2><?php esc_html_e( 'Total Sales by Sales Channel', 'consucorner' ); ?></h2>
						</header>
						<div class="cc-vlg-list" data-cc-list="sales_by_channel">
							<p class="cc-vlg-empty"><?php esc_html_e( 'Loading…', 'consucorner' ); ?></p>
						</div>
					</article>

					<article class="cc-vlg-panel">
						<header class="cc-vlg-section-head">
							<h2><?php esc_html_e( 'Performance by Referring Channel', 'consucorner' ); ?></h2>
						</header>
						<div class="cc-vlg-list" data-cc-list="referring_channels">
							<p class="cc-vlg-empty"><?php esc_html_e( 'Loading…', 'consucorner' ); ?></p>
						</div>
					</article>

					<article class="cc-vlg-panel">
						<header class="cc-vlg-section-head">
							<h2><?php esc_html_e( 'Total Sales by Product', 'consucorner' ); ?></h2>
						</header>
						<div class="cc-vlg-list" data-cc-list="sales_by_product">
							<p class="cc-vlg-empty"><?php esc_html_e( 'Loading…', 'consucorner' ); ?></p>
						</div>
					</article>

					<article class="cc-vlg-panel">
						<header class="cc-vlg-section-head">
							<h2><?php esc_html_e( 'Customer Cohort Analysis', 'consucorner' ); ?></h2>
						</header>
						<div class="cc-vlg-list" data-cc-list="customer_cohorts">
							<p class="cc-vlg-empty"><?php esc_html_e( 'Loading…', 'consucorner' ); ?></p>
						</div>
					</article>

					<article class="cc-vlg-panel">
						<header class="cc-vlg-section-head">
							<h2><?php esc_html_e( 'Total Sales by Referrer', 'consucorner' ); ?></h2>
						</header>
						<div class="cc-vlg-list" data-cc-list="sales_by_referrer">
							<p class="cc-vlg-empty"><?php esc_html_e( 'Loading…', 'consucorner' ); ?></p>
						</div>
					</article>

					<article class="cc-vlg-panel">
						<header class="cc-vlg-section-head">
							<h2><?php esc_html_e( 'Sessions by Referrer', 'consucorner' ); ?></h2>
						</header>
						<div class="cc-vlg-list" data-cc-list="sessions_by_referrer">
							<p class="cc-vlg-empty"><?php esc_html_e( 'Loading…', 'consucorner' ); ?></p>
						</div>
					</article>

					<article class="cc-vlg-panel">
						<header class="cc-vlg-section-head">
							<h2><?php esc_html_e( 'Products by Sell-through Rate', 'consucorner' ); ?></h2>
						</header>
						<div class="cc-vlg-list" data-cc-list="product_sell_through">
							<p class="cc-vlg-empty"><?php esc_html_e( 'Loading…', 'consucorner' ); ?></p>
						</div>
					</article>
				</section>

				<section class="cc-vlg-table-wrap">
					<header class="cc-vlg-section-head">
						<h2><?php esc_html_e( 'Orders Ledger', 'consucorner' ); ?></h2>
						<span class="cc-vlg-table-meta" data-cc-stat="table_showing"></span>
					</header>
					<div class="cc-vlg-table-scroll">
						<table class="cc-vlg-table" id="cc-vlg-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Order ID', 'consucorner' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Billing Name', 'consucorner' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Created Date', 'consucorner' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Completed Date', 'consucorner' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Vendor Name', 'consucorner' ); ?></th>
									<th scope="col" class="cc-vlg-num"><?php esc_html_e( 'Gross Total', 'consucorner' ); ?></th>
									<th scope="col" class="cc-vlg-num"><?php esc_html_e( 'Shipping Cost', 'consucorner' ); ?></th>
									<th scope="col" class="cc-vlg-num"><?php esc_html_e( 'Total Without Shipping', 'consucorner' ); ?></th>
									<th scope="col" class="cc-vlg-num"><?php esc_html_e( 'Admin Fee', 'consucorner' ); ?></th>
									<th scope="col" class="cc-vlg-num"><?php esc_html_e( 'Vendor Earning', 'consucorner' ); ?></th>
									<th scope="col" class="cc-vlg-num"><?php esc_html_e( 'Return Deductions', 'consucorner' ); ?></th>
									<th scope="col" class="cc-vlg-num"><?php esc_html_e( 'Net Payable', 'consucorner' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Status', 'consucorner' ); ?></th>
								</tr>
							</thead>
							<tbody data-cc-tbody>
								<tr><td colspan="13" class="cc-vlg-empty"><?php esc_html_e( 'Loading…', 'consucorner' ); ?></td></tr>
							</tbody>
						</table>
					</div>
					<div class="cc-vlg-table-actions">
						<button type="button" class="cc-vlg-btn cc-vlg-btn--ghost" data-cc-action="show-more-orders" hidden>
							<?php esc_html_e( 'Show more orders', 'consucorner' ); ?>
						</button>
					</div>
				</section>
				</div>

				<div class="cc-vlg-tab-panel" data-cc-tab-panel="customers" hidden>
					<section class="cc-vlg-customer-head">
						<div>
							<h2><?php esc_html_e( 'Customer Analysis', 'consucorner' ); ?></h2>
							<p><?php esc_html_e( 'Review retention, LTV, returning customers and each customer relationship for the selected date, vendor and status filters.', 'consucorner' ); ?></p>
						</div>
						<button type="button" class="cc-vlg-btn cc-vlg-btn--primary" id="cc-vlg-export-customers">
							<span class="cc-vlg-btn__icon" aria-hidden="true">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
							</span>
							<?php esc_html_e( 'Export Customers CSV', 'consucorner' ); ?>
						</button>
					</section>

					<section class="cc-vlg-insights cc-vlg-insights--customers" aria-label="<?php esc_attr_e( 'Customer KPIs', 'consucorner' ); ?>">
						<article class="cc-vlg-mini-card">
							<span class="cc-vlg-mini-card__label"><?php esc_html_e( 'Retention Rate', 'consucorner' ); ?></span>
							<strong class="cc-vlg-mini-card__value" data-cc-stat="retention_rate">—</strong>
							<span class="cc-vlg-mini-card__hint"><?php esc_html_e( 'Returning customers divided by total customers', 'consucorner' ); ?></span>
						</article>
						<article class="cc-vlg-mini-card">
							<span class="cc-vlg-mini-card__label"><?php esc_html_e( 'Avg LTV', 'consucorner' ); ?></span>
							<strong class="cc-vlg-mini-card__value" data-cc-stat="average_ltv">—</strong>
							<span class="cc-vlg-mini-card__hint"><?php esc_html_e( 'Sales divided by total customers', 'consucorner' ); ?></span>
						</article>
						<article class="cc-vlg-mini-card">
							<span class="cc-vlg-mini-card__label"><?php esc_html_e( 'Return Customers', 'consucorner' ); ?></span>
							<strong class="cc-vlg-mini-card__value" data-cc-stat="return_customers">—</strong>
							<span class="cc-vlg-mini-card__hint"><?php esc_html_e( 'Customers with more than one order', 'consucorner' ); ?></span>
						</article>
						<article class="cc-vlg-mini-card">
							<span class="cc-vlg-mini-card__label"><?php esc_html_e( 'Total Customers', 'consucorner' ); ?></span>
							<strong class="cc-vlg-mini-card__value" data-cc-stat="total_customers">—</strong>
							<span class="cc-vlg-mini-card__hint"><?php esc_html_e( 'Unique customers in selected range', 'consucorner' ); ?></span>
						</article>
					</section>

					<section class="cc-vlg-table-wrap cc-vlg-table-wrap--customers">
						<header class="cc-vlg-section-head">
							<h2><?php esc_html_e( 'Customer Management Table', 'consucorner' ); ?></h2>
							<span class="cc-vlg-table-meta" data-cc-stat="customer_table_showing"></span>
						</header>
						<div class="cc-vlg-table-scroll">
							<table class="cc-vlg-table cc-vlg-table--customers">
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Customer', 'consucorner' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Email', 'consucorner' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Type', 'consucorner' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Status', 'consucorner' ); ?></th>
										<th scope="col" class="cc-vlg-num"><?php esc_html_e( 'Orders', 'consucorner' ); ?></th>
										<th scope="col" class="cc-vlg-num"><?php esc_html_e( 'Customer LTV', 'consucorner' ); ?></th>
										<th scope="col" class="cc-vlg-num"><?php esc_html_e( 'AOV', 'consucorner' ); ?></th>
										<th scope="col"><?php esc_html_e( 'First Order', 'consucorner' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Last Order', 'consucorner' ); ?></th>
										<th scope="col" class="cc-vlg-num"><?php esc_html_e( 'Vendors', 'consucorner' ); ?></th>
									</tr>
								</thead>
								<tbody data-cc-customer-tbody>
									<tr><td colspan="10" class="cc-vlg-empty"><?php esc_html_e( 'Loading…', 'consucorner' ); ?></td></tr>
								</tbody>
							</table>
						</div>
					</section>
				</div>
			</div>
			<?php
		}
	}

	Consucorner_Vendor_Ledger::init();

endif;
