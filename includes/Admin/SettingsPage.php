<?php
/**
 * Admin Settings Page
 *
 * Handles the plugin settings page in WordPress admin.
 *
 * @package Labyrinth
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
 */
class SettingsPage {

	/**
	 * The settings page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'labyrinth';

	/**
	 * The settings group name.
	 *
	 * @var string
	 */
	private const SETTINGS_GROUP = 'lby_settings';

	/**
	 * Initialize the settings page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the settings page in the WordPress admin menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		// Add top-level menu.
		add_menu_page(
			__( 'Labyrinth Bot Protection', 'labyrinth' ),
			__( 'Labyrinth', 'labyrinth' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-shield',
			80
		);

		// Add settings submenu (replaces the default first submenu).
		add_submenu_page(
			self::PAGE_SLUG,
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
	 */
	public function register_settings(): void {
		// Section 1: General.
		add_settings_section(
			'lby_general_settings',
			__( 'General', 'labyrinth' ),
			array( $this, 'render_general_section_description' ),
			self::PAGE_SLUG
		);

		// Section 2: Trap Links.
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
			__( 'Trusted Proxy Header', 'labyrinth' ),
			array( $this, 'render_select_field' ),
			self::PAGE_SLUG,
			'lby_general_settings',
			array(
				'label_for'   => 'lby_trusted_proxy_header',
				'description' => __( 'Only set this if your site is behind a reverse proxy or CDN. Incorrect configuration can allow IP spoofing.', 'labyrinth' ),
				'options'     => array(
					''                 => __( 'None (auto-detect)', 'labyrinth' ),
					'CF-Connecting-IP' => __( 'Cloudflare', 'labyrinth' ),
					'X-Real-IP'        => __( 'Nginx / Generic proxy', 'labyrinth' ),
					'True-Client-IP'   => __( 'Akamai / Cloudflare Enterprise', 'labyrinth' ),
					'X-Forwarded-For'  => __( 'Standard proxy (rightmost IP)', 'labyrinth' ),
				),
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
	 */
	private function register_trap_link_fields(): void {
		// Links per real page.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_links_per_page',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_positive_integer' ),
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
	 */
	private function register_labyrinth_fields(): void {
		// Max depth.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_max_depth',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_positive_integer' ),
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
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_positive_integer' ),
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
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_positive_integer' ),
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
	}

	/**
	 * Register bot scoring settings fields.
	 *
	 * @return void
	 */
	private function register_scoring_fields(): void {
		// Points per visit.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_points_per_visit',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_positive_integer' ),
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
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_non_negative_integer' ),
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
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_positive_integer' ),
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
				'description' => __( 'How to respond to blocked bots. <strong>Block</strong> returns 403 Forbidden. <strong>Tarpit</strong> delays the response and serves fake content — uses more server resources (one PHP worker per blocked request).', 'labyrinth' ),
				'options'     => array(
					'block'  => __( 'Block (403 Forbidden)', 'labyrinth' ),
					'tarpit' => __( 'Tarpit (delay + fake content)', 'labyrinth' ),
				),
			)
		);
	}

	/**
	 * Register maintenance settings fields.
	 *
	 * @return void
	 */
	private function register_maintenance_fields(): void {
		// Log retention days.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_log_retention_days',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_positive_integer' ),
				'default'           => 30,
			)
		);

		add_settings_field(
			'lby_log_retention_days',
			__( 'Log Retention (Days)', 'labyrinth' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'lby_maintenance_settings',
			array(
				'label_for'   => 'lby_log_retention_days',
				'description' => __( 'Number of days to retain request log entries. Older entries are automatically pruned daily.', 'labyrinth' ),
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
				'description' => __( 'Add Allow rules and a fake sitemap to robots.txt to lure bots into the labyrinth.', 'labyrinth' ),
			)
		);

		// Advertise trap sitemap in robots.txt.
		register_setting(
			self::SETTINGS_GROUP,
			'lby_robots_sitemap_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		add_settings_field(
			'lby_robots_sitemap_enabled',
			__( 'Advertise Trap Sitemap', 'labyrinth' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'lby_robots_settings',
			array(
				'label_for'   => 'lby_robots_sitemap_enabled',
				'description' => __( 'Include a fake sitemap URL in robots.txt pointing to trap pages. Disable for safer defaults with search engines.', 'labyrinth' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
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
	 */
	public function render_general_section_description(): void {
		echo '<p>' . esc_html__( 'Enable or disable the plugin and configure the base URL path for trap pages.', 'labyrinth' ) . '</p>';
	}

	/**
	 * Render trap link section description.
	 *
	 * @return void
	 */
	public function render_trap_link_section_description(): void {
		echo '<p>' . esc_html__( 'Configure how hidden trap links are injected into your real pages.', 'labyrinth' ) . '</p>';
	}

	/**
	 * Render labyrinth section description.
	 *
	 * @return void
	 */
	public function render_labyrinth_section_description(): void {
		echo '<p>' . esc_html__( 'Configure the maze of trap pages that bots will be lured into.', 'labyrinth' ) . '</p>';
	}

	/**
	 * Render scoring section description.
	 *
	 * @return void
	 */
	public function render_scoring_section_description(): void {
		echo '<p>' . esc_html__( 'Configure the scoring algorithm that determines when a visitor is flagged as a bot.', 'labyrinth' ) . '</p>';
	}

	/**
	 * Render maintenance section description.
	 *
	 * @return void
	 */
	public function render_maintenance_section_description(): void {
		echo '<p>' . esc_html__( 'Data retention and labyrinth management.', 'labyrinth' ) . '</p>';
	}

	/**
	 * Render robots section description.
	 *
	 * @return void
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
	 */
	public function render_text_field( array $args ): void {
		$option_name = $args['label_for'];
		$default     = $args['default'] ?? '';
		$value       = get_option( $option_name, $default );
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
	 */
	public function render_number_field( array $args ): void {
		$option_name = $args['label_for'];
		$default     = $args['default'] ?? '';
		$value       = get_option( $option_name, $default );
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
	 */
	public function render_select_field( array $args ): void {
		$option_name = $args['label_for'];
		$default     = $args['default'] ?? '';
		$value       = get_option( $option_name, $default );
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
