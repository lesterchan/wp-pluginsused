<?php
/**
 * Tests for the blocks.
 *
 * @package WP-PluginsUsed
 */

/**
 * The three blocks, and the promise that they are an addition rather than a
 * replacement.
 *
 * Most of what is worth asserting here is not "the block renders" -- that is one
 * line -- but the four things a later change could quietly break:
 *
 * * the shortcodes still work, because they sit in published pages everywhere;
 * * the block and the shortcode render the *same* markup, because they are meant
 *   to share one renderer and nothing else checks that they still do;
 * * neither entry point is implemented in terms of the other, which is what
 *   stops the shortcode's parsing quirks leaking into the block;
 * * a plugin hidden on the settings screen stays hidden when the listing arrives
 *   through a block. That last one is the only assertion here that is about a
 *   disclosure rather than about tidiness: somebody ticks a plugin as hidden
 *   because they do not want the world knowing it is installed, and a block that
 *   read the setting differently would publish it.
 *
 * @covers WP_PluginsUsed_Blocks
 */
class WP_PluginsUsed_Blocks_Test extends WP_PluginsUsed_TestCase {

	/**
	 * Every block this plugin registers, in the order the class registers them.
	 *
	 * @var string[]
	 */
	private static $names = array(
		'wp-pluginsused/stats-pluginsused',
		'wp-pluginsused/active-pluginsused',
		'wp-pluginsused/inactive-pluginsused',
	);

	/**
	 * The shortcode table as it stood before a test edited it.
	 *
	 * @var array
	 */
	private $shortcodes;

	/**
	 * Snapshots the global state these tests deliberately break.
	 *
	 * Two tests below unregister a shortcode or a block on purpose, to prove
	 * neither entry point is implemented in terms of the other. Both registries
	 * are process-global and WP_UnitTestCase restores neither, so without this
	 * the first such test silently disarms every test that runs after it -- and
	 * they fail with `[active_pluginsused]` rendering as literal text, which
	 * reads as a broken shortcode rather than a leaky fixture.
	 */
	public function set_up() {
		parent::set_up();

		$this->shortcodes = $GLOBALS['shortcode_tags'];

		$this->restore_blocks();
	}

	/**
	 * Puts both registries back.
	 */
	public function tear_down() {
		$GLOBALS['shortcode_tags'] = $this->shortcodes;

		$this->restore_blocks();

		parent::tear_down();
	}

	/**
	 * Returns the block registry to exactly the three registered blocks.
	 *
	 * Unregisters before registering rather than registering conditionally: the
	 * plugin has already registered all three on `init` by the time any test
	 * runs, and registering a second time is a doing_it_wrong notice the suite
	 * fails on.
	 *
	 * @return void
	 */
	private function restore_blocks() {
		foreach ( self::$names as $name ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}

		WP_PluginsUsed_Blocks::register();
	}

	/**
	 * Hide a plugin the way the settings screen does, and drop the cache.
	 *
	 * The listing is gathered once per request and reused, so a setting written
	 * after something has already rendered would otherwise not be read at all --
	 * and the test would pass on stale output rather than on the setting.
	 *
	 * @param string[] $names Plugin Name headers to hide.
	 * @return void
	 */
	private function hide_plugins( array $names ) {
		update_option(
			WP_PluginsUsed_Options::OPTION,
			array(
				'show_version'   => true,
				'hidden_plugins' => $names,
			)
		);

		WP_PluginsUsed_Template::reset_cache();
	}

	// --- registration ----------------------------------------------------

	/**
	 * All three blocks register, under the prefixed names.
	 *
	 * The `wp-` prefix is deliberate and is the one place the naming rule for
	 * commands and namespaces does not carry: those drop it, because a collision
	 * there is survivable and visible. A block name is written into post_content
	 * and stays there for the life of the post, so a collision would render
	 * another plugin's block inside somebody's published pages.
	 *
	 * @return void
	 */
	public function test_all_three_blocks_register_under_the_prefixed_name() {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( self::$names as $name ) {
			$this->assertTrue( $registry->is_registered( $name ), $name . ' registers.' );
		}

		$this->assertFalse( $registry->is_registered( 'pluginsused/active-pluginsused' ), 'The unprefixed name is not also claimed.' );
	}

