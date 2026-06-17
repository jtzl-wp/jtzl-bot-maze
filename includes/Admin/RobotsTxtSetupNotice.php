<?php
/**
 * Robots.txt Setup Notice
 *
 * Admin notice shown when the plugin's Disallow rule is enabled but is not
 * actually present in the robots.txt the site serves.
 *
 * @package JTZL\Bot_Maze
 * @since 1.2.0
 */

namespace JTZL\Bot_Maze\Admin;

use JTZL\Bot_Maze\Service\RobotsTxtFileWriter;
use JTZL\Bot_Maze\Service\RobotsTxtVerifier;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Robots.txt Setup Notice.
 *
 * When verification reports the Disallow rule is enabled yet missing from the
 * served robots.txt, this shows a dismissible admin notice with the exact lines
 * to add. Where the site-root robots.txt is writable, it also offers a one-click
 * "add it for me" action; otherwise it falls back to manual instructions. The
 * notice only appears for the definite "missing" verdict — never for an
 * inconclusive "couldn't verify" — so a transient loopback failure cannot raise
 * a false alarm.
 *
 * @since 1.2.0
 */
class RobotsTxtSetupNotice {

	/**
	 * Action slug for the one-click "append rule" handler.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const APPEND_ACTION = 'jtzl_bm_append_robots';

	/**
	 * Action slug for the "re-check" handler.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const RECHECK_ACTION = 'jtzl_bm_recheck_robots';

	/**
	 * Action slug for the dismiss handler.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const DISMISS_ACTION = 'jtzl_bm_dismiss_robots_notice';

	/**
	 * User-meta key recording a per-user dismissal.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const DISMISS_META = 'jtzl_bm_robots_notice_dismissed';

	/**
	 * Transient key carrying one-shot feedback after an append attempt.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	private const FEEDBACK_TRANSIENT = 'jtzl_bm_robots_append_feedback';

	/**
	 * Verifier reporting whether the rule is live.
	 *
	 * @var RobotsTxtVerifier
	 * @since 1.2.0
	 */
	private RobotsTxtVerifier $verifier;

	/**
	 * Writer used for the optional one-click append.
	 *
	 * @var RobotsTxtFileWriter
	 * @since 1.2.0
	 */
	private RobotsTxtFileWriter $writer;

