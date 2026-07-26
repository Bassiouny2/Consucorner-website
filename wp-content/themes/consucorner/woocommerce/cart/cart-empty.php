<?php
/**
 * Empty cart page
 *
 * Overrides woocommerce/cart/cart-empty.php to render the ConsuCorner empty
 * cart design — matching the same visual DNA as the full cart page: page-head,
 * cart-notices at top, cart-top-pill (badge = 0), and the empty-state card.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

$shop_url  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$shop_url  = apply_filters( 'woocommerce_return_to_shop_redirect', $shop_url );
$free_ship = function_exists( 'consucorner_get_free_shipping_display' )
	? consucorner_get_free_shipping_display()
	: array( 'enabled' => false, 'subtitle' => '' );
?>

<section class="shop-page-head cart-page-head" aria-label="Cart page heading">
	<div class="shop-page-head-inner">
		<h1 class="shop-page-title"><?php esc_html_e( 'Cart', 'consucorner' ); ?></h1>
		<p class="shop-page-breadcrumbs">
			<?php consucorner_render_breadcrumbs( __( 'Home / Cart', 'consucorner' ), function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ); ?>
		</p>
	</div>
</section>

<section class="cart-section" aria-label="Shopping cart">
	<?php do_action( 'woocommerce_before_cart' ); ?>

	<div class="cart-wrap cart-wrap--empty">
		<div class="cart-top-pill">
			<div class="cart-top-left">
				<span class="cart-count-badge">0</span>
				<div>
					<p class="cart-top-title"><?php esc_html_e( 'Your cart', 'consucorner' ); ?></p>
					<?php if ( ! empty( $free_ship['enabled'] ) && ! empty( $free_ship['subtitle'] ) ) : ?>
						<p class="cart-top-sub"><?php echo esc_html( $free_ship['subtitle'] ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php
		if ( function_exists( 'woocommerce_output_all_notices' ) ) {
			echo '<div class="cart-notices-wrap" role="status" aria-live="polite">';
			woocommerce_output_all_notices();
			echo '</div>';
		}
		?>

		<div class="cart-grid">
			<article class="cart-list-card">
				<div class="cart-empty-state">
					<svg class="cart-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
						<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
					</svg>
					<h3><?php esc_html_e( 'Your cart is empty', 'consucorner' ); ?></h3>
					<p><?php esc_html_e( 'Looks like you removed everything. Browse products and add what you need.', 'consucorner' ); ?></p>
					<a href="<?php echo esc_url( $shop_url ); ?>" class="cart-empty-link">
						<?php esc_html_e( 'Go to Shop', 'consucorner' ); ?>
					</a>
				</div>
			</article>
		</div>
	</div>
</section>
