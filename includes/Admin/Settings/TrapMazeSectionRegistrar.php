<?php
/**
 * Trap Maze Settings Section Registrar
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
 * Registers the Trap Maze settings section.
 *
 * Controls depth, fan-out, and size of the generated trap maze.
 *
 * @since 1.0.0
 */
class TrapMazeSectionRegistrar extends AbstractSectionRegistrar {

	private const SECTION_ID = 'jtzl_bm_trap_maze_settings';

	/**
	 * Register the Trap Maze section and its fields.
	 *
	 * @param SettingsPage $page Settings page providing the field renderers.
	 * @return void
	 * @since 1.0.0
	 */
	public function register( SettingsPage $page ): void {
		$this->add_section( self::SECTION_ID, __( 'Trap Maze', 'jtzl-bot-maze' ) );
		$this->register_shape_fields( $page );
		$this->register_volume_fields( $page );
	}

	/**
	 * Register depth and link fan-out fields.
	 *
	 * @param SettingsPage $page Settings page for render callbacks.
	 * @return void
	 * @since 1.0.0
	 */
	private function register_shape_fields( SettingsPage $page ): void {
		$this->register_field(
			'jtzl_bm_max_depth',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bm_max_depth' ),
				'default'           => 5,
			),
			__( 'Max Depth', 'jtzl-bot-maze' ),
			array( $page, 'render_number_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bm_max_depth',
				'description' => __( 'Maximum depth of the trap page maze. Deeper mazes consume more storage but provide stronger evidence.', 'jtzl-bot-maze' ),
				'min'         => 2,
				'max'         => 10,
			)
		);

		$this->register_field(
			'jtzl_bm_links_per_trap_page',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bm_links_per_trap_page' ),
				'default'           => 5,
			),
			__( 'Links Per Trap Page', 'jtzl-bot-maze' ),
			array( $page, 'render_number_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bm_links_per_trap_page',
				'description' => __( 'Number of links to other trap pages within each trap page.', 'jtzl-bot-maze' ),
				'min'         => 1,
				'max'         => 10,
			)
		);
	}

	/**
	 * Register initial page count and global cap fields.
	 *
	 * @param SettingsPage $page Settings page for render callbacks.
	 * @return void
	 * @since 1.0.0
	 */
	private function register_volume_fields( SettingsPage $page ): void {
		$this->register_field(
			'jtzl_bm_initial_page_count',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bm_initial_page_count' ),
				'default'           => 20,
			),
			__( 'Initial Page Count', 'jtzl-bot-maze' ),
			array( $page, 'render_number_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bm_initial_page_count',
				'description' => __( 'Number of entry-level trap pages to generate. More pages make the trap maze harder to map.', 'jtzl-bot-maze' ),
				'min'         => 5,
				'max'         => 100,
			)
		);

		$this->register_field(
			'jtzl_bm_max_trap_pages',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bm_max_trap_pages' ),
				'default'           => 500,
			),
			__( 'Global Maximum Trap Pages', 'jtzl-bot-maze' ),
			array( $page, 'render_number_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bm_max_trap_pages',
				'description' => __( 'Maximum total number of trap pages (all depths). Once reached, no new pages are generated. Set higher to allow deeper mazes at the cost of more database rows.', 'jtzl-bot-maze' ),
				'min'         => 20,
				'max'         => 5000,
			)
		);
	}

	/**
	 * Render the Trap Maze section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_section_description(): void {
		echo '<p>' . esc_html__( 'Configure the maze of trap pages that bots will be lured into.', 'jtzl-bot-maze' ) . '</p>';
		echo '<p>' . esc_html__( 'Trap URLs under your base path always render as lightweight standalone HTML without your theme (header, menus, and theme assets are not loaded). This applies to every trap maze hit—not only blocked-visitor tarpit responses—and keeps server cost lower than a full themed page. Bots are still led by trap links and generated content; they do not see a pixel-perfect copy of your real posts.', 'jtzl-bot-maze' ) . '</p>';
	}
}
