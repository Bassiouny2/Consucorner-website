<?php
/**
 * Variable product — attribute swatches / dropdowns (rendered under price).
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product ) {
	return;
}

$attribute_keys = array_keys( $attributes );

if ( empty( $available_variations ) && false !== $available_variations ) {
	?>
	<p class="stock out-of-stock"><?php echo esc_html( apply_filters( 'woocommerce_out_of_stock_message', __( 'This product is currently out of stock and unavailable.', 'woocommerce' ) ) ); ?></p>
	<?php
	return;
}
?>
<div class="sp-variations variations" role="presentation">
	<?php
	foreach ( $attributes as $attribute_name => $options ) {
		cc_render_variation_attribute_field(
			$attribute_name,
			$options,
			$product,
			end( $attribute_keys ) === $attribute_name
		);
	}
	?>
</div>
<div class="reset_variations_alert screen-reader-text" role="alert" aria-live="polite" aria-relevant="all"></div>
<?php do_action( 'woocommerce_after_variations_table' ); ?>
