<?php
/**
 * Smoke/integration tests for order status + vendor financials implementation.
 *
 * Run:
 *   $env:PHPRC = "C:/Users/DELL/AppData/Roaming/Local/run/MuOxUxI3w/conf/php"
 *   $env:CONSUCORNER_CLI_DB_HOST = "127.0.0.1:10043"
 *   php wp-cli.phar eval-file wp-content/themes/consucorner/tests/order-workflow-smoke-test.php --user=1
 *
 * @package Consucorner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$GLOBALS['cc_test_results'] = array();

/**
 * Record a test result.
 *
 * @param string $name Test name.
 * @param bool   $pass Pass state.
 * @param string $detail Detail message.
 */
function cc_test( $name, $pass, $detail = '' ) {
	$GLOBALS['cc_test_results'][] = array(
		'name'   => $name,
		'pass'   => (bool) $pass,
		'detail' => (string) $detail,
	);
	$flag = $pass ? 'PASS' : 'FAIL';
	WP_CLI::log( sprintf( '[%s] %s%s', $flag, $name, $detail ? ' — ' . $detail : '' ) );
}

// --- Unit-level mapping tests ---
$warehouse = Consucorner_Order_Return_Workflow::map_bosta_to_fulfillment( 'Received at warehouse', 12 );
cc_test( 'Bosta warehouse maps to shipped', 'shipped' === $warehouse, (string) $warehouse );

$pickup = Consucorner_Order_Return_Workflow::map_bosta_to_fulfillment( 'Pickup requested', 21 );
cc_test( 'Bosta pickup maps to preparing', 'preparing' === $pickup, (string) $pickup );

$text_warehouse = Consucorner_Order_Return_Workflow::map_bosta_to_fulfillment( 'Received at warehouse', 0 );
cc_test( 'Bosta text-only warehouse maps to shipped', 'shipped' === $text_warehouse, (string) $text_warehouse );

$legacy_order = wc_create_order( array( 'customer_id' => 1 ) );
if ( $legacy_order ) {
	$legacy_order->update_meta_data(
		'_cc_cancel_requests',
		array(
			'cr_LegacyX1' => array(
				'id'          => 'cr_LegacyX1',
				'status'      => 'requested',
				'whole_order' => true,
				'items'       => array(),
				'reason'      => 'legacy',
				'created_at'  => current_time( 'mysql' ),
				'history'     => array(),
			),
		)
	);
	$legacy_order->save();
	$legacy_found = Consucorner_Order_Cancel_Requests::get_request( $legacy_order, 'cr_legacyx1' );
	cc_test( 'Legacy cancel request ID resolves case-insensitively', is_array( $legacy_found ), '' );
	$legacy_order->delete( true );
}

$completed_label = Consucorner_Order_Return_Workflow::get_customer_status_label( 'delivered' );
cc_test( 'Customer delivered label is Completed', 'Completed' === $completed_label, $completed_label );

$customer_return_statuses = Consucorner_Order_Return_Workflow::customer_return_eligible_fulfillment_statuses();
cc_test(
	'Customer return requires delivered',
	in_array( 'delivered', $customer_return_statuses, true ) && ! in_array( 'shipped', $customer_return_statuses, true ),
	implode( ',', $customer_return_statuses )
);

$cancel_statuses = Consucorner_Order_Return_Workflow::cancel_eligible_fulfillment_statuses();
cc_test(
	'Cancel allowed until out for delivery',
	in_array( 'out_for_delivery', $cancel_statuses, true ) && ! in_array( 'delivered', $cancel_statuses, true ),
	implode( ',', $cancel_statuses )
);

// --- Find a recent order for integration tests ---
$orders = wc_get_orders(
	array(
		'limit'   => 20,
		'orderby' => 'date',
		'order'   => 'DESC',
		'return'  => 'objects',
		'type'    => 'shop_order',
		'status'  => array( 'processing', 'completed', 'on-hold', 'pending' ),
	)
);
$order = null;
foreach ( $orders as $candidate ) {
	if ( $candidate instanceof WC_Order && 'shop_order' === $candidate->get_type() ) {
		$order = $candidate;
		break;
	}
}
cc_test( 'Sample order exists', $order instanceof WC_Order, $order ? '#' . $order->get_id() : 'none' );

