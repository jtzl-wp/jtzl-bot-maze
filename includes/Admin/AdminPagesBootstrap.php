<?php
/**
 * Admin Pages Bootstrap
 *
 * @package JTZL\Bot_Labyrinth
 * @since 1.0.0
 */

namespace JTZL\Bot_Labyrinth\Admin;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use DI\Container;
use JTZL\Bot_Labyrinth\WordPress\ContextInterface;

/**
 * Wires up every wp-admin page, asset, and admin-init callback.
 *
 * Split out from Bootstrap so the admin-only initialization block
 * isn't tangled with the runtime hook registration on the front end.
 *
 * @since 1.0.0
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

		$settings_page = new SettingsPage();
		$settings_page->init();

		$trap_pages_page = new TrapPagesPage(
			$this->container->get( 'JTZL\Bot_Labyrinth\Service\AnalyticsCollector' ),
			$this->container->get( 'config.jtzl_bl_trap_base_path' )
		);
		$trap_pages_page->init();

		$analytics_dashboard = new AnalyticsDashboard(
			$this->container->get( 'JTZL\Bot_Labyrinth\Service\AnalyticsCollector' )
		);
		$analytics_dashboard->init();

		$blocked_bots_page = new BlockedBotsPage(
			$this->container->get( 'JTZL\Bot_Labyrinth\Service\AnalyticsCollector' )
		);
		$blocked_bots_page->init();

		$recent_activity_page = new RecentActivityPage(
			$this->container->get( 'JTZL\Bot_Labyrinth\Service\AnalyticsCollector' )
		);
		$recent_activity_page->init();

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
		$allowed = array(
			'bot-labyrinth_page_jtzl-bot-labyrinth-analytics',
			'bot-labyrinth_page_jtzl-bot-labyrinth-blocked-bots',
			'bot-labyrinth_page_jtzl-bot-labyrinth-recent-activity',
		);
		if ( ! in_array( $hook_suffix, $allowed, true ) ) {
			return;
		}

		$asset_manager = $this->container->get( 'JTZL\Bot_Labyrinth\Service\AssetManager' );
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
			'<h2>%s</h2><p>%s</p><p>%s</p><p>%s</p><p>%s</p>',
			esc_html__( 'JTZL\'s Bot Labyrinth — Bot-Be-Gone', 'jtzl-bot-labyrinth' ),
			esc_html__( 'This site uses the JTZL\'s Bot Labyrinth — Bot-Be-Gone plugin to detect and block malicious bots and unauthorized scrapers. When a visitor accesses a trap page (a hidden page that only bots would find), the plugin records their IP address, user agent string, and the time of visit for security purposes.', 'jtzl-bot-labyrinth' ),
			esc_html__( 'This data is collected under the legal basis of legitimate interest (GDPR Article 6(1)(f)) to protect the website from automated abuse. Only visitors that access trap pages are recorded; normal browsing of the site is not tracked by this plugin.', 'jtzl-bot-labyrinth' ),
			sprintf(
				/* translators: %s: default retention period */
				esc_html__( 'Collected data is automatically deleted after a configurable retention period (default: %s days).', 'jtzl-bot-labyrinth' ),
				'30'
			),
			esc_html__( 'The plugin offers optional Geographic Tracking to display bot activity by country. This feature is off by default and requires explicit opt-in by a site administrator. Two provider options are available: (1) MaxMind GeoLite2, which performs lookups using a locally stored database — no visitor data leaves your server; (2) ip-api.com, which sends visitor IP addresses to a third-party service over unencrypted HTTP. The administrator chooses which provider to use in the plugin settings.', 'jtzl-bot-labyrinth' )
		);

		wp_add_privacy_policy_content( 'JTZL\'s Bot Labyrinth — Bot-Be-Gone', $content );
	}
}
