<?php
/**
 * Durable AbuseIPDB report queue repository.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Repository;

use JTZL\Bot_Maze\Entity\AbuseIpDbReport;

/** Implements atomic queue persistence and leased state transitions. */
class AbuseIpDbReportQueueRepository implements AbuseIpDbReportQueueRepositoryInterface {

	/**
	 * Reset predicate shared by every ON DUPLICATE KEY UPDATE clause.
	 *
	 * A settled row is re-opened only once its cooldown has elapsed by the time
	 * of the new observation.
	 *
	 * @var string
	 */
	private const RESET_WHEN = "status IN ('reported','failed') AND cooldown_until <= VALUES(observed_at)";

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
		$this->table_name = $wpdb->prefix . 'jtzl_bm_abuseipdb_reports';
	}

	/**
	 * Insert a report, or re-open the existing row once its cooldown expired.
	 *
	 * MySQL evaluates SET clauses left to right, so later columns see the values
	 * assigned by earlier ones. Every clause below therefore reads the original
	 * row except cooldown_until, which is assigned last and deliberately keys off
	 * the status this statement has already written.
	 *
	 * observed_at carries the age of the evidence, while created_at,
	 * updated_at and next_attempt_at all record queue activity and are stamped
	 * now. They coincide for a report queued as the abuse happens, and must not
	 * for a catch-up of older evidence: stamping created_at with the
	 * observation would make the batch arrive already past the processor's
	 * stuck-work cutoff, and the next tick would delete it unsent.
	 *
	 * A reset makes the row new work, so created_at restarts with it. Delivery
	 * normally prunes a settled row within a day of its cooldown ending, long
	 * before it could look stuck — but delivery is exactly what has stopped on
	 * a site whose cron has stalled, and a row re-opened during that stall must
	 * not be discarded by the tick that resumes it.
	 *
	 * @param string $ip_address  Canonical IP address.
	 * @param string $observed_at UTC SQL observation timestamp.
	 */
	public function enqueue( string $ip_address, string $observed_at ): bool {
		$reset = self::RESET_WHEN;
		$now   = gmdate( 'Y-m-d H:i:s' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare(
			"INSERT INTO {$this->table_name} (ip_address, status, observed_at, attempts, next_attempt_at, lease_token, last_error, created_at, updated_at)
			 VALUES (%s, 'queued', %s, 0, %s, '', '', %s, %s)
			 ON DUPLICATE KEY UPDATE
			 id = LAST_INSERT_ID(id),
			 observed_at = IF({$reset}, VALUES(observed_at), observed_at),
			 attempts = IF({$reset}, 0, attempts),
			 next_attempt_at = IF({$reset}, VALUES(next_attempt_at), next_attempt_at),
			 lease_token = IF({$reset}, '', lease_token),
			 lease_until = IF({$reset}, NULL, lease_until),
			 reported_at = IF({$reset}, NULL, reported_at),
			 last_error = IF({$reset}, '', last_error),
			 updated_at = IF({$reset}, VALUES(updated_at), updated_at),
			 created_at = IF({$reset}, VALUES(created_at), created_at),
			 status = IF({$reset}, 'queued', status),
			 cooldown_until = IF(status = 'queued' AND cooldown_until <= VALUES(observed_at), NULL, cooldown_until)",
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ip_address,
			$observed_at,
			$now,
			$now,
			$now
		);

		$result = $this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result && $result > 0;
	}

	/**
	 * Lease the next due report, reclaiming rows whose lease has expired.
	 *
	 * @param string $now         UTC SQL timestamp for "now".
	 * @param string $lease_until UTC SQL timestamp the lease runs to.
	 * @param string $lease_token Opaque token identifying this worker.
	 */
	public function claim_next( string $now, string $lease_until, string $lease_token ): ?AbuseIpDbReport {
		$select = $this->wpdb->prepare(
			"SELECT id FROM {$this->table_name} WHERE ((status IN ('queued','retry') AND next_attempt_at <= %s) OR (status = 'processing' AND lease_until <= %s)) ORDER BY next_attempt_at ASC, id ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$now,
			$now
		);

		$id = $this->wpdb->get_var( $select ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( null === $id ) {
			return null;
		}

		$claim = $this->wpdb->prepare(
			"UPDATE {$this->table_name} SET status = 'processing', lease_token = %s, lease_until = %s, updated_at = %s WHERE id = %d AND ((status IN ('queued','retry') AND next_attempt_at <= %s) OR (status = 'processing' AND lease_until <= %s))", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$lease_token,
			$lease_until,
			$now,
			$id,
			$now,
			$now
		);

		if ( 1 !== $this->wpdb->query( $claim ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return null;
		}

		$select_row = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d AND lease_token = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$id,
			$lease_token
		);

		$row = $this->wpdb->get_row( $select_row, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Settle a delivered report and start its cooldown.
	 *
	 * @param int    $id             Report row ID.
	 * @param string $lease_token    Lease token held by the caller.
	 * @param string $reported_at    UTC SQL delivery timestamp.
	 * @param string $cooldown_until UTC SQL timestamp the cooldown runs to.
	 */
	public function mark_reported( int $id, string $lease_token, string $reported_at, string $cooldown_until ): bool {
		return $this->transition( $id, $lease_token, "status = 'reported', reported_at = %s, cooldown_until = %s, last_error = ''", array( $reported_at, $cooldown_until ) );
	}

	/**
	 * Release the lease and re-arm the report after a retryable failure.
	 *
	 * @param int    $id              Report row ID.
	 * @param string $lease_token     Lease token held by the caller.
	 * @param int    $attempts        Attempt count after this failure.
	 * @param string $next_attempt_at UTC SQL timestamp of the next attempt.
	 * @param string $error           Sanitized error message.
	 */
	public function schedule_retry( int $id, string $lease_token, int $attempts, string $next_attempt_at, string $error ): bool {
		return $this->transition( $id, $lease_token, "status = 'retry', attempts = %d, next_attempt_at = %s, last_error = %s", array( $attempts, $next_attempt_at, $error ) );
	}

	/**
	 * Re-arm the report without spending an attempt, for quota deferrals.
	 *
	 * @param int    $id              Report row ID.
	 * @param string $lease_token     Lease token held by the caller.
	 * @param string $next_attempt_at UTC SQL timestamp of the next attempt.
	 * @param string $error           Sanitized error message.
	 */
	public function defer( int $id, string $lease_token, string $next_attempt_at, string $error ): bool {
		return $this->transition( $id, $lease_token, "status = 'retry', next_attempt_at = %s, last_error = %s", array( $next_attempt_at, $error ) );
	}

	/**
	 * Settle a report that will not be delivered and start its cooldown.
	 *
	 * @param int    $id             Report row ID.
	 * @param string $lease_token    Lease token held by the caller.
	 * @param int    $attempts       Final attempt count.
	 * @param string $cooldown_until UTC SQL timestamp the cooldown runs to.
	 * @param string $error          Sanitized error message.
	 */
	public function mark_failed( int $id, string $lease_token, int $attempts, string $cooldown_until, string $error ): bool {
		return $this->transition( $id, $lease_token, "status = 'failed', attempts = %d, cooldown_until = %s, last_error = %s", array( $attempts, $cooldown_until, $error ) );
	}

	/** Count reports still awaiting delivery. */
	public function count_pending(): int {
		$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE status IN ('queued','retry','processing')"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Discard every queued report, returning the number removed. */
	public function clear_all(): int {
		$query = "DELETE FROM {$this->table_name}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$result = $this->wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Drop settled rows past their cooldown and rows stuck in the queue.
	 *
	 * Staleness is measured from created_at, not observed_at: this clause
	 * exists to stop undeliverable work sitting in the queue forever, which is
	 * a fact about the row's age, not about how old the abuse it describes is.
	 * The two coincide for a report queued as the abuse happens, and diverge
	 * for the catch-up, which deliberately queues older observations.
	 *
	 * @param string $now          UTC SQL timestamp for "now".
	 * @param string $stuck_before UTC SQL cutoff for how long a row may wait.
	 */
	public function prune( string $now, string $stuck_before ): int {
		$query = $this->wpdb->prepare(
			"DELETE FROM {$this->table_name} WHERE (status IN ('reported','failed') AND cooldown_until <= %s) OR created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$now,
			$stuck_before
		);

		$result = $this->wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Apply a state change, but only while the caller still holds the lease.
	 *
	 * The updated_at column is stamped here rather than by each caller so it
	 * always records when the row changed, never the future time it points at.
	 *
	 * @param int                    $id          Report row ID.
	 * @param string                 $lease_token Lease token held by the caller.
	 * @param string                 $set         SET fragment contributing its own placeholders.
	 * @param array<int, int|string> $values      Values for the fragment's placeholders.
	 */
	private function transition( int $id, string $lease_token, string $set, array $values ): bool {
		$values[] = gmdate( 'Y-m-d H:i:s' );
		$values[] = $id;
		$values[] = $lease_token;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The fixed SET fragment contributes its own placeholders.
		$sql = $this->wpdb->prepare( "UPDATE {$this->table_name} SET {$set}, updated_at = %s, lease_token = '', lease_until = NULL WHERE id = %d AND status = 'processing' AND lease_token = %s", $values );
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return 1 === $this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Build a report entity from a database row.
	 *
	 * @param array<string, mixed> $row Database row.
	 */
	private function hydrate( array $row ): AbuseIpDbReport {
		return new AbuseIpDbReport( (int) $row['id'], $row['ip_address'], $row['status'], $row['observed_at'], (int) $row['attempts'], $row['next_attempt_at'], $row['lease_token'], $row['lease_until'], $row['reported_at'], $row['cooldown_until'], $row['last_error'], $row['created_at'], $row['updated_at'] );
	}
}
