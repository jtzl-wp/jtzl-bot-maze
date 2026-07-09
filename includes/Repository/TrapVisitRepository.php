<?php
/**
 * Trap Visit Repository
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
use JTZL\Bot_Maze\Entity\TrapVisit;

/**
 * Trap Visit Repository
 *
 * Handles database operations for trap visit entities.
 *
 * @since 1.0.0
 */
class TrapVisitRepository implements TrapVisitRepositoryInterface {

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
		$this->table_name = $wpdb->prefix . 'jtzl_bm_trap_visits';
	}

	/**
	 * Record a trap page visit.
	 *
	 * @param array $data Visit data.
	 * @return int The created visit ID, or 0 on failure.
	 * @since 1.0.0
	 */
	public function create( array $data ): int {
		$insert_data = array(
			'trap_page_id'  => $data['trap_page_id'],
			'ip_address'    => $data['ip_address'],
			'user_agent'    => $data['user_agent'],
			'referrer'      => $data['referrer'] ?? '',
			'session_id'    => $data['session_id'] ?? '',
			'depth_reached' => $data['depth_reached'] ?? 1,
			'request_type'  => $data['request_type'] ?? '',
			'response_code' => $data['response_code'] ?? 200,
			'country_code'  => $data['country_code'] ?? '',
			'visited_at'    => $data['visited_at'] ?? current_time( 'mysql' ),
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' );

		$result = $this->wpdb->insert( $this->table_name, $insert_data, $format );

		if ( false === $result ) {
			return 0;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Count visits by session ID.
	 *
	 * @param string $session_id Session identifier.
	 * @return int Number of visits.
	 * @since 1.0.0
	 */
	public function count_by_session( string $session_id ): int {
		$query = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE session_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$session_id
		);

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get max depth reached by session.
	 *
	 * @param string $session_id Session identifier.
	 * @return int Maximum depth.
	 * @since 1.0.0
	 */
	public function get_max_depth_by_session( string $session_id ): int {
		$query = $this->wpdb->prepare(
			"SELECT MAX(depth_reached) FROM {$this->table_name} WHERE session_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$session_id
		);

		$result = $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return null !== $result ? (int) $result : 0;
	}

	/**
	 * Get recent visits with pagination.
	 *
	 * @param int $limit  Number of visits.
	 * @param int $offset Offset for pagination.
	 * @return TrapVisit[] Array of visit entities.
	 * @since 1.0.0
	 */
	public function get_recent( int $limit = 50, int $offset = 0 ): array {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} ORDER BY visited_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$limit,
			$offset
		);

		$rows = $this->wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'hydrate' ), $rows );
	}

	/**
	 * Count total visits.
	 *
	 * @return int Total visit count.
	 * @since 1.0.0
	 */
	public function count_total(): int {
		$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE request_type IN ('', 'trap_visit')"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Prune visits older than the specified number of days.
	 *
	 * @param int $days Number of days to retain.
	 * @return int Number of rows deleted.
	 * @since 1.0.0
	 */
	public function prune_old_entries( int $days ): int {
		$query = $this->wpdb->prepare(
			"DELETE FROM {$this->table_name} WHERE visited_at < DATE_SUB(%s, INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			current_time( 'mysql' ),
			$days
		);

		$result = $this->wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result ? (int) $result : 0;
	}

	/**
	 * Get visits within the last N days with pagination, sorting and search.
	 *
	 * @param int    $days    Number of days.
	 * @param int    $limit   Number of visits.
	 * @param int    $offset  Offset for pagination.
	 * @param string $orderby Column to sort by (validated against an allowlist).
	 * @param string $order   Sort direction, 'ASC' or 'DESC'.
	 * @param string $search  Optional term matched against IP, user agent and country code.
	 * @return TrapVisit[] Array of visit entities.
	 * @since 1.0.0
	 */
	public function get_recent_days( int $days, int $limit = 50, int $offset = 0, string $orderby = 'visited_at', string $order = 'DESC', string $search = '' ): array {
		// Allowlist the sort column; anything else falls back to visited_at.
		$allowed = array( 'visited_at', 'ip_address', 'country_code', 'user_agent', 'depth_reached', 'request_type' );
		$orderby = in_array( $orderby, $allowed, true ) ? $orderby : 'visited_at';
		$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		$params = array();
		$where  = $this->recent_where_clause( $days, $search, $params );

		$params[] = $limit;
		$params[] = $offset;

		// $orderby and $order are drawn from fixed allowlists above, so they are
		// safe to interpolate; every value is bound through $wpdb->prepare(). The
		// placeholder sniffs cannot see the placeholders inside {$where}.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			$params
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders

		$rows = $this->wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'hydrate' ), $rows );
	}

	/**
	 * Count all visits in the last N days (all request types), optionally filtered.
	 *
	 * @param int    $days   Number of days.
	 * @param string $search Optional term matched against IP, user agent and country code.
	 * @return int Visit count.
	 * @since 1.0.0
	 */
	public function count_all_recent_days( int $days, string $search = '' ): int {
		$params = array();
		$where  = $this->recent_where_clause( $days, $search, $params );

		// The bound placeholders live inside {$where}, which the placeholder sniffs
		// cannot see; every value is passed through $wpdb->prepare().
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$query = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}",
			$params
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Build the shared WHERE clause for recent-activity queries.
	 *
	 * Populates $params (by reference) with the bound values in order. The search
	 * term, when present, is matched against the IP address, user agent and
	 * country code with an escaped LIKE.
	 *
	 * @param int    $days   Day window.
	 * @param string $search Optional search term.
	 * @param array  $params Bound-parameter accumulator, populated by reference.
	 * @return string SQL WHERE body (without the leading "WHERE").
	 * @since 1.3.0
	 */
	private function recent_where_clause( int $days, string $search, array &$params ): string {
		$where  = 'visited_at >= DATE_SUB(%s, INTERVAL %d DAY)';
		$params = array( current_time( 'mysql' ), $days );

		if ( '' !== $search ) {
			$like     = '%' . $this->wpdb->esc_like( $search ) . '%';
			$where   .= ' AND ( ip_address LIKE %s OR user_agent LIKE %s OR country_code LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		return $where;
	}

	/**
	 * Count visits in the last N days.
	 *
	 * @param int $days Number of days.
	 * @return int Visit count.
	 * @since 1.0.0
	 */
	public function count_recent_days( int $days ): int {
		$query = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE request_type IN ('', 'trap_visit') AND visited_at >= DATE_SUB(%s, INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			current_time( 'mysql' ),
			$days
		);

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get daily visit counts for chart data.
	 *
	 * @param int $days Number of days to look back.
	 * @return array Array of date => count pairs.
	 * @since 1.0.0
	 */
	public function get_daily_counts( int $days = 30 ): array {
		$query = $this->wpdb->prepare(
			"SELECT DATE(visited_at) as visit_date, COUNT(*) as visit_count FROM {$this->table_name} WHERE request_type IN ('', 'trap_visit') AND visited_at >= DATE_SUB(%s, INTERVAL %d DAY) GROUP BY DATE(visited_at) ORDER BY visit_date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			current_time( 'mysql' ),
			$days
		);

		$results = $this->wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$counts = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$counts[ $row['visit_date'] ] = (int) $row['visit_count'];
			}
		}

		return $counts;
	}

	/**
	 * Get visit counts grouped by country code.
	 *
	 * @param int $days Number of days to look back (default 30).
	 * @return array<string, int> Array of country_code => count pairs.
	 * @since 1.0.0
	 */
	public function get_counts_by_country( int $days = 30 ): array {
		$query = $this->wpdb->prepare(
			// `blocked_visit` rows are included so the geo map keeps reflecting bot
			// activity after a bot crosses the block threshold and stops generating
			// new `trap_visit` rows; they only exist when track_blocked_activity is on.
			"SELECT country_code, COUNT(*) as visit_count FROM {$this->table_name} WHERE request_type IN ('', 'trap_visit', 'blocked_visit') AND country_code != '' AND visited_at >= %s GROUP BY country_code ORDER BY visit_count DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days", strtotime( current_time( 'mysql' ) ) ) )
		);

		$results = $this->wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$counts = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$counts[ $row['country_code'] ] = (int) $row['visit_count'];
			}
		}

		return $counts;
	}

	/**
	 * Hydrate a database row into a TrapVisit entity.
	 *
	 * @param array $row Database row.
	 * @return TrapVisit Visit entity.
	 * @since 1.0.0
	 */
	private function hydrate( array $row ): TrapVisit {
		return new TrapVisit(
			(int) $row['id'],
			(int) $row['trap_page_id'],
			$row['ip_address'],
			$row['user_agent'],
			$row['referrer'] ?? '',
			$row['session_id'] ?? '',
			(int) ( $row['depth_reached'] ?? 1 ),
			new DateTime( $row['visited_at'] ),
			$row['request_type'] ?? '',
			(int) ( $row['response_code'] ?? 200 ),
			$row['country_code'] ?? ''
		);
	}
}
