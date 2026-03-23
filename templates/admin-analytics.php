<?php
/**
 * Admin Analytics Dashboard Template
 *
 * @package Labyrinth
 *
 * @var array $summary    Summary statistics.
 * @var array $chart_data Chart data with 'labels' and 'values'.
 * @var array $top_bots   Top bots by score.
 * @var array $activity   Recent trap visit activity.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap lby-analytics-wrap">
	<h1><?php echo esc_html__( 'Labyrinth Analytics', 'labyrinth' ); ?></h1>

	<div id="lby-analytics-app"
		data-chart='<?php echo esc_attr( wp_json_encode( $chart_data ) ); ?>'
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'lby_admin_action' ) ); ?>"
		data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">

		<!-- Summary Cards -->
		<div class="lby-summary-cards">
			<div class="lby-card">
				<h3><?php echo esc_html__( 'Active Trap Pages', 'labyrinth' ); ?></h3>
				<span class="lby-card-value" id="lby-stat-trap-pages"><?php echo esc_html( number_format_i18n( $summary['total_trap_pages'] ) ); ?></span>
			</div>
			<div class="lby-card">
				<h3><?php echo esc_html__( 'Trap Visits (7 Days)', 'labyrinth' ); ?></h3>
				<span class="lby-card-value" id="lby-stat-recent-visits"><?php echo esc_html( number_format_i18n( $summary['visits_last_7_days'] ) ); ?></span>
			</div>
			<div class="lby-card<?php echo $summary['blocked_bots'] > 0 ? ' lby-card-alert' : ''; ?>">
				<h3><?php echo esc_html__( 'Bots Blocked / Detected', 'labyrinth' ); ?></h3>
				<span class="lby-card-value" id="lby-stat-bot-ratio">
					<span id="lby-stat-blocked-bots"><?php echo esc_html( number_format_i18n( $summary['blocked_bots'] ) ); ?></span>
					<span class="lby-ratio-separator"> / </span>
					<span id="lby-stat-total-bots"><?php echo esc_html( number_format_i18n( $summary['total_bots'] ) ); ?></span>
				</span>
			</div>
		</div>

		<!-- Chart -->
		<div class="lby-section">
			<h2><?php echo esc_html__( 'Trap Visits Over Time', 'labyrinth' ); ?></h2>
			<div class="lby-chart-container" id="lby-chart">
				<!-- Chart rendered by JS -->
			</div>
		</div>

		<!-- Top Bots -->
		<div class="lby-section">
			<h2>
				<?php echo esc_html__( 'Top Bots by Score', 'labyrinth' ); ?>
				<button type="button" class="button button-small lby-refresh-btn" id="lby-refresh-btn">
					<?php echo esc_html__( 'Refresh', 'labyrinth' ); ?>
				</button>
			</h2>
			<table class="widefat striped lby-top-bots-table" id="lby-top-bots-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'IP Address', 'labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'User Agent', 'labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'Score', 'labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'Visits', 'labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'Max Depth', 'labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'Last Seen', 'labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'labyrinth' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $top_bots ) ) : ?>
						<tr><td colspan="7"><?php echo esc_html__( 'No bot activity detected yet.', 'labyrinth' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $top_bots as $bot ) : ?>
						<tr>
							<td><code><?php echo esc_html( $bot['ip_address'] ); ?></code></td>
							<td><?php echo esc_html( $bot['user_agent'] ); ?></td>
							<td><strong><?php echo esc_html( number_format( $bot['score'], 1 ) ); ?></strong></td>
							<td><?php echo esc_html( $bot['trap_visits_count'] ); ?></td>
							<td><?php echo esc_html( $bot['max_depth_reached'] ); ?></td>
							<td><?php echo esc_html( $bot['last_seen_at'] ); ?></td>
							<td>
								<?php if ( $bot['is_blocked'] ) : ?>
									<span class="lby-badge lby-badge-blocked"><?php echo esc_html__( 'Blocked', 'labyrinth' ); ?></span>
								<?php else : ?>
									<span class="lby-badge lby-badge-tracking"><?php echo esc_html__( 'Tracking', 'labyrinth' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- Recent Activity -->
		<div class="lby-section">
			<h2><?php echo esc_html__( 'Recent Activity', 'labyrinth' ); ?></h2>
			<table class="widefat striped" id="lby-activity-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Time', 'labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'IP Address', 'labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'User Agent', 'labyrinth' ); ?></th>
						<th><?php echo esc_html__( 'Depth', 'labyrinth' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $activity ) ) : ?>
						<tr><td colspan="4"><?php echo esc_html__( 'No recent activity.', 'labyrinth' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $activity as $visit ) : ?>
						<tr>
							<td><?php echo esc_html( $visit['visited_at'] ); ?></td>
							<td><code><?php echo esc_html( $visit['ip_address'] ); ?></code></td>
							<td class="lby-ua-cell"><?php echo esc_html( $visit['user_agent'] ); ?></td>
							<td><?php echo esc_html( $visit['depth_reached'] ); ?></td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

	</div>
</div>
