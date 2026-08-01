/**
 * Shared steps for the WP-PluginsUsed end-to-end suite.
 *
 * This plugin reads two things and writes one. What it reads is the plugin
 * headers of every file in wp-content/plugins plus the active-plugins option;
 * what it writes is a single settings row. So the fixtures here are of two
 * kinds: a settings row, put in place directly because the screen that writes it
 * has its own file, and a plugin file on disk, which is the only way to give the
 * listing a header it did not already have.
 *
 * A plugin header is attacker-controlled for anyone who can put a file in
 * wp-content/plugins, which is why the hostile fixture is a real file with real
 * headers rather than a string poked into a row.
 */

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { expect } = require( '@wordpress/e2e-test-utils-playwright' );

/** The plugin root, which is where wp-env reads .wp-env.json from. */
const PLUGIN_ROOT = path.join( __dirname, '../..' );

const SETTINGS_URL = '/wp-admin/options-general.php?page=wp-pluginsused';
const PLUGINS_URL = '/wp-admin/plugins.php';

/** The option row every setting lives in. */
const OPTION = 'wp_pluginsused_options';

/** The option row holding the two upgrade markers. */
const VERSION_OPTION = 'wp_pluginsused_version';

/** The settings row this plugin used before 2.0.0 renamed it. */
const LEGACY_OPTION = 'pluginsused_options';

/** Where the tests-only mu-plugin reads the names it feeds to the filter. */
const FILTER_HIDDEN_OPTION = 'wp_pluginsused_e2e_filter_hidden';

/** The plugin file, as WordPress names it on the Plugins screen. */
const PLUGIN_FILE = 'wp-pluginsused/wp-pluginsused.php';

/** The file the hostile fixture is written to, relative to the plugins directory. */
const HOSTILE_FILE = 'wp-pluginsused-e2e-hostile/wp-pluginsused-e2e-hostile.php';

/**
 * Run PHP inside the tests environment and hand back what it printed.
 *
 * The code is base64'd rather than passed as itself: a plugin header holding
 * quotes, angle brackets and a script tag is exactly the sort of string that
 * arrives at the other end subtly different, and a fixture that is not the
 * payload byte for byte proves nothing about escaping it.
 *
 * @param {string} code PHP to evaluate, without an opening tag.
 * @return {string} Whatever the code echoed between its markers.
 */
function wpEval( code ) {
	const encoded = Buffer.from( code, 'utf8' ).toString( 'base64' );

	const output = execFileSync(
		'npx',
		[
			'--yes',
			'@wordpress/env',
			'run',
			'tests-cli',
			'wp',
			'eval',
			`eval( base64_decode( '${ encoded }' ) );`,
		],
		{ cwd: PLUGIN_ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] },
	);

	// wp-env prints its own progress around the command's output, so the code
	// wraps what it wants to return in markers rather than the caller trying to
	// tell the two apart by position.
	const matched = output.match( /<<<([\s\S]*?)>>>/ );

	return matched ? matched[ 1 ] : '';
}

/**
 * Write the settings row exactly as given.
 *
 * Only the keys handed in are stored, which is realistic as well as short:
 * WP_PluginsUsed_Options::get() merges the defaults on read, so a partial row is
 * a shape real installs have.
 *
 * @param {Object} options Option keys to store.
 * @return {void}
 */
function setOptions( options ) {
	const data = Buffer.from( JSON.stringify( options ), 'utf8' ).toString( 'base64' );

	wpEval(
		`update_option( '${ OPTION }', json_decode( base64_decode( '${ data }' ), true ) );
		echo '<<<done>>>';`,
	);
}

/**
 * The settings row as the database holds it, before any defaults are merged in.
 *
 * @return {Object|false} The stored array, or false when there is no row.
 */
function getStoredOptions() {
	return JSON.parse( wpEval( `echo '<<<' . wp_json_encode( get_option( '${ OPTION }' ) ) . '>>>';` ) );
}

