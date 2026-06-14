<?php
/**
 * Uninstall entry point. WordPress loads this file when the plugin is deleted.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

global $wpdb;
\AssetRegistry\Uninstaller::uninstall( $wpdb );
