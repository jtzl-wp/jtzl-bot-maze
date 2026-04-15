<?php
/**
 * Admin Analytics Dashboard Template
 *
 * @package JTZL\Bot_Labyrinth
 *
 * @var array $summary     Summary statistics.
 * @var array $chart_data  Chart data with 'labels' and 'values'.
 * @var array $top_bots    Top bots by score.
 * @var array $countries   Country visit counts keyed by alpha-2 code.
 * @var bool  $geo_enabled Whether geographic tracking is enabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap jtzl-bl-analytics-wrap">
	<h1><?php echo esc_html__( 'JTZL\'s Bot Labyrinth — Bot-Be-Gone Analytics', 'jtzl-bot-labyrinth' ); ?></h1>

	<div id="jtzl-bl-analytics-app"
		data-chart='<?php echo esc_attr( wp_json_encode( $chart_data ) ); ?>'
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'jtzl_bl_admin_action' ) ); ?>"
		data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">

		<!-- Summary Cards -->
		<div class="jtzl-bl-summary-cards">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jtzl-bot-labyrinth-trap-pages' ) ); ?>" class="jtzl-bl-card jtzl-bl-card-link">
				<h3><?php echo esc_html__( 'Active Trap Pages', 'jtzl-bot-labyrinth' ); ?></h3>
				<span class="jtzl-bl-card-value" id="jtzl-bl-stat-trap-pages"><?php echo esc_html( number_format_i18n( $summary['total_trap_pages'] ) ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jtzl-bot-labyrinth-recent-activity' ) ); ?>" class="jtzl-bl-card jtzl-bl-card-link">
				<h3><?php echo esc_html__( 'Recent Activity (7 Days)', 'jtzl-bot-labyrinth' ); ?></h3>
				<span class="jtzl-bl-card-value" id="jtzl-bl-stat-recent-visits"><?php echo esc_html( number_format_i18n( $summary['all_visits_last_7_days'] ) ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jtzl-bot-labyrinth-blocked-bots' ) ); ?>" class="jtzl-bl-card jtzl-bl-card-link<?php echo $summary['blocked_bots'] > 0 ? ' jtzl-bl-card-alert' : ''; ?>">
				<h3><?php echo esc_html__( 'Bots Blocked / Detected', 'jtzl-bot-labyrinth' ); ?></h3>
				<span class="jtzl-bl-card-value" id="jtzl-bl-stat-bot-ratio">
					<span id="jtzl-bl-stat-blocked-bots"><?php echo esc_html( number_format_i18n( $summary['blocked_bots'] ) ); ?></span>
					<span class="jtzl-bl-ratio-separator"> / </span>
					<span id="jtzl-bl-stat-total-bots"><?php echo esc_html( number_format_i18n( $summary['total_bots'] ) ); ?></span>
				</span>
			</a>
		</div>

		<!-- Chart -->
		<div class="jtzl-bl-section">
			<h2><?php echo esc_html__( 'Trap Visits Over Time', 'jtzl-bot-labyrinth' ); ?></h2>
			<div class="jtzl-bl-chart-container" id="jtzl-bl-chart">
				<!-- Chart rendered by JS -->
			</div>
		</div>

		<!-- Top Bots -->
		<div class="jtzl-bl-section">
			<h2>
				<?php echo esc_html__( 'Top Bots by Score', 'jtzl-bot-labyrinth' ); ?>
				<button type="button" class="button button-small jtzl-bl-refresh-btn" id="jtzl-bl-refresh-btn">
					<?php echo esc_html__( 'Refresh', 'jtzl-bot-labyrinth' ); ?>
				</button>
			</h2>
			<div class="jtzl-bl-table-wrap">
			<table class="widefat striped jtzl-bl-top-bots-table" id="jtzl-bl-top-bots-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'IP Address', 'jtzl-bot-labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'Country', 'jtzl-bot-labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'User Agent', 'jtzl-bot-labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'Score', 'jtzl-bot-labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'Visits', 'jtzl-bot-labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'Max Depth', 'jtzl-bot-labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'Last Seen', 'jtzl-bot-labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'jtzl-bot-labyrinth' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $top_bots ) ) : ?>
						<tr><td colspan="8"><?php echo esc_html__( 'No bot activity detected yet.', 'jtzl-bot-labyrinth' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $top_bots as $bot ) : ?>
						<tr>
							<td><code><?php echo esc_html( $bot['ip_address'] ); ?></code></td>
							<td><?php echo esc_html( $bot['country_name'] ); ?></td>
							<td><?php echo esc_html( $bot['user_agent'] ); ?></td>
							<td><strong><?php echo esc_html( number_format( $bot['score'], 1 ) ); ?></strong></td>
							<td><?php echo esc_html( $bot['trap_visits_count'] ); ?></td>
							<td><?php echo esc_html( $bot['max_depth_reached'] ); ?></td>
							<td><?php echo esc_html( $bot['last_seen_at'] ); ?></td>
							<td>
								<?php if ( $bot['is_blocked'] ) : ?>
									<span class="jtzl-bl-badge jtzl-bl-badge-blocked"><?php echo esc_html__( 'Blocked', 'jtzl-bot-labyrinth' ); ?></span>
								<?php else : ?>
									<span class="jtzl-bl-badge jtzl-bl-badge-tracking"><?php echo esc_html__( 'Tracking', 'jtzl-bot-labyrinth' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			</div>
		</div>

		<?php require JTZL_BL_PLUGIN_DIR . 'templates/partials/geo-map-section.php'; ?>

	</div>
</div>
