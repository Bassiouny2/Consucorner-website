<?php
/**
 * Shared return-resolution and Dokan vendor-adjust helpers.
 *
 * Used by the Returns report (wallet / direct routes) and admin wallet refunds.
 *
 * @package Consucorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return resolution + wallet item meta keys (shared with wallet refunds).
 */
final class CC_Returns_Refund_Service {

	const ORDER_META_RESOLUTIONS = '_cc_return_resolutions';
	const ITEM_META_FLAG         = '_refunded_to_wallet';
	const ITEM_META_AMOUNT       = '_cc_wallet_refund_amount';
	const ITEM_META_QTY          = '_cc_wallet_refund_qty';
	const ITEM_META_DATE         = '_cc_wallet_refund_date';
	const ITEM_META_RMA_REQUEST  = '_cc_rma_request_id';
	const ITEM_META_RESOLUTION   = '_cc_return_resolution';

	/**
	 * Bootstrap hooks (none required; static service).
	 */
	public static function init() {
		// Intentionally empty — class is called directly.
	}

	/**
	 * Fetch a single RMA request row with product lines.
	 *
	 * @param int $request_id Request ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_request( $request_id ) {
		$request_id = absint( $request_id );
		if ( ! $request_id ) {
			return null;
		}

		if ( function_exists( 'dokan_get_warranty_request' ) ) {
			$row = dokan_get_warranty_request(
				array(
					'id'     => $request_id,
					'number' => 1,
				)
			);
			if ( is_array( $row ) && ! empty( $row['id'] ) ) {
				return self::normalize_request_row( $row );
			}
		}

		global $wpdb;
		$table = $wpdb->prefix . 'dokan_rma_request';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $request_id ),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$products = self::get_request_products( $request_id );
		$row['lines'] = $products;

		return $row;
	}

	/**
	 * Product lines for a request.
	 *
	 * @param int $request_id Request ID.
	 * @return array<int,array<string,int>>
	 */
	public static function get_request_products( $request_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dokan_rma_request_product';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT request_id, product_id, quantity, item_id FROM {$table} WHERE request_id = %d",
				absint( $request_id )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * All RMA requests for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_order_requests( $order_id ) {
		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return array();
		}

		if ( function_exists( 'dokan_get_warranty_request' ) ) {
			$rows = dokan_get_warranty_request(
				array(
					'order_id' => $order_id,
					'number'   => 100,
				)
			);
			if ( is_array( $rows ) ) {
				if ( isset( $rows['id'] ) ) {
					$rows = array( $rows );
				}
				return array_map( array( __CLASS__, 'normalize_request_row' ), $rows );
			}
		}

		global $wpdb;
		$table = $wpdb->prefix . 'dokan_rma_request';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at DESC LIMIT 100", $order_id ),
			ARRAY_A
		);

		foreach ( $rows as &$row ) {
			$row['lines'] = self::get_request_products( (int) $row['id'] );
		}
		unset( $row );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Normalize dokan_get_warranty_request GROUP_CONCAT row into lines array.
	 *
	 * @param array<string,mixed> $row Raw row.
	 * @return array<string,mixed>
	 */
	private static function normalize_request_row( $row ) {
		if ( ! empty( $row['lines'] ) && is_array( $row['lines'] ) ) {
			return $row;
		}

		$product_ids = isset( $row['products'] ) ? array_map( 'absint', explode( ',', (string) $row['products'] ) ) : array();
		$quantities  = isset( $row['quantity'] ) ? array_map( 'absint', preg_split( '/\s*,\s*/', (string) $row['quantity'] ) ) : array();
		$item_ids    = isset( $row['item_id'] ) ? array_map( 'absint', preg_split( '/\s*,\s*/', (string) $row['item_id'] ) ) : array();

		$lines = array();
		$count = max( count( $product_ids ), count( $quantities ), count( $item_ids ) );
		for ( $i = 0; $i < $count; $i++ ) {
			$lines[] = array(
				'product_id' => isset( $product_ids[ $i ] ) ? $product_ids[ $i ] : 0,
				'quantity'   => isset( $quantities[ $i ] ) ? $quantities[ $i ] : 0,
				'item_id'    => isset( $item_ids[ $i ] ) ? $item_ids[ $i ] : 0,
			);
		}

		$row['lines'] = $lines;

		return $row;
	}

