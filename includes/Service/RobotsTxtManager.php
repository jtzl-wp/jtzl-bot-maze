<?php
/**
 * Robots.txt Manager Service
 *
 * Modifies robots.txt to prevent good bots from wasting crawl budget.
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
 * Robots.txt Manager
 *
 * Adds Disallow rules to robots.txt to prevent good bots from
 * crawling the trap pages, saving crawl budget.
 *
 * @since 1.0.0
 */
class RobotsTxtManager {

	/**
	 * Whether robots.txt modifications are enabled.
	 *
	 * @var bool
	 * @since 1.0.0
	 */
	private bool $enabled;

	/**
	 * Base path for trap URLs.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $base_path;

	/**
	 * Home URL path prefix (e.g. '/blog' for subdirectory installs, '' for root).
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $home_path;

	/**
	 * Constructor.
	 *
	 * @param bool   $enabled   Whether modifications are enabled.
	 * @param string $base_path URL base path.
	 * @param string $home_path Home URL path prefix.
	 * @since 1.0.0
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
	 * @since 1.0.0
	 */
	public function modify_robots_txt( string $output, bool $is_public ): string {
		if ( ! $this->enabled || ! $is_public ) {
			return $output;
		}

		// Append Disallow directive to the existing User-agent group.
		// Trim trailing whitespace first — WordPress may end with a blank
		// line which per RFC 9309 would terminate the User-agent: * group.
		$output  = rtrim( $output ) . "\n";
		$output .= "# Labyrinth Bot Protection\n";
		$output .= 'Disallow: ' . $this->home_path . '/' . $this->base_path . "/\n";

		return $output;
	}
}
