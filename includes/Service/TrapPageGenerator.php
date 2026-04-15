<?php
/**
 * Trap Page Generator Service
 *
 * Generates trap pages from content templates.
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

use JTZL\Bot_Labyrinth\Repository\TrapPageRepositoryInterface;

/**
 * Trap Page Generator
 *
 * Creates initial trap pages, deeper pages, and handles regeneration.
 *
 * @since 1.0.0
 */
class TrapPageGenerator {

	/**
	 * Trap page repository.
	 *
	 * @var TrapPageRepositoryInterface
	 * @since 1.0.0
	 */
	private TrapPageRepositoryInterface $repository;

	/**
	 * Content template engine.
	 *
	 * @var ContentTemplateEngine
	 * @since 1.0.0
	 */
	private ContentTemplateEngine $engine;

	/**
	 * Number of initial entry-level pages to generate.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private int $initial_page_count;

	/**
	 * Maximum depth of the trap maze.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private int $max_depth;

	/**
	 * Number of child links per trap page.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private int $links_per_trap_page;

	/**
	 * Global maximum total trap pages.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private int $max_trap_pages;

	/**
	 * Constructor.
	 *
	 * @param TrapPageRepositoryInterface $repository          Trap page repository.
	 * @param ContentTemplateEngine       $engine               Content generation engine.
	 * @param int                         $initial_page_count  Number of entry pages to generate.
	 * @param int                         $max_depth           Maximum trap maze depth.
	 * @param int                         $links_per_trap_page Links per trap page.
	 * @param int                         $max_trap_pages      Global maximum trap pages.
	 * @since 1.0.0
	 */
	public function __construct(
		TrapPageRepositoryInterface $repository,
		ContentTemplateEngine $engine,
		int $initial_page_count,
		int $max_depth,
		int $links_per_trap_page,
		int $max_trap_pages = 500
	) {
		$this->repository          = $repository;
		$this->engine              = $engine;
		$this->initial_page_count  = $initial_page_count;
		$this->max_depth           = $max_depth;
		$this->links_per_trap_page = $links_per_trap_page;
		$this->max_trap_pages      = $max_trap_pages;
	}

