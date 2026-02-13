<?php
/**
 * Trap Visit Repository Interface
 *
 * @package Labyrinth
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
 */
interface TrapVisitRepositoryInterface {

	/**
	 * Record a trap page visit.
	 *
	 * @param array $data Visit data.
	 * @return int The created visit ID, or 0 on failure.
	 */
	public function create( array $data ): int;

	/**
	 * Count visits by session ID.
	 *
	 * @param string $session_id Session identifier.
	 * @return int Number of visits.
	 */
	public function count_by_session( string $session_id ): int;

	/**
	 * Get max depth reached by session.
	 *
	 * @param string $session_id Session identifier.
	 * @return int Maximum depth.
	 */
	public function get_max_depth_by_session( string $session_id ): int;

	/**
	 * Get recent visits with pagination.
	 *
	 * @param int $limit  Number of visits.
	 * @param int $offset Offset for pagination.
	 * @return TrapVisit[] Array of visit entities.
	 */
	public function get_recent( int $limit = 50, int $offset = 0 ): array;

	/**
	 * Count total visits.
	 *
	 * @return int Total visit count.
	 */
	public function count_total(): int;

	/**
	 * Count visits in the last N days.
	 *
	 * @param int $days Number of days.
	 * @return int Visit count.
	 */
	public function count_recent_days( int $days ): int;

	/**
	 * Get daily visit counts for chart data.
	 *
	 * @param int $days Number of days to look back.
	 * @return array<string, int> Array of date => count pairs.
	 */
	public function get_daily_counts( int $days = 30 ): array;
}