	/**
	 * Stored resolution for a request on an order.
	 *
	 * @param int $order_id Order ID.
	 * @param int $request_id Request ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_resolution( $order_id, $request_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		$all = $order->get_meta( self::ORDER_META_RESOLUTIONS, true );
		$all = is_array( $all ) ? $all : array();
		$key = (string) absint( $request_id );

		return isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Persist resolution metadata on the order.
	 *
	 * @param WC_Order            $order Order.
	 * @param int                 $request_id Request ID.
	 * @param array<string,mixed> $data Resolution payload.
	 */
	public static function save_resolution( $order, $request_id, $data ) {
		$all = $order->get_meta( self::ORDER_META_RESOLUTIONS, true );
		$all = is_array( $all ) ? $all : array();
		$key = (string) absint( $request_id );

		$all[ $key ] = array_merge(
			array(
				'route'       => '',
				'amount'      => 0,
				'resolved_at' => current_time( 'mysql' ),
				'resolved_by' => get_current_user_id(),
			),
			$data
		);

		$order->update_meta_data( self::ORDER_META_RESOLUTIONS, $all );
		$order->save();
	}

	/**
	 * Whether request is already resolved.
	 *
	 * @param int $order_id Order ID.
	 * @param int $request_id Request ID.
	 * @return bool
	 */
	public static function is_request_resolved( $order_id, $request_id ) {
		$resolution = self::get_resolution( $order_id, $request_id );
		return ! empty( $resolution['route'] );
	}

	/**
	 * Open (non-terminal) RMA requests for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_open_order_requests( $order_id ) {
		$terminal = array( 'completed', 'rejected', 'info_removed' );
		$open     = array();

		foreach ( self::get_order_requests( $order_id ) as $request ) {
			$status = isset( $request['status'] ) ? (string) $request['status'] : '';
			if ( ! in_array( $status, $terminal, true ) ) {
				$open[] = $request;
			}
		}

		return $open;
	}

	/**
	 * Item IDs blocked from legacy wallet refund (RMA or prior resolution).
	 *
	 * @param WC_Order $order Order.
	 * @return array<int,string> item_id => reason message.
	 */
	public static function get_wallet_blocked_items( $order ) {
		$blocked = array();

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$reason = self::get_item_block_reason( $order, (int) $item_id );
			if ( $reason ) {
				$blocked[ (int) $item_id ] = $reason;
			}
		}

