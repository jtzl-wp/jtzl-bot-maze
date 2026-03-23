<?php
/**
 * Trap Pages Admin Page
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
			'labyrinth',
			__( 'Trap Pages', 'labyrinth' ),
			__( 'Trap Pages', 'labyrinth' ),
			'manage_options',
			'labyrinth-trap-pages',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the trap pages listing.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'labyrinth' ) );
		}

		$pages     = $this->collector->get_all_active_trap_pages();
		$base_path = $this->base_path;

		$template_path = LABYRINTH_PLUGIN_DIR . 'templates/admin-trap-pages.php';

		if ( file_exists( $template_path ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variable extraction is intentional.
			extract( compact( 'pages', 'base_path' ) );
			include $template_path; // @phpstan-ignore include.fileNotFound
		}
	}
}
