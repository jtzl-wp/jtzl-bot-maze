<?php
/**
 * General Settings Section Registrar
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
 * Registers the General settings section.
 *
 * Owns the enable toggle, trusted-proxy header/allowlist pair, and
 * trap base path fields.
 *
 * @since 1.0.0
 */
class GeneralSectionRegistrar extends AbstractSectionRegistrar {

	private const SECTION_ID = 'jtzl_bm_general_settings';

	/**
	 * Register the General section and its fields.
	 *
	 * @param SettingsPage $page Settings page providing the field renderers.
	 * @return void
	 * @since 1.0.0
	 */
	public function register( SettingsPage $page ): void {
		$this->add_section( self::SECTION_ID, __( 'General', 'jtzl-bot-maze' ) );
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
			'jtzl_bm_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_enabled' ),
				'default'           => true,
			),
			__( 'Enable Bot Maze', 'jtzl-bot-maze' ),
			array( $page, 'render_checkbox_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bm_enabled',
				'description' => __( 'Enable or disable the bot protection trap maze.', 'jtzl-bot-maze' ),
			)
		);

		$this->register_field(
			'jtzl_bm_trusted_proxy_header',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_trusted_proxy_header' ),
				'default'           => '',
			),
			__( 'Trusted Client IP Header', 'jtzl-bot-maze' ),
			array( $page, 'render_select_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bm_trusted_proxy_header',
				'description' => __( 'Trust this header as the real client IP only if your site is behind a reverse proxy or CDN that sets it. If you are unsure, leave this on None. Incorrect configuration can allow IP spoofing and affect blocking and analytics. Choosing Cloudflare auto-loads and weekly-refreshes Cloudflare\'s official IP ranges, so no manual entry is needed. Note: Cloudflare\'s IP ranges are shared by all Cloudflare sites, so only use the Cloudflare preset if your server can\'t be reached directly by its own IP address (only through Cloudflare) — otherwise visitor IPs can be faked.', 'jtzl-bot-maze' ),
				'options'     => array(
					''                 => __( 'None (safe default)', 'jtzl-bot-maze' ),
					'CF-Connecting-IP' => __( 'Cloudflare (CF-Connecting-IP)', 'jtzl-bot-maze' ),
					'X-Real-IP'        => __( 'Nginx / Reverse proxy (X-Real-IP)', 'jtzl-bot-maze' ),
					'True-Client-IP'   => __( 'Enterprise CDN (True-Client-IP)', 'jtzl-bot-maze' ),
					'X-Forwarded-For'  => __( 'Advanced: X-Forwarded-For (rightmost untrusted IP)', 'jtzl-bot-maze' ),
				),
			)
		);

		$this->register_field(
			'jtzl_bm_trusted_proxy_ips',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_trusted_proxy_ips' ),
				'default'           => '',
			),
			__( 'Trusted Proxy IPs / CIDRs', 'jtzl-bot-maze' ),
			array( $page, 'render_text_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bm_trusted_proxy_ips',
				'description' => $this->trusted_proxy_ips_description(),
			)
		);
	}

	/**
	 * Build the trusted-proxy-IPs field description.
	 *
	 * The field is an optional override: when a CDN preset with built-in
	 * ranges (currently Cloudflare) is selected, it can be left blank. When
	 * Cloudflare is active, append a status line confirming the ranges are
	 * managed automatically, with the last-refresh date when available.
	 *
	 * @return string Field description (may contain inline HTML).
	 * @since 1.1.0
	 */
	private function trusted_proxy_ips_description(): string {
		$description = __( 'Cloudflare\'s official IP ranges are built in, so you can leave this blank when Cloudflare is selected. Leaving it blank trusts Cloudflare\'s shared edge ranges, which is only safe if your server can\'t be reached directly by its own IP address (only through Cloudflare). Every other header (X-Real-IP, True-Client-IP, X-Forwarded-For) has no built-in list — you must enter at least one proxy IP or CIDR range here, or the header is ignored. You can also list values to whitelist an extra proxy or override the Cloudflare defaults. Example: <code>172.64.0.0/13, 173.245.48.0/20</code>', 'jtzl-bot-maze' );

		if ( 'CF-Connecting-IP' !== (string) get_option( 'jtzl_bm_trusted_proxy_header', '' ) ) {
			return $description;
		}

		// Surface only the scalar refresh timestamp written by
		// Service\CloudflareRangeUpdater. Reading the raw option (rather than
		// CloudflareIpRanges::all()) mirrors GeoSectionRegistrar's MaxMind
		// status line and keeps the admin layer off the Data layer per the
		// project's dependency rules. The active range count is deliberately
		// not shown here: it is resolved (and plausibility-checked) in
		// CloudflareIpRanges::all(), so echoing a raw cache count could both
		// crash on a malformed option and disagree with what is actually
		// trusted.
		$cache      = get_option( 'jtzl_bm_cloudflare_ranges', array() );
		$fetched_at = ( is_array( $cache ) && ! empty( $cache['fetched_at'] ) && is_string( $cache['fetched_at'] ) )
			? $cache['fetched_at']
			: '';

		if ( '' !== $fetched_at ) {
			$status = sprintf(
				/* translators: %s: date the ranges were last refreshed */
				__( 'Cloudflare\'s official IP ranges are loaded automatically and refreshed weekly. Last refreshed %s.', 'jtzl-bot-maze' ),
				esc_html( wp_date( (string) get_option( 'date_format' ), strtotime( $fetched_at ) ) )
			);
		} else {
			$status = __( 'Cloudflare\'s official IP ranges are loaded automatically and refreshed weekly.', 'jtzl-bot-maze' );
		}

		return $description . '<br><em>' . $status . '</em>';
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
			'jtzl_bm_trap_base_path',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_base_path' ),
				'default'           => 'jbm',
			),
			__( 'Trap Base Path', 'jtzl-bot-maze' ),
			array( $page, 'render_text_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bm_trap_base_path',
				'description' => __( 'URL path prefix for trap pages (e.g., "jbm" makes URLs like /jbm/page-slug). Changing this requires a permalink refresh.', 'jtzl-bot-maze' ),
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
		echo '<p>' . esc_html__( 'Enable or disable the plugin and configure the base URL path for trap pages.', 'jtzl-bot-maze' ) . '</p>';
	}
}
