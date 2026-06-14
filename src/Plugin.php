<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry;

/**
 * Plugin orchestrator. Holds the canonical version and wires runtime hooks.
 * Feature hooks (admin, REST, frontend, PDF, files) are wired in later phases.
 */
final class Plugin {

    public const VERSION = '0.1.0';

    public static function init(): void {
        // Runtime hooks (admin, REST, frontend, PDF, files) are wired in later phases.
    }
}
