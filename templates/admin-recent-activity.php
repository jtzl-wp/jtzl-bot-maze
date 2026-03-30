<?php
/**
 * Admin Recent Activity Page Template
 *
 * @package Labyrinth
 *
 * @var array $activity      Visit data arrays.
 * @var int   $current_page  Current page number.
 * @var int   $total_pages   Total number of pages.
 * @var int   $total_visits  Total visit count for the period.
 * @var int   $per_page      Items per page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Recent Activity (7 Days)', 'labyrinth' ); ?></h1>
	<p class="description">
		<?php echo esc_html__( 'Trap page visits from the last 7 days. Only bots that follow hidden trap links generate visits.', 'labyrinth' ); ?>
	</p>

	<?php if ( $total_pages > 1 ) : ?>
	<div class="tablenav top">
		<div class="tablenav-pages">
			<span class="displaying-num">
				<?php
				printf(
					/* translators: %s: number of visits */
					esc_html( _n( '%s visit', '%s visits', $total_visits, 'labyrinth' ) ),
					(int) $total_visits
				);
				?>
			</span>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links returns safe HTML.
			echo paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
					'total'     => $total_pages,
					'current'   => $current_page,
				)
			);
			?>
		</div>
	</div>
	<?php endif; ?>

	<table class="widefat striped" style="margin-top: 16px;">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Time', 'labyrinth' ); ?></th>
				<th><?php echo esc_html__( 'IP Address', 'labyrinth' ); ?></th>
				<th><?php echo esc_html__( 'User Agent', 'labyrinth' ); ?></th>
				<th><?php echo esc_html__( 'Depth', 'labyrinth' ); ?></th>
				<th><?php echo esc_html__( 'Type', 'labyrinth' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $activity ) ) : ?>
				<tr><td colspan="5"><?php echo esc_html__( 'No activity in the last 7 days.', 'labyrinth' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $activity as $visit ) : ?>
				<tr>
					<td><?php echo esc_html( $visit['visited_at'] ); ?></td>
					<td><code><?php echo esc_html( $visit['ip_address'] ); ?></code></td>
					<td class="lby-ua-cell"><?php echo esc_html( $visit['user_agent'] ); ?></td>
					<td><?php echo esc_html( $visit['depth_reached'] ); ?></td>
					<td>
						<?php if ( 'blocked_visit' === $visit['request_type'] ) : ?>
							<span class="lby-badge lby-badge-blocked"><?php echo esc_html__( 'Blocked', 'labyrinth' ); ?></span>
						<?php elseif ( 'crawler_exempted' === $visit['request_type'] ) : ?>
							<span class="lby-badge lby-badge-exempted"><?php echo esc_html__( 'Exempted', 'labyrinth' ); ?></span>
						<?php else : ?>
							<span class="lby-badge lby-badge-tracking"><?php echo esc_html__( 'Trap Visit', 'labyrinth' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
	<div class="tablenav bottom">
		<div class="tablenav-pages">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links returns safe HTML.
			echo paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
					'total'     => $total_pages,
					'current'   => $current_page,
				)
			);
			?>
		</div>
	</div>
	<?php endif; ?>
</div>
