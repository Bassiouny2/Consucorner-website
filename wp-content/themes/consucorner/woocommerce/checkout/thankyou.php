<?php
/**
 * Thankyou page
 *
 * Overrides woocommerce/checkout/thankyou.php to render the
 * ConsuCorner thank-you design (`ty-*` classes).
 *
 * @package ConsuCorner
 *
 * @var WC_Order|false $order
 */

defined( 'ABSPATH' ) || exit;

$order = isset( $order ) ? $order : false;

if ( ! $order ) :
	?>
	<main class="ty-main">
		<section class="ty-wrap">
			<article class="ty-card">
				<h1 class="ty-title"><?php esc_html_e( 'Thank you', 'consucorner' ); ?></h1>
				<p><?php esc_html_e( 'Your order has been received.', 'consucorner' ); ?></p>
			</article>
		</section>
	</main>
	<?php
	return;
endif;

$order_id        = $order->get_id();
$customer_name   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
$customer_email  = $order->get_billing_email();
$customer_phone  = $order->get_billing_phone();
$payment_method  = $order->get_payment_method_title();
$is_cod          = ( 'cod' === $order->get_payment_method() );
$is_failed       = $order->has_status( 'failed' );
$wallet_credit_used = 0.0;

foreach ( $order->get_fees() as $fee_item ) {
	if ( ! ( $fee_item instanceof WC_Order_Item_Fee ) ) {
		continue;
	}

	$fee_total     = (float) $fee_item->get_total();
	$fee_name      = strtolower( wp_strip_all_tags( $fee_item->get_name() ) );
	$is_wallet_fee = 'yes' === (string) $fee_item->get_meta( '_cc_wallet_order_charge', true )
		|| false !== strpos( $fee_name, 'wallet credit' );

	if ( $is_wallet_fee && $fee_total < 0 ) {
		$wallet_credit_used += abs( $fee_total );
	}
}

if ( $wallet_credit_used <= 0 ) {
	$wallet_credit_used = max(
		(float) $order->get_meta( '_cc_wallet_credit_used', true ),
		(float) $order->get_meta( '_cc_wallet_admin_charged_total', true )
	);
}

$billing_address  = $order->get_formatted_billing_address();
$shipping_address = $order->get_formatted_shipping_address();
$same_address     = ( ! $shipping_address ) || ( $billing_address === $shipping_address );
$display_shipping = $shipping_address ? $shipping_address : $billing_address;
$shipping_name    = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
if ( ! $shipping_name ) {
	$shipping_name = $customer_name;
}

$run_thankyou_hooks = static function () use ( $order, $order_id ) {
	$default_details_priority = has_action( 'woocommerce_thankyou', 'woocommerce_order_details_table' );
	$payment_hook             = 'woocommerce_thankyou_' . $order->get_payment_method();
	$gateway                  = null;
	$gateway_priority         = false;

	if ( false !== $default_details_priority ) {
		remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', $default_details_priority );
	}

	if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
		$gateways = WC()->payment_gateways()->payment_gateways();
		$gateway  = isset( $gateways[ $order->get_payment_method() ] ) ? $gateways[ $order->get_payment_method() ] : null;
	}

	if ( $gateway
		&& in_array( $order->get_payment_method(), array( 'cod', 'bacs', 'cheque' ), true )
		&& method_exists( $gateway, 'thankyou_page' ) ) {
		$gateway_priority = has_action( $payment_hook, array( $gateway, 'thankyou_page' ) );
		if ( false !== $gateway_priority ) {
			remove_action( $payment_hook, array( $gateway, 'thankyou_page' ), $gateway_priority );
		}
	}

	do_action( $payment_hook, $order_id );
	do_action( 'woocommerce_thankyou', $order_id );

	if ( $gateway && false !== $gateway_priority ) {
		add_action( $payment_hook, array( $gateway, 'thankyou_page' ), $gateway_priority );
	}

	if ( false !== $default_details_priority ) {
		add_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', $default_details_priority );
	}
};
?>

