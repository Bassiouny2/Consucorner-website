<?php
/**
 * Customer processing order email — ConsuCorner custom design.
 *
 * @package ConsuCorner
 * @version 1.0.0
 *
 * @var WC_Order $order
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var string   $email_heading
 * @var WC_Email $email
 * @var string   $additional_content
 */

defined( 'ABSPATH' ) || exit;

if ( $plain_text ) {
	consucorner_render_processing_order_email_plain( $order, $additional_content );
	return;
}

consucorner_render_processing_order_email_html( $order, $sent_to_admin, $plain_text, $email, $additional_content );
