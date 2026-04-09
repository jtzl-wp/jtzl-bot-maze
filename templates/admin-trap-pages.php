<?php
/**
 * Admin Trap Pages Listing Template
 *
 * @package JTZL\Bot_Be_Gone
 *
 * @var array  $pages     Active trap pages.
 * @var string $base_path Trap URL base path.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Trap Pages', 'jtzl-bot-be-gone' ); ?></h1>
	<p class="description">
		<?php echo esc_html__( 'These are the active trap pages in your trap maze. Admin visits to these links are not tracked or scored.', 'jtzl-bot-be-gone' ); ?>
	</p>

	<table class="widefat striped" style="margin-top: 16px;">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Title', 'jtzl-bot-be-gone' ); ?></th>
				<th><?php echo esc_html__( 'URL', 'jtzl-bot-be-gone' ); ?></th>
				<th><?php echo esc_html__( 'Depth', 'jtzl-bot-be-gone' ); ?></th>
				<th><?php echo esc_html__( 'Created', 'jtzl-bot-be-gone' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $pages ) ) : ?>
				<tr><td colspan="4"><?php echo esc_html__( 'No active trap pages. Trap pages are generated automatically when the trap maze is enabled.', 'jtzl-bot-be-gone' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $pages as $trap_page ) : ?>
				<tr>
					<td><?php echo esc_html( $trap_page->title ); ?></td>
					<td>
						<a href="<?php echo esc_url( home_url( '/' . $base_path . '/' . $trap_page->slug ) ); ?>" target="_blank">
							<code>/<?php echo esc_html( $base_path . '/' . $trap_page->slug ); ?></code>
						</a>
					</td>
					<td><?php echo esc_html( $trap_page->depth_level ); ?></td>
					<td><?php echo esc_html( $trap_page->created_at->format( 'Y-m-d H:i' ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<p style="margin-top: 12px;">
		<?php
		printf(
			/* translators: %d: total number of active trap pages */
			esc_html__( 'Total: %d active trap pages', 'jtzl-bot-be-gone' ),
			count( $pages )
		);
		?>
	</p>
</div>
