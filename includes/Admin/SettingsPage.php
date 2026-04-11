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

use JTZL\Bot_Be_Gone\Admin\Settings\SectionRegistrarFactory;
use JTZL\Bot_Be_Gone\Admin\Settings\SectionRegistrarInterface;

/**
 * Settings Page Class
 *
 * Thin shell that owns the admin menu lifecycle and composes a list of
 * section registrars. Each registrar owns one Settings API section and
 * calls back into the generic field renderers on this class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * The settings page slug.
	 *
	 * Public so registrars can pass it to add_settings_section/add_settings_field.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public const PAGE_SLUG = 'jtzl-bot-be-gone-settings';

	/**
	 * The settings group name.
	 *
	 * Public so registrars can pass it to register_setting.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public const SETTINGS_GROUP = 'jtzl_bbg_settings';

	/**
	 * Section registrars composed into this settings page.
	 *
	 * @var SectionRegistrarInterface[]
	 * @since 1.0.0
	 */
	private array $registrars;

	/**
	 * Constructor.
	 *
	 * @param SectionRegistrarInterface[] $registrars Optional list of registrars.
	 *                                                Defaults to the built-in set.
	 * @since 1.0.0
	 */
	public function __construct( array $registrars = array() ) {
		$this->registrars = empty( $registrars ) ? SectionRegistrarFactory::create_default() : $registrars;
	}

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
	 * Register all plugin settings by delegating to each section registrar.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		foreach ( $this->registrars as $registrar ) {
			$registrar->register( $this );
		}
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
}
