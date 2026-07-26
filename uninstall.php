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
 * @return void
 */
function pluginsused_delete_options() {
	delete_option( 'pluginsused_options' );
}

if ( is_multisite() ) {
	/*
	 * 'number' => 0 lifts WP_Site_Query's default cap of 100, which would
	 * otherwise leave the option behind on every site past the hundredth
	 * while still reporting a successful uninstall. 'fields' => 'ids' avoids
	 * hydrating WP_Site objects the loop never looks at.
	 */
	$pluginsused_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $pluginsused_site_ids as $pluginsused_site_id ) {
		// switch_to_blog() pushes onto a stack, so the restore belongs inside
		// the loop -- restoring once at the end leaves the stack unwound by one.
		switch_to_blog( (int) $pluginsused_site_id );

		pluginsused_delete_options();

		restore_current_blog();
	}
} else {
	pluginsused_delete_options();
}
