<?php
/**
 * Collection and rendering of the listings.
 *
 * @package WP-PluginsUsed
 */

/**
 * @covers WP_PluginsUsed_Template
 */
class WP_PluginsUsed_Template_Test extends WP_PluginsUsed_TestCase {

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

		$this->assertStringContainsString( 'Alpha Test Plugin', $active, 'An active plugin is in the active listing.' );
		$this->assertStringNotContainsString( 'Alpha Test Plugin', $inactive, 'And not in the inactive one.' );

		$this->assertStringContainsString( 'beta Test Plugin', $inactive, 'An inactive plugin is in the inactive listing.' );
		$this->assertStringNotContainsString( 'beta Test Plugin', $active, 'And not in the active one, so the split is exclusive.' );
	}

	/**
	 * Network-activated plugins used to be reported as inactive, because only
	 * active_plugins was consulted.
	 */
	public function test_network_activated_plugins_count_as_active() {
		update_option( 'active_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array( 'zzz-alpha/zzz-alpha.php' => time() ) );
		WP_PluginsUsed_Template::reset_cache();

		$this->assertStringContainsString( 'Alpha Test Plugin', WP_PluginsUsed_Template::render( 'active' ), 'A network-activated plugin counts as active on the site.' );
		$this->assertStringNotContainsString( 'Alpha Test Plugin', WP_PluginsUsed_Template::render( 'inactive' ), 'Rather than appearing in both listings.' );

		delete_site_option( 'active_sitewide_plugins' );
	}

	public function test_listing_is_sorted_case_insensitively() {
		$inactive = WP_PluginsUsed_Template::render( 'inactive' );

		$beta   = strpos( $inactive, 'beta Test Plugin' );
		$hidden = strpos( $inactive, 'Hidden Test Plugin' );

		$this->assertNotFalse( $beta, 'beta Test Plugin is rendered at all, or the ordering assertion below is vacuous.' );
		$this->assertNotFalse( $hidden, 'Hidden Test Plugin is rendered at all, or the ordering assertion below is vacuous.' );
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

		$this->assertStringContainsString( '<strong>' . number_format_i18n( $active + $inactive ) . '</strong>', $stats, 'The stats total is the two listings added together.' );
		$this->assertStringContainsString( '<strong>' . number_format_i18n( $active ) . ' active plugin', $stats, 'The active count matches the active listing.' );
		$this->assertStringContainsString( '<strong>' . number_format_i18n( $inactive ) . ' inactive plugin', $stats, 'And the inactive count matches the inactive listing.' );
	}

	public function test_version_is_appended_by_default() {
		$this->assertStringContainsString( 'Alpha Test Plugin 1.2.3', WP_PluginsUsed_Template::render( 'active' ), 'The version is appended by default.' );
	}

	public function test_version_is_suppressed_when_disabled() {
		update_option( 'wp_pluginsused_options', array( 'show_version' => false ) );
		WP_PluginsUsed_Template::reset_cache();

		$active = WP_PluginsUsed_Template::render( 'active' );

		$this->assertStringNotContainsString( '1.2.3', $active, 'And suppressed entirely when the setting is off.' );
		$this->assertStringContainsString( 'Alpha Test Plugin', $active, 'The name must survive.' );
	}

	/**
	 * The stored list goes through sanitize_text_field(); the Name header goes
	 * through nothing. Comparing them raw meant a plugin whose name the
	 * sanitiser rewrites could be ticked, saved with a success notice, and go on
	 * being listed in full -- with the checkbox coming back unticked, so it read
	 * as a save that had not worked rather than as a suppression that had not.
	 */
	public function test_a_plugin_whose_name_the_sanitiser_rewrites_can_still_be_hidden() {
		$raw    = 'Gappy  Test%20 Plugin';
		$stored = sanitize_text_field( $raw );

		$this->assertNotSame( $raw, $stored, 'The premise: this name is not stored as it is written.' );

		$before = WP_PluginsUsed_Template::get_plugins_used();
		$total  = count( $before['active'] ) + count( $before['inactive'] );

		// Through the sanitiser, exactly as the settings screen would save it.
		update_option(
			'wp_pluginsused_options',
			WP_PluginsUsed_Options::sanitize( array( 'hidden_plugins' => array( $raw ) ) )
		);
		WP_PluginsUsed_Template::reset_cache();

		$after  = WP_PluginsUsed_Template::get_plugins_used();
		$markup = WP_PluginsUsed_Template::render( 'active' ) . WP_PluginsUsed_Template::render( 'inactive' );

		$this->assertStringNotContainsString( 'Gappy', $markup, 'The plugin is out of the listing.' );
		$this->assertSame( $total - 1, count( $after['active'] ) + count( $after['inactive'] ), 'And out of the counts.' );
	}

	public function test_hidden_plugins_are_removed_from_listing_and_counts() {
		$before = WP_PluginsUsed_Template::get_plugins_used();
		$total  = count( $before['active'] ) + count( $before['inactive'] );

		update_option( 'wp_pluginsused_options', array( 'hidden_plugins' => array( 'Hidden Test Plugin' ) ) );
		WP_PluginsUsed_Template::reset_cache();

		$after     = WP_PluginsUsed_Template::get_plugins_used();
		$new_total = count( $after['active'] ) + count( $after['inactive'] );
		$markup    = WP_PluginsUsed_Template::render( 'active' ) . WP_PluginsUsed_Template::render( 'inactive' );

		$this->assertStringNotContainsString( 'Hidden Test Plugin', $markup, 'A hidden plugin is out of the listing.' );
		$this->assertSame( $total - 1, $new_total, 'A hidden plugin must leave the counts too.' );
		$this->assertStringContainsString(
			'<strong>' . number_format_i18n( $new_total ) . '</strong>',
			WP_PluginsUsed_Template::render( 'stats' ),
			'And out of the counts, so the two cannot disagree.'
		);
	}

	public function test_plugins_used_filter_can_alter_the_listing() {
		$callback = static function ( $used ) {
			$used['active'] = array();
			return $used;
		};

		add_filter( 'wp_pluginsused_plugins_used', $callback );
		WP_PluginsUsed_Template::reset_cache();

		$this->assertSame( '', WP_PluginsUsed_Template::render( 'active' ), 'A filter can empty the listing, so it really decides what is rendered.' );

		remove_filter( 'wp_pluginsused_plugins_used', $callback );
	}

	public function test_unknown_type_renders_the_inactive_listing() {
		$this->assertSame(
			WP_PluginsUsed_Template::render( 'inactive' ),
			WP_PluginsUsed_Template::render( 'anything-else' ),
			'An unknown type falls back to the inactive listing rather than rendering nothing.'
		);
	}

	public function test_entries_are_wrapped_one_paragraph_each() {
		$used = WP_PluginsUsed_Template::get_plugins_used();

		$this->assertSame(
			count( $used['active'] ),
			substr_count( WP_PluginsUsed_Template::render( 'active' ), '<p>' ),
			'One paragraph per entry, so a theme can style them individually.'
		);
	}

	public function test_active_and_inactive_use_different_icons() {
		$active   = WP_PluginsUsed_Template::render( 'active' );
		$inactive = WP_PluginsUsed_Template::render( 'inactive' );

		$this->assertStringContainsString( 'wp-pluginsused-icon-active', $active, 'The active listing uses the active icon.' );
		$this->assertStringContainsString( 'wp-pluginsused-icon-inactive', $inactive, 'The inactive listing uses the inactive one.' );
		$this->assertStringNotContainsString( 'wp-pluginsused-icon-inactive', $active, 'And the two never appear together.' );
	}

	/**
	 * The GIFs are gone; nothing may still reference them.
	 */
	public function test_no_image_requests_are_emitted() {
		$markup = WP_PluginsUsed_Template::render( 'active' ) . WP_PluginsUsed_Template::render( 'inactive' );

		$this->assertStringNotContainsString( '<img', $markup, 'No image is requested; the icons are inline SVG.' );
		$this->assertStringNotContainsString( '.gif', $markup, 'And no raster file is referenced either.' );
	}

	public function test_empty_plugin_uri_does_not_emit_an_empty_anchor() {
		// The evil fixture's javascript: URI is dropped by esc_url(), leaving nothing to link to.
		$markup = WP_PluginsUsed_Template::render( 'active' );

		$this->assertStringNotContainsString( 'href=""', $markup, 'A plugin with no URI renders no empty anchor.' );
	}

	public function test_description_text_survives_rendering() {
		$parsed = $this->parse_html( WP_PluginsUsed_Template::render( 'active' ) );

		$this->assertStringContainsString( 'A plain description.', $parsed['doc']->textContent, 'The description reaches the page as text.' );
	}

	/**
	 * Hiding is an exact match on the plugin name, not a substring search --
	 * otherwise hiding "Alpha" would silently take "Alpha Test Plugin" with it.
	 */
	public function test_hiding_matches_the_whole_name_only() {
		update_option( 'wp_pluginsused_options', array( 'hidden_plugins' => array( 'Alpha' ) ) );
		WP_PluginsUsed_Template::reset_cache();

		$this->assertStringContainsString( 'Alpha Test Plugin', WP_PluginsUsed_Template::render( 'active' ), 'Hiding matches the whole name, so a substring does not hide a sibling.' );
	}

	/**
	 * The name and version are joined with a space, so suppressing the version
	 * must not leave the name with a trailing one.
	 */
	public function test_no_trailing_space_when_the_version_is_suppressed() {
		update_option( 'wp_pluginsused_options', array( 'show_version' => false ) );
		WP_PluginsUsed_Template::reset_cache();

		$this->assertStringContainsString( '>Alpha Test Plugin</a>', WP_PluginsUsed_Template::render( 'active' ), 'With the version suppressed the name has no trailing space left behind.' );
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

		$this->assertStringContainsString( 'Someone', $html, 'An author with no URI is still named.' );
		$this->assertStringNotContainsString( '(<a', $html, 'Just not linked.' );
		$this->assertStringNotContainsString( 'href=""', $html, 'And no empty anchor is emitted in place of the link.' );
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

		$this->assertStringContainsString( '<strong>No Plugin URI 1.0</strong>', $html, 'A plugin with no URI is rendered as plain text.' );
		$this->assertStringNotContainsString( 'href=""', $html, 'Rather than as an anchor to nowhere.' );
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

		$this->assertNotNull( $evil, 'The description is rendered, or the texturize assertion below is vacuous.' );
		$this->assertStringContainsString( '&#8220;quotes&#8221;', $evil['Description'], 'The description is texturised, so quotes render as the site writes them.' );
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
		$this->assertStringContainsString( 'role="img"', $active, 'The icon is exposed as an image to assistive technology.' );
		$this->assertStringContainsString( 'aria-label="Active plugin"', $active, 'And labelled, so the state is not conveyed by colour alone.' );
		$this->assertStringContainsString( 'aria-label="Inactive plugin"', $inactive, 'The inactive icon carries its own label.' );

		// ...and distinguished by shape, not just hue.
		$this->assertStringContainsString( '<path', $active, 'The active icon is drawn as a path.' );
		$this->assertStringContainsString( '<circle', $inactive, 'And the inactive one as a circle, so they differ in shape as well as colour.' );
	}

	public function test_icons_inherit_the_theme_colour() {
		$this->assertStringContainsString( 'currentColor', WP_PluginsUsed_Template::render( 'active' ), 'The active icon inherits the theme colour rather than hardcoding one.' );
		$this->assertStringContainsString( 'currentColor', WP_PluginsUsed_Template::render( 'inactive' ), 'And the inactive one.' );
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
	 * The allow-list display_pluginsused() escapes through must cover every tag
	 * and attribute the listings actually emit. If it does not, the template tag
	 * silently prints less than the shortcodes do, which is the kind of
	 * difference nobody notices until a theme reports half a listing missing.
	 */
	public function test_the_kses_allow_list_matches_the_markup_exactly() {
		$allowed = WP_PluginsUsed_Template::allowed_html();

		foreach ( array( 'stats', 'active', 'inactive' ) as $type ) {
			$markup = WP_PluginsUsed_Template::render( $type );

			// Compared as inventories of tags and attribute names, not byte for
			// byte. wp_kses() normalises as well as filtering -- it rewrites
			// &#039; to &apos; inside an attribute value, for one -- so demanding
			// identical bytes would fail on transformations that lose nothing.
			// What an incomplete allow list actually does is DROP a tag or an
			// attribute, and that is what this compares.
			$this->assertSame(
				$this->inventory( $markup ),
				$this->inventory( wp_kses( $markup, $allowed ) ),
				"wp_kses() dropped part of the {$type} listing, so allowed_html() no longer covers what render() emits."
			);
		}
	}

	/**
	 * Every tag and attribute name in some markup, sorted and de-duplicated.
	 *
	 * @param string $markup Markup to inspect.
	 * @return string[] e.g. array( 'a', 'a@href', 'svg', 'svg@viewbox' ).
	 */
	private function inventory( $markup ) {
		$found = array();

		if ( preg_match_all( '#<([a-z0-9]+)((?:\s+[a-z-]+(?:="[^"]*")?)*)#i', $markup, $tags, PREG_SET_ORDER ) ) {
			foreach ( $tags as $tag ) {
				$name           = strtolower( $tag[1] );
				$found[ $name ] = true;

				if ( preg_match_all( '#([a-z-]+)=#i', $tag[2], $attrs ) ) {
					foreach ( $attrs[1] as $attr ) {
						$found[ $name . '@' . strtolower( $attr ) ] = true;
					}
				}
			}
		}

		$found = array_keys( $found );
		sort( $found );

		return $found;
	}

	public function test_the_template_tag_echoes_what_the_shortcode_returns() {
		ob_start();
		display_pluginsused( 'active', true );
		$echoed = ob_get_clean();

		// The echoing form is the returning form put through the allow list, so
		// that is what it is compared against. Comparing it to raw render()
		// output would be asserting that wp_kses() normalises nothing, which it
		// does not -- see inventory() above.
		$this->assertSame(
			wp_kses( WP_PluginsUsed_Template::render( 'active' ), WP_PluginsUsed_Template::allowed_html() ),
			$echoed,
			'Echoing must not lose anything the returning form keeps.'
		);
	}

	/**
	 * The plugin ships no stylesheet, so nothing may depend on one.
	 */
	public function test_no_stylesheet_or_script_is_enqueued() {
		do_action( 'wp_enqueue_scripts' );

		$this->assertFalse( wp_style_is( 'wp-pluginsused', 'enqueued' ), 'This plugin enqueues no stylesheet of its own.' );
		$this->assertFalse( wp_script_is( 'wp-pluginsused', 'enqueued' ), 'This plugin enqueues no script of its own.' );
		$this->assertFalse( wp_script_is( 'jquery', 'enqueued' ), 'This plugin does not drag jQuery onto the page.' );
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
