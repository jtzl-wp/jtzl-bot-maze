<?php
/**
 * HTTP Response Value Object
 *
 * @package JTZL\Bot_Labyrinth
 * @since 1.0.0
 */

namespace JTZL\Bot_Labyrinth\Http;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Immutable HTTP response value object.
 *
 * Used to decouple response generation from response sending,
 * enabling unit testing of code that would otherwise call header()/echo/exit.
 *
 * @since 1.0.0
 */
class Response {

	/**
	 * HTTP status code.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public readonly int $status;

	/**
	 * Response headers.
	 *
	 * @var array<string, string>
	 * @since 1.0.0
	 */
	public readonly array $headers;

	/**
	 * Response body.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public readonly string $body;

	/**
	 * Constructor.
	 *
	 * @param int                   $status  HTTP status code.
	 * @param array<string, string> $headers Response headers.
	 * @param string                $body    Response body.
	 * @since 1.0.0
	 */
	public function __construct( int $status, array $headers, string $body ) {
		$this->status  = $status;
		$this->headers = $headers;
		$this->body    = $body;
	}

	/**
	 * Send the response to the client.
	 *
	 * @return never
	 * @since 1.0.0
	 */
	public function send(): never {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Matches WordPress's own status_header() pattern.
		status_header( $this->status );
		foreach ( $this->headers as $name => $value ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Suppresses "headers already sent" when output started early.
			@header( "{$name}: {$value}" );
		}
		echo $this->body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is pre-escaped by the builder.
		wp_die( '' );
	}
}
