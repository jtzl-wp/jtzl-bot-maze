<?php
/**
 * Admin Pages Bootstrap
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze\Admin;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use DI\Container;
use JTZL\Bot_Maze\Admin\Settings\SectionRegistrarFactory;
use JTZL\Bot_Maze\Service\AbuseIpDbStatus;
use JTZL\Bot_Maze\WordPress\ContextInterface;

/**
 * Wires up every wp-admin page, asset, and admin-init callback.
 *
 * Split out from Bootstrap so the admin-only initialization block
 * isn't tangled with the runtime hook registration on the front end.
 *
 * @since 1.0.0
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class AdminPagesBootstrap {

	/**
	 * The dependency injection container.
	 *
	 * @var Container
	 * @since 1.0.0
	 */
	private Container $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container DI container used to build page collaborators.
	 * @since 1.0.0
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Instantiate every admin page and register their hooks.
	 *
	 * @param ContextInterface $wp WordPress context facade.
	 * @return void
	 * @since 1.0.0
	 */
	public function register( ContextInterface $wp ): void {
		// About page first — registers the top-level menu.
		$about_page = new AboutPage();
		$about_page->init();

		$status        = $this->container->get( AbuseIpDbStatus::class );
		$settings_page = new SettingsPage( SectionRegistrarFactory::create_default( $status ) );
		$settings_page->init();

		$trap_pages_page = new TrapPagesPage(
			$this->container->get( 'JTZL\Bot_Maze\Service\AnalyticsCollector' ),
			$this->container->get( 'config.jtzl_bm_trap_base_path' )
		);
		$trap_pages_page->init();

		$analytics_dashboard = new AnalyticsDashboard(
			$this->container->get( 'JTZL\Bot_Maze\Service\AnalyticsCollector' )
		);
		$analytics_dashboard->init();

		$blocked_bots_page = new BlockedBotsPage(
			$this->container->get( 'JTZL\Bot_Maze\Service\AnalyticsCollector' )
		);
		$blocked_bots_page->init();

		$recent_activity_page = new RecentActivityPage(
			$this->container->get( 'JTZL\Bot_Maze\Service\AnalyticsCollector' )
		);
		$recent_activity_page->init();

		// Suggest the Cloudflare preset when the site is detected behind Cloudflare.
		$cloudflare_notice = new CloudflareSetupNotice(
			$this->container->get( 'JTZL\Bot_Maze\Service\CloudflareDetector' )
		);
		$cloudflare_notice->init();

		// Warn when the robots.txt Disallow rule is enabled but not actually live.
		// Built through the container (a Root→Admin dependency) to keep this
		// composition root's object coupling in check.
		$this->container->get( 'JTZL\Bot_Maze\Admin\RobotsTxtSetupNotice' )->init();

		// Admin-only migration Site Health hooks (debug info + manual retry).
		// The status-test filter itself is registered globally in Bootstrap so
		// the weekly cron Site Health check picks it up. Resolved through the
		// container (like the notice above) to avoid object coupling here.
		$this->container->get( 'JTZL\Bot_Maze\Admin\MigrationHealthCheck' )->register_admin_hooks();

		// Re-check robots.txt after the trap path or the toggle changes on save.
		$robots_verifier = $this->container->get( 'JTZL\Bot_Maze\Service\RobotsTxtVerifier' );
		$wp->add_action( 'update_option_jtzl_bm_robots_txt_enabled', array( $robots_verifier, 'invalidate' ) );
		$wp->add_action( 'update_option_jtzl_bm_trap_base_path', array( $robots_verifier, 'invalidate' ) );

		// Enqueue admin assets and privacy policy suggestion.
		$wp->add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		$wp->add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
	}

	/**
	 * Enqueue admin assets for analytics-style pages only.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 * @since 1.0.0
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		$analytics_pages = array(
			'bot-maze_page_jtzl-bot-maze-analytics',
			'bot-maze_page_jtzl-bot-maze-blocked-bots',
			'bot-maze_page_jtzl-bot-maze-recent-activity',
		);
		$settings_page   = 'bot-maze_page_jtzl-bot-maze-settings';

		if ( $settings_page === $hook_suffix ) {
			$asset_manager = $this->container->get( 'JTZL\Bot_Maze\Service\AssetManager' );
			$asset_manager->enqueue_settings_assets();
			return;
		}

		if ( ! in_array( $hook_suffix, $analytics_pages, true ) ) {
			return;
		}

		$asset_manager = $this->container->get( 'JTZL\Bot_Maze\Service\AssetManager' );
		$asset_manager->enqueue_admin_assets();
		$asset_manager->enqueue_map_assets();
	}

	/**
	 * Register the plugin's suggested privacy policy content.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function add_privacy_policy_content(): void {
		$content = sprintf(
			'<h2>%s</h2><p>%s</p><p>%s</p><p>%s</p><p>%s</p><h3>%s</h3><p>%s</p><p>%s</p>',
			esc_html__( 'JTZL\'s Bot Maze', 'jtzl-bot-maze' ),
			esc_html__( 'This site uses the JTZL\'s Bot Maze plugin to detect and block malicious bots and unauthorized scrapers. When a visitor accesses a trap page (a hidden page that only bots would find), the plugin records their IP address, user agent string, and the time of visit for security purposes.', 'jtzl-bot-maze' ),
			esc_html__( 'This data is collected under the legal basis of legitimate interest (GDPR Article 6(1)(f)) to protect the website from automated abuse. Only visitors that access trap pages are recorded; normal browsing of the site is not tracked by this plugin.', 'jtzl-bot-maze' ),
			sprintf(
				/* translators: %s: default retention period */
				esc_html__( 'Collected data is automatically deleted after a configurable retention period (default: %s days).', 'jtzl-bot-maze' ),
				'30'
			),
			esc_html__( 'The plugin offers optional Geographic Tracking to display bot activity by country. This feature is off by default and requires explicit opt-in by a site administrator. Two provider options are available: (1) MaxMind GeoLite2, which performs lookups using a locally stored database — no visitor data leaves your server; (2) ip-api.com, which sends visitor IP addresses to a third-party service over unencrypted HTTP. The administrator chooses which provider to use in the plugin settings.', 'jtzl-bot-maze' ),
			esc_html__( 'Optional AbuseIPDB reporting', 'jtzl-bot-maze' ),
			esc_html__( 'Only after a site administrator explicitly enables this feature with an administrator-owned AbuseIPDB account and API key, and a non-verified crawler at a public IP address crosses the configured blocking threshold, the plugin sends that public IP address, category 19 (Bad Web Bot), a generic explanation, and the event time to AbuseIPDB over HTTPS for security reporting. Reports are limited to once per IP address in any 24-hour period.', 'jtzl-bot-maze' ),
			sprintf(
				/* translators: 1: AbuseIPDB privacy policy URL, 2: AbuseIPDB terms URL */
				wp_kses_post( __( 'While this feature is enabled, two further records are kept on this site. An address waiting to be reported is held in a queue together with its delivery state; that entry is deleted 24 hours after the report is sent, or after seven days if it is never sent, and the whole queue is deleted as soon as an administrator turns reporting off or removes the API key. Separately, the date and time a report was sent is stored on the visitor records for that address, and is deleted with them at the end of the retention period above. That date is a local copy of a report AbuseIPDB holds; removing it here does not withdraw the report, which is managed through the AbuseIPDB account that sent it. See the <a href="%1$s">AbuseIPDB privacy policy</a> and <a href="%2$s">AbuseIPDB terms</a>.', 'jtzl-bot-maze' ) ),
				esc_url( 'https://www.abuseipdb.com/privacy' ),
				esc_url( 'https://www.abuseipdb.com/terms' )
			)
		);

		wp_add_privacy_policy_content( 'JTZL\'s Bot Maze', $content );
	}
}
