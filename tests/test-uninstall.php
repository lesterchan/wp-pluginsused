<?php
/**
 * Uninstall routine.
 *
 * A single-site suite cannot build a 101-site network, so the multisite loop is
 * asserted at the source level. That is a deliberately weak test of a
 * deliberately specific bug: get_sites() defaults 'number' to 100, so dropping
 * the argument silently leaves data behind on every site past the hundredth
 * while still reporting a clean uninstall.
 *
 * @package WP-PluginsUsed
 */

/**
 * @coversNothing
 */
class Test_PluginsUsed_Uninstall extends WP_UnitTestCase {

	/**
	 * Contents of uninstall.php.
	 *
	 * @return string
	 */
	protected function source() {
		return file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );
	}

	/**
	 * The uninstall routine with every comment removed.
	 *
	 * Matching the raw file is not good enough: this file *documents* the
	 * arguments it passes, so a regex for "'number' => 0" happily matches the
	 * comment explaining it and keeps passing after the argument itself is
	 * deleted. php_strip_whitespace() leaves only the code.
	 *
	 * @return string
	 */
	protected function code() {
		return php_strip_whitespace( dirname( __DIR__ ) . '/uninstall.php' );
	}

	public function test_uninstall_file_exists() {
		$this->assertFileExists( dirname( __DIR__ ) . '/uninstall.php' );
	}

	public function test_it_refuses_to_run_outside_the_uninstall_context() {
		$this->assertMatchesRegularExpression(
			"/if\s*\(\s*!\s*defined\(\s*'WP_UNINSTALL_PLUGIN'\s*\)\s*\)/",
			$this->code()
		);
	}

	public function test_site_query_is_not_capped_at_one_hundred() {
		$this->assertMatchesRegularExpression(
			"/get_sites\(.*?'number'\s*=>\s*0.*?\)/s",
			$this->code(),
			"get_sites() defaults 'number' to 100 and would skip every site after the hundredth."
		);
	}

	public function test_it_only_fetches_site_ids() {
		$this->assertMatchesRegularExpression( "/get_sites\(.*?'fields'\s*=>\s*'ids'.*?\)/s", $this->code() );
	}

	/**
	 * Restoring once after the loop leaves the switch_to_blog() stack unwound
	 * by exactly one, because every switch pushes onto it.
	 */
	public function test_restore_current_blog_is_inside_the_loop() {
		$code = $this->code();

		$foreach = strpos( $code, 'foreach' );
		$restore = strpos( $code, 'restore_current_blog' );
		$closing = strpos( $code, 'else' );

		$this->assertNotFalse( $foreach );
		$this->assertNotFalse( $restore );
		$this->assertGreaterThan( $foreach, $restore );
		$this->assertLessThan( $closing, $restore );
	}

	public function test_it_does_not_use_the_removed_wp_get_sites() {
		$this->assertStringNotContainsString(
			'wp_get_sites',
			$this->code(),
			'wp_get_sites() was removed in WordPress 5.1 and fatals on multisite uninstall.'
		);
	}

	/**
	 * Two rows are the plugin's entire footprint, and the third is what a
	 * development build of 2.0.0 may have left on an install that was removed
	 * before it ever reached wp-admin.
	 */
	public function test_it_deletes_every_row_the_plugin_can_have_written() {
		foreach ( array( 'wp_pluginsused_options', 'wp_pluginsused_version', 'pluginsused_options' ) as $row ) {
			$this->assertMatchesRegularExpression(
				"/delete_option\(\s*'" . $row . "'\s*\)/",
				$this->code(),
				"uninstall.php must delete the {$row} row."
			);
		}
	}

	/**
	 * Actually run it. The suite is single-site, so this exercises the else
	 * branch; the multisite loop above stays source-asserted because a
	 * single-site suite cannot build a 101-site network.
	 */
	public function test_running_uninstall_removes_every_row() {
		update_option( 'wp_pluginsused_options', array( 'show_version' => false ) );
		update_option( 'wp_pluginsused_version', array( 'plugin' => '2.0.0' ) );
		update_option( 'pluginsused_options', array( 'show_version' => false ) );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-pluginsused/wp-pluginsused.php' );
		}

		include_once dirname( __DIR__ ) . '/uninstall.php';

		$this->assertFalse( get_option( 'wp_pluginsused_options' ), 'The settings row must go.' );
		$this->assertFalse( get_option( 'wp_pluginsused_version' ), 'The marker row must go with it.' );
		$this->assertFalse( get_option( 'pluginsused_options' ), 'And so must the unprefixed leftover.' );
	}

	/**
	 * Uninstall runs with the plugin inactive, so it may not reach for the
	 * plugin's own classes or functions.
	 */
	public function test_it_does_not_depend_on_the_plugin_being_loaded() {
		$code = $this->code();

		foreach ( array( 'WP_PluginsUsed_Options', 'WP_PluginsUsed_Template', 'WP_PluginsUsed_Settings', 'display_pluginsused' ) as $symbol ) {
			$this->assertStringNotContainsString( $symbol, $code );
		}
	}

	/**
	 * Nothing else in wp_options belongs to this plugin, so uninstall naming
	 * those rows is complete rather than an oversight.
	 */
	public function test_the_plugin_writes_no_other_option_rows() {
		preg_match_all(
			'/(?:update_option|add_option)\(\s*([\'"])([^\'"]+)\1/',
			wp_pluginsused_test_source_code(),
			$matches
		);

		$this->assertSame(
			array(),
			array_diff( array_unique( $matches[2] ), array( 'wp_pluginsused_options', 'wp_pluginsused_version' ) ),
			'A row written but not deleted on uninstall is exactly what this catches.'
		);
	}
}
