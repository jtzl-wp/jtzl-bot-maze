<?php
/**
 * Trap Page Entity
 *
 * @package JTZL\Bot_Maze
 * @since 1.0.0
 */

namespace JTZL\Bot_Maze\Entity;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

use DateTime;

/**
 * Trap Page Entity
 *
 * Represents a virtual trap page in the trap maze.
 *
 * @since 1.0.0
 */
class TrapPage {

	/**
	 * Page ID.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public int $id;

	/**
	 * URL slug.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $slug;

	/**
	 * Page title.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $title;

	/**
	 * Pre-generated HTML content.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $content;

	/**
	 * Template ID that produced this page.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $template_id;

	/**
	 * Depth level (1 = entry, 2+ = deeper).
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public int $depth_level;

	/**
	 * Parent trap page ID.
	 *
	 * @var int|null
	 * @since 1.0.0
	 */
	public ?int $parent_page_id;

	/**
	 * Randomization seed.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $seed;

	/**
	 * Status (active/inactive).
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $status;

	/**
	 * Created timestamp.
	 *
	 * @var DateTime
	 * @since 1.0.0
	 */
	public DateTime $created_at;

	/**
	 * Updated timestamp.
	 *
	 * @var DateTime
	 * @since 1.0.0
	 */
	public DateTime $updated_at;

	/**
	 * Constructor.
	 *
	 * @param int      $id             Page ID.
	 * @param string   $slug           URL slug.
	 * @param string   $title          Page title.
	 * @param string   $content        HTML content.
	 * @param string   $template_id    Template ID.
	 * @param int      $depth_level    Depth level.
	 * @param int|null $parent_page_id Parent page ID.
	 * @param string   $seed           Randomization seed.
	 * @param string   $status         Status.
	 * @param DateTime $created_at     Created timestamp.
	 * @param DateTime $updated_at     Updated timestamp.
	 * @since 1.0.0
	 */
	public function __construct(
		int $id,
		string $slug,
		string $title,
		string $content,
		string $template_id,
		int $depth_level,
		?int $parent_page_id,
		string $seed,
		string $status,
		DateTime $created_at,
		DateTime $updated_at
	) {
		$this->id             = $id;
		$this->slug           = $slug;
		$this->title          = $title;
		$this->content        = $content;
		$this->template_id    = $template_id;
		$this->depth_level    = $depth_level;
		$this->parent_page_id = $parent_page_id;
		$this->seed           = $seed;
		$this->status         = $status;
		$this->created_at     = $created_at;
		$this->updated_at     = $updated_at;
	}
}
