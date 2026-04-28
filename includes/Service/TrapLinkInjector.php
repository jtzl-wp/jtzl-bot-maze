<?php
/**
 * Trap Link Injector Service
 *
 * Injects hidden trap links into real page content and footer.
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

use JTZL\Bot_Maze\Repository\TrapPageRepositoryInterface;
use JTZL\Bot_Maze\WordPress\ContextInterface;

/**
 * Trap Link Injector
 *
 * Hooks into the_content and wp_footer to inject invisible links
 * that lure bots into the trap page maze.
 *
 * @since 1.0.0
 */
class TrapLinkInjector {

	/**
	 * Trap page repository.
	 *
	 * @var TrapPageRepositoryInterface
	 * @since 1.0.0
	 */
	private TrapPageRepositoryInterface $repository;

	/**
	 * Number of links to inject per page.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private int $links_per_page;

	/**
	 * Injection method: 'content', 'footer', or 'both'.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $injection_method;

	/**
	 * Whether the plugin is enabled.
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
	 * WordPress context facade.
	 *
	 * @var ContextInterface
	 * @since 1.0.0
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @param TrapPageRepositoryInterface $repository       Trap page repository.
	 * @param int                         $links_per_page   Number of links per page.
	 * @param string                      $injection_method Where to inject links.
	 * @param bool                        $enabled          Whether injection is enabled.
	 * @param string                      $base_path        URL base path.
	 * @param ContextInterface            $wp               WordPress context facade.
	 * @since 1.0.0
	 */
	public function __construct(
		TrapPageRepositoryInterface $repository,
		int $links_per_page,
		string $injection_method,
		bool $enabled,
		string $base_path,
		ContextInterface $wp
	) {
		$this->repository       = $repository;
		$this->links_per_page   = $links_per_page;
		$this->injection_method = $injection_method;
		$this->enabled          = $enabled;
		$this->base_path        = $base_path;
		$this->wp               = $wp;
	}

	/**
	 * Inject hidden trap links into post content.
	 *
	 * Hooked to `the_content` filter.
	 *
	 * @param string $content Post content.
	 * @return string Modified content with trap links appended.
	 * @since 1.0.0
	 */
	public function inject_into_content( string $content ): string {
		if ( ! $this->should_inject( 'content' ) ) {
			return $content;
		}

		$links = $this->generate_hidden_links( 'content' );

		if ( empty( $links ) ) {
			return $content;
		}

		return $content . "\n" . $links;
	}

	/**
	 * Generate hidden trap link HTML for the footer.
	 *
	 * @return string Hidden links HTML, or empty string if injection should not proceed.
	 * @since 1.0.0
	 */
	public function inject_into_footer(): string {
		if ( ! $this->should_inject( 'footer' ) ) {
			return '';
		}

		return $this->generate_hidden_links( 'footer' );
	}

	/**
	 * Check if trap links should be injected for the given method.
	 *
	 * @param string $method The injection point: 'content' or 'footer'.
	 * @return bool True if injection should proceed.
	 * @since 1.0.0
	 */
	private function should_inject( string $method ): bool {
		// Plugin must be enabled.
		if ( ! $this->enabled ) {
			return false;
		}

		// Don't inject in admin, feeds, or REST API.
		if ( $this->wp->is_admin() || $this->wp->is_feed() || $this->wp->is_rest_request() ) {
			return false;
		}

		// Only inject on singular posts/pages.
		if ( ! $this->wp->is_singular() ) {
			return false;
		}

		// Check if this injection method is active.
		if ( 'both' !== $this->injection_method && $method !== $this->injection_method ) {
			return false;
		}

		return true;
	}

	/**
	 * Generate hidden link HTML for trap pages.
	 *
	 * Uses the current post ID as a seed for deterministic page selection,
	 * avoiding extra DB queries on repeat page loads.
	 *
	 * @param string $context Injection context ('content' or 'footer') for seed variation.
	 * @return string HTML containing hidden trap links.
	 * @since 1.0.0
	 */
	private function generate_hidden_links( string $context = '' ): string {
		$post_id = $this->wp->get_the_id();

		if ( ! $post_id ) {
			return '';
		}

		// Use post ID + context as seed for deterministic but unique selection per injection point.
		$seed       = (int) crc32( 'jtzl_bm_' . $post_id . '_' . $context );
		$trap_pages = $this->repository->get_random_active( $this->links_per_page, $seed );

		if ( empty( $trap_pages ) ) {
			return '';
		}

		// Use standard "Visually Hidden" accessible pattern (sr-only) to avoid SEO penalties for "hidden text".
		$html = '<div style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;" aria-hidden="true">';

		foreach ( $trap_pages as $page ) {
			$url   = $this->wp->home_url( '/' . $this->base_path . '/' . $page->slug . '/' );
			$html .= sprintf(
				'<a href="%s" rel="nofollow" tabindex="-1" aria-hidden="true">%s</a>',
				esc_url( $url ),
				esc_html( $page->title )
			);
		}

		$html .= '</div>';

		return $html;
	}
}
