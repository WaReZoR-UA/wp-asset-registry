<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit\Rest;

use AssetRegistry\Asset;
use AssetRegistry\AssetRepository;
use AssetRegistry\Category;
use AssetRegistry\Rest\AssetController;
use AssetRegistry\Status;
use AssetRegistry\Tests\Unit\UnitTestCase;
use Brain\Monkey\Functions;
use Mockery;

final class AssetControllerTest extends UnitTestCase {

	/**
	 * A fully-populated asset for prepare_item assertions.
	 *
	 * @return Asset The sample asset.
	 */
	private function sample_asset(): Asset {
		return Asset::from_array(
			array(
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
			)
		);
	}

	public function test_registers_list_and_single_routes_under_namespace(): void {
		$routes = array();
		Functions\expect( 'register_rest_route' )
			->twice()
			->andReturnUsing(
				static function ( $namespace, $route, $args ) use ( &$routes ) {
					$routes[] = array(
						'namespace' => $namespace,
						'route'     => $route,
						'args'      => $args,
					);
					return true;
				}
			);

		$controller = new AssetController( Mockery::mock( AssetRepository::class ) );
		$controller->register();

		$this->assertCount( 2, $routes );
		$this->assertSame( 'asset-registry/v1', $routes[0]['namespace'] );
		$this->assertSame( 'asset-registry/v1', $routes[1]['namespace'] );

		$paths = array( $routes[0]['route'], $routes[1]['route'] );
		$this->assertContains( '/assets', $paths );
		$this->assertContains( '/assets/(?P<id>\d+)', $paths );
	}

	public function test_list_args_validate_status_and_category_against_enums(): void {
		$controller = new AssetController( Mockery::mock( AssetRepository::class ) );
		$args       = $controller->list_args();

		$this->assertSame( Status::values(), $args['status']['enum'] );
		$this->assertArrayHasKey( 'validate_callback', $args['status'] );
		$this->assertSame( Category::values(), $args['category']['enum'] );
		$this->assertArrayHasKey( 'validate_callback', $args['category'] );

		$this->assertSame( 1, $args['page']['default'] );
		$this->assertSame( 12, $args['per_page']['default'] );
	}

	public function test_list_response_is_filtered_for_anonymous_visitor(): void {
		$controller = new AssetController( Mockery::mock( AssetRepository::class ) );

		$item = $controller->prepare_item( $this->sample_asset(), false );

		$this->assertSame( array( 'id', 'name', 'category', 'status' ), array_keys( $item ) );
		$this->assertSame( 42, $item['id'] );
		$this->assertArrayNotHasKey( 'asset_tag', $item );
		$this->assertArrayNotHasKey( 'location', $item );
		$this->assertArrayNotHasKey( 'value', $item );
		$this->assertArrayNotHasKey( 'notes', $item );
		$this->assertArrayNotHasKey( 'attachment_path', $item );
	}

	public function test_single_response_includes_sensitive_fields_for_authorized_viewer(): void {
		$controller = new AssetController( Mockery::mock( AssetRepository::class ) );

		$item = $controller->prepare_item( $this->sample_asset(), true );

		$this->assertSame( 42, $item['id'] );
		$this->assertSame( 'LAP-9', $item['asset_tag'] );
		$this->assertSame( 'HQ', $item['location'] );
		$this->assertSame( 1200.50, $item['value'] );
		$this->assertSame( 'Keep dry.', $item['notes'] );
		$this->assertTrue( $item['has_attachment'] );

		$this->assertArrayNotHasKey( 'attachment_path', $item );
	}
}
