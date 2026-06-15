<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit\Files;

use AssetRegistry\Files\AttachmentStore;
use AssetRegistry\Tests\Unit\UnitTestCase;
use Brain\Monkey\Functions;

final class AttachmentStoreTest extends UnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_upload_dir' )->justReturn(
			array(
				'basedir' => '/var/www/uploads',
				'baseurl' => 'https://example.test/uploads',
			)
		);
		Functions\when( 'trailingslashit' )->alias( static fn ( $p ) => rtrim( $p, '/\\' ) . '/' );
		Functions\when( 'sanitize_file_name' )->alias( static fn ( $n ) => preg_replace( '/[^A-Za-z0-9._-]/', '', $n ) );
	}

	public function test_generated_path_is_inside_protected_directory(): void {
		$store    = new AttachmentStore();
		$relative = $store->relative_path( 'photo.png', 7, 'abc123' );
		$absolute = $store->resolve( $relative );

		$this->assertNotNull( $absolute );
		$this->assertStringStartsWith( '/var/www/uploads/asset-registry-protected/', (string) $absolute );
		$this->assertStringContainsString( '7', $relative );
		$this->assertStringContainsString( 'abc123', $relative );
	}

	public function test_resolve_rejects_directory_traversal(): void {
		$store = new AttachmentStore();

		$this->assertNull( $store->resolve( '../../etc/passwd' ) );
		$this->assertNull( $store->resolve( 'a/../../b' ) );
	}

	public function test_resolve_rejects_absolute_and_nullbyte_paths(): void {
		$store = new AttachmentStore();

		$this->assertNull( $store->resolve( '/etc/passwd' ) );
		$this->assertNull( $store->resolve( "file\0.png" ) );
		$this->assertNull( $store->resolve( 'C:\\windows' ) );
	}

	public function test_resolve_accepts_a_clean_relative_path(): void {
		$store = new AttachmentStore();

		$this->assertSame(
			'/var/www/uploads/asset-registry-protected/7-abc123-photo.png',
			$store->resolve( '7-abc123-photo.png' )
		);
	}

	public function test_relative_path_sanitizes_the_original_name(): void {
		$store    = new AttachmentStore();
		$relative = $store->relative_path( '../etc /pass wd.png', 7, 'abc123' );

		$this->assertStringNotContainsString( '..', $relative );
		$this->assertStringNotContainsString( '/', $relative );
		$this->assertStringNotContainsString( '\\', $relative );
		$this->assertNotNull( $store->resolve( $relative ) );
	}
}
