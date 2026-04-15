<?php
/**
 * MaxMind GeoLite2 GeoIP Provider
 *
 * Resolves IP addresses to country codes using a local GeoLite2 database.
 *
 * @package JTZL\Bot_Labyrinth
 * @since 1.0.0
 */

namespace JTZL\Bot_Labyrinth\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use MaxMind\Db\Reader;

/**
 * MaxMind GeoLite2 GeoIP Provider
 *
 * Resolves visitor IP addresses to ISO 3166-1 alpha-2 country codes
 * using a locally stored GeoLite2-Country MMDB file. No data leaves
 * the server — all lookups are performed locally.
 *
 * @since 1.0.0
 */
class MaxMindGeoIpProvider implements GeoIpProviderInterface {

	/**
	 * Absolute path to the GeoLite2-Country.mmdb file.
	 *
	 * @var string
	 */
	private string $database_path;

	/**
	 * Cached Reader instance (lazy-loaded, reused across lookups).
	 *
	 * @var Reader|null
	 */
	private ?Reader $reader = null;

	/**
	 * Constructor.
	 *
	 * @param string $database_path Absolute path to the .mmdb file.
	 * @since 1.0.0
	 */
	public function __construct( string $database_path ) {
		$this->database_path = $database_path;
	}

	/**
	 * Resolve an IP address to a two-letter country code.
	 *
	 * Returns an empty string on failure or for private/reserved
	 * IP addresses.
	 *
	 * @param string $ip_address The IP address to resolve.
	 * @return string Two-letter country code or empty string.
	 * @since 1.0.0
	 */
	public function resolve_country( string $ip_address ): string {
		if ( empty( $ip_address ) || ! filter_var( $ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return '';
		}

		if ( ! file_exists( $this->database_path ) ) {
			return '';
		}

		if ( null === $this->reader ) {
			$this->reader = new Reader( $this->database_path );
		}

		$record = $this->reader->get( $ip_address );

		if ( ! is_array( $record ) || ! isset( $record['country']['iso_code'] ) ) {
			return '';
		}

		$code = $record['country']['iso_code'];

		if ( ! preg_match( '/^[A-Z]{2}$/', $code ) ) { // @codeCoverageIgnoreStart
			return ''; // Defensive guard — MMDB files always contain valid ISO codes.
		} // @codeCoverageIgnoreEnd

		return $code;
	}
}
