<?php
/**
 * Settings storage for WP-PluginsUsed.
 *
 * Two rows, and nothing else. `wp_pluginsused_options` holds every setting as a
 * nested array; `wp_pluginsused_version` holds the two upgrade markers. They are
 * kept apart on purpose: the settings screen writes one and the upgrade routine
 * writes the other, so neither can overwrite the other's work, and the sanitise
 * callback never has to rescue a value the form did not post.
 *
 * Before 2.0.0 there was no row at all -- the two settings were a `define()` and
 * a global variable inside the plugin file itself, which meant every WordPress
 * update silently reverted them.
 *
 * @package WP-PluginsUsed
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads, upgrades and normalises the plugin's stored rows.
 */
class WP_PluginsUsed_Options {

	/**
	 * The option row holding every setting, as a nested array.
	 *
	 * @var string
	 */
	const OPTION = 'wp_pluginsused_options';

	/**
	 * The option row holding the 'plugin' and 'db' version markers.
	 *
	 * @var string
	 */
	const VERSION = 'wp_pluginsused_version';

	/**
	 * The settings row this plugin used before 2.0.0 shipped.
	 *
	 * The rename happened inside the unreleased major -- the last release, 1.50,
	 * stored nothing at all -- so this is only ever seen on an install that ran a
	 * development build. It is folded in and deleted rather than left to rot.
	 *
	 * @var string
	 */
	const LEGACY_OPTION = 'pluginsused_options';

	/**
	 * Default values, and by extension the canonical shape of the option.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'show_version'   => true,
			'hidden_plugins' => array(),
		);
	}

	/**
	 * The stored settings, merged over the defaults.
	 *
	 * A missing row is indistinguishable from an all-defaults row, so a fresh
	 * install needs no seeding and no migration.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$options = wp_parse_args( $stored, self::defaults() );

		if ( ! is_array( $options['hidden_plugins'] ) ) {
			$options['hidden_plugins'] = array();
		}

		return $options;
	}

	/**
	 * The stored upgrade markers, normalised.
	 *
	 * @return array The 'plugin' and 'db' markers, each an empty string when unset.
	 */
	public static function get_versions() {
		$stored = get_option( self::VERSION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array(
			'plugin' => isset( $stored['plugin'] ) ? (string) $stored['plugin'] : '',
			'db'     => isset( $stored['db'] ) ? (string) $stored['db'] : '',
		);
	}

	/**
	 * Bring the stored rows up to date with the running code.
	 *
	 * Runs on activation and on every admin load, because activation hooks do not
	 * fire when a plugin is updated -- which is the usual reason a migration never
	 * runs. Idempotent: once the markers agree it costs one autoloaded read.
	 *
	 * Both markers are written together in one update_option() at the very end, so
	 * a half-finished upgrade never records itself as complete.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$versions = self::get_versions();

		if ( WP_PLUGINSUSED_VERSION === $versions['plugin'] && WP_PLUGINSUSED_DB_VERSION === $versions['db'] ) {
			return;
		}

		self::migrate();

		update_option(
			self::VERSION,
			array(
				'plugin' => WP_PLUGINSUSED_VERSION,
				'db'     => WP_PLUGINSUSED_DB_VERSION,
			)
		);
	}

	/**
	 * Fold the pre-2.0.0 settings row into the current one.
	 *
	 * The old row was named without the wp_ prefix every row in this collection
	 * now carries. It is read once, folded in, and deleted; re-running finds
	 * nothing left to do.
	 *
	 * Settings are re-sanitised on the way through, so an upgrade cleans a row an
	 * older, laxer build wrote just as thoroughly as a save would.
	 *
	 * @return void
	 */
	protected static function migrate() {
		$legacy = get_option( self::LEGACY_OPTION );

		if ( false !== $legacy ) {
			if ( false === get_option( self::OPTION ) ) {
				update_option( self::OPTION, self::sanitize( $legacy ) );
			}

			delete_option( self::LEGACY_OPTION );
		}

		$stored = get_option( self::OPTION );

		if ( false !== $stored ) {
			update_option( self::OPTION, self::sanitize( $stored ) );
		}
	}

	/**
	 * Whether the version number is appended to each plugin name.
	 *
	 * @return bool
	 */
	public static function show_version() {
		$options = self::get();
		$show    = (bool) $options['show_version'];

		/**
		 * Filters whether plugin version numbers are displayed.
		 *
		 * Renamed from `pluginsused_show_version` in 2.0.0. The old spelling is
		 * gone rather than deprecated: every hook in this collection carries the
		 * plugin's own prefix now, and a shim that keeps an unprefixed name alive
		 * keeps the clash it was renamed to avoid.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $show Whether to show version numbers.
		 */
		return (bool) apply_filters( 'wp_pluginsused_show_version', $show );
	}

	/**
	 * Plugin names that must not appear in any listing.
	 *
	 * @return string[]
	 */
	public static function hidden_plugins() {
		$options = self::get();
		$hidden  = $options['hidden_plugins'];

		/**
		 * Filters the list of plugin names to hide.
		 *
		 * Renamed from `pluginsused_hidden_plugins` in 2.0.0, and it no longer
		 * shares its name with a global variable: the settings screen is where
		 * plugins are hidden now, and this filter is for anything the screen
		 * cannot see.
		 *
		 * @since 2.0.0
		 *
		 * @param string[] $hidden Plugin names to hide, matched exactly against the plugin's Name header.
		 */
		$hidden = apply_filters( 'wp_pluginsused_hidden_plugins', $hidden );

		if ( ! is_array( $hidden ) ) {
			return array();
		}

		$hidden = array_map( 'strval', $hidden );

		return array_values( array_unique( array_filter( $hidden, 'strlen' ) ) );
	}

	/**
	 * Sanitize the whole option array on save.
	 *
	 * Called by register_setting(), which hands the entire nested array to a
	 * single callback, so this is the one place the form's input is validated.
	 *
	 * @param mixed $input Raw value submitted by the settings form.
	 * @return array
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$clean = self::defaults();

		// An unchecked checkbox is absent from the POST body entirely.
		$clean['show_version'] = ! empty( $input['show_version'] );

		$hidden = isset( $input['hidden_plugins'] ) ? (array) $input['hidden_plugins'] : array();
		$names  = array();

		foreach ( $hidden as $name ) {
			$name = sanitize_text_field( (string) $name );

			if ( '' !== $name ) {
				$names[] = $name;
			}
		}

		$clean['hidden_plugins'] = array_values( array_unique( $names ) );

		return $clean;
	}
}
