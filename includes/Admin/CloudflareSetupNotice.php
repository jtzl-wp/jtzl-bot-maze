<?php
/**
 * Cloudflare Setup Notice
 *
 * Admin notice suggesting the Cloudflare trusted-header preset when the site
 * is detected to be behind Cloudflare.
 *
 * @package JTZL\Bot_Maze
 * @since 1.1.0
 */

namespace JTZL\Bot_Maze\Admin;

use JTZL\Bot_Maze\Service\CloudflareDetector;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Cloudflare Setup Notice.
 *
 * Shows a dismissible admin notice when the current request looks like it came
 * through Cloudflare but the trusted client IP header is not yet set to
 * Cloudflare. Offers a one-click action to enable it (the bundled ranges load
 * automatically) plus a link to the settings page.
 *
 * @since 1.1.0
 */
class CloudflareSetupNotice {

	/**
	 * Action slug for the one-click "enable Cloudflare" handler.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	public const ENABLE_ACTION = 'jtzl_bm_enable_cloudflare';

	/**
	 * Action slug for the dismiss handler.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	public const DISMISS_ACTION = 'jtzl_bm_dismiss_cf_notice';

	/**
	 * User-meta key recording a per-user dismissal.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	public const DISMISS_META = 'jtzl_bm_cf_notice_dismissed';

	/**
	 * Trusted-proxy-header option key.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	private const HEADER_OPTION = 'jtzl_bm_trusted_proxy_header';

	/**
	 * Trusted-proxy-IPs override option key.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	private const IPS_OPTION = 'jtzl_bm_trusted_proxy_ips';

	/**
	 * Header value representing the Cloudflare preset.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	private const CF_HEADER_VALUE = 'CF-Connecting-IP';

	/**
	 * Cloudflare documentation on restricting origin access.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	private const DOCS_URL = 'https://developers.cloudflare.com/fundamentals/security/protect-your-origin-server/';

	/**
	 * Cloudflare request detector.
	 *
	 * @var CloudflareDetector
	 * @since 1.1.0
	 */
	private CloudflareDetector $detector;

	/**
	 * Constructor.
	 *
	 * @param CloudflareDetector $detector Cloudflare request detector.
	 * @since 1.1.0
	 */
	public function __construct( CloudflareDetector $detector ) {
		$this->detector = $detector;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 * @since 1.1.0
	 */
	public function init(): void {
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
		add_action( 'admin_post_' . self::ENABLE_ACTION, array( $this, 'handle_enable' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( $this, 'handle_dismiss' ) );
	}

	/**
	 * Whether the suggestion notice should be displayed to the current user.
	 *
	 * @return bool
	 * @since 1.1.0
	 */
	public function should_display(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( self::CF_HEADER_VALUE === (string) get_option( self::HEADER_OPTION, '' ) ) {
			return false;
		}

		if ( get_user_meta( get_current_user_id(), self::DISMISS_META, true ) ) {
			return false;
		}

		return $this->detector->is_behind_cloudflare();
	}

	/**
	 * Render the admin notice when appropriate.
	 *
	 * @return void
	 * @since 1.1.0
	 */
	public function maybe_render(): void {
		if ( ! $this->should_display() ) {
			return;
		}

		$enable_url   = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ENABLE_ACTION ), self::ENABLE_ACTION );
		$dismiss_url  = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION ), self::DISMISS_ACTION );
		$settings_url = admin_url( 'admin.php?page=' . SettingsPage::PAGE_SLUG );

		printf(
			'<div class="notice notice-info"><p><strong>%1$s</strong></p><p>%2$s</p><p><strong>%3$s</strong> %4$s <a href="%5$s" target="_blank" rel="noopener noreferrer">%6$s</a></p><p><a href="%7$s" class="button button-primary">%8$s</a> <a href="%9$s" class="button">%10$s</a> <a href="%11$s">%12$s</a></p></div>',
			esc_html__( 'JTZL Bot Maze: Cloudflare detected', 'jtzl-bot-maze' ),
			esc_html__( 'Your site looks like it is behind Cloudflare. Turn on Cloudflare mode so Bot Maze records each visitor\'s real IP address instead of Cloudflare\'s. The Cloudflare IP ranges are built in and stay up to date automatically.', 'jtzl-bot-maze' ),
			esc_html__( 'One thing to check first:', 'jtzl-bot-maze' ),
			esc_html__( 'your server should only be reachable through Cloudflare, not directly by its own IP address. If the server can still be reached by its IP, attackers can bypass Cloudflare and fake visitor IP addresses.', 'jtzl-bot-maze' ),
			esc_url( self::DOCS_URL ),
			esc_html__( 'How to secure your origin', 'jtzl-bot-maze' ),
			esc_url( $enable_url ),
			esc_html__( 'Enable Cloudflare mode', 'jtzl-bot-maze' ),
			esc_url( $settings_url ),
			esc_html__( 'Review in settings', 'jtzl-bot-maze' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'jtzl-bot-maze' )
		);
	}

	/**
	 * Handle the one-click "enable Cloudflare mode" action.
	 *
	 * @return void
	 * @since 1.1.0
	 */
	public function handle_enable(): void {
		check_admin_referer( self::ENABLE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'jtzl-bot-maze' ) );
		}

		update_option( self::HEADER_OPTION, self::CF_HEADER_VALUE );

		// Clear any leftover manual override so the bundled Cloudflare ranges
		// authoritatively take over. The container only auto-loads them when
		// this field is empty, so a stale non-Cloudflare entry would otherwise
		// silently become the trusted set and defeat the whole point.
		update_option( self::IPS_OPTION, '' );

		$this->redirect( admin_url( 'admin.php?page=' . SettingsPage::PAGE_SLUG ) );
	}

	/**
	 * Handle the dismiss action.
	 *
	 * @return void
	 * @since 1.1.0
	 */
	public function handle_dismiss(): void {
		check_admin_referer( self::DISMISS_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'jtzl-bot-maze' ) );
		}

		update_user_meta( get_current_user_id(), self::DISMISS_META, '1' );

		$referer = wp_get_referer();
		$this->redirect( $referer ? $referer : admin_url() );
	}

	/**
	 * Redirect and stop execution.
	 *
	 * @param string $url Destination URL.
	 * @return void
	 * @since 1.1.0
	 */
	private function redirect( string $url ): void {
		wp_safe_redirect( $url );
		exit; // @codeCoverageIgnore
	}
}
