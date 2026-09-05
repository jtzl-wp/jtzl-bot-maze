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
 * How far back that may reach is the site's own data retention setting: the
 * period the administrator has declared this evidence meaningful for, and the
 * period after which the maintenance cron deletes the rows anyway.
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
	 * Constructor.
	 *
	 * @param AbuseIpDbBackfillQuery     $candidates     Eligible-address lookup.
	 * @param AbuseIpDbReporterInterface $reporter       Report queue gateway.
	 * @param int                        $retention_days How long the site keeps bot data.
	 */
	public function __construct(
		private AbuseIpDbBackfillQuery $candidates,
		private AbuseIpDbReporterInterface $reporter,
		private int $retention_days
	) {}

	/** Count addresses a run would queue. */
	public function count_eligible(): int {
		return count( $this->eligible() );
	}

	/**
	 * Queue every eligible address, returning how many reached the queue.
	 *
	 * Counted from what the queue accepted rather than from what was offered.
	 * Every reason an address might be turned away — unreportable, or already
	 * waiting — is filtered before this point, so a refusal here is a real
	 * failure and the administrator should see a smaller number rather than a
	 * reassuring one.
	 */
	public function run(): int {
		$queued = 0;

		foreach ( $this->eligible() as $ip_address => $last_seen_at ) {
			// last_seen_at is stored in the site's timezone while the queue
			// works in UTC, so an unconverted value would misdate the report
			// by the site's offset and skew its own cooldown arithmetic.
			if ( $this->reporter->queue_if_eligible( (string) $ip_address, get_gmt_from_date( $last_seen_at ) ) ) {
				++$queued;
			}
		}

		return $queued;
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

	/**
	 * Oldest observation still worth offering, in the site's timezone.
	 *
	 * The site's own data retention setting decides this. It is the period an
	 * administrator has declared this evidence meaningful for, and the same
	 * period after which the maintenance cron deletes the score rows, so the
	 * catch-up offers exactly the blocked addresses the site still holds
	 * evidence for — no more, and nothing it would have to explain away.
	 */
	private function cutoff(): string {
		return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', time() - ( $this->retention_days * DAY_IN_SECONDS ) ) );
	}
}
