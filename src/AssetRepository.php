<?php
/**
 * Data-access layer for assets over the custom wp_ar_assets table.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry;

/**
 * CRUD and list queries over the assets table. The SQL fragment builders
 * are pure so the WHERE/ORDER/LIMIT assembly is unit-testable without a DB.
 */
class AssetRepository {

	/**
	 * Columns that may appear in an ORDER BY clause.
	 */
	private const ORDERABLE = array(
		'name',
		'asset_tag',
		'category',
		'status',
		'value',
		'purchase_date',
		'created_at',
	);

	/**
	 * Column write formats, aligned with Asset::to_array() key order.
	 */
	private const FORMATS = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s' );

	/**
	 * Stores the WordPress database access object.
	 *
	 * @param \wpdb $wpdb WordPress database access object.
	 */
	public function __construct( private \wpdb $wpdb ) {}

	/**
	 * Fully-qualified table name for the current site prefix.
	 *
	 * @return string The prefixed table name.
	 */
	private function table(): string {
		return Schema::table_name( $this->wpdb->prefix );
	}

	/**
	 * Builds the optional WHERE clause for list queries.
	 *
	 * @param array<string, string> $filters status, category, search.
	 * @return array{sql: string, args: array<int, string>} Clause and bind args.
	 */
	public function build_where( array $filters ): array {
		$clauses = array();
		$args    = array();

		if ( ! empty( $filters['status'] ) ) {
			$clauses[] = 'status = %s';
			$args[]    = (string) $filters['status'];
		}
		if ( ! empty( $filters['category'] ) ) {
			$clauses[] = 'category = %s';
			$args[]    = (string) $filters['category'];
		}
		if ( ! empty( $filters['search'] ) ) {
			$like      = '%' . $this->wpdb_like( (string) $filters['search'] ) . '%';
			$clauses[] = '(name LIKE %s OR asset_tag LIKE %s OR location LIKE %s)';
			$args[]    = $like;
			$args[]    = $like;
			$args[]    = $like;
		}

		if ( array() === $clauses ) {
			return array(
				'sql'  => '',
				'args' => array(),
			);
		}

		return array(
			'sql'  => 'WHERE ' . implode( ' AND ', $clauses ),
			'args' => $args,
		);
	}

	/**
	 * Builds a safe ORDER BY fragment from whitelisted tokens only.
	 *
	 * @param string $orderby Requested column.
	 * @param string $order   Requested direction.
	 * @return string The ORDER BY fragment.
	 */
	public function build_order_by( string $orderby, string $order ): string {
		$column    = in_array( $orderby, self::ORDERABLE, true ) ? $orderby : 'created_at';
		$direction = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		return "ORDER BY {$column} {$direction}";
	}

	/**
	 * Builds the LIMIT/OFFSET fragment with clamped bounds.
	 *
	 * @param int $per_page Rows per page (clamped 1..100).
	 * @param int $page     1-based page number (clamped >= 1).
	 * @return array{sql: string, args: array<int, int>} Clause and bind args.
	 */
	public function build_limit( int $per_page, int $page ): array {
		$per_page = max( 1, min( 100, $per_page ) );
		$page     = max( 1, $page );
		$offset   = ( $page - 1 ) * $per_page;

		return array(
			'sql'  => 'LIMIT %d OFFSET %d',
			'args' => array( $per_page, $offset ),
		);
	}

	/**
	 * Inserts a new asset and returns its id.
	 *
	 * @param Asset $asset The asset to persist.
	 * @return int The new primary key.
	 */
	public function insert( Asset $asset ): int {
		$this->wpdb->insert( $this->table(), $asset->to_array(), self::FORMATS );
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Updates an existing asset.
	 *
	 * @param int   $id    Primary key.
	 * @param Asset $asset The new values.
	 * @return bool True on a successful update.
	 */
	public function update( int $id, Asset $asset ): bool {
		$result = $this->wpdb->update(
			$this->table(),
			$asset->to_array(),
			array( 'id' => $id ),
			self::FORMATS,
			array( '%d' )
		);
		return false !== $result;
	}

	/**
	 * Deletes an asset by id.
	 *
	 * @param int $id Primary key.
	 * @return bool True on a successful delete.
	 */
	public function delete( int $id ): bool {
		return false !== $this->wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Finds an asset by primary key.
	 *
	 * @param int $id Primary key.
	 * @return Asset|null The asset, or null when not found.
	 */
	public function find( int $id ): ?Asset {
		$table = $this->table();
		// Table identifier is derived from the trusted site prefix; values are prepared.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? Asset::from_array( $row ) : null;
	}

	/**
	 * Finds an asset by its unique business tag.
	 *
	 * @param string $tag Asset tag.
	 * @return Asset|null The asset, or null when not found.
	 */
	public function find_by_tag( string $tag ): ?Asset {
		$table = $this->table();
		// Table identifier is derived from the trusted site prefix; values are prepared.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$table} WHERE asset_tag = %s", $tag ), ARRAY_A );
		return is_array( $row ) ? Asset::from_array( $row ) : null;
	}

	/**
	 * Runs a filtered, ordered, paginated list query.
	 *
	 * @param array<string, string> $filters  status, category, search.
	 * @param string                $orderby  Sort column.
	 * @param string                $order    Sort direction.
	 * @param int                   $per_page Rows per page.
	 * @param int                   $page     1-based page number.
	 * @return array<int, Asset> The matching assets.
	 */
	public function query( array $filters, string $orderby, string $order, int $per_page, int $page ): array {
		$where = $this->build_where( $filters );
		$limit = $this->build_limit( $per_page, $page );
		$table = $this->table();

		$sql  = trim( "SELECT * FROM {$table} {$where['sql']} {$this->build_order_by( $orderby, $order )} {$limit['sql']}" );
		$args = array_merge( $where['args'], $limit['args'] );

		// Table identifier is derived from the trusted site prefix; values are prepared.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, ...$args ), ARRAY_A );

		return array_map(
			static fn ( array $row ): Asset => Asset::from_array( $row ),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Counts assets matching the given filters (for pagination totals).
	 *
	 * @param array<string, string> $filters status, category, search.
	 * @return int The total matching rows.
	 */
	public function count( array $filters ): int {
		$where = $this->build_where( $filters );
		$table = $this->table();
		$sql   = trim( "SELECT COUNT(*) FROM {$table} {$where['sql']}" );

		if ( array() === $where['args'] ) {
			// Table identifier is derived from the trusted site prefix; no dynamic values.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $this->wpdb->get_var( $sql );
		}

		// Table identifier is derived from the trusted site prefix; values are prepared.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $this->wpdb->get_var( $this->wpdb->prepare( $sql, ...$where['args'] ) );
	}

	/**
	 * Escapes LIKE wildcards in a search term. Uses $wpdb->esc_like when
	 * available; the fallback mirrors its behaviour for the unit suite.
	 *
	 * @param string $text Raw search term.
	 * @return string The escaped term.
	 */
	private function wpdb_like( string $text ): string {
		if ( method_exists( $this->wpdb, 'esc_like' ) ) {
			return $this->wpdb->esc_like( $text );
		}
		return addcslashes( $text, '_%\\' );
	}
}
