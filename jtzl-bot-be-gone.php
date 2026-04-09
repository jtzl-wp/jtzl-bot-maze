<?php
/**
 * Plugin Name: JTZL's Bot-be-Gone
 * Plugin URI: https://github.com/jtzl/bot-be-gone
 * Description: AI bot protection through invisible trap link mazes — hidden links lure bots into a maze of fake content for detection and blocking
 * Version: 1.0.0-rc.8
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Author: JTZL
 * Author URI: https://github.com/jtzl
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: jtzl-bot-be-gone
 * Domain Path: /languages
 *
 * @package JTZL\Bot_Be_Gone
 */

namespace JTZL\Bot_Be_Gone;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

// Define plugin constants.
define( 'JTZL_BBG_VERSION', '1.0.0-rc.8' );
define( 'JTZL_BBG_PLUGIN_FILE', __FILE__ );
define( 'JTZL_BBG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JTZL_BBG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JTZL_BBG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Require Composer autoloader.
if ( file_exists( JTZL_BBG_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once JTZL_BBG_PLUGIN_DIR . 'vendor/autoload.php';
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
	 * Handle regenerate trap maze AJAX request.
	 *
	 * @return void
	 */
	function handle_regenerate_ajax() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'jtzl_bbg_admin_action', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Invalid security token. Please refresh the page and try again.',
						'jtzl-bot-be-gone',
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
						'jtzl-bot-be-gone',
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
		'wp_ajax_jtzl_bbg_regenerate',
		__NAMESPACE__ . '\\handle_regenerate_ajax',
	);

	/**
	 * Handle refresh analytics AJAX request.
	 *
	 * @return void
	 */
	function handle_refresh_analytics_ajax() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'jtzl_bbg_admin_action', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Invalid security token. Please refresh the page and try again.',
						'jtzl-bot-be-gone',
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
						'jtzl-bot-be-gone',
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
		'wp_ajax_jtzl_bbg_refresh_analytics',
		__NAMESPACE__ . '\\handle_refresh_analytics_ajax',
	);

	/**
	 * Handle geographic data AJAX request.
	 *
	 * @return void
	 */
	function handle_geo_data_ajax() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'jtzl_bbg_admin_action', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Invalid security token. Please refresh the page and try again.',
						'jtzl-bot-be-gone',
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
						'jtzl-bot-be-gone',
					),
				),
				403,
			);
		}

		$container = Plugin::get_container();
		$bootstrap = new Bootstrap( $container );
		$bootstrap->handle_geo_data_ajax();
	}

	add_action(
		'wp_ajax_jtzl_bbg_geo_data',
		__NAMESPACE__ . '\\handle_geo_data_ajax',
	);

	/**
	 * Handle MaxMind database download AJAX request.
	 *
	 * @return void
	 */
	function handle_maxmind_download_ajax() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'jtzl_bbg_admin_action', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Invalid security token. Please refresh the page and try again.',
						'jtzl-bot-be-gone',
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
						'jtzl-bot-be-gone',
					),
				),
				403,
			);
		}

		$manager     = new Service\MaxMindDatabaseManager();
		$license_key = (string) get_option( 'jtzl_bbg_maxmind_license_key', '' );

		try {
			$result = $manager->download_database( $license_key );
		} catch ( \Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}

		// $result is always set: try assigns it, catch terminates via wp_die.
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Database downloaded successfully.', 'jtzl-bot-be-gone' ) ) );
	}

	add_action(
		'wp_ajax_jtzl_bbg_maxmind_download',
		__NAMESPACE__ . '\\handle_maxmind_download_ajax',
	);

	/**
	 * Handle weekly MaxMind database update cron.
	 *
	 * @return void
	 */
	function handle_maxmind_update_cron() {
		$provider    = (string) get_option( 'jtzl_bbg_geo_provider', 'none' );
		$license_key = (string) get_option( 'jtzl_bbg_maxmind_license_key', '' );

		if ( 'maxmind' !== $provider || '' === $license_key ) {
			return;
		}

		$manager = new Service\MaxMindDatabaseManager();
		$result  = $manager->download_database( $license_key );

		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron error logging is appropriate.
			error_log( 'JTZL Bot-be-Gone: MaxMind database update failed: ' . $result->get_error_message() );
		}
	}

	add_action(
		'jtzl_bbg_maxmind_update_cron',
		__NAMESPACE__ . '\\handle_maxmind_update_cron',
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
		'jtzl_bbg_maintenance_cron',
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
