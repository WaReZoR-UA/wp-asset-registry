<?php
/**
 * Admin list table for assets: columns, sorting, filters, pagination.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Admin;

use AssetRegistry\Asset;
use AssetRegistry\AssetRepository;
use AssetRegistry\Category;
use AssetRegistry\Status;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the admin assets table. All pure data-shaping (columns, sorting,
 * query-arg parsing) is delegated to AssetListData so it stays unit-testable;
 * this subclass only wires that logic into the core WP_List_Table machinery
 * and is verified manually in wp-admin.
 */
final class AssetListTable extends \WP_List_Table {

	public const PAGE_SLUG    = 'asset-registry';
	public const DELETE_NONCE = 'asset_registry_delete_asset';

	/**
	 * Stores the optional repository dependency.
	 *
	 * @param AssetRepository|null $repository Injected for testing; built lazily otherwise.
	 */
	public function __construct( private ?AssetRepository $repository = null ) {
		parent::__construct(
			array(
				'singular' => 'asset',
				'plural'   => 'assets',
				'ajax'     => false,
			)
		);
	}

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
	 * Column header map for the table.
	 *
	 * @return array<string, string> Column key => header label.
	 */
	public function get_columns(): array {
		return AssetListData::columns();
	}

	/**
	 * Sortable column map for the table.
	 *
	 * @return array<string, array{0: string, 1: bool}> Sortable map.
	 */
	protected function get_sortable_columns(): array {
		return AssetListData::sortable_columns();
	}

	/**
	 * Loads the current page of assets and configures pagination.
	 */
	public function prepare_items(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filters; values are whitelisted in AssetListData::parse_query_args().
		$request = wp_unslash( $_GET );
		$args    = AssetListData::parse_query_args( is_array( $request ) ? $request : array() );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		$total = $this->repository()->count( $args['filters'] );

		$this->items = $this->repository()->query(
			$args['filters'],
			$args['orderby'],
			$args['order'],
			$args['per_page'],
			$args['page']
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $args['per_page'],
				'total_pages' => (int) ceil( $total / max( 1, $args['per_page'] ) ),
			)
		);
	}

	/**
	 * Renders a generic column value for an asset row.
	 *
	 * @param Asset  $item        The asset for the current row.
	 * @param string $column_name The column being rendered.
	 * @return string The escaped cell value.
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'category':
				return esc_html( Category::is_valid( $item->category ) ? Category::from( $item->category )->label() : $item->category );
			case 'status':
				return esc_html( Status::is_valid( $item->status ) ? Status::from( $item->status )->label() : $item->status );
			case 'value':
				return esc_html( number_format_i18n( $item->value, 2 ) );
			case 'asset_tag':
			case 'name':
			case 'location':
			case 'assigned_to':
				return esc_html( (string) $item->{$column_name} );
			default:
				return '';
		}
	}

	/**
	 * Renders the Tag column with edit/delete row actions.
	 *
	 * @param Asset $item The asset for the current row.
	 * @return string The cell markup, including row actions.
	 */
	public function column_asset_tag( Asset $item ): string {
		$id         = (int) $item->id;
		$edit_url   = add_query_arg(
			array(
				'page'   => self::PAGE_SLUG,
				'action' => 'edit',
				'asset'  => $id,
			),
			admin_url( 'admin.php' )
		);
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => self::PAGE_SLUG,
					'action' => 'delete',
					'asset'  => $id,
				),
				admin_url( 'admin.php' )
			),
			self::DELETE_NONCE . '_' . $id
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'asset-registry' ) ),
			'delete' => sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Delete this asset?', 'asset-registry' ) ),
				esc_html__( 'Delete', 'asset-registry' )
			),
		);

		return sprintf( '<strong>%s</strong>%s', esc_html( $item->asset_tag ), $this->row_actions( $actions ) );
	}

	/**
	 * Renders the status filter control above the table.
	 *
	 * @param string $which Either 'top' or 'bottom' tablenav position.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter prefill; the value is matched against the Status whitelist.
		$current = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$current = Status::is_valid( $current ) ? $current : '';

		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="filter-by-status">' . esc_html__( 'Filter by status', 'asset-registry' ) . '</label>';
		echo '<select name="status" id="filter-by-status">';
		echo '<option value="">' . esc_html__( 'All statuses', 'asset-registry' ) . '</option>';
		foreach ( Status::options() as $slug => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $slug ),
				selected( $current, $slug, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		submit_button( __( 'Filter', 'asset-registry' ), 'button', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Message shown when no assets match the current view.
	 */
	public function no_items(): void {
		esc_html_e( 'No assets found.', 'asset-registry' );
	}
}
