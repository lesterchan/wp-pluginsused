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
class WP_PluginsUsed_Summary_Test extends WP_PluginsUsed_TestCase {

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

	/**
	 * The three counts are joined by a translatable string, not by PHP.
	 *
	 * The join used to be a bare "and" concatenated between the parts, with
	 * the word order and the full stop living in the source. A translator
	 * handed only "and" cannot move the inactive count in front of the active
	 * one, and several languages want to -- so the test reorders the join and
	 * asserts the sentence follows.
	 */
	public function test_the_summary_word_order_comes_from_the_translation() {
		$reorder = static function ( $translation, $text, $domain ) {
			if ( 'wp-pluginsused' === $domain && '%1$s %2$s and %3$s.' === $text ) {
				return '%1$s %3$s, then %2$s!';
			}

			return $translation;
		};

		add_filter( 'gettext', $reorder, 10, 3 );
		$stats = $this->stats_for( 2, 3 );
		remove_filter( 'gettext', $reorder, 10 );

		$this->assertStringContainsString(
			'<strong>3 inactive plugins</strong>, then <strong>2 active plugins</strong>!',
			$stats,
			'The translated join decides the order of the counts and the punctuation.'
		);
	}

	public function test_one_plugin_uses_the_singular_form() {
		$stats = $this->stats_for( 1, 0 );

		$this->assertStringContainsString( 'There is <strong>1</strong> plugin used:', $stats, 'One plugin takes the singular in the total.' );
		$this->assertStringContainsString( '<strong>1 active plugin</strong>', $stats, 'And in the active count.' );
		$this->assertStringNotContainsString( '1 active plugins', $stats, 'With no plural left over, which is what a naive count produces.' );
	}

	public function test_one_inactive_plugin_uses_the_singular_form() {
		$this->assertStringContainsString( '<strong>1 inactive plugin</strong>.', $this->stats_for( 0, 1 ), 'One inactive plugin takes the singular too.' );
	}

	public function test_several_plugins_use_the_plural_form() {
		$stats = $this->stats_for( 2, 3 );

		$this->assertStringContainsString( 'There are <strong>5</strong> plugins used:', $stats, 'More than one takes the plural in the total.' );
		$this->assertStringContainsString( '<strong>2 active plugins</strong>', $stats, 'And in the active count.' );
		$this->assertStringContainsString( '<strong>3 inactive plugins</strong>.', $stats, 'And the inactive count.' );
	}

	public function test_zero_plugins_renders_without_error() {
		$stats = $this->stats_for( 0, 0 );

		$this->assertStringContainsString( '<strong>0</strong>', $stats, 'Zero renders rather than being treated as nothing to say.' );
		$this->assertStringContainsString( '<strong>0 active plugins</strong>', $stats, 'Zero takes the plural in the active count.' );
		$this->assertStringContainsString( '<strong>0 inactive plugins</strong>.', $stats, 'And the inactive count.' );
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

		$this->assertSame( '', WP_PluginsUsed_Template::render( 'active' ), 'An empty active listing renders an empty string, not an empty wrapper.' );
		$this->assertSame( '', WP_PluginsUsed_Template::render( 'inactive' ), 'And an empty inactive listing.' );

		remove_filter( 'wp_pluginsused_plugins_used', $callback );
	}

	/**
	 * The counts are formatted for the locale, so a four-figure install must
	 * not print a bare integer.
	 */
	public function test_counts_are_formatted_for_the_locale() {
		$stats = $this->stats_for( 1500, 0 );

		$this->assertStringContainsString( '<strong>' . number_format_i18n( 1500 ) . '</strong>', $stats, 'The count is localised, so a large number reads as the site would write it.' );
	}

	/**
	 * The <strong> tags belong to the source strings; nothing interpolated
	 * into them may introduce further markup.
	 */
	public function test_stats_markup_is_well_formed() {
		$parsed = $this->parse_html( $this->stats_for( 2, 3 ) );

		$this->assertSame( array(), $parsed['errors'], 'The stats markup parses without error.' );
	}
}
