<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry;

/**
 * Runs once on plugin activation: creates the custom table and roles.
 */
final class Activator {

    public static function activate( \wpdb $wpdb ): void {
        // dbDelta lives in an admin include not loaded on the front end.
        // When unit-tested, Brain Monkey defines dbDelta, so the require is skipped.
        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $table = Schema::table_name( $wpdb->prefix );
        dbDelta( Schema::create_sql( $table, $wpdb->get_charset_collate() ) );

        Capabilities::add_roles();

        update_option( 'asset_registry_db_version', Plugin::VERSION );
    }
}
