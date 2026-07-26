<?php
/**
 * WooCommerce free-shipping display helpers (cart + mini-cart).
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Scan enabled WooCommerce free_shipping methods and build display config.
 *
 * @return array{
 *     enabled: bool,
 *     has_threshold: bool,
 *     min_amount: float,
 *     subtitle: string
 * }
 */
function consucorner_get_free_shipping_display() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$empty = array(
		'enabled'       => false,
		'has_threshold' => false,
		'min_amount'    => 0.0,
		'subtitle'      => '',
	);

	if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Shipping_Zones' ) ) {
		$cache = $empty;
		return $cache;
	}

	$min_amounts          = array();
	$has_any_enabled      = false;
	$has_unconditional    = false;
	$zone_ids             = array( 0 );

	foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
		if ( isset( $zone['zone_id'] ) ) {
			$zone_ids[] = (int) $zone['zone_id'];
		}
	}

	foreach ( array_unique( $zone_ids ) as $zone_id ) {
		$zone = new WC_Shipping_Zone( $zone_id );

		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			if ( 'free_shipping' !== $method->id ) {
				continue;
			}

			$has_any_enabled = true;
			$requires        = $method->get_option( 'requires', '' );

			if ( in_array( $requires, array( 'min_amount', 'either', 'both' ), true ) ) {
				$amount = (float) wc_format_decimal( $method->get_option( 'min_amount', 0 ) );
				if ( $amount > 0 ) {
					$min_amounts[] = $amount;
				}
			} elseif ( '' === $requires ) {
				$has_unconditional = true;
			}
		}
	}

	if ( ! $has_any_enabled ) {
		$cache = $empty;
		return $cache;
	}

	if ( ! empty( $min_amounts ) ) {
		$min = min( $min_amounts );

		$cache = array(
			'enabled'       => true,
			'has_threshold' => true,
			'min_amount'    => $min,
			'subtitle'      => sprintf(
				/* translators: %s: formatted minimum order amount */
				__( 'Free shipping over %s', 'consucorner' ),
				wp_strip_all_tags( wc_price( $min ) )
			),
		);
		return $cache;
	}

	if ( $has_unconditional ) {
		$cache = array(
			'enabled'       => true,
			'has_threshold' => false,
			'min_amount'    => 0.0,
			'subtitle'      => __( 'Free shipping', 'consucorner' ),
		);
		return $cache;
	}

	/* Coupon-only free shipping — no threshold to show in progress UI. */
	$cache = $empty;
	return $cache;
}
