<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit\Files;

use AssetRegistry\Files\FileController;
use AssetRegistry\Tests\Unit\UnitTestCase;
use Brain\Monkey\Functions;

final class FileControllerTest extends UnitTestCase {

	public function test_denies_when_logged_out(): void {
		$controller = new FileController();
		$this->assertFalse( $controller->can_access( false, true, true ) );
	}

	public function test_denies_without_view_capability(): void {
		$controller = new FileController();
		$this->assertFalse( $controller->can_access( true, false, true ) );
	}

	public function test_denies_with_bad_nonce(): void {
		$controller = new FileController();
		$this->assertFalse( $controller->can_access( true, true, false ) );
	}

	public function test_allows_only_when_login_capability_and_nonce_all_pass(): void {
		$controller = new FileController();

		$this->assertTrue( $controller->can_access( true, true, true ) );
		// At least one more false-combination for completeness.
		$this->assertFalse( $controller->can_access( false, false, false ) );
	}

	public function test_register_hooks_admin_post_actions(): void {
		$hooks = array();
		Functions\expect( 'add_action' )
			->twice()
			->andReturnUsing(
				static function ( $hook, $callback ) use ( &$hooks ) {
					$hooks[ $hook ] = $callback;
					return true;
				}
			);

		( new FileController() )->register();

		$this->assertArrayHasKey( 'admin_post_' . FileController::ACTION, $hooks );
		$this->assertArrayHasKey( 'admin_post_nopriv_' . FileController::ACTION, $hooks );
		$this->assertSame( 'admin_post_asset_registry_file', 'admin_post_' . FileController::ACTION );
		$this->assertSame( 'admin_post_nopriv_asset_registry_file', 'admin_post_nopriv_' . FileController::ACTION );
		$this->assertTrue( is_callable( $hooks[ 'admin_post_' . FileController::ACTION ] ) );
		$this->assertTrue( is_callable( $hooks[ 'admin_post_nopriv_' . FileController::ACTION ] ) );
	}

	public function test_download_url_includes_action_asset_and_nonce(): void {
		Functions\when( 'admin_url' )->alias( static fn ( $path ) => 'https://example.test/wp-admin/' . $path );
		Functions\when( 'wp_nonce_url' )->alias( static fn ( $url, $action ) => $url . '&_wpnonce=' . $action );

		$url = ( new FileController() )->download_url( 7 );

		$this->assertStringContainsString( 'action=asset_registry_file', $url );
		$this->assertStringContainsString( 'asset=7', $url );
		$this->assertStringContainsString( 'asset_registry_file_7', $url );
	}
}
