<?php
/**
 * Block registration.
 *
 * @package WP-PluginsUsed
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's blocks and renders them.
 *
 * **The blocks are added beside the shortcodes, never in place of them.**
 * `[stats_pluginsused]`, `[active_pluginsused]` and `[inactive_pluginsused]`
 * stay registered, documented and supported, and nothing here deprecates any of
 * them. Those shortcodes sit in published pages on sites nobody here can survey;
 * a block that replaced them would render literal text on every one of them.
 *
 * All three blocks are dynamic. Their `save` returns null in JavaScript, so what
 * lands in post_content is the block comment and no markup at all -- every view
 * re-renders through the callbacks below. That is what lets a block and a
 * shortcode share one renderer, and it is what makes the hidden-plugins setting
 * work: a plugin ticked as hidden has to vanish from pages published before it
 * was ticked, and a block that had saved its markup would go on listing it.
 *
 * **Neither entry point calls the other.** The blocks do not run
 * `do_shortcode()` and the shortcodes do not ask this class for anything. They
 * are siblings over `WP_PluginsUsed_Template::render()`, which is where the
 * collecting, the hiding and the escaping all live. Routing a block through
 * `do_shortcode()` would break it outright the day anybody unregistered the
 * shortcode, and would put the block's output at the mercy of shortcode parsing
 * it has no way to produce.
 *
 * **Three blocks rather than one with a type attribute**, even though the three
 * shortcode callbacks differ only in the string they pass the renderer. Two
 * reasons, and the second is the one that settles it. None of the three
 * shortcodes accepts an attribute, so a `type` attribute would be surface
 * invented for the block and present on only one of the two entry points. And a
 * block name is written into post_content for the life of the post, whereas an
 * attribute is a value a later default or a later migration can change -- so
 * "which listing is this" belongs in the name, where nothing can flip it, rather
 * than in a field where a wrong value silently publishes the *other* listing.
 *
 * **One class registers all three**, rather than a class per block: a class
 * whose entire body is a single `register_block_type_from_metadata()` call would
 * be a file per block for no gain.
 */
class WP_PluginsUsed_Blocks {

	/**
	 * Blocks this plugin registers, as build directory => render callback.
	 *
	 * The keys are directory names under `build/`, which is what
	 * `register_block_type_from_metadata()` reads: the name, title and script
	 * handle all come out of the `block.json` copied there by the build, so this
	 * class never restates any of them.
	 *
	 * @return array<string, callable>
	 */
	private static function blocks() {
		return array(
			'stats-pluginsused'    => array( __CLASS__, 'render_stats' ),
			'active-pluginsused'   => array( __CLASS__, 'render_active' ),
			'inactive-pluginsused' => array( __CLASS__, 'render_inactive' ),
		);
	}

	/**
	 * Hooks block registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registers every block against its built metadata.
	 *
	 * A missing `build/` directory means the plugin was installed without its
	 * build step having run -- a git checkout rather than a release zip, since
	 * `bin/build` runs before the deploy copies anything. Registering a block
	 * whose script cannot be enqueued gives an editor that silently fails to
	 * load it, so this skips it and leaves the shortcodes working.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( self::blocks() as $directory => $callback ) {
			$metadata = WP_PLUGINSUSED_DIR . 'build/' . $directory;

			if ( ! file_exists( $metadata . '/block.json' ) ) {
				continue;
			}

			register_block_type_from_metadata(
				$metadata,
				array( 'render_callback' => $callback )
			);
		}
	}

	/**
	 * Renders the `wp-pluginsused/stats-pluginsused` block.
	 *
	 * Takes no attributes, because `[stats_pluginsused]` takes none either.
	 *
	 * @return string
	 */
	public static function render_stats() {
		return WP_PluginsUsed_Template::render( 'stats' );
	}

	/**
	 * Renders the `wp-pluginsused/active-pluginsused` block.
	 *
	 * @return string
	 */
	public static function render_active() {
		return WP_PluginsUsed_Template::render( 'active' );
	}

	/**
	 * Renders the `wp-pluginsused/inactive-pluginsused` block.
	 *
	 * @return string
	 */
	public static function render_inactive() {
		return WP_PluginsUsed_Template::render( 'inactive' );
	}
}
