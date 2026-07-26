<?php

/**
 * Single product content — fully custom layout that mirrors
 * front-end/single-product.html (the design reference).
 *
 * Overrides woocommerce/content-single-product.php
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

global $product;

if (empty($product) || ! $product->is_visible()) {
	$product = wc_get_product(get_the_ID());
}
if (! $product) {
	return;
}

$theme_uri = get_template_directory_uri();
$img_uri   = $theme_uri . '/assets/images/';
$placeholder_img = function_exists('consucorner_get_product_placeholder_image_url')
	? consucorner_get_product_placeholder_image_url()
	: $img_uri . rawurlencode('consucorner icon-logo.jpg');

// Gallery images (always include the main image first).
$gallery_ids = $product->get_gallery_image_ids();
$main_id     = $product->get_image_id();
$image_ids   = array();
if ($main_id) {
	$image_ids[] = (int) $main_id;
}
foreach ($gallery_ids as $gid) {
	$gid = (int) $gid;
	if ($gid && ! in_array($gid, $image_ids, true)) {
		$image_ids[] = $gid;
	}
}

$image_urls = array();
foreach ($image_ids as $iid) {
	$url = wp_get_attachment_image_url($iid, 'large');
	if ($url) {
		$image_urls[] = $url;
	}
}
if (empty($image_urls)) {
	$image_urls[] = $placeholder_img;
}

// Pricing.
$regular_price = (float) $product->get_regular_price();
$sale_price    = (float) $product->get_sale_price();
$current_price = (float) $product->get_price();
$currency      = get_woocommerce_currency();
$is_quote_product = cc_is_quote_product($product);
$is_variable   = $product->is_type('variable');
$show_variable_form = $is_variable && ! $is_quote_product && $product->is_purchasable() && $product->is_in_stock();

if ($show_variable_form) {
	cc_variable_product_prepare($product);
}

// Categories.
$cat_links = wc_get_product_category_list($product->get_id(), ', ', '<span class="sp-cat-link-wrap">', '</span>');

// Vendor (Dokan-aware, falls back to author). Hidden on Get A Quote products.
$vendor_name = '';
$vendor_logo = '';
$vendor_url  = '';
if (! $is_quote_product && function_exists('dokan_get_store_info') && function_exists('get_user_meta')) {
	$vendor_id = (int) get_post_field('post_author', $product->get_id());
	if ($vendor_id) {
		$store_info  = dokan_get_store_info($vendor_id);
		$vendor_name = ! empty($store_info['store_name']) ? $store_info['store_name'] : '';
		if (! empty($store_info['gravatar'])) {
			$vendor_logo = wp_get_attachment_url((int) $store_info['gravatar']);
		}
		if (function_exists('dokan_get_store_url')) {
			$vendor_url = dokan_get_store_url($vendor_id);
		}
	}
}
if (! $is_quote_product && ! $vendor_logo) {
	$vendor_logo = function_exists('consucorner_get_vendor_placeholder_image_url')
		? consucorner_get_vendor_placeholder_image_url()
		: $img_uri . rawurlencode('consucorner icon-logo.jpg');
}

// Country of origin — prefer the custom taxonomy, with legacy attribute/meta fallback.
$country_info  = function_exists('cc_get_product_country_origin_info')
	? cc_get_product_country_origin_info($product->get_id())
	: array('name' => '', 'image_url' => '');
$country_name  = isset($country_info['name']) ? $country_info['name'] : '';
$country_image = isset($country_info['image_url']) ? $country_info['image_url'] : '';
$country_slugs = function_exists('consucorner_country_origin_legacy_taxonomies')
	? consucorner_country_origin_legacy_taxonomies()
	: array('pa_country-of-origin', 'pa_country_of_origin', 'pa_country', 'pa_origin');

// Brand: WooCommerce attributes first, then common brand taxonomies (e.g. product_brand used in shop filters).
$brand_name  = '';
$brand_slugs = array(
	'pa_brand',
	'pa_brands',
	'pa_brand-name',
	'pa_brand_name',
	'pa_manufacturer',
	'pa_product-brand',
	'pa_product_brand',
);
foreach ($product->get_attributes() as $attr_slug => $attribute) {
	if ($attribute->get_variation()) {
		continue;
	}
	$slug = strtolower($attr_slug);
	if (in_array($slug, $country_slugs, true)) {
		continue;
	}
	$label = strtolower(wc_attribute_label($attribute->get_name(), $product));
	$is_brand = in_array($slug, $brand_slugs, true)
		|| (strpos($label, 'brand') !== false && strpos($label, 'country') === false)
		|| strpos($label, 'manufacturer') !== false
		|| (strpos($slug, 'brand') !== false && strpos($slug, 'country') === false);
	if (! $is_brand) {
		continue;
	}
	if ($attribute->is_taxonomy()) {
		$terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), array('fields' => 'names'));
		if (! empty($terms)) {
			$brand_name = implode(', ', $terms);
			break;
		}
	} else {
		$opts = $attribute->get_options();
		if (! empty($opts)) {
			$brand_name = implode(', ', $opts);
			break;
		}
	}
}
if (! $brand_name) {
	$brand_taxonomies = array('product_brand', 'pwb-brand', 'yith_product_brand', 'berocket_brand');
	foreach ($brand_taxonomies as $btax) {
		if (! taxonomy_exists($btax)) {
			continue;
		}
		$brand_terms = get_the_terms($product->get_id(), $btax);
		if (! is_wp_error($brand_terms) && ! empty($brand_terms)) {
			$brand_name = implode(', ', wp_list_pluck($brand_terms, 'name'));
			break;
		}
	}
}

$brand_logo_url = '';
$brand_shop_url = '';
if ($brand_name && function_exists('cc_get_product_brand_info')) {
	$brand_info     = cc_get_product_brand_info($product->get_id());
	$brand_logo_url = $brand_info['logo_url'];
	if ($brand_info['name']) {
		$brand_name = $brand_info['name'];
	}
}
if (function_exists('cc_get_product_brand_shop_url')) {
	$brand_shop_url = cc_get_product_brand_shop_url($product->get_id());
}
$country_shop_url = ! empty($country_info['shop_url']) ? $country_info['shop_url'] : '';

$applications_raw       = get_post_meta($product->get_id(), '_cc_applications', true);
$surgical_applications  = get_post_meta($product->get_id(), '_cc_surgical_applications', true);
$know_more_text         = get_post_meta($product->get_id(), '_cc_know_more', true);
$specs_intro            = get_post_meta($product->get_id(), '_cc_specs_intro', true);

$applications = array();
if ($applications_raw) {
	foreach (preg_split('/\r?\n/', $applications_raw) as $line) {
		$line = trim($line);
		if ($line !== '') {
			$applications[] = $line;
		}
	}
}

// Render the long description through `the_content` filters so any HTML
// tables / inline styles / shortcodes the admin entered render exactly
// as authored (this is what `the_content()` does in core templates).
$rendered_long_description = apply_filters('the_content', $product->get_description());

// Fall back to the long description for "Know More" if no custom text was entered.
if (! $know_more_text) {
	$know_more_text_html = $rendered_long_description;
} else {
	$know_more_text_html = apply_filters('the_content', $know_more_text);
}

// Fall back to the short description for the specs intro paragraph.
if (! $specs_intro) {
	$specs_intro_source = $product->get_short_description();
	$specs_intro_html   = $specs_intro_source
		? apply_filters('woocommerce_short_description', $specs_intro_source)
		: $rendered_long_description;
} else {
	$specs_intro_html = apply_filters('the_content', $specs_intro);
}

// Product attributes for the technical specs table (skip variation attrs).
$visible_attrs = array();
foreach ($product->get_attributes() as $attribute) {
	if (! $attribute->get_visible() || $attribute->get_variation()) {
		continue;
	}
	if ($attribute->is_taxonomy() && in_array($attribute->get_name(), $country_slugs, true)) {
		continue;
	}
	$name  = wc_attribute_label($attribute->get_name(), $product);
	$value = '';
	if ($attribute->is_taxonomy()) {
		$terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), array('fields' => 'names'));
		$value = implode(', ', $terms);
	} else {
		$value = implode(', ', $attribute->get_options());
	}
	if ($name !== '' && $value !== '') {
		$visible_attrs[] = array('name' => $name, 'value' => $value);
	}
}

// FAQs (up to 4, editable per product).
$faqs = array();
for ($i = 1; $i <= 4; $i++) {
	$q = get_post_meta($product->get_id(), "_cc_faq{$i}_q", true);
	$a = get_post_meta($product->get_id(), "_cc_faq{$i}_a", true);
	if ($q && $a) {
		$faqs[] = array('q' => $q, 'a' => $a);
	}
}

// Often Ordered With — same specialty (fallback: WooCommerce related).
$related_ids = cc_get_specialty_related_product_ids($product->get_id(), 10);

// Reviews.
$reviews = get_comments(array(
	'post_id' => $product->get_id(),
	'status'  => 'approve',
	'type'    => 'review',
	'number'  => 8,
));
?>

<!-- ── Single Product Page ── -->
<section class="sp-section">
	<div class="sp-inner">

		<!-- ── TOP: Image + Details ── -->
		<div class="sp-top">

			<!-- Image Panel -->
			<div class="sp-image-panel">
				<button class="sp-wishlist-btn" type="button" data-product-id="<?php echo esc_attr($product->get_id()); ?>" aria-label="<?php esc_attr_e('Save', 'consucorner'); ?>" aria-pressed="false">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
					</svg>
				</button>

				<?php $image_count = count($image_urls);
				$has_slider = $image_count > 1; ?>
				<div class="sp-slider-viewport<?php echo $has_slider ? '' : ' sp-slider-viewport--single'; ?>" id="spSliderViewport">
					<div class="sp-slider-track" id="spSliderTrack" data-total="<?php echo absint($image_count); ?>">
						<?php foreach ($image_urls as $i => $img_url) : ?>
							<div class="sp-slide">
								<img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($product->get_name() . ($has_slider ? ' ' . ($i + 1) : '')); ?>" class="sp-main-img" />
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<?php if ($has_slider) : ?>
					<div class="sp-image-bottom">
						<div class="sp-image-bottom-spacer"></div>
						<div class="sp-slider-dots" id="spSliderDots">
							<?php foreach ($image_urls as $i => $img_url) : ?>
								<span class="sp-dot<?php echo 0 === $i ? ' sp-dot--active' : ''; ?>" data-index="<?php echo absint($i); ?>"></span>
							<?php endforeach; ?>
						</div>
						<div class="sp-image-nav">
							<button class="sp-nav-btn" id="spPrev" aria-label="<?php esc_attr_e('Previous', 'consucorner'); ?>">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
									<polyline points="15 18 9 12 15 6" />
								</svg>
							</button>
							<button class="sp-nav-btn" id="spNext" aria-label="<?php esc_attr_e('Next', 'consucorner'); ?>">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
									<polyline points="9 18 15 12 9 6" />
								</svg>
							</button>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<!-- Details Panel — order: title → price → pills → trust bar → short description → actions (Figma) -->
			<div class="sp-details" data-cc-tour="product-details">
				<h1 class="sp-title"><?php echo esc_html($product->get_name()); ?></h1>

				<?php if ($show_variable_form) : ?>
					<?php cc_variable_product_form_start(); ?>
				<?php endif; ?>

				<?php if (! $is_quote_product) : ?>
					<div class="sp-pricing<?php echo $is_variable ? ' sp-pricing--variable sp-pricing--pending' : ''; ?>" id="spProductPrice" aria-live="polite">
						<?php if ($is_variable) : ?>
							<?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC price HTML. ?>
						<?php else : ?>
							<?php if ($product->is_on_sale() && $regular_price > 0) : ?>
								<span class="sp-price-old"><?php echo esc_html(function_exists('cc_format_product_price_amount') ? cc_format_product_price_amount($regular_price) : wc_format_localized_price($regular_price)) . ' ' . esc_html($currency); ?></span>
							<?php endif; ?>
							<div class="sp-price-current">
								<span class="sp-price"><?php echo esc_html(function_exists('cc_format_product_price_amount') ? cc_format_product_price_amount($current_price) : wc_format_localized_price($current_price)); ?></span>
								<span class="sp-currency"><?php echo esc_html($currency); ?></span>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php
				if ( ! $is_quote_product && function_exists( 'cc_bundles_render_single_product_promo' ) ) {
					$sp_bundle_promo_html = cc_bundles_render_single_product_promo( $product );
					if ( '' !== $sp_bundle_promo_html ) {
						echo $sp_bundle_promo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				}
				?>

				<?php
				$sp_offer_deal = ( ! $is_quote_product && function_exists( 'cc_offers_get_product_deal' ) )
					? cc_offers_get_product_deal($product, array('require_stock' => true))
					: null;
				$sp_bulk_tiers = (! $is_quote_product && function_exists('cc_get_product_bulk_tiers'))
					? cc_get_product_bulk_tiers($product, array('require_stock' => true))
					: array();
				// Bulk-only floor for the qty input: only enforced when there is no
				// exact-qty Offer Deal, so the deal's smaller bundle stays reachable.
				$sp_bulk_min_qty = ($sp_bulk_tiers && ! $sp_offer_deal && function_exists('cc_get_product_bulk_min_qty'))
					? cc_get_product_bulk_min_qty($product)
					: 0;
				// +/- click increment for bulk-tier products (e.g. +5 per click
				// instead of +1) so reaching a high minimum takes fewer clicks.
				$sp_bulk_qty_step = ($sp_bulk_tiers && function_exists('cc_get_product_bulk_qty_step'))
					? cc_get_product_bulk_qty_step($product)
					: 1;

				if ($sp_offer_deal || $sp_bulk_tiers) :
					$sp_pricing_json = array(
						'catalogPriceFormatted' => function_exists('cc_format_product_price_amount') ? cc_format_product_price_amount($current_price) : wc_format_localized_price($current_price),
						'deal'                  => null,
						'tiers'                 => array(),
					);

					if ($sp_offer_deal) {
						$sp_pricing_json['deal'] = array(
							'qty'           => (int) $sp_offer_deal['qty'],
							'unit'          => (float) $sp_offer_deal['unit'],
							'unitFormatted' => cc_format_product_price_amount((float) $sp_offer_deal['unit']),
						);
					}

					foreach ($sp_bulk_tiers as $sp_tier) {
						$sp_tier_price = (float) $sp_tier['price'];
						$sp_pricing_json['tiers'][] = array(
							'min'            => (int) $sp_tier['min'],
							'max'            => (int) $sp_tier['max'],
							'price'          => $sp_tier_price,
							'priceFormatted' => function_exists('cc_format_bulk_unit_price_amount')
								? cc_format_bulk_unit_price_amount($sp_tier_price, $product)
								: cc_format_product_price_amount($sp_tier_price),
						);
					}
				?>
					<div class="sp-pricing-deals" id="spPricingDeals" data-cc-pricing="<?php echo esc_attr(wp_json_encode($sp_pricing_json)); ?>">
						<?php if ($sp_offer_deal) : ?>
							<div
								class="sp-offer-deal"
								id="spOfferDeal"
								role="button"
								tabindex="0"
								aria-pressed="false"
								data-qty="<?php echo esc_attr((int) $sp_offer_deal['qty']); ?>"
								aria-label="<?php
								echo esc_attr(
									sprintf(
										/* translators: 1: deal threshold qty, 2: unit price with currency */
										__('Apply offer: buy %1$d or more at %2$s each', 'consucorner'),
										(int) $sp_offer_deal['qty'],
										cc_format_product_price_amount((float) $sp_offer_deal['unit']) . ' ' . $currency
									)
								);
								?>"
							>
								<span class="sp-offer-deal__badge"><?php esc_html_e('Bundle deal', 'consucorner'); ?></span>
								<p class="sp-offer-deal__text">
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: deal threshold qty, 2: unit price with currency */
											__('Buy %1$d+ for %2$s each', 'consucorner'),
											(int) $sp_offer_deal['qty'],
											cc_format_product_price_amount((float) $sp_offer_deal['unit']) . ' ' . $currency
										)
									);
									?>
								</p>
								<div class="sp-offer-deal__meta">
									<span class="sp-offer-deal__regular"><?php echo esc_html(cc_format_product_price_amount((float) $sp_offer_deal['regular_total']) . ' ' . $currency); ?></span>
									<span class="sp-offer-deal__save"><?php echo esc_html(sprintf(__('Save %d%%', 'consucorner'), (int) $sp_offer_deal['save_percent'])); ?></span>
								</div>
							</div>
						<?php endif; ?>

						<?php if ($sp_bulk_tiers) : ?>
							<div class="sp-bulk-pricing" id="spBulkPricing">
								<p class="sp-bulk-pricing__title"><?php esc_html_e('Bulk pricing', 'consucorner'); ?></p>
								<p class="sp-bulk-pricing__hint"><?php esc_html_e('Select a tier to set your quantity — the unit price updates automatically.', 'consucorner'); ?></p>
								<div class="sp-bulk-pricing__grid">
									<?php foreach ($sp_bulk_tiers as $sp_tier) : ?>
										<button
											type="button"
											class="sp-bulk-tier"
											data-min="<?php echo esc_attr((int) $sp_tier['min']); ?>"
											data-max="<?php echo esc_attr((int) $sp_tier['max']); ?>">
											<span class="sp-bulk-tier__range"><?php echo esc_html($sp_tier['label']); ?></span>
											<?php if ((int) $sp_tier['save_percent'] > 0) : ?>
												<span class="sp-bulk-tier__save"><?php echo esc_html(sprintf(__('Save %d%%', 'consucorner'), (int) $sp_tier['save_percent'])); ?></span>
											<?php endif; ?>
											<span class="sp-bulk-tier__price"><?php
												$sp_tier_price_fmt = function_exists('cc_format_bulk_unit_price_amount')
													? cc_format_bulk_unit_price_amount((float) $sp_tier['price'], $product)
													: cc_format_product_price_amount((float) $sp_tier['price']);
												echo esc_html($sp_tier_price_fmt . ' ' . $currency);
											?></span>
											<span class="sp-bulk-tier__unit"><?php esc_html_e('per unit', 'consucorner'); ?></span>
										</button>
									<?php endforeach; ?>
								</div>
								<p class="sp-bulk-pricing__note"><?php esc_html_e('Prices apply to the full order, not only the units above each threshold.', 'consucorner'); ?></p>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ($show_variable_form) : ?>
					<?php cc_variable_product_render_variations(); ?>
				<?php endif; ?>

				<?php if ($country_name || $brand_name || ($vendor_name && ! $is_quote_product)) : ?>
					<div class="sp-meta-pills" role="group" aria-label="<?php esc_attr_e('Product identifiers', 'consucorner'); ?>">
						<?php if ($country_name) : ?>
							<?php if ($country_shop_url) : ?>
								<a href="<?php echo esc_url($country_shop_url); ?>" class="sp-pill sp-pill--link" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: country name */ __('View products from %s', 'consucorner'), $country_name)); ?>">
									<span class="sp-pill-value"><?php echo esc_html($country_name); ?></span>
									<?php if ($country_image) : ?>
										<img class="sp-pill-flag" src="<?php echo esc_url($country_image); ?>" alt="" width="20" height="20" loading="lazy" decoding="async" />
									<?php endif; ?>
								</a>
							<?php else : ?>
								<div class="sp-pill">
									<span class="sp-pill-value"><?php echo esc_html($country_name); ?></span>
									<?php if ($country_image) : ?>
										<img class="sp-pill-flag" src="<?php echo esc_url($country_image); ?>" alt="" width="20" height="20" loading="lazy" decoding="async" />
									<?php endif; ?>
								</div>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ($brand_name) : ?>
							<?php if ($brand_shop_url) : ?>
								<a href="<?php echo esc_url($brand_shop_url); ?>" class="sp-pill sp-pill--brand sp-pill--brand-logo-only sp-pill--link" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: brand name */ __('View products by %s', 'consucorner'), $brand_name)); ?>">
									<span class="card-pill-icon card-pill-icon--brand" aria-hidden="true">
										<?php if ($brand_logo_url) : ?>
											<img src="<?php echo esc_url($brand_logo_url); ?>" alt="" loading="lazy" decoding="async" />
										<?php else : ?>
											<?php echo esc_html(mb_strtoupper(mb_substr($brand_name, 0, 1))); ?>
										<?php endif; ?>
									</span>
								</a>
							<?php else : ?>
								<div class="sp-pill sp-pill--brand sp-pill--brand-logo-only" aria-label="<?php echo esc_attr($brand_name); ?>">
									<span class="card-pill-icon card-pill-icon--brand" aria-hidden="true">
										<?php if ($brand_logo_url) : ?>
											<img src="<?php echo esc_url($brand_logo_url); ?>" alt="" loading="lazy" decoding="async" />
										<?php else : ?>
											<?php echo esc_html(mb_strtoupper(mb_substr($brand_name, 0, 1))); ?>
										<?php endif; ?>
									</span>
								</div>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ($vendor_name && ! $is_quote_product) : ?>
							<div class="sp-pill sp-pill--vendor">
								<span class="sp-pill-value"><?php echo esc_html($vendor_name); ?></span>
								<img class="sp-pill-vendor-logo" src="<?php echo esc_url($vendor_logo); ?>" alt="" width="22" height="22" loading="lazy" decoding="async" />
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

		<div class="sp-trust-bar" role="list" aria-label="<?php esc_attr_e('Purchase assurances', 'consucorner'); ?>">
			<div class="sp-trust-item" role="listitem">
				<span class="sp-trust-dot<?php echo $product->is_in_stock() ? '' : ' sp-trust-dot--out'; ?>" aria-hidden="true"></span>
				<span class="sp-trust-text"><?php echo $product->is_in_stock() ? esc_html__('In stock', 'consucorner') : esc_html__('Out of stock', 'consucorner'); ?></span>
			</div>
			<div class="sp-trust-item" role="listitem">
				<span class="sp-trust-ic" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
						<path d="M3 3v5h5" />
						<path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16" />
						<path d="M16 21h5v-5" />
					</svg>
				</span>
				<span class="sp-trust-text"><?php esc_html_e('Delivered in 48h', 'consucorner'); ?></span>
			</div>
			<div class="sp-trust-item" role="listitem">
				<span class="sp-trust-ic" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<rect x="6" y="10" width="12" height="10" rx="2" />
						<path d="M9 10V8a3 3 0 0 1 6 0v2" />
					</svg>
				</span>
				<span class="sp-trust-text"><?php esc_html_e('Secure payment', 'consucorner'); ?></span>
			</div>
		</div>

		<?php if ($product->get_short_description()) : ?>
			<div class="sp-description"><?php echo apply_filters('woocommerce_short_description', $product->get_short_description()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentionally rendered to preserve admin-authored HTML/tables/inline styles.
																	?></div>
		<?php endif; ?>

		<?php if ($is_quote_product) : ?>
			<div class="sp-actions sp-actions--cta">
				<button
					type="button"
					id="spGetQuoteBtn"
					class="sp-btn-quote js-cc-quote-trigger"
					data-product-id="<?php echo esc_attr($product->get_id()); ?>"
					data-product-name="<?php echo esc_attr($product->get_name()); ?>">
					<?php esc_html_e('Get A Quote', 'consucorner'); ?>
				</button>
			</div>
		<?php
		elseif ($show_variable_form) :
			cc_variable_product_render_actions();
			cc_variable_product_form_end();
		elseif ($product->is_purchasable() && $product->is_in_stock()) :
			$checkout_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/');
			$buy_now_url  = esc_url(
				add_query_arg(
					array(
						'add-to-cart' => $product->get_id(),
						'quantity'    => 1,
					),
					$checkout_url
				)
			);
		?>
			<form class="sp-cart-form cart" action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>" method="post" enctype="multipart/form-data">
				<div class="sp-actions sp-actions--toolbar">
					<div class="sp-qty">
						<button type="button" class="sp-qty-btn" id="spQtyMinus" aria-label="<?php esc_attr_e('Decrease quantity', 'consucorner'); ?>">−</button>
						<input type="number" class="sp-qty-val qty" id="spQtyVal" name="quantity" value="<?php echo esc_attr($sp_bulk_min_qty > 0 ? $sp_bulk_min_qty : 1); ?>" min="<?php echo esc_attr($sp_bulk_min_qty > 0 ? $sp_bulk_min_qty : 1); ?>" step="1" inputmode="numeric" data-bulk-min="<?php echo esc_attr($sp_bulk_min_qty); ?>" data-bulk-step="<?php echo esc_attr($sp_bulk_qty_step); ?>" />
						<button type="button" class="sp-qty-btn" id="spQtyPlus" aria-label="<?php esc_attr_e('Increase quantity', 'consucorner'); ?>">+</button>
					</div>
				</div>
				<div class="sp-actions sp-actions--cta">
					<a
						href="<?php echo $buy_now_url; ?>"
						class="sp-btn-buy"
						id="spBuyNow"
						data-checkout="<?php echo esc_url($checkout_url); ?>"
						data-product-id="<?php echo esc_attr($product->get_id()); ?>"><?php esc_html_e('Buy now', 'consucorner'); ?></a>
					<button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="sp-btn-cart single_add_to_cart_button button alt" data-cc-tour="add-to-cart" data-product-id="<?php echo esc_attr($product->get_id()); ?>" data-product-name="<?php echo esc_attr($product->get_name()); ?>" data-product-price="<?php echo esc_attr((string) $current_price); ?>" data-product-image="<?php echo esc_url($image_urls[0]); ?>" data-product-permalink="<?php echo esc_url(get_permalink($product->get_id())); ?>"<?php function_exists('cc_gtm_print_product_data_attributes') && cc_gtm_print_product_data_attributes($product); ?>>
						<?php echo esc_html($product->single_add_to_cart_text()); ?>
					</button>
				</div>
			</form>
		<?php else : ?>
			<div class="sp-actions sp-actions--unavailable">
				<p class="sp-out-of-stock"><?php esc_html_e('This product is currently unavailable.', 'consucorner'); ?></p>
			</div>
		<?php endif; ?>

		<div class="sp-meta">
			<?php if ($product->get_sku()) : ?>
				<div class="sp-meta-row">
					<span class="sp-meta-label"><?php esc_html_e('SKU:', 'consucorner'); ?></span>
					<span class="sp-meta-val"><?php echo esc_html($product->get_sku()); ?></span>
				</div>
			<?php endif; ?>
			<?php if ($cat_links) : ?>
				<div class="sp-meta-row">
					<span class="sp-meta-label"><?php esc_html_e('Categories:', 'consucorner'); ?></span>
					<span class="sp-meta-val"><?php echo wp_kses_post($cat_links); ?></span>
				</div>
			<?php endif; ?>
		</div>
		</div>
	</div><!-- /.sp-top -->

	<?php if ($rendered_long_description) : ?>
		<!-- ── Product Description: collapsible box (admin-authored HTML) ── -->
		<details class="sp-desc-box">
			<summary class="sp-desc-box__summary">
				<span class="sp-desc-box__summary-text"><?php esc_html_e('Description', 'consucorner'); ?></span>
				<span class="sp-desc-box__chevron" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="6 9 12 15 18 9" />
					</svg>
				</span>
			</summary>
			<div class="sp-desc-box__panel">
				<div class="sp-product-description sp-body-text">
					<?php echo $rendered_long_description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentionally rendered to preserve admin-authored HTML/tables/inline styles.
					?>
				</div>
			</div>
		</details>
	<?php endif; ?>

	</div><!-- /.sp-inner -->
</section>

<?php if ($is_quote_product) : ?>
	<div
		id="ccQuoteModal"
		class="cc-quote-modal"
		role="dialog"
		aria-modal="true"
		aria-label="<?php esc_attr_e('Get A Quote', 'consucorner'); ?>"
		hidden>
		<div class="cc-quote-modal__backdrop"></div>
		<div class="cc-quote-modal__box">
			<button type="button" class="cc-quote-modal__close" aria-label="<?php esc_attr_e('Close', 'consucorner'); ?>">
				&#x2715;
			</button>
			<div class="cc-quote-modal__header">
				<span class="cc-quote-modal__icon" aria-hidden="true">&#128196;</span>
				<h2 class="cc-quote-modal__title"><?php esc_html_e('Request a Quote', 'consucorner'); ?></h2>
				<p class="cc-quote-modal__subtitle"><?php esc_html_e("Fill in your details and we'll get back to you shortly.", 'consucorner'); ?></p>
			</div>
			<?php
			$quote_form_id = function_exists('cc_get_quote_forminator_form_id') ? cc_get_quote_forminator_form_id() : 0;
			if ($quote_form_id && shortcode_exists('forminator_form')) {
				echo do_shortcode('[forminator_form id="' . absint($quote_form_id) . '"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<p class="cc-quote-modal__empty">' . esc_html__('The quote form is currently unavailable.', 'consucorner') . '</p>';
			}
			?>
		</div>
	</div>
<?php endif; ?>

<?php
if (! $is_quote_product && $product->is_purchasable() && $product->is_in_stock()) :
	$sp_sticky_checkout = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/');
	$sp_sticky_buy_url  = esc_url(
		add_query_arg(
			array(
				'add-to-cart' => $product->get_id(),
				'quantity'    => 1,
			),
			$sp_sticky_checkout
		)
	);
	$sp_sticky_price_fmt = function_exists('cc_format_product_price_amount')
		? cc_format_product_price_amount($current_price)
		: wc_format_localized_price($current_price);
	?>
	<aside
		class="sp-sticky-atc<?php echo $is_variable ? ' sp-sticky-atc--variable' : ''; ?>"
		id="spStickyAtc"
		data-product-type="<?php echo esc_attr($is_variable ? 'variable' : 'simple'); ?>"
		aria-label="<?php esc_attr_e('Add to cart', 'consucorner'); ?>"
		hidden>
		<div class="sp-sticky-atc__inner">
			<div class="sp-sticky-atc__head">
				<p class="sp-sticky-atc__title"><?php echo esc_html($product->get_name()); ?></p>
				<?php if ($is_variable) : ?>
					<div class="sp-sticky-atc__price sp-sticky-atc__price--variable sp-pricing--pending" id="spStickyPrice" aria-live="polite">
						<?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC price HTML. ?>
					</div>
				<?php else : ?>
					<div class="sp-sticky-atc__price" aria-live="polite">
						<?php if ($product->is_on_sale() && $regular_price > 0) : ?>
							<span class="sp-sticky-atc__price-old"><?php echo esc_html((function_exists('cc_format_product_price_amount') ? cc_format_product_price_amount($regular_price) : wc_format_localized_price($regular_price)) . ' ' . $currency); ?></span>
						<?php endif; ?>
						<div class="sp-sticky-atc__price-current">
							<span class="sp-sticky-atc__amount"><?php echo esc_html($sp_sticky_price_fmt); ?></span>
							<span class="sp-sticky-atc__currency"><?php echo esc_html($currency); ?></span>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<div class="sp-sticky-atc__toolbar">
				<div class="sp-sticky-atc__qty sp-qty" role="group" aria-label="<?php esc_attr_e('Quantity', 'consucorner'); ?>">
					<button type="button" class="sp-qty-btn" id="spStickyQtyMinus" aria-label="<?php esc_attr_e('Decrease quantity', 'consucorner'); ?>">−</button>
					<input type="number" class="sp-qty-val qty" id="spStickyQtyVal" value="<?php echo esc_attr($sp_bulk_min_qty > 0 ? $sp_bulk_min_qty : 1); ?>" min="<?php echo esc_attr($sp_bulk_min_qty > 0 ? $sp_bulk_min_qty : 1); ?>" step="1" inputmode="numeric" data-bulk-step="<?php echo esc_attr($sp_bulk_qty_step); ?>" aria-label="<?php esc_attr_e('Quantity', 'consucorner'); ?>" />
					<button type="button" class="sp-qty-btn" id="spStickyQtyPlus" aria-label="<?php esc_attr_e('Increase quantity', 'consucorner'); ?>">+</button>
				</div>
				<a
					href="<?php echo $is_variable ? '#' : $sp_sticky_buy_url; ?>"
					class="sp-sticky-atc__buy sp-btn-buy<?php echo $is_variable ? ' is-disabled' : ''; ?>"
					id="spStickyBuyNow"
					data-checkout="<?php echo esc_url($sp_sticky_checkout); ?>"
					data-product-id="<?php echo esc_attr($product->get_id()); ?>"
					<?php echo $is_variable ? 'aria-disabled="true"' : ''; ?>
					aria-label="<?php esc_attr_e('Buy now', 'consucorner'); ?>">
					<span class="sp-sticky-atc__buy-label sp-sticky-atc__buy-label--full"><?php esc_html_e('Buy now', 'consucorner'); ?></span>
					<span class="sp-sticky-atc__buy-label sp-sticky-atc__buy-label--short"><?php esc_html_e('Buy', 'consucorner'); ?></span>
				</a>
				<button
					type="button"
					class="sp-sticky-atc__cart sp-btn-cart"
					id="spStickyAddCart"
					aria-label="<?php echo esc_attr($product->single_add_to_cart_text()); ?>">
					<svg class="sp-sticky-atc__cart-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<circle cx="9" cy="21" r="1" />
						<circle cx="20" cy="21" r="1" />
						<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
					</svg>
					<span class="sp-sticky-atc__cart-label sp-sticky-atc__cart-label--full"><?php echo esc_html($product->single_add_to_cart_text()); ?></span>
					<span class="sp-sticky-atc__cart-label sp-sticky-atc__cart-label--short"><?php esc_html_e('Add', 'consucorner'); ?></span>
				</button>
			</div>
		</div>
	</aside>
<?php endif; ?>

<?php if (! empty($related_ids)) : ?>
	<!-- ── Often Ordered With ── -->
	<section class="oow-section">
		<div class="oow-inner">
			<div class="oow-header">
				<h2 class="oow-title"><?php esc_html_e('Often Ordered With', 'consucorner'); ?></h2>
				<div class="oow-nav">
					<button class="oow-nav-btn" id="oowPrev" aria-label="<?php esc_attr_e('Previous', 'consucorner'); ?>">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
							<polyline points="15 18 9 12 15 6" />
						</svg>
					</button>
					<button class="oow-nav-btn" id="oowNext" aria-label="<?php esc_attr_e('Next', 'consucorner'); ?>">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
							<polyline points="9 18 15 12 9 6" />
						</svg>
					</button>
				</div>
			</div>
			<div class="oow-viewport" id="oowViewport">
				<div class="oow-track fp-products-grid" id="oowTrack">
					<?php
					foreach ($related_ids as $rid) {
						if (function_exists('cc_render_product_card')) {
							echo cc_render_product_card($rid); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
						}
					}
					?>
				</div>
			</div>
			<div class="oow-nav-bottom">
				<button class="oow-nav-btn" id="oowPrevBottom" aria-label="<?php esc_attr_e('Previous', 'consucorner'); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="15 18 9 12 15 6" />
					</svg>
				</button>
				<button class="oow-nav-btn" id="oowNextBottom" aria-label="<?php esc_attr_e('Next', 'consucorner'); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="9 18 15 12 9 6" />
					</svg>
				</button>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if (! empty($reviews)) : ?>
	<!-- ── Product Testimonials ── -->
	<section class="sp-t-section">
		<div class="sp-t-inner">
			<div class="sp-t-header">
				<div class="sp-t-heading-block">
					<h2 class="sp-t-title"><?php esc_html_e('What Our Customers', 'consucorner'); ?><br /><?php esc_html_e('Say About Us', 'consucorner'); ?></h2>
				</div>
				<div class="sp-t-rating-block">
					<?php
					$avg = (float) $product->get_average_rating();
					$avg = $avg ? $avg : 5;
					?>
					<div class="sp-t-stars" aria-label="<?php echo esc_attr($avg); ?>">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
					<p class="sp-t-rating-text"><?php esc_html_e('Trusted by Healthcare professionals', 'consucorner'); ?></p>
				</div>
			</div>
			<div class="sp-t-wrapper" id="spTWrapper">
				<div class="sp-t-track" id="spTTrack">
					<?php foreach ($reviews as $review) :
						$rating = (int) get_comment_meta($review->comment_ID, 'rating', true);
						if (! $rating) {
							$rating = 5;
						}
						$rating = max(1, min(5, $rating));
						$display_rating = number_format((float) $rating, 1);
					?>
						<div class="sp-t-card">
							<h4 class="sp-t-name"><?php echo esc_html($review->comment_author); ?></h4>
							<p class="sp-t-text"><?php echo esc_html($review->comment_content); ?></p>
							<span class="sp-t-rating">&#9733; <?php echo esc_html(sprintf(__('Rated %s/5', 'consucorner'), $display_rating)); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if (! empty($faqs)) : ?>
	<!-- ── FAQ Accordion ── -->
	<section class="sp-faq-section">
		<div class="sp-faq-inner">
			<div class="sp-faq-container">
				<?php foreach ($faqs as $idx => $faq) :
					$is_open = 0 === $idx;
					$num     = sprintf('%02d', $idx + 1);
				?>
					<div class="sp-faq-item<?php echo $is_open ? esc_attr(' sp-faq-item--open') : ''; ?>">
						<button class="sp-faq-header" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
							<span class="sp-faq-number"><?php echo esc_html($num); ?></span>
							<span class="sp-faq-question"><?php echo esc_html($faq['q']); ?></span>
							<span class="sp-faq-toggle">
								<?php if ($is_open) : ?>
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
										<path d="M1 1L13 13M13 1L1 13" stroke="white" stroke-width="2" stroke-linecap="round" />
									</svg>
								<?php else : ?>
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
										<path d="M7 1V13M1 7H13" stroke="#00C8B3" stroke-width="2" stroke-linecap="round" />
									</svg>
								<?php endif; ?>
							</span>
						</button>
						<div class="sp-faq-body">
							<?php echo wp_kses_post(wpautop($faq['a'])); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if (comments_open() && (get_option('woocommerce_enable_reviews') === 'yes')) : ?>
	<!-- ── Write A Review ── -->
	<section class="sp-review-section">
		<div class="sp-review-inner">
			<h2 class="sp-review-title"><?php esc_html_e('Write A Review', 'consucorner'); ?></h2>
			<p class="sp-review-subtitle"><?php esc_html_e('Share your experience to help others make better decisions.', 'consucorner'); ?></p>

			<?php
			$commenter    = wp_get_current_commenter();
			$comment_form = array(
				'title_reply'         => '',
				'title_reply_to'      => '',
				'comment_notes_before' => '',
				'comment_notes_after'  => '',
				'class_form'          => 'sp-review-form comment-form',
				'comment_field'       => '<label class="sp-review-comment-label" for="comment">' . esc_html__('Leave your comment here', 'consucorner') . '</label>'
					. '<textarea id="comment" name="comment" class="sp-review-textarea" rows="6" required></textarea>',
				'fields'              => array(
					'author' => '<div class="sp-review-contacts-card">'
						. '<p class="sp-review-contacts-label">' . esc_html__('Contacts', 'consucorner') . '</p>'
						. '<p class="sp-review-contacts-hint">' . esc_html__('These contacts are used to inform about reviews', 'consucorner') . '</p>'
						. '<input type="hidden" name="author" value="' . esc_attr($commenter['comment_author'] ?: (is_user_logged_in() ? wp_get_current_user()->display_name : '')) . '" />',
					'email'  => '<div class="sp-review-field">'
						. '<svg class="sp-review-field-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>'
						. '<input id="email" name="email" type="email" class="sp-review-input" placeholder="' . esc_attr__('your email Address', 'consucorner') . '" value="' . esc_attr($commenter['comment_author_email']) . '" required />'
						. '</div>',
					'url'    => '<div class="sp-review-field sp-review-field--last">'
						. '<svg class="sp-review-field-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.77 12 19.79 19.79 0 0 1 1.69 3.35 2 2 0 0 1 3.66 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.69a16 16 0 0 0 6 6l.94-.94a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>'
						. '<input id="url" name="url" type="tel" class="sp-review-input" placeholder="' . esc_attr__('phone number', 'consucorner') . '" />'
						. '</div></div>',
				),
				'label_submit'        => esc_html__('Submit', 'consucorner'),
				'submit_button'       => '<button name="%1$s" type="submit" id="%2$s" class="sp-review-submit %3$s" value="%4$s">' . esc_html__('Submit', 'consucorner')
					. ' <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></button>',
			);
			comment_form($comment_form);
			?>
		</div>
	</section>
<?php endif; ?>

<section>
	<div class="medical-products-banner sp-banner"<?php echo function_exists('consucorner_sp_banner_bg_style_attr') ? consucorner_sp_banner_bg_style_attr() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?> aria-label="<?php esc_attr_e('Promotional banner', 'consucorner'); ?>"></div>
</section>

<?php
do_action('woocommerce_after_single_product');
