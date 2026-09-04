<?php
/**
 * Admin Blocked Bots Listing Template
 *
 * @package JTZL\Bot_Maze
 *
 * @var \JTZL\Bot_Maze\Admin\BlockedBotsListTable $list_table  Prepared list table.
 * @var array $countries   Country visit counts keyed by alpha-2 code.
 * @var bool  $geo_enabled Whether geographic tracking is enabled.
 * @var \JTZL\Bot_Maze\Admin\AbuseIpDbBackfillAction $backfill_action AbuseIPDB catch-up control.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Blocked Bots', 'jtzl-bot-maze' ); ?></h1>
	<p class="description">
		<?php echo esc_html__( 'Bots that have exceeded the suspicion score threshold and are currently blocked. Click a column heading to sort, click an IP address or user agent to filter, or use the search box (matches IP and user agent) to narrow further.', 'jtzl-bot-maze' ); ?>
	</p>

	<?php $backfill_action->render_button(); ?>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( \JTZL\Bot_Maze\Admin\BlockedBotsListTable::PAGE_SLUG ); ?>" />
		<?php
		$list_table->search_box( esc_html__( 'Search blocked bots', 'jtzl-bot-maze' ), 'jtzl-bm-blocked-search' );
		$list_table->display();
		?>
	</form>

	<?php require JTZL_BM_PLUGIN_DIR . 'templates/partials/geo-map-section.php'; ?>
</div>
