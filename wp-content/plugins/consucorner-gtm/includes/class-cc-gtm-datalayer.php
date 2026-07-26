<?php
/**
 * dataLayer output and ecommerce payloads.
 *
 * @package ConsuCorner_GTM
 */

defined( 'ABSPATH' ) || exit;

/**
 * DataLayer handler.
 */
final class CC_GTM_Datalayer {

	/**
	 * @var int
	 */
	private static $purchase_order_id = 0;

	/**
	 * @param array<string, mixed> $payload Payload.
	 */
	public static function print_push( array $payload ) {
		if ( empty( $payload['event'] ) ) {
			return;
		}
		$ecommerce_events = array(
			'view_item',
			'view_item_list',
			'select_item',
			'add_to_cart',
			'view_cart',
			'begin_checkout',
			'purchase',
		);
		if ( in_array( $payload['event'], $ecommerce_events, true ) ) {
			echo "<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push({ecommerce:null});</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! $json ) {
			return;
		}
		printf(
			"<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push(%s);</script>\n",
			$json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);

		if ( CC_GTM_Settings::get( 'debug_mode', false ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			self::record_debug_event( $payload['event'] );
		}
	}

	/**
	 * @param string $event Event name.
	 */
	private static function record_debug_event( $event ) {
		$key = 'cc_gtm_debug_events';
		$list = get_transient( $key );
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		$list[] = array(
			'event' => sanitize_key( $event ),
			'time'  => time(),
		);
		$list = array_slice( $list, -30 );
		set_transient( $key, $list, HOUR_IN_SECONDS );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_debug_events() {
		$list = get_transient( 'cc_gtm_debug_events' );
		return is_array( $list ) ? $list : array();
	}

	/**
	 * Route-specific server-side events.
	 */
	public static function print_route_events() {
		if ( ! CC_GTM_Settings::get( 'enable_ecommerce', true ) ) {
			self::print_push( self::build_page_context_payload() );
			return;
		}

		$payloads = array( self::build_page_context_payload() );

		if ( function_exists( 'is_singular' ) && is_singular( 'product' ) ) {
			$view = self::build_view_item_payload();
			if ( $view ) {
				$payloads[] = $view;
			}
		} elseif ( function_exists( 'is_cart' ) && is_cart() && function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
			$payloads[] = self::build_cart_event_payload( 'view_cart' );
		} elseif ( function_exists( 'is_checkout' ) && is_checkout() && function_exists( 'is_wc_endpoint_url' ) && ! is_wc_endpoint_url( 'order-received' ) && WC()->cart && ! WC()->cart->is_empty() ) {
			$payloads[] = self::build_cart_event_payload( 'begin_checkout' );
		} elseif ( self::should_push_initial_item_list() ) {
			$list = self::build_view_item_list_payload();
			if ( $list ) {
				$payloads[] = $list;
			}
		}

		foreach ( $payloads as $payload ) {
			self::print_push( $payload );
		}
	}

	/**
	 * @return string
	 */
	public static function get_page_type() {
		if ( is_front_page() ) {
			return 'home';
		}
		if ( is_search() ) {
			return 'search';
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
			return 'purchase';
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return 'checkout';
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return 'cart';
		}
		if ( function_exists( 'consucorner_is_account_page' ) && consucorner_is_account_page() ) {
			return 'account';
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return 'account';
		}
		if ( function_exists( 'is_singular' ) && is_singular( 'product' ) ) {
			return 'product';
		}
		if ( function_exists( 'is_tax' ) && is_tax( 'specialty' ) ) {
			return 'specialty';
		}
		if ( function_exists( 'is_tax' ) && is_tax( 'procedure' ) ) {
			return 'procedure';
		}
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			return 'category';
		}
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return 'shop';
		}
		return 'content';
	}

