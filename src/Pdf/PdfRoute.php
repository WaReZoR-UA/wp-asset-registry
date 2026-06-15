<?php
/**
 * Gated admin-post route that streams an asset's PDF spec sheet.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Pdf;

use AssetRegistry\AssetRepository;
use AssetRegistry\Capabilities;

/**
 * Registers the admin-post endpoint, builds its per-asset nonced URL, and
 * streams the generated PDF only when the request carries the view capability
 * and a valid per-id nonce. The authorization decision is isolated in the pure
 * can_generate() seam so it can be unit-tested without the WordPress runtime.
 */
final class PdfRoute {

	public const ACTION       = 'asset_registry_pdf';
	public const NONCE_ACTION = 'asset_registry_pdf';

	/**
	 * Stores the optional repository dependency.
	 *
	 * @param AssetRepository|null $repository Injected for testing; built lazily otherwise.
	 */
	public function __construct( private ?AssetRepository $repository = null ) {}

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
	 * Whether the current request may generate a PDF. Pure decision seam.
	 *
	 * @param bool $can_view Whether the user holds the view capability.
	 * @param bool $nonce_ok Whether the per-id nonce verified.
	 * @return bool True only when both the capability and nonce pass.
	 */
	public function can_generate( bool $can_view, bool $nonce_ok ): bool {
		return $can_view && $nonce_ok;
	}

	/**
	 * Builds the nonced download URL for an asset's PDF.
	 *
	 * @param int $id Asset primary key.
	 * @return string The admin-post URL carrying the per-id nonce.
	 */
	public function download_url( int $id ): string {
		// add_query_arg returns a raw URL with literal "&" separators (not the
		// HTML-escaped "&amp;" wp_nonce_url produces), so the link survives a
		// trip through JSON/JS (setAttribute); HTML consumers escape via esc_url.
		return add_query_arg(
			array(
				'action'   => self::ACTION,
				'asset'    => $id,
				'_wpnonce' => wp_create_nonce( self::NONCE_ACTION . '_' . $id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Streams the requested asset's PDF after enforcing the view capability and
	 * the per-id nonce. Logged-out requests, missing capability, and bad nonces
	 * all fail with a 403. Verified manually because it streams and exits.
	 */
	public function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the id is read here and the nonce is verified immediately below.
		$id = isset( $_GET['asset'] ) ? absint( wp_unslash( $_GET['asset'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce token is read here and verified immediately below.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		$can_view = current_user_can( Capabilities::VIEW );
		$nonce_ok = (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION . '_' . $id );

		if ( $id <= 0 || ! $this->can_generate( $can_view, $nonce_ok ) ) {
			wp_die(
				esc_html__( 'You are not allowed to download this asset.', 'asset-registry' ),
				'',
				array( 'response' => 403 )
			);
		}

		$asset = $this->repository()->find( $id );
		if ( null === $asset ) {
			wp_die(
				esc_html__( 'Asset not found.', 'asset-registry' ),
				'',
				array( 'response' => 404 )
			);
		}

		$sheet = new SpecSheet();
		$pdf   = $sheet->render( $asset );

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $sheet->filename( $asset ) . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw binary PDF stream; escaping would corrupt the document.
		echo $pdf;
		exit;
	}
}
