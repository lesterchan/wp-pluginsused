<?php
/**
 * Settings storage and precedence.
 *
 * @package WP-PluginsUsed
 */

/**
 * @covers WP_PluginsUsed_Options
 */
class WP_PluginsUsed_Options_Test extends WP_PluginsUsed_TestCase {

	public function test_defaults_apply_when_no_row_exists() {
		delete_option( 'wp_pluginsused_options' );

		$options = WP_PluginsUsed_Options::get();

		$this->assertTrue( $options['show_version'], 'With no stored row, show_version takes its shipped default of on.' );
		$this->assertSame( array(), $options['hidden_plugins'], 'With no row stored, nothing is hidden.' );
	}

	/**
	 * Two rows, both prefixed, and nothing else with the plugin's name in it.
	 */
	public function test_the_plugin_owns_exactly_the_two_canonical_rows() {
		global $wpdb;

		update_option( 'wp_pluginsused_options', WP_PluginsUsed_Options::defaults() );
		WP_PluginsUsed_Options::maybe_upgrade();

		$rows = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%pluginsused%' ORDER BY option_name"
		);

		$this->assertSame(
			array( 'wp_pluginsused_options', 'wp_pluginsused_version' ),
			$rows,
			'The settings row and the marker row are the plugin\'s whole footprint.'
		);
	}

	public function test_partial_row_merges_over_defaults() {
		update_option( 'wp_pluginsused_options', array( 'show_version' => false ) );

		$options = WP_PluginsUsed_Options::get();

		$this->assertFalse( $options['show_version'], 'A partial stored row is merged over the defaults, keeping the stored false.' );
		$this->assertSame( array(), $options['hidden_plugins'], 'The missing key must come from the defaults.' );
	}

	public function test_scalar_option_value_falls_back_to_defaults() {
		update_option( 'wp_pluginsused_options', 'not-an-array' );

		$options = WP_PluginsUsed_Options::get();

		$this->assertTrue( $options['show_version'], 'A scalar where an array belongs falls back to the defaults.' );
		$this->assertSame( array(), $options['hidden_plugins'], 'A scalar where an array belongs falls back to the defaults.' );
	}

	public function test_non_array_hidden_plugins_is_coerced() {
		update_option( 'wp_pluginsused_options', array( 'hidden_plugins' => 'nope' ) );

		$this->assertSame( array(), WP_PluginsUsed_Options::get()['hidden_plugins'], 'A non-array hidden list is coerced to an empty one rather than iterated.' );
	}

	public function test_stored_show_version_is_honoured() {
		update_option( 'wp_pluginsused_options', array( 'show_version' => false ) );

		$this->assertFalse( WP_PluginsUsed_Options::show_version(), 'A stored false is honoured.' );
	}

	public function test_show_version_filter_overrides_the_stored_value() {
		update_option( 'wp_pluginsused_options', array( 'show_version' => false ) );

		add_filter( 'wp_pluginsused_show_version', '__return_true' );
		$this->assertTrue( WP_PluginsUsed_Options::show_version(), 'A filter can override the stored value to on.' );

		remove_filter( 'wp_pluginsused_show_version', '__return_true' );
		$this->assertFalse( WP_PluginsUsed_Options::show_version(), 'A filter can override the stored value to off.' );
	}

	/**
	 * The pre-2.0.0 way of hiding plugins was an unprefixed global variable.
	 * 2.0.0 stops reading it: the settings screen and the prefixed filter are
	 * the two supported ways, and nothing unprefixed survives.
	 */
	public function test_the_legacy_global_is_no_longer_consulted() {
		$GLOBALS['pluginsused_hidden_plugins'] = array( 'Hidden Test Plugin' );

		$this->assertNotContains(
			'Hidden Test Plugin',
			WP_PluginsUsed_Options::hidden_plugins(),
			'The unprefixed global was dropped in 2.0.0 and must not hide anything.'
		);
	}

	public function test_the_legacy_constant_is_no_longer_consulted() {
		$this->assertStringNotContainsString(
			'PLUGINSUSED_SHOW_VERSION',
			wp_pluginsused_test_source_code(),
			'The unprefixed constant was dropped in 2.0.0; the stored setting is the only source.'
		);
	}

	public function test_hidden_plugins_are_deduplicated() {
		$callback = static function ( $hidden ) {
			$hidden[] = 'Alpha Test Plugin';
			return $hidden;
		};

		update_option( 'wp_pluginsused_options', array( 'hidden_plugins' => array( 'Alpha Test Plugin' ) ) );
		add_filter( 'wp_pluginsused_hidden_plugins', $callback );

		$hidden = WP_PluginsUsed_Options::hidden_plugins();

		remove_filter( 'wp_pluginsused_hidden_plugins', $callback );

		$this->assertSame( array( 'Alpha Test Plugin' ), $hidden, 'A name added twice must be listed once.' );
	}

	public function test_hidden_plugins_filter_applies() {
		$callback = static function ( $hidden ) {
			$hidden[] = 'beta Test Plugin';
			return $hidden;
		};

		add_filter( 'wp_pluginsused_hidden_plugins', $callback );
		$this->assertContains( 'beta Test Plugin', WP_PluginsUsed_Options::hidden_plugins(), 'A filter can add to the hidden list.' );

		remove_filter( 'wp_pluginsused_hidden_plugins', $callback );
		$this->assertNotContains( 'beta Test Plugin', WP_PluginsUsed_Options::hidden_plugins(), 'And removing the filter takes it back out.' );
	}

	public function test_hidden_plugins_filter_returning_a_non_array_is_survivable() {
		add_filter( 'wp_pluginsused_hidden_plugins', '__return_false' );

		$this->assertSame( array(), WP_PluginsUsed_Options::hidden_plugins(), 'A filter returning a non-array is answered with an empty list rather than fatal.' );

		remove_filter( 'wp_pluginsused_hidden_plugins', '__return_false' );
	}

	public function test_sanitize_normalises_a_submitted_form() {
		$clean = WP_PluginsUsed_Options::sanitize(
			array(
				'show_version'   => '1',
				'hidden_plugins' => array( 'One', 'One', '', '   ', '<b>Two</b>' ),
			)
		);

		$this->assertTrue( $clean['show_version'], 'A ticked checkbox normalises to true.' );
		$this->assertSame( array( 'One', 'Two' ), $clean['hidden_plugins'], 'The submitted list is normalised to the values that were ticked.' );
	}

	public function test_sanitize_treats_an_absent_checkbox_as_false() {
		$clean = WP_PluginsUsed_Options::sanitize( array() );

		$this->assertFalse( $clean['show_version'], 'An absent checkbox normalises to false rather than being left out.' );
		$this->assertSame( array(), $clean['hidden_plugins'], 'An absent checkbox clears the list rather than leaving it as it was.' );
	}

	public function test_sanitize_rejects_non_array_input() {
		$clean = WP_PluginsUsed_Options::sanitize( 'garbage' );

		$this->assertSame( array_keys( WP_PluginsUsed_Options::defaults() ), array_keys( $clean ), 'A non-array is answered with the full default shape, so no key is missing.' );
	}
}
