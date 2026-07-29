<?php
/**
 * The Settings API screen.
 *
 * @package WP-PluginsUsed
 */

/**
 * @covers PluginsUsed_Settings
 */
class Test_PluginsUsed_Settings extends PluginsUsed_TestCase {

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
		PluginsUsed_Settings::init();
		PluginsUsed_Settings::add_page();
		PluginsUsed_Settings::register();
	}

	public function test_page_is_registered_under_settings() {
		global $submenu;

		$slugs = wp_list_pluck( $submenu['options-general.php'], 2 );

		$this->assertContains( 'wp-pluginsused', $slugs );
	}

	public function test_setting_is_registered_with_the_options_sanitizer() {
		$registered = get_registered_settings();

		$this->assertArrayHasKey( 'pluginsused_options', $registered );
		$this->assertSame(
			array( 'PluginsUsed_Options', 'sanitize' ),
			$registered['pluginsused_options']['sanitize_callback']
		);
	}

	public function test_both_fields_are_registered() {
		global $wp_settings_fields;

		$this->assertArrayHasKey( 'pluginsused_show_version', $wp_settings_fields['wp-pluginsused']['pluginsused_display'] );
		$this->assertArrayHasKey( 'pluginsused_hidden_plugins', $wp_settings_fields['wp-pluginsused']['pluginsused_hidden'] );
	}

	/**
	 * Render the screen.
	 *
	 * @return string
	 */
	protected function render() {
		ob_start();
		PluginsUsed_Settings::render();

		return ob_get_clean();
	}

	public function test_form_posts_to_options_php_with_a_nonce() {
		$html = $this->render();

		$this->assertStringContainsString( 'action="options.php"', $html );
		$this->assertStringContainsString( 'pluginsused_options_group', $html );
		$this->assertStringContainsString( '_wpnonce', $html );
	}

	public function test_both_inputs_are_present() {
		$html = $this->render();

		$this->assertStringContainsString( 'name="pluginsused_options[show_version]"', $html );
		$this->assertStringContainsString( 'name="pluginsused_options[hidden_plugins][]"', $html );
	}

	public function test_hostile_plugin_name_is_escaped_in_the_checkbox_value() {
		$html = $this->render();

		$this->assertStringContainsString( 'value="Evil&quot; onmouseover=&quot;alert(1)"', $html );

		$found = $this->find_injections( $html );
		$this->assertSame( array(), $found['handlers'] );
	}

	public function test_render_is_capability_gated() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( '', $this->render() );
	}

	/**
	 * A plain update_option() takes exactly the path options.php takes on
	 * submit, because register_setting() installs a sanitize_option_{$option}
	 * filter that runs either way.
	 */
	public function test_save_round_trip_runs_the_sanitizer() {
		update_option(
			'pluginsused_options',
			array(
				'show_version'   => '1',
				'hidden_plugins' => array( 'beta Test Plugin', 'beta Test Plugin' ),
			)
		);

		$stored = get_option( 'pluginsused_options' );

		$this->assertTrue( $stored['show_version'] );
		$this->assertSame( array( 'beta Test Plugin' ), $stored['hidden_plugins'] );
	}

	public function test_saved_setting_takes_effect_in_the_listing() {
		update_option(
			'pluginsused_options',
			array(
				'show_version'   => '1',
				'hidden_plugins' => array( 'beta Test Plugin' ),
			)
		);
		PluginsUsed_Template::reset_cache();

		$this->assertStringNotContainsString( 'beta Test Plugin', PluginsUsed_Template::render( 'inactive' ) );
	}

	public function test_submitting_an_empty_form_clears_the_settings() {
		update_option(
			'pluginsused_options',
			array(
				'show_version'   => '1',
				'hidden_plugins' => array( 'beta Test Plugin' ),
			)
		);

		update_option( 'pluginsused_options', array() );

		$stored = get_option( 'pluginsused_options' );

		$this->assertFalse( $stored['show_version'] );
		$this->assertSame( array(), $stored['hidden_plugins'] );
	}

	/**
	 * The checkbox values are plugin names, so a name containing quotes has to
	 * survive the escape-on-render / unescape-on-submit trip unchanged, or it
	 * will never match the name it is supposed to hide.
	 */
	public function test_a_hostile_plugin_name_round_trips_and_still_hides() {
		$name = 'Evil" onmouseover="alert(1)';

		update_option(
			'pluginsused_options',
			array(
				'show_version'   => '1',
				'hidden_plugins' => array( $name ),
			)
		);

		$this->assertSame( array( $name ), get_option( 'pluginsused_options' )['hidden_plugins'] );

		PluginsUsed_Template::reset_cache();
		$markup = PluginsUsed_Template::render( 'active' ) . PluginsUsed_Template::render( 'inactive' );
		$parsed = $this->parse_html( $markup );

		$this->assertStringNotContainsString( 'Evil" onmouseover="alert(1)', $parsed['doc']->textContent );
	}

	public function test_an_unknown_setting_key_is_discarded_on_save() {
		update_option(
			'pluginsused_options',
			array(
				'show_version' => '1',
				'evil_key'     => 'payload',
			)
		);

		$this->assertArrayNotHasKey( 'evil_key', get_option( 'pluginsused_options' ) );
	}

	public function test_the_section_lists_every_installed_plugin() {
		$html = $this->render();

		foreach ( array( 'Alpha Test Plugin', 'beta Test Plugin', 'Hidden Test Plugin' ) as $name ) {
			$this->assertStringContainsString( 'value="' . esc_attr( $name ) . '"', $html );
		}
	}

	public function test_a_ticked_plugin_renders_as_checked() {
		update_option( 'pluginsused_options', array( 'hidden_plugins' => array( 'Alpha Test Plugin' ) ) );

		$html = $this->render();

		$this->assertMatchesRegularExpression(
			'/value="Alpha Test Plugin"\s+checked/',
			$html
		);
	}

	public function test_the_screen_warns_when_the_legacy_constant_overrides_it() {
		ob_start();
		PluginsUsed_Settings::field_show_version();
		$html = ob_get_clean();

		if ( defined( 'PLUGINSUSED_SHOW_VERSION' ) ) {
			$this->assertStringContainsString( 'PLUGINSUSED_SHOW_VERSION', $html );
		} else {
			$this->assertStringNotContainsString( 'PLUGINSUSED_SHOW_VERSION', $html );
		}
	}

	public function test_settings_page_markup_is_well_formed() {
		$parsed = $this->parse_html( $this->render() );

		$this->assertSame( array(), $parsed['errors'] );
	}

	public function test_a_settings_link_is_added_to_the_plugins_row() {
		$links = apply_filters(
			'plugin_action_links_' . plugin_basename( WP_PLUGINSUSED_MAIN_FILE ),
			array( 'deactivate' => 'x' )
		);

		$this->assertStringContainsString( 'page=wp-pluginsused', $links[0] );
	}
}