	/**
	 * The blocks are dynamic, so each carries a render callback.
	 *
	 * Without one a block saves its markup into post_content, and two things
	 * depend on it not doing that: the shortcode and the block can only share a
	 * renderer if the markup is decided at render time, and a plugin hidden
	 * after a page was published can only disappear from that page if the page
	 * holds no copy of the listing.
	 *
	 * @return void
	 */
	public function test_every_block_is_dynamic() {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( self::$names as $name ) {
			$this->assertIsCallable( $registry->get_registered( $name )->render_callback, $name . ' renders server-side.' );
		}
	}

	/**
	 * No block.json declares an attribute, because no shortcode takes one.
	 *
	 * This is what keeps the two entry points the same shape. An attribute on
	 * the block with no counterpart in the shortcode would be surface invented
	 * for one of them, and the pair would start to drift from there.
	 *
	 * Asserted against the built metadata rather than against the registered
	 * block type, because core adds attributes of its own -- `lock` and
	 * `metadata` -- to every block it registers, so the registry cannot answer
	 * "did this plugin declare one".
	 *
	 * @return void
	 */
	public function test_no_block_declares_an_attribute() {
		foreach ( self::$names as $name ) {
			$directory = substr( $name, strlen( 'wp-pluginsused/' ) );
			$metadata  = json_decode( file_get_contents( WP_PLUGINSUSED_DIR . 'build/' . $directory . '/block.json' ), true );

			$this->assertIsArray( $metadata, $name . ' has readable built metadata.' );
			$this->assertArrayNotHasKey( 'attributes', $metadata, $name . ' takes no attributes, as its shortcode takes none.' );
		}
	}

	// --- the shortcodes survive ------------------------------------------

	/**
	 * Adding the blocks did not unregister any shortcode.
	 *
	 * If this ever fails, the blocks have stopped being an addition and become a
	 * replacement, and every published page holding `[active_pluginsused]`
	 * renders literal text.
	 *
	 * @return void
	 */
	public function test_all_three_shortcodes_are_still_registered() {
		$this->assertTrue( shortcode_exists( 'stats_pluginsused' ), 'The summary shortcode survives its block.' );
		$this->assertTrue( shortcode_exists( 'active_pluginsused' ), 'And the active listing shortcode.' );
		$this->assertTrue( shortcode_exists( 'inactive_pluginsused' ), 'And the inactive one.' );
	}

	// --- the block and the shortcode agree -------------------------------

	/**
	 * The summary block and its shortcode render identically.
	 *
	 * This is the assertion the whole design rests on. Two entry points that
	 * merely both work can drift; two that produce byte-identical markup are
	 * demonstrably going through one renderer.
	 *
	 * @return void
	 */
	public function test_the_summary_block_and_shortcode_render_the_same_markup() {
		$block = WP_PluginsUsed_Blocks::render_stats();

		$this->assertStringContainsString( 'plugins used', $block, 'The block rendered the summary.' );
		$this->assertSame( do_shortcode( '[stats_pluginsused]' ), $block, 'And it is what the shortcode renders.' );
	}

	/**
	 * So do the active listing block and its shortcode.
	 *
	 * @return void
	 */
	public function test_the_active_block_and_shortcode_render_the_same_markup() {
		$block = WP_PluginsUsed_Blocks::render_active();

		$this->assertStringContainsString( 'Alpha Test Plugin', $block, 'The block rendered the active listing.' );
		$this->assertSame( do_shortcode( '[active_pluginsused]' ), $block, 'And it is what the shortcode renders.' );
	}

