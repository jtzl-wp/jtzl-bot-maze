<?php
/**
 * Request Log Repository Interface
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

use Labyrinth\Entity\BotRequest;

/**
 * Interface for request log repository implementations.
 *
 * @since 1.0.0
 */
interface RequestLogRepositoryInterface {

	/**
	 * Log a request.
	 *
	 * @param array $data Request data.
	 * @return int The created log ID, or 0 on failure.
	 * @since 1.0.0
	 */
	public function create( array $data ): int;

	/**
	 * Get recent log entries.
	 *
	 * @param int $limit  Number of entries.
	 * @param int $offset Offset for pagination.
	 * @return BotRequest[] Array of request entities.
	 * @since 1.0.0
	 */
	public function get_recent( int $limit = 50, int $offset = 0 ): array;

	/**
	 * Prune entries older than specified days.
	 *
	 * @param int $days Number of days to retain.
	 * @return int Number of rows deleted.
	 * @since 1.0.0
	 */
	public function prune_old_entries( int $days ): int;

	/**
	 * Count total log entries.
	 *
	 * @return int Total count.
	 * @since 1.0.0
	 */
	public function count_total(): int;
}
