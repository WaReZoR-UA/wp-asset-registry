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

// Minimal stand-in for the WordPress WP_List_Table base class. Defining it
// here satisfies AssetListTable's file-level class_exists() guard so the unit
// suite never tries to require the real wp-admin include.
if ( ! class_exists( 'WP_List_Table' ) ) {
    // phpcs:ignore PEAR.NamingConventions.ValidClassName.Invalid -- mirrors WordPress class name.
    class WP_List_Table {
        public function __construct( $args = array() ) {}
    }
}

// Minimal stand-ins for the WordPress REST classes referenced in the REST
// controller's type hints. They only need to exist so the class file loads;
// the unit suite exercises the controller's pure seams, not these wrappers.
if ( ! class_exists( 'WP_REST_Request' ) ) {
    // phpcs:ignore PEAR.NamingConventions.ValidClassName.Invalid -- mirrors WordPress class name.
    class WP_REST_Request {
        /** @var array<string, mixed> */
        private array $params = array();

        public function set_param( string $key, $value ): void {
            $this->params[ $key ] = $value;
        }

        public function get_param( string $key ) {
            return $this->params[ $key ] ?? null;
        }
    }
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
    // phpcs:ignore PEAR.NamingConventions.ValidClassName.Invalid -- mirrors WordPress class name.
    class WP_REST_Response {
        /** @var mixed */
        public $data;

        /** @var array<string, string> */
        private array $headers = array();

        public function __construct( $data = null ) {
            $this->data = $data;
        }

        public function header( string $key, string $value ): void {
            $this->headers[ $key ] = $value;
        }

        /** @return array<string, string> */
        public function get_headers(): array {
            return $this->headers;
        }
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    // phpcs:ignore PEAR.NamingConventions.ValidClassName.Invalid -- mirrors WordPress class name.
    class WP_Error {
        public string $code;
        public string $message;

        /** @var array<string, mixed> */
        public array $data;

        /**
         * @param array<string, mixed> $data Error data (e.g. status).
         */
        public function __construct( string $code = '', string $message = '', array $data = array() ) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }
    }
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
