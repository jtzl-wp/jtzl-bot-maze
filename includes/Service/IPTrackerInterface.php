<?php
/**
 * IP Tracker Interface
 *
 * Defines the contract for client IP detection and session tracking.
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
 * IP Tracker interface.
 *
 * Provides methods for detecting client IP addresses,
 * generating session identifiers, and retrieving request metadata.
 *
 * @since 1.0.0
 */
interface IPTrackerInterface {

	/**
	 * Get the client's IP address.
	 *
	 * @return string The client IP address.
	 * @since 1.0.0
	 */
	public function get_client_ip(): string;

	/**
	 * Generate a session identifier from IP + User Agent.
	 *
	 * @return string Session hash.
	 * @since 1.0.0
	 */
	public function generate_session_id(): string;

	/**
	 * Generate a user agent hash.
	 *
	 * @return string SHA256-truncated UA hash.
	 * @since 1.0.0
	 */
	public function get_user_agent_hash(): string;

	/**
	 * Get the raw user agent string.
	 *
	 * @return string User agent string.
	 * @since 1.0.0
	 */
	public function get_user_agent(): string;

	/**
	 * Get the referrer URL without query parameters or fragments.
	 *
	 * @return string Referrer URL.
	 * @since 1.0.0
	 */
	public function get_referrer(): string;

	/**
	 * Get the current request URI without query parameters or fragments.
	 *
	 * @return string Request URI.
	 * @since 1.0.0
	 */
	public function get_request_uri(): string;
}
