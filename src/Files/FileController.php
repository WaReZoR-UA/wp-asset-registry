<?php
/**
 * Gated admin-post route that streams a stored asset attachment.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Files;

use AssetRegistry\AssetRepository;
use AssetRegistry\Capabilities;

/**
 * Registers the admin-post endpoint, builds its per-asset nonced URL, and
 * streams a stored attachment only when the request comes from a logged-in
 * user who holds the view capability and carries a valid per-id nonce. The
 * authorization decision is isolated in the pure can_access() seam so it can be
 * unit-tested without the WordPress runtime. Paths are resolved through the
 * AttachmentStore so a stored value can never escape the protected directory.
 */
final class FileController {

	public const ACTION       = 'asset_registry_file';
	public const NONCE_ACTION = 'asset_registry_file';

	/**
	 * Stores the optional dependencies.
	 *
	 * @param AssetRepository|null $repository Injected for testing; built lazily otherwise.
	 * @param AttachmentStore|null $store      Injected for testing; built lazily otherwise.
	 */
	public function __construct(
		private ?AssetRepository $repository = null,
		private ?AttachmentStore $store = null
	) {}

	/**
	 * Resolves the repository, wiring the global $wpdb when none was injected.
	 *
	 * @return AssetRepository The data-access layer.
	 */
	private function repository(): AssetRepository {
		if ( null === $this->repository ) {
			global $wpdb;
			$this->repository = new AssetRepository( $wpdb );
		}
		return $this->repository;
	}

	/**
	 * Resolves the attachment store, building a default instance when none was injected.
	 *
	 * @return AttachmentStore The protected-storage gateway.
	 */
	private function store(): AttachmentStore {
		if ( null === $this->store ) {
			$this->store = new AttachmentStore();
		}
		return $this->store;
	}

	/**
	 * Registers the admin-post handlers.
	 *
	 * The nopriv handler is registered so logged-out requests still reach
	 * handle() and are explicitly denied there with a 403, rather than being
	 * silently rejected by WordPress with a generic 400.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Whether the current request may access the file. Pure decision seam.
	 *
	 * @param bool $logged_in Whether a user is authenticated.
	 * @param bool $can_view  Whether the user holds the view capability.
	 * @param bool $nonce_ok  Whether the per-id nonce verified.
	 * @return bool True only when all three conditions pass.
	 */
	public function can_access( bool $logged_in, bool $can_view, bool $nonce_ok ): bool {
		return $logged_in && $can_view && $nonce_ok;
	}

	/**
	 * Builds the nonced download URL for an asset's attachment.
	 *
	 * @param int $id Asset primary key.
	 * @return string The admin-post URL carrying the per-id nonce.
	 */
	public function download_url( int $id ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION . '&asset=' . $id ),
			self::NONCE_ACTION . '_' . $id
		);
	}

	/**
	 * Streams the requested asset's attachment after enforcing authentication,
	 * the view capability and the per-id nonce. Logged-out requests, missing
	 * capability, and bad nonces all fail with a 403; missing assets, missing
	 * stored paths, and unresolvable or absent files fail with a 404. Verified
	 * manually because it streams and exits.
	 */
	public function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the id is read here and the nonce is verified immediately below.
		$id = isset( $_GET['asset'] ) ? absint( wp_unslash( $_GET['asset'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce token is read here and verified immediately below.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		$logged_in = is_user_logged_in();
		$can_view  = current_user_can( Capabilities::VIEW );
		$nonce_ok  = (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION . '_' . $id );

		if ( $id <= 0 || ! $this->can_access( $logged_in, $can_view, $nonce_ok ) ) {
			wp_die(
				esc_html__( 'You are not allowed to access this file.', 'asset-registry' ),
				'',
				array( 'response' => 403 )
			);
		}

		$asset = $this->repository()->find( $id );
		if ( null === $asset || empty( $asset->attachment_path ) ) {
			wp_die(
				esc_html__( 'File not found.', 'asset-registry' ),
				'',
				array( 'response' => 404 )
			);
		}

		$abs = $this->store()->resolve( (string) $asset->attachment_path );
		if ( null === $abs || ! is_file( $abs ) ) {
			wp_die(
				esc_html__( 'File not found.', 'asset-registry' ),
				'',
				array( 'response' => 404 )
			);
		}

		$mime = wp_check_filetype( $abs )['type'];
		if ( empty( $mime ) ) {
			$mime = 'application/octet-stream';
		}

		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: inline; filename="' . basename( $abs ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $abs ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.Security.EscapeOutput.OutputNotEscaped -- verified-safe binary stream of a path resolved inside the protected store; escaping would corrupt the file.
		readfile( $abs );
		exit;
	}
}
