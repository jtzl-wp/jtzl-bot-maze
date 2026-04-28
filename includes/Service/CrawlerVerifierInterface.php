<?php
/**
 * Crawler Verifier Interface
 *
 * Defines the contract for verifying legitimate search engine crawlers.
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
 * Crawler Verifier interface.
 *
 * Provides methods for verifying whether a visitor is a legitimate
 * search engine crawler using forward-confirmed reverse DNS.
 *
 * @since 1.0.0
 */
interface CrawlerVerifierInterface {

	/**
	 * Check if the visitor is a verified search engine crawler.
	 *
	 * @param string $ip         The client IP address.
	 * @param string $user_agent The user agent string.
	 * @return bool True if the visitor is a verified crawler.
	 * @since 1.0.0
	 */
	public function is_verified_crawler( string $ip, string $user_agent ): bool;
}
