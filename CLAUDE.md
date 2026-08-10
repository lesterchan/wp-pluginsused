# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What it is

Three shortcodes — `[stats_pluginsused]`, `[active_pluginsused]`,
`[inactive_pluginsused]` — and three blocks wrapping the same three renders,
which put the site's installed plugins on a public page. One settings screen
under Settings for hiding individual plugins and switching version numbers off.
No tables, no front-end assets, and no front-end JavaScript: the only JavaScript
in the plugin is the blocks' editor scripts.

## Data

`wp_pluginsused_options` (`show_version`, `hidden_plugins`) and
`wp_pluginsused_version`, which holds the `plugin` and `db` upgrade markers and
nothing else. **The released 1.50 stored nothing at all** — it was
configured by editing `wp-pluginsused.php`, so every plugin update reverted the
site's settings. This is therefore the plugin's *first* migration, and
`LEGACY_OPTION = 'pluginsused_options'` only ever existed inside pre-release
2.0.0 builds, so it is seen only on a development checkout. The retired escape hatches
were the `PLUGINSUSED_SHOW_VERSION` constant and a `$pluginsused_hidden_plugins`
global; neither is read any more.

## Traps

* **`migrate()` asks `get_option( self::OPTION, false )`, and the second argument
  is load-bearing.** `register_setting()` is passed a `default`, which installs a
  `default_option_wp_pluginsused_options` filter, and `maybe_upgrade()` is hooked
  to `admin_init` *after* `register_settings()` — so a bare `get_option()` answers
  with the defaults array and never with `false`. The "there is no current row
  yet" branch was therefore never taken on the admin path, while the
  `delete_option()` below it ran regardless: the owner's hidden-plugins list was
  read and thrown away. Passing an explicit default defeats the registered one,
  because `filter_default_option()` returns early when a default was passed.

  **Activation and WP-CLI never run `register_setting()`**, so reactivating
  repaired it and every test that went through activation passed. That is the
  whole reason it survived: a migration test that does not register the setting
  first is testing WP-CLI.
  `test_the_migration_folds_the_row_in_on_the_admin_path_too` is the one that
  registers it, and `tests/e2e/upgrade.spec.js` is the one that reaches the same
  path through a browser, where registration happens by itself.

  Two rules follow from the same defect. **Read the row raw when the question is
  "was it written"** — the options accessor merges the defaults, so it cannot
  tell a written row from an absent one. And **seed the shipped defaults, not
  customised values**: a customised fixture's migrated result differs from the
  defaults, so its write lands whatever the read before it did, and the test
  passes straight through the bug.
* **Plugin headers are attacker-controlled** for anyone who can drop a file in
  `wp-content/plugins`, and up to 1.50 the plugin only stripped tags — which
  leaves quotes intact, so a name containing `"` broke out of a `title=`
  attribute and put a working script on any page carrying a shortcode. That is
  the 2.0.0 security fix. Every value is escaped at its sink: `esc_html()`,
  `esc_attr()`, `esc_url()`. `wp_strip_all_tags()` in `get_plugins_used()` is
  *not* the escaping; do not treat it as such.
* **`allowed_html()` must stay in step with what `format()` and
  `render_stats()` emit**, because the template tag `display_pluginsused()`
  echoes through `wp_kses()` while the shortcodes return their markup directly.
  `tests/test-template.php` asserts the two agree — widen the markup without
  widening the list and the suite fails, rather than half the listing being
  silently stripped.
* **`viewbox` is lowercase on purpose and is not a typo** (`icon()`,
  `class-wp-pluginsused-template.php`). Spelled `viewBox`, kses lowercased it on
  the echoing path only, so the template tag and the shortcode emitted different
  bytes for the same icon. HTML parsers apply the SVG case-fixup table, so the
  lowercase form still reaches the DOM as `viewBox` and the icon scales. The
  same comment explains the space before the self-closing slash.
* **Network-activated plugins live in `active_sitewide_plugins`, a site option**,
  not `active_plugins`. Before 2.0.0 they were reported as inactive on every
  multisite. Reading it unconditionally is safe — on single site it simply does
  not exist.
* **The three public filters were renamed and the old names were dropped
  outright**, with no `apply_filters_deprecated()` shim. This one fails
  *silently*: nothing errors, the plugin just stops asking the site's code what
  it thinks, and the symptom (hidden plugins reappearing) shows up days after an
  update nobody connects it to. The Upgrade Notice says to grep for
  `pluginsused_`.
* **`<strong>` moved out of the translated strings** in `render_stats()` and
  into the concatenation. Rendered output is byte-identical to pre-2.0.0, but the
  three msgids changed, so existing translations fall back to English. Recorded
  as a `NOTE:` in the changelog — do not "fix" it by putting the markup back.
* **The blocks are an addition to the shortcodes and never a replacement.** Both
  entry points call `WP_PluginsUsed_Template::render()` and neither calls the
  other — no `do_shortcode()` in the block, no block lookup in the shortcode —
  so `tests/test-blocks.php` can unregister either one and watch the other carry
  on. There are three blocks rather than one with a listing-type attribute
  because none of the shortcodes takes an attribute either, and because a block
  name is fixed in `post_content` for the life of the post where an attribute is
  a value a later default can flip.
* **`build/` is generated, gitignored, and shipped; `src/` is committed and not
  shipped.** `register_block_type_from_metadata()` reads `build/`, so a checkout
  that has never run `bin/build` registers no blocks at all — `register()` skips
  a directory with no `block.json` rather than registering something whose script
  cannot load. `bin/test.sh` and `bin/test-e2e.sh` build first for that reason.
  `bin/build` also writes the silence-is-golden `index.php` into every directory
  it produced, walking them rather than listing them, because webpack does not
  know that rule and a block added later would otherwise ship without one.
* `get_plugins()` lives in an admin include; `get_plugins_used()` requires it
  explicitly because these shortcodes run on the **front end**.
* `WP_PluginsUsed_Template::$plugins_used` is a per-request cache mirroring the
  old `$plugins_used` global. `reset_cache()` exists for the suite, which changes
  settings and filters between assertions in one request.
* The legacy field shape (`Plugin_Name`, `Plugin_URI`, `Author_URI`…) is kept
  because `pluginsused_format_display()` is still callable from themes and takes
  an array in exactly that shape. It is not core's `get_plugins()` shape and
  should not be normalised to it.

## Tests

`bin/test.sh` runs PHPUnit, `bin/test-multisite.sh` the network pass, and
`bin/test-e2e.sh` the Playwright suite. **Run them rather than trusting a note
about their last result** — CI is the authority, and this file cannot be.

`tests/test-escaping.php` is the guard for the security fix above;
`test-template.php` pins the kses allow-list against the emitted markup;
`test-summary.php` covers the three-sentence pluralisation. `test-blocks.php`
pins that each block and its shortcode emit *identical* markup and that a plugin
hidden on the settings screen stays out of a block-rendered page — the one
assertion there that is about a disclosure rather than about tidiness. It
snapshots `$GLOBALS['shortcode_tags']` and re-registers the blocks in
`set_up`/`tear_down`, because both registries are process-global and the tests
that unregister things would otherwise disarm every test after them.

`run_uninstall()` in `helper-testcase.php` is the single include point for
`uninstall.php`, and every test that needs the uninstaller goes through it: a
second `require_once` of that file fatals on redeclare.
