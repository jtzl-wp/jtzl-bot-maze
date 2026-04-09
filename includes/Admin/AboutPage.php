<?php
/**
 * About Page
 *
 * @package JTZL\Bot_Be_Gone
 * @since 1.0.0
 */

namespace JTZL\Bot_Be_Gone\Admin;

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
			__( 'JTZL\'s Bot-be-Gone', 'jtzl-bot-be-gone' ),
			__( 'Bot-be-Gone', 'jtzl-bot-be-gone' ),
			'manage_options',
			'jtzl-bot-be-gone',
			array( $this, 'render_page' ),
			'dashicons-shield',
			80
		);

		// Replace the auto-generated first submenu with "About".
		add_submenu_page(
			'jtzl-bot-be-gone',
			__( 'About JTZL\'s Bot-be-Gone', 'jtzl-bot-be-gone' ),
			__( 'About', 'jtzl-bot-be-gone' ),
			'manage_options',
			'jtzl-bot-be-gone',
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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'jtzl-bot-be-gone' ) );
		}

		$template_path = JTZL_BBG_PLUGIN_DIR . 'templates/admin-about.php';

		if ( file_exists( $template_path ) ) {
			include $template_path; // @phpstan-ignore include.fileNotFound
		}
	}
}
