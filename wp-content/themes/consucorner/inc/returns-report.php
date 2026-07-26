<?php
/**
 * WooCommerce admin Returns monitoring report (Dokan RMA).
 *
 * @package Consucorner
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Consucorner_Returns_Report' ) ) :

	final class Consucorner_Returns_Report {

		const MENU_SLUG  = 'cc-returns-report';
		const NONCE_KEY  = 'cc_returns_nonce';
		const CAPABILITY = 'manage_woocommerce';
		const PER_PAGE   = 50;

		/**
		 * Admin page hook suffix.
		 *
		 * @var string
		 */
		private static $page_hook = '';

		/**
		 * Register hooks.
		 */
		public static function init() {
			add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 60 );
			add_action( 'admin_menu', array( __CLASS__, 'add_menu_badge' ), 999 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
			add_action( 'wp_ajax_cc_returns_filter', array( __CLASS__, 'ajax_filter' ) );
			add_action( 'wp_ajax_cc_returns_export', array( __CLASS__, 'ajax_export_csv' ) );
			add_action( 'wp_ajax_cc_returns_resolve', array( __CLASS__, 'ajax_resolve' ) );
			add_action( 'wp_ajax_cc_returns_lookup_order', array( __CLASS__, 'ajax_lookup_order' ) );
			add_action( 'wp_ajax_cc_returns_update_fulfillment', array( __CLASS__, 'ajax_update_fulfillment' ) );
			add_action( 'wp_ajax_cc_returns_create_manual', array( __CLASS__, 'ajax_create_manual_return' ) );
			add_action( 'wp_ajax_cc_returns_update_return_status', array( __CLASS__, 'ajax_update_return_status' ) );
			add_action( 'dokan_rma_save_warranty_request', array( __CLASS__, 'on_customer_return_submitted' ), 20 );
			add_action( 'dokan_rma_send_warranty_request', array( __CLASS__, 'on_customer_return_submitted' ), 20 );
			add_action( 'add_meta_boxes', array( __CLASS__, 'register_order_meta_box' ), 40 );
			add_action( 'add_meta_boxes', array( __CLASS__, 'register_operations_guide_meta_box' ), 35 );
			add_action( 'admin_notices', array( __CLASS__, 'render_orders_list_guide_notice' ) );
			add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_orders_list_column' ), 25 );
			add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_orders_list_column' ), 25, 2 );
			add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_orders_list_column' ), 25 );
			add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_hpos_orders_list_column' ), 25, 2 );
			add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'render_order_fulfillment_panel' ), 12 );
			add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'render_order_details_notice' ), 15 );
		}

		/**
		 * Register submenu under WooCommerce.
		 */
		public static function register_menu() {
			$count = self::get_pending_count();
			$label = __( 'Returns', 'consucorner' );
			if ( $count > 0 ) {
				$label = sprintf(
					/* translators: %s: pending count bubble */
					__( 'Returns %s', 'consucorner' ),
					'<span class="awaiting-mod update-plugins count-' . absint( $count ) . '"><span class="pending-count">' . number_format_i18n( $count ) . '</span></span>'
				);
			}

			self::$page_hook = (string) add_submenu_page(
				'woocommerce',
				__( 'Returns', 'consucorner' ),
				$label,
				self::CAPABILITY,
				self::MENU_SLUG,
				array( __CLASS__, 'render_page' )
			);
		}

		/**
		 * Mirror pending count onto the top-level WooCommerce menu (like Orders).
		 */
		public static function add_menu_badge() {
			global $menu;

			$count = self::get_pending_count();
			if ( $count < 1 || ! is_array( $menu ) ) {
				return;
			}

			foreach ( $menu as $index => $item ) {
				if ( isset( $item[2] ) && 'woocommerce' === $item[2] ) {
					$bubble = ' <span class="awaiting-mod update-plugins count-' . absint( $count ) . '"><span class="pending-count" aria-hidden="true">' . number_format_i18n( $count ) . '</span><span class="screen-reader-text">' .
						sprintf(
							/* translators: %d: pending return requests */
							_n( '%d return request awaiting review', '%d return requests awaiting review', $count, 'consucorner' ),
							$count
						) .
						'</span></span>';
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					$menu[ $index ][0] .= $bubble;
					break;
				}
			}
		}

		/**
		 * Count open return requests (new / processing / reviewing).
		 *
		 * @return int
		 */
		public static function get_pending_count() {
			$cached = get_transient( 'cc_returns_pending_count' );
			if ( false !== $cached ) {
				return absint( $cached );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'dokan_rma_request';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists !== $table ) {
				$count = class_exists( 'Consucorner_Order_Cancel_Requests' )
					? Consucorner_Order_Cancel_Requests::get_pending_count()
					: 0;
				set_transient( 'cc_returns_pending_count', $count, HOUR_IN_SECONDS );
				return $count;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$table}
				WHERE status IN ('new','processing','reviewing')"
			);

			if ( class_exists( 'Consucorner_Order_Cancel_Requests' ) ) {
				$count += Consucorner_Order_Cancel_Requests::get_pending_count();
			}

			set_transient( 'cc_returns_pending_count', $count, 5 * MINUTE_IN_SECONDS );
			return $count;
		}

		/**
		 * Clear pending-count cache.
		 */
		public static function clear_pending_count_cache() {
			delete_transient( 'cc_returns_pending_count' );
			if ( class_exists( 'Consucorner_Order_Cancel_Requests' ) ) {
				Consucorner_Order_Cancel_Requests::clear_pending_count_cache();
			}
		}

		/**
		 * Public wrapper for operations order panel HTML.
		 *
		 * @param array<string,mixed> $payload Order payload.
		 * @return string
		 */
		public static function render_ops_order_html_public( $payload ) {
			return self::render_ops_order_html( $payload );
		}

		/**
		 * When a customer submits a return: stamp order + clear badge cache.
		 *
		 * @param array<string,mixed> $data Request payload from Dokan.
		 */
		public static function on_customer_return_submitted( $data ) {
			self::clear_pending_count_cache();

			$order_id = absint( $data['order_id'] ?? 0 );
			$order    = $order_id ? wc_get_order( $order_id ) : false;
			if ( ! $order ) {
				return;
			}

			if ( class_exists( 'Consucorner_Order_Return_Workflow' ) ) {
				Consucorner_Order_Return_Workflow::stamp_customer_return_requested( $data );
			}

			$type    = isset( $data['type'] ) ? sanitize_key( (string) $data['type'] ) : '';
			$reasons = isset( $data['reasons'] ) ? sanitize_text_field( (string) $data['reasons'] ) : '';
			$order->update_meta_data( '_cc_has_return_request', 'yes' );
			$order->update_meta_data( '_cc_return_last_status', 'new' );
			$order->add_order_note(
				sprintf(
					/* translators: 1: request type, 2: reason */
					__( 'Customer submitted a return request (%1$s). Reason: %2$s. Review under WooCommerce → Returns.', 'consucorner' ),
					$type ? $type : __( 'return', 'consucorner' ),
					$reasons ? $reasons : '—'
				)
			);
			$order->save();

			self::notify_return_request_created( $order, $type, $reasons );
		}

		/**
		 * Email customer + ops when a customer submits a return request.
		 *
		 * @param WC_Order $order Order.
		 * @param string   $type Request type.
		 * @param string   $reasons Reason text.
		 */
		private static function notify_return_request_created( $order, $type, $reasons ) {
			$order_number = $order->get_order_number();
			$reason_line  = $reasons
				? sprintf(
					/* translators: %s: reason */
					__( "Reason: %s\n\n", 'consucorner' ),
					$reasons
				)
				: '';

			$customer_subject = sprintf(
				/* translators: %s: order number */
				__( 'We received your return request for order #%s', 'consucorner' ),
				$order_number
			);
			$customer_message = sprintf(
				/* translators: 1: order number, 2: optional reason */
				__( "Hi,\n\nWe received your return request for order #%1\$s.\n\n%2\$sOur operations team will review it and update you by email.\n\nThank you,\nConsuCorner", 'consucorner' ),
				$order_number,
				$reason_line
			);

			$ops_subject = sprintf(
				/* translators: %s: order number */
				__( 'Return requested for order #%s', 'consucorner' ),
				$order_number
			);
			$ops_message = sprintf(
				/* translators: 1: order number, 2: type, 3: optional reason */
				__( "A customer submitted a return request for order #%1\$s (%2\$s).\n\n%3\$sReview it under WooCommerce → Returns.\n\nConsuCorner", 'consucorner' ),
				$order_number,
				$type ? $type : __( 'return', 'consucorner' ),
				$reason_line
			);

			if ( $order->get_billing_email() ) {
				wp_mail( $order->get_billing_email(), $customer_subject, $customer_message );
			}

			$ops_emails = array( (string) get_option( 'admin_email' ) );
			$ops_emails = apply_filters( 'consucorner_ops_notification_emails', $ops_emails );
			foreach ( (array) $ops_emails as $email ) {
				$email = sanitize_email( (string) $email );
				if ( $email && is_email( $email ) ) {
					wp_mail( $email, $ops_subject, $ops_message );
				}
			}
		}

		/**
		 * Enqueue report assets only on this screen.
		 *
		 * @param string $hook Admin hook.
		 */
		public static function enqueue_assets( $hook ) {
			$is_returns_page = ! empty( self::$page_hook ) && $hook === self::$page_hook;
			$is_order_edit   = in_array( $hook, array( 'post.php', 'post-new.php', 'woocommerce_page_wc-orders' ), true );

			if ( ! $is_returns_page && ! $is_order_edit ) {
				return;
			}

			if ( $is_order_edit ) {
				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				if ( $screen && isset( $screen->post_type ) && 'shop_order' !== $screen->post_type && 'woocommerce_page_wc-orders' !== $screen->id ) {
					return;
				}
				if ( 'woocommerce_page_wc-orders' === $hook && ( ! isset( $_GET['action'] ) || 'edit' !== $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return;
				}
			}

			$base = get_template_directory_uri() . '/assets';
			$ver  = defined( '_S_VERSION' ) ? _S_VERSION : wp_get_theme()->get( 'Version' );

			wp_enqueue_style(
				'cc-returns-report',
				$base . '/css/admin-returns-report.css',
				array(),
				$ver
			);

			if ( $is_returns_page ) {
				wp_enqueue_script(
					'cc-returns-report',
					$base . '/js/admin-returns-report.js',
					array( 'jquery' ),
					$ver,
					true
				);
			}

			if ( $is_order_edit ) {
				wp_enqueue_script(
					'cc-order-fulfillment',
					$base . '/js/admin-order-fulfillment.js',
					array( 'jquery' ),
					$ver,
					true
				);
			}

			$script_handle = $is_returns_page ? 'cc-returns-report' : 'cc-order-fulfillment';
			wp_localize_script(
				$script_handle,
				'CC_RETURNS',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( self::NONCE_KEY ),
					'i18n'    => array(
						'errorLoad'      => __( 'Could not load returns. Please retry.', 'consucorner' ),
						'errorResolve'   => __( 'Could not resolve this return. Please retry.', 'consucorner' ),
						'errorOrder'     => __( 'Could not load this order.', 'consucorner' ),
						'errorCreate'    => __( 'Could not create the return request.', 'consucorner' ),
						'confirmWallet'  => __( 'Credit this return to the customer wallet?', 'consucorner' ),
						'confirmDirect'  => __( 'Record a manual WooCommerce refund (no gateway reversal)? Vendor earnings will be reduced for monthly payouts.', 'consucorner' ),
						'confirmRestock' => __( 'Restock returned items?', 'consucorner' ),
						'shippingPrompt' => __( 'Shipping deduction amount (0 for none):', 'consucorner' ),
						'resolved'       => __( 'Return resolved successfully.', 'consucorner' ),
						'created'        => __( 'Manual return request created.', 'consucorner' ),
						'updated'        => __( 'Status updated.', 'consucorner' ),
					),
					'fulfillmentStatuses' => class_exists( 'Consucorner_Order_Return_Workflow' ) ? Consucorner_Order_Return_Workflow::fulfillment_statuses() : array(),
					'returnStatuses'      => class_exists( 'Consucorner_Order_Return_Workflow' ) ? Consucorner_Order_Return_Workflow::return_statuses() : array(),
					'reasons'             => class_exists( 'Consucorner_Returns_Rma_Config' ) ? Consucorner_Returns_Rma_Config::get_admin_reason_map() : array(),
				)
			);
		}

		/**
		 * Render admin page shell.
		 */
		public static function render_page() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'You do not have permission to view returns.', 'consucorner' ) );
			}

			$filters = self::sanitize_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$result  = self::query_requests( $filters );
			$rows    = $result['rows'];
			$total   = $result['total'];
			?>
			<div class="wrap cc-returns-wrap" data-page="1">
				<h1><?php esc_html_e( 'Returns', 'consucorner' ); ?></h1>
				<p class="description">
					<?php esc_html_e( 'Monitor Dokan RMA return requests and resolve refunds to wallet or as a recorded manual refund.', 'consucorner' ); ?>
				</p>
				<p><?php self::render_operations_guide_buttons( 'inline' ); ?></p>

				<div class="cc-returns-notice" style="display:none;" aria-live="polite"></div>

				<div class="cc-returns-ops-panel">
					<h2><?php esc_html_e( 'Operations order workflow', 'consucorner' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Operations controls fulfillment status and can create vendor-visible return requests manually.', 'consucorner' ); ?></p>
					<div class="cc-returns-ops-lookup">
						<label>
							<?php esc_html_e( 'Order ID', 'consucorner' ); ?>
							<input type="text" inputmode="numeric" id="cc-returns-ops-order-id" value="<?php echo esc_attr( $filters['order_id'] ? (string) $filters['order_id'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Order ID or # (e.g. 13566249)', 'consucorner' ); ?>">
						</label>
						<button type="button" class="button button-primary cc-returns-lookup-order"><?php esc_html_e( 'Load order', 'consucorner' ); ?></button>
					</div>
					<div class="cc-returns-ops-order" hidden></div>
				</div>

				<div class="cc-returns-filters">
					<label>
						<?php esc_html_e( 'From', 'consucorner' ); ?>
						<input type="date" id="cc-returns-date-from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
					</label>
					<label>
						<?php esc_html_e( 'To', 'consucorner' ); ?>
						<input type="date" id="cc-returns-date-to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
					</label>
					<label>
						<?php esc_html_e( 'Vendor', 'consucorner' ); ?>
						<select id="cc-returns-vendor">
							<option value=""><?php esc_html_e( 'All vendors', 'consucorner' ); ?></option>
							<?php foreach ( self::get_vendors() as $vendor ) : ?>
								<option value="<?php echo esc_attr( $vendor->ID ); ?>" <?php selected( $filters['vendor_id'], $vendor->ID ); ?>>
									<?php echo esc_html( $vendor->display_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<?php esc_html_e( 'Status', 'consucorner' ); ?>
						<select id="cc-returns-status">
							<option value=""><?php esc_html_e( 'All statuses', 'consucorner' ); ?></option>
							<?php foreach ( self::get_status_options() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['status'], $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<?php esc_html_e( 'Type', 'consucorner' ); ?>
						<select id="cc-returns-type">
							<option value=""><?php esc_html_e( 'All types', 'consucorner' ); ?></option>
							<?php foreach ( self::get_type_options() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['type'], $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<?php esc_html_e( 'Order #', 'consucorner' ); ?>
						<input type="number" min="0" id="cc-returns-order" value="<?php echo esc_attr( $filters['order_id'] ? (string) $filters['order_id'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Any', 'consucorner' ); ?>">
					</label>
					<button type="button" class="button button-primary cc-returns-filter-btn"><?php esc_html_e( 'Filter', 'consucorner' ); ?></button>
					<button type="button" class="button cc-returns-export-btn"><?php esc_html_e( 'Export CSV', 'consucorner' ); ?></button>
				</div>

				<p class="cc-returns-summary">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: shown count, 2: total count */
							_n( 'Showing %1$d of %2$d return request.', 'Showing %1$d of %2$d return requests.', $total, 'consucorner' ),
							count( $rows ),
							$total
						)
					);
					?>
				</p>

				<table class="widefat striped cc-returns-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Request #', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Order', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Vendor', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Items', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Type', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Status', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Resolution', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Date', 'consucorner' ); ?></th>
							<th class="column-actions"><?php esc_html_e( 'Actions', 'consucorner' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php echo self::render_rows_html( $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</tbody>
				</table>
			</div>
			<?php
		}

		/**
		 * AJAX: filter table rows.
		 */
		public static function ajax_filter() {
			self::verify_ajax();

			$filters = self::sanitize_filters( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$result  = self::query_requests( $filters );

			wp_send_json_success(
				array(
					'rows_html' => self::render_rows_html( $result['rows'] ),
					'summary'   => sprintf(
						/* translators: 1: shown, 2: total */
						_n( 'Showing %1$d of %2$d return request.', 'Showing %1$d of %2$d return requests.', $result['total'], 'consucorner' ),
						count( $result['rows'] ),
						$result['total']
					),
				)
			);
		}

		/**
		 * AJAX: export CSV.
		 */
		public static function ajax_export_csv() {
			self::verify_ajax();

			$filters         = self::sanitize_filters( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$filters['page'] = 1;
			$filters['limit'] = 5000;
			$result          = self::query_requests( $filters );

			$filename = 'returns-report-' . gmdate( 'Y-m-d-His' ) . '.csv';
			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . $filename );

			$out = fopen( 'php://output', 'w' );
			if ( ! $out ) {
				wp_die( esc_html__( 'Could not export CSV.', 'consucorner' ) );
			}

			fputcsv(
				$out,
				array(
					'Request ID',
					'Order ID',
					'Customer',
					'Vendor',
					'Items',
					'Type',
					'Reason',
					'Status',
					'Resolution',
					'Amount',
					'Created',
				)
			);

			foreach ( $result['rows'] as $row ) {
				fputcsv(
					$out,
					array(
						$row['id'],
						$row['order_id'],
						$row['customer_name'],
						$row['vendor_name'],
						$row['items_label'],
						$row['type_label'],
						$row['reasons'],
						$row['status_label'],
						$row['resolution_label'],
						$row['amount_raw'],
						$row['created_at'],
					)
				);
			}

			fclose( $out );
			exit;
		}

		/**
		 * AJAX: resolve return to wallet or direct.
		 */
		public static function ajax_resolve() {
			self::verify_ajax();

			$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
			$route      = isset( $_POST['route'] ) ? sanitize_key( wp_unslash( $_POST['route'] ) ) : '';
			$restock    = ! empty( $_POST['restock'] );

			if ( ! $request_id || ! in_array( $route, array( 'wallet', 'direct' ), true ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid resolution request.', 'consucorner' ) ), 400 );
			}

			$args = array( 'restock' => $restock );
			if ( 'wallet' === $route ) {
				$args['shipping_deduction'] = isset( $_POST['shipping_deduction'] ) ? max( 0, (float) wp_unslash( $_POST['shipping_deduction'] ) ) : 0;
				$result = CC_Returns_Refund_Service::resolve_to_wallet( $request_id, $args );
			} else {
				$result = CC_Returns_Refund_Service::resolve_to_direct( $request_id, $args );
			}

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			}

			if ( class_exists( 'Consucorner_Order_Return_Workflow' ) ) {
				$request = CC_Returns_Refund_Service::get_request( $request_id );
				$order   = $request ? wc_get_order( absint( $request['order_id'] ?? 0 ) ) : false;
				if ( $order ) {
					Consucorner_Order_Return_Workflow::save_return_workflow_status( $order, $request_id, 'resolved', __( 'Resolved by operations.', 'consucorner' ) );
				}
			}

			self::clear_pending_count_cache();

			wp_send_json_success(
				array(
					'message' => 'wallet' === $route
						? __( 'Return credited to customer wallet. Vendor earnings updated for ledger/payouts.', 'consucorner' )
						: __( 'Manual refund recorded. Vendor earnings updated for ledger/payouts. Geidea was not reversed.', 'consucorner' ),
					'result'  => $result,
				)
			);
		}

		/**
		 * AJAX: load order for operations workflow panel.
		 */
		public static function ajax_lookup_order() {
			self::verify_ajax();

			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			if ( ! $order_id || ! class_exists( 'Consucorner_Order_Return_Workflow' ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid order lookup.', 'consucorner' ) ), 400 );
			}

			$payload = Consucorner_Order_Return_Workflow::get_order_ops_payload( $order_id );
			if ( is_wp_error( $payload ) ) {
				wp_send_json_error( array( 'message' => $payload->get_error_message() ), 400 );
			}

			wp_send_json_success(
				array(
					'order' => $payload,
					'html'  => self::render_ops_order_html( $payload ),
				)
			);
		}

		/**
		 * AJAX: update vendor fulfillment status.
		 */
		public static function ajax_update_fulfillment() {
			self::verify_ajax();

			if ( ! class_exists( 'Consucorner_Order_Return_Workflow' ) ) {
				wp_send_json_error( array( 'message' => __( 'Workflow service unavailable.', 'consucorner' ) ), 400 );
			}

			$order_id  = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$vendor_id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;
			$status    = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
			$note      = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

			$result = Consucorner_Order_Return_Workflow::update_fulfillment_status( $order_id, $vendor_id, $status, $note );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			}

			$payload = Consucorner_Order_Return_Workflow::get_order_ops_payload( $order_id );
			if ( is_wp_error( $payload ) ) {
				wp_send_json_error( array( 'message' => $payload->get_error_message() ), 400 );
			}

			wp_send_json_success(
				array(
					'message' => __( 'Fulfillment status updated.', 'consucorner' ),
					'order'   => $payload,
					'html'    => self::render_ops_order_html( $payload ),
				)
			);
		}

		/**
		 * AJAX: create manual vendor-split return requests.
		 */
		public static function ajax_create_manual_return() {
			self::verify_ajax();

			if ( ! class_exists( 'Consucorner_Order_Return_Workflow' ) ) {
				wp_send_json_error( array( 'message' => __( 'Workflow service unavailable.', 'consucorner' ) ), 400 );
			}

			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$reason   = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : 'not_needed';
			$details  = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
			$note     = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
			$items    = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();

			$item_quantities = array();
			foreach ( $items as $item_id => $qty ) {
				$item_quantities[ absint( $item_id ) ] = wc_stock_amount( $qty );
			}

			$result = Consucorner_Order_Return_Workflow::create_manual_return_requests(
				$order_id,
				$item_quantities,
				array(
					'reason'  => $reason,
					'details' => $details,
					'note'    => $note,
					'type'    => 'refund',
				)
			);

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			}

			self::clear_pending_count_cache();

			$payload = Consucorner_Order_Return_Workflow::get_order_ops_payload( $order_id );
			if ( is_wp_error( $payload ) ) {
				wp_send_json_error( array( 'message' => $payload->get_error_message() ), 400 );
			}

			wp_send_json_success(
				array(
					'message' => __( 'Manual return request(s) created and notifications sent.', 'consucorner' ),
					'created' => $result,
					'order'   => $payload,
					'html'    => self::render_ops_order_html( $payload ),
				)
			);
		}

		/**
		 * AJAX: update return workflow status.
		 */
		public static function ajax_update_return_status() {
			self::verify_ajax();

			if ( ! class_exists( 'Consucorner_Order_Return_Workflow' ) ) {
				wp_send_json_error( array( 'message' => __( 'Workflow service unavailable.', 'consucorner' ) ), 400 );
			}

			$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
			$status     = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
			$note       = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

			$result = Consucorner_Order_Return_Workflow::update_return_workflow_status( $request_id, $status, $note );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			}

			self::clear_pending_count_cache();

			wp_send_json_success(
				array(
					'message' => __( 'Return workflow updated.', 'consucorner' ),
					'result'  => $result,
				)
			);
		}

		/**
		 * Capability + nonce check for AJAX.
		 */
		private static function verify_ajax() {
			check_ajax_referer( self::NONCE_KEY, 'nonce' );
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'consucorner' ) ), 403 );
			}
		}

		/**
		 * Sanitize filter input.
		 *
		 * @param array<string,mixed> $source Raw input.
		 * @return array<string,mixed>
		 */
		private static function sanitize_filters( $source ) {
			$source = is_array( $source ) ? $source : array();
			$page   = isset( $source['page'] ) ? max( 1, absint( $source['page'] ) ) : 1;
			$limit  = isset( $source['limit'] ) ? min( 5000, max( 1, absint( $source['limit'] ) ) ) : self::PER_PAGE;

			$date_from = isset( $source['date_from'] ) ? sanitize_text_field( wp_unslash( $source['date_from'] ) ) : '';
			$date_to   = isset( $source['date_to'] ) ? sanitize_text_field( wp_unslash( $source['date_to'] ) ) : '';
			$order_id  = isset( $source['order_id'] ) ? absint( $source['order_id'] ) : 0;

			if ( ! $date_from ) {
				// When jumping from an order screen, don't hide older requests by date.
				$date_from = $order_id
					? gmdate( 'Y-m-d', strtotime( '-5 years' ) )
					: gmdate( 'Y-m-d', strtotime( '-30 days' ) );
			}
			if ( ! $date_to ) {
				$date_to = gmdate( 'Y-m-d' );
			}

			return array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'vendor_id' => isset( $source['vendor_id'] ) ? absint( $source['vendor_id'] ) : 0,
				'status'    => isset( $source['status'] ) ? sanitize_key( wp_unslash( $source['status'] ) ) : '',
				'type'      => isset( $source['type'] ) ? sanitize_key( wp_unslash( $source['type'] ) ) : '',
				'order_id'  => $order_id,
				'page'      => $page,
				'limit'     => $limit,
				'offset'    => ( $page - 1 ) * $limit,
			);
		}

		/**
		 * Query RMA requests with filters.
		 *
		 * @param array<string,mixed> $filters Filters.
		 * @return array{rows:array<int,array<string,mixed>>,total:int}
		 */
		private static function query_requests( $filters ) {
			global $wpdb;

			$rt  = $wpdb->prefix . 'dokan_rma_request';
			$rit = $wpdb->prefix . 'dokan_rma_request_product';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rt ) );
			if ( $table_exists !== $rt ) {
				return array( 'rows' => array(), 'total' => 0 );
			}

			$where  = array( 'rt.created_at >= %s', 'rt.created_at < %s' );
			$params = array(
				$filters['date_from'] . ' 00:00:00',
				gmdate( 'Y-m-d', strtotime( $filters['date_to'] . ' +1 day' ) ) . ' 00:00:00',
			);

			if ( ! empty( $filters['vendor_id'] ) ) {
				$where[]  = 'rt.vendor_id = %d';
				$params[] = $filters['vendor_id'];
			}
			if ( ! empty( $filters['status'] ) ) {
				$where[]  = 'rt.status = %s';
				$params[] = $filters['status'];
			}
			if ( ! empty( $filters['type'] ) ) {
				$where[]  = 'rt.type = %s';
				$params[] = $filters['type'];
			}
			if ( ! empty( $filters['order_id'] ) ) {
				$where[]  = 'rt.order_id = %d';
				$params[] = $filters['order_id'];
			}

			$where_sql = implode( ' AND ', $where );

			$count_sql = "SELECT COUNT(DISTINCT rt.id) FROM {$rt} rt WHERE {$where_sql}";
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

			$data_sql = "
				SELECT rt.*,
					GROUP_CONCAT(rit.item_id ORDER BY rit.item_id SEPARATOR ',') AS item_ids,
					GROUP_CONCAT(rit.product_id ORDER BY rit.item_id SEPARATOR ',') AS product_ids,
					GROUP_CONCAT(rit.quantity ORDER BY rit.item_id SEPARATOR ',') AS quantities
				FROM {$rt} rt
				INNER JOIN {$rit} rit ON rt.id = rit.request_id
				WHERE {$where_sql}
				GROUP BY rt.id
				ORDER BY rt.created_at DESC
				LIMIT %d OFFSET %d
			";

			$data_params   = $params;
			$data_params[] = (int) $filters['limit'];
			$data_params[] = (int) $filters['offset'];

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$raw_rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );
			$rows     = array();

			foreach ( (array) $raw_rows as $raw ) {
				$rows[] = self::enrich_row( $raw );
			}

			return array(
				'rows'  => $rows,
				'total' => $total,
			);
		}

		/**
		 * Enrich a DB row for display/export.
		 *
		 * @param array<string,mixed> $raw Raw SQL row.
		 * @return array<string,mixed>
		 */
		private static function enrich_row( $raw ) {
			$request_id = absint( $raw['id'] ?? 0 );
			$order_id   = absint( $raw['order_id'] ?? 0 );
			$order      = $order_id ? wc_get_order( $order_id ) : false;

			$customer_id   = absint( $raw['customer_id'] ?? 0 );
			$vendor_id     = absint( $raw['vendor_id'] ?? 0 );
			$customer      = $customer_id ? get_userdata( $customer_id ) : false;
			$vendor        = $vendor_id ? get_userdata( $vendor_id ) : false;
			$status        = isset( $raw['status'] ) ? (string) $raw['status'] : '';
			$type          = isset( $raw['type'] ) ? (string) $raw['type'] : '';
			$resolution    = $order ? CC_Returns_Refund_Service::get_resolution( $order_id, $request_id ) : null;
			$resolution_route = $resolution['route'] ?? '';
			$amount        = isset( $resolution['amount'] ) ? (float) $resolution['amount'] : self::estimate_request_amount( $order, $raw );

			$workflow_record  = class_exists( 'Consucorner_Order_Return_Workflow' )
				? Consucorner_Order_Return_Workflow::get_return_workflow( $order_id, $request_id )
				: array();
			$workflow_status  = class_exists( 'Consucorner_Order_Return_Workflow' )
				? Consucorner_Order_Return_Workflow::resolve_workflow_status( $workflow_record, $status )
				: '';
			$workflow_label   = $workflow_status && class_exists( 'Consucorner_Order_Return_Workflow' )
				? Consucorner_Order_Return_Workflow::get_return_label( $workflow_status )
				: self::get_status_label( $status );
			$workflow_actions = class_exists( 'Consucorner_Order_Return_Workflow' )
				? Consucorner_Order_Return_Workflow::get_return_workflow_actions( $workflow_status )
				: array();

			if ( ! $resolution_route && $order && 'completed' === $status ) {
				$resolution_route = self::infer_legacy_resolution( $order, $raw );
				if ( $resolution_route && ! $amount ) {
					$amount = self::estimate_request_amount( $order, $raw );
				}
			}

			return array(
				'id'                => $request_id,
				'order_id'          => $order_id,
				'customer_id'       => $customer_id,
				'vendor_id'         => $vendor_id,
				'customer_name'     => $customer ? $customer->display_name : __( 'Guest', 'consucorner' ),
				'vendor_name'       => $vendor ? $vendor->display_name : '—',
				'items_label'       => self::format_items_label( $order, $raw ),
				'type'              => $type,
				'type_label'        => self::get_type_label( $type ),
				'reasons'           => isset( $raw['reasons'] ) ? (string) $raw['reasons'] : '',
				'status'            => $status,
				'status_label'      => $workflow_label,
				'workflow_status'   => $workflow_status,
				'workflow_actions'  => $workflow_actions,
				'resolution'        => $resolution_route,
				'resolution_label'  => self::get_resolution_label( $resolution_route ),
				'amount_html'       => $order ? wc_price( $amount, array( 'currency' => $order->get_currency() ) ) : wc_price( $amount ),
				'amount_raw'        => wc_format_decimal( $amount ),
				'created_at'        => isset( $raw['created_at'] ) ? (string) $raw['created_at'] : '',
				'can_resolve'       => $order && ! $resolution_route && 'received' === $workflow_status,
				'can_workflow'      => $order && ! $resolution_route && ! in_array( $workflow_status, array( 'resolved', 'rejected' ), true ) && ! empty( $workflow_actions ),
				'order_edit_url'    => $order ? $order->get_edit_order_url() : '',
			);
		}

		/**
		 * Estimate refund amount from request line items.
		 *
		 * @param WC_Order|false      $order Order.
		 * @param array<string,mixed> $raw Request row.
		 * @return float
		 */
		private static function estimate_request_amount( $order, $raw ) {
			if ( ! $order ) {
				return 0.0;
			}

			$item_ids   = isset( $raw['item_ids'] ) ? array_map( 'absint', explode( ',', (string) $raw['item_ids'] ) ) : array();
			$quantities = isset( $raw['quantities'] ) ? array_map( 'absint', explode( ',', (string) $raw['quantities'] ) ) : array();
			$total      = 0.0;

			foreach ( $item_ids as $index => $item_id ) {
				$qty  = isset( $quantities[ $index ] ) ? $quantities[ $index ] : 0;
				$calc = CC_Returns_Refund_Service::calculate_line_refund( $order, $item_id, $qty );
				$total += (float) $calc['refund_total'];
			}

			return $total;
		}

		/**
		 * Infer resolution from wallet meta / WC refunds when order meta missing.
		 *
		 * @param WC_Order            $order Order.
		 * @param array<string,mixed> $raw Request row.
		 * @return string
		 */
		private static function infer_legacy_resolution( $order, $raw ) {
			$item_ids = isset( $raw['item_ids'] ) ? array_map( 'absint', explode( ',', (string) $raw['item_ids'] ) ) : array();
			foreach ( $item_ids as $item_id ) {
				$item = $order->get_item( $item_id );
				if ( ! $item ) {
					continue;
				}
				$res = (string) $item->get_meta( CC_Returns_Refund_Service::ITEM_META_RESOLUTION, true );
				if ( $res ) {
					return $res;
				}
				if ( 'yes' === $item->get_meta( CC_Returns_Refund_Service::ITEM_META_FLAG, true ) ) {
					return 'wallet';
				}
				if ( abs( (float) $order->get_qty_refunded_for_item( $item_id ) ) > 0 ) {
					return 'direct';
				}
			}
			return '';
		}

		/**
		 * Human-readable items column.
		 *
		 * @param WC_Order|false      $order Order.
		 * @param array<string,mixed> $raw Row.
		 * @return string
		 */
		private static function format_items_label( $order, $raw ) {
			$item_ids   = isset( $raw['item_ids'] ) ? array_map( 'absint', explode( ',', (string) $raw['item_ids'] ) ) : array();
			$quantities = isset( $raw['quantities'] ) ? array_map( 'absint', explode( ',', (string) $raw['quantities'] ) ) : array();
			$labels     = array();

			foreach ( $item_ids as $index => $item_id ) {
				$qty = isset( $quantities[ $index ] ) ? $quantities[ $index ] : 0;
				$name = '#' . $item_id;
				if ( $order ) {
					$item = $order->get_item( $item_id );
					if ( $item ) {
						$name = $item->get_name();
					}
				}
				$labels[] = sprintf( '%sx %s', $qty, $name );
			}

			return implode( '; ', $labels );
		}

		/**
		 * Render table body rows HTML.
		 *
		 * @param array<int,array<string,mixed>> $rows Rows.
		 * @return string
		 */
		private static function render_rows_html( $rows ) {
			if ( empty( $rows ) ) {
				return '<tr><td colspan="12">' . esc_html__( 'No return requests match these filters.', 'consucorner' ) . '</td></tr>';
			}

			ob_start();
			foreach ( $rows as $row ) :
				$status_class = 'cc-returns-badge--' . sanitize_html_class( $row['status'] );
				$res_class    = $row['resolution'] ? 'cc-returns-resolution--' . sanitize_html_class( $row['resolution'] ) : 'cc-returns-resolution--none';
				?>
				<tr>
					<td>#<?php echo esc_html( (string) $row['id'] ); ?></td>
					<td>
						<?php if ( ! empty( $row['order_edit_url'] ) ) : ?>
							<a href="<?php echo esc_url( $row['order_edit_url'] ); ?>">#<?php echo esc_html( (string) $row['order_id'] ); ?></a>
						<?php else : ?>
							#<?php echo esc_html( (string) $row['order_id'] ); ?>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $row['customer_name'] ); ?></td>
					<td><?php echo esc_html( $row['vendor_name'] ); ?></td>
					<td><?php echo esc_html( $row['items_label'] ); ?></td>
					<td><?php echo esc_html( $row['type_label'] ); ?></td>
					<td><?php echo esc_html( $row['reasons'] ); ?></td>
					<td><span class="cc-returns-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span></td>
					<td><span class="<?php echo esc_attr( $res_class ); ?>"><?php echo esc_html( $row['resolution_label'] ); ?></span></td>
					<td><?php echo wp_kses_post( $row['amount_html'] ); ?></td>
					<td><?php echo esc_html( $row['created_at'] ); ?></td>
					<td class="column-actions">
						<?php if ( ! empty( $row['can_workflow'] ) ) : ?>
							<div class="cc-returns-workflow-actions">
								<?php foreach ( (array) $row['workflow_actions'] as $workflow_action ) : ?>
									<button
										type="button"
										class="button button-small cc-returns-workflow-btn"
										data-request-id="<?php echo esc_attr( (string) $row['id'] ); ?>"
										data-status="<?php echo esc_attr( sanitize_key( (string) $workflow_action ) ); ?>"
									>
										<?php echo esc_html( class_exists( 'Consucorner_Order_Return_Workflow' ) ? Consucorner_Order_Return_Workflow::get_return_label( (string) $workflow_action ) : $workflow_action ); ?>
									</button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $row['can_resolve'] ) ) : ?>
							<div class="cc-returns-actions">
								<button type="button" class="button button-small cc-returns-resolve-wallet" data-request-id="<?php echo esc_attr( (string) $row['id'] ); ?>">
									<?php esc_html_e( 'Wallet', 'consucorner' ); ?>
								</button>
								<button type="button" class="button button-small cc-returns-resolve-direct" data-request-id="<?php echo esc_attr( (string) $row['id'] ); ?>">
									<?php esc_html_e( 'Direct', 'consucorner' ); ?>
								</button>
							</div>
						<?php elseif ( empty( $row['can_workflow'] ) ) : ?>
							<span class="description">—</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php
			endforeach;
			return ob_get_clean();
		}

		/**
		 * Render operations order workflow panel HTML.
		 *
		 * @param array<string,mixed> $payload Order payload.
		 * @return string
		 */
		private static function render_ops_order_html( $payload ) {
			if ( empty( $payload ) || ! is_array( $payload ) ) {
				return '';
			}

			$fulfillment_statuses = class_exists( 'Consucorner_Order_Return_Workflow' )
				? Consucorner_Order_Return_Workflow::fulfillment_statuses()
				: array();
			$reasons = class_exists( 'Consucorner_Returns_Rma_Config' )
				? Consucorner_Returns_Rma_Config::get_admin_reason_map()
				: array();

			ob_start();
			?>
			<div class="cc-returns-ops-summary">
				<p>
					<strong><?php esc_html_e( 'Order', 'consucorner' ); ?>:</strong>
					#<?php echo esc_html( (string) ( $payload['number'] ?? $payload['id'] ?? '' ) ); ?>
					<span class="description">(ID <?php echo esc_html( (string) ( $payload['id'] ?? '' ) ); ?>)</span>
				</p>
				<p>
					<strong><?php esc_html_e( 'Customer', 'consucorner' ); ?>:</strong>
					<?php echo esc_html( (string) ( $payload['customer_name'] ?? '' ) ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'WooCommerce status', 'consucorner' ); ?>:</strong>
					<?php echo esc_html( (string) ( $payload['wc_status_label'] ?? '' ) ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Operations fulfillment', 'consucorner' ); ?>:</strong>
					<?php echo esc_html( (string) ( $payload['fulfillment_label'] ?? '' ) ); ?>
					<?php if ( ! empty( $payload['return_eligible'] ) ) : ?>
						<span class="cc-returns-ops-pill cc-returns-ops-pill--ok"><?php esc_html_e( 'Return eligible', 'consucorner' ); ?></span>
					<?php else : ?>
						<span class="cc-returns-ops-pill"><?php esc_html_e( 'Not return eligible yet', 'consucorner' ); ?></span>
					<?php endif; ?>
				</p>
				<?php self::render_bosta_fulfillment_notice( $payload ); ?>
			</div>

			<div class="cc-returns-ops-vendors">
				<h3><?php esc_html_e( 'Vendor fulfillment', 'consucorner' ); ?></h3>
				<?php foreach ( (array) ( $payload['vendors'] ?? array() ) as $vendor ) : ?>
					<div class="cc-returns-ops-vendor-card" data-vendor-id="<?php echo esc_attr( (string) ( $vendor['id'] ?? 0 ) ); ?>">
						<div class="cc-returns-ops-vendor-head">
							<strong><?php echo esc_html( (string) ( $vendor['name'] ?? '' ) ); ?></strong>
							<span class="cc-returns-ops-pill"><?php echo esc_html( (string) ( $vendor['label'] ?? '' ) ); ?></span>
						</div>
						<div class="cc-returns-ops-vendor-controls">
							<label>
								<?php esc_html_e( 'Update status', 'consucorner' ); ?>
								<select class="cc-returns-fulfillment-status">
									<?php foreach ( $fulfillment_statuses as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( (string) ( $vendor['status'] ?? '' ), $key ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</label>
							<label>
								<?php esc_html_e( 'Operations note', 'consucorner' ); ?>
								<input type="text" class="cc-returns-fulfillment-note" placeholder="<?php esc_attr_e( 'Optional note', 'consucorner' ); ?>">
							</label>
							<button type="button" class="button button-secondary cc-returns-update-fulfillment" data-order-id="<?php echo esc_attr( (string) ( $payload['id'] ?? 0 ) ); ?>" data-vendor-id="<?php echo esc_attr( (string) ( $vendor['id'] ?? 0 ) ); ?>">
								<?php esc_html_e( 'Save fulfillment', 'consucorner' ); ?>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="cc-returns-ops-manual">
				<h3><?php esc_html_e( 'Create manual return request', 'consucorner' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Selections are split into one Dokan RMA request per vendor. Customer and vendor are notified.', 'consucorner' ); ?></p>
				<table class="widefat striped cc-returns-ops-items-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Select', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Item', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Vendor ID', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Qty', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Max', 'consucorner' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( (array) ( $payload['items'] ?? array() ) as $item ) : ?>
							<?php
							$blocked = ! empty( $item['blocked'] );
							$max_qty = isset( $item['max_qty'] ) ? (float) $item['max_qty'] : 0;
							?>
							<tr class="<?php echo $blocked ? 'is-blocked' : ''; ?>">
								<td>
									<input
										type="checkbox"
										class="cc-returns-manual-item"
										value="<?php echo esc_attr( (string) ( $item['item_id'] ?? 0 ) ); ?>"
										data-max-qty="<?php echo esc_attr( (string) $max_qty ); ?>"
										<?php disabled( $blocked || $max_qty < 1 ); ?>
									>
								</td>
								<td>
									<?php echo esc_html( (string) ( $item['name'] ?? '' ) ); ?>
									<?php if ( $blocked ) : ?>
										<br><span class="description"><?php echo esc_html( (string) $item['blocked'] ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( (string) ( $item['vendor_id'] ?? 0 ) ); ?></td>
								<td>
									<input
										type="number"
										min="1"
										max="<?php echo esc_attr( (string) max( 1, $max_qty ) ); ?>"
										class="cc-returns-manual-qty small-text"
										value="<?php echo esc_attr( (string) max( 1, $max_qty ) ); ?>"
										<?php disabled( $blocked || $max_qty < 1 ); ?>
									>
								</td>
								<td><?php echo esc_html( (string) $max_qty ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<div class="cc-returns-ops-manual-fields">
					<label>
						<?php esc_html_e( 'Reason', 'consucorner' ); ?>
						<select id="cc-returns-manual-reason">
							<?php foreach ( $reasons as $reason_key => $reason_label ) : ?>
								<option value="<?php echo esc_attr( sanitize_key( (string) $reason_key ) ); ?>"><?php echo esc_html( $reason_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<?php esc_html_e( 'Customer note', 'consucorner' ); ?>
						<textarea id="cc-returns-manual-details" rows="2" placeholder="<?php esc_attr_e( 'Visible to vendor/customer', 'consucorner' ); ?>"></textarea>
					</label>
					<label>
						<?php esc_html_e( 'Operations note', 'consucorner' ); ?>
						<textarea id="cc-returns-manual-note" rows="2" placeholder="<?php esc_attr_e( 'Internal operations note', 'consucorner' ); ?>"></textarea>
					</label>
					<button type="button" class="button button-primary cc-returns-create-manual" data-order-id="<?php echo esc_attr( (string) ( $payload['id'] ?? 0 ) ); ?>" <?php disabled( empty( $payload['return_eligible'] ) ); ?>>
						<?php esc_html_e( 'Create return request(s)', 'consucorner' ); ?>
					</button>
				</div>
			</div>

			<?php if ( ! empty( $payload['cancel_requests'] ) ) : ?>
				<div class="cc-returns-ops-cancel">
					<h3><?php esc_html_e( 'Cancellation requests', 'consucorner' ); ?></h3>
					<?php foreach ( (array) $payload['cancel_requests'] as $cancel_request ) : ?>
						<div class="cc-returns-ops-cancel-card" data-request-id="<?php echo esc_attr( (string) ( $cancel_request['id'] ?? '' ) ); ?>">
							<p>
								<strong><?php echo esc_html( (string) ( $cancel_request['label'] ?? '' ) ); ?></strong>
								<?php if ( ! empty( $cancel_request['whole_order'] ) ) : ?>
									<span class="cc-returns-ops-pill"><?php esc_html_e( 'Whole order', 'consucorner' ); ?></span>
								<?php endif; ?>
							</p>
							<?php if ( ! empty( $cancel_request['reason'] ) ) : ?>
								<p class="description"><?php echo esc_html( (string) $cancel_request['reason'] ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $cancel_request['items'] ) ) : ?>
								<ul>
									<?php foreach ( (array) $cancel_request['items'] as $cancel_item ) : ?>
										<li><?php echo esc_html( (string) ( $cancel_item['name'] ?? '' ) ); ?> × <?php echo esc_html( (string) ( $cancel_item['qty'] ?? 0 ) ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
							<?php if ( 'requested' === (string) ( $cancel_request['status'] ?? '' ) ) : ?>
								<div class="cc-returns-ops-cancel-actions">
									<input type="text" class="cc-returns-cancel-note" placeholder="<?php esc_attr_e( 'Optional operations note', 'consucorner' ); ?>">
									<button type="button" class="button button-primary cc-returns-review-cancel" data-order-id="<?php echo esc_attr( (string) ( $payload['id'] ?? 0 ) ); ?>" data-request-id="<?php echo esc_attr( (string) ( $cancel_request['id'] ?? '' ) ); ?>" data-decision="approved">
										<?php esc_html_e( 'Approve cancellation', 'consucorner' ); ?>
									</button>
									<button type="button" class="button button-secondary cc-returns-review-cancel" data-order-id="<?php echo esc_attr( (string) ( $payload['id'] ?? 0 ) ); ?>" data-request-id="<?php echo esc_attr( (string) ( $cancel_request['id'] ?? '' ) ); ?>" data-decision="rejected">
										<?php esc_html_e( 'Reject', 'consucorner' ); ?>
									</button>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php
			return ob_get_clean();
		}

		/**
		 * Vendor dropdown options.
		 *
		 * @return WP_User[]
		 */
		private static function get_vendors() {
			$vendors = get_users(
				array(
					'role__in' => array( 'seller', 'vendor' ),
					'fields'   => array( 'ID', 'display_name' ),
					'orderby'  => 'display_name',
					'order'    => 'ASC',
					'number'   => 1000,
				)
			);

			if ( ! empty( $vendors ) ) {
				return $vendors;
			}

			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ids = $wpdb->get_col( "SELECT DISTINCT vendor_id FROM {$wpdb->prefix}dokan_rma_request WHERE vendor_id > 0 LIMIT 1000" );
			if ( ! $ids ) {
				return array();
			}

			return get_users(
				array(
					'include' => array_map( 'absint', $ids ),
					'fields'  => array( 'ID', 'display_name' ),
					'orderby' => 'display_name',
				)
			);
		}

		/**
		 * Public URL for the complete operations HTML guide.
		 *
		 * @return string
		 */
		public static function get_operations_complete_guide_url() {
			return trailingslashit( get_template_directory_uri() ) . 'docs/operations-complete-guide.html';
		}

		/**
		 * Public URL for the fulfillment operations HTML guide.
		 *
		 * @return string
		 */
		public static function get_operations_fulfillment_guide_url() {
			return trailingslashit( get_template_directory_uri() ) . 'docs/operations-fulfillment-guide.html';
		}

		/**
		 * Public URL for the returns &amp; refunds operations HTML guide.
		 *
		 * @return string
		 */
		public static function get_operations_returns_refunds_guide_url() {
			return trailingslashit( get_template_directory_uri() ) . 'docs/operations-returns-refunds-guide.html';
		}

		/**
		 * Render the three operations guide buttons.
		 *
		 * @param string $layout stack|inline.
		 */
		private static function render_operations_guide_buttons( $layout = 'inline' ) {
			$stack = ( 'stack' === $layout );
			$style = $stack
				? 'display:flex;flex-direction:column;gap:8px;'
				: 'display:inline-flex;flex-wrap:wrap;gap:6px;margin-left:8px;vertical-align:middle;';
			?>
			<span class="cc-ops-guide-buttons" style="<?php echo esc_attr( $style ); ?>">
				<a class="button button-primary" href="<?php echo esc_url( self::get_operations_complete_guide_url() ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Complete guide', 'consucorner' ); ?>
				</a>
				<a class="button button-secondary" href="<?php echo esc_url( self::get_operations_fulfillment_guide_url() ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Fulfillment guide', 'consucorner' ); ?>
				</a>
				<a class="button button-secondary" href="<?php echo esc_url( self::get_operations_returns_refunds_guide_url() ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Returns & refunds guide', 'consucorner' ); ?>
				</a>
			</span>
			<?php
		}

		/**
		 * Whether the current admin screen is the WooCommerce orders list.
		 *
		 * @param WP_Screen|null $screen Current screen.
		 * @return bool
		 */
		private static function is_wc_orders_list_screen( $screen ) {
			if ( ! $screen instanceof WP_Screen ) {
				return false;
			}

			if ( 'edit-shop_order' === $screen->id ) {
				return true;
			}

			if ( 'woocommerce_page_wc-orders' === $screen->id ) {
				$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return 'edit' !== $action;
			}

			return false;
		}

		/**
		 * Banner on WooCommerce → Orders list linking to the operations guide.
		 */
		public static function render_orders_list_guide_notice() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				return;
			}

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! self::is_wc_orders_list_screen( $screen ) ) {
				return;
			}
			?>
			<div class="notice notice-info cc-orders-ops-guide-notice">
				<p>
					<strong><?php esc_html_e( 'Operations guides:', 'consucorner' ); ?></strong>
					<?php esc_html_e( 'Fulfillment, cancellations, returns, Wallet/Direct refunds, and Bosta sync.', 'consucorner' ); ?>
					<?php self::render_operations_guide_buttons( 'inline' ); ?>
				</p>
			</div>
			<?php
		}

		/**
		 * Sidebar meta box on order edit screens.
		 */
		public static function register_operations_guide_meta_box() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				return;
			}

			$screens = array( 'shop_order', 'woocommerce_page_wc-orders' );
			foreach ( $screens as $screen ) {
				add_meta_box(
					'cc-order-operations-guide',
					__( 'Operations guide', 'consucorner' ),
					array( __CLASS__, 'render_operations_guide_meta_box' ),
					$screen,
					'side',
					'high'
				);
			}
		}

		/**
		 * @param WP_Post|WC_Order $post_or_order Order object.
		 */
		public static function render_operations_guide_meta_box( $post_or_order ) {
			unset( $post_or_order );
			?>
			<p><?php esc_html_e( 'Open the operations training guides (new tab):', 'consucorner' ); ?></p>
			<?php self::render_operations_guide_buttons( 'stack' ); ?>
			<?php
		}

		/**
		 * Register order sidebar meta box for returns summary.
		 */
		public static function register_order_meta_box() {
			$screens = array( 'shop_order', 'woocommerce_page_wc-orders' );
			foreach ( $screens as $screen ) {
				add_meta_box(
					'cc-order-returns',
					__( 'Returns & Refunds', 'consucorner' ),
					array( __CLASS__, 'render_order_meta_box' ),
					$screen,
					'side',
					'high'
				);
			}
		}

		/**
		 * @param WP_Post|WC_Order $post_or_order Order object.
		 */
		public static function render_order_meta_box( $post_or_order ) {
			$order = ( $post_or_order instanceof WC_Order ) ? $post_or_order : wc_get_order( $post_or_order->ID );
			if ( ! $order ) {
				echo '<p>' . esc_html__( 'Order not found.', 'consucorner' ) . '</p>';
				return;
			}

			$requests = class_exists( 'CC_Returns_Refund_Service' )
				? CC_Returns_Refund_Service::get_order_requests( $order->get_id() )
				: array();

			if ( empty( $requests ) ) {
				echo '<p class="description">' . esc_html__( 'No return requests for this order.', 'consucorner' ) . '</p>';
				return;
			}

			$report_url = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&order_id=' . $order->get_id() );
			echo '<ul style="margin:0 0 8px;padding-left:16px;">';
			foreach ( $requests as $request ) {
				$id     = absint( $request['id'] ?? 0 );
				$status = sanitize_key( (string) ( $request['status'] ?? '' ) );
				$type   = sanitize_key( (string) ( $request['type'] ?? '' ) );
				$res    = class_exists( 'CC_Returns_Refund_Service' )
					? CC_Returns_Refund_Service::get_resolution( $order->get_id(), $id )
					: null;
				$res_label = $res ? self::get_resolution_label( (string) ( $res['route'] ?? '' ) ) : __( 'Open', 'consucorner' );
				printf(
					'<li><strong>#%1$d</strong> — %2$s / %3$s<br><span class="description">%4$s</span></li>',
					$id,
					esc_html( self::get_type_label( $type ) ),
					esc_html( self::get_status_label( $status ) ),
					esc_html( $res_label )
				);
			}
			echo '</ul>';
			printf(
				'<a class="button button-small" href="%s">%s</a>',
				esc_url( $report_url ),
				esc_html__( 'Open in Returns', 'consucorner' )
			);
		}

		/**
		 * Operations fulfillment controls on the WooCommerce order edit screen.
		 *
		 * @param WC_Order $order Order.
		 */
		public static function render_order_fulfillment_panel( $order ) {
			if ( ! $order instanceof WC_Order || ! current_user_can( self::CAPABILITY ) || ! class_exists( 'Consucorner_Order_Return_Workflow' ) ) {
				return;
			}

			$payload = Consucorner_Order_Return_Workflow::get_order_ops_payload( $order->get_id() );
			if ( is_wp_error( $payload ) ) {
				return;
			}

			$fulfillment_statuses = Consucorner_Order_Return_Workflow::fulfillment_statuses();
			$report_url           = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&order_id=' . rawurlencode( (string) $order->get_id() ) );
			?>
			<div class="cc-order-fulfillment-panel">
				<h3><?php esc_html_e( 'ConsuCorner operations fulfillment', 'consucorner' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'These statuses are separate from the WooCommerce payment status above. Vendor fulfillment auto-syncs from Bosta when shipment metadata is present. Customers can request returns after operations marks the order as Shipped or later.', 'consucorner' ); ?>
				</p>
				<?php self::render_bosta_fulfillment_notice( $payload ); ?>
				<p>
					<strong><?php esc_html_e( 'Current fulfillment:', 'consucorner' ); ?></strong>
					<?php echo esc_html( (string) ( $payload['fulfillment_label'] ?? '' ) ); ?>
					<?php if ( ! empty( $payload['return_eligible'] ) ) : ?>
						<span class="cc-returns-ops-pill cc-returns-ops-pill--ok"><?php esc_html_e( 'Return eligible', 'consucorner' ); ?></span>
					<?php else : ?>
						<span class="cc-returns-ops-pill"><?php esc_html_e( 'Not return eligible yet', 'consucorner' ); ?></span>
					<?php endif; ?>
				</p>
				<?php foreach ( (array) ( $payload['vendors'] ?? array() ) as $vendor ) : ?>
					<div class="cc-returns-ops-vendor-card" data-vendor-id="<?php echo esc_attr( (string) ( $vendor['id'] ?? 0 ) ); ?>">
						<div class="cc-returns-ops-vendor-head">
							<strong><?php echo esc_html( (string) ( $vendor['name'] ?? '' ) ); ?></strong>
							<span class="cc-returns-ops-pill"><?php echo esc_html( (string) ( $vendor['label'] ?? '' ) ); ?></span>
						</div>
						<div class="cc-returns-ops-vendor-controls">
							<label>
								<?php esc_html_e( 'Update status', 'consucorner' ); ?>
								<select class="cc-returns-fulfillment-status">
									<?php foreach ( $fulfillment_statuses as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( (string) ( $vendor['status'] ?? '' ), $key ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</label>
							<label>
								<?php esc_html_e( 'Operations note', 'consucorner' ); ?>
								<input type="text" class="cc-returns-fulfillment-note" placeholder="<?php esc_attr_e( 'Optional note', 'consucorner' ); ?>">
							</label>
							<button type="button" class="button button-secondary cc-returns-update-fulfillment" data-order-id="<?php echo esc_attr( (string) ( $payload['id'] ?? 0 ) ); ?>" data-vendor-id="<?php echo esc_attr( (string) ( $vendor['id'] ?? 0 ) ); ?>">
								<?php esc_html_e( 'Save fulfillment', 'consucorner' ); ?>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $report_url ); ?>">
						<?php esc_html_e( 'Open full Returns workflow', 'consucorner' ); ?>
					</a>
				</p>
			</div>
			<?php
		}

		/**
		 * Show Bosta shipment status and mapped fulfillment sync hint.
		 *
		 * @param array<string,mixed> $payload Operations payload.
		 */
		private static function render_bosta_fulfillment_notice( $payload ) {
			$bosta = isset( $payload['bosta'] ) && is_array( $payload['bosta'] ) ? $payload['bosta'] : array();
			if ( empty( $bosta['status'] ) && empty( $bosta['state_code'] ) && empty( $bosta['tracking_number'] ) ) {
				return;
			}
			?>
			<p class="cc-returns-bosta-sync">
				<strong><?php esc_html_e( 'Bosta shipment', 'consucorner' ); ?>:</strong>
				<?php
				if ( ! empty( $bosta['status'] ) ) {
					echo esc_html( (string) $bosta['status'] );
				} elseif ( ! empty( $bosta['state_code'] ) ) {
					printf(
						esc_html__( 'State %d', 'consucorner' ),
						absint( $bosta['state_code'] )
					);
				}
				?>
				<?php if ( ! empty( $bosta['tracking_number'] ) ) : ?>
					<span class="description">
						<?php
						printf(
							/* translators: %s: Bosta tracking number */
							esc_html__( 'Tracking #%s', 'consucorner' ),
							esc_html( (string) $bosta['tracking_number'] )
						);
						?>
					</span>
				<?php endif; ?>
				<?php if ( ! empty( $bosta['mapped_label'] ) ) : ?>
					<span class="cc-returns-ops-pill cc-returns-ops-pill--ok">
						<?php
						printf(
							/* translators: %s: fulfillment status label */
							esc_html__( 'Auto-syncs to %s', 'consucorner' ),
							esc_html( (string) $bosta['mapped_label'] )
						);
						?>
					</span>
				<?php endif; ?>
			</p>
			<?php
		}

		/**
		 * Notice under order details when a return exists.
		 *
		 * @param WC_Order $order Order.
		 */
		public static function render_order_details_notice( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return;
			}
			if ( 'yes' !== $order->get_meta( '_cc_has_return_request', true ) ) {
				$open = class_exists( 'CC_Returns_Refund_Service' )
					? CC_Returns_Refund_Service::get_open_order_requests( $order->get_id() )
					: array();
				if ( empty( $open ) ) {
					return;
				}
			}

			$url = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&order_id=' . $order->get_id() );
			echo '<p class="form-field form-field-wide" style="margin-top:12px;padding:10px 12px;background:#fff8e5;border-left:4px solid #dba617;">';
			echo '<strong>' . esc_html__( 'Return / refund activity', 'consucorner' ) . '</strong> — ';
			printf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'View on Returns report', 'consucorner' )
			);
			echo '</p>';
		}

		/**
		 * Add Returns column on orders list.
		 *
		 * @param array<string,string> $columns Columns.
		 * @return array<string,string>
		 */
		public static function add_orders_list_column( $columns ) {
			$new = array();
			foreach ( $columns as $key => $label ) {
				$new[ $key ] = $label;
				if ( 'order_status' === $key ) {
					$new['cc_returns'] = __( 'Returns', 'consucorner' );
				}
			}
			if ( ! isset( $new['cc_returns'] ) ) {
				$new['cc_returns'] = __( 'Returns', 'consucorner' );
			}
			return $new;
		}

		/**
		 * Classic orders list column.
		 *
		 * @param string $column Column key.
		 * @param int    $post_id Post ID.
		 */
		public static function render_orders_list_column( $column, $post_id ) {
			if ( 'cc_returns' !== $column ) {
				return;
			}
			self::echo_order_returns_badge( absint( $post_id ) );
		}

		/**
		 * HPOS orders list column.
		 *
		 * @param string   $column Column key.
		 * @param WC_Order $order Order.
		 */
		public static function render_hpos_orders_list_column( $column, $order ) {
			if ( 'cc_returns' !== $column || ! $order instanceof WC_Order ) {
				return;
			}
			self::echo_order_returns_badge( $order->get_id() );
		}

		/**
		 * @param int $order_id Order ID.
		 */
		private static function echo_order_returns_badge( $order_id ) {
			$order_id = absint( $order_id );
			if ( ! $order_id || ! class_exists( 'CC_Returns_Refund_Service' ) ) {
				echo '—';
				return;
			}

			$open = CC_Returns_Refund_Service::get_open_order_requests( $order_id );
			if ( ! empty( $open ) ) {
				printf(
					'<a href="%s"><mark class="order-status status-on-hold tips" style="background:#fff3bf;color:#e67700;"><span>%s</span></mark></a>',
					esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&order_id=' . $order_id ) ),
					esc_html(
						sprintf(
							/* translators: %d: open request count */
							_n( '%d open', '%d open', count( $open ), 'consucorner' ),
							count( $open )
						)
					)
				);
				return;
			}

			$all = CC_Returns_Refund_Service::get_order_requests( $order_id );
			if ( empty( $all ) ) {
				echo '—';
				return;
			}

			printf(
				'<a href="%s"><mark class="order-status status-completed tips"><span>%s</span></mark></a>',
				esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&order_id=' . $order_id ) ),
				esc_html__( 'Resolved', 'consucorner' )
			);
		}

		/**
		 * Status labels map.
		 *
		 * @return array<string,string>
		 */
		private static function get_status_options() {
			if ( function_exists( 'dokan_warranty_request_status' ) ) {
				return dokan_warranty_request_status();
			}
			return array(
				'new'        => __( 'New', 'consucorner' ),
				'processing' => __( 'Processing', 'consucorner' ),
				'completed'  => __( 'Completed', 'consucorner' ),
				'rejected'   => __( 'Rejected', 'consucorner' ),
				'reviewing'  => __( 'Reviewing', 'consucorner' ),
			);
		}

		/**
		 * Type labels map.
		 *
		 * @return array<string,string>
		 */
		private static function get_type_options() {
			if ( function_exists( 'dokan_warranty_request_type' ) ) {
				return dokan_warranty_request_type();
			}
			return array(
				'replace' => __( 'Replace', 'consucorner' ),
				'refund'  => __( 'Refund', 'consucorner' ),
				'coupon'  => __( 'Store credit', 'consucorner' ),
			);
		}

		/**
		 * @param string $status Status key.
		 * @return string
		 */
		private static function get_status_label( $status ) {
			$options = self::get_status_options();
			return $options[ $status ] ?? $status;
		}

		/**
		 * @param string $type Type key.
		 * @return string
		 */
		private static function get_type_label( $type ) {
			$options = self::get_type_options();
			return $options[ $type ] ?? $type;
		}

		/**
		 * @param string $route Resolution route.
		 * @return string
		 */
		private static function get_resolution_label( $route ) {
			if ( 'wallet' === $route ) {
				return __( 'Wallet', 'consucorner' );
			}
			if ( 'direct' === $route ) {
				return __( 'Direct (manual)', 'consucorner' );
			}
			return __( 'None', 'consucorner' );
		}
	}

	Consucorner_Returns_Report::init();

endif;
