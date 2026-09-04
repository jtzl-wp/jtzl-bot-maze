<?php
/**
 * AbuseIPDB Backfill Query
 *
 * @package JTZL\Bot_Maze
 * @since 1.6.0
 */

namespace JTZL\Bot_Maze\Repository;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Finds blocked addresses that predate the AbuseIPDB reporting opt-in.
 *
 * Owns the eligibility predicate — blocked, never reported, not already
 * waiting in the report queue, and seen recently enough that the queue would
 * still deliver the observation.
 *
 * The queue exclusion is what lets the catch-up control disappear the moment
 * it is pressed. A delivered report is hidden by abuseipdb_reported_at, but
 * that column is only written when AbuseIPDB accepts the report, hours after
 * the press; without this clause the control would keep offering addresses it
 * had already queued.
 *
 * The last_seen_at range leads the WHERE clause so idx_last_seen_at drives the
 * scan: the cutoff selects a narrow recent slice, while is_blocked and a null
 * abuseipdb_reported_at each match a large share of the table and would
 * degrade into a full scan if either drove it instead.
 *
 * @since 1.6.0
 */
class AbuseIpDbBackfillQuery {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 * @since 1.6.0
	 */
	private \wpdb $wpdb;

	/**
	 * Bot scores table name.
	 *
	 * @var string
	 * @since 1.6.0
	 */
	private string $table_name;

	/**
	 * Report queue table name.
	 *
	 * @var string
	 * @since 1.6.0
	 */
	private string $reports_table;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database object.
	 * @since 1.6.0
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb          = $wpdb;
		$this->table_name    = $wpdb->prefix . 'jtzl_bm_bot_scores';
		$this->reports_table = $wpdb->prefix . 'jtzl_bm_abuseipdb_reports';
	}

	/**
	 * List eligible addresses with their most recent observation.
	 *
	 * An address that reached the threshold under several user agents owns one
	 * score row per agent. They collapse to a single entry here because the
	 * report queue is keyed on the address alone, and reporting one address
	 * twice would spend AbuseIPDB quota to say the same thing.
	 *
	 * @param string $seen_since Site-local SQL cutoff for last_seen_at.
	 * @param int    $limit      Maximum addresses to return.
	 * @return array<string, string> Address => most recent site-local last_seen_at.
	 * @since 1.6.0
	 */
	public function rows( string $seen_since, int $limit ): array {
		$sql = $this->wpdb->prepare(
			"SELECT s.ip_address, MAX(s.last_seen_at) AS last_seen_at FROM {$this->table_name} s WHERE s.last_seen_at >= %s AND s.is_blocked = 1 AND s.abuseipdb_reported_at IS NULL AND NOT EXISTS (SELECT 1 FROM {$this->reports_table} r WHERE r.ip_address = s.ip_address) GROUP BY s.ip_address ORDER BY MAX(s.last_seen_at) DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$seen_since,
			$limit
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$addresses = array();
		foreach ( (array) $rows as $row ) {
			$addresses[ (string) $row['ip_address'] ] = (string) $row['last_seen_at'];
		}

		return $addresses;
	}
}
