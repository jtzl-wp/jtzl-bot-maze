/**
 * Utility functions for the Leaflet choropleth map.
 */

/**
 * Color scale for the choropleth (5 steps, light-to-dark blue).
 */
export const COLOR_SCALE = [
	'#dceefb',
	'#90c4e4',
	'#4a9bc7',
	'#2271b1',
	'#0a4b78',
];

/**
 * Get color for a visit count based on quantile thresholds.
 */
export function getColor(count: number, thresholds: number[]): string {
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
export function computeThresholds(values: number[]): number[] {
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
 * Resolve a country name from code, feature properties, or fallback.
 */
export function resolveCountryName(
	alpha2: string,
	countryNames: Record<string, string>,
	featureProperties?: { name?: string }
): string {
	return (
		countryNames[alpha2] ||
		featureProperties?.name ||
		'Unknown'
	);
}
