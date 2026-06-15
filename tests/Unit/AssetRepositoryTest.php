<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit;

use AssetRegistry\Asset;
use AssetRegistry\AssetRepository;
use Mockery;

final class AssetRepositoryTest extends UnitTestCase {

	private function wpdb(): Mockery\MockInterface {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		return $wpdb;
	}

	public function test_build_where_empty_filters_yields_no_clause(): void {
		$repo = new AssetRepository( $this->wpdb() );
		$out  = $repo->build_where( array() );

		$this->assertSame( '', $out['sql'] );
		$this->assertSame( array(), $out['args'] );
	}

	public function test_build_where_combines_status_category_and_search(): void {
		$repo = new AssetRepository( $this->wpdb() );
		$out  = $repo->build_where(
			array(
				'status'   => 'active',
				'category' => 'laptop',
				'search'   => 'thinkpad',
			)
		);

		$this->assertStringContainsString( 'status = %s', $out['sql'] );
		$this->assertStringContainsString( 'category = %s', $out['sql'] );
		$this->assertStringContainsString( '(name LIKE %s OR asset_tag LIKE %s OR location LIKE %s)', $out['sql'] );
		$this->assertStringStartsWith( 'WHERE ', $out['sql'] );
		// status, category, then three identical LIKE args.
		$this->assertSame(
			array( 'active', 'laptop', '%thinkpad%', '%thinkpad%', '%thinkpad%' ),
			$out['args']
		);
	}

	public function test_build_order_by_whitelists_column_and_direction(): void {
		$repo = new AssetRepository( $this->wpdb() );

		$this->assertSame( 'ORDER BY name ASC', $repo->build_order_by( 'name', 'asc' ) );
		$this->assertSame( 'ORDER BY value DESC', $repo->build_order_by( 'value', 'DESC' ) );
		// Unknown column falls back to created_at; bad direction to DESC.
		$this->assertSame( 'ORDER BY created_at DESC', $repo->build_order_by( 'id; DROP TABLE', 'sideways' ) );
	}

	public function test_build_limit_clamps_and_computes_offset(): void {
		$repo = new AssetRepository( $this->wpdb() );

		$this->assertSame(
			array(
				'sql'  => 'LIMIT %d OFFSET %d',
				'args' => array( 20, 40 ),
			),
			$repo->build_limit( 20, 3 )
		);
		// per_page clamped to 100, page clamped to 1 (offset 0).
		$this->assertSame(
			array(
				'sql'  => 'LIMIT %d OFFSET %d',
				'args' => array( 100, 0 ),
			),
			$repo->build_limit( 9999, 0 )
		);
	}

	public function test_insert_passes_formats_and_returns_new_id(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_ar_assets',
				Mockery::on( static fn ( $data ) => 'LAP-1' === $data['asset_tag'] ),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );
		$wpdb->insert_id = 42;

		$repo  = new AssetRepository( $wpdb );
		$asset = Asset::from_array(
			array(
				'asset_tag' => 'LAP-1',
				'name'      => 'X1',
				'category'  => 'laptop',
				'status'    => 'active',
				'value'     => 1000,
			)
		);

		$this->assertSame( 42, $repo->insert( $asset ) );
	}

	public function test_find_prepares_by_id_and_hydrates_asset(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with( Mockery::on( static fn ( $sql ) => str_contains( (string) $sql, 'WHERE id = %d' ) ), 7 )
			->andReturn( 'PREPARED' );
		$wpdb->shouldReceive( 'get_row' )
			->once()
			->with( 'PREPARED', ARRAY_A )
			->andReturn(
				array(
					'id'        => 7,
					'asset_tag' => 'LAP-7',
					'name'      => 'X1',
					'category'  => 'laptop',
					'status'    => 'active',
					'value'     => '1000.00',
				)
			);

		$repo  = new AssetRepository( $wpdb );
		$asset = $repo->find( 7 );

		$this->assertInstanceOf( Asset::class, $asset );
		$this->assertSame( 7, $asset->id );
		$this->assertSame( 'LAP-7', $asset->asset_tag );
	}

	public function test_find_returns_null_when_no_row(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'PREPARED' );
		$wpdb->shouldReceive( 'get_row' )->andReturn( null );

		$this->assertNull( ( new AssetRepository( $wpdb ) )->find( 99 ) );
	}

	public function test_query_prepares_combined_sql_and_maps_rows(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				Mockery::on(
					static fn ( $sql ) =>
						str_contains( (string) $sql, 'FROM wp_ar_assets' )
						&& str_contains( (string) $sql, 'status = %s' )
						&& str_contains( (string) $sql, 'ORDER BY name ASC' )
						&& str_contains( (string) $sql, 'LIMIT %d OFFSET %d' )
				),
				'active',
				20,
				0
			)
			->andReturn( 'PREPARED' );
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'PREPARED', ARRAY_A )
			->andReturn(
				array(
					array(
						'id'        => 1,
						'asset_tag' => 'A',
						'name'      => 'One',
						'category'  => 'tool',
						'status'    => 'active',
						'value'     => '1.00',
					),
					array(
						'id'        => 2,
						'asset_tag' => 'B',
						'name'      => 'Two',
						'category'  => 'tool',
						'status'    => 'active',
						'value'     => '2.00',
					),
				)
			);

		$repo   = new AssetRepository( $wpdb );
		$assets = $repo->query( array( 'status' => 'active' ), 'name', 'asc', 20, 1 );

		$this->assertCount( 2, $assets );
		$this->assertContainsOnlyInstancesOf( Asset::class, $assets );
		$this->assertSame( 'One', $assets[0]->name );
	}

	public function test_delete_calls_wpdb_delete_with_id_format(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_ar_assets', array( 'id' => 5 ), array( '%d' ) )
			->andReturn( 1 );

		$this->assertTrue( ( new AssetRepository( $wpdb ) )->delete( 5 ) );
	}
}
