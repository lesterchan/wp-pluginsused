<?php
/**
 * The cross-request cache of the installed plugins' headers.
 *
 * @package WP-PluginsUsed
 */

/**
 * These shortcodes run on the front end, and collecting the listing means
 * reading every plugin file's header off disk. Core caches that in a group it
 * registers as non-persistent, so the scan happens on every request no matter
 * what object cache the site runs. Every test here is about the copy that
 * survives the request, and about it not surviving anything it should not.
 */
class WP_PluginsUsed_Headers_Cache_Test extends WP_PluginsUsed_TestCase {

	/**
	 * The first collection stores the headers it read.
	 */
	public function test_the_first_collection_stores_the_headers() {
		$this->assertFalse( get_site_transient( WP_PluginsUsed_Template::HEADERS_TRANSIENT ), 'The test starts with nothing stored, or it proves nothing.' );

		WP_PluginsUsed_Template::get_plugins_used();

		$stored = get_site_transient( WP_PluginsUsed_Template::HEADERS_TRANSIENT );

		$this->assertIsArray( $stored, 'Collecting the listing did not store the headers it read.' );
		$this->assertArrayHasKey( 'zzz-alpha/zzz-alpha.php', $stored, 'The stored headers are core\'s scan, keyed by plugin file.' );
	}

	/**
	 * The listing is built from the stored headers, not from a fresh scan.
	 *
	 * Core's get_plugins() fires no filter -- all_plugins belongs to the list
	 * table -- so the only way to tell a read from a scan is to store something
	 * a scan would never produce and watch it come out of the listing.
	 */
	public function test_the_headers_are_read_off_disk_once() {
		$this->store_a_probe();

		$names = wp_list_pluck( WP_PluginsUsed_Template::get_plugins_used()['inactive'], 'Plugin_Name' );

		$this->assertContains( 'Cache Probe Plugin', $names, 'The plugins directory was scanned again rather than the stored headers being read.' );
	}

	/**
	 * Discarding the stored headers puts the scan back.
	 *
	 * The mirror of the test above, and the half that shows the cache can be
	 * got rid of at all rather than merely that it is there.
	 */
	public function test_flushing_the_headers_puts_the_scan_back() {
		$this->store_a_probe();

		WP_PluginsUsed_Template::flush_headers();

		$names = wp_list_pluck( WP_PluginsUsed_Template::get_plugins_used()['inactive'], 'Plugin_Name' );

		$this->assertNotContains( 'Cache Probe Plugin', $names, 'flush_headers() left the old headers in place.' );
		$this->assertContains( 'beta Test Plugin', $names, 'The rescan found the plugins that are really on disk.' );
	}

	/**
	 * Installing, updating, deleting, activating or deactivating discards it.
	 *
	 * Each of these is a moment the directory's contents may have changed, and
	 * a listing a day out of date is a plugin the owner has removed still being
	 * published on a public page.
	 *
	 * @dataProvider data_invalidating_hooks
	 *
	 * @param string $hook Action that must discard the stored headers.
	 */
	public function test_a_change_to_the_plugins_directory_discards_the_headers( $hook ) {
		// Core hangs Language_Pack_Upgrader::async_upgrade() off
		// upgrader_process_complete, and the upgrader classes are not loaded in
		// a PHPUnit run -- firing the action without them is a TypeError from
		// core rather than an answer about this plugin.
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$this->assertNotFalse(
			has_action( $hook, array( 'WP_PluginsUsed_Template', 'flush_headers' ) ),
			"Nothing is listening to '{$hook}', so firing it below proves nothing."
		);

		WP_PluginsUsed_Template::get_plugins_used();

		$this->assertIsArray( get_site_transient( WP_PluginsUsed_Template::HEADERS_TRANSIENT ), 'Nothing was stored, so the test below would pass for the wrong reason.' );

		// upgrader_process_complete carries two, and core's own listener on it
		// is typed to require both.
		do_action( $hook, '', array() );

		$this->assertFalse( get_site_transient( WP_PluginsUsed_Template::HEADERS_TRANSIENT ), "The '{$hook}' action left the cached headers in place." );
	}

	/**
	 * The actions that must invalidate the stored headers.
	 *
	 * @return array Test data.
	 */
	public function data_invalidating_hooks() {
		return array(
			'activated'   => array( 'activated_plugin' ),
			'deactivated' => array( 'deactivated_plugin' ),
			'deleted'     => array( 'deleted_plugin' ),
			'upgraded'    => array( 'upgrader_process_complete' ),
		);
	}

	/**
	 * Saving the settings does not invalidate anything, and need not.
	 *
	 * The hidden-plugins list and the version switch are applied to the headers
	 * on every request rather than baked into them, which is the reason the
	 * cache can be kept for a day without a settings save going unnoticed. That
	 * is a property of where the cache sits, so it is worth a test of its own.
	 */
	public function test_hiding_a_plugin_takes_effect_without_a_rescan() {
		WP_PluginsUsed_Template::get_plugins_used();

		$stored = get_site_transient( WP_PluginsUsed_Template::HEADERS_TRANSIENT );

		update_option( 'wp_pluginsused_options', array( 'hidden_plugins' => array( 'Alpha Test Plugin' ) ) );
		WP_PluginsUsed_Template::reset_cache();

		$names = wp_list_pluck( WP_PluginsUsed_Template::get_plugins_used()['active'], 'Plugin_Name' );

		$this->assertNotContains( 'Alpha Test Plugin', $names, 'A plugin hidden on the settings screen went on being listed until the cache expired.' );
		$this->assertSame( $stored, get_site_transient( WP_PluginsUsed_Template::HEADERS_TRANSIENT ), 'Saving the settings threw away headers it had no reason to.' );
	}

	/**
	 * Stores headers naming a plugin that is not on disk.
	 *
	 * @return void
	 */
	protected function store_a_probe() {
		set_site_transient(
			WP_PluginsUsed_Template::HEADERS_TRANSIENT,
			array(
				'cache-probe/cache-probe.php' => array(
					'Name'        => 'Cache Probe Plugin',
					'PluginURI'   => '',
					'Description' => '',
					'Author'      => '',
					'AuthorURI'   => '',
					'Version'     => '1.0',
				),
			),
			WP_PluginsUsed_Template::HEADERS_TTL
		);

		WP_PluginsUsed_Template::reset_cache();
	}
}
