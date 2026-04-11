<?php
/**
 * Geographic Tracking Settings Section Registrar
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
use JTZL\Bot_Be_Gone\Service\MaxMindDatabaseManager;

/**
 * Registers the Geographic Tracking settings section.
 *
 * Owns the GeoIP provider radio group and the MaxMind license/status fields.
 *
 * @since 1.0.0
 */
class GeoSectionRegistrar extends AbstractSectionRegistrar {

	private const SECTION_ID = 'jtzl_bbg_geo_settings';

	/**
	 * Register the Geographic Tracking section and its fields.
	 *
	 * @param SettingsPage $page Settings page providing the field renderers.
	 * @return void
	 * @since 1.0.0
	 */
	public function register( SettingsPage $page ): void {
		$this->add_section( self::SECTION_ID, __( 'Geographic Tracking', 'jtzl-bot-be-gone' ) );

		$this->register_field(
			'jtzl_bbg_geo_provider',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_geo_provider' ),
				'default'           => 'none',
			),
			__( 'GeoIP Provider', 'jtzl-bot-be-gone' ),
			array( $this, 'render_geo_provider_field' ),
			self::SECTION_ID
		);

		$this->register_field(
			'jtzl_bbg_maxmind_license_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_maxmind_license_key' ),
				'default'           => '',
			),
			__( 'MaxMind License Key', 'jtzl-bot-be-gone' ),
			array( $this, 'render_maxmind_fields' ),
			self::SECTION_ID
		);
	}

	/**
	 * Render the Geographic Tracking section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_section_description(): void {
		echo '<p>' . esc_html__( 'Optional geographic tracking resolves visitor IP addresses to country codes for the bot activity heat map. Choose a provider below. The local database option keeps all data on your server; the external API option is simpler to set up but sends IP data to a third party.', 'jtzl-bot-be-gone' ) . '</p>';
	}

	/**
	 * Render the GeoIP provider radio group.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_geo_provider_field(): void {
		$current = get_option( 'jtzl_bbg_geo_provider', 'none' );

		$providers = array(
			'none'    => array(
				'label' => __( 'Disabled', 'jtzl-bot-be-gone' ),
				'desc'  => __( 'No geographic tracking. No visitor data is sent to any external service.', 'jtzl-bot-be-gone' ),
			),
			'maxmind' => array(
				'label' => __( 'MaxMind GeoLite2 (Local Database) — Recommended', 'jtzl-bot-be-gone' ),
				'desc'  => __( 'Performs lookups locally using a downloaded database file. No visitor data leaves your server. Requires a free MaxMind license key. Database updates weekly (~60 MB download).', 'jtzl-bot-be-gone' ),
			),
			'ip_api'  => array(
				'label' => __( 'ip-api.com (External API)', 'jtzl-bot-be-gone' ),
				'desc'  => __( 'Sends each visitor\'s IP address to ip-api.com over unencrypted HTTP. Simple to set up, but transmits IP data to a third-party server without TLS. Free tier limited to 45 requests/minute (cached locally).', 'jtzl-bot-be-gone' ),
			),
		);

		echo '<fieldset>';
		foreach ( $providers as $value => $provider ) {
			printf(
				'<label style="display: block; margin-bottom: 12px;"><input type="radio" name="jtzl_bbg_geo_provider" value="%s" %s /> <strong>%s</strong><br /><span class="description" style="margin-left: 24px; display: inline-block;">%s</span></label>',
				esc_attr( $value ),
				checked( $current, $value, false ),
				esc_html( $provider['label'] ),
				esc_html( $provider['desc'] )
			);
		}
		echo '</fieldset>';

		// Privacy warnings.
		$ip_api_warning = __( 'Privacy notice: Visitor IP addresses will be sent to ip-api.com over unencrypted HTTP. This means visitor IPs are transmitted to a third-party server without encryption. If your site must comply with GDPR or similar privacy regulations, use the MaxMind local database option instead.', 'jtzl-bot-be-gone' );
		$maxmind_notice = __( 'All lookups are performed locally on your server. No visitor IP data is shared with any external service. This is the recommended option for GDPR compliance.', 'jtzl-bot-be-gone' );

		printf(
			'<div id="jtzl-bbg-geo-warning-ip_api" class="notice notice-warning inline" style="display: none; margin-top: 10px; padding: 8px 12px;"><p>%s</p></div>',
			esc_html( $ip_api_warning )
		);
		printf(
			'<div id="jtzl-bbg-geo-notice-maxmind" class="notice notice-success inline" style="display: none; margin-top: 10px; padding: 8px 12px;"><p>%s</p></div>',
			esc_html( $maxmind_notice )
		);

		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var radios = document.querySelectorAll('input[name="jtzl_bbg_geo_provider"]');
			var maxmindRow = document.getElementById('jtzl_bbg_maxmind_license_key');
			if (maxmindRow) maxmindRow = maxmindRow.closest('tr');
			var warningIpApi = document.getElementById('jtzl-bbg-geo-warning-ip_api');
			var noticeMaxmind = document.getElementById('jtzl-bbg-geo-notice-maxmind');

			function toggleFields() {
				var selected = document.querySelector('input[name="jtzl_bbg_geo_provider"]:checked');
				if (!selected) return;
				var val = selected.value;

				if (maxmindRow) maxmindRow.style.display = val === 'maxmind' ? '' : 'none';
				if (warningIpApi) warningIpApi.style.display = val === 'ip_api' ? '' : 'none';
				if (noticeMaxmind) noticeMaxmind.style.display = val === 'maxmind' ? '' : 'none';
			}

			radios.forEach(function(r) { r.addEventListener('change', toggleFields); });
			toggleFields();
		});
		</script>
		<?php
	}

	/**
	 * Render MaxMind license key and database status fields.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_maxmind_fields(): void {
		$license_key  = get_option( 'jtzl_bbg_maxmind_license_key', '' );
		$last_updated = get_option( 'jtzl_bbg_maxmind_last_updated', '' );
		$db_path      = MaxMindDatabaseManager::get_database_path();
		$db_exists    = file_exists( $db_path );

		$this->render_license_key_input( $license_key );
		$this->render_database_status( $db_exists, $last_updated );
		$this->render_download_button();
	}

	/**
	 * Render the masked license key input and "clear stored key" checkbox.
	 *
	 * @param string $license_key Current stored key.
	 * @return void
	 * @since 1.0.0
	 */
	private function render_license_key_input( string $license_key ): void {
		$display_key = '' !== $license_key
			? str_repeat( '•', max( 0, strlen( $license_key ) - 4 ) ) . substr( $license_key, -4 )
			: '';

		$placeholder = '' !== $license_key
			? $display_key
			: __( 'Enter your MaxMind license key', 'jtzl-bot-be-gone' );

		printf(
			'<input type="text" id="jtzl_bbg_maxmind_license_key" name="jtzl_bbg_maxmind_license_key" value="" class="regular-text" placeholder="%s" autocomplete="off" />',
			esc_attr( $placeholder )
		);

		if ( '' !== $license_key ) {
			printf(
				' <label><input type="checkbox" name="jtzl_bbg_clear_maxmind_key" value="1" /> %s</label>',
				esc_html__( 'Clear stored key', 'jtzl-bot-be-gone' )
			);
		}

		printf(
			'<p class="description">%s <a href="https://www.maxmind.com/en/geolite2/signup" target="_blank" rel="noopener noreferrer">%s</a></p>',
			esc_html__( 'Get a free license key from', 'jtzl-bot-be-gone' ),
			esc_html__( 'maxmind.com', 'jtzl-bot-be-gone' )
		);
	}

	/**
	 * Render the GeoLite2 database installation status line.
	 *
	 * @param bool   $db_exists    Whether the .mmdb file is present.
	 * @param string $last_updated Stored timestamp for the last successful download.
	 * @return void
	 * @since 1.0.0
	 */
	private function render_database_status( bool $db_exists, string $last_updated ): void {
		echo '<div style="margin-top: 10px;">';
		if ( $db_exists && '' !== $last_updated ) {
			printf(
				'<span style="color: #00a32a;">&#10003;</span> %s <strong>%s</strong>',
				esc_html__( 'GeoLite2-Country database installed. Last updated:', 'jtzl-bot-be-gone' ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_updated ) ) )
			);
		} elseif ( $db_exists ) {
			printf(
				'<span style="color: #00a32a;">&#10003;</span> %s',
				esc_html__( 'GeoLite2-Country database installed.', 'jtzl-bot-be-gone' )
			);
		} else {
			printf(
				'<span style="color: #dba617;">&#9888;</span> %s',
				esc_html__( 'Database not yet downloaded. Save your license key, then click "Download Database" below.', 'jtzl-bot-be-gone' )
			);
		}
		echo '</div>';
	}

	/**
	 * Render the "Download Database" button and its inline AJAX handler.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function render_download_button(): void {
		printf(
			'<div style="margin-top: 10px;"><button type="button" id="jtzl-bbg-maxmind-download-btn" class="button button-secondary">%s</button>',
			esc_html__( 'Download Database', 'jtzl-bot-be-gone' )
		);
		echo '<span id="jtzl-bbg-maxmind-download-status" style="margin-left: 10px;"></span></div>';

		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var btn = document.getElementById('jtzl-bbg-maxmind-download-btn');
			var status = document.getElementById('jtzl-bbg-maxmind-download-status');
			if (!btn) return;

			btn.addEventListener('click', function() {
				btn.disabled = true;
				status.textContent = <?php echo wp_json_encode( __( 'Downloading...', 'jtzl-bot-be-gone' ) ); ?>;
				status.style.color = '';

				var data = new FormData();
				data.append('action', 'jtzl_bbg_maxmind_download');
				data.append('nonce', <?php echo wp_json_encode( wp_create_nonce( 'jtzl_bbg_admin_action' ) ); ?>);

				fetch(ajaxurl, { method: 'POST', body: data })
					.then(function(r) { return r.json(); })
					.then(function(response) {
						if (response.success) {
							status.textContent = response.data.message;
							status.style.color = '#00a32a';
						} else {
							status.textContent = response.data.message || <?php echo wp_json_encode( __( 'Download failed.', 'jtzl-bot-be-gone' ) ); ?>;
							status.style.color = '#d63638';
						}
						btn.disabled = false;
					})
					.catch(function() {
						status.textContent = <?php echo wp_json_encode( __( 'Request failed.', 'jtzl-bot-be-gone' ) ); ?>;
						status.style.color = '#d63638';
						btn.disabled = false;
					});
			});
		});
		</script>
		<?php
	}
}
