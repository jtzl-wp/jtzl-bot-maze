<?php
/**
 * Ip-api.com GeoIP Provider
 *
 * Resolves IP addresses to country codes via the ip-api.com free API.
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

/**
 * Ip-api.com GeoIP Provider
 *
 * Resolves visitor IP addresses to ISO 3166-1 alpha-2 country codes
 * using the ip-api.com free API. Results are cached in WordPress
 * transients to minimise external requests.
 *
 * @since 1.0.0
 */
class IpApiGeoIpProvider implements GeoIpProviderInterface {

	/**
	 * API endpoint for country code lookups (ip-api.com).
	 *
	 * @var string
	 */
	private const API_URL = 'http://ip-api.com/json/';

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private const TIMEOUT = 2;

	/**
	 * Transient cache TTL for successful lookups (1 hour).
	 *
	 * @var int
	 */
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Transient cache TTL for failed lookups (5 minutes).
	 *
	 * @var int
	 */
	private const NEGATIVE_CACHE_TTL = 5 * MINUTE_IN_SECONDS;

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

		$cache_key = 'jtzl_bl_geo_ipapi_' . md5( $ip_address );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (string) $cached;
		}

		$country_code = $this->fetch_country_from_api( $ip_address );
		if ( '' === $country_code ) {
			set_transient( $cache_key, '', self::NEGATIVE_CACHE_TTL );
			return '';
		}

		set_transient( $cache_key, $country_code, self::CACHE_TTL );
		return $country_code;
	}

	/**
	 * Call the ip-api.com endpoint and return the parsed country code.
	 *
	 * Returns an empty string on any failure — transport, non-200,
	 * malformed JSON, unknown status, or a country code that isn't
	 * the expected ISO-3166 two-letter form — and leaves caching
	 * decisions to the caller.
	 *
	 * @param string $ip_address Validated IP address.
	 * @return string Two-letter country code, or empty string on failure.
	 * @since 1.0.0
	 */
	private function fetch_country_from_api( string $ip_address ): string {
		$url = self::API_URL . rawurlencode( $ip_address ) . '?fields=status,countryCode';

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || 'success' !== ( $data['status'] ?? '' ) ) {
			return '';
		}

		$country_code = (string) ( $data['countryCode'] ?? '' );
		if ( ! preg_match( '/^[A-Z]{2}$/', $country_code ) ) {
			return '';
		}

		return $country_code;
	}
}
