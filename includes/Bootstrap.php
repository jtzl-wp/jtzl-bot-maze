<?php
/**
 * Bootstrap Class
 *
 * Encapsulates plugin initialization, hook registration, and lifecycle logic.
 *
 * @package Labyrinth
 */

namespace Labyrinth;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use DI\Container;

/**
 * Bootstrap Class
 *
 * Encapsulates plugin initialization, hook registration, and lifecycle logic.
 * Receives the DI container via constructor injection, except for static
 * utility methods that must work without a container (e.g. uninstall).
 */
class Bootstrap {

	/**
	 * The dependency injection container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container The DI container instance.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register all plugin hooks with WordPress.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Check if we need to flush rewrite rules after activation.
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ) );
	}

	/**
	 * Plugin activation handler.
	 *
	 * Sets default options, schedules cron, and prepares rewrite rules.
	 *
	 * @return void
	 */
	public function activate(): void {
		// Clear DI compiled cache on activation.
		self::clear_di_cache();
		update_option( 'lby_plugin_version', LABYRINTH_VERSION );

		// Set flag to flush rewrite rules on next init.
		update_option( 'lby_flush_rewrite_rules_flag', true );

		// Schedule cron for maintenance tasks.
		if ( ! wp_next_scheduled( 'lby_maintenance_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'lby_maintenance_cron' );
		}
	}

	/**
	 * Plugin deactivation handler.
	 *
	 * Clears transient caches, DI cache, rewrite rules, and cron events.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// Clear all plugin transient caches.
		self::clear_transients();

		// Clear DI compiled cache on deactivation.
		self::clear_di_cache();

		// Flush rewrite rules to remove our custom rules.
		flush_rewrite_rules();

		// Unschedule cron.
		$timestamp = wp_next_scheduled( 'lby_maintenance_cron' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'lby_maintenance_cron' );
		}
	}

	/**
	 * Handle daily maintenance cron.
	 *
	 * Placeholder for future log pruning and trap page maintenance.
	 *
	 * @return void
	 */
	public function handle_maintenance_cron(): void {
		// Will be extended to prune logs and ensure minimum trap pages.
	}

	/**
	 * Flush rewrite rules if the activation flag is set.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( get_option( 'lby_flush_rewrite_rules_flag' ) ) {
			flush_rewrite_rules();
			delete_option( 'lby_flush_rewrite_rules_flag' );
		}
	}

	/**
	 * Clear DI compiled cache when the plugin version changes.
	 *
	 * This is static because it must run before the container is built.
	 *
	 * @return void
	 */
	public static function maybe_handle_version_change(): void {
		$stored_version = (string) get_option( 'lby_plugin_version', '' );

		if ( LABYRINTH_VERSION !== $stored_version ) {
			self::clear_di_cache();
			update_option( 'lby_plugin_version', LABYRINTH_VERSION );
		}
	}

	/**
	 * Clear all plugin transient caches.
	 *
	 * Static because it is also called from uninstall.php without a container.
	 *
	 * @return void
	 */
	public static function clear_transients(): void {
		global $wpdb;

		// Delete all transients with lby_ prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_lby_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_lby_' ) . '%'
			)
		);
	}

	/**
	 * Clear compiled PHP-DI cache.
	 *
	 * Static because it is also called from uninstall.php without a container.
	 *
	 * @param string|null $base_dir Plugin base directory. Defaults to LABYRINTH_PLUGIN_DIR.
	 * @return void
	 */
	public static function clear_di_cache( ?string $base_dir = null ): void {
		$base_dir   = $base_dir ?? LABYRINTH_PLUGIN_DIR;
		$cache_dir  = $base_dir . 'var/cache';
		$plugin_dir = realpath( $base_dir );

		$log_failure = static function ( string $message ): void {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only.
				error_log( 'Labyrinth: ' . $message );
			}
		};

		if ( ! $plugin_dir ) {
			$log_failure( 'DI cache clear failed to resolve plugin directory.' );
			return;
		}

		$expected_cache_dir = $plugin_dir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache';

		if ( is_link( $cache_dir ) ) {
			$log_failure( 'DI cache clear refused to follow symlinked cache directory.' );
			return;
		}

		$real_cache_dir = realpath( $cache_dir );

		if ( ! $real_cache_dir ) {
			return;
		}

		if ( $real_cache_dir !== $expected_cache_dir ) {
			$log_failure( 'DI cache clear refused unexpected cache directory path.' );
			return;
		}

		if ( ! is_dir( $real_cache_dir ) ) {
			return;
		}

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $real_cache_dir, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
		} catch ( \UnexpectedValueException $e ) {
			$log_failure( 'DI cache clear failed to read directory: ' . $e->getMessage() );
			return;
		}

		foreach ( $iterator as $item ) {
			$path = $item->getPathname();
			if ( $item->isDir() ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Local cache directory cleanup.
				if ( is_writable( $path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Local cache directory cleanup.
					rmdir( $path );
				}
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Local cache directory cleanup.
			if ( is_writable( $path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Local cache directory cleanup.
				unlink( $path );
			}
		}

		// Remove the cache directory if it's empty.
		$entries = scandir( $real_cache_dir );
		if ( false !== $entries && 2 === count( $entries ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Local cache directory cleanup.
			if ( is_writable( $real_cache_dir ) ) {
				rmdir( $real_cache_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Local cache directory cleanup.
			}
		}
	}
}
