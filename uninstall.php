<?php
/**
 * Uninstall Handler
 *
 * Fired when the plugin is uninstalled (deleted) from WordPress admin.
 * Removes all plugin data: custom tables, options, transients, and cron events.
 *
 * @package JTZL\Bot_Be_Gone
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load plugin file for constants.
require_once __DIR__ . '/jtzl-bot-be-gone.php';

// Drop all custom tables.
JTZL\Bot_Be_Gone\Database\Schema::drop_tables();

// Delete all plugin options.
JTZL\Bot_Be_Gone\Database\Options::delete_all();

// Clear all plugin transients.
JTZL\Bot_Be_Gone\clear_transients();

// Clear DI cache.
JTZL\Bot_Be_Gone\clear_di_cache();

// Remove plugin options.
delete_option( 'jtzl_bbg_plugin_version' );
delete_option( 'jtzl_bbg_flush_rewrite_rules_flag' );

// Clean up MaxMind database directory.
JTZL\Bot_Be_Gone\Service\MaxMindDatabaseManager::cleanup();

// Unschedule cron events.
$timestamp = wp_next_scheduled( 'jtzl_bbg_maintenance_cron' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'jtzl_bbg_maintenance_cron' );
}

$mm_timestamp = wp_next_scheduled( 'jtzl_bbg_maxmind_update_cron' );
if ( $mm_timestamp ) {
	wp_unschedule_event( $mm_timestamp, 'jtzl_bbg_maxmind_update_cron' );
}
