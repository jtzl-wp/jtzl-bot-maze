<?php
/**
 * Recent Activity Admin Page
 *
 * @package Labyrinth
 * @since 1.0.0
 */

namespace Labyrinth\Admin;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use Labyrinth\Service\AnalyticsCollector;

/**
 * Recent Activity Page
 *
 * Lists trap page visits from the last 7 days with pagination.
 *
 * @since 1.0.0
 */
class RecentActivityPage {

	/**
	 * Analytics collector service.
	 *
	 * @var AnalyticsCollector
	 * @since 1.0.0
	 */
	private AnalyticsCollector $collector;

	/**
	 * Items per page.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private const PER_PAGE = 50;

	/**
	 * Constructor.
	 *
	 * @param AnalyticsCollector $collector Analytics collector service.
	 * @since 1.0.0
	 */
	public function __construct( AnalyticsCollector $collector ) {
		$this->collector = $collector;
	}

	/**
	 * Initialize the recent activity page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register the recent activity submenu page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_menu(): void {
		add_submenu_page(
			'labyrinth',
			__( 'Recent Activity', 'labyrinth' ),
			__( 'Recent Activity', 'labyrinth' ),
			'manage_options',
			'labyrinth-recent-activity',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the recent activity listing.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'labyrinth' ) );
		}

		$current_page = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter.
		$offset       = ( $current_page - 1 ) * self::PER_PAGE;
		$activity     = $this->collector->get_recent_activity_days( 7, self::PER_PAGE, $offset );
		$total_visits = $this->collector->count_all_recent_activity( 7 );
		$total_pages  = max( 1, (int) ceil( $total_visits / self::PER_PAGE ) );
		$per_page     = self::PER_PAGE;
		$countries    = $this->collector->get_visits_by_country();
		$geo_enabled  = (bool) get_option( 'lby_enable_geo_tracking', false );

		$template_path = LABYRINTH_PLUGIN_DIR . 'templates/admin-recent-activity.php';

		if ( file_exists( $template_path ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variable extraction is intentional.
			extract( compact( 'activity', 'current_page', 'total_pages', 'total_visits', 'per_page', 'countries', 'geo_enabled' ) );
			include $template_path; // @phpstan-ignore include.fileNotFound
		}
	}
}
