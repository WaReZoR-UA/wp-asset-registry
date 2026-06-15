<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit;

use AssetRegistry\Plugin;
use Brain\Monkey\Functions;
use Mockery;

final class PluginAdminBootTest extends UnitTestCase {

	public function test_init_loads_textdomain(): void {
		Functions\expect( 'load_plugin_textdomain' )
			->once()
			->with( 'asset-registry' );
		Functions\when( 'is_admin' )->justReturn( false );

		Plugin::init();
	}

	public function test_init_hooks_admin_menu_when_in_admin(): void {
		Functions\when( 'load_plugin_textdomain' )->justReturn( true );
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\expect( 'add_action' )
			->atLeast()->once()
			->with( 'admin_menu', Mockery::type( 'callable' ) );

		Plugin::init();
	}

	public function test_init_does_not_hook_admin_menu_outside_admin(): void {
		Functions\when( 'load_plugin_textdomain' )->justReturn( true );
		Functions\when( 'is_admin' )->justReturn( false );

		$hooks = array();
		Functions\when( 'add_action' )->alias(
			static function ( $hook, $callback ) use ( &$hooks ) {
				$hooks[] = $hook;
				return true;
			}
		);

		Plugin::init();

		// The front-end and REST hooks run everywhere, but the admin menu
		// must not be wired outside the admin context.
		$this->assertNotContains( 'admin_menu', $hooks );
	}
}
