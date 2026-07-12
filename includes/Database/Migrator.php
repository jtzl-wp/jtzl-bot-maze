<?php
/**
 * Database Migrator
 *
 * Applies version-gated schema and data migrations.
 *
 * @package JTZL\Bot_Maze
 * @since 1.4.0
 */

namespace JTZL\Bot_Maze\Database;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Schema and data migration runner.
 *
 * Shared by plugin activation and the version-change check so both entry
 * points apply the same upgrade: dbDelta() the current schema, then
 * backfill the denormalized user_agent / country_code columns on
 * bot_scores from trap_visits evidence. The backfill is idempotent
 * (only blank rows are touched) and safe under concurrent requests
 * (competing writers store identical values), so it may run repeatedly
 * and is never permanently skipped.
 *
 * @since 1.4.0
 */
class Migrator {

	/**
	 * Option holding the installed plugin version, which gates run().
	 *
	 * @var string
	 * @since 1.4.0
	 */
	public const VERSION_OPTION = 'jtzl_bm_plugin_version';

	/**
	 * Option flag marking an unfinished denormalized-data backfill.
	 *
	 * @var string
	 * @since 1.4.0
	 */
	public const PENDING_BACKFILL_OPTION = 'jtzl_bm_pending_backfill';

	/**
	 * WP-Cron hook that retries an incomplete migration in the background.
	 *
	 * @var string
	 * @since 1.4.0
	 */
	public const BACKFILL_RETRY_HOOK = 'jtzl_bm_backfill_retry_cron';

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
	private string $scores_table;

