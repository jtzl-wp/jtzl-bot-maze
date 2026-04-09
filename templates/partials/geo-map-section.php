<?php
/**
 * Geographic Map Section Partial
 *
 * Renders a Leaflet choropleth map section with country data.
 * Include this partial from admin templates that need the map.
 *
 * @package JTZL\Bot_Be_Gone
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
<div class="jtzl-bbg-section">
	<h2><?php echo esc_html__( 'Bot Activity by Country', 'jtzl-bot-be-gone' ); ?></h2>
	<div id="jtzl-bbg-map" class="jtzl-bbg-map-container"
		data-countries='<?php echo esc_attr( wp_json_encode( empty( $countries ) ? new stdClass() : $countries ) ); ?>'>
	</div>
	<div id="jtzl-bbg-map-legend" class="jtzl-bbg-map-legend"></div>
</div>
