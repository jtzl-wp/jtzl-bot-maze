<?php
/**
 * DNS Resolver Interface
 *
 * Defines the contract for DNS resolution operations.
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
 * DNS Resolver interface.
 *
 * Provides methods for forward and reverse DNS lookups
 * used by the crawler verification system.
 *
 * @since 1.0.0
 */
interface DnsResolverInterface {

	/**
	 * Perform a reverse DNS lookup for an IP address.
	 *
	 * @param string $ip The IP address to look up.
	 * @return string The hostname, or the IP itself on failure.
	 * @since 1.0.0
	 */
	public function reverse_lookup( string $ip ): string;

	/**
	 * Perform a forward DNS lookup for a hostname.
	 *
	 * @param string $hostname The hostname to resolve.
	 * @return array<string> Array of IP addresses.
	 * @since 1.0.0
	 */
	public function forward_lookup( string $hostname ): array;
}
