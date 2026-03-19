<?php
/**
 * Asset Manager Service
 *
 * Centralizes admin asset enqueuing logic with manifest-based content-hashed loading.
 *
 * @package Labyrinth
 */

namespace Labyrinth\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Asset Manager
 *
 * Manages enqueuing of admin assets with content-hashed filenames.
 */
class AssetManager {

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private string $plugin_url;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Whether admin assets have already been enqueued.
	 *
	 * @var bool
	 */
	private bool $assets_enqueued = false;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_dir Plugin directory path.
	 * @param string $plugin_url Plugin URL.
	 * @param string $version    Plugin version.
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
	 */
	private function enqueue_admin_script(): void {
		$manifest_path = $this->plugin_dir . 'build/asset-manifest.json';

		if ( ! file_exists( $manifest_path ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read is appropriate here.
		$manifest = json_decode( file_get_contents( $manifest_path ), true );

		if ( ! is_array( $manifest ) || ! isset( $manifest['labyrinth-admin-analytics.js'] ) ) {
			return;
		}

		wp_enqueue_script(
			'lby-admin-analytics',
			$this->plugin_url . 'build/' . $manifest['labyrinth-admin-analytics.js'],
			array(),
			$this->version,
			true
		);

		add_filter( 'script_loader_tag', array( $this, 'add_module_type_attribute' ), 10, 2 );

		wp_localize_script(
			'lby-admin-analytics',
			'lbyAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'lby_admin_action' ),
			)
		);
	}

	/**
	 * Add module type attribute to admin script tag.
	 *
	 * @param string $tag    The script tag HTML.
	 * @param string $handle The script handle.
	 * @return string Modified script tag.
	 */
	public function add_module_type_attribute( string $tag, string $handle ): string {
		if ( 'lby-admin-analytics' === $handle ) {
			remove_filter( 'script_loader_tag', array( $this, 'add_module_type_attribute' ), 10 );
			$tag = preg_replace( '/(<script\b)(?=[^>]*\ssrc=)/', '$1 type="module"', $tag, 1 );
		}
		return $tag;
	}

	/**
	 * Enqueue admin CSS styles.
	 *
	 * Tries hashed version first, then falls back to non-hashed version.
	 *
	 * @return void
	 */
	private function enqueue_admin_styles(): void {
		$css_files = glob( $this->plugin_dir . 'build/labyrinth-admin.*.css' );

		if ( ! empty( $css_files ) ) {
			$css_file = basename( $css_files[0] );
			wp_enqueue_style(
				'lby-admin-style',
				$this->plugin_url . 'build/' . $css_file,
				array(),
				$this->version
			);
			return;
		}

		if ( file_exists( $this->plugin_dir . 'build/labyrinth-admin.css' ) ) {
			wp_enqueue_style(
				'lby-admin-style',
				$this->plugin_url . 'build/labyrinth-admin.css',
				array(),
				$this->version
			);
		}
	}
}
