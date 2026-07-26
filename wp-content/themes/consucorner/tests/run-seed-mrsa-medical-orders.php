<?php
/**
 * Standalone runner for MRSA Medical demo orders (no WP-CLI required).
 *
 * @package Consucorner
 */

define( 'WP_USE_THEMES', false );

$root = dirname( __DIR__, 4 );
require $root . '/wp-load.php';

require __DIR__ . '/seed-mrsa-medical-orders.php';
