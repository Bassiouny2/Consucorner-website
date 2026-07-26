<?php
/**
 * Lightweight customer wallet helpers and admin UI.
 *
 * Stores wallet balances in user meta and keeps a small transaction history
 * for admin review and customer self-service display.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

const CC_WALLET_BALANCE_META     = '_cc_wallet_balance';
const CC_WALLET_TRANSACTIONS_META = '_cc_wallet_transactions';
const CC_WALLET_LEGACY_SYNC_META = '_cc_wallet_refund_wallet_synced';

/**
 * Get a customer's wallet balance.
 *
 * @param int $user_id User ID.
 * @return float
 */
function cc_get_custom_wallet_balance( $user_id ) {
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return 0.0;
	}

	return max( 0, (float) get_user_meta( $user_id, CC_WALLET_BALANCE_META, true ) );
}

/**
 * Get recent wallet transactions.
 *
 * @param int $user_id User ID.
 * @param int $limit Number of entries to return.
 * @return array<int,array<string,mixed>>
 */
function cc_get_custom_wallet_transactions( $user_id, $limit = 20 ) {
	$transactions = get_user_meta( absint( $user_id ), CC_WALLET_TRANSACTIONS_META, true );
	$transactions = is_array( $transactions ) ? $transactions : array();

	return array_slice( $transactions, 0, max( 1, absint( $limit ) ) );
}

/**
 * Add credit to a customer's wallet.
 *
 * This is the function used by wallet refund operations.
 *
 * @param int    $user_id User ID.
 * @param float  $amount Credit amount.
 * @param string $note Transaction note.
 * @param int    $order_id Optional related order ID.
 * @return true|WP_Error
 */
function cc_add_to_custom_wallet( $user_id, $amount, $note = '', $order_id = 0, $transaction_id = '' ) {
	return cc_adjust_custom_wallet_balance( $user_id, abs( (float) $amount ), $note, 'credit', absint( $order_id ), $transaction_id );
}

/**
 * Debit a customer's wallet.
 *
 * @param int    $user_id User ID.
 * @param float  $amount Debit amount.
 * @param string $note Transaction note.
 * @param int    $order_id Optional related order ID.
 * @param string $transaction_id Optional idempotency key.
 * @return true|WP_Error
 */
function cc_debit_custom_wallet( $user_id, $amount, $note = '', $order_id = 0, $transaction_id = '' ) {
	return cc_adjust_custom_wallet_balance( $user_id, -abs( (float) $amount ), $note, 'debit', absint( $order_id ), $transaction_id );
}

/**
 * Set a customer's wallet balance directly.
 *
 * @param int    $user_id User ID.
 * @param float  $new_balance New balance.
 * @param string $note Transaction note.
 * @return true|WP_Error
 */
function cc_set_custom_wallet_balance( $user_id, $new_balance, $note = '' ) {
	$current = cc_get_custom_wallet_balance( $user_id );
	$delta   = max( 0, (float) $new_balance ) - $current;

	return cc_adjust_custom_wallet_balance( $user_id, $delta, $note, 'set_balance', 0 );
}

/**
 * Adjust the wallet balance and record the transaction.
 *
 * @param int    $user_id User ID.
 * @param float  $delta Positive or negative amount.
 * @param string $note Transaction note.
 * @param string $type Transaction type.
 * @param int    $order_id Optional related order ID.
 * @param string $transaction_id Optional idempotency key.
 * @return true|WP_Error
 */
function cc_adjust_custom_wallet_balance( $user_id, $delta, $note = '', $type = 'manual', $order_id = 0, $transaction_id = '' ) {
	$user_id = absint( $user_id );
	$user    = $user_id ? get_userdata( $user_id ) : false;
	if ( ! $user ) {
		return new WP_Error( 'cc_wallet_user_not_found', __( 'Wallet user not found.', 'consucorner' ) );
	}

	$transaction_id = sanitize_key( $transaction_id );
	if ( $transaction_id && cc_custom_wallet_transaction_exists( $user_id, $transaction_id ) ) {
		return true;
	}

	$delta       = (float) $delta;
	$old_balance = cc_get_custom_wallet_balance( $user_id );
	$new_balance = max( 0, $old_balance + $delta );
	$actual_delta = $new_balance - $old_balance;

	update_user_meta( $user_id, CC_WALLET_BALANCE_META, wc_format_decimal( $new_balance ) );
	cc_record_custom_wallet_transaction(
		$user_id,
		array(
			'type'        => sanitize_key( $type ),
			'amount'      => $actual_delta,
			'balance'     => $new_balance,
			'note'        => $note ? wp_kses_post( $note ) : __( 'Wallet balance updated.', 'consucorner' ),
			'order_id'    => absint( $order_id ),
			'admin_id'    => get_current_user_id(),
			'created_at'  => current_time( 'mysql' ),
			'transaction_id' => $transaction_id,
		)
	);

	return true;
}

/**
 * Record a wallet transaction.
 *
 * @param int   $user_id User ID.
 * @param array $entry Transaction data.
 */
function cc_record_custom_wallet_transaction( $user_id, array $entry ) {
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return;
	}

	$transactions = get_user_meta( $user_id, CC_WALLET_TRANSACTIONS_META, true );
	$transactions = is_array( $transactions ) ? $transactions : array();
	array_unshift(
		$transactions,
		wp_parse_args(
			$entry,
			array(
				'type'       => 'manual',
				'amount'     => 0,
				'balance'    => cc_get_custom_wallet_balance( $user_id ),
				'note'       => '',
				'order_id'   => 0,
				'admin_id'   => 0,
				'created_at' => current_time( 'mysql' ),
				'transaction_id' => '',
			)
		)
	);

	update_user_meta( $user_id, CC_WALLET_TRANSACTIONS_META, array_slice( $transactions, 0, 100 ) );
}

