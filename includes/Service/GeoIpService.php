<?php
/**
 * GeoIP Service
 *
 * Orchestrates geographic IP lookups by delegating to a configured provider.
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
 * GeoIP Service
 *
 * Thin orchestrator that checks whether geographic tracking is enabled
 * and delegates the actual lookup to a GeoIpProviderInterface implementation.
 *
 * @since 1.0.0
 */
class GeoIpService {

	/**
	 * Whether geographic tracking is enabled.
	 *
	 * @var bool
	 * @since 1.0.0
	 */
	private bool $enabled;

	/**
	 * The active GeoIP provider, or null when none is configured.
	 *
	 * @var GeoIpProviderInterface|null
	 * @since 1.0.0
	 */
	private ?GeoIpProviderInterface $provider;

	/**
	 * Constructor.
	 *
	 * @param bool                        $enabled  Whether geographic tracking is enabled.
	 * @param GeoIpProviderInterface|null $provider The GeoIP lookup provider.
	 * @since 1.0.0
	 */
	public function __construct( bool $enabled, ?GeoIpProviderInterface $provider = null ) {
		$this->enabled  = $enabled;
		$this->provider = $provider;
	}

	/**
	 * Resolve an IP address to a two-letter country code.
	 *
	 * Returns an empty string when disabled, when no provider is
	 * configured, or when the provider lookup fails.
	 *
	 * @param string $ip_address The IP address to resolve.
	 * @return string Two-letter country code or empty string.
	 * @since 1.0.0
	 */
	public function resolve_country( string $ip_address ): string {
		if ( ! $this->enabled || null === $this->provider ) {
			return '';
		}

		try {
			return $this->provider->resolve_country( $ip_address );
		} catch ( \Throwable $e ) {
			return '';
		}
	}
}
