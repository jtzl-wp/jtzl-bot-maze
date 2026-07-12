<?php
/**
 * Bootstrap Class
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use DI\Container;
use JTZL\Bot_Maze\Admin\AdminPagesBootstrap;
use JTZL\Bot_Maze\Cache\PluginCache;
use JTZL\Bot_Maze\Service\BlockingRequestHandler;
use JTZL\Bot_Maze\WordPress\ContextInterface;

/**
 * Plugin lifecycle and hook composition root.
 *
 * Delegates front-end blocking to BlockingRequestHandler and admin-side
 * initialization to AdminPagesBootstrap, and uses static cache/schedule
 * utilities from PluginCache. What remains here is plugin lifecycle
 * (activate, deactivate, maintenance cron) and the runtime hook wiring
 * that ties those collaborators to WordPress.
 *
 * AJAX endpoints are intentionally *not* wired from here — the plugin
 * entry file (jtzl-bot-maze.php) hooks admin-ajax actions directly
 * and delegates their business logic to Ajax\AjaxController, so nonce
 * and capability checks stay at the request boundary without needing
 * a Bootstrap instance.
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

		// Weekly cron schedule interval (WP only ships hourly/twicedaily/daily).
		PluginCache::register_weekly_schedule();

		// Flush rewrite rules at a late priority after trap routes are registered.
		$wp->add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );

		// Trap routing and robots.txt modifications only run while the maze is enabled.
		if ( (bool) get_option( 'jtzl_bm_enabled', true ) ) {
			$this->register_trap_routing_hooks( $wp );
		}

		// Trap link injection runs regardless so hidden maze entries still appear.
		$this->register_trap_link_injector_hooks( $wp );

		// Blocking handler owns the template_redirect response for flagged visitors.
		$blocker = $this->build_blocking_handler();
		$wp->add_action( 'template_redirect', array( $blocker, 'handle' ), 1 );

		// Register the migration Site Health status test on every request (via
		// the container string-get, so no object coupling here) so WordPress's
		// weekly cron Site Health check — which runs where is_admin() is false —
		// still includes it and the dashboard widget updates passively.
		$this->container->get( 'JTZL\Bot_Maze\Admin\MigrationHealthCheck' )->register_status_test();

		// Admin pages and assets (and the admin-only migration hooks) only mount
		// in wp-admin.
		if ( $wp->is_admin() ) {
			( new AdminPagesBootstrap( $this->container ) )->register( $wp );
		}
	}

	/**
	 * Wire up trap router + robots.txt hooks.
	 *
	 * @param ContextInterface $wp WordPress context facade.
	 * @return void
	 * @since 1.0.0
	 */
	private function register_trap_routing_hooks( ContextInterface $wp ): void {
		$router = $this->container->get( 'JTZL\Bot_Maze\Service\TrapRouter' );
		$wp->add_action( 'init', array( $router, 'register_rewrite_rules' ) );
		$wp->add_filter( 'query_vars', array( $router, 'register_query_vars' ) );
		$wp->add_filter( 'template_include', array( $router, 'template_loader' ) );
		$wp->add_action( 'template_redirect', array( $router, 'handle_request' ) );

		$robots = $this->container->get( 'JTZL\Bot_Maze\Service\RobotsTxtManager' );
		// Late priority so our appended rule survives plugins that *replace* the
		// robots.txt output (e.g. Rank Math's editor at priority 10). See
		// RobotsTxtManager::FILTER_PRIORITY.
		$wp->add_filter( 'robots_txt', array( $robots, 'modify_robots_txt' ), \JTZL\Bot_Maze\Service\RobotsTxtManager::FILTER_PRIORITY, 2 );
	}

	/**
	 * Wire up trap link injector hooks for real pages.
	 *
	 * @param ContextInterface $wp WordPress context facade.
	 * @return void
	 * @since 1.0.0
	 */
	private function register_trap_link_injector_hooks( ContextInterface $wp ): void {
		$injector = $this->container->get( 'JTZL\Bot_Maze\Service\TrapLinkInjector' );
		$wp->add_filter( 'the_content', array( $injector, 'inject_into_content' ) );
		$wp->add_action(
			'wp_footer',
			function () use ( $injector ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static plugin-authored markup; the only dynamic values (URL, link text) are escaped with esc_url()/esc_html() in TrapLinkInjector::generate_hidden_links(). wp_kses() cannot wrap this because safecss_filter_attr() would strip the position/clip CSS that hides the trap links from human visitors.
				echo $injector->inject_into_footer();
			}
		);
	}

	/**
	 * Build the blocking request handler.
	 *
	 * Only the two collaborators handle() always touches (the context and
	 * the bot detector) are eagerly resolved. The tarpit / tracking-path
	 * deps are resolved by the handler on demand via the container, which
	 * is why the container itself is passed through here.
	 *
	 * @return BlockingRequestHandler
	 * @since 1.0.0
	 */
	private function build_blocking_handler(): BlockingRequestHandler {
		return new BlockingRequestHandler(
			$this->container->get( ContextInterface::class ),
			$this->container->get( 'JTZL\Bot_Maze\Service\BotDetector' ),
			$this->container,
			(string) $this->container->get( 'config.jtzl_bm_trap_base_path' ),
			(int) $this->container->get( 'config.jtzl_bm_tarpit_delay_ms' )
		);
	}

	/**
	 * Plugin activation handler.
	 *
	 * Runs the same migration as PluginCache::maybe_handle_version_change()
	 * so deactivate/update/reactivate flows can't skip a schema upgrade.
	 * The migrator records the plugin version once the schema is verified;
	 * a failed step retries on the next request instead of being skipped.
	 *
	 * @param Database\Migrator|null $migrator Migration runner (defaults to a real one).
	 * @return void
	 * @since 1.0.0
	 */
	public function activate( ?Database\Migrator $migrator = null ): void {
		global $wpdb;
		$migrator = $migrator ?? new Database\Migrator( $wpdb );
		$migrator->run();
		Database\Options::set_defaults();

		PluginCache::clear_di_cache();
		update_option( 'jtzl_bm_flush_rewrite_rules_flag', true );

		if ( ! wp_next_scheduled( 'jtzl_bm_maintenance_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'jtzl_bm_maintenance_cron' );
		}

		// Register the weekly interval here too — activation fires before plugins_loaded.
		PluginCache::register_weekly_schedule();
		if ( ! wp_next_scheduled( 'jtzl_bm_maxmind_update_cron' ) ) {
			wp_schedule_event( time(), 'weekly', 'jtzl_bm_maxmind_update_cron' );
		}

		if ( ! wp_next_scheduled( 'jtzl_bm_cloudflare_update_cron' ) ) {
			wp_schedule_event( time(), 'weekly', 'jtzl_bm_cloudflare_update_cron' );
		}

		// If the migration didn't fully land — missing columns (Branch B),
		// a pending backfill (Branch A), or a stale version — schedule an hourly
		// cron to retry it in the background until it completes.
		if ( $migrator->is_incomplete() && ! wp_next_scheduled( Database\Migrator::BACKFILL_RETRY_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', Database\Migrator::BACKFILL_RETRY_HOOK );
		}

		if ( (bool) get_option( 'jtzl_bm_enabled', true ) ) {
			try {
				$generator = $this->container->get( 'JTZL\Bot_Maze\Service\TrapPageGenerator' );
				$generator->ensure_minimum_pages();
			} catch ( \Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is appropriate here.
				error_log( 'JTZL Bot Maze: Failed to generate initial trap pages: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Plugin deactivation handler.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function deactivate(): void {
		PluginCache::clear_transients();
		PluginCache::clear_di_cache();
		flush_rewrite_rules();

		self::unschedule_cron_events();
	}

	/**
	 * Unschedule all plugin cron events, including legacy names from prior renames.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function unschedule_cron_events(): void {
		$hooks = array(
			// Current hook names.
			'jtzl_bm_maintenance_cron',
			'jtzl_bm_maxmind_update_cron',
			'jtzl_bm_cloudflare_update_cron',
			Database\Migrator::BACKFILL_RETRY_HOOK,
			// Legacy hook names from the jtzl_bl_* era.
			'jtzl_bl_maintenance_cron',
			'jtzl_bl_maxmind_update_cron',
			// Legacy hook names from the jtzl_bbg_* era.
			'jtzl_bbg_maintenance_cron',
			'jtzl_bbg_maxmind_update_cron',
		);
		foreach ( $hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}

	/**
	 * Handle the retry cron: re-run whatever step of the migration is unfinished.
	 *
	 * Scheduled hourly whenever the migration is incomplete after activation or
	 * a version upgrade. When the schema is missing or the version is stale it
	 * re-runs the full migration (which re-applies dbDelta — Branch B recovery);
	 * otherwise it just retries the pending backfill (Branch A). Removes itself
	 * once the migration is fully complete so there is no perpetual overhead.
	 *
	 * @return void
	 * @since 1.4.0
	 */
	public function handle_backfill_retry_cron(): void {
		global $wpdb;
		$migrator = new Database\Migrator( $wpdb );
		$status   = $migrator->status();

		if ( ! $status['columns_present'] || JTZL_BM_VERSION !== $status['stored_version'] ) {
			$migrator->run();
		} else {
			$migrator->run_pending_backfill();
		}

		// Unschedule the retry once the migration is fully complete.
		if ( ! $migrator->is_incomplete() ) {
			wp_clear_scheduled_hook( Database\Migrator::BACKFILL_RETRY_HOOK );
		}
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
			$retention_days = $this->container->get( 'config.jtzl_bm_retention_days' );

			$visit_repo = $this->container->get( 'JTZL\Bot_Maze\Repository\TrapVisitRepositoryInterface' );
			$score_repo = $this->container->get( 'JTZL\Bot_Maze\Repository\BotScoreRepositoryInterface' );

			$visit_repo->prune_old_entries( $retention_days );
			$score_repo->prune_stale_entries( $retention_days );

			if ( (bool) get_option( 'jtzl_bm_enabled', true ) ) {
				$generator = $this->container->get( 'JTZL\Bot_Maze\Service\TrapPageGenerator' );
				$generator->ensure_minimum_pages();
			}

			// Refresh the cached robots.txt verdict in the background so the
			// admin notice has fresh data without ever blocking a page render.
			// This is the periodic robots.txt health-check the bug report asked for.
			$this->container->get( 'JTZL\Bot_Maze\Service\RobotsTxtVerifier' )->verify( true );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is appropriate here.
			error_log( 'JTZL Bot Maze: Maintenance cron error: ' . $e->getMessage() );
		}
	}

	/**
	 * Flush rewrite rules if the activation flag is set.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( get_option( 'jtzl_bm_flush_rewrite_rules_flag' ) ) {
			flush_rewrite_rules();
			delete_option( 'jtzl_bm_flush_rewrite_rules_flag' );
		}
	}
}
