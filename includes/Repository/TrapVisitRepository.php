<?php
/**
 * Trap Visit Repository
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

use DateTime;
use Labyrinth\Entity\TrapVisit;

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
		$this->table_name = $wpdb->prefix . 'lby_trap_visits';
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
			'visited_at'    => $data['visited_at'] ?? current_time( 'mysql' ),
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' );

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
		$query = "SELECT COUNT(*) FROM {$this->table_name}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
			"SELECT COUNT(*) FROM {$this->table_name} WHERE visited_at >= DATE_SUB(%s, INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
			"SELECT DATE(visited_at) as visit_date, COUNT(*) as visit_count FROM {$this->table_name} WHERE visited_at >= DATE_SUB(%s, INTERVAL %d DAY) GROUP BY DATE(visited_at) ORDER BY visit_date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
			new DateTime( $row['visited_at'] )
		);
	}
}
