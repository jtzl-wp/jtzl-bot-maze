<?php
/**
 * Bot Score Repository Interface
 *
 * @package Labyrinth
 */

namespace Labyrinth\Repository;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use Labyrinth\Entity\BotScore;

/**
 * Interface for bot score repository implementations.
 */
interface BotScoreRepositoryInterface {

	/**
	 * Find or create a bot score record for an IP+UA pair.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash SHA256-truncated UA hash.
	 * @return BotScore|null The bot score entity.
	 */
	public function find_or_create( string $ip_address, string $user_agent_hash ): ?BotScore;

	/**
	 * Find a bot score by IP+UA identity.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash UA hash.
	 * @return BotScore|null The bot score entity or null.
	 */
	public function find_by_identity( string $ip_address, string $user_agent_hash ): ?BotScore;

	/**
	 * Find a bot score by ID.
	 *
	 * @param int $id Score ID.
	 * @return BotScore|null The bot score entity or null.
	 */
	public function find_by_id( int $id ): ?BotScore;

	/**
	 * Update bot score after a visit.
	 *
	 * @param int   $id              Score record ID.
	 * @param float $points_to_add   Points to add.
	 * @param int   $depth_reached   Depth reached in this visit.
	 * @param int   $block_threshold Score threshold for auto-blocking (0 = no auto-block).
	 * @return bool True on success.
	 */
	public function update_score( int $id, float $points_to_add, int $depth_reached, int $block_threshold = 0 ): bool;

	/**
	 * Check if an IP+UA is blocked.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash UA hash.
	 * @return bool True if blocked.
	 */
	public function is_blocked( string $ip_address, string $user_agent_hash ): bool;

	/**
	 * Get top bots by score.
	 *
	 * @param int $limit Number of results.
	 * @return BotScore[] Array of bot score entities.
	 */
	public function get_top_by_score( int $limit = 10 ): array;

	/**
	 * Count total unique bot identities.
	 *
	 * @return int Total count.
	 */
	public function count_total(): int;

	/**
	 * Count blocked bots.
	 *
	 * @return int Blocked count.
	 */
	public function count_blocked(): int;
}
