<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit\Admin;

use AssetRegistry\Admin\AdminMenu;
use AssetRegistry\Tests\Unit\UnitTestCase;
use Brain\Monkey\Functions;
use Mockery;

final class AdminMenuTest extends UnitTestCase {

	public function test_register_adds_top_level_menu_with_manage_cap(): void {
		Functions\expect( 'add_menu_page' )
			->once()
			->with(
				'Asset Registry',
				'Asset Registry',
				'manage_assets',
				AdminMenu::SLUG,
				Mockery_any(),
				'dashicons-archive',
				56
			)
			->andReturn( 'toplevel_page_asset-registry' );
		Functions\when( 'add_action' )->justReturn( true );

		( new AdminMenu() )->register();
	}

	public function test_register_wires_load_hook_to_the_returned_suffix(): void {
		Functions\expect( 'add_menu_page' )
			->once()
			->andReturn( 'toplevel_page_asset-registry' );
		Functions\expect( 'add_action' )
			->once()
			->with( 'load-toplevel_page_asset-registry', Mockery::type( 'array' ) );

		( new AdminMenu() )->register();
	}

	public function test_screen_for_returns_form_on_new_or_edit_else_list(): void {
		$menu = new AdminMenu();

		$this->assertSame( 'form', $menu->screen_for( array( 'action' => 'new' ) ) );
		$this->assertSame( 'form', $menu->screen_for( array( 'action' => 'edit' ) ) );
		$this->assertSame( 'list', $menu->screen_for( array( 'action' => 'delete' ) ) );
		$this->assertSame( 'list', $menu->screen_for( array() ) );
	}

	public function test_can_delete_allows_with_capability_and_valid_nonce(): void {
		$menu = new AdminMenu();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		$this->assertTrue( $menu->can_delete( 7, 'good-nonce' ) );
	}

	public function test_can_delete_denies_without_capability(): void {
		$menu = new AdminMenu();

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		$this->assertFalse( $menu->can_delete( 7, 'good-nonce' ) );
	}

	public function test_can_delete_denies_with_bad_nonce(): void {
		$menu = new AdminMenu();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		$this->assertFalse( $menu->can_delete( 7, 'bad-nonce' ) );
	}
}

/**
 * Local helper so the test reads cleanly; matches any callback argument.
 */
function Mockery_any(): \Mockery\Matcher\Any {
	return \Mockery::any();
}
