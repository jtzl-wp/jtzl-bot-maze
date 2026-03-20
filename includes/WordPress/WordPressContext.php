<?php
/**
 * WordPress Context Implementation
 *
 * Production implementation that forwards to real WordPress functions.
 *
 * @package Labyrinth
 * @since 1.0.0
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
 *
 * @since 1.0.0
 */
class WordPressContext implements ContextInterface {

	/**
	 * Check if the current request is an admin page.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_admin(): bool {
		return is_admin();
	}

	/**
	 * Check if the current request is a feed.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_feed(): bool {
		return is_feed();
	}

	/**
	 * Check if the current request is a REST API request.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_rest_request(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Check if the current view is a singular post/page.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_singular(): bool {
		return is_singular();
	}

	/**
	 * Get the current post ID.
	 *
	 * @return int|false Post ID or false if not available.
	 * @since 1.0.0
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
	 * @since 1.0.0
	 */
	public function get_query_var( string $query_var, mixed $default_value = '' ): mixed {
		return get_query_var( $query_var, $default_value );
	}

	/**
	 * Get the home URL.
	 *
	 * @param string $path Optional path relative to home URL.
	 * @return string Full URL.
	 * @since 1.0.0
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
	 * @since 1.0.0
	 */
	public function add_rewrite_rule( string $regex, string $redirect, string $after = 'bottom' ): void {
		add_rewrite_rule( $regex, $redirect, $after );
	}

	/**
	 * Set the HTTP status header.
	 *
	 * @param int $code HTTP status code.
	 * @return void
	 * @since 1.0.0
	 */
	public function status_header( int $code ): void {
		status_header( $code );
	}

	/**
	 * Set the current query to 404.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function set_404(): void {
		global $wp_query;
		$wp_query->set_404();
	}

	/**
	 * Clear the 404 state on the current query.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function clear_404(): void {
		global $wp_query;
		$wp_query->is_404 = false;
	}

	/**
	 * Add a filter hook callback.
	 *
	 * @param string   $hook     Filter hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of accepted arguments.
	 * @return void
	 * @since 1.0.0
	 */
	public function add_filter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		add_filter( $hook, $callback, $priority, $args );
	}

	/**
	 * Check if current user has a capability.
	 *
	 * @param string $capability Capability name.
	 * @return bool
	 * @since 1.0.0
	 */
	public function current_user_can( string $capability ): bool {
		return current_user_can( $capability );
	}

	/**
	 * Get an option value.
	 *
	 * @param string $option        Option name.
	 * @param mixed  $default_value Default value.
	 * @return mixed Option value.
	 * @since 1.0.0
	 */
	public function get_option( string $option, mixed $default_value = false ): mixed {
		return get_option( $option, $default_value );
	}

	/**
	 * Generate a random integer.
	 *
	 * @param int|null $min Minimum value.
	 * @param int|null $max Maximum value.
	 * @return int
	 * @since 1.0.0
	 */
	public function wp_rand( ?int $min = null, ?int $max = null ): int {
		return wp_rand( $min, $max );
	}

	/**
	 * Determine whether headers were already sent.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function headers_sent(): bool {
		return headers_sent();
	}

	/**
	 * Send a raw HTTP header.
	 *
	 * @param string $header        Header line.
	 * @param bool   $replace       Replace previous header.
	 * @param int    $response_code Optional response code.
	 * @return void
	 * @since 1.0.0
	 */
	public function header( string $header, bool $replace = true, int $response_code = 0 ): void {
		header( $header, $replace, $response_code );
	}

	/**
	 * Load a template file.
	 *
	 * @param string $template_file Path to template.
	 * @param bool   $load_once     Whether to use require_once.
	 * @param array  $args          Optional template args.
	 * @return void
	 * @since 1.0.0
	 */
	public function load_template( string $template_file, bool $load_once = true, array $args = array() ): void {
		load_template( $template_file, $load_once, $args );
	}

	/**
	 * Terminate execution and display an error message.
	 *
	 * @param string           $message Error message.
	 * @param string           $title   Error title.
	 * @param array|int|string $args    Optional arguments.
	 * @return void
	 * @since 1.0.0
	 */
	public function wp_die( string $message = '', string $title = '', array|int|string $args = array() ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Delegates directly to WordPress core.
		wp_die( $message, $title, $args );
	}

	/**
	 * Add an action hook callback.
	 *
	 * @param string   $hook     Action hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of accepted arguments.
	 * @return void
	 * @since 1.0.0
	 */
	public function add_action( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		add_action( $hook, $callback, $priority, $args );
	}

	/**
	 * Get the plugin directory path.
	 *
	 * @return string Plugin directory path with trailing slash.
	 * @since 1.0.0
	 */
	public function get_plugin_dir(): string {
		return LABYRINTH_PLUGIN_DIR;
	}
}
