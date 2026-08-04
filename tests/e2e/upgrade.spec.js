/**
 * The upgrade routine, run the way a real site runs it.
 *
 * Activation hooks do not fire when a plugin is updated -- a site that updates
 * through the Plugins screen never calls activate() -- so the plugin also hangs
 * maybe_upgrade() off admin_init. That hook is the one every real upgrade
 * actually goes through, and it is only reachable by loading an admin page in a
 * browser, which is what these two tests do.
 *
 * Both of them then check the far end rather than a notice: the rows themselves,
 * and the page the migrated settings are supposed to change.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	createShortcodePost,
	deleteOptions,
	deleteVersionRow,
	ensurePluginActive,
	getLegacyOptions,
	getStoredOptions,
	getVersionRow,
	installedPlugins,
	openSettings,
	reactivatePlugin,
	removeHostilePlugin,
	runningVersions,
	setLegacyOptions,
	setOptions,
	setVersionRow,
	uniqueTitle,
} = require( './helpers.js' );

/** What the install actually holds. */
let plugins;

test.describe( 'The upgrade routine', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		removeHostilePlugin();

		plugins = installedPlugins();
	} );

	test.afterEach( async () => {
		deleteOptions();

		// This is the only file that ever switches the plugin off, and only for
		// as long as it takes to prove the activation hook runs. A failure part
		// way through that would otherwise hand every later test a site with no
		// plugin, and the run would report a hundred symptoms of one cause.
		ensurePluginActive();
	} );

	test( 'an admin load stamps the version row, without an activation in sight', async ( {
		page,
	} ) => {
		deleteVersionRow();

		// The fixture really is an install that has never been stamped, which is
		// the state an update through the Plugins screen leaves behind.
		expect( getVersionRow() ).toBe( false );

		await openSettings( page );

		// Both markers, together, matching the code that is running -- the point
		// of keeping them in a row of their own is that the settings screen can
		// never overwrite them and this can never overwrite a setting.
		expect( getVersionRow() ).toEqual( runningVersions() );
	} );

	test( 'a pre-2.0.0 settings row is folded in, cleaned, deleted, and takes effect', async ( {
		page,
		requestUtils,
	} ) => {
		const hidden = plugins.inactive[ 0 ];

		deleteOptions();
		deleteVersionRow();

		// The row as an older, laxer build wrote it: a string where a boolean
		// belongs, a duplicate, and an empty name. A migration that copied it
		// across untouched would leave all three in place.
		setLegacyOptions( {
			show_version: '1',
			hidden_plugins: [ hidden, '', hidden ],
		} );

		await openSettings( page );

		// The old row is gone rather than left to rot, so re-running finds
		// nothing to do and a later release has one row to think about.
		expect( getLegacyOptions() ).toBe( false );

		// This is the admin_init path -- the path every real update takes -- and
		// the settings have to come across it, cleaned. Opening the settings
		// screen is what makes it that path: register_setting() has run by the
		// time migrate() does.
		//
		// The assertion is here because it once failed, and the shape is worth
		// recognising. register_setting() is passed a 'default', which installs
		// a default_option_wp_pluginsused_options filter, so a bare get_option()
		// answers with the defaults array rather than false and migrate()'s
		// "there is no current row yet" branch is never taken -- while the
		// delete_option() below it runs regardless, taking the owner's
		// hidden-plugins list with it. migrate() passes an explicit default now,
		// which defeats the registered one.
		//
		// Reactivating repaired it, which is why it was easy to miss: WP-CLI
		// never runs register_setting(), so the branch is taken there. A
		// migration test that does not register the setting first is testing
		// WP-CLI. Do not weaken this to the reactivation path.
		expect( getStoredOptions() ).toEqual( {
			show_version: true,
			hidden_plugins: [ hidden ],
		} );

		// Present is not alive. The settings that came through the migration have
		// to be the settings the front end acts on, or the row survived and the
		// plugin did not.
		const post = await createShortcodePost(
			requestUtils,
			'[stats_pluginsused]\n\n[inactive_pluginsused]',
			uniqueTitle( 'After the migration' ),
		);

		await page.goto( post.link );

		await expect( page.locator( '.entry-content' ) ).not.toContainText( hidden );
		await expect( page.locator( '.entry-content' ) ).toContainText(
			`${ plugins.inactive.length - 1 } inactive plugin`,
		);
	} );

	test( 'a legacy row never overwrites settings the owner has already saved', async ( {
		page,
	} ) => {
		const hidden = plugins.inactive[ 0 ];

		// The shape an install lands in when it ran a development build, saved
		// something through the new screen, and only then met the migration.
		// The newer row is the one the owner has actually seen, so the older one
		// is folded away rather than folded in.
		setOptions( { show_version: false, hidden_plugins: [ hidden ] } );
		setLegacyOptions( { show_version: true, hidden_plugins: [] } );
		deleteVersionRow();

		await openSettings( page );

		expect( getLegacyOptions() ).toBe( false );
		expect( getStoredOptions() ).toEqual( {
			show_version: false,
			hidden_plugins: [ hidden ],
		} );
	} );

	test( 'reactivating runs the same migration, and the plugin still works after it', async ( {
		page,
		requestUtils,
	} ) => {
		const hidden = plugins.inactive[ 0 ];

		deleteOptions();
		deleteVersionRow();
		setLegacyOptions( { show_version: '1', hidden_plugins: [ hidden ] } );

		// The other entry point, and the only one that fires the activation
		// hook. Deactivating and reactivating is what an owner does to "fix" a
		// plugin, so it has to reach the same migration from the same rows --
		// without any admin page being loaded at all.
		reactivatePlugin();

		expect( getLegacyOptions() ).toBe( false );
		expect( getStoredOptions() ).toEqual( { show_version: true, hidden_plugins: [ hidden ] } );
		expect( getVersionRow() ).toEqual( runningVersions() );

		// Present is not alive: the install that came through the activation is
		// a working one, not just a tidy set of rows.
		const post = await createShortcodePost(
			requestUtils,
			'[inactive_pluginsused]',
			uniqueTitle( 'After the activation' ),
		);

		await page.goto( post.link );
		await expect( page.locator( '.entry-content' ) ).not.toContainText( hidden );

		await openSettings( page );
		await expect(
			page.locator( 'input[name="wp_pluginsused_options[show_version]"]' ),
		).toBeChecked();
	} );

	test( 'a second activation, and the admin load after it, change nothing', async () => {
		deleteOptions();
		deleteVersionRow();
		setLegacyOptions( { show_version: '1', hidden_plugins: [ plugins.inactive[ 0 ] ] } );

		reactivatePlugin();

		const once = { options: getStoredOptions(), versions: getVersionRow() };

		// Owners reactivate to fix things, sometimes twice. The second pass has
		// to be a bystander: the rows it finds are the rows it leaves.
		reactivatePlugin();

		expect( getStoredOptions() ).toEqual( once.options );
		expect( getVersionRow() ).toEqual( once.versions );
	} );

	test( 'an install already on this version is left alone', async ( { page } ) => {
		// A row the sanitizer would rewrite if it ran, and markers saying it has
		// already run. maybe_upgrade() returning early is what keeps every admin
		// request from being an option write, so the proof it returned early is
		// that this deliberately dirty row survives untouched.
		const dirty = { show_version: 'yes please', hidden_plugins: [ '', '' ] };

		setOptions( dirty );

		// Stamped here rather than leaned on from the test before it, because a
		// row that was not current would make the first admin load sanitise the
		// fixture and the assertion below would be about the wrong thing.
		setVersionRow( runningVersions() );

		await openSettings( page );

		expect( getStoredOptions() ).toEqual( dirty );
	} );
} );
