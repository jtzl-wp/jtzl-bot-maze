<?php
/**
 * Plugin Options Management
 *
 * Handles default plugin options and settings.
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze\Database;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Options Manager
 *
 * Handles plugin configuration options storage and retrieval.
 *
 * @since 1.0.0
 */
class Options {
	/** Defaults which must not be loaded on every request. */
	private const NON_AUTOLOAD_DEFAULTS = array(
		'jtzl_bm_abuseipdb_api_key' => '',
	);

	/**
	 * Apply default plugin options.
	 *
	 * Only sets options that do not already exist.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function apply_defaults(): void {
		$defaults = self::get_default_options();

		foreach ( $defaults as $option_name => $default_value ) {
			if ( null === get_option( $option_name, null ) ) {
				add_option( $option_name, $default_value );
			}
		}

		foreach ( self::NON_AUTOLOAD_DEFAULTS as $option_name => $default_value ) {
			if ( null === get_option( $option_name, null ) ) {
				add_option( $option_name, $default_value, '', false );
			}
		}
	}

	/**
	 * Remove all plugin options.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function remove_all(): void {
		$options = array_keys( self::get_default_options() );

		$options[] = 'jtzl_bm_plugin_version';
		$options[] = 'jtzl_bm_flush_rewrite_rules_flag';
		$options[] = 'jtzl_bm_cloudflare_ranges';
		$options[] = 'jtzl_bm_abuseipdb_api_key';
		$options[] = 'jtzl_bm_abuseipdb_last_success_at';
		$options[] = 'jtzl_bm_abuseipdb_last_error';
		$options[] = 'jtzl_bm_abuseipdb_last_error_at';
		$options[] = 'jtzl_bm_abuseipdb_pause_until';

		foreach ( $options as $option_name ) {
			delete_option( $option_name );
			// Also remove legacy counterparts from prior renames so upgraders
			// don't leave orphaned wp_options rows.
			delete_option( str_replace( 'jtzl_bm_', 'jtzl_bl_', $option_name ) );
			delete_option( str_replace( 'jtzl_bm_', 'jtzl_bbg_', $option_name ) );
		}
	}

	/**
	 * Set default plugin options (static convenience wrapper).
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function set_defaults(): void {
		$instance = new self();
		$instance->apply_defaults();
	}

	/**
	 * Get default option values.
	 *
	 * @return array Default options.
	 * @since 1.0.0
	 */
	public static function get_default_options(): array {
		return array(
			// General.
			'jtzl_bm_enabled'                => true,
			'jtzl_bm_trap_base_path'         => 'jbm',
			'jtzl_bm_trusted_proxy_header'   => '',
			'jtzl_bm_trusted_proxy_ips'      => '',

			// Trap Links.
			'jtzl_bm_links_per_page'         => 3,
			'jtzl_bm_injection_method'       => 'content', // content, footer, both.

			// Trap Maze.
			'jtzl_bm_max_depth'              => 5,
			'jtzl_bm_links_per_trap_page'    => 5,
			'jtzl_bm_initial_page_count'     => 20,
			'jtzl_bm_max_trap_pages'         => 500,

			// Bot Scoring.
			'jtzl_bm_points_per_visit'       => 25,
			'jtzl_bm_depth_bonus'            => 15,
			'jtzl_bm_block_threshold'        => 100,
			'jtzl_bm_blocking_behavior'      => 'block', // block, tarpit_light, tarpit.
			'jtzl_bm_tarpit_delay_ms'        => 1000,
			'jtzl_bm_track_blocked_activity' => false,

			// Geographic Tracking.
			'jtzl_bm_geo_provider'           => 'none', // none, ip_api, maxmind.
			'jtzl_bm_maxmind_license_key'    => '',
			'jtzl_bm_maxmind_last_updated'   => '',

			// Maintenance.
			'jtzl_bm_retention_days'         => 30,
			'jtzl_bm_abuseipdb_enabled'      => false,

			// robots.txt.
			'jtzl_bm_robots_txt_enabled'     => true,
		);
	}

	/**
	 * Delete all plugin options (static convenience wrapper).
	 *
	 * This should only be called on plugin uninstall, not deactivation.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function delete_all(): void {
		$instance = new self();
		$instance->remove_all();
	}
}
