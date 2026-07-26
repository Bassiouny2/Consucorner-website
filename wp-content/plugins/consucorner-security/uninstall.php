<?php
/**
 * Uninstall handler: drop tables, remove options and transients.
 *
 * @package Consucorner_Security
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop plugin tables.
$tables = array(
	$wpdb->prefix . 'ccs_logs',
	$wpdb->prefix . 'ccs_blocked_ips',
	$wpdb->prefix . 'ccs_whitelist_ips',
);
foreach ( $tables as $t ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$t}" ); // phpcs:ignore WordPress.DB
}

// Remove all plugin options.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE 'ccs_option_%'
		OR option_name = 'ccs_plugin_version'
		OR option_name = 'ccs_logs_schema_version'"
);

// Remove all plugin transients.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_ccs_%'
		OR option_name LIKE '_transient_timeout_ccs_%'"
);

// Unschedule any cron hooks.
$timestamp = wp_next_scheduled( 'ccs_logs_cleanup_cron' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'ccs_logs_cleanup_cron' );
}
