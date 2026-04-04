<?php
/**
 * GeoIP Service
 *
 * Resolves IP addresses to country codes via ip-api.com.
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
 * GeoIP Service
 *
 * Resolves visitor IP addresses to ISO 3166-1 alpha-2 country codes
 * using the ip-api.com free API. Requires opt-in via the
 * lby_enable_geo_tracking setting.
 *
 * @since 1.0.0
 */
class GeoIpService {

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
	 * Whether geographic tracking is enabled.
	 *
	 * @var bool
	 * @since 1.0.0
	 */
	private bool $enabled;

	/**
	 * Constructor.
	 *
	 * @param bool $enabled Whether geographic tracking is enabled.
	 * @since 1.0.0
	 */
	public function __construct( bool $enabled ) {
		$this->enabled = $enabled;
	}

	/**
	 * Resolve an IP address to a two-letter country code.
	 *
	 * Returns an empty string on failure, when disabled, or for
	 * private/reserved IP addresses.
	 *
	 * @param string $ip_address The IP address to resolve.
	 * @return string Two-letter country code or empty string.
	 * @since 1.0.0
	 */
	public function resolve_country( string $ip_address ): string {
		if ( ! $this->enabled ) {
			return '';
		}

		if ( empty( $ip_address ) || ! filter_var( $ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return '';
		}

		$cache_key = 'lby_geo_' . md5( $ip_address );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (string) $cached;
		}

		$url = self::API_URL . rawurlencode( $ip_address ) . '?fields=status,countryCode';

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			set_transient( $cache_key, '', self::NEGATIVE_CACHE_TTL );
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			set_transient( $cache_key, '', self::NEGATIVE_CACHE_TTL );
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || 'success' !== ( $data['status'] ?? '' ) ) {
			set_transient( $cache_key, '', self::NEGATIVE_CACHE_TTL );
			return '';
		}

		$country_code = $data['countryCode'] ?? '';

		if ( ! preg_match( '/^[A-Z]{2}$/', $country_code ) ) {
			set_transient( $cache_key, '', self::NEGATIVE_CACHE_TTL );
			return '';
		}

		set_transient( $cache_key, $country_code, self::CACHE_TTL );

		return $country_code;
	}
}
