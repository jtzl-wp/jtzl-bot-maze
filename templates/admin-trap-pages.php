<?php
/**
 * Admin Trap Pages Listing Template
 *
 * @package JTZL\Bot_Labyrinth
 *
 * @var array  $pages        Active trap pages for the current page.
 * @var string $base_path    Trap URL base path.
 * @var int    $total_count  Total number of active trap pages.
 * @var int    $total_pages  Total number of pagination pages.
 * @var int    $current_page Current pagination page number.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Trap Pages', 'jtzl-bot-labyrinth' ); ?></h1>
	<p class="description">
		<?php echo esc_html__( 'These are the active trap pages in your trap maze. Admin visits to these links are not tracked or scored.', 'jtzl-bot-labyrinth' ); ?>
	</p>

	<table class="widefat striped" style="margin-top: 16px;">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Title', 'jtzl-bot-labyrinth' ); ?></th>
				<th><?php echo esc_html__( 'URL', 'jtzl-bot-labyrinth' ); ?></th>
				<th><?php echo esc_html__( 'Depth', 'jtzl-bot-labyrinth' ); ?></th>
				<th><?php echo esc_html__( 'Created', 'jtzl-bot-labyrinth' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $pages ) ) : ?>
				<tr><td colspan="4"><?php echo esc_html__( 'No active trap pages. Trap pages are generated automatically when the trap maze is enabled.', 'jtzl-bot-labyrinth' ); ?></td></tr>
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

	<div class="tablenav bottom" style="margin-top: 8px;">
		<div class="tablenav-pages">
			<span class="displaying-num">
				<?php
				printf(
					/* translators: %d: total number of active trap pages */
					esc_html__( '%d items', 'jtzl-bot-labyrinth' ),
					(int) $total_count
				);
				?>
			</span>
			<?php if ( $total_pages > 1 ) : ?>
				<span class="pagination-links">
					<?php
					$page_links = paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $total_pages,
							'current'   => $current_page,
						)
					);
					echo wp_kses_post( $page_links );
					?>
				</span>
			<?php endif; ?>
		</div>
	</div>
</div>