/**
 * Check if a wallet transaction was already recorded.
 *
 * @param int    $user_id User ID.
 * @param string $transaction_id Idempotency key.
 * @return bool
 */
function cc_custom_wallet_transaction_exists( $user_id, $transaction_id ) {
	$transaction_id = sanitize_key( $transaction_id );
	if ( '' === $transaction_id ) {
		return false;
	}

	foreach ( cc_get_custom_wallet_transactions( $user_id, 100 ) as $entry ) {
		if ( ! empty( $entry['transaction_id'] ) && $transaction_id === $entry['transaction_id'] ) {
			return true;
		}
	}

	return false;
}

/**
 * Is wallet credit enabled for the current checkout session?
 *
 * @return bool
 */
function cc_checkout_wallet_is_enabled() {
	return function_exists( 'WC' ) && WC()->session && 'yes' === WC()->session->get( 'cc_use_wallet_credit' );
}

/**
 * Calculate how much wallet credit can be applied to the cart.
 *
 * @param WC_Cart|null $cart Cart object.
 * @param int          $user_id Optional user ID.
 * @return float
 */
function cc_get_checkout_wallet_credit_amount( $cart = null, $user_id = 0 ) {
	if ( ! function_exists( 'WC' ) ) {
		return 0.0;
	}

	$cart    = $cart instanceof WC_Cart ? $cart : WC()->cart;
	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
	if ( ! $cart || ! $user_id ) {
		return 0.0;
	}

	$balance = cc_get_custom_wallet_balance( $user_id );
	if ( $balance <= 0 ) {
		return 0.0;
	}

	$payable = (float) $cart->get_cart_contents_total()
		+ (float) $cart->get_shipping_total()
		+ (float) $cart->get_cart_contents_tax()
		+ (float) $cart->get_shipping_tax();

	return max( 0, min( $balance, $payable ) );
}

/**
 * Apply wallet credit as a negative WooCommerce fee.
 *
 * @param WC_Cart $cart Cart object.
 */
function cc_apply_checkout_wallet_credit_fee( $cart ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}

	if ( ! is_user_logged_in() || ! cc_checkout_wallet_is_enabled() ) {
		return;
	}

	$amount = cc_get_checkout_wallet_credit_amount( $cart );
	if ( $amount <= 0 ) {
		return;
	}

	$cart->add_fee( __( 'Wallet Credit', 'consucorner' ), -1 * $amount, false );
}
add_action( 'woocommerce_cart_calculate_fees', 'cc_apply_checkout_wallet_credit_fee', 30 );

/**
 * AJAX: enable/disable wallet credit during checkout.
 */
function cc_ajax_toggle_checkout_wallet_credit() {
	check_ajax_referer( 'cc_checkout_wallet', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Please log in to use wallet credit.', 'consucorner' ) ), 401 );
	}

	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		wp_send_json_error( array( 'message' => __( 'Checkout session is not available.', 'consucorner' ) ), 400 );
	}

	$enabled = ! empty( $_POST['enabled'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) );
	WC()->session->set( 'cc_use_wallet_credit', $enabled ? 'yes' : 'no' );

	if ( WC()->cart ) {
		WC()->cart->calculate_totals();
	}

	wp_send_json_success(
		array(
			'enabled' => $enabled,
			'amount'  => wc_format_decimal( cc_get_checkout_wallet_credit_amount() ),
		)
	);
}
add_action( 'wp_ajax_cc_toggle_checkout_wallet_credit', 'cc_ajax_toggle_checkout_wallet_credit' );

/**
 * Ensure the wallet credit is still valid at checkout submit time.
 */
function cc_validate_checkout_wallet_credit() {
	if ( ! cc_checkout_wallet_is_enabled() ) {
		return;
	}

	if ( ! is_user_logged_in() || cc_get_checkout_wallet_credit_amount() <= 0 ) {
		wc_add_notice( __( 'Wallet credit is no longer available for this order.', 'consucorner' ), 'error' );
	}
}
add_action( 'woocommerce_checkout_process', 'cc_validate_checkout_wallet_credit' );

/**
 * Store wallet credit amount on the order before payment.
 *
 * @param WC_Order $order Order object.
 */
function cc_store_checkout_wallet_credit_on_order( $order ) {
	if ( ! cc_checkout_wallet_is_enabled() || ! is_user_logged_in() ) {
		return;
	}

	$amount = cc_get_checkout_wallet_credit_amount();
	if ( $amount <= 0 ) {
		return;
	}

	$order->update_meta_data( '_cc_wallet_credit_used', wc_format_decimal( $amount ) );
	$order->update_meta_data( '_cc_wallet_user_id', get_current_user_id() );
}
add_action( 'woocommerce_checkout_create_order', 'cc_store_checkout_wallet_credit_on_order', 20 );

/**
 * Debit wallet after order creation.
 *
 * @param int $order_id Order ID.
 */
