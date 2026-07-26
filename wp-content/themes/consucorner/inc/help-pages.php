<?php
/**
 * Seed editable Help pages from the live ConsuCorner help content.
 *
 * The created pages use Gutenberg block content in post_content, so admins can
 * edit them normally in the WordPress block editor.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return reusable contact/support block.
 *
 * @return string
 */
function consucorner_help_support_block() {
	return <<<HTML
<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">We're here to support</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Just let us know your issue and get full support right away.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/contact/">Contact Support</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
HTML;
}

/**
 * Build editable Gutenberg content for Help landing page.
 *
 * @return string
 */
function consucorner_help_index_content() {
	return <<<HTML
<!-- wp:paragraph -->
<p class="cc-help-hub-intro">Find answers to common questions and detailed policy information through the links below.</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<nav class="cc-help-card-grid" aria-label="Help Center links">
	<a class="cc-help-card" href="/help/return-refund/">
		<span class="cc-help-card-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none"><path d="M4 9a7 7 0 0 1 11.95-4.95L18 6.1V2h2v7h-7V7h3.62l-2.09-2.09A5 5 0 1 0 16 12h2A7 7 0 1 1 4 9Z" fill="currentColor"/></svg>
		</span>
		<span class="cc-help-card-title">Return &amp; Refund Policy</span>
	</a>
	<a class="cc-help-card" href="/help/track-order/">
		<span class="cc-help-card-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none"><path d="M5 4h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-3.18a3 3 0 0 1-5.64 0H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 4v8h5.18a3 3 0 0 1 5.64 0H19V8H5Zm0-2h14V6H5Zm8 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" fill="currentColor"/></svg>
		</span>
		<span class="cc-help-card-title">Track Your Order</span>
	</a>
	<a class="cc-help-card" href="/help/shipping/">
		<span class="cc-help-card-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none"><path d="M3 5h11v9h2.1l2-4H21l2 4v4h-2.18a3 3 0 0 1-5.64 0H9.82a3 3 0 0 1-5.64 0H3V5Zm2 2v9h.18a3 3 0 0 1 5.64 0H12V7H5Zm13.34 5-1 2H21v-.53L20.1 12h-1.76ZM7 19a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm11 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" fill="currentColor"/></svg>
		</span>
		<span class="cc-help-card-title">Shipping &amp; Delivery</span>
	</a>
	<a class="cc-help-card" href="/help/faq/">
		<span class="cc-help-card-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none"><path d="M11 17h2v-2h-2v2Zm1-14a6 6 0 0 0-6 6h2a4 4 0 1 1 6.83 2.83l-1.24 1.25A5.4 5.4 0 0 0 12 17h2a3.4 3.4 0 0 1 1-2.42l1.24-1.24A6 6 0 0 0 12 3Z" fill="currentColor"/></svg>
		</span>
		<span class="cc-help-card-title">Frequently Asked Questions</span>
	</a>
	<a class="cc-help-card" href="/help/terms/">
		<span class="cc-help-card-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none"><path d="M5 3h14v18H5V3Zm2 2v14h10V5H7Zm2 3h6v2H9V8Zm0 4h6v2H9v-2Zm0 4h4v2H9v-2Z" fill="currentColor"/></svg>
		</span>
		<span class="cc-help-card-title">Terms &amp; Conditions</span>
	</a>
	<a class="cc-help-card" href="/help/payments/">
		<span class="cc-help-card-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none"><path d="M3 6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Zm2 2h14V6H5v2Zm0 3v7h14v-7H5Zm2 4h5v2H7v-2Z" fill="currentColor"/></svg>
		</span>
		<span class="cc-help-card-title">Payments &amp; Security</span>
	</a>
	<a class="cc-help-card" href="/help/cookies/">
		<span class="cc-help-card-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none"><path d="M12 2a1 1 0 0 1 1 1 3 3 0 0 0 3 3 1 1 0 0 1 1 1 3 3 0 0 0 3 3 1 1 0 0 1 1 1 9 9 0 1 1-9-9Zm-1 2.07A7 7 0 1 0 18.93 12 5 5 0 0 1 15.1 7.9 5 5 0 0 1 11 4.07ZM8.5 11A1.5 1.5 0 1 1 10 9.5 1.5 1.5 0 0 1 8.5 11Zm4.5 6a1 1 0 1 1 1-1 1 1 0 0 1-1 1Zm-4-2a1 1 0 1 1 1-1 1 1 0 0 1-1 1Z" fill="currentColor"/></svg>
		</span>
		<span class="cc-help-card-title">Cookies Policy</span>
	</a>
	<a class="cc-help-card cc-help-card-contact" href="/contact/">
		<span class="cc-help-card-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24 11.36 11.36 0 0 0 3.57.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.36 11.36 0 0 0 .57 3.57 1 1 0 0 1-.24 1.02l-2.21 2.2Z" fill="currentColor"/></svg>
		</span>
		<span class="cc-help-card-title">Contact Support</span>
	</a>
</nav>
<!-- /wp:html -->
HTML;
}