	/**
	 * Trap visits table name.
	 *
	 * @var string
	 * @since 1.4.0
	 */
	private string $visits_table;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database instance.
	 * @since 1.4.0
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb         = $wpdb;
		$this->scores_table = $wpdb->prefix . 'jtzl_bm_bot_scores';
		$this->visits_table = $wpdb->prefix . 'jtzl_bm_trap_visits';
	}

	/**
	 * Apply the current schema, then backfill denormalized bot_scores data.
	 *
	 * The pending-backfill flag is durably set *before* the version is
	 * recorded, so once the stored version reads as current the flag is
	 * guaranteed present to drive the retry — a crash between the two
	 * writes can never leave the version current with no pending flag,
	 * which would skip the backfill forever. A schema failure records
	 * nothing so the whole sequence retries; a backfill failure only
	 * leaves the flag pending.
	 *
	 * @return void
	 * @since 1.4.0
	 */
	public function run(): void {
		if ( ! $this->upgrade_schema() ) {
			return;
		}

		// Confirm the flag actually persisted before recording the version;
		// if its write failed, retry the whole sequence next request rather
		// than record a current version with no pending flag.
		update_option( self::PENDING_BACKFILL_OPTION, 1 );
		if ( ! get_option( self::PENDING_BACKFILL_OPTION ) ) {
			return;
		}

		update_option( self::VERSION_OPTION, JTZL_BM_VERSION );

		$this->run_pending_backfill();
	}

	/**
	 * Apply the current schema and verify dbDelta() added the new columns.
	 *
	 * @return bool True when bot_scores carries the denormalized columns.
	 * @since 1.4.0
	 */
	protected function upgrade_schema(): bool {
		Schema::create_tables();

		return $this->has_denormalized_columns();
	}

	/**
	 * Run the backfill if one is pending, resolving the flag on success.
	 *
	 * Costs a single option read when nothing is pending, so callers may
	 * invoke it on every request as the cheap retry path. The resolved
	 * flag is stored as an autoloaded 0 rather than deleted — a missing
	 * option would cost a dedicated query per request on installs without
	 * a persistent object cache (uninstall.php removes it for real).
	 *
	 * @return void
	 * @since 1.4.0
	 */
	public function run_pending_backfill(): void {
		if ( ! get_option( self::PENDING_BACKFILL_OPTION ) ) {
			return;
		}

		if ( $this->backfill() ) {
			update_option( self::PENDING_BACKFILL_OPTION, 0 );
		}
	}

	/**
	 * Backfill blank user_agent / country_code values from trap visits.
	 *
	 * Zero rows updated counts as success (nothing needed backfilling);
	 * a false query result — including columns missing out-of-band, which
	 * fails the UPDATEs themselves — is failure.
	 *
	 * @return bool True on success.
	 * @since 1.4.0
	 */
	public function backfill(): bool {
		return $this->backfill_user_agents() && $this->backfill_country_codes();
	}

	/**
	 * Return a snapshot of migration state for Site Health and support diagnostics.
	 *
	 * @return array{columns_present: bool, pending: bool, stored_version: string}
	 * @since 1.4.0
	 */
	public function status(): array {
		return array(
			'columns_present' => $this->has_denormalized_columns(),
			'pending'         => (bool) get_option( self::PENDING_BACKFILL_OPTION ),
			'stored_version'  => (string) get_option( self::VERSION_OPTION, '' ),
		);
	}

	/**
	 * Whether the migration has not fully landed and still needs work.
	 *
	 * True when the schema columns are missing (Branch B), a data backfill is
	 * still pending (Branch A), or the stored version is behind the code — any
	 * of which warrants scheduling (or keeping) the background retry cron.
	 *
	 * @return bool
	 * @since 1.4.0
	 */
	public function is_incomplete(): bool {
		$status = $this->status();

		return ! $status['columns_present']
			|| $status['pending']
			|| JTZL_BM_VERSION !== $status['stored_version'];
	}

	/**
	 * Verify dbDelta() actually added the denormalized columns.
	 *
	 * @return bool True when both columns exist on bot_scores.
	 * @since 1.4.0
	 */
	private function has_denormalized_columns(): bool {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$columns = $this->wpdb->get_col( "SHOW COLUMNS FROM {$this->scores_table}" );

		return in_array( 'user_agent', $columns, true ) && in_array( 'country_code', $columns, true );
	}

	/**
	 * Copy the readable user agent onto score rows that only have the hash.
	 *
	 * Joins on both identity components so the ip_address index narrows the
	 * visits that need SHA2 hashing; matching rows carry the identical UA by
	 * the hash invariant, so repeated matches are harmless. Rows whose trap
	 * visits were deleted out-of-band stay blank and keep the hash-fallback
	 * display.
	 *
	 * @return bool True on success.
	 * @since 1.4.0
	 */
	private function backfill_user_agents(): bool {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $this->wpdb->query(
			"UPDATE {$this->scores_table} scores
			 INNER JOIN {$this->visits_table} visits
			         ON visits.ip_address = scores.ip_address
			        AND SHA2( visits.user_agent, 256 ) = scores.user_agent_hash
			 SET scores.user_agent = visits.user_agent
			 WHERE scores.user_agent = ''"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return false !== $result;
	}

	/**
	 * Copy the most recent non-empty country onto blank score rows.
	 *
	 * Uses the latest matching visit by (visited_at, id) so the stored
	 * country reflects where the identity was last resolved. Identities
	 * with no resolved visits stay blank.
	 *
	 * @return bool True on success.
	 * @since 1.4.0
	 */
	private function backfill_country_codes(): bool {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $this->wpdb->query(
			"UPDATE {$this->scores_table} scores
			 SET scores.country_code = COALESCE(
			     (
			         SELECT visits.country_code
			         FROM {$this->visits_table} visits
			         WHERE visits.ip_address = scores.ip_address
			           AND SHA2( visits.user_agent, 256 ) = scores.user_agent_hash
			           AND visits.country_code <> ''
			         ORDER BY visits.visited_at DESC, visits.id DESC
			         LIMIT 1
			     ),
			     ''
			 )
			 WHERE scores.country_code = ''"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return false !== $result;
	}
}
