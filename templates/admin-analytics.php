<?php
/**
 * Admin Analytics Dashboard Template
 *
 * @package JTZL\Bot_Be_Gone
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
<div class="wrap jtzl-bbg-analytics-wrap">
	<h1><?php echo esc_html__( 'JTZL\'s Bot-be-Gone Analytics', 'jtzl-bot-be-gone' ); ?></h1>

	<div id="jtzl-bbg-analytics-app"
		data-chart='<?php echo esc_attr( wp_json_encode( $chart_data ) ); ?>'
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'jtzl_bbg_admin_action' ) ); ?>"
		data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">

		<!-- Summary Cards -->
		<div class="jtzl-bbg-summary-cards">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jtzl-bot-be-gone-trap-pages' ) ); ?>" class="jtzl-bbg-card jtzl-bbg-card-link">
				<h3><?php echo esc_html__( 'Active Trap Pages', 'jtzl-bot-be-gone' ); ?></h3>
				<span class="jtzl-bbg-card-value" id="jtzl-bbg-stat-trap-pages"><?php echo esc_html( number_format_i18n( $summary['total_trap_pages'] ) ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jtzl-bot-be-gone-recent-activity' ) ); ?>" class="jtzl-bbg-card jtzl-bbg-card-link">
				<h3><?php echo esc_html__( 'Recent Activity (7 Days)', 'jtzl-bot-be-gone' ); ?></h3>
				<span class="jtzl-bbg-card-value" id="jtzl-bbg-stat-recent-visits"><?php echo esc_html( number_format_i18n( $summary['all_visits_last_7_days'] ) ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jtzl-bot-be-gone-blocked-bots' ) ); ?>" class="jtzl-bbg-card jtzl-bbg-card-link<?php echo $summary['blocked_bots'] > 0 ? ' jtzl-bbg-card-alert' : ''; ?>">
				<h3><?php echo esc_html__( 'Bots Blocked / Detected', 'jtzl-bot-be-gone' ); ?></h3>
				<span class="jtzl-bbg-card-value" id="jtzl-bbg-stat-bot-ratio">
					<span id="jtzl-bbg-stat-blocked-bots"><?php echo esc_html( number_format_i18n( $summary['blocked_bots'] ) ); ?></span>
					<span class="jtzl-bbg-ratio-separator"> / </span>
					<span id="jtzl-bbg-stat-total-bots"><?php echo esc_html( number_format_i18n( $summary['total_bots'] ) ); ?></span>
				</span>
			</a>
		</div>

		<!-- Chart -->
		<div class="jtzl-bbg-section">
			<h2><?php echo esc_html__( 'Trap Visits Over Time', 'jtzl-bot-be-gone' ); ?></h2>
			<div class="jtzl-bbg-chart-container" id="jtzl-bbg-chart">
				<!-- Chart rendered by JS -->
			</div>
		</div>

		<!-- Top Bots -->
		<div class="jtzl-bbg-section">
			<h2>
				<?php echo esc_html__( 'Top Bots by Score', 'jtzl-bot-be-gone' ); ?>
				<button type="button" class="button button-small jtzl-bbg-refresh-btn" id="jtzl-bbg-refresh-btn">
					<?php echo esc_html__( 'Refresh', 'jtzl-bot-be-gone' ); ?>
				</button>
			</h2>
			<div class="jtzl-bbg-table-wrap">
			<table class="widefat striped jtzl-bbg-top-bots-table" id="jtzl-bbg-top-bots-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'IP Address', 'jtzl-bot-be-gone' ); ?></th>
						<th><?php echo esc_html__( 'Country', 'jtzl-bot-be-gone' ); ?></th>
						<th><?php echo esc_html__( 'User Agent', 'jtzl-bot-be-gone' ); ?></th>
						<th><?php echo esc_html__( 'Score', 'jtzl-bot-be-gone' ); ?></th>
						<th><?php echo esc_html__( 'Visits', 'jtzl-bot-be-gone' ); ?></th>
						<th><?php echo esc_html__( 'Max Depth', 'jtzl-bot-be-gone' ); ?></th>
						<th><?php echo esc_html__( 'Last Seen', 'jtzl-bot-be-gone' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'jtzl-bot-be-gone' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $top_bots ) ) : ?>
						<tr><td colspan="8"><?php echo esc_html__( 'No bot activity detected yet.', 'jtzl-bot-be-gone' ); ?></td></tr>
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
									<span class="jtzl-bbg-badge jtzl-bbg-badge-blocked"><?php echo esc_html__( 'Blocked', 'jtzl-bot-be-gone' ); ?></span>
								<?php else : ?>
									<span class="jtzl-bbg-badge jtzl-bbg-badge-tracking"><?php echo esc_html__( 'Tracking', 'jtzl-bot-be-gone' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			</div>
		</div>

		<?php require JTZL_BBG_PLUGIN_DIR . 'templates/partials/geo-map-section.php'; ?>

	</div>
</div>