	/**
	 * Generate initial entry-level trap pages.
	 *
	 * Called on plugin activation. Only creates entry pages (depth 1).
	 * Deeper pages are generated lazily when bots visit them.
	 *
	 * @return int Number of pages generated.
	 * @since 1.0.0
	 */
	public function generate_initial_pages(): int {
		$count = 0;

		for ( $i = 0; $i < $this->initial_page_count; $i++ ) {
			if ( ! $this->has_budget() ) {
				break;
			}

			$seed = $this->generate_seed();
			$id   = $this->create_page( $seed, 1, null );

			if ( $id > 0 ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Regenerate all trap pages.
	 *
	 * Deletes all existing pages and creates fresh entry pages.
	 *
	 * @return int Number of new pages generated.
	 * @since 1.0.0
	 */
	public function regenerate(): int {
		$this->repository->delete_all();
		return $this->generate_initial_pages();
	}

	/**
	 * Ensure minimum number of entry pages exist.
	 *
	 * Called by daily cron to maintain the trap maze.
	 *
	 * @return int Number of new pages added.
	 * @since 1.0.0
	 */
	public function ensure_minimum_pages(): int {
		$current_count = $this->repository->count_active_entry_pages();

		if ( $current_count >= $this->initial_page_count ) {
			return 0;
		}

		$needed = $this->initial_page_count - $current_count;
		$count  = 0;

		for ( $i = 0; $i < $needed; $i++ ) {
			if ( ! $this->has_budget() ) {
				break;
			}

			$seed = $this->generate_seed();
			$id   = $this->create_page( $seed, 1, null );

			if ( $id > 0 ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get existing children for a trap page, lazily generating them if needed.
	 *
	 * @param \JTZL\Bot_Labyrinth\Entity\TrapPage $page Trap page entity.
	 * @return array Child TrapPage entities.
	 * @since 1.0.0
	 */
	public function get_or_generate_children( \JTZL\Bot_Labyrinth\Entity\TrapPage $page ): array {
		$children = $this->repository->get_children( $page->id );

		if ( empty( $children ) && $page->depth_level < $this->max_depth ) {
			$this->generate_children_for(
				$page->id,
				$page->seed,
				$page->depth_level + 1
			);
			$children = $this->repository->get_children( $page->id );
		}

		return $children;
	}

	/**
	 * Generate child pages for a parent trap page (one level deep).
	 *
	 * Called lazily when a bot visits a trap page that has no children yet.
	 *
	 * @param int    $parent_id    Parent trap page ID.
	 * @param string $parent_seed  Parent seed for deriving child seeds.
	 * @param int    $depth_level  Depth level of the children to create.
	 * @return array Created child page data arrays (id, slug, title).
	 * @since 1.0.0
	 */
	public function generate_children_for( int $parent_id, string $parent_seed, int $depth_level ): array {
		if ( $depth_level > $this->max_depth ) {
			return array();
		}

		// Guard against concurrent requests generating duplicate children.
		if ( ! empty( $this->repository->get_children( $parent_id ) ) ) {
			return array();
		}

		$children = array();

		for ( $i = 0; $i < $this->links_per_trap_page; $i++ ) {
			if ( ! $this->has_budget() ) {
				break;
			}

			$child_seed = md5( $parent_seed . '_d' . $depth_level . '_c' . $i );
			// Disable slug collision retry: if slug exists, a concurrent request
			// already created this child page, so skip instead of creating duplicates.
			$child_id = $this->create_page( $child_seed, $depth_level, $parent_id, false );

			if ( $child_id > 0 ) {
				$children[] = $child_id;
			}
		}

		return $children;
	}

	/**
	 * Create a single trap page.
	 *
	 * @param string   $seed              Randomization seed.
	 * @param int      $depth_level       Depth level (1 = entry).
	 * @param int|null $parent_id         Parent page ID, or null for entry pages.
	 * @param bool     $retry_on_collision Whether to retry with modified seeds on slug collision.
	 * @return int Created page ID, or 0 on failure.
	 * @since 1.0.0
	 */
	private function create_page( string $seed, int $depth_level, ?int $parent_id, bool $retry_on_collision = true ): int {
		$effective_seed = $seed;
		$slug           = $this->engine->generate_slug( $effective_seed );

		if ( $retry_on_collision ) {
			// Retry with modified seeds to handle legitimate collisions.
			$attempt = 0;
			while ( $this->repository->slug_exists( $slug ) && $attempt < 5 ) {
				++$attempt;
				$effective_seed = md5( $seed . '_' . $attempt );
				$slug           = $this->engine->generate_slug( $effective_seed );
			}

			if ( $this->repository->slug_exists( $slug ) ) {
				return 0;
			}
		}

		// For child pages (retry_on_collision=false), skip slug_exists() entirely;
		// INSERT IGNORE handles duplicates at the DB level.
		return $this->repository->create(
			array(
				'slug'           => $slug,
				'title'          => $this->engine->generate_title( $effective_seed ),
				'content'        => $this->engine->generate_content( $effective_seed ),
				'template_id'    => $this->engine->generate_template_id( $effective_seed ),
				'depth_level'    => $depth_level,
				'parent_page_id' => $parent_id,
				'seed'           => $effective_seed,
				'status'         => 'active',
			)
		);
	}

	/**
	 * Check whether the global trap page budget allows more pages.
	 *
	 * Best-effort: concurrent requests may pass this check simultaneously
	 * and overshoot the cap by up to (concurrent_workers * links_per_trap_page).
	 * The duplicate-slug guard in create_page (INSERT IGNORE) limits the
	 * practical overshoot, and the cap still prevents unbounded growth.
	 *
	 * @return bool True if under the global maximum.
	 * @since 1.0.0
	 */
	private function has_budget(): bool {
		return $this->repository->count_active() < $this->max_trap_pages;
	}

	/**
	 * Generate a unique seed string.
	 *
	 * @return string Unique seed (32 chars).
	 * @since 1.0.0
	 */
	private function generate_seed(): string {
		return md5( uniqid( 'jtzl_bl_', true ) . wp_rand() );
	}
}