function cc_debit_wallet_after_checkout_order( $order_id ) {
	$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
	if ( ! $order ) {
		return;
	}

	$amount  = (float) $order->get_meta( '_cc_wallet_credit_used', true );
	$user_id = absint( $order->get_meta( '_cc_wallet_user_id', true ) );
	if ( $amount <= 0 || ! $user_id ) {
		return;
	}

	$result = cc_debit_custom_wallet(
		$user_id,
		$amount,
		sprintf(
			/* translators: 1: order ID */
			__( 'Wallet credit used for order #%d', 'consucorner' ),
			$order_id
		),
		$order_id,
		'wallet_order_' . $order_id
	);

	if ( is_wp_error( $result ) ) {
		$order->add_order_note( sprintf( __( 'Wallet debit failed: %s', 'consucorner' ), $result->get_error_message() ) );
		return;
	}

	$order->add_order_note(
		sprintf(
			/* translators: 1: amount */
			__( 'Wallet credit charged: %s.', 'consucorner' ),
			wc_price( $amount, array( 'currency' => $order->get_currency() ) )
		)
	);

	if ( function_exists( 'WC' ) && WC()->session ) {
		WC()->session->set( 'cc_use_wallet_credit', 'no' );
	}
}
add_action( 'woocommerce_checkout_order_processed', 'cc_debit_wallet_after_checkout_order', 20 );

/**
 * Import older wallet refund item meta into the wallet balance once.
 *
 * @param int $limit Max orders to scan.
 * @return array<string,int|float>
 */
function cc_sync_legacy_wallet_refunds( $limit = 300 ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return array( 'orders_scanned' => 0, 'items_synced' => 0, 'amount_synced' => 0 );
	}

	$orders = wc_get_orders(
		array(
			'limit'   => max( 1, absint( $limit ) ),
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
			'status'  => array_keys( wc_get_order_statuses() ),
		)
	);

	$stats = array(
		'orders_scanned' => count( $orders ),
		'items_synced'   => 0,
		'amount_synced'  => 0.0,
	);

	foreach ( $orders as $order ) {
		if ( ! $order instanceof WC_Order ) {
			continue;
		}

		$user_id = absint( $order->get_customer_id() );
		if ( ! $user_id ) {
			continue;
		}

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$wallet_flag = (string) $item->get_meta( '_refunded_to_wallet', true );
			$amount      = (float) $item->get_meta( '_cc_wallet_refund_amount', true );
			$synced      = (string) $item->get_meta( CC_WALLET_LEGACY_SYNC_META, true );

			if ( 'yes' === $synced || ( 'yes' !== $wallet_flag && 'partial' !== $wallet_flag ) || $amount <= 0 ) {
				continue;
			}

			$transaction_id = sanitize_key( 'wallet_refund_' . $order->get_id() . '_' . $item_id );
			$result = cc_add_to_custom_wallet(
				$user_id,
				$amount,
				sprintf(
					/* translators: 1: order ID, 2: item name */
					__( 'Imported previous wallet refund from order #%1$d: %2$s', 'consucorner' ),
					$order->get_id(),
					$item->get_name()
				),
				$order->get_id(),
				$transaction_id
			);

			if ( is_wp_error( $result ) ) {
				continue;
			}

			$item->update_meta_data( CC_WALLET_LEGACY_SYNC_META, 'yes' );
			$item->save();
			$stats['items_synced']++;
			$stats['amount_synced'] += $amount;
		}
	}

	return $stats;
}

/**
 * Admin-only capability for editing customer wallets.
 *
 * @return bool
 */
function cc_current_user_can_edit_wallets() {
	return current_user_can( 'manage_options' );
}

/**
 * Capability for charging wallet credit from the WooCommerce order screen.
 *
 * @return bool
 */
function cc_current_user_can_charge_wallet_orders() {
	return current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
}

/**
 * Is a screen ID a WooCommerce order edit screen?
 *
 * @param string $screen_id Screen ID.
 * @return bool
 */
function cc_wallet_is_order_edit_screen_id( $screen_id ) {
	$order_screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'woocommerce_page_wc-orders';

	return in_array( $screen_id, array( 'shop_order', $order_screen, 'woocommerce_page_wc-orders' ), true );
}

/**
 * Get an order object from a metabox callback context.
 *
 * @param mixed $post_or_order Post or order.
 * @return WC_Order|false
 */
