<?php
/**
 * Output escaping.
 *
 * Plugin headers are attacker-controlled for anyone who can place a file in
 * wp-content/plugins. Before 2.0.0 the only filtering applied was strip_tags(),
 * which removes tags but leaves quotes, so a header could break out of an
 * attribute and become a live event handler.
 *
 * @package WP-PluginsUsed
 */

/**
 * @covers WP_PluginsUsed_Template::format
 */
class WP_PluginsUsed_Escaping_Test extends WP_PluginsUsed_TestCase {

	/**
	 * Both listings concatenated.
	 *
	 * @return string
	 */
	protected function markup() {
		return WP_PluginsUsed_Template::render( 'active' ) . WP_PluginsUsed_Template::render( 'inactive' );
	}

	public function test_no_event_handler_attribute_reaches_the_dom() {
		$found = $this->find_injections( $this->markup() );

		$this->assertSame(
			array(),
			$found['handlers'],
			'A quote in a plugin header must not become an attribute.'
		);
	}

	public function test_no_javascript_url_reaches_the_dom() {
		$found = $this->find_injections( $this->markup() );

		$this->assertSame( array(), $found['schemes'] );
	}

	public function test_markup_is_well_formed() {
		$parsed = $this->parse_html( $this->markup() );

		$this->assertSame(
			array(),
			array_map(
				static function ( $error ) {
					return trim( $error->message );
				},
				$parsed['errors']
			)
		);
	}

	/**
	 * Escaping, not silent dropping: the hostile name must still be shown.
	 */
	public function test_hostile_name_is_displayed_as_literal_text() {
		$parsed = $this->parse_html( $this->markup() );

		$this->assertStringContainsString( 'Evil" onmouseover="alert(1)', $parsed['doc']->textContent );
	}

	public function test_hostile_author_is_displayed_as_literal_text() {
		$parsed = $this->parse_html( $this->markup() );

		$this->assertStringContainsString( 'Bad" autofocus="x', $parsed['doc']->textContent );
	}

	public function test_ampersand_in_description_round_trips() {
		$parsed = $this->parse_html( $this->markup() );

		$this->assertStringContainsString( 'Desc with & ampersand', $parsed['doc']->textContent );
	}

	/**
	 * An entity that arrives already encoded must not gain a second layer:
	 * esc_attr() passes $double_encode = false precisely so it does not.
	 */
	public function test_entities_are_not_double_encoded() {
		$this->assertStringNotContainsString( '&amp;amp;', $this->markup() );
		$this->assertStringNotContainsString( '&amp;quot;', $this->markup() );
	}

	public function test_a_script_tag_in_a_header_is_stripped() {
		$this->create_plugin_fixture(
			'zzz-script',
			array(
				'Plugin Name' => 'Script <script>alert(1)</script> Plugin',
				'Version'     => '1.0',
			)
		);
		$this->reset_plugin_state();

		$markup = $this->markup();

		$this->delete_plugin_fixture( 'zzz-script' );

		$this->assertStringNotContainsString( '<script', $markup, 'A tag in a plugin header must never reach the page.' );
		$this->assertStringContainsString( 'alert(1)', $markup, 'The text is kept; only the tag goes.' );
	}
}
