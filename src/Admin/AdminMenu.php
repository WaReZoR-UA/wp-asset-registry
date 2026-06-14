<?php
/**
 * Registers the Asset Registry admin menu and routes its single screen.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Admin;

use AssetRegistry\Capabilities;

/**
 * Owns the top-level admin menu and dispatches between the list table and
 * the add/edit form based on the request action.
 */
final class AdminMenu {

	public const SLUG = 'asset-registry';

	/**
	 * Stores the add/edit screen handler.
	 *
	 * @param AssetForm $form The add/edit screen handler.
	 */
	public function __construct( private AssetForm $form = new AssetForm() ) {}

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
	 * Renders the active sub-screen. Capability is re-checked here even
	 * though add_menu_page already gates the menu.
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to manage assets.', 'asset-registry' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen routing; mutations are nonce-checked in AssetForm.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

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
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&action=new' ) ) . '" class="page-title-action">' . esc_html__( 'Add New', 'asset-registry' ) . '</a><hr class="wp-header-end">';
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		$table->search_box( esc_html__( 'Search assets', 'asset-registry' ), 'asset-search' );
		$table->display();
		echo '</form></div>';
	}
}
