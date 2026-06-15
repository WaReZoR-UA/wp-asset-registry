<?php
/**
 * Front-end shortcode that mounts the asset registry browser.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Frontend;

use AssetRegistry\Capabilities;
use AssetRegistry\Category;
use AssetRegistry\Plugin;
use AssetRegistry\Rest\AssetController;
use AssetRegistry\Status;

/**
 * Registers the [asset_registry] shortcode and renders the mount point that
 * the front-end script hydrates from the REST API. The data passed to the
 * script is built by a pure, unit-tested seam.
 */
final class Shortcode {

	/**
	 * Shared handle for the enqueued style and script.
	 */
	public const HANDLE = 'asset-registry';

	/**
	 * Registers the shortcode and the matching server-rendered block. Both
	 * mount points reuse the same render(), so the block is a thin alias of
	 * the shortcode. Hooked on init.
	 */
	public function register(): void {
		add_shortcode( 'asset_registry', array( $this, 'render' ) );

		if ( function_exists( 'register_block_type' ) ) {
			register_block_type(
				ASSET_REGISTRY_DIR . 'blocks/asset-registry',
				array( 'render_callback' => array( $this, 'render' ) )
			);
		}
	}

	/**
	 * Enqueues the front-end assets and returns the mount markup. Thin
	 * wrapper over the pure localized_data() seam; integration-tested manually.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes (unused).
	 * @return string The root container markup the script mounts into.
	 */
	public function render( $atts = array() ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $atts is part of the WordPress shortcode callback signature.
		wp_enqueue_style( self::HANDLE, ASSET_REGISTRY_URL . 'assets/css/registry.css', array(), $this->asset_version( 'assets/css/registry.css' ) );
		wp_enqueue_script( self::HANDLE, ASSET_REGISTRY_URL . 'assets/js/registry.js', array(), $this->asset_version( 'assets/js/registry.js' ), true );
		wp_localize_script( self::HANDLE, 'AssetRegistryData', $this->localized_data() );

		return '<div class="asset-registry" id="asset-registry-app"><noscript>'
			. esc_html__( 'Enable JavaScript to browse the asset registry.', 'asset-registry' )
			. '</noscript></div>';
	}

	/**
	 * Cache-busting version for a bundled asset, based on its modification
	 * time so any edit invalidates the browser cache. Falls back to the
	 * plugin version when the file cannot be read.
	 *
	 * @param string $relative Asset path relative to the plugin directory.
	 * @return string The version string.
	 */
	private function asset_version( string $relative ): string {
		$path  = ASSET_REGISTRY_DIR . $relative;
		$mtime = is_readable( $path ) ? filemtime( $path ) : false;

		return false !== $mtime ? (string) $mtime : Plugin::VERSION;
	}

	/**
	 * Builds the configuration handed to the front-end script. Pure, so the
	 * REST endpoint, nonce, capability flag, and option maps are unit-tested.
	 *
	 * @return array<string, mixed> The localized data payload.
	 */
	public function localized_data(): array {
		return array(
			'restUrl'    => esc_url_raw( rest_url( AssetController::NAMESPACE . '/' ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'canView'    => current_user_can( Capabilities::VIEW ),
			'perPage'    => 12,
			'statuses'   => Status::options(),
			'categories' => Category::options(),
			'i18n'       => array(
				'search'        => __( 'Search assets', 'asset-registry' ),
				'allStatuses'   => __( 'All statuses', 'asset-registry' ),
				'allCategories' => __( 'All categories', 'asset-registry' ),
				'noResults'     => __( 'No assets found.', 'asset-registry' ),
				'loading'       => __( 'Loading...', 'asset-registry' ),
				'details'       => __( 'Details', 'asset-registry' ),
				'restricted'    => __( 'Sign in to view full asset details.', 'asset-registry' ),
			),
		);
	}
}
