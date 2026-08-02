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
	 * Write the settings row from inside an upgrade.
	 *
	 * `update_option()` declines to write a value equal to the one
	 * `get_option()` would return, and `register_setting()` is passed a
	 * `default`, which installs a `default_option_wp_pluginsused_options` filter answering with
	 * the shipped defaults for a row that does not exist. So on an admin request
	 * -- the path every real update takes, because activation hooks do not fire
	 * on an update -- a migration whose result happens to equal the defaults
	 * writes nothing at all, while the legacy rows it read are deleted anyway.
	 *
	 * Passing an explicit default to `get_option()` defeats the registered one,
	 * because `filter_default_option()` returns early when a default was passed.
	 * That is what lets an absent row be told from a defaulted one and added
	 * outright. `add_option()` runs the sanitize callback exactly as
	 * `update_option()` does, so nothing else about the write changes.
	 *
	 * Latent here rather than live: `get()` merges over the defaults, so a
	 * missing row and a defaults row read identically and nothing is lost today.
	 * It stops being latent the moment this plugin gains a setting whose
	 * *absence* means something other than its default -- and then the failure is
	 * silent, browser-only, and the legacy rows are already gone. §7.6.1 has the
	 * three plugins this shape has already bitten.
	 *
	 * @param array $options The settings to store.
	 * @return void
	 */
	private static function write( array $options ) {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $options );

			return;
		}

		update_option( self::OPTION, $options );
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
			/*
			 * The raw row, never one register_setting() can synthesise. A plugin
			 * that passes a `default` installs a `default_option_*` filter with it,
			 * and a bare get_option() then answers with that defaults array instead
			 * of false for a row which does not exist -- so this branch is skipped
			 * and the legacy row is deleted a few lines below regardless, taking the
			 * settings with it. Passing an explicit default defeats the registered
			 * one: filter_default_option() returns early when a default was passed.
			 *
			 * It bites only on an admin request. Activation and WP-CLI never run
			 * register_setting(), which is why reactivating repairs it and why every
			 * test that goes through activation passes.
			 */
			if ( false === get_option( self::OPTION, false ) ) {
				self::write( self::sanitize( $legacy ) );
			}

			delete_option( self::LEGACY_OPTION );
		}

		$stored = get_option( self::OPTION, false );

		if ( false !== $stored ) {
			self::write( self::sanitize( $stored ) );
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
