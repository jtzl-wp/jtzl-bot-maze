<?php
/**
 * Plugin Name: Labyrinth
 * Plugin URI: https://github.com/labyrinth/labyrinth
 * Description: AI bot protection through invisible trap link labyrinths — hidden links lure bots into a maze of fake content for detection and blocking
 * Version: 1.0.0-rc.5
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Author: Labyrinth
 * Author URI: https://github.com/labyrinth
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: labyrinth
 * Domain Path: /languages
 *
 * @package Labyrinth
 */

namespace Labyrinth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

// Define plugin constants.
define( 'LABYRINTH_VERSION', '1.0.0-rc.5' );
define( 'LABYRINTH_PLUGIN_FILE', __FILE__ );
define( 'LABYRINTH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LABYRINTH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LABYRINTH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Require Composer autoloader.
if ( file_exists( LABYRINTH_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once LABYRINTH_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function init() {
	// Clear DI cache if plugin version changed before building the container.
	Bootstrap::maybe_handle_version_change();

	$container = Plugin::get_container();
	$bootstrap = new Bootstrap( $container );
	$bootstrap->register_hooks();
}

// Only register runtime hooks when not being loaded for uninstall cleanup.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

	/**
	 * Handle regenerate labyrinth AJAX request.
	 *
	 * @return void
	 */
	function handle_regenerate_ajax() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'lby_admin_action', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Invalid security token. Please refresh the page and try again.',
						'labyrinth',
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
						'labyrinth',
					),
				),
				403,
			);
		}

		$container = Plugin::get_container();
		$bootstrap = new Bootstrap( $container );
		$bootstrap->handle_regenerate_ajax();
	}

	add_action(
		'wp_ajax_lby_regenerate',
		__NAMESPACE__ . '\\handle_regenerate_ajax',
	);

	/**
	 * Handle refresh analytics AJAX request.
	 *
	 * @return void
	 */
	function handle_refresh_analytics_ajax() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'lby_admin_action', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Invalid security token. Please refresh the page and try again.',
						'labyrinth',
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
						'labyrinth',
					),
				),
				403,
			);
		}

		$container = Plugin::get_container();
		$bootstrap = new Bootstrap( $container );
		$bootstrap->handle_refresh_analytics_ajax();
	}

	add_action(
		'wp_ajax_lby_refresh_analytics',
		__NAMESPACE__ . '\\handle_refresh_analytics_ajax',
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
		'lby_maintenance_cron',
		__NAMESPACE__ . '\\handle_maintenance_cron',
	);
}

/**
 * Clear all plugin transient caches.
 *
 * Thin delegate to Bootstrap::clear_transients() for backward compatibility
 * with uninstall.php.
 *
 * @return void
 */
function clear_transients() {
	Bootstrap::clear_transients();
}

/**
 * Clear compiled PHP-DI cache.
 *
 * Thin delegate to Bootstrap::clear_di_cache() for backward compatibility
 * with uninstall.php.
 *
 * @return void
 */
function clear_di_cache(): void {
	Bootstrap::clear_di_cache();
}
