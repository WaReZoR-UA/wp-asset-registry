<?php
/**
 * Traversal-safe storage for asset attachments in a non-public uploads subdir.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Files;

/**
 * Stores asset attachments in a protected directory under wp-content/uploads,
 * guarded by a deny-all .htaccess and an index.php. Files are only ever served
 * through the gated file route, never by a direct web request.
 *
 * The path-building seams (relative_path, resolve) are pure and unit-tested;
 * the filesystem methods (ensure_protected, store, delete) are thin wrappers
 * verified by manual/integration testing.
 *
 * Not declared final so the store can be mocked in unit tests of its callers.
 */
class AttachmentStore {

	/**
	 * Name of the protected subdirectory inside the uploads basedir.
	 */
	private const SUBDIR = 'asset-registry-protected';

	/**
	 * Extensions accepted by store(); everything else is rejected.
	 */
	private const ALLOWED_EXTENSIONS = array(
		'pdf',
		'png',
		'jpg',
		'jpeg',
		'gif',
		'webp',
		'doc',
		'docx',
		'xls',
		'xlsx',
	);

	/**
	 * Absolute path to the protected directory, without a trailing slash.
	 *
	 * Defaults to a subdirectory of the uploads folder guarded by a deny-all
	 * .htaccess. On servers that do not honour .htaccess (nginx, some
	 * LiteSpeed setups), point this OUTSIDE the web root via the
	 * ASSET_REGISTRY_PROTECTED_DIR constant or the filter below so the files
	 * are never directly served.
	 *
	 * @return string The clean directory path.
	 */
	public function protected_dir(): string {
		if ( defined( 'ASSET_REGISTRY_PROTECTED_DIR' ) && '' !== (string) ASSET_REGISTRY_PROTECTED_DIR ) {
			$dir = (string) ASSET_REGISTRY_PROTECTED_DIR;
		} else {
			$dir = trailingslashit( wp_upload_dir()['basedir'] ) . self::SUBDIR;
		}

		/**
		 * Filters the absolute path to the protected attachment directory.
		 *
		 * @param string $dir The protected directory path.
		 */
		$dir = (string) apply_filters( 'asset_registry_protected_dir', $dir );

		return untrailingslashit( $dir );
	}

	/**
	 * Creates the protected directory and writes the guard files on first use.
	 * Integration-tested manually.
	 */
	public function ensure_protected(): void {
		$dir = $this->protected_dir();

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules = "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- guard file written once outside the public document root.
			file_put_contents( $htaccess, $rules );
		}

		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- guard file written once outside the public document root.
			file_put_contents( $index, "<?php // Silence is golden.\n" );
		}
	}

	/**
	 * Builds a safe relative path (relative to protected_dir) for a new upload.
	 * The original name is sanitized so it cannot introduce path separators or
	 * traversal sequences. Deterministic: the token is supplied by the caller.
	 *
	 * @param string $original_name Untrusted client-supplied file name.
	 * @param int    $asset_id      Owning asset id.
	 * @param string $token         Caller-supplied random token (e.g. wp_generate_password).
	 * @return string A separator-free relative path safe to append to protected_dir.
	 */
	public function relative_path( string $original_name, int $asset_id, string $token ): string {
		$safe_name = sanitize_file_name( $original_name );
		$safe_name = str_replace( array( '/', '\\' ), '', $safe_name );
		// Collapse any residual traversal sequence so the name can never contain "..".
		$safe_name = str_replace( '..', '', $safe_name );
		if ( '' === $safe_name ) {
			$safe_name = 'file';
		}

		return $asset_id . '-' . $token . '-' . $safe_name;
	}

	/**
	 * Resolves a stored relative path to its absolute path inside protected_dir,
	 * or null when the input is unsafe. This is the security boundary: it rejects
	 * empty input, null bytes, absolute paths (including Windows drives) and any
	 * parent-traversal sequence. String-based: it does not require the file to exist.
	 *
	 * @param string $relative The stored relative path.
	 * @return string|null Absolute path inside protected_dir, or null when unsafe.
	 */
	public function resolve( string $relative ): ?string {
		if ( '' === $relative ) {
			return null;
		}

		if ( str_contains( $relative, "\0" ) ) {
			return null;
		}

		// Reject any parent-traversal sequence outright.
		if ( str_contains( $relative, '..' ) ) {
			return null;
		}

		$normalized = str_replace( '\\', '/', $relative );

		// Reject absolute POSIX paths.
		if ( str_starts_with( $normalized, '/' ) ) {
			return null;
		}

		// Reject Windows drive letters such as "C:" at the start.
		if ( 1 === preg_match( '/^[A-Za-z]:/', $normalized ) ) {
			return null;
		}

		return trailingslashit( $this->protected_dir() ) . $relative;
	}

	/**
	 * Validates and moves an uploaded file into the protected directory.
	 * Integration-tested manually.
	 *
	 * @param array<string, mixed> $file     A single $_FILES entry.
	 * @param int                  $asset_id Owning asset id.
	 * @return string The stored relative path.
	 * @throws \RuntimeException When the upload is invalid or cannot be stored.
	 */
	public function store( array $file, int $asset_id ): string {
		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			throw new \RuntimeException( 'Upload failed.' );
		}
		if ( empty( $file['name'] ) || empty( $file['tmp_name'] ) ) {
			throw new \RuntimeException( 'Empty upload.' );
		}

		$extension = strtolower( (string) pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, self::ALLOWED_EXTENSIONS, true ) ) {
			throw new \RuntimeException( 'Disallowed file type.' );
		}

		$this->ensure_protected();

		$token       = wp_generate_password( 8, false );
		$relative    = $this->relative_path( (string) $file['name'], $asset_id, $token );
		$destination = $this->resolve( $relative );

		if ( null === $destination ) {
			throw new \RuntimeException( 'Could not resolve a safe destination.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_move_uploaded_file, WordPress.PHP.NoSilencedErrors.Discouraged -- moving the PHP upload temp file into the protected directory.
		if ( ! @move_uploaded_file( (string) $file['tmp_name'], $destination ) ) {
			throw new \RuntimeException( 'Could not store the upload.' );
		}

		return $relative;
	}

	/**
	 * Deletes a stored attachment by its relative path.
	 *
	 * @param string $relative The stored relative path.
	 * @return bool True when a file was deleted, false otherwise.
	 */
	public function delete( string $relative ): bool {
		$absolute = $this->resolve( $relative );
		if ( null === $absolute || ! is_file( $absolute ) ) {
			return false;
		}

		wp_delete_file( $absolute );
		return true;
	}
}
