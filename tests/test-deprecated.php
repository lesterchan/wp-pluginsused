<?php
/**
 * The pre-2.0.0 procedural functions.
 *
 * They lived in the global namespace, so a theme may be calling them. They must
 * keep working, and they must warn under WP_DEBUG.
 *
 * @package WP-PluginsUsed
 */

/**
 * @coversNothing
 */
class Test_PluginsUsed_Deprecated extends PluginsUsed_TestCase {

	public function tear_down() {
		unset( $GLOBALS['wp_plugins'], $GLOBALS['plugins_used'] );

		parent::tear_down();
	}

	public function test_get_pluginsused_data_returns_the_legacy_field_shape() {
		$this->setExpectedDeprecated( 'get_pluginsused_data' );

		$data = get_pluginsused_data( WP_PLUGIN_DIR . '/zzz-alpha/zzz-alpha.php' );

		$this->assertSame( 'Alpha Test Plugin', $data['Plugin_Name'] );
		$this->assertSame( 'https://example.com/alpha', $data['Plugin_URI'] );
		$this->assertSame( 'Alpha Author', $data['Author'] );
		$this->assertSame( 'https://example.com/authora', $data['Author_URI'] );
		$this->assertSame( '1.2.3', $data['Version'] );
		$this->assertSame(
			array( 'Plugin_Name', 'Plugin_URI', 'Description', 'Author', 'Author_URI', 'Version' ),
			array_keys( $data )
		);
	}

	public function test_get_pluginsused_is_keyed_by_plugin_file() {
		$this->setExpectedDeprecated( 'get_pluginsused' );

		$plugins = get_pluginsused();

		$this->assertArrayHasKey( 'zzz-alpha/zzz-alpha.php', $plugins );
		$this->assertSame( 'Alpha Test Plugin', $plugins['zzz-alpha/zzz-alpha.php']['Plugin_Name'] );
	}

	public function test_pluginsused_sort_orders_by_name() {
		$this->setExpectedDeprecated( 'pluginsused_sort' );

		$this->assertLessThan(
			0,
			pluginsused_sort( array( 'Plugin_Name' => 'Alpha' ), array( 'Plugin_Name' => 'beta' ) )
		);
	}

	public function test_process_pluginsused_populates_the_legacy_global() {
		$this->setExpectedDeprecated( 'process_pluginsused' );

		process_pluginsused();

		$this->assertArrayHasKey( 'active', $GLOBALS['plugins_used'] );
		$this->assertArrayHasKey( 'inactive', $GLOBALS['plugins_used'] );
	}

	public function test_pluginsused_format_display_still_renders_an_entry() {
		$this->setExpectedDeprecated( 'pluginsused_format_display' );

		$html = pluginsused_format_display(
			array(
				'Plugin_Name' => 'Alpha Test Plugin',
				'Plugin_URI'  => 'https://example.com/alpha',
				'Description' => 'A plain description.',
				'Author'      => 'Alpha Author',
				'Author_URI'  => 'https://example.com/authora',
				'Version'     => '1.2.3',
			)
		);

		$this->assertStringContainsString( 'Alpha Test Plugin 1.2.3', $html );
		$this->assertStringContainsString( 'pluginsused-icon-active', $html );
	}

	public function test_pluginsused_format_display_escapes_its_input() {
		$this->setExpectedDeprecated( 'pluginsused_format_display' );

		$html = pluginsused_format_display(
			array(
				'Plugin_Name' => 'Evil" onmouseover="alert(1)',
				'Plugin_URI'  => 'javascript:alert(2)',
				'Description' => 'x',
				'Author'      => 'y',
				'Author_URI'  => '',
				'Version'     => '1.0',
			)
		);

		$found = $this->find_injections( $html );

		$this->assertSame( array(), $found['handlers'] );
		$this->assertSame( array(), $found['schemes'] );
	}
}
