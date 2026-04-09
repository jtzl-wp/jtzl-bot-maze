<?php
/**
 * Admin Settings Page
 *
 * Handles the plugin settings page in WordPress admin.
 *
 * @package JTZL\Bot_Be_Gone
 * @since 1.0.0
 */

namespace JTZL\Bot_Be_Gone\Admin;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Settings Page Class
 *
 * Manages the admin settings interface for configuring plugin behavior.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * The settings page slug.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private const PAGE_SLUG = 'jtzl-bot-be-gone-settings';

	/**
	 * The settings group name.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private const SETTINGS_GROUP = 'jtzl_bbg_settings';

	/**
	 * Initialize the settings page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the settings page in the WordPress admin menu.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_menu(): void {
		add_submenu_page(
			'jtzl-bot-be-gone',
			__( 'JTZL\'s Bot-be-Gone Settings', 'jtzl-bot-be-gone' ),
			__( 'Settings', 'jtzl-bot-be-gone' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register all plugin settings with WordPress Settings API.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		// Section 1: General.
		add_settings_section(
			'jtzl_bbg_general_settings',
			__( 'General', 'jtzl-bot-be-gone' ),
			array( $this, 'render_general_section_description' ),
			self::PAGE_SLUG
		);

		// Section 2: TrapLinks.
		add_settings_section(
			'jtzl_bbg_trap_link_settings',
			__( 'Trap Links', 'jtzl-bot-be-gone' ),
			array( $this, 'render_trap_link_section_description' ),
			self::PAGE_SLUG
		);

		// Section 3: Trap Maze.
		add_settings_section(
			'jtzl_bbg_trap_maze_settings',
			__( 'Trap Maze', 'jtzl-bot-be-gone' ),
			array( $this, 'render_trap_maze_section_description' ),
			self::PAGE_SLUG
		);

		// Section 4: Bot Scoring.
		add_settings_section(
			'jtzl_bbg_scoring_settings',
			__( 'Bot Scoring', 'jtzl-bot-be-gone' ),
			array( $this, 'render_scoring_section_description' ),
			self::PAGE_SLUG
		);

		// Section 5: Geographic Tracking.
		add_settings_section(
			'jtzl_bbg_geo_settings',
			__( 'Geographic Tracking', 'jtzl-bot-be-gone' ),
			array( $this, 'render_geo_section_description' ),
			self::PAGE_SLUG
		);

		// Section 6: Maintenance.
		add_settings_section(
			'jtzl_bbg_maintenance_settings',
			__( 'Maintenance', 'jtzl-bot-be-gone' ),
			array( $this, 'render_maintenance_section_description' ),
			self::PAGE_SLUG
		);

		// Section 7: robots.txt.
		add_settings_section(
			'jtzl_bbg_robots_settings',
			__( 'robots.txt', 'jtzl-bot-be-gone' ),
			array( $this, 'render_robots_section_description' ),
			self::PAGE_SLUG
		);

		// Register all fields.
		$this->register_general_fields();
		$this->register_trap_link_fields();
		$this->register_trap_maze_fields();
		$this->register_scoring_fields();
		$this->register_geo_fields();
		$this->register_maintenance_fields();
		$this->register_robots_fields();
	}

	/**
	 * Register general settings fields.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function register_general_fields(): void {
		// Enable/disable plugin.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_enabled' ),
				'default'           => true,
			)
		);

		add_settings_field(
			'jtzl_bbg_enabled',
			__( 'Enable Bot-be-Gone', 'jtzl-bot-be-gone' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_general_settings',
			array(
				'label_for'   => 'jtzl_bbg_enabled',
				'description' => __( 'Enable or disable the bot protection trap maze.', 'jtzl-bot-be-gone' ),
			)
		);

		// Trusted proxy header.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_trusted_proxy_header',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_trusted_proxy_header' ),
				'default'           => '',
			)
		);

		add_settings_field(
			'jtzl_bbg_trusted_proxy_header',
			__( 'Trusted Client IP Header', 'jtzl-bot-be-gone' ),
			array( $this, 'render_select_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_general_settings',
			array(
				'label_for'   => 'jtzl_bbg_trusted_proxy_header',
				'description' => __( 'Trust this header as the real client IP only if your site is behind a reverse proxy or CDN that sets it. If you are unsure, leave this on None. Incorrect configuration can allow IP spoofing and affect blocking and analytics.', 'jtzl-bot-be-gone' ),
				'options'     => array(
					''                 => __( 'None (safe default)', 'jtzl-bot-be-gone' ),
					'CF-Connecting-IP' => __( 'Cloudflare (CF-Connecting-IP)', 'jtzl-bot-be-gone' ),
					'X-Real-IP'        => __( 'Nginx / Reverse proxy (X-Real-IP)', 'jtzl-bot-be-gone' ),
					'True-Client-IP'   => __( 'Enterprise CDN (True-Client-IP)', 'jtzl-bot-be-gone' ),
					'X-Forwarded-For'  => __( 'Advanced: X-Forwarded-For (rightmost untrusted IP)', 'jtzl-bot-be-gone' ),
				),
			)
		);

		// Trusted proxy IPs.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_trusted_proxy_ips',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_trusted_proxy_ips' ),
				'default'           => '',
			)
		);

		add_settings_field(
			'jtzl_bbg_trusted_proxy_ips',
			__( 'Trusted Proxy IPs / CIDRs', 'jtzl-bot-be-gone' ),
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_general_settings',
			array(
				'label_for'   => 'jtzl_bbg_trusted_proxy_ips',
				'description' => __( 'Comma-separated IP addresses or CIDR ranges allowed to set the trusted header above. Required when a trusted header is configured — the header is ignored until at least one proxy is listed. Example: <code>172.64.0.0/13, 173.245.48.0/20</code>', 'jtzl-bot-be-gone' ),
			)
		);

		// Trap base path.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_trap_base_path',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_base_path' ),
				'default'           => 'bbg',
			)
		);

		add_settings_field(
			'jtzl_bbg_trap_base_path',
			__( 'Trap Base Path', 'jtzl-bot-be-gone' ),
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_general_settings',
			array(
				'label_for'   => 'jtzl_bbg_trap_base_path',
				'description' => __( 'URL path prefix for trap pages (e.g., "bbg" makes URLs like /bbg/page-slug). Changing this requires a permalink refresh.', 'jtzl-bot-be-gone' ),
			)
		);
	}

	/**
	 * Register trap link settings fields.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function register_trap_link_fields(): void {
		$sanitize_bounded_positive = static function ( string $option_key ): callable {
			return static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, $option_key );
		};

		// Links per real page.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_links_per_page',
			array(
				'type'              => 'integer',
				'sanitize_callback' => $sanitize_bounded_positive( 'jtzl_bbg_links_per_page' ),
				'default'           => 3,
			)
		);

		add_settings_field(
			'jtzl_bbg_links_per_page',
			__( 'Links Per Page', 'jtzl-bot-be-gone' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_trap_link_settings',
			array(
				'label_for'   => 'jtzl_bbg_links_per_page',
				'description' => __( 'Number of hidden trap links injected into each real page.', 'jtzl-bot-be-gone' ),
				'min'         => 1,
				'max'         => 10,
			)
		);

		// Injection method.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_injection_method',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_injection_method' ),
				'default'           => 'content',
			)
		);

		add_settings_field(
			'jtzl_bbg_injection_method',
			__( 'Injection Method', 'jtzl-bot-be-gone' ),
			array( $this, 'render_select_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_trap_link_settings',
			array(
				'label_for'   => 'jtzl_bbg_injection_method',
				'description' => __( 'Where to inject hidden trap links on real pages.', 'jtzl-bot-be-gone' ),
				'options'     => array(
					'content' => __( 'Content only (the_content filter)', 'jtzl-bot-be-gone' ),
					'footer'  => __( 'Footer only (wp_footer action)', 'jtzl-bot-be-gone' ),
					'both'    => __( 'Both content and footer', 'jtzl-bot-be-gone' ),
				),
			)
		);
	}

	/**
	 * Register trap maze settings fields.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function register_trap_maze_fields(): void {
		$sanitize_bounded_positive = static function ( string $option_key ): callable {
			return static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, $option_key );
		};

		// Max depth.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_max_depth',
			array(
				'type'              => 'integer',
				'sanitize_callback' => $sanitize_bounded_positive( 'jtzl_bbg_max_depth' ),
				'default'           => 5,
			)
		);

		add_settings_field(
			'jtzl_bbg_max_depth',
			__( 'Max Depth', 'jtzl-bot-be-gone' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_trap_maze_settings',
			array(
				'label_for'   => 'jtzl_bbg_max_depth',
				'description' => __( 'Maximum depth of the trap page maze. Deeper mazes consume more storage but provide stronger evidence.', 'jtzl-bot-be-gone' ),
				'min'         => 2,
				'max'         => 10,
			)
		);

		// Links per trap page.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_links_per_trap_page',
			array(
				'type'              => 'integer',
				'sanitize_callback' => $sanitize_bounded_positive( 'jtzl_bbg_links_per_trap_page' ),
				'default'           => 5,
			)
		);

		add_settings_field(
			'jtzl_bbg_links_per_trap_page',
			__( 'Links Per Trap Page', 'jtzl-bot-be-gone' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_trap_maze_settings',
			array(
				'label_for'   => 'jtzl_bbg_links_per_trap_page',
				'description' => __( 'Number of links to other trap pages within each trap page.', 'jtzl-bot-be-gone' ),
				'min'         => 1,
				'max'         => 10,
			)
		);

		// Initial page count.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_initial_page_count',
			array(
				'type'              => 'integer',
				'sanitize_callback' => $sanitize_bounded_positive( 'jtzl_bbg_initial_page_count' ),
				'default'           => 20,
			)
		);

		add_settings_field(
			'jtzl_bbg_initial_page_count',
			__( 'Initial Page Count', 'jtzl-bot-be-gone' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_trap_maze_settings',
			array(
				'label_for'   => 'jtzl_bbg_initial_page_count',
				'description' => __( 'Number of entry-level trap pages to generate. More pages make the trap maze harder to map.', 'jtzl-bot-be-gone' ),
				'min'         => 5,
				'max'         => 100,
			)
		);

		// Global maximum trap pages.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_max_trap_pages',
			array(
				'type'              => 'integer',
				'sanitize_callback' => $sanitize_bounded_positive( 'jtzl_bbg_max_trap_pages' ),
				'default'           => 500,
			)
		);

		add_settings_field(
			'jtzl_bbg_max_trap_pages',
			__( 'Global Maximum Trap Pages', 'jtzl-bot-be-gone' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_trap_maze_settings',
			array(
				'label_for'   => 'jtzl_bbg_max_trap_pages',
				'description' => __( 'Maximum total number of trap pages (all depths). Once reached, no new pages are generated. Set higher to allow deeper mazes at the cost of more database rows.', 'jtzl-bot-be-gone' ),
				'min'         => 20,
				'max'         => 5000,
			)
		);
	}

	/**
	 * Register bot scoring settings fields.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function register_scoring_fields(): void {
		// Points per visit.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_points_per_visit',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bbg_points_per_visit' ),
				'default'           => 25,
			)
		);

		add_settings_field(
			'jtzl_bbg_points_per_visit',
			__( 'Points Per Visit', 'jtzl-bot-be-gone' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_scoring_settings',
			array(
				'label_for'   => 'jtzl_bbg_points_per_visit',
				'description' => __( 'Base suspicion points added for each trap page visit.', 'jtzl-bot-be-gone' ),
				'min'         => 1,
			)
		);

		// Depth bonus.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_depth_bonus',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bbg_depth_bonus', true ),
				'default'           => 15,
			)
		);

		add_settings_field(
			'jtzl_bbg_depth_bonus',
			__( 'Depth Bonus', 'jtzl-bot-be-gone' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_scoring_settings',
			array(
				'label_for'   => 'jtzl_bbg_depth_bonus',
				'description' => __( 'Additional points multiplied by depth level. Deeper traversal = stronger evidence.', 'jtzl-bot-be-gone' ),
				'min'         => 0,
			)
		);

		// Block threshold.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_block_threshold',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bbg_block_threshold' ),
				'default'           => 100,
			)
		);

		add_settings_field(
			'jtzl_bbg_block_threshold',
			__( 'Block Threshold', 'jtzl-bot-be-gone' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_scoring_settings',
			array(
				'label_for'   => 'jtzl_bbg_block_threshold',
				'description' => __( 'Score at which a visitor is automatically flagged as a bot. Lower = more aggressive.', 'jtzl-bot-be-gone' ),
				'min'         => 10,
			)
		);

		// Blocking behavior.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_blocking_behavior',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_blocking_behavior' ),
				'default'           => 'block',
			)
		);

		add_settings_field(
			'jtzl_bbg_blocking_behavior',
			__( 'Blocking Behavior', 'jtzl-bot-be-gone' ),
			array( $this, 'render_select_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_scoring_settings',
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

		// Tarpit delay.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_tarpit_delay_ms',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bbg_tarpit_delay_ms' ),
				'default'           => 1000,
			)
		);

		add_settings_field(
			'jtzl_bbg_tarpit_delay_ms',
			__( 'Full Tarpit Delay (ms)', 'jtzl-bot-be-gone' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_scoring_settings',
			array(
				'label_for'   => 'jtzl_bbg_tarpit_delay_ms',
				'description' => __( 'Delay in milliseconds before responding to blocked visitors when using the full tarpit. Each request ties up a PHP worker for this duration. Default: 1000 (1 second).', 'jtzl-bot-be-gone' ),
				'min'         => 100,
				'max'         => 5000,
				'step'        => 100,
			)
		);

		// Track blocked bot activity.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_track_blocked_activity',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		add_settings_field(
			'jtzl_bbg_track_blocked_activity',
			__( 'Track Blocked Bot Activity', 'jtzl-bot-be-gone' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_scoring_settings',
			array(
				'label_for'   => 'jtzl_bbg_track_blocked_activity',
				'description' => __( 'Continue recording visits from blocked bots (updates last-seen time, increments visit count, and logs the request). Useful for monitoring persistent bots, but increases database writes. Default: off.', 'jtzl-bot-be-gone' ),
			)
		);
	}

	/**
	 * Register geographic tracking settings fields.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function register_geo_fields(): void {
		// Provider selector.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_geo_provider',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_geo_provider' ),
				'default'           => 'none',
			)
		);

		add_settings_field(
			'jtzl_bbg_geo_provider',
			__( 'GeoIP Provider', 'jtzl-bot-be-gone' ),
			array( $this, 'render_geo_provider_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_geo_settings'
		);

		// MaxMind license key.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_maxmind_license_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_maxmind_license_key' ),
				'default'           => '',
			)
		);

		add_settings_field(
			'jtzl_bbg_maxmind_license_key',
			__( 'MaxMind License Key', 'jtzl-bot-be-gone' ),
			array( $this, 'render_maxmind_fields' ),
			self::PAGE_SLUG,
			'jtzl_bbg_geo_settings'
		);
	}

	/**
	 * Register maintenance settings fields.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function register_maintenance_fields(): void {
		// Evidence retention days.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_retention_days',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'jtzl_bbg_retention_days' ),
				'default'           => 30,
			)
		);

		add_settings_field(
			'jtzl_bbg_retention_days',
			__( 'Evidence Retention (Days)', 'jtzl-bot-be-gone' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_maintenance_settings',
			array(
				'label_for'   => 'jtzl_bbg_retention_days',
				'description' => __( 'Number of days to retain request logs, trap visits, and bot scores, including blocked identities. Older entries are automatically pruned daily, so shorter retention windows also shorten how long blocked bots stay blocked.', 'jtzl-bot-be-gone' ),
				'min'         => 1,
				'max'         => 365,
			)
		);

		// Regenerate button (rendered as a custom field).
		add_settings_field(
			'jtzl_bbg_regenerate_trap_maze',
			__( 'Regenerate Trap Maze', 'jtzl-bot-be-gone' ),
			array( $this, 'render_regenerate_button' ),
			self::PAGE_SLUG,
			'jtzl_bbg_maintenance_settings'
		);
	}

	/**
	 * Register robots.txt settings fields.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function register_robots_fields(): void {
		// Enable robots.txt modifications.
		register_setting(
			self::SETTINGS_GROUP,
			'jtzl_bbg_robots_txt_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		add_settings_field(
			'jtzl_bbg_robots_txt_enabled',
			__( 'Modify robots.txt', 'jtzl-bot-be-gone' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'jtzl_bbg_robots_settings',
			array(
				'label_for'   => 'jtzl_bbg_robots_txt_enabled',
				'description' => __( 'Add a Disallow rule for the trap base path to keep compliant crawlers out of trap URLs.', 'jtzl-bot-be-gone' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'jtzl-bot-be-gone' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	// =========================================================================
	// Section Descriptions
	// =========================================================================

	/**
	 * Render general section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_general_section_description(): void {
		echo '<p>' . esc_html__( 'Enable or disable the plugin and configure the base URL path for trap pages.', 'jtzl-bot-be-gone' ) . '</p>';
	}

	/**
	 * Render trap link section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_trap_link_section_description(): void {
		echo '<p>' . esc_html__( 'Configure how hidden trap links are injected into your real pages.', 'jtzl-bot-be-gone' ) . '</p>';
	}

	/**
	 * Render trap maze section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_trap_maze_section_description(): void {
		echo '<p>' . esc_html__( 'Configure the maze of trap pages that bots will be lured into.', 'jtzl-bot-be-gone' ) . '</p>';
		echo '<p>' . esc_html__( 'Trap URLs under your base path always render as lightweight standalone HTML without your theme (header, menus, and theme assets are not loaded). This applies to every trap maze hit—not only blocked-visitor tarpit responses—and keeps server cost lower than a full themed page. Bots are still led by trap links and generated content; they do not see a pixel-perfect copy of your real posts.', 'jtzl-bot-be-gone' ) . '</p>';
	}

	/**
	 * Render scoring section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_scoring_section_description(): void {
		echo '<p>' . esc_html__( 'Configure the scoring algorithm that determines when a visitor is flagged as a bot.', 'jtzl-bot-be-gone' ) . '</p>';
	}

	/**
	 * Render geographic tracking section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_geo_section_description(): void {
		echo '<p>' . esc_html__( 'Optional geographic tracking resolves visitor IP addresses to country codes for the bot activity heat map. Choose a provider below. The local database option keeps all data on your server; the external API option is simpler to set up but sends IP data to a third party.', 'jtzl-bot-be-gone' ) . '</p>';
	}

	/**
	 * Render maintenance section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_maintenance_section_description(): void {
		echo '<p>' . esc_html__( 'Data retention and trap maze management.', 'jtzl-bot-be-gone' ) . '</p>';
	}

	/**
	 * Render robots section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_robots_section_description(): void {
		echo '<p>' . esc_html__( 'Control robots.txt modifications that help lure bots into the trap maze.', 'jtzl-bot-be-gone' ) . '</p>';
	}

	// =========================================================================
	// Field Renderers
	// =========================================================================

	/**
	 * Render a text input field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 * @since 1.0.0
	 */
	public function render_text_field( array $args ): void {
		$option_name = $args['label_for'];
		$value       = get_option( $option_name );
		$description = $args['description'] ?? '';

		printf(
			'<input type="text" id="%s" name="%s" value="%s" class="regular-text" />',
			esc_attr( $option_name ),
			esc_attr( $option_name ),
			esc_attr( $value )
		);

		if ( ! empty( $description ) ) {
			printf( '<p class="description">%s</p>', wp_kses_post( $description ) );
		}
	}

	/**
	 * Render a number input field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 * @since 1.0.0
	 */
	public function render_number_field( array $args ): void {
		$option_name = $args['label_for'];
		$value       = get_option( $option_name );
		$min         = $args['min'] ?? '';
		$max         = $args['max'] ?? '';
		$step        = $args['step'] ?? '';
		$description = $args['description'] ?? '';

		printf(
			'<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" step="%s" class="small-text" />',
			esc_attr( $option_name ),
			esc_attr( $option_name ),
			esc_attr( $value ),
			esc_attr( $min ),
			esc_attr( $max ),
			esc_attr( $step )
		);

		if ( ! empty( $description ) ) {
			printf( '<p class="description">%s</p>', wp_kses_post( $description ) );
		}
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 * @since 1.0.0
	 */
	public function render_checkbox_field( array $args ): void {
		$option_name = $args['label_for'];
		$value       = get_option( $option_name, false );
		$description = $args['description'] ?? '';

		printf(
			'<input type="checkbox" id="%s" name="%s" value="1" %s />',
			esc_attr( $option_name ),
			esc_attr( $option_name ),
			checked( $value, true, false )
		);

		if ( ! empty( $description ) ) {
			printf( '<label for="%s"><span class="description">%s</span></label>', esc_attr( $option_name ), wp_kses_post( $description ) );
		}
	}

	/**
	 * Render a select dropdown field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 * @since 1.0.0
	 */
	public function render_select_field( array $args ): void {
		$option_name = $args['label_for'];
		$value       = get_option( $option_name );
		$options     = $args['options'] ?? array();
		$description = $args['description'] ?? '';

		printf(
			'<select id="%s" name="%s">',
			esc_attr( $option_name ),
			esc_attr( $option_name )
		);

		foreach ( $options as $option_value => $option_label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $option_value ),
				selected( $value, $option_value, false ),
				esc_html( $option_label )
			);
		}

		echo '</select>';

		if ( ! empty( $description ) ) {
			printf( '<p class="description">%s</p>', wp_kses_post( $description ) );
		}
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
		$db_path      = \JTZL\Bot_Be_Gone\Service\MaxMindDatabaseManager::get_database_path();
		$db_exists    = file_exists( $db_path );

		// Mask the license key in the UI.
		$display_key = '';
		if ( '' !== $license_key ) {
			$display_key = str_repeat( '•', max( 0, strlen( $license_key ) - 4 ) ) . substr( $license_key, -4 );
		}

		// Use an empty value — the masked display is shown as placeholder.
		// This prevents the sanitizer from receiving masked bullet characters.
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

		// Database status.
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

		// Download button.
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

	/**
	 * Render the regenerate trap maze button.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_regenerate_button(): void {
		printf(
			'<button type="button" id="jtzl-bbg-regenerate-btn" class="button button-secondary">%s</button>',
			esc_html__( 'Regenerate Trap Maze', 'jtzl-bot-be-gone' )
		);
		echo '<span id="jtzl-bbg-regenerate-status" style="margin-left: 10px;"></span>';
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Delete all existing trap pages and generate a fresh set. This does not affect bot scores or visit logs.', 'jtzl-bot-be-gone' )
		);

		// Inline script for the regenerate button.
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var btn = document.getElementById('jtzl-bbg-regenerate-btn');
			var status = document.getElementById('jtzl-bbg-regenerate-status');
			if (!btn) return;

			btn.addEventListener('click', function() {
				if (!confirm(<?php echo wp_json_encode( __( 'Are you sure you want to regenerate all trap pages?', 'jtzl-bot-be-gone' ) ); ?>)) {
					return;
				}

				btn.disabled = true;
				status.textContent = <?php echo wp_json_encode( __( 'Regenerating...', 'jtzl-bot-be-gone' ) ); ?>;

				var data = new FormData();
				data.append('action', 'jtzl_bbg_regenerate');
				data.append('nonce', <?php echo wp_json_encode( wp_create_nonce( 'jtzl_bbg_admin_action' ) ); ?>);

				fetch(ajaxurl, { method: 'POST', body: data })
					.then(function(r) { return r.json(); })
					.then(function(response) {
						if (response.success) {
							status.textContent = response.data.message;
							status.style.color = '#00a32a';
						} else {
							status.textContent = response.data.message || <?php echo wp_json_encode( __( 'An error occurred.', 'jtzl-bot-be-gone' ) ); ?>;
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
