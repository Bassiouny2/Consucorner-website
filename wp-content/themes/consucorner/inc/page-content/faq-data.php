<?php
/**
 * FAQ Page - Editable Content Data
 *
 * To edit text from wp-admin: Pages -> FAQ -> "Page Content - FAQ".
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

$pid = get_the_ID();
$_m  = function ( $key, $default ) use ( $pid ) {
	$value = get_post_meta( $pid, $key, true );
	return ( '' !== $value && false !== $value ) ? $value : $default;
};

$faq_head = array(
	'title'       => $_m( '_cc_faq_head_title', __( 'Frequently Asked Questions', 'consucorner' ) ),
	'breadcrumbs' => $_m( '_cc_faq_head_breadcrumbs', __( 'Home/FAQ', 'consucorner' ) ),
);

$faq_intro = array(
	'eyebrow' => $_m( '_cc_faq_intro_eyebrow', __( 'Help Center', 'consucorner' ) ),
	'title'   => $_m( '_cc_faq_intro_title', __( 'Answers For A Smoother Medical Supply Experience', 'consucorner' ) ),
	'text'    => $_m( '_cc_faq_intro_text', __( 'Find quick answers about ordering, vendors, delivery, payments, returns, and using ConsuCorner for your medical purchasing workflow.', 'consucorner' ) ),
);

$faq_cta = array(
	'title'       => $_m( '_cc_faq_cta_title', __( 'Still Need Help?', 'consucorner' ) ),
	'text'        => $_m( '_cc_faq_cta_text', __( 'Our support team can help with product requests, order questions, vendor onboarding, and marketplace guidance.', 'consucorner' ) ),
	'button_text' => $_m( '_cc_faq_cta_button_text', __( 'Contact Support', 'consucorner' ) ),
	'button_url'  => $_m( '_cc_faq_cta_button_url', home_url( '/contact/' ) ),
);

$faq_items = get_post_meta( $pid, '_cc_faq_items', true );

if ( ! is_array( $faq_items ) || empty( $faq_items ) ) {
	$faq_items = array(
		array(
			'question' => __( 'How can I find the right medical product on ConsuCorner?', 'consucorner' ),
			'answer'   => __( 'Use the search bar, browse by specialty, or open the Shop mega menu to filter products by category, specialty, and procedure.', 'consucorner' ),
		),
		array(
			'question' => __( 'Can I order products from multiple suppliers in one place?', 'consucorner' ),
			'answer'   => __( 'Yes. ConsuCorner is designed to bring multiple trusted medical suppliers into one organized marketplace experience.', 'consucorner' ),
		),
		array(
			'question' => __( 'What payment options are available?', 'consucorner' ),
			'answer'   => __( 'Available payment methods may include online payment options and cash on delivery, depending on checkout settings and order eligibility.', 'consucorner' ),
		),
		array(
			'question' => __( 'How do I contact support about an order?', 'consucorner' ),
			'answer'   => __( 'Visit the Contact page and send your order details. Our team will review your request and follow up as soon as possible.', 'consucorner' ),
		),
		array(
			'question' => __( 'Can vendors join ConsuCorner?', 'consucorner' ),
			'answer'   => __( 'Yes. Medical suppliers can apply through the vendor page to start the onboarding process.', 'consucorner' ),
		),
	);
}
