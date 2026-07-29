<?php
/**
 * Settings storage and precedence.
 *
 * @package WP-PluginsUsed
 */

/**
 * @covers WP_PluginsUsed_Options
 */
class Test_PluginsUsed_Options extends WP_PluginsUsed_TestCase {

	public function test_defaults_apply_when_no_row_exists() {
		delete_option( 'wp_pluginsused_options' );

		$options = WP_PluginsUsed_Options::get();

		$this->assertTrue( $options['show_version'] );
		$this->assertSame( array(), $options['hidden_plugins'] );
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

	public function test_the_version_row_holds_the_running_version_and_schema_counter() {
		WP_PluginsUsed_Options::maybe_upgrade();

		$this->assertSame(
			array(
				'plugin' => WP_PLUGINSUSED_VERSION,
				'db'     => WP_PLUGINSUSED_DB_VERSION,
			),
			get_option( 'wp_pluginsused_version' ),
			'Both markers are written together, so a half-finished upgrade cannot record itself as done.'
		);
	}

	public function test_the_upgrade_routine_is_idempotent() {
		WP_PluginsUsed_Options::maybe_upgrade();
		$first = get_option( 'wp_pluginsused_version' );

		WP_PluginsUsed_Options::maybe_upgrade();

		$this->assertSame( $first, get_option( 'wp_pluginsused_version' ), 'A second run must change nothing.' );
	}

	/**
	 * The unprefixed row only exists on an install that ran a development build
	 * of 2.0.0, and the upgrade routine is the only thing that will ever see it.
	 */
	public function test_the_migration_folds_the_unprefixed_row_in_and_deletes_it() {
		delete_option( 'wp_pluginsused_options' );
		update_option(
			'pluginsused_options',
			array(
				'show_version'   => false,
				'hidden_plugins' => array( 'Alpha Test Plugin' ),
			)
		);

		WP_PluginsUsed_Options::maybe_upgrade();

		$this->assertFalse( get_option( 'pluginsused_options' ), 'The old row must be deleted, not merely ignored.' );

		$stored = get_option( 'wp_pluginsused_options' );

		$this->assertFalse( $stored['show_version'], 'The stored setting must survive the rename.' );
		$this->assertSame( array( 'Alpha Test Plugin' ), $stored['hidden_plugins'], 'So must the hidden list.' );
	}

	public function test_the_migration_re_sanitises_what_an_older_build_stored() {
		delete_option( 'wp_pluginsused_options' );
		update_option(
			'pluginsused_options',
			array(
				'show_version'   => '1',
				'hidden_plugins' => array( 'One', 'One', '' ),
				'evil_key'       => 'payload',
			)
		);

		WP_PluginsUsed_Options::maybe_upgrade();

		$stored = get_option( 'wp_pluginsused_options' );

		$this->assertSame( array( 'show_version', 'hidden_plugins' ), array_keys( $stored ), 'An unknown key must not survive the fold.' );
		$this->assertSame( array( 'One' ), $stored['hidden_plugins'], 'Duplicates and blanks go the way they would on save.' );
	}

	/**
	 * A prefixed row that is already there wins: it is the newer of the two, and
	 * silently overwriting it with a development build's leftovers would lose a
	 * site owner's settings.
	 */
	public function test_the_migration_does_not_overwrite_an_existing_prefixed_row() {
		update_option( 'wp_pluginsused_options', array( 'hidden_plugins' => array( 'Kept' ) ) );
		update_option( 'pluginsused_options', array( 'hidden_plugins' => array( 'Discarded' ) ) );

		WP_PluginsUsed_Options::maybe_upgrade();

		$this->assertSame( array( 'Kept' ), get_option( 'wp_pluginsused_options' )['hidden_plugins'] );
		$this->assertFalse( get_option( 'pluginsused_options' ), 'The old row still goes.' );
	}

	public function test_the_markers_read_back_as_empty_strings_before_the_first_upgrade() {
		delete_option( 'wp_pluginsused_version' );

		$this->assertSame(
			array(
				'plugin' => '',
				'db'     => '',
			),
			WP_PluginsUsed_Options::get_versions(),
			'A missing row must not be mistaken for a version.'
		);
	}

	public function test_a_corrupt_version_row_is_survivable() {
		update_option( 'wp_pluginsused_version', 'not-an-array' );

		$this->assertSame(
			array(
				'plugin' => '',
				'db'     => '',
			),
			WP_PluginsUsed_Options::get_versions()
		);
	}

	public function test_partial_row_merges_over_defaults() {
		update_option( 'wp_pluginsused_options', array( 'show_version' => false ) );

		$options = WP_PluginsUsed_Options::get();

		$this->assertFalse( $options['show_version'] );
		$this->assertSame( array(), $options['hidden_plugins'], 'The missing key must come from the defaults.' );
	}

	public function test_scalar_option_value_falls_back_to_defaults() {
		update_option( 'wp_pluginsused_options', 'not-an-array' );

		$options = WP_PluginsUsed_Options::get();

		$this->assertTrue( $options['show_version'] );
		$this->assertSame( array(), $options['hidden_plugins'] );
	}

	public function test_non_array_hidden_plugins_is_coerced() {
		update_option( 'wp_pluginsused_options', array( 'hidden_plugins' => 'nope' ) );

		$this->assertSame( array(), WP_PluginsUsed_Options::get()['hidden_plugins'] );
	}

	public function test_stored_show_version_is_honoured() {
		update_option( 'wp_pluginsused_options', array( 'show_version' => false ) );

		$this->assertFalse( WP_PluginsUsed_Options::show_version() );
	}

	public function test_show_version_filter_overrides_the_stored_value() {
		update_option( 'wp_pluginsused_options', array( 'show_version' => false ) );

		add_filter( 'wp_pluginsused_show_version', '__return_true' );
		$this->assertTrue( WP_PluginsUsed_Options::show_version() );

		remove_filter( 'wp_pluginsused_show_version', '__return_true' );
		$this->assertFalse( WP_PluginsUsed_Options::show_version() );
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
		$this->assertContains( 'beta Test Plugin', WP_PluginsUsed_Options::hidden_plugins() );

		remove_filter( 'wp_pluginsused_hidden_plugins', $callback );
		$this->assertNotContains( 'beta Test Plugin', WP_PluginsUsed_Options::hidden_plugins() );
	}

	public function test_hidden_plugins_filter_returning_a_non_array_is_survivable() {
		add_filter( 'wp_pluginsused_hidden_plugins', '__return_false' );

		$this->assertSame( array(), WP_PluginsUsed_Options::hidden_plugins() );

		remove_filter( 'wp_pluginsused_hidden_plugins', '__return_false' );
	}

	public function test_sanitize_normalises_a_submitted_form() {
		$clean = WP_PluginsUsed_Options::sanitize(
			array(
				'show_version'   => '1',
				'hidden_plugins' => array( 'One', 'One', '', '   ', '<b>Two</b>' ),
			)
		);

		$this->assertTrue( $clean['show_version'] );
		$this->assertSame( array( 'One', 'Two' ), $clean['hidden_plugins'] );
	}

	public function test_sanitize_treats_an_absent_checkbox_as_false() {
		$clean = WP_PluginsUsed_Options::sanitize( array() );

		$this->assertFalse( $clean['show_version'] );
		$this->assertSame( array(), $clean['hidden_plugins'] );
	}

	public function test_sanitize_rejects_non_array_input() {
		$clean = WP_PluginsUsed_Options::sanitize( 'garbage' );

		$this->assertSame( array_keys( WP_PluginsUsed_Options::defaults() ), array_keys( $clean ) );
	}
}
