<?php
/**
 * AbuseIPDB reporting settings section.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Admin\Settings;

use JTZL\Bot_Maze\Admin\SettingsPage;
use JTZL\Bot_Maze\Admin\SettingsSanitizer;
use JTZL\Bot_Maze\Service\AbuseIpDbStatus;

/** Register the administrator-owned AbuseIPDB setup and aggregate status UI. */
final class AbuseIpDbSectionRegistrar extends AbstractSectionRegistrar {

	private const SECTION_ID = 'jtzl_bm_abuseipdb_settings';

	/**
	 * Constructor.
	 *
	 * @param AbuseIpDbStatus $status Aggregate reporting status.
	 */
	public function __construct( private AbuseIpDbStatus $status ) {}

	/**
	 * Register settings and status fields.
	 *
	 * @param SettingsPage $page Settings page providing field renderers.
	 */
	public function register( SettingsPage $page ): void {
		$this->add_section( self::SECTION_ID, __( 'AbuseIPDB Reporting', 'jtzl-bot-maze' ) );
		$this->register_field(
			'jtzl_bm_abuseipdb_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_abuseipdb_api_key' ),
				'default'           => '',
			),
			__( 'AbuseIPDB API Key', 'jtzl-bot-maze' ),
			array( $this, 'render_api_key_field' ),
			self::SECTION_ID,
			array( 'label_for' => 'jtzl_bm_abuseipdb_api_key' )
		);
		$this->register_field(
			'jtzl_bm_abuseipdb_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_checkbox' ),
				'default'           => false,
			),
			__( 'Enable Automatic Reporting', 'jtzl-bot-maze' ),
			array( $this, 'render_enabled_field' ),
			self::SECTION_ID,
			array( 'label_for' => 'jtzl_bm_abuseipdb_enabled' )
		);
		add_settings_field(
			'jtzl_bm_abuseipdb_status',
			__( 'Delivery Status', 'jtzl-bot-maze' ),
			array( $this, 'render_status_field' ),
			SettingsPage::PAGE_SLUG,
			self::SECTION_ID
		);
	}

	/** Render the account and API-key setup journey. */
	public function render_section_description(): void {
		printf(
			'<p>%s</p><ol><li>%s</li><li>%s</li><li>%s</li><li>%s</li></ol><p>%s <a href="%s">%s</a></p><p><a href="%s">%s</a> | <a href="%s">%s</a></p>',
			esc_html__( 'This integration uses your own AbuseIPDB account and API key; the plugin does not provide a shared key.', 'jtzl-bot-maze' ),
			sprintf( '<a href="%s">%s</a>.', esc_url( 'https://www.abuseipdb.com/register' ), esc_html__( 'Create your own free AbuseIPDB account', 'jtzl-bot-maze' ) ),
			sprintf( '<a href="%s">%s</a> %s', esc_url( 'https://www.abuseipdb.com/account/api' ), esc_html__( 'Generate an API key', 'jtzl-bot-maze' ), esc_html__( 'in the API dashboard.', 'jtzl-bot-maze' ) ),
			esc_html__( 'Paste and save the key below.', 'jtzl-bot-maze' ),
			esc_html__( 'Enable automatic reporting after reviewing the data-sharing consent.', 'jtzl-bot-maze' ),
			esc_html__( 'The free Individual plan includes 1,000 IP checks and reports per day. Your account limit may differ.', 'jtzl-bot-maze' ),
			esc_url( 'https://www.abuseipdb.com/pricing' ),
			esc_html__( 'View current AbuseIPDB pricing and limits.', 'jtzl-bot-maze' ),
			esc_url( 'https://www.abuseipdb.com/terms' ),
			esc_html__( 'AbuseIPDB Terms', 'jtzl-bot-maze' ),
			esc_url( 'https://www.abuseipdb.com/privacy' ),
			esc_html__( 'AbuseIPDB Privacy Policy', 'jtzl-bot-maze' )
		);
	}

	/** Render a write-only credential field and explicit clear control. */
	public function render_api_key_field(): void {
		$api_key     = (string) get_option( 'jtzl_bm_abuseipdb_api_key', '' );
		$placeholder = '' === $api_key
			? __( 'Paste your AbuseIPDB API key', 'jtzl-bot-maze' )
			: ( strlen( $api_key ) <= 4
				? str_repeat( '•', strlen( $api_key ) )
				: str_repeat( '•', strlen( $api_key ) - 4 ) . substr( $api_key, -4 ) );

		printf(
			'<input type="password" id="jtzl_bm_abuseipdb_api_key" name="jtzl_bm_abuseipdb_api_key" value="" placeholder="%s" class="regular-text" autocomplete="off" aria-describedby="jtzl_bm_abuseipdb_api_key_help" />',
			esc_attr( $placeholder )
		);
		echo '<p id="jtzl_bm_abuseipdb_api_key_help" class="description">' . esc_html__( 'Paste a key from your AbuseIPDB API dashboard and save changes. A blank submission keeps the stored key; the full key is never shown here.', 'jtzl-bot-maze' ) . '</p>';
		echo '<p><input type="checkbox" id="jtzl_bm_clear_abuseipdb_key" name="jtzl_bm_clear_abuseipdb_key" value="1" /> <label for="jtzl_bm_clear_abuseipdb_key">' . esc_html__( 'Clear stored key', 'jtzl-bot-maze' ) . '</label></p>';
	}

	/** Render explicit opt-in consent. */
	public function render_enabled_field(): void {
		printf(
			'<input type="checkbox" id="jtzl_bm_abuseipdb_enabled" name="jtzl_bm_abuseipdb_enabled" value="1" %s /> <label for="jtzl_bm_abuseipdb_enabled">%s</label>',
			checked( get_option( 'jtzl_bm_abuseipdb_enabled', false ), true, false ),
			esc_html__( 'I consent to sharing an eligible blocked visitor IP address, category 19, observation time, and a generic explanation with AbuseIPDB for security reporting.', 'jtzl-bot-maze' )
		);
	}

	/** Render sanitized aggregate delivery status. */
	public function render_status_field(): void {
		$snapshot = $this->status->snapshot();
		printf(
			'<p><strong>%s</strong> %s</p><ul><li>%s %d</li><li>%s %s</li><li>%s %s%s</li></ul><p class="description">%s</p><p class="description">%s</p>',
			esc_html__( 'State:', 'jtzl-bot-maze' ),
			esc_html( $snapshot['state'] ),
			esc_html__( 'Pending reports:', 'jtzl-bot-maze' ),
			absint( $snapshot['pending'] ),
			esc_html__( 'Last successful delivery:', 'jtzl-bot-maze' ),
			esc_html( $snapshot['last_success_at'] ? $this->format_timestamp( $snapshot['last_success_at'] ) : __( 'None yet', 'jtzl-bot-maze' ) ),
			esc_html__( 'Latest error:', 'jtzl-bot-maze' ),
			esc_html( $snapshot['last_error'] ? $snapshot['last_error'] : __( 'None', 'jtzl-bot-maze' ) ),
			$snapshot['last_error_at'] ? ' — ' . esc_html( $this->format_timestamp( $snapshot['last_error_at'] ) ) : '',
			esc_html__( 'Active means reporting is configured, not key-validated. AbuseIPDB validates the key when an eligible report is delivered.', 'jtzl-bot-maze' ),
			esc_html__( 'Delivery uses WP-Cron and may be delayed on low-traffic sites.', 'jtzl-bot-maze' )
		);
	}

	/**
	 * Render a stored UTC timestamp in the site's timezone and formats.
	 *
	 * The processor records delivery metadata with gmdate(), so the raw value
	 * would otherwise read hours off for every administrator outside UTC.
	 *
	 * @param string $utc_timestamp UTC SQL timestamp.
	 */
	private function format_timestamp( string $utc_timestamp ): string {
		return (string) wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			(int) strtotime( $utc_timestamp . ' UTC' )
		);
	}
}
