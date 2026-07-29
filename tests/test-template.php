<?php
/**
 * Collection and rendering of the listings.
 *
 * @package WP-PluginsUsed
 */

/**
 * @covers WP_PluginsUsed_Template
 */
class Test_PluginsUsed_Template extends WP_PluginsUsed_TestCase {

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
		$active   = WP_PluginsUsed_Template::render( 'active' );
		$inactive = WP_PluginsUsed_Template::render( 'inactive' );

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
		WP_PluginsUsed_Template::reset_cache();

		$this->assertStringContainsString( 'Alpha Test Plugin', WP_PluginsUsed_Template::render( 'active' ) );
		$this->assertStringNotContainsString( 'Alpha Test Plugin', WP_PluginsUsed_Template::render( 'inactive' ) );

		delete_site_option( 'active_sitewide_plugins' );
	}

	public function test_listing_is_sorted_case_insensitively() {
		$inactive = WP_PluginsUsed_Template::render( 'inactive' );

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
		$stats    = WP_PluginsUsed_Template::render( 'stats' );
		$used     = WP_PluginsUsed_Template::get_plugins_used();
		$active   = count( $used['active'] );
		$inactive = count( $used['inactive'] );

		$this->assertStringContainsString( '<strong>' . number_format_i18n( $active + $inactive ) . '</strong>', $stats );
		$this->assertStringContainsString( '<strong>' . number_format_i18n( $active ) . ' active plugin', $stats );
		$this->assertStringContainsString( '<strong>' . number_format_i18n( $inactive ) . ' inactive plugin', $stats );
	}

	public function test_version_is_appended_by_default() {
		$this->assertStringContainsString( 'Alpha Test Plugin 1.2.3', WP_PluginsUsed_Template::render( 'active' ) );
	}

	public function test_version_is_suppressed_when_disabled() {
		update_option( 'wp_pluginsused_options', array( 'show_version' => false ) );
		WP_PluginsUsed_Template::reset_cache();

		$active = WP_PluginsUsed_Template::render( 'active' );

		$this->assertStringNotContainsString( '1.2.3', $active );
		$this->assertStringContainsString( 'Alpha Test Plugin', $active, 'The name must survive.' );
	}

	public function test_hidden_plugins_are_removed_from_listing_and_counts() {
		$before = WP_PluginsUsed_Template::get_plugins_used();
		$total  = count( $before['active'] ) + count( $before['inactive'] );

		update_option( 'wp_pluginsused_options', array( 'hidden_plugins' => array( 'Hidden Test Plugin' ) ) );
		WP_PluginsUsed_Template::reset_cache();

		$after     = WP_PluginsUsed_Template::get_plugins_used();
		$new_total = count( $after['active'] ) + count( $after['inactive'] );
		$markup    = WP_PluginsUsed_Template::render( 'active' ) . WP_PluginsUsed_Template::render( 'inactive' );

		$this->assertStringNotContainsString( 'Hidden Test Plugin', $markup );
		$this->assertSame( $total - 1, $new_total, 'A hidden plugin must leave the counts too.' );
		$this->assertStringContainsString(
			'<strong>' . number_format_i18n( $new_total ) . '</strong>',
			WP_PluginsUsed_Template::render( 'stats' )
		);
	}

	public function test_plugins_used_filter_can_alter_the_listing() {
		$callback = static function ( $used ) {
			$used['active'] = array();
			return $used;
		};

		add_filter( 'wp_pluginsused_plugins_used', $callback );
		WP_PluginsUsed_Template::reset_cache();

		$this->assertSame( '', WP_PluginsUsed_Template::render( 'active' ) );

		remove_filter( 'wp_pluginsused_plugins_used', $callback );
	}

	public function test_unknown_type_renders_the_inactive_listing() {
		$this->assertSame(
			WP_PluginsUsed_Template::render( 'inactive' ),
			WP_PluginsUsed_Template::render( 'anything-else' )
		);
	}

	public function test_entries_are_wrapped_one_paragraph_each() {
		$used = WP_PluginsUsed_Template::get_plugins_used();

		$this->assertSame(
			count( $used['active'] ),
			substr_count( WP_PluginsUsed_Template::render( 'active' ), '<p>' )
		);
	}

	public function test_active_and_inactive_use_different_icons() {
		$active   = WP_PluginsUsed_Template::render( 'active' );
		$inactive = WP_PluginsUsed_Template::render( 'inactive' );

		$this->assertStringContainsString( 'wp-pluginsused-icon-active', $active );
		$this->assertStringContainsString( 'wp-pluginsused-icon-inactive', $inactive );
		$this->assertStringNotContainsString( 'wp-pluginsused-icon-inactive', $active );
	}

	/**
	 * The GIFs are gone; nothing may still reference them.
	 */
	public function test_no_image_requests_are_emitted() {
		$markup = WP_PluginsUsed_Template::render( 'active' ) . WP_PluginsUsed_Template::render( 'inactive' );

		$this->assertStringNotContainsString( '<img', $markup );
		$this->assertStringNotContainsString( '.gif', $markup );
	}

	public function test_empty_plugin_uri_does_not_emit_an_empty_anchor() {
		// The evil fixture's javascript: URI is dropped by esc_url(), leaving nothing to link to.
		$markup = WP_PluginsUsed_Template::render( 'active' );

		$this->assertStringNotContainsString( 'href=""', $markup );
	}

	public function test_description_text_survives_rendering() {
		$parsed = $this->parse_html( WP_PluginsUsed_Template::render( 'active' ) );

		$this->assertStringContainsString( 'A plain description.', $parsed['doc']->textContent );
	}

	/**
	 * Hiding is an exact match on the plugin name, not a substring search --
	 * otherwise hiding "Alpha" would silently take "Alpha Test Plugin" with it.
	 */
	public function test_hiding_matches_the_whole_name_only() {
		update_option( 'wp_pluginsused_options', array( 'hidden_plugins' => array( 'Alpha' ) ) );
		WP_PluginsUsed_Template::reset_cache();

		$this->assertStringContainsString( 'Alpha Test Plugin', WP_PluginsUsed_Template::render( 'active' ) );
	}

	/**
	 * The name and version are joined with a space, so suppressing the version
	 * must not leave the name with a trailing one.
	 */
	public function test_no_trailing_space_when_the_version_is_suppressed() {
		update_option( 'wp_pluginsused_options', array( 'show_version' => false ) );
		WP_PluginsUsed_Template::reset_cache();

		$this->assertStringContainsString( '>Alpha Test Plugin</a>', WP_PluginsUsed_Template::render( 'active' ) );
	}

	public function test_missing_author_uri_renders_no_url_link() {
		$html = WP_PluginsUsed_Template::format(
			array(
				'Plugin_Name' => 'No Author URI',
				'Plugin_URI'  => 'https://example.com/x',
				'Description' => 'x',
				'Author'      => 'Someone',
				'Author_URI'  => '',
				'Version'     => '1.0',
			)
		);

		$this->assertStringContainsString( 'Someone', $html );
		$this->assertStringNotContainsString( '(<a', $html );
		$this->assertStringNotContainsString( 'href=""', $html );
	}

	public function test_missing_plugin_uri_renders_the_name_as_plain_text() {
		$html = WP_PluginsUsed_Template::format(
			array(
				'Plugin_Name' => 'No Plugin URI',
				'Plugin_URI'  => '',
				'Description' => 'x',
				'Author'      => 'Someone',
				'Author_URI'  => '',
				'Version'     => '1.0',
			)
		);

		$this->assertStringContainsString( '<strong>No Plugin URI 1.0</strong>', $html );
		$this->assertStringNotContainsString( 'href=""', $html );
	}

	/**
	 * Descriptions were run through wptexturize() before 2.0.0 and core's
	 * get_plugins() does not do it, so the plugin must keep doing it itself.
	 */
	public function test_description_is_texturized() {
		$used = WP_PluginsUsed_Template::get_plugins_used();

		$evil = null;
		foreach ( $used['active'] as $plugin ) {
			if ( 0 === strpos( $plugin['Plugin_Name'], 'Evil' ) ) {
				$evil = $plugin;
			}
		}

		$this->assertNotNull( $evil );
		$this->assertStringContainsString( '&#8220;quotes&#8221;', $evil['Description'] );
	}

	/**
	 * All three shortcodes run on one page load, so the scan happens once.
	 */
	public function test_listing_is_cached_within_the_request() {
		$first = WP_PluginsUsed_Template::get_plugins_used();

		update_option( 'wp_pluginsused_options', array( 'hidden_plugins' => array( 'Alpha Test Plugin' ) ) );

		$this->assertSame( $first, WP_PluginsUsed_Template::get_plugins_used(), 'Cached within the request.' );

		WP_PluginsUsed_Template::reset_cache();

		$this->assertNotSame( $first, WP_PluginsUsed_Template::get_plugins_used(), 'reset_cache() re-reads.' );
	}

	public function test_icons_are_accessible_without_relying_on_colour() {
		$active   = WP_PluginsUsed_Template::render( 'active' );
		$inactive = WP_PluginsUsed_Template::render( 'inactive' );

		// Announced to assistive tech...
		$this->assertStringContainsString( 'role="img"', $active );
		$this->assertStringContainsString( 'aria-label="Active plugin"', $active );
		$this->assertStringContainsString( 'aria-label="Inactive plugin"', $inactive );

		// ...and distinguished by shape, not just hue.
		$this->assertStringContainsString( '<path', $active );
		$this->assertStringContainsString( '<circle', $inactive );
	}

	public function test_icons_inherit_the_theme_colour() {
		$this->assertStringContainsString( 'currentColor', WP_PluginsUsed_Template::render( 'active' ) );
		$this->assertStringContainsString( 'currentColor', WP_PluginsUsed_Template::render( 'inactive' ) );
	}

	/**
	 * Class names are scoped under the plugin's slug, and the markup carries no
	 * presentation of its own for a theme to have to fight.
	 */
	public function test_front_end_markup_is_scoped_and_free_of_inline_style() {
		$markup = WP_PluginsUsed_Template::render( 'active' ) . WP_PluginsUsed_Template::render( 'inactive' );

		preg_match_all( '/class="([^"]*)"/', $markup, $classes );

		$this->assertNotEmpty( $classes[1], 'The listing must carry at least one class.' );

		foreach ( $classes[1] as $attribute ) {
			foreach ( explode( ' ', $attribute ) as $class ) {
				$this->assertStringStartsWith(
					'wp-pluginsused',
					$class,
					"Every class the plugin emits is scoped under its slug; '{$class}' is not."
				);
			}
		}

		$this->assertStringNotContainsString( 'style=', $markup, 'The plugin ships no inline CSS.' );
	}

	/**
	 * The plugin ships no stylesheet, so nothing may depend on one.
	 */
	public function test_no_stylesheet_or_script_is_enqueued() {
		do_action( 'wp_enqueue_scripts' );

		$this->assertFalse( wp_style_is( 'wp-pluginsused', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'wp-pluginsused', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'jquery', 'enqueued' ) );
	}

	public function test_a_plugin_without_a_name_header_is_skipped() {
		$this->create_plugin_fixture(
			'zzz-nameless',
			array(
				'Description' => 'No name header.',
				'Version'     => '1.0',
			)
		);
		$this->reset_plugin_state();

		$used  = WP_PluginsUsed_Template::get_plugins_used();
		$names = wp_list_pluck( array_merge( $used['active'], $used['inactive'] ), 'Plugin_Name' );

		$this->delete_plugin_fixture( 'zzz-nameless' );

		$this->assertNotContains( '', $names, 'A plugin with no Name header has nothing to list.' );
	}
}