/**
 * Return child Help page definitions.
 *
 * @return array[]
 */
function consucorner_help_page_definitions() {
	$support = consucorner_help_support_block();

	return array(
		array(
			'title'   => 'Refund and Returns Policy',
			'slug'    => 'return-refund',
			'content' => <<<HTML
<!-- wp:heading -->
<h2 class="wp-block-heading">Can I return a product?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We accept return requests within 14 days of delivery for eligible products under the following conditions:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><!-- wp:list-item -->
<li>The item is unused, unopened, sealed, and in its original packaging.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>The item has not been installed, sterilized, used, or exposed to clinical use.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>The product is not listed as non-returnable due to hygiene, sterility, or safety reasons.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Returns are also accepted if the item is defective, damaged on arrival, incorrectly supplied, or not as described.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p><strong>Opened, used, unsealed, sterile, single-use, or hygiene-sensitive medical products cannot be returned unless the item is defective, damaged on arrival, incorrectly supplied, or not as described.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Important:</strong> Some medical products may be exempt from standard return rights due to their nature, including sterile, sealed, single-use, or hygiene-sensitive items, in accordance with applicable Egyptian consumer protection regulations.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="https://www.cpa.gov.eg/pdf/Law181_2018.pdf" target="_blank" rel="noreferrer noopener">View the official law (PDF)</a></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>If the return is due to an error from our side, such as sending the wrong item, we will cover the return shipping. Otherwise, return shipping is the customer's responsibility.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Items that cannot be returned</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The following products cannot be returned if opened or if the packaging seal is broken:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><!-- wp:list-item -->
<li>Sterile or single-use surgical instruments</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Diagnostic kits</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Disposable medical supplies, such as syringes, gloves, and gauze</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Any product marked non-returnable on the product page</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Inspect your order upon delivery</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>You must inspect the shipping box and outer packaging in the presence of the courier before signing for delivery.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>If the package appears damaged, opened, unsealed, incorrect, or missing quantities, refuse the shipment and ask the courier to return it. Then immediately contact our support team with your order number and photos.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>We cannot honor refund requests once the package is signed as received in good condition.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Refund process</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul><!-- wp:list-item -->
<li>Refunds are issued to the original payment method used at checkout.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>If you paid Cash on Delivery, the refund will be processed via bank transfer or mobile wallet, such as Vodafone Cash.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Our team will contact you to collect refund details after inspecting the returned item.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Refunds are processed within 7-14 business days depending on your bank or mobile provider.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Please note that shipping charges and payment processing fees are non-refundable.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Payment disputes and chargebacks</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We encourage you to contact our support team before initiating any dispute or chargeback with your bank. We are committed to resolving all issues quickly and fairly.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Customer responsibility</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Customers are expected to review all product descriptions and specifications before placing an order and ensure the item is appropriate for its intended medical or clinical use.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Opened or used items cannot be returned, even if unused after opening, due to hygiene regulations.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Shipping delays</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>ConsuCorner is not responsible for shipping delays caused by courier services. However, our team is available to assist in resolving delivery issues.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">How to request a return</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>To request a return, please contact our support team and include:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><!-- wp:list-item -->
<li>Order number</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Photos of the item and original packaging</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Reason for return</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Delivery date</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Return requests must be submitted within 14 days of delivery for eligible products, subject to the return conditions stated on this page.</p>
<!-- /wp:paragraph -->

$support
HTML
		),
		array(
			'title'   => 'Track Your Order',
			'slug'    => 'track-order',
			'content' => <<<HTML
<!-- wp:heading -->
<h2 class="wp-block-heading">Order Tracking Information</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Once your order has been shipped, you will receive a tracking number via email or SMS. This tracking number allows you to monitor the status of your shipment directly.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">How to Track Your Shipment</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol><!-- wp:list-item -->
<li>Locate your tracking number in the email or SMS we sent.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Visit the Bosta Tracking Page.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Enter the tracking number in the appropriate field to view your shipment status.</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">What If I Didn't Receive a Tracking Number?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If you have not received your tracking number within 2 business days of placing your order, please contact our support team.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Need Help with a Delayed Shipment?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If your order has not arrived on time or the tracking number does not work, please get in touch with us and we will follow up with the courier on your behalf.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>For policies and detailed information, you may also visit our <a href="/help/return-refund/">Return &amp; Refund Policy</a>, <a href="/help/shipping/">Shipping &amp; Delivery</a>, <a href="/help/payments/">Payments &amp; Security</a>, and <a href="/help/terms/">Terms &amp; Conditions</a> pages.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Track your order</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>To track your order, please enter your Order ID and billing email in the box below and press the Track button. These details were given to you on your receipt and in the confirmation email.</p>
<!-- /wp:paragraph -->

<!-- wp:shortcode -->
[woocommerce_order_tracking]
<!-- /wp:shortcode -->

$support
HTML
		),
		array(
			'title'   => 'Shipping & Delivery',
			'slug'    => 'shipping',
			'content' => <<<HTML
<!-- wp:heading -->
<h2 class="wp-block-heading">Delivery Coverage</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>ConsuCorner currently delivers to Cairo, Giza, Alexandria, and most major cities across Egypt. We are actively working to extend our delivery network to reach all governorates.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>If you are unsure whether your area is covered, please check with our support team via our Contact &amp; Support page before placing your order.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Delivery Timelines</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul><!-- wp:list-item -->
<li><strong>Cairo &amp; Giza:</strong> 1-3 working days</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>Other major cities:</strong> 3-5 working days</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>Remote areas:</strong> Delivery times may vary based on courier availability</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Once your order has been shipped, you will receive tracking details by email or SMS. You can follow the progress of your shipment from our <a href="/help/track-order/">Order Tracking</a> page.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Shipping Fees</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Shipping charges are automatically calculated during checkout based on your delivery location and the total weight or volume of your order. You will see the exact shipping cost before confirming your purchase.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>We strive to keep our delivery rates affordable and transparent. Occasional free shipping offers and promotions may apply - stay connected via our newsletter and social channels.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Courier Partners</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>All shipments are handled by trusted third-party courier partners. Orders are packed securely to preserve the safety and hygiene of medical supplies during transit.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Inspection Upon Delivery</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We strongly advise all customers to inspect their order at the time of delivery, while the courier is present.</p>
<!-- /wp:paragraph -->

<!-- wp:list {"ordered":true} -->
<ol><!-- wp:list-item -->
<li>If the shipment appears damaged, opened, or incorrect, refuse the package.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Ask the courier to return it.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Email us immediately with your order number and photos of the issue.</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p><strong>Please note:</strong> We are unable to process claims for damage or item discrepancies once the package has been accepted and signed for as received in good condition.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Address Modifications</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If your order was placed within the last few hours and has not yet been shipped, we may be able to update the delivery address. We recommend submitting address change requests within 2 hours of placing your order for the best chance of successful modification.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Once the shipment is processed, changes may no longer be possible.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Unexpected Delays</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Although we aim to ensure timely deliveries, occasional delays may occur due to courier issues, weather disruptions, public holidays, or incorrect address details.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>If your order has not arrived within the expected delivery window, please contact our support team. We will follow up with the courier on your behalf to investigate and resolve the issue.</p>
<!-- /wp:paragraph -->

$support
HTML
		),
		array(
			'title'   => 'Frequently Asked Questions (FAQ)',
			'slug'    => 'faq',
			'content' => <<<HTML
<!-- wp:heading -->
<h2 class="wp-block-heading">Orders</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">How do I place an order?</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>You can place an order by browsing our product catalog, adding items to your cart, and proceeding to checkout. You will receive a confirmation email once your order is submitted.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">Can I change or cancel my order?</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If your order has not been shipped yet, please contact us immediately. Once shipped, changes may not be possible. See more in our Terms &amp; Conditions.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Shipping &amp; Delivery</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">Where do you deliver?</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We currently deliver to Cairo, Giza, Alexandria, and other major cities in Egypt. For full details, visit our <a href="/help/shipping/">Shipping &amp; Delivery</a> page.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">How long does delivery take?</h4>
<!-- /wp:heading -->

<!-- wp:list -->
<ul><!-- wp:list-item -->
<li><strong>Cairo &amp; Giza:</strong> 1-3 business days</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>Other cities:</strong> 3-5 business days</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">How much does shipping cost?</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Shipping fees are calculated based on location and weight. You will see the exact fee at checkout.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Payments</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">What payment methods are accepted?</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We accept Cash on Delivery and secure online card payments. Learn more on our <a href="/help/payments/">Payments &amp; Security</a> page.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">Is my payment information secure?</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Yes. Online payments are handled through a certified secure payment gateway. We do not store your card information. See more under our Privacy Policy.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Returns &amp; Refunds</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">Can I return a product?</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We accept returns on eligible unused and unopened products under the conditions explained in our <a href="/help/return-refund/">Return &amp; Refund Policy</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">How will I receive my refund?</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Refunds are issued to the original payment method where possible. Cash refunds may take up to 14 business days depending on your bank or courier process.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Accounts &amp; Support</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">Do I need an account to place an order?</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>No, but creating an account allows you to track your orders and manage your preferences. Your data is handled securely as explained in our Privacy Policy.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">How can I contact support?</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>You can reach our support team Sunday to Thursday, 9:00 AM - 5:00 PM Cairo time.</p>
<!-- /wp:paragraph -->

$support
HTML
		),
		array(
			'title'   => 'Terms and Conditions',
			'slug'    => 'terms',
			'content' => <<<HTML
<!-- wp:heading -->
<h2 class="wp-block-heading">1. Introduction</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Welcome to www.consucorner.com. By accessing or using our website, you agree to be legally bound by these Terms and Conditions. If you do not accept these terms, please refrain from using our services.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Use of the Website</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>You must be at least 18 years old to place an order. By using this website, you agree not to violate applicable laws, infringe upon the rights of others, or attempt to hack, alter, or misuse the site.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>All content, trademarks, and product data are the intellectual property of ConsuCorner.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Products and Orders</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>All product descriptions, specifications, and images are provided for informational purposes only and may differ slightly from the actual items.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>We reserve the right to cancel or limit any order and refuse service to anyone at our discretion. Please ensure you review all product details before purchasing. By placing an order, you agree to our <a href="/help/return-refund/">Return &amp; Refund Policy</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Pricing and Payment</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Prices listed on the website are in Egyptian Pounds (EGP) and are subject to change without prior notice.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><!-- wp:list-item -->
<li>Cash on Delivery (COD)</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Online payment via supported payment gateways, including cards and eligible wallets</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>For details, visit our <a href="/help/payments/">Payments &amp; Security</a> page.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. Shipping and Delivery</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We partner with trusted couriers to deliver your order securely and on time. Estimated delivery times vary based on location. You are responsible for providing accurate shipping information. Read full details on our <a href="/help/shipping/">Shipping &amp; Delivery</a> page.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. Returns and Refunds</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Return requests are subject to product eligibility and timing. Due to the nature of medical consumables, not all items are returnable. Please read our full <a href="/help/return-refund/">Return &amp; Refund Policy</a> before ordering.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">7. Limitation of Liability</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We are not liable for indirect or incidental damages, misuse of products by the customer, or delays caused by third-party delivery providers. All items should be inspected upon delivery.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">8. Privacy</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We are committed to protecting your personal data. Please review our Privacy Policy to understand how we collect, store, and use your information.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">9. Modifications to the Terms</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We may update these Terms and Conditions at any time. Changes will be posted on this page. Continued use of the website indicates your acceptance of any modifications.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">10. Governing Law</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>These Terms are governed by the laws of the Arab Republic of Egypt. For more on your rights as a customer, you may refer to the <a href="https://www.cpa.gov.eg/pdf/Law181_2018.pdf" target="_blank" rel="noreferrer noopener">Egyptian Consumer Protection Law (PDF)</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">11. Promotions and Discounts</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Promotional offers are valid for a limited time and may be withdrawn without notice. Only one promo code may be used per order unless stated otherwise. Discounts cannot be exchanged for cash or applied to past purchases. Refunds for discounted orders will reflect the discounted amount.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">12. User Accounts</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If you create an account on ConsuCorner, you agree to provide accurate and up-to-date personal information, keep your login credentials confidential, and be fully responsible for any activity under your account.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>We reserve the right to suspend or delete accounts that misuse the service or violate any terms.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">13. Copyright &amp; Intellectual Property</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>All content on this website, including product descriptions, images, videos, and design, is the intellectual property of ConsuCorner or its licensors. You may not reproduce, redistribute, or repurpose any part of the website without written permission.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">14. Payment Policy</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>By using this site or placing an order, you confirm that you have read, understood, and agreed to these Terms and all related policies including Return &amp; Refund Policy, Shipping &amp; Delivery, Payments &amp; Security, and Privacy Policy.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">15. Contact Us</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Need help? Contact our support team anytime from the Contact page.</p>
<!-- /wp:paragraph -->

$support
HTML
		),
		array(
			'title'   => 'Payments & Security',
			'slug'    => 'payments',
			'content' => <<<HTML
<!-- wp:heading -->
<h2 class="wp-block-heading">1. Accepted Payment Methods</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We currently accept the following payment options for orders placed through www.consucorner.com:</p>
<!-- /wp:paragraph -->

<!-- wp:list {"ordered":true} -->
<ol><!-- wp:list-item -->
<li><strong>Cash on Delivery (COD)</strong> - available for most orders within Cairo, Giza, and major cities. Please ensure someone is available to receive the delivery and pay in full upon arrival.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>Secure Online Payment via Geidea</strong> - Visa &amp; MasterCard, Meeza cards, and supported mobile wallets such as Vodafone Cash, Orange Money, Etisalat Cash, and similar wallets.</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>We are committed to expanding our payment options to make your shopping experience as flexible and convenient as possible.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Unsupported Payment Methods</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Please note that InstaPay transfers and bank transfers are not currently accepted. If new methods are added in the future, they will be clearly announced on this page.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Is it safe to pay online?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Absolutely. All online payments on ConsuCorner are securely processed via Geidea, a licensed and PCI-DSS compliant payment gateway.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><!-- wp:list-item -->
<li>Your card details and payment information are encrypted and never stored on our servers.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>You will receive an email confirmation immediately after a successful transaction.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>You can always choose Cash on Delivery for extra peace of mind.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Payment Confirmation &amp; Receipts</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Once your payment is completed, you will receive a digital receipt via email. This receipt will include your order number, transaction reference, and the amount paid.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>You can also access your order history and invoices from your account dashboard if you created an account during checkout.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. What if my payment fails?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If your online payment fails or is declined, try the following:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><!-- wp:list-item -->
<li>Make sure your card has sufficient funds.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Ensure the card is enabled for online and international use.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Retry using a different payment method, card, wallet, or COD.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>If the issue persists, please contact our support team for help.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. Disputes &amp; Refunds</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If you believe a payment was made in error, or if you did not authorize a transaction, please contact us immediately before initiating a dispute with your bank.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Our support team will investigate and resolve the issue promptly. For refund policies, please visit our <a href="/help/return-refund/">Return &amp; Refund Policy</a>.</p>
<!-- /wp:paragraph -->

$support
HTML
		),
		array(
			'title'   => 'Cookies Policy',
			'slug'    => 'cookies',
			'content' => <<<HTML
<!-- wp:heading -->
<h2 class="wp-block-heading">1. What are cookies?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Cookies are small text files stored on your device when you visit a website. They help the site remember useful information, improve performance, and provide a smoother shopping experience.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. How ConsuCorner uses cookies</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>ConsuCorner may use cookies and similar technologies to support core website functionality, improve the shopping experience, understand site performance, and keep account, cart, checkout, and security features working correctly.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><!-- wp:list-item -->
<li><strong>Essential cookies:</strong> Required for login, cart, checkout, security, and basic website operation.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>Performance cookies:</strong> Help us understand how visitors use the website so we can improve speed, layout, and content.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>Preference cookies:</strong> Remember choices such as account, language, or display preferences where available.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>Marketing cookies:</strong> May help measure campaigns or show more relevant content, where enabled.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Third-party services</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Some cookies may be set by trusted third-party services used for payments, analytics, security, embedded content, or advertising. These providers process information according to their own policies.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Managing cookies</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>You can manage or delete cookies through your browser settings. Blocking some cookies may affect website features such as login, cart, checkout, saved preferences, or order tracking.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. Updates to this policy</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We may update this Cookies Policy from time to time to reflect changes in our website, technology, or legal requirements. Changes will be posted on this page.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. Contact us</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If you have questions about this Cookies Policy, please contact our support team.</p>
<!-- /wp:paragraph -->

$support
HTML
		),
	);
}

