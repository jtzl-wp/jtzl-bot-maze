<?php
/**
 * Database Schema
 *
 * Defines and creates the custom database tables for the plugin.
 *
 * @package Labyrinth
 * @since 1.0.0
 */

namespace Labyrinth\Database;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Database Schema Manager
 *
 * Handles plugin database table creation and management.
 *
 * @since 1.0.0
 */
class Schema {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 * @since 1.0.0
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database instance.
	 * @since 1.0.0
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Get the SQL statements for creating all plugin tables.
	 *
	 * @return array Array of CREATE TABLE SQL strings.
	 * @since 1.0.0
	 */
	public function get_create_table_sql(): array {
		$charset_collate = $this->wpdb->get_charset_collate();

		$trap_pages_table  = $this->wpdb->prefix . 'lby_trap_pages';
		$trap_visits_table = $this->wpdb->prefix . 'lby_trap_visits';
		$bot_scores_table  = $this->wpdb->prefix . 'lby_bot_scores';

		$sql = array();

		// SQL for lby_trap_pages table - Virtual trap pages (not WP posts).
		$sql[] = "CREATE TABLE $trap_pages_table (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  slug varchar(64) NOT NULL,
  title varchar(255) NOT NULL,
  content longtext NOT NULL,
  template_id varchar(50) NOT NULL DEFAULT '',
  depth_level int(10) UNSIGNED NOT NULL DEFAULT 1,
  parent_page_id bigint(20) UNSIGNED DEFAULT NULL,
  seed varchar(32) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'active',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY idx_slug (slug),
  KEY idx_status (status),
  KEY idx_depth_level (depth_level),
  KEY idx_parent_page_id (parent_page_id)
) $charset_collate;";

		// SQL for lby_trap_visits table - Every trap page access (evidence).
		$sql[] = "CREATE TABLE $trap_visits_table (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  trap_page_id bigint(20) UNSIGNED NOT NULL,
  ip_address varchar(45) NOT NULL,
  user_agent text NOT NULL,
  referrer varchar(2048) DEFAULT '',
  session_id varchar(64) NOT NULL DEFAULT '',
  depth_reached int(10) UNSIGNED NOT NULL DEFAULT 1,
  request_type varchar(20) NOT NULL DEFAULT '',
  response_code smallint(5) UNSIGNED NOT NULL DEFAULT 200,
  country_code char(2) NOT NULL DEFAULT '',
  visited_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_trap_page_id (trap_page_id),
  KEY idx_ip_address (ip_address),
  KEY idx_session_id (session_id),
  KEY idx_visited_at (visited_at),
  KEY idx_request_type (request_type),
  KEY idx_country_code (country_code)
) $charset_collate;";

		// SQL for lby_bot_scores table - Accumulated risk per IP+UA.
		$sql[] = "CREATE TABLE $bot_scores_table (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_address varchar(45) NOT NULL,
  user_agent_hash varchar(64) NOT NULL,
  score decimal(8,2) NOT NULL DEFAULT 0.00,
  trap_visits_count int(10) UNSIGNED NOT NULL DEFAULT 0,
  max_depth_reached int(10) UNSIGNED NOT NULL DEFAULT 0,
  first_seen_at datetime NOT NULL,
  last_seen_at datetime NOT NULL,
  is_blocked tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY idx_ip_ua (ip_address,user_agent_hash),
  KEY idx_score (score),
  KEY idx_is_blocked (is_blocked),
  KEY idx_last_seen_at (last_seen_at)
) $charset_collate;";

		return $sql;
	}

	/**
	 * Create all plugin database tables.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function create(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $this->get_create_table_sql() );
	}

	/**
	 * Drop all plugin database tables.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function drop(): void {
		$tables = array(
			$this->wpdb->prefix . 'lby_trap_visits',
			$this->wpdb->prefix . 'lby_bot_scores',
			$this->wpdb->prefix . 'lby_trap_pages',
			// Legacy table removed in consolidation; still dropped for clean uninstall.
			$this->wpdb->prefix . 'lby_request_log',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->wpdb->query( "DROP TABLE IF EXISTS $table" );
		}
	}

	/**
	 * Create all plugin database tables (static convenience wrapper).
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function create_tables(): void {
		global $wpdb;
		$schema = new self( $wpdb );
		$schema->create();
	}

	/**
	 * Drop all plugin database tables (static convenience wrapper).
	 *
	 * This should only be called on plugin uninstall, not deactivation.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function drop_tables(): void {
		global $wpdb;
		$schema = new self( $wpdb );
		$schema->drop();
	}
}