	/**
	 * Constructor.
	 *
	 * @param RobotsTxtVerifier   $verifier Verifier reporting rule presence.
	 * @param RobotsTxtFileWriter $writer   Writer for the one-click append.
	 * @since 1.2.0
	 */
	public function __construct( RobotsTxtVerifier $verifier, RobotsTxtFileWriter $writer ) {
		$this->verifier = $verifier;
		$this->writer   = $writer;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 * @since 1.2.0
	 */
	public function init(): void {
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
		add_action( 'admin_post_' . self::APPEND_ACTION, array( $this, 'handle_append' ) );
		add_action( 'admin_post_' . self::RECHECK_ACTION, array( $this, 'handle_recheck' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( $this, 'handle_dismiss' ) );
	}

	/**
	 * Whether the notice should be displayed to the current user.
	 *
	 * @return bool
	 * @since 1.2.0
	 */
	public function should_display(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( get_user_meta( get_current_user_id(), self::DISMISS_META, true ) ) {
			return false;
		}

		// Cached-only: admin_notices fires on every wp-admin page, so this must
		// never trigger a blocking loopback. The verdict is primed by the
		// settings page, the Re-check action, and the daily maintenance cron.
		return RobotsTxtVerifier::STATUS_MISSING === $this->verifier->verify( false, false )['status'];
	}

	/**
	 * Render the admin notice when appropriate.
	 *
	 * @return void
	 * @since 1.2.0
	 */
	public function maybe_render(): void {
		$this->render_feedback();

		if ( ! $this->should_display() ) {
			return;
		}

		// Cached-only (should_display() already confirmed a cached "missing").
		$result = $this->verifier->verify( false, false );
		$lines  = "User-agent: *\n" . $result['expected'];

		$append_url   = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::APPEND_ACTION ), self::APPEND_ACTION );
		$recheck_url  = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::RECHECK_ACTION ), self::RECHECK_ACTION );
		$dismiss_url  = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION ), self::DISMISS_ACTION );
		$settings_url = admin_url( 'admin.php?page=' . SettingsPage::PAGE_SLUG );

		echo '<div class="notice notice-warning">';
		printf(
			'<p><strong>%s</strong></p>',
			esc_html__( 'JTZL Bot Maze: robots.txt rule is not active', 'jtzl-bot-maze' )
		);
		printf(
			'<p>%s</p>',
			esc_html__( 'The trap Disallow rule is enabled, but it is missing from the robots.txt your site serves — usually because a static robots.txt file at your site root overrides the one WordPress generates. Until the rule is present, compliant crawlers are not steered away from your trap URLs.', 'jtzl-bot-maze' )
		);
		printf(
			'<p>%s</p><pre style="background:#f6f7f7; padding:8px 12px; margin:0 0 12px;">%s</pre>',
			esc_html__( 'Add these lines to that robots.txt file:', 'jtzl-bot-maze' ),
			esc_html( $lines )
		);
		printf(
			'<p>%s</p>',
			esc_html__( 'If you manage robots.txt through an SEO plugin (such as Yoast SEO or Rank Math), add these lines using that plugin\'s robots.txt editor instead.', 'jtzl-bot-maze' )
		);

		echo '<p>';
		// Only offer the one-click append when a physical file already exists and
		// is writable. Creating a new static robots.txt would shadow WordPress's
		// virtual one and drop other plugins' directives, so that case is left to
		// the manual instructions above.
		if ( $this->writer->file_exists() && $this->writer->can_write() ) {
			printf(
				'<a href="%s" class="button button-primary">%s</a> ',
				esc_url( $append_url ),
				esc_html__( 'Add it for me', 'jtzl-bot-maze' )
			);
		}
		printf(
			'<a href="%s" class="button">%s</a> <a href="%s" class="button">%s</a> <a href="%s">%s</a>',
			esc_url( $recheck_url ),
			esc_html__( 'Re-check now', 'jtzl-bot-maze' ),
			esc_url( $settings_url ),
			esc_html__( 'Review in settings', 'jtzl-bot-maze' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'jtzl-bot-maze' )
		);
		echo '</p></div>';
	}

	/**
	 * Handle the one-click "append rule" action.
	 *
	 * @return void
	 * @since 1.2.0
	 */
	public function handle_append(): void {
		check_admin_referer( self::APPEND_ACTION );
		$this->require_cap();

		$result = $this->writer->append_directive( $this->verifier->verify()['expected'] );

		// The served file changed; force the next verification to re-fetch.
		$this->verifier->invalidate();

		set_transient( self::FEEDBACK_TRANSIENT . '_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );

		$this->redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	}

	/**
	 * Handle the "re-check" action.
	 *
	 * @return void
	 * @since 1.2.0
	 */
	public function handle_recheck(): void {
		check_admin_referer( self::RECHECK_ACTION );
		$this->require_cap();

		$this->verifier->invalidate();
		$this->verifier->verify( true );

		$this->redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	}

	/**
	 * Handle the dismiss action.
	 *
	 * @return void
	 * @since 1.2.0
	 */
	public function handle_dismiss(): void {
		check_admin_referer( self::DISMISS_ACTION );
		$this->require_cap();

		update_user_meta( get_current_user_id(), self::DISMISS_META, '1' );

		$this->redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	}

	/**
	 * Render one-shot feedback after an append attempt, if any is queued.
	 *
	 * @return void
	 * @since 1.2.0
	 */
	private function render_feedback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$key      = self::FEEDBACK_TRANSIENT . '_' . get_current_user_id();
		$feedback = get_transient( $key );
		if ( false === $feedback ) {
			return;
		}
		delete_transient( $key );

		$messages = array(
			RobotsTxtFileWriter::RESULT_APPENDED        => array(
				'success',
				__( 'JTZL Bot Maze: the Disallow rule was added to your robots.txt.', 'jtzl-bot-maze' ),
			),
			RobotsTxtFileWriter::RESULT_ALREADY_PRESENT => array(
				'info',
				__( 'JTZL Bot Maze: the Disallow rule was already present in your robots.txt.', 'jtzl-bot-maze' ),
			),
			RobotsTxtFileWriter::RESULT_NOT_WRITABLE    => array(
				'error',
				__( 'JTZL Bot Maze: your robots.txt file is not writable, so the rule could not be added automatically. Please add it manually.', 'jtzl-bot-maze' ),
			),
			RobotsTxtFileWriter::RESULT_NO_FILE         => array(
				'info',
				__( 'JTZL Bot Maze: no robots.txt file exists at your site root, so there is nothing to append to. The rule belongs in the robots.txt WordPress generates — check for a plugin overriding it, or add the rule via your SEO plugin.', 'jtzl-bot-maze' ),
			),
			RobotsTxtFileWriter::RESULT_ERROR           => array(
				'error',
				__( 'JTZL Bot Maze: writing to robots.txt failed unexpectedly. Please add the rule manually.', 'jtzl-bot-maze' ),
			),
		);

		if ( ! isset( $messages[ $feedback ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $feedback ][0] ),
			esc_html( $messages[ $feedback ][1] )
		);
	}

	/**
	 * Bail out unless the current user may manage options.
	 *
	 * @return void
	 * @since 1.2.0
	 */
	private function require_cap(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'jtzl-bot-maze' ) );
		}
	}

	/**
	 * Redirect and stop execution.
	 *
	 * @param string $url Destination URL.
	 * @return void
	 * @since 1.2.0
	 */
	private function redirect( string $url ): void {
		wp_safe_redirect( $url );
		exit; // @codeCoverageIgnore
	}
}
