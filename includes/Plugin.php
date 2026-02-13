<?php
/**
 * Main Plugin Class
 *
 * Handles plugin initialization, dependency injection, and WordPress integration.
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

/**
 * Main Plugin Class
 *
 * Handles plugin initialization, dependency injection, and WordPress integration.
 */
class Plugin {

	/**
	 * The dependency injection container instance.
	 *
	 * @var Container|null
	 */
	private static $container = null;

	/**
	 * Get the dependency injection container.
	 *
	 * @return Container The container instance.
	 */
	public static function get_container(): Container {
		if ( null === self::$container ) {
			self::$container = ContainerFactory::create();
		}

		return self::$container;
	}

	/**
	 * Reset the container instance (for testing purposes).
	 *
	 * @return void
	 */
	public static function reset_container(): void {
		self::$container = null;
	}

	/**
	 * Set the container instance (for testing purposes).
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public static function set_container( Container $container ): void {
		self::$container = $container;
	}
}
