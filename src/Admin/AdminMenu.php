<?php
/**
 * Registers the Asset Registry admin menu and routes its single screen.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Admin;

use AssetRegistry\AssetRepository;
use AssetRegistry\Capabilities;

/**
 * Owns the top-level admin menu and dispatches between the list table and
 * the add/edit form based on the request action.
 */
final class AdminMenu {

	public const SLUG = 'asset-registry';

	/**
	 * Stores the add/edit screen handler and an optional repository.
	 *
	 * @param AssetForm            $form       The add/edit screen handler.
	 * @param AssetRepository|null $repository Injected for testing; built lazily otherwise.
	 */
	public function __construct(
		private AssetForm $form = new AssetForm(),
		private ?AssetRepository $repository = null
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
	 * Registers the top-level menu. Hooked on admin_menu.
	 */
	public function register(): void {
		add_menu_page(
			'Asset Registry',
			'Asset Registry',
			Capabilities::MANAGE,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-archive',
			56
		);
	}

	/**
	 * Decides which sub-screen to render for the given request.
	 *
	 * @param array<string, mixed> $request Sanitized request values.
	 * @return string Either 'form' or 'list'.
	 */
	public function screen_for( array $request ): string {
		$action = isset( $request['action'] ) ? (string) $request['action'] : '';
		return in_array( $action, array( 'new', 'edit' ), true ) ? 'form' : 'list';
	}

	/**
	 * Whether the current request may delete the given asset.
	 *
	 * @param int         $id    Asset primary key.
	 * @param string|null $nonce Submitted nonce value.
	 * @return bool True only when capability and per-id nonce both pass.
	 */
	public function can_delete( int $id, ?string $nonce ): bool {
		return current_user_can( Capabilities::MANAGE )
			&& (bool) wp_verify_nonce( (string) $nonce, AssetListTable::DELETE_NONCE . '_' . $id );
	}

	/**
	 * Renders the active sub-screen. Capability is re-checked here even
	 * though add_menu_page already gates the menu.
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to manage assets.', 'asset-registry' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen routing; mutations are nonce-checked in AssetForm and can_delete().
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'delete' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only id read; verified immediately by can_delete().
			$id = isset( $_GET['asset'] ) ? absint( wp_unslash( $_GET['asset'] ) ) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce token is read here and verified immediately by can_delete().
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : null;

			if ( $id > 0 && $this->can_delete( $id, $nonce ) ) {
				$this->repository()->delete( $id );
				wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&deleted=1' ) );
				exit;
			}

			wp_die( esc_html__( 'Security check failed.', 'asset-registry' ) );
		}

		if ( 'form' === $this->screen_for( array( 'action' => $action ) ) ) {
			$this->form->render();
			return;
		}

		$this->render_list();
	}

	/**
	 * Renders the list-table screen. Integration-tested manually.
	 */
	private function render_list(): void {
		$table = new AssetListTable();
		$table->prepare_items();
		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Asset Registry', 'asset-registry' ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only success flag set by a redirect after a nonce-checked deletion.
		$deleted = isset( $_GET['deleted'] ) ? sanitize_text_field( wp_unslash( $_GET['deleted'] ) ) : '';
		if ( '1' === $deleted ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Asset deleted.', 'asset-registry' ) . '</p></div>';
		}

		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&action=new' ) ) . '" class="page-title-action">' . esc_html__( 'Add New', 'asset-registry' ) . '</a><hr class="wp-header-end">';
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		$table->search_box( esc_html__( 'Search assets', 'asset-registry' ), 'asset-search' );
		$table->display();
		echo '</form></div>';
	}
}
