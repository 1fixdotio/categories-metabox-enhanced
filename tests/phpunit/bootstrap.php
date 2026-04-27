<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads the WP test suite shipped by wp-phpunit and registers this plugin
 * before WordPress finishes booting so its hooks are in place when tests run.
 */

$_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = dirname( __DIR__, 2 ) . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Could not find wp-phpunit at {$_tests_dir}. Run `composer install`." . PHP_EOL );
	exit( 1 );
}

if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', dirname( __DIR__, 2 ) . '/wp-tests-config.php' );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', static function () {
	require dirname( __DIR__, 2 ) . '/categories-metabox-enhanced.php';
} );

require $_tests_dir . '/includes/bootstrap.php';
