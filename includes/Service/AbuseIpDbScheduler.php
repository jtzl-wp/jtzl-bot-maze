<?php
/**
 * AbuseIPDB report cron coordinator.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Service;

use JTZL\Bot_Maze\Repository\AbuseIpDbReportQueueRepositoryInterface;

/** Coordinate report scheduling with configuration changes. */
final class AbuseIpDbScheduler {
	public const CRON_HOOK = 'jtzl_bm_abuseipdb_report_cron';
	public const INTERVAL  = 'jtzl_bm_five_minutes';

	/**
	 * Seconds between report runs.
	 *
	 * Public for the same reason as AbuseIpDbReportProcessor::MAX_BATCH: the
	 * About page quotes the delivery rate, and the two halves of that rate
	 * must come from the schedule rather than from prose that can drift.
	 *
	 * @var int
	 */
	public const INTERVAL_SECONDS = 300;

	/**
	 * Constructor.
	 *
	 * @param AbuseIpDbReportQueueRepositoryInterface $queue Report queue.
	 */
	public function __construct( private AbuseIpDbReportQueueRepositoryInterface $queue ) {}

	/**
	 * Add the five-minute cron interval.
	 *
	 * @param array $schedules Existing cron schedules.
	 */
	public function add_schedule( array $schedules ): array {
		$schedules[ self::INTERVAL ] = array(
			'interval' => self::INTERVAL_SECONDS,
			'display'  => __( 'Every five minutes', 'jtzl-bot-maze' ),
		);
		return $schedules;
	}

	/** Reconcile the cron event with current configuration. */
	public function sync(): void {
		$enabled = (bool) get_option( 'jtzl_bm_abuseipdb_enabled', false );
		if ( ! $enabled || '' === (string) get_option( 'jtzl_bm_abuseipdb_api_key', '' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, self::INTERVAL, self::CRON_HOOK );
		}
	}

	/**
	 * React to reporting enablement changes.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 */
	public function handle_enabled_change( mixed $old_value, mixed $new_value ): void {
		unset( $old_value );
		if ( ! (bool) $new_value ) {
			$this->cancel();
			return;
		}
		$this->sync();
	}

	/**
	 * React to API key changes.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 */
	public function handle_api_key_change( mixed $old_value, mixed $new_value ): void {
		unset( $old_value );
		if ( '' === (string) $new_value ) {
			$this->cancel();
			return;
		}
		$this->sync();
	}

	/** Unschedule reporting and discard pending reports. */
	public function cancel(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		$this->queue->clear_all();
		delete_option( AbuseIpDbReportProcessor::LAST_SUCCESS_OPTION );
		delete_option( AbuseIpDbReportProcessor::LAST_ERROR_OPTION );
		delete_option( AbuseIpDbReportProcessor::LAST_ERROR_AT_OPTION );
		delete_option( AbuseIpDbReportProcessor::PAUSE_UNTIL_OPTION );
	}
}
