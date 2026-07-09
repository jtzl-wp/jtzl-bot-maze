<?php
/**
 * User-Agent Table Cell Partial
 *
 * Renders the inner content of a User Agent table cell: a truncated text span
 * (click to expand) plus a copy-to-clipboard button. The full string is always
 * present in the DOM so it can be read via hover tooltip, expanded inline, or
 * copied — addressing the truncated-UA limitation of the listing tables.
 *
 * @package JTZL\Bot_Maze
 *
 * @var string $jtzl_bm_ua Full user-agent string for this row.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jtzl_bm_ua = isset( $jtzl_bm_ua ) ? (string) $jtzl_bm_ua : '';
?>
<span class="jtzl-bm-ua-text" title="<?php echo esc_attr( $jtzl_bm_ua ); ?>" role="button" tabindex="0" aria-expanded="false"><?php echo esc_html( $jtzl_bm_ua ); ?></span>
<button type="button" class="jtzl-bm-ua-copy" data-clipboard-text="<?php echo esc_attr( $jtzl_bm_ua ); ?>">
	<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
	<span class="screen-reader-text"><?php echo esc_html__( 'Copy user agent to clipboard', 'jtzl-bot-maze' ); ?></span>
</button>
