<?php
/**
 * Analytics Collector Service
 *
 * Aggregates data for the admin analytics dashboard.
 *
 * @package Labyrinth
 */

namespace Labyrinth\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use Labyrinth\Repository\TrapPageRepositoryInterface;
use Labyrinth\Repository\TrapVisitRepositoryInterface;
use Labyrinth\Repository\BotScoreRepositoryInterface;
use Labyrinth\Repository\RequestLogRepositoryInterface;

/**
 * Analytics Collector
 *
 * Provides summary stats, chart data, top bots, and recent activity
 * for the analytics dashboard.
 */
class AnalyticsCollector {

	/**
	 * Trap page repository.
	 *
	 * @var TrapPageRepositoryInterface
	 */
	private TrapPageRepositoryInterface $trap_page_repo;

	/**
	 * Trap visit repository.
	 *
	 * @var TrapVisitRepositoryInterface
	 */
	private TrapVisitRepositoryInterface $visit_repo;

	/**
	 * Bot score repository.
	 *
	 * @var BotScoreRepositoryInterface
	 */
	private BotScoreRepositoryInterface $score_repo;

	/**
	 * Request log repository.
	 *
	 * @var RequestLogRepositoryInterface
	 */
	private RequestLogRepositoryInterface $log_repo;

	/**
	 * Constructor.
	 *
	 * @param TrapPageRepositoryInterface   $trap_page_repo Trap page repository.
	 * @param TrapVisitRepositoryInterface  $visit_repo     Trap visit repository.
	 * @param BotScoreRepositoryInterface   $score_repo     Bot score repository.
	 * @param RequestLogRepositoryInterface $log_repo       Request log repository.
	 */
	public function __construct(
		TrapPageRepositoryInterface $trap_page_repo,
		TrapVisitRepositoryInterface $visit_repo,
		BotScoreRepositoryInterface $score_repo,
		RequestLogRepositoryInterface $log_repo
	) {
		$this->trap_page_repo = $trap_page_repo;
		$this->visit_repo     = $visit_repo;
		$this->score_repo     = $score_repo;
		$this->log_repo       = $log_repo;
	}

	/**
	 * Get summary statistics for the dashboard header.
	 *
	 * @return array Summary stats.
	 */
	public function get_summary_stats(): array {
		return array(
			'total_trap_pages'   => $this->trap_page_repo->count_active(),
			'total_visits'       => $this->visit_repo->count_total(),
			'visits_last_7_days' => $this->visit_repo->count_recent_days( 7 ),
			'total_bots'         => $this->score_repo->count_total(),
			'blocked_bots'       => $this->score_repo->count_blocked(),
			'total_log_entries'  => $this->log_repo->count_total(),
		);
	}

	/**
	 * Get chart data for the visits-over-time chart.
	 *
	 * @param int $days Number of days to look back.
	 * @return array Array with 'labels' (dates) and 'values' (counts).
	 */
	public function get_chart_data( int $days = 30 ): array {
		$raw_counts = $this->visit_repo->get_daily_counts( $days );

		// Fill in missing dates with zero counts.
		$labels = array();
		$values = array();

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date     = wp_date( 'Y-m-d', strtotime( "-{$i} days" ) );
			$labels[] = $date;
			$values[] = $raw_counts[ $date ] ?? 0;
		}

		return array(
			'labels' => $labels,
			'values' => $values,
		);
	}

	/**
	 * Get top bots by score.
	 *
	 * @param int $limit Number of results.
	 * @return array Array of bot data arrays.
	 */
	public function get_top_bots( int $limit = 10 ): array {
		$bots = $this->score_repo->get_top_by_score( $limit );

		return array_map(
			function ( $bot ) {
				return array(
					'id'                => $bot->id,
					'ip_address'        => $bot->ip_address,
					'user_agent_hash'   => substr( $bot->user_agent_hash, 0, 12 ) . '...',
					'score'             => $bot->score,
					'trap_visits_count' => $bot->trap_visits_count,
					'max_depth_reached' => $bot->max_depth_reached,
					'first_seen_at'     => $bot->first_seen_at->format( 'Y-m-d H:i:s' ),
					'last_seen_at'      => $bot->last_seen_at->format( 'Y-m-d H:i:s' ),
					'is_blocked'        => $bot->is_blocked,
				);
			},
			$bots
		);
	}

	/**
	 * Get recent activity (trap visits).
	 *
	 * @param int $limit Number of entries.
	 * @return array Array of visit data arrays.
	 */
	public function get_recent_activity( int $limit = 20 ): array {
		$visits = $this->visit_repo->get_recent( $limit );

		return array_map(
			function ( $visit ) {
				return array(
					'id'            => $visit->id,
					'trap_page_id'  => $visit->trap_page_id,
					'ip_address'    => $visit->ip_address,
					'user_agent'    => mb_substr( $visit->user_agent, 0, 80 ) . ( mb_strlen( $visit->user_agent ) > 80 ? '...' : '' ),
					'depth_reached' => $visit->depth_reached,
					'visited_at'    => $visit->visited_at->format( 'Y-m-d H:i:s' ),
				);
			},
			$visits
		);
	}
}
