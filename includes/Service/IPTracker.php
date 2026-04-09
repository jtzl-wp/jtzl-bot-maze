<?php
/**
 * IP Tracker Service
 *
 * Handles client IP detection for bot tracking.
 *
 * @package JTZL\Bot_Be_Gone
 * @since 1.0.0
 */

namespace JTZL\Bot_Be_Gone\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * IP Tracker class.
 *
 * Detects client IP addresses with proxy-aware logic.
 *
 * @since 1.0.0
 */
class IPTracker implements IPTrackerInterface {

	/**
	 * Maximum stored length for user-agent strings.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private const MAX_USER_AGENT_LENGTH = 512;

	/**
	 * Maximum stored length for request URI values.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private const MAX_REQUEST_URI_LENGTH = 2048;

	/**
	 * Maximum stored length for referrer values.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private const MAX_REFERRER_LENGTH = 2048;

	/**
	 * Trusted proxy header name (e.g. 'CF-Connecting-IP').
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $trusted_header;

	/**
	 * Trusted proxy IP addresses and CIDR ranges.
	 *
	 * @var string[]
	 * @since 1.0.0
	 */
	private array $trusted_proxies;

	/**
	 * Constructor.
	 *
	 * @param string   $trusted_header  Optional trusted proxy header name.
	 * @param string[] $trusted_proxies Trusted proxy IPs/CIDRs. Empty = header is ignored (trust nothing).
	 * @since 1.0.0
	 */
	public function __construct( string $trusted_header = '', array $trusted_proxies = array() ) {
		$this->trusted_header  = $trusted_header;
		$this->trusted_proxies = $trusted_proxies;
	}

