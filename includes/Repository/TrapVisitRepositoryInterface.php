<?php
/**
 * Trap Visit Repository Interface
 *
 * @package Labyrinth
 * @since 1.0.0
 */

namespace Labyrinth\Repository;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use Labyrinth\Entity\TrapVisit;

/**
 * Interface for trap visit repository implementations.
 *
 * @since 1.0.0
 */
interface TrapVisitRepositoryInterface {

	/**
	 * Record a trap page visit.
	 *
	 * @param array $data Visit data.
	 * @return int The created visit ID, or 0 on failure.
	 * @since 1.0.0
	 */
	public function create( array $data ): int;

	/**
	 * Count visits by session ID.
	 *
	 * @param string $session_id Session identifier.
	 * @return int Number of visits.
	 * @since 1.0.0
	 */
	public function count_by_session( string $session_id ): int;

	/**
	 * Get max depth reached by session.
	 *
	 * @param string $session_id Session identifier.
	 * @return int Maximum depth.
	 * @since 1.0.0
	 */
	public function get_max_depth_by_session( string $session_id ): int;

	/**
	 * Get recent visits with pagination.
	 *
	 * @param int $limit  Number of visits.
	 * @param int $offset Offset for pagination.
	 * @return TrapVisit[] Array of visit entities.
	 * @since 1.0.0
	 */
	public function get_recent( int $limit = 50, int $offset = 0 ): array;

	/**
	 * Count total visits.
	 *
	 * @return int Total visit count.
	 * @since 1.0.0
	 */
	public function count_total(): int;

	/**
	 * Prune visits older than the specified number of days.
	 *
	 * @param int $days Number of days to retain.
	 * @return int Number of rows deleted.
	 * @since 1.0.0
	 */
	public function prune_old_entries( int $days ): int;

	/**
	 * Get visits within the last N days with pagination.
	 *
	 * @param int $days   Number of days.
	 * @param int $limit  Number of visits.
	 * @param int $offset Offset for pagination.
	 * @return TrapVisit[] Array of visit entities.
	 * @since 1.0.0
	 */
	public function get_recent_days( int $days, int $limit = 50, int $offset = 0 ): array;

	/**
	 * Count all visits in the last N days (all request types).
	 *
	 * @param int $days Number of days.
	 * @return int Visit count.
	 * @since 1.0.0
	 */
	public function count_all_recent_days( int $days ): int;

	/**
	 * Count visits in the last N days.
	 *
	 * @param int $days Number of days.
	 * @return int Visit count.
	 * @since 1.0.0
	 */
	public function count_recent_days( int $days ): int;

	/**
	 * Get daily visit counts for chart data.
	 *
	 * @param int $days Number of days to look back.
	 * @return array<string, int> Array of date => count pairs.
	 * @since 1.0.0
	 */
	public function get_daily_counts( int $days = 30 ): array;

	/**
	 * Get visit counts grouped by country code.
	 *
	 * Only counts trap visits (excludes blocked/exempted). Excludes rows
	 * with an empty country code (geo tracking was disabled at visit time).
	 *
	 * @return array<string, int> Array of country_code => count pairs.
	 * @since 1.0.0
	 */
	public function get_counts_by_country(): array;
}
