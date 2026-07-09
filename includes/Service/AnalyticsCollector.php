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
		$bots = $this->score_repo->get_top_by_score( $limit );

		$lookups     = $this->build_bot_lookups( $bots );
		$ua_map      = $lookups['ua'];
		$country_map = $lookups['country'];

		return array_map(
			function ( $bot ) use ( $ua_map, $country_map ) {
				$key          = $bot->ip_address . '|' . $bot->user_agent_hash;
				$ua           = $ua_map[ $key ] ?? $bot->user_agent_hash;
				$country_code = $country_map[ $bot->ip_address ] ?? '';
				return array(
					'id'                => $bot->id,
					'ip_address'        => $bot->ip_address,
					'user_agent'        => $ua,
					'score'             => $bot->score,
					'trap_visits_count' => $bot->trap_visits_count,
					'max_depth_reached' => $bot->max_depth_reached,
					'first_seen_at'     => $bot->first_seen_at->format( 'Y-m-d H:i:s' ),
					'last_seen_at'      => $bot->last_seen_at->format( 'Y-m-d H:i:s' ),
					'is_blocked'        => $bot->is_blocked,
					'country_code'      => $country_code,
					'country_name'      => CountryNames::get( $country_code ),
				);
			},
			$bots
		);
	}

	/**
	 * Build user-agent and country lookups from recent visits for a set of bots.
	 *
	 * @param array $bots Bot score entities to resolve data for.
	 * @return array{ua: array<string, string>, country: array<string, string>}
	 * @since 1.0.0
	 */
	private function build_bot_lookups( array $bots ): array {
		if ( empty( $bots ) ) {
			return array(
				'ua'      => array(),
				'country' => array(),
			);
		}

		$needed = array();
		foreach ( $bots as $bot ) {
			$needed[ $bot->ip_address . '|' . $bot->user_agent_hash ] = true;
		}

		$visits      = $this->visit_repo->get_recent( count( $bots ) * 20 );
		$ua_map      = array();
		$country_map = array();

		foreach ( $visits as $visit ) {
			$hash = hash( 'sha256', $visit->user_agent );
			$key  = $visit->ip_address . '|' . $hash;

			if ( isset( $needed[ $key ] ) && ! isset( $ua_map[ $key ] ) ) {
				$ua_map[ $key ] = $visit->user_agent;
			}

			if ( ! empty( $visit->country_code ) && ! isset( $country_map[ $visit->ip_address ] ) ) {
				$country_map[ $visit->ip_address ] = $visit->country_code;
			}
		}

		return array(
			'ua'      => $ua_map,
			'country' => $country_map,
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
		$this->score_repo->touch_blocked_visit( $ip_address, $user_agent_hash );

		$country_code = $this->geo_ip_service->resolve_country( $ip_address );

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
	 * Get blocked bots with resolved user agents.
	 *
	 * @param int $limit  Number of results.
	 * @param int $offset Offset for pagination.
	 * @return array Array of bot data arrays.
	 * @since 1.0.0
	 */
	public function get_blocked_bots( int $limit = 50, int $offset = 0 ): array {
		$bots = $this->score_repo->get_blocked( $limit, $offset );

		$lookups     = $this->build_bot_lookups( $bots );
		$ua_map      = $lookups['ua'];
		$country_map = $lookups['country'];

		return array_map(
			function ( $bot ) use ( $ua_map, $country_map ) {
				$key          = $bot->ip_address . '|' . $bot->user_agent_hash;
				$ua           = $ua_map[ $key ] ?? $bot->user_agent_hash;
				$country_code = $country_map[ $bot->ip_address ] ?? '';
				return array(
					'id'                => $bot->id,
					'ip_address'        => $bot->ip_address,
					'user_agent'        => $ua,
					'score'             => $bot->score,
					'trap_visits_count' => $bot->trap_visits_count,
					'max_depth_reached' => $bot->max_depth_reached,
					'first_seen_at'     => $bot->first_seen_at->format( 'Y-m-d H:i:s' ),
					'last_seen_at'      => $bot->last_seen_at->format( 'Y-m-d H:i:s' ),
					'is_blocked'        => $bot->is_blocked,
					'country_code'      => $country_code,
					'country_name'      => CountryNames::get( $country_code ),
				);
			},
			$bots
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
	 * Count all visits in the last N days (all request types).
	 *
	 * @param int $days Number of days.
	 * @return int Visit count.
	 * @since 1.0.0
	 */
	public function count_all_recent_activity( int $days = 7 ): int {
		return $this->visit_repo->count_all_recent_days( $days );
	}

	/**
	 * Get recent activity within the last N days with pagination.
	 *
	 * @param int $days   Number of days.
	 * @param int $limit  Number of entries.
	 * @param int $offset Offset for pagination.
	 * @return array Array of visit data arrays.
	 * @since 1.0.0
	 */
	public function get_recent_activity_days( int $days = 7, int $limit = 50, int $offset = 0 ): array {
		$visits = $this->visit_repo->get_recent_days( $days, $limit, $offset );

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
