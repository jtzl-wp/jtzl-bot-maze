<?php
/**
 * Uninstall Handler
 *
 * Fired when the plugin is uninstalled (deleted) from WordPress admin.
 * Removes all plugin data: custom tables, options, transients, and cron events.
 *
 * @package JTZL\Bot_Labyrinth
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load plugin file for constants.
require_once __DIR__ . '/jtzl-bot-labyrinth.php';

// Drop all custom tables.
JTZL\Bot_Labyrinth\Database\Schema::drop_tables();

// Delete all plugin options.
JTZL\Bot_Labyrinth\Database\Options::delete_all();

// Clear all plugin transients.
JTZL\Bot_Labyrinth\clear_transients();

// Clear DI cache.
JTZL\Bot_Labyrinth\clear_di_cache();

// Remove plugin options.
delete_option( 'jtzl_bl_plugin_version' );
delete_option( 'jtzl_bl_flush_rewrite_rules_flag' );

// Clean up MaxMind database directory.
JTZL\Bot_Labyrinth\Service\MaxMindDatabaseManager::cleanup();

// Unschedule cron events (current + legacy names from prior renames).
JTZL\Bot_Labyrinth\Bootstrap::unschedule_cron_events();
