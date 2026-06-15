<?php
/**
 * Asset data-transfer object mirroring the wp_ar_assets columns.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry;

/**
 * Read-mostly value object for a single asset row. Constructed from a DB
 * row via from_array(); flattened to writable columns via to_array().
 */
final class Asset {

	/**
	 * Holds the column values for a single asset row.
	 *
	 * @param int|null    $id              Primary key, null before persistence.
	 * @param string      $asset_tag       Unique business identifier.
	 * @param string      $name            Display name.
	 * @param string      $category        Category slug (see Category enum).
	 * @param string      $status          Status slug (see Status enum).
	 * @param string      $location        Free-text location.
	 * @param string      $assigned_to     Free-text assignee.
	 * @param string|null $purchase_date   ISO date (Y-m-d) or null.
	 * @param float       $value           Monetary value.
	 * @param string      $notes           Free-text notes.
	 * @param string|null $attachment_path Relative path inside protected store, or null.
	 * @param string|null $created_at      DB-managed creation timestamp.
	 * @param string|null $updated_at      DB-managed update timestamp.
	 */
	public function __construct(
		public readonly ?int $id,
		public readonly string $asset_tag,
		public readonly string $name,
		public readonly string $category,
		public readonly string $status,
		public readonly string $location,
		public readonly string $assigned_to,
		public readonly ?string $purchase_date,
		public readonly float $value,
		public readonly string $notes,
		public readonly ?string $attachment_path,
		public readonly ?string $created_at = null,
		public readonly ?string $updated_at = null
	) {}

	/**
	 * Builds an Asset from a (DB row or sanitized input) associative array.
	 *
	 * @param array<string, mixed> $row Column-keyed values.
	 * @return self The hydrated asset.
	 */
	public static function from_array( array $row ): self {
		return new self(
			isset( $row['id'] ) ? (int) $row['id'] : null,
			(string) ( $row['asset_tag'] ?? '' ),
			(string) ( $row['name'] ?? '' ),
			(string) ( $row['category'] ?? '' ),
			(string) ( $row['status'] ?? '' ),
			(string) ( $row['location'] ?? '' ),
			(string) ( $row['assigned_to'] ?? '' ),
			isset( $row['purchase_date'] ) && '' !== $row['purchase_date'] ? (string) $row['purchase_date'] : null,
			isset( $row['value'] ) ? (float) $row['value'] : 0.0,
			(string) ( $row['notes'] ?? '' ),
			isset( $row['attachment_path'] ) && '' !== $row['attachment_path'] ? (string) $row['attachment_path'] : null,
			isset( $row['created_at'] ) ? (string) $row['created_at'] : null,
			isset( $row['updated_at'] ) ? (string) $row['updated_at'] : null
		);
	}

	/**
	 * Flattens to the writable columns only (no id, no DB-managed timestamps).
	 *
	 * @return array<string, mixed> Column-keyed writable values.
	 */
	public function to_array(): array {
		return array(
			'asset_tag'       => $this->asset_tag,
			'name'            => $this->name,
			'category'        => $this->category,
			'status'          => $this->status,
			'location'        => $this->location,
			'assigned_to'     => $this->assigned_to,
			'purchase_date'   => $this->purchase_date,
			'value'           => $this->value,
			'notes'           => $this->notes,
			'attachment_path' => $this->attachment_path,
		);
	}
}
