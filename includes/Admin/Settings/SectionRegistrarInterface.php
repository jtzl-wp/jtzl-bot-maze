<?php
/**
 * Settings Section Registrar Interface
 *
 * @package JTZL\Bot_Labyrinth
 * @since 1.0.0
 */

namespace JTZL\Bot_Labyrinth\Admin\Settings;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Labyrinth\Admin\SettingsPage;

/**
 * Contract for a cohesive slice of the plugin settings page.
 *
 * Each implementation owns one WordPress Settings API section: it declares
 * the section, registers its fields and sanitizers, and renders the
 * section description. SettingsPage composes a list of these registrars
 * and delegates all field-level work to them.
 *
 * @since 1.0.0
 */
interface SectionRegistrarInterface {

	/**
	 * Register the section and all its fields with the Settings API.
	 *
	 * @param SettingsPage $page Settings page providing the generic field
	 *                           render callbacks used by this section.
	 * @return void
	 * @since 1.0.0
	 */
	public function register( SettingsPage $page ): void;

	/**
	 * Render the section description (used as the Settings API callback).
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_section_description(): void;
}
