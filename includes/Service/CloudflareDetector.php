<?php
/**
 * Cloudflare Detector
 *
 * Best-effort detection of whether the current request reached the origin
 * through Cloudflare.
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
 * Cloudflare Detector.
 *
 * Inspects the current request for signals that it was proxied by Cloudflare:
 * a REMOTE_ADDR inside Cloudflare's published edge ranges (the definitive
 * signal — the TCP connection to the origin came from a Cloudflare edge), or a
 * CF-Ray header (which Cloudflare adds to every proxied request).
 *
 * Used only to *suggest* configuration via an admin notice, never to make a
 * trust decision, so a rare false positive is harmless. Detection requires the
 * inspected request to traverse Cloudflare; setups where wp-admin bypasses the
 * proxy will simply not trigger the suggestion.
 *
 * @since 1.1.0
 */
class CloudflareDetector {

	/**
	 * Determine whether the current request appears to come through Cloudflare.
	 *
	 * @return bool
	 * @since 1.1.0
	 */
	public function is_behind_cloudflare(): bool {
		return $this->remote_addr_is_cloudflare() || $this->has_cloudflare_header();
	}

	/**
	 * Whether REMOTE_ADDR falls inside Cloudflare's published edge ranges.
	 *
	 * This is the strongest signal: it means the connection to the origin
	 * originated from a Cloudflare edge, which a direct client cannot forge.
	 *
	 * @return bool
	 * @since 1.1.0
	 */
	private function remote_addr_is_cloudflare(): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$remote_addr = ! empty( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		if ( '' === $remote_addr ) {
			return false;
		}

		return Cidr::ip_matches_any( $remote_addr, CloudflareIpRanges::all() );
	}

	/**
	 * Whether the request carries a Cloudflare-specific header.
	 *
	 * CF-Ray is attached by Cloudflare to every proxied request. This is a
	 * weaker, corroborating signal (a direct client could set it), but it
	 * catches setups where another proxy sits between Cloudflare and the
	 * origin so REMOTE_ADDR is not itself a Cloudflare IP.
	 *
	 * @return bool
	 * @since 1.1.0
	 */
	private function has_cloudflare_header(): bool {
		return ! empty( $_SERVER['HTTP_CF_RAY'] );
	}
}
