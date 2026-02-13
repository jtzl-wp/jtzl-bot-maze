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
use Labyrinth\WordPress\ContextInterface;
use Labyrinth\WordPress\WordPressContext;
use function DI\autowire;

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
		);
	}
}
