<?php
/**
 * General Settings Section Registrar
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
use JTZL\Bot_Be_Gone\Admin\SettingsSanitizer;

/**
 * Registers the General settings section.
 *
 * Owns the enable toggle, trusted-proxy header/allowlist pair, and
 * trap base path fields.
 *
 * @since 1.0.0
 */
class GeneralSectionRegistrar extends AbstractSectionRegistrar {

	private const SECTION_ID = 'jtzl_bbg_general_settings';

	/**
	 * Register the General section and its fields.
	 *
	 * @param SettingsPage $page Settings page providing the field renderers.
	 * @return void
	 * @since 1.0.0
	 */
	public function register( SettingsPage $page ): void {
		$this->add_section( self::SECTION_ID, __( 'General', 'jtzl-bot-be-gone' ) );
		$this->register_core_fields( $page );
		$this->register_base_path_field( $page );
	}

	/**
	 * Register the enable toggle and trusted-proxy pair.
	 *
	 * @param SettingsPage $page Settings page for render callbacks.
	 * @return void
	 * @since 1.0.0
	 */
	private function register_core_fields( SettingsPage $page ): void {
		$this->register_field(
			'jtzl_bbg_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_enabled' ),
				'default'           => true,
			),
			__( 'Enable Bot-be-Gone', 'jtzl-bot-be-gone' ),
			array( $page, 'render_checkbox_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bbg_enabled',
				'description' => __( 'Enable or disable the bot protection trap maze.', 'jtzl-bot-be-gone' ),
			)
		);

		$this->register_field(
			'jtzl_bbg_trusted_proxy_header',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_trusted_proxy_header' ),
				'default'           => '',
			),
			__( 'Trusted Client IP Header', 'jtzl-bot-be-gone' ),
			array( $page, 'render_select_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bbg_trusted_proxy_header',
				'description' => __( 'Trust this header as the real client IP only if your site is behind a reverse proxy or CDN that sets it. If you are unsure, leave this on None. Incorrect configuration can allow IP spoofing and affect blocking and analytics.', 'jtzl-bot-be-gone' ),
				'options'     => array(
					''                 => __( 'None (safe default)', 'jtzl-bot-be-gone' ),
					'CF-Connecting-IP' => __( 'Cloudflare (CF-Connecting-IP)', 'jtzl-bot-be-gone' ),
					'X-Real-IP'        => __( 'Nginx / Reverse proxy (X-Real-IP)', 'jtzl-bot-be-gone' ),
					'True-Client-IP'   => __( 'Enterprise CDN (True-Client-IP)', 'jtzl-bot-be-gone' ),
					'X-Forwarded-For'  => __( 'Advanced: X-Forwarded-For (rightmost untrusted IP)', 'jtzl-bot-be-gone' ),
				),
			)
		);

		$this->register_field(
			'jtzl_bbg_trusted_proxy_ips',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_trusted_proxy_ips' ),
				'default'           => '',
			),
			__( 'Trusted Proxy IPs / CIDRs', 'jtzl-bot-be-gone' ),
			array( $page, 'render_text_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bbg_trusted_proxy_ips',
				'description' => __( 'Comma-separated IP addresses or CIDR ranges allowed to set the trusted header above. Required when a trusted header is configured — the header is ignored until at least one proxy is listed. Example: <code>172.64.0.0/13, 173.245.48.0/20</code>', 'jtzl-bot-be-gone' ),
			)
		);
	}

	/**
	 * Register the trap base path field.
	 *
	 * @param SettingsPage $page Settings page for render callbacks.
	 * @return void
	 * @since 1.0.0
	 */
	private function register_base_path_field( SettingsPage $page ): void {
		$this->register_field(
			'jtzl_bbg_trap_base_path',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_base_path' ),
				'default'           => 'bbg',
			),
			__( 'Trap Base Path', 'jtzl-bot-be-gone' ),
			array( $page, 'render_text_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bbg_trap_base_path',
				'description' => __( 'URL path prefix for trap pages (e.g., "bbg" makes URLs like /bbg/page-slug). Changing this requires a permalink refresh.', 'jtzl-bot-be-gone' ),
			)
		);
	}

	/**
	 * Render the General section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_section_description(): void {
		echo '<p>' . esc_html__( 'Enable or disable the plugin and configure the base URL path for trap pages.', 'jtzl-bot-be-gone' ) . '</p>';
	}
}
