<?php
/**
 * Admin Recent Activity Page Template
 *
 * @package JTZL\Bot_Maze
 *
 * @var \JTZL\Bot_Maze\Admin\RecentActivityListTable $list_table  Prepared list table.
 * @var array $countries   Country visit counts keyed by alpha-2 code.
 * @var bool  $geo_enabled Whether geographic tracking is enabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Recent Activity (7 Days)', 'jtzl-bot-maze' ); ?></h1>
	<p class="description">
		<?php echo esc_html__( 'Trap page visits from the last 7 days. Click a column heading to sort, click an IP address or user agent to filter, or use the search box to narrow further. The count above the table reflects the current filter.', 'jtzl-bot-maze' ); ?>
	</p>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( \JTZL\Bot_Maze\Admin\RecentActivityListTable::PAGE_SLUG ); ?>" />
		<?php
		$list_table->search_box( esc_html__( 'Search activity', 'jtzl-bot-maze' ), 'jtzl-bm-recent-search' );
		$list_table->display();
		?>
	</form>

	<?php require JTZL_BM_PLUGIN_DIR . 'templates/partials/geo-map-section.php'; ?>
</div>