/**
 * Remove the settings row entirely, which is the state a fresh install is in.
 *
 * @return {void}
 */
function deleteOptions() {
	wpEval( `delete_option( '${ OPTION }' ); echo '<<<done>>>';` );
}

/**
 * The upgrade markers, as the database holds them.
 *
 * @return {Object|false} The stored array, or false when there is no row.
 */
function getVersionRow() {
	return JSON.parse(
		wpEval( `echo '<<<' . wp_json_encode( get_option( '${ VERSION_OPTION }' ) ) . '>>>';` ),
	);
}

/**
 * Write the upgrade markers exactly as given.
 *
 * @param {Object} versions The 'plugin' and 'db' markers.
 * @return {void}
 */
function setVersionRow( versions ) {
	const data = Buffer.from( JSON.stringify( versions ), 'utf8' ).toString( 'base64' );

	wpEval(
		`update_option( '${ VERSION_OPTION }', json_decode( base64_decode( '${ data }' ), true ) );
		echo '<<<done>>>';`,
	);
}

/**
 * Take the upgrade markers away, which is the state a pre-2.0.0 install is in.
 *
 * @return {void}
 */
function deleteVersionRow() {
	wpEval( `delete_option( '${ VERSION_OPTION }' ); echo '<<<done>>>';` );
}

/**
 * The version numbers the running code expects to find stamped.
 *
 * Read from the install rather than written down here: the point of the upgrade
 * test is that the row catches up with the code, and a hard-coded expectation
 * would have to be edited every release to keep saying so.
 *
 * @return {{plugin: string, db: string}} The two markers.
 */
function runningVersions() {
	return JSON.parse(
		wpEval(
			`echo '<<<' . wp_json_encode( array(
				'plugin' => WP_PLUGINSUSED_VERSION,
				'db'     => WP_PLUGINSUSED_DB_VERSION,
			) ) . '>>>';`,
		),
	);
}

/**
 * Write the settings row this plugin used before 2.0.0 renamed it.
 *
 * @param {Object} options Option keys to store, exactly as given.
 * @return {void}
 */
function setLegacyOptions( options ) {
	const data = Buffer.from( JSON.stringify( options ), 'utf8' ).toString( 'base64' );

	wpEval(
		`update_option( '${ LEGACY_OPTION }', json_decode( base64_decode( '${ data }' ), true ) );
		echo '<<<done>>>';`,
	);
}

/**
 * Whether the pre-2.0.0 settings row is still there.
 *
 * @return {Object|false} The stored array, or false once the migration has run.
 */
function getLegacyOptions() {
	return JSON.parse(
		wpEval( `echo '<<<' . wp_json_encode( get_option( '${ LEGACY_OPTION }' ) ) . '>>>';` ),
	);
}

/**
 * Names the tests-only mu-plugin feeds to the hidden-plugins filter.
 *
 * @param {string[]} names Plugin names to hide through the filter.
 * @return {void}
 */
function setFilterHidden( names ) {
	const data = Buffer.from( JSON.stringify( names ), 'utf8' ).toString( 'base64' );

	wpEval(
		`update_option( '${ FILTER_HIDDEN_OPTION }', json_decode( base64_decode( '${ data }' ), true ) );
		echo '<<<done>>>';`,
	);
}

/**
 * Stop the mu-plugin hiding anything.
 *
 * @return {void}
 */
function clearFilterHidden() {
	wpEval( `delete_option( '${ FILTER_HIDDEN_OPTION }' ); echo '<<<done>>>';` );
}

/**
 * Set one of the options the tests-only mu-plugin reads its answers from.
 *
 * Every fixture filter is inert until one of these is written, so a suite that
 * forgets to clear one does not quietly decide the outcome of everything after
 * it -- which is also why each caller clears it in a finally.
 *
 * @param {string} name  Option name.
 * @param {*}      value Anything JSON can carry.
 * @return {void}
 */
