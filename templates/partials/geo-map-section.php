<?php
/**
 * Geographic Map Section Partial
 *
 * Renders a Leaflet choropleth map section with country data.
 * Include this partial from admin templates that need the map.
 *
 * @package JTZL\Bot_Labyrinth
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
<div class="jtzl-bl-section">
	<h2><?php echo esc_html__( 'Bot Activity by Country', 'jtzl-bot-labyrinth' ); ?></h2>
	<div id="jtzl-bl-map" class="jtzl-bl-map-container"
		data-countries='<?php echo esc_attr( wp_json_encode( empty( $countries ) ? new stdClass() : $countries ) ); ?>'>
	</div>
	<div id="jtzl-bl-map-legend" class="jtzl-bl-map-legend"></div>
</div>