function cc_wallet_get_order_from_context( $post_or_order ) {
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
 * Register wallet charge metabox on order edit screens.
 *
 * @param string $screen_id Screen ID.
 * @param mixed  $post_or_order Post or order.
 */
function cc_register_order_wallet_charge_metabox( $screen_id, $post_or_order ) {
	if ( ! cc_wallet_is_order_edit_screen_id( $screen_id ) || ! cc_current_user_can_charge_wallet_orders() ) {
		return;
	}

	$order = cc_wallet_get_order_from_context( $post_or_order );
	if ( ! $order ) {
		return;
	}

	add_meta_box(
		'cc-order-wallet-charge',
		__( 'Customer Wallet Charge', 'consucorner' ),
		'cc_render_order_wallet_charge_metabox',
		$screen_id,
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'cc_register_order_wallet_charge_metabox', 25, 2 );

/**
 * Render wallet charge metabox.
 *
 * @param mixed $post_or_order Post or order.
 */
function cc_render_order_wallet_charge_metabox( $post_or_order ) {
	$order = cc_wallet_get_order_from_context( $post_or_order );
	if ( ! $order ) {
		echo '<p>' . esc_html__( 'Unable to load this order.', 'consucorner' ) . '</p>';
		return;
	}

	$user_id = absint( $order->get_customer_id() );
	if ( ! $user_id ) {
		echo '<p>' . esc_html__( 'Select and save a registered customer before charging wallet credit.', 'consucorner' ) . '</p>';
		return;
	}

	$balance        = cc_get_custom_wallet_balance( $user_id );
	$order_due      = max( 0, (float) $order->get_total() );
	$max_charge     = min( $balance, $order_due );
	$wallet_charged = (float) $order->get_meta( '_cc_wallet_admin_charged_total', true );
	?>
	<div class="cc-order-wallet-charge-box" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cc_charge_order_wallet' ) ); ?>">
		<p>
			<strong><?php esc_html_e( 'Wallet Balance:', 'consucorner' ); ?></strong><br>
			<?php echo wp_kses_post( wc_price( $balance, array( 'currency' => $order->get_currency() ) ) ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Current Order Due:', 'consucorner' ); ?></strong><br>
			<?php echo wp_kses_post( wc_price( $order_due, array( 'currency' => $order->get_currency() ) ) ); ?>
		</p>
		<?php if ( $wallet_charged > 0 ) : ?>
			<p>
				<strong><?php esc_html_e( 'Already Charged From Wallet:', 'consucorner' ); ?></strong><br>
				<?php echo wp_kses_post( wc_price( $wallet_charged, array( 'currency' => $order->get_currency() ) ) ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $max_charge <= 0 ) : ?>
			<p><?php esc_html_e( 'No wallet amount is available to charge for this order.', 'consucorner' ); ?></p>
		<?php else : ?>
			<div class="cc-order-wallet-charge-controls">
				<p>
					<label for="cc_order_wallet_amount"><?php esc_html_e( 'Amount to deduct', 'consucorner' ); ?></label>
					<input
						type="number"
						min="0.01"
						max="<?php echo esc_attr( wc_format_decimal( $max_charge ) ); ?>"
						step="0.01"
						name="wallet_amount"
						id="cc_order_wallet_amount"
						class="widefat"
						value="<?php echo esc_attr( wc_format_decimal( $max_charge ) ); ?>"
						required
					>
				</p>
				<p class="description">
					<?php
					printf(
						/* translators: 1: max charge */
						esc_html__( 'Maximum safe charge: %s. If there is a remaining order total, customer can pay it by Cash on Delivery.', 'consucorner' ),
						esc_html( wp_strip_all_tags( wc_price( $max_charge, array( 'currency' => $order->get_currency() ) ) ) )
					);
					?>
				</p>
				<p>
					<label for="cc_order_wallet_note"><?php esc_html_e( 'Operation note', 'consucorner' ); ?></label>
					<textarea name="wallet_note" id="cc_order_wallet_note" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'Optional reason or internal note', 'consucorner' ); ?>"></textarea>
				</p>
				<button type="button" class="button button-primary widefat cc-order-wallet-charge-submit">
					<?php esc_html_e( 'Deduct Wallet From Order', 'consucorner' ); ?>
				</button>
				<p class="cc-order-wallet-charge-message" aria-live="polite" hidden></p>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Process order wallet charge from admin.
 */
function cc_handle_order_wallet_charge() {
	if ( ! cc_current_user_can_charge_wallet_orders() ) {
		wp_die( esc_html__( 'You do not have permission to charge wallets.', 'consucorner' ) );
	}

	check_admin_referer( 'cc_charge_order_wallet' );

	$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	$order    = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
	if ( ! $order ) {
		wp_die( esc_html__( 'Order not found.', 'consucorner' ) );
	}

	$user_id = absint( $order->get_customer_id() );
	if ( ! $user_id ) {
		wp_die( esc_html__( 'This order is not attached to a registered customer wallet.', 'consucorner' ) );
	}

	$requested = isset( $_POST['wallet_amount'] ) ? (float) wc_format_decimal( wp_unslash( $_POST['wallet_amount'] ) ) : 0;
	$note      = isset( $_POST['wallet_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wallet_note'] ) ) : '';
	$balance   = cc_get_custom_wallet_balance( $user_id );
	$order_due = max( 0, (float) $order->get_total() );
	$amount    = min( max( 0, $requested ), $balance, $order_due );

	if ( $amount <= 0 ) {
		wp_die( esc_html__( 'Wallet charge amount must be greater than zero and within the safe limit.', 'consucorner' ) );
	}

	$fee = new WC_Order_Item_Fee();
	$fee->set_name( __( 'Wallet Credit', 'consucorner' ) );
	$fee->set_amount( -1 * $amount );
	$fee->set_total( -1 * $amount );
	$fee->add_meta_data( '_cc_wallet_order_charge', 'yes', true );
	$order->add_item( $fee );
	$order->calculate_totals( false );
	$order->save();

	$result = cc_debit_custom_wallet(
		$user_id,
		$amount,
		$note ? $note : sprintf(
			/* translators: 1: order ID */
			__( 'Wallet charged by operations team for order #%d', 'consucorner' ),
			$order_id
		),
		$order_id,
		'wallet_admin_order_' . $order_id . '_' . time()
	);

	if ( is_wp_error( $result ) ) {
		$order->remove_item( $fee->get_id() );
		$order->calculate_totals( false );
		$order->save();
		wp_die( esc_html( $result->get_error_message() ) );
	}

	$previous_charged = (float) $order->get_meta( '_cc_wallet_admin_charged_total', true );
	$order->update_meta_data( '_cc_wallet_admin_charged_total', wc_format_decimal( $previous_charged + $amount ) );
	$order->update_meta_data( '_cc_wallet_user_id', $user_id );
	$order->add_order_note(
		sprintf(
			/* translators: 1: amount, 2: admin name, 3: remaining order total */
			__( 'Wallet charged from admin dashboard: %1$s by %2$s. Remaining order total: %3$s.', 'consucorner' ),
			wc_price( $amount, array( 'currency' => $order->get_currency() ) ),
			wp_get_current_user()->display_name,
			wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) )
		)
	);
	$order->save();

	wp_safe_redirect(
		add_query_arg(
			array(
				'cc_wallet_order_charge' => 1,
			),
			method_exists( $order, 'get_edit_order_url' ) ? $order->get_edit_order_url() : admin_url( 'post.php?post=' . $order_id . '&action=edit' )
		)
	);
	exit;
}
add_action( 'admin_post_cc_charge_order_wallet', 'cc_handle_order_wallet_charge' );

