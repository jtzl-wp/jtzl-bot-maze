<?php
/**
 * Trap Visit Entity
 *
 * @package Labyrinth
 * @since 1.0.0
 */

namespace Labyrinth\Entity;

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
		DateTime $visited_at
	) {
		$this->id            = $id;
		$this->trap_page_id  = $trap_page_id;
		$this->ip_address    = $ip_address;
		$this->user_agent    = $user_agent;
		$this->referrer      = $referrer;
		$this->session_id    = $session_id;
		$this->depth_reached = $depth_reached;
		$this->visited_at    = $visited_at;
	}
}
