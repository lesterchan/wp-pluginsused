# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

WP-PluginsUsed follows `_standards/STANDARDS.md` in the parent folder, which is
the contract for all nineteen plugins in the collection. Where this file and
that one disagree, that one wins.

## What it is

Three shortcodes — `[pluginsused]`, `[pluginsused_active]`,
`[pluginsused_inactive]` — plus a summary line, that render the site's installed
plugins on a public page. One settings screen under Settings for hiding
individual plugins and switching version numbers off. No tables, no front-end
assets, no JavaScript.

## Data

`wp_pluginsused_options` (`show_version`, `hidden_plugins`) and
`wp_pluginsused_version`. **The released 1.50 stored nothing at all** — it was
configured by editing `wp-pluginsused.php`, so every plugin update reverted the
site's settings. This is therefore the plugin's *first* migration, and
`LEGACY_OPTION = 'pluginsused_options'` only ever existed inside the unreleased
2.0.0, so it is seen only on a development build. The retired escape hatches
were the `PLUGINSUSED_SHOW_VERSION` constant and a `$pluginsused_hidden_plugins`
global; neither is read any more.

## Traps

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
  outright**, per the collection's decision on renamed hooks. This one fails
  *silently*: nothing errors, the plugin just stops asking the site's code what
  it thinks, and the symptom (hidden plugins reappearing) shows up days after an
  update nobody connects it to. The Upgrade Notice says to grep for
  `pluginsused_`.
* **`<strong>` moved out of the translated strings** in `render_stats()` and
  into the concatenation. Rendered output is byte-identical to pre-2.0.0, but the
  three msgids changed, so existing translations fall back to English. Recorded
  as a `NOTE:` in the changelog — do not "fix" it by putting the markup back.
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

`tests/test-escaping.php` is the §7.2.4 guard for the security fix above;
`test-template.php` pins the kses allow-list against the emitted markup;
`test-summary.php` covers the three-sentence pluralisation.
`tests/e2e/upgrade.spec.js` and `security.spec.js` are among the twelve suites
listed as never run to green in `_standards/RESUME.md` — verify before trusting.

`run_uninstall()` in `helper-testcase.php` is the shared include point for
`uninstall.php`; §7.2.1 names this plugin as the example of doing it right,
because a second `require_once` of the uninstaller fatals on redeclare.
