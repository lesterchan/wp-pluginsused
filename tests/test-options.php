<?php
/**
 * Settings storage and precedence.
 *
 * @package WP-PluginsUsed
 */

/**
 * @covers PluginsUsed_Options
 */
class Test_PluginsUsed_Options extends PluginsUsed_TestCase {

	public function test_defaults_apply_when_no_row_exists() {
		delete_option( 'pluginsused_options' );

		$options = PluginsUsed_Options::get();

		$this->assertTrue( $options['show_version'] );
		$this->assertSame( array(), $options['hidden_plugins'] );
	}

	public function test_the_plugin_owns_exactly_one_option_row() {
		global $wpdb;

		update_option( 'pluginsused_options', PluginsUsed_Options::defaults() );

		$rows = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%pluginsused%'"
		);

		$this->assertSame( array( 'pluginsused_options' ), $rows );
	}

	public function test_partial_row_merges_over_defaults() {
		update_option( 'pluginsused_options', array( 'show_version' => false ) );

		$options = PluginsUsed_Options::get();

		$this->assertFalse( $options['show_version'] );
		$this->assertSame( array(), $options['hidden_plugins'], 'The missing key must come from the defaults.' );
	}

	public function test_scalar_option_value_falls_back_to_defaults() {
		update_option( 'pluginsused_options', 'not-an-array' );

		$options = PluginsUsed_Options::get();

		$this->assertTrue( $options['show_version'] );
		$this->assertSame( array(), $options['hidden_plugins'] );
	}

	public function test_non_array_hidden_plugins_is_coerced() {
		update_option( 'pluginsused_options', array( 'hidden_plugins' => 'nope' ) );

		$this->assertSame( array(), PluginsUsed_Options::get()['hidden_plugins'] );
	}

	public function test_stored_show_version_is_honoured() {
		update_option( 'pluginsused_options', array( 'show_version' => false ) );

		$this->assertFalse( PluginsUsed_Options::show_version() );
	}

	public function test_show_version_filter_overrides_the_stored_value() {
		update_option( 'pluginsused_options', array( 'show_version' => false ) );

		add_filter( 'pluginsused_show_version', '__return_true' );
		$this->assertTrue( PluginsUsed_Options::show_version() );

		remove_filter( 'pluginsused_show_version', '__return_true' );
		$this->assertFalse( PluginsUsed_Options::show_version() );
	}

	/**
	 * The pre-2.0.0 way of hiding plugins was a global, documented in the readme.
	 */
	public function test_legacy_global_still_hides_plugins() {
		$GLOBALS['pluginsused_hidden_plugins'] = array( 'Hidden Test Plugin' );

		$this->assertContains( 'Hidden Test Plugin', PluginsUsed_Options::hidden_plugins() );
	}

	public function test_legacy_global_merges_with_the_stored_list_rather_than_replacing_it() {
		update_option( 'pluginsused_options', array( 'hidden_plugins' => array( 'Alpha Test Plugin' ) ) );
		$GLOBALS['pluginsused_hidden_plugins'] = array( 'Hidden Test Plugin' );

		$hidden = PluginsUsed_Options::hidden_plugins();

		$this->assertContains( 'Alpha Test Plugin', $hidden );
		$this->assertContains( 'Hidden Test Plugin', $hidden );
	}

	public function test_hidden_plugins_are_deduplicated() {
		update_option( 'pluginsused_options', array( 'hidden_plugins' => array( 'Alpha Test Plugin' ) ) );
		$GLOBALS['pluginsused_hidden_plugins'] = array( 'Alpha Test Plugin' );

		$this->assertSame( array( 'Alpha Test Plugin' ), PluginsUsed_Options::hidden_plugins() );
	}

	public function test_hidden_plugins_filter_applies() {
		$callback = static function ( $hidden ) {
			$hidden[] = 'beta Test Plugin';
			return $hidden;
		};

		add_filter( 'pluginsused_hidden_plugins', $callback );
		$this->assertContains( 'beta Test Plugin', PluginsUsed_Options::hidden_plugins() );

		remove_filter( 'pluginsused_hidden_plugins', $callback );
		$this->assertNotContains( 'beta Test Plugin', PluginsUsed_Options::hidden_plugins() );
	}

	public function test_hidden_plugins_filter_returning_a_non_array_is_survivable() {
		add_filter( 'pluginsused_hidden_plugins', '__return_false' );

		$this->assertSame( array(), PluginsUsed_Options::hidden_plugins() );

		remove_filter( 'pluginsused_hidden_plugins', '__return_false' );
	}

	public function test_sanitize_normalises_a_submitted_form() {
		$clean = PluginsUsed_Options::sanitize(
			array(
				'show_version'   => '1',
				'hidden_plugins' => array( 'One', 'One', '', '   ', '<b>Two</b>' ),
			)
		);

		$this->assertTrue( $clean['show_version'] );
		$this->assertSame( array( 'One', 'Two' ), $clean['hidden_plugins'] );
	}

	public function test_sanitize_treats_an_absent_checkbox_as_false() {
		$clean = PluginsUsed_Options::sanitize( array() );

		$this->assertFalse( $clean['show_version'] );
		$this->assertSame( array(), $clean['hidden_plugins'] );
	}

	public function test_sanitize_rejects_non_array_input() {
		$clean = PluginsUsed_Options::sanitize( 'garbage' );

		$this->assertSame( array_keys( PluginsUsed_Options::defaults() ), array_keys( $clean ) );
	}
}
