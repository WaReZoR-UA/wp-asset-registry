<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit\Support;

use AssetRegistry\Support\FieldVisibility;
use AssetRegistry\Tests\Unit\UnitTestCase;

final class FieldVisibilityTest extends UnitTestCase {

	/**
	 * A fully-populated row mirroring the asset columns plus DB-only fields.
	 *
	 * @return array<string, mixed> The sample row.
	 */
	private function full_row(): array {
		return array(
			'id'              => 42,
			'asset_tag'       => 'LAP-9',
			'name'            => 'X1 Carbon',
			'category'        => 'laptop',
			'status'          => 'active',
			'location'        => 'HQ',
			'assigned_to'     => 'Jane Roe',
			'purchase_date'   => '2024-01-15',
			'value'           => 1200.50,
			'notes'           => 'Keep dry.',
			'attachment_path' => '2024/01/manual.pdf',
			'created_at'      => '2024-01-15 10:00:00',
			'updated_at'      => '2024-02-01 12:00:00',
		);
	}

	public function test_public_context_exposes_only_name_category_status(): void {
		$out = FieldVisibility::filter( $this->full_row(), false );

		$this->assertSame( array( 'name', 'category', 'status' ), array_keys( $out ) );
		$this->assertSame( 'X1 Carbon', $out['name'] );
		$this->assertSame( 'laptop', $out['category'] );
		$this->assertSame( 'active', $out['status'] );
	}

	public function test_authenticated_context_exposes_all_fields(): void {
		$out = FieldVisibility::filter( $this->full_row(), true );

		$this->assertArrayHasKey( 'asset_tag', $out );
		$this->assertArrayHasKey( 'name', $out );
		$this->assertArrayHasKey( 'category', $out );
		$this->assertArrayHasKey( 'status', $out );
		$this->assertArrayHasKey( 'location', $out );
		$this->assertArrayHasKey( 'assigned_to', $out );
		$this->assertArrayHasKey( 'purchase_date', $out );
		$this->assertArrayHasKey( 'value', $out );
		$this->assertArrayHasKey( 'notes', $out );
		$this->assertArrayHasKey( 'has_attachment', $out );

		$this->assertArrayNotHasKey( 'attachment_path', $out );
	}

	public function test_never_returns_keys_outside_the_allowed_set(): void {
		$row = array_merge(
			$this->full_row(),
			array(
				'bogus'      => 'nope',
				'is_admin'   => true,
				'secret_key' => 'leak',
			)
		);

		foreach ( array( true, false ) as $can_view ) {
			$out     = FieldVisibility::filter( $row, $can_view );
			$allowed = FieldVisibility::allowed_keys( $can_view );

			foreach ( array_keys( $out ) as $key ) {
				$this->assertContains(
					$key,
					$allowed,
					"Key '{$key}' leaked outside the allowed set (can_view=" . ( $can_view ? 'true' : 'false' ) . ').'
				);
			}

			$this->assertArrayNotHasKey( 'id', $out );
			$this->assertArrayNotHasKey( 'created_at', $out );
			$this->assertArrayNotHasKey( 'updated_at', $out );
			$this->assertArrayNotHasKey( 'attachment_path', $out );
			$this->assertArrayNotHasKey( 'bogus', $out );
		}
	}

	public function test_has_attachment_true_when_attachment_path_present(): void {
		$out = FieldVisibility::filter( $this->full_row(), true );
		$this->assertTrue( $out['has_attachment'] );
	}

	public function test_has_attachment_false_when_attachment_path_empty_or_null(): void {
		$row_null = array_merge( $this->full_row(), array( 'attachment_path' => null ) );
		$out_null = FieldVisibility::filter( $row_null, true );
		$this->assertFalse( $out_null['has_attachment'] );

		$row_empty = array_merge( $this->full_row(), array( 'attachment_path' => '' ) );
		$out_empty = FieldVisibility::filter( $row_empty, true );
		$this->assertFalse( $out_empty['has_attachment'] );
	}

	public function test_missing_keys_are_omitted_not_filled(): void {
		$row = array(
			'name'   => 'Partial',
			'status' => 'retired',
		);

		$out = FieldVisibility::filter( $row, false );
		$this->assertSame( array( 'name', 'status' ), array_keys( $out ) );
	}
}
