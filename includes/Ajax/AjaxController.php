<?php
/**
 * AJAX Controller
 *
 * @package JTZL\Bot_Labyrinth
 * @since 1.0.0
 */

namespace JTZL\Bot_Labyrinth\Ajax;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use DI\Container;

/**
 * Admin AJAX endpoint handlers.
 *
 * Owns the business logic for admin-panel AJAX requests. Nonce and
 * capability checks are the responsibility of the caller in the main
 * plugin file — this class only runs after auth has already passed.
 *
 * @since 1.0.0
 */
class AjaxController {

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
	 * @param Container $container The DI container instance.
	 * @since 1.0.0
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Handle regenerate trap maze AJAX request.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function handle_regenerate(): void {
		try {
			$generator = $this->container->get( 'JTZL\Bot_Labyrinth\Service\TrapPageGenerator' );
			$count     = $generator->regenerate();
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is appropriate here.
			error_log( 'JTZL Bot Labyrinth — Bot-Be-Gone: Regenerate error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while regenerating the trap maze.', 'jtzl-bot-labyrinth' ),
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of trap pages generated */
					__( 'Trap maze regenerated with %d trap pages.', 'jtzl-bot-labyrinth' ),
					$count
				),
				'count'   => $count,
			)
		);
	}

	/**
	 * Handle refresh analytics AJAX request.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function handle_refresh_analytics(): void {
		try {
			$collector = $this->container->get( 'JTZL\Bot_Labyrinth\Service\AnalyticsCollector' );
			$summary   = $collector->get_summary_stats();
			$chart     = $collector->get_chart_data();
			$bots      = $collector->get_top_bots();
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is appropriate here.
			error_log( 'JTZL Bot Labyrinth — Bot-Be-Gone: Analytics refresh error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while refreshing analytics.', 'jtzl-bot-labyrinth' ),
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'summary'    => $summary,
				'chart_data' => $chart,
				'top_bots'   => $bots,
			)
		);
	}

	/**
	 * Handle geographic data AJAX request.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function handle_geo_data(): void {
		try {
			$collector = $this->container->get( 'JTZL\Bot_Labyrinth\Service\AnalyticsCollector' );
			$countries = $collector->get_visits_by_country();
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is appropriate here.
			error_log( 'JTZL Bot Labyrinth — Bot-Be-Gone: Geo data error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while loading geographic data.', 'jtzl-bot-labyrinth' ),
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'countries' => empty( $countries ) ? new \stdClass() : $countries,
				'enabled'   => 'none' !== (string) get_option( 'jtzl_bl_geo_provider', 'none' ),
			)
		);
	}
}
