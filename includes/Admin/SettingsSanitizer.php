<?php
/**
 * Settings Sanitizer
 *
 * Sanitization callbacks for WordPress Settings API.
 * Note: sanitize_base_path() has side effects (flags rewrite rules for flush).
 *
 * @package Labyrinth
 * @since 1.0.0
 */

namespace Labyrinth\Admin;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Settings Sanitizer Class
 *
 * Provides static sanitization methods for WordPress Settings API callbacks.
 * Most methods are pure; sanitize_base_path() reads/writes options to flag rewrite rule flushes.
 *
 * @since 1.0.0
 */
class SettingsSanitizer {

	/**
	 * Maximum allowed numeric values by option key.
	 *
	 * Server-side caps prevent accidental resource blowups if options are
	 * manipulated outside the admin UI.
	 *
	 * @var array<string, int>
	 * @since 1.0.0
	 */
	private const OPTION_MAX = array(
		'lby_links_per_page'      => 10,
		'lby_max_depth'           => 10,
		'lby_links_per_trap_page' => 10,
		'lby_initial_page_count'  => 100,
		'lby_points_per_visit'    => 1000,
		'lby_depth_bonus'         => 1000,
		'lby_block_threshold'     => 10000,
		'lby_retention_days'      => 365,
	);

	/**
	 * Sanitize a positive integer value (>= 1).
	 *
	 * @param mixed $value The value to sanitize.
	 * @return int The sanitized value.
	 * @since 1.0.0
	 */
	public static function sanitize_positive_integer( $value ): int {
		return max( 1, absint( $value ) );
	}

	/**
	 * Sanitize a positive integer and enforce an upper bound.
	 *
	 * @param mixed $value The value to sanitize.
	 * @param int   $max   Maximum allowed value.
	 * @return int The sanitized value.
	 * @since 1.0.0
	 */
	public static function sanitize_positive_integer_with_max( $value, int $max ): int {
		return min( max( 1, absint( $value ) ), max( 1, $max ) );
	}

	/**
	 * Sanitize a non-negative integer value (>= 0).
	 *
	 * @param mixed $value The value to sanitize.
	 * @return int The sanitized value.
	 * @since 1.0.0
	 */
	public static function sanitize_non_negative_integer( $value ): int {
		return absint( $value );
	}

	/**
	 * Sanitize a non-negative integer and enforce an upper bound.
	 *
	 * @param mixed $value The value to sanitize.
	 * @param int   $max   Maximum allowed value.
	 * @return int The sanitized value.
	 * @since 1.0.0
	 */
	public static function sanitize_non_negative_integer_with_max( $value, int $max ): int {
		return min( absint( $value ), max( 0, $max ) );
	}

	/**
	 * Sanitize a numeric option using centralized option bounds.
	 *
	 * @param mixed  $value      The value to sanitize.
	 * @param string $option_key Option name used to resolve max value.
	 * @param bool   $allow_zero Whether zero is a valid value.
	 * @return int The sanitized value.
	 * @since 1.0.0
	 */
	public static function sanitize_option_bounded_integer( $value, string $option_key, bool $allow_zero = false ): int {
		$max = self::OPTION_MAX[ $option_key ] ?? PHP_INT_MAX;

		if ( $allow_zero ) {
			return self::sanitize_non_negative_integer_with_max( $value, $max );
		}

		return self::sanitize_positive_integer_with_max( $value, $max );
	}

	/**
	 * Sanitize a checkbox value.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return bool The sanitized value.
	 * @since 1.0.0
	 */
	public static function sanitize_checkbox( $value ): bool {
		return (bool) $value;
	}

	/**
	 * Sanitize the enabled checkbox and flag rewrite rules for flush on change.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return bool The sanitized value.
	 * @since 1.0.0
	 */
	public static function sanitize_enabled( $value ): bool {
		$new_value = (bool) $value;
		$old_value = (bool) get_option( 'lby_enabled', true );

		if ( $new_value !== $old_value ) {
			update_option( 'lby_flush_rewrite_rules_flag', true );
		}

		return $new_value;
	}

	/**
	 * Sanitize the trap base path.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return string The sanitized value.
	 * @since 1.0.0
	 */
	public static function sanitize_base_path( $value ): string {
		$path = sanitize_title( $value );

		if ( empty( $path ) ) {
			$path = 'lby';
		}

		// Flag rewrite rules for refresh.
		$old_value = get_option( 'lby_trap_base_path', 'lby' );
		if ( $path !== $old_value ) {
			update_option( 'lby_flush_rewrite_rules_flag', true );
		}

		return $path;
	}

	/**
	 * Sanitize the trusted proxy header.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return string The sanitized value.
	 * @since 1.0.0
	 */
	public static function sanitize_trusted_proxy_header( $value ): string {
		$valid = array( '', 'CF-Connecting-IP', 'X-Real-IP', 'True-Client-IP', 'X-Forwarded-For' );
		return in_array( $value, $valid, true ) ? $value : '';
	}

	/**
	 * Sanitize the injection method.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return string The sanitized value.
	 * @since 1.0.0
	 */
	public static function sanitize_injection_method( $value ): string {
		$valid = array( 'content', 'footer', 'both' );
		return in_array( $value, $valid, true ) ? $value : 'content';
	}

	/**
	 * Sanitize the blocking behavior.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return string The sanitized value.
	 * @since 1.0.0
	 */
	public static function sanitize_blocking_behavior( $value ): string {
		$valid = array( 'block', 'tarpit_light', 'tarpit' );
		return in_array( $value, $valid, true ) ? $value : 'tarpit_light';
	}
}