	/**
	 * And the inactive pair.
	 *
	 * @return void
	 */
	public function test_the_inactive_block_and_shortcode_render_the_same_markup() {
		$block = WP_PluginsUsed_Blocks::render_inactive();

		$this->assertStringContainsString( 'beta Test Plugin', $block, 'The block rendered the inactive listing.' );
		$this->assertSame( do_shortcode( '[inactive_pluginsused]' ), $block, 'And it is what the shortcode renders.' );
	}

	/**
	 * The two listing blocks are not each other.
	 *
	 * They differ only in the string they hand the shared renderer, which is
	 * exactly the kind of near-duplicate a later tidy-up collapses. If both ever
	 * end up passing the same string, every assertion above still passes and
	 * only this one notices.
	 *
	 * @return void
	 */
	public function test_the_active_and_inactive_blocks_render_different_listings() {
		$active   = WP_PluginsUsed_Blocks::render_active();
		$inactive = WP_PluginsUsed_Blocks::render_inactive();

		$this->assertStringContainsString( 'Alpha Test Plugin', $active, 'The active listing holds the active fixture.' );
		$this->assertStringNotContainsString( 'Alpha Test Plugin', $inactive, 'And the inactive listing does not.' );
		$this->assertStringContainsString( 'beta Test Plugin', $inactive, 'The inactive listing holds the inactive fixture.' );
		$this->assertStringNotContainsString( 'beta Test Plugin', $active, 'And the active listing does not.' );
	}

	// --- neither is implemented in terms of the other ---------------------

	/**
	 * The blocks do not render by running the shortcodes.
	 *
	 * Routing a block through do_shortcode() would break it outright the day
	 * anybody unregistered the shortcode. So: unregister all three, and assert
	 * the blocks carry on rendering.
	 *
	 * @return void
	 */
	public function test_the_blocks_render_with_the_shortcodes_unregistered() {
		remove_shortcode( 'stats_pluginsused' );
		remove_shortcode( 'active_pluginsused' );
		remove_shortcode( 'inactive_pluginsused' );

		$this->assertStringContainsString( 'plugins used', WP_PluginsUsed_Blocks::render_stats(), 'The summary block does not need the shortcode.' );
		$this->assertStringContainsString( 'Alpha Test Plugin', WP_PluginsUsed_Blocks::render_active(), 'Nor does the active listing block.' );
		$this->assertStringContainsString( 'beta Test Plugin', WP_PluginsUsed_Blocks::render_inactive(), 'Nor the inactive one.' );
	}

	/**
	 * The shortcodes do not render by running the blocks.
	 *
	 * The other direction of the same rule, and the one a later "tidy-up" is
	 * likelier to break, because making a shortcode a thin wrapper over a block
	 * reads as removing duplication.
	 *
	 * @return void
	 */
	public function test_the_shortcodes_render_with_the_blocks_unregistered() {
		foreach ( self::$names as $name ) {
			unregister_block_type( $name );
		}

		$this->assertStringContainsString( 'plugins used', do_shortcode( '[stats_pluginsused]' ), 'The summary shortcode does not need the block.' );
		$this->assertStringContainsString( 'Alpha Test Plugin', do_shortcode( '[active_pluginsused]' ), 'Nor does the active listing shortcode.' );
		$this->assertStringContainsString( 'beta Test Plugin', do_shortcode( '[inactive_pluginsused]' ), 'Nor the inactive one.' );
	}

	// --- the hidden-plugins setting --------------------------------------

