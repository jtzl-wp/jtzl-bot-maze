<?php
/**
 * Blocked Bots list table.
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
 * Server-side sortable, searchable table of blocked bots.
 *
 * Every column sorts server-side over the full dataset — the score table
 * stores the readable User Agent and Country alongside the hash, so both
 * are real sortable columns (parity with Recent Activity). Search and the
 * click-to-filter links cover the IP and the User Agent directly. The
 * pagination header reports the count.
 *
 * @since 1.3.0
 */
class BlockedBotsListTable extends \WP_List_Table {

	/**
	 * Admin page slug this table lives on.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'jtzl-bot-maze-blocked-bots';

	/**
	 * Rows per page.
	 *
	 * @var int
	 */
	private const PER_PAGE = 50;

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
				'singular' => 'blocked-bot',
				'plural'   => 'blocked-bots',
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
			'ip_address'  => __( 'IP Address', 'jtzl-bot-maze' ),
			'country'     => __( 'Country', 'jtzl-bot-maze' ),
			'user_agent'  => __( 'User Agent', 'jtzl-bot-maze' ),
			'score'       => __( 'Score', 'jtzl-bot-maze' ),
			'visits'      => __( 'Visits', 'jtzl-bot-maze' ),
			'max_depth'   => __( 'Max Depth', 'jtzl-bot-maze' ),
			'first_seen'  => __( 'First Seen', 'jtzl-bot-maze' ),
			'last_seen'   => __( 'Last Seen', 'jtzl-bot-maze' ),
			'reported'    => __( 'Reported', 'jtzl-bot-maze' ),
		);
	}

	/**
	 * Columns that support sorting, mapped to their database column.
	 *
	 * Country sorts by ISO code rather than localized display name —
	 * intentional, matching the Recent Activity table.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function get_sortable_columns(): array {
		return array(
			'ip_address' => array( 'ip_address', false ),
			'country'    => array( 'country_code', false ),
			'user_agent' => array( 'user_agent', false ),
			'score'      => array( 'score', false ),
			'visits'     => array( 'trap_visits_count', false ),
			'max_depth'  => array( 'max_depth_reached', false ),
			'first_seen' => array( 'first_seen_at', false ),
			'last_seen'  => array( 'last_seen_at', true ),
			'reported'   => array( 'abuseipdb_reported_at', false ),
		);
	}

	/**
	 * Query the current page of blocked bots and set up pagination.
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

		$this->items = $this->collector->get_blocked_bots( $per_page, $offset, $orderby, $order, $search );

		$total = $this->collector->count_blocked_bots( $search );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Message shown when there are no matching blocked bots.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No blocked bots yet. Bots are blocked when their suspicion score exceeds the configured threshold.', 'jtzl-bot-maze' );
	}

	/**
	 * Default cell rendering (escaped plain text).
	 *
	 * @param array  $item        Bot data.
	 * @param string $column_name Column slug.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'country':
				return esc_html( (string) $item['country_name'] );
			case 'score':
				return '<strong>' . esc_html( number_format( (float) $item['score'], 1 ) ) . '</strong>';
			case 'visits':
				return esc_html( (string) $item['trap_visits_count'] );
			case 'max_depth':
				return esc_html( (string) $item['max_depth_reached'] );
			case 'first_seen':
				return esc_html( (string) $item['first_seen_at'] );
			case 'last_seen':
				return esc_html( (string) $item['last_seen_at'] );
			case 'reported':
				return $this->reported_cell( $item['abuseipdb_reported_at'] ?? null );
			default:
				return '';
		}
	}

	/**
	 * Reported cell: when this address was sent to AbuseIPDB, or a dash.
	 *
	 * A dash is not "never reported" — reporting may simply be off, or the
	 * address may have been reported before the marker column existed. The
	 * title attribute says so rather than implying a negative.
	 *
	 * @param string|null $reported_at Site-local SQL timestamp, or null.
	 * @return string
	 */
	private function reported_cell( ?string $reported_at ): string {
		if ( null === $reported_at || '' === $reported_at ) {
			return sprintf(
				'<span aria-hidden="true" title="%s">&mdash;</span><span class="screen-reader-text">%s</span>',
				esc_attr__( 'No AbuseIPDB report recorded for this address', 'jtzl-bot-maze' ),
				esc_html__( 'No AbuseIPDB report recorded for this address', 'jtzl-bot-maze' )
			);
		}

		return sprintf(
			'<span class="jtzl-bm-reported" title="%s">%s</span>',
			esc_attr__( 'Reported to AbuseIPDB', 'jtzl-bot-maze' ),
			esc_html( $reported_at )
		);
	}

	/**
	 * IP cell: click to filter the table to this address.
	 *
	 * @param array $item Bot data.
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
	 * @param array $item Bot data.
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

		$orderby = $this->current_orderby();
		$order   = $this->current_order();
		if ( 'last_seen_at' !== $orderby || 'DESC' !== $order ) {
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
		return isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'last_seen_at';
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
