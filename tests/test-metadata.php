<?php
/**
 * The release invariants, asserted from the source and from the stored rows.
 *
 * Everything §7.2 asks of all nineteen plugins now lives in
 * Plugin_Metadata_TestCase. What is left here is what only WP-PluginsUsed can
 * say: the version it ships, its class prefix, the breaks its Upgrade Notice
 * has to cover, and the handful of rules that have no home in the shared base
 * -- the hook prefix, the dropped shims, and the readme link and tag hygiene.
 *
 * @package WP-PluginsUsed
 */

/**
 * WP-PluginsUsed's half of the shared metadata contract.
 *
 * @coversNothing
 */
class WP_PluginsUsed_Metadata_Test extends Plugin_Metadata_TestCase {

	/**
	 * The version this release ships.
	 *
	 * @return string
	 */
	protected function expected_version() {
		return '2.0.0';
	}

	/**
	 * The prefix every class the plugin declares carries.
	 *
	 * @return string
	 */
	protected function class_prefix() {
		return 'WP_PluginsUsed';
	}

	/**
	 * What a site owner updating from the released 1.50 would notice.
	 *
	 * The three filters were public and are gone under their old names, so both
	 * spellings of each have to appear. 1.50 was configured by editing the
	 * plugin file, and both escape routes people used to survive an update --
	 * the constant in wp-config.php and the global -- are dead as well.
	 *
	 * @return string[]
	 */
	protected function upgrade_notice_subjects() {
		return array(
			'WordPress 6.8',
			'PHP 8.2',
			'`pluginsused_show_version`',
			'`wp_pluginsused_show_version`',
			'`pluginsused_hidden_plugins`',
			'`wp_pluginsused_hidden_plugins`',
			'`pluginsused_plugins_used`',
			'`wp_pluginsused_plugins_used`',
			'PLUGINSUSED_SHOW_VERSION',
			'$pluginsused_hidden_plugins',
			'wp_pluginsused_options',
			'wp_pluginsused_version',
			'Settings -> WP-PluginsUsed',
		);
	}

	/**
	 * Seed the rows uninstall has to remove.
	 *
	 * @return void
	 */
	protected function seed_option_rows() {
		update_option( WP_PluginsUsed_Options::OPTION, WP_PluginsUsed_Options::defaults() );
		WP_PluginsUsed_Options::maybe_upgrade();
	}

	/**
	 * Write the wp_pluginsused_version marker row.
	 *
	 * @return void
	 */
	protected function write_version_row() {
		WP_PluginsUsed_Options::maybe_upgrade();
	}

	/**
	 * Round-trip the settings sanitiser.
	 *
	 * @param array $input What the settings form is pretending to have posted.
	 * @return array
	 */
	protected function sanitize_settings( array $input ) {
		return (array) WP_PluginsUsed_Options::sanitize( $input );
	}

	/**
	 * A real settings key beside the poison, so the sanitiser actually runs.
	 *
	 * @return array
	 */
	protected function settings_fixture() {
		return array( 'show_version' => '1' );
	}

	/**
	 * Five tags, because wordpress.org shows five and ignores the rest.
	 */
	public function test_the_readme_lists_exactly_five_tags() {
		preg_match( '/^Tags:\s*(.+?)\s*$/m', $this->readme(), $matches );

		$this->assertNotEmpty( $matches, 'The readme must carry a Tags line.' );
		$this->assertCount( 5, explode( ',', $matches[1] ), 'wordpress.org shows five tags and ignores the rest.' );
	}

	/**
	 * The licence statement must not contradict itself.
	 *
	 * The header says GPLv2 or later, so the copyright block below it has to
	 * offer the later versions too.
	 */
	public function test_the_licence_block_is_the_or_later_variant() {
		$this->assertSame( 'GPLv2 or later', $this->header_field( 'License' ) );
		$this->assertStringContainsString(
			'either version 2 of the License, or',
			$this->plugin_file(),
			'A version-2-only block would contradict the header two lines above it.'
		);
		$this->assertStringContainsString( '(at your option) any later version.', $this->plugin_file() );
	}

	/**
	 * Every hook this plugin fires carries its prefix.
	 *
	 * The three that did not were renamed in 2.0.0 and dropped rather than
	 * deprecated, so a bare one reappearing means a shim crept back in.
	 */
	public function test_every_fired_hook_carries_the_plugin_prefix() {
		preg_match_all(
			"/(?:apply_filters|do_action)(?:_ref_array|_deprecated)?\(\s*'([a-z0-9_]+)'/",
			wp_pluginsused_test_source_code(),
			$hooks
		);

		$this->assertNotEmpty( $hooks[1], 'The plugin fires at least one hook.' );

		foreach ( array_unique( $hooks[1] ) as $hook ) {
			$this->assertStringStartsWith(
				'wp_pluginsused_',
				$hook,
				"'{$hook}' is fired without the plugin's prefix."
			);
		}
	}

	/**
	 * The old filter names were dropped outright, not shimmed.
	 */
	public function test_no_deprecated_hook_shim_was_added() {
		$this->assertStringNotContainsString(
			'apply_filters_deprecated',
			wp_pluginsused_test_source_code(),
			'The old filter names were dropped outright; a shim would keep the clash alive.'
		);
	}

	/**
	 * A translation call without the text domain is never translated.
	 */
	public function test_every_translation_call_uses_the_plugin_text_domain() {
		preg_match_all( '/(?:__|_n|esc_html__|esc_attr__)\((.*?)\);/s', wp_pluginsused_test_source_code(), $calls );

		foreach ( $calls[1] as $arguments ) {
			$this->assertStringContainsString(
				"'wp-pluginsused'",
				$arguments,
				"A translation call is missing the text domain: {$arguments}"
			);
		}
	}

	/**
	 * The old forums.lesterchan.net is gone, and the rest had drifted to http.
	 *
	 * Code spans are exempt: they document input rather than link anywhere.
	 */
	public function test_no_insecure_or_dead_links_remain() {
		$readme = (string) preg_replace( '/`[^`]*`/', '', $this->readme() );

		$this->assertSame( 0, preg_match( '#http://#', $readme ), 'Every readme link must use https.' );
		$this->assertSame( 0, preg_match( '#http://#', $this->plugin_file() ) );
		$this->assertStringNotContainsString( 'forums.lesterchan.net', $readme );
	}
}
