<?php
/**
 * REST controller exposing read-only asset endpoints.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Rest;

use AssetRegistry\Asset;
use AssetRegistry\AssetRepository;
use AssetRegistry\Capabilities;
use AssetRegistry\Category;
use AssetRegistry\Status;
use AssetRegistry\Support\FieldVisibility;

/**
 * Registers and serves the public read API for assets. The endpoints are
 * open, but the payload is field-filtered by capability: anonymous callers
 * receive a minimal public subset, authenticated viewers the full record.
 * The thin WordPress wrappers delegate all logic to pure, unit-tested seams.
 */
final class AssetController {

	public const NAMESPACE = 'asset-registry/v1';

	/**
	 * Default rows returned per list page.
	 */
	private const DEFAULT_PER_PAGE = 12;

	/**
	 * Hard upper bound on rows per list page, mirroring the repository's
	 * internal clamp so pagination headers stay consistent with the data.
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Stores the optional repository dependency.
	 *
	 * @param AssetRepository|null $repository Injected for testing; built lazily otherwise.
	 */
	public function __construct( private ?AssetRepository $repository = null ) {}

	/**
	 * Resolves the repository, wiring the global $wpdb when none was injected.
	 *
	 * @return AssetRepository The data-access layer.
	 */
	private function repository(): AssetRepository {
		if ( null === $this->repository ) {
			global $wpdb;
			$this->repository = new AssetRepository( $wpdb );
		}
		return $this->repository;
	}

	/**
	 * Registers the list and single-item routes. Hooked on rest_api_init.
	 */
	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/assets',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => '__return_true',
				'args'                => $this->list_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/assets/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => static fn ( $value ): bool => ctype_digit( (string) $value ),
					),
				),
			)
		);
	}

	/**
	 * The argument schema for the list endpoint. Kept pure for unit testing.
	 *
	 * @return array<string, array<string, mixed>> The args schema.
	 */
	public function list_args(): array {
		$statuses   = Status::values();
		$categories = Category::values();

		return array(
			'status'   => array(
				'type'              => 'string',
				'required'          => false,
				'enum'              => $statuses,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => static fn ( $value ): bool => '' === (string) $value || in_array( (string) $value, $statuses, true ),
			),
			'category' => array(
				'type'              => 'string',
				'required'          => false,
				'enum'              => $categories,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => static fn ( $value ): bool => '' === (string) $value || in_array( (string) $value, $categories, true ),
			),
			'search'   => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => self::DEFAULT_PER_PAGE,
				'minimum'           => 1,
				'maximum'           => self::MAX_PER_PAGE,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Builds the REST payload for one asset, filtered by capability. Pure for
	 * the anonymous path (no WordPress calls); authorized items additionally
	 * carry gated download URLs the front end can offer.
	 *
	 * @param Asset $asset    The source asset.
	 * @param bool  $can_view Whether the caller holds the view capability.
	 * @return array<string, mixed> The item, always carrying id plus visible fields.
	 */
	public function prepare_item( Asset $asset, bool $can_view ): array {
		$row = array(
			'asset_tag'       => $asset->asset_tag,
			'name'            => $asset->name,
			'category'        => $asset->category,
			'status'          => $asset->status,
			'location'        => $asset->location,
			'assigned_to'     => $asset->assigned_to,
			'purchase_date'   => $asset->purchase_date,
			'value'           => $asset->value,
			'notes'           => $asset->notes,
			'attachment_path' => $asset->attachment_path,
		);

		$item = array_merge( array( 'id' => (int) $asset->id ), FieldVisibility::filter( $row, $can_view ) );

		// Anonymous callers receive no download URLs, and this branch makes no
		// WordPress calls so it stays pure and fast.
		if ( ! $can_view ) {
			return $item;
		}

		$id              = (int) $asset->id;
		$item['pdf_url'] = ( new \AssetRegistry\Pdf\PdfRoute() )->download_url( $id );

		if ( ! empty( $asset->attachment_path ) ) {
			$item['file_url'] = ( new \AssetRegistry\Files\FileController() )->download_url( $id );
		}

		return $item;
	}

	/**
	 * Serves the filtered, paginated list. Thin wrapper over the repository
	 * and the pure prepare_item seam; integration-tested manually.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response The list payload with pagination headers.
	 */
	public function get_items( $request ): \WP_REST_Response {
		$filters = array();
		foreach ( array( 'status', 'category', 'search' ) as $key ) {
			$value = (string) $request->get_param( $key );
			if ( '' !== $value ) {
				$filters[ $key ] = $value;
			}
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) $request->get_param( 'per_page' ) ) );

		$can_view = current_user_can( Capabilities::VIEW );

		$assets = $this->repository()->query( $filters, 'created_at', 'desc', $per_page, $page );
		$total  = $this->repository()->count( $filters );

		$items = array_map(
			fn ( Asset $asset ): array => $this->prepare_item( $asset, $can_view ),
			$assets
		);

		$response = new \WP_REST_Response( $items );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * Serves a single asset by id. Thin wrapper; integration-tested manually.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error The item payload, or a 404 error.
	 */
	public function get_item( $request ) {
		$id    = (int) $request->get_param( 'id' );
		$asset = $this->repository()->find( $id );

		if ( ! $asset instanceof Asset ) {
			return new \WP_Error( 'asset_not_found', __( 'Asset not found.', 'asset-registry' ), array( 'status' => 404 ) );
		}

		return new \WP_REST_Response( $this->prepare_item( $asset, current_user_can( Capabilities::VIEW ) ) );
	}
}
