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
	 * Transient that throttles request-path backfill attempts.
	 *
	 * @var string
	 * @since 1.4.0
	 */
	public const BACKFILL_LOCK_TRANSIENT = 'jtzl_bm_backfill_lock';

	/**
	 * Seconds a request-path backfill attempt holds the throttle.
	 *
	 * @var int
	 * @since 1.4.0
	 */
	private const BACKFILL_LOCK_TTL = 300;

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
	 * Schema verification helper.
	 *
	 * @var SchemaVerifier
	 */
	private SchemaVerifier $verifier;

	/**
	 * Constructor.
	 *
	 * @param \wpdb               $wpdb     WordPress database instance.
	 * @param SchemaVerifier|null $verifier Schema verification helper.
	 * @since 1.4.0
	 */
	public function __construct( \wpdb $wpdb, ?SchemaVerifier $verifier = null ) {
		$this->wpdb         = $wpdb;
		$this->scores_table = $wpdb->prefix . 'jtzl_bm_bot_scores';
		$this->visits_table = $wpdb->prefix . 'jtzl_bm_trap_visits';
		$this->verifier     = $verifier ?? new SchemaVerifier( $wpdb );
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

		return $this->verifier->has_denormalized_columns() && $this->verifier->has_report_queue_table() && $this->verifier->has_reported_marker_column();
	}

	/**
	 * Run the backfill if one is pending, resolving the flag on success.
	 *
	 * Costs a single option read when nothing is pending. This is the
	 * unthrottled entry point, for callers that are already serialized or
	 * explicitly user-initiated — the retry cron and the Site Health retry
	 * button. The request path must use run_pending_backfill_throttled()
	 * instead. The resolved flag is stored as an autoloaded 0 rather than
	 * deleted — a missing option would cost a dedicated query per request
	 * on installs without a persistent object cache (uninstall.php removes
	 * it for real).
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
	 * Request-path retry: run a pending backfill at most once per throttle window.
	 *
	 * The version is recorded before the backfill runs (see run()), so the
	 * pending window is open to every incoming request — without this guard
	 * each one would launch its own full backfill concurrently, with no lock
	 * between them. And if the backfill is slow enough to be cut short by a
	 * request timeout, the flag clearing it never runs, so the window never
	 * closes and every subsequent request pays the full cost again.
	 *
	 * The transient is deliberately left to expire rather than released after
	 * a run. On success the pending flag already stops further work; on
	 * failure — including a request killed mid-query — the remaining TTL is
	 * what caps the retry rate. It is a throttle, not a mutex: requests
	 * arriving in the same instant can still overlap, which is why the queries
	 * themselves stay idempotent and only ever touch blank rows.
	 *
	 * @return void
	 * @since 1.4.0
	 */
	public function run_pending_backfill_throttled(): void {
		if ( ! get_option( self::PENDING_BACKFILL_OPTION ) ) {
			return;
		}

		if ( get_transient( self::BACKFILL_LOCK_TRANSIENT ) ) {
			return;
		}

		set_transient( self::BACKFILL_LOCK_TRANSIENT, 1, self::BACKFILL_LOCK_TTL );

		$this->run_pending_backfill();
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
	 * @return array{columns_present: bool, report_queue_present: bool, reported_marker_present: bool, pending: bool, stored_version: string}
	 * @since 1.4.0
	 */
	public function status(): array {
		return array(
			'columns_present'         => $this->verifier->has_denormalized_columns(),
			'report_queue_present'    => $this->verifier->has_report_queue_table(),
			'reported_marker_present' => $this->verifier->has_reported_marker_column(),
			'pending'                 => (bool) get_option( self::PENDING_BACKFILL_OPTION ),
			'stored_version'          => (string) get_option( self::VERSION_OPTION, '' ),
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
			|| ! $status['report_queue_present']
			|| ! $status['reported_marker_present']
			|| $status['pending']
			|| JTZL_BM_VERSION !== $status['stored_version'];
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
	 * Copy the most recently recorded non-empty country onto blank score rows.
	 *
	 * Resolves the winning visit once per identity in a grouped derived table,
	 * then reads its country through a primary-key join. The obvious shape —
	 * a correlated "ORDER BY visited_at DESC, id DESC LIMIT 1" subquery — is
	 * not used: whenever ip_address cardinality is low (a bot farm cycling
	 * many user agents through a handful of IPs, which is exactly the traffic
	 * this plugin collects) the optimizer abandons the ip_address index for a
	 * backward scan of idx_visited_at, turning the backfill into a full index
	 * scan per score row. Measured on 200k visits / 20k scores that plan costs
	 * ~11 minutes against ~0.5s for this one.
	 *
	 * "Most recent" is the highest visit id rather than the greatest
	 * (visited_at, id): id is AUTO_INCREMENT and visited_at is stamped at
	 * insert, so the two agree except when the site clock or timezone moves
	 * backwards — where the highest id is the better answer anyway, being the
	 * last country actually recorded. Identities with no resolved visits are
	 * left blank by the inner join.
	 *
	 * @return bool True on success.
	 * @since 1.4.0
	 */
	private function backfill_country_codes(): bool {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $this->wpdb->query(
			"UPDATE {$this->scores_table} scores
			 INNER JOIN (
			     SELECT visits.ip_address AS ip_address,
			            SHA2( visits.user_agent, 256 ) AS user_agent_hash,
			            MAX( visits.id ) AS visit_id
			     FROM {$this->visits_table} visits
			     WHERE visits.country_code <> ''
			     GROUP BY visits.ip_address, SHA2( visits.user_agent, 256 )
			 ) latest
			         ON latest.ip_address = scores.ip_address
			        AND latest.user_agent_hash = scores.user_agent_hash
			 INNER JOIN {$this->visits_table} resolved
			         ON resolved.id = latest.visit_id
			 SET scores.country_code = resolved.country_code
			 WHERE scores.country_code = ''"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return false !== $result;
	}
}
