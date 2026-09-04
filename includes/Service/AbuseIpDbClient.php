<?php
/**
 * AbuseIPDB HTTP client.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Service;

/**
 * Submit reports and classify AbuseIPDB responses.
 */
final class AbuseIpDbClient implements AbuseIpDbClientInterface {

	private const ENDPOINT = 'https://api.abuseipdb.com/api/v2/report';

	private const COMMENT = "Automated report from JTZL's Bot Maze: crawled hidden bot-trap pages and crossed the site's configured blocking threshold.";

	/**
	 * Submit an IP report.
	 *
	 * @param string $api_key     AbuseIPDB API key.
	 * @param string $ip_address  Reported IP address.
	 * @param string $observed_at UTC SQL observation timestamp.
	 */
	public function report( string $api_key, string $ip_address, string $observed_at ): AbuseIpDbClientResult {
		if ( false === inet_pton( $ip_address ) ) {
			return new AbuseIpDbClientResult( AbuseIpDbClientResult::FAILED, 'Bot Maze skipped an invalid IP address.' );
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'method'  => 'POST',
				'timeout' => 5,
				'headers' => array(
					'Accept' => 'application/json',
					'Key'    => $api_key,
				),
				'body'    => array(
					'ip'         => $ip_address,
					'categories' => '19',
					'timestamp'  => gmdate( 'c', strtotime( $observed_at . ' UTC' ) ),
					'comment'    => self::COMMENT,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new AbuseIpDbClientResult( AbuseIpDbClientResult::RETRY, 'AbuseIPDB request failed.' );
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( 200 === $status ) {
			return $this->classify_success( $response, $ip_address );
		}

		if ( 401 === $status || 403 === $status ) {
			return new AbuseIpDbClientResult( AbuseIpDbClientResult::RETRY, 'AbuseIPDB rejected the API key.' );
		}

		if ( 408 === $status || $status >= 500 ) {
			return new AbuseIpDbClientResult( AbuseIpDbClientResult::RETRY, 'AbuseIPDB is temporarily unavailable.' );
		}

		if ( 429 === $status ) {
			return $this->classify_rate_limit( $response );
		}

		return new AbuseIpDbClientResult( AbuseIpDbClientResult::FAILED, 'AbuseIPDB rejected the report.' );
	}

	/**
	 * Validate a successful API response.
	 *
	 * @param array|\WP_Error $response   WordPress HTTP response.
	 * @param string          $ip_address Submitted IP address.
	 */
	private function classify_success( $response, string $ip_address ): AbuseIpDbClientResult {
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );
		$returned_ip = is_array( $body ) && isset( $body['data']['ipAddress'] ) && is_string( $body['data']['ipAddress'] )
			? $body['data']['ipAddress']
			: '';
		$submitted   = inet_pton( $ip_address );
		$returned    = inet_pton( $returned_ip );

		if ( false === $submitted || false === $returned || $submitted !== $returned ) {
			return new AbuseIpDbClientResult( AbuseIpDbClientResult::FAILED, 'AbuseIPDB returned an invalid response.' );
		}

		$remaining = wp_remote_retrieve_header( $response, 'X-RateLimit-Remaining' );
		return new AbuseIpDbClientResult( AbuseIpDbClientResult::REPORTED, '', null, '0' === (string) $remaining );
	}

	/**
	 * Classify an HTTP 429 response.
	 *
	 * @param array|\WP_Error $response WordPress HTTP response.
	 */
	private function classify_rate_limit( $response ): AbuseIpDbClientResult {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $body ) && isset( $body['errors'] ) && is_array( $body['errors'] ) ) {
			foreach ( $body['errors'] as $error ) {
				if ( is_array( $error ) && 'ip' === ( $error['source']['parameter'] ?? null ) ) {
					return new AbuseIpDbClientResult( AbuseIpDbClientResult::REPORTED );
				}
			}
		}

		return new AbuseIpDbClientResult(
			AbuseIpDbClientResult::DEFERRED,
			'AbuseIPDB rate limit reached.',
			$this->retry_time( $response ),
			true
		);
	}

	/**
	 * Resolve a future retry time from rate-limit headers.
	 *
	 * The advertised time is clamped to 24 hours: an absurd Retry-After would
	 * otherwise park the row beyond the retention prune (silently dropping the
	 * report) or overflow past the DATETIME range MySQL accepts.
	 *
	 * @param array|\WP_Error $response WordPress HTTP response.
	 */
	private function retry_time( $response ): int {
		$now = time();

		return min( $now + DAY_IN_SECONDS, $this->advertised_retry_time( $response, $now ) );
	}

	/**
	 * Read the retry time the response asks for, before clamping.
	 *
	 * @param array|\WP_Error $response WordPress HTTP response.
	 * @param int             $now      Current epoch.
	 */
	private function advertised_retry_time( $response, int $now ): int {
		$retry_after = trim( (string) wp_remote_retrieve_header( $response, 'Retry-After' ) );

		if ( ctype_digit( $retry_after ) && (int) $retry_after > 0 ) {
			return $now + min( (int) $retry_after, DAY_IN_SECONDS );
		}

		if ( '' !== $retry_after ) {
			$retry_date = strtotime( $retry_after );
			if ( false !== $retry_date && $retry_date > $now ) {
				return $retry_date;
			}
		}

		$reset = wp_remote_retrieve_header( $response, 'X-RateLimit-Reset' );
		if ( is_numeric( $reset ) && (int) $reset > $now ) {
			return (int) $reset;
		}

		return $now + DAY_IN_SECONDS;
	}
}
