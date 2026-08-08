<?php
/**
 * Uninstall WP-PluginsUsed.
 *
 * Runs with the plugin inactive, so nothing here may depend on the plugin's
 * own classes or functions being loaded.
 *
 * @package WP-PluginsUsed
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

/**
 * Delete the plugin's options for the current site.
 *
 * The row names are spelled out rather than read from WP_PluginsUsed_Options,
 * which is not loaded here. tests/test-metadata.php asserts over wp_options with
 * a LIKE rather than naming rows, so a row added later and forgotten in this
 * file fails the suite.
 *
 * @return void
 */
function wp_pluginsused_uninstall_site() {
	delete_option( 'wp_pluginsused_options' );
	delete_option( 'wp_pluginsused_version' );

	// The cached plugin headers. A site transient, so on multisite this is one
	// network-wide row rather than one per site -- deleting it inside the loop
	// costs a redundant query and nothing worse.
	delete_site_transient( 'wp_pluginsused_headers' );

	// The settings row went unprefixed before 2.0.0 shipped. The upgrade routine
	// deletes it, so this only catches an install that never reached wp-admin
	// between updating and being removed.
	delete_option( 'pluginsused_options' );
}

if ( is_multisite() ) {
	/*
	 * 'number' => 0 lifts WP_Site_Query's default cap of 100, which would
	 * otherwise leave the rows behind on every site past the hundredth
	 * while still reporting a successful uninstall. 'fields' => 'ids' avoids
	 * hydrating WP_Site objects the loop never looks at.
	 */
	$wp_pluginsused_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $wp_pluginsused_site_ids as $wp_pluginsused_site_id ) {
		// switch_to_blog() pushes onto a stack, so the restore belongs inside
		// the loop -- restoring once at the end leaves the stack unwound by one.
		switch_to_blog( (int) $wp_pluginsused_site_id );

		wp_pluginsused_uninstall_site();

		restore_current_blog();
	}
} else {
	wp_pluginsused_uninstall_site();
}
