<?php
/**
 * Shortcodes and template tags -- the plugin's public API.
 *
 * @package WP-PluginsUsed
 */

/**
 * @covers WP_PluginsUsed
 * @covers ::display_pluginsused
 */
class Test_PluginsUsed_Shortcodes extends WP_PluginsUsed_TestCase {

	public function test_all_three_shortcodes_are_registered() {
		$this->assertTrue( shortcode_exists( 'stats_pluginsused' ) );
		$this->assertTrue( shortcode_exists( 'active_pluginsused' ) );
		$this->assertTrue( shortcode_exists( 'inactive_pluginsused' ) );
	}

	public function test_shortcodes_match_the_template_output() {
		$this->assertSame( WP_PluginsUsed_Template::render( 'stats' ), do_shortcode( '[stats_pluginsused]' ) );
		$this->assertSame( WP_PluginsUsed_Template::render( 'active' ), do_shortcode( '[active_pluginsused]' ) );
		$this->assertSame( WP_PluginsUsed_Template::render( 'inactive' ), do_shortcode( '[inactive_pluginsused]' ) );
	}

	public function test_shortcodes_render_inside_post_content() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '[stats_pluginsused]',
			)
		);

		$rendered = apply_filters( 'the_content', get_post( $post_id )->post_content );

		$this->assertStringContainsString( 'plugins used', $rendered );
	}

	public function test_template_tag_returns_markup_by_default() {
		$this->assertStringContainsString( 'plugins used', display_pluginsused( 'stats' ) );
	}

	public function test_template_tag_echoes_when_asked() {
		ob_start();
		$returned = display_pluginsused( 'stats', true );
		$echoed   = ob_get_clean();

		$this->assertStringContainsString( 'plugins used', $echoed );
		$this->assertNull( $returned, 'Echo mode must not also return the markup.' );
	}

	public function test_template_tag_does_not_echo_by_default() {
		ob_start();
		display_pluginsused( 'stats' );
		$echoed = ob_get_clean();

		$this->assertSame( '', $echoed );
	}
}
