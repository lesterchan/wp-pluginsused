<?php
/**
 * Source-inspection helpers shared by the test cases.
 *
 * Kept apart from helper-fixtures.php because a file may declare either
 * functions or a class, not both.
 *
 * @package WP-PluginsUsed
 */

/**
 * Every shipped PHP source file, root and includes/.
 *
 * Built from two glob() calls rather than one GLOB_BRACE pattern: GLOB_BRACE is
 * a GNU extension and is not defined in every PHP build, including the one in
 * the wp-env container.
 *
 * @return string[] Absolute paths.
 */
function wp_pluginsused_test_source_files() {
	$root = dirname( __DIR__ );

	return array_merge(
		(array) glob( $root . '/*.php' ),
		(array) glob( $root . '/includes/*.php' )
	);
}

/**
 * Every shipped PHP source file concatenated, with all comments removed.
 *
 * Comments must not be searchable: these files *document* the symbols they no
 * longer call, so "does the plugin still call pluginsused_format_display()?"
 * matches the docblock explaining that it does not, and a test asserting on the
 * raw text fails for the wrong reason -- or worse, passes for one.
 *
 * @param string[] $skip Basenames to leave out.
 * @return string
 */
function wp_pluginsused_test_source_code( array $skip = array() ) {
	$code = '';

	foreach ( wp_pluginsused_test_source_files() as $file ) {
		if ( in_array( basename( $file ), $skip, true ) ) {
			continue;
		}

		$code .= php_strip_whitespace( $file );
	}

	return $code;
}
