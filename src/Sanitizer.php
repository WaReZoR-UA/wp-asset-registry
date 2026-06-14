<?php
/**
 * Sanitizes and validates raw asset form input into a safe column array.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry;

use DateTimeImmutable;

/**
 * Pure sanitize/validate stage. Takes raw request data and returns a
 * column-keyed array of trusted values ready for the repository.
 */
final class Sanitizer {

	/**
	 * Cleans raw form input into a safe, column-keyed array.
	 *
	 * @param array<string, mixed> $raw Untrusted request values.
	 * @return array<string, mixed> Sanitized, validated column values.
	 */
	public static function sanitize( array $raw ): array {
		$category = sanitize_text_field( (string) ( $raw['category'] ?? '' ) );
		$status   = sanitize_text_field( (string) ( $raw['status'] ?? '' ) );
		$date     = trim( (string) ( $raw['purchase_date'] ?? '' ) );

		return array(
			'asset_tag'     => trim( sanitize_text_field( (string) ( $raw['asset_tag'] ?? '' ) ) ),
			'name'          => trim( sanitize_text_field( (string) ( $raw['name'] ?? '' ) ) ),
			'category'      => Category::is_valid( $category ) ? $category : '',
			'status'        => Status::is_valid( $status ) ? $status : Status::Active->value,
			'location'      => trim( sanitize_text_field( (string) ( $raw['location'] ?? '' ) ) ),
			'assigned_to'   => trim( sanitize_text_field( (string) ( $raw['assigned_to'] ?? '' ) ) ),
			'purchase_date' => self::is_valid_date( $date ) ? $date : null,
			'value'         => self::normalize_value( (string) ( $raw['value'] ?? '' ) ),
			'notes'         => sanitize_textarea_field( (string) ( $raw['notes'] ?? '' ) ),
		);
	}

	/**
	 * Whether the string is a real Y-m-d calendar date.
	 *
	 * @param string $value Candidate date string.
	 * @return bool True when the date parses back to the same Y-m-d.
	 */
	public static function is_valid_date( string $value ): bool {
		if ( '' === $value ) {
			return false;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date instanceof DateTimeImmutable && $date->format( 'Y-m-d' ) === $value;
	}

	/**
	 * Parses a loosely formatted money string to a non-negative 2dp float.
	 *
	 * @param string $value Raw value string (may contain currency/grouping).
	 * @return float The normalized, clamped value.
	 */
	private static function normalize_value( string $value ): float {
		$stripped = preg_replace( '/[^0-9.\-]/', '', $value );
		if ( ! is_string( $stripped ) || '' === $stripped || ! is_numeric( $stripped ) ) {
			return 0.0;
		}
		return round( max( 0.0, (float) $stripped ), 2 );
	}
}
