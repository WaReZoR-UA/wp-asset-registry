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
use AssetRegistry\Category;
use AssetRegistry\Sanitizer;
use AssetRegistry\Status;

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
	 * Sanitizes, validates and persists a submission.
	 *
	 * @param array<string, mixed> $raw   Untrusted request values.
	 * @param string|null          $nonce Submitted nonce value.
	 * @return array{saved: bool, id: int, error: string} The persistence outcome.
	 */
	public function handle( array $raw, ?string $nonce ): array {
		if ( ! $this->can_submit( $nonce ) ) {
			return array(
				'saved' => false,
				'id'    => 0,
				'error' => '',
			);
		}

		$clean = Sanitizer::sanitize( $raw );
		$id    = isset( $raw['id'] ) ? (int) $raw['id'] : 0;

		if ( '' === $clean['asset_tag'] || '' === $clean['name'] || '' === $clean['category'] ) {
			return array(
				'saved' => false,
				'id'    => $id,
				'error' => 'required',
			);
		}

		$existing = $this->repository()->find_by_tag( $clean['asset_tag'] );
		if ( $existing instanceof Asset && $existing->id !== $id ) {
			return array(
				'saved' => false,
				'id'    => $id,
				'error' => 'duplicate',
			);
		}

		$asset = Asset::from_array( $clean );

		// Attachment upload is handled by the file-store integration.
		if ( $id > 0 ) {
			$ok = $this->repository()->update( $id, $asset );
			return array(
				'saved' => $ok,
				'id'    => $id,
				'error' => $ok ? '' : 'save_failed',
			);
		}

		$new_id = $this->repository()->insert( $asset );
		if ( $new_id > 0 ) {
			return array(
				'saved' => true,
				'id'    => $new_id,
				'error' => '',
			);
		}

		return array(
			'saved' => false,
			'id'    => 0,
			'error' => 'save_failed',
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
					$this->render_fields();
					return;
				}

				$this->render_error_notice( $result['error'] );
				// $_POST is sanitized field-by-field inside Sanitizer::sanitize().
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via can_submit().
				$this->render_fields( Sanitizer::sanitize( wp_unslash( $_POST ) ) );
				return;
			}
		}

		$this->render_fields();
	}

	/**
	 * Echoes the appropriate error notice for a failed submission.
	 *
	 * @param string $error Error code from handle().
	 */
	private function render_error_notice( string $error ): void {
		$messages = array(
			'required'    => __( 'Asset tag, name, and category are required.', 'asset-registry' ),
			'duplicate'   => __( 'An asset with that tag already exists.', 'asset-registry' ),
			'save_failed' => __( 'Could not save the asset.', 'asset-registry' ),
		);

		if ( ! isset( $messages[ $error ] ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html( $messages[ $error ] ) . '</p></div>';
	}

	/**
	 * Outputs the form fields. Integration-tested manually.
	 *
	 * @param array<string, mixed>|null $values Pre-filled values; when null the
	 *                                          form prefills from the edited record.
	 */
	private function render_fields( ?array $values = null ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill of an existing record.
		$id = isset( $_GET['asset'] ) ? absint( $_GET['asset'] ) : 0;

		if ( null === $values ) {
			$asset  = $id > 0 ? $this->repository()->find( $id ) : null;
			$values = $asset instanceof Asset ? $asset->to_array() : array();
		}

		$asset_tag     = (string) ( $values['asset_tag'] ?? '' );
		$name          = (string) ( $values['name'] ?? '' );
		$category      = (string) ( $values['category'] ?? '' );
		$status        = (string) ( $values['status'] ?? Status::Active->value );
		$location      = (string) ( $values['location'] ?? '' );
		$assigned_to   = (string) ( $values['assigned_to'] ?? '' );
		$purchase_date = (string) ( $values['purchase_date'] ?? '' );
		$value         = isset( $values['value'] ) ? (string) $values['value'] : '';
		$notes         = (string) ( $values['notes'] ?? '' );

		echo '<div class="wrap"><h1>' . esc_html__( 'Asset', 'asset-registry' ) . '</h1>';
		echo '<form method="post">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		if ( $id > 0 ) {
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" />';
		}

		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="asset_tag">' . esc_html__( 'Asset tag', 'asset-registry' ) . '</label></th>';
		echo '<td><input type="text" id="asset_tag" name="asset_tag" class="regular-text" required value="' . esc_attr( $asset_tag ) . '" /></td></tr>';

		echo '<tr><th scope="row"><label for="name">' . esc_html__( 'Name', 'asset-registry' ) . '</label></th>';
		echo '<td><input type="text" id="name" name="name" class="regular-text" required value="' . esc_attr( $name ) . '" /></td></tr>';

		echo '<tr><th scope="row"><label for="category">' . esc_html__( 'Category', 'asset-registry' ) . '</label></th>';
		echo '<td><select id="category" name="category" required>';
		echo '<option value="">' . esc_html__( '-- Select --', 'asset-registry' ) . '</option>';
		foreach ( Category::options() as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $category, $slug, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="status">' . esc_html__( 'Status', 'asset-registry' ) . '</label></th>';
		echo '<td><select id="status" name="status">';
		foreach ( Status::options() as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $status, $slug, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="location">' . esc_html__( 'Location', 'asset-registry' ) . '</label></th>';
		echo '<td><input type="text" id="location" name="location" class="regular-text" value="' . esc_attr( $location ) . '" /></td></tr>';

		echo '<tr><th scope="row"><label for="assigned_to">' . esc_html__( 'Assigned to', 'asset-registry' ) . '</label></th>';
		echo '<td><input type="text" id="assigned_to" name="assigned_to" class="regular-text" value="' . esc_attr( $assigned_to ) . '" /></td></tr>';

		echo '<tr><th scope="row"><label for="purchase_date">' . esc_html__( 'Purchase date', 'asset-registry' ) . '</label></th>';
		echo '<td><input type="date" id="purchase_date" name="purchase_date" value="' . esc_attr( $purchase_date ) . '" /></td></tr>';

		echo '<tr><th scope="row"><label for="value">' . esc_html__( 'Value', 'asset-registry' ) . '</label></th>';
		echo '<td><input type="number" step="0.01" min="0" id="value" name="value" value="' . esc_attr( $value ) . '" /></td></tr>';

		echo '<tr><th scope="row"><label for="notes">' . esc_html__( 'Notes', 'asset-registry' ) . '</label></th>';
		echo '<td><textarea id="notes" name="notes" class="large-text" rows="4">' . esc_textarea( $notes ) . '</textarea></td></tr>';

		echo '</tbody></table>';

		echo '<p><input type="submit" class="button button-primary" value="' . esc_attr__( 'Save asset', 'asset-registry' ) . '" /></p>';
		echo '</form></div>';
	}
}
