<?php
/**
 * CIDR / IP Range Utilities
 *
 * Shared validation and normalization for IP addresses and CIDR ranges.
 *
 * @package JTZL\Bot_Maze
 * @since 1.1.0
 */

namespace JTZL\Bot_Maze\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * CIDR validation helper.
 *
 * Provides a single source of truth for validating trusted-proxy tokens,
 * used both when sanitizing user-entered overrides and when ingesting
 * auto-fetched CDN range lists.
 *
 * @since 1.1.0
 */
class Cidr {

	/**
	 * Normalize a single IP or IP/CIDR token, or return null if invalid.
	 *
	 * Accepts bare IPv4/IPv6 addresses and CIDR notation. The prefix length
	 * is range-checked against the address family (0-32 for IPv4, 0-128 for
	 * IPv6) so malformed or out-of-range masks are rejected rather than
	 * silently trusted.
	 *
	 * @param string $token Raw token (e.g. '172.64.0.0/13' or '203.0.113.5').
	 * @return string|null Canonical token, or null if invalid.
	 * @since 1.1.0
	 */
	public static function normalize( string $token ): ?string {
		$token = trim( $token );

		if ( '' === $token ) {
			return null;
		}

		if ( ! str_contains( $token, '/' ) ) {
			return filter_var( $token, FILTER_VALIDATE_IP ) ? $token : null;
		}

		list( $ip, $bits ) = explode( '/', $token, 2 );
		if ( ! ctype_digit( $bits ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return null;
		}

		$bits_int = (int) $bits;
		$max_bits = str_contains( $ip, ':' ) ? 128 : 32;
		if ( $bits_int < 0 || $bits_int > $max_bits ) {
			return null;
		}

		return $ip . '/' . $bits_int;
	}

	/**
	 * Check whether an IP falls inside any of the given ranges.
	 *
	 * @param string   $ip     The IP address to test.
	 * @param string[] $ranges CIDR ranges or exact IPs.
	 * @return bool True if the IP matches at least one range.
	 * @since 1.1.0
	 */
	public static function ip_matches_any( string $ip, array $ranges ): bool {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		foreach ( $ranges as $range ) {
			if ( is_string( $range ) && self::ip_in_cidr( $ip, $range ) ) {
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
	 * @since 1.1.0
	 */
	public static function ip_in_cidr( string $ip, string $cidr ): bool {
		if ( ! str_contains( $cidr, '/' ) ) {
			// Compare by packed binary so equivalent IPv6 representations
			// (compressed vs expanded, differing case) match. inet_pton also
			// keeps address families distinct — a v4 and v6 string never
			// collide. Fall back to a literal compare only if either side is
			// not a parseable IP.
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) && filter_var( $cidr, FILTER_VALIDATE_IP ) ) {
				return inet_pton( $ip ) === inet_pton( $cidr );
			}

			return $ip === $cidr;
		}

		list( $subnet, $bits_str ) = explode( '/', $cidr, 2 );
		if ( ! ctype_digit( $bits_str ) ) {
			return false;
		}

		$ip_is_v6     = str_contains( $ip, ':' );
		$subnet_is_v6 = str_contains( $subnet, ':' );
		if ( $ip_is_v6 !== $subnet_is_v6 ) {
			return false;
		}

		$bits = (int) $bits_str;

		return $ip_is_v6
			? self::match_ipv6_cidr( $ip, $subnet, $bits )
			: self::match_ipv4_cidr( $ip, $subnet, $bits );
	}

	/**
	 * Check if an IPv4 address falls inside a CIDR range.
	 *
	 * @param string $ip     IPv4 address.
	 * @param string $subnet IPv4 subnet.
	 * @param int    $bits   Prefix length.
	 * @return bool
	 * @since 1.1.0
	 */
	private static function match_ipv4_cidr( string $ip, string $subnet, int $bits ): bool {
		if ( $bits < 0 || $bits > 32 ) {
			return false;
		}

		$ip_long     = ip2long( $ip );
		$subnet_long = ip2long( $subnet );
		if ( false === $ip_long || false === $subnet_long ) {
			return false;
		}

		if ( 0 === $bits ) {
			return true;
		}

		$mask = -1 << ( 32 - $bits );
		return ( $ip_long & $mask ) === ( $subnet_long & $mask );
	}

	/**
	 * Check if an IPv6 address falls inside a CIDR range.
	 *
	 * @param string $ip     IPv6 address.
	 * @param string $subnet IPv6 subnet.
	 * @param int    $bits   Prefix length.
	 * @return bool
	 * @since 1.1.0
	 */
	private static function match_ipv6_cidr( string $ip, string $subnet, int $bits ): bool {
		if ( $bits < 0 || $bits > 128 ) {
			return false;
		}

		$ip_bin     = inet_pton( $ip );
		$subnet_bin = inet_pton( $subnet );
		if ( false === $ip_bin || false === $subnet_bin ) {
			return false;
		}

		$full_bytes     = intdiv( $bits, 8 );
		$remaining_bits = $bits % 8;

		if ( substr( $ip_bin, 0, $full_bytes ) !== substr( $subnet_bin, 0, $full_bytes ) ) {
			return false;
		}

		if ( 0 === $remaining_bits ) {
			return true;
		}

		$mask = 0xFF << ( 8 - $remaining_bits );
		return ( ord( $ip_bin[ $full_bytes ] ) & $mask ) === ( ord( $subnet_bin[ $full_bytes ] ) & $mask );
	}
}
