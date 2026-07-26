<?php
/**
 * Contact Page – Editable Content Data
 *
 * To edit text from wp-admin: Pages → Contact → "Page Content — Contact"
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

$pid = get_the_ID();
$_m  = function( $key, $default ) use ( $pid ) {
	$v = get_post_meta( $pid, $key, true );
	return ( $v !== '' && $v !== false ) ? $v : $default;
};

$contact_head = array(
	'title'       => $_m( '_cc_contact_head_title',       'Contact Us' ),
	'breadcrumbs' => $_m( '_cc_contact_head_breadcrumbs', 'Home/Contact Us' ),
);

$contact_form = array(
	'title'      => $_m( '_cc_contact_form_title', 'Get in <span>Touch</span>' ),
	'desc'       => $_m( '_cc_contact_form_desc',  "Whether you have a question, need support with an order, or want to request a product you don't see on the site." ),
	'ph_name'    => $_m( '_cc_contact_ph_name',    'Name *' ),
	'ph_email'   => $_m( '_cc_contact_ph_email',   'Email' ),
	'ph_phone'   => $_m( '_cc_contact_ph_phone',   'Phone number *' ),
	'ph_message' => $_m( '_cc_contact_ph_message', 'Your Message' ),
	'btn_text'   => $_m( '_cc_contact_btn_text',   'SEND' ),
	'map_src'    => $_m( '_cc_contact_map_src',
		'https://www.openstreetmap.org/export/embed.html?bbox=31.3180%2C30.0820%2C31.3380%2C30.0960&layer=mapnik&marker=30.0890%2C31.3280' ),
);

$contact_info = array(
	array(
		'icon'  => 'phone-icon.png',
		'label' => $_m( '_cc_contact_phone_label', 'PHONE' ),
		'value' => $_m( '_cc_contact_phone_value', '01555458555' ),
		'href'  => $_m( '_cc_contact_phone_href',  'tel:01555458555' ),
	),
	array(
		'icon'  => 'mail-icon.png',
		'label' => $_m( '_cc_contact_email_label', 'EMAIL' ),
		'value' => $_m( '_cc_contact_email_value', 'info@consucorner.com' ),
		'href'  => $_m( '_cc_contact_email_href',  'mailto:info@consucorner.com' ),
	),
	array(
		'icon'  => 'location-icon.png',
		'label' => $_m( '_cc_contact_loc_label', 'Location' ),
		'value' => $_m( '_cc_contact_loc_value', '7 Obour Buildings, Salah Salem St., Heliopolis Cairo, 4460020, Egypt' ),
		'href'  => '',
	),
);
