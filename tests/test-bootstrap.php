<?php
/**
 * Plugin boot: constants, wiring, and the direct-access guards.
 *
 * @package WP-PluginsUsed
 */

/**
 * @covers WP_PluginsUsed
 */
class WP_PluginsUsed_Bootstrap_Test extends WP_PluginsUsed_TestCase {

	/**
	 * Every shipped PHP file that holds code, and so needs an ABSPATH guard.
	 *
	 * The index.php silence guards are excluded: they contain nothing but a
	 * docblock, so there is no code for a direct request to reach.
	 *
	 * @return array<int, array{0: string}>
	 */
	public function guarded_files() {
		return array(
			array( 'wp-pluginsused.php' ),
			array( 'includes/template-tags.php' ),
			array( 'includes/deprecated.php' ),
			array( 'includes/class-wp-pluginsused.php' ),
			array( 'includes/class-wp-pluginsused-options.php' ),
			array( 'includes/class-wp-pluginsused-settings.php' ),
			array( 'includes/class-wp-pluginsused-template.php' ),
		);
	}

	/**
	 * @dataProvider guarded_files
	 *
	 * @param string $file Repo-relative path.
	 */
	public function test_every_code_file_refuses_direct_access( $file ) {
		$path = dirname( __DIR__ ) . '/' . $file;

		$this->assertFileExists( $path, $file . ' does not exist, so the guard assertion below would pass on an empty string.' );
		$this->assertMatchesRegularExpression(
			"/defined\(\s*'ABSPATH'\s*\)\s*\|\|\s*exit;/",
			php_strip_whitespace( $path ),
			$file . ' must refuse to run when loaded directly.'
		);
	}

	/**
	 * Only the entry points belong in the plugin root.
	 *
	 * Everything else lives in includes/, matching the rest of the collection.
	 * WordPress itself requires the main file and uninstall.php to sit at the
	 * root, and index.php is the silence guard for the directory.
	 */
	public function test_only_entry_points_live_in_the_plugin_root() {
		$root = array_map( 'basename', (array) glob( dirname( __DIR__ ) . '/*.php' ) );

		sort( $root );

		$this->assertSame(
			array( 'index.php', 'uninstall.php', 'wp-pluginsused.php' ),
			$root,
			'Only entry points live in the root; everything else is in a subdirectory.'
		);
	}

	public function test_uninstall_is_guarded_by_its_own_constant() {
		$this->assertMatchesRegularExpression(
			"/defined\(\s*'WP_UNINSTALL_PLUGIN'\s*\)/",
			php_strip_whitespace( dirname( __DIR__ ) . '/uninstall.php' ),
			'uninstall.php refuses to run outside the uninstall context.'
		);
	}

	public function test_version_constant_matches_the_plugin_header() {
		$header = get_file_data(
			dirname( __DIR__ ) . '/wp-pluginsused.php',
			array( 'Version' => 'Version' )
		);

		$this->assertSame( $header['Version'], WP_PLUGINSUSED_VERSION, 'The version constant matches the plugin header.' );
	}

	public function test_version_constant_matches_the_readme_stable_tag() {
		preg_match( '/^Stable tag:\s*(\S+)/m', file_get_contents( dirname( __DIR__ ) . '/README.md' ), $m );

		$this->assertSame( WP_PLUGINSUSED_VERSION, $m[1], 'And the readme stable tag, so all three cannot drift apart.' );
	}

	public function test_main_file_constant_points_at_the_plugin() {
		$this->assertSame(
			realpath( dirname( __DIR__ ) . '/wp-pluginsused.php' ),
			realpath( WP_PLUGINSUSED_MAIN_FILE ),
			'The main file constant resolves to the plugin file itself.'
		);
	}

	public function test_every_class_is_loaded() {
		$this->assertTrue( class_exists( 'WP_PluginsUsed' ), 'The main class is loaded by the bootstrap.' );
		$this->assertTrue( class_exists( 'WP_PluginsUsed_Options' ), 'The options class is loaded by the bootstrap.' );
		$this->assertTrue( class_exists( 'WP_PluginsUsed_Settings' ), 'The settings class is loaded by the bootstrap.' );
		$this->assertTrue( class_exists( 'WP_PluginsUsed_Template' ), 'The template class is loaded by the bootstrap.' );
	}

