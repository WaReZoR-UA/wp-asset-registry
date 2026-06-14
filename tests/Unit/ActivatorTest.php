<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit;

use AssetRegistry\Activator;
use AssetRegistry\Plugin;
use Brain\Monkey\Functions;
use Mockery;

final class ActivatorTest extends UnitTestCase {

    public function test_activate_runs_dbdelta_with_table_sql_and_stamps_version(): void {
        $wpdb         = Mockery::mock( 'wpdb' );
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive( 'get_charset_collate' )->andReturn( 'DEFAULT CHARSET=utf8mb4' );

        // Roles API is exercised in CapabilitiesTest; here it is allowed but not asserted.
        Functions\when( 'add_role' )->justReturn( null );
        Functions\when( 'get_role' )->justReturn( null );

        Functions\expect( 'dbDelta' )
            ->once()
            ->with( Mockery::on( static fn ( $sql ) => str_contains( (string) $sql, 'CREATE TABLE wp_ar_assets' ) ) );

        Functions\expect( 'update_option' )
            ->once()
            ->with( 'asset_registry_db_version', Plugin::VERSION );

        Activator::activate( $wpdb );
    }
}
