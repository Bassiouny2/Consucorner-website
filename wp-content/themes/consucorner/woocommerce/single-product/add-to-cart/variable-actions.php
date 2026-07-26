<?php
/**
 * Variable product — qty + buy / add-to-cart (after description).
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $available_variations ) && false !== $available_variations ) {
	return;
}
?>
<div class="single_variation_wrap">
	<?php
	do_action( 'woocommerce_before_single_variation' );
	do_action( 'woocommerce_single_variation' );
	do_action( 'woocommerce_after_single_variation' );
	?>
</div>
