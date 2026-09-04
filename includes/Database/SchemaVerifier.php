<?php
/**
 * Schema verification.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Database;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Confirms dbDelta() actually applied the schema it was asked for.
 *
 * When an ALTER is refused — most often because the database user lacks the
 * privilege — dbDelta() reports nothing useful, so every column and table the
 * plugin depends on is checked explicitly. Extracted from Migrator, which owns
 * applying and backfilling rather than verifying.
 */
class SchemaVerifier {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Bot scores table name.
	 *
	 * @var string
	 */
	private string $scores_table;

	/**
	 * AbuseIPDB report queue table name.
	 *
	 * @var string
	 */
	private string $reports_table;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database instance.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb          = $wpdb;
		$this->scores_table  = $wpdb->prefix . 'jtzl_bm_bot_scores';
		$this->reports_table = $wpdb->prefix . 'jtzl_bm_abuseipdb_reports';
	}

	/**
	 * Whether bot_scores carries the 1.4.0 denormalized columns.
	 *
	 * @return bool True when both columns exist.
	 */
	public function has_denormalized_columns(): bool {
		$columns = $this->score_columns();

		return in_array( 'user_agent', $columns, true ) && in_array( 'country_code', $columns, true );
	}

	/**
	 * Whether bot_scores carries the AbuseIPDB reporting marker.
	 *
	 * @return bool True when the column exists.
	 */
	public function has_reported_marker_column(): bool {
		return in_array( 'abuseipdb_reported_at', $this->score_columns(), true );
	}

	/**
	 * Whether the durable AbuseIPDB report queue exists.
	 *
	 * @return bool True when the table exists.
	 */
	public function has_report_queue_table(): bool {
		// The table name carries underscores, which are single-character LIKE
		// wildcards: without escaping, a similarly-named table in the same
		// database can satisfy the pattern and fail the strict comparison below.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Runtime value is passed through prepare().
		$result = $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->wpdb->esc_like( $this->reports_table ) ) );

		return $this->reports_table === $result;
	}

	/**
	 * Read the current bot_scores column names.
	 *
	 * @return array<int, string>
	 */
	private function score_columns(): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $this->wpdb->get_col( "SHOW COLUMNS FROM {$this->scores_table}" );
	}
}
