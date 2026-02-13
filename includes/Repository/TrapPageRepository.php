<?php
/**
 * Trap Page Repository
 *
 * @package Labyrinth
 */

namespace Labyrinth\Repository;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use DateTime;
use Labyrinth\Entity\TrapPage;

/**
 * Trap Page Repository
 *
 * Handles database operations for trap page entities.
 */
class TrapPageRepository implements TrapPageRepositoryInterface {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database object.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'lby_trap_pages';
	}

	/**
	 * Create a new trap page.
	 *
	 * @param array $data Trap page data.
	 * @return int The created page ID, or 0 on failure.
	 */
	public function create( array $data ): int {
		$now = current_time( 'mysql' );

		$insert_data = array(
			'slug'        => $data['slug'],
			'title'       => $data['title'],
			'content'     => $data['content'],
			'template_id' => $data['template_id'] ?? '',
			'depth_level' => $data['depth_level'] ?? 1,
			'seed'        => $data['seed'] ?? '',
			'status'      => $data['status'] ?? 'active',
			'created_at'  => $now,
			'updated_at'  => $now,
		);

		$format = array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' );

		// Only include parent_page_id when non-null to preserve NULL in the database.
		$parent_page_id = $data['parent_page_id'] ?? null;
		if ( null !== $parent_page_id ) {
			$insert_data['parent_page_id'] = $parent_page_id;
			$format[] = '%d';
		}

		$result = $this->wpdb->insert( $this->table_name, $insert_data, $format );

		if ( false === $result ) {
			return 0;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Find a trap page by slug.
	 *
	 * @param string $slug URL slug.
	 * @return TrapPage|null The trap page entity or null.
	 */
	public function find_by_slug( string $slug ): ?TrapPage {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE slug = %s AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$slug
		);

		$row = $this->wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( null === $row ) {
			return null;
		}

		return $this->hydrate( $row );
	}

	/**
	 * Find a trap page by ID.
	 *
	 * @param int $id Page ID.
	 * @return TrapPage|null The trap page entity or null.
	 */
	public function find_by_id( int $id ): ?TrapPage {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$id
		);

		$row = $this->wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( null === $row ) {
			return null;
		}

		return $this->hydrate( $row );
	}

	/**
	 * Get active trap pages for injection into real pages.
	 * Uses deterministic selection based on a seed (post ID hash).
	 *
	 * @param int $count Number of pages to retrieve.
	 * @param int $seed  Seed for deterministic selection.
	 * @return TrapPage[] Array of trap page entities.
	 */
	public function get_random_active( int $count, int $seed = 0 ): array {
		// Use deterministic ordering based on seed to avoid DB queries varying per request.
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE status = 'active' AND depth_level = 1 ORDER BY RAND(%d) LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$seed,
			$count
		);

		$rows = $this->wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'hydrate' ), $rows );
	}

	/**
	 * Get all active trap pages.
	 *
	 * @return TrapPage[] Array of trap page entities.
	 */
	public function get_all_active(): array {
		$query = "SELECT * FROM {$this->table_name} WHERE status = 'active' ORDER BY depth_level ASC, id ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$rows = $this->wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'hydrate' ), $rows );
	}

	/**
	 * Get child trap pages for a given parent.
	 *
	 * @param int $parent_id Parent trap page ID.
	 * @return TrapPage[] Array of child trap page entities.
	 */
	public function get_children( int $parent_id ): array {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE parent_page_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$parent_id
		);

		$rows = $this->wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'hydrate' ), $rows );
	}

	/**
	 * Count active trap pages.
	 *
	 * @return int Total active trap pages.
	 */
	public function count_active(): int {
		$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'active'"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count active trap pages at depth level 1.
	 *
	 * @return int Total active entry-level trap pages.
	 */
	public function count_active_entry_pages(): int {
		$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'active' AND depth_level = 1"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Delete all trap pages.
	 *
	 * @return int Number of rows deleted.
	 */
	public function delete_all(): int {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $this->wpdb->query( "DELETE FROM {$this->table_name}" );

		return false !== $result ? (int) $result : 0;
	}

	/**
	 * Check if a slug exists.
	 *
	 * @param string $slug Slug to check.
	 * @return bool True if slug exists.
	 */
	public function slug_exists( string $slug ): bool {
		$query = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE slug = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$slug
		);

		return (int) $this->wpdb->get_var( $query ) > 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Hydrate a database row into a TrapPage entity.
	 *
	 * @param array $row Database row.
	 * @return TrapPage Trap page entity.
	 */
	private function hydrate( array $row ): TrapPage {
		return new TrapPage(
			(int) $row['id'],
			$row['slug'],
			$row['title'],
			$row['content'],
			$row['template_id'] ?? '',
			(int) ( $row['depth_level'] ?? 1 ),
			! empty( $row['parent_page_id'] ) ? (int) $row['parent_page_id'] : null,
			$row['seed'] ?? '',
			$row['status'],
			new DateTime( $row['created_at'] ),
			new DateTime( $row['updated_at'] )
		);
	}
}