/**
 * Create or update Help pages once per content version.
 *
 * @return void
 */
function consucorner_setup_help_pages() {
	$version = '20260506-help-cookies';
	if ( get_option( 'consucorner_help_pages_seeded_version' ) === $version ) {
		return;
	}

	$help_page = get_page_by_path( 'help', OBJECT, 'page' );
	if ( $help_page ) {
		$help_id = (int) $help_page->ID;
		wp_update_post(
			array(
				'ID'           => $help_id,
				'post_title'   => 'Help Center',
				'post_name'    => 'help',
				'post_status'  => 'publish',
				'post_content' => consucorner_help_index_content(),
			)
		);
	} else {
		$help_id = wp_insert_post(
			array(
				'post_title'   => 'Help Center',
				'post_name'    => 'help',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => consucorner_help_index_content(),
				'post_author'  => 1,
			)
		);

		if ( is_wp_error( $help_id ) ) {
			return;
		}
	}

	update_post_meta( $help_id, '_wp_page_template', 'page-help.php' );

	foreach ( consucorner_help_page_definitions() as $page ) {
		$existing = get_page_by_path( 'help/' . $page['slug'], OBJECT, 'page' );
		if ( $existing ) {
			$page_id = wp_update_post(
				array(
					'ID'          => (int) $existing->ID,
					'post_title'  => $page['title'],
					'post_name'   => $page['slug'],
					'post_status' => 'publish',
					'post_parent' => $help_id,
				),
				true
			);
		} else {
			$page_id = wp_insert_post(
				array(
					'post_title'   => $page['title'],
					'post_name'    => $page['slug'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_parent'  => $help_id,
					'post_content' => $page['content'],
					'post_author'  => 1,
				),
				true
			);
		}

		if ( is_wp_error( $page_id ) ) {
			continue;
		}

		update_post_meta( $page_id, '_wp_page_template', 'page-help.php' );
	}

	update_option( 'consucorner_help_pages_seeded_version', $version );
}
add_action( 'after_setup_theme', 'consucorner_setup_help_pages' );