/**
 * AJAX: process order wallet charge from admin order screen.
 */
function cc_ajax_order_wallet_charge() {
	if ( ! cc_current_user_can_charge_wallet_orders() ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to charge wallets.', 'consucorner' ) ), 403 );
	}

	check_ajax_referer( 'cc_charge_order_wallet', 'nonce' );

	$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	$amount   = isset( $_POST['wallet_amount'] ) ? (float) wc_format_decimal( wp_unslash( $_POST['wallet_amount'] ) ) : 0;
	$note     = isset( $_POST['wallet_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wallet_note'] ) ) : '';
	$result   = cc_process_order_wallet_charge( $order_id, $amount, $note );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_cc_charge_order_wallet', 'cc_ajax_order_wallet_charge' );

/**
 * Process the wallet charge and return data for UI/redirects.
 *
 * @param int    $order_id Order ID.
 * @param float  $requested Requested wallet amount.
 * @param string $note Operation note.
 * @return array|WP_Error
 */
function cc_process_order_wallet_charge( $order_id, $requested, $note = '' ) {
	$order = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
	if ( ! $order ) {
		return new WP_Error( 'cc_wallet_order_not_found', __( 'Order not found.', 'consucorner' ) );
	}

	$user_id = absint( $order->get_customer_id() );
	if ( ! $user_id ) {
		return new WP_Error( 'cc_wallet_no_customer', __( 'This order is not attached to a registered customer wallet.', 'consucorner' ) );
	}

	$balance   = cc_get_custom_wallet_balance( $user_id );
	$order_due = max( 0, (float) $order->get_total() );
	$amount    = min( max( 0, (float) $requested ), $balance, $order_due );

	if ( $amount <= 0 ) {
		return new WP_Error( 'cc_wallet_invalid_amount', __( 'Wallet charge amount must be greater than zero and within the safe limit.', 'consucorner' ) );
	}

	$fee = new WC_Order_Item_Fee();
	$fee->set_name( __( 'Wallet Credit', 'consucorner' ) );
	$fee->set_amount( -1 * $amount );
	$fee->set_total( -1 * $amount );
	$fee->add_meta_data( '_cc_wallet_order_charge', 'yes', true );
	$order->add_item( $fee );
	$order->calculate_totals( false );
	$order->save();

	$result = cc_debit_custom_wallet(
		$user_id,
		$amount,
		$note ? $note : sprintf(
			/* translators: 1: order ID */
			__( 'Wallet charged by operations team for order #%d', 'consucorner' ),
			$order_id
		),
		$order_id,
		'wallet_admin_order_' . $order_id . '_' . time()
	);

	if ( is_wp_error( $result ) ) {
		$order->remove_item( $fee->get_id() );
		$order->calculate_totals( false );
		$order->save();
		return $result;
	}

	$previous_charged = (float) $order->get_meta( '_cc_wallet_admin_charged_total', true );
	$order->update_meta_data( '_cc_wallet_admin_charged_total', wc_format_decimal( $previous_charged + $amount ) );
	$order->update_meta_data( '_cc_wallet_user_id', $user_id );
	$order->add_order_note(
		sprintf(
			/* translators: 1: amount, 2: admin name, 3: remaining order total */
			__( 'Wallet charged from admin dashboard: %1$s by %2$s. Remaining order total: %3$s.', 'consucorner' ),
			wc_price( $amount, array( 'currency' => $order->get_currency() ) ),
			wp_get_current_user()->display_name,
			wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) )
		)
	);
	$order->save();

	return array(
		'message'         => __( 'Wallet charge applied successfully.', 'consucorner' ),
		'charged'         => wc_format_decimal( $amount ),
		'remaining_total' => wc_format_decimal( $order->get_total() ),
	);
}

/**
 * Register admin wallet page.
 */
function cc_register_customer_wallet_admin_page() {
	add_users_page(
		__( 'Customer Wallets', 'consucorner' ),
		__( 'Customer Wallets', 'consucorner' ),
		'manage_options',
		'cc-customer-wallets',
		'cc_render_customer_wallet_admin_page'
	);
}
add_action( 'admin_menu', 'cc_register_customer_wallet_admin_page' );

/**
 * Handle admin wallet updates.
 */
function cc_handle_customer_wallet_admin_update() {
	if ( ! cc_current_user_can_edit_wallets() ) {
		wp_die( esc_html__( 'You do not have permission to edit wallets.', 'consucorner' ) );
	}

	check_admin_referer( 'cc_update_customer_wallet' );

	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	$mode    = isset( $_POST['wallet_mode'] ) ? sanitize_key( wp_unslash( $_POST['wallet_mode'] ) ) : 'adjust';
	$amount  = isset( $_POST['wallet_amount'] ) ? (float) wc_format_decimal( wp_unslash( $_POST['wallet_amount'] ) ) : 0;
	$note    = isset( $_POST['wallet_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wallet_note'] ) ) : '';
	$result  = null;

	if ( 'set' === $mode ) {
		$result = cc_set_custom_wallet_balance( $user_id, $amount, $note );
	} else {
		$result = cc_adjust_custom_wallet_balance( $user_id, $amount, $note, 'admin_adjustment', 0 );
	}

	$args = array(
		'page'    => 'cc-customer-wallets',
		'user_id' => $user_id,
	);

	if ( is_wp_error( $result ) ) {
		$args['cc_wallet_error'] = rawurlencode( $result->get_error_message() );
	} else {
		$args['cc_wallet_updated'] = 1;
	}

	wp_safe_redirect( add_query_arg( $args, admin_url( 'users.php' ) ) );
	exit;
}
add_action( 'admin_post_cc_update_customer_wallet', 'cc_handle_customer_wallet_admin_update' );

