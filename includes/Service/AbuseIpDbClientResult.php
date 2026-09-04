<?php
/**
 * Typed AbuseIPDB client result.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Service;

/**
 * Describe an AbuseIPDB report outcome for the queue processor.
 */
final class AbuseIpDbClientResult {

	public const REPORTED = 'reported';
	public const RETRY    = 'retry';
	public const DEFERRED = 'deferred';
	public const FAILED   = 'failed';

	/**
	 * Create a client result.
	 *
	 * @param string   $status     Processor-facing status.
	 * @param string   $error      Sanitized local error.
	 * @param int|null $retry_at   Retry epoch, when applicable.
	 * @param bool     $stop_batch Whether processing should stop this batch.
	 */
	public function __construct(
		public readonly string $status,
		public readonly string $error = '',
		public readonly ?int $retry_at = null,
		public readonly bool $stop_batch = false
	) {}
}
