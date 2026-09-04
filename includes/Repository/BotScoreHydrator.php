<?php
/**
 * Bot score row hydration.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Repository;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use DateTime;
use JTZL\Bot_Maze\Entity\BotScore;

/**
 * Maps a bot_scores row onto its entity.
 *
 * Extracted from BotScoreRepository so the row-to-entity contract has one
 * named home, and so query collaborators can hydrate without reaching back
 * into the repository.
 */
final class BotScoreHydrator {

	/**
	 * Build an entity from a database row.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return BotScore Bot score entity.
	 */
	public static function from_row( array $row ): BotScore {
		return new BotScore(
			(int) $row['id'],
			(string) $row['ip_address'],
			(string) $row['user_agent_hash'],
			(float) $row['score'],
			(int) $row['trap_visits_count'],
			(int) $row['max_depth_reached'],
			new DateTime( (string) $row['first_seen_at'] ),
			new DateTime( (string) $row['last_seen_at'] ),
			(bool) $row['is_blocked'],
			(string) $row['user_agent'],
			(string) $row['country_code'],
			null === $row['abuseipdb_reported_at'] ? null : (string) $row['abuseipdb_reported_at']
		);
	}
}
