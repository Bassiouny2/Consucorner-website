<?php

/**
 * ConsuCorner custom WooCommerce order emails.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/**
 * Bootstrap email customizations.
 */
function consucorner_order_email_init()
{
	add_filter('woocommerce_email_heading_customer_processing_order', 'consucorner_processing_email_heading');
	add_filter('woocommerce_email_subject_customer_processing_order', 'consucorner_processing_email_subject', 10, 3);
}
add_action('init', 'consucorner_order_email_init');

/**
 * @return string
 */
function consucorner_processing_email_heading()
{
	return __('Order Confirmed!', 'consucorner');
}

/**
 * @param string        $subject Email subject.
 * @param WC_Order|mixed $order   Order.
 * @param WC_Email|null $email   Email object.
 * @return string
 */
function consucorner_processing_email_subject($subject, $order, $email = null)
{ // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	if (! $order instanceof WC_Order) {
		return $subject;
	}

	return sprintf(
		/* translators: %s: order number */
		__('Your ConsuCorner order #%s is confirmed', 'consucorner'),
		$order->get_order_number()
	);
}

/**
 * Logo URL for transactional emails.
 *
 * @return string
 */
function consucorner_order_email_logo_url()
{
	$default = 'https://consucorner.com/wp-content/uploads/2026/06/consu3.png';

	return (string) apply_filters('consucorner_order_email_logo_url', $default);
}

/**
 * @return string
 */
function consucorner_order_email_support_address()
{
	return (string) apply_filters('consucorner_order_email_support_email', 'contact@consucorner.com');
}

/**
 * Footer navigation links.
 *
 * @return array<int, array{label:string,url:string}>
 */
function consucorner_order_email_footer_links()
{
	$shop_url    = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	$orders_url  = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('orders') : home_url('/my-account/ ');
	$contact_url = home_url('/contact/');
	$faq_url     = home_url('/faq/');

	$links = array(
		array(
			'label' => __('Shop', 'consucorner'),
			'url'   => $shop_url,
		),
		array(
			'label' => __('My Orders', 'consucorner'),
			'url'   => $orders_url,
		),
		array(
			'label' => __('Contact Us', 'consucorner'),
			'url'   => $contact_url,
		),
		array(
			'label' => __('FAQ', 'consucorner'),
			'url'   => $faq_url,
		),
	);

	return (array) apply_filters('consucorner_order_email_footer_links', $links);
}

/**
 * @param WC_Order $order Order.
 * @return string
 */
function consucorner_order_email_customer_name(WC_Order $order)
{
	$name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
	if (! $name) {
		$name = trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name());
	}
	return $name ? $name : __('Customer', 'consucorner');
}

/**
 * @param WC_Order $order Order.
 * @return string
 */
function consucorner_order_email_order_date(WC_Order $order)
{
	$created = $order->get_date_created();
	if (! $created) {
		return '';
	}
	return wp_date(get_option('date_format'), $created->getTimestamp());
}

/**
 * @param WC_Order $order Order.
 * @return string
 */
function consucorner_order_email_estimated_delivery(WC_Order $order)
{
	$created = $order->get_date_created();
	if (! $created) {
		return '';
	}

	$min_days = max(1, (int) apply_filters('consucorner_order_email_delivery_min_days', 3));
	$max_days = max($min_days, (int) apply_filters('consucorner_order_email_delivery_max_days', 5));
	$ts       = $created->getTimestamp();

	$start = wp_date('F j', $ts + ($min_days * DAY_IN_SECONDS));
	$end   = wp_date('F j, Y', $ts + ($max_days * DAY_IN_SECONDS));

	return $start . '–' . $end;
}

/**
 * @param int $product_id Product ID.
 * @return string
 */
function consucorner_order_email_product_meta_line($product_id)
{
	$parts = array();

	$cats = get_the_terms($product_id, 'product_cat');
	if (is_array($cats) && ! empty($cats)) {
		$parts[] = $cats[0]->name;
	}

	if (taxonomy_exists('specialty')) {
		$specs = get_the_terms($product_id, 'specialty');
		if (is_array($specs) && ! empty($specs)) {
			$parts[] = $specs[0]->name;
		}
	}

	return implode(' · ', $parts);
}

