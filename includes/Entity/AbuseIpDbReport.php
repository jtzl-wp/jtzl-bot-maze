<?php
/**
 * AbuseIPDB report queue entity.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Entity;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * A durable AbuseIPDB report queue row.
 */
final class AbuseIpDbReport {

	/**
	 * Constructor.
	 *
	 * @param int         $id              Row ID.
	 * @param string      $ip_address      Canonical reported IP address.
	 * @param string      $status          Queue status.
	 * @param string      $observed_at     UTC SQL observation timestamp.
	 * @param int         $attempts        Delivery attempts spent so far.
	 * @param string      $next_attempt_at UTC SQL timestamp the row becomes due.
	 * @param string      $lease_token     Token of the worker holding the row.
	 * @param string|null $lease_until     UTC SQL timestamp the lease runs to.
	 * @param string|null $reported_at     UTC SQL delivery timestamp.
	 * @param string|null $cooldown_until  UTC SQL timestamp the cooldown runs to.
	 * @param string      $last_error      Sanitized error from the last attempt.
	 * @param string      $created_at      UTC SQL creation timestamp.
	 * @param string      $updated_at      UTC SQL timestamp of the last change.
	 */
	public function __construct(
		public int $id,
		public string $ip_address,
		public string $status,
		public string $observed_at,
		public int $attempts,
		public string $next_attempt_at,
		public string $lease_token,
		public ?string $lease_until,
		public ?string $reported_at,
		public ?string $cooldown_until,
		public string $last_error,
		public string $created_at,
		public string $updated_at
	) {}
}
