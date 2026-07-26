<?php
/**
 * Seed demo orders for all fulfillment / cancel-request / return scenarios.
 * Does NOT cancel any orders (cancel requests stay pending or rejected only).
 *
 * Run:
 *   php wp-cli.phar eval-file wp-content/themes/consucorner/tests/seed-scenario-orders.php --user=1
 *
 * @package Consucorner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

/**
 * Log a line.
 *
 * @param string $msg Message.
 */
function cc_seed_log( $msg ) {
	WP_CLI::log( $msg );
}

/**
 * Advance fulfillment along the allowed path to a target status.
 *
 * @param int    $order_id Order ID.
 * @param int    $vendor_id Vendor ID.
 * @param string $target Target status.
 * @return true|WP_Error
 */
function cc_seed_set_fulfillment( $order_id, $vendor_id, $target ) {
	$path = array( 'confirmed', 'preparing', 'shipped', 'out_for_delivery', 'delivered' );
	$idx  = array_search( $target, $path, true );
	if ( false === $idx ) {
		return new WP_Error( 'bad_target', 'Invalid target: ' . $target );
	}

	$order  = wc_get_order( $order_id );
	$record = Consucorner_Order_Return_Workflow::get_vendor_fulfillment( $order, $vendor_id );
	$from   = Consucorner_Order_Return_Workflow::normalize_fulfillment_status( (string) ( $record['status'] ?? 'confirmed' ) );
	$from_i = array_search( $from, $path, true );
	if ( false === $from_i ) {
		$from_i = 0;
	}

	for ( $i = $from_i + 1; $i <= $idx; $i++ ) {
		$result = Consucorner_Order_Return_Workflow::update_fulfillment_status(
			$order_id,
			$vendor_id,
			$path[ $i ],
			'Demo seed: set to ' . $path[ $i ],
			'operations'
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

	return true;
}

/**
 * Create a processing order with one product for a customer.
 *
 * @param int    $customer_id Customer ID.
 * @param int    $product_id Product ID.
 * @param string $label Scenario label stored in order note.
 * @return WC_Order|WP_Error
 */
function cc_seed_create_order( $customer_id, $product_id, $label ) {
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return new WP_Error( 'no_product', 'Product missing' );
	}

	$order = wc_create_order( array( 'customer_id' => $customer_id ) );
	$order->add_product( $product, 1 );
	$order->set_billing_first_name( 'Ops' );
	$order->set_billing_last_name( 'Demo' );
	$order->set_billing_email( 'ops.demo@example.com' );
	$order->set_billing_phone( '01000000000' );
	$order->set_billing_address_1( 'Demo Street 1' );
	$order->set_billing_city( 'Cairo' );
	$order->set_billing_country( 'EG' );
	$order->set_shipping_first_name( 'Ops' );
	$order->set_shipping_last_name( 'Demo' );
	$order->set_shipping_address_1( 'Demo Street 1' );
	$order->set_shipping_city( 'Cairo' );
	$order->set_shipping_country( 'EG' );
	$order->set_payment_method( 'cod' );
	$order->set_payment_method_title( 'Cash on delivery' );
	$order->calculate_totals();
	$order->set_status( 'processing' );
	$order->add_order_note( '[DEMO SCENARIO] ' . $label );
	$order->update_meta_data( '_cc_demo_scenario', sanitize_text_field( $label ) );
	$order->save();

	return $order;
}

/**
 * Get first vendor id on order.
 *
 * @param WC_Order $order Order.
 * @return int
 */
function cc_seed_vendor_id( $order ) {
	$ids = Consucorner_Order_Return_Workflow::get_order_vendor_ids( $order );
	return ! empty( $ids ) ? (int) $ids[0] : 0;
}

/**
 * Attach sample Bosta meta for display demos.
 *
 * @param WC_Order $order Order.
 * @param string   $status_text Bosta status text.
 * @param int      $state_code State code.
 * @param string   $tracking Tracking number.
 */
function cc_seed_bosta( $order, $status_text, $state_code, $tracking ) {
	$order->update_meta_data( 'bosta_status', $status_text );
	$order->update_meta_data( 'bosta_state_code', absint( $state_code ) );
	$order->update_meta_data( 'bosta_tracking_number', $tracking );
	$order->save();
}

// --- Resolve fixtures ---
$customers = get_users(
	array(
		'role'   => 'customer',
		'number' => 1,
		'fields' => array( 'ID' ),
	)
);
$customer_id = ! empty( $customers ) ? (int) $customers[0]->ID : 0;
if ( ! $customer_id ) {
	WP_CLI::error( 'No customer user found.' );
}

$product_ids = get_posts(
	array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 5,
		'fields'         => 'ids',
		'author__not_in' => array( 0 ),
	)
);
if ( empty( $product_ids ) ) {
	WP_CLI::error( 'No published products found.' );
}
$product_id = (int) $product_ids[0];

