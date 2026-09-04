<?php
/**
 * Blocked Bot Query
 *
 * @package JTZL\Bot_Maze
 * @since 1.4.0
 */

namespace JTZL\Bot_Maze\Repository;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Builds and runs the blocked-bots admin list queries.
 *
 * Owns the shared WHERE clause (search over IP, the denormalized readable
 * user agent, and the hash fallback), the sort-column allowlist, and the
 * pagination query for the Blocked Bots page. BotScoreRepository delegates
 * here and hydrates the returned rows.
 *
 * @since 1.4.0
 */
class BlockedBotQuery {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 * @since 1.4.0
	 */
	private \wpdb $wpdb;

	/**
	 * Bot scores table name.
	 *
	 * @var string
	 * @since 1.4.0
	 */
	private string $table_name;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database object.
	 * @since 1.4.0
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'jtzl_bm_bot_scores';
	}

	/**
	 * Count blocked bots, optionally filtered by a search term.
	 *
	 * @param string $search Optional term matched against IP and user agent.
	 * @return int Blocked count.
	 * @since 1.4.0
	 */
	public function count( string $search = '' ): int {
		list( $where, $params ) = $this->where( $search );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}";
		if ( ! empty( $params ) ) {
			$query = $this->wpdb->prepare( $query, $params );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get one page of blocked-bot rows with sorting and search.
	 *
	 * @param int    $limit   Number of results.
	 * @param int    $offset  Offset for pagination.
	 * @param string $orderby Column to sort by (validated against an allowlist).
	 * @param string $order   Sort direction, 'ASC' or 'DESC'.
	 * @param string $search  Optional term matched against IP and user agent.
	 * @return array<int, array<string, mixed>> Raw bot-score rows.
	 * @since 1.4.0
	 */
	public function rows( int $limit, int $offset, string $orderby, string $order, string $search ): array {
		$allowed  = array( 'ip_address', 'country_code', 'user_agent', 'score', 'trap_visits_count', 'max_depth_reached', 'first_seen_at', 'last_seen_at', 'abuseipdb_reported_at' );
		$orderby  = in_array( $orderby, $allowed, true ) ? $orderby : 'last_seen_at';
		$order    = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$order_by = $this->order_expression( $orderby );

		list( $where, $params ) = $this->where( $search );

		$params[] = $limit;
		$params[] = $offset;

		// $order_by and $order come from fixed allowlists; every value is bound.
		// The id tiebreaker keeps pagination deterministic when the sort column
		// has duplicate values (common for country and user agent).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY {$order_by} {$order}, id {$order} LIMIT %d OFFSET %d",
			$params
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders

		$rows = $this->wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return empty( $rows ) ? array() : $rows;
	}

	/**
	 * Map an allowlisted sort column to its ORDER BY expression.
	 *
	 * User Agent sorts by the same value the table displays: the readable
	 * user_agent, falling back to user_agent_hash for orphan rows whose
	 * user_agent is blank — so orphans collate by their visible hash rather
	 * than as empty strings. Every other column sorts by itself.
	 *
	 * @param string $orderby Allowlisted sort column.
	 * @return string SQL expression to sort by (safe: derived from the allowlist).
	 * @since 1.4.0
	 */
	private function order_expression( string $orderby ): string {
		if ( 'user_agent' === $orderby ) {
			return "COALESCE( NULLIF( user_agent, '' ), user_agent_hash )";
		}

		return $orderby;
	}

	/**
	 * Build the WHERE body and bound parameters for blocked-bot queries.
	 *
	 * The search matches the denormalized user_agent column directly, plus
	 * user_agent_hash so the hash shown for unrecoverable orphan rows (blank
	 * user_agent) can still be click-filtered to its own row.
	 *
	 * @param string $search Search term for IP and user agent.
	 * @return array{0: string, 1: array} SQL WHERE body (without the leading "WHERE") and its parameters.
	 * @since 1.4.0
	 */
	private function where( string $search ): array {
		if ( '' === $search ) {
			return array( 'is_blocked = 1', array() );
		}

		$like = '%' . $this->wpdb->esc_like( $search ) . '%';

		return array(
			'is_blocked = 1 AND ( ip_address LIKE %s OR user_agent LIKE %s OR user_agent_hash LIKE %s )',
			array( $like, $like, $like ),
		);
	}
}
