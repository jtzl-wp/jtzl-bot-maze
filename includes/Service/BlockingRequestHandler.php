<?php
/**
 * Blocking Request Handler
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

use DI\Container;
use JTZL\Bot_Maze\Repository\TrapPageRepositoryInterface;
use JTZL\Bot_Maze\WordPress\ContextInterface;

/**
 * Responds to flagged visitors on template_redirect.
 *
 * Previously an 85-line inline closure inside Bootstrap::register_hooks(),
 * now a first-class service so its responsibilities (block vs light
 * tarpit vs full tarpit vs blocked-activity tracking vs AbuseIPDB
 * reporting) are testable in isolation and don't bloat the
 * hook-registration method.
 *
 * @since 1.0.0
 */
class BlockingRequestHandler {

	/**
	 * WordPress context facade.
	 *
	 * @var ContextInterface
	 * @since 1.0.0
	 */
	private ContextInterface $wp;

	/**
	 * Bot detector service.
	 *
	 * @var BotDetector
	 * @since 1.0.0
	 */
	private BotDetector $detector;

	/**
	 * DI container used to lazily resolve tarpit-path collaborators.
	 *
	 * Passed in explicitly rather than eagerly resolving TrapPageGenerator,
	 * TrapPageRepository, IPTracker, and AnalyticsCollector up-front so the
	 * common early-return path (normal visitor, not flagged) doesn't pay
	 * their construction cost on every request.
	 *
	 * @var Container
	 * @since 1.0.0
	 */
	private Container $container;

	/**
	 * Trap base URL path segment.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $base_path;

	/**
	 * Full-tarpit delay before serving the decoy, in milliseconds.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private int $tarpit_delay_ms;

	/**
	 * Constructor.
	 *
	 * Only $wp and $detector are eagerly injected because they're the two
	 * services handle() always touches. The rest are resolved from the
	 * container on demand inside the tarpit / tracking helpers so the
	 * common early-return path stays cheap.
	 *
	 * @param ContextInterface $wp              WordPress context facade.
	 * @param BotDetector      $detector        Bot detector service.
	 * @param Container        $container       DI container for lazy deps.
	 * @param string           $base_path       Trap base URL path segment.
	 * @param int              $tarpit_delay_ms Delay in ms before tarpit response.
	 * @since 1.0.0
	 */
	public function __construct(
		ContextInterface $wp,
		BotDetector $detector,
		Container $container,
		string $base_path,
		int $tarpit_delay_ms
	) {
		$this->wp              = $wp;
		$this->detector        = $detector;
		$this->container       = $container;
		$this->base_path       = $base_path;
		$this->tarpit_delay_ms = $tarpit_delay_ms;
	}

