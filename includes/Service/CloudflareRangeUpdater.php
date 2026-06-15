<?php
/**
 * Cloudflare Range Updater
 *
 * Fetches Cloudflare's published IP ranges and refreshes the bundled cache.
 *
 * @package JTZL\Bot_Maze
 * @since 1.1.0
 */

namespace JTZL\Bot_Maze\Service;

use JTZL\Bot_Maze\Data\CloudflareIpRanges;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Cloudflare Range Updater.
 *
 * Pulls the canonical IPv4 + IPv6 lists from cloudflare.com and stores them so
 * the "Cloudflare" trusted-header preset stays current without admin action.
 *
 * Because this list is a trust boundary — anything on it may declare a
 * visitor's real IP via CF-Connecting-IP — the refresh is deliberately
 * conservative. It is all-or-nothing: both endpoints must succeed, every token
 * must pass CIDR validation, and the total must fall within plausible bounds.
 * On any failure the previously stored list (or the bundled baseline) is left
 * untouched, so a blocked, truncated, or tampered response can never narrow or
 * widen the trusted set.
 *
 * @since 1.1.0
 */
class CloudflareRangeUpdater {

	/**
	 * Cloudflare's published IPv4 range list (one CIDR per line).
	 *
	 * @var string
	 * @since 1.1.0
	 */
	private const URL_V4 = 'https://www.cloudflare.com/ips-v4';

	/**
	 * Cloudflare's published IPv6 range list (one CIDR per line).
	 *
	 * @var string
	 * @since 1.1.0
	 */
	private const URL_V6 = 'https://www.cloudflare.com/ips-v6';

	/**
	 * Per-request timeout in seconds.
	 *
	 * @var int
	 * @since 1.1.0
	 */
	private const TIMEOUT = 15;

	/**
	 * Refresh the cached Cloudflare ranges.
	 *
	 * @return bool True if a fresh, validated list was stored; false if the
	 *              fetch failed validation and the prior list was kept.
	 * @since 1.1.0
	 */
	public function refresh(): bool {
		$v4 = $this->fetch_list( self::URL_V4 );
		$v6 = $this->fetch_list( self::URL_V6 );

		// All-or-nothing: a single failed endpoint must not produce a partial set.
		if ( null === $v4 || null === $v6 ) {
			return false;
		}

		$clean = array();
		foreach ( array_merge( $v4, $v6 ) as $token ) {
			$normalized = Cidr::normalize( $token );

			// Any malformed token taints the whole payload — keep the prior list.
			if ( null === $normalized ) {
				return false;
			}

			$clean[] = $normalized;
		}

		$count = count( $clean );
		if ( $count < CloudflareIpRanges::MIN_RANGES || $count > CloudflareIpRanges::MAX_RANGES ) {
			return false;
		}

		CloudflareIpRanges::save( $clean );

		return true;
	}

	/**
	 * Fetch and split a Cloudflare range list endpoint.
	 *
	 * @param string $url Endpoint returning one CIDR per line.
	 * @return string[]|null Non-empty list of raw tokens, or null on any failure.
	 * @since 1.1.0
	 */
	private function fetch_list( string $url ): ?array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => self::TIMEOUT,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = trim( (string) wp_remote_retrieve_body( $response ) );
		if ( '' === $body ) {
			return null;
		}

		$tokens = array();
		foreach ( preg_split( '/\R/', $body ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$tokens[] = $line;
			}
		}

		return empty( $tokens ) ? null : $tokens;
	}
}
