<?php
/**
 * Asset Manager Service
 *
 * Centralizes admin asset enqueuing logic with manifest-based content-hashed loading.
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Asset Manager
 *
 * Manages enqueuing of admin assets with content-hashed filenames.
 *
 * @since 1.0.0
 */
class AssetManager {

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $plugin_dir;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $plugin_url;

	/**
	 * Plugin version.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $version;

	/**
	 * Whether admin assets have already been enqueued.
	 *
	 * @var bool
	 * @since 1.0.0
	 */
	private bool $assets_enqueued = false;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_dir Plugin directory path.
	 * @param string $plugin_url Plugin URL.
	 * @param string $version    Plugin version.
	 * @since 1.0.0
	 */
	public function __construct( string $plugin_dir, string $plugin_url, string $version ) {
		$this->plugin_dir = $plugin_dir;
		$this->plugin_url = $plugin_url;
		$this->version    = $version;
	}

	/**
	 * Enqueue admin assets (CSS and JavaScript).
	 *
	 * This method is idempotent - calling it multiple times will only enqueue assets once.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function enqueue_admin_assets(): void {
		if ( $this->assets_enqueued ) {
			return;
		}

		$this->enqueue_admin_script();
		$this->enqueue_admin_styles();

		$this->assets_enqueued = true;
	}

	/**
	 * Enqueue admin JavaScript from manifest.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function enqueue_admin_script(): void {
		$manifest_path = $this->plugin_dir . 'build/asset-manifest.json';

		if ( ! file_exists( $manifest_path ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read is appropriate here.
		$manifest = json_decode( file_get_contents( $manifest_path ), true );

		if ( ! is_array( $manifest ) || ! isset( $manifest['jtzl-bm-admin-analytics.js'] ) ) {
			return;
		}

		wp_enqueue_script(
			'jtzl-bm-admin-analytics',
			$this->plugin_url . 'build/' . $manifest['jtzl-bm-admin-analytics.js'],
			array(),
			$this->version,
			true
		);

		add_filter( 'script_loader_tag', array( $this, 'add_module_type_attribute' ), 10, 2 );

		wp_localize_script(
			'jtzl-bm-admin-analytics',
			'jtzlBbgAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'jtzl_bm_admin_action' ),
			)
		);
	}

	/**
	 * Enqueue map page assets (shared CSS and map-specific JavaScript).
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function enqueue_map_assets(): void {
		$this->enqueue_admin_styles();
		$this->enqueue_map_script();
	}

	/**
	 * Add module type attribute to admin script tag.
	 *
	 * @param string $tag    The script tag HTML.
	 * @param string $handle The script handle.
	 * @return string Modified script tag.
	 * @since 1.0.0
	 */
	public function add_module_type_attribute( string $tag, string $handle ): string {
		$module_handles = array( 'jtzl-bm-admin-analytics', 'jtzl-bm-admin-map' );
		if ( in_array( $handle, $module_handles, true ) && false === strpos( $tag, ' type=' ) ) {
			$tag = preg_replace( '/(<script\b)(?=[^>]*\ssrc=)/', '$1 type="module"', $tag, 1 );
		}
		return $tag;
	}

	/**
	 * Enqueue map page JavaScript from manifest.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function enqueue_map_script(): void {
		$manifest_path = $this->plugin_dir . 'build/asset-manifest.json';

		if ( ! file_exists( $manifest_path ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read is appropriate here.
		$manifest = json_decode( file_get_contents( $manifest_path ), true );

		if ( ! is_array( $manifest ) || ! isset( $manifest['jtzl-bm-admin-map.js'] ) ) {
			return;
		}

		wp_enqueue_script(
			'jtzl-bm-admin-map',
			$this->plugin_url . 'build/' . $manifest['jtzl-bm-admin-map.js'],
			array(),
			$this->version,
			true
		);

		add_filter( 'script_loader_tag', array( $this, 'add_module_type_attribute' ), 10, 2 );

		wp_localize_script(
			'jtzl-bm-admin-map',
			'jtzlBbgMap',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'jtzl_bm_admin_action' ),
			)
		);

		// Enqueue map CSS from manifest.
		if ( isset( $manifest['jtzl-bm-admin-map.css'] ) ) {
			wp_enqueue_style(
				'jtzl-bm-admin-map-style',
				$this->plugin_url . 'build/' . $manifest['jtzl-bm-admin-map.css'],
				array(),
				$this->version
			);
		}
	}

	/**
	 * Enqueue admin CSS styles.
	 *
	 * Tries hashed version first, then falls back to non-hashed version.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function enqueue_admin_styles(): void {
		$css_files = glob( $this->plugin_dir . 'build/jtzl-bm-admin.*.css' );

		if ( ! empty( $css_files ) ) {
			$css_file = basename( $css_files[0] );
			wp_enqueue_style(
				'jtzl-bm-admin-style',
				$this->plugin_url . 'build/' . $css_file,
				array(),
				$this->version
			);
			return;
		}

		if ( file_exists( $this->plugin_dir . 'build/jtzl-bm-admin.css' ) ) {
			wp_enqueue_style(
				'jtzl-bm-admin-style',
				$this->plugin_url . 'build/jtzl-bm-admin.css',
				array(),
				$this->version
			);
		}
	}
}
