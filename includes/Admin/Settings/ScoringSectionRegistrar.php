<?php
/**
 * Scoring Settings Section Registrar
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
 * Registers the Bot Scoring settings section.
 *
 * Owns points-per-visit, depth bonus, block threshold, blocking behavior,
 * tarpit delay, and the blocked-activity tracking toggle.
 *
 * @since 1.0.0
 */
class ScoringSectionRegistrar extends AbstractSectionRegistrar {

	private const SECTION_ID = 'jtzl_bbg_scoring_settings';

	/**
	 * Register the Bot Scoring section and its fields.
	 *
	 * @param SettingsPage $page Settings page providing the field renderers.
	 * @return void
	 * @since 1.0.0
	 */
	public function register( SettingsPage $page ): void {
		$this->add_section( self::SECTION_ID, __( 'Bot Scoring', 'jtzl-bot-be-gone' ) );
		$this->register_score_weight_fields( $page );
		$this->register_response_fields( $page );
	}

	/**
	 * Register the point-weight fields that drive the score total.
	 *
	 * @param SettingsPage $page Settings page for render callbacks.
	 * @return void
	 * @since 1.0.0
	 */
	private function register_score_weight_fields( SettingsPage $page ): void {
		$this->register_field(
			'jtzl_bbg_points_per_visit',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bbg_points_per_visit' ),
				'default'           => 25,
			),
			__( 'Points Per Visit', 'jtzl-bot-be-gone' ),
			array( $page, 'render_number_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bbg_points_per_visit',
				'description' => __( 'Base suspicion points added for each trap page visit.', 'jtzl-bot-be-gone' ),
				'min'         => 1,
			)
		);

		$this->register_field(
			'jtzl_bbg_depth_bonus',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bbg_depth_bonus', true ),
				'default'           => 15,
			),
			__( 'Depth Bonus', 'jtzl-bot-be-gone' ),
			array( $page, 'render_number_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bbg_depth_bonus',
				'description' => __( 'Additional points multiplied by depth level. Deeper traversal = stronger evidence.', 'jtzl-bot-be-gone' ),
				'min'         => 0,
			)
		);

		$this->register_field(
			'jtzl_bbg_block_threshold',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bbg_block_threshold' ),
				'default'           => 100,
			),
			__( 'Block Threshold', 'jtzl-bot-be-gone' ),
			array( $page, 'render_number_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bbg_block_threshold',
				'description' => __( 'Score at which a visitor is automatically flagged as a bot. Lower = more aggressive.', 'jtzl-bot-be-gone' ),
				'min'         => 10,
			)
		);
	}

	/**
	 * Register blocking-behavior fields plus the "track blocked activity" toggle.
	 *
	 * @param SettingsPage $page Settings page for render callbacks.
	 * @return void
	 * @since 1.0.0
	 */
	private function register_response_fields( SettingsPage $page ): void {
		$this->register_field(
			'jtzl_bbg_blocking_behavior',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_blocking_behavior' ),
				'default'           => 'block',
			),
			__( 'Blocking Behavior', 'jtzl-bot-be-gone' ),
			array( $page, 'render_select_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bbg_blocking_behavior',
				'description' => __(
					'How to respond after a visitor is already flagged as blocked. Every option still loads WordPress to run this check, so very high volumes of blocked traffic can stress PHP workers. <strong>Block</strong> (default) — HTTP 403 with a short message; lowest cost and recommended for most sites. <strong>Light tarpit</strong> — HTTP 200 serving a random existing trap page with links to keep bots crawling; no new child pages are generated for blocked traffic. <strong>Full tarpit</strong> — same decoy page plus a configurable delay (see below); holds each PHP worker longer to waste bot time.',
					'jtzl-bot-be-gone'
				),
				'options'     => array(
					'block'        => __( 'Block — 403 Forbidden (default, lowest server cost)', 'jtzl-bot-be-gone' ),
					'tarpit_light' => __( 'Light tarpit — serve existing decoy page, no delay', 'jtzl-bot-be-gone' ),
					'tarpit'       => __( 'Full tarpit — configurable delay + same decoy page', 'jtzl-bot-be-gone' ),
				),
			)
		);

		$this->register_field(
			'jtzl_bbg_tarpit_delay_ms',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bbg_tarpit_delay_ms' ),
				'default'           => 1000,
			),
			__( 'Full Tarpit Delay (ms)', 'jtzl-bot-be-gone' ),
			array( $page, 'render_number_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bbg_tarpit_delay_ms',
				'description' => __( 'Delay in milliseconds before responding to blocked visitors when using the full tarpit. Each request ties up a PHP worker for this duration. Default: 1000 (1 second).', 'jtzl-bot-be-gone' ),
				'min'         => 100,
				'max'         => 5000,
				'step'        => 100,
			)
		);

		$this->register_field(
			'jtzl_bbg_track_blocked_activity',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_checkbox' ),
				'default'           => false,
			),
			__( 'Track Blocked Bot Activity', 'jtzl-bot-be-gone' ),
			array( $page, 'render_checkbox_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bbg_track_blocked_activity',
				'description' => __( 'Continue recording visits from blocked bots (updates last-seen time, increments visit count, and logs the request). Useful for monitoring persistent bots, but increases database writes. Default: off.', 'jtzl-bot-be-gone' ),
			)
		);
	}

	/**
	 * Render the Bot Scoring section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_section_description(): void {
		echo '<p>' . esc_html__( 'Configure the scoring algorithm that determines when a visitor is flagged as a bot.', 'jtzl-bot-be-gone' ) . '</p>';
	}
}
