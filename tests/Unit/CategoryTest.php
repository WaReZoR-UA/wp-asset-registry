<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit;

use AssetRegistry\Category;

final class CategoryTest extends UnitTestCase {

	public function test_backing_values_are_stable_slugs(): void {
		$this->assertSame( 'laptop', Category::Laptop->value );
		$this->assertSame( 'vehicle', Category::Vehicle->value );
		$this->assertSame( 'tool', Category::Tool->value );
		$this->assertSame( 'furniture', Category::Furniture->value );
	}

	public function test_values_lists_every_case_slug(): void {
		$this->assertSame( array( 'laptop', 'vehicle', 'tool', 'furniture' ), Category::values() );
	}

	public function test_options_maps_slug_to_human_label(): void {
		$this->assertSame(
			array(
				'laptop'    => 'Laptop',
				'vehicle'   => 'Vehicle',
				'tool'      => 'Tool',
				'furniture' => 'Furniture',
			),
			Category::options()
		);
	}

	public function test_is_valid_accepts_known_slug_and_rejects_unknown(): void {
		$this->assertTrue( Category::is_valid( 'tool' ) );
		$this->assertFalse( Category::is_valid( 'spaceship' ) );
		$this->assertFalse( Category::is_valid( '' ) );
	}
}
