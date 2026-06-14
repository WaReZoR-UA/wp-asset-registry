<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry;

use WP_Role;

/**
 * Defines the plugin's custom roles and capabilities and registers/removes
 * them through the WordPress Roles API.
 */
final class Capabilities {

	public const MANAGE = 'manage_assets';
	public const VIEW   = 'view_assets';

	public const ROLE_MANAGER = 'registry_manager';
	public const ROLE_VIEWER  = 'registry_viewer';

	/**
	 * Role definitions: slug => [ display, caps[] ].
	 *
	 * @return array<string, array{display: string, caps: array<int, string>}>
	 */
	public static function roles(): array {
		return array(
			self::ROLE_MANAGER => array(
				'display' => 'Registry Manager',
				'caps'    => array( self::MANAGE, self::VIEW, 'read' ),
			),
			self::ROLE_VIEWER  => array(
				'display' => 'Registry Viewer',
				'caps'    => array( self::VIEW, 'read' ),
			),
		);
	}

	/**
	 * Registers the custom roles and grants both caps to administrators.
	 */
	public static function add_roles(): void {
		foreach ( self::roles() as $slug => $definition ) {
			add_role( $slug, $definition['display'], array_fill_keys( $definition['caps'], true ) );
		}

		$admin = get_role( 'administrator' );
		if ( $admin instanceof WP_Role ) {
			$admin->add_cap( self::MANAGE );
			$admin->add_cap( self::VIEW );
		}
	}

	/**
	 * Removes the custom roles and revokes both caps from administrators.
	 */
	public static function remove_roles(): void {
		foreach ( array_keys( self::roles() ) as $slug ) {
			remove_role( $slug );
		}

		$admin = get_role( 'administrator' );
		if ( $admin instanceof WP_Role ) {
			$admin->remove_cap( self::MANAGE );
			$admin->remove_cap( self::VIEW );
		}
	}
}
