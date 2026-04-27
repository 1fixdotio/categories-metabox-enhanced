<?php
/**
 * WordPress test suite configuration.
 *
 * Loaded by wp-phpunit/wp-phpunit when running tests inside the wp-env
 * `tests-cli` container. Mirrors the credentials wp-env provisions for the
 * tests environment; using a dedicated table prefix keeps the test schema
 * separate from the installed site in the same database.
 */

define( 'ABSPATH', '/var/www/html/' );
define( 'WP_DEFAULT_THEME', 'default' );

define( 'DB_NAME', 'tests-wordpress' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'password' );
define( 'DB_HOST', 'tests-mysql' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
