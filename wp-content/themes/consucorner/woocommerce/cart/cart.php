<?php

/**
 * Cart Page
 *
 * Overrides woocommerce/cart/cart.php to render the ConsuCorner cart design.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_cart');
?>

<section class="shop-page-head cart-page-head" aria-label="Cart page heading">
	<div class="shop-page-head-inner">
		<h1 class="shop-page-title"><?php esc_html_e('Cart', 'consucorner'); ?></h1>
		<p class="shop-page-breadcrumbs">
			<?php consucorner_render_breadcrumbs(__('Home / Cart', 'consucorner'), function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/')); ?>
		</p>
	</div>
</section>

<section class="cart-section" aria-label="Shopping cart">
	<?php
	/*
	 * woocommerce_before_cart fires here for full plugin compatibility
	 * (third-party plugins may hook into it). Note that we removed
	 * woocommerce_output_all_notices from this hook via template_redirect
	 * in functions.php — we render notices ourselves below the pill instead.
	 */
	do_action('woocommerce_before_cart');
	?>
	<form
		class="woocommerce-cart-form cart-wrap"
		action="<?php echo esc_url(wc_get_cart_url()); ?>"
		method="post"
		enctype="multipart/form-data">
		<?php do_action('woocommerce_before_cart_table'); ?>

		<?php
		$cart        = WC()->cart;
		$total_items = (int) $cart->get_cart_contents_count();
		$free_ship   = function_exists( 'consucorner_get_free_shipping_display' )
			? consucorner_get_free_shipping_display()
			: array( 'enabled' => false, 'subtitle' => '' );
		?>

		<div class="cart-top-pill">
			<div class="cart-top-left">
				<span class="cart-count-badge" id="cart-count-badge"><?php echo esc_html($total_items); ?></span>
				<div>
					<p class="cart-top-title"><?php esc_html_e('Your cart', 'consucorner'); ?></p>
					<?php if ( ! empty( $free_ship['enabled'] ) && ! empty( $free_ship['subtitle'] ) ) : ?>
						<p class="cart-top-sub"><?php echo esc_html( $free_ship['subtitle'] ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="cart-top-actions">
				<button type="button" class="cart-action-btn" data-cc-cart-share>
					<?php esc_html_e('Share cart', 'consucorner'); ?>
				</button>
				<a
					href="<?php echo esc_url(wp_nonce_url(add_query_arg('empty-cart', 'yes', wc_get_cart_url()), 'woocommerce-cart')); ?>"
					class="cart-action-btn"
					data-clear-cart>
					<?php esc_html_e('Clear', 'consucorner'); ?>
				</a>
			</div>
		</div>

		<?php
		// WC notices rendered here — directly under the pill, above the grid.
		// (woocommerce_output_all_notices was removed from woocommerce_before_cart
		// via template_redirect so this is the single, correctly positioned output.)
		if (function_exists('woocommerce_output_all_notices')) {
			echo '<div class="cart-notices-wrap" role="status" aria-live="polite">';
			woocommerce_output_all_notices();
			echo '</div>';
		}
		?>

		<div class="cart-grid">
			<article class="cart-list-card" data-cc-tour="cart-list">
				<header class="cart-list-head">
					<p><?php esc_html_e('PRODUCT', 'consucorner'); ?></p>
					<p><?php esc_html_e('QUANTITY & PRICE', 'consucorner'); ?></p>
				</header>

				<?php
				/**
				 * Render a single cart line's markup. Reused both for standalone
				 * items and for member lines inside a bundle frame — bundle
				 * members render with a locked (read-only) quantity and no
				 * individual remove control (the frame has one "Remove bundle").
				 *
				 * @param string $cart_item_key Cart item key.
				 * @param array  $cart_item     Cart item data.
				 * @param bool   $locked        Whether this line belongs to a bundle group.
				 */
				$cc_render_cart_row = function ($cart_item_key, $cart_item, $locked = false) {
					$_product   = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
					$product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

					if (! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0) {
						return;
					}

					$product_permalink = apply_filters('woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);
					$thumb_url         = wp_get_attachment_image_url($_product->get_image_id(), 'medium');
					if (! $thumb_url) {
						$thumb_url = wc_placeholder_img_src('medium');
					}

					$line_unit       = $_product->get_price();
					$line_unit_html  = wc_price($line_unit);
					$regular_price   = $_product->get_regular_price();
					$bulk_display    = function_exists('cc_get_cart_item_bulk_price_display_data')
						? cc_get_cart_item_bulk_price_display_data($cart_item)
						: null;
					$has_sale        = ! $locked && '' !== $regular_price && $regular_price > $line_unit;

					if ($bulk_display && ! empty($bulk_display['unitHtml'])) {
						$line_unit_html = $bulk_display['unitHtml'];
						if (! empty($bulk_display['regularUnit']) && (float) $bulk_display['regularUnit'] > (float) $bulk_display['unit']) {
							$has_sale      = true;
							$regular_price = (float) $bulk_display['regularUnit'];
						}
					}

					$cat_ids   = $_product->get_category_ids();
					$cat_label = '';
					if (! empty($cat_ids)) {
						$first_cat = get_term($cat_ids[0], 'product_cat');
						if ($first_cat && ! is_wp_error($first_cat)) {
							$cat_label = strtoupper($first_cat->name);
						}
					}

					/* Build a small meta line from variation/attributes. */
					$meta_html = wc_get_formatted_cart_item_data($cart_item, true);
					$meta_text = $meta_html ? trim(wp_strip_all_tags(str_replace(',', ' · ', wp_strip_all_tags($meta_html)))) : '';
					$bulk_min_qty = function_exists('cc_get_product_bulk_min_qty') ? cc_get_product_bulk_min_qty($_product, array('require_stock' => false)) : 0;
					$bulk_step    = function_exists('cc_get_product_bulk_qty_step') ? cc_get_product_bulk_qty_step($_product) : 1;
					?>
					<div
						class="cart-item woocommerce-cart-form__cart-item<?php echo $locked ? ' cc-bundle-cart-item' : ''; ?> <?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key)); ?>"
						data-price="<?php echo esc_attr($line_unit); ?>"
						data-max-stock="<?php echo esc_attr(function_exists('cc_get_product_max_qty') ? cc_get_product_max_qty($_product) : 0); ?>"
						data-bulk-min="<?php echo esc_attr($bulk_min_qty); ?>"
						data-bulk-step="<?php echo esc_attr($bulk_step); ?>"
						data-key="<?php echo esc_attr($cart_item_key); ?>">
						<?php if ($locked) : ?>
							<span class="cart-remove cart-remove--spacer" aria-hidden="true"></span>
						<?php else : ?>
							<a
								href="<?php echo esc_url(wc_get_cart_remove_url($cart_item_key)); ?>"
								class="cart-remove"
								aria-label="<?php esc_attr_e('Remove item', 'consucorner'); ?>"
								data-product_id="<?php echo esc_attr($product_id); ?>"
								data-product_sku="<?php echo esc_attr($_product->get_sku()); ?>">x</a>
						<?php endif; ?>

						<div class="cart-item-main">
							<div class="cart-thumb">
								<?php if ($product_permalink) : ?>
									<a href="<?php echo esc_url($product_permalink); ?>">
										<img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($_product->get_name()); ?>" />
									</a>
								<?php else : ?>
									<img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($_product->get_name()); ?>" />
								<?php endif; ?>
							</div>
							<div class="cart-item-info">
								<?php if ($cat_label) : ?>
									<p class="cart-item-cat"><?php echo esc_html($cat_label); ?></p>
								<?php endif; ?>
								<h3 class="cart-item-name">
									<?php if ($product_permalink) : ?>
										<a href="<?php echo esc_url($product_permalink); ?>"><?php echo wp_kses_post($_product->get_name()); ?></a>
									<?php else : ?>
										<?php echo wp_kses_post($_product->get_name()); ?>
									<?php endif; ?>
								</h3>
								<?php if ($meta_text) : ?>
									<p class="cart-item-meta"><?php echo esc_html($meta_text); ?></p>
								<?php endif; ?>
								<div class="cart-qty-row<?php echo $locked ? ' cart-qty-row--locked' : ''; ?>" data-cart-key="<?php echo esc_attr($cart_item_key); ?>">
									<button type="button" class="qty-btn qty-minus" aria-label="<?php esc_attr_e('Decrease quantity', 'consucorner'); ?>" <?php disabled($locked); ?>>-</button>
									<span class="qty-value"><?php echo esc_html($cart_item['quantity']); ?></span>
									<button type="button" class="qty-btn qty-plus" aria-label="<?php esc_attr_e('Increase quantity', 'consucorner'); ?>" <?php disabled($locked); ?>>+</button>
									<input
										type="hidden"
										class="qty-input"
										name="cart[<?php echo esc_attr($cart_item_key); ?>][qty]"
										value="<?php echo esc_attr($cart_item['quantity']); ?>"
										data-key="<?php echo esc_attr($cart_item_key); ?>" />
								</div>
							</div>
						</div>

						<div class="cart-item-price">
							<?php if ($locked) : ?>
								<p class="price-now cc-bundle-cart-item__note"><?php esc_html_e('Included in bundle', 'consucorner'); ?></p>
							<?php else : ?>
								<p class="price-now"><?php echo wp_kses_post($line_unit_html); ?></p>
								<?php if ($has_sale) : ?>
									<p class="price-old"><?php
										echo wp_kses_post(
											($bulk_display && ! empty($bulk_display['regularUnitHtml']))
												? $bulk_display['regularUnitHtml']
												: wc_price($regular_price)
										);
									?></p>
								<?php endif; ?>
								<?php if ($bulk_display && ! empty($bulk_display['note'])) : ?>
									<p class="cc-bulk-unit-note"><?php echo esc_html($bulk_display['note']); ?></p>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</div>
					<?php
				};

				$cart_groups = function_exists('cc_bundles_group_cart_items') ? cc_bundles_group_cart_items($cart) : array();

				foreach ($cart_groups as $group) :
					if ('bundle' === $group['type'] && ! empty($group['items'])) :
						$first_item   = $group['items'][0]['item'];
						$remove_key   = $group['items'][0]['key'];
						$bundle_name  = ! empty($first_item['cc_bundle_name']) ? (string) $first_item['cc_bundle_name'] : get_the_title($group['bundle_id']);
						$bundle_price = isset($first_item['cc_bundle_price']) ? (float) $first_item['cc_bundle_price'] : 0;
						$bundle_size  = isset($first_item['cc_bundle_size']) ? (int) $first_item['cc_bundle_size'] : 0;
						?>
						<div class="cc-bundle-cart-frame">
							<div class="cc-bundle-cart-frame__head">
								<div class="cc-bundle-cart-frame__info">
									<p class="cc-bundle-cart-frame__label"><?php esc_html_e('Bundle', 'consucorner'); ?></p>
									<h4 class="cc-bundle-cart-frame__title"><?php echo esc_html($bundle_name); ?></h4>
									<?php if ($bundle_size > 0) : ?>
										<p class="cc-bundle-cart-frame__meta">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %d: number of items in the bundle */
													__('%d items · flat price', 'consucorner'),
													$bundle_size
												)
											);
											?>
										</p>
									<?php endif; ?>
								</div>
								<div class="cc-bundle-cart-frame__price"><?php echo wp_kses_post(wc_price($bundle_price)); ?></div>
								<a href="<?php echo esc_url(wc_get_cart_remove_url($remove_key)); ?>" class="cc-bundle-cart-frame__remove">
									<?php esc_html_e('Remove bundle', 'consucorner'); ?>
								</a>
							</div>
							<div class="cc-bundle-cart-frame__items">
								<?php
								foreach ($group['items'] as $row) {
									$cc_render_cart_row($row['key'], $row['item'], true);
								}
								?>
							</div>
						</div>
						<?php
					else :
						$cc_render_cart_row($group['key'], $group['item'], false);
					endif;
				endforeach;

				do_action('woocommerce_cart_contents');
				?>
			</article>

			<aside class="cart-summary-card">
				<header class="summary-head">
					<h2><?php esc_html_e('Order summary', 'consucorner'); ?></h2>
					<span id="cart-items-count"><?php echo esc_html(sprintf(_n('%d item', '%d items', $total_items, 'consucorner'), $total_items)); ?></span>
				</header>

				<div class="summary-line">
					<p><?php esc_html_e('Subtotal', 'consucorner'); ?></p>
					<p id="summary-subtotal"><?php echo wp_kses_post(wc_price($cart->get_subtotal())); ?></p>
				</div>
				<div class="summary-line">
					<p><?php esc_html_e('Shipping', 'consucorner'); ?></p>
					<p id="summary-shipping"><?php echo wp_kses_post(wc_price($cart->get_shipping_total())); ?></p>
				</div>
				<?php if (wc_tax_enabled() && ! $cart->display_prices_including_tax()) : ?>
					<div class="summary-line">
						<p><?php esc_html_e('VAT', 'consucorner'); ?></p>
						<p id="summary-vat"><?php echo wp_kses_post(wc_price($cart->get_total_tax())); ?></p>
					</div>
				<?php endif; ?>

				<?php if (wc_coupons_enabled()) :
					$applied_codes = $cart->get_applied_coupons();
					$has_applied   = ! empty($applied_codes);
					$applied_label = $has_applied ? strtoupper($applied_codes[0]) : '';
					$discount_val  = (float) $cart->get_discount_total();
				?>
					<div class="coupon-block<?php echo $has_applied ? ' coupon-block--applied' : ''; ?>">

						<?php if (! $has_applied) : ?>
							<!-- STATE A: no coupon applied -->
							<div class="coupon-row">
								<input
									type="text"
									id="coupon-input"
									name="coupon_code"
									placeholder="<?php esc_attr_e('Enter coupon code', 'consucorner'); ?>"
									autocomplete="off"
									maxlength="32" />
								<button
									type="submit"
									class="coupon-apply-btn"
									name="apply_coupon"
									value="<?php esc_attr_e('Apply coupon', 'consucorner'); ?>"><?php esc_html_e('Apply', 'consucorner'); ?></button>
							</div>

						<?php else : ?>
							<!-- STATE B: coupon applied -->
							<div class="coupon-chip" role="status" aria-label="<?php echo esc_attr(sprintf(__('Coupon %s applied', 'consucorner'), $applied_label)); ?>">
								<span class="coupon-chip__icon" aria-hidden="true">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
										<polyline points="20 6 9 17 4 12" />
									</svg>
								</span>
								<span class="coupon-chip__code"><?php echo esc_html($applied_label); ?></span>
								<span class="coupon-chip__badge"><?php esc_html_e('Applied', 'consucorner'); ?></span>
								<span class="coupon-chip__savings">-<?php echo wp_kses_post(wc_price($discount_val)); ?></span>
								<a
									href="<?php echo esc_url(wp_nonce_url(add_query_arg('remove_coupon', rawurlencode($applied_codes[0]), wc_get_cart_url()), 'woocommerce-remove-coupon')); ?>"
									class="coupon-chip__remove"
									aria-label="<?php esc_attr_e('Remove coupon', 'consucorner'); ?>">
									<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
										<line x1="18" y1="6" x2="6" y2="18" />
										<line x1="6" y1="6" x2="18" y2="18" />
									</svg>
								</a>
							</div>

						<?php endif; ?>

					</div>
				<?php endif; ?>

				<?php
				$cc_pre_codes = wc_coupons_enabled() ? $cart->get_applied_coupons() : array();
				if (! empty($cc_pre_codes)) :
					$cc_first_code = isset($cc_pre_codes[0]) ? strtoupper($cc_pre_codes[0]) : '';
				?>
					<div class="summary-pre-discount" id="summary-pre-discount">
						<div class="summary-line summary-line--emphasis">
							<p><?php esc_html_e('Total before discount', 'consucorner'); ?></p>
							<p id="summary-before-coupon"><?php echo wp_kses_post(wc_price($cart->get_subtotal() + $cart->get_shipping_total() + $cart->get_total_tax())); ?></p>
						</div>
						<div class="summary-line summary-discount-line">
							<p id="coupon-discount-label"><?php echo esc_html(sprintf(__('Coupon (%s)', 'consucorner'), $cc_first_code)); ?></p>
							<p id="summary-coupon-deduction">-<?php echo wp_kses_post(wc_price($cart->get_discount_total())); ?></p>
						</div>
					</div>
				<?php else : ?>
					<div class="summary-pre-discount" id="summary-pre-discount" hidden>
						<div class="summary-line summary-line--emphasis">
							<p><?php esc_html_e('Total before discount', 'consucorner'); ?></p>
							<p id="summary-before-coupon">0 EGP</p>
						</div>
						<div class="summary-line summary-discount-line">
							<p id="coupon-discount-label"><?php esc_html_e('Coupon discount', 'consucorner'); ?></p>
							<p id="summary-coupon-deduction">-0 EGP</p>
						</div>
					</div>
				<?php endif; ?>

				<div class="summary-total">
					<p><?php esc_html_e('Total', 'consucorner'); ?></p>
					<p id="summary-total"><?php echo wp_kses_post($cart->get_total()); ?></p>
				</div>

				<a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="cart-checkout-btn" data-cc-tour="cart-checkout">
					<?php esc_html_e('Checkout', 'consucorner'); ?>
				</a>
			</aside>
		</div>

		<?php do_action('woocommerce_cart_actions'); ?>
		<?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
		<?php do_action('woocommerce_after_cart_contents'); ?>
		<?php do_action('woocommerce_after_cart_table'); ?>
	</form>
</section>

<?php do_action('woocommerce_before_cart_collaterals'); ?>
<?php do_action('woocommerce_after_cart'); ?>