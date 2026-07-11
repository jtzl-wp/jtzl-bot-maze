<?php
/**
 * Uninstall Handler
 *
 * Fired when the plugin is uninstalled (deleted) from WordPress admin.
 * Removes all plugin data: custom tables, options, transients, and cron events.
 *
 * @package JTZL\Bot_Maze
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load plugin file for constants.
require_once __DIR__ . '/jtzl-bot-maze.php';

// Drop all custom tables.
JTZL\Bot_Maze\Database\Schema::drop_tables();

// Delete all plugin options.
JTZL\Bot_Maze\Database\Options::delete_all();

// Clear all plugin transients.
JTZL\Bot_Maze\clear_transients();

// Clear DI cache.
JTZL\Bot_Maze\clear_di_cache();

// Remove plugin options.
delete_option( 'jtzl_bm_plugin_version' );
delete_option( 'jtzl_bm_flush_rewrite_rules_flag' );
delete_option( 'jtzl_bm_pending_backfill' );

// Remove per-user Cloudflare-notice dismissals (see Admin\CloudflareSetupNotice).
delete_metadata( 'user', 0, 'jtzl_bm_cf_notice_dismissed', '', true );

// Clean up MaxMind database directory.
JTZL\Bot_Maze\Service\MaxMindDatabaseManager::cleanup();

// Unschedule cron events (current + legacy names from prior renames).
JTZL\Bot_Maze\Bootstrap::unschedule_cron_events();
