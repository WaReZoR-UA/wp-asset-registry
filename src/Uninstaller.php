<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry;

/**
 * Runs on plugin deletion: removes roles, drops the custom table, and
 * clears the stored DB-version option. This is a demo plugin, so data is
 * removed on uninstall by design.
 */
final class Uninstaller {

    public static function uninstall( \wpdb $wpdb ): void {
        Capabilities::remove_roles();

        $table = Schema::table_name( $wpdb->prefix );
        // Table identifiers cannot be bound as prepared parameters; the name
        // is derived from the trusted site prefix plus a fixed constant.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" );

        delete_option( 'asset_registry_db_version' );
    }
}
