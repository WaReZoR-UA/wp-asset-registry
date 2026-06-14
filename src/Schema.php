<?php
/**
 * Custom table name and schema definition for the Asset Registry plugin.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry;

/**
 * Owns the custom table name and its dbDelta-compatible CREATE TABLE SQL.
 */
final class Schema {

	/**
	 * Base table name, appended to the site table prefix.
	 */
	public const TABLE = 'ar_assets';

	/**
	 * Fully-qualified, prefixed table name.
	 *
	 * @param string $prefix Site table prefix (for example wp_).
	 * @return string The prefixed table name.
	 */
	public static function table_name( string $prefix ): string {
		return $prefix . self::TABLE;
	}

	/**
	 * CREATE TABLE statement formatted for dbDelta (two spaces after
	 * PRIMARY KEY, one column/key per line, KEY not INDEX).
	 *
	 * @param string $table_name      Fully-qualified, prefixed table name.
	 * @param string $charset_collate Database charset and collation clause.
	 * @return string The dbDelta-compatible CREATE TABLE statement.
	 */
	public static function create_sql( string $table_name, string $charset_collate ): string {
		return "CREATE TABLE {$table_name} (
\t\tid BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
\t\tasset_tag VARCHAR(64) NOT NULL,
\t\tname VARCHAR(191) NOT NULL,
\t\tcategory VARCHAR(64) NOT NULL DEFAULT '',
\t\tstatus VARCHAR(32) NOT NULL DEFAULT 'active',
\t\tlocation VARCHAR(191) NOT NULL DEFAULT '',
\t\tassigned_to VARCHAR(191) NOT NULL DEFAULT '',
\t\tpurchase_date DATE DEFAULT NULL,
\t\tvalue DECIMAL(10,2) NOT NULL DEFAULT 0.00,
\t\tnotes TEXT NULL,
\t\tattachment_path VARCHAR(255) DEFAULT NULL,
\t\tcreated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
\t\tupdated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
\t\tPRIMARY KEY  (id),
\t\tUNIQUE KEY asset_tag (asset_tag),
\t\tKEY status (status),
\t\tKEY category (category)
\t) {$charset_collate};";
	}
}
