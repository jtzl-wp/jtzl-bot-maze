<?php
/**
 * AbuseIPDB request-path reporter.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Service;

use JTZL\Bot_Maze\Repository\AbuseIpDbReportQueueRepositoryInterface;

/** Validates report eligibility and delegates durable persistence to the queue. */
final class AbuseIpDbReporter implements AbuseIpDbReporterInterface {

	/**
	 * Constructor.
	 *
	 * @param AbuseIpDbReportQueueRepositoryInterface $queue   Durable queue.
	 * @param bool                                    $enabled Reporting enabled flag.
	 * @param string                                  $api_key Administrator API key.
	 */
	public function __construct(
		private AbuseIpDbReportQueueRepositoryInterface $queue,
		private bool $enabled,
		private string $api_key
	) {
	}

	/**
	 * Queue an eligible address.
	 *
	 * @param string $ip_address  Candidate IP address.
	 * @param string $observed_at UTC observation time.
	 */
	public function queue_if_eligible( string $ip_address, string $observed_at ): bool {
		if ( ! $this->is_active() ) {
			return false;
		}

		$canonical_ip = $this->canonicalize( $ip_address );
		if ( null === $canonical_ip ) {
			return false;
		}

		return $this->queue->enqueue( $canonical_ip, $observed_at );
	}

	/**
	 * Whether an address is one AbuseIPDB would accept a report for.
	 *
	 * Callers that offer a report before making it — the catch-up control
	 * counting what it will send — ask here rather than reimplementing the
	 * rules, so the number offered is the number that can actually go.
	 *
	 * @param string $ip_address Candidate IP address.
	 */
	public function is_reportable( string $ip_address ): bool {
		return null !== $this->canonicalize( $ip_address );
	}

	/**
	 * Reduce an address to the form reports are keyed on, or null when it is
	 * not a globally routable unicast address.
	 *
	 * @param string $ip_address Candidate IP address.
	 */
	private function canonicalize( string $ip_address ): ?string {
		$valid_ip = filter_var( $ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE );
		$packed   = inet_pton( $ip_address );
		if ( false === $valid_ip || false === $packed ) {
			return null;
		}

		$is_ipv4_multicast = 4 === strlen( $packed ) && 0xe0 === ( ord( $packed[0] ) & 0xf0 );
		$is_ipv6_multicast = 16 === strlen( $packed ) && 0xff === ord( $packed[0] );
		if ( $is_ipv4_multicast || $is_ipv6_multicast ) {
			return null;
		}

		// A successful inet_pton() always produces bytes accepted by inet_ntop().
		return (string) inet_ntop( $packed );
	}

	/** Whether reporting is enabled and configured. */
	public function is_active(): bool {
		return $this->enabled && '' !== $this->api_key;
	}
}
