<?php
/**
 * About Page
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

/**
 * About Page
 *
 * Renders a how-to guide and plugin information page.
 *
 * @since 1.0.0
 */
class AboutPage {

	/**
	 * Initialize the about page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register the top-level menu and about submenu page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Labyrinth Bot Protection', 'labyrinth' ),
			__( 'Labyrinth', 'labyrinth' ),
			'manage_options',
			'labyrinth',
			array( $this, 'render_page' ),
			'dashicons-shield',
			80
		);

		// Replace the auto-generated first submenu with "About".
		add_submenu_page(
			'labyrinth',
			__( 'About Labyrinth', 'labyrinth' ),
			__( 'About', 'labyrinth' ),
			'manage_options',
			'labyrinth',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the about page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'labyrinth' ) );
		}

		$template_path = LABYRINTH_PLUGIN_DIR . 'templates/admin-about.php';

		if ( file_exists( $template_path ) ) {
			include $template_path; // @phpstan-ignore include.fileNotFound
		}
	}
}
