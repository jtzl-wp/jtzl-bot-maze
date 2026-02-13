<?php
/**
 * Request Log Repository Interface
 *
 * @package Labyrinth
 */

namespace Labyrinth\Repository;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use Labyrinth\Entity\BotRequest;

/**
 * Interface for request log repository implementations.
 */
interface RequestLogRepositoryInterface {

	/**
	 * Log a request.
	 *
	 * @param array $data Request data.
	 * @return int The created log ID, or 0 on failure.
	 */
	public function create( array $data ): int;

	/**
	 * Get recent log entries.
	 *
	 * @param int $limit  Number of entries.
	 * @param int $offset Offset for pagination.
	 * @return BotRequest[] Array of request entities.
	 */
	public function get_recent( int $limit = 50, int $offset = 0 ): array;

	/**
	 * Prune entries older than specified days.
	 *
	 * @param int $days Number of days to retain.
	 * @return int Number of rows deleted.
	 */
	public function prune_old_entries( int $days ): int;

	/**
	 * Count total log entries.
	 *
	 * @return int Total count.
	 */
	public function count_total(): int;
}
