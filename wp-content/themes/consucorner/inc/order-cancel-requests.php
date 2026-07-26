<?php
/**
 * Customer cancellation requests with operations approval.
 *
 * @package Consucorner
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Consucorner_Order_Cancel_Requests' ) ) :

	/**
	 * Store and process cancellation requests on parent orders.
	 */
	final class Consucorner_Order_Cancel_Requests {

		const META_REQUESTS = '_cc_cancel_requests';

		/**
		 * Register hooks.
		 */
		public static function init() {
			add_action( 'wp_ajax_consucorner_profile_request_cancel', array( __CLASS__, 'ajax_customer_request_cancel' ) );
			add_action( 'wp_ajax_cc_returns_review_cancel', array( __CLASS__, 'ajax_review_cancel_request' ) );
		}

		/**
		 * Status labels.
		 *
		 * @return array<string,string>
		 */
		public static function status_labels() {
			return array(
				'requested' => __( 'Requested', 'consucorner' ),
				'approved'  => __( 'Approved', 'consucorner' ),
				'rejected'  => __( 'Rejected', 'consucorner' ),
			);
		}

		/**
		 * Read all requests for an order.
		 *
		 * @param WC_Order $order Order.
		 * @return array<string,array<string,mixed>>
		 */
		public static function get_requests( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return array();
			}

			$all = $order->get_meta( self::META_REQUESTS, true );
			return is_array( $all ) ? $all : array();
		}

		/**
		 * Get one request by ID.
		 *
		 * @param WC_Order $order Order.
		 * @param string   $request_id Request ID.
		 * @return array<string,mixed>|null
		 */
		public static function get_request( $order, $request_id ) {
			$all = self::get_requests( $order );
			$key = self::resolve_request_storage_key( $all, $request_id );
			if ( '' === $key ) {
				return null;
			}

			return isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : null;
		}

		/**
		 * Resolve the meta array key for a cancellation request ID.
		 *
		 * Request IDs are generated with mixed case, while admin/AJAX input is
		 * normalized with sanitize_key(). Match either form safely.
		 *
		 * @param array<string,array<string,mixed>> $all Stored requests.
		 * @param string                            $request_id Request ID.
		 * @return string
		 */
		private static function resolve_request_storage_key( $all, $request_id ) {
			if ( ! is_array( $all ) ) {
				return '';
			}

			$request_id = trim( (string) $request_id );
			if ( '' === $request_id ) {
				return '';
			}

			if ( isset( $all[ $request_id ] ) && is_array( $all[ $request_id ] ) ) {
				return $request_id;
			}

			$sanitized = sanitize_key( $request_id );
			if ( '' !== $sanitized && isset( $all[ $sanitized ] ) && is_array( $all[ $sanitized ] ) ) {
				return $sanitized;
			}

			$needle = strtolower( $request_id );
			foreach ( $all as $key => $request ) {
				if ( ! is_array( $request ) ) {
					continue;
				}
				if ( strtolower( (string) $key ) === $needle ) {
					return (string) $key;
				}
				if ( isset( $request['id'] ) && strtolower( (string) $request['id'] ) === $needle ) {
					return (string) $key;
				}
			}

			return '';
		}

		/**
		 * Pending request count across recent orders (cached).
		 *
		 * @return int
		 */
		public static function get_pending_count() {
			$cached = get_transient( 'cc_cancel_pending_count' );
			if ( false !== $cached ) {
				return absint( $cached );
			}

			global $wpdb;
			$count = 0;

			if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$table = $wpdb->prefix . 'wc_orders_meta';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT meta_value FROM {$table} WHERE meta_key = %s LIMIT 500",
						self::META_REQUESTS
					)
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 500",
						self::META_REQUESTS
					)
				);
			}

			foreach ( (array) $rows as $raw ) {
				$requests = maybe_unserialize( $raw );
				if ( ! is_array( $requests ) ) {
					continue;
				}
				foreach ( $requests as $request ) {
					if ( is_array( $request ) && 'requested' === sanitize_key( (string) ( $request['status'] ?? '' ) ) ) {
						++$count;
					}
				}
			}

			set_transient( 'cc_cancel_pending_count', $count, 5 * MINUTE_IN_SECONDS );

			return $count;
		}

		/**
		 * Clear pending count cache.
		 */
		public static function clear_pending_count_cache() {
			delete_transient( 'cc_cancel_pending_count' );
		}

		/**
		 * Summary for vendor dashboard badge.
		 *
		 * @param int $order_id Order ID.
		 * @param int $vendor_id Vendor ID.
		 * @return array{status:string,label:string}
		 */
		public static function get_vendor_summary( $order_id, $vendor_id ) {
			$order = wc_get_order( absint( $order_id ) );
			if ( ! $order ) {
				return array();
			}

			$vendor_id = absint( $vendor_id );
			$latest    = null;

			foreach ( self::get_requests( $order ) as $request ) {
				if ( ! is_array( $request ) ) {
					continue;
				}
				if ( ! self::request_affects_vendor( $request, $order, $vendor_id ) ) {
					continue;
				}
				$latest = $request;
			}

			if ( ! $latest ) {
				return array();
			}

			$status = sanitize_key( (string) ( $latest['status'] ?? '' ) );
			$labels = self::status_labels();

			return array(
				'status' => $status,
				'label'  => isset( $labels[ $status ] ) ? $labels[ $status ] : $status,
			);
		}

		/**
		 * Customer-facing cancel state for profile JSON.
		 *
		 * @param WC_Order $order Order.
		 * @return array<string,mixed>
		 */
		public static function get_customer_payload( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return array(
					'can_request_cancel'    => false,
					'cancel_blocked_reason' => '',
					'has_pending_cancel'    => false,
					'cancel_items'          => array(),
				);
			}

			$items          = array();
			$has_cancelable = false;

			foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}

				$vendor_id = class_exists( 'CC_Returns_Refund_Service' )
					? CC_Returns_Refund_Service::get_item_vendor_id( $item, $order )
					: 0;
				$qty       = (float) $item->get_quantity();
				$refunded  = abs( (float) $order->get_qty_refunded_for_item( $item_id ) );
				$max_qty   = max( 0, $qty - $refunded );
				$eligible  = $max_qty > 0
					&& class_exists( 'Consucorner_Order_Return_Workflow' )
					&& Consucorner_Order_Return_Workflow::vendor_is_cancel_eligible( $order, $vendor_id );

				if ( $eligible ) {
					$has_cancelable = true;
				}

				$items[] = array(
					'item_id'     => (int) $item_id,
					'name'        => wp_strip_all_tags( $item->get_name() ),
					'qty'         => $qty,
					'max_qty'     => $max_qty,
					'can_cancel'  => $eligible,
					'vendor_id'   => $vendor_id,
				);
			}

			$has_pending = self::order_has_pending_request( $order );
			$allowed     = $has_cancelable
				&& ! $has_pending
				&& ! $order->has_status( array( 'cancelled', 'refunded', 'failed', 'completed' ) );

			$reason = '';
			if ( $has_pending ) {
				$reason = __( 'A cancellation request is already pending review.', 'consucorner' );
			} elseif ( $order->has_status( array( 'completed' ) ) || ( class_exists( 'Consucorner_Order_Return_Workflow' ) && Consucorner_Order_Return_Workflow::order_allows_customer_return( $order ) ) ) {
				$reason = __( 'This order was delivered. Please request a return instead of cancelling.', 'consucorner' );
			} elseif ( ! $has_cancelable ) {
				$reason = __( 'This order can no longer be cancelled online.', 'consucorner' );
			}

			return array(
				'can_request_cancel'    => $allowed,
				'cancel_blocked_reason' => $reason,
				'has_pending_cancel'    => $has_pending,
				'cancel_items'          => $items,
			);
		}

		/**
		 * Operations payload list.
		 *
		 * @param WC_Order $order Order.
		 * @return array<int,array<string,mixed>>
		 */
		public static function get_ops_payload( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return array();
			}

			$rows = array();
			foreach ( self::get_requests( $order ) as $request_id => $request ) {
				if ( ! is_array( $request ) ) {
					continue;
				}

				$items = array();
				foreach ( (array) ( $request['items'] ?? array() ) as $item_id => $qty ) {
					$item = $order->get_item( absint( $item_id ) );
					$items[] = array(
						'item_id' => absint( $item_id ),
						'name'    => $item ? wp_strip_all_tags( $item->get_name() ) : '',
						'qty'     => wc_stock_amount( $qty ),
					);
				}

				$status = sanitize_key( (string) ( $request['status'] ?? '' ) );
				$labels = self::status_labels();

				$rows[] = array(
					'id'          => (string) $request_id,
					'status'      => $status,
					'label'       => isset( $labels[ $status ] ) ? $labels[ $status ] : $status,
					'whole_order' => ! empty( $request['whole_order'] ),
					'items'       => $items,
					'reason'      => (string) ( $request['reason'] ?? '' ),
					'created_at'  => (string) ( $request['created_at'] ?? '' ),
				);
			}

			return $rows;
		}

		/**
		 * Create a customer cancellation request.
		 *
		 * @param int                  $order_id Order ID.
		 * @param int                  $customer_id Customer user ID.
		 * @param bool                 $whole_order Whole order flag.
		 * @param array<int,int|float> $item_quantities item_id => qty.
		 * @param string               $reason Reason text.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function create_request( $order_id, $customer_id, $whole_order, $item_quantities, $reason = '' ) {
			$order = wc_get_order( absint( $order_id ) );
			if ( ! $order ) {
				return new WP_Error( 'cc_cancel_order', __( 'Order not found.', 'consucorner' ) );
			}

			if ( (int) $order->get_customer_id() !== absint( $customer_id ) ) {
				return new WP_Error( 'cc_cancel_owner', __( 'You cannot cancel this order.', 'consucorner' ) );
			}

			if ( self::order_has_pending_request( $order ) ) {
				return new WP_Error( 'cc_cancel_pending', __( 'A cancellation request is already pending review.', 'consucorner' ) );
			}

			if ( $order->has_status( array( 'cancelled', 'refunded', 'failed', 'completed' ) ) ) {
				return new WP_Error( 'cc_cancel_status', __( 'This order can no longer be cancelled.', 'consucorner' ) );
			}

			if ( class_exists( 'Consucorner_Order_Return_Workflow' ) && Consucorner_Order_Return_Workflow::order_allows_customer_return( $order ) ) {
				return new WP_Error( 'cc_cancel_delivered', __( 'This order was delivered. Please request a return instead of cancelling.', 'consucorner' ) );
			}

			$whole_order     = (bool) $whole_order;
			$item_quantities = is_array( $item_quantities ) ? $item_quantities : array();
			$selected        = array();

			if ( $whole_order ) {
				foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
					if ( ! $item instanceof WC_Order_Item_Product ) {
						continue;
					}
					$vendor_id = class_exists( 'CC_Returns_Refund_Service' )
						? CC_Returns_Refund_Service::get_item_vendor_id( $item, $order )
						: 0;
					if ( ! class_exists( 'Consucorner_Order_Return_Workflow' ) || ! Consucorner_Order_Return_Workflow::vendor_is_cancel_eligible( $order, $vendor_id ) ) {
						return new WP_Error( 'cc_cancel_vendor', __( 'Some items in this order can no longer be cancelled.', 'consucorner' ) );
					}
					$qty = max( 0, (float) $item->get_quantity() - abs( (float) $order->get_qty_refunded_for_item( $item_id ) ) );
					if ( $qty > 0 ) {
						$selected[ absint( $item_id ) ] = wc_stock_amount( $qty );
					}
				}
			} else {
				foreach ( $item_quantities as $item_id => $qty ) {
					$item_id = absint( $item_id );
					$qty     = wc_stock_amount( $qty );
					if ( ! $item_id || $qty < 1 ) {
						continue;
					}

					$item = $order->get_item( $item_id );
					if ( ! $item instanceof WC_Order_Item_Product ) {
						return new WP_Error( 'cc_cancel_item', __( 'Invalid item selected.', 'consucorner' ) );
					}

					$vendor_id = class_exists( 'CC_Returns_Refund_Service' )
						? CC_Returns_Refund_Service::get_item_vendor_id( $item, $order )
						: 0;
					if ( ! class_exists( 'Consucorner_Order_Return_Workflow' ) || ! Consucorner_Order_Return_Workflow::vendor_is_cancel_eligible( $order, $vendor_id ) ) {
						return new WP_Error( 'cc_cancel_vendor', __( 'Selected items can no longer be cancelled.', 'consucorner' ) );
					}

					$max_qty = max( 0, (float) $item->get_quantity() - abs( (float) $order->get_qty_refunded_for_item( $item_id ) ) );
					if ( $qty > $max_qty ) {
						return new WP_Error( 'cc_cancel_qty', __( 'Cancellation quantity exceeds available quantity.', 'consucorner' ) );
					}

					$selected[ $item_id ] = $qty;
				}
			}

			if ( empty( $selected ) ) {
				return new WP_Error( 'cc_cancel_empty', __( 'Select at least one item to cancel.', 'consucorner' ) );
			}

			$request_id = 'cr_' . strtolower( wp_generate_password( 8, false, false ) );
			$all        = self::get_requests( $order );
			$all[ $request_id ] = array(
				'id'          => $request_id,
				'status'      => 'requested',
				'whole_order' => $whole_order,
				'items'       => $selected,
				'reason'      => sanitize_textarea_field( $reason ),
				'created_at'  => current_time( 'mysql' ),
				'created_by'  => absint( $customer_id ),
				'history'     => array(
					array(
						'status'     => 'requested',
						'note'       => sanitize_textarea_field( $reason ),
						'changed_at' => current_time( 'mysql' ),
						'changed_by' => absint( $customer_id ),
					),
				),
			);

			$order->update_meta_data( self::META_REQUESTS, $all );
			$order->add_order_note(
				sprintf(
					/* translators: %s: request id */
					__( 'Customer submitted cancellation request %s.', 'consucorner' ),
					$request_id
				)
			);
			$order->save();

			self::clear_pending_count_cache();
			self::notify_cancel_request_created( $order, $all[ $request_id ] );

			return $all[ $request_id ];
		}

		/**
		 * Approve or reject a cancellation request.
		 *
		 * @param int    $order_id Order ID.
		 * @param string $request_id Request ID.
		 * @param string $decision approved|rejected.
		 * @param string $note Optional ops note.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function review_request( $order_id, $request_id, $decision, $note = '' ) {
			$order = wc_get_order( absint( $order_id ) );
			if ( ! $order ) {
				return new WP_Error( 'cc_cancel_order', __( 'Order not found.', 'consucorner' ) );
			}

			$request = self::get_request( $order, $request_id );
			if ( ! $request ) {
				return new WP_Error( 'cc_cancel_missing', __( 'Cancellation request not found.', 'consucorner' ) );
			}

			$decision = sanitize_key( $decision );
			if ( ! in_array( $decision, array( 'approved', 'rejected' ), true ) ) {
				return new WP_Error( 'cc_cancel_decision', __( 'Invalid cancellation decision.', 'consucorner' ) );
			}

			if ( 'requested' !== sanitize_key( (string) ( $request['status'] ?? '' ) ) ) {
				return new WP_Error( 'cc_cancel_state', __( 'This cancellation request was already reviewed.', 'consucorner' ) );
			}

			if ( 'rejected' === $decision ) {
				return self::set_request_status( $order, $request_id, 'rejected', $note );
			}

			return self::approve_request( $order, $request_id, $note );
		}

		/**
		 * AJAX: customer cancellation request.
		 */
		public static function ajax_customer_request_cancel() {
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'consucorner' ) ), 403 );
			}

			check_ajax_referer( 'consucorner_profile_nonce', 'nonce' );

			$data       = function_exists( 'consucorner_profile_payload' ) ? consucorner_profile_payload() : $_POST;
			$order_id   = isset( $data['order_id'] ) ? absint( $data['order_id'] ) : 0;
			$whole      = ! empty( $data['whole_order'] );
			$reason     = isset( $data['reason'] ) ? sanitize_textarea_field( (string) $data['reason'] ) : '';
			$items      = isset( $data['items'] ) && is_array( $data['items'] ) ? wp_unslash( $data['items'] ) : array();
			$quantities = array();

			foreach ( $items as $item_id => $qty ) {
				$quantities[ absint( $item_id ) ] = wc_stock_amount( $qty );
			}

			$result = self::create_request( $order_id, get_current_user_id(), $whole, $quantities, $reason );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			}

			wp_send_json_success(
				array(
					'message' => __( 'Cancellation request submitted. Operations will review it shortly.', 'consucorner' ),
					'request' => $result,
				)
			);
		}

		/**
		 * AJAX: operations approve/reject cancellation.
		 */
		public static function ajax_review_cancel_request() {
			check_ajax_referer( Consucorner_Returns_Report::NONCE_KEY, 'nonce' );
			if ( ! current_user_can( Consucorner_Returns_Report::CAPABILITY ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'consucorner' ) ), 403 );
			}

			$order_id   = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$request_id = isset( $_POST['request_id'] ) ? sanitize_key( wp_unslash( $_POST['request_id'] ) ) : '';
			$decision   = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
			$note       = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

			$result = self::review_request( $order_id, $request_id, $decision, $note );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			}

			Consucorner_Returns_Report::clear_pending_count_cache();

			$payload = Consucorner_Order_Return_Workflow::get_order_ops_payload( $order_id );
			if ( is_wp_error( $payload ) ) {
				wp_send_json_error( array( 'message' => $payload->get_error_message() ), 400 );
			}

			wp_send_json_success(
				array(
					'message' => 'approved' === $decision
						? __( 'Cancellation approved.', 'consucorner' )
						: __( 'Cancellation rejected.', 'consucorner' ),
					'result'  => $result,
					'order'   => $payload,
					'html'    => Consucorner_Returns_Report::render_ops_order_html_public( $payload ),
				)
			);
		}

		/**
		 * Whether order has a pending cancellation request.
		 *
		 * @param WC_Order $order Order.
		 * @return bool
		 */
		private static function order_has_pending_request( $order ) {
			foreach ( self::get_requests( $order ) as $request ) {
				if ( is_array( $request ) && 'requested' === sanitize_key( (string) ( $request['status'] ?? '' ) ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Whether a request includes items from a vendor.
		 *
		 * @param array<string,mixed> $request Request.
		 * @param WC_Order            $order Order.
		 * @param int                 $vendor_id Vendor ID.
		 * @return bool
		 */
		private static function request_affects_vendor( $request, $order, $vendor_id ) {
			foreach ( (array) ( $request['items'] ?? array() ) as $item_id => $qty ) {
				$item = $order->get_item( absint( $item_id ) );
				if ( ! $item instanceof WC_Order_Item_Product || ! class_exists( 'CC_Returns_Refund_Service' ) ) {
					continue;
				}
				if ( CC_Returns_Refund_Service::get_item_vendor_id( $item, $order ) === absint( $vendor_id ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Approve and execute cancellation side effects.
		 *
		 * @param WC_Order $order Order.
		 * @param string   $request_id Request ID.
		 * @param string   $note Ops note.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function approve_request( $order, $request_id, $note ) {
			$request = self::get_request( $order, $request_id );
			if ( ! $request ) {
				return new WP_Error( 'cc_cancel_missing', __( 'Cancellation request not found.', 'consucorner' ) );
			}

			$items_map = (array) ( $request['items'] ?? array() );
			$vendors   = array();
			$line_items = array();
			$refund_amount = 0.0;

			foreach ( $items_map as $item_id => $qty ) {
				$item_id = absint( $item_id );
				$qty     = wc_stock_amount( $qty );
				$item    = $order->get_item( $item_id );
				if ( ! $item instanceof WC_Order_Item_Product || $qty < 1 ) {
					continue;
				}

				$vendor_id = class_exists( 'CC_Returns_Refund_Service' )
					? CC_Returns_Refund_Service::get_item_vendor_id( $item, $order )
					: 0;
				if ( $vendor_id ) {
					$vendors[ $vendor_id ] = true;
				}

				$line_qty      = max( 1, (float) $item->get_quantity() );
				$qty_ratio     = min( 1, $qty / $line_qty );
				$line_subtotal = (float) $item->get_total();
				$line_tax      = array_sum( array_map( 'floatval', (array) $item->get_taxes()['total'] ) );
				$line_refund   = wc_format_decimal( ( $line_subtotal + $line_tax ) * $qty_ratio );
				$refund_amount += (float) $line_refund;

				$line_items[ $item_id ] = array(
					'qty'          => $qty,
					'refund_total' => $line_subtotal * $qty_ratio,
					'refund_tax'   => array_map(
						static function ( $tax ) use ( $qty_ratio ) {
							return wc_format_decimal( (float) $tax * $qty_ratio );
						},
						(array) $item->get_taxes()['total']
					),
				);
			}

			if ( empty( $line_items ) ) {
				return new WP_Error( 'cc_cancel_lines', __( 'No cancellable items found on this request.', 'consucorner' ) );
			}

			if ( ! empty( $request['whole_order'] ) ) {
				$order->update_status( 'cancelled', __( 'Cancellation approved by operations.', 'consucorner' ) );
			} elseif ( $refund_amount > 0 && function_exists( 'wc_create_refund' ) ) {
				$refund = wc_create_refund(
					array(
						'order_id'       => $order->get_id(),
						'amount'         => wc_format_decimal( $refund_amount ),
						'reason'         => __( 'Cancellation approved by operations.', 'consucorner' ),
						'line_items'     => $line_items,
						'refund_payment' => false,
						'restock_items'  => true,
					)
				);
				if ( is_wp_error( $refund ) ) {
					return $refund;
				}
				$order = wc_get_order( $order->get_id() );
				if ( ! $order ) {
					return new WP_Error( 'cc_cancel_order', __( 'Order not found after refund.', 'consucorner' ) );
				}
			}

			if ( class_exists( 'Consucorner_Order_Return_Workflow' ) ) {
				foreach ( array_keys( $vendors ) as $vendor_id ) {
					Consucorner_Order_Return_Workflow::update_fulfillment_status(
						$order->get_id(),
						absint( $vendor_id ),
						'cancelled',
						$note ? $note : __( 'Cancellation approved by operations.', 'consucorner' )
					);
				}
			}

			$remaining_qty = 0;
			foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				$remaining_qty += max( 0, (float) $item->get_quantity() - abs( (float) $order->get_qty_refunded_for_item( $item_id ) ) );
			}
			if ( $remaining_qty <= 0 && ! $order->has_status( 'cancelled' ) ) {
				$order->update_status( 'cancelled', __( 'All items cancelled.', 'consucorner' ) );
			}

			$result = self::set_request_status( $order, $request_id, 'approved', $note );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			self::notify_customer_reviewed( $order, $request_id, 'approved' );

			return $result;
		}

		/**
		 * Update request status and persist.
		 *
		 * @param WC_Order $order Order.
		 * @param string   $request_id Request ID.
		 * @param string   $status Status.
		 * @param string   $note Note.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function set_request_status( $order, $request_id, $status, $note = '' ) {
			$all = self::get_requests( $order );
			$key = self::resolve_request_storage_key( $all, $request_id );
			if ( '' === $key || empty( $all[ $key ] ) || ! is_array( $all[ $key ] ) ) {
				return new WP_Error( 'cc_cancel_missing', __( 'Cancellation request not found.', 'consucorner' ) );
			}

			$history   = isset( $all[ $key ]['history'] ) && is_array( $all[ $key ]['history'] ) ? $all[ $key ]['history'] : array();
			$history[] = array(
				'status'     => sanitize_key( $status ),
				'note'       => sanitize_textarea_field( $note ),
				'changed_at' => current_time( 'mysql' ),
				'changed_by' => get_current_user_id(),
			);

			$all[ $key ]['status']     = sanitize_key( $status );
			$all[ $key ]['updated_at'] = current_time( 'mysql' );
			$all[ $key ]['history']    = array_slice( $history, -50 );

			$order->update_meta_data( self::META_REQUESTS, $all );
			$order->add_order_note(
				sprintf(
					/* translators: 1: request id, 2: status */
					__( 'Cancellation request %1$s marked as %2$s.', 'consucorner' ),
					$key,
					sanitize_key( $status )
				)
			);
			$order->save();
			self::clear_pending_count_cache();

			if ( 'rejected' === $status ) {
				self::notify_customer_reviewed( $order, $request_id, 'rejected' );
			}

			return $all[ $key ];
		}

		/**
		 * Notify customer, vendors, and ops when a cancellation request is created.
		 *
		 * @param WC_Order            $order Order.
		 * @param array<string,mixed> $request Request.
		 */
		private static function notify_cancel_request_created( $order, $request ) {
			$order_number = $order->get_order_number();
			$reason       = trim( (string) ( $request['reason'] ?? '' ) );
			$reason_line  = $reason
				? sprintf(
					/* translators: %s: customer reason */
					__( "Reason: %s\n\n", 'consucorner' ),
					$reason
				)
				: '';

			$customer_subject = sprintf(
				/* translators: %s: order number */
				__( 'We received your cancellation request for order #%s', 'consucorner' ),
				$order_number
			);
			$customer_message = sprintf(
				/* translators: 1: order number, 2: optional reason line */
				__( "Hi,\n\nWe received your cancellation request for order #%1\$s.\n\n%2\$sOur operations team will review it shortly and email you when it is approved or rejected.\n\nThank you,\nConsuCorner", 'consucorner' ),
				$order_number,
				$reason_line
			);

			$ops_subject = sprintf(
				/* translators: %s: order number */
				__( 'Cancellation requested for order #%s', 'consucorner' ),
				$order_number
			);
			$ops_message = sprintf(
				/* translators: 1: order number, 2: optional reason line */
				__( "A customer requested cancellation for order #%1\$s.\n\n%2\$sReview it in WooCommerce → Returns (Operations order workflow).\n\nConsuCorner", 'consucorner' ),
				$order_number,
				$reason_line
			);

			$customer_email = $order->get_billing_email();
			if ( $customer_email ) {
				wp_mail( $customer_email, $customer_subject, $customer_message );
			}

			foreach ( self::get_ops_notification_emails() as $ops_email ) {
				wp_mail( $ops_email, $ops_subject, $ops_message );
			}

			$vendor_ids = array();
			foreach ( (array) ( $request['items'] ?? array() ) as $item_id => $qty ) {
				unset( $qty );
				$item = $order->get_item( absint( $item_id ) );
				if ( ! $item instanceof WC_Order_Item_Product || ! class_exists( 'CC_Returns_Refund_Service' ) ) {
					continue;
				}
				$vendor_ids[] = CC_Returns_Refund_Service::get_item_vendor_id( $item, $order );
			}

			foreach ( array_unique( array_filter( array_map( 'absint', $vendor_ids ) ) ) as $vendor_id ) {
				$vendor = get_userdata( $vendor_id );
				if ( $vendor && $vendor->user_email ) {
					wp_mail( $vendor->user_email, $ops_subject, $ops_message );
				}
			}
		}

		/**
		 * Operations notification email addresses.
		 *
		 * @return string[]
		 */
		private static function get_ops_notification_emails() {
			$emails = array( (string) get_option( 'admin_email' ) );
			/**
			 * Filter cancellation/return ops notification recipients.
			 *
			 * @param string[] $emails Email addresses.
			 */
			$emails = apply_filters( 'consucorner_ops_notification_emails', $emails );
			$clean  = array();
			foreach ( (array) $emails as $email ) {
				$email = sanitize_email( (string) $email );
				if ( $email && is_email( $email ) ) {
					$clean[] = $email;
				}
			}
			return array_values( array_unique( $clean ) );
		}

		/**
		 * Notify customer (and vendors) when operations reviews a request.
		 *
		 * @param WC_Order $order Order.
		 * @param string   $request_id Request ID.
		 * @param string   $decision approved|rejected.
		 */
		private static function notify_customer_reviewed( $order, $request_id, $decision ) {
			$email = $order->get_billing_email();
			$decision_label = 'approved' === $decision
				? __( 'Approved', 'consucorner' )
				: __( 'Rejected', 'consucorner' );

			$subject = sprintf(
				/* translators: 1: order number, 2: decision */
				__( 'Cancellation update for order #%1$s: %2$s', 'consucorner' ),
				$order->get_order_number(),
				$decision_label
			);
			$message = sprintf(
				/* translators: 1: order number, 2: decision */
				__( "Hi,\n\nYour cancellation request for order #%1\$s was %2\$s by operations.\n\nThank you,\nConsuCorner", 'consucorner' ),
				$order->get_order_number(),
				strtolower( $decision_label )
			);

			if ( $email ) {
				wp_mail( $email, $subject, $message );
			}

			$request = self::get_request( $order, $request_id );
			if ( ! is_array( $request ) ) {
				return;
			}

			$vendor_ids = array();
			foreach ( (array) ( $request['items'] ?? array() ) as $item_id => $qty ) {
				unset( $qty );
				$item = $order->get_item( absint( $item_id ) );
				if ( ! $item instanceof WC_Order_Item_Product || ! class_exists( 'CC_Returns_Refund_Service' ) ) {
					continue;
				}
				$vendor_ids[] = CC_Returns_Refund_Service::get_item_vendor_id( $item, $order );
			}

			$vendor_message = sprintf(
				/* translators: 1: order number, 2: decision */
				__( "Cancellation request for order #%1\$s was %2\$s by operations.\n\nConsuCorner", 'consucorner' ),
				$order->get_order_number(),
				strtolower( $decision_label )
			);

			foreach ( array_unique( array_filter( array_map( 'absint', $vendor_ids ) ) ) as $vendor_id ) {
				$vendor = get_userdata( $vendor_id );
				if ( $vendor && $vendor->user_email ) {
					wp_mail( $vendor->user_email, $subject, $vendor_message );
				}
			}
		}
	}

	Consucorner_Order_Cancel_Requests::init();

endif;