/**
 * @param float  $amount   Amount.
 * @param string $currency Currency code.
 * @return string
 */
function consucorner_order_email_price($amount, $currency)
{
	return wp_kses_post(wc_price($amount, array('currency' => $currency)));
}

/**
 * @param WC_Order $order Order.
 * @return string
 */
function consucorner_order_email_shipping_display(WC_Order $order)
{
	$shipping_total = (float) $order->get_shipping_total();
	if ($shipping_total <= 0) {
		return '<span style="color:#16b894;font-weight:bold;">' . esc_html__('Free', 'consucorner') . '</span>';
	}
	return consucorner_order_email_price($shipping_total, $order->get_currency());
}

/**
 * @param WC_Order $order Order.
 * @return string
 */
function consucorner_order_email_shipping_address_html(WC_Order $order)
{
	$address = $order->get_formatted_shipping_address();
	if (! $address) {
		$address = $order->get_formatted_billing_address();
	}
	if (! $address) {
		return '';
	}
	return wp_kses_post(preg_replace('/<br\s*\/?>/i', '<br />', $address));
}

/**
 * @param WC_Order $order Order.
 * @return string
 */
function consucorner_order_email_track_url(WC_Order $order)
{
	if (function_exists('consucorner_profile_get_order_track_url')) {
		return consucorner_profile_get_order_track_url($order);
	}

	$url = $order->get_view_order_url();
	if ($url) {
		return $url;
	}
	return function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('orders') : home_url('/my-account/orders/');
}

/**
 * Render the HTML processing-order email.
 *
 * @param WC_Order $order              Order.
 * @param bool     $sent_to_admin      Admin copy.
 * @param bool     $plain_text         Plain text mode.
 * @param WC_Email $email              Email object.
 * @param string   $additional_content Extra content from WC email settings.
 */
function consucorner_render_processing_order_email_html($order, $sent_to_admin, $plain_text, $email, $additional_content)
{ // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if (! $order instanceof WC_Order) {
		return;
	}

	$logo_url       = consucorner_order_email_logo_url();
	$home_url       = home_url('/');
	$customer_name  = consucorner_order_email_customer_name($order);
	$order_number   = $order->get_order_number();
	$order_date     = consucorner_order_email_order_date($order);
	$payment_method = $order->get_payment_method_title() ? $order->get_payment_method_title() : __('N/A', 'consucorner');
	$delivery_est   = consucorner_order_email_estimated_delivery($order);
	$currency       = $order->get_currency();
	$track_url      = consucorner_order_email_track_url($order);
	$shipping_name  = trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name());
	if (! $shipping_name) {
		$shipping_name = $customer_name;
	}
	$shipping_address = consucorner_order_email_shipping_address_html($order);
	$tax_label        = function_exists('wc_tax_enabled') && wc_tax_enabled() ? WC()->countries->tax_or_vat() : __('Tax', 'consucorner');
	$support_email    = consucorner_order_email_support_address();
	$footer_links     = consucorner_order_email_footer_links();
	$intro            = (string) apply_filters(
		'consucorner_processing_order_email_intro',
		__(
			'Thank you for your order on ConsuCorner — Egypt\'s trusted medical supply hub. We\'ve received your order and it\'s being prepared for shipment. You\'ll receive a tracking update once it\'s dispatched.',
			'consucorner'
		),
		$order
	);

