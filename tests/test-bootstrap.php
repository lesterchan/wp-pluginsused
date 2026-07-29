<?php
/**
 * Plugin boot: constants, wiring, and the direct-access guards.
 *
 * @package WP-PluginsUsed
 */

/**
 * @covers WP_PluginsUsed
 */
class Test_PluginsUsed_Bootstrap extends WP_UnitTestCase {

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

		$this->assertFileExists( $path );
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
			$root
		);
	}

	public function test_uninstall_is_guarded_by_its_own_constant() {
		$this->assertMatchesRegularExpression(
			"/defined\(\s*'WP_UNINSTALL_PLUGIN'\s*\)/",
			php_strip_whitespace( dirname( __DIR__ ) . '/uninstall.php' )
		);
	}

	public function test_version_constant_matches_the_plugin_header() {
		$header = get_file_data(
			dirname( __DIR__ ) . '/wp-pluginsused.php',
			array( 'Version' => 'Version' )
		);

		$this->assertSame( $header['Version'], WP_PLUGINSUSED_VERSION );
	}

	public function test_version_constant_matches_the_readme_stable_tag() {
		preg_match( '/^Stable tag:\s*(\S+)/m', file_get_contents( dirname( __DIR__ ) . '/README.md' ), $m );

		$this->assertSame( WP_PLUGINSUSED_VERSION, $m[1] );
	}

	public function test_main_file_constant_points_at_the_plugin() {
		$this->assertSame(
			realpath( dirname( __DIR__ ) . '/wp-pluginsused.php' ),
			realpath( WP_PLUGINSUSED_MAIN_FILE )
		);
	}

	public function test_every_class_is_loaded() {
		$this->assertTrue( class_exists( 'WP_PluginsUsed' ) );
		$this->assertTrue( class_exists( 'WP_PluginsUsed_Options' ) );
		$this->assertTrue( class_exists( 'WP_PluginsUsed_Settings' ) );
		$this->assertTrue( class_exists( 'WP_PluginsUsed_Template' ) );
	}

	public function test_get_instance_is_a_singleton() {
		$this->assertSame( WP_PluginsUsed::get_instance(), WP_PluginsUsed::get_instance() );
	}

	/**
	 * Every class carries the display name as its prefix, and its file is that
	 * name lowercased with the underscores turned into hyphens.
	 */
	public function test_every_class_is_named_after_the_plugin_and_lives_in_a_matching_file() {
		foreach ( array( 'WP_PluginsUsed', 'WP_PluginsUsed_Options', 'WP_PluginsUsed_Settings', 'WP_PluginsUsed_Template' ) as $class ) {
			$this->assertStringStartsWith( 'WP_PluginsUsed', $class, 'Nothing unprefixed may reach the global namespace.' );

			$file = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';

			$this->assertFileExists(
				dirname( __DIR__ ) . '/includes/' . $file,
				"{$class} must be declared in includes/{$file}."
			);
		}
	}

	public function test_public_functions_are_available() {
		$this->assertTrue( function_exists( 'display_pluginsused' ) );
		$this->assertTrue( function_exists( 'get_pluginsused' ) );
		$this->assertTrue( function_exists( 'get_pluginsused_data' ) );
		$this->assertTrue( function_exists( 'process_pluginsused' ) );
		$this->assertTrue( function_exists( 'pluginsused_format_display' ) );
		$this->assertTrue( function_exists( 'pluginsused_sort' ) );
	}

	/**
	 * Since WordPress 6.7 an early textdomain load triggers _doing_it_wrong,
	 * and WordPress.org-hosted plugins have been served translations
	 * automatically since 4.6.
	 */
	public function test_no_textdomain_is_loaded_manually() {
		$this->assertStringNotContainsString(
			'load_plugin_textdomain',
			wp_pluginsused_test_source_code()
		);
	}

	public function test_settings_screen_is_not_wired_up_on_the_front_end() {
		// is_admin() is false in the test suite, so the constructor must not
		// have registered the admin hooks.
		$this->assertFalse( has_action( 'admin_menu', array( 'WP_PluginsUsed_Settings', 'add_page' ) ) );
	}
}
