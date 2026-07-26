<?php
/**
 * Plugin Name: WP-PluginsUsed
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Display WordPress plugins that you currently have (both active and inactive) onto a post/page.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
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
	Copyright 2026 Lester Chan  (email : lesterchan@gmail.com)

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
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

defined( 'ABSPATH' ) || exit;

/**
 * WP-PluginsUsed version.
 */
define( 'WP_PLUGINSUSED_VERSION', '2.0.0' );

/**
 * WP-PluginsUsed main file.
 */
define( 'WP_PLUGINSUSED_MAIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-pluginsused-options.php';
require_once __DIR__ . '/includes/class-pluginsused-template.php';
require_once __DIR__ . '/includes/class-pluginsused-settings.php';
require_once __DIR__ . '/includes/class-pluginsused.php';
require_once __DIR__ . '/template-tags.php';
require_once __DIR__ . '/deprecated.php';

PluginsUsed::get_instance();
