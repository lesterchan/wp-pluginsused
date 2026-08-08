<?php
/**
 * Plugin Name: WP-PluginsUsed
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Display WordPress plugins that you currently have (both active and inactive) onto a post/page.
 * Version: 2.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-pluginsused
 * Domain Path: /languages
 *
 * @package WP-PluginsUsed
 */

/*
	Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*/

defined( 'ABSPATH' ) || exit;

/**
 * WP-PluginsUsed version. The last-run value is kept in the wp_pluginsused_version row.
 */
define( 'WP_PLUGINSUSED_VERSION', '2.0.0' );

/**
 * Schema counter. Bumped only when the stored rows need reshaping.
 */
define( 'WP_PLUGINSUSED_DB_VERSION', '1' );

/**
 * WP-PluginsUsed slug, which is also the text domain.
 */
define( 'WP_PLUGINSUSED_SLUG', 'wp-pluginsused' );

/**
 * WP-PluginsUsed main file.
 */
define( 'WP_PLUGINSUSED_MAIN_FILE', __FILE__ );

/**
 * WP-PluginsUsed directory, with a trailing slash.
 */
define( 'WP_PLUGINSUSED_DIR', plugin_dir_path( __FILE__ ) );

/**
 * WP-PluginsUsed URL, with a trailing slash.
 */
define( 'WP_PLUGINSUSED_URL', plugin_dir_url( __FILE__ ) );

require_once WP_PLUGINSUSED_DIR . 'includes/class-wp-pluginsused-options.php';
require_once WP_PLUGINSUSED_DIR . 'includes/class-wp-pluginsused-template.php';
require_once WP_PLUGINSUSED_DIR . 'includes/class-wp-pluginsused-settings.php';
require_once WP_PLUGINSUSED_DIR . 'includes/class-wp-pluginsused-blocks.php';
require_once WP_PLUGINSUSED_DIR . 'includes/class-wp-pluginsused.php';
require_once WP_PLUGINSUSED_DIR . 'includes/template-tags.php';
require_once WP_PLUGINSUSED_DIR . 'includes/deprecated.php';

WP_PluginsUsed::get_instance();
WP_PluginsUsed_Blocks::init();
