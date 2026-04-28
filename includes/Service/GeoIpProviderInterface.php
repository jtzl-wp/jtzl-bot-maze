<?php
/**
 * GeoIP Provider Interface
 *
 * Defines the contract for resolving IP addresses to country codes.
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * GeoIP Provider interface.
 *
 * Implementations resolve an IP address to an ISO 3166-1 alpha-2
 * country code using different lookup strategies (external API,
 * local database, etc.).
 *
 * @since 1.0.0
 */
interface GeoIpProviderInterface {

	/**
	 * Resolve an IP address to a two-letter country code.
	 *
	 * @param string $ip_address The IP address to resolve.
	 * @return string Two-letter country code or empty string on failure.
	 * @since 1.0.0
	 */
	public function resolve_country( string $ip_address ): string;
}
