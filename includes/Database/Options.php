<?php
/**
 * Plugin Options Management
 *
 * Handles default plugin options and settings.
 *
 * @package Labyrinth
 * @since 1.0.0
 */

namespace Labyrinth\Database;

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

		$options[] = 'lby_plugin_version';
		$options[] = 'lby_flush_rewrite_rules_flag';

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
			'lby_enabled'                => true,
			'lby_trap_base_path'         => 'lby',
			'lby_trusted_proxy_header'   => '',
			'lby_trusted_proxy_ips'      => '',

			// Trap Links.
			'lby_links_per_page'         => 3,
			'lby_injection_method'       => 'content', // content, footer, both.

			// Labyrinth.
			'lby_max_depth'              => 5,
			'lby_links_per_trap_page'    => 5,
			'lby_initial_page_count'     => 20,
			'lby_max_trap_pages'         => 500,

			// Bot Scoring.
			'lby_points_per_visit'       => 25,
			'lby_depth_bonus'            => 15,
			'lby_block_threshold'        => 100,
			'lby_blocking_behavior'      => 'block', // block, tarpit_light, tarpit.
			'lby_tarpit_delay_ms'        => 1000,
			'lby_track_blocked_activity' => false,

			// Geographic Tracking.
			'lby_geo_provider'           => 'none', // none, ip_api, maxmind.
			'lby_maxmind_license_key'    => '',
			'lby_maxmind_last_updated'   => '',

			// Maintenance.
			'lby_retention_days'         => 30,

			// robots.txt.
			'lby_robots_txt_enabled'     => true,
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
