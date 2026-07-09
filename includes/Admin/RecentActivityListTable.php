<?php
/**
 * Recent Activity list table.
 *
 * @package JTZL\Bot_Maze
 * @since 1.3.0
 */

namespace JTZL\Bot_Maze\Admin;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use JTZL\Bot_Maze\Service\AnalyticsCollector;

// @codeCoverageIgnoreStart
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
// @codeCoverageIgnoreEnd

/**
 * Server-side sortable, searchable table of trap-page visits.
 *
 * Sorting and search run over the full data set (not just the visible page):
 * columns sort via an allowlisted ORDER BY, and the search box — plus the
 * click-to-filter links on the IP and User Agent cells — narrow the rows with
 * an escaped LIKE. The pagination header reports the match count.
 *
 * @since 1.3.0
 */
class RecentActivityListTable extends \WP_List_Table {

	/**
	 * Admin page slug this table lives on.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'jtzl-bot-maze-recent-activity';

	/**
	 * Rows per page.
	 *
	 * @var int
	 */
	private const PER_PAGE = 50;

	/**
	 * Day window shown.
	 *
	 * @var int
	 */
	private const DAYS = 7;

	/**
	 * Analytics collector service.
	 *
	 * @var AnalyticsCollector
	 */
	private AnalyticsCollector $collector;

	/**
	 * Constructor.
	 *
	 * @param AnalyticsCollector $collector Analytics collector service.
	 */
	public function __construct( AnalyticsCollector $collector ) {
		$this->collector = $collector;

		parent::__construct(
			array(
				'singular' => 'visit',
				'plural'   => 'visits',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Column headings keyed by slug.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'visited_at'   => __( 'Time', 'jtzl-bot-maze' ),
			'ip_address'   => __( 'IP Address', 'jtzl-bot-maze' ),
			'country'      => __( 'Country', 'jtzl-bot-maze' ),
			'user_agent'   => __( 'User Agent', 'jtzl-bot-maze' ),
			'depth'        => __( 'Depth', 'jtzl-bot-maze' ),
			'request_type' => __( 'Type', 'jtzl-bot-maze' ),
		);
	}

	/**
	 * Columns that support sorting, mapped to their database column.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function get_sortable_columns(): array {
		return array(
			'visited_at'   => array( 'visited_at', true ),
			'ip_address'   => array( 'ip_address', false ),
			'country'      => array( 'country_code', false ),
			'user_agent'   => array( 'user_agent', false ),
			'depth'        => array( 'depth_reached', false ),
			'request_type' => array( 'request_type', false ),
		);
	}

	/**
	 * Query the current page of visits and set up pagination.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'ip_address' );

		$search   = $this->current_search();
		$orderby  = $this->current_orderby();
		$order    = $this->current_order();
		$per_page = self::PER_PAGE;
		$offset   = ( $this->get_pagenum() - 1 ) * $per_page;

		$this->items = $this->collector->get_recent_activity_days( self::DAYS, $per_page, $offset, $orderby, $order, $search );

		$total = $this->collector->count_all_recent_activity( self::DAYS, $search );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Message shown when there are no matching visits.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No activity in the last 7 days.', 'jtzl-bot-maze' );
	}

	/**
	 * Default cell rendering (escaped plain text).
	 *
	 * @param array  $item        Visit data.
	 * @param string $column_name Column slug.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'visited_at':
				return esc_html( (string) $item['visited_at'] );
			case 'country':
				return esc_html( (string) $item['country_name'] );
			case 'depth':
				return esc_html( (string) $item['depth_reached'] );
			default:
				return '';
		}
	}

	/**
	 * IP cell: click to filter the table to this address.
	 *
	 * @param array $item Visit data.
	 * @return string
	 */
	public function column_ip_address( $item ): string {
		return sprintf(
			'<a href="%s" title="%s"><code>%s</code></a>',
			esc_url( $this->filter_url( (string) $item['ip_address'] ) ),
			esc_attr__( 'Show only this IP address', 'jtzl-bot-maze' ),
			esc_html( (string) $item['ip_address'] )
		);
	}

	/**
	 * User-Agent cell: the shared expand/copy control plus a click-to-filter link.
	 *
	 * @param array $item Visit data.
	 * @return string
	 */
	public function column_user_agent( $item ): string {
		ob_start();
		$jtzl_bm_ua = (string) $item['user_agent'];
		require JTZL_BM_PLUGIN_DIR . 'templates/partials/ua-cell.php'; // @phpstan-ignore require.fileNotFound
		$cell = (string) ob_get_clean();

		$cell .= sprintf(
			'<a class="jtzl-bm-ua-filter" href="%s" title="%s"><span class="dashicons dashicons-filter" aria-hidden="true"></span><span class="screen-reader-text">%s</span></a>',
			esc_url( $this->filter_url( (string) $item['user_agent'] ) ),
			esc_attr__( 'Filter by this user agent', 'jtzl-bot-maze' ),
			esc_html__( 'Filter by this user agent', 'jtzl-bot-maze' )
		);

		return '<div class="jtzl-bm-ua-cell">' . $cell . '</div>';
	}

	/**
	 * Type cell: a coloured status badge.
	 *
	 * @param array $item Visit data.
	 * @return string
	 */
	public function column_request_type( $item ): string {
		$type = (string) $item['request_type'];

		if ( 'blocked_visit' === $type ) {
			return '<span class="jtzl-bm-badge jtzl-bm-badge-blocked">' . esc_html__( 'Blocked', 'jtzl-bot-maze' ) . '</span>';
		}
		if ( 'crawler_exempted' === $type ) {
			return '<span class="jtzl-bm-badge jtzl-bm-badge-exempted">' . esc_html__( 'Exempted', 'jtzl-bot-maze' ) . '</span>';
		}

		return '<span class="jtzl-bm-badge jtzl-bm-badge-tracking">' . esc_html__( 'Trap Visit', 'jtzl-bot-maze' ) . '</span>';
	}

	/**
	 * Build a URL that filters the table to a given value, preserving the sort.
	 *
	 * @param string $value Value to search for.
	 * @return string
	 */
	private function filter_url( string $value ): string {
		$args = array(
			'page' => self::PAGE_SLUG,
			's'    => $value,
		);

		// Carry the active sort through the filter link, but omit it when it is
		// the default (visited_at DESC) so the URL stays clean.
		$orderby = $this->current_orderby();
		$order   = $this->current_order();
		if ( 'visited_at' !== $orderby || 'DESC' !== $order ) {
			$args['orderby'] = $orderby;
			$args['order']   = $order;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Current search term from the request.
	 *
	 * @return string
	 */
	private function current_search(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		return isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
	}

	/**
	 * Current sort column from the request (validated downstream by the repository).
	 *
	 * @return string
	 */
	private function current_orderby(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list sort.
		return isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'visited_at';
	}

	/**
	 * Current sort direction from the request.
	 *
	 * @return string
	 */
	private function current_order(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list sort.
		$order = isset( $_REQUEST['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) : 'DESC';

		return 'ASC' === $order ? 'ASC' : 'DESC';
	}
}
