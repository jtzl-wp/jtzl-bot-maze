<?php
/**
 * Bot Detector Service
 *
 * Scoring engine for bot detection based on trap page visits.
 *
 * @package Labyrinth
 * @since 1.0.0
 */

namespace Labyrinth\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use Labyrinth\Entity\TrapPage;
use Labyrinth\Repository\BotScoreRepositoryInterface;
use Labyrinth\Repository\TrapVisitRepositoryInterface;
use Labyrinth\Repository\RequestLogRepositoryInterface;

/**
 * Bot Detector
 *
 * Records trap page visits, calculates bot scores, and triggers blocking.
 *
 * @since 1.0.0
 */
class BotDetector {

	/**
	 * Bot score repository.
	 *
	 * @var BotScoreRepositoryInterface
	 * @since 1.0.0
	 */
	private BotScoreRepositoryInterface $score_repo;

	/**
	 * Trap visit repository.
	 *
	 * @var TrapVisitRepositoryInterface
	 * @since 1.0.0
	 */
	private TrapVisitRepositoryInterface $visit_repo;

	/**
	 * Request log repository.
	 *
	 * @var RequestLogRepositoryInterface
	 * @since 1.0.0
	 */
	private RequestLogRepositoryInterface $log_repo;

	/**
	 * IP tracker service.
	 *
	 * @var IPTrackerInterface
	 * @since 1.0.0
	 */
	private IPTrackerInterface $ip_tracker;

	/**
	 * Crawler verifier service.
	 *
	 * @var CrawlerVerifierInterface
	 * @since 1.0.0
	 */
	private CrawlerVerifierInterface $crawler_verifier;

	/**
	 * Base points added per trap page visit.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private int $points_per_visit;

	/**
	 * Bonus points multiplied by depth level.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private int $depth_bonus;

	/**
	 * Score threshold for auto-blocking.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private int $block_threshold;

	/**
	 * Constructor.
	 *
	 * @param BotScoreRepositoryInterface   $score_repo       Bot score repository.
	 * @param TrapVisitRepositoryInterface  $visit_repo       Trap visit repository.
	 * @param RequestLogRepositoryInterface $log_repo         Request log repository.
	 * @param IPTrackerInterface            $ip_tracker       IP tracker service.
	 * @param CrawlerVerifierInterface      $crawler_verifier Crawler verifier service.
	 * @param int                           $points_per_visit Base points per visit.
	 * @param int                           $depth_bonus      Depth-level bonus multiplier.
	 * @param int                           $block_threshold  Auto-block threshold.
	 * @since 1.0.0
	 */
	public function __construct(
		BotScoreRepositoryInterface $score_repo,
		TrapVisitRepositoryInterface $visit_repo,
		RequestLogRepositoryInterface $log_repo,
		IPTrackerInterface $ip_tracker,
		CrawlerVerifierInterface $crawler_verifier,
		int $points_per_visit,
		int $depth_bonus,
		int $block_threshold
	) {
		$this->score_repo       = $score_repo;
		$this->visit_repo       = $visit_repo;
		$this->log_repo         = $log_repo;
		$this->ip_tracker       = $ip_tracker;
		$this->crawler_verifier = $crawler_verifier;
		$this->points_per_visit = $points_per_visit;
		$this->depth_bonus      = $depth_bonus;
		$this->block_threshold  = $block_threshold;
	}

	/**
	 * Record a trap page visit and update bot score.
	 *
	 * @param TrapPage $trap_page The trap page that was visited.
	 * @return void
	 * @since 1.0.0
	 */
	public function record_visit( TrapPage $trap_page ): void {
		$ip         = $this->ip_tracker->get_client_ip();
		$ua         = $this->ip_tracker->get_user_agent();
		$ua_hash    = $this->ip_tracker->get_user_agent_hash();
		$session_id = $this->ip_tracker->generate_session_id();
		$referrer   = $this->ip_tracker->get_referrer();

		if ( empty( $ip ) ) {
			return;
		}

		if ( $this->crawler_verifier->is_verified_crawler( $ip, $ua ) ) {
			$this->log_repo->create(
				array(
					'ip_address'    => $ip,
					'request_uri'   => $this->ip_tracker->get_request_uri(),
					'user_agent'    => $ua,
					'request_type'  => 'crawler_exempted',
					'response_code' => 200,
				)
			);
			return;
		}

		// Record the visit.
		$this->visit_repo->create(
			array(
				'trap_page_id'  => $trap_page->id,
				'ip_address'    => $ip,
				'user_agent'    => $ua,
				'referrer'      => $referrer,
				'session_id'    => $session_id,
				'depth_reached' => $trap_page->depth_level,
			)
		);

		// Log the raw request.
		$this->log_repo->create(
			array(
				'ip_address'    => $ip,
				'request_uri'   => $this->ip_tracker->get_request_uri(),
				'user_agent'    => $ua,
				'request_type'  => 'trap_visit',
				'response_code' => 200,
			)
		);

		// Find or create a bot score record.
		$bot_score = $this->score_repo->find_or_create( $ip, $ua_hash );

		// Score update is best-effort; visit logging above is more important.
		if ( null === $bot_score ) {
			return;
		}

		// Calculate points: base + (depth_bonus * depth_level).
		$points = $this->points_per_visit + ( $this->depth_bonus * $trap_page->depth_level );

		// Update score and compute blocking atomically in SQL to avoid TOCTOU races.
		$this->score_repo->update_score(
			$bot_score->id,
			(float) $points,
			$trap_page->depth_level,
			$this->block_threshold
		);
	}

	/**
	 * Check if a visitor is blocked.
	 *
	 * @return bool True if the current visitor is blocked.
	 * @since 1.0.0
	 */
	public function is_visitor_blocked(): bool {
		$ip      = $this->ip_tracker->get_client_ip();
		$ua_hash = $this->ip_tracker->get_user_agent_hash();

		if ( empty( $ip ) ) {
			return false;
		}

		if ( ! $this->score_repo->is_blocked( $ip, $ua_hash ) ) {
			return false;
		}

		$ua = $this->ip_tracker->get_user_agent();

		if ( $this->crawler_verifier->is_verified_crawler( $ip, $ua ) ) {
			return false;
		}

		return true;
	}
}
