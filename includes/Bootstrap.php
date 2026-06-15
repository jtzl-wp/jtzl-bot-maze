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

		// Admin pages and assets only mount in wp-admin.
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
		$wp->add_filter( 'robots_txt', array( $robots, 'modify_robots_txt' ), 10, 2 );
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
	 * @return void
	 * @since 1.0.0
	 */
	public function activate(): void {
		Database\Schema::create_tables();
		Database\Options::set_defaults();

		PluginCache::clear_di_cache();
		update_option( 'jtzl_bm_plugin_version', JTZL_BM_VERSION );
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
