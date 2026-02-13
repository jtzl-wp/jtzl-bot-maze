<?php
/**
 * Bot Request Entity
 *
 * @package Labyrinth
 */

namespace Labyrinth\Entity;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use DateTime;

/**
 * Bot Request Entity
 *
 * Represents a raw request log entry.
 */
class BotRequest {

	/**
	 * Request ID.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * Visitor IP address.
	 *
	 * @var string
	 */
	public string $ip_address;

	/**
	 * Requested URI.
	 *
	 * @var string
	 */
	public string $request_uri;

	/**
	 * Full user agent string.
	 *
	 * @var string
	 */
	public string $user_agent;

	/**
	 * Request type (trap_visit, etc.).
	 *
	 * @var string
	 */
	public string $request_type;

	/**
	 * HTTP response code.
	 *
	 * @var int
	 */
	public int $response_code;

	/**
	 * Created timestamp.
	 *
	 * @var DateTime
	 */
	public DateTime $created_at;

	/**
	 * Constructor.
	 *
	 * @param int      $id            Request ID.
	 * @param string   $ip_address    Visitor IP.
	 * @param string   $request_uri   Requested URL.
	 * @param string   $user_agent    Full UA string.
	 * @param string   $request_type  Request type.
	 * @param int      $response_code HTTP status.
	 * @param DateTime $created_at    Timestamp.
	 */
	public function __construct(
		int $id,
		string $ip_address,
		string $request_uri,
		string $user_agent,
		string $request_type,
		int $response_code,
		DateTime $created_at
	) {
		$this->id            = $id;
		$this->ip_address    = $ip_address;
		$this->request_uri   = $request_uri;
		$this->user_agent    = $user_agent;
		$this->request_type  = $request_type;
		$this->response_code = $response_code;
		$this->created_at    = $created_at;
	}
}
