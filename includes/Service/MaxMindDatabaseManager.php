<?php
/**
 * MaxMind Database Manager
 *
 * Handles downloading and updating the GeoLite2-Country database.
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

/**
 * MaxMind Database Manager
 *
 * Downloads, extracts, and updates the GeoLite2-Country MMDB file
 * from MaxMind's download service using a user-provided license key.
 *
 * @since 1.0.0
 */
class MaxMindDatabaseManager {

	/**
	 * MaxMind download URL template.
	 *
	 * @var string
	 */
	private const DOWNLOAD_URL = 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=%s&suffix=tar.gz';

	/**
	 * Download timeout in seconds.
	 *
	 * @var int
	 */
	private const DOWNLOAD_TIMEOUT = 30;

	/**
	 * The database filename inside the archive.
	 *
	 * @var string
	 */
	private const DB_FILENAME = 'GeoLite2-Country.mmdb';

	/**
	 * Get the storage directory for the database.
	 *
	 * @return string Absolute path to the storage directory (with trailing slash).
	 * @since 1.0.0
	 */
	public static function get_storage_dir(): string {
		return WP_CONTENT_DIR . '/jtzl-bot-be-gone/geolite2/';
	}

	/**
	 * Get the expected database file path.
	 *
	 * @return string Absolute path to the .mmdb file.
	 * @since 1.0.0
	 */
	public static function get_database_path(): string {
		return self::get_storage_dir() . self::DB_FILENAME;
	}

	/**
	 * Download and install the GeoLite2-Country database.
	 *
	 * @param string $license_key MaxMind license key.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 * @since 1.0.0
	 */
	public function download_database( string $license_key ) {
		if ( '' === $license_key ) {
			return new \WP_Error( 'jtzl_bbg_maxmind_no_key', __( 'MaxMind license key is required.', 'jtzl-bot-be-gone' ) );
		}

		$url      = sprintf( self::DOWNLOAD_URL, rawurlencode( $license_key ) );
		$tmp_file = wp_tempnam( 'jtzl_bbg_maxmind_' );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'  => self::DOWNLOAD_TIMEOUT,
				'stream'   => true,
				'filename' => $tmp_file,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_delete_file( $tmp_file );
			return new \WP_Error( 'jtzl_bbg_maxmind_download_failed', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 401 === $code ) {
			wp_delete_file( $tmp_file );
			return new \WP_Error( 'jtzl_bbg_maxmind_invalid_key', __( 'Invalid MaxMind license key.', 'jtzl-bot-be-gone' ) );
		}

		if ( 200 !== $code ) {
			wp_delete_file( $tmp_file );
			return new \WP_Error(
				'jtzl_bbg_maxmind_http_error',
				/* translators: %d: HTTP response code */
				sprintf( __( 'MaxMind download returned HTTP %d.', 'jtzl-bot-be-gone' ), $code )
			);
		}

		$result = $this->extract_mmdb( $tmp_file );
		wp_delete_file( $tmp_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_option( 'jtzl_bbg_maxmind_last_updated', gmdate( 'c' ) );

		return true;
	}

	/**
	 * Extract the .mmdb file from a tar.gz archive.
	 *
	 * @param string $archive_path Path to the downloaded tar.gz file.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 * @since 1.0.0
	 */
	private function extract_mmdb( string $archive_path ) {
		try {
			$phar    = new \PharData( $archive_path );
			$storage = self::get_storage_dir();

			if ( ! file_exists( $storage ) ) {
				wp_mkdir_p( $storage );
			}

			// Protect directory from direct web access.
			$index_path = $storage . 'index.php';
			if ( ! file_exists( $index_path ) ) {
				file_put_contents( $index_path, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}

			// Find the .mmdb file inside the archive (it's in a subdirectory).
			$mmdb_found = false;
			foreach ( $phar as $file ) {
				if ( $file->isDir() ) {
					$inner_phar = new \PharData( $file->getPathname() );
					foreach ( $inner_phar as $inner_file ) {
						if ( self::DB_FILENAME === $inner_file->getFilename() ) {
							$install_result = $this->install_mmdb( $inner_file->getPathname(), $storage );
							if ( is_wp_error( $install_result ) ) { // @codeCoverageIgnoreStart
								return $install_result; // Filesystem write failure.
							} // @codeCoverageIgnoreEnd
							$mmdb_found = true;
							break 2;
						}
					}
				}
				if ( self::DB_FILENAME === $file->getFilename() ) {
					$install_result = $this->install_mmdb( $file->getPathname(), $storage );
					if ( is_wp_error( $install_result ) ) { // @codeCoverageIgnoreStart
						return $install_result; // Filesystem write failure.
					} // @codeCoverageIgnoreEnd
					$mmdb_found = true;
					break;
				}
			}

			if ( ! $mmdb_found ) {
				return new \WP_Error( 'jtzl_bbg_maxmind_no_mmdb', __( 'No .mmdb file found in the MaxMind archive.', 'jtzl-bot-be-gone' ) );
			}

			return true;
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'jtzl_bbg_maxmind_extract_failed', $e->getMessage() );
		}
	}

	/**
	 * Install the .mmdb file to the storage directory atomically.
	 *
	 * @param string $source_path  Path to the extracted .mmdb file.
	 * @param string $storage_dir  Target storage directory.
	 * @return void
	 * @since 1.0.0
	 */
	/**
	 * Install the .mmdb file to the storage directory atomically.
	 *
	 * @param string $source_path  Path to the extracted .mmdb file.
	 * @param string $storage_dir  Target storage directory.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 * @since 1.0.0
	 */
	private function install_mmdb( string $source_path, string $storage_dir ) {
		$target  = $storage_dir . self::DB_FILENAME;
		$tmp_dst = $storage_dir . self::DB_FILENAME . '.tmp';

		// @codeCoverageIgnoreStart — Filesystem write failures cannot be reliably triggered in tests.
		if ( ! copy( $source_path, $tmp_dst ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new \WP_Error( 'jtzl_bbg_maxmind_copy_failed', __( 'Failed to copy the database file.', 'jtzl-bot-be-gone' ) );
		}

		if ( ! rename( $tmp_dst, $target ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			wp_delete_file( $tmp_dst );
			return new \WP_Error( 'jtzl_bbg_maxmind_rename_failed', __( 'Failed to install the database file.', 'jtzl-bot-be-gone' ) );
		}
		// @codeCoverageIgnoreEnd

		return true;
	}

	/**
	 * Delete the database and storage directory.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function cleanup(): void {
		$storage = self::get_storage_dir();

		if ( ! is_dir( $storage ) ) {
			return;
		}

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $storage, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $files as $file ) {
			if ( $file->isDir() ) {
				rmdir( $file->getRealPath() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			} else {
				wp_delete_file( $file->getRealPath() );
			}
		}

		rmdir( $storage ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
