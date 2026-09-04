<?php
/**
 * Migration Health Check
 *
 * Exposes database migration state through WordPress Site Health.
 *
 * @package JTZL\Bot_Maze
 * @since 1.4.0
 */

namespace JTZL\Bot_Maze\Admin;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Maze\Database\Migrator;

/**
 * Registers a Site Health Status test and debug-information section for
 * the plugin's schema migrations.
 *
 * Three verdicts map to the three possible states:
 *   - good        : columns present, no backfill pending (healthy).
 *   - recommended : columns present, backfill still pending (finishing up).
 *   - critical    : schema missing (a table or ALTER failed — protection degraded).
 *
 * A one-click "Retry upgrade" admin-post action lets an administrator
 * manually re-run the migration without WP-CLI or a page reload loop.
 *
 * @since 1.4.0
 */
class MigrationHealthCheck {

	/**
	 * Admin-post action name for the manual retry.
	 *
	 * @var string
	 * @since 1.4.0
	 */
	public const RETRY_ACTION = 'jtzl_bm_retry_migration';

	/**
	 * Nonce action used to protect the retry form.
	 *
	 * @var string
	 * @since 1.4.0
	 */
	public const RETRY_NONCE = 'jtzl_bm_retry_migration_nonce';

	/**
	 * Migration runner instance.
	 *
	 * @var Migrator
	 * @since 1.4.0
	 */
	private Migrator $migrator;

	/**
	 * Constructor.
	 *
	 * @param Migrator $migrator Migration runner.
	 * @since 1.4.0
	 */
	public function __construct( Migrator $migrator ) {
		$this->migrator = $migrator;
	}

	/**
	 * Register the Site Health status test.
	 *
	 * Registered on every request (not just wp-admin) so the test is present
	 * for WordPress's weekly wp_site_health_scheduled_check, which runs under
	 * WP-Cron where is_admin() is false — otherwise the dashboard Site Health
	 * widget would never reflect this test until someone opened the page. The
	 * callback stays inert until Site Health actually collects the tests.
	 *
	 * @return void
	 * @since 1.4.0
	 */
	public function register_status_test(): void {
		add_filter( 'site_status_tests', array( $this, 'add_status_test' ) );
	}

	/**
	 * Register the admin-only hooks (debug information + manual retry action).
	 *
	 * @return void
	 * @since 1.4.0
	 */
	public function register_admin_hooks(): void {
		add_filter( 'debug_information', array( $this, 'add_debug_information' ) );
		add_action( 'admin_post_' . self::RETRY_ACTION, array( $this, 'handle_retry' ) );
	}

	/**
	 * Append the migration status test to Site Health's direct-test list.
	 *
	 * @param array $tests Existing tests array from the filter.
	 * @return array
	 * @since 1.4.0
	 */
	public function add_status_test( array $tests ): array {
		$tests['direct']['jtzl_bm_migration'] = array(
			'label' => __( 'JTZL Bot Maze database upgrade', 'jtzl-bot-maze' ),
			'test'  => array( $this, 'get_status_result' ),
		);

		return $tests;
	}

	/**
	 * Run the migration status test and return a Site Health result array.
	 *
	 * @return array<string, mixed>
	 * @since 1.4.0
	 */
	public function get_status_result(): array {
		$status = $this->migrator->status();

		if ( ! $status['columns_present'] ) {
			return $this->make_result(
				'critical',
				__( 'Bot Maze database upgrade didn\'t finish', 'jtzl-bot-maze' ),
				sprintf(
					'<p>%s</p><p>%s</p>%s',
					esc_html__( 'The database schema upgrade did not complete. New bot activity is not being scored, so bot protection may be silently degraded.', 'jtzl-bot-maze' ),
					esc_html__( 'This usually means the database user lacks the ALTER TABLE permission. Check that the database user can alter tables, then use the button below to retry.', 'jtzl-bot-maze' ),
					$this->retry_action_html()
				)
			);
		}

		if ( ! $status['report_queue_present'] || ! $status['reported_marker_present'] ) {
			return $this->make_result(
				'critical',
				__( 'Bot Maze database upgrade didn\'t finish', 'jtzl-bot-maze' ),
				sprintf(
					'<p>%s</p>%s',
					esc_html__( 'The AbuseIPDB schema is incomplete, so security reports cannot be stored for background delivery or recorded against the bots they came from.', 'jtzl-bot-maze' ),
					$this->retry_action_html()
				)
			);
		}

		if ( $status['pending'] ) {
			return $this->make_result(
				'recommended',
				__( 'Bot Maze is finishing a one-time upgrade', 'jtzl-bot-maze' ),
				sprintf(
					'<p>%s</p>%s',
					esc_html__( 'The schema upgrade completed but a one-time data backfill is still in progress. Analytics may show shortened identifiers until it finishes. It finishes automatically on a following page load, or in the background within the hour.', 'jtzl-bot-maze' ),
					$this->retry_action_html()
				)
			);
		}

		return $this->make_result(
			'good',
			__( 'Bot Maze database upgrade complete', 'jtzl-bot-maze' ),
			'<p>' . esc_html__( 'The database upgrade finished successfully.', 'jtzl-bot-maze' ) . '</p>'
		);
	}

