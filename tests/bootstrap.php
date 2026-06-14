<?php
/**
 * PHPUnit bootstrap: Composer autoload plus minimal WordPress doubles
 * needed by the pure-unit suite (no live WordPress, no database).
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', sys_get_temp_dir() . '/' );
}

// WordPress core constant used as a $wpdb output type; the repository tests
// reference it, so define it for the no-WordPress unit suite.
if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

// Minimal stand-in for the WordPress $wpdb object. Mockery can mock this
// class to assert calls; tests that only read properties use it directly.
if ( ! class_exists( 'wpdb' ) ) {
    // phpcs:ignore PEAR.NamingConventions.ValidClassName.Invalid -- mirrors WordPress class name.
    class wpdb {
        public string $prefix = 'wp_';

        public function get_charset_collate(): string {
            return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }

        public function query( string $sql ) {
            return 0;
        }
    }
}
