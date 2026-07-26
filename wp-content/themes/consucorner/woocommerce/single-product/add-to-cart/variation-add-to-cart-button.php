<?php
/**
 * Single variation cart button — ConsuCorner themed layout.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package ConsuCorner
 * @version 10.5.2
 */

defined( 'ABSPATH' ) || exit;

global $product;

$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' );
$min_qty      = $product->get_min_purchase_quantity();
$max_qty      = 0 < $product->get_max_purchase_quantity() ? $product->get_max_purchase_quantity() : '';
$input_qty    = isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $min_qty; // phpcs:ignore WordPress.Security.NonceVerification.Missing
?>
<div class="woocommerce-variation-add-to-cart variations_button sp-variation-actions">
	<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

	<div class="sp-actions sp-actions--toolbar">
		<div class="sp-qty">
			<button type="button" class="sp-qty-btn" id="spQtyMinus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'consucorner' ); ?>">−</button>
			<?php
			do_action( 'woocommerce_before_add_to_cart_quantity' );
			?>
			<input
				type="number"
				class="sp-qty-val qty"
				id="spQtyVal"
				name="quantity"
				value="<?php echo esc_attr( $input_qty ); ?>"
				min="<?php echo esc_attr( $min_qty ); ?>"
				<?php echo $max_qty ? 'max="' . esc_attr( $max_qty ) . '"' : ''; ?>
				step="1"
				inputmode="numeric"
				aria-label="<?php esc_attr_e( 'Quantity', 'consucorner' ); ?>"
			/>
			<?php
			do_action( 'woocommerce_after_add_to_cart_quantity' );
			?>
			<button type="button" class="sp-qty-btn" id="spQtyPlus" aria-label="<?php esc_attr_e( 'Increase quantity', 'consucorner' ); ?>">+</button>
		</div>
	</div>

	<div class="sp-actions sp-actions--cta">
		<a
			href="#"
			class="sp-btn-buy is-disabled"
			id="spBuyNow"
			data-checkout="<?php echo esc_url( $checkout_url ); ?>"
			data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
			aria-disabled="true"
		><?php esc_html_e( 'Buy now', 'consucorner' ); ?></a>
		<button type="submit" class="sp-btn-cart single_add_to_cart_button button alt<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" data-cc-tour="add-to-cart" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-product-name="<?php echo esc_attr( $product->get_name() ); ?>" data-product-permalink="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
			<?php echo esc_html( $product->single_add_to_cart_text() ); ?>
		</button>
	</div>

	<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

	<input type="hidden" name="add-to-cart" value="<?php echo absint( $product->get_id() ); ?>" />
	<input type="hidden" name="product_id" value="<?php echo absint( $product->get_id() ); ?>" />
	<input type="hidden" name="variation_id" class="variation_id" value="0" />
</div>
