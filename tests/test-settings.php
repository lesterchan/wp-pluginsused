<?php
/**
 * The Settings API screen.
 *
 * @package WP-PluginsUsed
 */

/**
 * @covers WP_PluginsUsed_Settings
 */
class WP_PluginsUsed_Settings_Test extends WP_PluginsUsed_TestCase {

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		/*
		 * The plugin's own callbacks, invoked directly rather than by firing
		 * admin_menu/admin_init. Firing those runs every core handler too --
		 * wp_update_plugins() reaching for WordPress.org, and header sends the
		 * CLI cannot make -- none of which is what this file is testing.
		 */
		WP_PluginsUsed_Settings::init();
		WP_PluginsUsed_Settings::add_page();
		WP_PluginsUsed_Settings::register_settings();
	}

	public function test_page_is_registered_under_settings() {
		global $submenu;

		$slugs = wp_list_pluck( $submenu['options-general.php'], 2 );

		$this->assertContains( 'wp-pluginsused', $slugs, 'The screen is registered under Settings, per the menu rule.' );
	}

	public function test_setting_is_registered_with_the_options_sanitizer() {
		$registered = get_registered_settings();

		$this->assertArrayHasKey( 'wp_pluginsused_options', $registered, 'The settings row is registered, so its sanitise callback is attached.' );
		$this->assertSame(
			array( 'WP_PluginsUsed_Options', 'sanitize' ),
			$registered['wp_pluginsused_options']['sanitize_callback'],
			'The registration names the options sanitiser, so a save cannot bypass it.'
		);
	}

	public function test_both_fields_are_registered_in_their_own_sections() {
		global $wp_settings_fields;

		$this->assertArrayHasKey(
			'wp_pluginsused_show_version',
			$wp_settings_fields['wp-pluginsused'][ WP_PluginsUsed_Settings::SECTION_DISPLAY ],
			'The version-number checkbox belongs to the display section.'
		);
		$this->assertArrayHasKey(
			'wp_pluginsused_hidden_plugins',
			$wp_settings_fields['wp-pluginsused'][ WP_PluginsUsed_Settings::SECTION_HIDDEN ],
			'The hidden-plugins list belongs to the hidden section.'
		);
	}

	public function test_the_sections_are_named_after_the_plugin() {
		$this->assertSame( 'wp_pluginsused_display', WP_PluginsUsed_Settings::SECTION_DISPLAY, 'The display section is named after the plugin.' );
		$this->assertSame( 'wp_pluginsused_hidden', WP_PluginsUsed_Settings::SECTION_HIDDEN, 'And the hidden section, so no other plugin can claim either.' );
	}

	/**
	 * One filter decides who may reach the screen, so a site can hand it to
	 * somebody other than an administrator in one place.
	 */
	public function test_the_capability_runs_through_the_filter() {
		$this->assertSame( 'manage_options', WP_PluginsUsed_Settings::capability(), 'The capability comes through the filter, which is what makes it overridable.' );

		$callback = static function () {
			return 'edit_posts';
		};

		add_filter( 'wp_pluginsused_capability', $callback );
		$capability = WP_PluginsUsed_Settings::capability();
		remove_filter( 'wp_pluginsused_capability', $callback );

		$this->assertSame( 'edit_posts', $capability, 'The filter must decide the capability.' );
	}

	public function test_the_screen_carries_no_inline_style_attribute() {
		$this->assertStringNotContainsString(
			'style=',
			$this->render(),
			'Core classes only: no admin markup in this collection carries inline CSS.'
		);
	}

	public function test_the_form_table_comes_from_the_settings_api() {
		$this->assertStringNotContainsString(
			'<table',
			wp_pluginsused_test_source_code(),
			'do_settings_sections() emits the form table; no screen hand-writes one.'
		);
	}

	/**
	 * Render the screen.
	 *
	 * @return string
	 */
	protected function render() {
		ob_start();
		WP_PluginsUsed_Settings::render_page();

		return ob_get_clean();
	}

	public function test_form_posts_to_options_php_with_a_nonce() {
		$html = $this->render();

		$this->assertStringContainsString( 'action="options.php"', $html, 'The form must post to the Settings API endpoint.' );
		// settings_fields() quotes option_page with single quotes, so the needle
		// is quote-agnostic rather than assuming core's style, which is not part
		// of any contract.
		$this->assertMatchesRegularExpression(
			'/name=.option_page.\s+value=.wp_pluginsused_options./',
			$html,
			'settings_fields() must name the registered group.'
		);
		$this->assertStringContainsString( '_wpnonce', $html, 'settings_fields() must emit its nonce.' );
	}

	public function test_both_inputs_are_present() {
		$html = $this->render();

		$this->assertStringContainsString( 'name="wp_pluginsused_options[show_version]"', $html, 'The version toggle posts into the settings row.' );
		$this->assertStringContainsString( 'name="wp_pluginsused_options[hidden_plugins][]"', $html, 'And the hidden list posts as an array under its own name.' );
	}

	public function test_hostile_plugin_name_is_escaped_in_the_checkbox_value() {
		$html = $this->render();

		$this->assertStringContainsString( 'value="Evil&quot; onmouseover=&quot;alert(1)"', $html, 'A hostile plugin name is escaped in the checkbox value.' );

		$found = $this->find_injections( $html );
		$this->assertSame( array(), $found['handlers'], 'So it produces no event handler on the control.' );
	}

	public function test_render_is_capability_gated() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( '', $this->render(), 'A reader without the capability gets nothing at all, not a partial screen.' );
	}

	/**
	 * A plain update_option() takes exactly the path options.php takes on
	 * submit, because register_setting() installs a sanitize_option_{$option}
	 * filter that runs either way.
	 */
	public function test_save_round_trip_runs_the_sanitizer() {
		update_option(
			'wp_pluginsused_options',
			array(
				'show_version'   => '1',
				'hidden_plugins' => array( 'beta Test Plugin', 'beta Test Plugin' ),
			)
		);

		$stored = get_option( 'wp_pluginsused_options' );

		$this->assertTrue( $stored['show_version'], 'The saved form round-trips through the sanitiser to the stored row.' );
		$this->assertSame( array( 'beta Test Plugin' ), $stored['hidden_plugins'], 'The saved form round-trips through the sanitiser to the stored row.' );
	}

	public function test_saved_setting_takes_effect_in_the_listing() {
		update_option(
			'wp_pluginsused_options',
			array(
				'show_version'   => '1',
				'hidden_plugins' => array( 'beta Test Plugin' ),
			)
		);
		WP_PluginsUsed_Template::reset_cache();

		$this->assertStringNotContainsString( 'beta Test Plugin', WP_PluginsUsed_Template::render( 'inactive' ), 'And the stored setting takes effect in the listing, which is the far end.' );
	}

	public function test_submitting_an_empty_form_clears_the_settings() {
		update_option(
			'wp_pluginsused_options',
			array(
				'show_version'   => '1',
				'hidden_plugins' => array( 'beta Test Plugin' ),
			)
		);

		update_option( 'wp_pluginsused_options', array() );

		$stored = get_option( 'wp_pluginsused_options' );

		$this->assertFalse( $stored['show_version'], 'An empty form clears the setting rather than leaving the old value.' );
		$this->assertSame( array(), $stored['hidden_plugins'], 'An empty form clears the list rather than leaving the old values.' );
	}

	/**
	 * The checkbox values are plugin names, so a name containing quotes has to
	 * survive the escape-on-render / unescape-on-submit trip unchanged, or it
	 * will never match the name it is supposed to hide.
	 */
	public function test_a_hostile_plugin_name_round_trips_and_still_hides() {
		$name = 'Evil" onmouseover="alert(1)';

		update_option(
			'wp_pluginsused_options',
			array(
				'show_version'   => '1',
				'hidden_plugins' => array( $name ),
			)
		);

		$this->assertSame( array( $name ), get_option( 'wp_pluginsused_options' )['hidden_plugins'], 'A hostile name round-trips into storage as the name that was posted.' );

		WP_PluginsUsed_Template::reset_cache();
		$markup = WP_PluginsUsed_Template::render( 'active' ) . WP_PluginsUsed_Template::render( 'inactive' );
		$parsed = $this->parse_html( $markup );

		$this->assertStringNotContainsString( 'Evil" onmouseover="alert(1)', $parsed['doc']->textContent, 'And still hides the plugin, so the escaping did not break the match.' );
	}

	public function test_an_unknown_setting_key_is_discarded_on_save() {
		update_option(
			'wp_pluginsused_options',
			array(
				'show_version' => '1',
				'evil_key'     => 'payload',
			)
		);

		$this->assertArrayNotHasKey( 'evil_key', get_option( 'wp_pluginsused_options' ), 'A key the sanitiser does not know is discarded on save.' );
	}

	public function test_the_section_lists_every_installed_plugin() {
		$html = $this->render();

		foreach ( array( 'Alpha Test Plugin', 'beta Test Plugin', 'Hidden Test Plugin' ) as $name ) {
			$this->assertStringContainsString( 'value="' . esc_attr( $name ) . '"', $html, $name . ' is installed but missing from the section.' );
		}
	}

	public function test_a_ticked_plugin_renders_as_checked() {
		update_option( 'wp_pluginsused_options', array( 'hidden_plugins' => array( 'Alpha Test Plugin' ) ) );

		$html = $this->render();

		$this->assertMatchesRegularExpression(
			'/value="Alpha Test Plugin"\s+checked/',
			$html,
			'A ticked plugin renders with its checkbox checked.'
		);
	}

	/**
	 * Nothing on the screen may point a site owner at an identifier 2.0.0
	 * dropped: the checkbox is the whole story for version numbers, and the
	 * hidden-plugins hint names the prefixed filter.
	 */
	public function test_the_screen_names_no_dropped_identifier() {
		$html = $this->render();

		$this->assertStringNotContainsString( 'PLUGINSUSED_SHOW_VERSION', $html, 'The constant is gone.' );
		$this->assertStringNotContainsString( '$pluginsused_hidden_plugins', $html, 'So is the global.' );
		$this->assertStringContainsString(
			'wp_pluginsused_hidden_plugins',
			$html,
			'The surviving filter is named in full so it can be copied off the screen.'
		);
	}

	public function test_settings_page_markup_is_well_formed() {
		$parsed = $this->parse_html( $this->render() );

		$this->assertSame( array(), $parsed['errors'], 'The settings screen markup parses without error.' );
	}

	public function test_a_settings_link_is_added_to_the_plugins_row() {
		$links = apply_filters(
			'plugin_action_links_' . plugin_basename( WP_PLUGINSUSED_MAIN_FILE ),
			array( 'deactivate' => 'x' )
		);

		$this->assertStringContainsString( 'page=wp-pluginsused', $links[0], 'The Settings link on the plugins row points at this screen.' );
	}
}
