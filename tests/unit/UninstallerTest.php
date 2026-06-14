<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit;

use AssetRegistry\Uninstaller;
use Brain\Monkey\Functions;
use Mockery;

final class UninstallerTest extends UnitTestCase {

    public function test_uninstall_removes_roles_drops_table_and_deletes_option(): void {
        Functions\expect( 'remove_role' )->twice();
        Functions\when( 'get_role' )->justReturn( null );

        $wpdb         = Mockery::mock( 'wpdb' );
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive( 'query' )
            ->once()
            ->with( Mockery::on( static fn ( $sql ) => str_contains( (string) $sql, 'DROP TABLE IF EXISTS wp_ar_assets' ) ) );

        Functions\expect( 'delete_option' )->once()->with( 'asset_registry_db_version' );

        Uninstaller::uninstall( $wpdb );
    }
}
