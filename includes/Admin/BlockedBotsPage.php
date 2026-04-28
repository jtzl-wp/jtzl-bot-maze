<?php
/**
 * Blocked Bots Admin Page
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
 * Blocked Bots Page
 *
 * Lists all blocked bots with full details.
 *
 * @since 1.0.0
 */
class BlockedBotsPage {

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
	 * Initialize the blocked bots page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register the blocked bots submenu page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_menu(): void {
		add_submenu_page(
			'jtzl-bot-maze',
			__( 'Blocked Bots', 'jtzl-bot-maze' ),
			__( 'Blocked Bots', 'jtzl-bot-maze' ),
			'manage_options',
			'jtzl-bot-maze-blocked-bots',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the blocked bots listing.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'jtzl-bot-maze' ) );
		}

		$bots          = $this->collector->get_blocked_bots();
		$total_blocked = $this->collector->get_summary_stats()['blocked_bots'];
		$countries     = $this->collector->get_visits_by_country();
		$geo_enabled   = 'none' !== (string) get_option( 'jtzl_bm_geo_provider', 'none' );

		$template_path = JTZL_BM_PLUGIN_DIR . 'templates/admin-blocked-bots.php';

		if ( file_exists( $template_path ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variable extraction is intentional.
			extract( compact( 'bots', 'total_blocked', 'countries', 'geo_enabled' ) );
			include $template_path; // @phpstan-ignore include.fileNotFound
		}
	}
}
