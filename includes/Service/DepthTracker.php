<?php
/**
 * Depth Tracker Service
 *
 * Tracks traversal depth for bot sessions within the trap maze.
 *
 * @package JTZL\Bot_Labyrinth
 * @since 1.0.0
 */

namespace JTZL\Bot_Labyrinth\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Labyrinth\Repository\TrapVisitRepositoryInterface;

/**
 * Depth Tracker
 *
 * Provides session-based depth tracking for trap page visitors.
 *
 * @since 1.0.0
 */
class DepthTracker {

	/**
	 * Trap visit repository.
	 *
	 * @var TrapVisitRepositoryInterface
	 * @since 1.0.0
	 */
	private TrapVisitRepositoryInterface $visit_repo;

	/**
	 * IP tracker service.
	 *
	 * @var IPTrackerInterface
	 * @since 1.0.0
	 */
	private IPTrackerInterface $ip_tracker;

	/**
	 * Constructor.
	 *
	 * @param TrapVisitRepositoryInterface $visit_repo Trap visit repository.
	 * @param IPTrackerInterface           $ip_tracker IP tracker service.
	 * @since 1.0.0
	 */
	public function __construct( TrapVisitRepositoryInterface $visit_repo, IPTrackerInterface $ip_tracker ) {
		$this->visit_repo = $visit_repo;
		$this->ip_tracker = $ip_tracker;
	}

	/**
	 * Get the current session's visit count.
	 *
	 * @return int Number of trap page visits in this session.
	 * @since 1.0.0
	 */
	public function get_session_visit_count(): int {
		$session_id = $this->ip_tracker->generate_session_id();

		if ( empty( $session_id ) ) {
			return 0;
		}

		return $this->visit_repo->count_by_session( $session_id );
	}

	/**
	 * Get the maximum depth reached in the current session.
	 *
	 * @return int Maximum depth level reached.
	 * @since 1.0.0
	 */
	public function get_max_depth_reached(): int {
		$session_id = $this->ip_tracker->generate_session_id();

		if ( empty( $session_id ) ) {
			return 0;
		}

		return $this->visit_repo->get_max_depth_by_session( $session_id );
	}
}
