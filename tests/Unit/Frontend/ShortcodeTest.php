<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit\Frontend;

use AssetRegistry\Category;
use AssetRegistry\Frontend\Shortcode;
use AssetRegistry\Rest\AssetController;
use AssetRegistry\Status;
use AssetRegistry\Tests\Unit\UnitTestCase;
use Brain\Monkey\Functions;
use Mockery;

final class ShortcodeTest extends UnitTestCase {

	public function test_localized_data_includes_rest_url_nonce_and_can_view_flag(): void {
		Functions\when( 'rest_url' )->returnArg();
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_create_nonce' )->justReturn( 'NONCE' );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( '__' )->returnArg();

		$data = ( new Shortcode() )->localized_data();

		$this->assertStringContainsString( AssetController::NAMESPACE, $data['restUrl'] );
		$this->assertSame( 'NONCE', $data['nonce'] );
		$this->assertTrue( $data['canView'] );
		$this->assertSame( 12, $data['perPage'] );

		$this->assertSame( Status::options(), $data['statuses'] );
		$this->assertSame( Category::options(), $data['categories'] );
		$this->assertNotEmpty( $data['statuses'] );
		$this->assertNotEmpty( $data['categories'] );

		$this->assertArrayHasKey( 'i18n', $data );
		$this->assertArrayHasKey( 'search', $data['i18n'] );
		$this->assertArrayHasKey( 'noResults', $data['i18n'] );
	}

	public function test_localized_data_reports_can_view_false_for_anonymous_visitor(): void {
		Functions\when( 'rest_url' )->returnArg();
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_create_nonce' )->justReturn( 'NONCE' );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( '__' )->returnArg();

		$data = ( new Shortcode() )->localized_data();

		$this->assertFalse( $data['canView'] );
	}

	public function test_register_adds_the_asset_registry_shortcode(): void {
		Functions\expect( 'add_shortcode' )
			->once()
			->with( 'asset_registry', Mockery::type( 'array' ) );

		( new Shortcode() )->register();
	}
}
