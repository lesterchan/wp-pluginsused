<?php
/**
 * The summary sentence.
 *
 * Its three strings are pluralised independently, so the singular, plural and
 * zero forms are three different code paths through _n().
 *
 * @package WP-PluginsUsed
 */

/**
 * @covers WP_PluginsUsed_Template::render
 */
class Test_PluginsUsed_Stats extends WP_PluginsUsed_TestCase {

	/**
	 * Force the listing to an exact shape, bypassing what is on disk.
	 *
	 * @param int $active   Number of active entries.
	 * @param int $inactive Number of inactive entries.
	 * @return string The rendered stats sentence.
	 */
	protected function stats_for( $active, $inactive ) {
		$entry = array(
			'Plugin_Name' => 'X',
			'Plugin_URI'  => '',
			'Description' => '',
			'Author'      => '',
			'Author_URI'  => '',
			'Version'     => '',
		);

		$callback = static function () use ( $active, $inactive, $entry ) {
			return array(
				'active'   => array_fill( 0, $active, $entry ),
				'inactive' => array_fill( 0, $inactive, $entry ),
			);
		};

		add_filter( 'wp_pluginsused_plugins_used', $callback );
		WP_PluginsUsed_Template::reset_cache();

		$stats = WP_PluginsUsed_Template::render( 'stats' );

		remove_filter( 'wp_pluginsused_plugins_used', $callback );
		WP_PluginsUsed_Template::reset_cache();

		return $stats;
	}

	public function test_one_plugin_uses_the_singular_form() {
		$stats = $this->stats_for( 1, 0 );

		$this->assertStringContainsString( 'There is <strong>1</strong> plugin used:', $stats );
		$this->assertStringContainsString( '<strong>1 active plugin</strong>', $stats );
		$this->assertStringNotContainsString( '1 active plugins', $stats );
	}

	public function test_one_inactive_plugin_uses_the_singular_form() {
		$this->assertStringContainsString( '<strong>1 inactive plugin</strong>.', $this->stats_for( 0, 1 ) );
	}

	public function test_several_plugins_use_the_plural_form() {
		$stats = $this->stats_for( 2, 3 );

		$this->assertStringContainsString( 'There are <strong>5</strong> plugins used:', $stats );
		$this->assertStringContainsString( '<strong>2 active plugins</strong>', $stats );
		$this->assertStringContainsString( '<strong>3 inactive plugins</strong>.', $stats );
	}

	public function test_zero_plugins_renders_without_error() {
		$stats = $this->stats_for( 0, 0 );

		$this->assertStringContainsString( '<strong>0</strong>', $stats );
		$this->assertStringContainsString( '<strong>0 active plugins</strong>', $stats );
		$this->assertStringContainsString( '<strong>0 inactive plugins</strong>.', $stats );
	}

	public function test_empty_listings_render_as_empty_strings() {
		$callback = static function () {
			return array(
				'active'   => array(),
				'inactive' => array(),
			);
		};

		add_filter( 'wp_pluginsused_plugins_used', $callback );
		WP_PluginsUsed_Template::reset_cache();

		$this->assertSame( '', WP_PluginsUsed_Template::render( 'active' ) );
		$this->assertSame( '', WP_PluginsUsed_Template::render( 'inactive' ) );

		remove_filter( 'wp_pluginsused_plugins_used', $callback );
	}

	/**
	 * The counts are formatted for the locale, so a four-figure install must
	 * not print a bare integer.
	 */
	public function test_counts_are_formatted_for_the_locale() {
		$stats = $this->stats_for( 1500, 0 );

		$this->assertStringContainsString( '<strong>' . number_format_i18n( 1500 ) . '</strong>', $stats );
	}

	/**
	 * The <strong> tags belong to the source strings; nothing interpolated
	 * into them may introduce further markup.
	 */
	public function test_stats_markup_is_well_formed() {
		$parsed = $this->parse_html( $this->stats_for( 2, 3 ) );

		$this->assertSame( array(), $parsed['errors'] );
	}
}