cc_seed_log( 'Customer #' . $customer_id . ' · Product #' . $product_id );
cc_seed_log( '' );

$GLOBALS['cc_seed_created'] = array();

/**
 * Register created scenario.
 *
 * @param WC_Order $order Order.
 * @param string   $label Label.
 */
function cc_seed_track( $order, $label ) {
	$GLOBALS['cc_seed_created'][] = array(
		'id'     => $order->get_id(),
		'number' => $order->get_order_number(),
		'label'  => $label,
	);
	cc_seed_log( sprintf( '✓ Order #%s (ID %d) — %s', $order->get_order_number(), $order->get_id(), $label ) );
}

// ============================================================
// A) Fulfillment-only scenarios (no cancel, no return)
// ============================================================
$fulfillment_scenarios = array(
	array( 'confirmed', 'FULFILLMENT: Confirmed (new order default)' ),
	array( 'preparing', 'FULFILLMENT: Preparing' ),
	array( 'shipped', 'FULFILLMENT: Shipped (+ Bosta tracking)' ),
	array( 'out_for_delivery', 'FULFILLMENT: Out for delivery (+ Bosta)' ),
	array( 'delivered', 'FULFILLMENT: Delivered (customer sees Completed)' ),
);

foreach ( $fulfillment_scenarios as $row ) {
	list( $status, $label ) = $row;
	$order = cc_seed_create_order( $customer_id, $product_id, $label );
	if ( is_wp_error( $order ) ) {
		cc_seed_log( 'FAIL create: ' . $order->get_error_message() );
		continue;
	}
	$vendor_id = cc_seed_vendor_id( $order );
	if ( ! $vendor_id ) {
		cc_seed_log( 'FAIL no vendor on order #' . $order->get_id() );
		continue;
	}
	if ( 'confirmed' !== $status ) {
		$r = cc_seed_set_fulfillment( $order->get_id(), $vendor_id, $status );
		if ( is_wp_error( $r ) ) {
			cc_seed_log( 'FAIL fulfillment: ' . $r->get_error_message() );
		}
	}

	if ( 'shipped' === $status ) {
		cc_seed_bosta( $order, 'Received at warehouse', 12, 'DEMO-SHIP-' . $order->get_id() );
	} elseif ( 'out_for_delivery' === $status ) {
		cc_seed_bosta( $order, 'Out for delivery', 41, 'DEMO-OFD-' . $order->get_id() );
	} elseif ( 'delivered' === $status ) {
		cc_seed_bosta( $order, 'Delivered', 45, 'DEMO-DEL-' . $order->get_id() );
	}

	cc_seed_track( $order, $label );
}

// ============================================================
// B) Cancellation REQUEST scenarios (pending / rejected only)
// ============================================================
$cancel_pending_label = 'CANCEL REQUEST: Pending ops review (order still open)';
$order = cc_seed_create_order( $customer_id, $product_id, $cancel_pending_label );
if ( ! is_wp_error( $order ) ) {
	$vendor_id = cc_seed_vendor_id( $order );
	cc_seed_set_fulfillment( $order->get_id(), $vendor_id, 'confirmed' );
	$req = Consucorner_Order_Cancel_Requests::create_request(
		$order->get_id(),
		$customer_id,
		true,
		array(),
		'Demo: customer asked to cancel before shipping'
	);
	if ( is_wp_error( $req ) ) {
		cc_seed_log( 'FAIL cancel pending: ' . $req->get_error_message() );
	} else {
		cc_seed_track( $order, $cancel_pending_label . ' · req ' . ( $req['id'] ?? '' ) );
	}
}

