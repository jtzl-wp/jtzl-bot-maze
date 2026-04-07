/**
 * Labyrinth Geographic Map
 *
 * Renders a Leaflet choropleth map showing bot visit counts by country.
 */

import 'leaflet/dist/leaflet.css';
import './styles/admin-map.css';

import L from 'leaflet';
import { feature } from 'topojson-client';
import type { Topology, GeometryCollection } from 'topojson-specification';
import worldData from 'world-atlas/countries-110m.json';
import { numericToAlpha2, countryNames } from './data/country-codes';

declare const lbyMap: {
	ajaxUrl: string;
	nonce: string;
};

interface CountryData {
	[countryCode: string]: number;
}

/**
 * Color scale for the choropleth (5 steps, light-to-dark blue).
 */
const COLOR_SCALE = ['#dceefb', '#90c4e4', '#4a9bc7', '#2271b1', '#0a4b78'];
const NO_DATA_COLOR = '#e0e0e0';
const BORDER_COLOR = '#fff';

/**
 * Get color for a visit count based on quantile thresholds.
 */
function getColor(count: number, thresholds: number[]): string {
	for (let i = thresholds.length - 1; i >= 0; i--) {
		if (count >= thresholds[i]) {
			return COLOR_SCALE[i];
		}
	}
	return COLOR_SCALE[0];
}

/**
 * Compute quantile-based thresholds from visit counts.
 */
function computeThresholds(values: number[]): number[] {
	if (values.length === 0) {
		return [1, 2, 5, 10, 50];
	}

	const sorted = [...values].sort((a, b) => a - b);
	const quantiles = [0, 0.25, 0.5, 0.75, 0.95];

	return quantiles.map((q) => {
		const idx = Math.min(
			Math.floor(q * sorted.length),
			sorted.length - 1
		);
		return Math.max(1, sorted[idx]);
	});
}

/**
 * Build the legend below the map.
 */
function buildLegend(
	container: HTMLElement,
	thresholds: number[],
	hasData: boolean
): void {
	container.replaceChildren();

	if (!hasData) {
		return;
	}

	const lowLabel = document.createElement('span');
	lowLabel.className = 'lby-map-legend-label';
	lowLabel.textContent = 'Low';
	container.appendChild(lowLabel);

	for (let i = 0; i < COLOR_SCALE.length; i++) {
		const swatch = document.createElement('span');
		swatch.className = 'lby-map-legend-swatch';
		swatch.style.background = COLOR_SCALE[i];
		swatch.title = `>= ${thresholds[i]} visits`;
		container.appendChild(swatch);
	}

	const highLabel = document.createElement('span');
	highLabel.className = 'lby-map-legend-label';
	highLabel.textContent = 'High';
	container.appendChild(highLabel);

	const noDataSwatch = document.createElement('span');
	noDataSwatch.className = 'lby-map-legend-swatch';
	noDataSwatch.style.background = NO_DATA_COLOR;
	noDataSwatch.style.marginLeft = '12px';
	container.appendChild(noDataSwatch);

	const noDataLabel = document.createElement('span');
	noDataLabel.className = 'lby-map-legend-label';
	noDataLabel.textContent = 'No data';
	container.appendChild(noDataLabel);
}

/**
 * Initialize the Leaflet choropleth map.
 */
function initMap(): void {
	const mapContainer = document.getElementById('lby-map');
	const legendContainer = document.getElementById('lby-map-legend');

	if (!mapContainer) {
		return;
	}

	// Parse server-side data from the map container.
	let countries: CountryData = {};
	try {
		const raw = mapContainer.getAttribute('data-countries');
		if (raw) {
			countries = JSON.parse(raw);
		}
	} catch {
		// Invalid data — render empty map.
	}

	const values = Object.values(countries).filter((v) => v > 0);
	const thresholds = computeThresholds(values);
	const hasData = values.length > 0;

	// Create Leaflet map.
	const map = L.map(mapContainer, {
		center: [20, 0],
		zoom: 2,
		minZoom: 2,
		maxZoom: 6,
		worldCopyJump: true,
		attributionControl: false,
	});

	// Convert TopoJSON to GeoJSON.
	const topology = worldData as unknown as Topology;
	const geojsonData = feature(
		topology,
		topology.objects['countries'] as GeometryCollection
	);

	// Create choropleth layer.
	const geojsonLayer = L.geoJSON(
		geojsonData as unknown as GeoJSON.FeatureCollection,
		{
			style: (feature) => {
				const numericId = feature?.id as string;
				const alpha2 = numericToAlpha2[numericId] || '';
				const count = countries[alpha2] || 0;

				return {
					fillColor: count > 0 ? getColor(count, thresholds) : NO_DATA_COLOR,
					weight: 1,
					color: BORDER_COLOR,
					fillOpacity: 0.8,
				};
			},
			onEachFeature: (feature, layer) => {
				const numericId = feature.id as string;
				const alpha2 = numericToAlpha2[numericId] || '';
				const name =
					countryNames[alpha2] ||
					(feature.properties as { name?: string })?.name ||
					'Unknown';
				const count = countries[alpha2] || 0;

				layer.bindTooltip(
					`<div class="lby-map-tooltip"><strong>${name}</strong>${count > 0 ? `${count.toLocaleString()} visits` : 'No data'}</div>`,
					{ sticky: true }
				);

				layer.on({
					mouseover: (e: L.LeafletMouseEvent) => {
						const target = e.target as L.Path;
						target.setStyle({
							weight: 2,
							color: '#1d2327',
							fillOpacity: 0.95,
						});
					},
					mouseout: () => {
						geojsonLayer.resetStyle(layer);
					},
				});
			},
		}
	);

	geojsonLayer.addTo(map);

	// No-data overlay.
	if (!hasData) {
		const overlay = document.createElement('div');
		overlay.className = 'lby-map-no-data';
		overlay.textContent = 'No geographic data available yet.';
		mapContainer.style.position = 'relative';
		mapContainer.appendChild(overlay);
	}

	// Build legend.
	if (legendContainer) {
		buildLegend(legendContainer, thresholds, hasData);
	}
}

// Initialize when DOM is ready.
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initMap);
} else {
	initMap();
}
