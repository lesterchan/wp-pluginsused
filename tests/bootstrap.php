<?php
/**
 * PHPUnit bootstrap for WP-PluginsUsed.
 *
 * Runs inside the wp-env "tests" container, where WP_TESTS_DIR is already
 * exported and the WordPress test library is present.
 *
 * @package WP-PluginsUsed
 */

/*
 * The fixtures write real plugin files through WP_Filesystem rather than through
 * mkdir() and file_put_contents(). Left to itself, get_filesystem_method() picks
 * "direct" only when the running user owns the files, which is not true inside a
 * container whose wp-content is bind-mounted from the host -- it would fall
 * through to the FTP transport and hand back false. Pinning the constant makes
 * the choice explicit instead of environment-dependent.
 */
define( 'FS_METHOD', 'direct' );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test library at {$_tests_dir}." . PHP_EOL;
	echo 'Run the suite through wp-env: bash bin/test.sh' . PHP_EOL;
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the plugin under test before WordPress finishes booting.
 *
 * @return void
 */
function wp_pluginsused_test_load_plugin() {
	require dirname( __DIR__ ) . '/wp-pluginsused.php';
}
tests_add_filter( 'muplugins_loaded', 'wp_pluginsused_test_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

// After the test library, not before: the base class extends WP_UnitTestCase,
// which does not exist until the bootstrap above has run.
require_once __DIR__ . '/helper-source.php';
require_once __DIR__ . '/helper-testcase.php';
