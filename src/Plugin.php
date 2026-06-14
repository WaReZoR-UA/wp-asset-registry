<?php
/**
 * Runtime orchestrator for the Asset Registry plugin.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry;

/**
 * Plugin orchestrator. Holds the canonical version and boots the plugin.
 * Feature hooks (admin, REST, frontend, PDF, files) are wired in later phases.
 */
final class Plugin {

	public const VERSION = '0.1.1';

	/**
	 * Boots the plugin: loads translations and wires runtime hooks.
	 */
	public static function init(): void {
		load_plugin_textdomain( 'asset-registry' );
		// Runtime hooks (admin, REST, frontend, PDF, files) are wired in later phases.
	}
}
