<?php
/**
 * Dependency Injection Container Factory
 *
 * Configures and builds the PHP-DI container for the plugin.
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
use DI\ContainerBuilder;
use Labyrinth\Repository\TrapPageRepositoryInterface;
use Labyrinth\Repository\TrapVisitRepositoryInterface;
use Labyrinth\Repository\BotScoreRepositoryInterface;
use Labyrinth\Service\IPTrackerInterface;
use Labyrinth\Service\DnsResolverInterface;
use Labyrinth\Service\CrawlerVerifierInterface;
use Labyrinth\Admin\SettingsSanitizer;
use Labyrinth\WordPress\ContextInterface;
use Labyrinth\WordPress\WordPressContext;
use function DI\autowire;
use function DI\get;

/**
 * Dependency Injection Container Factory
 *
 * Configures and builds the PHP-DI container for the plugin.
 *
 * @since 1.0.0
 */
class ContainerFactory {

	/**
	 * Create and configure the container.
	 *
	 * @param bool|null $enable_compilation Override compilation flag. Defaults to !WP_DEBUG.
	 * @return Container The configured container instance.
	 * @since 1.0.0
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
	 * @since 1.0.0
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

			IPTrackerInterface::class                 => function () {
				$header  = (string) get_option( 'lby_trusted_proxy_header', '' );
				$raw_ips = (string) get_option( 'lby_trusted_proxy_ips', '' );
				$proxies = array_filter( array_map( 'trim', explode( ',', $raw_ips ) ) );
				return new Service\IPTracker( $header, $proxies );
			},

			DnsResolverInterface::class               => autowire( Service\DnsResolver::class ),
			CrawlerVerifierInterface::class           => autowire( Service\CrawlerVerifier::class )
				->constructor( get( DnsResolverInterface::class ) ),

			'Labyrinth\Service\GeoIpService'          => function () {
				$provider_key = (string) get_option( 'lby_geo_provider', 'none' );

				$provider = null;
				if ( 'ip_api' === $provider_key ) {
					$provider = new Service\IpApiGeoIpProvider();
				} elseif ( 'maxmind' === $provider_key ) {
					$db_path = Service\MaxMindDatabaseManager::get_database_path();
					if ( file_exists( $db_path ) ) {
						$provider = new Service\MaxMindGeoIpProvider( $db_path );
					}
				}

				return new Service\GeoIpService( null !== $provider, $provider );
			},

			'Labyrinth\Service\BotDetector'           => autowire()
				->constructor(
					get( BotScoreRepositoryInterface::class ),
					get( TrapVisitRepositoryInterface::class ),
					get( IPTrackerInterface::class ),
					get( CrawlerVerifierInterface::class ),
					get( 'Labyrinth\Service\GeoIpService' ),
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
					get( 'Labyrinth\Service\GeoIpService' )
				),

			'Labyrinth\Service\TrapLinkInjector'      => autowire()
				->constructor(
					get( TrapPageRepositoryInterface::class ),
					get( 'config.lby_links_per_page' ),
					get( 'config.lby_injection_method' ),
					get( 'config.lby_enabled' ),
					get( 'config.lby_trap_base_path' ),
					get( ContextInterface::class )
				),

			'Labyrinth\Service\RobotsTxtManager'      => function ( Container $c ) {
				$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
				$home_path = rtrim( $home_path, '/' );
				return new Service\RobotsTxtManager(
					$c->get( 'config.lby_robots_txt_enabled' ),
					$c->get( 'config.lby_trap_base_path' ),
					$home_path
				);
			},

			'Labyrinth\Template\ContentPool'          => autowire(),

			'Labyrinth\Service\ContentTemplateEngine' => autowire()
				->constructor(
					get( 'Labyrinth\Template\ContentPool' )
				),

			'Labyrinth\Service\TrapPageGenerator'     => autowire()
				->constructor(
					get( TrapPageRepositoryInterface::class ),
					get( 'Labyrinth\Service\ContentTemplateEngine' ),
					get( 'config.lby_initial_page_count' ),
					get( 'config.lby_max_depth' ),
					get( 'config.lby_links_per_trap_page' ),
					get( 'config.lby_max_trap_pages' )
				),

			'Labyrinth\Service\TrapRouter'            => autowire()
				->constructor(
					get( TrapPageRepositoryInterface::class ),
					get( 'Labyrinth\Service\BotDetector' ),
					get( 'Labyrinth\Service\TrapPageGenerator' ),
					get( 'config.lby_trap_base_path' ),
					get( 'config.lby_max_depth' ),
					get( ContextInterface::class )
				),

			// ============================================================
			// Configuration Values
			// ============================================================

			'config.lby_enabled'                      => function () {
				return (bool) get_option( 'lby_enabled', true );
			},

			'config.lby_trap_base_path'               => function () {
				$path = sanitize_title( (string) get_option( 'lby_trap_base_path', 'lby' ) );
				return ! empty( $path ) ? $path : 'lby';
			},

			'config.lby_links_per_page'               => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'lby_links_per_page', 3 ), 'lby_links_per_page' );
			},

			'config.lby_injection_method'             => function () {
				return SettingsSanitizer::sanitize_injection_method( get_option( 'lby_injection_method', 'content' ) );
			},

			'config.lby_max_depth'                    => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'lby_max_depth', 5 ), 'lby_max_depth' );
			},

			'config.lby_links_per_trap_page'          => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'lby_links_per_trap_page', 5 ), 'lby_links_per_trap_page' );
			},

			'config.lby_initial_page_count'           => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'lby_initial_page_count', 20 ), 'lby_initial_page_count' );
			},

			'config.lby_max_trap_pages'               => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'lby_max_trap_pages', 500 ), 'lby_max_trap_pages' );
			},

			'config.lby_points_per_visit'             => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'lby_points_per_visit', 25 ), 'lby_points_per_visit' );
			},

			'config.lby_depth_bonus'                  => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'lby_depth_bonus', 15 ), 'lby_depth_bonus', true );
			},

			'config.lby_tarpit_delay_ms'              => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'lby_tarpit_delay_ms', 1000 ), 'lby_tarpit_delay_ms' );
			},

			'config.lby_block_threshold'              => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'lby_block_threshold', 100 ), 'lby_block_threshold' );
			},

			'config.lby_retention_days'               => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'lby_retention_days', 30 ), 'lby_retention_days' );
			},

			'config.lby_robots_txt_enabled'           => function () {
				return (bool) get_option( 'lby_robots_txt_enabled', true );
			},
		);
	}
}
