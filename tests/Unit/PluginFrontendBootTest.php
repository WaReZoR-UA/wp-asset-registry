<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit;

use AssetRegistry\Plugin;
use Brain\Monkey\Functions;
use Mockery;

final class PluginFrontendBootTest extends UnitTestCase {

	public function test_init_registers_rest_and_shortcode_hooks_outside_admin(): void {
		Functions\when( 'load_plugin_textdomain' )->justReturn( true );
		Functions\when( 'is_admin' )->justReturn( false );

		$hooks = array();
		Functions\expect( 'add_action' )
			->andReturnUsing(
				static function ( $hook, $callback ) use ( &$hooks ) {
					$hooks[] = $hook;
					return true;
				}
			);

		Plugin::init();

		$this->assertContains( 'rest_api_init', $hooks );
		$this->assertContains( 'init', $hooks );
	}

	public function test_init_registers_rest_and_shortcode_hooks_inside_admin(): void {
		Functions\when( 'load_plugin_textdomain' )->justReturn( true );
		Functions\when( 'is_admin' )->justReturn( true );

		$hooks = array();
		Functions\expect( 'add_action' )
			->andReturnUsing(
				static function ( $hook, $callback ) use ( &$hooks ) {
					$hooks[] = $hook;
					return true;
				}
			);

		Plugin::init();

		$this->assertContains( 'rest_api_init', $hooks );
		$this->assertContains( 'init', $hooks );
		$this->assertContains( 'admin_menu', $hooks );
	}

	public function test_init_uses_closures_for_rest_and_shortcode_wiring(): void {
		Functions\when( 'load_plugin_textdomain' )->justReturn( true );
		Functions\when( 'is_admin' )->justReturn( false );

		Functions\expect( 'add_action' )
			->once()
			->with( 'rest_api_init', Mockery::type( 'Closure' ) );
		Functions\expect( 'add_action' )
			->once()
			->with( 'init', Mockery::type( 'Closure' ) );

		Plugin::init();
	}
}
