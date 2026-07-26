<?php
/**
 * Customer processing order email (plain text).
 *
 * @package ConsuCorner
 * @version 1.0.0
 *
 * @var WC_Order $order
 * @var string   $additional_content
 */

defined( 'ABSPATH' ) || exit;

consucorner_render_processing_order_email_plain( $order, $additional_content );
