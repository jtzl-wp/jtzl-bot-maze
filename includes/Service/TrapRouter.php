<?php
/**
 * Trap Router Service
 *
 * Handles URL routing for trap pages via WordPress rewrite rules.
 *
 * @package JTZL\Bot_Be_Gone
 * @since 1.0.0
 */

namespace JTZL\Bot_Be_Gone\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Be_Gone\Repository\TrapPageRepositoryInterface;
use JTZL\Bot_Be_Gone\Entity\TrapPage;
use JTZL\Bot_Be_Gone\Service\TrapPageGenerator;
use JTZL\Bot_Be_Gone\WordPress\ContextInterface;

/**
 * Trap Router
 *
 * Registers rewrite rules, query vars, and handles trap page serving.
 *
 * @since 1.0.0
 */
class TrapRouter {

	/**
	 * Trap page cache for the current request keyed by slug.
	 *
	 * Avoids duplicate repository reads when both template_redirect and
	 * template_include resolve the same trap page.
	 *
	 * @var array<string, TrapPage|null>
	 * @since 1.0.0
	 */
	private array $trap_page_cache = array();

	/**
	 * Trap page repository.
	 *
	 * @var TrapPageRepositoryInterface
	 * @since 1.0.0
	 */
	private TrapPageRepositoryInterface $repository;

	/**
	 * Bot detector service.
	 *
	 * @var BotDetector
	 * @since 1.0.0
	 */
	private BotDetector $bot_detector;

	/**
	 * Trap page generator for lazy child generation.
	 *
	 * @var TrapPageGenerator
	 * @since 1.0.0
	 */
	private TrapPageGenerator $generator;

	/**
	 * Base path for trap URLs.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $base_path;

	/**
	 * Maximum trap maze depth.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private int $max_depth;

	/**
	 * WordPress context facade.
	 *
	 * @var ContextInterface
	 * @since 1.0.0
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @param TrapPageRepositoryInterface $repository   Trap page repository.
	 * @param BotDetector                 $bot_detector Bot detector service.
	 * @param TrapPageGenerator           $generator    Trap page generator.
	 * @param string                      $base_path    URL base path for trap pages.
	 * @param int                         $max_depth    Maximum trap maze depth.
	 * @param ContextInterface            $wp           WordPress context facade.
	 * @since 1.0.0
	 */
	public function __construct(
		TrapPageRepositoryInterface $repository,
		BotDetector $bot_detector,
		TrapPageGenerator $generator,
		string $base_path,
		int $max_depth,
		ContextInterface $wp
	) {
		$this->repository   = $repository;
		$this->bot_detector = $bot_detector;
		$this->generator    = $generator;
		$this->base_path    = $base_path;
		$this->max_depth    = $max_depth;
		$this->wp           = $wp;
	}

	/**
	 * Register custom rewrite rules.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_rewrite_rules(): void {
		$this->wp->add_rewrite_rule(
			'^' . preg_quote( $this->base_path, '#' ) . '/([a-z0-9-]+)/?$',
			'index.php?jtzl_bbg_view=trap_page&jtzl_bbg_slug=$matches[1]',
			'top'
		);
	}

	/**
	 * Register custom query variables.
	 *
	 * @param array $vars Existing query variables.
	 * @return array Modified query variables.
	 * @since 1.0.0
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'jtzl_bbg_view';
		$vars[] = 'jtzl_bbg_slug';
		return $vars;
	}

	/**
	 * Handle trap page requests on template_redirect.
	 *
	 * Records visits and serves appropriate responses.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function handle_request(): void {
		$view = $this->wp->get_query_var( 'jtzl_bbg_view', '' );

		if ( empty( $view ) ) {
			return;
		}

		if ( 'trap_page' !== $view ) {
			return;
		}

		$slug = $this->wp->get_query_var( 'jtzl_bbg_slug', '' );

		if ( empty( $slug ) ) {
			return;
		}

		$trap_page = $this->get_trap_page_by_slug( $slug );

		if ( null === $trap_page ) {
			// Lightweight plugin-owned 404 — avoids loading the full theme 404 template.
			$this->wp->status_header( 404 );
			if ( ! $this->wp->headers_sent() ) {
				$this->wp->header( 'X-Robots-Tag: noindex, nofollow', true );
				$this->wp->header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
			}
			$this->wp->wp_die(
				'<h1>' . esc_html__( 'Not Found', 'jtzl-bot-be-gone' ) . '</h1>',
				esc_html__( 'Not Found', 'jtzl-bot-be-gone' ),
				array( 'response' => 404 )
			);
			return;
		}

		// Clear WordPress 404 state and set 200 status for valid trap pages.
		$this->wp->clear_404();
		$this->wp->status_header( 200 );

		// Send noindex directive to prevent search engine indexing.
		if ( ! headers_sent() ) {
			// @codeCoverageIgnoreStart
			header( 'X-Robots-Tag: noindex, nofollow', true );
			// @codeCoverageIgnoreEnd
		}

		// Skip visit recording for admin users previewing trap pages.
		if ( $this->wp->current_user_can( 'manage_options' ) ) {
			return;
		}

		// Defer visit recording to shutdown (after response sent).
		$this->wp->add_action(
			'shutdown',
			function () use ( $trap_page ) {
				$this->bot_detector->record_visit( $trap_page );
			}
		);
	}

	/**
	 * Load trap page template.
	 *
	 * @param string $template The path of the template to include.
	 * @return string Modified template path.
	 * @since 1.0.0
	 */
	public function template_loader( string $template ): string {
		$view = $this->wp->get_query_var( 'jtzl_bbg_view', '' );

		if ( 'trap_page' !== $view ) {
			return $template;
		}

		$slug = $this->wp->get_query_var( 'jtzl_bbg_slug', '' );

		if ( empty( $slug ) ) {
			return $template;
		}

		$trap_page = $this->get_trap_page_by_slug( $slug );

		if ( null === $trap_page ) {
			return $template;
		}

		// Store trap page data for the template.
		$GLOBALS['jtzl_bbg_trap_page'] = $trap_page;

		// Get child pages for internal links, generating lazily if needed.
		$children = $this->repository->get_children( $trap_page->id );

		if ( empty( $children ) && $trap_page->depth_level < $this->max_depth ) {
			$this->generator->generate_children_for(
				$trap_page->id,
				$trap_page->seed,
				$trap_page->depth_level + 1
			);
			$children = $this->repository->get_children( $trap_page->id );
		}

		$GLOBALS['jtzl_bbg_trap_children']  = $children;
		$GLOBALS['jtzl_bbg_trap_base_path'] = $this->base_path;

		$plugin_template = $this->wp->get_plugin_dir() . 'templates/trap-page.php';

		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return $template;
	}

	/**
	 * Resolve and cache a trap page by slug for this request.
	 *
	 * @param string $slug Trap page slug.
	 * @return TrapPage|null
	 * @since 1.0.0
	 */
	private function get_trap_page_by_slug( string $slug ): ?TrapPage {
		if ( array_key_exists( $slug, $this->trap_page_cache ) ) {
			return $this->trap_page_cache[ $slug ];
		}

		$this->trap_page_cache[ $slug ] = $this->repository->find_by_slug( $slug );

		return $this->trap_page_cache[ $slug ];
	}
}
