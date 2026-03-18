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
use Labyrinth\WordPress\ContextInterface;

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
		$wp = $this->container->get( ContextInterface::class );

		// Check if we need to flush rewrite rules after activation.
		// Late priority ensures trap rewrite rules are registered first.
		$wp->add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );

		// Initialize trap router (rewrite rules, query vars, template loading).
		// Only register routing hooks when the labyrinth is enabled.
		if ( (bool) get_option( 'lby_enabled', true ) ) {
			$router = $this->container->get( 'Labyrinth\Service\TrapRouter' );
			$wp->add_action( 'init', array( $router, 'register_rewrite_rules' ) );
			$wp->add_filter( 'query_vars', array( $router, 'register_query_vars' ) );
			$wp->add_filter( 'template_include', array( $router, 'template_loader' ) );
			$wp->add_action( 'template_redirect', array( $router, 'handle_request' ) );

			// Initialize robots.txt manager (only when enabled — directives reference trap routes).
			$robots = $this->container->get( 'Labyrinth\Service\RobotsTxtManager' );
			$wp->add_filter( 'robots_txt', array( $robots, 'modify_robots_txt' ), 10, 2 );
		}

		// Initialize trap link injector (hidden links in content/footer).
		$injector = $this->container->get( 'Labyrinth\Service\TrapLinkInjector' );
		$wp->add_filter( 'the_content', array( $injector, 'inject_into_content' ) );
		$wp->add_action(
			'wp_footer',
			function () use ( $injector ) {
				echo $injector->inject_into_footer(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		);

		// Initialize bot blocking.
		$detector  = $this->container->get( 'Labyrinth\Service\BotDetector' );
		$repo      = $this->container->get( 'Labyrinth\Repository\TrapPageRepositoryInterface' );
		$generator = $this->container->get( 'Labyrinth\Service\TrapPageGenerator' );
		$max_depth = $this->container->get( 'config.lby_max_depth' );
		$base_path = $this->container->get( 'config.lby_trap_base_path' );

		$wp->add_action(
			'template_redirect',
			function () use ( $detector, $repo, $generator, $max_depth, $base_path, $wp ) {
				// Skip blocking entirely when the labyrinth is disabled.
				if ( ! (bool) $wp->get_option( 'lby_enabled', true ) ) {
					return;
				}

				// Allow admins to bypass blocking.
				if ( $wp->current_user_can( 'manage_options' ) ) {
					return;
				}

				if ( $detector->is_visitor_blocked() ) {
					$behavior = $wp->get_option( 'lby_blocking_behavior', 'block' );

					if ( 'tarpit' === $behavior ) {
						// Delay to waste bot's time (1 second).
						sleep( 1 );

						// Get a random trap page to serve.
						// Use random seed to confuse bot mapping.
						$pages = $repo->get_random_active( 1, $wp->wp_rand() );
						$page  = ! empty( $pages ) ? $pages[0] : null;

						if ( $page ) {
							// Set globals for the trap page template.
							$GLOBALS['lby_trap_page'] = $page;
							$children                 = $repo->get_children( $page->id );
							if ( empty( $children ) && $page->depth_level < $max_depth ) {
								$generator->generate_children_for( $page->id, $page->seed, $page->depth_level + 1 );
								$children = $repo->get_children( $page->id );
							}
							$GLOBALS['lby_trap_children']  = $children;
							$GLOBALS['lby_trap_base_path'] = $base_path;

							// Force 200 OK status to keep them crawling.
							$wp->status_header( 200 );

							// Send noindex/nofollow to prevent indexing of this "page".
							if ( ! $wp->headers_sent() ) {
								$wp->header( 'X-Robots-Tag: noindex, nofollow', true );
								$wp->header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
							}

							// Load the trap page template and exit.
							$wp->load_template( LABYRINTH_PLUGIN_DIR . 'templates/trap-page.php' );
							// @codeCoverageIgnoreStart
							exit;
							// @codeCoverageIgnoreEnd
						}
					}

					// Default 'block' behavior.
					$wp->wp_die(
						'<h1>' . esc_html__( 'Access Denied', 'labyrinth' ) . '</h1>' .
						'<p>' . esc_html__( 'Your IP address has been blocked due to suspicious activity.', 'labyrinth' ) . '</p>',
						esc_html__( 'Forbidden', 'labyrinth' ),
						array( 'response' => 403 )
					);
				}
			},
			1
		);

		// Initialize admin pages.
		if ( $wp->is_admin() ) {
			$settings_page = new Admin\SettingsPage();
			$settings_page->init();

			$analytics_dashboard = new Admin\AnalyticsDashboard(
				$this->container->get( 'Labyrinth\Service\AnalyticsCollector' )
			);
			$analytics_dashboard->init();

			// Enqueue admin assets.
			$wp->add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}
	}

	/**
	 * Enqueue admin assets for plugin pages.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'labyrinth_page_labyrinth-analytics' !== $hook_suffix ) {
			return;
		}

		$this->container->get( 'Labyrinth\Service\AssetManager' )->enqueue_admin_assets();
	}

	/**
	 * Plugin activation handler.
	 *
	 * Creates database tables, sets default options, schedules cron,
	 * and generates initial trap pages.
	 *
	 * @return void
	 */
	public function activate(): void {
		// Create database tables.
		Database\Schema::create_tables();

		// Set default options.
		Database\Options::set_defaults();

		// Clear DI compiled cache on activation.
		self::clear_di_cache();
		update_option( 'lby_plugin_version', LABYRINTH_VERSION );

		// Set flag to flush rewrite rules on next init.
		update_option( 'lby_flush_rewrite_rules_flag', true );

		// Schedule cron for maintenance tasks.
		if ( ! wp_next_scheduled( 'lby_maintenance_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'lby_maintenance_cron' );
		}

		// Generate initial trap pages (only if enabled and below minimum).
		if ( (bool) get_option( 'lby_enabled', true ) ) {
			try {
				$generator = $this->container->get( 'Labyrinth\Service\TrapPageGenerator' );
				$generator->ensure_minimum_pages();
			} catch ( \Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is appropriate here.
				error_log( 'Labyrinth: Failed to generate initial trap pages: ' . $e->getMessage() );
			}
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
	 * Handle regenerate labyrinth AJAX request (business logic only).
	 *
	 * Nonce and capability checks are performed by the caller.
	 *
	 * @return void
	 */
	public function handle_regenerate_ajax(): void {
		try {
			$generator = $this->container->get( 'Labyrinth\Service\TrapPageGenerator' );
			$count     = $generator->regenerate();

			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %d: number of trap pages generated */
						__( 'Labyrinth regenerated with %d trap pages.', 'labyrinth' ),
						$count
					),
					'count'   => $count,
				)
			);
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is appropriate here.
			error_log( 'Labyrinth: Regenerate error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while regenerating the labyrinth.', 'labyrinth' ),
				),
				500
			);
		}
	}

	/**
	 * Handle refresh analytics AJAX request (business logic only).
	 *
	 * Nonce and capability checks are performed by the caller.
	 *
	 * @return void
	 */
	public function handle_refresh_analytics_ajax(): void {
		try {
			$collector = $this->container->get( 'Labyrinth\Service\AnalyticsCollector' );

			wp_send_json_success(
				array(
					'summary'         => $collector->get_summary_stats(),
					'chart_data'      => $collector->get_chart_data(),
					'top_bots'        => $collector->get_top_bots(),
					'recent_activity' => $collector->get_recent_activity(),
				)
			);
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is appropriate here.
			error_log( 'Labyrinth: Analytics refresh error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while refreshing analytics.', 'labyrinth' ),
				),
				500
			);
		}
	}

	/**
	 * Handle daily maintenance cron.
	 *
	 * Prunes old request logs and ensures minimum trap pages exist.
	 *
	 * @return void
	 */
	public function handle_maintenance_cron(): void {
		try {
			// Prune old request logs.
			$log_repo       = $this->container->get( 'Labyrinth\Repository\RequestLogRepositoryInterface' );
			$retention_days = (int) get_option( 'lby_log_retention_days', 30 );
			$log_repo->prune_old_entries( $retention_days );

			// Ensure minimum trap pages exist (only when enabled).
			if ( (bool) get_option( 'lby_enabled', true ) ) {
				$generator = $this->container->get( 'Labyrinth\Service\TrapPageGenerator' );
				$generator->ensure_minimum_pages();
			}
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is appropriate here.
			error_log( 'Labyrinth: Maintenance cron error: ' . $e->getMessage() );
		}
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
