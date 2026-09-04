<?php
/**
 * Bot Score Repository Interface
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze\Repository;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Maze\Entity\BotScore;

/**
 * Interface for bot score repository implementations.
 *
 * @since 1.0.0
 */
interface BotScoreRepositoryInterface {

	/**
	 * Find or create a bot score record for an IP+UA pair.
	 *
	 * The readable user agent and country are required (no defaults) so
	 * every caller keeps the denormalized columns populated; existing rows
	 * self-heal a blank user_agent and refresh a non-empty country_code.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash SHA256-truncated UA hash.
	 * @param string $user_agent      Readable user agent (the exact string that produced the hash).
	 * @param string $country_code    Resolved country code ('' when unresolved).
	 * @return BotScore|null The bot score entity.
	 * @since 1.0.0
	 */
	public function find_or_create( string $ip_address, string $user_agent_hash, string $user_agent, string $country_code ): ?BotScore;

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
	 * Count blocked bots, optionally filtered by a search term.
	 *
	 * @param string $search Optional term matched against IP and user agent.
	 * @return int Blocked count.
	 * @since 1.0.0
	 */
	public function count_blocked( string $search = '' ): int;

	/**
	 * Update last_seen_at and increment visit count for a blocked bot.
	 *
	 * Also self-heals the readable user agent and refreshes the country
	 * from a non-empty code, mirroring find_or_create() for the blocked
	 * path that bypasses it.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash UA hash.
	 * @param string $user_agent      Readable user agent (the exact string that produced the hash).
	 * @param string $country_code    Resolved country code ('' when unresolved).
	 * @return bool True on success.
	 * @since 1.0.0
	 */
	public function touch_blocked_visit( string $ip_address, string $user_agent_hash, string $user_agent, string $country_code ): bool;

	/**
	 * Record that an address was reported to AbuseIPDB.
	 *
	 * Marks every row sharing the address, since a report is per-IP while an
	 * identity is (IP, UA hash).
	 *
	 * This is a local mirror for the admin tables, not the system of record —
	 * AbuseIPDB holds that per account. Callers treat a false return as a lost
	 * badge rather than a lost report, in the same best-effort spirit as
	 * touch_blocked_visit().
	 *
	 * @param string $ip_address Reported IP address.
	 * @return bool True on success.
	 */
	public function mark_abuseipdb_reported( string $ip_address ): bool;

	/**
	 * Get blocked bots with pagination, sorting and search.
	 *
	 * @param int    $limit   Number of results.
	 * @param int    $offset  Offset for pagination.
	 * @param string $orderby Column to sort by (validated against an allowlist).
	 * @param string $order   Sort direction, 'ASC' or 'DESC'.
	 * @param string $search  Optional term matched against IP and user agent.
	 * @return BotScore[] Array of blocked bot score entities.
	 * @since 1.0.0
	 */
	public function get_blocked( int $limit = 50, int $offset = 0, string $orderby = 'last_seen_at', string $order = 'DESC', string $search = '' ): array;

	/**
	 * Prune bot scores that have been inactive longer than the specified number of days.
	 *
	 * @param int $days Number of days to retain.
	 * @return int Number of rows deleted.
	 * @since 1.0.0
	 */
	public function prune_stale_entries( int $days ): int;
}
