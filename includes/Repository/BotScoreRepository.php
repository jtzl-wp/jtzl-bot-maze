<?php
/**
 * Bot Score Repository
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
use Labyrinth\Entity\BotScore;

/**
 * Bot Score Repository
 *
 * Handles database operations for bot score entities.
 */
class BotScoreRepository implements BotScoreRepositoryInterface {

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
		$this->table_name = $wpdb->prefix . 'lby_bot_scores';
	}

	/**
	 * Find or create a bot score record for an IP+UA pair.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash SHA256-truncated UA hash.
	 * @return BotScore|null The bot score entity.
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
	 * @param int   $id             Score record ID.
	 * @param float $points_to_add  Points to add.
	 * @param int   $depth_reached  Depth reached in this visit.
	 * @param bool  $should_block   Whether to set blocked flag.
	 * @return bool True on success.
	 */
	public function update_score( int $id, float $points_to_add, int $depth_reached, bool $should_block = false ): bool {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare(
			"UPDATE {$this->table_name} SET score = score + %f, trap_visits_count = trap_visits_count + 1, max_depth_reached = GREATEST(max_depth_reached, %d), last_seen_at = %s, is_blocked = GREATEST(is_blocked, %d) WHERE id = %d",
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$points_to_add,
			$depth_reached,
			current_time( 'mysql' ),
			$should_block ? 1 : 0,
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
	 */
	public function count_total(): int {
		$query = "SELECT COUNT(*) FROM {$this->table_name}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count blocked bots.
	 *
	 * @return int Blocked count.
	 */
	public function count_blocked(): int {
		$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE is_blocked = 1"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Hydrate a database row into a BotScore entity.
	 *
	 * @param array $row Database row.
	 * @return BotScore Bot score entity.
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
