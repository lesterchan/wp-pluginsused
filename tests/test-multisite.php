<?php
/**
 * Network activation: the upgrade routine has to reach every site.
 *
 * Only runs under WP_MULTISITE=1 (see bin/test-multisite.sh). The settings and
 * the version markers are per-site rows, so an activation that upgrades only
 * whichever site happened to be current leaves every other site's pre-2.0.0 row
 * unread. Nothing is destroyed by that -- the legacy row is only deleted once it
 * has been folded in, and the admin load runs the same routine -- which is
 * precisely why it went unnoticed: each site heals the moment somebody opens its
 * dashboard, and a network whose subsites are front-end only never does.
 *
 * @package WP-PluginsUsed
 */

/**
 * WP_PluginsUsed::activate() across a network.
 */
class WP_PluginsUsed_Multisite_Test extends WP_PluginsUsed_TestCase {

	/**
	 * Skip the whole class on a single site install.
	 *
	 * @return void
	 */
	public function set_up() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires a multisite install. Run bin/test-multisite.sh.' );
		}

		parent::set_up();
	}

	/**
	 * Create sites and put each one on a pre-2.0.0 install.
	 *
	 * The hidden plugin name differs per site so a loop that upgrades one site
	 * and reports success for all of them cannot pass by accident.
	 *
	 * @param int $count How many extra sites to create.
	 * @return array Site IDs mapped to the hidden plugin list each was seeded with.
	 */
	protected function seed_network( $count = 3 ) {
		$site_ids = array( get_current_blog_id() );

		for ( $i = 0; $i < $count; $i++ ) {
			$site_ids[] = self::factory()->blog->create();
		}

		$seeded = array();

		foreach ( $site_ids as $site_id ) {
			$hidden = array( "site-{$site_id}-only.php" );

			switch_to_blog( (int) $site_id );

			delete_option( WP_PluginsUsed_Options::OPTION );
			delete_option( WP_PluginsUsed_Options::VERSION );
			update_option( WP_PluginsUsed_Options::LEGACY_OPTION, array( 'hidden_plugins' => $hidden ) );

			restore_current_blog();

			$seeded[ (int) $site_id ] = $hidden;
		}

		return $seeded;
	}

	/**
	 * Network activation upgrades every site, not just the current one.
	 *
	 * @return void
	 */
	public function test_network_activation_upgrades_every_site() {
		$seeded = $this->seed_network( 3 );

		WP_PluginsUsed::activate( true );

		foreach ( $seeded as $site_id => $hidden ) {
			switch_to_blog( $site_id );

			$options = WP_PluginsUsed_Options::get();

			$this->assertSame(
				$hidden,
				$options['hidden_plugins'],
				"Site {$site_id} kept the defaults instead of its migrated settings."
			);

			$this->assertFalse(
				get_option( WP_PluginsUsed_Options::LEGACY_OPTION ),
				"The pre-2.0.0 row survived on site {$site_id}."
			);

			$this->assertSame(
				WP_PLUGINSUSED_VERSION,
				WP_PluginsUsed_Options::get_versions()['plugin'],
				"Site {$site_id} was never stamped with the running version."
			);

			restore_current_blog();
		}
	}

	/**
	 * Activating on one site does not touch the rest of the network.
	 *
	 * @return void
	 */
	public function test_single_site_activation_leaves_other_sites_alone() {
		$seeded = $this->seed_network( 1 );
		$others = array_slice( array_keys( $seeded ), 1 );

		WP_PluginsUsed::activate( false );

		foreach ( $others as $site_id ) {
			switch_to_blog( $site_id );

			$this->assertSame(
				array( 'hidden_plugins' => $seeded[ $site_id ] ),
				get_option( WP_PluginsUsed_Options::LEGACY_OPTION ),
				"A per-site activation migrated site {$site_id}."
			);

			restore_current_blog();
		}
	}

	/**
	 * The site query is uncapped and asks only for IDs.
	 *
	 * Asserted by reading the arguments the query was given rather than by
	 * building a 101 site fixture: get_sites() defaults to 100, so a larger
	 * network silently skips every site past the hundredth, and the cheap
	 * version of that assertion is the only one worth running per suite.
	 *
	 * @return void
	 */
	public function test_network_activation_queries_sites_without_a_cap() {
		$this->seed_network( 2 );

		$captured = array();
		add_action(
			'pre_get_sites',
			function ( $query ) use ( &$captured ) {
				$captured[] = $query->query_vars;
			}
		);

		WP_PluginsUsed::activate( true );

		$this->assertNotEmpty( $captured, 'Activation never queried the site list.' );
		$this->assertSame( 0, (int) $captured[0]['number'], 'get_sites() was left at its default cap of 100 sites.' );
		$this->assertSame( 'ids', $captured[0]['fields'], 'Only the site IDs are needed.' );
	}

	/**
	 * The blog stack is left unwound and the original site is current.
	 *
	 * Calling switch_to_blog() pushes onto a stack. Restoring once after the loop
	 * rather than once per iteration leaves the stack short, so whatever runs next
	 * operates against the last site visited instead of the one it thinks it is on.
	 *
	 * @return void
	 */
	public function test_network_activation_unwinds_the_blog_stack() {
		$original = get_current_blog_id();
		$this->seed_network( 3 );

		WP_PluginsUsed::activate( true );

		$this->assertFalse( ms_is_switched(), 'The blog stack was left switched.' );
		$this->assertSame( $original, get_current_blog_id(), 'The original site is no longer current.' );
	}
}
