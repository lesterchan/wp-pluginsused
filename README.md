# WP-PluginsUsed
Contributors: GamerZ  
Donate link: http://lesterchan.net/site/donation/  
Tags: use, used, plugin, plugins, plugin used, plugins used, plugins use  
Requires at least: 2.8  
Tested up to: 5.4  
Stable tag: trunk  

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

### Build Status
[![Build Status](https://travis-ci.org/lesterchan/wp-pluginsused.svg?branch=master)](https://travis-ci.org/lesterchan/wp-pluginsused)

### Development
[https://github.com/lesterchan/wp-pluginsused/](https://github.com/lesterchan/wp-pluginsused/ "https://github.com/lesterchan/wp-pluginsused/")

### Translations
[http://dev.wp-plugins.org/browser/wp-pluginsused/i18n/](http://dev.wp-plugins.org/browser/wp-pluginsused/i18n/ "http://dev.wp-plugins.org/browser/wp-pluginsused/i18n/")

### Credits
* Plugin icon by [Freepik](http://www.freepik.com) from [Flaticon](http://www.flaticon.com)
* Icons courtesy of [FamFamFam](http://www.famfamfam.com/ "FamFamFam")

### Donations
* I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog

### Version 1.50 (01-06-2009)
* NEW: Use _n() Instead Of __ngettext() And _n_noop() Instead Of __ngettext_noop()
* NEW: Hide Plugins

### Version 1.40 (12-12-2008)
*  NEW: Works For WordPress 2.6 Only
*  NEW: Better Translation Using __ngetext() by Anna Ozeritskaya
*  NEW: Right To Left Language Support by Kambiz R. Khojasteh
*  NEW: Uses number_format_i18n()

### Version 1.31 (16-07-2008)
*  NEW: Works For WordPress 2.6

### Version 1.30 (01-06-2008)
* NEW: Works With WordPress 2.5 Only
* NEW: Uses ShortCode API
* NEW: Uses /wp-pluginsused/ Folder Instead Of /pluginsused/
* NEW: Uses wp-pluginsused.php Instead Of pluginsused.php
* NEW: Added Option To Hide Plugins Version Number
* FIXED: Strip Away HTML Codes In Plugin Descriptions

### Version 1.00 (01-10-2007)
* NEW: Initial Release

## Screenshots

1. Embed ShortCode Into Page
2. Active Plugins
3. Inactive Plugins

## Frequently Asked Questions

### To Hide Plugins Version Number
1. Open `wp-pluginsused.php`
2. Find: `define('PLUGINSUSED_SHOW_VERSION', true);`
3. Replace: `define('PLUGINSUSED_SHOW_VERSION', false);`

### To Hide Plugins
1. Open `wp-pluginsused.php`
2. Find: `$pluginsused_hidden_plugins = array();`
3. Replace: `$pluginsused_hidden_plugins = array('Plugin Name 1', 'Plugin Name 2');`
4. Replace `Plugin Name 1` and `Plugin Name 2` with the plugin name you want to hide.
