<?php
/**
 * The plugin's first migration, and the markers that gate it.
 *
 * @package WP-PluginsUsed
 */

/**
 * @covers WP_PluginsUsed_Options
 */
class WP_PluginsUsed_Upgrade_Test extends WP_PluginsUsed_TestCase {

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

		$this->assertSame( array( 'Kept' ), get_option( 'wp_pluginsused_options' )['hidden_plugins'], 'An existing prefixed row wins; the migration does not overwrite what is there.' );
		$this->assertFalse( get_option( 'pluginsused_options' ), 'The old row still goes.' );
	}

	/**
	 * The same fold, on the path every real update takes.
	 *
	 * Activation hooks do not fire when a plugin is updated, so a site that
	 * updates through the Plugins screen reaches the migration through admin_init
	 * -- and register_settings() is hooked to admin_init first, so by then
	 * register_setting()'s `default` has installed a default_option filter and a
	 * bare get_option() answers with the defaults array rather than false. The
	 * "there is no current row yet" branch was therefore never taken, while the
	 * delete a few lines below ran regardless: the hidden-plugins list was read
	 * and thrown away.
	 *
	 * Every test above passes on that bug, because none of them registers the
	 * setting first -- which is the same thing as saying they all test WP-CLI.
	 */
	public function test_the_migration_folds_the_row_in_on_the_admin_path_too() {
		delete_option( 'wp_pluginsused_options' );
		update_option(
			'pluginsused_options',
			array(
				'show_version'   => false,
				'hidden_plugins' => array( 'Alpha Test Plugin' ),
			)
		);

		WP_PluginsUsed_Settings::register_settings();
		WP_PluginsUsed_Options::maybe_upgrade();

		$stored = get_option( 'wp_pluginsused_options', false );

		$this->assertIsArray( $stored, 'The migration wrote no settings row at all.' );
		$this->assertSame(
			array( 'Alpha Test Plugin' ),
			$stored['hidden_plugins'],
			'The hidden list has to survive an update as well as a reactivation.'
		);
		$this->assertFalse(
			get_option( 'pluginsused_options' ),
			'The old row is gone, so whatever the fold missed is gone with it.'
		);
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
			WP_PluginsUsed_Options::get_versions(),
			'A corrupt version row reads as empty markers rather than propagating.'
		);
	}
}
