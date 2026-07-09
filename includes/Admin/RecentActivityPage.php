<?php
/**
 * Recent Activity Admin Page
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze\Admin;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Maze\Service\AnalyticsCollector;

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
			'jtzl-bot-maze',
			__( 'Recent Activity', 'jtzl-bot-maze' ),
			__( 'Recent Activity', 'jtzl-bot-maze' ),
			'manage_options',
			'jtzl-bot-maze-recent-activity',
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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'jtzl-bot-maze' ) );
		}

		$list_table = new RecentActivityListTable( $this->collector );
		$list_table->prepare_items();

		$countries   = $this->collector->get_visits_by_country();
		$geo_enabled = 'none' !== (string) get_option( 'jtzl_bm_geo_provider', 'none' );

		$template_path = JTZL_BM_PLUGIN_DIR . 'templates/admin-recent-activity.php';

		if ( file_exists( $template_path ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variable extraction is intentional.
			extract( compact( 'list_table', 'countries', 'geo_enabled' ) );
			include $template_path; // @phpstan-ignore include.fileNotFound
		}
	}
}
