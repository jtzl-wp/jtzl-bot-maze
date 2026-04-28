<?php
/**
 * Geographic Map Section Partial
 *
 * Renders a Leaflet choropleth map section with country data.
 * Include this partial from admin templates that need the map.
 *
 * @package JTZL\Bot_Maze
 *
 * @var array $countries   Country visit counts keyed by alpha-2 code.
 * @var bool  $geo_enabled Whether geographic tracking is enabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $geo_enabled ) {
	return;
}
?>
<div class="jtzl-bm-section">
	<h2><?php echo esc_html__( 'Bot Activity by Country', 'jtzl-bot-maze' ); ?></h2>
	<div id="jtzl-bm-map" class="jtzl-bm-map-container"
		data-countries='<?php echo esc_attr( wp_json_encode( empty( $countries ) ? new stdClass() : $countries ) ); ?>'>
	</div>
	<div id="jtzl-bm-map-legend" class="jtzl-bm-map-legend"></div>
</div>