	/**
	 * A hidden plugin does not appear in a block-rendered listing.
	 *
	 * The reason this is worth its own test rather than being left to the
	 * shortcode's: somebody ticks a plugin as hidden because they do not want
	 * visitors knowing their site runs it. A block that collected the listing
	 * its own way would put that plugin into a published page, and nothing in
	 * the editor would show that it had.
	 *
	 * The second assertion is the one that keeps this honest -- without it, a
	 * block that rendered nothing at all would pass.
	 *
	 * @return void
	 */
	public function test_the_block_honours_the_hidden_plugins_setting() {
		$this->assertStringContainsString( 'Hidden Test Plugin', WP_PluginsUsed_Blocks::render_inactive(), 'The fixture starts out visible.' );

		$this->hide_plugins( array( 'Hidden Test Plugin' ) );

		$listing = WP_PluginsUsed_Blocks::render_inactive();

		$this->assertStringNotContainsString( 'Hidden Test Plugin', $listing, 'A hidden plugin is not in the block listing.' );
		$this->assertStringContainsString( 'beta Test Plugin', $listing, 'And the rest of the listing is still there.' );
	}

	/**
	 * A hidden plugin is out of the block's counts as well as its listings.
	 *
	 * Hiding a plugin from the list but still counting it announces that there
	 * is something to find, which is most of what the setting is for.
	 *
	 * @return void
	 */
	public function test_the_summary_block_leaves_hidden_plugins_out_of_the_counts() {
		$before = WP_PluginsUsed_Blocks::render_stats();

		$this->hide_plugins( array( 'Hidden Test Plugin' ) );

		$this->assertNotSame( $before, WP_PluginsUsed_Blocks::render_stats(), 'Hiding a plugin changes the summary the block renders.' );
		$this->assertSame( do_shortcode( '[stats_pluginsused]' ), WP_PluginsUsed_Blocks::render_stats(), 'And both entry points count it the same way.' );
	}

	/**
	 * The hidden-plugins filter reaches the block too.
	 *
	 * The setting is only half of it: a site can hide a plugin from code, and
	 * that route has to work for a block for the same reason the ticked one
	 * does.
	 *
	 * @return void
	 */
	public function test_the_block_honours_the_hidden_plugins_filter() {
		add_filter(
			'wp_pluginsused_hidden_plugins',
			static function ( $hidden ) {
				$hidden[] = 'Hidden Test Plugin';

				return $hidden;
			}
		);

		WP_PluginsUsed_Template::reset_cache();

		$this->assertStringNotContainsString( 'Hidden Test Plugin', WP_PluginsUsed_Blocks::render_inactive(), 'A plugin hidden by the filter is not in the block listing.' );
	}

	/**
	 * And a hidden plugin does not survive the block parser either.
	 *
	 * The tests above call the render callback directly. This is the path a
	 * published page actually takes, and it is the one that would leak.
	 *
	 * @return void
	 */
	public function test_a_hidden_plugin_does_not_reach_a_rendered_post() {
		$this->hide_plugins( array( 'Hidden Test Plugin' ) );

		$rendered = do_blocks( '<!-- wp:wp-pluginsused/inactive-pluginsused /-->' );

		$this->assertStringContainsString( 'beta Test Plugin', $rendered, 'The saved block rendered the listing.' );
		$this->assertStringNotContainsString( 'Hidden Test Plugin', $rendered, 'And the hidden plugin is not in it.' );
	}

	// --- rendering through the block parser -------------------------------

	/**
	 * A post holding the block comments renders all three listings.
	 *
	 * The tests above call the callbacks directly, which does not prove the
	 * registration wired them to the names that get saved into post_content.
	 * This goes through do_blocks(), the way a published page does.
	 *
	 * @return void
	 */
	public function test_the_saved_blocks_render_through_the_block_parser() {
		$rendered = do_blocks(
			'<!-- wp:wp-pluginsused/stats-pluginsused /-->'
			. '<!-- wp:wp-pluginsused/active-pluginsused /-->'
			. '<!-- wp:wp-pluginsused/inactive-pluginsused /-->'
		);

		$this->assertStringContainsString( 'plugins used', $rendered, 'The summary block rendered.' );
		$this->assertStringContainsString( 'Alpha Test Plugin', $rendered, 'The active listing block rendered.' );
		$this->assertStringContainsString( 'beta Test Plugin', $rendered, 'The inactive listing block rendered.' );
	}

