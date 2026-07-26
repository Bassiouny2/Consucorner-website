<?php
/**
 * Order tracking form — ConsuCorner override.
 *
 * @package ConsuCorner
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

global $post;
?>

<form action="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" method="post" class="woocommerce-form woocommerce-form-track-order track_order">

	<?php do_action( 'woocommerce_order_tracking_form_start' ); ?>

	<p><?php esc_html_e( 'To track your order, enter your order number and billing email below, then press Track. You can use the order number from your confirmation email (for example #38546441) or your internal order ID.', 'consucorner' ); ?></p>

	<p class="form-row form-row-first">
		<label for="orderid"><?php esc_html_e( 'Order number', 'consucorner' ); ?></label>
		<input class="input-text" type="text" name="orderid" id="orderid" value="<?php echo isset( $_REQUEST['orderid'] ) ? esc_attr( wp_unslash( $_REQUEST['orderid'] ) ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. #38546441', 'consucorner' ); ?>" />
	</p>
	<p class="form-row form-row-last">
		<label for="order_email"><?php esc_html_e( 'Billing email', 'woocommerce' ); ?></label>
		<input class="input-text" type="text" name="order_email" id="order_email" value="<?php echo isset( $_REQUEST['order_email'] ) ? esc_attr( wp_unslash( $_REQUEST['order_email'] ) ) : ''; ?>" placeholder="<?php esc_attr_e( 'Email you used during checkout.', 'woocommerce' ); ?>" />
	</p>
	<div class="clear"></div>

	<?php do_action( 'woocommerce_order_tracking_form' ); ?>

	<p class="form-row">
		<button type="submit" class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="track" value="<?php esc_attr_e( 'Track', 'woocommerce' ); ?>">
			<?php esc_html_e( 'Track', 'woocommerce' ); ?>
		</button>
	</p>
	<?php wp_nonce_field( 'woocommerce-order_tracking', 'woocommerce-order-tracking-nonce' ); ?>

	<?php do_action( 'woocommerce_order_tracking_form_end' ); ?>

</form>
