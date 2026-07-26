<?php

/**
 * "Pricing & Deals" Product Data tab.
 *
 * Native WooCommerce Product Data tab (simple products only) that replaces
 * the old "Offer Deal (offers page)" meta-box section and adds a new
 * repeatable "Bulk pricing" tier editor.
 *
 * Meta keys:
 * - _cc_offer_deal_enabled ('1' | '')
 * - _cc_offer_deal_qty     (int)
 * - _cc_offer_deal_price   (decimal, bundle total for the qty above)
 * - _cc_bulk_enabled       ('1' | '')
 * - _cc_bulk_show_exact_unit_price ('1' | '') — display fractional bulk unit prices even when WC decimals = 0
 * - _cc_bulk_tiers         (array of { min:int, max:int (0 = "and up"), price:float (per unit) })
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/**
 * Register the "Pricing & Deals" tab (simple products only).
 *
 * @param array $tabs Existing product data tabs.
 * @return array
 */
function cc_add_pricing_deals_product_tab($tabs)
{
	$tabs['cc_pricing'] = array(
		'label'    => __('Pricing & Deals', 'consucorner'),
		'target'   => 'cc_pricing_data',
		'class'    => array('show_if_simple'),
		'priority' => 25,
	);

	return $tabs;
}
add_filter('woocommerce_product_data_tabs', 'cc_add_pricing_deals_product_tab');

/**
 * Render the "Pricing & Deals" panel.
 */