$cancel_rejected_label = 'CANCEL REQUEST: Rejected by ops (order still open)';
$order = cc_seed_create_order( $customer_id, $product_id, $cancel_rejected_label );
if ( ! is_wp_error( $order ) ) {
	$vendor_id = cc_seed_vendor_id( $order );
	cc_seed_set_fulfillment( $order->get_id(), $vendor_id, 'preparing' );
	$req = Consucorner_Order_Cancel_Requests::create_request(
		$order->get_id(),
		$customer_id,
		true,
		array(),
		'Demo: cancel request that ops will reject'
	);
	if ( ! is_wp_error( $req ) ) {
		$rev = Consucorner_Order_Cancel_Requests::review_request(
			$order->get_id(),
			(string) ( $req['id'] ?? '' ),
			'rejected',
			'Demo: items already packed — cannot cancel'
		);
		if ( is_wp_error( $rev ) ) {
			cc_seed_log( 'FAIL cancel reject: ' . $rev->get_error_message() );
		} else {
			cc_seed_track( $order, $cancel_rejected_label . ' · req ' . ( $req['id'] ?? '' ) );
		}
	} else {
		cc_seed_log( 'FAIL cancel reject create: ' . $req->get_error_message() );
	}
}

$cancel_item_label = 'CANCEL REQUEST: Item-level pending (partial, order open)';
$order = cc_seed_create_order( $customer_id, $product_id, $cancel_item_label );
if ( ! is_wp_error( $order ) ) {
	// Add a second unit so partial cancel is clearer if same product.
	$order->add_product( wc_get_product( $product_id ), 1 );
	$order->calculate_totals();
	$order->save();
	$vendor_id = cc_seed_vendor_id( $order );
	cc_seed_set_fulfillment( $order->get_id(), $vendor_id, 'confirmed' );
	$items = $order->get_items( 'line_item' );
	$first = reset( $items );
	$item_id = $first ? (int) $first->get_id() : 0;
	$req = Consucorner_Order_Cancel_Requests::create_request(
		$order->get_id(),
		$customer_id,
		false,
		array( $item_id => 1 ),
		'Demo: cancel one item only'
	);
	if ( is_wp_error( $req ) ) {
		cc_seed_log( 'FAIL item cancel: ' . $req->get_error_message() );
	} else {
		cc_seed_track( $order, $cancel_item_label . ' · req ' . ( $req['id'] ?? '' ) );
	}
}

// ============================================================
// C) Return workflow scenarios (shipped+, order NOT cancelled)
// ============================================================
$return_stages = array(
	array( 'requested', 'RETURN: Requested (awaiting ops)' ),
	array( 'reviewing', 'RETURN: Reviewing' ),
	array( 'approved', 'RETURN: Approved' ),
	array( 'return_in_transit', 'RETURN: Return in transit' ),
	array( 'received', 'RETURN: Received (Wallet/Direct buttons ready)' ),
);

$return_path = array( 'requested', 'reviewing', 'approved', 'return_in_transit', 'received' );

