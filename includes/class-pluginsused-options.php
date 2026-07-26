<?php
/**
 * Settings storage for WP-PluginsUsed.
 *
 * Everything the plugin can be configured with lives in a single option row,
 * `pluginsused_options`, holding a nested array. Before 2.0.0 there was no
 * option at all -- the two settings were a `define()` and a global variable
 * inside the plugin file itself, which meant every WordPress update silently
 * reverted them.
 *
 * @package WP-PluginsUsed
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and normalises the plugin's settings.
 */
class PluginsUsed_Options {

	/**
	 * Name of the single option row holding every setting.
	 *
	 * @var string
	 */
	const OPTION = 'pluginsused_options';

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
	 * Whether the version number is appended to each plugin name.
	 *
	 * @return bool
	 */
	public static function show_version() {
		$options = self::get();
		$show    = (bool) $options['show_version'];

		/*
		 * Backwards compatibility: until 2.0.0 this was a constant defined in
		 * the plugin file. The plugin no longer defines it, so it is only set
		 * if the site itself does -- in wp-config.php or an mu-plugin -- and
		 * an explicit site-level constant should outrank the stored setting.
		 */
		if ( defined( 'PLUGINSUSED_SHOW_VERSION' ) ) {
			$show = (bool) constant( 'PLUGINSUSED_SHOW_VERSION' );
		}

		/**
		 * Filters whether plugin version numbers are displayed.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $show Whether to show version numbers.
		 */
		return (bool) apply_filters( 'pluginsused_show_version', $show );
	}

	/**
	 * Plugin names that must not appear in any listing.
	 *
	 * @return string[]
	 */
	public static function hidden_plugins() {
		$options = self::get();
		$hidden  = $options['hidden_plugins'];

		/*
		 * Backwards compatibility: the documented pre-2.0.0 way to hide
		 * plugins was to edit this global into the plugin file. Anything a
		 * site still sets is merged in rather than replaced, so the setting
		 * screen and the global can coexist.
		 */
		if ( isset( $GLOBALS['pluginsused_hidden_plugins'] ) && is_array( $GLOBALS['pluginsused_hidden_plugins'] ) ) {
			$hidden = array_merge( $hidden, $GLOBALS['pluginsused_hidden_plugins'] );
		}

		/**
		 * Filters the list of plugin names to hide.
		 *
		 * @since 2.0.0
		 *
		 * @param string[] $hidden Plugin names to hide, matched exactly against the plugin's Name header.
		 */
		$hidden = apply_filters( 'pluginsused_hidden_plugins', $hidden );

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