function cc_render_pricing_deals_product_panel()
{
	global $post;

	$product_id = $post->ID;

	$deal_enabled = '1' === (string) get_post_meta($product_id, '_cc_offer_deal_enabled', true);
	$deal_qty     = get_post_meta($product_id, '_cc_offer_deal_qty', true);
	$deal_price   = get_post_meta($product_id, '_cc_offer_deal_price', true);

	$bulk_enabled  = '1' === (string) get_post_meta($product_id, '_cc_bulk_enabled', true);
	$bulk_exact    = '1' === (string) get_post_meta($product_id, '_cc_bulk_show_exact_unit_price', true);
	$bulk_tiers    = get_post_meta($product_id, '_cc_bulk_tiers', true);
	$bulk_qty_step = absint(get_post_meta($product_id, '_cc_bulk_qty_step', true));
	if (! is_array($bulk_tiers)) {
		$bulk_tiers = array();
	}
?>
	<div id="cc_pricing_data" class="panel woocommerce_options_panel">

		<div class="options_group">
			<p class="form-field" style="padding:0 12px">
				<strong style="display:block;font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:#787c82"><?php esc_html_e('Offer Deal (bundle price)', 'consucorner'); ?></strong>
				<span style="display:block;font-size:12px;color:#787c82;margin-top:4px"><?php esc_html_e('Applies the deal unit price once the cart quantity reaches this threshold (and for every unit above it). Shown on the Offers page and product cards/single page.', 'consucorner'); ?></span>
			</p>

			<?php
			woocommerce_wp_checkbox(
				array(
					'id'          => '_cc_offer_deal_enabled',
					'value'       => $deal_enabled ? 'yes' : 'no',
					'label'       => __('Enable bundle deal', 'consucorner'),
					'description' => __('Applies when quantity is at least the bundle quantity (extras keep the offer unit price).', 'consucorner'),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'                => '_cc_offer_deal_qty',
					'label'             => __('Bundle quantity', 'consucorner'),
					'placeholder'       => __('e.g. 10', 'consucorner'),
					'value'             => $deal_qty ? $deal_qty : '',
					'type'              => 'number',
					'custom_attributes' => array(
						'min'  => '2',
						'step' => '1',
					),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'                => '_cc_offer_deal_price',
					'label'             => sprintf(
						/* translators: %s: currency symbol/code */
						__('Bundle total price (%s)', 'consucorner'),
						get_woocommerce_currency_symbol()
					),
					'placeholder'       => __('e.g. 800', 'consucorner'),
					'value'             => $deal_price ? $deal_price : '',
					'data_type'         => 'price',
				)
			);
			?>
		</div>

		<div class="options_group cc-bulk-pricing-group">
			<p class="form-field" style="padding:0 12px">
				<strong style="display:block;font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:#787c82"><?php esc_html_e('Bulk pricing (quantity tiers)', 'consucorner'); ?></strong>
				<span style="display:block;font-size:12px;color:#787c82;margin-top:4px"><?php esc_html_e('Per-unit price for the whole order once the cart quantity falls inside a range. Leave "Max" empty on the last tier for "and up".', 'consucorner'); ?></span>
				<span style="display:block;font-size:12px;color:#787c82;margin-top:4px"><?php esc_html_e('The lowest Min qty becomes the minimum order quantity for this bulk-only product.', 'consucorner'); ?></span>
			</p>

			<?php
			woocommerce_wp_checkbox(
				array(
					'id'          => '_cc_bulk_enabled',
					'value'       => $bulk_enabled ? 'yes' : 'no',
					'label'       => __('Enable bulk pricing', 'consucorner'),
					'description' => __('Applies site-wide (shop, offers page, single product, cart).', 'consucorner'),
				)
			);

			woocommerce_wp_checkbox(
				array(
					'id'          => '_cc_bulk_show_exact_unit_price',
					'value'       => $bulk_exact ? 'yes' : 'no',
					'label'       => __('Show exact bulk unit prices', 'consucorner'),
					'description' => __('When WooCommerce decimals are 0, still show fractional bulk tier prices like 11.5 EGP on the product page, mini-cart, cart, checkout, thank-you page, and emails. Totals stay on WooCommerce rounding.', 'consucorner'),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'                => '_cc_bulk_qty_step',
					'label'             => __('Quantity step (+/- click increment)', 'consucorner'),
					'description'       => __('Each "+"/"−" click on the product page changes the quantity by this many units instead of 1 — useful when the minimum is high (e.g. min 21, max 100). Leave empty or 1 for normal single-unit clicks.', 'consucorner'),
					'desc_tip'          => true,
					'placeholder'       => __('e.g. 5', 'consucorner'),
					'value'             => $bulk_qty_step > 1 ? $bulk_qty_step : '',
					'type'              => 'number',
					'custom_attributes' => array(
						'min'  => '1',
						'step' => '1',
					),
				)
			);
			?>

			<table class="widefat cc-bulk-tiers-table" id="cc-bulk-tiers-table" style="margin:0 12px 12px;width:calc(100% - 24px)">
				<thead>
					<tr>
						<th style="width:30%"><?php esc_html_e('Min qty', 'consucorner'); ?></th>
						<th style="width:30%"><?php esc_html_e('Max qty (optional)', 'consucorner'); ?></th>
						<th><?php esc_html_e('Price per unit', 'consucorner'); ?></th>
						<th style="width:40px"></th>
					</tr>
				</thead>
				<tbody id="cc-bulk-tiers-rows">
					<?php if (! empty($bulk_tiers)) : ?>
						<?php foreach ($bulk_tiers as $tier) : ?>
							<?php cc_render_bulk_tier_row($tier); ?>
						<?php endforeach; ?>
					<?php else : ?>
						<?php cc_render_bulk_tier_row(array()); ?>
					<?php endif; ?>
				</tbody>
			</table>
			<p style="margin:0 12px 12px">
				<button type="button" class="button" id="cc-bulk-tier-add"><?php esc_html_e('+ Add tier', 'consucorner'); ?></button>
			</p>
		</div>

	</div>

	<script type="text/template" id="cc-bulk-tier-row-template">
		<?php cc_render_bulk_tier_row(array(), true); ?>
	</script>

	<script>
		(function($) {
			$(function() {
				var $rows = $('#cc-bulk-tiers-rows');

				$('#cc-bulk-tier-add').on('click', function(e) {
					e.preventDefault();
					var tpl = document.getElementById('cc-bulk-tier-row-template').innerHTML;
					$rows.append(tpl);
				});

				$rows.on('click', '.cc-bulk-tier-remove', function(e) {
					e.preventDefault();
					if ($rows.find('tr').length > 1) {
						$(this).closest('tr').remove();
					} else {
						$(this).closest('tr').find('input').val('');
					}
				});
			});
		})(jQuery);
	</script>
<?php
}
add_action('woocommerce_product_data_panels', 'cc_render_pricing_deals_product_panel');