foreach ( $return_stages as $row ) {
	list( $target_status, $label ) = $row;
	$order = cc_seed_create_order( $customer_id, $product_id, $label );
	if ( is_wp_error( $order ) ) {
		cc_seed_log( 'FAIL return order: ' . $order->get_error_message() );
		continue;
	}
	$vendor_id = cc_seed_vendor_id( $order );
	$r = cc_seed_set_fulfillment( $order->get_id(), $vendor_id, 'shipped' );
	if ( is_wp_error( $r ) ) {
		cc_seed_log( 'FAIL ship for return: ' . $r->get_error_message() );
		continue;
	}
	cc_seed_bosta( $order, 'In transit between Hubs', 30, 'DEMO-RET-' . $order->get_id() );

	$items_map = array();
	foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
		$items_map[ (int) $item_id ] = 1;
		break;
	}

	$created_returns = Consucorner_Order_Return_Workflow::create_manual_return_requests(
		$order->get_id(),
		$items_map,
		array(
			'reason'  => 'not_needed',
			'type'    => 'refund',
			'details' => 'Demo return for scenario: ' . $target_status,
			'note'    => 'Seeded demo return',
		)
	);

	if ( is_wp_error( $created_returns ) ) {
		cc_seed_log( 'FAIL create return (' . $target_status . '): ' . $created_returns->get_error_message() );
		cc_seed_track( $order, $label . ' · RETURN CREATE FAILED' );
		continue;
	}

	$request_id = 0;
	if ( is_array( $created_returns ) ) {
		$first_ret = reset( $created_returns );
		$request_id = absint( $first_ret['id'] ?? $first_ret['request_id'] ?? 0 );
		if ( ! $request_id && isset( $first_ret[0] ) ) {
			$request_id = absint( $first_ret[0]['id'] ?? 0 );
		}
	}

	if ( ! $request_id && class_exists( 'CC_Returns_Refund_Service' ) ) {
		$reqs = CC_Returns_Refund_Service::get_order_requests( $order->get_id() );
		if ( ! empty( $reqs ) ) {
			$latest = reset( $reqs );
			$request_id = absint( $latest['id'] ?? 0 );
		}
	}

	if ( ! $request_id ) {
		cc_seed_log( 'FAIL: no request id after create for ' . $target_status );
		cc_seed_track( $order, $label . ' · NO REQUEST ID' );
		continue;
	}

	// Advance return workflow to target (skip 'requested' — that's the default after create).
	$target_i = array_search( $target_status, $return_path, true );
	for ( $i = 1; $i <= $target_i; $i++ ) {
		$step = Consucorner_Order_Return_Workflow::update_return_workflow_status(
			$request_id,
			$return_path[ $i ],
			'Demo seed advance to ' . $return_path[ $i ]
		);
		if ( is_wp_error( $step ) ) {
			cc_seed_log( 'FAIL advance to ' . $return_path[ $i ] . ': ' . $step->get_error_message() );
			break;
		}
	}

	cc_seed_track( $order, $label . ' · RMA #' . $request_id );
}

// ============================================================
// D) Delivered + contact-support (no self-service return)
// ============================================================
$delivered_label = 'CUSTOMER UX: Delivered/Completed — return via support only';
$order = cc_seed_create_order( $customer_id, $product_id, $delivered_label );
if ( ! is_wp_error( $order ) ) {
	$vendor_id = cc_seed_vendor_id( $order );
	cc_seed_set_fulfillment( $order->get_id(), $vendor_id, 'delivered' );
	cc_seed_bosta( $order, 'Delivered', 45, 'DEMO-DONE-' . $order->get_id() );
	cc_seed_track( $order, $delivered_label );
}

// ============================================================
// E) Warehouse Bosta → Shipped display
// ============================================================
$wh_label = 'BOSTA MAP: Received at warehouse → fulfillment Shipped';
$order = cc_seed_create_order( $customer_id, $product_id, $wh_label );
if ( ! is_wp_error( $order ) ) {
	$vendor_id = cc_seed_vendor_id( $order );
	cc_seed_set_fulfillment( $order->get_id(), $vendor_id, 'preparing' );
	cc_seed_bosta( $order, 'Received at warehouse', 12, 'DEMO-WH-' . $order->get_id() );
	Consucorner_Order_Return_Workflow::maybe_sync_fulfillment_from_bosta( wc_get_order( $order->get_id() ) );
	$summary = Consucorner_Order_Return_Workflow::get_customer_fulfillment_summary( wc_get_order( $order->get_id() ) );
	cc_seed_track( $order, $wh_label . ' · now ' . ( $summary['status'] ?? '?' ) );
}

cc_seed_log( '' );
cc_seed_log( '========== DEMO ORDERS SUMMARY (none cancelled) ==========' );
foreach ( $GLOBALS['cc_seed_created'] as $row ) {
	cc_seed_log( sprintf( '#%s (ID %d) — %s', $row['number'], $row['id'], $row['label'] ) );
}
cc_seed_log( '' );
cc_seed_log( 'Total: ' . count( $GLOBALS['cc_seed_created'] ) . ' orders' );
cc_seed_log( 'View: WooCommerce → Orders (filter by note/meta _cc_demo_scenario)' );
cc_seed_log( 'Returns hub: WooCommerce → Returns → Load order by ID' );
