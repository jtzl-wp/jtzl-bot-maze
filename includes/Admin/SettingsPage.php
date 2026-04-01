<?php
/**
 * Admin Settings Page
 *
 * Handles the plugin settings page in WordPress admin.
 *
 * @package Labyrinth
 * @since 1.0.0
 */

namespace Labyrinth\Admin;

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
	private const PAGE_SLUG = 'labyrinth-settings';

	/**
	 * The settings group name.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private const SETTINGS_GROUP = 'lby_settings';

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
			'labyrinth',
			__( 'Labyrinth Settings', 'labyrinth' ),
			__( 'Settings', 'labyrinth' ),
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
			'lby_general_settings',
			__( 'General', 'labyrinth' ),
			array( $this, 'render_general_section_description' ),
			self::PAGE_SLUG
		);

		// Section 2: TrapLinks.
		add_settings_section(
			'lby_trap_link_settings',
			__( 'Trap Links', 'labyrinth' ),
			array( $this, 'render_trap_link_section_description' ),
			self::PAGE_SLUG
		);

		// Section 3: Labyrinth.
		add_settings_section(
			'lby_labyrinth_settings',
			__( 'Labyrinth', 'labyrinth' ),
			array( $this, 'render_labyrinth_section_description' ),
			self::PAGE_SLUG
		);

		// Section 4: Bot Scoring.
		add_settings_section(
			'lby_scoring_settings',
			__( 'Bot Scoring', 'labyrinth' ),
			array( $this, 'render_scoring_section_description' ),
			self::PAGE_SLUG
		);

		// Section 5: Maintenance.
		add_settings_section(
			'lby_maintenance_settings',
			__( 'Maintenance', 'labyrinth' ),
			array( $this, 'render_maintenance_section_description' ),
			self::PAGE_SLUG
		);

		// Section 6: robots.txt.
		add_settings_section(
			'lby_robots_settings',
			__( 'robots.txt', 'labyrinth' ),
			array( $this, 'render_robots_section_description' ),
			self::PAGE_SLUG
		);

		// Register all fields.
		$this->register_general_fields();
		$this->register_trap_link_fields();
		$this->register_labyrinth_fields();
		$this->register_scoring_fields();
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
			'lby_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_enabled' ),
				'default'           => true,
			)
		);

		add_settings_field(
			'lby_enabled',
			__( 'Enable Labyrinth', 'labyrinth' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'lby_general_settings',
			array(
				'label_for'   => 'lby_enabled',
				'description' => __( 'Enable or disable the bot protection labyrinth.', 'labyrinth' ),
			)
		);

		// Trusted proxy header.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_trusted_proxy_header',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_trusted_proxy_header' ),
				'default'           => '',
			)
		);

		add_settings_field(
			'lby_trusted_proxy_header',
			__( 'Trusted Client IP Header', 'labyrinth' ),
			array( $this, 'render_select_field' ),
			self::PAGE_SLUG,
			'lby_general_settings',
			array(
				'label_for'   => 'lby_trusted_proxy_header',
				'description' => __( 'Trust this header as the real client IP only if your site is behind a reverse proxy or CDN that sets it. If you are unsure, leave this on None. Incorrect configuration can allow IP spoofing and affect blocking and analytics.', 'labyrinth' ),
				'options'     => array(
					''                 => __( 'None (safe default)', 'labyrinth' ),
					'CF-Connecting-IP' => __( 'Cloudflare (CF-Connecting-IP)', 'labyrinth' ),
					'X-Real-IP'        => __( 'Nginx / Reverse proxy (X-Real-IP)', 'labyrinth' ),
					'True-Client-IP'   => __( 'Enterprise CDN (True-Client-IP)', 'labyrinth' ),
					'X-Forwarded-For'  => __( 'Advanced: X-Forwarded-For (rightmost untrusted IP)', 'labyrinth' ),
				),
			)
		);

		// Trusted proxy IPs.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_trusted_proxy_ips',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_trusted_proxy_ips' ),
				'default'           => '',
			)
		);

		add_settings_field(
			'lby_trusted_proxy_ips',
			__( 'Trusted Proxy IPs / CIDRs', 'labyrinth' ),
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'lby_general_settings',
			array(
				'label_for'   => 'lby_trusted_proxy_ips',
				'description' => __( 'Comma-separated IP addresses or CIDR ranges allowed to set the trusted header above. Required when a trusted header is configured — the header is ignored until at least one proxy is listed. Example: <code>172.64.0.0/13, 173.245.48.0/20</code>', 'labyrinth' ),
			)
		);

		// Trap base path.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_trap_base_path',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_base_path' ),
				'default'           => 'lby',
			)
		);

		add_settings_field(
			'lby_trap_base_path',
			__( 'Trap Base Path', 'labyrinth' ),
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'lby_general_settings',
			array(
				'label_for'   => 'lby_trap_base_path',
				'description' => __( 'URL path prefix for trap pages (e.g., "lby" makes URLs like /lby/page-slug). Changing this requires a permalink refresh.', 'labyrinth' ),
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
			'lby_links_per_page',
			array(
				'type'              => 'integer',
				'sanitize_callback' => $sanitize_bounded_positive( 'lby_links_per_page' ),
				'default'           => 3,
			)
		);

		add_settings_field(
			'lby_links_per_page',
			__( 'Links Per Page', 'labyrinth' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'lby_trap_link_settings',
			array(
				'label_for'   => 'lby_links_per_page',
				'description' => __( 'Number of hidden trap links injected into each real page.', 'labyrinth' ),
				'min'         => 1,
				'max'         => 10,
			)
		);

		// Injection method.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_injection_method',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_injection_method' ),
				'default'           => 'content',
			)
		);

		add_settings_field(
			'lby_injection_method',
			__( 'Injection Method', 'labyrinth' ),
			array( $this, 'render_select_field' ),
			self::PAGE_SLUG,
			'lby_trap_link_settings',
			array(
				'label_for'   => 'lby_injection_method',
				'description' => __( 'Where to inject hidden trap links on real pages.', 'labyrinth' ),
				'options'     => array(
					'content' => __( 'Content only (the_content filter)', 'labyrinth' ),
					'footer'  => __( 'Footer only (wp_footer action)', 'labyrinth' ),
					'both'    => __( 'Both content and footer', 'labyrinth' ),
				),
			)
		);
	}

	/**
	 * Register labyrinth settings fields.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function register_labyrinth_fields(): void {
		$sanitize_bounded_positive = static function ( string $option_key ): callable {
			return static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, $option_key );
		};

		// Max depth.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_max_depth',
			array(
				'type'              => 'integer',
				'sanitize_callback' => $sanitize_bounded_positive( 'lby_max_depth' ),
				'default'           => 5,
			)
		);

		add_settings_field(
			'lby_max_depth',
			__( 'Max Depth', 'labyrinth' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'lby_labyrinth_settings',
			array(
				'label_for'   => 'lby_max_depth',
				'description' => __( 'Maximum depth of the trap page labyrinth. Deeper mazes consume more storage but provide stronger evidence.', 'labyrinth' ),
				'min'         => 2,
				'max'         => 10,
			)
		);

		// Links per trap page.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_links_per_trap_page',
			array(
				'type'              => 'integer',
				'sanitize_callback' => $sanitize_bounded_positive( 'lby_links_per_trap_page' ),
				'default'           => 5,
			)
		);

		add_settings_field(
			'lby_links_per_trap_page',
			__( 'Links Per Trap Page', 'labyrinth' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'lby_labyrinth_settings',
			array(
				'label_for'   => 'lby_links_per_trap_page',
				'description' => __( 'Number of links to other trap pages within each trap page.', 'labyrinth' ),
				'min'         => 1,
				'max'         => 10,
			)
		);

		// Initial page count.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_initial_page_count',
			array(
				'type'              => 'integer',
				'sanitize_callback' => $sanitize_bounded_positive( 'lby_initial_page_count' ),
				'default'           => 20,
			)
		);

		add_settings_field(
			'lby_initial_page_count',
			__( 'Initial Page Count', 'labyrinth' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'lby_labyrinth_settings',
			array(
				'label_for'   => 'lby_initial_page_count',
				'description' => __( 'Number of entry-level trap pages to generate. More pages make the labyrinth harder to map.', 'labyrinth' ),
				'min'         => 5,
				'max'         => 100,
			)
		);

		// Global maximum trap pages.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_max_trap_pages',
			array(
				'type'              => 'integer',
				'sanitize_callback' => $sanitize_bounded_positive( 'lby_max_trap_pages' ),
				'default'           => 500,
			)
		);

		add_settings_field(
			'lby_max_trap_pages',
			__( 'Global Maximum Trap Pages', 'labyrinth' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'lby_labyrinth_settings',
			array(
				'label_for'   => 'lby_max_trap_pages',
				'description' => __( 'Maximum total number of trap pages (all depths). Once reached, no new pages are generated. Set higher to allow deeper mazes at the cost of more database rows.', 'labyrinth' ),
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
			'lby_points_per_visit',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'lby_points_per_visit' ),
				'default'           => 25,
			)
		);

		add_settings_field(
			'lby_points_per_visit',
			__( 'Points Per Visit', 'labyrinth' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'lby_scoring_settings',
			array(
				'label_for'   => 'lby_points_per_visit',
				'description' => __( 'Base suspicion points added for each trap page visit.', 'labyrinth' ),
				'min'         => 1,
			)
		);

		// Depth bonus.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_depth_bonus',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'lby_depth_bonus', true ),
				'default'           => 15,
			)
		);

		add_settings_field(
			'lby_depth_bonus',
			__( 'Depth Bonus', 'labyrinth' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'lby_scoring_settings',
			array(
				'label_for'   => 'lby_depth_bonus',
				'description' => __( 'Additional points multiplied by depth level. Deeper traversal = stronger evidence.', 'labyrinth' ),
				'min'         => 0,
			)
		);

		// Block threshold.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_block_threshold',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'lby_block_threshold' ),
				'default'           => 100,
			)
		);

		add_settings_field(
			'lby_block_threshold',
			__( 'Block Threshold', 'labyrinth' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'lby_scoring_settings',
			array(
				'label_for'   => 'lby_block_threshold',
				'description' => __( 'Score at which a visitor is automatically flagged as a bot. Lower = more aggressive.', 'labyrinth' ),
				'min'         => 10,
			)
		);

		// Blocking behavior.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_blocking_behavior',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_blocking_behavior' ),
				'default'           => 'block',
			)
		);

		add_settings_field(
			'lby_blocking_behavior',
			__( 'Blocking Behavior', 'labyrinth' ),
			array( $this, 'render_select_field' ),
			self::PAGE_SLUG,
			'lby_scoring_settings',
			array(
				'label_for'   => 'lby_blocking_behavior',
				'description' => __(
					'How to respond after a visitor is already flagged as blocked. Every option still loads WordPress to run this check, so very high volumes of blocked traffic can stress PHP workers. <strong>Block</strong> (default) — HTTP 403 with a short message; lowest cost and recommended for most sites. <strong>Light tarpit</strong> — HTTP 200 serving a random existing trap page with links to keep bots crawling; no new child pages are generated for blocked traffic. <strong>Full tarpit</strong> — same decoy page plus a configurable delay (see below); holds each PHP worker longer to waste bot time.',
					'labyrinth'
				),
				'options'     => array(
					'block'        => __( 'Block — 403 Forbidden (default, lowest server cost)', 'labyrinth' ),
					'tarpit_light' => __( 'Light tarpit — serve existing decoy page, no delay', 'labyrinth' ),
					'tarpit'       => __( 'Full tarpit — configurable delay + same decoy page', 'labyrinth' ),
				),
			)
		);

		// Tarpit delay.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_tarpit_delay_ms',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'lby_tarpit_delay_ms' ),
				'default'           => 1000,
			)
		);

		add_settings_field(
			'lby_tarpit_delay_ms',
			__( 'Full Tarpit Delay (ms)', 'labyrinth' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'lby_scoring_settings',
			array(
				'label_for'   => 'lby_tarpit_delay_ms',
				'description' => __( 'Delay in milliseconds before responding to blocked visitors when using the full tarpit. Each request ties up a PHP worker for this duration. Default: 1000 (1 second).', 'labyrinth' ),
				'min'         => 100,
				'max'         => 5000,
				'step'        => 100,
			)
		);

		// Track blocked bot activity.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_track_blocked_activity',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		add_settings_field(
			'lby_track_blocked_activity',
			__( 'Track Blocked Bot Activity', 'labyrinth' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'lby_scoring_settings',
			array(
				'label_for'   => 'lby_track_blocked_activity',
				'description' => __( 'Continue recording visits from blocked bots (updates last-seen time, increments visit count, and logs the request). Useful for monitoring persistent bots, but increases database writes. Default: off.', 'labyrinth' ),
			)
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
			'lby_retention_days',
			array(
				'type'              => 'integer',
				'sanitize_callback' => static fn( $value ): int => SettingsSanitizer::sanitize_option_bounded_integer( $value, 'lby_retention_days' ),
				'default'           => 30,
			)
		);

		add_settings_field(
			'lby_retention_days',
			__( 'Evidence Retention (Days)', 'labyrinth' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'lby_maintenance_settings',
			array(
				'label_for'   => 'lby_retention_days',
				'description' => __( 'Number of days to retain request logs, trap visits, and bot scores, including blocked identities. Older entries are automatically pruned daily, so shorter retention windows also shorten how long blocked bots stay blocked.', 'labyrinth' ),
				'min'         => 1,
				'max'         => 365,
			)
		);

		// Regenerate button (rendered as a custom field).
		add_settings_field(
			'lby_regenerate_labyrinth',
			__( 'Regenerate Labyrinth', 'labyrinth' ),
			array( $this, 'render_regenerate_button' ),
			self::PAGE_SLUG,
			'lby_maintenance_settings'
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
			'lby_robots_txt_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		add_settings_field(
			'lby_robots_txt_enabled',
			__( 'Modify robots.txt', 'labyrinth' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'lby_robots_settings',
			array(
				'label_for'   => 'lby_robots_txt_enabled',
				'description' => __( 'Add a Disallow rule for the trap base path to keep compliant crawlers out of trap URLs.', 'labyrinth' ),
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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'labyrinth' ) );
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
		echo '<p>' . esc_html__( 'Enable or disable the plugin and configure the base URL path for trap pages.', 'labyrinth' ) . '</p>';
	}

	/**
	 * Render trap link section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_trap_link_section_description(): void {
		echo '<p>' . esc_html__( 'Configure how hidden trap links are injected into your real pages.', 'labyrinth' ) . '</p>';
	}

	/**
	 * Render labyrinth section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_labyrinth_section_description(): void {
		echo '<p>' . esc_html__( 'Configure the maze of trap pages that bots will be lured into.', 'labyrinth' ) . '</p>';
		echo '<p>' . esc_html__( 'Trap URLs under your base path always render as lightweight standalone HTML without your theme (header, menus, and theme assets are not loaded). This applies to every labyrinth hit—not only blocked-visitor tarpit responses—and keeps server cost lower than a full themed page. Bots are still led by trap links and generated content; they do not see a pixel-perfect copy of your real posts.', 'labyrinth' ) . '</p>';
	}

	/**
	 * Render scoring section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_scoring_section_description(): void {
		echo '<p>' . esc_html__( 'Configure the scoring algorithm that determines when a visitor is flagged as a bot.', 'labyrinth' ) . '</p>';
	}

	/**
	 * Render maintenance section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_maintenance_section_description(): void {
		echo '<p>' . esc_html__( 'Data retention and labyrinth management.', 'labyrinth' ) . '</p>';
	}

	/**
	 * Render robots section description.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_robots_section_description(): void {
		echo '<p>' . esc_html__( 'Control robots.txt modifications that help lure bots into the labyrinth.', 'labyrinth' ) . '</p>';
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
	 * Render the regenerate labyrinth button.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_regenerate_button(): void {
		printf(
			'<button type="button" id="lby-regenerate-btn" class="button button-secondary">%s</button>',
			esc_html__( 'Regenerate Labyrinth', 'labyrinth' )
		);
		echo '<span id="lby-regenerate-status" style="margin-left: 10px;"></span>';
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Delete all existing trap pages and generate a fresh set. This does not affect bot scores or visit logs.', 'labyrinth' )
		);

		// Inline script for the regenerate button.
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var btn = document.getElementById('lby-regenerate-btn');
			var status = document.getElementById('lby-regenerate-status');
			if (!btn) return;

			btn.addEventListener('click', function() {
				if (!confirm(<?php echo wp_json_encode( __( 'Are you sure you want to regenerate all trap pages?', 'labyrinth' ) ); ?>)) {
					return;
				}

				btn.disabled = true;
				status.textContent = <?php echo wp_json_encode( __( 'Regenerating...', 'labyrinth' ) ); ?>;

				var data = new FormData();
				data.append('action', 'lby_regenerate');
				data.append('nonce', <?php echo wp_json_encode( wp_create_nonce( 'lby_admin_action' ) ); ?>);

				fetch(ajaxurl, { method: 'POST', body: data })
					.then(function(r) { return r.json(); })
					.then(function(response) {
						if (response.success) {
							status.textContent = response.data.message;
							status.style.color = '#00a32a';
						} else {
							status.textContent = response.data.message || <?php echo wp_json_encode( __( 'An error occurred.', 'labyrinth' ) ); ?>;
							status.style.color = '#d63638';
						}
						btn.disabled = false;
					})
					.catch(function() {
						status.textContent = <?php echo wp_json_encode( __( 'Request failed.', 'labyrinth' ) ); ?>;
						status.style.color = '#d63638';
						btn.disabled = false;
					});
			});
		});
		</script>
		<?php
	}
}
