<?php
/**
 * WordPress Context Implementation
 *
 * Production implementation that forwards to real WordPress functions.
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
 * WordPress Context
 *
 * Production implementation of ContextInterface that delegates
 * to real WordPress global functions.
 */
class WordPressContext implements ContextInterface {

	/**
	 * Check if the current request is an admin page.
	 *
	 * @return bool
	 */
	public function is_admin(): bool {
		return is_admin();
	}

	/**
	 * Check if the current request is a feed.
	 *
	 * @return bool
	 */
	public function is_feed(): bool {
		return is_feed();
	}

	/**
	 * Check if the current request is a REST API request.
	 *
	 * @return bool
	 */
	public function is_rest_request(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Check if the current view is a singular post/page.
	 *
	 * @return bool
	 */
	public function is_singular(): bool {
		return is_singular();
	}

	/**
	 * Get the current post ID.
	 *
	 * @return int|false Post ID or false if not available.
	 */
	public function get_the_id(): int|false {
		return get_the_ID();
	}

	/**
	 * Get a query variable value.
	 *
	 * @param string $query_var     Query variable name.
	 * @param mixed  $default_value Default value.
	 * @return mixed Query variable value.
	 */
	public function get_query_var( string $query_var, mixed $default_value = '' ): mixed {
		return get_query_var( $query_var, $default_value );
	}

	/**
	 * Get the home URL.
	 *
	 * @param string $path Optional path relative to home URL.
	 * @return string Full URL.
	 */
	public function home_url( string $path = '' ): string {
		return home_url( $path );
	}

	/**
	 * Add a rewrite rule.
	 *
	 * @param string $regex    Regular expression.
	 * @param string $redirect Redirect URL.
	 * @param string $after    Priority ('top' or 'bottom').
	 * @return void
	 */
	public function add_rewrite_rule( string $regex, string $redirect, string $after = 'bottom' ): void {
		add_rewrite_rule( $regex, $redirect, $after );
	}

	/**
	 * Set the HTTP status header.
	 *
	 * @param int $code HTTP status code.
	 * @return void
	 */
	public function status_header( int $code ): void {
		status_header( $code );
	}

	/**
	 * Set the current query to 404.
	 *
	 * @return void
	 */
	public function set_404(): void {
		global $wp_query;
		$wp_query->set_404();
	}

	/**
	 * Add an action hook callback.
	 *
	 * @param string   $hook     Action hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of accepted arguments.
	 * @return void
	 */
	public function add_action( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		add_action( $hook, $callback, $priority, $args );
	}

	/**
	 * Get the plugin directory path.
	 *
	 * @return string Plugin directory path with trailing slash.
	 */
	public function get_plugin_dir(): string {
		return LABYRINTH_PLUGIN_DIR;
	}

	/**
	 * Register a filter hook callback.
	 *
	 * @param string   $hook     Filter hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of accepted arguments.
	 * @return void
	 */
	public function add_filter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		add_filter( $hook, $callback, $priority, $args );
	}
}
