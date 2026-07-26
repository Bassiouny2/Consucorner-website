<?php
/**
 * Fast duplicate detection for migrated orders (HPOS-safe).
 *
 * @package ConsuCorner_Order_Migration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Indexes old order IDs / keys already present on the destination site.
 */
class CC_Order_Migration_Index {

	/**
	 * @var array<int, int> old_id => local_order_id
	 */
	private $by_old_id = array();

	/**
	 * @var array<string, int> order_key => local_order_id
	 */
	private $by_order_key = array();

	/**
	 * @var string
	 */
	private $meta_key;

	/**
	 * @param string $meta_key Migration meta key.
	 */
	public function __construct( $meta_key ) {
		$this->meta_key = $meta_key;
	}

	/**
	 * Load all migrated mappings from DB (includes trashed orders).
	 *
	 * @return void
	 */
	public function load() {
		global $wpdb;

		$this->by_old_id    = array();
		$this->by_order_key = array();

		$rows = $this->query_meta_rows( $this->meta_key );
		foreach ( $rows as $row ) {
			$local_id = (int) $row['order_id'];
			$old_id   = absint( $row['meta_value'] );
			if ( $old_id > 0 ) {
				$this->by_old_id[ $old_id ] = $local_id;
			}
		}

		$key_rows = $this->query_meta_rows( '_cc_migrated_from_order_key' );
		foreach ( $key_rows as $row ) {
			$key = (string) $row['meta_value'];
			if ( '' !== $key ) {
				$this->by_order_key[ $key ] = (int) $row['order_id'];
			}
		}
	}

	/**
	 * @param string $meta_key Meta key.
	 * @return array<int, array{order_id: int, meta_value: string}>
	 */
	private function query_meta_rows( $meta_key ) {
		global $wpdb;

		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$table = $wpdb->prefix . 'wc_orders_meta';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"SELECT order_id, meta_value FROM {$table} WHERE meta_key = %s",
				$meta_key
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"SELECT post_id AS order_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				$meta_key
			);
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param int    $old_id    Source order ID.
	 * @param string $order_key Source order key.
	 * @return int Local order ID or 0.
	 */
	public function find_local_id( $old_id, $order_key = '' ) {
		$old_id = absint( $old_id );
		if ( $old_id > 0 && isset( $this->by_old_id[ $old_id ] ) ) {
			return (int) $this->by_old_id[ $old_id ];
		}

		$order_key = sanitize_text_field( (string) $order_key );
		if ( '' !== $order_key && isset( $this->by_order_key[ $order_key ] ) ) {
			return (int) $this->by_order_key[ $order_key ];
		}

		return 0;
	}

	/**
	 * Register a newly imported mapping in memory.
	 *
	 * @param int    $old_id    Source order ID.
	 * @param int    $local_id  New order ID.
	 * @param string $order_key Source order key.
	 * @return void
	 */
	public function remember( $old_id, $local_id, $order_key = '' ) {
		$old_id   = absint( $old_id );
		$local_id = absint( $local_id );

		if ( $old_id > 0 && $local_id > 0 ) {
			$this->by_old_id[ $old_id ] = $local_id;
		}
		if ( '' !== (string) $order_key && $local_id > 0 ) {
			$this->by_order_key[ (string) $order_key ] = $local_id;
		}
	}

	/**
	 * @return int
	 */
	public function count() {
		return count( $this->by_old_id );
	}
}
