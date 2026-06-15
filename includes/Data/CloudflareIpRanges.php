<?php
/**
 * Cloudflare IP Ranges
 *
 * Bundled baseline of Cloudflare's published edge IP ranges, with an
 * optional auto-refreshed cache layered on top.
 *
 * @package JTZL\Bot_Maze
 * @since 1.1.0
 */

namespace JTZL\Bot_Maze\Data;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Cloudflare IP range provider.
 *
 * When a site sits behind Cloudflare, every request reaches the origin from a
 * Cloudflare edge IP. To safely trust the CF-Connecting-IP header, the plugin
 * must know which source IPs are Cloudflare. Rather than make admins paste
 * those ranges by hand, the "Cloudflare" trusted-header preset falls back to
 * this bundled list automatically.
 *
 * The BASELINE constant is the source of truth shipped with the plugin and is
 * always functional offline. {@see CloudflareRangeUpdater} may layer a fresher
 * copy on top via the cache option; resolution happens in {@see self::all()}.
 *
 * @since 1.1.0
 */
class CloudflareIpRanges {

	/**
	 * Option key holding the auto-refreshed range cache.
	 *
	 * Stored shape: array{ ranges: string[], fetched_at: string }.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	public const CACHE_OPTION = 'jtzl_bm_cloudflare_ranges';

	/**
	 * Minimum plausible number of ranges for a list to be trusted.
	 *
	 * A list smaller than this is treated as truncated/tampered and rejected
	 * in favor of the bundled baseline. Cloudflare publishes ~22 blocks.
	 *
	 * @var int
	 * @since 1.1.0
	 */
	public const MIN_RANGES = 10;

	/**
	 * Maximum plausible number of ranges for a list to be trusted.
	 *
	 * Guards against an implausibly large (and therefore suspect) response
	 * widening the trusted-proxy set.
	 *
	 * @var int
	 * @since 1.1.0
	 */
	public const MAX_RANGES = 200;

	/**
	 * Bundled baseline of Cloudflare's published IPv4 + IPv6 edge ranges.
	 *
	 * Source: https://www.cloudflare.com/ips/ (verified 2026-06).
	 *
	 * @var string[]
	 * @since 1.1.0
	 */
	public const BASELINE = array(
		// IPv4 — https://www.cloudflare.com/ips-v4.
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		// IPv6 — https://www.cloudflare.com/ips-v6.
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	/**
	 * Get the bundled baseline ranges.
	 *
	 * @return string[] Baseline Cloudflare ranges.
	 * @since 1.1.0
	 */
	public static function baseline(): array {
		return self::BASELINE;
	}

	/**
	 * Resolve the active Cloudflare ranges.
	 *
	 * Returns the auto-refreshed cache when it is present and structurally
	 * plausible; otherwise falls back to the bundled baseline. The cache is
	 * only ever written by {@see CloudflareRangeUpdater} after full CIDR
	 * validation, so this method performs a lightweight structural check
	 * rather than re-validating every entry.
	 *
	 * @return string[] Active Cloudflare ranges (never empty).
	 * @since 1.1.0
	 */
	public static function all(): array {
		$cache = get_option( self::CACHE_OPTION, array() );

		if ( ! is_array( $cache ) || empty( $cache['ranges'] ) || ! is_array( $cache['ranges'] ) ) {
			return self::BASELINE;
		}

		$ranges = array_values( array_filter( $cache['ranges'], 'is_string' ) );
		$count  = count( $ranges );

		if ( $count < self::MIN_RANGES || $count > self::MAX_RANGES ) {
			return self::BASELINE;
		}

		return $ranges;
	}

	/**
	 * Persist a validated set of ranges to the refresh cache.
	 *
	 * Callers are responsible for validating each range before saving;
	 * {@see CloudflareRangeUpdater} is the sole intended writer.
	 *
	 * @param string[] $ranges Validated, normalized CIDR/IP strings.
	 * @return void
	 * @since 1.1.0
	 */
	public static function save( array $ranges ): void {
		update_option(
			self::CACHE_OPTION,
			array(
				'ranges'     => array_values( $ranges ),
				'fetched_at' => gmdate( 'c' ),
			)
		);
	}

	/**
	 * Get the ISO-8601 timestamp of the last successful refresh.
	 *
	 * @return string Timestamp, or empty string if the baseline is in use.
	 * @since 1.1.0
	 */
	public static function last_updated(): string {
		$cache = get_option( self::CACHE_OPTION, array() );

		if ( is_array( $cache ) && ! empty( $cache['fetched_at'] ) && is_string( $cache['fetched_at'] ) ) {
			return $cache['fetched_at'];
		}

		return '';
	}
}
