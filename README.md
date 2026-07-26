# WP-PluginsUsed
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: plugins used, plugin used, plugins use, plugins, plugin  
Requires at least: 6.0  
Tested up to: 7.0  
Stable tag: 2.0.0  
Requires PHP: 7.4  
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display WordPress plugins that you currently have (both active and inactive) onto a post/page.

## Description

### General Usage
1. To create a Plugins Used Page
2. Go to `WP-Admin -> Pages -> Add New`
3. Type any title you like in the page's title area
4. Copy and paste the following in the page's content area:
```
[stats_pluginsused]
Active Plugins
[active_pluginsused]
Inactive Plugins
[inactive_pluginsused]
```
5. Click 'Publish'

### Settings
Go to `WP-Admin -> Settings -> WP-PluginsUsed` to choose whether version numbers
are shown and to tick any plugins you would rather not list. Hidden plugins are
left out of the counts as well as the listings.

### Template Tags
`display_pluginsused( $type, $display )` renders one of the listings from a theme
template. `$type` is `stats`, `active` or `inactive`; pass `true` as `$display`
to echo instead of return.

### Filters
* `pluginsused_show_version` — whether version numbers are appended to plugin names.
* `pluginsused_hidden_plugins` — array of plugin names to hide, matched against the plugin's `Plugin Name` header.
* `pluginsused_plugins_used` — the collected listing, keyed by `active` and `inactive`, before it is rendered.

### Development
[https://github.com/lesterchan/wp-pluginsused/](https://github.com/lesterchan/wp-pluginsused/ "https://github.com/lesterchan/wp-pluginsused/")

### Credits
* Plugin icon by [Freepik](https://www.freepik.com) from [Flaticon](https://www.flaticon.com)

### Donations
* I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog

### 2.0.0
* SECURITY: Fixed a stored cross-site scripting hole in the plugin listings. Values taken from plugin headers were written straight into `href`, `src`, `alt` and `title` attributes with only `strip_tags()` applied, which removes tags but leaves quotes — so a plugin whose header contained a double quote could inject a live event handler, and a `Plugin URI` of `javascript:...` was emitted verbatim. Every value is now escaped at its sink.
* NEW: Added a settings screen at `Settings -> WP-PluginsUsed`. Version numbers and hidden plugins were previously configurable only by editing `wp-pluginsused.php`, which every plugin update silently reverted.
* NEW: Added the `pluginsused_show_version`, `pluginsused_hidden_plugins` and `pluginsused_plugins_used` filters.
* NEW: Settings are stored in a single `pluginsused_options` row, and `uninstall.php` removes it.
* NEW: Replaced the `plugin_active.gif` / `plugin_inactive.gif` markers with inline SVG. They stay sharp on high-density displays, follow the theme's text colour, and add no HTTP requests.
* FIXED: Network-activated plugins were listed as inactive on multisite, because only `active_plugins` was consulted.
* FIXED: A plugin with no `Plugin URI` or `Author URI` no longer renders an empty `<a href="">`.
* FIXED: `uninstall.php` no longer stops at the hundredth site on multisite, and restores each site inside the loop.
* CHANGED: Plugin scanning now uses core's `get_plugins()` instead of a hand-rolled directory walk and header regexes. Ordering is unchanged.
* CHANGED: Restructured into `includes/`, with the old procedural functions kept as working deprecated shims.
* CHANGED: Requires WordPress 6.0 and PHP 7.4.
* CHANGED: Removed `load_plugin_textdomain()`; WordPress has loaded plugin translations automatically since 4.6.

### 1.50.2
* FIXED: Remove create_function

### 1.50 (01-06-2009)
* NEW: Use _n() Instead Of __ngettext() And _n_noop() Instead Of __ngettext_noop()
* NEW: Hide Plugins

### 1.40 (12-12-2008)
*  NEW: Works For WordPress 2.6 Only
*  NEW: Better Translation Using __ngetext() by Anna Ozeritskaya
*  NEW: Right To Left Language Support by Kambiz R. Khojasteh
*  NEW: Uses number_format_i18n()

### 1.31 (16-07-2008)
*  NEW: Works For WordPress 2.6

### 1.30 (01-06-2008)
* NEW: Works With WordPress 2.5 Only
* NEW: Uses ShortCode API
* NEW: Uses /wp-pluginsused/ Folder Instead Of /pluginsused/
* NEW: Uses wp-pluginsused.php Instead Of pluginsused.php
* NEW: Added Option To Hide Plugins Version Number
* FIXED: Strip Away HTML Codes In Plugin Descriptions

### 1.00 (01-10-2007)
* NEW: Initial Release

## Screenshots

1. Embed ShortCode Into Page
2. Active Plugins
3. Inactive Plugins

## Frequently Asked Questions

### How do I hide plugin version numbers?
Go to `WP-Admin -> Settings -> WP-PluginsUsed` and untick **Show each plugin's
version number next to its name**.

Sites that defined the old `PLUGINSUSED_SHOW_VERSION` constant in `wp-config.php`
are still honoured, and the constant overrides the setting.

### How do I hide plugins?
Go to `WP-Admin -> Settings -> WP-PluginsUsed` and tick the plugins you want left
out. To do it in code instead:

```php
add_filter( 'pluginsused_hidden_plugins', function ( $hidden ) {
	$hidden[] = 'Plugin Name 1';
	$hidden[] = 'Plugin Name 2';
	return $hidden;
} );
```

The pre-2.0.0 `$pluginsused_hidden_plugins` global still works and is merged with
whatever is ticked on the settings screen.

### Where did the settings from my edited plugin file go?
Before 2.0.0 the only way to configure this plugin was to edit
`wp-pluginsused.php`, so those edits were lost every time the plugin updated.
They now live in the database and survive updates. Re-apply them once on the
settings screen.
