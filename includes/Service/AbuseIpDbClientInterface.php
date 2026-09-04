<?php
/**
 * AbuseIPDB HTTP client contract.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Service;

/** Network boundary for AbuseIPDB report submission. */
interface AbuseIpDbClientInterface {

	/**
	 * Submit an IP report.
	 *
	 * @param string $api_key     AbuseIPDB API key.
	 * @param string $ip_address  Reported IP address.
	 * @param string $observed_at UTC SQL observation timestamp.
	 */
	public function report( string $api_key, string $ip_address, string $observed_at ): AbuseIpDbClientResult;
}
