<?php
/**
 * Blocked Bots Admin Page
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
			'labyrinth',
			__( 'Blocked Bots', 'labyrinth' ),
			__( 'Blocked Bots', 'labyrinth' ),
			'manage_options',
			'labyrinth-blocked-bots',
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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'labyrinth' ) );
		}

		$bots          = $this->collector->get_blocked_bots();
		$total_blocked = $this->collector->get_summary_stats()['blocked_bots'];

		$template_path = LABYRINTH_PLUGIN_DIR . 'templates/admin-blocked-bots.php';

		if ( file_exists( $template_path ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variable extraction is intentional.
			extract( compact( 'bots', 'total_blocked' ) );
			include $template_path; // @phpstan-ignore include.fileNotFound
		}
	}
}
