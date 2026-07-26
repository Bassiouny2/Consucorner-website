<?php
/**
 * WooCommerce / product helpers for dataLayer items.
 *
 * @package ConsuCorner_GTM
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce GTM helpers.
 */
final class CC_GTM_WooCommerce {

	/**
	 * @return int
	 */
	public static function list_limit() {
		return defined( 'CC_GTM_LIST_LIMIT' ) ? (int) CC_GTM_LIST_LIMIT : 20;
	}

	/**
	 * @return string
	 */
	public static function get_currency() {
		return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EGP';
	}

	/**
	 * @param WC_Product $product Product.
	 * @param int        $qty Quantity.
	 * @return array<string, mixed>
	 */
	public static function product_to_item( $product, $qty = 1 ) {
		$qty = max( 1, (int) $qty );

		$item = array(
			'item_id'   => (string) $product->get_id(),
			'item_sku'  => (string) $product->get_sku(),
			'item_name' => $product->get_name(),
			'price'     => (float) wc_get_price_to_display( $product ),
			'quantity'  => $qty,
		);

		$cat = self::first_term_slug( $product->get_id(), 'product_cat' );
		if ( $cat ) {
			$item['item_category'] = $cat;
		}

		$specialty = self::first_term_slug( $product->get_id(), 'specialty' );
		if ( $specialty ) {
			$item['item_category2'] = $specialty;
		}

		$procedure = self::first_term_slug( $product->get_id(), 'procedure' );
		if ( $procedure ) {
			$item['item_category3'] = $procedure;
		}

		$vendor = self::product_vendor( $product->get_id() );
		if ( $vendor ) {
			$item['item_brand'] = $vendor;
		}

		return $item;
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string|null
	 */
	public static function first_term_slug( $product_id, $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}
		$terms = get_the_terms( $product_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}
		foreach ( $terms as $term ) {
			if ( 'uncategorized' === $term->slug ) {
				continue;
			}
			return $term->slug;
		}
		return $terms[0]->slug;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return string|null
	 */
	public static function product_vendor( $product_id ) {
		if ( ! function_exists( 'dokan_get_vendor_by_product' ) ) {
			return null;
		}
		$vendor = dokan_get_vendor_by_product( $product_id );
		if ( ! $vendor || ! method_exists( $vendor, 'get_shop_name' ) ) {
			return null;
		}
		$name = $vendor->get_shop_name();
		return $name ? (string) $name : null;
	}

	/**
	 * @param WC_Cart|null $cart Cart.
	 * @return array<int, array<string, mixed>>
	 */
	public static function cart_items( $cart ) {
		$items = array();
		if ( ! $cart ) {
			return $items;
		}
		foreach ( $cart->get_cart() as $line ) {
			$product = isset( $line['data'] ) ? $line['data'] : null;
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			$row = self::product_to_item( $product, (int) ( $line['quantity'] ?? 1 ) );
			$row['price'] = isset( $line['line_total'] ) && (int) $line['quantity'] > 0
				? (float) $line['line_total'] / (int) $line['quantity']
				: $row['price'];
			$items[] = $row;
		}
		return array_slice( $items, 0, self::list_limit() );
	}

	/**
	 * @param array<int, WP_Post|int> $posts Posts.
	 * @param int                     $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function items_from_posts( $posts, $limit = 0 ) {
		if ( $limit <= 0 ) {
			$limit = self::list_limit();
		}
		$items = array();
		foreach ( $posts as $post ) {
			$id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
			if ( ! $product ) {
				continue;
			}
			$items[] = self::product_to_item( $product, 1 );
			if ( count( $items ) >= $limit ) {
				break;
			}
		}
		return $items;
	}

	/**
	 * @param WC_Product $product Product.
	 * @return array<string, string>
	 */
	public static function product_data_attributes( $product ) {
		$item = self::product_to_item( $product, 1 );
		return array(
			'data-product-id'       => (string) $product->get_id(),
			'data-product_id'       => (string) $product->get_id(),
			'data-product-sku'      => (string) $item['item_sku'],
			'data-product_sku'      => (string) $item['item_sku'],
			'data-product-name'     => (string) $item['item_name'],
			'data-product_name'     => (string) $item['item_name'],
			'data-product-price'    => (string) $item['price'],
			'data-product_price'    => (string) $item['price'],
			'data-product-category' => isset( $item['item_category'] ) ? (string) $item['item_category'] : '',
			'data-product_category' => isset( $item['item_category'] ) ? (string) $item['item_category'] : '',
			'data-specialty'        => isset( $item['item_category2'] ) ? (string) $item['item_category2'] : '',
			'data-procedure'        => isset( $item['item_category3'] ) ? (string) $item['item_category3'] : '',
			'data-vendor'           => isset( $item['item_brand'] ) ? (string) $item['item_brand'] : '',
			'data-item_brand'       => isset( $item['item_brand'] ) ? (string) $item['item_brand'] : '',
		);
	}

	/**
	 * @param WC_Product $product Product.
	 */
	public static function print_product_data_attributes( $product ) {
		foreach ( self::product_data_attributes( $product ) as $key => $value ) {
			if ( '' === $value ) {
				continue;
			}
			printf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}
	}

	/**
	 * @param array<int, WP_Post> $posts Posts.
	 * @return array<string, mixed>
	 */
	public static function filter_ajax_payload( $posts ) {
		$list  = CC_GTM_Datalayer::get_list_context();
		$items = self::items_from_posts( $posts );
		$value = 0.0;
		foreach ( $items as $item ) {
			$value += (float) ( $item['price'] ?? 0 );
		}
		return array(
			'gtm_items'      => $items,
			'gtm_list_id'    => $list['item_list_id'],
			'gtm_list_name'  => $list['item_list_name'],
			'gtm_list_value' => $value,
		);
	}
}
