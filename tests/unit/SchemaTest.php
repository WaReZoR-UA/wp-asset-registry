<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit;

use AssetRegistry\Schema;

final class SchemaTest extends UnitTestCase {

    public function test_table_name_prefixes_the_base_table(): void {
        $this->assertSame( 'wp_ar_assets', Schema::table_name( 'wp_' ) );
        $this->assertSame( 'site5_ar_assets', Schema::table_name( 'site5_' ) );
    }

    public function test_create_sql_contains_table_and_core_columns(): void {
        $sql = Schema::create_sql( 'wp_ar_assets', 'DEFAULT CHARSET=utf8mb4' );

        $this->assertStringContainsString( 'CREATE TABLE wp_ar_assets', $sql );
        $this->assertStringContainsString( 'id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT', $sql );
        $this->assertStringContainsString( 'asset_tag VARCHAR(64) NOT NULL', $sql );
        $this->assertStringContainsString( 'value DECIMAL(10,2)', $sql );
        $this->assertStringContainsString( 'PRIMARY KEY  (id)', $sql );
        $this->assertStringContainsString( 'UNIQUE KEY asset_tag (asset_tag)', $sql );
        $this->assertStringContainsString( 'DEFAULT CHARSET=utf8mb4', $sql );
    }
}
