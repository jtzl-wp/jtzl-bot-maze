<?php
/**
 * Geographic Map Section Partial
 *
 * Renders a Leaflet choropleth map section with country data.
 * Include this partial from admin templates that need the map.
 *
 * @package Labyrinth
 *
 * @var array $countries   Country visit counts keyed by alpha-2 code.
 * @var bool  $geo_enabled Whether geographic tracking is enabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="lby-section">
	<h2><?php echo esc_html__( 'Bot Activity by Country', 'labyrinth' ); ?></h2>
	<?php if ( ! $geo_enabled ) : ?>
		<p class="description">
			<?php
			printf(
				/* translators: %s: URL to settings page */
				esc_html__( 'Geographic tracking is disabled. Enable it on the %s to start collecting country data.', 'labyrinth' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=labyrinth-settings' ) ) . '">' . esc_html__( 'Settings page', 'labyrinth' ) . '</a>'
			);
			?>
		</p>
	<?php endif; ?>
	<div id="lby-map" class="lby-map-container"
		data-countries='<?php echo esc_attr( wp_json_encode( empty( $countries ) ? new stdClass() : $countries ) ); ?>'>
	</div>
	<div id="lby-map-legend" class="lby-map-legend"></div>
</div>
