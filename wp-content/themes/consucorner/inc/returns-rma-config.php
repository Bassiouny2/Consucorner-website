<?php
/**
 * Site-wide Dokan RMA defaults: all orders (old + new) return-eligible.
 *
 * @package Consucorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Configure Dokan RMA and backfill order line warranty meta.
 */
final class Consucorner_Returns_Rma_Config {

	const CONFIG_VERSION       = '1.0.3';
	const OPTION_VERSION       = 'consucorner_rma_config_version';
	const OPTION_BACKFILL_AT   = 'consucorner_rma_backfill_completed_at';
	const OPTION_REWRITE_FLUSH = 'consucorner_rma_rewrite_flushed';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_apply_site_configuration' ), 5 );
		add_action( 'after_switch_theme', array( __CLASS__, 'force_apply_site_configuration' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'stamp_checkout_line_item_warranty' ), 99, 4 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'ensure_order_line_items_warranty' ), 20, 1 );
		add_filter( 'dokan_warranty_request_type', array( __CLASS__, 'ensure_return_request_types' ) );
	}

	/**
	 * Universal included warranty meta stamped on every line item.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_default_line_item_warranty() {
		return array(
			'label'           => __( 'Return', 'consucorner' ),
			'type'            => 'included_warranty',
			'policy'          => '',
			'reasons'         => array(),
			'length'          => 'lifetime',
			'length_value'    => 'lifetime',
			'length_duration' => '',
			'addon_settings'  => array(),
			'from'            => 'store',
		);
	}

	/**
	 * Default vendor-level RMA settings applied to every seller.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_default_vendor_settings() {
		$reason_ids = array_keys( self::get_admin_reason_map() );

		return array(
			'label'           => __( 'Return', 'consucorner' ),
			'type'            => 'included_warranty',
			'policy'          => '',
			'reasons'         => $reason_ids,
			'length'          => 'lifetime',
			'length_value'    => 'lifetime',
			'length_duration' => '',
			'addon_settings'  => array(),
			'from'            => 'store',
		);
	}

	/**
	 * Admin RMA reason id => label map.
	 *
	 * @return array<string,string>
	 */
	public static function get_admin_reason_map() {
		if ( function_exists( 'dokan_rma_refund_reasons' ) ) {
			$reasons = dokan_rma_refund_reasons();
			if ( is_array( $reasons ) && ! empty( $reasons ) ) {
				return $reasons;
			}
		}

		return array(
			'not_needed'     => __( 'Not Needed', 'consucorner' ),
			'wrong_product'  => __( 'Wrong Product', 'consucorner' ),
			'broken_product' => __( 'Broken Product', 'consucorner' ),
		);
	}

	/**
	 * Apply configuration once per version.
	 */
	public static function maybe_apply_site_configuration() {
		if ( get_option( self::OPTION_VERSION ) === self::CONFIG_VERSION ) {
			return;
		}

		self::force_apply_site_configuration();
	}

	/**
	 * Apply admin options, vendor defaults, and backfill historical line items.
	 */
	public static function force_apply_site_configuration() {
		self::apply_admin_rma_options();
		self::sync_all_vendor_rma_settings();
		self::backfill_all_order_line_items();
		self::maybe_flush_rma_rewrite_rules();
		update_option( self::OPTION_VERSION, self::CONFIG_VERSION );
		update_option( self::OPTION_BACKFILL_AT, current_time( 'mysql' ) );
	}

	/**
	 * Ensure Dokan RMA account endpoints resolve (request-warranty, rma-requests).
	 */
	public static function maybe_flush_rma_rewrite_rules() {
		if ( get_option( self::OPTION_REWRITE_FLUSH ) === self::CONFIG_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::OPTION_REWRITE_FLUSH, self::CONFIG_VERSION );
	}

	/**
	 * Dokan → Settings → RMA site options.
	 */
	public static function apply_admin_rma_options() {
		$reasons = array();
		foreach ( self::get_admin_reason_map() as $id => $label ) {
			$reasons[] = array(
				'id'    => sanitize_key( $id ),
				'value' => $label,
			);
		}

		$options = get_option( 'dokan_rma', array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$options['rma_order_status']            = 'wc-completed';
		$options['rma_enable_refund_request']   = 'on';
		$options['rma_enable_coupon_request']   = 'off';
		$options['rma_reasons']               = $reasons;

		if ( empty( $options['rma_policy'] ) ) {
			$options['rma_policy'] = __(
				'Returns can be requested after ConsuCorner operations marks your order as shipped or delivered. Refunds are processed to your wallet or manually after review.',
				'consucorner'
			);
		}

		update_option( 'dokan_rma', $options );
	}

	/**
	 * Push default included warranty settings to every Dokan vendor.
	 */
	public static function sync_all_vendor_rma_settings() {
		$defaults = self::get_default_vendor_settings();
		$user_ids = get_users(
			array(
				'role__in' => array( 'seller', 'vendor' ),
				'fields'   => 'ID',
				'number'   => 5000,
			)
		);

		foreach ( (array) $user_ids as $user_id ) {
			update_user_meta( absint( $user_id ), '_dokan_rma_settings', $defaults );
		}
	}

	/**
	 * Stamp warranty on new checkout line items (overrides vendor no-warranty).
	 *
	 * @param WC_Order_Item_Product $item Order line item.
	 * @param string                $cart_item_key Cart key.
	 * @param array                 $values Cart values.
	 * @param WC_Order              $order Order.
	 */
	public static function stamp_checkout_line_item_warranty( $item, $cart_item_key, $values, $order ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}

		$item->update_meta_data( '_dokan_item_warranty', self::get_default_line_item_warranty() );
	}

	/**
	 * Ensure all line items on a completed order carry return warranty meta.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function ensure_order_line_items_warranty( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$changed = false;
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( self::ensure_line_item_warranty( $item, false ) ) {
				$changed = true;
			}
		}

		if ( $changed ) {
			$order->save();
		}
	}

	/**
	 * Ensure a single line item has universal return warranty meta.
	 *
	 * @param WC_Order_Item $item Line item.
	 * @param bool          $save Whether to save the item immediately.
	 * @return bool True when meta was added/updated.
	 */
	public static function ensure_line_item_warranty( $item, $save = true ) {
		if ( ! $item instanceof WC_Order_Item ) {
			return false;
		}

		$existing = $item->get_meta( '_dokan_item_warranty', true );
		if ( self::is_valid_return_warranty( $existing ) ) {
			return false;
		}

		$item->update_meta_data( '_dokan_item_warranty', self::get_default_line_item_warranty() );
		if ( $save ) {
			$item->save();
		}

		return true;
	}

	/**
	 * Whether stored warranty meta already allows returns.
	 *
	 * @param mixed $warranty Warranty meta.
	 * @return bool
	 */
	private static function is_valid_return_warranty( $warranty ) {
		if ( ! is_array( $warranty ) ) {
			return false;
		}

		$type = isset( $warranty['type'] ) ? (string) $warranty['type'] : '';
		return in_array( $type, array( 'included_warranty', 'addon_warranty' ), true );
	}

	/**
	 * Backfill every historical line item missing warranty meta.
	 *
	 * @return int Number of line items updated.
	 */
	public static function backfill_all_order_line_items() {
		global $wpdb;

		$table_items = $wpdb->prefix . 'woocommerce_order_items';
		$table_meta  = $wpdb->prefix . 'woocommerce_order_itemmeta';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$item_ids = $wpdb->get_col(
			"SELECT oi.order_item_id
			FROM {$table_items} oi
			LEFT JOIN {$table_meta} om
				ON oi.order_item_id = om.order_item_id
				AND om.meta_key = '_dokan_item_warranty'
			WHERE oi.order_item_type = 'line_item'
				AND ( om.meta_id IS NULL OR om.meta_value = '' OR om.meta_value LIKE '%no_warranty%' )"
		);

		$updated = 0;
		$warranty = self::get_default_line_item_warranty();

		foreach ( (array) $item_ids as $item_id ) {
			$item_id = absint( $item_id );
			if ( ! $item_id ) {
				continue;
			}

			$item = WC_Order_Factory::get_order_item( $item_id );
			if ( ! $item ) {
				continue;
			}

			$item->update_meta_data( '_dokan_item_warranty', $warranty );
			$item->save();
			++$updated;
		}

		return $updated;
	}

	/**
	 * Ensure return/refund appears as a customer request type (money still resolved by staff).
	 *
	 * @param array<string,string> $types Request types.
	 * @return array<string,string>
	 */
	public static function ensure_return_request_types( $types ) {
		if ( ! is_array( $types ) ) {
			$types = array();
		}

		if ( isset( $types['refund'] ) ) {
			$types['refund'] = __( 'Return / Refund', 'consucorner' );
		}

		if ( ! isset( $types['replace'] ) ) {
			$types['replace'] = __( 'Replace', 'dokan' );
		}

		return $types;
	}

	/**
	 * Lazy backfill for one order before eligibility checks.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function ensure_order_ready_for_returns( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		self::ensure_order_line_items_warranty( $order->get_id() );
	}
}

Consucorner_Returns_Rma_Config::init();
