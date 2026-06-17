<?php
/**
 * Robots.txt Settings Section Registrar
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
use JTZL\Bot_Maze\Service\RobotsTxtVerifier;

/**
 * Registers the robots.txt settings section.
 *
 * Controls whether the plugin appends Disallow rules to the virtual
 * robots.txt to steer compliant crawlers away from the trap base path, and
 * surfaces — right under the checkbox — whether that rule is actually live in
 * the robots.txt the site serves.
 *
 * @since 1.0.0
 */
class RobotsSectionRegistrar extends AbstractSectionRegistrar {

	private const SECTION_ID = 'jtzl_bm_robots_settings';

	/**
	 * Verifier used to report whether the Disallow rule is live.
	 *
	 * @var RobotsTxtVerifier|null
	 * @since 1.2.0
	 */
	private ?RobotsTxtVerifier $verifier;

	/**
	 * Settings page providing the generic field renderers.
	 *
	 * @var SettingsPage|null
	 * @since 1.2.0
	 */
	private ?SettingsPage $page = null;

	/**
	 * Constructor.
	 *
	 * @param RobotsTxtVerifier|null $verifier Optional verifier; resolved from
	 *                                         current options when omitted so the
	 *                                         registrar stays no-arg constructible.
	 * @since 1.2.0
	 */
	public function __construct( ?RobotsTxtVerifier $verifier = null ) {
		$this->verifier = $verifier;
	}

	/**
	 * Register the robots.txt section and its field.
	 *
	 * @param SettingsPage $page Settings page providing the field renderers.
	 * @return void
	 * @since 1.0.0
	 */
	public function register( SettingsPage $page ): void {
		$this->page = $page;

		$this->add_section( self::SECTION_ID, __( 'robots.txt', 'jtzl-bot-maze' ) );

		$this->register_field(
			'jtzl_bm_robots_txt_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_checkbox' ),
				'default'           => true,
			),
			__( 'Modify robots.txt', 'jtzl-bot-maze' ),
			array( $this, 'render_robots_field' ),
			self::SECTION_ID,
			array(
				'label_for'   => 'jtzl_bm_robots_txt_enabled',
				'description' => __( 'Add a Disallow rule for the trap base path to keep compliant crawlers out of trap URLs.', 'jtzl-bot-maze' ),
			)
		);
	}

	/**
	 * Render the robots.txt section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_section_description(): void {
		echo '<p>' . esc_html__( 'Control robots.txt modifications that help lure bots into the trap maze. The Disallow rule is added to the robots.txt WordPress generates; it cannot take effect if a static robots.txt file already exists at your site root, or if search-engine visibility is switched off in Settings → Reading. If you manage robots.txt through an SEO plugin (such as Yoast SEO or Rank Math), add the Disallow rule using that plugin instead — Bot Maze will detect it once it is live.', 'jtzl-bot-maze' ) . '</p>';
	}

	/**
	 * Render the enable checkbox followed by a live verification status line.
	 *
	 * @param array $args Field arguments forwarded from add_settings_field().
	 * @return void
	 * @since 1.2.0
	 */
	public function render_robots_field( array $args ): void {
		if ( null !== $this->page ) {
			$this->page->render_checkbox_field( $args );
		}

		$this->render_status_block();
	}

	/**
	 * Render the inline verification notice beneath the checkbox.
	 *
	 * @return void
	 * @since 1.2.0
	 */
	private function render_status_block(): void {
		$result = $this->verifier()->verify();
		$status = $result['status'];
		$line   = $result['expected'];

		switch ( $status ) {
			case RobotsTxtVerifier::STATUS_LIVE:
				$this->render_inline_notice(
					'success',
					esc_html__( 'Verified: the trap Disallow rule is live in your robots.txt.', 'jtzl-bot-maze' )
				);
				break;

			case RobotsTxtVerifier::STATUS_MISSING:
				$this->render_inline_notice(
					'warning',
					sprintf(
						/* translators: %s: the robots.txt directive lines to add. */
						esc_html__( 'The Disallow rule is enabled but is not present in the robots.txt your site serves — most likely a static robots.txt file at your site root, or an SEO plugin\'s robots.txt editor (e.g. Yoast SEO or Rank Math), is overriding the one WordPress generates. Add these lines there manually: %s', 'jtzl-bot-maze' ),
						$this->code_block( "User-agent: *\n" . $line )
					)
				);
				break;

			case RobotsTxtVerifier::STATUS_UNKNOWN:
				$this->render_inline_notice(
					'info',
					sprintf(
						/* translators: %s: the robots.txt directive lines to add. */
						esc_html__( 'Could not verify robots.txt (the request to fetch it failed). If the rule is not applied, add these lines to a static robots.txt at your site root: %s', 'jtzl-bot-maze' ),
						$this->code_block( "User-agent: *\n" . $line )
					)
				);
				break;

			case RobotsTxtVerifier::STATUS_NOT_PUBLIC:
				$this->render_inline_notice(
					'info',
					esc_html__( 'Search-engine visibility is off (Settings → Reading), so robots.txt already disallows the entire site — trap URLs included. This rule is not needed while that setting stays off.', 'jtzl-bot-maze' )
				);
				break;

			case RobotsTxtVerifier::STATUS_DISABLED:
			default:
				// Nothing to verify while the rule is switched off.
				break;
		}
	}

	/**
	 * Render an inline WordPress notice.
	 *
	 * @param string $type    Notice type suffix (success|warning|info).
	 * @param string $message Pre-escaped message HTML.
	 * @return void
	 * @since 1.2.0
	 */
	private function render_inline_notice( string $type, string $message ): void {
		printf(
			'<div class="notice notice-%1$s inline" style="margin: 10px 0 0; padding: 8px 12px;"><p style="margin: 0;">%2$s</p></div>',
			esc_attr( $type ),
			wp_kses(
				$message,
				array(
					'code' => array(),
					'br'   => array(),
				)
			)
		);
	}

	/**
	 * Format directive text as an inline multi-line code block.
	 *
	 * @param string $text Directive text.
	 * @return string Escaped HTML safe to embed in a notice message.
	 * @since 1.2.0
	 */
	private function code_block( string $text ): string {
		return '<code>' . nl2br( esc_html( $text ) ) . '</code>';
	}

	/**
	 * Resolve the verifier, falling back to one built from current options.
	 *
	 * @return RobotsTxtVerifier
	 * @since 1.2.0
	 */
	private function verifier(): RobotsTxtVerifier {
		if ( null === $this->verifier ) {
			$this->verifier = RobotsTxtVerifier::for_current_site();
		}

		return $this->verifier;
	}
}
