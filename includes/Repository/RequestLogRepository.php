<?php
/**
 * Request Log Repository
 *
 * @package Labyrinth
 */

namespace Labyrinth\Repository;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use DateTime;
use Labyrinth\Entity\BotRequest;

/**
 * Request Log Repository
 *
 * Handles database operations for raw request log entries.
 */
class RequestLogRepository implements RequestLogRepositoryInterface {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database object.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'lby_request_log';
	}

	/**
	 * Log a request.
	 *
	 * @param array $data Request data.
	 * @return int The created log ID, or 0 on failure.
	 */
	public function create( array $data ): int {
		$insert_data = array(
			'ip_address'    => $data['ip_address'],
			'request_uri'   => $data['request_uri'],
			'user_agent'    => $data['user_agent'],
			'request_type'  => $data['request_type'] ?? '',
			'response_code' => $data['response_code'] ?? 200,
			'created_at'    => $data['created_at'] ?? current_time( 'mysql' ),
		);

		$format = array( '%s', '%s', '%s', '%s', '%d', '%s' );

		$result = $this->wpdb->insert( $this->table_name, $insert_data, $format );

		if ( false === $result ) {
			return 0;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get recent log entries.
	 *
	 * @param int $limit  Number of entries.
	 * @param int $offset Offset for pagination.
	 * @return BotRequest[] Array of request entities.
	 */
	public function get_recent( int $limit = 50, int $offset = 0 ): array {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	 * Prune entries older than specified days.
	 *
	 * @param int $days Number of days to retain.
	 * @return int Number of rows deleted.
	 */
	public function prune_old_entries( int $days ): int {
		$query = $this->wpdb->prepare(
			"DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			current_time( 'mysql' ),
			$days
		);

		$result = $this->wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result ? (int) $result : 0;
	}

	/**
	 * Count total log entries.
	 *
	 * @return int Total count.
	 */
	public function count_total(): int {
		$query = "SELECT COUNT(*) FROM {$this->table_name}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Hydrate a database row into a BotRequest entity.
	 *
	 * @param array $row Database row.
	 * @return BotRequest Request entity.
	 */
	private function hydrate( array $row ): BotRequest {
		return new BotRequest(
			(int) $row['id'],
			$row['ip_address'],
			$row['request_uri'],
			$row['user_agent'],
			$row['request_type'] ?? '',
			(int) ( $row['response_code'] ?? 200 ),
			new DateTime( $row['created_at'] )
		);
	}
}
