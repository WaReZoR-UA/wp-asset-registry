<?php
/**
 * Server-side field visibility for asset data exposed over the REST API.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Support;

/**
 * Filters an asset row down to the fields the caller is allowed to see.
 * Anonymous callers receive a minimal public subset; authenticated viewers
 * receive the full business fields plus a derived has_attachment flag. The
 * raw attachment path and DB-managed columns are never exposed.
 */
final class FieldVisibility {

	/**
	 * Fields visible to anonymous callers, in output order.
	 */
	public const PUBLIC_FIELDS = array( 'name', 'category', 'status' );

	/**
	 * Fields visible to authenticated viewers, in output order.
	 */
	public const AUTH_FIELDS = array(
		'asset_tag',
		'name',
		'category',
		'status',
		'location',
		'assigned_to',
		'purchase_date',
		'value',
		'notes',
	);

	/**
	 * Reduces a row to the fields the caller may see.
	 *
	 * @param array<string, mixed> $row      Source asset row (column-keyed).
	 * @param bool                 $can_view Whether the caller holds the view capability.
	 * @return array<string, mixed> The visible subset, with keys in allowed order.
	 */
	public static function filter( array $row, bool $can_view ): array {
		if ( ! $can_view ) {
			return self::pick( $row, self::PUBLIC_FIELDS );
		}

		$out                   = self::pick( $row, self::AUTH_FIELDS );
		$out['has_attachment'] = ! empty( $row['attachment_path'] );

		return $out;
	}

	/**
	 * The exact set of keys a caller may receive, for callers that need to
	 * reason about the contract (controllers, tests).
	 *
	 * @param bool $can_view Whether the caller holds the view capability.
	 * @return array<int, string> The allowed key set.
	 */
	public static function allowed_keys( bool $can_view ): array {
		if ( ! $can_view ) {
			return self::PUBLIC_FIELDS;
		}

		return array_merge( self::AUTH_FIELDS, array( 'has_attachment' ) );
	}

	/**
	 * Copies the listed keys that are present in the row, preserving the
	 * order of the allowlist. Missing keys are omitted, not filled.
	 *
	 * @param array<string, mixed> $row  Source row.
	 * @param array<int, string>   $keys Allowed keys, in output order.
	 * @return array<string, mixed> The picked subset.
	 */
	private static function pick( array $row, array $keys ): array {
		$out = array();
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $row ) ) {
				$out[ $key ] = $row[ $key ];
			}
		}
		return $out;
	}
}
