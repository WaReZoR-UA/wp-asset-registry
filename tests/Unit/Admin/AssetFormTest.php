<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit\Admin;

use AssetRegistry\Admin\AssetForm;
use AssetRegistry\Asset;
use AssetRegistry\AssetRepository;
use AssetRegistry\Tests\Unit\UnitTestCase;
use Brain\Monkey\Functions;
use Mockery;

final class AssetFormTest extends UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_textarea_field' )->returnArg();
		Functions\when( 'absint' )->alias( static fn ( $v ) => abs( (int) $v ) );
	}

	public function test_can_submit_requires_capability_and_valid_nonce(): void {
		$form = new AssetForm( Mockery::mock( AssetRepository::class ) );

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		$this->assertTrue( $form->can_submit( 'good-nonce' ) );
	}

	public function test_can_submit_denies_without_capability(): void {
		$form = new AssetForm( Mockery::mock( AssetRepository::class ) );

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		$this->assertFalse( $form->can_submit( 'good-nonce' ) );
	}

	public function test_can_submit_denies_with_bad_nonce(): void {
		$form = new AssetForm( Mockery::mock( AssetRepository::class ) );

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		$this->assertFalse( $form->can_submit( null ) );
	}

	public function test_handle_inserts_when_no_id(): void {
		$repo = Mockery::mock( AssetRepository::class );
		$repo->shouldReceive( 'find_by_tag' )->once()->with( 'LAP-9' )->andReturn( null );
		$repo->shouldReceive( 'insert' )->once()->with( Mockery::type( Asset::class ) )->andReturn( 101 );
		$repo->shouldReceive( 'update' )->never();

		$form = new AssetForm( $repo );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		$result = $form->handle(
			array(
				'asset_tag' => 'LAP-9',
				'name'      => 'X1',
				'category'  => 'laptop',
				'status'    => 'active',
				'value'     => '1000',
			),
			'good-nonce'
		);

		$this->assertSame(
			array(
				'saved' => true,
				'id'    => 101,
				'error' => '',
			),
			$result
		);
	}

	public function test_handle_returns_save_failed_when_insert_fails(): void {
		$repo = Mockery::mock( AssetRepository::class );
		$repo->shouldReceive( 'find_by_tag' )->once()->with( 'LAP-9' )->andReturn( null );
		$repo->shouldReceive( 'insert' )->once()->with( Mockery::type( Asset::class ) )->andReturn( 0 );
		$repo->shouldReceive( 'update' )->never();

		$form = new AssetForm( $repo );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		$result = $form->handle(
			array(
				'asset_tag' => 'LAP-9',
				'name'      => 'X1',
				'category'  => 'laptop',
			),
			'good-nonce'
		);

		$this->assertSame(
			array(
				'saved' => false,
				'id'    => 0,
				'error' => 'save_failed',
			),
			$result
		);
	}

	public function test_handle_updates_when_id_present(): void {
		$repo = Mockery::mock( AssetRepository::class );
		$repo->shouldReceive( 'find_by_tag' )->once()->with( 'LAP-9' )->andReturn( null );
		$repo->shouldReceive( 'update' )->once()->with( 55, Mockery::type( Asset::class ) )->andReturn( true );
		$repo->shouldReceive( 'insert' )->never();

		$form = new AssetForm( $repo );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		$result = $form->handle(
			array(
				'id'        => '55',
				'asset_tag' => 'LAP-9',
				'name'      => 'X1',
				'category'  => 'laptop',
				'status'    => 'active',
				'value'     => '1000',
			),
			'good-nonce'
		);

		$this->assertSame(
			array(
				'saved' => true,
				'id'    => 55,
				'error' => '',
			),
			$result
		);
	}

	public function test_handle_update_keeps_same_tag(): void {
		$existing = Asset::from_array(
			array(
				'id'        => 55,
				'asset_tag' => 'LAP-9',
				'name'      => 'X1',
				'category'  => 'laptop',
				'status'    => 'active',
			)
		);

		$repo = Mockery::mock( AssetRepository::class );
		$repo->shouldReceive( 'find_by_tag' )->once()->with( 'LAP-9' )->andReturn( $existing );
		$repo->shouldReceive( 'update' )->once()->with( 55, Mockery::type( Asset::class ) )->andReturn( true );
		$repo->shouldReceive( 'insert' )->never();

		$form = new AssetForm( $repo );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		$result = $form->handle(
			array(
				'id'        => '55',
				'asset_tag' => 'LAP-9',
				'name'      => 'X1 Carbon',
				'category'  => 'laptop',
				'status'    => 'active',
			),
			'good-nonce'
		);

		$this->assertSame(
			array(
				'saved' => true,
				'id'    => 55,
				'error' => '',
			),
			$result
		);
	}

	public function test_handle_returns_save_failed_when_update_fails(): void {
		$repo = Mockery::mock( AssetRepository::class );
		$repo->shouldReceive( 'find_by_tag' )->once()->with( 'LAP-9' )->andReturn( null );
		$repo->shouldReceive( 'update' )->once()->with( 55, Mockery::type( Asset::class ) )->andReturn( false );
		$repo->shouldReceive( 'insert' )->never();

		$form = new AssetForm( $repo );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		$result = $form->handle(
			array(
				'id'        => '55',
				'asset_tag' => 'LAP-9',
				'name'      => 'X1',
				'category'  => 'laptop',
			),
			'good-nonce'
		);

		$this->assertSame(
			array(
				'saved' => false,
				'id'    => 55,
				'error' => 'save_failed',
			),
			$result
		);
	}

	public function test_handle_rejects_duplicate_on_insert(): void {
		$existing = Asset::from_array(
			array(
				'id'        => 7,
				'asset_tag' => 'LAP-9',
				'name'      => 'Existing',
				'category'  => 'laptop',
				'status'    => 'active',
			)
		);

		$repo = Mockery::mock( AssetRepository::class );
		$repo->shouldReceive( 'find_by_tag' )->once()->with( 'LAP-9' )->andReturn( $existing );
		$repo->shouldReceive( 'insert' )->never();
		$repo->shouldReceive( 'update' )->never();

		$form = new AssetForm( $repo );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		$result = $form->handle(
			array(
				'asset_tag' => 'LAP-9',
				'name'      => 'X1',
				'category'  => 'laptop',
			),
			'good-nonce'
		);

		$this->assertSame(
			array(
				'saved' => false,
				'id'    => 0,
				'error' => 'duplicate',
			),
			$result
		);
	}

	public function test_handle_rejects_duplicate_on_update_against_other_record(): void {
		$other = Asset::from_array(
			array(
				'id'        => 99,
				'asset_tag' => 'LAP-9',
				'name'      => 'Other',
				'category'  => 'laptop',
				'status'    => 'active',
			)
		);

		$repo = Mockery::mock( AssetRepository::class );
		$repo->shouldReceive( 'find_by_tag' )->once()->with( 'LAP-9' )->andReturn( $other );
		$repo->shouldReceive( 'update' )->never();
		$repo->shouldReceive( 'insert' )->never();

		$form = new AssetForm( $repo );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		$result = $form->handle(
			array(
				'id'        => '55',
				'asset_tag' => 'LAP-9',
				'name'      => 'X1',
				'category'  => 'laptop',
			),
			'good-nonce'
		);

		$this->assertSame(
			array(
				'saved' => false,
				'id'    => 55,
				'error' => 'duplicate',
			),
			$result
		);
	}

	public function test_handle_rejects_missing_asset_tag(): void {
		$repo = Mockery::mock( AssetRepository::class );
		$repo->shouldReceive( 'find_by_tag' )->never();
		$repo->shouldReceive( 'insert' )->never();
		$repo->shouldReceive( 'update' )->never();

		$form = new AssetForm( $repo );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		$result = $form->handle(
			array(
				'asset_tag' => '',
				'name'      => 'X1',
				'category'  => 'laptop',
			),
			'good-nonce'
		);

		$this->assertSame(
			array(
				'saved' => false,
				'id'    => 0,
				'error' => 'required',
			),
			$result
		);
	}

	public function test_handle_rejects_missing_name(): void {
		$repo = Mockery::mock( AssetRepository::class );
		$repo->shouldReceive( 'find_by_tag' )->never();
		$repo->shouldReceive( 'insert' )->never();
		$repo->shouldReceive( 'update' )->never();

		$form = new AssetForm( $repo );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		$result = $form->handle(
			array(
				'asset_tag' => 'LAP-9',
				'name'      => '',
				'category'  => 'laptop',
			),
			'good-nonce'
		);

		$this->assertSame(
			array(
				'saved' => false,
				'id'    => 0,
				'error' => 'required',
			),
			$result
		);
	}

	public function test_handle_rejects_invalid_category(): void {
		$repo = Mockery::mock( AssetRepository::class );
		$repo->shouldReceive( 'find_by_tag' )->never();
		$repo->shouldReceive( 'insert' )->never();
		$repo->shouldReceive( 'update' )->never();

		$form = new AssetForm( $repo );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

		// Sanitizer maps an unknown category slug to '' which fails validation.
		$result = $form->handle(
			array(
				'asset_tag' => 'LAP-9',
				'name'      => 'X1',
				'category'  => 'spaceship',
			),
			'good-nonce'
		);

		$this->assertSame(
			array(
				'saved' => false,
				'id'    => 0,
				'error' => 'required',
			),
			$result
		);
	}

	public function test_handle_refuses_when_guard_fails(): void {
		$repo = Mockery::mock( AssetRepository::class );
		$repo->shouldReceive( 'find_by_tag' )->never();
		$repo->shouldReceive( 'insert' )->never();
		$repo->shouldReceive( 'update' )->never();

		$form = new AssetForm( $repo );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		$result = $form->handle( array( 'asset_tag' => 'X' ), 'bad' );

		$this->assertSame(
			array(
				'saved' => false,
				'id'    => 0,
				'error' => '',
			),
			$result
		);
	}
}
