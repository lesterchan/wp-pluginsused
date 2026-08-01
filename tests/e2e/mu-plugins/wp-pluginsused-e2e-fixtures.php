<?php
/**
 * Plugin Name: WP-PluginsUsed E2E fixtures
 * Description: Exposes display_pluginsused() through a shortcode and attaches the plugin's four public filters, so the browser suite can reach the paths a theme or a site plugin owns. Loaded only in the wp-env tests environment.
 *
 * The plugin has two ways out. The three shortcodes *return* their markup, and
 * display_pluginsused() *echoes* it through wp_kses() with the plugin's own
 * allow-list. Those are not the same path and they have not always agreed: the
 * icon's viewBox attribute was spelled in camel case once, kses lowercased it on
 * the echoing path alone, and a theme calling the template tag got markup the
 * shortcode never produced.
 *
 * A template tag is a theme's job to call, and no bundled theme calls this one,
 * so this file plays the part of a theme that does. Without it the echoing path
 * -- the plugin's documented public API -- would be reachable from PHPUnit only,
 * which is the half that cannot see what a browser makes of the markup.
 *
 * It is a fixture, not a shipped file: it lives under tests/ and is mapped into
 * wp-content/mu-plugins for the tests environment only, by .wp-env.json.
 *
 * @package WP-PluginsUsed
 */

defined( 'ABSPATH' ) || exit;

/*
 * Web requests only.
 *
 * wp-env maps this directory into the tests environment, and PHPUnit runs in
 * that same environment -- so without this guard the unit suite would load a
 * fixture that has no business being visible to a test that is not driving a
 * browser.
 */
if ( 'cli' === PHP_SAPI ) {
	return;
}

/**
 * Call display_pluginsused() the way a theme would, and capture what it echoed.
 *
 * @param array $atts {
 *     Shortcode attributes.
 *
 *     @type string $type Listing to render: 'stats', 'active', 'inactive', or
 *                        anything else, which the plugin documents as falling
 *                        back to the inactive listing.
 * }
 * @return string The markup the template tag printed.
 */
function wp_pluginsused_e2e_template_tag( $atts ) {
	$atts = shortcode_atts( array( 'type' => 'active' ), $atts, 'pluginsused_e2e_tag' );

	ob_start();

	display_pluginsused( $atts['type'], true );

	return '<div class="pluginsused-e2e-tag">' . ob_get_clean() . '</div>';
}

add_shortcode( 'pluginsused_e2e_tag', 'wp_pluginsused_e2e_template_tag' );

/**
 * Hide whatever the test asked for through the plugin's own filter.
 *
 * The settings screen tells the owner that plugins hidden through
 * `wp_pluginsused_hidden_plugins` are not listed on the screen and stay hidden
 * regardless of what is ticked. That is a promise about a PHP filter, and the
 * only way to check the browser keeps it is to have a filter attached.
 *
 * The names come from an option so this file is inert until a test asks for
 * something: a filter that always hid a plugin would quietly change every count
 * the rest of the suite asserts on.
 *
 * @param string[] $hidden Names already hidden by the settings row.
 * @return string[]
 */
function wp_pluginsused_e2e_filter_hidden( $hidden ) {
	$extra = get_option( 'wp_pluginsused_e2e_filter_hidden', array() );

	if ( ! is_array( $extra ) ) {
		return $hidden;
	}

	return array_merge( $hidden, $extra );
}

add_filter( 'wp_pluginsused_hidden_plugins', 'wp_pluginsused_e2e_filter_hidden' );

/**
 * Override the version-number setting through the plugin's own filter.
 *
 * The filter is the last word on this: it runs after the stored setting has been
 * read, so a site can force version numbers off however the box is ticked. That
 * ordering is the whole point of it and it is the thing a test has to prove,
 * which means the fixture has to be able to disagree with a saved `true`.
 *
 * An absent option means "say nothing", so this stays out of the way of the
 * tests that are about the checkbox rather than about the filter.
 *
 * @param bool $show Whether the stored setting says to show version numbers.
 * @return bool
 */
function wp_pluginsused_e2e_filter_show_version( $show ) {
	$answer = get_option( 'wp_pluginsused_e2e_show_version', null );

	return null === $answer ? $show : (bool) $answer;
}

add_filter( 'wp_pluginsused_show_version', 'wp_pluginsused_e2e_filter_show_version' );

/**
 * Add an entry to the collected listing through the plugin's own filter.
 *
 * `wp_pluginsused_plugins_used` is the filter for a site that wants to list
 * something wp-content/plugins does not hold -- a must-use plugin, a drop-in, a
 * theme's bundled library. Adding rather than removing is the direction worth
 * testing: it proves the filter's return value is what gets rendered and counted,
 * where a filter that only ever removed things could be confused with the
 * hidden-plugins list that already works.
 *
 * @param array $plugins_used Listing keyed by 'active' and 'inactive'.
 * @return array
 */
function wp_pluginsused_e2e_filter_listing( $plugins_used ) {
	$name = (string) get_option( 'wp_pluginsused_e2e_extra_plugin', '' );

	if ( '' === $name ) {
		return $plugins_used;
	}

	$plugins_used['active'][] = array(
		'Plugin_Name' => $name,
		'Plugin_URI'  => 'https://example.org/invented',
		'Description' => 'Invented by the filter, not found on disk.',
		'Author'      => 'The filter',
		'Author_URI'  => '',
		'Version'     => '0.1',
	);

	return $plugins_used;
}

add_filter( 'wp_pluginsused_plugins_used', 'wp_pluginsused_e2e_filter_listing' );

/**
 * Hand the settings screen to a lesser capability when the test asks.
 *
 * `read` is the capability every logged-in user has, so a subscriber reaching
 * the screen is unambiguous evidence the filter was honoured rather than that
 * some other gate happened to let them by.
 *
 * @param string $capability The capability the plugin requires.
 * @return string
 */
function wp_pluginsused_e2e_filter_capability( $capability ) {
	$answer = (string) get_option( 'wp_pluginsused_e2e_capability', '' );

	return '' === $answer ? $capability : $answer;
}

add_filter( 'wp_pluginsused_capability', 'wp_pluginsused_e2e_filter_capability' );