/**
 * Run a one-time automatic import for older wallet refunds when admins load wp-admin.
 */
function cc_maybe_auto_sync_legacy_wallet_refunds() {
	if ( ! is_admin() || ! cc_current_user_can_edit_wallets() || 'yes' === get_option( '_cc_wallet_legacy_refunds_synced', 'no' ) ) {
		return;
	}

	$stats = cc_sync_legacy_wallet_refunds( 500 );
	update_option( '_cc_wallet_legacy_refunds_synced', 'yes', false );
	update_option( '_cc_wallet_legacy_refunds_sync_stats', $stats, false );
}
add_action( 'admin_init', 'cc_maybe_auto_sync_legacy_wallet_refunds' );

/**
 * Manually re-run the legacy wallet refund sync.
 */
function cc_handle_customer_wallet_legacy_sync() {
	if ( ! cc_current_user_can_edit_wallets() ) {
		wp_die( esc_html__( 'You do not have permission to sync wallets.', 'consucorner' ) );
	}

	check_admin_referer( 'cc_sync_legacy_wallet_refunds' );

	$limit = isset( $_POST['sync_limit'] ) ? absint( $_POST['sync_limit'] ) : 500;
	$stats = cc_sync_legacy_wallet_refunds( $limit );
	update_option( '_cc_wallet_legacy_refunds_synced', 'yes', false );
	update_option( '_cc_wallet_legacy_refunds_sync_stats', $stats, false );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'                    => 'cc-customer-wallets',
				'cc_wallet_legacy_synced' => 1,
				'items_synced'            => absint( $stats['items_synced'] ),
				'amount_synced'           => rawurlencode( wc_format_decimal( $stats['amount_synced'] ) ),
			),
			admin_url( 'users.php' )
		)
	);
	exit;
}
add_action( 'admin_post_cc_sync_legacy_wallet_refunds', 'cc_handle_customer_wallet_legacy_sync' );

/**
 * Render customer wallet admin page.
 */
