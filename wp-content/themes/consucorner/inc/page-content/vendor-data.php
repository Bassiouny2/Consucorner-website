<?php
/**
 * Vendor Page – Editable Content Data
 *
 * To edit text from wp-admin: Pages → Vendor → "Page Content — Vendor"
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

$pid = get_the_ID();
$_m  = function( $key, $default ) use ( $pid ) {
	$v = get_post_meta( $pid, $key, true );
	return ( $v !== '' && $v !== false ) ? $v : $default;
};

/* ── Hero ──────────────────────────────────────────────────── */
$vendor_hero = array(
	'title'      => $_m( '_cc_vendor_hero_title',    'All on ConsuCorner Marketing, Delivery, and Collections' ),
	'subtitle'   => $_m( '_cc_vendor_hero_subtitle', 'Your gateway to doctors, clinics, and medical centers online.' ),
	'desc'       => $_m( '_cc_vendor_hero_desc',     "List your products, we manage operations. From listing to secure payment – we simplify every step. Join as a vendor and expand your reach across Egypt's leading medical marketplace." ),
	'form_title' => $_m( '_cc_vendor_form_title',    'Become a vendor <span>→</span>' ),
);

/* ── Why Join ──────────────────────────────────────────────── */
$why_defaults = array(
	1 => array( 'Reach more customers', 'We serve healthcare professionals with care, respect, and a clear understanding of their daily needs.',                                                                                             'reach-icon.png' ),
	2 => array( 'Earn more money',       "We'll help you serve more medical professionals and clinics without expanding your physical store — and we'll make sure you get paid promptly and securely.",                                    'earn-icon.png' ),
	3 => array( 'Grow your business',    'Increase your sales, connect with more healthcare buyers, and showcase your products more effectively. At ConsuCorner, we provide the tools to grow your business — because your success drives ours.', 'grow-icon.png' ),
);

$vendor_why = array(
	'tag'   => $_m( '_cc_vendor_why_tag',   'Your gateway to doctors, clinics, and medical centers online.' ),
	'title' => $_m( '_cc_vendor_why_title', 'Why Join ConsuCorner as a <span>Vendor?</span>' ),
	'desc'  => $_m( '_cc_vendor_why_desc',  "With ConsuCorner, you can expand your business reach and connect with a growing network of buyers in the medical supplies and equipment field. We help you boost sales, manage orders with ease, and ensure fast and secure payouts. Your success starts here—because our growth is tied to yours." ),
	'items' => array_map( function( $n, $d ) use ( $_m ) {
		return array(
			'icon'  => $_m( "_cc_vendor_why_{$n}_icon",  $d[2] ),
			'title' => $_m( "_cc_vendor_why_{$n}_title", $d[0] ),
			'desc'  => $_m( "_cc_vendor_why_{$n}_desc",  $d[1] ),
		);
	}, array_keys( $why_defaults ), array_values( $why_defaults ) ),
);

/* ── Brochure ──────────────────────────────────────────────── */
$vendor_brochure = array(
	'title'    => $_m( '_cc_vendor_brochure_title',    'Partners brochure' ),
	'btn_text' => $_m( '_cc_vendor_brochure_btn_text', 'DOWNLOAD' ),
	'btn_link' => $_m( '_cc_vendor_brochure_btn_link', '#' ),
);

/* ── How We Collaborate ────────────────────────────────────── */
$step_defaults = array(
	1 => array( '1', 'List Your Products',    'Add your items with clear prices and details through your vendor dashboard.' ),
	2 => array( '2', 'We Do the Marketing',   'Your products are promoted on ConsuCorner to the right audience — free of charge.' ),
	3 => array( '3', 'We Handle Delivery',    'Orders are shipped to customers and payments are collected securely.' ),
	4 => array( '4', 'Receive Your Earnings', 'Your balance is transferred directly to you after commission deduction.' ),
);

$vendor_collab = array(
	'tag'   => $_m( '_cc_vendor_collab_tag',   'A simple, seamless process that connects your products to the right buyers.' ),
	'title' => $_m( '_cc_vendor_collab_title', 'How We Collaborate with <span>Our Vendors</span>' ),
	'desc'  => $_m( '_cc_vendor_collab_desc',  "From listing your medical products on ConsuCorner to receiving secure payments, we streamline every step. Customers place their orders, you prepare them, and our logistics partners handle the delivery — while you monitor sales and growth through your vendor dashboard." ),
	'steps' => array_map( function( $n, $d ) use ( $_m ) {
		return array(
			'num'   => $_m( "_cc_vendor_step_{$n}_num",   $d[0] ),
			'title' => $_m( "_cc_vendor_step_{$n}_title", $d[1] ),
			'desc'  => $_m( "_cc_vendor_step_{$n}_desc",  $d[2] ),
		);
	}, array_keys( $step_defaults ), array_values( $step_defaults ) ),
);

/* ── FAQ ───────────────────────────────────────────────────── */
$faq_defaults = array(
	1 => array( 'What is intraocular lens (IOL) implantation?',       'IOL implantation is a surgical procedure performed after cataract removal or extraction of a damaged natural lens, where a transparent artificial lens is implanted to restore clear vision.' ),
	2 => array( 'Is IOL implantation only used for cataract surgery?', 'No, IOL implantation is not limited to cataract surgery. It is also used in refractive lens exchange procedures to correct vision problems such as severe myopia, hyperopia, or presbyopia.' ),
	3 => array( 'What instruments are required for IOL implantation?', 'The procedure requires a phacoemulsification system, IOL injector and cartridge, micro-incision knives, viscoelastic agents, irrigation/aspiration handpieces, and a microscope for precision surgical visualization.' ),
	4 => array( 'Why is an injector and cartridge system important?',  'The injector and cartridge system allows the surgeon to fold and insert the IOL through a very small incision, minimizing trauma to the eye, reducing recovery time, and lowering the risk of complications.' ),
);

$vendor_faq = array(
	'title' => $_m( '_cc_vendor_faq_title', "Questions? <span>We've got answers</span>" ),
	'items' => array(),
);

$saved_vendor_faqs = metadata_exists( 'post', $pid, '_cc_vendor_faq_items' )
	? get_post_meta( $pid, '_cc_vendor_faq_items', true )
	: null;

if ( is_array( $saved_vendor_faqs ) ) {
	foreach ( $saved_vendor_faqs as $faq ) {
		$question = isset( $faq['question'] ) ? $faq['question'] : '';
		$answer   = isset( $faq['answer'] ) ? $faq['answer'] : '';

		if ( '' === $question && '' === trim( wp_strip_all_tags( $answer ) ) ) {
			continue;
		}

		$vendor_faq['items'][] = array(
			'q' => $question,
			'a' => $answer,
		);
	}
} else {
	$vendor_faq['items'] = array_map( function( $n, $d ) use ( $_m ) {
		return array(
			'q' => $_m( "_cc_vendor_faq_{$n}_q", $d[0] ),
			'a' => $_m( "_cc_vendor_faq_{$n}_a", $d[1] ),
		);
	}, array_keys( $faq_defaults ), array_values( $faq_defaults ) );
}
