<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit\Admin;

use AssetRegistry\Admin\AdminMenu;
use AssetRegistry\Tests\Unit\UnitTestCase;
use Brain\Monkey\Functions;

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
			);

		( new AdminMenu() )->register();
	}

	public function test_screen_for_returns_form_on_new_or_edit_else_list(): void {
		$menu = new AdminMenu();

		$this->assertSame( 'form', $menu->screen_for( array( 'action' => 'new' ) ) );
		$this->assertSame( 'form', $menu->screen_for( array( 'action' => 'edit' ) ) );
		$this->assertSame( 'list', $menu->screen_for( array( 'action' => 'delete' ) ) );
		$this->assertSame( 'list', $menu->screen_for( array() ) );
	}
}

/**
 * Local helper so the test reads cleanly; matches any callback argument.
 */
function Mockery_any(): \Mockery\Matcher\Any {
	return \Mockery::any();
}
