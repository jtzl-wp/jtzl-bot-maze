<?php
/**
 * Admin control for AbuseIPDB catch-up reporting.
 *
 * @package JTZL\Bot_Maze
 */

namespace JTZL\Bot_Maze\Admin;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Maze\Service\AbuseIpDbBackfill;

/**
 * Render and handle the one-press catch-up on the Blocked Bots page.
 *
 * The catch-up is a button rather than something that runs when reporting is
 * switched on: enabling a setting should not silently send hundreds of reports
 * from an administrator's account. Pressing a control that names the number of
 * addresses is the consent, and it is deliberately repeatable.
 *
 * @since 1.6.0
 */
final class AbuseIpDbBackfillAction {

	public const ACTION = 'jtzl_bm_abuseipdb_backfill';

	private const NONCE = 'jtzl_bm_abuseipdb_backfill';

	private const FEEDBACK_TRANSIENT = 'jtzl_bm_abuseipdb_backfill_feedback';

	/**
	 * Constructor.
	 *
	 * @param AbuseIpDbBackfill $backfill Catch-up reporting service.
	 */
	public function __construct( private AbuseIpDbBackfill $backfill ) {}

	/** Register admin hooks. */
	public function init(): void {
		add_action( 'admin_notices', array( $this, 'render_feedback' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Render the catch-up control, or nothing when there is no work.
	 *
	 * Absent rather than disabled: a permanently greyed button on a site with
	 * reporting switched off is noise, and the Settings page already explains
	 * how to switch it on.
	 *
	 * Rendered as an admin notice rather than a loose button and sentence. The
	 * .description class only greys its text inside a form table, so on this
	 * page the explanation would render at full strength and read as a stray
	 * second intro paragraph; the notice box groups it with the button it
	 * belongs to and separates both from the table's own toolbar.
	 */
	public function render_button(): void {
		// Already exactly what one press reports: the service bounds the
		// candidate list to MAX_PER_RUN and both the offer and the run read it.
		$eligible = $this->backfill->count_eligible();
		if ( 0 === $eligible ) {
			return;
		}

		// The nonce field is passed positionally (%2$s) rather than
		// concatenated into the format string: it carries an esc_url() referer
		// whose percent-encoded sequences would otherwise be read by printf as
		// conversion specifiers.
		printf(
			'<form method="post" action="%1$s" class="notice notice-info inline">'
			. '%2$s'
			. '<input type="hidden" name="action" value="%3$s">'
			. '<p>%4$s <button type="submit" class="button button-secondary">%5$s</button></p>'
			. '</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( self::NONCE, '_wpnonce', true, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() returns its own escaped markup.
			esc_attr( self::ACTION ),
			esc_html(
				sprintf(
					/* translators: %d: number of blocked addresses eligible for reporting. */
					_n(
						'%d blocked address has not been reported to AbuseIPDB, and was seen recently enough to still be worth reporting.',
						'%d blocked addresses have not been reported to AbuseIPDB, and were seen recently enough to still be worth reporting.',
						$eligible,
						'jtzl-bot-maze'
					),
					$eligible
				)
			),
			esc_html__( 'Report to AbuseIPDB', 'jtzl-bot-maze' )
		);
	}

	/** Queue the eligible addresses and return to the listing. */
	public function handle(): void {
		check_admin_referer( self::NONCE );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'jtzl-bot-maze' ), 403 );
		}

		set_transient(
			self::FEEDBACK_TRANSIENT . '_' . get_current_user_id(),
			$this->backfill->run(),
			5 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect( admin_url( 'admin.php?page=' . BlockedBotsListTable::PAGE_SLUG ) );
		exit; // @codeCoverageIgnore
	}

	/** Report the outcome of a catch-up once, after the redirect. */
	public function render_feedback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$key    = self::FEEDBACK_TRANSIENT . '_' . get_current_user_id();
		$queued = get_transient( $key );
		if ( false === $queued ) {
			return;
		}

		delete_transient( $key );

		$queued  = (int) $queued;
		$message = 0 === $queued
			? __( 'JTZL Bot Maze: no blocked addresses were eligible for AbuseIPDB reporting.', 'jtzl-bot-maze' )
			: sprintf(
				/* translators: %d: number of addresses queued for reporting. */
				_n(
					'JTZL Bot Maze: %d blocked address queued for AbuseIPDB reporting. Delivery runs in the background.',
					'JTZL Bot Maze: %d blocked addresses queued for AbuseIPDB reporting. Delivery runs in the background.',
					$queued,
					'jtzl-bot-maze'
				),
				$queued
			);

		if ( AbuseIpDbBackfill::MAX_PER_RUN === $queued ) {
			$message .= ' ' . __( 'More may remain — use the button again once these have been sent.', 'jtzl-bot-maze' );
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( 0 === $queued ? 'info' : 'success' ),
			esc_html( $message )
		);
	}
}
