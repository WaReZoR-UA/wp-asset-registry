<?php
/**
 * Fixed category set for an asset.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry;

/**
 * The closed set of asset categories. Backing values are stored in the
 * category column; labels are shown in the UI.
 */
enum Category: string {

	case Laptop    = 'laptop';
	case Vehicle   = 'vehicle';
	case Tool      = 'tool';
	case Furniture = 'furniture';

	/**
	 * Human-readable label for this category.
	 *
	 * @return string The display label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::Laptop    => 'Laptop',
			self::Vehicle   => 'Vehicle',
			self::Tool      => 'Tool',
			self::Furniture => 'Furniture',
		};
	}

	/**
	 * All backing slugs, in declaration order.
	 *
	 * @return array<int, string> The category slugs.
	 */
	public static function values(): array {
		return array_map( static fn ( self $c ): string => $c->value, self::cases() );
	}

	/**
	 * Slug => label map for select dropdowns.
	 *
	 * @return array<string, string> Slug-keyed labels.
	 */
	public static function options(): array {
		$options = array();
		foreach ( self::cases() as $case ) {
			$options[ $case->value ] = $case->label();
		}
		return $options;
	}

	/**
	 * Whether the given slug is a known category.
	 *
	 * @param string $value Candidate category slug.
	 * @return bool True when the slug maps to a case.
	 */
	public static function is_valid( string $value ): bool {
		return self::tryFrom( $value ) instanceof self;
	}
}
