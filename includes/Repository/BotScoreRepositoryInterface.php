<?php
/**
 * Bot Score Repository Interface
 *
 * @package JTZL\Bot_Be_Gone
 * @since 1.0.0
 */

namespace JTZL\Bot_Be_Gone\Repository;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Be_Gone\Entity\BotScore;

/**
 * Interface for bot score repository implementations.
 *
 * @since 1.0.0
 */
interface BotScoreRepositoryInterface {

	/**
	 * Find or create a bot score record for an IP+UA pair.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash SHA256-truncated UA hash.
	 * @return BotScore|null The bot score entity.
	 * @since 1.0.0
	 */
	public function find_or_create( string $ip_address, string $user_agent_hash ): ?BotScore;

	/**
	 * Find a bot score by IP+UA identity.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash UA hash.
	 * @return BotScore|null The bot score entity or null.
	 * @since 1.0.0
	 */
	public function find_by_identity( string $ip_address, string $user_agent_hash ): ?BotScore;

	/**
	 * Find a bot score by ID.
	 *
	 * @param int $id Score ID.
	 * @return BotScore|null The bot score entity or null.
	 * @since 1.0.0
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
	 * @since 1.0.0
	 */
	public function update_score( int $id, float $points_to_add, int $depth_reached, int $block_threshold = 0 ): bool;

	/**
	 * Check if an IP+UA is blocked.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash UA hash.
	 * @return bool True if blocked.
	 * @since 1.0.0
	 */
	public function is_blocked( string $ip_address, string $user_agent_hash ): bool;

	/**
	 * Get top bots by score.
	 *
	 * @param int $limit Number of results.
	 * @return BotScore[] Array of bot score entities.
	 * @since 1.0.0
	 */
	public function get_top_by_score( int $limit = 10 ): array;

	/**
	 * Count total unique bot identities.
	 *
	 * @return int Total count.
	 * @since 1.0.0
	 */
	public function count_total(): int;

	/**
	 * Count blocked bots.
	 *
	 * @return int Blocked count.
	 * @since 1.0.0
	 */
	public function count_blocked(): int;

	/**
	 * Update last_seen_at and increment visit count for a blocked bot.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash UA hash.
	 * @return bool True on success.
	 * @since 1.0.0
	 */
	public function touch_blocked_visit( string $ip_address, string $user_agent_hash ): bool;

	/**
	 * Get blocked bots ordered by last seen.
	 *
	 * @param int $limit  Number of results.
	 * @param int $offset Offset for pagination.
	 * @return BotScore[] Array of blocked bot score entities.
	 * @since 1.0.0
	 */
	public function get_blocked( int $limit = 50, int $offset = 0 ): array;

	/**
	 * Prune bot scores that have been inactive longer than the specified number of days.
	 *
	 * @param int $days Number of days to retain.
	 * @return int Number of rows deleted.
	 * @since 1.0.0
	 */
	public function prune_stale_entries( int $days ): int;
}
