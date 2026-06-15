<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit\Admin;

use AssetRegistry\Admin\AssetListData;
use AssetRegistry\Tests\Unit\UnitTestCase;
use Brain\Monkey\Functions;

final class AssetListDataTest extends UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
	}

	public function test_columns_include_core_fields_and_actions(): void {
		$columns = AssetListData::columns();

		$this->assertArrayHasKey( 'asset_tag', $columns );
		$this->assertArrayHasKey( 'name', $columns );
		$this->assertArrayHasKey( 'category', $columns );
		$this->assertArrayHasKey( 'status', $columns );
	}

	public function test_sortable_columns_map_to_db_columns(): void {
		$sortable = AssetListData::sortable_columns();

		$this->assertSame( array( 'name', false ), $sortable['name'] );
		$this->assertSame( array( 'value', false ), $sortable['value'] );
		$this->assertArrayNotHasKey( 'notes', $sortable );
	}

	public function test_parse_query_args_whitelists_and_defaults(): void {
		$args = AssetListData::parse_query_args(
			array(
				'status'   => 'active',
				'category' => 'laptop',
				's'        => 'thinkpad',
				'orderby'  => 'name',
				'order'    => 'asc',
				'paged'    => '2',
			)
		);

		$this->assertSame( 'active', $args['filters']['status'] );
		$this->assertSame( 'laptop', $args['filters']['category'] );
		$this->assertSame( 'thinkpad', $args['filters']['search'] );
		$this->assertSame( 'name', $args['orderby'] );
		$this->assertSame( 'asc', $args['order'] );
		$this->assertSame( 2, $args['page'] );
	}

	public function test_parse_query_args_drops_unknown_status_and_category(): void {
		$args = AssetListData::parse_query_args(
			array(
				'status'   => 'exploded',
				'category' => 'spaceship',
			)
		);

		$this->assertArrayNotHasKey( 'status', $args['filters'] );
		$this->assertArrayNotHasKey( 'category', $args['filters'] );
	}
}
