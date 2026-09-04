<?php
/**
 * Analytics Collector Service
 *
 * Aggregates data for the admin analytics dashboard.
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Maze\Data\CountryNames;
use JTZL\Bot_Maze\Entity\BotScore;
use JTZL\Bot_Maze\Repository\TrapPageRepositoryInterface;
use JTZL\Bot_Maze\Repository\TrapVisitRepositoryInterface;
use JTZL\Bot_Maze\Repository\BotScoreRepositoryInterface;
use JTZL\Bot_Maze\Service\GeoIpService;

/**
 * Analytics Collector
 *
 * Provides summary stats, chart data, top bots, and recent activity
 * for the analytics dashboard.
 *
 * @since 1.0.0
 */
class AnalyticsCollector {

	/**
	 * Trap page repository.
	 *
	 * @var TrapPageRepositoryInterface
	 * @since 1.0.0
	 */
	private TrapPageRepositoryInterface $trap_page_repo;

	/**
	 * Trap visit repository.
	 *
	 * @var TrapVisitRepositoryInterface
	 * @since 1.0.0
	 */
	private TrapVisitRepositoryInterface $visit_repo;

	/**
	 * Bot score repository.
	 *
	 * @var BotScoreRepositoryInterface
	 * @since 1.0.0
	 */
	private BotScoreRepositoryInterface $score_repo;

	/**
	 * GeoIP service for resolving visitor country.
	 *
	 * @var GeoIpService
	 * @since 1.0.0
	 */
	private GeoIpService $geo_ip_service;

	/**
	 * Constructor.
	 *
	 * @param TrapPageRepositoryInterface  $trap_page_repo  Trap page repository.
	 * @param TrapVisitRepositoryInterface $visit_repo      Trap visit repository.
	 * @param BotScoreRepositoryInterface  $score_repo      Bot score repository.
	 * @param GeoIpService                 $geo_ip_service  GeoIP service.
	 * @since 1.0.0
	 */
	public function __construct(
		TrapPageRepositoryInterface $trap_page_repo,
		TrapVisitRepositoryInterface $visit_repo,
		BotScoreRepositoryInterface $score_repo,
		GeoIpService $geo_ip_service
	) {
		$this->trap_page_repo = $trap_page_repo;
		$this->visit_repo     = $visit_repo;
		$this->score_repo     = $score_repo;
		$this->geo_ip_service = $geo_ip_service;
	}

	/**
	 * Get summary statistics for the dashboard header.
	 *
	 * @return array Summary stats.
	 * @since 1.0.0
	 */
	public function get_summary_stats(): array {
		return array(
			'total_trap_pages'       => $this->trap_page_repo->count_active(),
			'total_visits'           => $this->visit_repo->count_total(),
			'visits_last_7_days'     => $this->visit_repo->count_recent_days( 7 ),
			'all_visits_last_7_days' => $this->visit_repo->count_all_recent_days( 7 ),
			'total_bots'             => $this->score_repo->count_total(),
			'blocked_bots'           => $this->score_repo->count_blocked(),
		);
	}

	/**
	 * Get chart data for the visits-over-time chart.
	 *
	 * @param int $days Number of days to look back.
	 * @return array Array with 'labels' (dates) and 'values' (counts).
	 * @since 1.0.0
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
	 * @since 1.0.0
	 */
	public function get_top_bots( int $limit = 10 ): array {
		return array_map(
			array( $this, 'format_bot' ),
			$this->score_repo->get_top_by_score( $limit )
		);
	}

	/**
	 * Format a bot score entity for the admin tables.
	 *
	 * The user agent and country are read straight from the denormalized
	 * score row. A blank user agent (unrecoverable orphan) falls back to
	 * the hash, which the blocked-bots search also matches so the value
	 * stays click-filterable.
	 *
	 * @param BotScore $bot Bot score entity.
	 * @return array Bot data array.
	 * @since 1.4.0
	 */
	private function format_bot( BotScore $bot ): array {
		return array(
			'id'                    => $bot->id,
			'ip_address'            => $bot->ip_address,
			'user_agent'            => '' !== $bot->user_agent ? $bot->user_agent : $bot->user_agent_hash,
			'score'                 => $bot->score,
			'trap_visits_count'     => $bot->trap_visits_count,
			'max_depth_reached'     => $bot->max_depth_reached,
			'first_seen_at'         => $bot->first_seen_at->format( 'Y-m-d H:i:s' ),
			'last_seen_at'          => $bot->last_seen_at->format( 'Y-m-d H:i:s' ),
			'is_blocked'            => $bot->is_blocked,
			'country_code'          => $bot->country_code,
			'country_name'          => CountryNames::get( $bot->country_code ),
			'abuseipdb_reported_at' => $bot->abuseipdb_reported_at,
		);
	}

