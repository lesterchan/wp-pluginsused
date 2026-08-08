# WP-PluginsUsed
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: plugins used, plugin used, plugins use, plugins, plugin  
Requires at least: 6.8  
Tested up to: 7.0  
Stable tag: 2.0.0  
Requires PHP: 8.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display WordPress plugins that you currently have (both active and inactive) onto a post/page.
## Description
WP-PluginsUsed lists the plugins your site has installed, split into active and
inactive, on any post or page you like. It reads what is really in
`wp-content/plugins` every time the page is built, so the list never goes stale.

### Features
* Three shortcodes: a summary line, the active plugins and the inactive ones.
* Each entry links to the plugin's own page and to its author, with the description underneath.
* Version numbers can be shown or left off.
* Any plugin you would rather not advertise can be left out of both the listings and the counts.
* A template tag for themes that would rather call it directly.

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Usage

### Creating a Plugins Used page
1. Go to `WP-Admin -> Pages -> Add New`
2. Type any title you like in the page's title area
3. Copy and paste the following into the page's content area:
```
[stats_pluginsused]
Active Plugins
[active_pluginsused]
Inactive Plugins
[inactive_pluginsused]
```
4. Click 'Publish'

### Settings
Go to `WP-Admin -> Settings -> WP-PluginsUsed` to choose whether version numbers
are shown and to tick any plugins you would rather not list. Hidden plugins are
left out of the counts as well as the listings.

### Template Tags
`display_pluginsused( $type, $display )` renders one of the listings from a theme
template. `$type` is `stats`, `active` or `inactive`; pass `true` as `$display`
to echo instead of return.

### Filters
* `wp_pluginsused_show_version` — whether version numbers are appended to plugin names.
* `wp_pluginsused_hidden_plugins` — array of plugin names to hide, matched against the plugin's `Plugin Name` header.
* `wp_pluginsused_plugins_used` — the collected listing, keyed by `active` and `inactive`, before it is rendered.
* `wp_pluginsused_capability` — the capability required to reach the settings screen.

The first three were renamed in 2.0.0 and the old, unprefixed names do nothing.
See `Upgrade Notice`.

## Frequently Asked Questions

### How do I hide plugin version numbers?
Go to `WP-Admin -> Settings -> WP-PluginsUsed` and untick **Show each plugin's
version number next to its name**.

The `PLUGINSUSED_SHOW_VERSION` constant some sites moved into `wp-config.php` is
no longer read. The checkbox replaces it.

### How do I hide plugins?
Go to `WP-Admin -> Settings -> WP-PluginsUsed` and tick the plugins you want left
out. To do it in code instead:

```php
add_filter( 'wp_pluginsused_hidden_plugins', function ( $hidden ) {
	$hidden[] = 'Plugin Name 1';
	$hidden[] = 'Plugin Name 2';
	return $hidden;
} );
```

Names added by the filter are merged with whatever is ticked on the settings
screen. The `$pluginsused_hidden_plugins` global that did this before 2.0.0 is no
longer read.

### Where did the settings from my edited plugin file go?
Before 2.0.0 the only way to configure this plugin was to edit
`wp-pluginsused.php`, so those edits were lost every time the plugin updated.
They now live in the database and survive updates. Re-apply them once on the
settings screen.

## Screenshots

1. Settings -> WP-PluginsUsed, which chooses whether versions are shown and which plugins are left out
2. The list of active plugins, on a page holding the shortcode

## Changelog