	/**
	 * @return array{productCategory:?string,specialty:?string,procedure:?string}
	 */
	public static function get_archive_context_slugs() {
		$out = array(
			'productCategory' => null,
			'specialty'       => null,
			'procedure'       => null,
		);
		$obj = get_queried_object();
		if ( ! $obj instanceof WP_Term ) {
			return $out;
		}
		if ( 'product_cat' === $obj->taxonomy ) {
			$out['productCategory'] = $obj->slug;
		} elseif ( 'specialty' === $obj->taxonomy ) {
			$out['specialty'] = $obj->slug;
		} elseif ( 'procedure' === $obj->taxonomy ) {
			$out['procedure'] = $obj->slug;
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build_page_context_payload() {
		$ctx    = self::get_archive_context_slugs();
		$cart_n = 0;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$cart_n = (int) WC()->cart->get_cart_contents_count();
		}
		return array(
			'event'           => 'page_context',
			'pageType'        => self::get_page_type(),
			'loggedIn'        => is_user_logged_in(),
			'cartQuantity'    => $cart_n,
			'currency'        => CC_GTM_WooCommerce::get_currency(),
			'productCategory' => $ctx['productCategory'],
			'specialty'       => $ctx['specialty'],
			'procedure'       => $ctx['procedure'],
		);
	}

	/**
	 * @return array{item_list_id:string,item_list_name:string}
	 */
	public static function get_list_context() {
		$obj = get_queried_object();
		if ( $obj instanceof WP_Term ) {
			return array(
				'item_list_id'   => $obj->taxonomy . '_' . $obj->term_id,
				'item_list_name' => $obj->name,
			);
		}
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return array(
				'item_list_id'   => 'shop',
				'item_list_name' => __( 'Shop', 'consucorner-gtm' ),
			);
		}
		if ( is_search() ) {
			return array(
				'item_list_id'   => 'search',
				'item_list_name' => __( 'Search results', 'consucorner-gtm' ),
			);
		}
		return array(
			'item_list_id'   => 'product_list',
			'item_list_name' => __( 'Products', 'consucorner-gtm' ),
		);
	}

