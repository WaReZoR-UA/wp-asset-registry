<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit;

use AssetRegistry\Capabilities;
use Brain\Monkey\Functions;
use Mockery;

final class CapabilitiesTest extends UnitTestCase {

	public function test_role_map_grants_expected_capabilities(): void {
		$roles = Capabilities::roles();

		$this->assertArrayHasKey( Capabilities::ROLE_MANAGER, $roles );
		$this->assertArrayHasKey( Capabilities::ROLE_VIEWER, $roles );

		$this->assertContains( Capabilities::MANAGE, $roles[ Capabilities::ROLE_MANAGER ]['caps'] );
		$this->assertContains( Capabilities::VIEW, $roles[ Capabilities::ROLE_MANAGER ]['caps'] );

		// Viewer is read-only: has view, never manage.
		$this->assertContains( Capabilities::VIEW, $roles[ Capabilities::ROLE_VIEWER ]['caps'] );
		$this->assertNotContains( Capabilities::MANAGE, $roles[ Capabilities::ROLE_VIEWER ]['caps'] );
	}

	public function test_add_roles_registers_each_role_and_grants_admin(): void {
		Functions\expect( 'add_role' )
			->once()
			->with( Capabilities::ROLE_MANAGER, 'Registry Manager', Mockery::type( 'array' ) );
		Functions\expect( 'add_role' )
			->once()
			->with( Capabilities::ROLE_VIEWER, 'Registry Viewer', Mockery::type( 'array' ) );

		$admin = Mockery::mock( 'WP_Role' );
		$admin->shouldReceive( 'add_cap' )->once()->with( Capabilities::MANAGE );
		$admin->shouldReceive( 'add_cap' )->once()->with( Capabilities::VIEW );
		Functions\expect( 'get_role' )->once()->with( 'administrator' )->andReturn( $admin );

		Capabilities::add_roles();
	}

	public function test_remove_roles_unregisters_each_role_and_revokes_admin(): void {
		Functions\expect( 'remove_role' )->once()->with( Capabilities::ROLE_MANAGER );
		Functions\expect( 'remove_role' )->once()->with( Capabilities::ROLE_VIEWER );

		$admin = Mockery::mock( 'WP_Role' );
		$admin->shouldReceive( 'remove_cap' )->once()->with( Capabilities::MANAGE );
		$admin->shouldReceive( 'remove_cap' )->once()->with( Capabilities::VIEW );
		Functions\expect( 'get_role' )->once()->with( 'administrator' )->andReturn( $admin );

		Capabilities::remove_roles();
	}
}
