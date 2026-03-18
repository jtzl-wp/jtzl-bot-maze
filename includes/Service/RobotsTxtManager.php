<?php
/**
 * Robots.txt Manager Service
 *
 * Modifies robots.txt to prevent good bots from wasting crawl budget.
 *
 * @package Labyrinth
 */

namespace Labyrinth\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Robots.txt Manager
 *
 * Adds Disallow rules to robots.txt to prevent good bots from
 * crawling the trap pages, saving crawl budget.
 */
class RobotsTxtManager {

	/**
	 * Whether robots.txt modifications are enabled.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Base path for trap URLs.
	 *
	 * @var string
	 */
	private string $base_path;

	/**
	 * Home URL path prefix (e.g. '/blog' for subdirectory installs, '' for root).
	 *
	 * @var string
	 */
	private string $home_path;

	/**
	 * Constructor.
	 *
	 * @param bool   $enabled   Whether modifications are enabled.
	 * @param string $base_path URL base path.
	 * @param string $home_path Home URL path prefix.
	 */
	public function __construct( bool $enabled, string $base_path, string $home_path = '' ) {
		$this->enabled   = $enabled;
		$this->base_path = $base_path;
		$this->home_path = rtrim( $home_path, '/' );
	}

	/**
	 * Modify robots.txt output.
	 *
	 * Hooked to `robots_txt` filter. Adds Disallow directives for trap paths
	 * to prevent good bots from wasting crawl budget.
	 *
	 * @param string $output   Existing robots.txt content.
	 * @param bool   $is_public Whether the site is public.
	 * @return string Modified robots.txt content.
	 */
	public function modify_robots_txt( string $output, bool $is_public ): string {
		if ( ! $this->enabled || ! $is_public ) {
			return $output;
		}

		// Append Disallow directive to the existing User-agent group.
		// No blank line before — a blank line would start a new record per
		// RFC 9309, potentially overriding earlier rules for User-agent: *.
		$output .= "# Labyrinth Bot Protection\n";
		$output .= 'Disallow: ' . $this->home_path . '/' . $this->base_path . "/\n";

		return $output;
	}
}
