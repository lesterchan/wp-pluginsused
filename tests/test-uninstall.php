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
	 * uninstall.php with every comment removed.
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
	 * switch_to_blog() pushes onto a stack, so restoring once after the loop
	 * leaves it unwound by exactly one.
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
	 * The option row is the plugin's entire footprint, so uninstall must name it.
	 */
	public function test_it_deletes_the_one_option_the_plugin_owns() {
		$this->assertMatchesRegularExpression(
			"/delete_option\(\s*'pluginsused_options'\s*\)/",
			$this->code()
		);
	}
}
