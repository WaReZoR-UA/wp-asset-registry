<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit\Pdf;

use AssetRegistry\Pdf\PdfRoute;
use AssetRegistry\Tests\Unit\UnitTestCase;
use Brain\Monkey\Functions;
use Mockery;

final class PdfRouteTest extends UnitTestCase {

	public function test_denies_generation_without_view_capability(): void {
		$route = new PdfRoute();
		$this->assertFalse( $route->can_generate( false, true ) );
	}

	public function test_denies_generation_with_invalid_nonce(): void {
		$route = new PdfRoute();
		$this->assertFalse( $route->can_generate( true, false ) );
	}

	public function test_allows_generation_with_capability_and_valid_nonce(): void {
		$route = new PdfRoute();
		$this->assertTrue( $route->can_generate( true, true ) );
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

		( new PdfRoute() )->register();

		$this->assertArrayHasKey( 'admin_post_' . PdfRoute::ACTION, $hooks );
		$this->assertArrayHasKey( 'admin_post_nopriv_' . PdfRoute::ACTION, $hooks );
		$this->assertSame( 'admin_post_asset_registry_pdf', 'admin_post_' . PdfRoute::ACTION );
		$this->assertSame( 'admin_post_nopriv_asset_registry_pdf', 'admin_post_nopriv_' . PdfRoute::ACTION );
		$this->assertTrue( is_callable( $hooks[ 'admin_post_' . PdfRoute::ACTION ] ) );
		$this->assertTrue( is_callable( $hooks[ 'admin_post_nopriv_' . PdfRoute::ACTION ] ) );
	}

	public function test_download_url_includes_action_asset_and_nonce(): void {
		Functions\when( 'admin_url' )->alias( static fn ( $path ) => 'https://example.test/wp-admin/' . $path );
		Functions\when( 'wp_create_nonce' )->alias( static fn ( $action ) => $action );
		Functions\when( 'add_query_arg' )->alias( static fn ( $args, $url ) => $url . '?' . http_build_query( $args ) );

		$url = ( new PdfRoute() )->download_url( 7 );

		$this->assertStringContainsString( 'action=asset_registry_pdf', $url );
		$this->assertStringContainsString( 'asset=7', $url );
		$this->assertStringContainsString( 'asset_registry_pdf_7', $url );
		// Raw URL with literal "&" so it survives JSON/JS without breaking the query string.
		$this->assertStringNotContainsString( '&amp;', $url );
	}
}