function cc_render_customer_wallet_admin_page() {
	if ( ! cc_current_user_can_edit_wallets() ) {
		wp_die( esc_html__( 'You do not have permission to view wallets.', 'consucorner' ) );
	}

	$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
	$users   = cc_get_wallet_admin_users( $search );
	$selected_user = $user_id ? get_userdata( $user_id ) : ( ! empty( $users ) ? $users[0] : false );

	?>
	<div class="wrap cc-wallet-admin">
		<h1><?php esc_html_e( 'Customer Wallets', 'consucorner' ); ?></h1>

		<?php if ( isset( $_GET['cc_wallet_updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Wallet updated successfully.', 'consucorner' ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['cc_wallet_error'] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['cc_wallet_error'] ) ) ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['cc_wallet_legacy_synced'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: 1: item count, 2: amount */
						esc_html__( 'Previous wallet refunds synced. Items: %1$d. Amount: %2$s.', 'consucorner' ),
						absint( $_GET['items_synced'] ?? 0 ),
						esc_html( wp_strip_all_tags( wc_price( (float) wc_format_decimal( (float) wp_unslash( $_GET['amount_synced'] ?? 0 ) ) ) ) )
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<div class="cc-wallet-admin-sync">
			<div>
				<strong><?php esc_html_e( 'Previous wallet refunds import', 'consucorner' ); ?></strong>
				<p><?php esc_html_e( 'Imports older order items already marked as refunded to wallet into customer wallet balances. Already synced items are skipped.', 'consucorner' ); ?></p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cc_sync_legacy_wallet_refunds' ); ?>
				<input type="hidden" name="action" value="cc_sync_legacy_wallet_refunds">
				<input type="hidden" name="sync_limit" value="1000">
				<?php submit_button( __( 'Sync Previous Refunds', 'consucorner' ), 'secondary', '', false ); ?>
			</form>
		</div>

		<form method="get" class="cc-wallet-admin-search">
			<input type="hidden" name="page" value="cc-customer-wallets">
			<label class="screen-reader-text" for="cc-wallet-user-search"><?php esc_html_e( 'Search customers', 'consucorner' ); ?></label>
			<input type="search" id="cc-wallet-user-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email, or username', 'consucorner' ); ?>">
			<?php submit_button( __( 'Search Customers', 'consucorner' ), 'secondary', '', false ); ?>
		</form>

		<div class="cc-wallet-admin-grid">
			<div class="cc-wallet-admin-card">
				<h2><?php esc_html_e( 'Customers', 'consucorner' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Customer', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Wallet Balance', 'consucorner' ); ?></th>
							<th><?php esc_html_e( 'Action', 'consucorner' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $users ) ) : ?>
							<tr><td colspan="3"><?php esc_html_e( 'No customers found.', 'consucorner' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $users as $user ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $user->display_name ); ?></strong><br>
										<span><?php echo esc_html( $user->user_email ); ?></span>
									</td>
									<td><?php echo wp_kses_post( wc_price( cc_get_custom_wallet_balance( $user->ID ) ) ); ?></td>
									<td>
										<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'cc-customer-wallets', 'user_id' => $user->ID, 's' => $search ), admin_url( 'users.php' ) ) ); ?>">
											<?php esc_html_e( 'View/Edit', 'consucorner' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div class="cc-wallet-admin-card">
				<?php if ( $selected_user instanceof WP_User ) : ?>
					<?php cc_render_customer_wallet_editor( $selected_user ); ?>
				<?php else : ?>
					<h2><?php esc_html_e( 'Wallet Details', 'consucorner' ); ?></h2>
					<p><?php esc_html_e( 'Select a customer to view or edit wallet balance.', 'consucorner' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Get users for admin wallet table.
 *
 * @param string $search Optional search string.
 * @return array<int,WP_User>
 */
function cc_get_wallet_admin_users( $search = '' ) {
	$args = array(
		'number'  => 50,
		'orderby' => 'registered',
		'order'   => 'DESC',
		'fields'  => 'all',
	);

	if ( '' !== $search ) {
		$args['search']         = '*' . esc_attr( $search ) . '*';
		$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
	}

	return get_users( $args );
}

/**
 * Render wallet editor panel.
 *
 * @param WP_User $user User object.
 */
function cc_render_customer_wallet_editor( $user ) {
	$balance      = cc_get_custom_wallet_balance( $user->ID );
	$transactions = cc_get_custom_wallet_transactions( $user->ID, 20 );
	?>
	<h2><?php echo esc_html( sprintf( __( 'Wallet: %s', 'consucorner' ), $user->display_name ) ); ?></h2>
	<p class="cc-wallet-balance-large"><?php echo wp_kses_post( wc_price( $balance ) ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cc-wallet-edit-form">
		<?php wp_nonce_field( 'cc_update_customer_wallet' ); ?>
		<input type="hidden" name="action" value="cc_update_customer_wallet">
		<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>">

		<p>
			<label for="cc-wallet-mode"><?php esc_html_e( 'Update Type', 'consucorner' ); ?></label>
			<select name="wallet_mode" id="cc-wallet-mode">
				<option value="adjust"><?php esc_html_e( 'Adjust balance (+ credit / - debit)', 'consucorner' ); ?></option>
				<option value="set"><?php esc_html_e( 'Set exact balance', 'consucorner' ); ?></option>
			</select>
		</p>
		<p>
			<label for="cc-wallet-amount"><?php esc_html_e( 'Amount', 'consucorner' ); ?></label>
			<input type="number" step="0.01" name="wallet_amount" id="cc-wallet-amount" class="regular-text" required>
		</p>
		<p>
			<label for="cc-wallet-note"><?php esc_html_e( 'Admin Note', 'consucorner' ); ?></label>
			<textarea name="wallet_note" id="cc-wallet-note" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Reason for wallet update', 'consucorner' ); ?>"></textarea>
		</p>
		<?php submit_button( __( 'Update Wallet', 'consucorner' ) ); ?>
	</form>

	<h3><?php esc_html_e( 'Recent Transactions', 'consucorner' ); ?></h3>
	<?php cc_render_wallet_transactions_table( $transactions, true ); ?>
	<?php
}

/**
 * Add wallet fields to wp-admin user profile screen.
 *
 * @param WP_User $user User object.
 */
function cc_render_customer_wallet_user_profile_fields( $user ) {
	$can_edit = cc_current_user_can_edit_wallets();
	?>
	<h2><?php esc_html_e( 'Customer Wallet', 'consucorner' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><?php esc_html_e( 'Current Balance', 'consucorner' ); ?></th>
			<td>
				<strong><?php echo wp_kses_post( wc_price( cc_get_custom_wallet_balance( $user->ID ) ) ); ?></strong>
				<?php if ( $can_edit ) : ?>
					<p class="description"><?php esc_html_e( 'Admins can adjust the wallet from the Customer Wallets page.', 'consucorner' ); ?></p>
					<p>
						<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'cc-customer-wallets', 'user_id' => $user->ID ), admin_url( 'users.php' ) ) ); ?>">
							<?php esc_html_e( 'Open Wallet Editor', 'consucorner' ); ?>
						</a>
					</p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Only admins can edit wallet balances.', 'consucorner' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'cc_render_customer_wallet_user_profile_fields' );
add_action( 'edit_user_profile', 'cc_render_customer_wallet_user_profile_fields' );

/**
 * Show current customer's wallet on My Account dashboard.
 */
function cc_render_customer_wallet_account_panel() {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$user_id      = get_current_user_id();
	$balance      = cc_get_custom_wallet_balance( $user_id );
	$transactions = cc_get_custom_wallet_transactions( $user_id, 5 );
	?>
	<div class="cc-account-wallet-panel">
		<div>
			<p class="cc-account-wallet-kicker"><?php esc_html_e( 'Your Wallet', 'consucorner' ); ?></p>
			<h2><?php echo wp_kses_post( wc_price( $balance ) ); ?></h2>
			<p><?php esc_html_e( 'Wallet credit can be used for refunds, exchanges, or future store credit based on admin approval.', 'consucorner' ); ?></p>
		</div>
		<div class="cc-account-wallet-history">
			<h3><?php esc_html_e( 'Recent wallet activity', 'consucorner' ); ?></h3>
			<?php cc_render_wallet_transactions_table( $transactions, false ); ?>
		</div>
	</div>
	<?php
}

/**
 * Render transaction table for admin/frontend.
 *
 * @param array $transactions Transaction rows.
 * @param bool  $show_admin Whether to show admin column.
 */
function cc_render_wallet_transactions_table( array $transactions, $show_admin = false ) {
	if ( empty( $transactions ) ) {
		echo '<p>' . esc_html__( 'No wallet transactions yet.', 'consucorner' ) . '</p>';
		return;
	}
	?>
	<table class="<?php echo $show_admin ? 'widefat striped' : 'cc-wallet-transactions'; ?>">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'consucorner' ); ?></th>
				<th><?php esc_html_e( 'Amount', 'consucorner' ); ?></th>
				<th><?php esc_html_e( 'Balance', 'consucorner' ); ?></th>
				<th><?php esc_html_e( 'Note', 'consucorner' ); ?></th>
				<?php if ( $show_admin ) : ?>
					<th><?php esc_html_e( 'Admin', 'consucorner' ); ?></th>
				<?php endif; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $transactions as $entry ) : ?>
				<?php
				$admin = ! empty( $entry['admin_id'] ) ? get_userdata( absint( $entry['admin_id'] ) ) : false;
				$date  = ! empty( $entry['created_at'] ) ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['created_at'] ) : '';
				?>
				<tr>
					<td><?php echo esc_html( $date ); ?></td>
					<td><?php echo wp_kses_post( wc_price( (float) ( $entry['amount'] ?? 0 ) ) ); ?></td>
					<td><?php echo wp_kses_post( wc_price( (float) ( $entry['balance'] ?? 0 ) ) ); ?></td>
					<td><?php echo esc_html( wp_strip_all_tags( (string) ( $entry['note'] ?? '' ) ) ); ?></td>
					<?php if ( $show_admin ) : ?>
						<td><?php echo esc_html( $admin ? $admin->display_name : __( 'System', 'consucorner' ) ); ?></td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Minimal admin/frontend styles for wallet panels.
 */
