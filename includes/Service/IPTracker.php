<?php
/**
 * IP Tracker Service
 *
 * Handles client IP detection for bot tracking.
 *
 * @package Labyrinth
 */

namespace Labyrinth\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * IP Tracker class.
 *
 * Detects client IP addresses with proxy-aware logic.
 */
class IPTracker implements IPTrackerInterface {

	/**
	 * Trusted proxy header name (e.g. 'CF-Connecting-IP').
	 *
	 * @var string
	 */
	private string $trusted_header;

	/**
	 * Constructor.
	 *
	 * @param string $trusted_header Optional trusted proxy header name.
	 */
	public function __construct( string $trusted_header = '' ) {
		$this->trusted_header = $trusted_header;
	}

	/**
	 * Get the client's IP address.
	 *
	 * When a trusted proxy header is configured, reads the real client IP
	 * from that header directly. Otherwise, prioritizes REMOTE_ADDR and
	 * only falls back to proxy headers when REMOTE_ADDR is a private/local IP.
	 *
	 * @return string The client IP address.
	 */
	public function get_client_ip(): string {
		// If a trusted proxy header is configured, use it directly.
		if ( '' !== $this->trusted_header ) {
			$server_key = 'HTTP_' . str_replace( '-', '_', strtoupper( $this->trusted_header ) );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			if ( ! empty( $_SERVER[ $server_key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $server_key ] ) );
				// For X-Forwarded-For style headers, take the rightmost IP.
				if ( str_contains( $ip, ',' ) ) {
					$parts = explode( ',', $ip );
					$ip    = trim( end( $parts ) );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		$ip = '';

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

			// Only check proxy headers if REMOTE_ADDR is a private/local IP.
			if ( $this->is_private_ip( $ip ) ) {
				$ip = $this->get_forwarded_ip() ?? $ip;
			}
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotValidated

		// Validate IP address format.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		return $ip;
	}

	/**
	 * Generate a session identifier from IP + User Agent.
	 *
	 * @return string Session hash.
	 */
	public function generate_session_id(): string {
		$ip = $this->get_client_ip();
		$ua = $this->get_user_agent();

		if ( empty( $ip ) ) {
			return '';
		}

		return hash( 'sha256', $ip . '|' . $ua );
	}

	/**
	 * Generate a user agent hash.
	 *
	 * @return string SHA256 UA hash (64 hex chars).
	 */
	public function get_user_agent_hash(): string {
		$ua = $this->get_user_agent();
		return hash( 'sha256', $ua );
	}

	/**
	 * Get the raw user agent string.
	 *
	 * @return string User agent string.
	 */
	public function get_user_agent(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		}

		return '';
	}

	/**
	 * Get the referrer URL.
	 *
	 * @return string Referrer URL.
	 */
	public function get_referrer(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			return esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		return '';
	}

	/**
	 * Get the current request URI.
	 *
	 * @return string Request URI.
	 */
	public function get_request_uri(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		return '';
	}

	/**
	 * Check if an IP address is private/local.
	 *
	 * @param string $ip The IP address to check.
	 * @return bool True if the IP is private/local.
	 */
	private function is_private_ip( string $ip ): bool {
		return ! filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}

	/**
	 * Get the forwarded IP from proxy headers.
	 *
	 * @return string|null The forwarded IP or null if not found.
	 */
	private function get_forwarded_ip(): ?string {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded_ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			// Use rightmost IP — the one the trusted proxy actually appended.
			$ip = trim( end( $forwarded_ips ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotValidated

		return null;
	}
}
