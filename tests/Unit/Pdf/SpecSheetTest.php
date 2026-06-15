<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit\Pdf;

use AssetRegistry\Asset;
use AssetRegistry\Pdf\SpecSheet;
use AssetRegistry\Tests\Unit\UnitTestCase;
use Brain\Monkey\Functions;

final class SpecSheetTest extends UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Pass-through doubles for the only WP helpers build_html()/filename() touch.
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'sanitize_file_name' )->returnArg();
	}

	/**
	 * Builds a fully-populated asset for the HTML assertions.
	 *
	 * @return Asset The fixture asset.
	 */
	private function full_asset(): Asset {
		return Asset::from_array(
			array(
				'id'            => 7,
				'asset_tag'     => 'LAP-0007',
				'name'          => 'ThinkPad X1',
				'category'      => 'laptop',
				'status'        => 'in_repair',
				'location'      => 'HQ Floor 2',
				'assigned_to'   => 'A. Engineer',
				'purchase_date' => '2025-03-14',
				'value'         => 1499.5,
				'notes'         => 'Carbon, 32GB',
			)
		);
	}

	public function test_build_html_contains_every_asset_field(): void {
		$html = ( new SpecSheet() )->build_html( $this->full_asset() );

		$this->assertStringContainsString( 'LAP-0007', $html );
		$this->assertStringContainsString( 'ThinkPad X1', $html );
		$this->assertStringContainsString( 'HQ Floor 2', $html );
		$this->assertStringContainsString( 'A. Engineer', $html );
		$this->assertStringContainsString( '2025-03-14', $html );
		$this->assertStringContainsString( '1,499.50', $html );
		$this->assertStringContainsString( 'Carbon, 32GB', $html );
	}

	public function test_build_html_uses_human_labels_for_status_and_category(): void {
		$html = ( new SpecSheet() )->build_html( $this->full_asset() );

		$this->assertStringContainsString( 'In Repair', $html );
		$this->assertStringContainsString( 'Laptop', $html );
	}

	public function test_build_html_renders_a_complete_document(): void {
		$html = ( new SpecSheet() )->build_html( $this->full_asset() );

		$this->assertStringStartsWith( '<!DOCTYPE html>', $html );
		$this->assertStringContainsString( '<meta charset="utf-8">', $html );
		$this->assertStringContainsString( '</html>', $html );
		$this->assertStringContainsString( 'Asset Spec Sheet', $html );
	}

	public function test_build_html_shows_a_dash_for_a_null_purchase_date(): void {
		$asset = Asset::from_array(
			array(
				'asset_tag'     => 'TLB-0001',
				'name'          => 'Cordless Drill',
				'category'      => 'tool',
				'status'        => 'active',
				'location'      => 'Workshop',
				'assigned_to'   => '',
				'purchase_date' => null,
				'value'         => 0.0,
				'notes'         => '',
			)
		);

		$html = ( new SpecSheet() )->build_html( $asset );

		// The purchase-date row falls back to a hyphen placeholder in a cell.
		$this->assertStringContainsString( '>-</td>', $html );
	}

	public function test_build_html_escapes_dynamic_values(): void {
		// esc_html is stubbed to a real escaper so escaping is actually exercised.
		Functions\when( 'esc_html' )->alias(
			static fn ( $value ): string => htmlspecialchars( (string) $value, ENT_QUOTES )
		);

		$asset = Asset::from_array(
			array(
				'asset_tag'     => 'EVL-0001',
				'name'          => '<script>alert(1)</script>',
				'category'      => 'tool',
				'status'        => 'active',
				'location'      => 'A & B',
				'assigned_to'   => 'O"Brien',
				'purchase_date' => '2025-01-01',
				'value'         => 10.0,
				'notes'         => 'safe',
			)
		);

		$html = ( new SpecSheet() )->build_html( $asset );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		$this->assertStringContainsString( 'A &amp; B', $html );
	}

	public function test_filename_is_a_safe_pdf_name_built_from_the_tag(): void {
		$this->assertSame( 'asset-LAP-0007.pdf', ( new SpecSheet() )->filename( $this->full_asset() ) );
	}
}
