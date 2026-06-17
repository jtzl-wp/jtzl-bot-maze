<?php
/**
 * Robots.txt Verifier Service
 *
 * Confirms whether the plugin's Disallow directive is actually present in the
 * robots.txt that the site serves.
 *
 * @package JTZL\Bot_Maze
 * @since 1.2.0
 */

namespace JTZL\Bot_Maze\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Robots.txt Verifier.
 *
 * The plugin adds its Disallow rule through the `robots_txt` filter, which only
 * runs when WordPress itself serves robots.txt — i.e. when no static
 * robots.txt file exists at the document root and the site is public. When a
 * physical robots.txt shadows the virtual one (or search-engine visibility is
 * off, or another plugin overrides the filter) the rule silently never reaches
 * crawlers.
 *
 * Rather than guess the cause from the filesystem, this service checks the
 * authoritative signal: it fetches the live robots.txt over a loopback request
 * and looks for the exact directive the manager would emit. The verdict is
 * cached in a transient so the check runs at most once per TTL, not on every
 * admin page load.
 *
 * @since 1.2.0
 */
class RobotsTxtVerifier {

	/**
	 * Our Disallow line is present in the served robots.txt.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const STATUS_LIVE = 'live';

	/**
	 * The robots.txt was fetched successfully but our Disallow line is absent.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const STATUS_MISSING = 'missing';

	/**
	 * The robots.txt could not be fetched, so presence is unknown.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const STATUS_UNKNOWN = 'unknown';

	/**
	 * The robots.txt modification setting is switched off; nothing to verify.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const STATUS_DISABLED = 'disabled';

	/**
	 * Search-engine visibility is off, so robots.txt already disallows the
	 * whole site (trap URLs included). Our specific rule is intentionally moot.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const STATUS_NOT_PUBLIC = 'not_public';

	/**
	 * Transient key holding the cached verdict.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	private const TRANSIENT_KEY = 'jtzl_bm_robots_txt_status';

	/**
	 * How long a definitive verdict (live/missing) stays cached.
	 *
	 * @var int
	 * @since 1.2.0
	 */
	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * How long an inconclusive verdict (unknown) stays cached.
	 *
	 * Much shorter than a definitive one so a transient loopback failure does
	 * not pin the UI to "couldn't verify" for hours after the site recovers.
	 *
	 * @var int
	 * @since 1.2.0
	 */
	private const CACHE_TTL_UNKNOWN = 15 * MINUTE_IN_SECONDS;

	/**
	 * Loopback request timeout in seconds.
	 *
	 * @var int
	 * @since 1.2.0
	 */
	private const TIMEOUT = 5;

	/**
	 * Manager supplying the canonical Disallow directive and enabled state.
	 *
	 * @var RobotsTxtManager
	 * @since 1.2.0
	 */
	private RobotsTxtManager $manager;

	/**
	 * Constructor.
	 *
	 * @param RobotsTxtManager $manager Manager describing the expected directive.
	 * @since 1.2.0
	 */
	public function __construct( RobotsTxtManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Build a verifier from the current site's options.
	 *
	 * Convenience for non-DI callers (e.g. the settings section registrar).
	 *
	 * @return self
	 * @since 1.2.0
	 */
	public static function for_current_site(): self {
		return new self( RobotsTxtManager::for_current_site() );
	}

	/**
	 * Determine whether the plugin's Disallow rule is live in robots.txt.
	 *
	 * @param bool $force       Skip the cache and re-fetch.
	 * @param bool $allow_fetch Whether a synchronous loopback fetch is permitted
	 *                          on a cache miss. The global admin_notices hook
	 *                          passes false so it never blocks an arbitrary admin
	 *                          page; the settings page, the Re-check action, and
	 *                          the daily maintenance cron pass true to prime it.
	 * @return array{status: string, expected: string, url: string} Verdict.
	 * @since 1.2.0
	 */
	public function verify( bool $force = false, bool $allow_fetch = true ): array {
		$expected = $this->manager->get_disallow_directive();

		// The robots_txt filter is only registered while the maze itself is on
		// (see Bootstrap::register_trap_routing_hooks). With the master switch
		// off the rule can never be live, so there is nothing to verify or warn
		// about — treat it the same as the robots.txt toggle being off.
		if ( ! $this->manager->is_enabled() || ! (bool) get_option( 'jtzl_bm_enabled', true ) ) {
			return $this->result( self::STATUS_DISABLED, $expected );
		}

		if ( ! $this->site_is_public() ) {
			return $this->result( self::STATUS_NOT_PUBLIC, $expected );
		}

		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( is_array( $cached ) && ( $cached['expected'] ?? null ) === $expected ) {
				return $cached;
			}
		}

		if ( ! $allow_fetch ) {
			// Cache cold and the caller forbids a blocking fetch: report unknown
			// without hitting the network. A primed verdict will arrive from the
			// settings page, the Re-check action, or the daily cron.
			return $this->result( self::STATUS_UNKNOWN, $expected );
		}

		$result = $this->fetch_and_evaluate( $expected );
		$ttl    = self::STATUS_UNKNOWN === $result['status'] ? self::CACHE_TTL_UNKNOWN : self::CACHE_TTL;
		set_transient( self::TRANSIENT_KEY, $result, $ttl );

		return $result;
	}

