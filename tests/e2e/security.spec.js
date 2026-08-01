/**
 * A hostile plugin header, rendered on every surface that renders one.
 *
 * This plugin stores almost nothing, so the value it can be attacked with is not
 * one of its own: it is a plugin header, read fresh from wp-content/plugins on
 * every request. Anyone who can put a file in that directory -- a compromised
 * install, an abandoned plugin from a zip site, a supply-chain update -- chooses
 * those strings, and the plugin then prints them into a link, a title attribute
 * and a checkbox value.
 *
 * So the fixture is a real plugin file with real headers, left deactivated:
 * WordPress never runs a line of it, and the listing picks it up all the same.
 *
 * The assertion has two halves everywhere. The sentinel the payload would set
 * must never become defined, and the payload's text must still be on the page --
 * escaping that ate the value entirely passes the first half and is its own bug,
 * because a listing that silently omits a plugin is exactly the wrong answer for
 * a plugin whose whole job is listing plugins.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	createShortcodePost,
	deleteOptions,
	hideCheckbox,
	openSettings,
	removeHostilePlugin,
	uniqueTitle,
	writeHostilePlugin,
} = require( './helpers.js' );

const SCRIPT_PAYLOAD = '<script>window.__pwned = 1;</script>';
const IMG_PAYLOAD = '<img src=x onerror="window.__pwned = 1">';
const ATTR_PAYLOAD = '" onmouseover="window.__pwned = 1';

/** The name the hostile plugin claims, which is also how the listing sorts it. */
const HOSTILE_NAME = `Zed hostile ${ ATTR_PAYLOAD }`;

/**
 * Whether any payload managed to run.
 *
 * @param {import('@playwright/test').Page} page Page to ask.
 * @return {Promise<boolean>} True if the sentinel was set.
 */
function pwned( page ) {
	return page.evaluate( () => window.__pwned === 1 );
}

/**
 * Put the hostile plugin on disk, with every header carrying a payload.
 *
 * @return {void}
 */
function installHostilePlugin() {
	writeHostilePlugin( {
		'Plugin Name': HOSTILE_NAME,
		'Plugin URI': 'javascript:window.__pwned=1',
		Description: `Description ${ SCRIPT_PAYLOAD } ${ IMG_PAYLOAD }`,
		Author: `Author ${ ATTR_PAYLOAD }`,
		'Author URI': 'javascript:window.__pwned=1',
		Version: `9.9 ${ SCRIPT_PAYLOAD }`,
	} );
}

test.describe( 'A hostile plugin header stays inert', () => {
	test.beforeEach( async () => {
		deleteOptions();
		installHostilePlugin();
	} );

	test.afterEach( async () => {
		// The plugins directory outlives the test, and a leftover fixture would
		// change every count the rest of the suite asserts on.
		removeHostilePlugin();
		deleteOptions();
	} );

	test( 'the front-end listing renders every payload as text and runs none of it', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createShortcodePost(
			requestUtils,
			'[inactive_pluginsused]',
			uniqueTitle( 'Hostile listing' ),
		);

		await page.goto( post.link );

		const content = page.locator( '.entry-content' );

		// The fixture really is in the listing. Without this the assertions
		// below would all pass on a page that never mentioned it.
		await expect( content ).toContainText( 'Zed hostile' );

		expect( await pwned( page ) ).toBe( false );

		// Nothing that runs. The entry is built from four escaped sinks and a
		// title attribute, and the attribute is the one that a bare quote breaks
		// out of -- which is why the name carries the attribute payload.
		await expect( content.locator( 'script' ) ).toHaveCount( 0 );
		await expect( content.locator( '[onerror]' ) ).toHaveCount( 0 );
		await expect( content.locator( '[onmouseover]' ) ).toHaveCount( 0 );

		// And the values survived. Markup is not among them: the plugin runs every
		// header through wp_strip_all_tags(), as it did before 2.0.0, so the
		// script and image elements are gone along with their contents. What must
		// survive is the text a header legitimately holds, and the attribute
		// payload is exactly that -- a quote and some words, no tags at all -- so
		// it is the half of the fixture that proves nothing was swallowed.
		await expect( content ).toContainText( 'onmouseover' );
		await expect( content ).toContainText( 'window.__pwned' );
		await expect( content ).toContainText( 'Description' );

		// esc_url() empties an unsafe protocol, and the entry only links when the
		// URL survives it, so the hostile entry is plain text where a well-formed
		// one would be a link.
		await expect( content.locator( 'a[href^="javascript:"]' ) ).toHaveCount( 0 );
	} );

	test( 'the template tag renders it as text too, through its own escaping', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createShortcodePost(
			requestUtils,
			'[pluginsused_e2e_tag type="inactive"]',
			uniqueTitle( 'Hostile template tag' ),
		);

		await page.goto( post.link );

		const tag = page.locator( '.pluginsused-e2e-tag' );

		// A separate test from the shortcode on purpose: this path escapes with
		// wp_kses() and an allow-list rather than by returning already-escaped
		// markup, and a payload can be inert in one and live in the other.
		await expect( tag ).toContainText( 'Zed hostile' );

		expect( await pwned( page ) ).toBe( false );

		await expect( tag.locator( 'script' ) ).toHaveCount( 0 );
		await expect( tag.locator( '[onerror]' ) ).toHaveCount( 0 );
		await expect( tag.locator( '[onmouseover]' ) ).toHaveCount( 0 );
		await expect( tag ).toContainText( 'onmouseover' );
	} );

	test( 'the settings screen lists it as text, in the label and in the value', async ( { page } ) => {
		await openSettings( page );

		expect( await pwned( page ) ).toBe( false );

		// The hide list prints each plugin name twice: once as a label a person
		// reads, and once as the value attribute the form posts back. The value
		// is the dangerous one, and it is the one an escaping mistake is easiest
		// to make in, because it is not the copy anybody looks at.
		const checkbox = hideCheckbox( page, HOSTILE_NAME );

		await expect( checkbox ).toHaveCount( 1 );
		await expect( page.locator( '#wpbody' ) ).toContainText( 'Zed hostile' );
		await expect( page.locator( '#wpbody [onmouseover]' ) ).toHaveCount( 0 );
		await expect( page.locator( '#wpbody [onerror]' ) ).toHaveCount( 0 );

		// And it still works as a control: ticking it and saving must hide the
		// plugin the value names, which only happens if the value round-tripped
		// byte for byte rather than being escaped into a different string.
		await checkbox.check();
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();
		await expect( page.locator( '#setting-error-settings_updated' ) ).toContainText( 'Settings saved.' );

		await expect( hideCheckbox( page, HOSTILE_NAME ) ).toBeChecked();
	} );
} );
