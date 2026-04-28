<?php
/**
 * Trap Visit Entity
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze\Entity;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use DateTime;

/**
 * Trap Visit Entity
 *
 * Represents a single visit to a trap page.
 *
 * @since 1.0.0
 */
class TrapVisit {

	/**
	 * Visit ID.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public int $id;

	/**
	 * Trap page ID.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public int $trap_page_id;

	/**
	 * Visitor IP address.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $ip_address;

	/**
	 * Full user agent string.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $user_agent;

	/**
	 * Referrer URL.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $referrer;

	/**
	 * Session identifier (hash of IP+UA).
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $session_id;

	/**
	 * Depth reached at this trap page.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public int $depth_reached;

	/**
	 * Visit timestamp.
	 *
	 * @var DateTime
	 * @since 1.0.0
	 */
	public DateTime $visited_at;

	/**
	 * Request type (e.g. 'trap_visit', 'crawler_exempted', 'blocked_visit').
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $request_type;

	/**
	 * HTTP response code sent for this request.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public int $response_code;

	/**
	 * ISO 3166-1 alpha-2 country code resolved via GeoIP lookup.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $country_code;

	/**
	 * Constructor.
	 *
	 * @param int      $id            Visit ID.
	 * @param int      $trap_page_id  Trap page ID.
	 * @param string   $ip_address    Visitor IP.
	 * @param string   $user_agent    Full UA string.
	 * @param string   $referrer      Referrer URL.
	 * @param string   $session_id    Session ID.
	 * @param int      $depth_reached Depth reached.
	 * @param DateTime $visited_at    Visit timestamp.
	 * @param string   $request_type  Request type.
	 * @param int      $response_code HTTP response code.
	 * @param string   $country_code  Country code from GeoIP.
	 * @since 1.0.0
	 */
	public function __construct(
		int $id,
		int $trap_page_id,
		string $ip_address,
		string $user_agent,
		string $referrer,
		string $session_id,
		int $depth_reached,
		DateTime $visited_at,
		string $request_type = '',
		int $response_code = 200,
		string $country_code = ''
	) {
		$this->id            = $id;
		$this->trap_page_id  = $trap_page_id;
		$this->ip_address    = $ip_address;
		$this->user_agent    = $user_agent;
		$this->referrer      = $referrer;
		$this->session_id    = $session_id;
		$this->depth_reached = $depth_reached;
		$this->visited_at    = $visited_at;
		$this->request_type  = $request_type;
		$this->response_code = $response_code;
		$this->country_code  = $country_code;
	}
}
