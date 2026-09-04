<?php
/**
 * AbuseIPDB catch-up reporting for already-blocked addresses.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Service;

use JTZL\Bot_Maze\Repository\AbuseIpDbBackfillQuery;

/**
 * Queue blocked addresses that predate the reporting opt-in.
 *
 * Reporting normally fires the moment a score crosses the blocking threshold.
 * Addresses blocked before an administrator enabled reporting never see that
 * moment again: the blocking response ends their requests before any scoring
 * runs, and in 'block' mode they stop receiving trap links altogether, so they
 * cannot re-offend in a way the site can observe. Without a catch-up they stay
 * unreported forever.
 *
 * The observation reported is the address's own last_seen_at, which is a real
 * recorded event rather than the time the administrator pressed the button.
 * That accuracy is what bounds the feature: nothing older than the report
 * queue's retention window is offered, because the processor would prune it
 * unsent.
 */
final class AbuseIpDbBackfill {

	/**
	 * Addresses offered, and queued, per run.
	 *
	 * The eligible set is already bounded by the retention window; this only
	 * stops a pathological site from timing out the administrator's request.
	 * It bounds the offer as well as the run, so the control cannot name a
	 * figure a single press would not honour.
	 *
	 * @var int
	 */
	public const MAX_PER_RUN = 500;

	/**
	 * Slack between the cutoff and the processor's prune.
	 *
	 * The processor prunes on observed_at at the start of every tick and runs
	 * up to five minutes after a row is queued, so a cutoff sitting exactly on
	 * the retention boundary would offer rows deleted before they are claimed.
	 *
	 * @var int
	 */
	private const SAFETY_MARGIN = HOUR_IN_SECONDS;

	/**
	 * Constructor.
	 *
	 * @param AbuseIpDbBackfillQuery     $candidates Eligible-address lookup.
	 * @param AbuseIpDbReporterInterface $reporter   Report queue gateway.
	 */
	public function __construct(
		private AbuseIpDbBackfillQuery $candidates,
		private AbuseIpDbReporterInterface $reporter
	) {}

	/** Count addresses a run would queue. */
	public function count_eligible(): int {
		return count( $this->eligible() );
	}

	/**
	 * Queue every eligible address, returning how many were submitted.
	 *
	 * The count is exact because it comes from the same list the control
	 * offered: an address that cannot be reported was never in it, and one
	 * already waiting in the queue was excluded by the query.
	 */
	public function run(): int {
		$eligible = $this->eligible();

		foreach ( $eligible as $ip_address => $last_seen_at ) {
			// last_seen_at is stored in the site's timezone while the queue
			// works in UTC, so an unconverted value would misdate the report
			// by the site's offset and skew its own cooldown arithmetic.
			$this->reporter->queue_if_eligible( (string) $ip_address, get_gmt_from_date( $last_seen_at ) );
		}

		return count( $eligible );
	}

	/**
	 * The addresses one press would report, newest observation first.
	 *
	 * Both the offer and the run read this, so the number on the control is
	 * the number that goes. Addresses AbuseIPDB would not accept are dropped
	 * here rather than silently inside the reporter, where they would leave
	 * the control advertising work it could never complete.
	 *
	 * @return array<string, string> Address => site-local last_seen_at.
	 */
	private function eligible(): array {
		if ( ! $this->reporter->is_active() ) {
			return array();
		}

		return array_filter(
			$this->candidates->rows( $this->cutoff(), self::MAX_PER_RUN ),
			fn( string $ip_address ): bool => $this->reporter->is_reportable( $ip_address ),
			ARRAY_FILTER_USE_KEY
		);
	}

	/** Oldest site-local observation the report queue will still deliver. */
	private function cutoff(): string {
		$retention_starts = time() - AbuseIpDbReportProcessor::RETENTION_SECONDS + self::SAFETY_MARGIN;

		return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $retention_starts ) );
	}
}
