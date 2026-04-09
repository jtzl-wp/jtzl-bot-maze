<?php
/**
 * Bot Score Entity
 *
 * @package JTZL\Bot_Be_Gone
 * @since 1.0.0
 */

namespace JTZL\Bot_Be_Gone\Entity;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use DateTime;

/**
 * Bot Score Entity
 *
 * Represents accumulated risk score per IP+UA identity.
 *
 * @since 1.0.0
 */
class BotScore {

	/**
	 * Score ID.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public int $id;

	/**
	 * Visitor IP address.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $ip_address;

	/**
	 * SHA256-truncated user agent hash.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $user_agent_hash;

	/**
	 * Accumulated suspicion score.
	 *
	 * @var float
	 * @since 1.0.0
	 */
	public float $score;

	/**
	 * Total trap page visits.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public int $trap_visits_count;

	/**
	 * Deepest traversal depth.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public int $max_depth_reached;

	/**
	 * First seen timestamp.
	 *
	 * @var DateTime
	 * @since 1.0.0
	 */
	public DateTime $first_seen_at;

	/**
	 * Last seen timestamp.
	 *
	 * @var DateTime
	 * @since 1.0.0
	 */
	public DateTime $last_seen_at;

	/**
	 * Whether this bot is blocked.
	 *
	 * @var bool
	 * @since 1.0.0
	 */
	public bool $is_blocked;

	/**
	 * Constructor.
	 *
	 * @param int      $id                Score ID.
	 * @param string   $ip_address        Visitor IP.
	 * @param string   $user_agent_hash   UA hash.
	 * @param float    $score             Accumulated score.
	 * @param int      $trap_visits_count Total visits.
	 * @param int      $max_depth_reached Max depth.
	 * @param DateTime $first_seen_at     First seen.
	 * @param DateTime $last_seen_at      Last seen.
	 * @param bool     $is_blocked        Blocked flag.
	 * @since 1.0.0
	 */
	public function __construct(
		int $id,
		string $ip_address,
		string $user_agent_hash,
		float $score,
		int $trap_visits_count,
		int $max_depth_reached,
		DateTime $first_seen_at,
		DateTime $last_seen_at,
		bool $is_blocked
	) {
		$this->id                = $id;
		$this->ip_address        = $ip_address;
		$this->user_agent_hash   = $user_agent_hash;
		$this->score             = $score;
		$this->trap_visits_count = $trap_visits_count;
		$this->max_depth_reached = $max_depth_reached;
		$this->first_seen_at     = $first_seen_at;
		$this->last_seen_at      = $last_seen_at;
		$this->is_blocked        = $is_blocked;
	}
}
