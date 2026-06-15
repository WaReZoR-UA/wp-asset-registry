<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit;

use AssetRegistry\Status;

final class StatusTest extends UnitTestCase {

	public function test_backing_values_are_stable_slugs(): void {
		$this->assertSame( 'active', Status::Active->value );
		$this->assertSame( 'in_repair', Status::InRepair->value );
		$this->assertSame( 'retired', Status::Retired->value );
	}

	public function test_values_lists_every_case_slug(): void {
		$this->assertSame( array( 'active', 'in_repair', 'retired' ), Status::values() );
	}

	public function test_options_maps_slug_to_human_label(): void {
		$this->assertSame(
			array(
				'active'    => 'Active',
				'in_repair' => 'In Repair',
				'retired'   => 'Retired',
			),
			Status::options()
		);
	}

	public function test_label_returns_human_label_for_case(): void {
		$this->assertSame( 'In Repair', Status::InRepair->label() );
	}

	public function test_is_valid_accepts_known_slug_and_rejects_unknown(): void {
		$this->assertTrue( Status::is_valid( 'retired' ) );
		$this->assertFalse( Status::is_valid( 'exploded' ) );
		$this->assertFalse( Status::is_valid( '' ) );
	}
}
