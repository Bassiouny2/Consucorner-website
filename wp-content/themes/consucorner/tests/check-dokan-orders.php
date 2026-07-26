<?php
define( 'WP_USE_THEMES', false );
require dirname( __DIR__, 4 ) . '/wp-load.php';

global $wpdb;
$vendor_id = 210;
$order_ids = array( 5713, 5714, 5715, 5716, 5717, 5718, 5719, 5720 );

foreach ( $order_ids as $oid ) {
	$row = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT seller_id, order_total FROM ' . $wpdb->prefix . 'dokan_orders WHERE order_id = %d',
			$oid
		)
	);
	echo 'Order ' . $oid . ': ' . ( $row ? 'seller=' . $row->seller_id . ' total=' . $row->order_total : 'MISSING in dokan_orders' ) . PHP_EOL;
}

// Compare with a real checkout order if any exists for mrsa
$sample = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT order_id FROM ' . $wpdb->prefix . 'dokan_orders WHERE seller_id = %d ORDER BY order_id DESC LIMIT 1',
		$vendor_id
	)
);
echo 'Latest dokan order for vendor 210: ' . ( $sample ? $sample->order_id : 'none' ) . PHP_EOL;
