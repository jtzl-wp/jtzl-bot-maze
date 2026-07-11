<?php
/**
 * Plugin Cache Utilities
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze\Cache;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Maze\Database\Migrator;

/**
 * Plugin-scoped cache and schedule utilities.
 *
 * All methods are static because they must also run from contexts without
 * a DI container (uninstall.php, version-change checks that run before the
 * container is built). Split out from Bootstrap so Bootstrap can focus on
 * plugin lifecycle and hook registration.
 *
 * @since 1.0.0
 */
class PluginCache {

	/**
	 * Clear DI compiled cache and migrate when the plugin version changes.
	 *
	 * Runs before the container is built so it must not depend on DI.
	 * The migrator records the version as soon as the schema is verified,
	 * so the cache clear and dbDelta never repeat per request; only an
	 * unfinished data backfill is retried, gated by its own flag.
	 *
	 * @param Migrator|null $migrator Migration runner (defaults to a real one).
	 * @return void
	 * @since 1.0.0
	 */
	public static function maybe_handle_version_change( ?Migrator $migrator = null ): void {
		global $wpdb;
		$migrator = $migrator ?? new Migrator( $wpdb );

		$stored_version = (string) get_option( Migrator::VERSION_OPTION, '' );

		if ( JTZL_BM_VERSION === $stored_version ) {
			// Cheap retry path: one option read when nothing is pending.
			$migrator->run_pending_backfill();
			return;
		}

		self::clear_di_cache();
		$migrator->run();
	}

	/**
	 * Register the weekly cron schedule interval.
	 *
	 * WordPress only ships hourly/twicedaily/daily. Must be callable from
	 * both activation (before plugins_loaded) and from normal hook
	 * registration, which is why this lives as a static utility.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function register_weekly_schedule(): void {
		add_filter(
			'cron_schedules',
			static function ( array $schedules ): array {
				if ( ! isset( $schedules['weekly'] ) ) {
					$schedules['weekly'] = array(
						'interval' => WEEK_IN_SECONDS,
						'display'  => __( 'Once Weekly', 'jtzl-bot-maze' ),
					);
				}
				return $schedules;
			}
		);
	}

	/**
	 * Delete all plugin transient caches via direct DB query.
	 *
	 * Static because uninstall.php runs without a container.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function clear_transients(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE %s OR option_name LIKE %s
				    OR option_name LIKE %s OR option_name LIKE %s
				    OR option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_jtzl_bm_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_jtzl_bm_' ) . '%',
				// Legacy prefix from the prior jtzl_bl_* naming.
				$wpdb->esc_like( '_transient_jtzl_bl_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_jtzl_bl_' ) . '%',
				// Legacy prefix from the older jtzl_bbg_* naming.
				$wpdb->esc_like( '_transient_jtzl_bbg_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_jtzl_bbg_' ) . '%'
			)
		);
	}

	/**
	 * Clear compiled PHP-DI cache.
	 *
	 * Static because uninstall.php runs without a container.
	 *
	 * @param string|null $base_dir Plugin base directory. Defaults to JTZL_BM_PLUGIN_DIR.
	 * @return void
	 * @since 1.0.0
	 */
	public static function clear_di_cache( ?string $base_dir = null ): void {
		$base_dir       = $base_dir ?? JTZL_BM_PLUGIN_DIR;
		$real_cache_dir = self::resolve_safe_cache_dir( $base_dir );
		if ( null === $real_cache_dir ) {
			return;
		}

		self::delete_cache_tree( $real_cache_dir );
		self::remove_cache_dir_if_empty( $real_cache_dir );
	}

	/**
	 * Validate and resolve the cache directory path.
	 *
	 * Refuses symlinks and paths that don't match the expected location
	 * under the plugin directory, which prevents the recursive delete
	 * below from escaping the plugin root if a caller passes a crafted
	 * base directory.
	 *
	 * @param string $base_dir Plugin base directory.
	 * @return string|null Real cache directory path, or null if it is
	 *                     missing or fails any safety check.
	 * @since 1.0.0
	 */
	private static function resolve_safe_cache_dir( string $base_dir ): ?string {
		$plugin_dir = realpath( $base_dir );
		if ( ! $plugin_dir ) {
			self::log_failure( 'DI cache clear failed to resolve plugin directory.' );
			return null;
		}

		$cache_dir = $base_dir . 'var/cache';
		if ( is_link( $cache_dir ) ) {
			self::log_failure( 'DI cache clear refused to follow symlinked cache directory.' );
			return null;
		}

		$real_cache_dir = realpath( $cache_dir );
		if ( ! $real_cache_dir || ! is_dir( $real_cache_dir ) ) {
			return null;
		}

		$expected_cache_dir = $plugin_dir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache';
		if ( $real_cache_dir !== $expected_cache_dir ) {
			self::log_failure( 'DI cache clear refused unexpected cache directory path.' );
			return null;
		}

		return $real_cache_dir;
	}

	/**
	 * Recursively delete every file and child directory under the cache dir.
	 *
	 * @param string $real_cache_dir Validated cache directory path.
	 * @return void
	 * @since 1.0.0
	 */
	private static function delete_cache_tree( string $real_cache_dir ): void {
		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $real_cache_dir, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
		} catch ( \UnexpectedValueException $e ) {
			self::log_failure( 'DI cache clear failed to read directory: ' . $e->getMessage() );
			return;
		}

		foreach ( $iterator as $item ) {
			self::delete_cache_entry( $item );
		}
	}

	/**
	 * Delete a single file or empty directory, skipping unwritable entries.
	 *
	 * @param \SplFileInfo $item Iterator entry.
	 * @return void
	 * @since 1.0.0
	 */
	private static function delete_cache_entry( \SplFileInfo $item ): void {
		$path = $item->getPathname();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Local cache directory cleanup.
		if ( ! is_writable( $path ) ) {
			return;
		}

		if ( $item->isDir() ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Local cache directory cleanup.
			rmdir( $path );
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Local cache directory cleanup.
		unlink( $path );
	}

	/**
	 * Remove the cache directory itself if it is empty and writable.
	 *
	 * @param string $real_cache_dir Cache directory path.
	 * @return void
	 * @since 1.0.0
	 */
	private static function remove_cache_dir_if_empty( string $real_cache_dir ): void {
		// Unreadable directories can't be scanned, so there's nothing to clean up.
		if ( ! is_readable( $real_cache_dir ) ) {
			return;
		}

		$entries = scandir( $real_cache_dir );
		if ( false === $entries || 2 !== count( $entries ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Local cache directory cleanup.
		if ( is_writable( $real_cache_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Local cache directory cleanup.
			rmdir( $real_cache_dir );
		}
	}

	/**
	 * Log a cache-clear failure only when WP_DEBUG is on.
	 *
	 * @param string $message Failure context.
	 * @return void
	 * @since 1.0.0
	 */
	private static function log_failure( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only.
			error_log( 'JTZL Bot Maze: ' . $message );
		}
	}
}
