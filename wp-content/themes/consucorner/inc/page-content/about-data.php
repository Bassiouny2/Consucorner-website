<?php
/**
 * About Page – Editable Content Data
 *
 * Default values live here as fallbacks.
 * To override any text without touching PHP, go to:
 *   WordPress Admin → Pages → About → "Page Content — About" meta box
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/* Helper: read post meta, fall back to $default if empty. */
$pid = get_the_ID();
$_m  = function( $key, $default ) use ( $pid ) {
	$v = get_post_meta( $pid, $key, true );
	return ( $v !== '' && $v !== false ) ? $v : $default;
};

/* ── Page Head ────────────────────────────────────────────── */
$about_head = array(
	'title'       => $_m( '_cc_about_head_title',       'About Consucorner' ),
	'breadcrumbs' => $_m( '_cc_about_head_breadcrumbs', 'Home/About' ),
);

/* ── About Us Section ──────────────────────────────────────── */
$about_us = array(
	'tag'        => $_m( '_cc_about_us_tag',   'About Us' ),
	'title'      => $_m( '_cc_about_us_title', 'CONSU<span>CORNER</span>' ),
	'paragraphs' => array(
		$_m( '_cc_about_us_text_1', "Our Goal is to make ConsuCorner the medical hub for professionals across every specialty — offering clarity, speed, and trusted tools in one organized platform." ),
		$_m( '_cc_about_us_text_2', "We're building ConsuCorner to become a multi-specialty platform, covering a full range of medical fields — from ophthalmology to dental, ENT, and beyond." ),
		$_m( '_cc_about_us_text_3', "Each specialty will be launched with the same care, organization, and commitment to compliance that define our current ophthalmology offerings." ),
		$_m( '_cc_about_us_text_4', "Today, ConsuCorner is your go-to destination for trusted eye care tools — from surgical instruments and diagnostic devices to sterile consumables that are all carefully selected and clearly presented for professionals who don't have time to waste." ),
	),
);

/* ── What Makes Us Different ─────────────────────────────── */
$diff_defaults = array(
	1 => array( 'Professionalism',       "We serve healthcare professionals with care, respect, and a clear understanding of their daily needs.",                                                                                      'professionalism-icon.png' ),
	2 => array( 'Trust',                 "We only offer products we'd trust ourselves — reliable, compliant, and carefully chosen to support real-world medical work.",                                                                'trust-icon.png' ),
	3 => array( 'Clarity',               "We keep things simple and clear. No clutter, no confusion — just well-organized tools and supplies that speak for themselves.",                                                             'clarity-icon.png' ),
	4 => array( 'Speed',                 "We respect your time. Our platform is built to help you find what you need quickly, so you can focus on what really matters — your patients.",                                             'speed-icon.png' ),
	5 => array( 'Integrity & Compliance','We do things the right way, every time. Each product and specialty is added with full attention to medical standards and ethical practices.',                                                'integrity-icon.png' ),
	6 => array( 'Specialty-Focused',     'We grow with purpose — building spaces that truly meet the needs of each medical field, one specialty at a time.',                                                                         'specialty-icon.png' ),
);

$about_diff = array(
	'tag'   => $_m( '_cc_about_diff_tag',   'Why choose us' ),
	'title' => $_m( '_cc_about_diff_title', 'What Makes Us <span>Different</span>' ),
	'cards' => array_map( function( $n, $d ) use ( $_m ) {
		return array(
			'icon'  => $_m( "_cc_about_diff_{$n}_icon",  $d[2] ),
			'title' => $_m( "_cc_about_diff_{$n}_title", $d[0] ),
			'desc'  => $_m( "_cc_about_diff_{$n}_desc",  $d[1] ),
		);
	}, array_keys( $diff_defaults ), array_values( $diff_defaults ) ),
);

/* ── Mission & Vision ─────────────────────────────────────── */
$about_mv = array(
	'mission' => array(
		'title' => $_m( '_cc_about_mission_title', '<span>Our</span> Mission' ),
		'text'  => $_m( '_cc_about_mission_text',  'To simplify the medical supply process for healthcare providers across Egypt — offering a trusted platform with reliable brands, organized ordering system, and all the products they need in one marketplace.' ),
	),
	'vision' => array(
		'title' => $_m( '_cc_about_vision_title', '<span>Our</span> Vision' ),
		'text'  => $_m( '_cc_about_vision_text',  'To build a trusted medical hub where finding, ordering, and receiving supplies is always simple, smooth, and within reach.' ),
	),
);

/* ── Core Values ─────────────────────────────────────────── */
$cv_defaults = array(
	1 => array( 'Organized Access',  'All your medical tools in one reliable platform — sorted, searchable, and updated.',        'organized-icon.png' ),
	2 => array( 'Verified Quality',  'We only work with licensed, verified suppliers. Every product is traceable and certified.', 'verified-icon.png' ),
	3 => array( 'Smooth Operations', 'All your medical tools in one reliable platform — sorted, searchable, and updated.',        'smooth-icon.png' ),
	4 => array( 'Compliance-Ready',  'We only work with licensed, verified suppliers. Every product is traceable and certified.', 'compliance-icon.png' ),
);

$about_values = array(
	'title'    => $_m( '_cc_about_cv_title',    '<span>Our Core</span> Values' ),
	'subtitle' => $_m( '_cc_about_cv_subtitle', 'ConsuCorner is built on four values that guide everything we do:' ),
	'cards'    => array_map( function( $n, $d ) use ( $_m ) {
		return array(
			'icon'  => $_m( "_cc_about_cv_{$n}_icon",  $d[2] ),
			'title' => $_m( "_cc_about_cv_{$n}_title", $d[0] ),
			'desc'  => $_m( "_cc_about_cv_{$n}_desc",  $d[1] ),
		);
	}, array_keys( $cv_defaults ), array_values( $cv_defaults ) ),
);
