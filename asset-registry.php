<?php
/**
 * Plugin Name:       Asset Registry
 * Plugin URI:        https://github.com/warezor/wp-asset-registry
 * Description:       Asset / Equipment Registry: custom tables, role-based access, a responsive front-end grid with filters and search, server-side PDF spec sheets, and secure authenticated attachments.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            warezor
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       asset-registry
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'ASSET_REGISTRY_FILE', __FILE__ );
define( 'ASSET_REGISTRY_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASSET_REGISTRY_URL', plugin_dir_url( __FILE__ ) );

$asset_registry_autoload = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $asset_registry_autoload ) ) {
    require_once $asset_registry_autoload;
}

register_activation_hook(
    __FILE__,
    static function (): void {
        global $wpdb;
        \AssetRegistry\Activator::activate( $wpdb );
    }
);

register_deactivation_hook(
    __FILE__,
    static function (): void {
        \AssetRegistry\Deactivator::deactivate();
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        \AssetRegistry\Plugin::init();
    }
);
