/**
 * Settings -> WP-PluginsUsed.
 *
 * Two settings, and both of them are only worth anything if they reach the
 * front end, so no test here stops at the stored row: each one saves through the
 * form, reads the row back, and then goes and looks at a page. A setting that
 * saves but does nothing and a setting that does something but will not save are
 * the two failures a screenshot cannot tell apart.
 *
 * Before 2.0.0 there was no screen at all -- hiding a plugin meant editing the
 * plugin's own source, which WordPress overwrote on the next update -- so this
 * whole file is testing new surface.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	PLUGINS_URL,
	SETTINGS_URL,
	clearFixtureOption,
	createShortcodePost,
	deleteOptions,
	getStoredOptions,
	hideCheckbox,
	installedPlugins,
	openSettings,
	removeHostilePlugin,
	saveSettings,
	setFixtureOption,
	summary,
	uniqueTitle,
} = require( './helpers.js' );

/** The lesser user both capability tests are argued from. */
const SUBSCRIBER = {
	username: 'pluginsused_subscriber',
	password: 'correct-horse-battery-staple',
};

/**
 * A second browser, logged in as a subscriber.
 *
 * A fresh context rather than the shared one, because the suite's stored session
 * is the administrator's and swapping users inside it would leave whichever test
 * ran next signed in as the wrong person.
 *
 * @param {import('@playwright/test').Page} page         The administrator's page, for its browser.
 * @param {Object}                          requestUtils The e2e-test-utils request helper.
 * @return {Promise<Object>} The new `context` and the logged-in `subscriber` page.
 */
async function loginAsSubscriber( page, requestUtils ) {
	await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/users',
		data: {
			username: SUBSCRIBER.username,
			email: `${ SUBSCRIBER.username }@example.com`,
			password: SUBSCRIBER.password,
			roles: [ 'subscriber' ],
		},
	} ).catch( () => {} ); // Already there from an earlier run.

	const context = await page.context().browser().newContext( { storageState: undefined } );
	const subscriber = await context.newPage();

	await subscriber.goto( '/wp-login.php' );

	// wp-login.php focuses and selects #user_login on a 200ms timer, so that a
	// visitor can start typing. Filling across that moment puts the password
	// into the username box: Playwright focuses #user_pass, the timer takes
	// focus back and selects what is there, and the typed text replaces the
	// selection. Waiting for the timer's own effect is the signal that it has
	// already fired.
	await expect( subscriber.locator( '#user_login' ) ).toBeFocused();

	await subscriber.locator( '#user_login' ).fill( SUBSCRIBER.username );
	await subscriber.locator( '#user_pass' ).fill( SUBSCRIBER.password );
	await subscriber.locator( '#wp-submit' ).click();
	await expect( subscriber.locator( '#wpadminbar' ) ).toBeVisible();

	return { context, subscriber };
}

/** What the install actually holds. */
let plugins;

/** A page carrying all three shortcodes, so a setting's effect can be looked at. */
let listingLink;

