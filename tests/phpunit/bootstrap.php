<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads the WordPress test suite installed by bin/install-wp-tests.sh and
 * registers this plugin before WordPress finishes booting so its hooks are
 * in place when tests run.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Could not find the WordPress test suite at {$_tests_dir}." . PHP_EOL );
	fwrite( STDERR, "Run bin/install-wp-tests.sh, or set WP_TESTS_DIR to an existing install." . PHP_EOL );
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', static function () {
	require dirname( __DIR__, 2 ) . '/categories-metabox-enhanced.php';
} );

require $_tests_dir . '/includes/bootstrap.php';
