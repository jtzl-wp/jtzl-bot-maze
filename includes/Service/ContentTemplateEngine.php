<?php
/**
 * Content Template Engine
 *
 * Generates deterministic fake content from templates and seeds.
 *
 * @package JTZL\Bot_Be_Gone
 * @since 1.0.0
 */

namespace JTZL\Bot_Be_Gone\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Be_Gone\Template\ContentPool;

/**
 * Content Template Engine
 *
 * Selects templates and fills them with content using seed-based
 * deterministic randomization. Same seed always produces same output.
 *
 * @since 1.0.0
 */
class ContentTemplateEngine {

	/**
	 * Content pool instance.
	 *
	 * @var ContentPool
	 * @since 1.0.0
	 */
	private ContentPool $pool;

	/**
	 * Constructor.
	 *
	 * @param ContentPool $pool Content data pool.
	 * @since 1.0.0
	 */
	public function __construct( ContentPool $pool ) {
		$this->pool = $pool;
	}

	/**
	 * Generate a page title from seed.
	 *
	 * @param string $seed Randomization seed.
	 * @return string Generated title.
	 * @since 1.0.0
	 */
	public function generate_title( string $seed ): string {
		$this->seed_random( $seed . '_title' );

		$adjectives = $this->pool->get_adjectives();
		$topics     = $this->pool->get_topics();

		$adjective = $adjectives[ mt_rand( 0, count( $adjectives ) - 1 ) ]; // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		$topic     = $topics[ mt_rand( 0, count( $topics ) - 1 ) ]; // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand

		// Vary the title pattern.
		$pattern = mt_rand( 0, 3 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand

		switch ( $pattern ) {
			case 0:
				return $adjective . ' ' . $topic;
			case 1:
				return 'Guide to ' . $adjective . ' ' . $topic;
			case 2:
				$article = in_array( strtolower( $adjective[0] ), array( 'a', 'e', 'i', 'o', 'u' ), true ) ? 'An' : 'A';
				return $topic . ': ' . $article . ' ' . $adjective . ' Overview';
			default:
				return $adjective . ' ' . $topic . ' for Professionals';
		}
	}

	/**
	 * Generate a URL slug from seed.
	 *
	 * @param string $seed Randomization seed.
	 * @return string Generated slug (max 64 chars).
	 * @since 1.0.0
	 */
	public function generate_slug( string $seed ): string {
		$title = $this->generate_title( $seed );
		$slug  = sanitize_title( $title );

		// Append a short hash for uniqueness.
		$hash = substr( md5( $seed ), 0, 6 );
		$slug = substr( $slug, 0, 56 ) . '-' . $hash;

		return $slug;
	}

	/**
	 * Generate full page content from seed.
	 *
	 * @param string $seed Randomization seed.
	 * @return string Generated HTML content (500-2000 words).
	 * @since 1.0.0
	 */
	public function generate_content( string $seed ): string {
		$this->seed_random( $seed . '_content' );

		$templates  = $this->pool->get_templates();
		$template   = $templates[ mt_rand( 0, count( $templates ) - 1 ) ]; // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		$headings   = $this->pool->get_headings();
		$paragraphs = $this->pool->get_paragraphs();
		$list_items = $this->pool->get_list_items();

		$heading_index   = 0;
		$paragraph_index = 0;
		$list_index      = 0;

		// Shuffle arrays deterministically.
		$this->deterministic_shuffle( $headings );
		$this->deterministic_shuffle( $paragraphs );
		$this->deterministic_shuffle( $list_items );

		$html = '';

		foreach ( $template as $section_type ) {
			switch ( $section_type ) {
				case 'intro':
					$html .= '<p>' . esc_html( $paragraphs[ $paragraph_index % count( $paragraphs ) ] ) . '</p>' . "\n\n";
					++$paragraph_index;
					break;

				case 'h2':
					$html .= '<h2>' . esc_html( $headings[ $heading_index % count( $headings ) ] ) . '</h2>' . "\n\n";
					++$heading_index;
					break;

				case 'h3':
					$html .= '<h3>' . esc_html( $headings[ $heading_index % count( $headings ) ] ) . '</h3>' . "\n\n";
					++$heading_index;
					break;

				case 'p':
					$html .= '<p>' . esc_html( $paragraphs[ $paragraph_index % count( $paragraphs ) ] ) . '</p>' . "\n\n";
					++$paragraph_index;
					break;

				case 'ul':
					$count = mt_rand( 3, 6 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
					$html .= "<ul>\n";
					for ( $i = 0; $i < $count; $i++ ) {
						$html .= '<li>' . esc_html( $list_items[ $list_index % count( $list_items ) ] ) . "</li>\n";
						++$list_index;
					}
					$html .= "</ul>\n\n";
					break;

				case 'ol':
					$count = mt_rand( 3, 6 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
					$html .= "<ol>\n";
					for ( $i = 0; $i < $count; $i++ ) {
						$html .= '<li>' . esc_html( $list_items[ $list_index % count( $list_items ) ] ) . "</li>\n";
						++$list_index;
					}
					$html .= "</ol>\n\n";
					break;
			}
		}

		return trim( $html );
	}

	/**
	 * Generate a template ID for tracking which template was used.
	 *
	 * @param string $seed Randomization seed.
	 * @return string Template identifier.
	 * @since 1.0.0
	 */
	public function generate_template_id( string $seed ): string {
		$this->seed_random( $seed . '_content' );

		$templates = $this->pool->get_templates();
		$index     = mt_rand( 0, count( $templates ) - 1 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand

		return 'template_' . $index;
	}

	/**
	 * Seed the random number generator deterministically.
	 *
	 * @param string $seed Seed string.
	 * @return void
	 * @since 1.0.0
	 */
	private function seed_random( string $seed ): void {
		mt_srand( crc32( $seed ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand
	}

	/**
	 * Shuffle an array deterministically using the current mt_rand state.
	 *
	 * @param array $items Array to shuffle (modified in place).
	 * @return void
	 * @since 1.0.0
	 */
	private function deterministic_shuffle( array &$items ): void {
		$count = count( $items );
		for ( $i = $count - 1; $i > 0; $i-- ) {
			$j           = mt_rand( 0, $i ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$temp        = $items[ $i ];
			$items[ $i ] = $items[ $j ];
			$items[ $j ] = $temp;
		}
	}
}
