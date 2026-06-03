<?php
/**
 * Admin Recent Activity Page Template
 *
 * @package JTZL\Bot_Maze
 *
 * @var array $activity      Visit data arrays.
 * @var int   $current_page  Current page number.
 * @var int   $total_pages   Total number of pages.
 * @var int   $total_visits  Total visit count for the period.
 * @var int   $per_page      Items per page.
 * @var array $countries     Country visit counts keyed by alpha-2 code.
 * @var bool  $geo_enabled   Whether geographic tracking is enabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Recent Activity (7 Days)', 'jtzl-bot-maze' ); ?></h1>
	<p class="description">
		<?php echo esc_html__( 'Trap page visits from the last 7 days. Only bots that follow hidden trap links generate visits.', 'jtzl-bot-maze' ); ?>
	</p>

	<?php if ( $total_pages > 1 ) : ?>
	<div class="tablenav top">
		<div class="tablenav-pages">
			<span class="displaying-num">
				<?php
				printf(
					/* translators: %s: number of visits */
					esc_html( _n( '%s visit', '%s visits', $total_visits, 'jtzl-bot-maze' ) ),
					(int) $total_visits
				);
				?>
			</span>
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'total'     => $total_pages,
						'current'   => $current_page,
					)
				)
			);
			?>
		</div>
	</div>
	<?php endif; ?>

	<div class="jtzl-bm-table-wrap">
	<table class="widefat striped" style="margin-top: 16px;">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Time', 'jtzl-bot-maze' ); ?></th>
				<th><?php echo esc_html__( 'IP Address', 'jtzl-bot-maze' ); ?></th>
				<th><?php echo esc_html__( 'Country', 'jtzl-bot-maze' ); ?></th>
				<th><?php echo esc_html__( 'User Agent', 'jtzl-bot-maze' ); ?></th>
				<th><?php echo esc_html__( 'Depth', 'jtzl-bot-maze' ); ?></th>
				<th><?php echo esc_html__( 'Type', 'jtzl-bot-maze' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $activity ) ) : ?>
				<tr><td colspan="6"><?php echo esc_html__( 'No activity in the last 7 days.', 'jtzl-bot-maze' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $activity as $visit ) : ?>
				<tr>
					<td><?php echo esc_html( $visit['visited_at'] ); ?></td>
					<td><code><?php echo esc_html( $visit['ip_address'] ); ?></code></td>
					<td><?php echo esc_html( $visit['country_name'] ); ?></td>
					<td class="jtzl-bm-ua-cell"><?php echo esc_html( $visit['user_agent'] ); ?></td>
					<td><?php echo esc_html( $visit['depth_reached'] ); ?></td>
					<td>
						<?php if ( 'blocked_visit' === $visit['request_type'] ) : ?>
							<span class="jtzl-bm-badge jtzl-bm-badge-blocked"><?php echo esc_html__( 'Blocked', 'jtzl-bot-maze' ); ?></span>
						<?php elseif ( 'crawler_exempted' === $visit['request_type'] ) : ?>
							<span class="jtzl-bm-badge jtzl-bm-badge-exempted"><?php echo esc_html__( 'Exempted', 'jtzl-bot-maze' ); ?></span>
						<?php else : ?>
							<span class="jtzl-bm-badge jtzl-bm-badge-tracking"><?php echo esc_html__( 'Trap Visit', 'jtzl-bot-maze' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	</div>

	<?php require JTZL_BM_PLUGIN_DIR . 'templates/partials/geo-map-section.php'; ?>

	<?php if ( $total_pages > 1 ) : ?>
	<div class="tablenav bottom">
		<div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'total'     => $total_pages,
						'current'   => $current_page,
					)
				)
			);
			?>
		</div>
	</div>
	<?php endif; ?>
</div>
