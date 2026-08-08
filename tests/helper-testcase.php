<?php
/**
 * Shared fixtures: real plugin files written into wp-content/plugins.
 *
 * The plugin reads the filesystem through core's get_plugins(), so the only
 * honest way to test it is with actual plugin headers on disk.
 *
 * @package WP-PluginsUsed
 */

/**
 * Base class handling fixture plugin creation and teardown.
 */
abstract class WP_PluginsUsed_TestCase extends WP_UnitTestCase {

	/**
	 * Fixture definitions: slug => header values.
	 *
	 * The "evil" fixture contains no HTML tag at all, so stripping tags does
	 * nothing to it -- only real escaping keeps it out of the attributes.
	 *
	 * @var array<string, array<string, string>>
	 */
	protected static $fixtures = array(
		'zzz-alpha'  => array(
			'Name'        => 'Alpha Test Plugin',
			'PluginURI'   => 'https://example.com/alpha',
			'Description' => 'A plain description.',
			'Author'      => 'Alpha Author',
			'AuthorURI'   => 'https://example.com/authora',
			'Version'     => '1.2.3',
		),
		'zzz-beta'   => array(
			'Name'        => 'beta Test Plugin',
			'PluginURI'   => 'https://example.com/beta',
			'Description' => 'Second description.',
			'Author'      => 'Beta Author',
			'AuthorURI'   => 'https://example.com/authorb',
			'Version'     => '2.0',
		),
		'zzz-evil'   => array(
			'Name'        => 'Evil" onmouseover="alert(1)',
			'PluginURI'   => 'javascript:alert(2)',
			'Description' => 'Desc with & ampersand and "quotes".',
			'Author'      => 'Bad" autofocus="x',
			'AuthorURI'   => 'javascript:alert(3)',
			'Version'     => '9.9" onload="alert(4)',
		),
		'zzz-hidden' => array(
			'Name'        => 'Hidden Test Plugin',
			'PluginURI'   => 'https://example.com/hidden',
			'Description' => 'Should be hideable.',
			'Author'      => 'Hidden Author',
			'AuthorURI'   => 'https://example.com/authorh',
			'Version'     => '3.1',
		),

		/*
		 * A name the settings sanitiser rewrites: the double space collapses and
		 * the percent-octet is deleted, so the stored value and the raw header
		 * are not the same string. Every other fixture here survives
		 * sanitize_text_field() byte for byte -- including the "evil" one, whose
		 * double quote it leaves alone -- which is why the hide round-trip
		 * looked covered while it was broken for exactly this shape of name.
		 */
		'zzz-gappy'  => array(
			'Name'        => 'Gappy  Test%20 Plugin',
			'PluginURI'   => 'https://example.com/gappy',
			'Description' => 'A name the sanitiser rewrites.',
			'Author'      => 'Gappy Author',
			'AuthorURI'   => 'https://example.com/authorg',
			'Version'     => '4.2',
		),
	);

	/**
	 * Plugin files considered active for the duration of a test.
	 *
	 * @var string[]
	 */
	protected static $active = array( 'zzz-alpha/zzz-alpha.php', 'zzz-evil/zzz-evil.php' );

	/**
	 * Creates a user who may actually reach the plugin's screens.
	 *
	 * The settings screen takes `manage_options`, which core's map_meta_cap()
	 * does not touch under multisite, so no grant_super_admin() here: a site
	 * administrator holds it on a network exactly as on a single site. Granting
	 * anyway would make the fixture stop representing the operator this plugin
	 * actually has and hide the very class of bug §7.2.2 is about.
	 *
	 * Every administrator the suite creates goes through this, so the network
	 * question is answered in one place rather than at each call site. Tests
	 * that assert the *unprivileged* path set their own subscriber or editor
	 * explicitly and must not be routed through here.
	 *
	 * @return int The new user's ID.
	 */
	protected function create_admin() {
		return self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Write the fixture plugins and reset all plugin state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		foreach ( self::$fixtures as $slug => $headers ) {
			$this->create_plugin_fixture(
				$slug,
				array(
					'Plugin Name' => $headers['Name'],
					'Plugin URI'  => $headers['PluginURI'],
					'Description' => $headers['Description'],
					'Author'      => $headers['Author'],
					'Author URI'  => $headers['AuthorURI'],
					'Version'     => $headers['Version'],
				)
			);
		}

		update_option( 'active_plugins', self::$active );

		$this->reset_plugin_state();
	}

	/**
	 * The filesystem abstraction, initialised on first use.
	 *
	 * The fixtures are real files in wp-content/plugins, so something has to
	 * write them; WP_Filesystem is the API WordPress offers for that, and
	 * tests/bootstrap.php pins the transport to "direct" so this never has to
	 * negotiate credentials.
	 *
	 * @return WP_Filesystem_Base
	 */
	protected function filesystem() {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';

			WP_Filesystem();
		}

