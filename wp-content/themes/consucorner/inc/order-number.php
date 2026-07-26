<?php
/**
 * Custom WooCommerce order numbers.
 *
 * Format (one number): {N} + orderID + {S}
 *   {N} — minutes of the hour, always 2 digits (leading zeros)
 *   orderID — WooCommerce order ID (integer, no padding)
 *   {S} — seconds, always 2 digits (leading zeros)
 *
 * Example: order #5419 at 14:45:30 → 45541930
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order number parts used to build the display value.
 *
 * @param int $order_id  WooCommerce order ID.
 * @param int $timestamp Unix timestamp (site timezone).
 * @return array{n: string, id: string, s: string}|null
 */
function cc_get_order_display_number_parts( $order_id, $timestamp ) {
	$order_id  = absint( $order_id );
	$timestamp = absint( $timestamp );

	if ( ! $order_id || ! $timestamp ) {
		return null;
	}

	return array(
		'n'  => wp_date( 'i', $timestamp ), // Minutes 00–59, leading zeros.
		'id' => (string) $order_id,       // Order ID as integer string.
		's'  => wp_date( 's', $timestamp ), // Seconds 00–59, leading zeros.
	);
}

/**
 * Build display order number: {N}orderID{S} as one number.
 *
 * @param int $order_id  WooCommerce order ID.
 * @param int $timestamp Unix timestamp (site timezone).
 * @return string
 */
function cc_build_order_display_number( $order_id, $timestamp ) {
	$parts = cc_get_order_display_number_parts( $order_id, $timestamp );

	if ( ! $parts ) {
		return (string) absint( $order_id );
	}

	return $parts['n'] . $parts['id'] . $parts['s'];
}

/**
 * Resolve the display order number for an order object.
 *
 * Always derived from the order creation time so the format stays consistent.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function cc_get_order_display_number( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return '';
	}

	$created = $order->get_date_created();
	if ( ! $created ) {
		return (string) $order->get_id();
	}

	return cc_build_order_display_number( $order->get_id(), $created->getTimestamp() );
}

/**
 * Persist custom order number when a new order is created.
 *
 * @param int|WC_Order $order Order ID or object.
 * @return void
 */
function cc_assign_order_display_number( $order ) {
	$order = is_a( $order, 'WC_Order' ) ? $order : wc_get_order( $order );
	if ( ! $order ) {
		return;
	}

	$created = $order->get_date_created();
	$ts      = $created ? $created->getTimestamp() : current_time( 'timestamp' );
	$number  = cc_build_order_display_number( $order->get_id(), $ts );

	$order->update_meta_data( '_cc_display_order_number', $number );
	$order->save();
}

add_action( 'woocommerce_new_order', 'cc_assign_order_display_number', 20, 1 );
add_action( 'woocommerce_checkout_order_created', 'cc_assign_order_display_number', 20, 1 );

/**
 * Filter WooCommerce order number everywhere it is displayed.
 *
 * @param string   $order_number Default order number (order ID).
 * @param WC_Order $order        Order object.
 * @return string
 */
function cc_filter_woocommerce_order_number( $order_number, $order ) {
	return cc_get_order_display_number( $order );
}

add_filter( 'woocommerce_order_number', 'cc_filter_woocommerce_order_number', 10, 2 );

/**
 * Build the public Bosta shipment tracking URL.
 *
 * @param string $tracking_number Bosta tracking / shipment number.
 * @return string
 */
function cc_get_bosta_tracking_url( $tracking_number ) {
	$tracking_number = preg_replace( '/\D+/', '', (string) $tracking_number );
	if ( '' === $tracking_number ) {
		return '';
	}

	return add_query_arg(
		'shipment-number',
		$tracking_number,
		'https://bosta.co/en-eg/tracking-shipments'
	);
}

/**
 * Resolve a customer-entered order reference to a WooCommerce order ID.
 *
 * Accepts the ConsuCorner display number (e.g. 38546441), the internal ID (5464),
 * or values with a leading #.
 *
 * @param string|int $order_ref Value from the track-order form.
 * @return int|string Order ID when found, otherwise the original reference.
 */
function cc_resolve_order_tracking_input( $order_ref ) {
	$digits = preg_replace( '/\D+/', '', (string) $order_ref );
	if ( '' === $digits || ! function_exists( 'wc_get_orders' ) ) {
		return $order_ref;
	}

	$by_meta = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_key'   => '_cc_display_order_number',
				'meta_value' => $digits,
				'return'     => 'ids',
			)
		);
	if ( ! empty( $by_meta ) ) {
		return (int) $by_meta[0];
	}

	if ( strlen( $digits ) <= 6 && function_exists( 'wc_get_order' ) ) {
		$direct = wc_get_order( (int) $digits );
		if ( $direct ) {
			return (int) $direct->get_id();
		}
	}

	if ( strlen( $digits ) >= 5 && function_exists( 'wc_get_order' ) ) {
		$candidate_id = (int) substr( $digits, 2, -2 );
		if ( $candidate_id > 0 ) {
			$candidate = wc_get_order( $candidate_id );
			if ( $candidate && cc_get_order_display_number( $candidate ) === $digits ) {
				return (int) $candidate->get_id();
			}
		}
	}

	return $order_ref;
}

/**
 * Map custom display order numbers for the WooCommerce track-order shortcode.
 *
 * @param string|int $order_id Submitted order reference.
 * @return int|string
 */
function cc_filter_order_tracking_order_id( $order_id ) {
	return cc_resolve_order_tracking_input( $order_id );
}
add_filter( 'woocommerce_shortcode_order_tracking_order_id', 'cc_filter_order_tracking_order_id' );

/**
 * Add a Bosta tracking row to order detail tables (track-order page, emails, etc.).
 *
 * @param array    $total_rows Order total rows.
 * @param WC_Order $order      Order object.
 * @param string   $tax_display Tax display mode.
 * @return array
 */
function cc_add_bosta_tracking_order_total_row( $total_rows, $order, $tax_display ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	unset( $tax_display );

	if ( ! $order instanceof WC_Order ) {
		return $total_rows;
	}

	$tracking = sanitize_text_field( (string) $order->get_meta( 'bosta_tracking_number', true ) );
	if ( '' === $tracking ) {
		return $total_rows;
	}

	$url  = cc_get_bosta_tracking_url( $tracking );
	$row  = array(
		'label' => __( 'Bosta tracking:', 'consucorner' ),
		'value' => $url
			? sprintf(
				'<a class="cc-bosta-tracking-link" href="%1$s" target="_blank" rel="noopener noreferrer">#%2$s</a>',
				esc_url( $url ),
				esc_html( $tracking )
			)
			: '#' . esc_html( $tracking ),
	);
	$rows = array();

	foreach ( $total_rows as $key => $total_row ) {
		if ( 'order_total' === $key ) {
			$rows['bosta_tracking'] = $row;
		}
		$rows[ $key ] = $total_row;
	}

	if ( ! isset( $rows['bosta_tracking'] ) ) {
		$rows['bosta_tracking'] = $row;
	}

	return $rows;
}
add_filter( 'woocommerce_get_order_item_totals', 'cc_add_bosta_tracking_order_total_row', 20, 3 );
