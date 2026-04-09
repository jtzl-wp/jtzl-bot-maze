<?php
/**
 * Dependency Injection Container Factory
 *
 * Configures and builds the PHP-DI container for the plugin.
 *
 * @package JTZL\Bot_Be_Gone
 * @since 1.0.0
 */

namespace JTZL\Bot_Be_Gone;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use DI\Container;
use DI\ContainerBuilder;
use JTZL\Bot_Be_Gone\Repository\TrapPageRepositoryInterface;
use JTZL\Bot_Be_Gone\Repository\TrapVisitRepositoryInterface;
use JTZL\Bot_Be_Gone\Repository\BotScoreRepositoryInterface;
use JTZL\Bot_Be_Gone\Service\IPTrackerInterface;
use JTZL\Bot_Be_Gone\Service\DnsResolverInterface;
use JTZL\Bot_Be_Gone\Service\CrawlerVerifierInterface;
use JTZL\Bot_Be_Gone\Admin\SettingsSanitizer;
use JTZL\Bot_Be_Gone\WordPress\ContextInterface;
use JTZL\Bot_Be_Gone\WordPress\WordPressContext;
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
			$cache_dir = JTZL_BBG_PLUGIN_DIR . 'var/cache';
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

			'JTZL\Bot_Be_Gone\Service\AssetManager'          => function () {
				return new Service\AssetManager(
					JTZL_BBG_PLUGIN_DIR,
					JTZL_BBG_PLUGIN_URL,
					JTZL_BBG_VERSION
				);
			},

			IPTrackerInterface::class                 => function () {
				$header  = (string) get_option( 'jtzl_bbg_trusted_proxy_header', '' );
				$raw_ips = (string) get_option( 'jtzl_bbg_trusted_proxy_ips', '' );
				$proxies = array_filter( array_map( 'trim', explode( ',', $raw_ips ) ) );
				return new Service\IPTracker( $header, $proxies );
			},

			DnsResolverInterface::class               => autowire( Service\DnsResolver::class ),
			CrawlerVerifierInterface::class           => autowire( Service\CrawlerVerifier::class )
				->constructor( get( DnsResolverInterface::class ) ),

			'JTZL\Bot_Be_Gone\Service\GeoIpService'          => function () {
				$provider_key = (string) get_option( 'jtzl_bbg_geo_provider', 'none' );

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

			'JTZL\Bot_Be_Gone\Service\BotDetector'           => autowire()
				->constructor(
					get( BotScoreRepositoryInterface::class ),
					get( TrapVisitRepositoryInterface::class ),
					get( IPTrackerInterface::class ),
					get( CrawlerVerifierInterface::class ),
					get( 'JTZL\Bot_Be_Gone\Service\GeoIpService' ),
					get( 'config.jtzl_bbg_points_per_visit' ),
					get( 'config.jtzl_bbg_depth_bonus' ),
					get( 'config.jtzl_bbg_block_threshold' )
				),

			'JTZL\Bot_Be_Gone\Service\DepthTracker'          => autowire()
				->constructor(
					get( TrapVisitRepositoryInterface::class ),
					get( IPTrackerInterface::class )
				),

			'JTZL\Bot_Be_Gone\Service\AnalyticsCollector'    => autowire()
				->constructor(
					get( TrapPageRepositoryInterface::class ),
					get( TrapVisitRepositoryInterface::class ),
					get( BotScoreRepositoryInterface::class ),
					get( 'JTZL\Bot_Be_Gone\Service\GeoIpService' )
				),

			'JTZL\Bot_Be_Gone\Service\TrapLinkInjector'      => autowire()
				->constructor(
					get( TrapPageRepositoryInterface::class ),
					get( 'config.jtzl_bbg_links_per_page' ),
					get( 'config.jtzl_bbg_injection_method' ),
					get( 'config.jtzl_bbg_enabled' ),
					get( 'config.jtzl_bbg_trap_base_path' ),
					get( ContextInterface::class )
				),

			'JTZL\Bot_Be_Gone\Service\RobotsTxtManager'      => function ( Container $c ) {
				$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
				$home_path = rtrim( $home_path, '/' );
				return new Service\RobotsTxtManager(
					$c->get( 'config.jtzl_bbg_robots_txt_enabled' ),
					$c->get( 'config.jtzl_bbg_trap_base_path' ),
					$home_path
				);
			},

			'JTZL\Bot_Be_Gone\Template\ContentPool'          => autowire(),

			'JTZL\Bot_Be_Gone\Service\ContentTemplateEngine' => autowire()
				->constructor(
					get( 'JTZL\Bot_Be_Gone\Template\ContentPool' )
				),

			'JTZL\Bot_Be_Gone\Service\TrapPageGenerator'     => autowire()
				->constructor(
					get( TrapPageRepositoryInterface::class ),
					get( 'JTZL\Bot_Be_Gone\Service\ContentTemplateEngine' ),
					get( 'config.jtzl_bbg_initial_page_count' ),
					get( 'config.jtzl_bbg_max_depth' ),
					get( 'config.jtzl_bbg_links_per_trap_page' ),
					get( 'config.jtzl_bbg_max_trap_pages' )
				),

			'JTZL\Bot_Be_Gone\Service\TrapRouter'            => autowire()
				->constructor(
					get( TrapPageRepositoryInterface::class ),
					get( 'JTZL\Bot_Be_Gone\Service\BotDetector' ),
					get( 'JTZL\Bot_Be_Gone\Service\TrapPageGenerator' ),
					get( 'config.jtzl_bbg_trap_base_path' ),
					get( ContextInterface::class )
				),

			// ============================================================
			// Configuration Values
			// ============================================================

			'config.jtzl_bbg_enabled'                      => function () {
				return (bool) get_option( 'jtzl_bbg_enabled', true );
			},

			'config.jtzl_bbg_trap_base_path'               => function () {
				$path = sanitize_title( (string) get_option( 'jtzl_bbg_trap_base_path', 'bbg' ) );
				return ! empty( $path ) ? $path : 'bbg';
			},

			'config.jtzl_bbg_links_per_page'               => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bbg_links_per_page', 3 ), 'jtzl_bbg_links_per_page' );
			},

			'config.jtzl_bbg_injection_method'             => function () {
				return SettingsSanitizer::sanitize_injection_method( get_option( 'jtzl_bbg_injection_method', 'content' ) );
			},

			'config.jtzl_bbg_max_depth'                    => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bbg_max_depth', 5 ), 'jtzl_bbg_max_depth' );
			},

			'config.jtzl_bbg_links_per_trap_page'          => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bbg_links_per_trap_page', 5 ), 'jtzl_bbg_links_per_trap_page' );
			},

			'config.jtzl_bbg_initial_page_count'           => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bbg_initial_page_count', 20 ), 'jtzl_bbg_initial_page_count' );
			},

			'config.jtzl_bbg_max_trap_pages'               => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bbg_max_trap_pages', 500 ), 'jtzl_bbg_max_trap_pages' );
			},

			'config.jtzl_bbg_points_per_visit'             => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bbg_points_per_visit', 25 ), 'jtzl_bbg_points_per_visit' );
			},

			'config.jtzl_bbg_depth_bonus'                  => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bbg_depth_bonus', 15 ), 'jtzl_bbg_depth_bonus', true );
			},

			'config.jtzl_bbg_tarpit_delay_ms'              => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bbg_tarpit_delay_ms', 1000 ), 'jtzl_bbg_tarpit_delay_ms' );
			},

			'config.jtzl_bbg_block_threshold'              => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bbg_block_threshold', 100 ), 'jtzl_bbg_block_threshold' );
			},

			'config.jtzl_bbg_retention_days'               => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bbg_retention_days', 30 ), 'jtzl_bbg_retention_days' );
			},

			'config.jtzl_bbg_robots_txt_enabled'           => function () {
				return (bool) get_option( 'jtzl_bbg_robots_txt_enabled', true );
			},
		);
	}
}
