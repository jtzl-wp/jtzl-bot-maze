<?php
/**
 * Robots.txt Settings Section Registrar
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze\Admin\Settings;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Maze\Admin\SettingsPage;
use JTZL\Bot_Maze\Admin\SettingsSanitizer;

/**
 * Registers the robots.txt settings section.
 *
 * Controls whether the plugin appends Disallow rules to the virtual
 * robots.txt to steer compliant crawlers away from the trap base path.
 *
 * @since 1.0.0
 */
class RobotsSectionRegistrar extends AbstractSectionRegistrar {

	private const SECTION_ID = 'jtzl_bm_robots_settings';

	/**
	 * Register the robots.txt section and its field.
	 *
	 * @param SettingsPage $page Settings page providing the field renderers.
	 * @return void
	 * @since 1.0.0
	 */
	public function register( SettingsPage $page ): void {
		$this->add_section( self::SECTION_ID, __( 'robots.txt', 'jtzl-bot-maze' ) );

		$this->register_field(
			'jtzl_bm_robots_txt_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_checkbox' ),
				'default'           => true,
			),
			__( 'Modify robots.txt', 'jtzl-bot-maze' ),
			array( $page, 'render_checkbox_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bm_robots_txt_enabled',
				'description' => __( 'Add a Disallow rule for the trap base path to keep compliant crawlers out of trap URLs.', 'jtzl-bot-maze' ),
			)
		);
	}

	/**
	 * Render the robots.txt section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_section_description(): void {
		echo '<p>' . esc_html__( 'Control robots.txt modifications that help lure bots into the trap maze.', 'jtzl-bot-maze' ) . '</p>';
	}
}