	/**
	 * A page holding a block and its shortcode renders the listing twice.
	 *
	 * Both entry points on one page is the state an author is in while moving
	 * from one to the other, and it is the plainest statement that the block did
	 * not take the shortcode's place.
	 *
	 * @return void
	 */
	public function test_a_block_and_a_shortcode_coexist_in_one_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:wp-pluginsused/active-pluginsused /-->' . "\n\n" . '[active_pluginsused]',
			)
		);

		$rendered = apply_filters( 'the_content', get_post( $post_id )->post_content );

		// The fixture's Plugin URI, which appears exactly once per rendered
		// entry -- unlike its name, which is both the link text and the title
		// attribute and would make the arithmetic here a puzzle.
		$this->assertSame( 2, substr_count( $rendered, 'https://example.com/alpha' ), 'The listing appears once for the block and once for the shortcode.' );
	}

	/**
	 * Drive the core block-renderer route for one of this plugin's blocks.
	 *
	 * @param string $name Block name.
	 * @return WP_REST_Response
	 */
	private function render_over_rest( $name ) {
		$request = new WP_REST_Request( 'GET', '/wp/v2/block-renderer/' . $name );
		$request->set_param( 'context', 'edit' );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Registering a dynamic block is what creates /wp/v2/block-renderer/<name>,
	 * and core gates that route on edit_posts -- a Contributor. So merely having
	 * this plugin active published the whole inventory, inactive plugins and
	 * exact versions included, to anybody who could open the editor, on a site
	 * that had never placed a listing anywhere. Core keeps the same inventory
	 * behind activate_plugins on /wp/v2/plugins.
	 */
	public function test_a_contributor_cannot_render_a_listing_over_the_block_renderer_route() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );

		$response = $this->render_over_rest( 'wp-pluginsused/inactive-pluginsused' );

		$this->assertTrue( $response->is_error(), 'A contributor is refused.' );
		$this->assertSame( 403, $response->get_status(), 'With a permission error rather than a render.' );

		$body = wp_json_encode( $response->get_data() );

		$this->assertStringNotContainsString( 'Hidden Test Plugin', $body, 'And no part of the inventory comes back in the refusal.' );
		$this->assertStringNotContainsString( 'example.com/beta', $body, 'Nor any plugin URI.' );
	}

	/**
	 * The gate is `manage_options`, not `activate_plugins`. Both belong to an
	 * administrator on a single site, but under multisite core resolves
	 * `activate_plugins` to `manage_network_plugins` for anyone who is not a
	 * super admin -- so gating on it locks every site administrator on every
	 * network out of previewing a block on their own site. This test only fails
	 * on the network pass, which is where it earned its place.
	 */
	public function test_an_administrator_can_still_render_a_listing_over_the_block_renderer_route() {
		wp_set_current_user( $this->create_admin() );

		$response = $this->render_over_rest( 'wp-pluginsused/active-pluginsused' );

		$this->assertFalse( $response->is_error(), 'A site administrator may preview the listing, on a network as on a single site.' );

		$data = $response->get_data();

		$this->assertStringContainsString( 'Alpha Test Plugin', $data['rendered'], 'And gets the listing.' );
	}

	/**
	 * The guard is on the route, not on REST_REQUEST -- do_blocks() also runs
	 * when a post's content is rendered through /wp/v2/posts, and a check on the
	 * constant would blank the block for every reader of a headless site.
	 */
	public function test_the_guard_does_not_reach_an_ordinary_rest_render_of_post_content() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:wp-pluginsused/active-pluginsused /-->',
				'post_status'  => 'publish',
			)
		);

		wp_set_current_user( 0 );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id ) );

		$this->assertFalse( $response->is_error(), 'The post reads normally.' );

		$data = $response->get_data();

		$this->assertStringContainsString(
			'Alpha Test Plugin',
			$data['content']['rendered'],
			'And the block a site owner published still renders for an anonymous reader.'
		);
	}
}