	/**
	 * Drop any cached verdict so the next verify() re-checks.
	 *
	 * @return void
	 * @since 1.2.0
	 */
	public function invalidate(): void {
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Fetch the live robots.txt and decide whether our directive is present.
	 *
	 * @param string $expected Directive to look for.
	 * @return array{status: string, expected: string, url: string} Verdict.
	 * @since 1.2.0
	 */
	private function fetch_and_evaluate( string $expected ): array {
		$url = home_url( '/robots.txt' );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 3,
				// Loopback to our own host; a staging self-signed cert must not
				// turn a present rule into a false "couldn't verify".
				'sslverify'   => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->result( self::STATUS_UNKNOWN, $expected, $url );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return $this->result( self::STATUS_UNKNOWN, $expected, $url );
		}

		$body   = (string) wp_remote_retrieve_body( $response );
		$status = self::body_contains_directive( $body, $expected )
			? self::STATUS_LIVE
			: self::STATUS_MISSING;

		return $this->result( $status, $expected, $url );
	}

	/**
	 * Whether a robots.txt body contains the given directive on its own line.
	 *
	 * Compares in normalized form so equivalent spellings crawlers treat alike
	 * still match: directive-keyword casing, the amount of whitespace around the
	 * colon, and a trailing inline comment are all ignored. This keeps the
	 * verifier from reporting a present rule as missing, and keeps the file
	 * writer from appending a duplicate group for an already-present rule.
	 *
	 * @param string $body      robots.txt contents.
	 * @param string $directive Directive to find (e.g. "Disallow: /jbm/").
	 * @return bool
	 * @since 1.2.0
	 */
	public static function body_contains_directive( string $body, string $directive ): bool {
		$needle = self::normalize_directive( $directive );
		if ( null === $needle ) {
			return false;
		}

		$lines = preg_split( '/\r\n|\r|\n/', $body );
		if ( ! is_array( $lines ) ) {
			return false;
		}

		foreach ( $lines as $line ) {
			if ( self::normalize_directive( $line ) === $needle ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize a robots.txt line to canonical "<lower-keyword>: <value>" form.
	 *
	 * Strips an inline comment, collapses whitespace around the colon, and
	 * lowercases the keyword (case-insensitive per the spec) while preserving
	 * the value's case (paths are case-sensitive to crawlers). Returns null for
	 * lines that are not a "keyword: value" directive (blank or comment-only).
	 *
	 * @param string $line Raw robots.txt line or directive.
	 * @return string|null Canonical directive, or null if the line is not one.
	 * @since 1.2.0
	 */
	private static function normalize_directive( string $line ): ?string {
		// Drop an inline comment: everything from the first '#' to end of line.
		// robots.txt paths never contain '#', so this cannot truncate our value.
		$hash = strpos( $line, '#' );
		if ( false !== $hash ) {
			$line = substr( $line, 0, $hash );
		}

		$line = trim( $line );
		if ( '' === $line || ! str_contains( $line, ':' ) ) {
			return null;
		}

		list( $keyword, $value ) = explode( ':', $line, 2 );

		$keyword = strtolower( trim( $keyword ) );
		if ( '' === $keyword ) {
			return null;
		}

		return $keyword . ': ' . trim( $value );
	}

	/**
	 * Whether the site is flagged as visible to search engines.
	 *
	 * @return bool
	 * @since 1.2.0
	 */
	private function site_is_public(): bool {
		return (bool) get_option( 'blog_public', 1 );
	}

	/**
	 * Assemble a verdict array.
	 *
	 * @param string $status   One of the STATUS_* constants.
	 * @param string $expected The directive that was looked for.
	 * @param string $url      The robots.txt URL that was checked, if any.
	 * @return array{status: string, expected: string, url: string}
	 * @since 1.2.0
	 */
	private function result( string $status, string $expected, string $url = '' ): array {
		return array(
			'status'   => $status,
			'expected' => $expected,
			'url'      => $url,
		);
	}
}
