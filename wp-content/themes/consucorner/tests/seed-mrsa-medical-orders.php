<?php
/**
 * Seed demo orders for mrsa-medical vendor dashboard testing.
 * Products: only from vendor login "mrsa-medical".
 * Registers each order in Dokan (dokan_orders) so it appears in the vendor dashboard.
 *
 * Standalone:
 *   php wp-content/themes/consucorner/tests/run-seed-mrsa-medical-orders.php
 *
 * @package Consucorner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

/**
 * @param string $msg Message.
 */
function cc_mrsa_seed_log( $msg ) {
	if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
		WP_CLI::log( $msg );
	} else {
		echo $msg . PHP_EOL;
	}
}

/**
 * @param string $msg Message.
 */
function cc_mrsa_seed_error( $msg ) {
	if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
		WP_CLI::error( $msg );
	}
	echo 'ERROR: ' . $msg . PHP_EOL;
	exit( 1 );
}

/**
 * Register order in Dokan vendor dashboard tables.
 *
 * @param WC_Order $order Order.
 * @return true|WP_Error
 */
function cc_mrsa_seed_sync_dokan( $order ) {
	if ( ! function_exists( 'dokan_sync_insert_order' ) || ! function_exists( 'dokan' ) ) {
		return new WP_Error( 'no_dokan', 'Dokan is not active.' );
	}

	$order_id = $order->get_id();
	dokan()->order->maybe_split_orders( $order_id );
	dokan_sync_insert_order( $order_id );

	global $wpdb;
	$row = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT seller_id FROM ' . $wpdb->prefix . 'dokan_orders WHERE order_id = %d LIMIT 1',
			$order_id
		)
	);

	if ( ! $row || ! (int) $row->seller_id ) {
		return new WP_Error( 'dokan_sync_failed', 'Order #' . $order_id . ' not in dokan_orders.' );
	}

	return true;
}

/**
 * Repair previously seeded MRSA demo orders missing from Dokan.
 */
function cc_mrsa_seed_repair_existing() {
	$ids = get_posts(
		array(
			'post_type'      => 'shop_order',
			'post_status'    => 'any',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_cc_demo_vendor',
					'value' => 'mrsa-medical',
				),
			),
		)
	);

	if ( empty( $ids ) ) {
		return;
	}

	cc_mrsa_seed_log( 'Repairing ' . count( $ids ) . ' existing MRSA demo order(s) for Dokan…' );
	foreach ( $ids as $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			continue;
		}
		$r = cc_mrsa_seed_sync_dokan( $order );
		if ( is_wp_error( $r ) ) {
			cc_mrsa_seed_log( '  FAIL repair #' . $order_id . ': ' . $r->get_error_message() );
		} else {
			cc_mrsa_seed_log( '  ✓ Repaired #' . $order->get_order_number() . ' (ID ' . $order_id . ')' );
		}
	}
	cc_mrsa_seed_log( '' );
}

/**
 * @param int    $customer_id Customer ID.
 * @param int    $product_id  Product ID.
 * @param string $label       Scenario label.
 * @return WC_Order|WP_Error
 */
function cc_mrsa_seed_create_order( $customer_id, $product_id, $label ) {
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return new WP_Error( 'no_product', 'Product missing: ' . $product_id );
	}

	$order = wc_create_order( array( 'customer_id' => $customer_id ) );
	$order->add_product( $product, 1 );
	$order->set_billing_first_name( 'MRSA' );
	$order->set_billing_last_name( 'Demo' );
	$order->set_billing_email( 'mrsa.demo@example.com' );
	$order->set_billing_phone( '01012345678' );
	$order->set_billing_address_1( 'Demo Street 12' );
	$order->set_billing_city( 'Cairo' );
	$order->set_billing_country( 'EG' );
	$order->set_shipping_first_name( 'MRSA' );
	$order->set_shipping_last_name( 'Demo' );
	$order->set_shipping_address_1( 'Demo Street 12' );
	$order->set_shipping_city( 'Cairo' );
	$order->set_shipping_country( 'EG' );
	$order->set_payment_method( 'cod' );
	$order->set_payment_method_title( 'Cash on delivery' );
	$order->calculate_totals();
	$order->set_status( 'processing' );
	$order->add_order_note( '[MRSA DEMO] ' . $label );
	$order->update_meta_data( '_cc_demo_scenario', sanitize_text_field( $label ) );
	$order->update_meta_data( '_cc_demo_vendor', 'mrsa-medical' );
	$order->save();

	$sync = cc_mrsa_seed_sync_dokan( $order );
	if ( is_wp_error( $sync ) ) {
		return $sync;
	}

	return $order;
}

/**
 * @param WC_Order $order Order.
 * @return int
 */
function cc_mrsa_seed_vendor_id( $order ) {
	if ( ! class_exists( 'Consucorner_Order_Return_Workflow' ) ) {
		return 0;
	}
	$ids = Consucorner_Order_Return_Workflow::get_order_vendor_ids( $order );
	return ! empty( $ids ) ? (int) $ids[0] : 0;
}

