<?php
/**
 * Robots.txt Manager Service
 *
 * Modifies robots.txt to prevent good bots from wasting crawl budget.
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
 * Robots.txt Manager
 *
 * Adds Disallow rules to robots.txt to prevent good bots from
 * crawling the trap pages, saving crawl budget.
 *
 * @since 1.0.0
 */
class RobotsTxtManager {

	/**
	 * Comment line marking the plugin-managed robots.txt block.
	 *
	 * Shared so the front-end filter, the verifier, and the physical-file
	 * writer all emit and recognise the same marker.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const MANAGED_COMMENT = "# JTZL's Bot Maze";

	/**
	 * Hook priority for the `robots_txt` filter.
	 *
	 * Deliberately late. Rank Math's robots.txt editor hooks `robots_txt` at the
	 * default priority 10 and *replaces* the output with the site owner's saved
	 * content, discarding anything contributed earlier. Running after it (and
	 * appending, never replacing) keeps our Disallow rule intact instead of
	 * having it silently dropped. Yoast hooks at 99999 but appends-preserving, so
	 * our line survives it regardless of order.
	 *
	 * @var int
	 * @since 1.2.0
	 */
	public const FILTER_PRIORITY = 999;

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
	 * Build a manager configured from the current site's options.
	 *
	 * Mirrors the wiring in ContainerFactory (same option names, same
	 * sanitisation, same home-path derivation) so non-DI callers such as the
	 * settings page can obtain a manager whose Disallow line is byte-for-byte
	 * identical to the one the front-end `robots_txt` filter emits.
	 *
	 * @return self
	 * @since 1.2.0
	 */
	public static function for_current_site(): self {
		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		return new self(
			(bool) get_option( 'jtzl_bm_robots_txt_enabled', true ),
			sanitize_title( (string) get_option( 'jtzl_bm_trap_base_path', 'jbm' ) ),
			rtrim( $home_path, '/' )
		);
	}

	/**
	 * Whether robots.txt modifications are enabled.
	 *
	 * @return bool
	 * @since 1.2.0
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * The Disallow directive this manager adds for the trap base path.
	 *
	 * Single source of truth for the line: the front-end filter appends it,
	 * the verifier searches for it, and the file writer writes it.
	 *
	 * @return string
	 * @since 1.2.0
	 */
	public function get_disallow_directive(): string {
		return 'Disallow: ' . $this->home_path . '/' . $this->base_path . '/';
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
		$output .= self::MANAGED_COMMENT . "\n";
		$output .= $this->get_disallow_directive() . "\n";

		return $output;
	}
}
