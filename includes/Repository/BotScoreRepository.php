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

use DateTime;
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
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database object.
	 * @since 1.0.0
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'jtzl_bm_bot_scores';
	}

	/**
	 * Find or create a bot score record for an IP+UA pair.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash SHA256-truncated UA hash.
	 * @return BotScore|null The bot score entity.
	 * @since 1.0.0
	 */
	public function find_or_create( string $ip_address, string $user_agent_hash ): ?BotScore {
		$existing = $this->find_by_identity( $ip_address, $user_agent_hash );

		if ( null !== $existing ) {
			return $existing;
		}

		$now = current_time( 'mysql' );

		$result = $this->wpdb->insert(
			$this->table_name,
			array(
				'ip_address'        => $ip_address,
				'user_agent_hash'   => $user_agent_hash,
				'score'             => 0.00,
				'trap_visits_count' => 0,
				'max_depth_reached' => 0,
				'first_seen_at'     => $now,
				'last_seen_at'      => $now,
				'is_blocked'        => 0,
			),
			array( '%s', '%s', '%f', '%d', '%d', '%s', '%s', '%d' )
		);

		if ( false === $result ) {
			// Possible race: another request may have inserted between our check and insert.
			return $this->find_by_identity( $ip_address, $user_agent_hash );
		}

		return $this->find_by_id( (int) $this->wpdb->insert_id );
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

		return $this->hydrate( $row );
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

		return $this->hydrate( $row );
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

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'hydrate' ), $rows );
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
		$params = array();
		$where  = $this->blocked_where( $search, $params );
		if ( empty( $params ) ) {
			return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$query = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}",
			$params
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Update last_seen_at and increment visit count for a blocked bot.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash UA hash.
	 * @return bool True on success.
	 * @since 1.0.0
	 */
	public function touch_blocked_visit( string $ip_address, string $user_agent_hash ): bool {
		$sql = $this->wpdb->prepare(
			"UPDATE {$this->table_name} SET trap_visits_count = trap_visits_count + 1, last_seen_at = %s WHERE ip_address = %s AND user_agent_hash = %s AND is_blocked = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			current_time( 'mysql' ),
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
		$allowed = array( 'ip_address', 'score', 'trap_visits_count', 'max_depth_reached', 'first_seen_at', 'last_seen_at' );
		$orderby = in_array( $orderby, $allowed, true ) ? $orderby : 'last_seen_at';
		$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		$params = array();
		$where  = $this->blocked_where( $search, $params );
		$params[] = $limit;
		$params[] = $offset;

		// $orderby and $order come from fixed allowlists; every value is bound.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			$params
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders

		$rows = $this->wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return empty( $rows ) ? array() : array_map( array( $this, 'hydrate' ), $rows );
	}

	/**
	 * Build the WHERE body for blocked-bot queries, populating $params by reference.
	 *
	 * The UA search matches SHA2(user_agent,256) of trap-visit user agents, which
	 * mirrors the stored PHP sha256() hash (an integration test guards it); bots
	 * whose trap visits were pruned are not UA-searchable.
	 *
	 * @param string $search Search term for IP and user agent.
	 * @param array  $params Bound-parameter accumulator, populated by reference.
	 * @return string SQL WHERE body (without the leading "WHERE").
	 * @since 1.3.0
	 */
	private function blocked_where( string $search, array &$params ): string {
		$params = array();

		if ( '' === $search ) {
			return 'is_blocked = 1';
		}

		$like     = '%' . $this->wpdb->esc_like( $search ) . '%';
		$visits   = $this->wpdb->prefix . 'jtzl_bm_trap_visits';
		$params[] = $like;
		$params[] = $like;

		return "is_blocked = 1 AND ( ip_address LIKE %s OR user_agent_hash IN ( SELECT SHA2( user_agent, 256 ) FROM {$visits} WHERE user_agent LIKE %s ) )";
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

	/**
	 * Hydrate a database row into a BotScore entity.
	 *
	 * @param array $row Database row.
	 * @return BotScore Bot score entity.
	 * @since 1.0.0
	 */
	private function hydrate( array $row ): BotScore {
		return new BotScore(
			(int) $row['id'],
			$row['ip_address'],
			$row['user_agent_hash'],
			(float) $row['score'],
			(int) $row['trap_visits_count'],
			(int) $row['max_depth_reached'],
			new DateTime( $row['first_seen_at'] ),
			new DateTime( $row['last_seen_at'] ),
			(bool) $row['is_blocked']
		);
	}
}