/**
 * @param int    $order_id  Order ID.
 * @param int    $vendor_id Vendor ID.
 * @param string $target    Fulfillment status.
 * @return true|WP_Error
 */
function cc_mrsa_seed_set_fulfillment( $order_id, $vendor_id, $target ) {
	if ( ! class_exists( 'Consucorner_Order_Return_Workflow' ) ) {
		return true;
	}

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
			'MRSA demo seed: ' . $path[ $i ],
			'operations'
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

	return true;
}

/**
 * @param WC_Order $order       Order.
 * @param string   $status_text Bosta status.
 * @param int      $state_code  State code.
 * @param string   $tracking    Tracking number.
 */
function cc_mrsa_seed_bosta( $order, $status_text, $state_code, $tracking ) {
	$order->update_meta_data( 'bosta_status', $status_text );
	$order->update_meta_data( 'bosta_state_code', absint( $state_code ) );
	$order->update_meta_data( 'bosta_tracking_number', $tracking );
	$order->save();
}

// --- Resolve vendor + products ---
$vendor_user = get_user_by( 'login', 'mrsa-medical' );
if ( ! $vendor_user ) {
	$vendor_user = get_user_by( 'slug', 'mrsa-medical' );
}
if ( ! $vendor_user ) {
	cc_mrsa_seed_error( 'Vendor user "mrsa-medical" not found.' );
}

$vendor_id = (int) $vendor_user->ID;
cc_mrsa_seed_log( 'Vendor: mrsa-medical (user #' . $vendor_id . ')' );

$product_ids = get_posts(
	array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'author'         => $vendor_id,
		'posts_per_page' => 25,
		'fields'         => 'ids',
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);

if ( empty( $product_ids ) ) {
	cc_mrsa_seed_error( 'No published products found for mrsa-medical.' );
}

cc_mrsa_seed_log( 'Products: ' . count( $product_ids ) . ' from mrsa-medical only' );

$customers = get_users(
	array(
		'role'   => 'customer',
		'number' => 1,
		'fields' => array( 'ID' ),
	)
);
$customer_id = ! empty( $customers ) ? (int) $customers[0]->ID : 1;

cc_mrsa_seed_repair_existing();

$GLOBALS['cc_mrsa_seed_created'] = array();
$pi                              = 0;

/**
 * @param WC_Order $order Order.
 * @param string   $label Label.
 */
function cc_mrsa_seed_track( $order, $label ) {
	$product_name = '';
	$items        = $order->get_items( 'line_item' );
	if ( ! empty( $items ) ) {
		$first = reset( $items );
		$product_name = $first ? wp_strip_all_tags( $first->get_name() ) : '';
	}
	$GLOBALS['cc_mrsa_seed_created'][] = array(
		'id'      => $order->get_id(),
		'number'  => $order->get_order_number(),
		'label'   => $label,
		'product' => $product_name,
	);
	cc_mrsa_seed_log(
		sprintf(
			'✓ Order #%s (ID %d) — %s · %s',
			$order->get_order_number(),
			$order->get_id(),
			$label,
			$product_name
		)
	);
}

/**
 * Pick next mrsa product ID.
 *
 * @return int
 */
function cc_mrsa_next_product() {
	global $product_ids, $pi;
	$id = (int) $product_ids[ $pi % count( $product_ids ) ];
	$pi++;
	return $id;
}

// A) All fulfillment statuses (vendor dashboard columns).
$fulfillment_scenarios = array(
	array( 'confirmed', 'FULFILLMENT: Confirmed' ),
	array( 'preparing', 'FULFILLMENT: Preparing' ),
	array( 'shipped', 'FULFILLMENT: Shipped' ),
	array( 'out_for_delivery', 'FULFILLMENT: Out for delivery' ),
	array( 'delivered', 'FULFILLMENT: Delivered' ),
);

foreach ( $fulfillment_scenarios as $row ) {
	list( $status, $label ) = $row;
	$order = cc_mrsa_seed_create_order( $customer_id, cc_mrsa_next_product(), $label );
	if ( is_wp_error( $order ) ) {
		cc_mrsa_seed_log( 'FAIL create: ' . $order->get_error_message() );
		continue;
	}
	$vid = cc_mrsa_seed_vendor_id( $order );
	if ( $vid !== $vendor_id ) {
		cc_mrsa_seed_log( 'WARN vendor mismatch on #' . $order->get_id() );
	}
	if ( 'confirmed' !== $status ) {
		$r = cc_mrsa_seed_set_fulfillment( $order->get_id(), $vendor_id, $status );
		if ( is_wp_error( $r ) ) {
			cc_mrsa_seed_log( 'FAIL fulfillment: ' . $r->get_error_message() );
		}
	}
	if ( 'shipped' === $status ) {
		cc_mrsa_seed_bosta( $order, 'Received at warehouse', 12, 'MRSA-SHIP-' . $order->get_id() );
	} elseif ( 'out_for_delivery' === $status ) {
		cc_mrsa_seed_bosta( $order, 'Out for delivery', 41, 'MRSA-OFD-' . $order->get_id() );
	} elseif ( 'delivered' === $status ) {
		cc_mrsa_seed_bosta( $order, 'Delivered', 45, 'MRSA-DEL-' . $order->get_id() );
	}
	cc_mrsa_seed_track( $order, $label );
}

