<?php
/**
 * Abstract Section Registrar
 *
 * @package JTZL\Bot_Be_Gone
 * @since 1.0.0
 */

namespace JTZL\Bot_Be_Gone\Admin\Settings;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Be_Gone\Admin\SettingsPage;

/**
 * Shared helpers for concrete section registrars.
 *
 * Exists so each concrete section's register() method can focus on its
 * own fields instead of restating the Settings API boilerplate for
 * every single one.
 *
 * @since 1.0.0
 */
abstract class AbstractSectionRegistrar implements SectionRegistrarInterface {

	/**
	 * Register a Settings API section pinned to this registrar's description callback.
	 *
	 * @param string $section_id Section identifier.
	 * @param string $title      Human-readable section title.
	 * @return void
	 * @since 1.0.0
	 */
	protected function add_section( string $section_id, string $title ): void {
		add_settings_section(
			$section_id,
			$title,
			array( $this, 'render_section_description' ),
			SettingsPage::PAGE_SLUG
		);
	}

	/**
	 * Register one option + its settings field in a single call.
	 *
	 * @param string   $option_name     Option name under SETTINGS_GROUP.
	 * @param array    $setting_config  Config passed to register_setting().
	 * @param string   $title           Field label shown in the UI.
	 * @param callable $render_callback Callable used by add_settings_field().
	 * @param string   $section_id      Section to attach the field to.
	 * @param array    $field_args      Extra args forwarded to the render callback.
	 * @return void
	 * @since 1.0.0
	 */
	protected function register_field(
		string $option_name,
		array $setting_config,
		string $title,
		callable $render_callback,
		string $section_id,
		array $field_args = array()
	): void {
		register_setting( SettingsPage::SETTINGS_GROUP, $option_name, $setting_config );
		add_settings_field(
			$option_name,
			$title,
			$render_callback,
			SettingsPage::PAGE_SLUG,
			$section_id,
			$field_args
		);
	}
}
