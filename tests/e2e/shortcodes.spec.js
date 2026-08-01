/**
 * The three shortcodes and the template tag, on a real page.
 *
 * WP-PluginsUsed has almost no state of its own: what it renders is a reading of
 * the install taken at request time, so the interesting question is never "did
 * the value save" but "does the page agree with the install". Every count here
 * is therefore asked of the site first and compared with what the page says --
 * hard-coding the answer would make the suite agree with itself.
 *
 * The template tag gets its own tests because it is a different code path from
 * the shortcodes, not a synonym for one: the shortcodes return their markup and
 * display_pluginsused() echoes it through wp_kses(). A tests-only mu-plugin
 * exposes it as a shortcode, because a template tag is a theme's job to call and
 * no bundled theme calls this one.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	clearFilterHidden,
	clearFixtureOption,
	createShortcodePost,
	deleteOptions,
	hideCheckbox,
	installedPlugins,
	openSettings,
	removeHostilePlugin,
	setFilterHidden,
	setFixtureOption,
	setOptions,
	summary,
	uniqueTitle,
} = require( './helpers.js' );

/** What the install actually holds, read once and shared by every test here. */
let plugins;

test.describe( 'The listings on a page', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();

		// Anything an earlier run left in wp-content/plugins would change every
		// count below, so the plugins directory starts from a known state too.
		removeHostilePlugin();
		deleteOptions();
		clearFilterHidden();

		plugins = installedPlugins();
	} );

	test.afterAll( async () => {
		deleteOptions();
	} );

	test( 'the fixture really is a site with both an active and an inactive plugin', async ( {
		page,
		requestUtils,
	} ) => {
		// The precondition the rest of the file leans on. On a site with nothing
		// inactive, "the inactive listing does not carry the active plugin" is
		// true because the listing is empty, and every one of these tests would
		// pass while proving nothing.
		expect( plugins.active.length ).toBeGreaterThan( 0 );
		expect( plugins.inactive.length ).toBeGreaterThan( 0 );

		const post = await createShortcodePost(
			requestUtils,
			'[stats_pluginsused]',
			uniqueTitle( 'Stats' ),
		);

		await page.goto( post.link );

		// And the summary is the plugin's own count of the same two lists.
		await expect( page.locator( '.entry-content' ) ).toContainText(
			summary( plugins.active.length, plugins.inactive.length ),
		);
	} );

	test( 'the active listing carries the active plugins and none of the inactive ones', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createShortcodePost(
			requestUtils,
			'[active_pluginsused]',
			uniqueTitle( 'Active' ),
		);

		await page.goto( post.link );

		const content = page.locator( '.entry-content' );

		for ( const name of plugins.active ) {
			await expect( content ).toContainText( name );
		}

		for ( const name of plugins.inactive ) {
			await expect( content ).not.toContainText( name );
		}

		// One entry per plugin and every marker the active one, so a listing that
		// rendered the right names with the wrong state is still a failure.
		await expect( content.locator( 'svg.wp-pluginsused-icon-active' ) ).toHaveCount(
			plugins.active.length,
		);
		await expect( content.locator( 'svg.wp-pluginsused-icon-inactive' ) ).toHaveCount( 0 );
	} );

	test( 'the inactive listing is the mirror image', async ( { page, requestUtils } ) => {
		const post = await createShortcodePost(
			requestUtils,
			'[inactive_pluginsused]',
			uniqueTitle( 'Inactive' ),
		);

		await page.goto( post.link );

		const content = page.locator( '.entry-content' );

		for ( const name of plugins.inactive ) {
			await expect( content ).toContainText( name );
		}

		for ( const name of plugins.active ) {
			await expect( content ).not.toContainText( name );
		}

		await expect( content.locator( 'svg.wp-pluginsused-icon-inactive' ) ).toHaveCount(
			plugins.inactive.length,
		);
		await expect( content.locator( 'svg.wp-pluginsused-icon-active' ) ).toHaveCount( 0 );
	} );

	test( 'an entry links to the plugin and to its author, with the version in the label', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createShortcodePost(
			requestUtils,
			'[active_pluginsused]',
			uniqueTitle( 'Links' ),
		);

		await page.goto( post.link );

		// The plugin under test is the one active plugin whose headers this
		// repository owns, so it is the one entry whose every field is known.
		const entry = page.locator( '.entry-content p', { hasText: 'WP-PluginsUsed' } );

		const pluginLink = entry.getByRole( 'link', { name: /^WP-PluginsUsed / } );

		await expect( pluginLink ).toHaveAttribute(
			'href',
			'https://lesterchan.net/portfolio/programming/php/',
		);

		// The version is appended to the name, and the same string is repeated in
		// the title attribute -- the tooltip a visitor gets on a truncated name.
		await expect( pluginLink ).toHaveText( /^WP-PluginsUsed \d+\.\d+/ );
		await expect( pluginLink ).toHaveAttribute( 'title', await pluginLink.innerText() );

		// The author's own name carries an apostrophe, which is the cheapest
		// possible check that the escaping round-trips rather than mangling.
		await expect( entry ).toContainText( "Lester 'GaMerZ' Chan" );
		await expect( entry.getByRole( 'link', { name: 'url' } ) ).toHaveAttribute(
			'href',
			'https://lesterchan.net',
		);
	} );

	test( 'the three shortcodes agree with each other on one page', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createShortcodePost(
			requestUtils,
			'[stats_pluginsused]\n\n[active_pluginsused]\n\n[inactive_pluginsused]',
			uniqueTitle( 'All three' ),
		);

		await page.goto( post.link );

		const content = page.locator( '.entry-content' );

		// All three read one cached listing per request, so the summary and the
		// two listings can only disagree if that cache is being rebuilt with
		// different settings partway through the page.
		await expect( content ).toContainText(
			summary( plugins.active.length, plugins.inactive.length ),
		);
		await expect( content.locator( 'svg.wp-pluginsused-icon-active' ) ).toHaveCount(
			plugins.active.length,
		);
		await expect( content.locator( 'svg.wp-pluginsused-icon-inactive' ) ).toHaveCount(
			plugins.inactive.length,
		);
	} );

	test( 'the template tag echoes the same listing, icon and all', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createShortcodePost(
			requestUtils,
			'[pluginsused_e2e_tag type="active"]',
			uniqueTitle( 'Template tag' ),
		);

		await page.goto( post.link );

		const tag = page.locator( '.pluginsused-e2e-tag' );

		// display_pluginsused() escapes at its sink with wp_kses() and an
		// allow-list, so this is the path where a tag the list forgot silently
		// disappears. The icon is the whole reason that matters: it is the only
		// SVG the plugin emits, and an allow-list that dropped <path> would leave
		// an empty square that still passes a text assertion.
		await expect( tag.locator( 'svg.wp-pluginsused-icon-active' ) ).toHaveCount(
			plugins.active.length,
		);
		await expect( tag.locator( 'svg.wp-pluginsused-icon-active path' ) ).toHaveCount(
			plugins.active.length,
		);

		for ( const name of plugins.active ) {
			await expect( tag ).toContainText( name );
		}

		await expect( tag.getByRole( 'link', { name: /^WP-PluginsUsed / } ) ).toHaveAttribute(
			'href',
			'https://lesterchan.net/portfolio/programming/php/',
		);
	} );

	test( 'the hidden-plugins filter hides a plugin the settings row never mentions', async ( {
		page,
		requestUtils,
	} ) => {
		const hidden = plugins.inactive[ 0 ];

		setFilterHidden( [ hidden ] );

		try {
			const post = await createShortcodePost(
				requestUtils,
				'[stats_pluginsused]\n\n[inactive_pluginsused]',
				uniqueTitle( 'Filtered' ),
			);

			await page.goto( post.link );

			// The filter is the escape hatch for anything the settings screen
			// cannot see, so it has to hide as thoroughly as a ticked box does --
			// out of the listing and out of the counts both.
			await expect( page.locator( '.entry-content' ) ).not.toContainText( hidden );
			await expect( page.locator( '.entry-content' ) ).toContainText(
				`${ plugins.inactive.length - 1 } inactive plugin`,
			);

			// And the screen says as much: a plugin hidden by the filter is not
			// ticked there, because the row does not know about it. An owner who
			// saw an unticked box and a missing plugin with no explanation would
			// reasonably conclude the plugin was broken.
			await openSettings( page );
			await expect( hideCheckbox( page, hidden ) ).not.toBeChecked();
			await expect( page.locator( '#wpbody' ) ).toContainText(
				'wp_pluginsused_hidden_plugins',
			);
		} finally {
			// A filter left attached decides the outcome of every test after it.
			clearFilterHidden();
		}
	} );

	test( 'the version filter overrules the saved setting, in both directions', async ( {
		page,
		requestUtils,
	} ) => {
		// The setting says yes and the filter says no, which is the ordering the
		// filter exists for: it runs on the value already read from the row, so
		// a site can force version numbers off however the box is ticked.
		setOptions( { show_version: true, hidden_plugins: [] } );

		const post = await createShortcodePost(
			requestUtils,
			'[active_pluginsused]',
			uniqueTitle( 'Filtered version' ),
		);

		try {
			setFixtureOption( 'wp_pluginsused_e2e_show_version', 0 );

			await page.goto( post.link );

			await expect( page.locator( '.entry-content' ) ).toContainText( 'WP-PluginsUsed' );
			await expect( page.locator( '.entry-content' ) ).not.toContainText(
				/WP-PluginsUsed \d+\.\d+/,
			);

			// The other direction, against a row that says no: a filter that
			// could only take things away would be indistinguishable from the
			// setting it is meant to be able to overrule.
			setOptions( { show_version: false, hidden_plugins: [] } );
			setFixtureOption( 'wp_pluginsused_e2e_show_version', 1 );

			await page.goto( post.link );

			await expect( page.locator( '.entry-content' ) ).toContainText(
				/WP-PluginsUsed \d+\.\d+/,
			);
		} finally {
			clearFixtureOption( 'wp_pluginsused_e2e_show_version' );
			deleteOptions();
		}
	} );

	test( 'the listing filter can add an entry that is not on disk, and it is counted', async ( {
		page,
		requestUtils,
	} ) => {
		const invented = uniqueTitle( 'Invented plugin' );

		setFixtureOption( 'wp_pluginsused_e2e_extra_plugin', invented );

		try {
			const post = await createShortcodePost(
				requestUtils,
				'[stats_pluginsused]\n\n[active_pluginsused]',
				uniqueTitle( 'Filtered listing' ),
			);

			await page.goto( post.link );

			const content = page.locator( '.entry-content' );

			// Adding rather than removing, because the removing direction is
			// what the hidden-plugins list already does. What this proves is
			// that the filter's return value is the thing rendered and counted
			// -- a filter whose result was collected and then thrown away would
			// look identical from the hiding side.
			await expect( content ).toContainText( invented );
			await expect( content ).toContainText(
				summary( plugins.active.length + 1, plugins.inactive.length ),
			);
			await expect( content.locator( 'svg.wp-pluginsused-icon-active' ) ).toHaveCount(
				plugins.active.length + 1,
			);

			// The invented entry has a plugin URL and no author URL, which is
			// the only branch of format() the real install cannot produce:
			// every plugin on disk here carries both. So the name is a link,
			// the author is not, and the author's name is still printed.
			const entry = content.locator( 'p', { hasText: invented } );

			await expect( entry.getByRole( 'link', { name: invented } ) ).toHaveAttribute(
				'href',
				'https://example.org/invented',
			);
			await expect( entry ).toContainText( 'The filter' );
			await expect( entry.getByRole( 'link', { name: 'url' } ) ).toHaveCount( 0 );
		} finally {
			clearFixtureOption( 'wp_pluginsused_e2e_extra_plugin' );
		}
	} );

	test( 'the template tag falls back to the inactive listing for a type it does not know', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createShortcodePost(
			requestUtils,
			'[pluginsused_e2e_tag type="nonsense"]',
			uniqueTitle( 'Unknown type' ),
		);

		await page.goto( post.link );

		const tag = page.locator( '.pluginsused-e2e-tag' );

		// Documented behaviour, kept from before 2.0.0: anything that is not
		// 'stats' or 'active' renders the inactive listing rather than nothing at
		// all, so a theme calling it with a typo keeps working.
		await expect( tag.locator( 'svg.wp-pluginsused-icon-inactive' ) ).toHaveCount(
			plugins.inactive.length,
		);
		await expect( tag.locator( 'svg.wp-pluginsused-icon-active' ) ).toHaveCount( 0 );
	} );
} );
