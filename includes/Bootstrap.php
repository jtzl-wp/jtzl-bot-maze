<?php
/**
 * Bootstrap Class
 *
 * Encapsulates plugin initialization, hook registration, and lifecycle logic.
 *
 * @package Labyrinth
 * @since 1.0.0
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
 *
 * @since 1.0.0
 */
class Bootstrap {

	/**
	 * The dependency injection container.
	 *
	 * @var Container
	 * @since 1.0.0
	 */
	private Container $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container The DI container instance.
	 * @since 1.0.0
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register all plugin hooks with WordPress.
	 *
	 * @return void
	 * @since 1.0.0
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
		$detector     = $this->container->get( 'Labyrinth\Service\BotDetector' );
		$repo         = $this->container->get( 'Labyrinth\Repository\TrapPageRepositoryInterface' );
		$base_path    = $this->container->get( 'config.lby_trap_base_path' );
		$tarpit_delay = $this->container->get( 'config.lby_tarpit_delay_ms' );
		$home_path    = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$trap_prefix  = $home_path . '/' . $base_path . '/';
		$container    = $this->container;

		$wp->add_action(
			'template_redirect',
			function () use ( $detector, $repo, $base_path, $tarpit_delay, $trap_prefix, $wp, $container ) {
				// Skip blocking entirely when the labyrinth is disabled.
				if ( ! (bool) $wp->get_option( 'lby_enabled', true ) ) {
					return;
				}

				// Allow admins to bypass blocking.
				if ( $wp->current_user_can( 'manage_options' ) ) {
					return;
				}

				if ( $detector->is_visitor_blocked() ) {
					// Build a tracking helper that logs at each exit point with the actual response code.
					// Only tracks trap page requests — non-trap visits from blocked bots are not recorded.
					$track_visit = static function ( int $response_code ) use ( $wp, $container, $trap_prefix ): void {
						if ( ! (bool) $wp->get_option( 'lby_track_blocked_activity', false ) ) {
							return;
						}
						$ip_tracker  = $container->get( 'Labyrinth\Service\IPTrackerInterface' );
						$request_uri = $ip_tracker->get_request_uri();
						if ( ! str_starts_with( $request_uri, $trap_prefix ) ) {
							return;
						}
						$collector = $container->get( 'Labyrinth\Service\AnalyticsCollector' );
						$collector->record_blocked_visit(
							$ip_tracker->get_client_ip(),
							$ip_tracker->get_user_agent_hash(),
							$ip_tracker->get_user_agent(),
							$response_code
						);
					};

					$behavior = $wp->get_option( 'lby_blocking_behavior', 'block' );
					$is_light = ( 'tarpit_light' === $behavior );
					$is_full  = ( 'tarpit' === $behavior );

					if ( $is_light || $is_full ) {
						// Full tarpit: intentional delay to waste bot wall-clock time.
						// Admins opt into this knowing it ties up a PHP worker per request.
						if ( $is_full && $tarpit_delay > 0 ) {
							usleep( $tarpit_delay * 1000 );
						}

						// Get a random existing trap page to serve as decoy.
						$pages = $repo->get_random_active( 1, $wp->wp_rand() );
						$page  = ! empty( $pages ) ? $pages[0] : null;

						if ( $page ) {
							$track_visit( 200 );

							$GLOBALS['lby_trap_page']      = $page;
							$GLOBALS['lby_trap_children']  = $repo->get_children( $page->id );
							$GLOBALS['lby_trap_base_path'] = $base_path;

							// Force 200 OK status to keep them crawling.
							$wp->status_header( 200 );

							if ( ! $wp->headers_sent() ) {
								$wp->header( 'X-Robots-Tag: noindex, nofollow', true );
								$wp->header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
							}

							$wp->load_template( LABYRINTH_PLUGIN_DIR . 'templates/trap-page.php' );
							// @codeCoverageIgnoreStart
							exit;
							// @codeCoverageIgnoreEnd
						}
					}

					// Default 'block' behavior (also tarpit fallback when no trap pages exist).
					$track_visit( 403 );
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
			// About page first — registers the top-level menu.
			$about_page = new Admin\AboutPage();
			$about_page->init();

			$settings_page = new Admin\SettingsPage();
			$settings_page->init();

			$trap_pages_page = new Admin\TrapPagesPage(
				$this->container->get( 'Labyrinth\Service\AnalyticsCollector' ),
				$this->container->get( 'config.lby_trap_base_path' )
			);
			$trap_pages_page->init();

			$analytics_dashboard = new Admin\AnalyticsDashboard(
				$this->container->get( 'Labyrinth\Service\AnalyticsCollector' )
			);
			$analytics_dashboard->init();

			$blocked_bots_page = new Admin\BlockedBotsPage(
				$this->container->get( 'Labyrinth\Service\AnalyticsCollector' )
			);
			$blocked_bots_page->init();

			$recent_activity_page = new Admin\RecentActivityPage(
				$this->container->get( 'Labyrinth\Service\AnalyticsCollector' )
			);
			$recent_activity_page->init();

			// Enqueue admin assets.
			$wp->add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

			// Privacy policy suggestion.
			$wp->add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		}
	}

	/**
	 * Add suggested privacy policy text for the plugin.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function add_privacy_policy_content(): void {
		$content = sprintf(
			'<h2>%s</h2><p>%s</p><p>%s</p><p>%s</p><p>%s</p>',
			esc_html__( 'Labyrinth Bot Protection', 'labyrinth' ),
			esc_html__( 'This site uses the Labyrinth plugin to detect and block malicious bots and unauthorized scrapers. When a visitor accesses a trap page (a hidden page that only bots would find), the plugin records their IP address, user agent string, and the time of visit for security purposes.', 'labyrinth' ),
			esc_html__( 'This data is collected under the legal basis of legitimate interest (GDPR Article 6(1)(f)) to protect the website from automated abuse. Only visitors that access trap pages are recorded; normal browsing of the site is not tracked by this plugin.', 'labyrinth' ),
			sprintf(
				/* translators: %s: default retention period */
				esc_html__( 'Collected data is automatically deleted after a configurable retention period (default: %s days).', 'labyrinth' ),
				'30'
			),
			esc_html__( 'When the optional Geographic Tracking setting is enabled, visitor IP addresses are sent to ip-api.com to determine the country of origin. This feature is off by default and requires explicit opt-in by a site administrator.', 'labyrinth' )
		);

		wp_add_privacy_policy_content( 'Labyrinth', $content );
	}

	/**
	 * Enqueue admin assets for plugin pages.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 * @since 1.0.0
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		$allowed = array(
			'labyrinth_page_labyrinth-analytics',
			'labyrinth_page_labyrinth-blocked-bots',
			'labyrinth_page_labyrinth-recent-activity',
		);
		if ( ! in_array( $hook_suffix, $allowed, true ) ) {
			return;
		}

		$asset_manager = $this->container->get( 'Labyrinth\Service\AssetManager' );
		$asset_manager->enqueue_admin_assets();
		$asset_manager->enqueue_map_assets();
	}

	/**
	 * Plugin activation handler.
	 *
	 * Creates database tables, sets default options, schedules cron,
	 * and generates initial trap pages.
	 *
	 * @return void
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
	 */
	public function handle_regenerate_ajax(): void {
		try {
			$generator = $this->container->get( 'Labyrinth\Service\TrapPageGenerator' );
			$count     = $generator->regenerate();
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is appropriate here.
			error_log( 'Labyrinth: Regenerate error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while regenerating the labyrinth.', 'labyrinth' ),
				),
				500
			);
		}

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
	}

	/**
	 * Handle refresh analytics AJAX request (business logic only).
	 *
	 * Nonce and capability checks are performed by the caller.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function handle_refresh_analytics_ajax(): void {
		try {
			$collector = $this->container->get( 'Labyrinth\Service\AnalyticsCollector' );
			$summary   = $collector->get_summary_stats();
			$chart     = $collector->get_chart_data();
			$bots      = $collector->get_top_bots();
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is appropriate here.
			error_log( 'Labyrinth: Analytics refresh error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while refreshing analytics.', 'labyrinth' ),
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'summary'    => $summary,
				'chart_data' => $chart,
				'top_bots'   => $bots,
			)
		);
	}

	/**
	 * Handle geographic data AJAX request (business logic only).
	 *
	 * Nonce and capability checks are performed by the caller.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function handle_geo_data_ajax(): void {
		try {
			$collector = $this->container->get( 'Labyrinth\Service\AnalyticsCollector' );
			$countries = $collector->get_visits_by_country();
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is appropriate here.
			error_log( 'Labyrinth: Geo data error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while loading geographic data.', 'labyrinth' ),
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'countries' => empty( $countries ) ? new \stdClass() : $countries,
				'enabled'   => (bool) get_option( 'lby_enable_geo_tracking', false ),
			)
		);
	}

	/**
	 * Handle daily maintenance cron.
	 *
	 * Prunes old evidence data and ensures minimum trap pages exist.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function handle_maintenance_cron(): void {
		try {
			$retention_days = $this->container->get( 'config.lby_retention_days' );

			// Prune old trap visits and stale bot scores.
			$visit_repo = $this->container->get( 'Labyrinth\Repository\TrapVisitRepositoryInterface' );
			$score_repo = $this->container->get( 'Labyrinth\Repository\BotScoreRepositoryInterface' );

			$visit_repo->prune_old_entries( $retention_days );
			$score_repo->prune_stale_entries( $retention_days );

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
	 * @since 1.0.0
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
	 * @since 1.0.0
	 */
	public static function maybe_handle_version_change(): void {
		$stored_version = (string) get_option( 'lby_plugin_version', '' );

		if ( LABYRINTH_VERSION !== $stored_version ) {
			self::clear_di_cache();
			self::migrate_renamed_options();
			Database\Schema::create_tables();
			update_option( 'lby_plugin_version', LABYRINTH_VERSION );
		}
	}

	/**
	 * Migrate renamed option keys from earlier versions.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function migrate_renamed_options(): void {
		$renames = array(
			'lby_log_retention_days' => 'lby_retention_days',
		);

		foreach ( $renames as $old_key => $new_key ) {
			$old_value = get_option( $old_key );
			if ( false !== $old_value ) {
				if ( false === get_option( $new_key ) ) {
					update_option( $new_key, $old_value );
				}
				delete_option( $old_key );
			}
		}
	}

	/**
	 * Clear all plugin transient caches.
	 *
	 * Static because it is also called from uninstall.php without a container.
	 *
	 * @return void
	 * @since 1.0.0
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
	 * @since 1.0.0
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