// B) Cancel request scenarios (vendor sees pending/rejected).
if ( class_exists( 'Consucorner_Order_Cancel_Requests' ) ) {
	$cancel_pending_label = 'CANCEL: Pending ops review';
	$order                = cc_mrsa_seed_create_order( $customer_id, cc_mrsa_next_product(), $cancel_pending_label );
	if ( ! is_wp_error( $order ) ) {
		cc_mrsa_seed_set_fulfillment( $order->get_id(), $vendor_id, 'confirmed' );
		$req = Consucorner_Order_Cancel_Requests::create_request(
			$order->get_id(),
			$customer_id,
			true,
			array(),
			'MRSA demo: cancel before shipping'
		);
		if ( ! is_wp_error( $req ) ) {
			cc_mrsa_seed_track( $order, $cancel_pending_label );
		}
	}

	$cancel_rejected_label = 'CANCEL: Rejected by ops';
	$order                 = cc_mrsa_seed_create_order( $customer_id, cc_mrsa_next_product(), $cancel_rejected_label );
	if ( ! is_wp_error( $order ) ) {
		cc_mrsa_seed_set_fulfillment( $order->get_id(), $vendor_id, 'preparing' );
		$req = Consucorner_Order_Cancel_Requests::create_request(
			$order->get_id(),
			$customer_id,
			true,
			array(),
			'MRSA demo: reject cancel'
		);
		if ( ! is_wp_error( $req ) ) {
			Consucorner_Order_Cancel_Requests::review_request(
				$order->get_id(),
				(string) ( $req['id'] ?? '' ),
				'rejected',
				'MRSA demo: already packed'
			);
			cc_mrsa_seed_track( $order, $cancel_rejected_label );
		}
	}
}

// C) Return workflow (vendor dashboard return states).
if ( class_exists( 'Consucorner_Order_Return_Workflow' ) ) {
	$return_stages = array(
		array( 'requested', 'RETURN: Requested' ),
		array( 'reviewing', 'RETURN: Reviewing' ),
		array( 'approved', 'RETURN: Approved' ),
		array( 'return_in_transit', 'RETURN: In transit' ),
		array( 'received', 'RETURN: Received' ),
	);
	$return_path   = array( 'requested', 'reviewing', 'approved', 'return_in_transit', 'received' );

	foreach ( $return_stages as $row ) {
		list( $target_status, $label ) = $row;
		$order = cc_mrsa_seed_create_order( $customer_id, cc_mrsa_next_product(), $label );
		if ( is_wp_error( $order ) ) {
			continue;
		}
		cc_mrsa_seed_set_fulfillment( $order->get_id(), $vendor_id, 'shipped' );
		cc_mrsa_seed_bosta( $order, 'In transit between Hubs', 30, 'MRSA-RET-' . $order->get_id() );

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
				'details' => 'MRSA demo return: ' . $target_status,
				'note'    => 'MRSA seeded return',
			)
		);

		if ( is_wp_error( $created_returns ) ) {
			cc_mrsa_seed_track( $order, $label . ' · RETURN FAILED' );
			continue;
		}

		$request_id = 0;
		if ( is_array( $created_returns ) ) {
			$first_ret = reset( $created_returns );
			$request_id = absint( $first_ret['id'] ?? $first_ret['request_id'] ?? 0 );
		}

		$target_i = array_search( $target_status, $return_path, true );
		for ( $i = 1; $i <= $target_i; $i++ ) {
			Consucorner_Order_Return_Workflow::update_return_workflow_status(
				$request_id,
				$return_path[ $i ],
				'MRSA demo advance to ' . $return_path[ $i ]
			);
		}

		cc_mrsa_seed_track( $order, $label . ' · RMA #' . $request_id );
	}
}

cc_mrsa_seed_log( '' );
cc_mrsa_seed_log( '========== MRSA-MEDICAL VENDOR DASHBOARD ORDERS ==========' );
foreach ( $GLOBALS['cc_mrsa_seed_created'] as $row ) {
	cc_mrsa_seed_log( sprintf( '#%s (ID %d) — %s · %s', $row['number'], $row['id'], $row['label'], $row['product'] ) );
}
cc_mrsa_seed_log( 'Total new: ' . count( $GLOBALS['cc_mrsa_seed_created'] ) );
cc_mrsa_seed_log( 'Vendor dashboard: /dashboard/orders/ (login as mrsa-medical)' );
cc_mrsa_seed_log( 'Filter: meta _cc_demo_vendor = mrsa-medical' );