function setFixtureOption( name, value ) {
	const data = Buffer.from( JSON.stringify( value ), 'utf8' ).toString( 'base64' );

	wpEval(
		`update_option( '${ name }', json_decode( base64_decode( '${ data }' ), true ) );
		echo '<<<done>>>';`,
	);
}

/**
 * Stop one of the fixture filters answering.
 *
 * @param {string} name Option name.
 * @return {void}
 */
function clearFixtureOption( name ) {
	wpEval( `delete_option( '${ name }' ); echo '<<<done>>>';` );
}

/**
 * Deactivate and reactivate the plugin, which is the path that fires activate().
 *
 * The other entry point into the upgrade routine, and genuinely a different one:
 * updating through the Plugins screen never fires the activation hook and leaves
 * admin_init to run the migration alone.
 *
 * @return {void}
 */
function reactivatePlugin() {
	wpEval(
		`require_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( '${ PLUGIN_FILE }' );
		activate_plugin( '${ PLUGIN_FILE }' );
		echo '<<<done>>>';`,
	);
}

/**
 * Put the plugin back on, whatever a test left behind.
 *
 * Only the upgrade suite ever switches it off, and only for as long as it takes
 * to prove the activation hook runs -- but a failure in the middle of that would
 * hand every later test a site with no plugin, and the run would report a
 * hundred symptoms of one cause.
 *
 * @return {void}
 */
function ensurePluginActive() {
	wpEval(
		`require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( ! is_plugin_active( '${ PLUGIN_FILE }' ) ) {
			activate_plugin( '${ PLUGIN_FILE }' );
		}
		echo '<<<done>>>';`,
	);
}

/**
 * Every installed plugin's name, split by whether WordPress has it switched on.
 *
 * Asked of the site rather than written down here, because the answer is what
 * the plugin under test is reporting on: hard-coding it would make the suite
 * agree with itself instead of with the install.
 *
 * @return {{active: string[], inactive: string[]}} Plugin names, sorted as get_plugins() sorts them.
 */
function installedPlugins() {
	return JSON.parse(
		wpEval(
			`require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$active = (array) get_option( 'active_plugins', array() );
			$split = array( 'active' => array(), 'inactive' => array() );
			foreach ( get_plugins() as $file => $data ) {
				if ( '' === $data['Name'] ) {
					continue;
				}
				$split[ in_array( $file, $active, true ) ? 'active' : 'inactive' ][] = $data['Name'];
			}
			echo '<<<' . wp_json_encode( $split ) . '>>>';`,
		),
	);
}

/**
 * Put a plugin on disk whose every header is an attack.
 *
 * A real file with real headers, because that is the only way this plugin can
 * meet a hostile value: it never stores plugin data, it re-reads it from
 * wp-content/plugins on every request. Left deactivated, so it shows up in the
 * inactive listing without WordPress ever running a line of it.
 *
 * @param {Object} headers Header values, keyed by header name.
 * @return {void}
 */
function writeHostilePlugin( headers ) {
	const data = Buffer.from( JSON.stringify( headers ), 'utf8' ).toString( 'base64' );

	wpEval(
		`$headers = json_decode( base64_decode( '${ data }' ), true );
		$file = WP_PLUGIN_DIR . '/${ HOSTILE_FILE }';
		wp_mkdir_p( dirname( $file ) );
		$out = "<?php\\n/**\\n";
		foreach ( $headers as $name => $value ) {
			$out .= ' * ' . $name . ': ' . $value . "\\n";
		}
		$out .= " */\\n";
		file_put_contents( $file, $out );
		echo '<<<done>>>';`,
	);
}

/**
 * Take the hostile plugin off disk again.
 *
 * The plugins directory is global state that outlives a test, and a leftover
 * fixture would change every count the next test asserts on.
 *
 * @return {void}
 */