?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>

	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo('charset'); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title><?php echo esc_html(sprintf(__('Order #%s confirmed', 'consucorner'), $order_number)); ?></title>
	</head>

	<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#123047;">
		<table class="email-wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;background:#ffffff;padding:24px 0;border-collapse:collapse;">
			<tr>
				<td align="center">
					<table class="email-container" cellpadding="0" cellspacing="0" role="presentation" width="620" style="width:620px;max-width:620px;background:#effffb;border-radius:10px;overflow:hidden;border-collapse:collapse;">
						<tr>
							<td class="header" align="center" style="background:#1e9de0;background-image:linear-gradient(135deg,#1e9de0,#126da8);padding:26px 30px 30px;text-align:center;color:#ffffff;">
								<a href="<?php echo esc_url($home_url); ?>" class="logo-link" target="_blank" rel="noopener noreferrer" style="display:inline-block;text-decoration:none;">
									<img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" class="logo-img" style="max-width:220px;width:100%;height:auto;display:block;margin:0 auto 18px;border:0;" />
								</a>
								<div class="check-circle" style="width:58px;height:58px;background:#66f1d4;border-radius:50%;margin:0 auto 16px;line-height:58px;font-size:28px;color:#0d74af;font-weight:bold;text-align:center;">&#10003;</div>
								<h1 style="text-align:center;margin:0;font-size:22px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;"><?php esc_html_e('Order Confirmed!', 'consucorner'); ?></h1>
								<p style="text-align:center;margin:8px 0 0;font-size:13px;color:#d9f6ff;font-family:Arial,Helvetica,sans-serif;"><?php esc_html_e('Your medical supplies are on their way', 'consucorner'); ?></p>
							</td>
						</tr>
						<tr>
							<td class="content" style="padding:34px 38px 28px;background:#effffb;">
								<div class="intro" style="font-size:13px;line-height:1.7;color:#17445c;margin-bottom:22px;font-family:Arial,Helvetica,sans-serif;">
									<strong><?php echo esc_html(sprintf(__('Dear %s,', 'consucorner'), $customer_name)); ?></strong><br />
									<?php echo esc_html($intro); ?>
								</div>

								<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;border-collapse:collapse;table-layout:fixed;">
									<tr>
										<td width="48%" valign="top" style="width:48%;vertical-align:top;">
											<div class="info-card" style="display:block;width:100%;box-sizing:border-box;background:#e6fbf7;border:1px solid #92ddd2;border-radius:9px;padding:14px 16px;font-size:12px;color:#17445c;">
												<span class="info-title" style="display:block;font-size:10px;text-transform:uppercase;color:#1591ca;font-weight:bold;letter-spacing:0.6px;margin-bottom:6px;"><?php esc_html_e('Order Number', 'consucorner'); ?></span>
												<div class="info-value" style="font-weight:bold;font-size:12px;color:#173f57;margin:0;">#<?php echo esc_html($order_number); ?></div>
											</div>
										</td>
										<td width="14" style="width:14px;min-width:14px;max-width:14px;font-size:0;line-height:0;">&nbsp;</td>
										<td width="48%" valign="top" style="width:48%;vertical-align:top;">
											<div class="info-card" style="display:block;width:100%;box-sizing:border-box;background:#e6fbf7;border:1px solid #92ddd2;border-radius:9px;padding:14px 16px;font-size:12px;color:#17445c;">
												<span class="info-title" style="display:block;font-size:10px;text-transform:uppercase;color:#1591ca;font-weight:bold;letter-spacing:0.6px;margin-bottom:6px;"><?php esc_html_e('Order Date', 'consucorner'); ?></span>
												<div class="info-value" style="font-weight:bold;font-size:12px;color:#173f57;margin:0;"><?php echo esc_html($order_date); ?></div>
											</div>
										</td>
									</tr>
									<tr>
										<td colspan="3" height="10" style="height:10px;font-size:0;line-height:0;">&nbsp;</td>
									</tr>
									<tr>
										<td width="48%" valign="top" style="width:48%;vertical-align:top;">
											<div class="info-card" style="display:block;width:100%;box-sizing:border-box;background:#e6fbf7;border:1px solid #92ddd2;border-radius:9px;padding:14px 16px;font-size:12px;color:#17445c;">
												<span class="info-title" style="display:block;font-size:10px;text-transform:uppercase;color:#1591ca;font-weight:bold;letter-spacing:0.6px;margin-bottom:6px;"><?php esc_html_e('Payment Method', 'consucorner'); ?></span>
												<div class="info-value" style="font-weight:bold;font-size:12px;color:#173f57;margin:0;"><?php echo esc_html(wp_strip_all_tags($payment_method)); ?></div>
											</div>
										</td>
										<td width="14" style="width:14px;min-width:14px;max-width:14px;font-size:0;line-height:0;">&nbsp;</td>
										<td width="48%" valign="top" style="width:48%;vertical-align:top;">
											<div class="info-card" style="display:block;width:100%;box-sizing:border-box;background:#e6fbf7;border:1px solid #92ddd2;border-radius:9px;padding:14px 16px;font-size:12px;color:#17445c;">
												<span class="info-title" style="display:block;font-size:10px;text-transform:uppercase;color:#1591ca;font-weight:bold;letter-spacing:0.6px;margin-bottom:6px;"><?php esc_html_e('Estimated Delivery', 'consucorner'); ?></span>
												<div class="info-value" style="font-weight:bold;font-size:12px;color:#173f57;margin:0;"><?php echo esc_html($delivery_est); ?></div>
											</div>
										</td>
									</tr>
								</table>

								<div class="section-title" style="color:#1591ca;font-weight:bold;font-size:11px;letter-spacing:0.8px;text-transform:uppercase;margin:26px 0 12px;"><?php esc_html_e('Order Summary', 'consucorner'); ?></div>
								<table class="summary-table" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;table-layout:fixed;border-collapse:collapse;font-size:12px;color:#16394f;">
									<colgroup>
										<col style="width:70%;" />
										<col style="width:10%;" />
										<col style="width:20%;" />
									</colgroup>
									<thead>
										<tr>
											<th class="item-head" style="font-size:10px;color:#1591ca;text-transform:uppercase;font-weight:bold;padding-bottom:10px;border-bottom:1px solid #aee6dd;text-align:left;padding-right:12px;"><?php esc_html_e('Item', 'consucorner'); ?></th>
											<th class="qty-head" style="font-size:10px;color:#1591ca;text-transform:uppercase;font-weight:bold;padding-bottom:10px;border-bottom:1px solid #aee6dd;text-align:center;white-space:nowrap;"><?php esc_html_e('Qty', 'consucorner'); ?></th>
											<th class="price-head" style="font-size:10px;color:#1591ca;text-transform:uppercase;font-weight:bold;padding-bottom:10px;border-bottom:1px solid #aee6dd;text-align:right;white-space:nowrap;"><?php esc_html_e('Price', 'consucorner'); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($order->get_items() as $item) : ?>
											<?php
											if (! $item instanceof WC_Order_Item_Product) {
												continue;
											}
											$product    = $item->get_product();
											$product_id = $item->get_product_id();
											$meta_line  = $product_id ? consucorner_order_email_product_meta_line($product_id) : '';
											$bulk_display = function_exists('cc_get_order_item_bulk_price_display_data')
												? cc_get_order_item_bulk_price_display_data($item)
												: null;
											?>
											<tr>
												<td class="item-cell" style="padding:12px 12px 12px 0;border-bottom:1px solid #d5f1ed;vertical-align:top;text-align:left;">
													<div class="product-name" style="font-weight:bold;color:#16394f;margin-bottom:4px;"><?php echo esc_html($item->get_name()); ?></div>
													<?php if ($meta_line) : ?>
														<div class="product-cat" style="color:#39a7c8;font-size:11px;line-height:1.4;"><?php echo esc_html($meta_line); ?></div>
													<?php endif; ?>
													<?php if ($bulk_display && ! empty($bulk_display['note'])) : ?>
														<div class="product-bulk-note" style="color:#64748b;font-size:11px;line-height:1.4;margin-top:4px;"><?php echo esc_html($bulk_display['note']); ?></div>
													<?php endif; ?>
												</td>
												<td class="qty-cell" style="padding:12px 0;border-bottom:1px solid #d5f1ed;vertical-align:top;text-align:center;white-space:nowrap;"><?php echo esc_html((string) $item->get_quantity()); ?></td>
												<td class="price-cell" style="padding:12px 0;border-bottom:1px solid #d5f1ed;vertical-align:top;text-align:right;white-space:nowrap;"><?php echo consucorner_order_email_price((float) $item->get_total(), $currency); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
																								?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>

								<table class="totals-table" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;margin-top:12px;font-size:12px;color:#17445c;border-collapse:collapse;">
									<tr>
										<td class="label" style="padding:5px 0;text-align:right;color:#4c7b8b;"><?php esc_html_e('Subtotal', 'consucorner'); ?></td>
										<td class="amount" style="padding:5px 0;text-align:right;width:110px;font-weight:normal;"><?php echo consucorner_order_email_price((float) $order->get_subtotal(), $currency); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
																				?></td>
									</tr>
									<?php if ((float) $order->get_discount_total() > 0) : ?>
										<tr>
											<td class="label" style="padding:5px 0;text-align:right;color:#4c7b8b;"><?php esc_html_e('Discount', 'consucorner'); ?></td>
											<td class="amount" style="padding:5px 0;text-align:right;width:110px;font-weight:normal;">-<?php echo consucorner_order_email_price((float) $order->get_discount_total(), $currency); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
																					?></td>
										</tr>
									<?php endif; ?>
									<tr>
										<td class="label" style="padding:5px 0;text-align:right;color:#4c7b8b;"><?php esc_html_e('Shipping', 'consucorner'); ?></td>
										<td class="amount" style="padding:5px 0;text-align:right;width:110px;font-weight:normal;"><?php echo consucorner_order_email_shipping_display($order); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
																				?></td>
									</tr>
									<?php if ((float) $order->get_total_tax() > 0) : ?>
										<tr>
											<td class="label" style="padding:5px 0;text-align:right;color:#4c7b8b;"><?php echo esc_html($tax_label); ?></td>
											<td class="amount" style="padding:5px 0;text-align:right;width:110px;font-weight:normal;"><?php echo consucorner_order_email_price((float) $order->get_total_tax(), $currency); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
																					?></td>
										</tr>
									<?php endif; ?>
									<?php foreach ($order->get_fees() as $fee) : ?>
										<?php if (! $fee instanceof WC_Order_Item_Fee) {
											continue;
										} ?>
										<tr>
											<td class="label" style="padding:5px 0;text-align:right;color:#4c7b8b;"><?php echo esc_html($fee->get_name()); ?></td>
											<td class="amount" style="padding:5px 0;text-align:right;width:110px;font-weight:normal;"><?php echo consucorner_order_email_price((float) $fee->get_total(), $currency); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
																					?></td>
										</tr>
									<?php endforeach; ?>
									<tr class="total-row">
										<td style="padding-top:12px;border-top:1px dashed #8ddbd0;font-size:16px;color:#1591ca;font-weight:bold;text-align:right;"><?php esc_html_e('Total', 'consucorner'); ?></td>
										<td class="amount" style="padding-top:12px;border-top:1px dashed #8ddbd0;font-size:16px;color:#1591ca;font-weight:bold;text-align:right;width:110px;"><?php echo consucorner_order_email_price((float) $order->get_total(), $currency); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
																				?></td>
									</tr>
								</table>

								<?php if ($shipping_address) : ?>
									<div class="section-title" style="color:#1591ca;font-weight:bold;font-size:11px;letter-spacing:0.8px;text-transform:uppercase;margin:26px 0 12px;"><?php esc_html_e('Shipping Address', 'consucorner'); ?></div>
									<div class="address-card" style="background:#e6fbf7;border:1px solid #92ddd2;border-radius:9px;padding:16px;">
										<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
											<tr>
												<td width="48" valign="top">
													<div class="address-icon" style="width:36px;height:36px;border-radius:8px;background:#25a8e0;color:#ffffff;text-align:center;line-height:36px;font-size:18px;">&#8982;</div>
												</td>
												<td class="address-text" valign="top" style="font-size:12px;line-height:1.6;color:#17445c;">
													<strong style="color:#173f57;"><?php esc_html_e('Delivery To', 'consucorner'); ?></strong><br />
													<?php echo $shipping_address; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
													?>
												</td>
											</tr>
										</table>
									</div>
								<?php endif; ?>

								<a href="<?php echo esc_url($track_url); ?>" class="track-button" style="display:block;background:#2ba8e4;background-image:linear-gradient(135deg,#2ba8e4,#19cda4);color:#ffffff !important;text-decoration:none;padding:15px;border-radius:8px;text-align:center;font-size:13px;font-weight:bold;margin:24px 0 6px;">
									<?php esc_html_e('Track My Order →', 'consucorner'); ?>
								</a>
							</td>
						</tr>
						<tr>
							<td class="features" style="background:#e3faf6;padding:22px 28px;border-top:1px solid #c4eee7;">
								<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;border-collapse:collapse;table-layout:fixed;">
									<tr>
										<td width="33.33%" align="center" valign="top" style="width:33.33%;text-align:center;padding:0 10px;">
											<div class="feature-icon" style="color:#1591ca;font-size:18px;line-height:20px;margin-bottom:6px;font-weight:bold;">24h</div>
											<div class="feature-title" style="color:#1591ca;font-weight:bold;font-size:11px;margin-bottom:4px;"><?php esc_html_e('24/7 Support', 'consucorner'); ?></div>
											<div class="feature-text" style="color:#4d7886;font-size:10px;line-height:1.4;"><?php echo esc_html($support_email); ?></div>
										</td>
										<td width="33.33%" align="center" valign="top" style="width:33.33%;text-align:center;padding:0 10px;">
											<div class="feature-icon" style="color:#1591ca;font-size:22px;line-height:20px;margin-bottom:6px;font-weight:bold;">&#8634;</div>
											<div class="feature-title" style="color:#1591ca;font-weight:bold;font-size:11px;margin-bottom:4px;"><?php esc_html_e('Easy Returns', 'consucorner'); ?></div>
											<div class="feature-text" style="color:#4d7886;font-size:10px;line-height:1.4;"><?php esc_html_e('30-day return policy', 'consucorner'); ?></div>
										</td>
										<td width="33.33%" align="center" valign="top" style="width:33.33%;text-align:center;padding:0 10px;">
											<div class="feature-icon" style="color:#1591ca;font-size:22px;line-height:20px;margin-bottom:6px;font-weight:bold;">&#8594;</div>
											<div class="feature-title" style="color:#1591ca;font-weight:bold;font-size:11px;margin-bottom:4px;"><?php esc_html_e('Fast Shipping', 'consucorner'); ?></div>
											<div class="feature-text" style="color:#4d7886;font-size:10px;line-height:1.4;"><?php esc_html_e('2–3 business days', 'consucorner'); ?></div>
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr>
							<td class="footer" style="background:#1e9de0;background-image:linear-gradient(135deg,#1e9de0,#126da8);color:#ffffff;text-align:center;padding:24px 30px;">
								<div class="footer-logo" style="font-size:18px;font-weight:bold;letter-spacing:1px;margin-bottom:8px;"><?php echo esc_html(get_bloginfo('name')); ?></div>
								<div class="footer-text" style="font-size:11px;color:#d8f6ff;margin-bottom:12px;"><?php esc_html_e('Egypt\'s Trusted Medical Supply Hub', 'consucorner'); ?></div>
								<div class="footer-links" style="font-size:10px;color:#d8f6ff;margin-bottom:12px;">
									<?php
									$link_bits = array();
									foreach ($footer_links as $link) {
										$link_bits[] = '<a href="' . esc_url($link['url']) . '" target="_blank" rel="noopener noreferrer" style="color:#d8f6ff;text-decoration:none;">' . esc_html($link['label']) . '</a>';
									}
									echo wp_kses_post(implode(' &nbsp;|&nbsp; ', $link_bits));
									?>
								</div>
								<div>
									<span class="payment-badge" style="display:inline-block;background:rgba(255,255,255,0.18);color:#ffffff;font-size:9px;padding:4px 9px;border-radius:12px;margin:3px;text-transform:uppercase;">Visa</span>
									<span class="payment-badge" style="display:inline-block;background:rgba(255,255,255,0.18);color:#ffffff;font-size:9px;padding:4px 9px;border-radius:12px;margin:3px;text-transform:uppercase;">Mastercard</span>
									<span class="payment-badge" style="display:inline-block;background:rgba(255,255,255,0.18);color:#ffffff;font-size:9px;padding:4px 9px;border-radius:12px;margin:3px;text-transform:uppercase;">Fawry</span>
									<span class="payment-badge" style="display:inline-block;background:rgba(255,255,255,0.18);color:#ffffff;font-size:9px;padding:4px 9px;border-radius:12px;margin:3px;text-transform:uppercase;"><?php esc_html_e('Cash on Delivery', 'consucorner'); ?></span>
								</div>
								<div class="copyright" style="font-size:9px;color:#bdeeff;margin-top:12px;line-height:1.5;">
									&copy; <?php echo esc_html(gmdate('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php esc_html_e('All Rights Reserved.', 'consucorner'); ?><br />
									<?php echo esc_html(sprintf(__('Cairo, Egypt · %s', 'consucorner'), wp_parse_url(home_url(), PHP_URL_HOST))); ?>
								</div>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</body>

	</html>
<?php
}

/**
 * Plain-text fallback for the processing-order email.
 *
 * @param WC_Order $order              Order.
 * @param string   $additional_content Extra content.
 */
function consucorner_render_processing_order_email_plain($order, $additional_content)
{
	if (! $order instanceof WC_Order) {
		return;
	}

	echo esc_html(sprintf(__('Dear %s,', 'consucorner'), consucorner_order_email_customer_name($order))) . "\n\n";
	echo esc_html__('Your order has been confirmed and is being processed.', 'consucorner') . "\n\n";
	echo esc_html__('Order Number:', 'consucorner') . ' #' . esc_html($order->get_order_number()) . "\n";
	echo esc_html__('Order Date:', 'consucorner') . ' ' . esc_html(consucorner_order_email_order_date($order)) . "\n";
	echo esc_html__('Payment Method:', 'consucorner') . ' ' . esc_html(wp_strip_all_tags($order->get_payment_method_title())) . "\n";
	echo esc_html__('Estimated Delivery:', 'consucorner') . ' ' . esc_html(consucorner_order_email_estimated_delivery($order)) . "\n\n";

	echo "----------------------------------------\n";
	echo esc_html__('Order Summary', 'consucorner') . "\n";
	echo "----------------------------------------\n";

	foreach ($order->get_items() as $item) {
		if (! $item instanceof WC_Order_Item_Product) {
			continue;
		}
		echo esc_html($item->get_name()) . ' x' . esc_html((string) $item->get_quantity()) . ' - ';
		echo wp_strip_all_tags(wc_price((float) $item->get_total(), array('currency' => $order->get_currency()))) . "\n";

		$bulk_display = function_exists('cc_get_order_item_bulk_price_display_data')
			? cc_get_order_item_bulk_price_display_data($item)
			: null;
		if ($bulk_display && ! empty($bulk_display['note'])) {
			echo '  ' . esc_html($bulk_display['note']) . "\n";
		}
	}

	echo "\n" . esc_html__('Total:', 'consucorner') . ' ';
	echo wp_strip_all_tags(wc_price((float) $order->get_total(), array('currency' => $order->get_currency()))) . "\n\n";
	echo esc_html__('Track your order:', 'consucorner') . ' ' . esc_url(consucorner_order_email_track_url($order)) . "\n";
}
