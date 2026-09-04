<?php
/**
 * AbuseIPDB report queue repository contract.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Repository;

use JTZL\Bot_Maze\Entity\AbuseIpDbReport;

/** Durable report queue persistence boundary. */
interface AbuseIpDbReportQueueRepositoryInterface {

	/**
	 * Insert a report, or re-open the existing row once its cooldown expired.
	 *
	 * @param string $ip_address  Canonical IP address.
	 * @param string $observed_at UTC SQL observation timestamp.
	 */
	public function enqueue( string $ip_address, string $observed_at ): bool;

	/**
	 * Lease the next due report, reclaiming rows whose lease has expired.
	 *
	 * @param string $now         UTC SQL timestamp for "now".
	 * @param string $lease_until UTC SQL timestamp the lease runs to.
	 * @param string $lease_token Opaque token identifying this worker.
	 */
	public function claim_next( string $now, string $lease_until, string $lease_token ): ?AbuseIpDbReport;

	/**
	 * Settle a delivered report and start its cooldown.
	 *
	 * @param int    $id             Report row ID.
	 * @param string $lease_token    Lease token held by the caller.
	 * @param string $reported_at    UTC SQL delivery timestamp.
	 * @param string $cooldown_until UTC SQL timestamp the cooldown runs to.
	 */
	public function mark_reported( int $id, string $lease_token, string $reported_at, string $cooldown_until ): bool;

	/**
	 * Release the lease and re-arm the report after a retryable failure.
	 *
	 * @param int    $id              Report row ID.
	 * @param string $lease_token     Lease token held by the caller.
	 * @param int    $attempts        Attempt count after this failure.
	 * @param string $next_attempt_at UTC SQL timestamp of the next attempt.
	 * @param string $error           Sanitized error message.
	 */
	public function schedule_retry( int $id, string $lease_token, int $attempts, string $next_attempt_at, string $error ): bool;

	/**
	 * Re-arm the report without spending an attempt, for quota deferrals.
	 *
	 * @param int    $id              Report row ID.
	 * @param string $lease_token     Lease token held by the caller.
	 * @param string $next_attempt_at UTC SQL timestamp of the next attempt.
	 * @param string $error           Sanitized error message.
	 */
	public function defer( int $id, string $lease_token, string $next_attempt_at, string $error ): bool;

	/**
	 * Settle a report that will not be delivered and start its cooldown.
	 *
	 * @param int    $id             Report row ID.
	 * @param string $lease_token    Lease token held by the caller.
	 * @param int    $attempts       Final attempt count.
	 * @param string $cooldown_until UTC SQL timestamp the cooldown runs to.
	 * @param string $error          Sanitized error message.
	 */
	public function mark_failed( int $id, string $lease_token, int $attempts, string $cooldown_until, string $error ): bool;

	/** Count reports still awaiting delivery. */
	public function count_pending(): int;

	/** Discard every queued report, returning the number removed. */
	public function clear_all(): int;

	/**
	 * Drop settled rows past their cooldown and observations past retention.
	 *
	 * @param string $now           UTC SQL timestamp for "now".
	 * @param string $expiry_cutoff UTC SQL retention cutoff.
	 */
	public function prune( string $now, string $expiry_cutoff ): int;
}
