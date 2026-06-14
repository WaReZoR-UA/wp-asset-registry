<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry;

/**
 * Runs on deactivation. Custom roles and data are intentionally preserved
 * (only removed on uninstall). Flushes rewrite rules so the file route
 * registered in later phases is cleared.
 */
final class Deactivator {

    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
