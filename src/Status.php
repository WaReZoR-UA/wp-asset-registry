<?php
/**
 * Fixed lifecycle statuses for an asset.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry;

/**
 * The closed set of asset statuses. Backing values are stored in the
 * status column; labels are shown in the UI.
 */
enum Status: string {

	case Active   = 'active';
	case InRepair = 'in_repair';
	case Retired  = 'retired';

	/**
	 * Human-readable label for this status.
	 *
	 * @return string The display label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::Active   => 'Active',
			self::InRepair => 'In Repair',
			self::Retired  => 'Retired',
		};
	}

	/**
	 * All backing slugs, in declaration order.
	 *
	 * @return array<int, string> The status slugs.
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
	 * Whether the given slug is a known status.
	 *
	 * @param string $value Candidate status slug.
	 * @return bool True when the slug maps to a case.
	 */
	public static function is_valid( string $value ): bool {
		return self::tryFrom( $value ) instanceof self;
	}
}
