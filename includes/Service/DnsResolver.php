<?php
/**
 * DNS Resolver Service
 *
 * Production DNS resolver using PHP's built-in functions.
 *
 * @package Labyrinth
 * @since 1.0.0
 */

namespace Labyrinth\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * DNS Resolver
 *
 * Wraps PHP DNS functions for production use.
 *
 * @since 1.0.0
 */
class DnsResolver implements DnsResolverInterface {

	/**
	 * Reverse DNS lookup (IP to hostname).
	 *
	 * @param string $ip The IP address.
	 * @return string The hostname, or the original IP on failure.
	 * @since 1.0.0
	 */
	public function reverse_lookup( string $ip ): string {
		$hostname = $this->native_reverse_lookup( $ip );
		return false === $hostname ? $ip : $hostname;
	}

	/**
	 * Forward DNS lookup (hostname to IP addresses).
	 *
	 * @param string $hostname The hostname to resolve.
	 * @return string[] List of resolved IP addresses.
	 * @since 1.0.0
	 */
	public function forward_lookup( string $hostname ): array {
		$ips = array();

		$a_records = $this->native_a_records( $hostname );
		if ( is_array( $a_records ) ) {
			foreach ( $a_records as $record ) {
				if ( isset( $record['ip'] ) ) {
					$ips[] = $record['ip'];
				}
			}
		}

		$aaaa_records = $this->native_aaaa_records( $hostname );
		if ( is_array( $aaaa_records ) ) {
			foreach ( $aaaa_records as $record ) {
				if ( isset( $record['ipv6'] ) ) {
					$ips[] = $record['ipv6'];
				}
			}
		}

		return $ips;
	}

	/**
	 * Run the native reverse DNS lookup.
	 *
	 * Separated for deterministic tests without relying on local DNS setup.
	 *
	 * @param string $ip The IP address.
	 * @return string|false Hostname on success, false on failure.
	 * @since 1.0.0
	 */
	protected function native_reverse_lookup( string $ip ): string|false {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DNS functions emit E_WARNING on network failures.
		return @gethostbyaddr( $ip );
	}

	/**
	 * Fetch native IPv4 (A) DNS records.
	 *
	 * @param string $hostname The hostname.
	 * @return array|false DNS records or false on failure.
	 * @since 1.0.0
	 */
	protected function native_a_records( string $hostname ): array|false {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DNS functions emit E_WARNING on network failures.
		return @dns_get_record( $hostname, DNS_A );
	}

	/**
	 * Fetch native IPv6 (AAAA) DNS records.
	 *
	 * @param string $hostname The hostname.
	 * @return array|false DNS records or false on failure.
	 * @since 1.0.0
	 */
	protected function native_aaaa_records( string $hostname ): array|false {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DNS functions emit E_WARNING on network failures.
		return @dns_get_record( $hostname, DNS_AAAA );
	}
}
