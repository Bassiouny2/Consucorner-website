<?php
/**
 * Operations-owned order fulfillment and return workflow helpers.
 *
 * @package Consucorner
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Consucorner_Order_Return_Workflow' ) ) :

	/**
	 * Keep fulfillment/return workflow state in theme-owned order meta.
	 */
	final class Consucorner_Order_Return_Workflow {

		const META_FULFILLMENT     = '_cc_ops_fulfillment';
		const META_RETURN_FLOW     = '_cc_return_workflow';
		const META_BOSTA_SYNC_FP   = '_cc_bosta_fulfillment_sync_fp';

		/**
		 * Prevent recursive sync when fulfillment saves re-trigger order save.
		 *
		 * @var bool
		 */
		private static $bosta_sync_running = false;

		/**
		 * Register frontend/vendor hooks.
		 */
		public static function init() {
			add_action( 'woocommerce_after_order_object_save', array( __CLASS__, 'maybe_sync_fulfillment_from_bosta' ), 50, 1 );
			add_action( 'dokan_order_listing_header_before_action_column', array( __CLASS__, 'render_vendor_order_header' ) );
			add_action( 'dokan_order_listing_row_before_action_field', array( __CLASS__, 'render_vendor_order_row' ) );
			add_action( 'dokan_order_detail_after_order_items', array( __CLASS__, 'render_vendor_order_detail' ), 25 );
			add_action( 'dokan_rma_single_request_content_inside_before', array( __CLASS__, 'render_vendor_rma_readonly_notice' ), 1 );
			add_action( 'wp_footer', array( __CLASS__, 'render_vendor_readonly_styles' ), 99 );
			add_action( 'woocommerce_account_request-warranty_endpoint', array( __CLASS__, 'gate_customer_return_endpoint' ), 1 );
			add_action( 'template_redirect', array( __CLASS__, 'gate_customer_return_submit' ), 5 );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_vendor_orders_bosta_column' ), 40 );
			add_filter( 'dokan_rest_prepare_shop_order_object', array( __CLASS__, 'rest_prepare_vendor_order_bosta_fields' ), 20, 3 );
		}

		/**
		 * Fulfillment status labels.
		 *
		 * @return array<string,string>
		 */
		public static function fulfillment_statuses() {
			return array(
				'confirmed'        => __( 'Confirmed', 'consucorner' ),
				'preparing'        => __( 'Preparing', 'consucorner' ),
				'shipped'          => __( 'Shipped', 'consucorner' ),
				'out_for_delivery' => __( 'Out for delivery', 'consucorner' ),
				'delivered'        => __( 'Delivered', 'consucorner' ),
				'cancelled'        => __( 'Cancelled', 'consucorner' ),
			);
		}

		/**
		 * Normalize legacy fulfillment keys (Ordered was removed; maps to Confirmed).
		 *
		 * @param string $status Raw status.
		 * @return string
		 */
		public static function normalize_fulfillment_status( $status ) {
			$status = sanitize_key( (string) $status );
			if ( 'ordered' === $status || '' === $status ) {
				return 'confirmed';
			}

			return $status;
		}

		/**
		 * Return workflow labels.
		 *
		 * @return array<string,string>
		 */
		public static function return_statuses() {
			return array(
				'requested'         => __( 'Requested', 'consucorner' ),
				'reviewing'         => __( 'Reviewing', 'consucorner' ),
				'approved'          => __( 'Approved', 'consucorner' ),
				'return_in_transit' => __( 'Return in transit', 'consucorner' ),
				'received'          => __( 'Received', 'consucorner' ),
				'resolved'          => __( 'Resolved', 'consucorner' ),
				'rejected'          => __( 'Rejected', 'consucorner' ),
			);
		}

		/**
		 * Return statuses that allow a customer/admin to start a return.
		 *
		 * @return string[]
		 */
		public static function return_eligible_fulfillment_statuses() {
			return array( 'shipped', 'out_for_delivery', 'delivered' );
		}

		/**
		 * Validate status transition.
		 *
		 * @param string $from Existing status.
		 * @param string $to New status.
		 * @return bool
		 */
		public static function can_transition_fulfillment( $from, $to ) {
			$from = self::normalize_fulfillment_status( $from );
			$to   = self::normalize_fulfillment_status( $to );

			if ( $from === $to ) {
				return true;
			}

			$allowed = array(
				'confirmed'        => array( 'preparing', 'cancelled' ),
				'preparing'        => array( 'shipped', 'cancelled' ),
				'shipped'          => array( 'out_for_delivery', 'delivered' ),
				'out_for_delivery' => array( 'delivered' ),
				'delivered'        => array(),
				'cancelled'        => array(),
			);

			return isset( $allowed[ $from ] ) && in_array( $to, $allowed[ $from ], true );
		}

		/**
		 * Human label for fulfillment status.
		 *
		 * @param string $status Status key.
		 * @return string
		 */
		public static function get_fulfillment_label( $status ) {
			$status  = self::normalize_fulfillment_status( $status );
			$options = self::fulfillment_statuses();
			return isset( $options[ $status ] ) ? $options[ $status ] : $status;
		}

		/**
		 * Customer-facing label for fulfillment status.
		 *
		 * @param string $status Status key.
		 * @return string
		 */
		public static function get_customer_status_label( $status ) {
			$status = self::normalize_fulfillment_status( $status );
			if ( 'delivered' === $status ) {
				return __( 'Completed', 'consucorner' );
			}
			if ( 'cancelled' === $status ) {
				return __( 'Cancelled', 'consucorner' );
			}

			return self::get_fulfillment_label( $status );
		}

		/**
		 * Aggregate fulfillment state for customer display.
		 *
		 * @param WC_Order $order Order.
		 * @return array{status:string,label:string}
		 */
		public static function get_customer_fulfillment_summary( $order ) {
			$summary = self::get_order_fulfillment_summary( $order );
			$status  = isset( $summary['status'] ) ? self::normalize_fulfillment_status( (string) $summary['status'] ) : 'confirmed';

			return array(
				'status' => $status,
				'label'  => self::get_customer_status_label( $status ),
			);
		}

		/**
		 * Return statuses that allow customer self-service returns.
		 *
		 * Customers may request a return only after the product was received
		 * (Delivered). Before that they should cancel instead.
		 *
		 * @return string[]
		 */
		public static function customer_return_eligible_fulfillment_statuses() {
			return array( 'delivered' );
		}

		/**
		 * Fulfillment statuses that allow cancellation requests.
		 *
		 * Cancel is allowed until the order is delivered. After delivery,
		 * customers use the return flow instead.
		 *
		 * @return string[]
		 */
		public static function cancel_eligible_fulfillment_statuses() {
			return array( 'confirmed', 'preparing', 'shipped', 'out_for_delivery' );
		}

		/**
		 * Human label for return workflow status.
		 *
		 * @param string $status Status key.
		 * @return string
		 */
		public static function get_return_label( $status ) {
			$options = self::return_statuses();
			return isset( $options[ $status ] ) ? $options[ $status ] : $status;
		}

		/**
		 * Vendor IDs present in an order.
		 *
		 * @param WC_Order $order Order.
		 * @return int[]
		 */
		public static function get_order_vendor_ids( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return array();
			}

			$ids = array();
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				if ( class_exists( 'CC_Returns_Refund_Service' ) ) {
					$vendor_id = CC_Returns_Refund_Service::get_item_vendor_id( $item, $order );
				} else {
					$product   = $item instanceof WC_Order_Item_Product ? $item->get_product() : false;
					$product_id = $product ? ( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id() ) : 0;
					$vendor_id = $product_id ? absint( get_post_field( 'post_author', $product_id ) ) : 0;
				}
				if ( $vendor_id ) {
					$ids[] = $vendor_id;
				}
			}

			$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
			if ( empty( $ids ) ) {
				$fallback = absint( $order->get_meta( '_dokan_vendor_id', true ) );
				if ( $fallback ) {
					$ids[] = $fallback;
				}
			}

			return $ids;
		}

		/**
		 * Read all fulfillment records from an order.
		 *
		 * @param WC_Order $order Order.
		 * @return array<string,array<string,mixed>>
		 */
		public static function get_fulfillment_records( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return array();
			}

			$records = $order->get_meta( self::META_FULFILLMENT, true );
			$records = is_array( $records ) ? $records : array();

			foreach ( self::get_order_vendor_ids( $order ) as $vendor_id ) {
				$key = (string) $vendor_id;
				if ( empty( $records[ $key ] ) || ! is_array( $records[ $key ] ) ) {
					$records[ $key ] = self::default_fulfillment_record();
					continue;
				}

				$status = self::normalize_fulfillment_status( (string) ( $records[ $key ]['status'] ?? 'confirmed' ) );
				$records[ $key ]['status'] = $status;
				$records[ $key ]['label']  = self::get_fulfillment_label( $status );
			}

			return $records;
		}

		/**
		 * Get one vendor fulfillment record.
		 *
		 * @param WC_Order $order Order.
		 * @param int      $vendor_id Vendor ID.
		 * @return array<string,mixed>
		 */
		public static function get_vendor_fulfillment( $order, $vendor_id ) {
			$records = self::get_fulfillment_records( $order );
			$key     = (string) absint( $vendor_id );
			return isset( $records[ $key ] ) && is_array( $records[ $key ] ) ? $records[ $key ] : self::default_fulfillment_record();
		}

		/**
		 * Aggregate fulfillment state for an order.
		 *
		 * @param WC_Order $order Order.
		 * @return array{status:string,label:string}
		 */
		public static function get_order_fulfillment_summary( $order ) {
			$records = self::get_fulfillment_records( $order );
			if ( empty( $records ) ) {
				return array( 'status' => 'confirmed', 'label' => self::get_fulfillment_label( 'confirmed' ) );
			}

			$rank = array(
				'cancelled'        => -1,
				'confirmed'        => 1,
				'preparing'        => 2,
				'shipped'          => 3,
				'out_for_delivery' => 4,
				'delivered'        => 5,
			);

			$lowest = 'delivered';
			foreach ( $records as $record ) {
				$status = self::normalize_fulfillment_status( (string) ( $record['status'] ?? 'confirmed' ) );
				if ( ( $rank[ $status ] ?? 1 ) < ( $rank[ $lowest ] ?? 5 ) ) {
					$lowest = $status;
				}
			}

			return array( 'status' => $lowest, 'label' => self::get_fulfillment_label( $lowest ) );
		}

		/**
		 * Update one vendor fulfillment status.
		 *
		 * @param int    $order_id Order ID.
		 * @param int    $vendor_id Vendor ID.
		 * @param string $status New status.
		 * @param string $note Optional operations note.
		 * @param string $source Change source: operations|bosta.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function update_fulfillment_status( $order_id, $vendor_id, $status, $note = '', $source = 'operations' ) {
			$order = self::resolve_order( $order_id );
			if ( ! $order ) {
				return new WP_Error( 'cc_workflow_order', __( 'Order not found.', 'consucorner' ) );
			}
			$order_id = $order->get_id();

			$vendor_id = absint( $vendor_id );
			if ( ! $vendor_id || ! in_array( $vendor_id, self::get_order_vendor_ids( $order ), true ) ) {
				return new WP_Error( 'cc_workflow_vendor', __( 'Vendor is not part of this order.', 'consucorner' ) );
			}

			$status = self::normalize_fulfillment_status( $status );
			if ( ! isset( self::fulfillment_statuses()[ $status ] ) ) {
				return new WP_Error( 'cc_workflow_status', __( 'Invalid fulfillment status.', 'consucorner' ) );
			}

			$records = self::get_fulfillment_records( $order );
			$key     = (string) $vendor_id;
			$current = isset( $records[ $key ] ) && is_array( $records[ $key ] ) ? $records[ $key ] : self::default_fulfillment_record();
			$from    = self::normalize_fulfillment_status( (string) ( $current['status'] ?? 'confirmed' ) );

			if ( ! self::can_transition_fulfillment( $from, $status ) ) {
				return new WP_Error(
					'cc_workflow_transition',
					sprintf(
						/* translators: 1: old status, 2: new status */
						__( 'Cannot move fulfillment from %1$s to %2$s.', 'consucorner' ),
						self::get_fulfillment_label( $from ),
						self::get_fulfillment_label( $status )
					)
				);
			}

			$source = sanitize_key( $source );
			if ( ! in_array( $source, array( 'operations', 'bosta' ), true ) ) {
				$source = 'operations';
			}

			$entry = array(
				'from'       => $from,
				'to'         => $status,
				'note'       => sanitize_textarea_field( $note ),
				'changed_at' => current_time( 'mysql' ),
				'changed_by' => 'bosta' === $source ? 0 : get_current_user_id(),
				'source'     => $source,
			);

			$history = isset( $current['history'] ) && is_array( $current['history'] ) ? $current['history'] : array();
			$history[] = $entry;

			$records[ $key ] = array(
				'status'     => $status,
				'label'      => self::get_fulfillment_label( $status ),
				'updated_at' => current_time( 'mysql' ),
				'updated_by' => 'bosta' === $source ? 0 : get_current_user_id(),
				'source'     => $source,
				'history'    => array_slice( $history, -50 ),
			);

			self::$bosta_sync_running = true;
			$order->update_meta_data( self::META_FULFILLMENT, $records );
			if ( 'bosta' === $source ) {
				$order->add_order_note(
					sprintf(
						/* translators: 1: vendor id, 2: old status, 3: new status, 4: note */
						__( 'Bosta sync updated vendor #%1$d fulfillment from %2$s to %3$s. %4$s', 'consucorner' ),
						$vendor_id,
						self::get_fulfillment_label( $from ),
						self::get_fulfillment_label( $status ),
						$note ? $note : ''
					)
				);
			} else {
				$order->add_order_note(
					sprintf(
						/* translators: 1: vendor id, 2: old status, 3: new status */
						__( 'Operations updated vendor #%1$d fulfillment from %2$s to %3$s.', 'consucorner' ),
						$vendor_id,
						self::get_fulfillment_label( $from ),
						self::get_fulfillment_label( $status )
					)
				);
			}
			$order->save();
			self::$bosta_sync_running = false;

			return $records[ $key ];
		}

		/**
		 * Whether an order can start a return under operations fulfillment rules.
		 *
		 * @param WC_Order $order Order.
		 * @return bool
		 */
		public static function order_has_return_eligible_fulfillment( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return false;
			}

			$fulfillment_order = self::resolve_fulfillment_order( $order );
			if ( ! $fulfillment_order ) {
				$fulfillment_order = $order;
			}

			foreach ( self::get_fulfillment_records( $fulfillment_order ) as $record ) {
				$status = self::normalize_fulfillment_status( (string) ( $record['status'] ?? 'confirmed' ) );
				if ( in_array( $status, self::return_eligible_fulfillment_statuses(), true ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Whether a customer can self-initiate a return.
		 *
		 * @param WC_Order $order Order.
		 * @return bool
		 */
		public static function order_has_customer_return_eligible_fulfillment( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return false;
			}

			$fulfillment_order = self::resolve_fulfillment_order( $order );
			if ( ! $fulfillment_order ) {
				$fulfillment_order = $order;
			}

			foreach ( self::get_fulfillment_records( $fulfillment_order ) as $record ) {
				$status = self::normalize_fulfillment_status( (string) ( $record['status'] ?? 'confirmed' ) );
				if ( in_array( $status, self::customer_return_eligible_fulfillment_statuses(), true ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Whether any vendor line is still cancellable.
		 *
		 * @param WC_Order $order Order.
		 * @return bool
		 */
		public static function order_has_cancel_eligible_fulfillment( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return false;
			}

			$fulfillment_order = self::resolve_fulfillment_order( $order );
			if ( ! $fulfillment_order ) {
				$fulfillment_order = $order;
			}

			foreach ( self::get_fulfillment_records( $fulfillment_order ) as $record ) {
				$status = self::normalize_fulfillment_status( (string) ( $record['status'] ?? 'confirmed' ) );
				if ( in_array( $status, self::cancel_eligible_fulfillment_statuses(), true ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Whether a vendor item is cancellable under fulfillment rules.
		 *
		 * @param WC_Order $order Order.
		 * @param int      $vendor_id Vendor ID.
		 * @return bool
		 */
		public static function vendor_is_cancel_eligible( $order, $vendor_id ) {
			if ( ! $order instanceof WC_Order ) {
				return false;
			}

			// WooCommerce completed/cancelled orders are not cancellable online.
			if ( $order->has_status( array( 'completed', 'cancelled', 'refunded', 'failed' ) ) ) {
				return false;
			}

			$fulfillment_order = self::resolve_fulfillment_order( $order );
			if ( ! $fulfillment_order ) {
				$fulfillment_order = $order;
			}

			$record = self::get_vendor_fulfillment( $fulfillment_order, $vendor_id );
			$status = self::normalize_fulfillment_status( (string) ( $record['status'] ?? 'confirmed' ) );

			return in_array( $status, self::cancel_eligible_fulfillment_statuses(), true );
		}

		/**
		 * Whether the customer may open a self-service return for this order.
		 *
		 * Allowed when fulfillment is Delivered, or WooCommerce status is Completed.
		 *
		 * @param WC_Order $order Order.
		 * @return bool
		 */
		public static function order_allows_customer_return( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return false;
			}

			if ( $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
				return false;
			}

			if ( $order->has_status( 'completed' ) ) {
				return true;
			}

			return self::order_has_customer_return_eligible_fulfillment( $order );
		}

		/**
		 * Build operations lookup data for an order.
		 *
		 * @param int $order_id Order ID.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function get_order_ops_payload( $order_id ) {
			$order = self::resolve_order( $order_id );
			if ( ! $order ) {
				return new WP_Error( 'cc_workflow_order', __( 'Order not found.', 'consucorner' ) );
			}

			self::maybe_sync_fulfillment_from_bosta( $order );

			$fulfillment_order = self::resolve_fulfillment_order( $order );
			if ( ! $fulfillment_order ) {
				$fulfillment_order = $order;
			}

			$vendors = array();
			foreach ( self::get_order_vendor_ids( $fulfillment_order ) as $vendor_id ) {
				$user      = get_userdata( $vendor_id );
				$record    = self::get_vendor_fulfillment( $fulfillment_order, $vendor_id );
				$vendors[] = array(
					'id'         => $vendor_id,
					'name'       => $user ? $user->display_name : sprintf( __( 'Vendor #%d', 'consucorner' ), $vendor_id ),
					'status'     => $record['status'],
					'label'      => self::get_fulfillment_label( (string) $record['status'] ),
					'updated_at' => isset( $record['updated_at'] ) ? (string) $record['updated_at'] : '',
				);
			}

			$items = array();
			foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}

				$vendor_id = class_exists( 'CC_Returns_Refund_Service' ) ? CC_Returns_Refund_Service::get_item_vendor_id( $item, $order ) : 0;
				$block     = class_exists( 'CC_Returns_Refund_Service' ) ? CC_Returns_Refund_Service::get_item_block_reason( $order, (int) $item_id ) : '';
				$qty       = (float) $item->get_quantity();
				$refunded  = abs( (float) $order->get_qty_refunded_for_item( $item_id ) );
				$max_qty   = max( 0, $qty - $refunded );

				$items[] = array(
					'item_id'   => (int) $item_id,
					'vendor_id' => $vendor_id,
					'name'      => $item->get_name(),
					'qty'       => $qty,
					'max_qty'   => $max_qty,
					'blocked'   => $block,
				);
			}

			$summary = self::get_order_fulfillment_summary( $fulfillment_order );
			$bosta   = self::get_order_bosta_snapshot( $order );

			return array(
				'id'                  => $fulfillment_order->get_id(),
				'number'              => $order->get_order_number(),
				'customer_id'         => $order->get_customer_id(),
				'customer_name'       => trim( $order->get_formatted_billing_full_name() ),
				'wc_status'           => $order->get_status(),
				'wc_status_label'     => wc_get_order_status_name( $order->get_status() ),
				'fulfillment_status'  => $summary['status'],
				'fulfillment_label'   => $summary['label'],
				'return_eligible'     => self::order_has_return_eligible_fulfillment( $fulfillment_order ),
				'bosta'               => $bosta,
				'vendors'             => $vendors,
				'items'               => $items,
				'cancel_requests'     => class_exists( 'Consucorner_Order_Cancel_Requests' )
					? Consucorner_Order_Cancel_Requests::get_ops_payload( $order )
					: array(),
			);
		}

		/**
		 * Create vendor-split manual RMA requests.
		 *
		 * @param int                  $order_id Order ID.
		 * @param array<int,int>       $item_quantities item_id => qty.
		 * @param array<string,string> $args Request args.
		 * @return array<int,array<string,mixed>>|WP_Error
		 */
		public static function create_manual_return_requests( $order_id, $item_quantities, $args = array() ) {
			$order = self::resolve_order( $order_id );
			if ( ! $order ) {
				return new WP_Error( 'cc_return_order', __( 'Order not found.', 'consucorner' ) );
			}
			if ( ! function_exists( 'dokan_save_warranty_request' ) ) {
				return new WP_Error( 'cc_return_dokan', __( 'Dokan RMA is not available.', 'consucorner' ) );
			}
			if ( ! self::order_has_return_eligible_fulfillment( $order ) ) {
				return new WP_Error( 'cc_return_fulfillment', __( 'Returns can be created after operations marks the order as shipped or later.', 'consucorner' ) );
			}

			if ( class_exists( 'Consucorner_Returns_Rma_Config' ) ) {
				Consucorner_Returns_Rma_Config::ensure_order_ready_for_returns( $order );
			}

			$grouped = array();
			foreach ( (array) $item_quantities as $item_id => $qty ) {
				$item_id = absint( $item_id );
				$qty     = wc_stock_amount( $qty );
				if ( ! $item_id || $qty < 1 ) {
					continue;
				}
				$item = $order->get_item( $item_id );
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				$block = class_exists( 'CC_Returns_Refund_Service' ) ? CC_Returns_Refund_Service::get_item_block_reason( $order, $item_id ) : '';
				if ( $block ) {
					return new WP_Error( 'cc_return_blocked', $block );
				}

				$line_qty = (float) $item->get_quantity();
				if ( $qty > $line_qty ) {
					return new WP_Error( 'cc_return_qty', __( 'Return quantity exceeds ordered quantity.', 'consucorner' ) );
				}

				$vendor_id = class_exists( 'CC_Returns_Refund_Service' ) ? CC_Returns_Refund_Service::get_item_vendor_id( $item, $order ) : 0;
				if ( ! $vendor_id ) {
					return new WP_Error( 'cc_return_vendor', __( 'Could not resolve item vendor.', 'consucorner' ) );
				}

				$record = self::get_vendor_fulfillment( $order, $vendor_id );
				if ( ! in_array( sanitize_key( (string) $record['status'] ), self::return_eligible_fulfillment_statuses(), true ) ) {
					return new WP_Error( 'cc_return_vendor_status', __( 'Selected vendor items are not marked shipped yet.', 'consucorner' ) );
				}

				$product = $item->get_product();
				$grouped[ $vendor_id ][] = array(
					'product_id' => $product ? $product->get_id() : $item->get_product_id(),
					'quantity'   => $qty,
					'item_id'    => $item_id,
				);
			}

			if ( empty( $grouped ) ) {
				return new WP_Error( 'cc_return_empty', __( 'Select at least one returnable item.', 'consucorner' ) );
			}

			$created = array();
			$reason  = isset( $args['reason'] ) ? sanitize_key( (string) $args['reason'] ) : 'not_needed';
			$type    = isset( $args['type'] ) ? sanitize_key( (string) $args['type'] ) : 'refund';
			$details = isset( $args['details'] ) ? sanitize_textarea_field( (string) $args['details'] ) : '';
			$note    = isset( $args['note'] ) ? sanitize_textarea_field( (string) $args['note'] ) : '';

			foreach ( $grouped as $vendor_id => $items ) {
				$data = array(
					'order_id'    => $order->get_id(),
					'vendor_id'   => absint( $vendor_id ),
					'customer_id' => absint( $order->get_customer_id() ),
					'type'        => $type,
					'status'      => 'new',
					'reasons'     => $reason,
					'details'     => $details,
					'note'        => $note,
					'items'       => $items,
				);

				$result = dokan_save_warranty_request( $data );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				if ( ! $result ) {
					return new WP_Error( 'cc_return_create', __( 'Could not create a return request.', 'consucorner' ) );
				}

				$request_id = self::find_recent_request_id( $data );
				if ( $request_id ) {
					self::save_return_workflow_status( $order, $request_id, 'requested', __( 'Created by operations.', 'consucorner' ) );
					$created[] = array(
						'request_id' => $request_id,
						'vendor_id'  => absint( $vendor_id ),
					);
				}

				do_action( 'dokan_rma_send_warranty_request', $data );
			}

			$user = wp_get_current_user();
			$order->update_meta_data( '_cc_has_return_request', 'yes' );
			$order->update_meta_data( '_cc_return_last_status', 'new' );
			$order->add_order_note(
				sprintf(
					/* translators: 1: admin name, 2: request count */
					__( 'Operations user %1$s created %2$d manual vendor return request(s).', 'consucorner' ),
					$user && $user->exists() ? $user->display_name : __( 'Unknown', 'consucorner' ),
					count( $created )
				)
			);
			$order->save();

			self::notify_manual_return_created( $order, $created );

			return $created;
		}

		/**
		 * Save additional return workflow status for a request.
		 *
		 * @param WC_Order $order Order.
		 * @param int      $request_id Request ID.
		 * @param string   $status Workflow status.
		 * @param string   $note Optional note.
		 */
		public static function save_return_workflow_status( $order, $request_id, $status, $note = '' ) {
			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$status = sanitize_key( $status );
			if ( ! isset( self::return_statuses()[ $status ] ) ) {
				return;
			}

			$all = $order->get_meta( self::META_RETURN_FLOW, true );
			$all = is_array( $all ) ? $all : array();
			$key = (string) absint( $request_id );
			$old = isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : array();

			$history = isset( $old['history'] ) && is_array( $old['history'] ) ? $old['history'] : array();
			$history[] = array(
				'status'     => $status,
				'note'       => sanitize_textarea_field( $note ),
				'changed_at' => current_time( 'mysql' ),
				'changed_by' => get_current_user_id(),
			);

			$all[ $key ] = array(
				'status'     => $status,
				'label'      => self::get_return_label( $status ),
				'updated_at' => current_time( 'mysql' ),
				'updated_by' => get_current_user_id(),
				'history'    => array_slice( $history, -50 ),
			);

			$order->update_meta_data( self::META_RETURN_FLOW, $all );
			$order->update_meta_data( '_cc_has_return_request', 'yes' );
			$order->update_meta_data( '_cc_return_last_status', $status );
			$order->save();
		}

		/**
		 * Get workflow record for a request.
		 *
		 * @param int $order_id Order ID.
		 * @param int $request_id Request ID.
		 * @return array<string,mixed>
		 */
		public static function get_return_workflow( $order_id, $request_id ) {
			$order = wc_get_order( absint( $order_id ) );
			if ( ! $order ) {
				return array();
			}
			$all = $order->get_meta( self::META_RETURN_FLOW, true );
			$all = is_array( $all ) ? $all : array();
			$key = (string) absint( $request_id );
			return isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : array();
		}

		/**
		 * Update a return workflow and mirror safe Dokan status.
		 *
		 * @param int    $request_id Request ID.
		 * @param string $status Workflow status.
		 * @param string $note Optional note.
		 * @return array<string,mixed>|WP_Error
		 */
		/**
		 * Resolve workflow status from stored meta or Dokan status.
		 *
		 * @param array<string,mixed> $workflow_record Workflow meta.
		 * @param string              $dokan_status Dokan RMA status.
		 * @return string
		 */
		public static function resolve_workflow_status( $workflow_record, $dokan_status = '' ) {
			if ( ! empty( $workflow_record['status'] ) ) {
				return sanitize_key( (string) $workflow_record['status'] );
			}

			$dokan_status = sanitize_key( (string) $dokan_status );
			$map          = array(
				'new'        => 'requested',
				'reviewing'  => 'reviewing',
				'processing' => 'approved',
				'completed'  => 'resolved',
				'rejected'   => 'rejected',
			);

			return isset( $map[ $dokan_status ] ) ? $map[ $dokan_status ] : 'requested';
		}

		/**
		 * Allowed next workflow actions for operations.
		 *
		 * @param string $current_status Workflow status.
		 * @return string[]
		 */
		public static function get_return_workflow_actions( $current_status ) {
			$current_status = sanitize_key( $current_status );
			$allowed        = array(
				'requested'         => array( 'reviewing', 'rejected' ),
				'reviewing'         => array( 'approved', 'rejected' ),
				'approved'          => array( 'return_in_transit', 'rejected' ),
				'return_in_transit' => array( 'received', 'rejected' ),
				'received'          => array(),
				'resolved'          => array(),
				'rejected'          => array(),
			);

			return isset( $allowed[ $current_status ] ) ? $allowed[ $current_status ] : array();
		}

		/**
		 * Validate return workflow transition.
		 *
		 * @param string $from Existing status.
		 * @param string $to New status.
		 * @return bool
		 */
		public static function can_transition_return( $from, $to ) {
			$from = sanitize_key( $from );
			$to   = sanitize_key( $to );

			if ( $from === $to ) {
				return true;
			}

			return in_array( $to, self::get_return_workflow_actions( $from ), true );
		}

		/**
		 * Stamp workflow when a customer submits a Dokan RMA request.
		 *
		 * @param array<string,mixed> $data Request payload.
		 */
		public static function stamp_customer_return_requested( $data ) {
			$order_id = absint( $data['order_id'] ?? 0 );
			$order    = $order_id ? wc_get_order( $order_id ) : false;
			if ( ! $order ) {
				return;
			}

			$request_id = self::find_recent_request_id( $data );
			if ( $request_id ) {
				self::save_return_workflow_status( $order, $request_id, 'requested', __( 'Submitted by customer.', 'consucorner' ) );
			}
		}

		/**
		 * Block Dokan return form when operations fulfillment is not eligible.
		 */
		public static function gate_customer_return_endpoint() {
			$order_id = absint( get_query_var( 'request-warranty' ) );
			if ( ! $order_id ) {
				return;
			}

			$order = wc_get_order( $order_id );
			if ( ! $order || self::order_allows_customer_return( $order ) ) {
				return;
			}

			self::remove_dokan_warranty_endpoint_content();
			$message = self::order_has_cancel_eligible_fulfillment( $order )
				? __( 'This order is still in progress. Please cancel it instead of requesting a return.', 'consucorner' )
				: __( 'Returns are available after your order has been delivered.', 'consucorner' );
			wc_add_notice( $message, 'error' );
			wc_print_notices();
			printf(
				'<p><a class="button wc-backward" href="%s">%s</a></p>',
				esc_url( wc_get_account_endpoint_url( 'orders' ) ),
				esc_html__( 'Back to orders', 'consucorner' )
			);
		}

		/**
		 * Block customer POST submissions before Dokan saves the request.
		 */
		public static function gate_customer_return_submit() {
			if ( ! is_account_page() || ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' !== $_SERVER['REQUEST_METHOD'] ) ) {
				return;
			}
			if ( ! isset( $_POST['dokan_save_warranty_request_nonce'] ) ) {
				return;
			}

			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			if ( ! $order_id ) {
				return;
			}

			$order = wc_get_order( $order_id );
			if ( ! $order || self::order_allows_customer_return( $order ) ) {
				return;
			}

			$message = self::order_has_cancel_eligible_fulfillment( $order )
				? __( 'This order is still in progress. Please cancel it instead of requesting a return.', 'consucorner' )
				: __( 'Returns are available after your order has been delivered.', 'consucorner' );
			wc_add_notice( $message, 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
			exit;
		}

		public static function update_return_workflow_status( $request_id, $status, $note = '' ) {
			$request = class_exists( 'CC_Returns_Refund_Service' ) ? CC_Returns_Refund_Service::get_request( $request_id ) : null;
			if ( ! $request ) {
				return new WP_Error( 'cc_return_missing', __( 'Return request not found.', 'consucorner' ) );
			}

			$order = wc_get_order( absint( $request['order_id'] ?? 0 ) );
			if ( ! $order ) {
				return new WP_Error( 'cc_return_order', __( 'Order not found for this return.', 'consucorner' ) );
			}

			$status = sanitize_key( $status );
			if ( ! isset( self::return_statuses()[ $status ] ) ) {
				return new WP_Error( 'cc_return_status', __( 'Invalid return workflow status.', 'consucorner' ) );
			}

			$workflow = self::get_return_workflow( $order->get_id(), $request_id );
			$current  = self::resolve_workflow_status( $workflow, (string) ( $request['status'] ?? '' ) );
			if ( ! self::can_transition_return( $current, $status ) ) {
				return new WP_Error(
					'cc_return_transition',
					sprintf(
						/* translators: 1: old status, 2: new status */
						__( 'Cannot move return workflow from %1$s to %2$s.', 'consucorner' ),
						self::get_return_label( $current ),
						self::get_return_label( $status )
					)
				);
			}

			$dokan_status         = self::map_workflow_to_dokan_status( $status );
			$current_dokan_status = sanitize_key( (string) ( $request['status'] ?? '' ) );
			// Dokan returns an error when the DB row is unchanged; several workflow steps map to the same Dokan status.
			if ( $dokan_status && $dokan_status !== $current_dokan_status && function_exists( 'dokan_update_warranty_request_status' ) ) {
				$updated = dokan_update_warranty_request_status( absint( $request_id ), $dokan_status );
				if ( is_wp_error( $updated ) ) {
					return $updated;
				}
			}

			self::save_return_workflow_status( $order, $request_id, $status, $note );
			$order->add_order_note(
				sprintf(
					/* translators: 1: request id, 2: return status */
					__( 'Operations updated return request #%1$d to %2$s.', 'consucorner' ),
					absint( $request_id ),
					self::get_return_label( $status )
				)
			);
			$order->save();
			self::notify_return_status_changed( $order, $request, $status );

			return array(
				'request_id' => absint( $request_id ),
				'status'     => $status,
				'label'      => self::get_return_label( $status ),
			);
		}

		/**
		 * Latest open return status for an order.
		 *
		 * @param int $order_id Order ID.
		 * @return array<string,string>
		 */
		public static function get_order_return_summary( $order_id ) {
			if ( ! class_exists( 'CC_Returns_Refund_Service' ) ) {
				return array();
			}
			$requests = CC_Returns_Refund_Service::get_order_requests( $order_id );
			if ( empty( $requests ) ) {
				return array();
			}

			$latest = reset( $requests );
			$status = isset( $latest['status'] ) ? sanitize_key( (string) $latest['status'] ) : '';
			$workflow = self::get_return_workflow( $order_id, absint( $latest['id'] ?? 0 ) );
			if ( ! empty( $workflow['status'] ) ) {
				$status = sanitize_key( (string) $workflow['status'] );
			}

			return array(
				'status' => $status,
				'label'  => isset( self::return_statuses()[ $status ] ) ? self::get_return_label( $status ) : ( function_exists( 'dokan_warranty_request_status' ) ? dokan_warranty_request_status( $status ) : $status ),
			);
		}

		/**
		 * Vendor financial summary for Dokan order views.
		 *
		 * @param WC_Order $order Order (usually vendor sub-order).
		 * @param int      $vendor_id Vendor ID.
		 * @return array<string,mixed>
		 */
		public static function get_vendor_financial_summary( $order, $vendor_id ) {
			global $wpdb;

			if ( ! $order instanceof WC_Order ) {
				return array();
			}

			$vendor_id = absint( $vendor_id );
			$order_id  = $order->get_id();
			$table     = $wpdb->prefix . 'dokan_orders';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT order_total, net_amount FROM {$table} WHERE order_id = %d AND seller_id = %d LIMIT 1",
					$order_id,
					$vendor_id
				),
				ARRAY_A
			);

			$order_total = $row ? (float) $row['order_total'] : (float) $order->get_total();
			$net_amount  = $row ? (float) $row['net_amount'] : 0.0;
			$commission  = max( 0, $order_total - $net_amount );

			$deductions = self::get_vendor_return_deductions( $order_id, $vendor_id );
			$net_payable = max( 0, $net_amount );

			$items = array();
			foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}

				$item_vendor = class_exists( 'CC_Returns_Refund_Service' )
					? CC_Returns_Refund_Service::get_item_vendor_id( $item, $order )
					: 0;
				if ( $item_vendor !== $vendor_id ) {
					continue;
				}

				$items[] = array(
					'item_id' => (int) $item_id,
					'name'    => wp_strip_all_tags( $item->get_name() ),
					'qty'     => (float) $item->get_quantity(),
					'state'   => self::get_vendor_item_financial_state( $order, $item, $vendor_id ),
					'label'   => self::get_vendor_item_financial_label( self::get_vendor_item_financial_state( $order, $item, $vendor_id ) ),
				);
			}

			return array(
				'order_total'        => $order_total,
				'order_total_html'   => wc_price( $order_total, array( 'currency' => $order->get_currency() ) ),
				'commission'         => $commission,
				'commission_html'    => wc_price( $commission, array( 'currency' => $order->get_currency() ) ),
				'return_deductions'  => $deductions,
				'return_deductions_html' => wc_price( $deductions, array( 'currency' => $order->get_currency() ) ),
				'net_payable'        => $net_payable,
				'net_payable_html'   => wc_price( $net_payable, array( 'currency' => $order->get_currency() ) ),
				'items'              => $items,
			);
		}

		/**
		 * Sum vendor return deductions from Dokan balance ledger.
		 *
		 * @param int $order_id Order ID.
		 * @param int $vendor_id Vendor ID.
		 * @return float
		 */
		public static function get_vendor_return_deductions( $order_id, $vendor_id ) {
			global $wpdb;

			$table = $wpdb->prefix . 'dokan_vendor_balance';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists !== $table ) {
				return 0.0;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sum = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(credit) FROM {$table} WHERE trn_id = %d AND vendor_id = %d AND trn_type = %s",
					absint( $order_id ),
					absint( $vendor_id ),
					'dokan_refund'
				)
			);

			return max( 0, (float) $sum );
		}

		/**
		 * Financial state for a vendor line item.
		 *
		 * @param WC_Order            $order Order.
		 * @param WC_Order_Item_Product $item Line item.
		 * @param int                 $vendor_id Vendor ID.
		 * @return string pending|earned|deducted
		 */
		public static function get_vendor_item_financial_state( $order, $item, $vendor_id ) {
			$item_id = (int) $item->get_id();
			$refunded_qty = abs( (float) $order->get_qty_refunded_for_item( $item_id ) );
			if ( $refunded_qty > 0 ) {
				return 'deducted';
			}

			if ( class_exists( 'CC_Returns_Refund_Service' ) ) {
				$block = CC_Returns_Refund_Service::get_item_block_reason( $order, $item_id );
				if ( $block ) {
					return 'deducted';
				}
			}

			$fulfillment_order = self::resolve_fulfillment_order( $order );
			if ( ! $fulfillment_order ) {
				$fulfillment_order = $order;
			}
			$record = self::get_vendor_fulfillment( $fulfillment_order, $vendor_id );
			$status = self::normalize_fulfillment_status( (string) ( $record['status'] ?? 'confirmed' ) );

			if ( in_array( $status, array( 'shipped', 'out_for_delivery', 'delivered' ), true ) ) {
				return 'earned';
			}
			if ( 'cancelled' === $status ) {
				return 'deducted';
			}

			return 'pending';
		}

		/**
		 * Label for vendor item financial state.
		 *
		 * @param string $state State key.
		 * @return string
		 */
		public static function get_vendor_item_financial_label( $state ) {
			$labels = array(
				'pending'  => __( 'Pending', 'consucorner' ),
				'earned'   => __( 'Earned', 'consucorner' ),
				'deducted' => __( 'Deducted', 'consucorner' ),
			);

			$state = sanitize_key( (string) $state );
			return isset( $labels[ $state ] ) ? $labels[ $state ] : $state;
		}

		/**
		 * Vendor order listing header cell (classic PHP table).
		 */
		public static function render_vendor_order_header() {
			echo '<th>' . esc_html__( 'Bosta status', 'consucorner' ) . '</th>';
		}

		/**
		 * Enqueue React DataViews column script on vendor dashboard.
		 *
		 * Must load before dokan-react-frontend so wp.hooks filters are registered
		 * before the Orders DataViews component mounts.
		 */
		public static function enqueue_vendor_orders_bosta_column() {
			if ( ! function_exists( 'dokan_is_seller_dashboard' ) || ! dokan_is_seller_dashboard() ) {
				return;
			}

			$rel  = '/assets/js/dokan-orders-bosta-column.js';
			$path = get_template_directory() . $rel;
			if ( ! file_exists( $path ) ) {
				return;
			}

			$handle = 'consucorner-dokan-orders-bosta';
			wp_enqueue_script(
				$handle,
				get_template_directory_uri() . $rel,
				array( 'wp-hooks', 'wp-element', 'wp-i18n' ),
				(string) filemtime( $path ),
				true
			);

			// Ensure our filters run before Dokan React mounts Orders DataViews.
			if ( wp_script_is( 'dokan-react-frontend', 'registered' ) ) {
				$scripts = wp_scripts();
				if ( isset( $scripts->registered['dokan-react-frontend'] ) ) {
					$deps = $scripts->registered['dokan-react-frontend']->deps;
					if ( ! in_array( $handle, $deps, true ) ) {
						$scripts->registered['dokan-react-frontend']->deps[] = $handle;
					}
				}
			}
		}

		/**
		 * Expose Bosta + fulfillment fields on Dokan vendor orders REST payloads.
		 *
		 * @param WP_REST_Response $response Response.
		 * @param WC_Data          $object Order.
		 * @param WP_REST_Request  $request Request.
		 * @return WP_REST_Response
		 */
		public static function rest_prepare_vendor_order_bosta_fields( $response, $object, $request ) {
			unset( $request );

			if ( ! $response instanceof WP_REST_Response || ! $object instanceof WC_Order ) {
				return $response;
			}

			$data    = $response->get_data();
			$bosta   = self::get_order_bosta_snapshot( $object );
			$bosta   = is_array( $bosta ) ? $bosta : array();
			$summary = self::get_customer_fulfillment_summary( $object );

			$data['bosta_status']          = (string) ( $bosta['status'] ?? '' );
			$data['bosta_tracking_number'] = (string) ( $bosta['tracking_number'] ?? '' );
			$data['bosta_state_code']      = absint( $bosta['state_code'] ?? 0 );
			$data['cc_fulfillment_status'] = (string) ( $summary['status'] ?? '' );
			$data['cc_fulfillment_label']  = (string) ( $summary['label'] ?? '' );

			$response->set_data( $data );
			return $response;
		}

		/**
		 * Vendor order listing row cell.
		 *
		 * @param WC_Order $order Order.
		 */
		public static function render_vendor_order_row( $order ) {
			if ( ! $order instanceof WC_Order ) {
				echo '<td>—</td>';
				return;
			}

			$vendor_id = function_exists( 'dokan_get_current_user_id' ) ? absint( dokan_get_current_user_id() ) : get_current_user_id();
			$record    = self::get_vendor_fulfillment( $order, $vendor_id );
			$return    = self::get_order_return_summary( $order->get_id() );
			$cancel    = class_exists( 'Consucorner_Order_Cancel_Requests' )
				? Consucorner_Order_Cancel_Requests::get_vendor_summary( $order->get_id(), $vendor_id )
				: array();
			$financial = self::get_vendor_financial_summary( $order, $vendor_id );
			$bosta     = self::get_order_bosta_snapshot( $order );
			$bosta     = is_array( $bosta ) ? $bosta : array();
			echo '<td class="cc-vendor-workflow-status" data-title="' . esc_attr__( 'Bosta status', 'consucorner' ) . '">';
			if ( ! empty( $bosta['status'] ) ) {
				echo '<span class="dokan-label dokan-label-default">' . esc_html( (string) $bosta['status'] ) . '</span>';
				if ( ! empty( $bosta['tracking_number'] ) ) {
					echo '<br><span class="description" style="display:block;margin-top:4px;">' . esc_html__( 'Tracking:', 'consucorner' ) . ' ' . esc_html( (string) $bosta['tracking_number'] ) . '</span>';
				}
			} else {
				echo '<span class="description">—</span>';
			}
			echo '<br><span class="dokan-label dokan-label-info" style="margin-top:4px;">' . esc_html( self::get_fulfillment_label( (string) $record['status'] ) ) . '</span>';
			if ( ! empty( $return['label'] ) ) {
				echo '<br><span class="dokan-label dokan-label-warning" style="margin-top:4px;">' . esc_html( sprintf( __( 'Return: %s', 'consucorner' ), $return['label'] ) ) . '</span>';
			}
			if ( ! empty( $cancel['label'] ) ) {
				echo '<br><span class="dokan-label dokan-label-danger" style="margin-top:4px;">' . esc_html( sprintf( __( 'Cancellation: %s', 'consucorner' ), $cancel['label'] ) ) . '</span>';
			}
			if ( ! empty( $financial['net_payable_html'] ) ) {
				echo '<br><span class="description" style="display:block;margin-top:4px;">' . esc_html__( 'Net payable:', 'consucorner' ) . ' ' . wp_kses_post( $financial['net_payable_html'] ) . '</span>';
			}
			echo '</td>';
		}

		/**
		 * Vendor order detail read-only panel.
		 *
		 * @param WC_Order $order Order.
		 */
		public static function render_vendor_order_detail( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$vendor_id = function_exists( 'dokan_get_current_user_id' ) ? absint( dokan_get_current_user_id() ) : get_current_user_id();
			$record    = self::get_vendor_fulfillment( $order, $vendor_id );
			$return    = self::get_order_return_summary( $order->get_id() );
			$cancel    = class_exists( 'Consucorner_Order_Cancel_Requests' )
				? Consucorner_Order_Cancel_Requests::get_vendor_summary( $order->get_id(), $vendor_id )
				: array();
			$financial = self::get_vendor_financial_summary( $order, $vendor_id );
			$bosta     = self::get_order_bosta_snapshot( $order );
			$bosta     = is_array( $bosta ) ? $bosta : array();
			?>
			<div class="dokan-panel dokan-panel-default cc-vendor-workflow-panel">
				<div class="dokan-panel-heading"><?php esc_html_e( 'ConsuCorner operations status', 'consucorner' ); ?></div>
				<div class="dokan-panel-body">
					<p><strong><?php esc_html_e( 'Order:', 'consucorner' ); ?></strong> <?php echo esc_html( self::get_fulfillment_label( (string) $record['status'] ) ); ?></p>
					<?php if ( ! empty( $return['label'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Return:', 'consucorner' ); ?></strong> <?php echo esc_html( $return['label'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $cancel['label'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Cancellation:', 'consucorner' ); ?></strong> <?php echo esc_html( $cancel['label'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $bosta['status'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Bosta status:', 'consucorner' ); ?></strong> <?php echo esc_html( (string) $bosta['status'] ); ?>
							<?php if ( ! empty( $bosta['tracking_number'] ) ) : ?>
								<br><span class="description"><?php esc_html_e( 'Tracking:', 'consucorner' ); ?> <?php echo esc_html( (string) $bosta['tracking_number'] ); ?></span>
							<?php endif; ?>
						</p>
					<?php else : ?>
						<p><strong><?php esc_html_e( 'Bosta status:', 'consucorner' ); ?></strong> —</p>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Operations controls these statuses. Please contact operations if anything needs correction.', 'consucorner' ); ?></p>
				</div>
			</div>
			<div class="dokan-panel dokan-panel-default cc-vendor-financial-panel">
				<div class="dokan-panel-heading"><?php esc_html_e( 'Your earnings on this order', 'consucorner' ); ?></div>
				<div class="dokan-panel-body">
					<p><strong><?php esc_html_e( 'Items total:', 'consucorner' ); ?></strong> <?php echo wp_kses_post( (string) ( $financial['order_total_html'] ?? '' ) ); ?></p>
					<p><strong><?php esc_html_e( 'Platform commission:', 'consucorner' ); ?></strong> <?php echo wp_kses_post( (string) ( $financial['commission_html'] ?? '' ) ); ?></p>
					<p><strong><?php esc_html_e( 'Return deductions:', 'consucorner' ); ?></strong> <?php echo wp_kses_post( (string) ( $financial['return_deductions_html'] ?? '' ) ); ?></p>
					<p><strong><?php esc_html_e( 'Net payable:', 'consucorner' ); ?></strong> <?php echo wp_kses_post( (string) ( $financial['net_payable_html'] ?? '' ) ); ?></p>
					<?php if ( ! empty( $financial['items'] ) ) : ?>
						<table class="dokan-table dokan-table-striped" style="margin-top:10px;">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Product', 'consucorner' ); ?></th>
									<th><?php esc_html_e( 'Qty', 'consucorner' ); ?></th>
									<th><?php esc_html_e( 'State', 'consucorner' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( (array) $financial['items'] as $line ) : ?>
									<tr>
										<td><?php echo esc_html( (string) ( $line['name'] ?? '' ) ); ?></td>
										<td><?php echo esc_html( (string) ( $line['qty'] ?? 0 ) ); ?></td>
										<td><?php echo esc_html( (string) ( $line['label'] ?? '' ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}

		/**
		 * Vendor RMA read-only notice.
		 */
		public static function render_vendor_rma_readonly_notice() {
			echo '<div class="dokan-alert dokan-alert-info cc-rma-readonly-notice">' . esc_html__( 'ConsuCorner operations manages return request statuses and resolutions. Vendors can review details and conversations here.', 'consucorner' ) . '</div>';
		}

		/**
		 * Hide vendor-side RMA mutation controls without editing Dokan templates.
		 */
		public static function render_vendor_readonly_styles() {
			if ( ! function_exists( 'dokan_is_seller_dashboard' ) || ! dokan_is_seller_dashboard() ) {
				return;
			}
			?>
			<style>
				.dokan-rma-single-request-area .dokan-status-update-panel form,
				.rma-request-listing-table .row-actions .delete {
					display: none !important;
				}
			</style>
			<?php
		}

		/**
		 * Resolve WooCommerce order by internal ID or ConsuCorner display number.
		 *
		 * @param int|string $order_ref Order ID or display number.
		 * @return WC_Order|false
		 */
		public static function resolve_order( $order_ref ) {
			if ( function_exists( 'cc_resolve_order_tracking_input' ) ) {
				$resolved = cc_resolve_order_tracking_input( $order_ref );
				if ( is_numeric( $resolved ) ) {
					$order = wc_get_order( absint( $resolved ) );
					if ( $order ) {
						return $order;
					}
				}
			}

			$order = wc_get_order( absint( $order_ref ) );
			return $order ? $order : false;
		}

		/**
		 * Default record.
		 *
		 * @return array<string,mixed>
		 */
		private static function default_fulfillment_record() {
			return array(
				'status'     => 'confirmed',
				'label'      => self::get_fulfillment_label( 'confirmed' ),
				'updated_at' => '',
				'updated_by' => 0,
				'history'    => array(),
			);
		}

		/**
		 * Find recently inserted RMA request ID after Dokan save.
		 *
		 * @param array<string,mixed> $data Request data.
		 * @return int
		 */
		private static function find_recent_request_id( $data ) {
			global $wpdb;
			$table = $wpdb->prefix . 'dokan_rma_request';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return absint(
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$table}
						WHERE order_id = %d AND vendor_id = %d AND customer_id = %d
						ORDER BY id DESC LIMIT 1",
						absint( $data['order_id'] ?? 0 ),
						absint( $data['vendor_id'] ?? 0 ),
						absint( $data['customer_id'] ?? 0 )
					)
				)
			);
		}

		/**
		 * Map operations workflow to valid Dokan RMA status.
		 *
		 * @param string $status Workflow status.
		 * @return string
		 */
		/**
		 * Remove Dokan warranty endpoint output without editing Dokan Pro.
		 */
		private static function remove_dokan_warranty_endpoint_content() {
			global $wp_filter;

			$hook = 'woocommerce_account_request-warranty_endpoint';
			if ( empty( $wp_filter[ $hook ] ) || ! is_a( $wp_filter[ $hook ], 'WP_Hook' ) ) {
				return;
			}

			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'] ?? null;
					if ( is_array( $function ) && isset( $function[1] ) && 'content_request_warranty' === $function[1] ) {
						remove_action( $hook, $function, (int) $priority );
					}
				}
			}
		}

		private static function map_workflow_to_dokan_status( $status ) {
			$map = array(
				'requested'         => 'new',
				'reviewing'         => 'reviewing',
				'approved'          => 'processing',
				'return_in_transit' => 'processing',
				'received'          => 'processing',
				'resolved'          => 'completed',
				'rejected'          => 'rejected',
			);

			return isset( $map[ $status ] ) ? $map[ $status ] : '';
		}

		/**
		 * Notify customer and vendor when ops creates return.
		 *
		 * @param WC_Order              $order Order.
		 * @param array<int,array>      $created Created requests.
		 */
		private static function notify_manual_return_created( $order, $created ) {
			$subject = sprintf(
				/* translators: %s: order number */
				__( 'Return request created for order #%s', 'consucorner' ),
				$order->get_order_number()
			);
			$message = sprintf(
				/* translators: %s: order number */
				__( 'ConsuCorner operations created a return request for order #%s. The vendor and operations team will review it from the Returns dashboard.', 'consucorner' ),
				$order->get_order_number()
			);

			if ( $order->get_billing_email() ) {
				wp_mail( $order->get_billing_email(), $subject, $message );
			}

			foreach ( $created as $row ) {
				$vendor = get_userdata( absint( $row['vendor_id'] ?? 0 ) );
				if ( $vendor && $vendor->user_email ) {
					wp_mail( $vendor->user_email, $subject, $message );
				}
			}
		}

		/**
		 * Sync vendor fulfillment when Bosta shipment status changes.
		 *
		 * @param WC_Order $order Saved order.
		 */
		public static function maybe_sync_fulfillment_from_bosta( $order ) {
			if ( self::$bosta_sync_running || ! $order instanceof WC_Order ) {
				return;
			}

			if ( ! in_array( $order->get_type(), array( 'shop_order' ), true ) ) {
				return;
			}

			$bosta_order = self::resolve_bosta_source_order( $order );
			if ( ! $bosta_order ) {
				return;
			}

			$fulfillment_order = self::resolve_fulfillment_order( $order );
			if ( ! $fulfillment_order ) {
				return;
			}

			$status_text = sanitize_text_field( (string) $bosta_order->get_meta( 'bosta_status', true ) );
			$state_code  = absint( $bosta_order->get_meta( 'bosta_state_code', true ) );
			$target      = self::map_bosta_to_fulfillment( $status_text, $state_code );

			if ( ! $target || ! isset( self::fulfillment_statuses()[ $target ] ) ) {
				return;
			}

			$fingerprint = self::build_bosta_sync_fingerprint( $status_text, $state_code, $target );
			$stored_fp   = (string) $fulfillment_order->get_meta( self::META_BOSTA_SYNC_FP, true );
			if ( $stored_fp === $fingerprint ) {
				return;
			}

			$note = $status_text
				? sprintf(
					/* translators: 1: Bosta status text, 2: state code */
					__( 'Synced from Bosta: %1$s (state %2$d).', 'consucorner' ),
					$status_text,
					$state_code
				)
				: sprintf(
					/* translators: %d: Bosta state code */
					__( 'Synced from Bosta state %d.', 'consucorner' ),
					$state_code
				);

			$changed = false;
			foreach ( self::get_order_vendor_ids( $fulfillment_order ) as $vendor_id ) {
				if ( self::advance_fulfillment_to_target( $fulfillment_order->get_id(), $vendor_id, $target, $note, 'bosta' ) ) {
					$changed = true;
				}
			}

			self::$bosta_sync_running = true;
			$fulfillment_order->update_meta_data( self::META_BOSTA_SYNC_FP, $fingerprint );
			if ( $changed ) {
				$fulfillment_order->add_order_note(
					sprintf(
						/* translators: 1: Bosta status, 2: mapped fulfillment label */
						__( 'Bosta shipment status "%1$s" synced to vendor fulfillment (%2$s).', 'consucorner' ),
						$status_text ? $status_text : '#' . $state_code,
						self::get_fulfillment_label( $target )
					)
				);
			}
			$fulfillment_order->save();
			self::$bosta_sync_running = false;
		}

		/**
		 * Bosta snapshot for admin/customer payloads.
		 *
		 * @param WC_Order $order Order.
		 * @return array<string,mixed>|null
		 */
		public static function get_order_bosta_snapshot( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return null;
			}

			$bosta_order = self::resolve_bosta_source_order( $order );
			if ( ! $bosta_order ) {
				return null;
			}

			$status_text = sanitize_text_field( (string) $bosta_order->get_meta( 'bosta_status', true ) );
			$state_code  = absint( $bosta_order->get_meta( 'bosta_state_code', true ) );
			$mapped      = self::map_bosta_to_fulfillment( $status_text, $state_code );

			return array(
				'status'             => $status_text,
				'state_code'         => $state_code,
				'tracking_number'    => sanitize_text_field( (string) $bosta_order->get_meta( 'bosta_tracking_number', true ) ),
				'mapped_fulfillment' => $mapped ? $mapped : '',
				'mapped_label'       => $mapped ? self::get_fulfillment_label( $mapped ) : '',
				'order_id'           => $bosta_order->get_id(),
			);
		}

		/**
		 * Map Bosta shipment state to operations fulfillment status.
		 *
		 * @param string $status_text Bosta status label.
		 * @param int    $state_code Bosta numeric state.
		 * @return string|null
		 */
		public static function map_bosta_to_fulfillment( $status_text, $state_code ) {
			$state_code = absint( $state_code );

			if ( $state_code > 0 ) {
				$known_codes = array(
					10  => 'confirmed',
					11  => 'confirmed',
					20  => 'shipped',
					21  => 'preparing',
					22  => 'preparing',
					30  => 'shipped',
					31  => 'shipped',
					41  => 'out_for_delivery',
					45  => 'delivered',
					46  => 'delivered',
					48  => 'cancelled',
					100 => 'cancelled',
					101 => 'cancelled',
					104 => 'cancelled',
				);

				if ( isset( $known_codes[ $state_code ] ) ) {
					return $known_codes[ $state_code ];
				}

				if ( in_array( $state_code, array( 48, 100, 101, 104 ), true ) ) {
					return 'cancelled';
				}

				if ( $state_code >= 45 ) {
					return 'delivered';
				}

				if ( $state_code >= 40 ) {
					return self::bosta_text_is_out_for_delivery( $status_text ) ? 'out_for_delivery' : 'delivered';
				}

				if ( $state_code >= 30 ) {
					return self::bosta_text_is_out_for_delivery( $status_text ) ? 'out_for_delivery' : 'shipped';
				}

				if ( $state_code >= 20 ) {
					return 'shipped';
				}

				if ( $state_code >= 10 ) {
					if ( self::bosta_text_is_warehouse( $status_text ) ) {
						return 'shipped';
					}
					return 'confirmed';
				}
			}

			return self::map_bosta_text_to_fulfillment( $status_text );
		}

		/**
		 * Order that stores fulfillment meta (parent for Dokan sub-orders).
		 *
		 * @param WC_Order $order Order.
		 * @return WC_Order|null
		 */
		public static function resolve_fulfillment_order( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return null;
			}

			$meta = $order->get_meta( self::META_FULFILLMENT, true );
			if ( is_array( $meta ) && ! empty( $meta ) ) {
				return $order;
			}

			$parent_id = $order->get_parent_id();
			if ( $parent_id ) {
				$parent = wc_get_order( $parent_id );
				if ( $parent instanceof WC_Order ) {
					$parent_meta = $parent->get_meta( self::META_FULFILLMENT, true );
					if ( is_array( $parent_meta ) && ! empty( $parent_meta ) ) {
						return $parent;
					}

					if ( count( self::get_order_vendor_ids( $parent ) ) > 0 ) {
						return $parent;
					}
				}
			}

			return $order;
		}

		/**
		 * Order that owns Bosta shipment meta.
		 *
		 * @param WC_Order $order Order.
		 * @return WC_Order|null
		 */
		public static function resolve_bosta_source_order( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return null;
			}

			if ( self::order_has_bosta_meta( $order ) ) {
				return $order;
			}

			$parent_id = $order->get_parent_id();
			if ( $parent_id ) {
				$parent = wc_get_order( $parent_id );
				if ( $parent instanceof WC_Order && self::order_has_bosta_meta( $parent ) ) {
					return $parent;
				}
			}

			return null;
		}

		/**
		 * Whether an order has Bosta shipment metadata.
		 *
		 * @param WC_Order $order Order.
		 * @return bool
		 */
		private static function order_has_bosta_meta( $order ) {
			$state  = $order->get_meta( 'bosta_state_code', true );
			$status = $order->get_meta( 'bosta_status', true );
			$track  = $order->get_meta( 'bosta_tracking_number', true );

			return ( '' !== (string) $state && null !== $state )
				|| ( '' !== (string) $status && null !== $status )
				|| ( '' !== (string) $track && null !== $track );
		}

		/**
		 * Build sync fingerprint for Bosta updates.
		 *
		 * @param string $status_text Bosta status label.
		 * @param int    $state_code Bosta state code.
		 * @param string $target Mapped fulfillment status.
		 * @return string
		 */
		private static function build_bosta_sync_fingerprint( $status_text, $state_code, $target ) {
			return md5( strtolower( trim( (string) $status_text ) ) . '|' . absint( $state_code ) . '|' . sanitize_key( $target ) );
		}

		/**
		 * Fulfillment rank for forward-only Bosta sync.
		 *
		 * @param string $status Fulfillment status.
		 * @return int
		 */
		private static function fulfillment_rank( $status ) {
			$ranks = array(
				'cancelled'        => -1,
				'confirmed'        => 1,
				'preparing'        => 2,
				'shipped'          => 3,
				'out_for_delivery' => 4,
				'delivered'        => 5,
			);

			$status = self::normalize_fulfillment_status( $status );
			return isset( $ranks[ $status ] ) ? (int) $ranks[ $status ] : 1;
		}

		/**
		 * Forward fulfillment path used for multi-step Bosta advancement.
		 *
		 * @return string[]
		 */
		private static function fulfillment_path() {
			return array( 'confirmed', 'preparing', 'shipped', 'out_for_delivery', 'delivered' );
		}

		/**
		 * Advance one vendor fulfillment status toward a Bosta-mapped target.
		 *
		 * @param int    $order_id Order ID.
		 * @param int    $vendor_id Vendor ID.
		 * @param string $target_status Target fulfillment status.
		 * @param string $note Sync note.
		 * @param string $source Source key.
		 * @return bool
		 */
		private static function advance_fulfillment_to_target( $order_id, $vendor_id, $target_status, $note = '', $source = 'bosta' ) {
			$target_status = sanitize_key( (string) $target_status );
			$max_steps     = 8;
			$changed       = false;

			for ( $step = 0; $step < $max_steps; $step++ ) {
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					break;
				}

				$current = self::get_vendor_fulfillment( $order, $vendor_id );
				$from    = self::normalize_fulfillment_status( (string) ( $current['status'] ?? 'confirmed' ) );

				if ( $from === $target_status ) {
					break;
				}

				if ( 'cancelled' === $target_status ) {
					if ( in_array( $from, array( 'delivered', 'cancelled' ), true ) ) {
						break;
					}
					if ( self::can_transition_fulfillment( $from, 'cancelled' ) ) {
						$result = self::update_fulfillment_status( $order_id, $vendor_id, 'cancelled', $note, $source );
						if ( ! is_wp_error( $result ) ) {
							$changed = true;
						}
					}
					break;
				}

				if ( self::fulfillment_rank( $target_status ) < self::fulfillment_rank( $from ) ) {
					break;
				}

				if ( self::can_transition_fulfillment( $from, $target_status ) ) {
					$result = self::update_fulfillment_status( $order_id, $vendor_id, $target_status, $note, $source );
					if ( ! is_wp_error( $result ) ) {
						$changed = true;
					}
					break;
				}

				$path       = self::fulfillment_path();
				$from_index = array_search( $from, $path, true );
				if ( false === $from_index ) {
					break;
				}

				$next = null;
				for ( $i = $from_index + 1; $i < count( $path ); $i++ ) {
					if ( self::fulfillment_rank( $path[ $i ] ) > self::fulfillment_rank( $target_status ) ) {
						break;
					}
					if ( self::can_transition_fulfillment( $from, $path[ $i ] ) ) {
						$next = $path[ $i ];
						break;
					}
				}

				if ( ! $next ) {
					break;
				}

				$result = self::update_fulfillment_status( $order_id, $vendor_id, $next, $note, $source );
				if ( is_wp_error( $result ) ) {
					break;
				}

				$changed = true;
			}

			return $changed;
		}

		/**
		 * Text fallback mapping for Bosta shipment labels.
		 *
		 * @param string $status_text Bosta status label.
		 * @return string|null
		 */
		private static function map_bosta_text_to_fulfillment( $status_text ) {
			$text = strtolower( trim( (string) $status_text ) );
			if ( '' === $text ) {
				return null;
			}

			if ( preg_match( '/terminat|returned to sender|cancel|failed|unsuccess/', $text ) ) {
				return 'cancelled';
			}
			if ( preg_match( '/delivered|delivery completed|successfully delivered/', $text ) ) {
				return 'delivered';
			}
			if ( self::bosta_text_is_out_for_delivery( $status_text ) ) {
				return 'out_for_delivery';
			}
			if ( preg_match( '/transit|hub|picked up|on the way|in route/', $text ) ) {
				return 'shipped';
			}
			if ( self::bosta_text_is_warehouse( $status_text ) ) {
				return 'shipped';
			}
			if ( preg_match( '/pickup|created|new shipment|waiting/', $text ) ) {
				return 'confirmed';
			}

			return 'confirmed';
		}

		/**
		 * Whether Bosta text indicates warehouse handling.
		 *
		 * @param string $status_text Bosta status label.
		 * @return bool
		 */
		private static function bosta_text_is_warehouse( $status_text ) {
			$text = strtolower( trim( (string) $status_text ) );
			return (bool) preg_match( '/warehouse|received at/', $text );
		}

		/**
		 * Whether Bosta text indicates last-mile delivery.
		 *
		 * @param string $status_text Bosta status label.
		 * @return bool
		 */
		private static function bosta_text_is_out_for_delivery( $status_text ) {
			$text = strtolower( trim( (string) $status_text ) );
			return (bool) preg_match( '/out for delivery|with courier|last mile|out for deliver/', $text );
		}

		/**
		 * Notify customer and vendor when return workflow changes.
		 *
		 * @param WC_Order            $order Order.
		 * @param array<string,mixed> $request RMA request.
		 * @param string              $status Workflow status.
		 */
		private static function notify_return_status_changed( $order, $request, $status ) {
			$label   = self::get_return_label( $status );
			$subject = sprintf(
				/* translators: 1: request id, 2: status */
				__( 'Return #%1$d updated: %2$s', 'consucorner' ),
				absint( $request['id'] ?? 0 ),
				$label
			);
			$message = sprintf(
				/* translators: 1: order number, 2: status */
				__( 'Your return request for order #%1$s is now: %2$s.', 'consucorner' ),
				$order->get_order_number(),
				$label
			);

			if ( $order->get_billing_email() ) {
				wp_mail( $order->get_billing_email(), $subject, $message );
			}

			$vendor = get_userdata( absint( $request['vendor_id'] ?? 0 ) );
			if ( $vendor && $vendor->user_email ) {
				wp_mail( $vendor->user_email, $subject, $message );
			}
		}
	}

	Consucorner_Order_Return_Workflow::init();

endif;
