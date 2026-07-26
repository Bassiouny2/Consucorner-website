<?php
/**
 * Admin wallet operations for WooCommerce orders.
 *
 * Adds a lightweight "Wallet Operations: Partial Refund & Exchange" meta box
 * for sending selected order item refunds to the site's custom wallet.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

final class CC_Admin_Wallet_Refunds {
	const NONCE_ACTION     = 'cc_wallet_refund_to_wallet';
	const AJAX_ACTION      = 'cc_process_wallet_refund';
	const ITEM_META_FLAG   = CC_Returns_Refund_Service::ITEM_META_FLAG;
	const ITEM_META_AMOUNT = CC_Returns_Refund_Service::ITEM_META_AMOUNT;
	const ITEM_META_QTY    = CC_Returns_Refund_Service::ITEM_META_QTY;
	const ITEM_META_DATE   = CC_Returns_Refund_Service::ITEM_META_DATE;

	/**
	 * Bootstrap hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_process_wallet_refund' ) );
	}

	/**
	 * Register the meta box on classic and HPOS order edit screens.
	 *
	 * @param string $screen_id Current screen/post type.
	 * @param mixed  $post_or_order Post or WC_Order object.
	 */
	public static function register_meta_box( $screen_id, $post_or_order ) {
		if ( ! self::is_order_edit_screen_id( $screen_id ) ) {
			return;
		}

		$order = self::get_order_from_context( $post_or_order );
		if ( ! $order ) {
			return;
		}

		add_meta_box(
			'cc-wallet-refund-operations',
			__( 'Wallet Operations: Partial Refund & Exchange', 'consucorner' ),
			array( __CLASS__, 'render_meta_box' ),
			$screen_id,
			'side',
			'high'
		);
	}

	/**
	 * Enqueue small inline assets only on WooCommerce order admin screens.
	 *
	 * @param string $hook Current admin hook.
	 */
	public static function enqueue_admin_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! self::is_order_edit_screen_id( $screen->id ) ) {
			return;
		}

		wp_register_style( 'cc-admin-wallet-refunds', false, array(), defined( '_S_VERSION' ) ? _S_VERSION : '1.0.0' );
		wp_enqueue_style( 'cc-admin-wallet-refunds' );
		wp_add_inline_style( 'cc-admin-wallet-refunds', self::admin_css() );

		wp_register_script( 'cc-admin-wallet-refunds', false, array(), defined( '_S_VERSION' ) ? _S_VERSION : '1.0.0', true );
		wp_enqueue_script( 'cc-admin-wallet-refunds' );
		wp_add_inline_script(
			'cc-admin-wallet-refunds',
			'window.ccWalletRefunds=' . wp_json_encode(
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'action'     => self::AJAX_ACTION,
					'nonce'      => wp_create_nonce( self::NONCE_ACTION ),
					'processing' => __( 'Processing...', 'consucorner' ),
					'buttonText' => __( 'Process Refund to Wallet', 'consucorner' ),
				)
			) . ';' . self::admin_js()
		);
	}

	/**
	 * Render meta box shell.
	 *
	 * @param mixed $post_or_order Post or WC_Order object.
	 */
	public static function render_meta_box( $post_or_order ) {
		$order = self::get_order_from_context( $post_or_order );
		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Unable to load this order.', 'consucorner' ) . '</p>';
			return;
		}

		echo self::render_box_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Process selected wallet refund items.
	 */
	public static function ajax_process_wallet_refund() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! current_user_can( 'edit_shop_order', $order_id ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit orders.', 'consucorner' ) ), 403 );
		}

		$order    = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'consucorner' ) ), 404 );
		}

		$customer_id = absint( $order->get_customer_id() );
		if ( ! $customer_id ) {
			wp_send_json_error( array( 'message' => __( 'This order is not attached to a registered customer wallet.', 'consucorner' ) ), 400 );
		}

		$item_ids = isset( $_POST['item_ids'] ) ? (array) wp_unslash( $_POST['item_ids'] ) : array();
		$item_ids = array_values( array_filter( array_map( 'absint', $item_ids ) ) );
		if ( empty( $item_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select at least one item to refund.', 'consucorner' ) ), 400 );
		}

		$shipping_deduction = isset( $_POST['shipping_deduction'] ) ? wc_format_decimal( wp_unslash( $_POST['shipping_deduction'] ) ) : 0;
		$shipping_deduction = max( 0, (float) $shipping_deduction );
		$restock_items      = ! empty( $_POST['restock_items'] );
		$refundable_items   = self::get_refundable_items( $order );
		$item_quantities_raw = isset( $_POST['item_quantities'] ) ? (array) wp_unslash( $_POST['item_quantities'] ) : array();
		$item_quantities     = array_map( 'absint', $item_quantities_raw );
		$selected           = array();
		$items_total        = 0.0;

		foreach ( $item_ids as $item_id ) {
			if ( empty( $refundable_items[ $item_id ] ) ) {
				continue;
			}

			if ( class_exists( 'CC_Returns_Refund_Service' ) ) {
				$block_reason = CC_Returns_Refund_Service::get_item_block_reason( $order, $item_id );
				if ( $block_reason ) {
					wp_send_json_error( array( 'message' => $block_reason ), 409 );
				}
			}

			$requested_qty = isset( $item_quantities[ $item_id ] ) ? wc_stock_amount( $item_quantities[ $item_id ] ) : 0;
			$max_qty       = wc_stock_amount( $refundable_items[ $item_id ]['qty'] );
			if ( $requested_qty <= 0 || $requested_qty > $max_qty ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: 1: item name, 2: max quantity */
							__( 'Refund quantity for %1$s must be between 1 and %2$s.', 'consucorner' ),
							$refundable_items[ $item_id ]['name'],
							$max_qty
						),
					),
					400
				);
			}

			$line_refund_total = min(
				(float) $refundable_items[ $item_id ]['refund_total'],
				(float) $refundable_items[ $item_id ]['unit_refund_total'] * $requested_qty
			);

			$selected[ $item_id ]                  = $refundable_items[ $item_id ];
			$selected[ $item_id ]['selected_qty']  = $requested_qty;
			$selected[ $item_id ]['refund_total']  = $line_refund_total;
			$selected[ $item_id ]['refund_ratio']  = (float) $refundable_items[ $item_id ]['original_total'] > 0
				? min( 1, $line_refund_total / (float) $refundable_items[ $item_id ]['original_total'] )
				: 0;
			$items_total                          += $line_refund_total;
		}

		if ( empty( $selected ) ) {
			wp_send_json_error( array( 'message' => __( 'The selected items are no longer refundable to wallet.', 'consucorner' ) ), 409 );
		}

		if ( $shipping_deduction > $items_total ) {
			wp_send_json_error( array( 'message' => __( 'Shipping deduction cannot be greater than the selected item refund total.', 'consucorner' ) ), 400 );
		}

		$wallet_amount = max( 0, $items_total - $shipping_deduction );
		if ( $wallet_amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Refund amount must be greater than zero after shipping deduction.', 'consucorner' ) ), 400 );
		}

		$item_names = array_map(
			static function ( $data ) {
				return sprintf( '%sx %s', wc_stock_amount( $data['selected_qty'] ), $data['name'] );
			},
			$selected
		);
		$wallet_note = sprintf(
			/* translators: 1: order ID, 2: item names */
			__( 'Wallet refund for order #%1$d: %2$s', 'consucorner' ),
			$order->get_id(),
			implode( ', ', $item_names )
		);

		$wallet_result = self::deposit_to_custom_wallet( $customer_id, $wallet_amount, $wallet_note, $order );
		if ( is_wp_error( $wallet_result ) ) {
			wp_send_json_error( array( 'message' => $wallet_result->get_error_message() ), 500 );
		}

		$restock_payload = array();
		$dokan_adjusted  = 0.0;
		$current_user    = wp_get_current_user();
		$refunded_by     = $current_user && $current_user->exists()
			? sprintf( '%s (%s)', $current_user->display_name, $current_user->user_login )
			: __( 'Unknown admin', 'consucorner' );
		$refunded_at     = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) );

		foreach ( $selected as $item_id => $data ) {
			$item             = $data['item'];
			$previous_qty     = (float) $item->get_meta( self::ITEM_META_QTY, true );
			$previous_amount  = (float) $item->get_meta( self::ITEM_META_AMOUNT, true );
			$new_wallet_qty   = $previous_qty + (float) $data['selected_qty'];
			$new_wallet_total = $previous_amount + (float) $data['refund_total'];

			$item->update_meta_data( self::ITEM_META_QTY, wc_format_decimal( $new_wallet_qty ) );
			$item->update_meta_data( self::ITEM_META_AMOUNT, wc_format_decimal( $new_wallet_total ) );
			$item->update_meta_data( self::ITEM_META_DATE, current_time( 'mysql' ) );
			$item->update_meta_data( self::ITEM_META_FLAG, $new_wallet_qty >= (float) $data['wallet_refundable_qty'] ? 'yes' : 'partial' );
			if ( defined( 'CC_WALLET_LEGACY_SYNC_META' ) ) {
				$item->update_meta_data( CC_WALLET_LEGACY_SYNC_META, 'yes' );
			}
			$item->save();

			if ( $restock_items && $data['selected_qty'] > 0 ) {
				$restock_payload[ $item_id ] = array( 'qty' => $data['selected_qty'] );
			}

			$dokan_adjusted += CC_Returns_Refund_Service::adjust_dokan_vendor_for_item(
				$order,
				$item,
				(float) $data['refund_total'],
				(float) $data['refund_ratio'],
				__( 'Wallet refund', 'consucorner' )
			);

			$order->add_order_note(
				sprintf(
					/* translators: 1: admin user, 2: date/time, 3: item name, 4: quantity, 5: refund amount, 6: shipping deduction */
					__( 'Wallet refund processed by %1$s on %2$s. Item: %3$s. Quantity refunded: %4$s. Amount: %5$s. Shipping deducted: %6$s.', 'consucorner' ),
					$refunded_by,
					$refunded_at,
					$data['name'],
					wc_stock_amount( $data['selected_qty'] ),
					wc_price( $data['refund_total'], array( 'currency' => $order->get_currency() ) ),
					wc_price( $shipping_deduction, array( 'currency' => $order->get_currency() ) )
				)
			);
		}

		if ( $restock_items && ! empty( $restock_payload ) && function_exists( 'wc_restock_refunded_items' ) ) {
			wc_restock_refunded_items( $order, $restock_payload );
		}

		$order->save();

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: wallet amount */
					__( 'Wallet refund processed successfully. Wallet amount: %s.', 'consucorner' ),
					wp_strip_all_tags( wc_price( $wallet_amount, array( 'currency' => $order->get_currency() ) ) )
				),
				'html'    => self::render_box_html( $order, 'success' ),
				'totals'  => array(
					'wallet_amount'  => wc_format_decimal( $wallet_amount ),
					'dokan_adjusted' => wc_format_decimal( $dokan_adjusted ),
				),
			)
		);
	}

	/**
	 * Render the inner box HTML.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $notice_type Optional notice type.
	 * @return string
	 */
	private static function render_box_html( $order, $notice_type = '' ) {
		$items           = self::get_refundable_items( $order );
		$open_requests   = class_exists( 'CC_Returns_Refund_Service' ) ? CC_Returns_Refund_Service::get_open_order_requests( $order->get_id() ) : array();
		$blocked_items   = class_exists( 'CC_Returns_Refund_Service' ) ? CC_Returns_Refund_Service::get_wallet_blocked_items( $order ) : array();

		ob_start();
		?>
		<div class="cc-wallet-refunds-box" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
			<div class="cc-wallet-refunds-notice" aria-live="polite" <?php echo $notice_type ? '' : 'hidden'; ?>>
				<?php if ( 'success' === $notice_type ) : ?>
					<?php esc_html_e( 'Refund saved. Refunded items were removed from this list.', 'consucorner' ); ?>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $open_requests ) ) : ?>
				<p class="cc-wallet-refunds-rma-notice" style="margin:0 0 10px;padding:8px 10px;background:#fff8e5;border-left:3px solid #dba617;">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of open RMA requests */
							_n(
								'This order has %d open return request. Resolve it under WooCommerce → Returns to avoid duplicate refunds.',
								'This order has %d open return requests. Resolve them under WooCommerce → Returns to avoid duplicate refunds.',
								count( $open_requests ),
								'consucorner'
							),
							count( $open_requests )
						)
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( empty( $items ) ) : ?>
				<p class="cc-wallet-refunds-empty">
					<?php esc_html_e( 'No remaining order items are available for wallet refund.', 'consucorner' ); ?>
				</p>
			<?php else : ?>
				<table class="widefat cc-wallet-refunds-table">
					<thead>
						<tr>
							<th class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Select item', 'consucorner' ); ?></span></th>
							<th><?php esc_html_e( 'Item', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Available', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Refund Qty', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Available Total', 'consucorner' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $item_id => $data ) : ?>
							<?php
							$block_reason = isset( $blocked_items[ $item_id ] ) ? $blocked_items[ $item_id ] : '';
							$is_blocked   = (bool) $block_reason;
							?>
							<tr class="<?php echo $is_blocked ? 'cc-wallet-refunds-blocked' : ''; ?>">
								<td class="check-column">
									<input type="checkbox" class="cc-wallet-refunds-item" value="<?php echo esc_attr( $item_id ); ?>" <?php disabled( $is_blocked ); ?>>
								</td>
								<td>
									<strong><?php echo esc_html( $data['name'] ); ?></strong>
									<?php if ( $is_blocked ) : ?>
										<br><span class="description"><?php echo esc_html( $block_reason ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( wc_stock_amount( $data['qty'] ) ); ?></td>
								<td>
									<input
										type="number"
										min="1"
										max="<?php echo esc_attr( wc_stock_amount( $data['qty'] ) ); ?>"
										step="1"
										value="<?php echo esc_attr( wc_stock_amount( $data['qty'] ) ); ?>"
										class="cc-wallet-refunds-qty"
										data-item-id="<?php echo esc_attr( $item_id ); ?>"
										aria-label="<?php echo esc_attr( sprintf( __( 'Refund quantity for %s', 'consucorner' ), $data['name'] ) ); ?>"
									>
								</td>
								<td><?php echo wp_kses_post( wc_price( $data['refund_total'], array( 'currency' => $order->get_currency() ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="cc-wallet-refunds-field">
					<label for="cc-wallet-shipping-deduction-<?php echo esc_attr( $order->get_id() ); ?>">
						<?php esc_html_e( 'Deduct Shipping Cost (Amount)', 'consucorner' ); ?>
					</label>
					<input type="number" min="0" step="0.01" id="cc-wallet-shipping-deduction-<?php echo esc_attr( $order->get_id() ); ?>" class="cc-wallet-refunds-shipping" placeholder="0.00">
				</div>

				<label class="cc-wallet-refunds-check">
					<input type="checkbox" class="cc-wallet-refunds-restock" value="1">
					<span><?php esc_html_e( 'Restock refunded items', 'consucorner' ); ?></span>
				</label>

				<button type="button" class="button button-primary cc-wallet-refunds-submit">
					<?php esc_html_e( 'Process Refund to Wallet', 'consucorner' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Return line items still available for wallet refund.
	 *
	 * @param WC_Order $order Order object.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_refundable_items( $order ) {
		$items = array();

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			if ( class_exists( 'CC_Returns_Refund_Service' ) ) {
				$resolution = (string) $item->get_meta( CC_Returns_Refund_Service::ITEM_META_RESOLUTION, true );
				if ( $resolution ) {
					continue;
				}
			}

			if ( 'yes' === $item->get_meta( self::ITEM_META_FLAG, true ) ) {
				continue;
			}

			$qty                 = (float) $item->get_quantity();
			$refunded_qty        = abs( (float) $order->get_qty_refunded_for_item( $item_id ) );
			$wallet_refunded_qty = max( 0, (float) $item->get_meta( self::ITEM_META_QTY, true ) );
			$remaining_qty       = max( 0, $qty - $refunded_qty - $wallet_refunded_qty );
			if ( $remaining_qty <= 0 ) {
				continue;
			}

			$item_total     = (float) $item->get_total();
			$item_tax       = (float) $item->get_total_tax();
			$original_total = max( 0, $item_total + $item_tax );
			$refunded_total = abs( (float) $order->get_total_refunded_for_item( $item_id ) );
			$refunded_tax   = 0.0;
			$item_taxes     = $item->get_taxes();
			foreach ( array_keys( $item_taxes['total'] ?? array() ) as $tax_id ) {
				$refunded_tax += abs( (float) $order->get_tax_refunded_for_item( $item_id, $tax_id ) );
			}

			$wallet_refunded_amount = max( 0, (float) $item->get_meta( self::ITEM_META_AMOUNT, true ) );
			$refund_total           = max( 0, $original_total - ( $refunded_total + $refunded_tax + $wallet_refunded_amount ) );
			if ( $refund_total <= 0 ) {
				continue;
			}

			$items[ $item_id ] = array(
				'item'                  => $item,
				'name'                  => $item->get_name(),
				'qty'                   => $remaining_qty,
				'wallet_refundable_qty' => $qty - $refunded_qty,
				'refund_total'          => $refund_total,
				'unit_refund_total'     => $remaining_qty > 0 ? $refund_total / $remaining_qty : 0,
				'original_total'        => $original_total,
			);
		}

		return $items;
	}

	/**
	 * Placeholder wallet integration.
	 *
	 * Replace/map cc_add_to_custom_wallet() with the real wallet deposit method.
	 *
	 * @param int      $user_id Customer user ID.
	 * @param float    $amount Wallet deposit amount.
	 * @param string   $note Wallet transaction note.
	 * @param WC_Order $order Order object.
	 * @return true|WP_Error
	 */
	private static function deposit_to_custom_wallet( $user_id, $amount, $note, $order ) {
		/*
		 * CUSTOM WALLET PLACEHOLDER:
		 * Map this function call to your real wallet system, for example:
		 *
		 * cc_add_to_custom_wallet( $user_id, $amount, $note );
		 */
		if ( function_exists( 'cc_add_to_custom_wallet' ) ) {
			$result = cc_add_to_custom_wallet( $user_id, $amount, $note, $order->get_id() );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		} else {
			do_action( 'cc_add_to_custom_wallet_placeholder', $user_id, $amount, $note, $order );
		}

		return true;
	}

	/**
	 * Get order object from meta box callback context.
	 *
	 * @param mixed $post_or_order Post or order.
	 * @return WC_Order|false
	 */
	private static function get_order_from_context( $post_or_order ) {
		if ( $post_or_order instanceof WC_Order ) {
			return $post_or_order;
		}

		$order_id = 0;
		if ( $post_or_order instanceof WP_Post ) {
			$order_id = absint( $post_or_order->ID );
		} elseif ( isset( $_GET['id'] ) ) {
			$order_id = absint( $_GET['id'] );
		} elseif ( isset( $_GET['post'] ) ) {
			$order_id = absint( $_GET['post'] );
		}

		return $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
	}

	/**
	 * Is the current screen a WooCommerce order edit screen?
	 *
	 * @param string $screen_id Screen ID.
	 * @return bool
	 */
	private static function is_order_edit_screen_id( $screen_id ) {
		$order_screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'woocommerce_page_wc-orders';

		return in_array( $screen_id, array( 'shop_order', $order_screen, 'woocommerce_page_wc-orders' ), true );
	}

	/**
	 * Inline admin CSS.
	 *
	 * @return string
	 */
	private static function admin_css() {
		return '
			#cc-wallet-refund-operations .inside { margin: 0; padding: 12px; }
			.cc-wallet-refunds-box { color: #1d2327; }
			.cc-wallet-refunds-notice { margin: 0 0 10px; padding: 8px 10px; border-left: 4px solid #00a32a; background: #f0f6ef; font-size: 12px; }
			.cc-wallet-refunds-empty { margin: 4px 0; color: #646970; }
			.cc-wallet-refunds-table { margin: 0 0 12px; border-radius: 6px; overflow: hidden; }
			.cc-wallet-refunds-table th, .cc-wallet-refunds-table td { padding: 8px 6px; vertical-align: middle; font-size: 12px; }
			.cc-wallet-refunds-table .check-column { width: 28px; padding-left: 8px; }
			.cc-wallet-refunds-qty { width: 58px; min-height: 30px; }
			.cc-wallet-refunds-field { margin: 12px 0; }
			.cc-wallet-refunds-field label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 12px; }
			.cc-wallet-refunds-field input { width: 100%; min-height: 34px; }
			.cc-wallet-refunds-check { display: flex; gap: 7px; align-items: center; margin: 0 0 12px; font-size: 12px; }
			.cc-wallet-refunds-submit { width: 100%; justify-content: center; min-height: 34px; }
			.cc-wallet-refunds-submit.is-busy { cursor: progress; opacity: .75; }
			.cc-wallet-refunds-error { margin: 0 0 10px; padding: 8px 10px; border-left: 4px solid #d63638; background: #fcf0f1; color: #8a2424; font-size: 12px; }
		';
	}

	/**
	 * Inline admin JS.
	 *
	 * @return string
	 */
	private static function admin_js() {
		return <<<'JS'
(function () {
	'use strict';

	function setNotice(box, message, isError) {
		var existing = box.querySelector('.cc-wallet-refunds-error');
		if (existing) {
			existing.remove();
		}
		if (!message) {
			return;
		}
		var notice = document.createElement('div');
		notice.className = isError ? 'cc-wallet-refunds-error' : 'cc-wallet-refunds-notice';
		notice.setAttribute('aria-live', 'polite');
		notice.textContent = message;
		box.insertBefore(notice, box.firstChild);
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('.cc-wallet-refunds-submit');
		if (!button || button.disabled) {
			return;
		}

		var box = button.closest('.cc-wallet-refunds-box');
		if (!box || !window.ccWalletRefunds) {
			return;
		}

		var selected = Array.prototype.slice.call(box.querySelectorAll('.cc-wallet-refunds-item:checked')).map(function (input) {
			return input.value;
		});

		if (!selected.length) {
			setNotice(box, 'Please select at least one item to refund.', true);
			return;
		}

		var payload = new window.FormData();
		payload.append('action', window.ccWalletRefunds.action);
		payload.append('nonce', window.ccWalletRefunds.nonce);
		payload.append('order_id', box.getAttribute('data-order-id') || '');
		payload.append('shipping_deduction', (box.querySelector('.cc-wallet-refunds-shipping') || {}).value || '0');
		payload.append('restock_items', box.querySelector('.cc-wallet-refunds-restock:checked') ? '1' : '');
		selected.forEach(function (itemId) {
			var qtyInput = box.querySelector('.cc-wallet-refunds-qty[data-item-id="' + itemId + '"]');
			payload.append('item_ids[]', itemId);
			payload.append('item_quantities[' + itemId + ']', qtyInput ? qtyInput.value : '0');
		});

		button.disabled = true;
		button.classList.add('is-busy');
		button.textContent = window.ccWalletRefunds.processing;
		setNotice(box, '', false);

		window.fetch(window.ccWalletRefunds.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: payload
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (response) {
				if (!response || !response.success) {
					throw new Error(response && response.data && response.data.message ? response.data.message : 'Unable to process wallet refund.');
				}
				if (response.data && response.data.html) {
					box.outerHTML = response.data.html;
				} else {
					setNotice(box, response.data && response.data.message ? response.data.message : 'Wallet refund processed.', false);
				}
			})
			.catch(function (error) {
				button.disabled = false;
				button.classList.remove('is-busy');
				button.textContent = window.ccWalletRefunds.buttonText;
				setNotice(box, error.message || 'Unable to process wallet refund.', true);
			});
	});
})();
JS;
	}
}

CC_Admin_Wallet_Refunds::init();
