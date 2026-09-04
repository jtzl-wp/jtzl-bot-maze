<?php
/**
 * Settings Sanitizer
 *
 * Sanitization callbacks for WordPress Settings API.
 * Note: sanitize_base_path() has side effects (flags rewrite rules for flush).
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze\Admin;

use JTZL\Bot_Maze\Service\Cidr;

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
		'jtzl_bm_links_per_page'      => 10,
		'jtzl_bm_max_depth'           => 10,
		'jtzl_bm_links_per_trap_page' => 10,
		'jtzl_bm_initial_page_count'  => 100,
		'jtzl_bm_max_trap_pages'      => 5000,
		'jtzl_bm_points_per_visit'    => 1000,
		'jtzl_bm_depth_bonus'         => 1000,
		'jtzl_bm_block_threshold'     => 10000,
		'jtzl_bm_tarpit_delay_ms'     => 5000,
		'jtzl_bm_retention_days'      => 365,
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
		$old_value = (bool) get_option( 'jtzl_bm_enabled', true );

		if ( $new_value !== $old_value ) {
			update_option( 'jtzl_bm_flush_rewrite_rules_flag', true );
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
			$path = 'jbm';
		}

		// Flag rewrite rules for refresh.
		$old_value = get_option( 'jtzl_bm_trap_base_path', 'jbm' );
		if ( $path !== $old_value ) {
			update_option( 'jtzl_bm_flush_rewrite_rules_flag', true );
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
	 * Sanitize trusted proxy IPs/CIDRs.
	 *
	 * @param mixed $value Comma-separated IPs/CIDRs.
	 * @return string Cleaned comma-separated string.
	 * @since 1.0.0
	 */
	public static function sanitize_trusted_proxy_ips( $value ): string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		$valid = array();
		foreach ( explode( ',', $value ) as $token ) {
			$normalized = Cidr::normalize( $token );
			if ( null !== $normalized ) {
				$valid[] = $normalized;
			}
		}

		return implode( ', ', $valid );
	}

	/**
	 * Sanitize the geographic tracking provider.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return string The sanitized value.
	 * @since 1.0.0
	 */
	public static function sanitize_geo_provider( $value ): string {
		$valid = array( 'none', 'ip_api', 'maxmind' );
		return in_array( $value, $valid, true ) ? $value : 'none';
	}

	/**
	 * Sanitize the MaxMind license key.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return string The sanitized value.
	 * @since 1.0.0
	 */
	public static function sanitize_maxmind_license_key( $value ): string {
		// Explicit clear via checkbox — also remove the downloaded database.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the Settings API.
		if ( ! empty( $_POST['jtzl_bm_clear_maxmind_key'] ) ) {
			\JTZL\Bot_Maze\Service\MaxMindDatabaseManager::cleanup();
			delete_option( 'jtzl_bm_maxmind_last_updated' );
			return '';
		}

		$key = sanitize_text_field( $value );

		// Empty submission preserves the existing key (UI sends empty to avoid exposing it).
		if ( '' === $key ) {
			return (string) get_option( 'jtzl_bm_maxmind_license_key', '' );
		}

		return preg_match( '/^[a-zA-Z0-9_]+$/', $key ) ? $key : (string) get_option( 'jtzl_bm_maxmind_license_key', '' );
	}

	/**
	 * Sanitize the administrator-owned AbuseIPDB API key.
	 *
	 * @param mixed $value The submitted value.
	 * @return string The replacement, preserved, or cleared key.
	 */
	public static function sanitize_abuseipdb_api_key( $value ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the Settings API.
		if ( ! empty( $_POST['jtzl_bm_clear_abuseipdb_key'] ) ) {
			return '';
		}

		$key = sanitize_text_field( $value );
		if ( '' === $key ) {
			return (string) get_option( 'jtzl_bm_abuseipdb_api_key', '' );
		}

		return $key;
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
		return in_array( $value, $valid, true ) ? $value : 'block';
	}
}