<?php do_action( 'woocommerce_before_thankyou', $order_id ); ?>

<main class="ty-main">
	<section class="ty-wrap">
		<?php if ( $is_failed ) : ?>
			<article class="ty-card">
				<h1 class="ty-title"><?php esc_html_e( 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction.', 'consucorner' ); ?></h1>
				<p><?php esc_html_e( 'Please attempt your purchase again.', 'consucorner' ); ?></p>
				<p><a class="cart-checkout-btn" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Pay', 'consucorner' ); ?></a></p>
			</article>
			<?php $run_thankyou_hooks(); ?>
		<?php else : ?>
			<article class="ty-card">
				<p class="ty-order-id">ORDER #<?php echo esc_html( $order->get_order_number() ); ?></p>
				<h1 class="ty-title">
					<svg class="ty-title-icon" viewBox="0 0 24 24" aria-hidden="true">
						<path d="M6 11V6a1.5 1.5 0 0 1 3 0v4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						<path d="M9 10V4.5a1.5 1.5 0 0 1 3 0V10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						<path d="M12 10V5.2a1.5 1.5 0 0 1 3 0V12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						<path d="M15 12V8.5a1.5 1.5 0 0 1 3 0V14c0 3-2.5 6-6 6h-1.2c-2.5 0-4.7-1.5-5.7-3.8L3.7 13a1.5 1.5 0 0 1 2.7-1.3L7 13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: customer name */
							__( 'Thank you, %s!', 'consucorner' ),
							$customer_name ? $customer_name : __( 'Customer', 'consucorner' )
						)
					);
					?>
				</h1>

				<div class="ty-status-row">
					<p class="ty-chip">
						<svg class="ty-chip-icon" viewBox="0 0 24 24" aria-hidden="true">
							<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
							<path d="M8 12l3 3 5-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<?php esc_html_e( 'Your order is confirmed', 'consucorner' ); ?>
					</p>
					<?php if ( $customer_email ) : ?>
						<p class="ty-chip">
							<svg class="ty-chip-icon" viewBox="0 0 24 24" aria-hidden="true">
								<rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
								<path d="M3 7l9 6 9-6" fill="none" stroke="currentColor" stroke-width="2"/>
							</svg>
							<?php echo esc_html( sprintf( __( "We've sent details to %s", 'consucorner' ), $customer_email ) ); ?>
						</p>
					<?php endif; ?>
				</div>

				<div class="ty-grid">
					<section class="ty-panel">
						<h2 class="ty-panel-title">
							<svg class="ty-panel-icon" viewBox="0 0 24 24" aria-hidden="true">
								<circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="2"/>
								<path d="M4 21a8 8 0 0 1 16 0" fill="none" stroke="currentColor" stroke-width="2"/>
							</svg>
							<?php esc_html_e( 'CUSTOMER', 'consucorner' ); ?>
						</h2>
						<div class="ty-simple-list">
							<?php if ( $customer_email ) : ?>
								<p>
									<svg class="ty-line-icon" viewBox="0 0 24 24" aria-hidden="true">
										<rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
										<path d="M3 7l9 6 9-6" fill="none" stroke="currentColor" stroke-width="2"/>
									</svg>
									<span><?php esc_html_e( 'Email', 'consucorner' ); ?></span> <?php echo esc_html( $customer_email ); ?>
								</p>
							<?php endif; ?>
							<?php if ( $customer_phone ) : ?>
								<p>
									<svg class="ty-line-icon" viewBox="0 0 24 24" aria-hidden="true">
										<path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
									</svg>
									<span><?php esc_html_e( 'Phone', 'consucorner' ); ?></span> <?php echo esc_html( $customer_phone ); ?>
								</p>
							<?php endif; ?>
						</div>

						<?php if ( $billing_address ) : ?>
							<h2 class="ty-panel-title">
								<svg class="ty-panel-icon" viewBox="0 0 24 24" aria-hidden="true">
									<rect x="5" y="3" width="14" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
									<path d="M9 8h6M9 12h6M9 16h4" fill="none" stroke="currentColor" stroke-width="2"/>
								</svg>
								<?php esc_html_e( 'BILLING ADDRESS', 'consucorner' ); ?>
							</h2>
							<div class="ty-address-box">
								<p class="ty-strong">
									<svg class="ty-line-icon" viewBox="0 0 24 24" aria-hidden="true">
										<circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="2"/>
										<path d="M4 21a8 8 0 0 1 16 0" fill="none" stroke="currentColor" stroke-width="2"/>
									</svg>
									<?php echo esc_html( $customer_name ); ?>
								</p>
								<p>
									<svg class="ty-line-icon" viewBox="0 0 24 24" aria-hidden="true">
										<path d="M12 22s7-7 7-12a7 7 0 1 0-14 0c0 5 7 12 7 12z" fill="none" stroke="currentColor" stroke-width="2"/>
										<circle cx="12" cy="10" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/>
									</svg>
									<?php echo wp_kses_post( str_replace( '<br/>', ' · ', $billing_address ) ); ?>
								</p>
							</div>
						<?php endif; ?>

						<?php if ( $display_shipping ) : ?>
							<h2 class="ty-panel-title">
								<svg class="ty-panel-icon" viewBox="0 0 24 24" aria-hidden="true">
									<path d="M3 7h11v8H3zM14 10h4l3 3v2h-7zM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM18 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
								</svg>
								<?php esc_html_e( 'SHIPPING ADDRESS', 'consucorner' ); ?>
							</h2>
							<div class="ty-address-box">
								<?php if ( $shipping_name ) : ?>
									<p class="ty-strong">
										<svg class="ty-line-icon" viewBox="0 0 24 24" aria-hidden="true">
											<circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="2"/>
											<path d="M4 21a8 8 0 0 1 16 0" fill="none" stroke="currentColor" stroke-width="2"/>
										</svg>
										<?php echo esc_html( $shipping_name ); ?>
									</p>
								<?php endif; ?>
								<p>
									<svg class="ty-line-icon" viewBox="0 0 24 24" aria-hidden="true">
										<path d="M12 22s7-7 7-12a7 7 0 1 0-14 0c0 5 7 12 7 12z" fill="none" stroke="currentColor" stroke-width="2"/>
										<circle cx="12" cy="10" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/>
									</svg>
									<?php echo wp_kses_post( str_replace( '<br/>', ' · ', $display_shipping ) ); ?>
								</p>
							</div>
						<?php endif; ?>

						<?php if ( $same_address ) : ?>
							<p class="ty-same-note">
								<svg class="ty-chip-icon" viewBox="0 0 24 24" aria-hidden="true">
									<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
									<path d="M8 12l3 3 5-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<?php esc_html_e( 'Delivery same as billing', 'consucorner' ); ?>
							</p>
						<?php endif; ?>
					</section>

					<section class="ty-panel">
						<h2 class="ty-panel-title">
							<svg class="ty-panel-icon" viewBox="0 0 24 24" aria-hidden="true">
								<path d="M3 7l9-4 9 4v10l-9 4-9-4V7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
								<path d="M3 7l9 4 9-4M12 11v10" fill="none" stroke="currentColor" stroke-width="2"/>
							</svg>
							<?php esc_html_e( 'ORDER SUMMARY', 'consucorner' ); ?>
						</h2>

						<?php
						$item_count = 0;
						foreach ( $order->get_items() as $item_id => $item ) :
							if ( ! ( $item instanceof WC_Order_Item_Product ) ) {
								continue;
							}
							$_product = $item->get_product();
							if ( ! $_product ) {
								continue;
							}
							$item_count += (int) $item->get_quantity();
							$thumb_url = wp_get_attachment_image_url( $_product->get_image_id(), 'medium' );
							if ( ! $thumb_url ) {
								$thumb_url = wc_placeholder_img_src( 'medium' );
							}
							$bulk_display = function_exists( 'cc_get_order_item_bulk_price_display_data' )
								? cc_get_order_item_bulk_price_display_data( $item )
								: null;
							?>
							<div class="ty-product">
								<div class="ty-product-main">
									<div class="ty-product-thumb">
										<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $_product->get_name() ); ?>" />
									</div>
									<div class="ty-product-text">
										<p class="ty-product-name">
											<?php echo esc_html( $item->get_name() ); ?>
											<?php if ( (int) $item->get_quantity() > 1 ) : ?>
												<span> × <?php echo esc_html( $item->get_quantity() ); ?></span>
											<?php endif; ?>
										</p>
										<?php
										$cat_ids = $_product->get_category_ids();
										if ( ! empty( $cat_ids ) ) :
											$first_cat = get_term( $cat_ids[0], 'product_cat' );
											if ( $first_cat && ! is_wp_error( $first_cat ) ) : ?>
												<p class="ty-product-meta">
													<svg class="ty-line-icon" viewBox="0 0 24 24" aria-hidden="true">
														<path d="M3 7l9-4 9 4v10l-9 4-9-4V7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
													</svg>
													<?php echo esc_html( $first_cat->name ); ?>
												</p>
											<?php endif;
										endif; ?>
										<?php
										$product_tags = get_the_terms( $_product->get_id(), 'product_tag' );
										if ( ! empty( $product_tags ) && ! is_wp_error( $product_tags ) ) :
											$first_tag = reset( $product_tags );
											?>
											<p class="ty-product-tag">
												<svg class="ty-line-icon" viewBox="0 0 24 24" aria-hidden="true">
													<path d="M3 7l9-4 9 4v10l-9 4-9-4V7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
												</svg>
												<?php echo esc_html( $first_tag->name ); ?>
											</p>
										<?php endif; ?>
										<?php if ( $bulk_display && ! empty( $bulk_display['note'] ) ) : ?>
											<p class="cc-bulk-unit-note"><?php echo esc_html( $bulk_display['note'] ); ?></p>
										<?php endif; ?>
									</div>
								</div>
								<p class="ty-product-price"><?php echo wp_kses_post( wc_price( $item->get_total() ) ); ?></p>
							</div>
							<?php
						endforeach;
						?>

						<div class="ty-totals-box">
							<p>
								<span><?php echo esc_html( sprintf( _n( 'Subtotal (%d item)', 'Subtotal (%d items)', $item_count, 'consucorner' ), $item_count ) ); ?></span>
								<strong><?php echo wp_kses_post( wc_price( $order->get_subtotal() ) ); ?></strong>
							</p>
							<?php if ( $order->get_shipping_total() > 0 ) : ?>
								<p>
									<span>
										<svg class="ty-line-icon" viewBox="0 0 24 24" aria-hidden="true">
											<path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
										</svg>
										<?php echo esc_html( sprintf( __( 'Shipping (%s)', 'consucorner' ), $order->get_shipping_method() ? $order->get_shipping_method() : __( 'Flat rate', 'consucorner' ) ) ); ?>
									</span>
									<strong><?php echo wp_kses_post( wc_price( $order->get_shipping_total() ) ); ?></strong>
								</p>
							<?php endif; ?>
							<?php if ( $order->get_total_tax() > 0 ) : ?>
								<p>
									<span><?php esc_html_e( 'VAT', 'consucorner' ); ?></span>
									<strong><?php echo wp_kses_post( wc_price( $order->get_total_tax() ) ); ?></strong>
								</p>
							<?php endif; ?>
							<?php if ( $order->get_discount_total() > 0 ) : ?>
								<p>
									<span><?php esc_html_e( 'Discount', 'consucorner' ); ?></span>
									<strong>-<?php echo wp_kses_post( wc_price( $order->get_discount_total() ) ); ?></strong>
								</p>
							<?php endif; ?>
							<?php if ( $wallet_credit_used > 0 ) : ?>
								<p class="ty-wallet-line">
									<span><?php esc_html_e( 'Wallet Deduction', 'consucorner' ); ?></span>
									<strong>-<?php echo wp_kses_post( wc_price( $wallet_credit_used, array( 'currency' => $order->get_currency() ) ) ); ?></strong>
								</p>
							<?php endif; ?>
							<p class="ty-total-line">
								<span><?php esc_html_e( 'Total', 'consucorner' ); ?></span>
								<strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
							</p>
							<?php if ( $payment_method ) : ?>
								<p class="ty-cod-pill">
									<svg class="ty-line-icon" viewBox="0 0 24 24" aria-hidden="true">
										<circle cx="9" cy="20" r="2" fill="none" stroke="currentColor" stroke-width="2"/>
										<circle cx="17" cy="20" r="2" fill="none" stroke="currentColor" stroke-width="2"/>
										<path d="M3 4h2l3 12h11l2-8H6" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
									</svg>
									<?php echo esc_html( $payment_method ); ?>
								</p>
							<?php endif; ?>
						</div>
					</section>
				</div>

				<?php if ( $is_cod ) : ?>
					<p class="ty-payment-note">
						<svg class="ty-payment-icon" viewBox="0 0 24 24" aria-hidden="true">
							<circle cx="9" cy="20" r="2" fill="none" stroke="currentColor" stroke-width="2"/>
							<circle cx="17" cy="20" r="2" fill="none" stroke="currentColor" stroke-width="2"/>
							<path d="M3 4h2l3 12h11l2-8H6" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
						</svg>
						<?php esc_html_e( 'Pay with cash upon delivery. Your order will be shipped after confirmation.', 'consucorner' ); ?>
					</p>
				<?php endif; ?>

				<div class="ty-bottom-meta">
					<p>
						<svg class="ty-line-icon" viewBox="0 0 24 24" aria-hidden="true">
							<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
							<path d="M9 9a3 3 0 1 1 5 2.5c-1 .8-2 1.3-2 2.5M12 17h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						</svg>
						<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">
							<?php esc_html_e( 'Need help? Contact support', 'consucorner' ); ?>
						</a>
					</p>
					<p>
						<svg class="ty-line-icon" viewBox="0 0 24 24" aria-hidden="true">
							<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/>
							<path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						</svg>
						<?php echo esc_html( sprintf( __( 'Confirmed · #%s', 'consucorner' ), $order->get_order_number() ) ); ?>
					</p>
				</div>
			</article>

			<?php
			/*
			 * Keep WooCommerce's thank-you hooks so payment gateways, tracking
			 * pixels, email/CRM plugins, and custom order logic can run. The
			 * helper suppresses only WooCommerce's duplicate default details
			 * table because this template renders the designed summary above.
			 */
			$run_thankyou_hooks();
			?>

			<section class="ty-followup">
				<div>
					<p><?php esc_html_e( 'At ConsuCorner, we truly appreciate your trust. Your submission has been received, and our team is already reviewing it to ensure you get the best possible support and service.', 'consucorner' ); ?></p>
					<p class="ty-thanks-line"><?php esc_html_e( 'Thank you for choosing ConsuCorner. We look forward to serving you.', 'consucorner' ); ?></p>
				</div>

				<div>
					<h3><?php esc_html_e( "What's next?", 'consucorner' ); ?></h3>
					<p><?php esc_html_e( 'One of our team members will get back to you shortly with the details you need. We are committed to providing a smooth, reliable, and professional experience tailored to healthcare professionals.', 'consucorner' ); ?></p>
					<h3><?php esc_html_e( 'Need immediate assistance?', 'consucorner' ); ?></h3>
					<p><?php esc_html_e( 'If you have any questions or require further support, feel free to contact us — we are always happy to help.', 'consucorner' ); ?></p>
				</div>
			</section>
		<?php endif; ?>
	</section>
</main>