		return $wp_filesystem;
	}

	/**
	 * Write one fixture plugin into wp-content/plugins.
	 *
	 * @param string   $slug    Directory and file name, without the extension.
	 * @param string[] $headers Plugin header labels mapped to their values.
	 * @return void
	 */
	protected function create_plugin_fixture( $slug, array $headers ) {
		$filesystem = $this->filesystem();
		$dir        = WP_PLUGIN_DIR . '/' . $slug;

		if ( ! $filesystem->is_dir( $dir ) ) {
			$filesystem->mkdir( $dir );
		}

		$php = "<?php\n/*\n";

		foreach ( $headers as $label => $value ) {
			$php .= $label . ': ' . $value . "\n";
		}

		$php .= "*/\n";

		$filesystem->put_contents( $dir . '/' . $slug . '.php', $php );
	}

	/**
	 * Remove one fixture plugin again.
	 *
	 * @param string $slug Directory and file name, without the extension.
	 * @return void
	 */
	protected function delete_plugin_fixture( $slug ) {
		$filesystem = $this->filesystem();
		$dir        = WP_PLUGIN_DIR . '/' . $slug;

		$filesystem->delete( $dir . '/' . $slug . '.php' );
		$filesystem->rmdir( $dir );
	}

	/**
	 * Remove the fixture plugins.
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( array_keys( self::$fixtures ) as $slug ) {
			$this->delete_plugin_fixture( $slug );
		}

		unset( $GLOBALS['pluginsused_hidden_plugins'] );
		delete_option( 'wp_pluginsused_options' );
		delete_option( 'wp_pluginsused_version' );
		delete_option( 'pluginsused_options' );

		$this->reset_plugin_state();

		parent::tear_down();
	}

	/**
	 * Drop both the plugin's cache and core's plugin-scan cache.
	 *
	 * @return void
	 */
	protected function reset_plugin_state() {
		wp_cache_delete( 'plugins', 'plugins' );
		WP_PluginsUsed_Template::reset_cache();
	}

	/**
	 * Run the uninstall routine.
	 *
	 * The uninstall file declares a function, so it can only be included once in
	 * a process; a second require_once is a silent no-op and would leave a test
	 * asserting against rows nothing had removed. The first caller includes the
	 * file, which is what exercises its multisite branch, and every later caller
	 * runs the routine for the current site directly.
	 *
	 * @return void
	 */
	protected function run_uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-pluginsused/wp-pluginsused.php' );
		}

		if ( function_exists( 'wp_pluginsused_uninstall_site' ) ) {
			wp_pluginsused_uninstall_site();

			return;
		}

		require dirname( __DIR__ ) . '/uninstall.php';
	}

	/**
	 * Every option row the plugin owns, read straight from the table.
	 *
	 * A LIKE rather than a list of names: a row added later and forgotten in
	 * uninstall.php is exactly the failure this is here to catch.
	 *
	 * @return string[]
	 */
	protected function stored_option_names() {
		global $wpdb;

		return (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'wp_pluginsused_' ) . '%'
			)
		);
	}

	/**
	 * Parse markup and return the DOMDocument, ignoring libxml's ignorance of SVG.
	 *
	 * @param string $html Markup to parse.
	 * @return array{doc: DOMDocument, errors: array}
	 */
	protected function parse_html( $html ) {
		$previous = libxml_use_internal_errors( true );
		libxml_clear_errors();

		$doc = new DOMDocument();
		$doc->loadHTML( '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>' );

		// DOMDocument uses libxml's pre-HTML5 parser, which reports every inline
		// SVG element as an invalid tag. Real malformation still surfaces.
		$errors = array_filter(
			libxml_get_errors(),
			static function ( $error ) {
				if ( preg_match( '/^Tag (svg|path|circle) invalid$/', trim( $error->message ) ) ) {
					return false;
				}
				return LIBXML_ERR_ERROR <= $error->level;
			}
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return array(
			'doc'    => $doc,
			'errors' => $errors,
		);
	}

	/**
	 * Collect every event-handler attribute and javascript: URL in some markup.
	 *
	 * @param string $html Markup to inspect.
	 * @return array{handlers: string[], schemes: string[]}
	 */
	protected function find_injections( $html ) {
		$parsed   = $this->parse_html( $html );
		$handlers = array();
		$schemes  = array();

		foreach ( $parsed['doc']->getElementsByTagName( '*' ) as $element ) {
			foreach ( $element->attributes as $attribute ) {
				// DOMNode's own properties are camelCase and are not ours to
				// rename, so the node is described through getNodePath() and
				// read through DOMAttr's lowercase name/value pair instead.
				$name = strtolower( $attribute->name );

				if ( 0 === strpos( $name, 'on' ) || 'autofocus' === $name ) {
					$handlers[] = $element->getNodePath() . '[' . $name . ']';
				}

				if ( in_array( $name, array( 'href', 'src' ), true )
					&& preg_match( '~^\s*javascript:~i', $attribute->value ) ) {
					$schemes[] = $element->getNodePath() . '[' . $name . ']';
				}
			}
		}

		return array(
			'handlers' => $handlers,
			'schemes'  => $schemes,
		);
	}
}