/**
 * Render a single bulk-tier row (min / max / price / remove).
 *
 * @param array $tier     { min, max, price } or empty for a blank row.
 * @param bool  $is_template Whether this is the JS clone template row.
 */
function cc_render_bulk_tier_row($tier, $is_template = false)
{
	$min   = isset($tier['min']) ? $tier['min'] : '';
	$max   = isset($tier['max']) && $tier['max'] ? $tier['max'] : '';
	$price = isset($tier['price']) ? $tier['price'] : '';
?>
	<tr class="cc-bulk-tier-row">
		<td><input type="number" min="1" step="1" name="cc_bulk_tier_min[]" value="<?php echo esc_attr($min); ?>" style="width:100%" /></td>
		<td><input type="number" min="1" step="1" name="cc_bulk_tier_max[]" value="<?php echo esc_attr($max); ?>" placeholder="<?php esc_attr_e('and up', 'consucorner'); ?>" style="width:100%" /></td>
		<td><input type="text" name="cc_bulk_tier_price[]" value="<?php echo esc_attr($price); ?>" placeholder="0.00" style="width:100%" /></td>
		<td><button type="button" class="button cc-bulk-tier-remove" title="<?php esc_attr_e('Remove tier', 'consucorner'); ?>">&times;</button></td>
	</tr>
<?php
}

/**
 * Save "Pricing & Deals" fields for simple products.
 *
 * @param int $post_id Product ID.
 */
function cc_save_pricing_deals_product_panel($post_id)
{
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified upstream by WC_Admin_Meta_Boxes::save() (woocommerce_meta_nonce).

	update_post_meta(
		$post_id,
		'_cc_offer_deal_enabled',
		isset($_POST['_cc_offer_deal_enabled']) ? '1' : ''
	);

	if (isset($_POST['_cc_offer_deal_qty'])) {
		update_post_meta($post_id, '_cc_offer_deal_qty', absint(wp_unslash($_POST['_cc_offer_deal_qty'])));
	}

	if (isset($_POST['_cc_offer_deal_price'])) {
		update_post_meta($post_id, '_cc_offer_deal_price', wc_format_decimal(wp_unslash($_POST['_cc_offer_deal_price'])));
	}

	update_post_meta(
		$post_id,
		'_cc_bulk_enabled',
		isset($_POST['_cc_bulk_enabled']) ? '1' : ''
	);

	update_post_meta(
		$post_id,
		'_cc_bulk_show_exact_unit_price',
		isset($_POST['_cc_bulk_show_exact_unit_price']) ? '1' : ''
	);

	if (isset($_POST['_cc_bulk_qty_step'])) {
		$step = absint(wp_unslash($_POST['_cc_bulk_qty_step']));
		update_post_meta($post_id, '_cc_bulk_qty_step', $step > 1 ? $step : 1);
	}

	$mins   = isset($_POST['cc_bulk_tier_min']) ? (array) wp_unslash($_POST['cc_bulk_tier_min']) : array();
	$maxes  = isset($_POST['cc_bulk_tier_max']) ? (array) wp_unslash($_POST['cc_bulk_tier_max']) : array();
	$prices = isset($_POST['cc_bulk_tier_price']) ? (array) wp_unslash($_POST['cc_bulk_tier_price']) : array();

	$tiers = array();
	foreach ($mins as $i => $raw_min) {
		$min   = absint($raw_min);
		$max   = isset($maxes[$i]) ? absint($maxes[$i]) : 0;
		$price = isset($prices[$i]) ? (float) wc_format_decimal($prices[$i]) : 0;

		if ($min < 1 || $price <= 0) {
			continue;
		}

		if ($max && $max < $min) {
			$max = 0;
		}

		$tiers[] = array(
			'min'   => $min,
			'max'   => $max,
			'price' => $price,
		);
	}

	usort(
		$tiers,
		function ($a, $b) {
			return $a['min'] <=> $b['min'];
		}
	);

	update_post_meta($post_id, '_cc_bulk_tiers', $tiers);

	// phpcs:enable WordPress.Security.NonceVerification.Missing
}
add_action('woocommerce_process_product_meta_simple', 'cc_save_pricing_deals_product_panel');
