<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit;

use AssetRegistry\Asset;

final class AssetTest extends UnitTestCase {

	/**
	 * @return array<string, mixed>
	 */
	private function row(): array {
		return array(
			'id'              => 7,
			'asset_tag'       => 'LAP-0007',
			'name'            => 'ThinkPad X1',
			'category'        => 'laptop',
			'status'          => 'active',
			'location'        => 'HQ Floor 2',
			'assigned_to'     => 'A. Engineer',
			'purchase_date'   => '2025-03-14',
			'value'           => '1499.00',
			'notes'           => 'Carbon, 32GB',
			'attachment_path' => '2026/06/lap-0007.pdf',
			'created_at'      => '2026-06-01 10:00:00',
			'updated_at'      => '2026-06-02 11:00:00',
		);
	}

	public function test_from_array_maps_every_column(): void {
		$asset = Asset::from_array( $this->row() );

		$this->assertSame( 7, $asset->id );
		$this->assertSame( 'LAP-0007', $asset->asset_tag );
		$this->assertSame( 'ThinkPad X1', $asset->name );
		$this->assertSame( 'laptop', $asset->category );
		$this->assertSame( 'active', $asset->status );
		$this->assertSame( 'HQ Floor 2', $asset->location );
		$this->assertSame( 'A. Engineer', $asset->assigned_to );
		$this->assertSame( '2025-03-14', $asset->purchase_date );
		$this->assertSame( 1499.00, $asset->value );
		$this->assertSame( 'Carbon, 32GB', $asset->notes );
		$this->assertSame( '2026/06/lap-0007.pdf', $asset->attachment_path );
		$this->assertSame( '2026-06-01 10:00:00', $asset->created_at );
		$this->assertSame( '2026-06-02 11:00:00', $asset->updated_at );
	}

	public function test_from_array_tolerates_missing_id_and_nullables(): void {
		$row = $this->row();
		unset( $row['id'], $row['purchase_date'], $row['attachment_path'], $row['notes'] );

		$asset = Asset::from_array( $row );

		$this->assertNull( $asset->id );
		$this->assertNull( $asset->purchase_date );
		$this->assertNull( $asset->attachment_path );
		$this->assertSame( '', $asset->notes );
	}

	public function test_to_array_round_trips_persistable_columns(): void {
		$asset = Asset::from_array( $this->row() );
		$out   = $asset->to_array();

		$this->assertSame( 'LAP-0007', $out['asset_tag'] );
		$this->assertSame( 'laptop', $out['category'] );
		$this->assertSame( 1499.00, $out['value'] );
		$this->assertArrayNotHasKey( 'id', $out );
		$this->assertArrayNotHasKey( 'created_at', $out );
		$this->assertArrayNotHasKey( 'updated_at', $out );
	}
}