	/**
	 * Template_redirect callback: respond to flagged visitors.
	 *
	 * The plugin-enabled gate, admin bypass, and is_visitor_blocked check
	 * are all evaluated before any tarpit/tracking collaborator is
	 * resolved, so a normal visitor exits after two option reads and a
	 * BotDetector call — everything else stays unbuilt.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function handle(): void {
		if ( ! (bool) $this->wp->get_option( 'jtzl_bm_enabled', true ) ) {
			return;
		}

		if ( $this->wp->current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->detector->is_visitor_blocked() ) {
			return;
		}

		$behavior = (string) $this->wp->get_option( 'jtzl_bm_blocking_behavior', 'block' );

		if ( 'tarpit_light' === $behavior || 'tarpit' === $behavior ) {
			if ( 'tarpit' === $behavior && $this->tarpit_delay_ms > 0 ) {
				usleep( $this->tarpit_delay_ms * 1000 );
			}

			// serve_tarpit_decoy() either exits the script via load_template
			// or returns false when no trap pages exist. In the latter case
			// control falls through to the 403 block response below.
			$this->serve_tarpit_decoy();
		}

		// Default 'block' behavior (also the tarpit fallback when no trap pages exist).
		$this->handle_blocked_trap_hit( 403 );
		$this->wp->wp_die(
			'<h1>' . esc_html__( 'Access Denied', 'jtzl-bot-maze' ) . '</h1>' .
			'<p>' . esc_html__( 'Your IP address has been blocked due to suspicious activity.', 'jtzl-bot-maze' ) . '</p>',
			esc_html__( 'Forbidden', 'jtzl-bot-maze' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Serve a random existing trap page as a 200 decoy for tarpit modes.
	 *
	 * Returns false when no trap pages exist, so the caller can fall back
	 * to the plain 403 block response.
	 *
	 * @return bool True on successful decoy response (method calls exit()
	 *              via load_template so returning true is only reached in
	 *              tests that stub load_template/exit).
	 * @since 1.0.0
	 */
	private function serve_tarpit_decoy(): bool {
		$repo  = $this->container->get( TrapPageRepositoryInterface::class );
		$pages = $repo->get_random_active( 1, $this->wp->wp_rand() );
		$page  = ! empty( $pages ) ? $pages[0] : null;
		if ( null === $page ) {
			return false;
		}

		$this->handle_blocked_trap_hit( 200 );

		$generator                         = $this->container->get( TrapPageGenerator::class );
		$GLOBALS['jtzl_bm_trap_page']      = $page;
		$GLOBALS['jtzl_bm_trap_children']  = $generator->get_or_generate_children( $page );
		$GLOBALS['jtzl_bm_trap_base_path'] = $this->base_path;

		$this->wp->status_header( 200 );

		if ( ! $this->wp->headers_sent() ) {
			$this->wp->header( 'X-Robots-Tag: noindex, nofollow', true );
			$this->wp->header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
		}

		$this->wp->load_template( JTZL_BM_PLUGIN_DIR . 'templates/trap-page.php' );
		// @codeCoverageIgnoreStart
		exit;
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Record and report a blocked visitor still walking the maze.
	 *
	 * Because handle() ends the request at template_redirect priority 1, a
	 * blocked identity never reaches BotDetector::record_visit() again.
	 * This is therefore the only place a returning bot's continued
	 * traversal can be observed, and the only place its re-offence can be
	 * reported.
	 *
	 * Analytics tracking and AbuseIPDB reporting are gated independently:
	 * they answer to separate settings, and consenting to one is not
	 * consent to the other.
	 *
	 * Neither acts on a non-trap request. The analytics only care about
	 * maze traversal, and a report asserts the address crawled the trap
	 * pages, so a blocked address merely requesting the homepage is not
	 * fresh evidence for either.
	 *
	 * @param int $response_code HTTP status being returned to the visitor.
	 * @return void
	 * @since 1.5.1
	 */
	private function handle_blocked_trap_hit( int $response_code ): void {
		$tracking  = (bool) $this->wp->get_option( 'jtzl_bm_track_blocked_activity', false );
		$reporter  = $this->container->get( AbuseIpDbReporterInterface::class );
		$reporting = $reporter->is_active();

		if ( ! $tracking && ! $reporting ) {
			return;
		}

		$ip_tracker  = $this->container->get( IPTrackerInterface::class );
		$request_uri = $ip_tracker->get_request_uri();
		if ( ! str_starts_with( $request_uri, $this->trap_prefix() ) ) {
			return;
		}

		if ( $tracking ) {
			$analytics = $this->container->get( AnalyticsCollector::class );
			$analytics->record_blocked_visit(
				$ip_tracker->get_client_ip(),
				$ip_tracker->get_user_agent_hash(),
				$ip_tracker->get_user_agent(),
				$response_code
			);
		}

		// The observation is stamped now, not carried over from the score
		// row: the report must describe this request, which happened after
		// the administrator turned reporting on. The queue's 24-hour per-IP
		// cooldown keeps a hammering bot to one report a day.
		if ( $reporting ) {
			$reporter->queue_if_eligible( $ip_tracker->get_client_ip(), current_time( 'mysql', true ) );
		}
	}

	/**
	 * Build the absolute URL path prefix that trap URLs start with.
	 *
	 * Lazily computed rather than stored on the instance so the handler
	 * picks up the right home_url() value if WordPress is still warming
	 * up when the constructor runs.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function trap_prefix(): string {
		$home_path = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		return $home_path . '/' . $this->base_path . '/';
	}
}