	/**
	 * Record a visit from a blocked bot.
	 *
	 * Updates the bot's last_seen_at and visit count, and logs the request.
	 *
	 * @param string $ip_address      Visitor IP.
	 * @param string $user_agent_hash UA hash.
	 * @param string $user_agent      Full user agent string.
	 * @param int    $response_code   HTTP response code that will be sent.
	 * @return void
	 * @since 1.0.0
	 */
	public function record_blocked_visit( string $ip_address, string $user_agent_hash, string $user_agent, int $response_code = 403 ): void {
		// Resolve the country before the touch so the score row's
		// denormalized copies refresh in the same write.
		$country_code = $this->geo_ip_service->resolve_country( $ip_address );

		$this->score_repo->touch_blocked_visit( $ip_address, $user_agent_hash, $user_agent, $country_code );

		$this->visit_repo->create(
			array(
				'trap_page_id'  => 0,
				'ip_address'    => $ip_address,
				'user_agent'    => $user_agent,
				'referrer'      => '',
				'session_id'    => '',
				'depth_reached' => 0,
				'request_type'  => 'blocked_visit',
				'response_code' => $response_code,
				'country_code'  => $country_code,
			)
		);
	}

	/**
	 * Count blocked bots, optionally filtered by a search term.
	 *
	 * @param string $search Optional term matched against IP and user agent.
	 * @return int Blocked count.
	 * @since 1.3.0
	 */
	public function count_blocked_bots( string $search = '' ): int {
		return $this->score_repo->count_blocked( $search );
	}

	/**
	 * Get blocked bots with pagination, sorting and search.
	 *
	 * @param int    $limit   Number of results.
	 * @param int    $offset  Offset for pagination.
	 * @param string $orderby Column to sort by (validated by the repository).
	 * @param string $order   Sort direction, 'ASC' or 'DESC'.
	 * @param string $search  Optional term matched against IP and user agent.
	 * @return array Array of bot data arrays.
	 * @since 1.0.0
	 */
	public function get_blocked_bots( int $limit = 50, int $offset = 0, string $orderby = 'last_seen_at', string $order = 'DESC', string $search = '' ): array {
		return array_map(
			array( $this, 'format_bot' ),
			$this->score_repo->get_blocked( $limit, $offset, $orderby, $order, $search )
		);
	}

	/**
	 * Get all active trap pages.
	 *
	 * @return array Array of TrapPage entities.
	 * @since 1.0.0
	 */
	public function get_all_active_trap_pages(): array {
		return $this->trap_page_repo->get_all_active();
	}

	/**
	 * Get a paginated slice of active trap pages.
	 *
	 * @param int $limit  Number of pages per page.
	 * @param int $offset Number of rows to skip.
	 * @return array Array of TrapPage entities.
	 * @since 1.0.0
	 */
	public function get_trap_pages_paginated( int $limit, int $offset ): array {
		return $this->trap_page_repo->get_active_paginated( $limit, $offset );
	}

	/**
	 * Count all active trap pages.
	 *
	 * @return int Total active trap pages.
	 * @since 1.0.0
	 */
	public function get_trap_pages_count(): int {
		return $this->trap_page_repo->count_active();
	}

	/**
	 * Get trap visit counts grouped by country code.
	 *
	 * Returns an associative array of country_code => count for countries
	 * with at least one resolved visit. Empty country codes (geo tracking
	 * disabled at visit time) are excluded.
	 *
	 * @return array<string, int> Country code => visit count pairs.
	 * @since 1.0.0
	 */
	public function get_visits_by_country(): array {
		return $this->visit_repo->get_counts_by_country();
	}

	/**
	 * Count all visits in the last N days (all request types), optionally filtered.
	 *
	 * @param int    $days   Number of days.
	 * @param string $search Optional term matched against IP, user agent and country code.
	 * @return int Visit count.
	 * @since 1.0.0
	 */
	public function count_all_recent_activity( int $days = 7, string $search = '' ): int {
		return $this->visit_repo->count_all_recent_days( $days, $search );
	}

	/**
	 * Get recent activity within the last N days with pagination, sorting and search.
	 *
	 * @param int    $days    Number of days.
	 * @param int    $limit   Number of entries.
	 * @param int    $offset  Offset for pagination.
	 * @param string $orderby Column to sort by (validated by the repository).
	 * @param string $order   Sort direction, 'ASC' or 'DESC'.
	 * @param string $search  Optional term matched against IP, user agent and country code.
	 * @return array Array of visit data arrays.
	 * @since 1.0.0
	 */
	public function get_recent_activity_days( int $days = 7, int $limit = 50, int $offset = 0, string $orderby = 'visited_at', string $order = 'DESC', string $search = '' ): array {
		$visits = $this->visit_repo->get_recent_days( $days, $limit, $offset, $orderby, $order, $search );

		return array_map(
			function ( $visit ) {
				return array(
					'id'            => $visit->id,
					'trap_page_id'  => $visit->trap_page_id,
					'ip_address'    => $visit->ip_address,
					'user_agent'    => $visit->user_agent,
					'depth_reached' => $visit->depth_reached,
					'request_type'  => $visit->request_type,
					'visited_at'    => $visit->visited_at->format( 'Y-m-d H:i:s' ),
					'country_code'  => $visit->country_code,
					'country_name'  => CountryNames::get( $visit->country_code ),
				);
			},
			$visits
		);
	}
}
