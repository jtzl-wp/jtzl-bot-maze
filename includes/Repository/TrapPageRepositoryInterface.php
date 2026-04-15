<?php
/**
 * Trap Page Repository Interface
 *
 * @package JTZL\Bot_Labyrinth
 * @since 1.0.0
 */

namespace JTZL\Bot_Labyrinth\Repository;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Labyrinth\Entity\TrapPage;

/**
 * Interface for trap page repository implementations.
 *
 * @since 1.0.0
 */
interface TrapPageRepositoryInterface {

	/**
	 * Create a new trap page.
	 *
	 * @param array $data Trap page data.
	 * @return int The created page ID, or 0 on failure.
	 * @since 1.0.0
	 */
	public function create( array $data ): int;

	/**
	 * Find a trap page by slug.
	 *
	 * @param string $slug URL slug.
	 * @return TrapPage|null The trap page entity or null.
	 * @since 1.0.0
	 */
	public function find_by_slug( string $slug ): ?TrapPage;

	/**
	 * Find a trap page by ID.
	 *
	 * @param int $id Page ID.
	 * @return TrapPage|null The trap page entity or null.
	 * @since 1.0.0
	 */
	public function find_by_id( int $id ): ?TrapPage;

	/**
	 * Get active trap pages for injection into real pages.
	 * Uses deterministic selection based on a seed (post ID hash).
	 *
	 * @param int $count Number of pages to retrieve.
	 * @param int $seed  Seed for deterministic selection.
	 * @return TrapPage[] Array of trap page entities.
	 * @since 1.0.0
	 */
	public function get_random_active( int $count, int $seed = 0 ): array;

	/**
	 * Get all active trap pages.
	 *
	 * @return TrapPage[] Array of trap page entities.
	 * @since 1.0.0
	 */
	public function get_all_active(): array;

	/**
	 * Get a paginated slice of active trap pages.
	 *
	 * @param int $limit  Maximum number of rows to return.
	 * @param int $offset Number of rows to skip.
	 * @return TrapPage[] Array of trap page entities.
	 * @since 1.0.0
	 */
	public function get_active_paginated( int $limit, int $offset ): array;

	/**
	 * Get child trap pages for a given parent.
	 *
	 * @param int $parent_id Parent trap page ID.
	 * @return TrapPage[] Array of child trap page entities.
	 * @since 1.0.0
	 */
	public function get_children( int $parent_id ): array;

	/**
	 * Count active trap pages.
	 *
	 * @return int Total active trap pages.
	 * @since 1.0.0
	 */
	public function count_active(): int;

	/**
	 * Count active trap pages at depth level 1.
	 *
	 * @return int Total active entry-level trap pages.
	 * @since 1.0.0
	 */
	public function count_active_entry_pages(): int;

	/**
	 * Delete all trap pages.
	 *
	 * @return int Number of rows deleted.
	 * @since 1.0.0
	 */
	public function delete_all(): int;

	/**
	 * Check if a slug exists.
	 *
	 * @param string $slug Slug to check.
	 * @return bool True if slug exists.
	 * @since 1.0.0
	 */
	public function slug_exists( string $slug ): bool;
}
