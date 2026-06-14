<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit;

use AssetRegistry\Sanitizer;
use AssetRegistry\Status;
use Brain\Monkey\Functions;

final class SanitizerTest extends UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Pass-through doubles for the WP sanitizers the class relies on.
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_textarea_field' )->returnArg();
		Functions\when( 'absint' )->alias( static fn ( $v ) => abs( (int) $v ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function valid_raw(): array {
		return array(
			'asset_tag'     => '  LAP-0007 ',
			'name'          => 'ThinkPad X1',
			'category'      => 'laptop',
			'status'        => 'in_repair',
			'location'      => 'HQ Floor 2',
			'assigned_to'   => 'A. Engineer',
			'purchase_date' => '2025-03-14',
			'value'         => '1,499.00',
			'notes'         => 'Carbon, 32GB',
		);
	}

	public function test_sanitizes_and_normalizes_valid_input(): void {
		$clean = Sanitizer::sanitize( $this->valid_raw() );

		$this->assertSame( 'LAP-0007', $clean['asset_tag'] );
		$this->assertSame( 'laptop', $clean['category'] );
		$this->assertSame( 'in_repair', $clean['status'] );
		$this->assertSame( '2025-03-14', $clean['purchase_date'] );
		$this->assertSame( 1499.00, $clean['value'] );
	}

	public function test_unknown_category_becomes_empty(): void {
		$raw             = $this->valid_raw();
		$raw['category'] = 'spaceship';

		$this->assertSame( '', Sanitizer::sanitize( $raw )['category'] );
	}

	public function test_unknown_or_missing_status_defaults_to_active(): void {
		$raw           = $this->valid_raw();
		$raw['status'] = 'exploded';
		$this->assertSame( Status::Active->value, Sanitizer::sanitize( $raw )['status'] );

		unset( $raw['status'] );
		$this->assertSame( Status::Active->value, Sanitizer::sanitize( $raw )['status'] );
	}

	public function test_invalid_date_becomes_null(): void {
		$raw                  = $this->valid_raw();
		$raw['purchase_date'] = '2025-13-40';
		$this->assertNull( Sanitizer::sanitize( $raw )['purchase_date'] );

		$raw['purchase_date'] = 'not-a-date';
		$this->assertNull( Sanitizer::sanitize( $raw )['purchase_date'] );

		$raw['purchase_date'] = '';
		$this->assertNull( Sanitizer::sanitize( $raw )['purchase_date'] );
	}

	public function test_non_numeric_value_becomes_zero_and_negatives_are_clamped(): void {
		$raw          = $this->valid_raw();
		$raw['value'] = 'free';
		$this->assertSame( 0.0, Sanitizer::sanitize( $raw )['value'] );

		$raw['value'] = '-50';
		$this->assertSame( 0.0, Sanitizer::sanitize( $raw )['value'] );

		$raw['value'] = '$2,000.5';
		$this->assertSame( 2000.50, Sanitizer::sanitize( $raw )['value'] );
	}

	public function test_is_valid_date_accepts_real_dates_only(): void {
		$this->assertTrue( Sanitizer::is_valid_date( '2025-03-14' ) );
		$this->assertFalse( Sanitizer::is_valid_date( '2025-02-30' ) );
		$this->assertFalse( Sanitizer::is_valid_date( '14-03-2025' ) );
	}
}
