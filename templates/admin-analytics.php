<?php
/**
 * Admin Analytics Dashboard Template
 *
 * @package JTZL\Bot_Maze
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
<div class="wrap jtzl-bm-analytics-wrap">
	<h1><?php echo esc_html__( 'JTZL\'s Bot Maze Analytics', 'jtzl-bot-maze' ); ?></h1>

	<div id="jtzl-bm-analytics-app"
		data-chart='<?php echo esc_attr( wp_json_encode( $chart_data ) ); ?>'
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'jtzl_bm_admin_action' ) ); ?>"
		data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">

		<!-- Summary Cards -->
		<div class="jtzl-bm-summary-cards">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jtzl-bot-maze-trap-pages' ) ); ?>" class="jtzl-bm-card jtzl-bm-card-link">
				<h3><?php echo esc_html__( 'Active Trap Pages', 'jtzl-bot-maze' ); ?></h3>
				<span class="jtzl-bm-card-value" id="jtzl-bm-stat-trap-pages"><?php echo esc_html( number_format_i18n( $summary['total_trap_pages'] ) ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jtzl-bot-maze-recent-activity' ) ); ?>" class="jtzl-bm-card jtzl-bm-card-link">
				<h3><?php echo esc_html__( 'Recent Activity (7 Days)', 'jtzl-bot-maze' ); ?></h3>
				<span class="jtzl-bm-card-value" id="jtzl-bm-stat-recent-visits"><?php echo esc_html( number_format_i18n( $summary['all_visits_last_7_days'] ) ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jtzl-bot-maze-blocked-bots' ) ); ?>" class="jtzl-bm-card jtzl-bm-card-link<?php echo $summary['blocked_bots'] > 0 ? ' jtzl-bm-card-alert' : ''; ?>">
				<h3><?php echo esc_html__( 'Bots Blocked / Detected', 'jtzl-bot-maze' ); ?></h3>
				<span class="jtzl-bm-card-value" id="jtzl-bm-stat-bot-ratio">
					<span id="jtzl-bm-stat-blocked-bots"><?php echo esc_html( number_format_i18n( $summary['blocked_bots'] ) ); ?></span>
					<span class="jtzl-bm-ratio-separator"> / </span>
					<span id="jtzl-bm-stat-total-bots"><?php echo esc_html( number_format_i18n( $summary['total_bots'] ) ); ?></span>
				</span>
			</a>
		</div>

		<!-- Chart -->
		<div class="jtzl-bm-section">
			<h2><?php echo esc_html__( 'Trap Visits Over Time', 'jtzl-bot-maze' ); ?></h2>
			<div class="jtzl-bm-chart-container" id="jtzl-bm-chart">
				<!-- Chart rendered by JS -->
			</div>
		</div>

		<!-- Top Bots -->
		<div class="jtzl-bm-section">
			<h2>
				<?php echo esc_html__( 'Top Bots by Score', 'jtzl-bot-maze' ); ?>
				<button type="button" class="button button-small jtzl-bm-refresh-btn" id="jtzl-bm-refresh-btn">
					<?php echo esc_html__( 'Refresh', 'jtzl-bot-maze' ); ?>
				</button>
			</h2>
			<div class="jtzl-bm-table-wrap">
			<table class="widefat striped jtzl-bm-top-bots-table" id="jtzl-bm-top-bots-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'IP Address', 'jtzl-bot-maze' ); ?></th>
						<th><?php echo esc_html__( 'Country', 'jtzl-bot-maze' ); ?></th>
						<th><?php echo esc_html__( 'User Agent', 'jtzl-bot-maze' ); ?></th>
						<th><?php echo esc_html__( 'Score', 'jtzl-bot-maze' ); ?></th>
						<th><?php echo esc_html__( 'Visits', 'jtzl-bot-maze' ); ?></th>
						<th><?php echo esc_html__( 'Max Depth', 'jtzl-bot-maze' ); ?></th>
						<th><?php echo esc_html__( 'Last Seen', 'jtzl-bot-maze' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'jtzl-bot-maze' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $top_bots ) ) : ?>
						<tr><td colspan="8"><?php echo esc_html__( 'No bot activity detected yet.', 'jtzl-bot-maze' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $top_bots as $bot ) : ?>
						<tr>
							<td><code><?php echo esc_html( $bot['ip_address'] ); ?></code></td>
							<td><?php echo esc_html( $bot['country_name'] ); ?></td>
							<td class="jtzl-bm-ua-cell">
								<?php
								$jtzl_bm_ua = $bot['user_agent'];
								require JTZL_BM_PLUGIN_DIR . 'templates/partials/ua-cell.php';
								?>
							</td>
							<td><strong><?php echo esc_html( number_format( $bot['score'], 1 ) ); ?></strong></td>
							<td><?php echo esc_html( $bot['trap_visits_count'] ); ?></td>
							<td><?php echo esc_html( $bot['max_depth_reached'] ); ?></td>
							<td><?php echo esc_html( $bot['last_seen_at'] ); ?></td>
							<td>
								<?php if ( $bot['is_blocked'] ) : ?>
									<span class="jtzl-bm-badge jtzl-bm-badge-blocked"><?php echo esc_html__( 'Blocked', 'jtzl-bot-maze' ); ?></span>
								<?php else : ?>
									<span class="jtzl-bm-badge jtzl-bm-badge-tracking"><?php echo esc_html__( 'Tracking', 'jtzl-bot-maze' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			</div>
		</div>

		<?php require JTZL_BM_PLUGIN_DIR . 'templates/partials/geo-map-section.php'; ?>

	</div>
</div>