		return $blocked;
	}

	/**
	 * Why a line item cannot be wallet-refunded again.
	 *
	 * @param WC_Order $order Order.
	 * @param int      $item_id Line item ID.
	 * @param int      $ignore_request_id Optional RMA request ID to ignore (when resolving that request).
	 * @return string Empty if allowed.
	 */
	public static function get_item_block_reason( $order, $item_id, $ignore_request_id = 0 ) {
		$item_id            = absint( $item_id );
		$ignore_request_id  = absint( $ignore_request_id );
		$item               = $order->get_item( $item_id );
		if ( ! $item ) {
			return __( 'Line item not found.', 'consucorner' );
		}

		$rma_request = absint( $item->get_meta( self::ITEM_META_RMA_REQUEST, true ) );
		$resolution  = (string) $item->get_meta( self::ITEM_META_RESOLUTION, true );
		if ( $rma_request && $resolution && $rma_request !== $ignore_request_id ) {
			return sprintf(
				/* translators: 1: resolution route, 2: request id */
				__( 'Already resolved via return request #%2$d (%1$s).', 'consucorner' ),
				$resolution,
				$rma_request
			);
		}

		$refunded_qty = abs( (float) $order->get_qty_refunded_for_item( $item_id ) );
		$qty          = (float) $item->get_quantity();
		if ( $refunded_qty >= $qty && $qty > 0 ) {
			return __( 'Fully refunded in WooCommerce.', 'consucorner' );
		}

		foreach ( self::get_order_requests( $order->get_id() ) as $request ) {
			$request_id = absint( $request['id'] ?? 0 );
			if ( ! $request_id || $request_id === $ignore_request_id || self::is_request_resolved( $order->get_id(), $request_id ) ) {
				continue;
			}

			$status = isset( $request['status'] ) ? (string) $request['status'] : '';
			if ( in_array( $status, array( 'rejected', 'info_removed', 'completed' ), true ) ) {
				continue;
			}

			foreach ( (array) ( $request['lines'] ?? array() ) as $line ) {
				if ( absint( $line['item_id'] ?? 0 ) === $item_id ) {
					return sprintf(
						/* translators: %d: RMA request ID */
						__( 'Pending return request #%d — resolve it on WooCommerce → Returns.', 'consucorner' ),
						$request_id
					);
				}
			}
		}

		return '';
	}

	/**
	 * Calculate refundable amount for a line item quantity.
	 *
	 * @param WC_Order $order Order.
	 * @param int      $item_id Line item ID.
	 * @param int      $qty Quantity to refund.
	 * @return array{qty:int,refund_total:float,refund_ratio:float,original_total:float,item:WC_Order_Item_Product|null,name:string}
	 */
	public static function calculate_line_refund( $order, $item_id, $qty ) {
		$item = $order->get_item( $item_id );
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return array(
				'qty'            => 0,
				'refund_total'   => 0.0,
				'refund_ratio'   => 0.0,
				'original_total' => 0.0,
				'item'           => null,
				'name'           => '',
			);
		}

		$qty          = max( 0, wc_stock_amount( $qty ) );
		$line_qty     = max( 1, (float) $item->get_quantity() );
		$item_total   = (float) $item->get_total();
		$item_tax     = (float) $item->get_total_tax();
		$original     = max( 0, $item_total + $item_tax );
		$unit_total   = $original / $line_qty;
		$refund_total = min( $original, $unit_total * $qty );
		$refund_ratio = $original > 0 ? min( 1, $refund_total / $original ) : 0;

		return array(
			'qty'            => $qty,
			'refund_total'   => $refund_total,
			'refund_ratio'   => $refund_ratio,
			'original_total' => $original,
			'item'           => $item,
			'name'           => $item->get_name(),
		);
	}

	/**
	 * Resolve return to customer wallet.
	 *
	 * @param int                  $request_id Request ID.
	 * @param array<string,mixed>  $args Optional restock, shipping_deduction.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function resolve_to_wallet( $request_id, $args = array() ) {
		$request = self::get_request( $request_id );
		if ( ! $request ) {
			return new WP_Error( 'cc_returns_missing', __( 'Return request not found.', 'consucorner' ) );
		}

		$order_id = absint( $request['order_id'] ?? 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order ) {
			return new WP_Error( 'cc_returns_order', __( 'Order not found for this return.', 'consucorner' ) );
		}

		if ( self::is_request_resolved( $order_id, $request_id ) ) {
			return new WP_Error( 'cc_returns_done', __( 'This return was already resolved.', 'consucorner' ) );
		}

		$customer_id = absint( $request['customer_id'] ?? $order->get_customer_id() );
		if ( ! $customer_id ) {
			return new WP_Error( 'cc_returns_customer', __( 'No registered customer for wallet credit.', 'consucorner' ) );
		}

		$restock            = ! empty( $args['restock'] );
		$shipping_deduction = isset( $args['shipping_deduction'] ) ? max( 0, (float) $args['shipping_deduction'] ) : 0;
		$lines              = self::build_resolvable_lines( $order, $request );
		if ( empty( $lines ) ) {
			return new WP_Error( 'cc_returns_lines', __( 'No refundable items found on this return.', 'consucorner' ) );
		}

		$items_total = array_sum( wp_list_pluck( $lines, 'refund_total' ) );
		if ( $shipping_deduction > $items_total ) {
			return new WP_Error( 'cc_returns_shipping', __( 'Shipping deduction exceeds refund total.', 'consucorner' ) );
		}

		$wallet_amount = max( 0, $items_total - $shipping_deduction );
		if ( $wallet_amount <= 0 ) {
			return new WP_Error( 'cc_returns_amount', __( 'Refund amount must be greater than zero.', 'consucorner' ) );
		}

		$item_labels = array_map(
			static function ( $line ) {
				return sprintf( '%sx %s', wc_stock_amount( $line['qty'] ), $line['name'] );
			},
			$lines
		);

		$wallet_note = sprintf(
			/* translators: 1: return request id, 2: order id, 3: items */
			__( 'Return request #%1$d (order #%2$d): %3$s', 'consucorner' ),
			$request_id,
			$order_id,
			implode( ', ', $item_labels )
		);

		$txn_id = 'rma_wallet_' . $request_id;
		if ( function_exists( 'cc_add_to_custom_wallet' ) ) {
			$result = cc_add_to_custom_wallet( $customer_id, $wallet_amount, $wallet_note, $order_id, $txn_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		} else {
			return new WP_Error( 'cc_returns_wallet', __( 'Wallet system is not available.', 'consucorner' ) );
		}

		$restock_payload = array();
		$dokan_adjusted  = 0.0;
		$current_user    = wp_get_current_user();
		$refunded_by     = $current_user && $current_user->exists()
			? sprintf( '%s (%s)', $current_user->display_name, $current_user->user_login )
			: __( 'Unknown admin', 'consucorner' );

		foreach ( $lines as $line ) {
			$item            = $line['item'];
			$item_id         = (int) $item->get_id();
			$previous_qty    = (float) $item->get_meta( self::ITEM_META_QTY, true );
			$previous_amount = (float) $item->get_meta( self::ITEM_META_AMOUNT, true );
			$new_qty         = $previous_qty + (float) $line['qty'];
			$new_amount      = $previous_amount + (float) $line['refund_total'];

			$item->update_meta_data( self::ITEM_META_QTY, wc_format_decimal( $new_qty ) );
			$item->update_meta_data( self::ITEM_META_AMOUNT, wc_format_decimal( $new_amount ) );
			$item->update_meta_data( self::ITEM_META_DATE, current_time( 'mysql' ) );
			$item->update_meta_data( self::ITEM_META_FLAG, 'yes' );
			$item->update_meta_data( self::ITEM_META_RMA_REQUEST, $request_id );
			$item->update_meta_data( self::ITEM_META_RESOLUTION, 'wallet' );
			if ( defined( 'CC_WALLET_LEGACY_SYNC_META' ) ) {
				$item->update_meta_data( CC_WALLET_LEGACY_SYNC_META, 'yes' );
			}
			$item->save();

			if ( $restock && $line['qty'] > 0 ) {
				$restock_payload[ $item_id ] = array( 'qty' => $line['qty'] );
			}

			$dokan_adjusted += self::adjust_dokan_vendor_for_item(
				$order,
				$item,
				(float) $line['refund_total'],
				(float) $line['refund_ratio'],
				__( 'Return wallet refund', 'consucorner' )
			);
		}

		if ( $restock && ! empty( $restock_payload ) && function_exists( 'wc_restock_refunded_items' ) ) {
			wc_restock_refunded_items( $order, $restock_payload );
		}

		self::complete_rma_request( $request_id );

		self::save_resolution(
			$order,
			$request_id,
			array(
				'route'  => 'wallet',
				'amount' => $wallet_amount,
			)
		);

		$order->add_order_note(
			sprintf(
				/* translators: 1: admin, 2: request id, 3: amount, 4: shipping deduction, 5: vendor adjustment */
				__( 'Return #%2$d resolved to wallet by %1$s. Wallet credit: %3$s. Shipping deducted: %4$s. Vendor earning reduced: %5$s.', 'consucorner' ),
				$refunded_by,
				$request_id,
				wp_strip_all_tags( wc_price( $wallet_amount, array( 'currency' => $order->get_currency() ) ) ),
				wp_strip_all_tags( wc_price( $shipping_deduction, array( 'currency' => $order->get_currency() ) ) ),
				wp_strip_all_tags( wc_price( $dokan_adjusted, array( 'currency' => $order->get_currency() ) ) )
			)
		);
		$order->update_meta_data( '_cc_has_return_request', 'yes' );
		$order->update_meta_data( '_cc_return_last_status', 'completed' );
		$order->save();

		return array(
			'route'          => 'wallet',
			'amount'         => $wallet_amount,
			'dokan_adjusted' => $dokan_adjusted,
		);
	}

	/**
	 * Resolve return with a recorded WooCommerce refund (manual / offline money).
	 *
	 * @param int                 $request_id Request ID.
	 * @param array<string,mixed> $args Optional restock flag.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function resolve_to_direct( $request_id, $args = array() ) {
		$request = self::get_request( $request_id );
		if ( ! $request ) {
			return new WP_Error( 'cc_returns_missing', __( 'Return request not found.', 'consucorner' ) );
		}

		$order_id = absint( $request['order_id'] ?? 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order ) {
			return new WP_Error( 'cc_returns_order', __( 'Order not found for this return.', 'consucorner' ) );
		}

		if ( self::is_request_resolved( $order_id, $request_id ) ) {
			return new WP_Error( 'cc_returns_done', __( 'This return was already resolved.', 'consucorner' ) );
		}

		$restock = ! empty( $args['restock'] );
		$lines   = self::build_resolvable_lines( $order, $request );
		if ( empty( $lines ) ) {
			return new WP_Error( 'cc_returns_lines', __( 'No refundable items found on this return.', 'consucorner' ) );
		}

		$line_items = array();
		$amount     = 0.0;
		foreach ( $lines as $line ) {
			$item         = $line['item'];
			$item_id      = (int) $item->get_id();
			$line_qty     = max( 1, (float) $item->get_quantity() );
			$qty_ratio    = min( 1, (float) $line['qty'] / $line_qty );
			$refund_total = wc_format_decimal( (float) $item->get_total() * $qty_ratio );
			$refund_tax   = array();

			$tax_data = $item->get_taxes();
			if ( ! empty( $tax_data['total'] ) ) {
				foreach ( $tax_data['total'] as $tax_id => $tax_total ) {
					$refund_tax[ $tax_id ] = wc_format_decimal( (float) $tax_total * $qty_ratio );
				}
			}

			$line_items[ $item_id ] = array(
				'qty'          => $line['qty'],
				'refund_total' => $refund_total,
				'refund_tax'   => $refund_tax,
			);

			$amount += (float) $refund_total + array_sum( array_map( 'floatval', $refund_tax ) );
		}

		$reason = sprintf(
			/* translators: %d: RMA request ID */
			__( 'Manual return refund for request #%d (offline payment)', 'consucorner' ),
			$request_id
		);

		$refund = wc_create_refund(
			array(
				'amount'         => wc_format_decimal( $amount ),
				'reason'         => $reason,
				'order_id'       => $order_id,
				'line_items'     => $line_items,
				'refund_payment' => false,
				'restock_items'  => $restock,
			)
		);

		if ( is_wp_error( $refund ) ) {
			return $refund;
		}

		$dokan_adjusted = 0.0;
		foreach ( $lines as $line ) {
			$item = $line['item'];
			$item->update_meta_data( self::ITEM_META_RMA_REQUEST, $request_id );
			$item->update_meta_data( self::ITEM_META_RESOLUTION, 'direct' );
			$item->save();

			// Dokan Pro skips lite WC-refund hooks — adjust vendor earnings for monthly ledger/payouts.
			$dokan_adjusted += self::adjust_dokan_vendor_for_item(
				$order,
				$item,
				(float) $line['refund_total'],
				(float) $line['refund_ratio'],
				__( 'Return direct refund', 'consucorner' )
			);
		}

		self::complete_rma_request( $request_id );

		self::save_resolution(
			$order,
			$request_id,
			array(
				'route'        => 'direct',
				'amount'       => $amount,
				'wc_refund_id' => $refund->get_id(),
			)
		);

		$current_user = wp_get_current_user();
		$refunded_by  = $current_user && $current_user->exists()
			? sprintf( '%s (%s)', $current_user->display_name, $current_user->user_login )
			: __( 'Unknown admin', 'consucorner' );

		$order->add_order_note(
			sprintf(
				/* translators: 1: admin, 2: request id, 3: amount, 4: refund id, 5: vendor adjustment */
				__( 'Return #%2$d recorded as manual refund by %1$s. Amount: %3$s (WC refund #%4$d). Vendor earning reduced: %5$s. Payment gateway was not refunded.', 'consucorner' ),
				$refunded_by,
				$request_id,
				wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ),
				$refund->get_id(),
				wp_strip_all_tags( wc_price( $dokan_adjusted, array( 'currency' => $order->get_currency() ) ) )
			)
		);
		$order->update_meta_data( '_cc_has_return_request', 'yes' );
		$order->update_meta_data( '_cc_return_last_status', 'completed' );
		$order->save();

		return array(
			'route'          => 'direct',
			'amount'         => $amount,
			'wc_refund_id'   => $refund->get_id(),
			'dokan_adjusted' => $dokan_adjusted,
		);
	}

	/**
	 * Build line refund rows from an RMA request.
	 *
	 * @param WC_Order              $order Order.
	 * @param array<string,mixed>   $request Request row.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_resolvable_lines( $order, $request ) {
		$lines      = array();
		$request_id = absint( $request['id'] ?? 0 );

		foreach ( (array) ( $request['lines'] ?? array() ) as $raw ) {
			$item_id = absint( $raw['item_id'] ?? 0 );
			$qty     = absint( $raw['quantity'] ?? 0 );
			if ( ! $item_id || ! $qty ) {
				continue;
			}

			// Ignore this request's own pending status so Wallet/Direct can resolve it.
			$block = self::get_item_block_reason( $order, $item_id, $request_id );
			if ( $block ) {
				continue;
			}

			$calc = self::calculate_line_refund( $order, $item_id, $qty );
			if ( $calc['refund_total'] <= 0 || ! $calc['item'] ) {
				continue;
			}

			$lines[] = $calc;
		}

		return $lines;
	}

	/**
	 * Mark Dokan RMA request completed.
	 *
	 * @param int $request_id Request ID.
	 */
	private static function complete_rma_request( $request_id ) {
		if ( function_exists( 'dokan_update_warranty_request_status' ) ) {
			dokan_update_warranty_request_status( absint( $request_id ), 'completed' );
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'dokan_rma_request';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'status' => 'completed' ),
			array( 'id' => absint( $request_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Reduce Dokan vendor totals and vendor balance for a refunded item.
	 *
	 * @param WC_Order            $order Order.
	 * @param WC_Order_Item       $item Line item.
	 * @param float               $item_refund_total Refund incl. tax.
	 * @param float               $refund_ratio Ratio of line total.
	 * @param string              $reason_label Balance particulars label.
	 * @return float Vendor earning adjusted.
	 */
	public static function adjust_dokan_vendor_for_item( $order, $item, $item_refund_total, $refund_ratio, $reason_label = '' ) {
		global $wpdb;

		$vendor_id = self::get_item_vendor_id( $item, $order );
		if ( ! $vendor_id || $item_refund_total <= 0 ) {
			return 0.0;
		}

		$table = $wpdb->prefix . 'dokan_orders';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, order_total, net_amount FROM {$table} WHERE order_id = %d AND seller_id = %d LIMIT 1",
				$order->get_id(),
				$vendor_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return 0.0;
		}

		$current_order_total = max( 0, (float) $row['order_total'] );
		$current_net_amount  = max( 0, (float) $row['net_amount'] );
		$vendor_refund       = self::get_item_vendor_earning_refund( $order, $item, $item_refund_total, $refund_ratio, $current_order_total, $current_net_amount );
		$vendor_refund       = min( $vendor_refund, $current_net_amount );

		$new_order_total = max( 0, $current_order_total - $item_refund_total );
		$new_net_amount  = max( 0, $current_net_amount - $vendor_refund );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'order_total' => wc_format_decimal( $new_order_total ),
				'net_amount'  => wc_format_decimal( $new_net_amount ),
			),
			array( 'id' => absint( $row['id'] ) ),
			array( '%f', '%f' ),
			array( '%d' )
		);

		self::insert_dokan_vendor_balance_refund( $order, $vendor_id, $vendor_refund, $item->get_name(), $reason_label );
		self::clear_dokan_order_cache( $order->get_id(), $vendor_id );

		return $vendor_refund;
	}

	/**
	 * Get an item's vendor ID.
	 *
	 * @param WC_Order_Item $item Line item.
	 * @param WC_Order      $order Order.
	 * @return int
	 */
	public static function get_item_vendor_id( $item, $order ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return absint( $order->get_meta( '_dokan_vendor_id', true ) );
		}

		$product = $item->get_product();
		if ( $product ) {
			$product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
			$author_id  = absint( get_post_field( 'post_author', $product_id ) );
			if ( $author_id ) {
				return $author_id;
			}
		}

		return absint( $order->get_meta( '_dokan_vendor_id', true ) );
	}

	/**
	 * Resolve vendor earning reduction for the line item.
	 *
	 * @param WC_Order            $order Order.
	 * @param WC_Order_Item       $item Line item.
	 * @param float               $item_refund_total Refund amount incl. tax.
	 * @param float               $refund_ratio Line ratio.
	 * @param float               $current_order_total Dokan order total.
	 * @param float               $current_net_amount Dokan vendor earning.
	 * @return float
	 */
	public static function get_item_vendor_earning_refund( $order, $item, $item_refund_total, $refund_ratio, $current_order_total, $current_net_amount ) {
		if ( function_exists( 'dokan_get_container' ) && class_exists( '\WeDevs\Dokan\Commission\OrderCommission' ) ) {
			try {
				$commission = dokan_get_container()->get( \WeDevs\Dokan\Commission\OrderCommission::class );
				$commission->set_order( $order );
				$line_commission = $commission->get_commission_for_line_item( (int) $item->get_id() );
				if ( $line_commission && method_exists( $line_commission, 'get_vendor_net_earning' ) ) {
					$earning = (float) $line_commission->get_vendor_net_earning();
					if ( $earning > 0 ) {
						return min( $earning * max( 0, min( 1, $refund_ratio ) ), $item_refund_total );
					}
				}
			} catch ( Exception $exception ) {
				// Fall through to proportional adjustment.
			}
		}

		if ( $current_order_total <= 0 || $current_net_amount <= 0 ) {
			return 0.0;
		}

		return min( $item_refund_total * ( $current_net_amount / $current_order_total ), $current_net_amount );
	}

	/**
	 * Insert a Dokan vendor balance credit.
	 *
	 * @param WC_Order $order Order.
	 * @param int      $vendor_id Vendor user ID.
	 * @param float    $vendor_refund Vendor earning reduction.
	 * @param string   $item_name Item name.
	 * @param string   $reason_label Optional label.
	 */
	public static function insert_dokan_vendor_balance_refund( $order, $vendor_id, $vendor_refund, $item_name, $reason_label = '' ) {
		global $wpdb;

		if ( $vendor_refund <= 0 ) {
			return;
		}

		$table = $wpdb->prefix . 'dokan_vendor_balance';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists !== $table ) {
			return;
		}

		$particulars = $reason_label
			? sprintf(
				/* translators: 1: reason, 2: item name, 3: order ID */
				__( '%1$s for %2$s on order #%3$d', 'consucorner' ),
				$reason_label,
				$item_name,
				$order->get_id()
			)
			: sprintf(
				/* translators: 1: item name, 2: order ID */
				__( 'Wallet refund for %1$s on order #%2$d', 'consucorner' ),
				$item_name,
				$order->get_id()
			);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$table,
			array(
				'vendor_id'    => $vendor_id,
				'trn_id'       => $order->get_id(),
				'trn_type'     => 'dokan_refund',
				'perticulars'  => $particulars,
				'debit'        => 0,
				'credit'       => wc_format_decimal( $vendor_refund ),
				'status'       => 'approved',
				'trn_date'     => current_time( 'mysql' ),
				'balance_date' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%f', '%f', '%s', '%s', '%s' )
		);
	}

	/**
	 * Clear Dokan earning caches after direct table updates.
	 *
	 * @param int $order_id Order ID.
	 * @param int $vendor_id Vendor ID.
	 */
	public static function clear_dokan_order_cache( $order_id, $vendor_id ) {
		if ( class_exists( '\WeDevs\Dokan\Cache' ) ) {
			\WeDevs\Dokan\Cache::delete( "get_earning_from_order_table_{$order_id}_seller" );
			\WeDevs\Dokan\Cache::delete( "get_earning_from_order_table_{$order_id}_admin" );
			\WeDevs\Dokan\Cache::delete( "seller_earnings_{$vendor_id}_" . current_time( 'Y_m_d' ), "seller_order_data_{$vendor_id}" );
			\WeDevs\Dokan\Cache::delete( "earning_{$vendor_id}", "seller_order_data_{$vendor_id}" );
		}
	}
}

CC_Returns_Refund_Service::init();
