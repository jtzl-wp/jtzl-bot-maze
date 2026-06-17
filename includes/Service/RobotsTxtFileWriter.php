<?php
/**
 * Robots.txt File Writer Service
 *
 * Optionally appends the plugin's Disallow rule to a physical robots.txt file
 * when the virtual one is being shadowed.
 *
 * @package JTZL\Bot_Maze
 * @since 1.2.0
 */

namespace JTZL\Bot_Maze\Service;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Robots.txt File Writer.
 *
 * The plugin is otherwise passive about robots.txt — it never owns a file on
 * disk. This writer exists only for the opt-in "add it for me" action offered
 * when a physical robots.txt is shadowing the virtual one. It appends a
 * self-contained `User-agent: *` group (rather than a bare Disallow, which would
 * attach to whatever group happens to come last) so the rule applies to every
 * crawler regardless of the file's existing contents.
 *
 * It deliberately never *creates* a robots.txt from scratch: doing so would turn
 * the served robots.txt from WordPress's virtual one into a static file,
 * silently dropping the sitemap reference and any other plugins' directives the
 * virtual file was emitting. When no physical file exists the rule belongs in
 * the virtual robots.txt, not in a new static file this writer would conjure.
 *
 * Every write is guarded by a writability check, so a read-only file degrades to
 * a clear "not writable" result the caller can fall back to manual instructions
 * for. File I/O uses the same direct, owner-controlled approach as
 * MaxMindDatabaseManager rather than WP_Filesystem, whose FTP-credential prompt
 * would defeat a one-click action.
 *
 * @since 1.2.0
 */
class RobotsTxtFileWriter {

	/**
	 * The directive was appended to the file.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const RESULT_APPENDED = 'appended';

	/**
	 * The directive was already present; nothing was written.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const RESULT_ALREADY_PRESENT = 'already_present';

	/**
	 * The target file (or its directory) is not writable.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const RESULT_NOT_WRITABLE = 'not_writable';

	/**
	 * No physical robots.txt exists; the writer refuses to create one.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const RESULT_NO_FILE = 'no_file';

	/**
	 * Writing the file failed unexpectedly despite the writability check.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	public const RESULT_ERROR = 'error';

	/**
	 * Absolute path to the robots.txt file the writer manages.
	 *
	 * @var string
	 * @since 1.2.0
	 */
	private string $path;

	/**
	 * Constructor.
	 *
	 * @param string|null $path Override the target path (defaults to the file at
	 *                          the WordPress root). Injectable for tests.
	 * @since 1.2.0
	 */
	public function __construct( ?string $path = null ) {
		$this->path = $path ?? ABSPATH . 'robots.txt';
	}

	/**
	 * Absolute path to the robots.txt file the writer targets.
	 *
	 * @return string
	 * @since 1.2.0
	 */
	public function get_path(): string {
		return $this->path;
	}

	/**
	 * Whether a physical robots.txt file already exists at the target path.
	 *
	 * @return bool
	 * @since 1.2.0
	 */
	public function file_exists(): bool {
		return file_exists( $this->path );
	}

	/**
	 * Whether the writer can create or modify the target file.
	 *
	 * @return bool
	 * @since 1.2.0
	 */
	public function can_write(): bool {
		$target = $this->file_exists() ? $this->path : dirname( $this->path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Pre-flight writability check for a local owner-controlled file; WP_Filesystem would prompt for FTP credentials and defeat the one-click action.
		return is_writable( $target );
	}

	/**
	 * Append the given directive as a self-contained group if not already present.
	 *
	 * @param string $directive Directive to ensure (e.g. "Disallow: /jbm/").
	 * @return string One of the RESULT_* constants.
	 * @since 1.2.0
	 */
	public function append_directive( string $directive ): string {
		// Only ever modify an existing file; never create one (see class docblock).
		if ( ! $this->file_exists() ) {
			return self::RESULT_NO_FILE;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local, owner-controlled file; WP_Filesystem would prompt for FTP credentials and break this one-click action on some hosts. Matches MaxMindDatabaseManager's convention.
		$contents = file_get_contents( $this->path );
		if ( false === $contents ) {
			return self::RESULT_ERROR;
		}
		$existing = (string) $contents;

		if ( RobotsTxtVerifier::body_contains_directive( $existing, $directive ) ) {
			return self::RESULT_ALREADY_PRESENT;
		}

		if ( ! $this->can_write() ) {
			return self::RESULT_NOT_WRITABLE;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local owner-controlled write guarded by can_write(); see file_get_contents note above.
		$written = file_put_contents( $this->path, $this->compose( $existing, $directive ), LOCK_EX );

		return false !== $written ? self::RESULT_APPENDED : self::RESULT_ERROR;
	}

	/**
	 * Compose the new file contents by appending a managed crawler group.
	 *
	 * @param string $existing  Current file contents ('' when the file is new).
	 * @param string $directive Disallow directive to add.
	 * @return string
	 * @since 1.2.0
	 */
	private function compose( string $existing, string $directive ): string {
		$block = RobotsTxtManager::MANAGED_COMMENT . "\n"
			. "User-agent: *\n"
			. $directive . "\n";

		if ( '' === trim( $existing ) ) {
			return $block;
		}

		return rtrim( $existing, "\r\n" ) . "\n\n" . $block;
	}
}
