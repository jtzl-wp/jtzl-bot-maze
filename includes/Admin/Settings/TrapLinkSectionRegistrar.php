<?php
/**
 * Trap Link Settings Section Registrar
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
 * Registers the Trap Links settings section.
 *
 * Controls how hidden trap links are injected into real pages.
 *
 * @since 1.0.0
 */
class TrapLinkSectionRegistrar extends AbstractSectionRegistrar {

	private const SECTION_ID = 'jtzl_bm_trap_link_settings';

	/**
	 * Register the Trap Links section and its fields.
	 *
	 * @param SettingsPage $page Settings page providing the field renderers.
	 * @return void
	 * @since 1.0.0
	 */
	public function register( SettingsPage $page ): void {
		$this->add_section( self::SECTION_ID, __( 'Trap Links', 'jtzl-bot-maze' ) );

		$this->register_field(
			'jtzl_bm_links_per_page',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bm_links_per_page' ),
				'default'           => 3,
			),
			__( 'Links Per Page', 'jtzl-bot-maze' ),
			array( $page, 'render_number_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bm_links_per_page',
				'description' => __( 'Number of hidden trap links injected into each real page.', 'jtzl-bot-maze' ),
				'min'         => 1,
				'max'         => 10,
			)
		);

		$this->register_field(
			'jtzl_bm_injection_method',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_injection_method' ),
				'default'           => 'content',
			),
			__( 'Injection Method', 'jtzl-bot-maze' ),
			array( $page, 'render_select_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bm_injection_method',
				'description' => __( 'Where to inject hidden trap links on real pages.', 'jtzl-bot-maze' ),
				'options'     => array(
					'content' => __( 'Content only (the_content filter)', 'jtzl-bot-maze' ),
					'footer'  => __( 'Footer only (wp_footer action)', 'jtzl-bot-maze' ),
					'both'    => __( 'Both content and footer', 'jtzl-bot-maze' ),
				),
			)
		);
	}

	/**
	 * Render the Trap Links section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_section_description(): void {
		echo '<p>' . esc_html__( 'Configure how hidden trap links are injected into your real pages.', 'jtzl-bot-maze' ) . '</p>';
	}
}
