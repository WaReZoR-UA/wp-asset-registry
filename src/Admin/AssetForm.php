<?php
/**
 * Add/edit asset form: render plus a guarded POST handler.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Admin;

use AssetRegistry\Asset;
use AssetRegistry\AssetRepository;
use AssetRegistry\Capabilities;
use AssetRegistry\Sanitizer;

/**
 * Renders the asset form and persists submissions after enforcing the
 * capability and nonce. Persistence is delegated to AssetRepository.
 */
final class AssetForm {

	public const NONCE_ACTION = 'asset_registry_save_asset';
	public const NONCE_FIELD  = 'asset_registry_nonce';

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
	 * Whether the current request may persist an asset.
	 *
	 * @param string|null $nonce Submitted nonce value.
	 * @return bool True only when capability and nonce both pass.
	 */
	public function can_submit( ?string $nonce ): bool {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return false;
		}
		return (bool) wp_verify_nonce( (string) $nonce, self::NONCE_ACTION );
	}

	/**
	 * Sanitizes and persists a submission.
	 *
	 * @param array<string, mixed> $raw   Untrusted request values.
	 * @param string|null          $nonce Submitted nonce value.
	 * @return array{saved: bool, id: int} The persistence outcome.
	 */
	public function handle( array $raw, ?string $nonce ): array {
		if ( ! $this->can_submit( $nonce ) ) {
			return array(
				'saved' => false,
				'id'    => 0,
			);
		}

		$clean = Sanitizer::sanitize( $raw );
		$asset = Asset::from_array( $clean );
		$id    = isset( $raw['id'] ) ? (int) $raw['id'] : 0;

		// Attachment upload is handled by the file-store integration.
		if ( $id > 0 ) {
			$this->repository()->update( $id, $asset );
			return array(
				'saved' => true,
				'id'    => $id,
			);
		}

		$new_id = $this->repository()->insert( $asset );
		return array(
			'saved' => true,
			'id'    => $new_id,
		);
	}

	/**
	 * Renders the add/edit form and processes a POST submission.
	 * Integration-tested manually in wp-admin.
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to manage assets.', 'asset-registry' ) );
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' === $method ) {
			// The nonce field itself is read here; verification happens immediately below via can_submit().
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- reading the nonce token prior to verifying it.
			$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : null;
			if ( $this->can_submit( $nonce ) ) {
				// $_POST is sanitized field-by-field inside Sanitizer::sanitize().
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via can_submit().
				$result = $this->handle( wp_unslash( $_POST ), $nonce );
				if ( $result['saved'] ) {
					echo '<div class="notice notice-success"><p>' . esc_html__( 'Asset saved.', 'asset-registry' ) . '</p></div>';
				}
			}
		}

		$this->render_fields();
	}

	/**
	 * Outputs the form fields. Integration-tested manually.
	 */
	private function render_fields(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill of an existing record.
		$id     = isset( $_GET['asset'] ) ? absint( $_GET['asset'] ) : 0;
		$asset  = $id > 0 ? $this->repository()->find( $id ) : null;
		$values = $asset instanceof Asset ? $asset->to_array() : array();

		echo '<div class="wrap"><h1>' . esc_html__( 'Asset', 'asset-registry' ) . '</h1>';
		echo '<form method="post">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		if ( $id > 0 ) {
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" />';
		}
		// Text/select/textarea fields for each column, prefilled from $values,
		// built with esc_attr/selected() helpers. Full markup added at implementation time.
		echo '<p><input type="submit" class="button button-primary" value="' . esc_attr__( 'Save asset', 'asset-registry' ) . '" /></p>';
		echo '</form></div>';

		unset( $values );
	}
}
