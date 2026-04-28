<?php
/**
 * Country name resolver.
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze\Data;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Resolves ISO 3166-1 alpha-2 country codes to human-readable names.
 *
 * Uses the PHP intl extension when available, otherwise returns the raw code.
 *
 * @since 1.0.0
 */
class CountryNames {

	/**
	 * Get the display name for a country code.
	 *
	 * @param string $code ISO 3166-1 alpha-2 code (e.g. "US").
	 * @return string Country name or the raw code if intl is unavailable.
	 * @since 1.0.0
	 */
	public static function get( string $code ): string {
		if ( '' === $code ) {
			return '';
		}

		if ( class_exists( 'Locale' ) ) {
			$name = \Locale::getDisplayRegion( '_' . $code, 'en' );
			if ( '' !== $name && '_' . $code !== $name ) {
				return $name;
			}
		}

		return $code;
	}
}
