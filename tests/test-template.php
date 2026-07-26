<?php
/**
 * Collection and rendering of the listings.
 *
 * @package WP-PluginsUsed
 */

/**
 * @covers PluginsUsed_Template
 */
class Test_PluginsUsed_Template extends PluginsUsed_TestCase {

	/**
	 * Names present in one of the rendered listings.
	 *
	 * @param string $html Rendered markup.
	 * @return string[]
	 */
	protected function names_in( $html ) {
		preg_match_all( '~<strong><(?:a[^>]*)>([^<]*)</a></strong>|<strong>([^<]*)</strong><br~', $html, $m );

		return array_values( array_filter( array_merge( $m[1], $m[2] ), 'strlen' ) );
	}

	public function test_active_and_inactive_are_split_by_active_plugins() {
		$active   = PluginsUsed_Template::render( 'active' );
		$inactive = PluginsUsed_Template::render( 'inactive' );

		$this->assertStringContainsString( 'Alpha Test Plugin', $active );
		$this->assertStringNotContainsString( 'Alpha Test Plugin', $inactive );

		$this->assertStringContainsString( 'beta Test Plugin', $inactive );
		$this->assertStringNotContainsString( 'beta Test Plugin', $active );
	}

	/**
	 * Network-activated plugins used to be reported as inactive, because only
	 * active_plugins was consulted.
	 */
	public function test_network_activated_plugins_count_as_active() {
		update_option( 'active_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array( 'zzz-alpha/zzz-alpha.php' => time() ) );
		PluginsUsed_Template::reset_cache();

		$this->assertStringContainsString( 'Alpha Test Plugin', PluginsUsed_Template::render( 'active' ) );
		$this->assertStringNotContainsString( 'Alpha Test Plugin', PluginsUsed_Template::render( 'inactive' ) );

		delete_site_option( 'active_sitewide_plugins' );
	}

	public function test_listing_is_sorted_case_insensitively() {
		$inactive = PluginsUsed_Template::render( 'inactive' );

		$beta   = strpos( $inactive, 'beta Test Plugin' );
		$hidden = strpos( $inactive, 'Hidden Test Plugin' );

		$this->assertNotFalse( $beta );
		$this->assertNotFalse( $hidden );
		$this->assertLessThan(
			$hidden,
			$beta,
			'A lowercase name must sort by letter, not after every uppercase one.'
		);
	}

	public function test_stats_counts_match_the_listings() {
		$stats    = PluginsUsed_Template::render( 'stats' );
		$used     = PluginsUsed_Template::get_plugins_used();
		$active   = count( $used['active'] );
		$inactive = count( $used['inactive'] );

		$this->assertStringContainsString( '<strong>' . number_format_i18n( $active + $inactive ) . '</strong>', $stats );
		$this->assertStringContainsString( '<strong>' . number_format_i18n( $active ) . ' active plugin', $stats );
		$this->assertStringContainsString( '<strong>' . number_format_i18n( $inactive ) . ' inactive plugin', $stats );
	}

	public function test_version_is_appended_by_default() {
		$this->assertStringContainsString( 'Alpha Test Plugin 1.2.3', PluginsUsed_Template::render( 'active' ) );
	}

	public function test_version_is_suppressed_when_disabled() {
		update_option( 'pluginsused_options', array( 'show_version' => false ) );
		PluginsUsed_Template::reset_cache();

		$active = PluginsUsed_Template::render( 'active' );

		$this->assertStringNotContainsString( '1.2.3', $active );
		$this->assertStringContainsString( 'Alpha Test Plugin', $active, 'The name must survive.' );
	}

	public function test_hidden_plugins_are_removed_from_listing_and_counts() {
		$before = PluginsUsed_Template::get_plugins_used();
		$total  = count( $before['active'] ) + count( $before['inactive'] );

		update_option( 'pluginsused_options', array( 'hidden_plugins' => array( 'Hidden Test Plugin' ) ) );
		PluginsUsed_Template::reset_cache();

		$after     = PluginsUsed_Template::get_plugins_used();
		$new_total = count( $after['active'] ) + count( $after['inactive'] );
		$markup    = PluginsUsed_Template::render( 'active' ) . PluginsUsed_Template::render( 'inactive' );

		$this->assertStringNotContainsString( 'Hidden Test Plugin', $markup );
		$this->assertSame( $total - 1, $new_total, 'A hidden plugin must leave the counts too.' );
		$this->assertStringContainsString(
			'<strong>' . number_format_i18n( $new_total ) . '</strong>',
			PluginsUsed_Template::render( 'stats' )
		);
	}

	public function test_plugins_used_filter_can_alter_the_listing() {
		$callback = static function ( $used ) {
			$used['active'] = array();
			return $used;
		};

		add_filter( 'pluginsused_plugins_used', $callback );
		PluginsUsed_Template::reset_cache();

		$this->assertSame( '', PluginsUsed_Template::render( 'active' ) );

		remove_filter( 'pluginsused_plugins_used', $callback );
	}

	public function test_unknown_type_renders_the_inactive_listing() {
		$this->assertSame(
			PluginsUsed_Template::render( 'inactive' ),
			PluginsUsed_Template::render( 'anything-else' )
		);
	}

	public function test_entries_are_wrapped_one_paragraph_each() {
		$used = PluginsUsed_Template::get_plugins_used();

		$this->assertSame(
			count( $used['active'] ),
			substr_count( PluginsUsed_Template::render( 'active' ), '<p>' )
		);
	}

	public function test_active_and_inactive_use_different_icons() {
		$active   = PluginsUsed_Template::render( 'active' );
		$inactive = PluginsUsed_Template::render( 'inactive' );

		$this->assertStringContainsString( 'pluginsused-icon-active', $active );
		$this->assertStringContainsString( 'pluginsused-icon-inactive', $inactive );
		$this->assertStringNotContainsString( 'pluginsused-icon-inactive', $active );
	}

	/**
	 * The GIFs are gone; nothing may still reference them.
	 */
	public function test_no_image_requests_are_emitted() {
		$markup = PluginsUsed_Template::render( 'active' ) . PluginsUsed_Template::render( 'inactive' );

		$this->assertStringNotContainsString( '<img', $markup );
		$this->assertStringNotContainsString( '.gif', $markup );
	}

	public function test_empty_plugin_uri_does_not_emit_an_empty_anchor() {
		// The evil fixture's javascript: URI is dropped by esc_url(), leaving nothing to link to.
		$markup = PluginsUsed_Template::render( 'active' );

		$this->assertStringNotContainsString( 'href=""', $markup );
	}

	public function test_description_text_survives_rendering() {
		$parsed = $this->parse_html( PluginsUsed_Template::render( 'active' ) );

		$this->assertStringContainsString( 'A plain description.', $parsed['doc']->textContent );
	}
}
