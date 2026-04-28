<?php
/**
 * Maintenance Settings Section Registrar
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
 * Registers the Maintenance settings section.
 *
 * Owns the retention window and the "Regenerate Trap Maze" action button.
 *
 * @since 1.0.0
 */
class MaintenanceSectionRegistrar extends AbstractSectionRegistrar {

	private const SECTION_ID = 'jtzl_bm_maintenance_settings';

	/**
	 * Register the Maintenance section and its fields.
	 *
	 * @param SettingsPage $page Settings page providing the field renderers.
	 * @return void
	 * @since 1.0.0
	 */
	public function register( SettingsPage $page ): void {
		$this->add_section( self::SECTION_ID, __( 'Maintenance', 'jtzl-bot-maze' ) );

		$this->register_field(
			'jtzl_bm_retention_days',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bm_retention_days' ),
				'default'           => 30,
			),
			__( 'Evidence Retention (Days)', 'jtzl-bot-maze' ),
			array( $page, 'render_number_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bm_retention_days',
				'description' => __( 'Number of days to retain request logs, trap visits, and bot scores, including blocked identities. Older entries are automatically pruned daily, so shorter retention windows also shorten how long blocked bots stay blocked.', 'jtzl-bot-maze' ),
				'min'         => 1,
				'max'         => 365,
			)
		);

		// Regenerate button is a pure action field — no stored option.
		add_settings_field(
			'jtzl_bm_regenerate_trap_maze',
			__( 'Regenerate Trap Maze', 'jtzl-bot-maze' ),
			array( $this, 'render_regenerate_button' ),
			SettingsPage::PAGE_SLUG,
			self::SECTION_ID
		);
	}

	/**
	 * Render the Maintenance section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_section_description(): void {
		echo '<p>' . esc_html__( 'Data retention and trap maze management.', 'jtzl-bot-maze' ) . '</p>';
	}

	/**
	 * Render the "Regenerate Trap Maze" action button and its inline AJAX handler.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_regenerate_button(): void {
		printf(
			'<button type="button" id="jtzl-bm-regenerate-btn" class="button button-secondary">%s</button>',
			esc_html__( 'Regenerate Trap Maze', 'jtzl-bot-maze' )
		);
		echo '<span id="jtzl-bm-regenerate-status" style="margin-left: 10px;"></span>';
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Delete all existing trap pages and generate a fresh set. This does not affect bot scores or visit logs.', 'jtzl-bot-maze' )
		);

		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var btn = document.getElementById('jtzl-bm-regenerate-btn');
			var status = document.getElementById('jtzl-bm-regenerate-status');
			if (!btn) return;

			btn.addEventListener('click', function() {
				if (!confirm(<?php echo wp_json_encode( __( 'Are you sure you want to regenerate all trap pages?', 'jtzl-bot-maze' ) ); ?>)) {
					return;
				}

				btn.disabled = true;
				status.textContent = <?php echo wp_json_encode( __( 'Regenerating...', 'jtzl-bot-maze' ) ); ?>;

				var data = new FormData();
				data.append('action', 'jtzl_bm_regenerate');
				data.append('nonce', <?php echo wp_json_encode( wp_create_nonce( 'jtzl_bm_admin_action' ) ); ?>);

				fetch(ajaxurl, { method: 'POST', body: data })
					.then(function(r) { return r.json(); })
					.then(function(response) {
						if (response.success) {
							status.textContent = response.data.message;
							status.style.color = '#00a32a';
						} else {
							status.textContent = response.data.message || <?php echo wp_json_encode( __( 'An error occurred.', 'jtzl-bot-maze' ) ); ?>;
							status.style.color = '#d63638';
						}
						btn.disabled = false;
					})
					.catch(function() {
						status.textContent = <?php echo wp_json_encode( __( 'Request failed.', 'jtzl-bot-maze' ) ); ?>;
						status.style.color = '#d63638';
						btn.disabled = false;
					});
			});
		});
		</script>
		<?php
	}
}