test.describe( 'The settings screen', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		removeHostilePlugin();

		plugins = installedPlugins();

		const post = await createShortcodePost(
			requestUtils,
			'[stats_pluginsused]\n\n[active_pluginsused]\n\n[inactive_pluginsused]',
			uniqueTitle( 'Everything' ),
		);

		listingLink = post.link;
	} );

	test.beforeEach( async () => {
		deleteOptions();
	} );

	test.afterEach( async () => {
		// The row decides what every page on the site renders, so no test leaves
		// its own settings behind.
		deleteOptions();
	} );

	test( 'the fixture really is a fresh install, and the screen offers every plugin', async ( {
		page,
	} ) => {
		// The precondition the rest of the file leans on: with a row already in
		// place, "the checkbox saved" could be true before the test did anything.
		expect( getStoredOptions() ).toBe( false );

		await openSettings( page );

		// Version numbers default to on, which is what the pre-2.0.0 plugin did
		// with no way to change it.
		await expect( page.locator( 'input[name="wp_pluginsused_options[show_version]"]' ) ).toBeChecked();

		// And the hide list is built from the install rather than from a stored
		// list, so a plugin added yesterday is offered today.
		for ( const name of [ ...plugins.active, ...plugins.inactive ] ) {
			await expect( hideCheckbox( page, name ) ).toHaveCount( 1 );
			await expect( hideCheckbox( page, name ) ).not.toBeChecked();
		}

		// The screen is also the only documentation a shortcode has: nothing else
		// in wp-admin says what to type into a post, so the three names being on
		// the page is the difference between a usable plugin and a mystery.
		for ( const shortcode of [
			'[stats_pluginsused]',
			'[active_pluginsused]',
			'[inactive_pluginsused]',
		] ) {
			await expect( page.locator( '#wpbody code' ).filter( { hasText: shortcode } ) ).toHaveCount(
				1,
			);
		}

		await expect( page.locator( '#wpbody' ) ).toContainText(
			'Hidden plugins are left out of the counts as well as the listings',
		);
	} );

	test( 'turning version numbers off saves, and takes them off the page', async ( { page } ) => {
		await page.goto( listingLink );

		// The fixture for this test is the default state, asserted rather than
		// assumed: without this the "off" half could be passing because the
		// version was never there.
		await expect( page.locator( '.entry-content' ) ).toContainText( /WP-PluginsUsed \d+\.\d+/ );

		await openSettings( page );
		await page.locator( 'input[name="wp_pluginsused_options[show_version]"]' ).uncheck();
		await saveSettings( page );

		expect( getStoredOptions().show_version ).toBe( false );

		await page.goto( listingLink );

		// The name stays, the number goes -- and the entry is still an entry.
		await expect( page.locator( '.entry-content' ) ).toContainText( 'WP-PluginsUsed' );
		await expect( page.locator( '.entry-content' ) ).not.toContainText( /WP-PluginsUsed \d+\.\d+/ );

		// A checkbox that saves when ticked and cannot be unticked again is a bug
		// this collection has shipped before, so both directions are one test.
		await openSettings( page );
		await page.locator( 'input[name="wp_pluginsused_options[show_version]"]' ).check();
		await saveSettings( page );

		expect( getStoredOptions().show_version ).toBe( true );

		await page.goto( listingLink );
		await expect( page.locator( '.entry-content' ) ).toContainText( /WP-PluginsUsed \d+\.\d+/ );
	} );

	test( 'hiding a plugin removes it from the listing and from the counts', async ( { page } ) => {
		const hidden = plugins.inactive[ 0 ];

		await openSettings( page );
		await hideCheckbox( page, hidden ).check();
		await saveSettings( page );

		expect( getStoredOptions().hidden_plugins ).toEqual( [ hidden ] );

		await page.goto( listingLink );

		await expect( page.locator( '.entry-content' ) ).not.toContainText( hidden );

		// The counts are the half a listing assertion misses: a plugin left out
		// of the list but still counted turns the summary into a lie, and the
		// screen promises it is left out of both.
		//
		// Hiding one also takes the summary through its singular forms, which the
		// default two-plugin install never reaches: "There is 1 plugin used" is a
		// different _n() branch from "There are 2 plugins used", and a plugin that
		// got the plural wrong would read as broken on every small site.
		await expect( page.locator( '.entry-content' ) ).toContainText(
			`${ plugins.inactive.length - 1 } inactive plugin`,
		);
		await expect( page.locator( '.entry-content' ) ).toContainText(
			summary( plugins.active.length, plugins.inactive.length - 1 ),
		);

		await openSettings( page );
		await expect( hideCheckbox( page, hidden ) ).toBeChecked();
		await hideCheckbox( page, hidden ).uncheck();
		await saveSettings( page );

		expect( getStoredOptions().hidden_plugins ).toEqual( [] );

		await page.goto( listingLink );
		await expect( page.locator( '.entry-content' ) ).toContainText( hidden );
	} );

	test( 'the success notice is printed once, not twice', async ( { page } ) => {
		await openSettings( page );
		await saveSettings( page );

		// A page registered with add_options_page() has options-head.php run
		// ahead of it, and that file already calls settings_errors(). A screen
		// that calls it again renders every queued notice a second time, one
		// under the other, in what looks like the plugin's own markup. This
		// screen deliberately does not call it -- this is the guard on that.
		await expect( page.locator( '#setting-error-settings_updated' ) ).toHaveCount( 1 );
	} );

	test( 'the Plugins screen carries a Settings link that goes to the screen', async ( { page } ) => {
		await page.goto( PLUGINS_URL );

		const row = page.locator( 'tr[data-slug="wp-pluginsused"]' );

		await expect( row ).toHaveCount( 1 );
		await row.getByRole( 'link', { name: 'Settings' } ).click();

		await expect( page.getByRole( 'heading', { name: 'Plugins Used' } ) ).toBeVisible();
	} );

	test( 'a subscriber gets neither the menu item nor the screen, and an administrator gets both', async ( {
		page,
		requestUtils,
	} ) => {
		// Both directions on purpose. "The subscriber sees nothing" passes with
		// the plugin deactivated, because there is nothing to see either way; the
		// administrator half is what proves the gate is the capability.
		await page.goto( '/wp-admin/options-general.php' );
		await expect( page.locator( '#adminmenu' ) ).toContainText( 'WP-PluginsUsed' );

		await openSettings( page );

		const { context, subscriber } = await loginAsSubscriber( page, requestUtils );

		await subscriber.goto( '/wp-admin/index.php' );
		await expect( subscriber.locator( '#adminmenu' ).getByText( 'WP-PluginsUsed' ) ).toHaveCount( 0 );

		await subscriber.goto( SETTINGS_URL );
		await expect( subscriber.locator( 'body' ) ).toContainText( /not allowed to access this page/ );

		await context.close();
	} );

	test( 'the capability filter is what decides, and it decides both the menu and the screen', async ( {
		page,
		requestUtils,
	} ) => {
		// The filter exists so a site can hand this screen to somebody who is
		// not an administrator without handing them everything else. It has to
		// move two gates, not one: add_options_page() reads it for the menu and
		// render_page() reads it again before printing anything, and a screen
		// that let the menu through but printed nothing would look exactly like
		// a broken plugin.
		//
		// The fixture mu-plugin answers the filter from an option, so it stays
		// inert for the test above -- which is the one that has to keep proving
		// manage_options is the default.
		setFixtureOption( 'wp_pluginsused_e2e_capability', 'read' );

		const { context, subscriber } = await loginAsSubscriber( page, requestUtils );

		try {
			await subscriber.goto( '/wp-admin/index.php' );
			await expect( subscriber.locator( '#adminmenu' ) ).toContainText( 'WP-PluginsUsed' );

			await subscriber.goto( SETTINGS_URL );
			await expect( subscriber.getByRole( 'heading', { name: 'Plugins Used' } ) ).toBeVisible();

			// The form itself, not just the wrapper: render_page() returns
			// early on a failed capability check, which would leave the heading
			// printed by nothing and the fields absent.
			await expect(
				subscriber.locator( 'input[name="wp_pluginsused_options[show_version]"]' ),
			).toBeAttached();
		} finally {
			// Inside a finally, because a filter left answering 'read' would
			// hand this screen to a subscriber for the rest of the run and
			// quietly invalidate the test above it.
			clearFixtureOption( 'wp_pluginsused_e2e_capability' );
			await context.close();
		}
	} );
} );
