<?php
/**
 * Trap Pages Admin Page
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
 * Trap Pages Page
 *
 * Lists all active trap pages with clickable preview links.
 *
 * @since 1.0.0
 */
class TrapPagesPage {

	/**
	 * Analytics collector service.
	 *
	 * @var AnalyticsCollector
	 * @since 1.0.0
	 */
	private AnalyticsCollector $collector;

	/**
	 * Base path for trap URLs.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $base_path;

	/**
	 * Constructor.
	 *
	 * @param AnalyticsCollector $collector Analytics collector service.
	 * @param string             $base_path URL base path for trap pages.
	 * @since 1.0.0
	 */
	public function __construct( AnalyticsCollector $collector, string $base_path ) {
		$this->collector = $collector;
		$this->base_path = $base_path;
	}

	/**
	 * Initialize the trap pages page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register the trap pages submenu page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_menu(): void {
		add_submenu_page(
			'jtzl-bot-maze',
			__( 'Trap Pages', 'jtzl-bot-maze' ),
			__( 'Trap Pages', 'jtzl-bot-maze' ),
			'manage_options',
			'jtzl-bot-maze-trap-pages',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Number of trap pages shown per admin page.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	const PER_PAGE = 50;

	/**
	 * Render the trap pages listing.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'jtzl-bot-maze' ) );
		}

		$total_count = $this->collector->get_trap_pages_count();
		$total_pages = (int) ceil( $total_count / self::PER_PAGE );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page number, no state change.
		$current_page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$current_page = min( $current_page, max( 1, $total_pages ) );
		$offset       = ( $current_page - 1 ) * self::PER_PAGE;

		$pages     = $this->collector->get_trap_pages_paginated( self::PER_PAGE, $offset );
		$base_path = $this->base_path;

		$template_path = JTZL_BM_PLUGIN_DIR . 'templates/admin-trap-pages.php';

		if ( file_exists( $template_path ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variable extraction is intentional.
			extract( compact( 'pages', 'base_path', 'total_count', 'total_pages', 'current_page' ) );
			include $template_path; // @phpstan-ignore include.fileNotFound
		}
	}
}
