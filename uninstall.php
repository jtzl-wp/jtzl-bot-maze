<?php
/**
 * Uninstall Handler
 *
 * Fired when the plugin is uninstalled (deleted) from WordPress admin.
 * Removes all plugin data: custom tables, options, transients, and cron events.
 *
 * @package Labyrinth
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load plugin file for constants.
require_once __DIR__ . '/labyrinth.php';

// Clear all plugin transients.
Labyrinth\clear_transients();

// Clear DI cache.
Labyrinth\clear_di_cache();

// Remove plugin options.
delete_option( 'lby_plugin_version' );
delete_option( 'lby_flush_rewrite_rules_flag' );

// Unschedule cron events.
$timestamp = wp_next_scheduled( 'lby_maintenance_cron' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'lby_maintenance_cron' );
}
