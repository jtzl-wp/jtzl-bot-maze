<?php
/**
 * WordPress Context Interface
 *
 * Abstracts WordPress global functions for testability.
 *
 * @package Labyrinth
 */

namespace Labyrinth\WordPress;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * WordPress Context Interface
 *
 * Provides access to WordPress conditional functions and context
 * without direct global function calls, enabling unit testing with fakes.
 */
interface ContextInterface {

	/**
	 * Check if the current request is an admin page.
	 *
	 * @return bool
	 */
	public function is_admin(): bool;

	/**
	 * Check if the current request is a feed.
	 *
	 * @return bool
	 */
	public function is_feed(): bool;

	/**
	 * Check if the current request is a REST API request.
	 *
	 * @return bool
	 */
	public function is_rest_request(): bool;

	/**
	 * Check if the current view is a singular post/page.
	 *
	 * @return bool
	 */
	public function is_singular(): bool;

	/**
	 * Get the current post ID.
	 *
	 * @return int|false Post ID or false if not available.
	 */
	public function get_the_id(): int|false;

	/**
	 * Get a query variable value.
	 *
	 * @param string $query_var     Query variable name.
	 * @param mixed  $default_value Default value.
	 * @return mixed Query variable value.
	 */
	public function get_query_var( string $query_var, mixed $default_value = '' ): mixed;

	/**
	 * Get the home URL.
	 *
	 * @param string $path Optional path relative to home URL.
	 * @return string Full URL.
	 */
	public function home_url( string $path = '' ): string;

	/**
	 * Add a rewrite rule.
	 *
	 * @param string $regex    Regular expression.
	 * @param string $redirect Redirect URL.
	 * @param string $after    Priority ('top' or 'bottom').
	 * @return void
	 */
	public function add_rewrite_rule( string $regex, string $redirect, string $after = 'bottom' ): void;

	/**
	 * Set the HTTP status header.
	 *
	 * @param int $code HTTP status code.
	 * @return void
	 */
	public function status_header( int $code ): void;

	/**
	 * Set the current query to 404.
	 *
	 * @return void
	 */
	public function set_404(): void;

	/**
	 * Clear the 404 state on the current query.
	 *
	 * @return void
	 */
	public function clear_404(): void;

	/**
	 * Add an action hook callback.
	 *
	 * @param string   $hook     Action hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of accepted arguments.
	 * @return void
	 */
	public function add_action( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void;

	/**
	 * Get the plugin directory path.
	 *
	 * @return string Plugin directory path with trailing slash.
	 */
	public function get_plugin_dir(): string;

	/**
	 * Register a filter hook callback.
	 *
	 * @param string   $hook     Filter hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of accepted arguments.
	 * @return void
	 */
	public function add_filter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void;
}
