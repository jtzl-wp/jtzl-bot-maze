<?php
/**
 * Aggregate AbuseIPDB reporting status.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Service;

use JTZL\Bot_Maze\Repository\AbuseIpDbReportQueueRepositoryInterface;

/** Provide a sanitized point-in-time reporting status for administrators. */
final class AbuseIpDbStatus {

	/**
	 * Constructor.
	 *
	 * @param AbuseIpDbReportQueueRepositoryInterface $queue Queue persistence.
	 */
	public function __construct( private AbuseIpDbReportQueueRepositoryInterface $queue ) {}

	/**
	 * Get the current aggregate status.
	 *
	 * @return array{state: string, pending: int, last_success_at: string, last_error: string, last_error_at: string}
	 */
	public function snapshot(): array {
		$enabled = (bool) get_option( 'jtzl_bm_abuseipdb_enabled', false );
		$api_key = (string) get_option( 'jtzl_bm_abuseipdb_api_key', '' );

		if ( ! $enabled ) {
			$state = __( 'Disabled', 'jtzl-bot-maze' );
		} elseif ( '' === $api_key ) {
			$state = __( 'Setup incomplete — API key required', 'jtzl-bot-maze' );
		} else {
			$state = __( 'Active', 'jtzl-bot-maze' );
		}

		return array(
			'state'           => $state,
			'pending'         => $this->queue->count_pending(),
			'last_success_at' => (string) get_option( AbuseIpDbReportProcessor::LAST_SUCCESS_OPTION, '' ),
			'last_error'      => (string) get_option( AbuseIpDbReportProcessor::LAST_ERROR_OPTION, '' ),
			'last_error_at'   => (string) get_option( AbuseIpDbReportProcessor::LAST_ERROR_AT_OPTION, '' ),
		);
	}
}
