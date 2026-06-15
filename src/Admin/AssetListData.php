<?php
/**
 * Pure data-shaping for the asset list table (columns, sorting, query args).
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Admin;

use AssetRegistry\Category;
use AssetRegistry\Status;

/**
 * Holds the list-table logic that does not depend on WP_List_Table, so it
 * can be unit-tested in isolation.
 */
final class AssetListData {

	/**
	 * Column header map for the list table.
	 *
	 * @return array<string, string> Column key => header label.
	 */
	public static function columns(): array {
		return array(
			'asset_tag'   => __( 'Tag', 'asset-registry' ),
			'name'        => __( 'Name', 'asset-registry' ),
			'category'    => __( 'Category', 'asset-registry' ),
			'status'      => __( 'Status', 'asset-registry' ),
			'location'    => __( 'Location', 'asset-registry' ),
			'assigned_to' => __( 'Assigned To', 'asset-registry' ),
			'value'       => __( 'Value', 'asset-registry' ),
		);
	}

	/**
	 * Sortable column map: column key => [ db column, already-sorted ].
	 *
	 * @return array<string, array{0: string, 1: bool}> Sortable map.
	 */
	public static function sortable_columns(): array {
		return array(
			'asset_tag' => array( 'asset_tag', false ),
			'name'      => array( 'name', false ),
			'category'  => array( 'category', false ),
			'status'    => array( 'status', false ),
			'value'     => array( 'value', false ),
		);
	}

	/**
	 * Normalizes raw request args into repository-ready query parameters.
	 *
	 * @param array<string, mixed> $request Raw, unslashed request values.
	 * @return array{filters: array<string, string>, orderby: string, order: string, page: int, per_page: int}
	 */
	public static function parse_query_args( array $request ): array {
		$filters = array();

		$status = isset( $request['status'] ) ? (string) $request['status'] : '';
		if ( Status::is_valid( $status ) ) {
			$filters['status'] = $status;
		}

		$category = isset( $request['category'] ) ? (string) $request['category'] : '';
		if ( Category::is_valid( $category ) ) {
			$filters['category'] = $category;
		}

		$search = isset( $request['s'] ) ? trim( (string) $request['s'] ) : '';
		if ( '' !== $search ) {
			$filters['search'] = $search;
		}

		return array(
			'filters'  => $filters,
			'orderby'  => isset( $request['orderby'] ) ? (string) $request['orderby'] : 'created_at',
			'order'    => isset( $request['order'] ) ? (string) $request['order'] : 'desc',
			'page'     => isset( $request['paged'] ) ? max( 1, (int) $request['paged'] ) : 1,
			'per_page' => 20,
		);
	}
}
