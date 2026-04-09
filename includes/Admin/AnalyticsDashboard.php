<?php
/**
 * Analytics Dashboard Admin Page
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
 * Analytics Dashboard
 *
 * Renders the trap analytics admin page with summary stats,
 * charts, top bots table, and recent activity.
 *
 * @since 1.0.0
 */
class AnalyticsDashboard {

	/**
	 * Analytics collector service.
	 *
	 * @var AnalyticsCollector
	 * @since 1.0.0
	 */
	private AnalyticsCollector $collector;

	/**
	 * Constructor.
	 *
	 * @param AnalyticsCollector $collector Analytics data collector.
	 * @since 1.0.0
	 */
	public function __construct( AnalyticsCollector $collector ) {
		$this->collector = $collector;
	}

	/**
	 * Initialize the dashboard page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register the analytics submenu page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_menu(): void {
		add_submenu_page(
			'labyrinth',
			__( 'Labyrinth Analytics', 'labyrinth' ),
			__( 'Analytics', 'labyrinth' ),
			'manage_options',
			'labyrinth-analytics',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the analytics dashboard page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'labyrinth' ) );
		}

		$summary     = $this->collector->get_summary_stats();
		$chart_data  = $this->collector->get_chart_data();
		$top_bots    = $this->collector->get_top_bots();
		$countries   = $this->collector->get_visits_by_country();
		$geo_enabled = 'none' !== (string) get_option( 'lby_geo_provider', 'none' );

		// Pass data to template.
		$template_data = compact( 'summary', 'chart_data', 'top_bots', 'countries', 'geo_enabled' );

		$template_path = LABYRINTH_PLUGIN_DIR . 'templates/admin-analytics.php';

		if ( file_exists( $template_path ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variable extraction is intentional.
			extract( $template_data );
			include $template_path; // @phpstan-ignore include.fileNotFound
		}
	}
}