	/**
	 * Get the client's IP address.
	 *
	 * When a trusted proxy header and allowlist are configured, reads the
	 * real client IP from that header only if REMOTE_ADDR matches a trusted
	 * proxy. Otherwise returns REMOTE_ADDR directly.
	 *
	 * @return string The client IP address.
	 * @since 1.0.0
	 */
	public function get_client_ip(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$remote_addr = ! empty( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		// If a trusted proxy header is configured and REMOTE_ADDR is a trusted proxy, use the header.
		if ( '' !== $this->trusted_header && $this->is_trusted_proxy( $remote_addr ) ) {
			$server_key = 'HTTP_' . str_replace( '-', '_', strtoupper( $this->trusted_header ) );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			if ( ! empty( $_SERVER[ $server_key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $server_key ] ) );
				$ip = $this->extract_client_ip( $ip );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		// Fall back to REMOTE_ADDR without trusting any forwarding headers.
		if ( ! filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		return $remote_addr;
	}

	/**
	 * Generate a session identifier from IP + User Agent.
	 *
	 * @return string Session hash.
	 * @since 1.0.0
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
	 * @since 1.0.0
	 */
	public function get_user_agent_hash(): string {
		$ua = $this->get_user_agent();
		return hash( 'sha256', $ua );
	}

	/**
	 * Get the raw user agent string.
	 *
	 * @return string User agent string.
	 * @since 1.0.0
	 */
	public function get_user_agent(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$ua = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
			return substr( $ua, 0, self::MAX_USER_AGENT_LENGTH );
		}

		return '';
	}

	/**
	 * Get the referrer URL without query parameters.
	 *
	 * @return string Referrer URL.
	 * @since 1.0.0
	 */
	public function get_referrer(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referrer = $this->strip_query_string( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
			return substr( $referrer, 0, self::MAX_REFERRER_LENGTH );
		}

		return '';
	}

	/**
	 * Get the current request URI without query parameters.
	 *
	 * @return string Request URI.
	 * @since 1.0.0
	 */
	public function get_request_uri(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = $this->strip_query_string( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
			return substr( $request_uri, 0, self::MAX_REQUEST_URI_LENGTH );
		}

		return '';
	}

	/**
	 * Check whether REMOTE_ADDR is a trusted proxy.
	 *
	 * Returns false when the trusted_proxies list is empty — a header
	 * without an explicit allowlist is not trusted, preventing spoofing.
	 *
	 * @param string $ip The REMOTE_ADDR value.
	 * @return bool
	 * @since 1.0.0
	 */
	private function is_trusted_proxy( string $ip ): bool {
		if ( empty( $this->trusted_proxies ) ) {
			return false;
		}

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		foreach ( $this->trusted_proxies as $trusted ) {
			if ( $this->ip_matches_cidr( $ip, $trusted ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an IP matches a CIDR range or exact IP.
	 *
	 * @param string $ip   The IP address to test.
	 * @param string $cidr CIDR range (e.g. '172.64.0.0/13') or exact IP.
	 * @return bool
	 * @since 1.0.0
	 */
	private function ip_matches_cidr( string $ip, string $cidr ): bool {
		if ( ! str_contains( $cidr, '/' ) ) {
			return $ip === $cidr;
		}

		list( $subnet, $bits ) = explode( '/', $cidr, 2 );
		if ( ! ctype_digit( $bits ) ) {
			return false;
		}
		$bits = (int) $bits;

		// Reject cross-family comparisons and out-of-range prefix lengths.
		$ip_is_v6     = str_contains( $ip, ':' );
		$subnet_is_v6 = str_contains( $subnet, ':' );
		if ( $ip_is_v6 !== $subnet_is_v6 ) {
			return false;
		}
		$max_bits = $ip_is_v6 ? 128 : 32;
		if ( $bits < 0 || $bits > $max_bits ) {
			return false;
		}

		// IPv4.
		$ip_long     = ip2long( $ip );
		$subnet_long = ip2long( $subnet );

		if ( false !== $ip_long && false !== $subnet_long ) {
			if ( 0 === $bits ) {
				return true;
			}
			$mask = -1 << ( 32 - $bits );
			return ( $ip_long & $mask ) === ( $subnet_long & $mask );
		}

		// IPv6.
		$ip_bin     = inet_pton( $ip );
		$subnet_bin = inet_pton( $subnet );

		if ( false === $ip_bin || false === $subnet_bin ) {
			return false;
		}

		$full_bytes    = intdiv( $bits, 8 );
		$remaining_bits = $bits % 8;

		if ( substr( $ip_bin, 0, $full_bytes ) !== substr( $subnet_bin, 0, $full_bytes ) ) {
			return false;
		}

		if ( $remaining_bits > 0 ) {
			$mask = 0xFF << ( 8 - $remaining_bits );
			return ( ord( $ip_bin[ $full_bytes ] ) & $mask ) === ( ord( $subnet_bin[ $full_bytes ] ) & $mask );
		}

		return true;
	}

	/**
	 * Strip query strings and fragments from a URL-like value.
	 *
	 * @param string $value URL or path value.
	 * @return string Value without query strings or fragments.
	 * @since 1.0.0
	 */
	private function strip_query_string( string $value ): string {
		return (string) strtok( $value, '?#' );
	}

	/**
	 * Extract the real client IP from a forwarded header value.
	 *
	 * For single-value headers, returns the value directly. For multi-value
	 * headers (X-Forwarded-For), walks right-to-left and returns the first
	 * IP that is not in the trusted proxy list — the rightmost untrusted hop.
	 *
	 * @param string $value Header value (may be comma-separated).
	 * @return string Client IP candidate.
	 * @since 1.0.0
	 */
	private function extract_client_ip( string $value ): string {
		if ( ! str_contains( $value, ',' ) ) {
			return trim( $value );
		}

		$parts = array_map( 'trim', explode( ',', $value ) );

		// Walk right-to-left, skip trusted proxies, return first untrusted IP.
		for ( $i = count( $parts ) - 1; $i >= 0; $i-- ) {
			$candidate = $parts[ $i ];
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) && ! $this->is_trusted_proxy( $candidate ) ) {
				return $candidate;
			}
		}

		// All hops are trusted or invalid — return empty so the caller
		// falls back to REMOTE_ADDR rather than a spoofable leftmost value.
		return '';
	}
}
