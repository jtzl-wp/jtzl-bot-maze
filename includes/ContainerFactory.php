<?php
/**
 * Dependency Injection Container Factory
 *
 * Configures and builds the PHP-DI container for the plugin.
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
use DI\ContainerBuilder;
use JTZL\Bot_Maze\Repository\TrapPageRepositoryInterface;
use JTZL\Bot_Maze\Repository\TrapVisitRepositoryInterface;
use JTZL\Bot_Maze\Repository\BotScoreRepositoryInterface;
use JTZL\Bot_Maze\Repository\AbuseIpDbReportQueueRepositoryInterface;
use JTZL\Bot_Maze\Service\IPTrackerInterface;
use JTZL\Bot_Maze\Service\DnsResolverInterface;
use JTZL\Bot_Maze\Service\CrawlerVerifierInterface;
use JTZL\Bot_Maze\Service\AbuseIpDbClientInterface;
use JTZL\Bot_Maze\Service\AbuseIpDbReporterInterface;
use JTZL\Bot_Maze\Admin\SettingsSanitizer;
use JTZL\Bot_Maze\WordPress\ContextInterface;
use JTZL\Bot_Maze\WordPress\WordPressContext;
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
			$cache_dir = JTZL_BM_PLUGIN_DIR . 'var/cache';
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

			\wpdb::class                                   => function () {
				global $wpdb;
				return $wpdb;
			},

			ContextInterface::class                        => autowire( WordPressContext::class ),

			// ============================================================
			// Repository Layer
			// ============================================================

			TrapPageRepositoryInterface::class             => autowire( Repository\TrapPageRepository::class ),
			TrapVisitRepositoryInterface::class            => autowire( Repository\TrapVisitRepository::class ),
			BotScoreRepositoryInterface::class             => autowire( Repository\BotScoreRepository::class ),
			AbuseIpDbReportQueueRepositoryInterface::class => autowire( Repository\AbuseIpDbReportQueueRepository::class ),

			// ============================================================
			// Service Layer
			// ============================================================

			'JTZL\Bot_Maze\Service\AssetManager'           => function () {
				return new Service\AssetManager(
					JTZL_BM_PLUGIN_DIR,
					JTZL_BM_PLUGIN_URL,
					JTZL_BM_VERSION
				);
			},

			IPTrackerInterface::class                      => function () {
				$header  = (string) get_option( 'jtzl_bm_trusted_proxy_header', '' );
				$raw_ips = (string) get_option( 'jtzl_bm_trusted_proxy_ips', '' );
				$proxies = array_values( array_filter( array_map( 'trim', explode( ',', $raw_ips ) ) ) );

				// Selecting the Cloudflare preset without a manual override
				// auto-loads Cloudflare's published edge ranges, so admins
				// never have to paste CIDRs to use the most common CDN.
				if ( empty( $proxies ) && 'CF-Connecting-IP' === $header ) {
					$proxies = Data\CloudflareIpRanges::all();
				}

				return new Service\IPTracker( $header, $proxies );
			},

			'JTZL\Bot_Maze\Service\CloudflareDetector'     => autowire(),

			DnsResolverInterface::class                    => autowire( Service\DnsResolver::class ),
			CrawlerVerifierInterface::class                => autowire( Service\CrawlerVerifier::class )
				->constructor( get( DnsResolverInterface::class ) ),

			'JTZL\Bot_Maze\Service\GeoIpService'           => function () {
				$provider_key = (string) get_option( 'jtzl_bm_geo_provider', 'none' );

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

			AbuseIpDbReporterInterface::class              => autowire( Service\AbuseIpDbReporter::class )
				->constructor(
					get( AbuseIpDbReportQueueRepositoryInterface::class ),
					get( 'config.jtzl_bm_abuseipdb_enabled' ),
					get( 'config.jtzl_bm_abuseipdb_api_key' )
				),

			AbuseIpDbClientInterface::class                => autowire( Service\AbuseIpDbClient::class ),
			Service\AbuseIpDbReportProcessor::class        => autowire()
				->constructor(
					get( AbuseIpDbReportQueueRepositoryInterface::class ),
					get( AbuseIpDbClientInterface::class ),
					get( BotScoreRepositoryInterface::class ),
					get( 'config.jtzl_bm_abuseipdb_api_key' )
				),
			Service\AbuseIpDbScheduler::class              => autowire()
				->constructor( get( AbuseIpDbReportQueueRepositoryInterface::class ) ),
			Service\AbuseIpDbStatus::class                 => autowire()
				->constructor( get( AbuseIpDbReportQueueRepositoryInterface::class ) ),

			'JTZL\Bot_Maze\Service\BotDetector'            => autowire()
				->constructor(
					get( BotScoreRepositoryInterface::class ),
					get( TrapVisitRepositoryInterface::class ),
					get( IPTrackerInterface::class ),
					get( CrawlerVerifierInterface::class ),
					get( 'JTZL\Bot_Maze\Service\GeoIpService' ),
					get( AbuseIpDbReporterInterface::class ),
					get( 'config.jtzl_bm_points_per_visit' ),
					get( 'config.jtzl_bm_depth_bonus' ),
					get( 'config.jtzl_bm_block_threshold' )
				),

			'JTZL\Bot_Maze\Service\DepthTracker'           => autowire()
				->constructor(
					get( TrapVisitRepositoryInterface::class ),
					get( IPTrackerInterface::class )
				),

			'JTZL\Bot_Maze\Service\AnalyticsCollector'     => autowire()
				->constructor(
					get( TrapPageRepositoryInterface::class ),
					get( TrapVisitRepositoryInterface::class ),
					get( BotScoreRepositoryInterface::class ),
					get( 'JTZL\Bot_Maze\Service\GeoIpService' )
				),

			'JTZL\Bot_Maze\Service\TrapLinkInjector'       => autowire()
				->constructor(
					get( TrapPageRepositoryInterface::class ),
					get( 'config.jtzl_bm_links_per_page' ),
					get( 'config.jtzl_bm_injection_method' ),
					get( 'config.jtzl_bm_enabled' ),
					get( 'config.jtzl_bm_trap_base_path' ),
					get( ContextInterface::class )
				),

			'JTZL\Bot_Maze\Service\RobotsTxtManager'       => function ( Container $c ) {
				$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
				$home_path = rtrim( $home_path, '/' );
				return new Service\RobotsTxtManager(
					$c->get( 'config.jtzl_bm_robots_txt_enabled' ),
					$c->get( 'config.jtzl_bm_trap_base_path' ),
					$home_path
				);
			},

			'JTZL\Bot_Maze\Service\RobotsTxtVerifier'      => function ( Container $c ) {
				return new Service\RobotsTxtVerifier(
					$c->get( 'JTZL\Bot_Maze\Service\RobotsTxtManager' )
				);
			},

			'JTZL\Bot_Maze\Service\RobotsTxtFileWriter'    => function () {
				return new Service\RobotsTxtFileWriter();
			},

			'JTZL\Bot_Maze\Admin\RobotsTxtSetupNotice'     => function ( Container $c ) {
				return new Admin\RobotsTxtSetupNotice(
					$c->get( 'JTZL\Bot_Maze\Service\RobotsTxtVerifier' ),
					$c->get( 'JTZL\Bot_Maze\Service\RobotsTxtFileWriter' )
				);
			},

			'JTZL\Bot_Maze\Admin\MigrationHealthCheck'     => function ( Container $c ) {
				return new Admin\MigrationHealthCheck(
					new Database\Migrator( $c->get( \wpdb::class ) )
				);
			},

			'JTZL\Bot_Maze\Template\ContentPool'           => autowire(),

			'JTZL\Bot_Maze\Service\ContentTemplateEngine'  => autowire()
				->constructor(
					get( 'JTZL\Bot_Maze\Template\ContentPool' )
				),

			'JTZL\Bot_Maze\Service\TrapPageGenerator'      => autowire()
				->constructor(
					get( TrapPageRepositoryInterface::class ),
					get( 'JTZL\Bot_Maze\Service\ContentTemplateEngine' ),
					get( 'config.jtzl_bm_initial_page_count' ),
					get( 'config.jtzl_bm_max_depth' ),
					get( 'config.jtzl_bm_links_per_trap_page' ),
					get( 'config.jtzl_bm_max_trap_pages' )
				),

			'JTZL\Bot_Maze\Service\TrapRouter'             => autowire()
				->constructor(
					get( TrapPageRepositoryInterface::class ),
					get( 'JTZL\Bot_Maze\Service\BotDetector' ),
					get( 'JTZL\Bot_Maze\Service\TrapPageGenerator' ),
					get( 'config.jtzl_bm_trap_base_path' ),
					get( ContextInterface::class )
				),

			// ============================================================
			// Configuration Values
			// ============================================================

			'config.jtzl_bm_enabled'                       => function () {
				return (bool) get_option( 'jtzl_bm_enabled', true );
			},

			'config.jtzl_bm_trap_base_path'                => function () {
				$path = sanitize_title( (string) get_option( 'jtzl_bm_trap_base_path', 'jbm' ) );
				return ! empty( $path ) ? $path : 'jbm';
			},

			'config.jtzl_bm_links_per_page'                => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bm_links_per_page', 3 ), 'jtzl_bm_links_per_page' );
			},

			'config.jtzl_bm_injection_method'              => function () {
				return SettingsSanitizer::sanitize_injection_method( get_option( 'jtzl_bm_injection_method', 'content' ) );
			},

			'config.jtzl_bm_max_depth'                     => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bm_max_depth', 5 ), 'jtzl_bm_max_depth' );
			},

			'config.jtzl_bm_links_per_trap_page'           => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bm_links_per_trap_page', 5 ), 'jtzl_bm_links_per_trap_page' );
			},

			'config.jtzl_bm_initial_page_count'            => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bm_initial_page_count', 20 ), 'jtzl_bm_initial_page_count' );
			},

			'config.jtzl_bm_max_trap_pages'                => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bm_max_trap_pages', 500 ), 'jtzl_bm_max_trap_pages' );
			},

			'config.jtzl_bm_points_per_visit'              => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bm_points_per_visit', 25 ), 'jtzl_bm_points_per_visit' );
			},

			'config.jtzl_bm_depth_bonus'                   => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bm_depth_bonus', 15 ), 'jtzl_bm_depth_bonus', true );
			},

			'config.jtzl_bm_tarpit_delay_ms'               => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bm_tarpit_delay_ms', 1000 ), 'jtzl_bm_tarpit_delay_ms' );
			},

			'config.jtzl_bm_block_threshold'               => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bm_block_threshold', 100 ), 'jtzl_bm_block_threshold' );
			},

			'config.jtzl_bm_retention_days'                => function () {
				return SettingsSanitizer::sanitize_option_bounded_integer( get_option( 'jtzl_bm_retention_days', 30 ), 'jtzl_bm_retention_days' );
			},

			'config.jtzl_bm_robots_txt_enabled'            => function () {
				return (bool) get_option( 'jtzl_bm_robots_txt_enabled', true );
			},

			'config.jtzl_bm_abuseipdb_enabled'             => function () {
				return (bool) get_option( 'jtzl_bm_abuseipdb_enabled', false );
			},

			// The key row is deliberately not autoloaded, so reading it costs a
			// query. Both consumers treat a disabled integration as inert, so the
			// autoloaded consent flag decides whether that query is worth making.
			'config.jtzl_bm_abuseipdb_api_key'             => function () {
				if ( ! (bool) get_option( 'jtzl_bm_abuseipdb_enabled', false ) ) {
					return '';
				}

				return (string) get_option( 'jtzl_bm_abuseipdb_api_key', '' );
			},
		);
	}
}
