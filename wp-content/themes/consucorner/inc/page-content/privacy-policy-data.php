<?php
/**
 * Privacy Policy Page – Editable Content Data
 *
 * To edit text from wp-admin: Pages → Privacy Policy → "Page Content — Privacy Policy"
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

$pid = get_the_ID();
$_m  = function( $key, $default ) use ( $pid ) {
	$v = get_post_meta( $pid, $key, true );
	return ( $v !== '' && $v !== false ) ? $v : $default;
};

$pp_head = array(
	'title'       => $_m( '_cc_pp_head_title',       'Privacy &amp; Policy' ),
	'breadcrumbs' => $_m( '_cc_pp_head_breadcrumbs', 'Home / Privacy &amp; Policy' ),
);

/* Policy sections — default titles and HTML content */
$pp_section_defaults = array(
	1  => array(
		'title'   => '1. Introduction',
		'content' => 'ConsuCorner respects your privacy and is committed to protecting your personal data. This Privacy Policy explains what information we collect, how we use it, and the measures we take to keep it safe. By using our website, you agree to this policy.',
	),
	2  => array(
		'title'   => '2. What Information We Collect',
		'content' => '<p>We may collect the following types of information:</p><ul class="pp-list"><li><strong>Personal information</strong> (such as your name, email address, phone number, and shipping address) when you place an order or create an account.</li><li><strong>Order details, payment method</strong> (e.g., cash or online) and transaction reference. We do not store or have access to your full credit/debit card information, as all online payments are processed securely via Paymob.</li><li><strong>Device and browser data</strong> (such as your IP address and device type), collected for analytics and security purposes.</li><li><strong>Marketing interaction indicators</strong> (e.g., open rates, click rates) based on your activity on our website or in our emails.</li></ul>',
	),
	3  => array(
		'title'   => '3. How We Use Your Information',
		'content' => '<p>We use your information to:</p><ul class="pp-list"><li>Process and fulfill your orders</li><li>Communicate with you about your order or inquiries</li><li>Improve our website and customer service</li><li>Comply with legal obligations</li></ul>',
	),
	4  => array(
		'title'   => '4. Sharing Your Information',
		'content' => '<p>We do not sell or rent your personal data. We may share your information with:</p><ul class="pp-list"><li>Trusted third-party service providers (e.g., couriers, payment processors)</li><li>Legal or regulatory bodies if required by law</li></ul>',
	),
	5  => array(
		'title'   => '5. Data Security',
		'content' => 'We implement a variety of technical and organizational measures to protect your personal information, including encryption, secure servers, and limited access to sensitive data.',
	),
	6  => array(
		'title'   => '6. Your Rights',
		'content' => '<p>You have the right to:</p><ul class="pp-list"><li>Access the personal data we hold about you</li><li>Correct inaccuracies in your data</li><li>Request deletion of your data</li><li>Opt out of marketing communications</li></ul>',
	),
	7  => array(
		'title'   => '7. Cookies and Tracking',
		'content' => 'We use cookies to enhance your browsing experience, personalize content, and analyze website traffic. You can control cookie settings in your browser preferences.',
	),
	8  => array(
		'title'   => '8. Retention of Data',
		'content' => 'We retain your personal data only as long as necessary for the purposes outlined in this policy or as required by law.',
	),
	9  => array(
		'title'   => '9. Changes to This Policy',
		'content' => 'We may update this Privacy Policy from time to time. Changes will be posted on this page and the revised date will be updated accordingly.',
	),
	10 => array(
		'title'   => '10. Contact Us',
		'content' => 'If you have any questions or concerns about this Privacy Policy, please contact us at:<br><strong>Email:</strong> <a class="pp-link" href="mailto:support@consucorner.com">support@consucorner.com</a>',
	),
);

$pp_sections = array_map( function( $n, $d ) use ( $_m ) {
	return array(
		'title'   => $_m( "_cc_pp_s{$n}_title",   $d['title'] ),
		'content' => $_m( "_cc_pp_s{$n}_content", $d['content'] ),
	);
}, array_keys( $pp_section_defaults ), array_values( $pp_section_defaults ) );

/* Sidebar promo banner slides */
$pp_banner_defaults = array(
	1 => array( 'Future is here',   'Shop Now With<br>Premium<br>Quality',         'Shop Now',    get_home_url( null, '/shop' ) ),
	2 => array( 'Fast Delivery',    'Explore Global<br>Brands at<br>Your Doorstep', 'Shop Brands', get_home_url( null, '/shop' ) ),
	3 => array( 'Secure Payments',  'Reliable Checkouts<br>For Every<br>Order',    'Order Now',   get_home_url( null, '/shop' ) ),
);

$pp_banners = array_map( function( $n, $d ) use ( $_m ) {
	return array(
		'tag'      => $_m( "_cc_pp_b{$n}_tag",      $d[0] ),
		'title'    => $_m( "_cc_pp_b{$n}_title",    $d[1] ),
		'btn_text' => $_m( "_cc_pp_b{$n}_btn_text", $d[2] ),
		'btn_link' => $_m( "_cc_pp_b{$n}_btn_link", $d[3] ),
	);
}, array_keys( $pp_banner_defaults ), array_values( $pp_banner_defaults ) );
