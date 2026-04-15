<?php
/**
 * Admin Blocked Bots Listing Template
 *
 * @package JTZL\Bot_Labyrinth
 *
 * @var array $bots          Blocked bot data arrays.
 * @var int   $total_blocked True total blocked bot count.
 * @var array $countries     Country visit counts keyed by alpha-2 code.
 * @var bool  $geo_enabled   Whether geographic tracking is enabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Blocked Bots', 'jtzl-bot-labyrinth' ); ?></h1>
	<p class="description">
		<?php echo esc_html__( 'Bots that have exceeded the suspicion score threshold and are currently blocked.', 'jtzl-bot-labyrinth' ); ?>
	</p>

	<div class="jtzl-bl-table-wrap">
	<table class="widefat striped jtzl-bl-top-bots-table" style="margin-top: 16px;">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'IP Address', 'jtzl-bot-labyrinth' ); ?></th>
				<th><?php echo esc_html__( 'Country', 'jtzl-bot-labyrinth' ); ?></th>
				<th><?php echo esc_html__( 'User Agent', 'jtzl-bot-labyrinth' ); ?></th>
				<th><?php echo esc_html__( 'Score', 'jtzl-bot-labyrinth' ); ?></th>
				<th><?php echo esc_html__( 'Visits', 'jtzl-bot-labyrinth' ); ?></th>
				<th><?php echo esc_html__( 'Max Depth', 'jtzl-bot-labyrinth' ); ?></th>
				<th><?php echo esc_html__( 'First Seen', 'jtzl-bot-labyrinth' ); ?></th>
				<th><?php echo esc_html__( 'Last Seen', 'jtzl-bot-labyrinth' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $bots ) ) : ?>
				<tr><td colspan="8"><?php echo esc_html__( 'No blocked bots yet. Bots are blocked when their suspicion score exceeds the configured threshold.', 'jtzl-bot-labyrinth' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $bots as $bot ) : ?>
				<tr>
					<td><code><?php echo esc_html( $bot['ip_address'] ); ?></code></td>
					<td><?php echo esc_html( $bot['country_name'] ); ?></td>
					<td class="jtzl-bl-ua-cell"><?php echo esc_html( $bot['user_agent'] ); ?></td>
					<td><strong><?php echo esc_html( number_format( $bot['score'], 1 ) ); ?></strong></td>
					<td><?php echo esc_html( $bot['trap_visits_count'] ); ?></td>
					<td><?php echo esc_html( $bot['max_depth_reached'] ); ?></td>
					<td><?php echo esc_html( $bot['first_seen_at'] ); ?></td>
					<td><?php echo esc_html( $bot['last_seen_at'] ); ?></td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	</div>

	<?php require JTZL_BL_PLUGIN_DIR . 'templates/partials/geo-map-section.php'; ?>

	<p style="margin-top: 12px;">
		<?php
		$shown = count( $bots );
		if ( $shown < $total_blocked ) {
			printf(
				/* translators: 1: number of bots shown, 2: total number of blocked bots */
				esc_html__( 'Showing %1$d of %2$d blocked bots', 'jtzl-bot-labyrinth' ),
				(int) $shown,
				(int) $total_blocked
			);
		} else {
			printf(
				/* translators: %d: total number of blocked bots */
				esc_html__( 'Total: %d blocked bots', 'jtzl-bot-labyrinth' ),
				(int) $total_blocked
			);
		}
		?>
	</p>
</div>