	/**
	 * Append a "JTZL Bot Maze" block to Site Health's debug-information section.
	 *
	 * @param array $info Existing debug information from the filter.
	 * @return array
	 * @since 1.4.0
	 */
	public function add_debug_information( array $info ): array {
		$status = $this->migrator->status();

		$info['jtzl_bm_migration'] = array(
			'label'  => __( 'JTZL Bot Maze — migration', 'jtzl-bot-maze' ),
			'fields' => array(
				'plugin_version'       => array(
					'label' => __( 'Installed version', 'jtzl-bot-maze' ),
					'value' => JTZL_BM_VERSION,
				),
				'stored_version'       => array(
					'label' => __( 'Stored (applied) version', 'jtzl-bot-maze' ),
					'value' => '' !== $status['stored_version'] ? $status['stored_version'] : __( '(not set)', 'jtzl-bot-maze' ),
				),
				'columns_present'      => array(
					'label' => __( 'Denormalized columns present', 'jtzl-bot-maze' ),
					'value' => $status['columns_present'] ? __( 'Yes', 'jtzl-bot-maze' ) : __( 'No', 'jtzl-bot-maze' ),
				),
				'report_queue_present' => array(
					'label' => __( 'AbuseIPDB queue present', 'jtzl-bot-maze' ),
					'value' => $status['report_queue_present'] ? __( 'Yes', 'jtzl-bot-maze' ) : __( 'No', 'jtzl-bot-maze' ),
				),
				'reported_marker'      => array(
					'label' => __( 'AbuseIPDB reported marker present', 'jtzl-bot-maze' ),
					'value' => $status['reported_marker_present'] ? __( 'Yes', 'jtzl-bot-maze' ) : __( 'No', 'jtzl-bot-maze' ),
				),
				'backfill_pending'     => array(
					'label' => __( 'Backfill pending', 'jtzl-bot-maze' ),
					'value' => $status['pending'] ? __( 'Yes', 'jtzl-bot-maze' ) : __( 'No', 'jtzl-bot-maze' ),
				),
			),
		);

		return $info;
	}

	/**
	 * Handle the manual retry admin-post request.
	 *
	 * Verifies nonce and capability, re-runs the migration, then redirects
	 * back to Site Health.
	 *
	 * @return void
	 * @since 1.4.0
	 */
	public function handle_retry(): void {
		check_admin_referer( self::RETRY_NONCE );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'jtzl-bot-maze' ), 403 );
		}

		$this->migrator->run();

		// If the migration still didn't fully land (e.g. schema fixed but the
		// backfill is now pending), make sure the background retry is scheduled
		// so it self-heals without further manual clicks.
		if ( $this->migrator->is_incomplete() && ! wp_next_scheduled( Migrator::BACKFILL_RETRY_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', Migrator::BACKFILL_RETRY_HOOK );
		}

		wp_safe_redirect( admin_url( 'site-health.php' ) );
		exit; // @codeCoverageIgnore
	}

	/**
	 * Build a Site Health result array.
	 *
	 * @param string $status  One of 'good', 'recommended', 'critical'.
	 * @param string $label   Short human-readable label (no HTML).
	 * @param string $description HTML description shown in the expanded card.
	 * @return array<string, mixed>
	 * @since 1.4.0
	 */
	private function make_result( string $status, string $label, string $description ): array {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Bot Maze', 'jtzl-bot-maze' ),
				'color' => 'critical' === $status ? 'red' : ( 'recommended' === $status ? 'orange' : 'blue' ),
			),
			'description' => $description,
			'actions'     => '',
			'test'        => 'jtzl_bm_migration',
		);
	}

	/**
	 * Return HTML for the one-click retry form button.
	 *
	 * @return string
	 * @since 1.4.0
	 */
	private function retry_action_html(): string {
		// The nonce field HTML is passed as a positional argument (%2$s), never
		// concatenated into the format string: it carries an esc_url() referer
		// whose percent-encoded sequences (e.g. %2F) would otherwise be parsed
		// by sprintf as conversion specifiers and break the render.
		return sprintf(
			'<form method="post" action="%1$s" style="margin-top:8px">'
			. '%2$s'
			. '<input type="hidden" name="action" value="%3$s">'
			. '<button type="submit" class="button button-secondary">%4$s</button>'
			. '</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( self::RETRY_NONCE, '_wpnonce', true, false ),
			esc_attr( self::RETRY_ACTION ),
			esc_html__( 'Retry upgrade', 'jtzl-bot-maze' )
		);
	}
}