	public function test_get_instance_is_a_singleton() {
		$this->assertSame( WP_PluginsUsed::get_instance(), WP_PluginsUsed::get_instance(), 'get_instance() hands back the same object rather than building a second.' );
	}

	/**
	 * Every class carries the display name as its prefix, and its file is that
	 * name lowercased with the underscores turned into hyphens.
	 *
	 * The classes are read back out of PHP rather than listed here. Against a
	 * hardcoded list the prefix half compared two literals and could not fail,
	 * and the file half only ever asked about the four names already typed --
	 * so a fifth class, prefixed or not, was invisible to both. Asking PHP
	 * which classes this plugin declared is what makes either half bite.
	 */
	public function test_every_class_is_named_after_the_plugin_and_lives_in_a_matching_file() {
		$declared = $this->classes_declared_by_the_plugin();

		// Without this the test passes by finding nothing -- a filter that
		// matches no files is exactly how the version before it went vacuous.
		$this->assertNotEmpty( $declared, 'No class was traced back to this plugin, so the checks below looked at nothing.' );

		foreach ( $declared as $class => $file ) {
			$this->assertStringStartsWith( 'WP_PluginsUsed', $class, $class . ' reaches the global namespace without the plugin prefix.' );

			$expected = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';

			// The file the class was really declared in, rather than whether a
			// file of that name happens to exist somewhere.
			$this->assertSame(
				dirname( __DIR__ ) . '/includes/' . $expected,
				$file,
				"{$class} must be declared in includes/{$expected}."
			);
		}
	}

	/**
	 * The classes this plugin declared, mapped to the file each came from.
	 *
	 * Matched against the directories the plugin's own code lives in rather
	 * than against the plugin root with the rest subtracted. The root holds
	 * vendor/ under CI, and Composer declares ComposerAutoloaderInit<hash>
	 * there -- so a root match minus tests/ failed on a class that is not this
	 * plugin's to name and never appears on a developer's machine, where
	 * vendor/ is absent. An allow list cannot acquire a new member by somebody
	 * else installing something.
	 *
	 * @return array<string, string> Class name to the file declaring it.
	 */
	protected function classes_declared_by_the_plugin() {
		$root     = dirname( __DIR__ ) . '/';
		$includes = $root . 'includes/';
		$ours     = array();

		foreach ( get_declared_classes() as $class ) {
			$file = ( new ReflectionClass( $class ) )->getFileName();

			if ( ! is_string( $file ) ) {
				continue;
			}

			// includes/, plus the entry points sitting in the root itself.
			if ( str_starts_with( $file, $includes ) || dirname( $file ) . '/' === $root ) {
				$ours[ $class ] = $file;
			}
		}

		return $ours;
	}

	public function test_public_functions_are_available() {
		$this->assertTrue( function_exists( 'display_pluginsused' ), 'The documented display_pluginsused() is available to a theme.' );
		$this->assertTrue( function_exists( 'get_pluginsused' ), 'The documented get_pluginsused() is available to a theme.' );
		$this->assertTrue( function_exists( 'get_pluginsused_data' ), 'The documented get_pluginsused_data() is available to a theme.' );
		$this->assertTrue( function_exists( 'process_pluginsused' ), 'The documented process_pluginsused() is available to a theme.' );
		$this->assertTrue( function_exists( 'pluginsused_format_display' ), 'The documented pluginsused_format_display() is available to a theme.' );
		$this->assertTrue( function_exists( 'pluginsused_sort' ), 'The documented pluginsused_sort() is available to a theme.' );
	}

	/**
	 * Since WordPress 6.7 an early textdomain load triggers _doing_it_wrong,
	 * and WordPress.org-hosted plugins have been served translations
	 * automatically since 4.6.
	 */
	public function test_no_textdomain_is_loaded_manually() {
		$this->assertStringNotContainsString(
			'load_plugin_textdomain',
			wp_pluginsused_test_source_code(),
			'No textdomain is loaded by hand; WordPress has done that since 4.6.'
		);
	}

	public function test_settings_screen_is_not_wired_up_on_the_front_end() {
		// is_admin() is false in the test suite, so the constructor must not
		// have registered the admin hooks.
		$this->assertFalse( has_action( 'admin_menu', array( 'WP_PluginsUsed_Settings', 'add_page' ) ), 'The settings screen is not hooked up on a front-end request.' );
	}
}
