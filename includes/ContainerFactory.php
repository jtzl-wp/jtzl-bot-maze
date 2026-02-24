<?php
/**
 * Dependency Injection Container Factory
 *
 * Configures and builds the PHP-DI container for the plugin.
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
use DI\ContainerBuilder;
use Labyrinth\Repository\TrapPageRepositoryInterface;
use Labyrinth\Repository\TrapVisitRepositoryInterface;
use Labyrinth\Repository\BotScoreRepositoryInterface;
use Labyrinth\Repository\RequestLogRepositoryInterface;
use Labyrinth\Service\IPTrackerInterface;
use Labyrinth\Service\DnsResolverInterface;
use Labyrinth\Service\CrawlerVerifierInterface;
use Labyrinth\WordPress\ContextInterface;
use Labyrinth\WordPress\WordPressContext;
use function DI\autowire;
use function DI\get;

/**
 * Dependency Injection Container Factory
 *
 * Configures and builds the PHP-DI container for the plugin.
 */
class ContainerFactory {

	/**
	 * Create and configure the container.
	 *
	 * @param bool|null $enable_compilation Override compilation flag. Defaults to !WP_DEBUG.
	 * @return Container The configured container instance.
	 */
	public static function create( ?bool $enable_compilation = null ): Container {
		$builder = new ContainerBuilder();

		// Enable compilation for production.
		$should_compile = $enable_compilation ?? ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG );
		if ( $should_compile ) {
			$cache_dir = LABYRINTH_PLUGIN_DIR . 'var/cache';
			if ( ! file_exists( $cache_dir ) ) {
				wp_mkdir_p( $cache_dir );
			}
			$builder->enableCompilation( $cache_dir );
		}

		// Enable autowiring for automatic dependency resolution.
		$builder->useAutowiring( true );

		// Add service definitions.
		$builder->addDefinitions( self::get_definitions() );

		return $builder->build();
	}

	/**
	 * Get container definitions.
	 *
	 * Defines all service bindings for repositories, services, and configuration.
	 *
	 * @return array Container definitions.
	 */
	private static function get_definitions(): array {
		return array(
			// ============================================================
			// WordPress Core
			// ============================================================

			\wpdb::class                              => function () {
				global $wpdb;
				return $wpdb;
			},

			ContextInterface::class                   => autowire( WordPressContext::class ),

			// ============================================================
			// Repository Layer
			// ============================================================

			TrapPageRepositoryInterface::class        => autowire( Repository\TrapPageRepository::class ),
			TrapVisitRepositoryInterface::class       => autowire( Repository\TrapVisitRepository::class ),
			BotScoreRepositoryInterface::class        => autowire( Repository\BotScoreRepository::class ),
			RequestLogRepositoryInterface::class      => autowire( Repository\RequestLogRepository::class ),

			// ============================================================
			// Service Layer
			// ============================================================

			'Labyrinth\Service\AssetManager'          => function () {
				return new Service\AssetManager(
					LABYRINTH_PLUGIN_DIR,
					LABYRINTH_PLUGIN_URL,
					LABYRINTH_VERSION
				);
			},

			IPTrackerInterface::class                 => autowire( Service\IPTracker::class ),

			DnsResolverInterface::class               => autowire( Service\DnsResolver::class ),
			CrawlerVerifierInterface::class           => autowire( Service\CrawlerVerifier::class )
				->constructor( get( DnsResolverInterface::class ) ),

			'Labyrinth\Service\BotDetector'           => autowire()
				->constructor(
					get( BotScoreRepositoryInterface::class ),
					get( TrapVisitRepositoryInterface::class ),
					get( RequestLogRepositoryInterface::class ),
					get( IPTrackerInterface::class ),
					get( CrawlerVerifierInterface::class ),
					get( 'config.lby_points_per_visit' ),
					get( 'config.lby_depth_bonus' ),
					get( 'config.lby_block_threshold' )
				),

			'Labyrinth\Service\DepthTracker'          => autowire()
				->constructor(
					get( TrapVisitRepositoryInterface::class ),
					get( IPTrackerInterface::class )
				),

			'Labyrinth\Service\AnalyticsCollector'    => autowire()
				->constructor(
					get( TrapPageRepositoryInterface::class ),
					get( TrapVisitRepositoryInterface::class ),
					get( BotScoreRepositoryInterface::class ),
					get( RequestLogRepositoryInterface::class )
				),

			// ============================================================
			// Configuration Values
			// ============================================================

			'config.lby_enabled'                      => function () {
				return (bool) get_option( 'lby_enabled', true );
			},

			'config.lby_trap_base_path'               => function () {
				return get_option( 'lby_trap_base_path', 'lby' );
			},

			'config.lby_links_per_page'               => function () {
				return (int) get_option( 'lby_links_per_page', 3 );
			},

			'config.lby_injection_method'             => function () {
				return get_option( 'lby_injection_method', 'content' );
			},

			'config.lby_max_depth'                    => function () {
				return (int) get_option( 'lby_max_depth', 5 );
			},

			'config.lby_links_per_trap_page'          => function () {
				return (int) get_option( 'lby_links_per_trap_page', 5 );
			},

			'config.lby_initial_page_count'           => function () {
				return (int) get_option( 'lby_initial_page_count', 20 );
			},

			'config.lby_points_per_visit'             => function () {
				return (int) get_option( 'lby_points_per_visit', 25 );
			},

			'config.lby_depth_bonus'                  => function () {
				return (int) get_option( 'lby_depth_bonus', 15 );
			},

			'config.lby_block_threshold'              => function () {
				return (int) get_option( 'lby_block_threshold', 100 );
			},

			'config.lby_log_retention_days'           => function () {
				return (int) get_option( 'lby_log_retention_days', 30 );
			},

			'config.lby_robots_txt_enabled'           => function () {
				return (bool) get_option( 'lby_robots_txt_enabled', true );
			},

			'config.lby_robots_sitemap_enabled'       => function () {
				return (bool) get_option( 'lby_robots_sitemap_enabled', false );
			},
		);
	}
}
