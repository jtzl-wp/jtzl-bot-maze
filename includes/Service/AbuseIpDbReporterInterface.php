<?php
/**
 * AbuseIPDB request-path reporter contract.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Service;

/** Queues eligible AbuseIPDB reports without performing network requests. */
interface AbuseIpDbReporterInterface {

	/**
	 * Queue an eligible address for later reporting.
	 *
	 * @param string $ip_address  Public IP address.
	 * @param string $observed_at UTC observation time.
	 */
	public function queue_if_eligible( string $ip_address, string $observed_at ): bool;

	/**
	 * Whether an address is one AbuseIPDB would accept a report for.
	 *
	 * @param string $ip_address Candidate IP address.
	 * @return bool True when a report for this address could be delivered.
	 * @since 1.6.0
	 */
	public function is_reportable( string $ip_address ): bool;

	/**
	 * Whether reporting is enabled and configured.
	 *
	 * Callers use this to skip the work that only feeds a report, so an
	 * inactive integration costs nothing on the request path.
	 */
	public function is_active(): bool;
}