if ( $order ) {
	$payload = consucorner_profile_format_order_detail( $order );
	cc_test( 'Profile payload has fulfillment label', ! empty( $payload['fulfillment_label'] ), (string) ( $payload['fulfillment_label'] ?? '' ) );
	cc_test( 'Profile payload hides bosta_status', ! array_key_exists( 'bosta_status', $payload ), '' );
	cc_test( 'Profile payload has cancel fields', isset( $payload['can_request_cancel'] ), '' );

	$ops = Consucorner_Order_Return_Workflow::get_order_ops_payload( $order->get_id() );
	cc_test( 'Ops payload includes cancel_requests key', is_array( $ops ) && array_key_exists( 'cancel_requests', $ops ), '' );

	$vendor_ids = Consucorner_Order_Return_Workflow::get_order_vendor_ids( $order );
	if ( ! empty( $vendor_ids ) ) {
		$financial = Consucorner_Order_Return_Workflow::get_vendor_financial_summary( $order, $vendor_ids[0] );
		cc_test( 'Vendor financial summary has net_payable', isset( $financial['net_payable'] ), (string) ( $financial['net_payable'] ?? '' ) );

		$bosta_order = wc_create_order( array( 'customer_id' => (int) $order->get_customer_id() ) );
		$first_item  = current( $order->get_items( 'line_item' ) );
		if ( $bosta_order && $first_item instanceof WC_Order_Item_Product ) {
			$product = $first_item->get_product();
			if ( $product ) {
				$bosta_order->add_product( $product, 1 );
				$bosta_order->calculate_totals();
				$bosta_order->set_status( 'processing' );
				$bosta_order->save();
				$vendor_id = $vendor_ids[0];
				Consucorner_Order_Return_Workflow::update_fulfillment_status( $bosta_order->get_id(), $vendor_id, 'confirmed', 'Bosta sync test setup', 'operations' );
				Consucorner_Order_Return_Workflow::update_fulfillment_status( $bosta_order->get_id(), $vendor_id, 'preparing', 'Bosta sync test setup', 'operations' );
				$bosta_order->update_meta_data( 'bosta_status', 'Received at warehouse' );
				$bosta_order->update_meta_data( 'bosta_state_code', 12 );
				$bosta_order->save();
				Consucorner_Order_Return_Workflow::maybe_sync_fulfillment_from_bosta( wc_get_order( $bosta_order->get_id() ) );
				$bosta_summary = Consucorner_Order_Return_Workflow::get_customer_fulfillment_summary( wc_get_order( $bosta_order->get_id() ) );
				cc_test(
					'Bosta warehouse sync updates fulfillment to shipped',
					'shipped' === ( $bosta_summary['status'] ?? '' ),
					(string) ( $bosta_summary['status'] ?? '' )
				);
				$bosta_order->delete( true );
			}
		}
	} else {
		cc_test( 'Vendor financial summary has net_payable', true, 'skipped — no vendor on sample order' );
	}
}

// --- Cancellation request lifecycle on a dedicated test order ---
$customer = get_users(
	array(
		'role'   => 'customer',
		'number' => 1,
		'fields' => array( 'ID' ),
	)
);
$customer_id = ! empty( $customer ) ? (int) $customer[0]->ID : 0;
cc_test( 'Customer user exists for cancel test', $customer_id > 0, (string) $customer_id );

	if ( $customer_id && class_exists( 'WC_Order' ) ) {
		$product_id = 0;
		$vendor_products = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'author__not_in' => array( 0 ),
			)
		);
		if ( ! empty( $vendor_products ) ) {
			$product_id = (int) $vendor_products[0];
		}

	if ( $product_id ) {
		$test_order = wc_create_order( array( 'customer_id' => $customer_id ) );
		$test_order->add_product( wc_get_product( $product_id ), 1 );
		$test_order->calculate_totals();
		$test_order->set_status( 'processing' );
		$test_order->save();

		$test_order_id = $test_order->get_id();
		cc_test( 'Created test order for cancel flow', $test_order_id > 0, '#' . $test_order_id );

		Consucorner_Order_Return_Workflow::update_fulfillment_status( $test_order_id, Consucorner_Order_Return_Workflow::get_order_vendor_ids( $test_order )[0], 'confirmed', 'Test setup', 'operations' );

		$create = Consucorner_Order_Cancel_Requests::create_request( $test_order_id, $customer_id, true, array(), 'Smoke test cancel' );
		cc_test( 'Create cancel request', ! is_wp_error( $create ), is_wp_error( $create ) ? $create->get_error_message() : (string) ( $create['id'] ?? '' ) );

		if ( ! is_wp_error( $create ) ) {
			$request_id = (string) ( $create['id'] ?? '' );
			$approve    = Consucorner_Order_Cancel_Requests::review_request( $test_order_id, $request_id, 'approved', 'Smoke test approve' );
			cc_test( 'Approve cancel request', ! is_wp_error( $approve ), is_wp_error( $approve ) ? $approve->get_error_message() : 'approved' );

			$test_order = wc_get_order( $test_order_id );
			cc_test( 'Order cancelled after approval', $test_order && $test_order->has_status( 'cancelled' ), $test_order ? $test_order->get_status() : 'missing' );
		}
	} else {
		cc_test( 'Created test order for cancel flow', false, 'no published product found' );
	}
}

// --- Vendor ledger dataset smoke ---
if ( class_exists( 'Consucorner_Vendor_Ledger' ) ) {
	$reflection = new ReflectionClass( 'Consucorner_Vendor_Ledger' );
	$parse      = $reflection->getMethod( 'parse_filters' );
	$parse->setAccessible( true );
	$query = $reflection->getMethod( 'query_orders' );
	$query->setAccessible( true );
	$build = $reflection->getMethod( 'build_dataset' );
	$build->setAccessible( true );

	$f       = $parse->invoke( null, array() );
	$rows    = $query->invoke( null, $f );
	$dataset = $build->invoke( null, $rows, $f, 5 );
	$first   = $dataset['rows'][0] ?? array();
	cc_test(
		'Vendor ledger rows include return_deductions',
		array_key_exists( 'return_deductions', $first ) && array_key_exists( 'net_payable', $first ),
		empty( $first ) ? 'no ledger rows in default range' : ''
	);
}

$passed = count( array_filter( $GLOBALS['cc_test_results'], static function ( $row ) { return $row['pass']; } ) );
$failed = count( $GLOBALS['cc_test_results'] ) - $passed;
WP_CLI::log( '' );
WP_CLI::log( sprintf( 'Summary: %d passed, %d failed, %d total', $passed, $failed, count( $GLOBALS['cc_test_results'] ) ) );

if ( $failed > 0 ) {
	exit( 1 );
}