function removeHostilePlugin() {
	wpEval(
		`$dir = dirname( WP_PLUGIN_DIR . '/${ HOSTILE_FILE }' );
		if ( is_dir( $dir ) ) {
			array_map( 'unlink', glob( $dir . '/*' ) );
			rmdir( $dir );
		}
		echo '<<<done>>>';`,
	);
}

/**
 * Publish a post carrying shortcodes.
 *
 * @param {Object} requestUtils The e2e-test-utils request helper.
 * @param {string} content      Post body.
 * @param {string} title        Post title.
 * @return {Promise<Object>} The created post.
 */
function createShortcodePost( requestUtils, content, title ) {
	return requestUtils.createPost( { title, content, status: 'publish' } );
}

/**
 * Open the settings screen.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once the screen is up.
 */
async function openSettings( page ) {
	await page.goto( SETTINGS_URL );

	await expect( page.getByRole( 'heading', { name: 'Plugins Used' } ) ).toBeVisible();
}

/**
 * Save the settings form and wait for WordPress to confirm it.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once "Settings saved." is on screen.
 */
async function saveSettings( page ) {
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();

	// The notice rather than the redirect: options.php sends the browser back
	// whether or not anything was written, so arriving here again says nothing.
	await expect( page.locator( '#setting-error-settings_updated' ) ).toContainText( 'Settings saved.' );
}

/**
 * The checkbox that hides one plugin from the listings.
 *
 * @param {import('@playwright/test').Page} page Page showing the settings.
 * @param {string}                          name The plugin's Name header.
 * @return {import('@playwright/test').Locator} The checkbox.
 */
function hideCheckbox( page, name ) {
	// A plugin name is attacker-controlled text going into a CSS attribute
	// selector, and the hostile fixture's name is a quote followed by an event
	// handler -- exactly the string that ends the selector early and makes the
	// locator match nothing while looking like it matched the wrong thing.
	const value = name.replace( /\\/g, '\\\\' ).replace( /"/g, '\\"' );

	return page.locator( `input[name="${ OPTION }[hidden_plugins][]"][value="${ value }"]` );
}

/**
 * The summary sentence the plugin should be printing for a given split.
 *
 * Spelled out here rather than read off the page, so this is an independent
 * statement of what the counts ought to be and not a restatement of whatever
 * appeared. It also pins the singular forms, which the default two-plugin
 * install never reaches until something is hidden.
 *
 * @param {number} active   Number of active plugins.
 * @param {number} inactive Number of inactive plugins.
 * @return {string} The expected sentence.
 */
function summary( active, inactive ) {
	const total = active + inactive;
	const plural = ( count, word ) => `${ count } ${ word }${ 1 === count ? '' : 's' }`;

	return (
		`There ${ 1 === total ? 'is' : 'are' } ${ plural( total, 'plugin' ) } used: ` +
		`${ plural( active, 'active plugin' ) } and ${ plural( inactive, 'inactive plugin' ) }.`
	);
}

/**
 * A title no earlier run can have used.
 *
 * @param {string} base What the title should say.
 * @return {string} That, plus enough to tell this run from the last.
 */
function uniqueTitle( base ) {
	return `${ base } ${ Date.now().toString( 36 ) }`;
}

module.exports = {
	FILTER_HIDDEN_OPTION,
	LEGACY_OPTION,
	OPTION,
	PLUGINS_URL,
	PLUGIN_FILE,
	SETTINGS_URL,
	VERSION_OPTION,
	clearFilterHidden,
	clearFixtureOption,
	createShortcodePost,
	deleteOptions,
	deleteVersionRow,
	ensurePluginActive,
	getLegacyOptions,
	getStoredOptions,
	getVersionRow,
	hideCheckbox,
	installedPlugins,
	openSettings,
	reactivatePlugin,
	removeHostilePlugin,
	runningVersions,
	saveSettings,
	setFilterHidden,
	setFixtureOption,
	setLegacyOptions,
	setOptions,
	setVersionRow,
	summary,
	uniqueTitle,
	writeHostilePlugin,
	wpEval,
};
