<?php
/**
 * Plugin Options Management
 *
 * Handles default plugin options and settings.
 *
 * @package JTZL\Bot_Be_Gone
 * @since 1.0.0
 */

namespace JTZL\Bot_Be_Gone\Database;

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
	}

	/**
	 * Remove all plugin options.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function remove_all(): void {
		$options = array_keys( self::get_default_options() );

		$options[] = 'jtzl_bbg_plugin_version';
		$options[] = 'jtzl_bbg_flush_rewrite_rules_flag';

		foreach ( $options as $option_name ) {
			delete_option( $option_name );
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
			'jtzl_bbg_enabled'                => true,
			'jtzl_bbg_trap_base_path'         => 'bbg',
			'jtzl_bbg_trusted_proxy_header'   => '',
			'jtzl_bbg_trusted_proxy_ips'      => '',

			// Trap Links.
			'jtzl_bbg_links_per_page'         => 3,
			'jtzl_bbg_injection_method'       => 'content', // content, footer, both.

			// Trap Maze.
			'jtzl_bbg_max_depth'              => 5,
			'jtzl_bbg_links_per_trap_page'    => 5,
			'jtzl_bbg_initial_page_count'     => 20,
			'jtzl_bbg_max_trap_pages'         => 500,

			// Bot Scoring.
			'jtzl_bbg_points_per_visit'       => 25,
			'jtzl_bbg_depth_bonus'            => 15,
			'jtzl_bbg_block_threshold'        => 100,
			'jtzl_bbg_blocking_behavior'      => 'block', // block, tarpit_light, tarpit.
			'jtzl_bbg_tarpit_delay_ms'        => 1000,
			'jtzl_bbg_track_blocked_activity' => false,

			// Geographic Tracking.
			'jtzl_bbg_geo_provider'           => 'none', // none, ip_api, maxmind.
			'jtzl_bbg_maxmind_license_key'    => '',
			'jtzl_bbg_maxmind_last_updated'   => '',

			// Maintenance.
			'jtzl_bbg_retention_days'         => 30,

			// robots.txt.
			'jtzl_bbg_robots_txt_enabled'     => true,
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
