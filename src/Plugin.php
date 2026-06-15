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

		if ( is_admin() ) {
			$menu = new \AssetRegistry\Admin\AdminMenu();
			add_action( 'admin_menu', array( $menu, 'register' ) );
		}

		// The REST API and front-end shortcode serve every context, so they
		// are wired outside the admin-only branch above.
		add_action(
			'rest_api_init',
			static function (): void {
				( new \AssetRegistry\Rest\AssetController() )->register();
			}
		);

		add_action(
			'init',
			static function (): void {
				( new \AssetRegistry\Frontend\Shortcode() )->register();
			}
		);

		// PDF and secure file hooks are wired in later phases.
	}
}
