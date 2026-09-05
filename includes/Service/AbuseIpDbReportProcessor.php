<?php
/**
 * AbuseIPDB report queue processor.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Service;

use JTZL\Bot_Maze\Entity\AbuseIpDbReport;
use JTZL\Bot_Maze\Repository\AbuseIpDbReportQueueRepositoryInterface;
use JTZL\Bot_Maze\Repository\BotScoreRepositoryInterface;

/** Process a bounded batch of queued AbuseIPDB reports. */
final class AbuseIpDbReportProcessor {

	/**
	 * Reports delivered per background run.
	 *
	 * Public because the About page states the delivery rate, and a figure
	 * quoted there has to be the figure the loop actually uses.
	 *
	 * @var int
	 */
	public const MAX_BATCH = 5;

	private const MAX_ATTEMPTS  = 6;
	private const LEASE_SECONDS = 600;

	/**
	 * How long a row may sit in the queue before it is abandoned.
	 *
	 * A report nobody could deliver in a week is not going to be delivered,
	 * and holding it only makes the queue lie about what is pending. This
	 * bounds the wait, not the age of the observation being reported.
	 *
	 * @var int
	 */
	private const STUCK_AFTER_SECONDS = 7 * DAY_IN_SECONDS;

	private const COOLDOWN_SECONDS = DAY_IN_SECONDS;
	private const RETRY_DELAYS     = array( 300, 1800, 7200, 21600, 86400 );

	public const LAST_SUCCESS_OPTION  = 'jtzl_bm_abuseipdb_last_success_at';
	public const LAST_ERROR_OPTION    = 'jtzl_bm_abuseipdb_last_error';
	public const LAST_ERROR_AT_OPTION = 'jtzl_bm_abuseipdb_last_error_at';
	public const PAUSE_UNTIL_OPTION   = 'jtzl_bm_abuseipdb_pause_until';

	/**
	 * Constructor.
	 *
	 * @param AbuseIpDbReportQueueRepositoryInterface $queue   Queue persistence.
	 * @param AbuseIpDbClientInterface                $client  AbuseIPDB client.
	 * @param BotScoreRepositoryInterface             $scores  Bot score persistence.
	 * @param string                                  $api_key AbuseIPDB API key.
	 */
	public function __construct(
		private AbuseIpDbReportQueueRepositoryInterface $queue,
		private AbuseIpDbClientInterface $client,
		private BotScoreRepositoryInterface $scores,
		private string $api_key
	) {}

	/** Process due reports. */
	public function process(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$now = time();
		$this->queue->prune( $this->format_time( $now ), $this->format_time( $now - self::STUCK_AFTER_SECONDS ) );

		// A 429 exhausts the account quota, not one row's allowance, so the
		// pause is global: without it every tick would burn one more
		// over-quota request until the whole batch had been deferred.
		if ( (int) get_option( self::PAUSE_UNTIL_OPTION, 0 ) > $now ) {
			return;
		}

		for ( $slot = 0; $slot < self::MAX_BATCH; ++$slot ) {
			$now    = time();
			$report = $this->queue->claim_next(
				$this->format_time( $now ),
				$this->format_time( $now + self::LEASE_SECONDS ),
				wp_generate_uuid4()
			);
			if ( null === $report ) {
				break;
			}

			$result = $this->client->report( $this->api_key, $report->ip_address, $report->observed_at );
			if (
					! $this->apply_result( $report, $result, $now )
					|| $result->stop_batch
					|| AbuseIpDbClientResult::RETRY === $result->status
					|| AbuseIpDbClientResult::DEFERRED === $result->status
					|| AbuseIpDbClientResult::FAILED === $result->status
				) {
				break;
			}
		}
	}

	/**
	 * Whether delivery is configured and still consented to.
	 *
	 * The consent flag is re-read here rather than injected: cron events,
	 * direct database edits and add_option() writes can all outlive the
	 * unscheduling in AbuseIpDbScheduler, and no address may leave the site
	 * after an administrator has withdrawn consent.
	 */
	private function is_active(): bool {
		return '' !== $this->api_key && (bool) get_option( 'jtzl_bm_abuseipdb_enabled', false );
	}

	/**
	 * Apply one result using the current lease token.
	 *
	 * @param AbuseIpDbReport       $report Claimed queue report.
	 * @param AbuseIpDbClientResult $result Client result.
	 * @param int                   $now    Current epoch.
	 */
	private function apply_result( AbuseIpDbReport $report, AbuseIpDbClientResult $result, int $now ): bool {
		$transitioned = false;
		switch ( $result->status ) {
			case AbuseIpDbClientResult::REPORTED:
				$transitioned = $this->queue->mark_reported( $report->id, $report->lease_token, $this->format_time( $now ), $this->format_time( $now + self::COOLDOWN_SECONDS ) );
				break;
			case AbuseIpDbClientResult::RETRY:
				$attempts = $report->attempts + 1;
				if ( self::MAX_ATTEMPTS === $attempts ) {
					$transitioned = $this->queue->mark_failed( $report->id, $report->lease_token, $attempts, $this->format_time( $now + self::COOLDOWN_SECONDS ), $result->error );
					break;
				}
				$transitioned = $this->queue->schedule_retry( $report->id, $report->lease_token, $attempts, $this->format_time( $now + self::RETRY_DELAYS[ $attempts - 1 ] ), $result->error );
				break;
			case AbuseIpDbClientResult::DEFERRED:
				if ( null === $result->retry_at ) {
					break;
				}
				update_option( self::PAUSE_UNTIL_OPTION, $result->retry_at, false );
				$transitioned = $this->queue->defer( $report->id, $report->lease_token, $this->format_time( $result->retry_at ), $result->error );
				break;
			case AbuseIpDbClientResult::FAILED:
				$transitioned = $this->queue->mark_failed( $report->id, $report->lease_token, $report->attempts, $this->format_time( $now + self::COOLDOWN_SECONDS ), $result->error );
				break;
		}

		if ( ! $transitioned ) {
			return false;
		}

		if ( AbuseIpDbClientResult::REPORTED === $result->status ) {
			// The queue row is pruned 24 hours from now, so the score row carries
			// the marker that this address was reported. It is bookkeeping, not a
			// delivery guarantee: the report has already been accepted, and
			// AbuseIPDB keeps its own per-account record of it. A failed write is
			// therefore deliberately not retried — re-delivering to prove a local
			// column got written would spend third-party quota on our own
			// records. The realistic cause is a missing column, which
			// SchemaVerifier already reports to Site Health.
			$this->scores->mark_abuseipdb_reported( $report->ip_address );
			update_option( self::LAST_SUCCESS_OPTION, $this->format_time( $now ), false );
			delete_option( self::LAST_ERROR_OPTION );
			delete_option( self::LAST_ERROR_AT_OPTION );
			return true;
		}

		update_option( self::LAST_ERROR_OPTION, $result->error, false );
		update_option( self::LAST_ERROR_AT_OPTION, $this->format_time( $now ), false );
		return true;
	}

	/**
	 * Format an epoch as a UTC database timestamp.
	 *
	 * @param int $timestamp Epoch to format.
	 */
	private function format_time( int $timestamp ): string {
		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