function cc_customer_wallet_inline_styles() {
	if ( ! is_admin() && function_exists( 'is_account_page' ) && ! is_account_page() ) {
		return;
	}

	?>
	<style>
		.cc-wallet-admin-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(320px,.9fr);gap:20px;margin-top:18px}
		.cc-wallet-admin-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
		.cc-wallet-admin-sync{display:flex;justify-content:space-between;gap:16px;align-items:center;background:#f6fbfa;border:1px solid #b9ebe4;border-radius:10px;padding:14px 16px;margin:16px 0 10px}
		.cc-wallet-admin-sync p{margin:4px 0 0;color:#646970}
		.cc-wallet-admin-search{display:flex;gap:8px;align-items:center;margin-top:16px}
		.cc-wallet-admin-search input[type=search]{min-width:320px}
		.cc-wallet-balance-large{font-size:28px;font-weight:700;margin:6px 0 18px;color:#008f83}
		.cc-wallet-edit-form label{display:block;font-weight:600;margin-bottom:5px}
		.cc-account-wallet-panel{margin:24px auto;padding:22px;border:1px solid rgba(0,200,179,.22);border-radius:18px;background:#fff;box-shadow:0 14px 35px rgba(15,23,42,.08);display:grid;grid-template-columns:minmax(0,.8fr) minmax(0,1.2fr);gap:22px}
		.cc-account-wallet-kicker{margin:0 0 6px;color:#008f83;font-weight:700;text-transform:uppercase;letter-spacing:.08em;font-size:12px}
		.cc-account-wallet-panel h2{margin:0 0 8px;font-size:32px;color:#111827}
		.cc-wallet-transactions{width:100%;border-collapse:collapse;font-size:13px}
		.cc-wallet-transactions th,.cc-wallet-transactions td{padding:9px 8px;border-bottom:1px solid #edf2f7;text-align:left}
		@media (max-width: 900px){.cc-wallet-admin-grid,.cc-account-wallet-panel{grid-template-columns:1fr}.cc-wallet-admin-sync,.cc-wallet-admin-search{align-items:stretch;flex-direction:column}.cc-wallet-admin-search input[type=search]{min-width:0;width:100%}}
	</style>
	<?php
}
add_action( 'admin_head-users_page_cc-customer-wallets', 'cc_customer_wallet_inline_styles' );
add_action( 'wp_head', 'cc_customer_wallet_inline_styles' );

/**
 * Inline JS for the order wallet charge metabox.
 */
function cc_order_wallet_charge_admin_script() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! cc_wallet_is_order_edit_screen_id( $screen->id ) ) {
		return;
	}
	?>
	<script>
		(function () {
			'use strict';

			document.addEventListener('click', function (event) {
				var button = event.target.closest ? event.target.closest('.cc-order-wallet-charge-submit') : null;
				if (!button || button.disabled) {
					return;
				}

				var box = button.closest('.cc-order-wallet-charge-box');
				if (!box) {
					return;
				}

				var amount = box.querySelector('[name="wallet_amount"]');
				var note = box.querySelector('[name="wallet_note"]');
				var message = box.querySelector('.cc-order-wallet-charge-message');
				var payload = new window.FormData();

				payload.append('action', 'cc_charge_order_wallet');
				payload.append('nonce', box.getAttribute('data-nonce') || '');
				payload.append('order_id', box.getAttribute('data-order-id') || '');
				payload.append('wallet_amount', amount ? amount.value : '0');
				payload.append('wallet_note', note ? note.value : '');

				button.disabled = true;
				button.textContent = '<?php echo esc_js( __( 'Charging wallet...', 'consucorner' ) ); ?>';
				if (message) {
					message.hidden = true;
					message.textContent = '';
				}

				window.fetch(ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					body: payload
				})
					.then(function (response) { return response.json(); })
					.then(function (response) {
						if (!response || !response.success) {
							throw new Error(response && response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Unable to charge wallet.', 'consucorner' ) ); ?>');
						}
						window.location.reload();
					})
					.catch(function (error) {
						button.disabled = false;
						button.textContent = '<?php echo esc_js( __( 'Deduct Wallet From Order', 'consucorner' ) ); ?>';
						if (message) {
							message.hidden = false;
							message.textContent = error.message || '<?php echo esc_js( __( 'Unable to charge wallet.', 'consucorner' ) ); ?>';
						}
					});
			});
		})();
	</script>
	<?php
}
add_action( 'admin_footer', 'cc_order_wallet_charge_admin_script' );
