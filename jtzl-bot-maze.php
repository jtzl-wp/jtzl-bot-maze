<?php
/**
 * Plugin Name: JTZL's Bot Maze
 * Plugin URI: https://wordpress.org/plugins/jtzl-bot-maze
 * Description: AI bot protection through invisible trap link mazes — hidden links lure bots into a maze of fake content for detection and blocking
 * Version: 1.4.0
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Author: JTZL
 * Author URI: https://github.com/jtzl
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: jtzl-bot-maze
 * Domain Path: /languages
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze;

use JTZL\Bot_Maze\Ajax\AjaxController;
use JTZL\Bot_Maze\Cache\PluginCache;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

// Define plugin constants.
define( 'JTZL_BM_VERSION', '1.4.0' );
define( 'JTZL_BM_PLUGIN_FILE', __FILE__ );
define( 'JTZL_BM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JTZL_BM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JTZL_BM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Require Composer autoloader.
if ( file_exists( JTZL_BM_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once JTZL_BM_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function init() {
	// Clear DI cache if plugin version changed before building the container.
	PluginCache::maybe_handle_version_change();

	$container = Plugin::get_container();
	$bootstrap = new Bootstrap( $container );
	$bootstrap->register_hooks();
}

// Only register runtime hooks when not being loaded for uninstall cleanup.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

	/**
	 * Handle regenerate trap maze AJAX request.
	 *
	 * @return void
	 */
	function handle_regenerate_ajax() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'jtzl_bm_admin_action', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Invalid security token. Please refresh the page and try again.',
						'jtzl-bot-maze',
					),
				),
				403,
			);
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'You do not have permission to perform this action.',
						'jtzl-bot-maze',
					),
				),
				403,
			);
		}

		$container = Plugin::get_container();
		( new AjaxController( $container ) )->handle_regenerate();
	}

	add_action(
		'wp_ajax_jtzl_bm_regenerate',
		__NAMESPACE__ . '\\handle_regenerate_ajax',
	);

	/**
	 * Handle refresh analytics AJAX request.
	 *
	 * @return void
	 */
	function handle_refresh_analytics_ajax() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'jtzl_bm_admin_action', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Invalid security token. Please refresh the page and try again.',
						'jtzl-bot-maze',
					),
				),
				403,
			);
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'You do not have permission to perform this action.',
						'jtzl-bot-maze',
					),
				),
				403,
			);
		}

		$container = Plugin::get_container();
		( new AjaxController( $container ) )->handle_refresh_analytics();
	}

	add_action(
		'wp_ajax_jtzl_bm_refresh_analytics',
		__NAMESPACE__ . '\\handle_refresh_analytics_ajax',
	);

	/**
	 * Handle geographic data AJAX request.
	 *
	 * @return void
	 */
	function handle_geo_data_ajax() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'jtzl_bm_admin_action', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Invalid security token. Please refresh the page and try again.',
						'jtzl-bot-maze',
					),
				),
				403,
			);
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'You do not have permission to perform this action.',
						'jtzl-bot-maze',
					),
				),
				403,
			);
		}

		$container = Plugin::get_container();
		( new AjaxController( $container ) )->handle_geo_data();
	}

	add_action(
		'wp_ajax_jtzl_bm_geo_data',
		__NAMESPACE__ . '\\handle_geo_data_ajax',
	);

	/**
	 * Handle MaxMind database download AJAX request.
	 *
	 * @return void
	 */
	function handle_maxmind_download_ajax() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'jtzl_bm_admin_action', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Invalid security token. Please refresh the page and try again.',
						'jtzl-bot-maze',
					),
				),
				403,
			);
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'You do not have permission to perform this action.',
						'jtzl-bot-maze',
					),
				),
				403,
			);
		}

		$manager     = new Service\MaxMindDatabaseManager();
		$license_key = (string) get_option( 'jtzl_bm_maxmind_license_key', '' );

		try {
			$result = $manager->download_database( $license_key );
		} catch ( \Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}

		// $result is always set: try assigns it, catch terminates via wp_die.
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Database downloaded successfully.', 'jtzl-bot-maze' ) ) );
	}

	add_action(
		'wp_ajax_jtzl_bm_maxmind_download',
		__NAMESPACE__ . '\\handle_maxmind_download_ajax',
	);

	/**
	 * Handle weekly MaxMind database update cron.
	 *
	 * @return void
	 */
	function handle_maxmind_update_cron() {
		$provider    = (string) get_option( 'jtzl_bm_geo_provider', 'none' );
		$license_key = (string) get_option( 'jtzl_bm_maxmind_license_key', '' );

		if ( 'maxmind' !== $provider || '' === $license_key ) {
			return;
		}

		$manager = new Service\MaxMindDatabaseManager();
		$result  = $manager->download_database( $license_key );

		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron error logging is appropriate.
			error_log( 'JTZL Bot Maze: MaxMind database update failed: ' . $result->get_error_message() );
		}
	}

	add_action(
		'jtzl_bm_maxmind_update_cron',
		__NAMESPACE__ . '\\handle_maxmind_update_cron',
	);

	/**
	 * Handle weekly Cloudflare IP range refresh cron.
	 *
	 * Only refreshes when the Cloudflare trusted-header preset is active —
	 * the ranges are irrelevant otherwise.
	 *
	 * @return void
	 */
	function handle_cloudflare_ranges_update_cron() {
		if ( 'CF-Connecting-IP' !== (string) get_option( 'jtzl_bm_trusted_proxy_header', '' ) ) {
			return;
		}

		$updater = new Service\CloudflareRangeUpdater();
		$updater->refresh();
	}

	add_action(
		'jtzl_bm_cloudflare_update_cron',
		__NAMESPACE__ . '\\handle_cloudflare_ranges_update_cron',
	);

	/**
	 * Plugin activation hook.
	 *
	 * Creates database tables and sets default options.
	 *
	 * @return void
	 */
	function activate() {
		$container = Plugin::get_container();
		$bootstrap = new Bootstrap( $container );
		$bootstrap->activate();
	}

	register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

	/**
	 * Plugin deactivation hook.
	 *
	 * Cleans up transient caches and scheduled events.
	 *
	 * @return void
	 */
	function deactivate() {
		$container = Plugin::get_container();
		$bootstrap = new Bootstrap( $container );
		$bootstrap->deactivate();
	}

	register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

	/**
	 * Handle daily maintenance cron.
	 *
	 * @return void
	 */
	function handle_maintenance_cron() {
		$container = Plugin::get_container();
		$bootstrap = new Bootstrap( $container );
		$bootstrap->handle_maintenance_cron();
	}

	add_action(
		'jtzl_bm_maintenance_cron',
		__NAMESPACE__ . '\\handle_maintenance_cron',
	);
}

/**
 * Clear all plugin transient caches.
 *
 * Thin delegate to PluginCache::clear_transients() for the uninstall.php
 * entry point, which must work without instantiating the DI container.
 *
 * @return void
 */
function clear_transients() {
	PluginCache::clear_transients();
}

/**
 * Clear compiled PHP-DI cache.
 *
 * Thin delegate to PluginCache::clear_di_cache() for the uninstall.php
 * entry point, which must work without instantiating the DI container.
 *
 * @return void
 */
function clear_di_cache(): void {
	PluginCache::clear_di_cache();
}