	/**
	 * @return bool
	 */
	public static function should_push_initial_item_list() {
		return false;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function build_view_item_payload() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product ) {
			return null;
		}
		$item  = CC_GTM_WooCommerce::product_to_item( $product, 1 );
		$value = (float) $product->get_price();
		return array(
			'event'     => 'view_item',
			'ecommerce' => array(
				'currency' => CC_GTM_WooCommerce::get_currency(),
				'value'    => $value,
				'items'    => array( $item ),
			),
		);
	}

	/**
	 * @param string $event Event slug.
	 * @return array<string, mixed>
	 */
	public static function build_cart_event_payload( $event ) {
		$cart   = WC()->cart;
		$items  = CC_GTM_WooCommerce::cart_items( $cart );
		$coupon = $cart->get_applied_coupons();
		$payload = array(
			'event'     => $event,
			'ecommerce' => array(
				'currency' => CC_GTM_WooCommerce::get_currency(),
				'value'    => (float) $cart->get_total( 'edit' ),
				'items'    => $items,
			),
		);
		if ( ! empty( $coupon ) ) {
			$payload['ecommerce']['coupon'] = implode( ',', array_map( 'strval', $coupon ) );
		}
		return $payload;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function build_view_item_list_payload() {
		global $wp_query;
		if ( ! $wp_query instanceof WP_Query || empty( $wp_query->posts ) ) {
			return null;
		}
		$items = CC_GTM_WooCommerce::items_from_posts( $wp_query->posts );
		if ( empty( $items ) ) {
			return null;
		}
		$list  = self::get_list_context();
		$value = 0.0;
		foreach ( $items as $item ) {
			$value += (float) ( $item['price'] ?? 0 ) * (float) ( $item['quantity'] ?? 1 );
		}
		return array(
			'event'     => 'view_item_list',
			'ecommerce' => array(
				'currency'       => CC_GTM_WooCommerce::get_currency(),
				'value'          => $value,
				'item_list_id'   => $list['item_list_id'],
				'item_list_name' => $list['item_list_name'],
				'items'          => $items,
			),
		);
	}

	/**
	 * @param int $order_id Order ID.
	 */
	public static function capture_purchase_order_from_thankyou( $order_id ) {
		$order_id = absint( $order_id );
		if ( $order_id ) {
			self::$purchase_order_id = $order_id;
		}
	}

	/**
	 * Capture order ID on thank-you route.
	 */
	public static function capture_purchase_order_id() {
		if ( self::$purchase_order_id ) {
			return;
		}
		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}
		$order = self::resolve_thankyou_order();
		if ( $order ) {
			self::$purchase_order_id = $order->get_id();
			return;
		}
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			if ( preg_match( '#/order-received/([0-9]+)/?#', (string) wp_unslash( $_SERVER['REQUEST_URI'] ), $matches ) ) {
				self::$purchase_order_id = absint( $matches[1] );
			}
		}
	}

	/**
	 * Output purchase in footer.
	 */
	public static function print_captured_purchase() {
		$order_id = self::$purchase_order_id;
		self::$purchase_order_id = 0;

		if ( ! $order_id && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			if ( preg_match( '#/order-received/([0-9]+)/?#', (string) wp_unslash( $_SERVER['REQUEST_URI'] ), $matches ) ) {
				$order_id = absint( $matches[1] );
			}
		}
		if ( ! $order_id ) {
			return;
		}

		$payload = self::build_purchase_payload_for_order( $order_id );
		if ( ! $payload ) {
			return;
		}
		self::print_push( $payload );
		self::mark_purchase_tracked( $order_id );
	}

	/**
	 * @return WC_Order|null
	 */
	public static function resolve_thankyou_order() {
		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) ) {
			return null;
		}
		global $wp;
		$order_id = absint( get_query_var( 'order-received' ) );
		if ( ! $order_id && isset( $wp->query_vars['order-received'] ) ) {
			$order_id = absint( $wp->query_vars['order-received'] );
		}
		if ( ! $order_id && function_exists( 'WC' ) && WC()->query ) {
			$endpoint = WC()->query->get_query_vars();
			if ( ! empty( $endpoint['order-received'] ) && isset( $wp->query_vars[ $endpoint['order-received'] ] ) ) {
				$order_id = absint( $wp->query_vars[ $endpoint['order-received'] ] );
			}
		}
		if ( ! $order_id && ! empty( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_id = wc_get_order_id_by_order_key( wc_clean( wp_unslash( $_GET['key'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! $order_id && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			if ( preg_match( '#/order-received/([0-9]+)/?#', (string) wp_unslash( $_SERVER['REQUEST_URI'] ), $matches ) ) {
				$order_id = absint( $matches[1] );
			}
		}
		if ( ! $order_id ) {
			return null;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}
		if ( ! empty( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_key = wc_clean( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $order_key && ! hash_equals( $order->get_order_key(), $order_key ) ) {
				return null;
			}
		}
		return $order;
	}

	/**
	 * @param int $order_id Order ID.
	 * @return array<string, mixed>|null
	 */
	public static function build_purchase_payload_for_order( $order_id ) {
		$meta_key = defined( 'CC_GTM_PURCHASE_META' ) ? CC_GTM_PURCHASE_META : '_cc_gtm_purchase_tracked';
		$order    = wc_get_order( absint( $order_id ) );
		if ( ! $order ) {
			return null;
		}
		if ( '1' === (string) $order->get_meta( $meta_key, true ) ) {
			return null;
		}

		$items = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$row = CC_GTM_WooCommerce::product_to_item( $product, (int) $item->get_quantity() );
			$row['price'] = (float) $order->get_item_total( $item, false, false );
			$items[] = $row;
		}

		$coupons = $order->get_coupon_codes();
		$payload = array(
			'event'     => 'purchase',
			'ecommerce' => array(
				'transaction_id' => (string) $order->get_order_number(),
				'value'          => (float) $order->get_total(),
				'tax'            => (float) $order->get_total_tax(),
				'shipping'       => (float) $order->get_shipping_total(),
				'currency'       => $order->get_currency(),
				'payment_type'   => $order->get_payment_method(),
				'items'          => $items,
			),
		);
		if ( ! empty( $coupons ) ) {
			$payload['ecommerce']['coupon'] = implode( ',', $coupons );
		}
		return $payload;
	}

	/**
	 * @param int $order_id Order ID.
	 */
	public static function mark_purchase_tracked( $order_id ) {
		$meta_key = defined( 'CC_GTM_PURCHASE_META' ) ? CC_GTM_PURCHASE_META : '_cc_gtm_purchase_tracked';
		$order    = wc_get_order( absint( $order_id ) );
		if ( ! $order ) {
			return;
		}
		$order->update_meta_data( $meta_key, '1' );
		$order->save();
	}
}
