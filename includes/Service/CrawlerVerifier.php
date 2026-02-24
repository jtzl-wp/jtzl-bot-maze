<?php
/**
 * Crawler Verifier Service
 *
 * Verifies legitimate search engine crawlers using forward-confirmed reverse DNS.
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
 * Crawler Verifier
 *
 * Uses forward-confirmed reverse DNS to verify Googlebot and Bingbot.
 * Results are cached via WordPress transients to minimize DNS lookups.
 */
class CrawlerVerifier implements CrawlerVerifierInterface {

	/**
	 * Cache TTL for verified crawlers (24 hours).
	 *
	 * @var int
	 */
	private const VERIFIED_TTL = 86400;

	/**
	 * Cache TTL for non-verified lookups (1 hour).
	 *
	 * @var int
	 */
	private const DENIED_TTL = 3600;

	/**
	 * User agent patterns that trigger DNS verification, grouped by crawler family.
	 *
	 * @var array<string, array<string>>
	 */
	private const UA_PATTERNS = array(
		'google' => array( 'googlebot', 'google-inspectiontool', 'storebot-google', 'googleother' ),
		'bing'   => array( 'bingbot', 'bingpreview', 'microsoftpreview' ),
	);

	/**
	 * Valid hostname suffixes per crawler family.
	 *
	 * @var array<string, array<string>>
	 */
	private const HOSTNAME_SUFFIXES = array(
		'google' => array( '.googlebot.com', '.google.com' ),
		'bing'   => array( '.search.msn.com' ),
	);

	/**
	 * DNS resolver instance.
	 *
	 * @var DnsResolverInterface
	 */
	private DnsResolverInterface $dns_resolver;

	/**
	 * Constructor.
	 *
	 * @param DnsResolverInterface $dns_resolver DNS resolver implementation.
	 */
	public function __construct( DnsResolverInterface $dns_resolver ) {
		$this->dns_resolver = $dns_resolver;
	}

	/**
	 * Check if the visitor is a verified search engine crawler.
	 *
	 * @param string $ip         The client IP address.
	 * @param string $user_agent The user agent string.
	 * @return bool True if the visitor is a verified crawler.
	 */
	public function is_verified_crawler( string $ip, string $user_agent ): bool {
		if ( '' === $ip || '' === $user_agent ) {
			return false;
		}

		$crawler_family = $this->detect_crawler_family( $user_agent );

		if ( null === $crawler_family ) {
			return false;
		}

		$cache_key = 'lby_crawler_' . wp_hash( $ip . ':' . $crawler_family );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return '1' === $cached;
		}

		$verified = $this->verify_via_dns( $ip, $crawler_family );

		set_transient( $cache_key, $verified ? '1' : '0', $verified ? self::VERIFIED_TTL : self::DENIED_TTL );

		return $verified;
	}

	/**
	 * Detect which crawler family matches the user agent.
	 *
	 * @param string $user_agent The user agent string.
	 * @return string|null The crawler family key, or null if no match.
	 */
	private function detect_crawler_family( string $user_agent ): ?string {
		$ua_lower = strtolower( $user_agent );

		foreach ( self::UA_PATTERNS as $family => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( str_contains( $ua_lower, $pattern ) ) {
					return $family;
				}
			}
		}

		return null;
	}

	/**
	 * Verify an IP belongs to a crawler family via forward-confirmed reverse DNS.
	 *
	 * @param string $ip             The IP address to verify.
	 * @param string $crawler_family The crawler family key.
	 * @return bool True if verified.
	 */
	private function verify_via_dns( string $ip, string $crawler_family ): bool {
		$hostname = $this->dns_resolver->reverse_lookup( $ip );

		if ( $hostname === $ip ) {
			return false;
		}

		$suffixes     = self::HOSTNAME_SUFFIXES[ $crawler_family ] ?? array();
		$valid_suffix = false;

		foreach ( $suffixes as $suffix ) {
			if ( str_ends_with( strtolower( $hostname ), $suffix ) ) {
				$valid_suffix = true;
				break;
			}
		}

		if ( ! $valid_suffix ) {
			return false;
		}

		$resolved_ips = $this->dns_resolver->forward_lookup( $hostname );

		// Normalize via inet_pton to handle IPv6 format differences.
		$ip_bin = inet_pton( $ip );
		foreach ( $resolved_ips as $resolved_ip ) {
			if ( inet_pton( $resolved_ip ) === $ip_bin ) {
				return true;
			}
		}
		return false;
	}
}