### 2.0.0
* BREAKING: Requires WordPress 6.8 and PHP 8.2, up from 6.0 and 7.4.
* BREAKING: Renamed all three filters: `pluginsused_show_version`, `pluginsused_hidden_plugins` and `pluginsused_plugins_used` are now `wp_pluginsused_show_version`, `wp_pluginsused_hidden_plugins` and `wp_pluginsused_plugins_used`. The old names were dropped outright rather than deprecated, so code still using them stops being called.
* BREAKING: Dropped the `PLUGINSUSED_SHOW_VERSION` constant and the `$pluginsused_hidden_plugins` global, the pre-2.0.0 ways of configuring the plugin by editing its own file. Use the settings screen.
* NEW: Added a settings screen at `Settings -> WP-PluginsUsed`. Version numbers and hidden plugins were previously configurable only by editing `wp-pluginsused.php`, which every plugin update silently reverted.
* NEW: Settings are stored in a single `wp_pluginsused_options` row and the upgrade markers in `wp_pluginsused_version`, and `uninstall.php` removes both on a single site and across a network.
* NEW: Added the `wp_pluginsused_capability` filter, so the settings screen can be handed to a role other than administrator.
* NEW: Replaced the `plugin_active.gif` / `plugin_inactive.gif` markers with inline SVG. They stay sharp on high-density displays, follow the theme's text colour, and add no HTTP requests.
* NEW: Added a PHPUnit test suite and GitHub Actions CI.
* CHANGED: Plugin scanning now uses core's `get_plugins()` instead of a hand-rolled directory walk and header regexes. Ordering is unchanged.
* CHANGED: Restructured into `includes/` as `WP_PluginsUsed_*` classes, with the old procedural functions kept as working deprecated shims.
* CHANGED: Removed `load_plugin_textdomain()`; WordPress has loaded plugin translations automatically since 4.6.
* FIXED: Fixed a stored cross-site scripting hole in the plugin listings. Values taken from plugin headers were written straight into `href`, `src`, `alt` and `title` attributes with only `strip_tags()` applied, which removes tags but leaves quotes — so a plugin whose header contained a double quote could inject a live event handler, and a `Plugin URI` of `javascript:...` was emitted verbatim. Every value is now escaped at its sink.
* FIXED: Network-activated plugins were listed as inactive on multisite, because only `active_plugins` was consulted.
* FIXED: A plugin with no `Plugin URI` or `Author URI` no longer renders an empty `<a href="">`.
* FIXED: `uninstall.php` no longer stops at the hundredth site on multisite, and restores each site inside the loop.
* NOTE: The three phrases making up the summary sentence were reworded so their bold markup is no longer part of the translated text. Existing translations of those three fall back to English until they are retranslated.

## Upgrade Notice

### 2.0.0

Requires WordPress 6.8 and PHP 8.2.

**Security fix.** Up to 1.50 the plugin printed plugin names, authors and links with only their HTML tags stripped, which leaves quotes intact, so a plugin whose name or description contained a double quote could break out and put a working script into any page carrying one of the shortcodes. Every value is now escaped properly.

**The three filters are renamed, and the old names do nothing at all:**

* `pluginsused_show_version` is now `wp_pluginsused_show_version`
* `pluginsused_hidden_plugins` is now `wp_pluginsused_hidden_plugins`
* `pluginsused_plugins_used` is now `wp_pluginsused_plugins_used`

Same arguments, same return value, same place in the page. This one fails silently: nothing errors, the plugin simply stops asking your code what it thinks. The symptom is version numbers you had switched off coming back, or a plugin you had hidden reappearing, days after an update nobody connects it to. Search your theme's `functions.php`, any snippets plugin and `wp-content/mu-plugins` for `pluginsused_`.

**Configuration moved from the plugin file to a settings screen.** 1.50 was configured by editing `wp-pluginsused.php` itself, so every plugin update wiped the changes. Neither escape route is read any more: the `PLUGINSUSED_SHOW_VERSION` constant, often parked in `wp-config.php`, and the `$pluginsused_hidden_plugins` global. Set both at `WP-Admin -> Settings -> WP-PluginsUsed`.

**Smaller changes.** The plugin stores `wp_pluginsused_options` and `wp_pluginsused_version`; deleting it from the Plugins screen removes both. The summary sentence was rewritten so its bold formatting is no longer part of the translated string, so on a non-English site those phrases fall back to English until translate.wordpress.org catches up. The shortcodes, `display_pluginsused()` and the older function names are unchanged.
