<?php
/**
 * Bot Score Repository
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
 * Bot Score Repository
 *
 * Handles database operations for bot score entities.
 *
 * @since 1.0.0
 */
class BotScoreRepository implements BotScoreRepositoryInterface {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 * @since 1.0.0
	 */
	private \wpdb $wpdb;

	/**
	 * Table name.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $table_name;

	/**
	 * Blocked-bots list query builder.
	 *
	 * @var BlockedBotQuery
	 * @since 1.4.0
	 */
	private BlockedBotQuery $blocked_query;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database object.
	 * @since 1.0.0
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb          = $wpdb;
		$this->table_name    = $wpdb->prefix . 'jtzl_bm_bot_scores';
		$this->blocked_query = new BlockedBotQuery( $wpdb );
	}

	/**
	 * Find or create a bot score record for an IP+UA pair.
	 *
	 * UA and country are required so every write path self-heals the row.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash SHA256-truncated UA hash.
	 * @param string $user_agent      Readable user agent (the exact string that produced the hash).
	 * @param string $country_code    Resolved country code ('' when unresolved).
	 * @return BotScore|null The bot score entity.
	 * @since 1.0.0
	 */
	public function find_or_create( string $ip_address, string $user_agent_hash, string $user_agent, string $country_code ): ?BotScore {
		$existing = $this->refresh_denormalized_fields( $this->find_by_identity( $ip_address, $user_agent_hash ), $user_agent, $country_code );

		if ( null !== $existing ) {
			return $existing;
		}

		$now = current_time( 'mysql' );

		$result = $this->wpdb->insert(
			$this->table_name,
			array(
				'ip_address'        => $ip_address,
				'user_agent_hash'   => $user_agent_hash,
				'user_agent'        => $user_agent,
				'country_code'      => $country_code,
				'score'             => 0.00,
				'trap_visits_count' => 0,
				'max_depth_reached' => 0,
				'first_seen_at'     => $now,
				'last_seen_at'      => $now,
				'is_blocked'        => 0,
			),
			array( '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s', '%d' )
		);

		if ( false === $result ) {
			// Lost a race: another request inserted first. Heal its row too.
			return $this->refresh_denormalized_fields( $this->find_by_identity( $ip_address, $user_agent_hash ), $user_agent, $country_code );
		}

		return $this->find_by_id( (int) $this->wpdb->insert_id );
	}

	/**
	 * Self-heal an existing row's user agent and refresh a non-empty country
	 * (an empty code never erases a known country), returning the updated
	 * entity; a null entity passes through so callers can pipe find_by_identity() in.
	 *
	 * @param BotScore|null $existing     Existing entity, or null when absent.
	 * @param string        $user_agent   Readable user agent.
	 * @param string        $country_code Resolved country code ('' when unresolved).
	 * @return BotScore|null The same entity (refreshed), or null.
	 * @since 1.4.0
	 */
	private function refresh_denormalized_fields( ?BotScore $existing, string $user_agent, string $country_code ): ?BotScore {
		if ( null === $existing ) {
			return null;
		}

		$data = array();

		if ( $existing->user_agent !== $user_agent ) {
			$data['user_agent']   = $user_agent;
			$existing->user_agent = $user_agent;
		}

		if ( '' !== $country_code && $existing->country_code !== $country_code ) {
			$data['country_code']   = $country_code;
			$existing->country_code = $country_code;
		}

		if ( ! empty( $data ) ) {
			$this->wpdb->update( $this->table_name, $data, array( 'id' => $existing->id ), '%s', array( '%d' ) );
		}

		return $existing;
	}

	/**
	 * Find a bot score by IP+UA identity.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash UA hash.
	 * @return BotScore|null The bot score entity or null.
	 * @since 1.0.0
	 */
	public function find_by_identity( string $ip_address, string $user_agent_hash ): ?BotScore {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE ip_address = %s AND user_agent_hash = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ip_address,
			$user_agent_hash
		);

		$row = $this->wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( null === $row ) {
			return null;
		}

		return BotScoreHydrator::from_row( $row );
	}

	/**
	 * Find a bot score by ID.
	 *
	 * @param int $id Score ID.
	 * @return BotScore|null The bot score entity or null.
	 * @since 1.0.0
	 */
	public function find_by_id( int $id ): ?BotScore {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$id
		);

		$row = $this->wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( null === $row ) {
			return null;
		}

		return BotScoreHydrator::from_row( $row );
	}

	/**
	 * Update bot score after a visit.
	 *
	 * Blocking is computed atomically in SQL against the real current score
	 * to avoid TOCTOU races from concurrent requests.
	 *
	 * @param int   $id              Score record ID.
	 * @param float $points_to_add   Points to add.
	 * @param int   $depth_reached   Depth reached in this visit.
	 * @param int   $block_threshold Score threshold for auto-blocking (0 = no auto-block).
	 * @return bool True on success.
	 * @since 1.0.0
	 */
	public function update_score( int $id, float $points_to_add, int $depth_reached, int $block_threshold = 0 ): bool {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare(
			"UPDATE {$this->table_name} SET score = score + %f, trap_visits_count = trap_visits_count + 1, max_depth_reached = GREATEST(max_depth_reached, %d), last_seen_at = %s, is_blocked = GREATEST(is_blocked, CASE WHEN %d > 0 AND score >= %d THEN 1 ELSE 0 END) WHERE id = %d",
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$points_to_add,
			$depth_reached,
			current_time( 'mysql' ),
			$block_threshold,
			$block_threshold,
			$id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $sql );

		return false !== $result;
	}

	/**
	 * Check if an IP+UA is blocked.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash UA hash.
	 * @return bool True if blocked.
	 * @since 1.0.0
	 */
	public function is_blocked( string $ip_address, string $user_agent_hash ): bool {
		$query = $this->wpdb->prepare(
			"SELECT is_blocked FROM {$this->table_name} WHERE ip_address = %s AND user_agent_hash = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ip_address,
			$user_agent_hash
		);

		$result = $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return null !== $result && (bool) $result;
	}

	/**
	 * Record that an address was reported to AbuseIPDB.
	 *
	 * A report is per-IP while an identity is (IP, UA hash), so every row
	 * sharing the address is marked: the address was reported, whichever user
	 * agent tripped the threshold. The timestamp is site-local for parity with
	 * first_seen_at / last_seen_at on the same row.
	 *
	 * @param string $ip_address Reported IP address.
	 * @return bool True on success.
	 */
	public function mark_abuseipdb_reported( string $ip_address ): bool {
		$sql = $this->wpdb->prepare(
			"UPDATE {$this->table_name} SET abuseipdb_reported_at = %s WHERE ip_address = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			current_time( 'mysql' ),
			$ip_address
		);

		$result = $this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result;
	}

	/**
	 * Get top bots by score.
	 *
	 * @param int $limit Number of results.
	 * @return BotScore[] Array of bot score entities.
	 * @since 1.0.0
	 */
	public function get_top_by_score( int $limit = 10 ): array {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} ORDER BY score DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$limit
		);

		$rows = $this->wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return empty( $rows ) ? array() : array_map( array( BotScoreHydrator::class, 'from_row' ), $rows );
	}

	/**
	 * Count total unique bot identities.
	 *
	 * @return int Total count.
	 * @since 1.0.0
	 */
	public function count_total(): int {
		$query = "SELECT COUNT(*) FROM {$this->table_name}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count blocked bots, optionally filtered by a search term.
	 *
	 * @param string $search Optional term matched against IP and user agent.
	 * @return int Blocked count.
	 * @since 1.0.0
	 */
	public function count_blocked( string $search = '' ): int {
		return $this->blocked_query->count( $search );
	}

	/**
	 * Update last_seen_at and increment visit count for a blocked bot.
	 *
	 * Also self-heals the UA and refreshes a non-empty country, mirroring
	 * find_or_create() for the blocked path that bypasses it.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash UA hash.
	 * @param string $user_agent      Readable user agent (the exact string that produced the hash).
	 * @param string $country_code    Resolved country code ('' when unresolved).
	 * @return bool True on success.
	 * @since 1.0.0
	 */
	public function touch_blocked_visit( string $ip_address, string $user_agent_hash, string $user_agent, string $country_code ): bool {
		$sql = $this->wpdb->prepare(
			"UPDATE {$this->table_name} SET trap_visits_count = trap_visits_count + 1, last_seen_at = %s, user_agent = %s, country_code = CASE WHEN %s = '' THEN country_code ELSE %s END WHERE ip_address = %s AND user_agent_hash = %s AND is_blocked = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			current_time( 'mysql' ),
			$user_agent,
			$country_code,
			$country_code,
			$ip_address,
			$user_agent_hash
		);

		$result = $this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result && $result > 0;
	}

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
	public function get_blocked( int $limit = 50, int $offset = 0, string $orderby = 'last_seen_at', string $order = 'DESC', string $search = '' ): array {
		return array_map(
			array( BotScoreHydrator::class, 'from_row' ),
			$this->blocked_query->rows( $limit, $offset, $orderby, $order, $search )
		);
	}

	/**
	 * Prune bot scores that have been inactive longer than the specified number of days.
	 *
	 * This retention policy applies to blocked identities too.
	 *
	 * @param int $days Number of days to retain.
	 * @return int Number of rows deleted.
	 * @since 1.0.0
	 */
	public function prune_stale_entries( int $days ): int {
		$query = $this->wpdb->prepare(
			"DELETE FROM {$this->table_name} WHERE last_seen_at < DATE_SUB(%s, INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			current_time( 'mysql' ),
			$days
		);

		$result = $this->wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result ? (int) $result : 0;
	}
}
