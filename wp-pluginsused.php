<?php
/*
Plugin Name: WP-PluginsUsed
Plugin URI: http://lesterchan.net/portfolio/programming/php/
Description: Display WordPress plugins that you currently have (both active and inactive) onto a post/page.
Version: 1.50.2
Author: Lester 'GaMerZ' Chan
Author URI: https://lesterchan.net
Text Domain: wp-pluginsused
*/


/*
	Copyright 2021  Lester Chan  (email : lesterchan@gmail.com)

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


### Define: Show Plugin Version Number?
define( 'PLUGINSUSED_SHOW_VERSION', true );


### Variable: Plugins To Hide?
$pluginsused_hidden_plugins = array();


### Create Text Domain For Translations
add_action( 'init', 'pluginsused_textdomain' );
function pluginsused_textdomain() {
	load_plugin_textdomain( 'wp-pluginsused', false, 'wp-pluginsused' );
}


### Function: WordPress Get Plugin Data
function get_pluginsused_data( $plugin_file ) {
	$plugin_data = implode('', file( $plugin_file ) );
	preg_match("|Plugin Name:(.*)|i", $plugin_data, $plugin_name);
	preg_match("|Plugin URI:(.*)|i", $plugin_data, $plugin_uri);
	preg_match("|Description:(.*)|i", $plugin_data, $description);
	preg_match("|Author:(.*)|i", $plugin_data, $author_name);
	preg_match("|Author URI:(.*)|i", $plugin_data, $author_uri);
	if (preg_match("|Version:(.*)|i", $plugin_data, $version)) {
		$version = trim($version[1]);
	} else {
		$version = '';
	}
	$plugin_name    = ! empty( $plugin_name[1] ) ? trim( $plugin_name[1] ) : '';
	$plugin_uri     = ! empty( $plugin_uri[1] ) ? trim( $plugin_uri[1] ) : '';
	$description    = ! empty( $description[1] ) ? wptexturize( trim( $description[1] ) )  : '';
	$author         = ! empty( $author_name[1] ) ? trim( $author_name[1] ) : '';
	$author_uri     = ! empty( $author_uri[1] ) ? trim( $author_uri[1] ) : '';

	return array(
		'Plugin_Name'   => $plugin_name,
		'Plugin_URI'    => $plugin_uri,
		'Description'   => $description,
		'Author'        => $author,
		'Author_URI'    => $author_uri,
		'Version'       => $version
	);
}


### Function: WordPress Get Plugins
function get_pluginsused() {
	global $wp_plugins;
	if ( isset( $wp_plugins ) ) {
		return $wp_plugins;
	}
	$wp_plugins = array();
	$plugin_root = WP_PLUGIN_DIR;
	$plugins_dir = @dir( $plugin_root );
	$plugin_files = array();
	if ( $plugins_dir ) {
		while( ( $file = $plugins_dir->read() ) !== false ) {
			if ( $file[0] === '.' ) {
				continue;
			}
			if ( is_dir( $plugin_root . '/' . $file ) ) {
				$plugins_subdir = @dir( $plugin_root . '/' . $file );
				if ( $plugins_subdir ) {
					while ( ( $subfile = $plugins_subdir->read()) !== false ) {
						if ( $subfile[0] === '.' ) {
							continue;
						}
						if ( substr( $subfile, -4 ) === '.php' ) {
							$plugin_files[] = $file .'/'. $subfile;
						}
					}
				}
			} else {
				if ( substr( $file, -4 ) === '.php' ) {
					$plugin_files[] = $file;
				}
			}
		}
	}
	if ( empty( $plugins_dir) || empty( $plugin_files ) ) {
		return $wp_plugins;
	}
	foreach ( $plugin_files as $plugin_file ) {
		if ( ! is_readable( $plugin_root . '/' . $plugin_file ) ) {
			continue;
		}
		$plugin_data = get_pluginsused_data( $plugin_root . '/' . $plugin_file );
		if ( empty( $plugin_data['Plugin_Name'] ) ) {
			continue;
		}
		$wp_plugins[ plugin_basename( $plugin_file ) ] = $plugin_data;
	}

	uasort( $wp_plugins, 'pluginsused_sort' );

	return $wp_plugins;
}

function pluginsused_sort($a, $b) {
	return strnatcasecmp( $a['Plugin_Name'], $b['Plugin_Name'] );
}

### Function: Process Plugins Used
function process_pluginsused() {
	global $plugins_used, $pluginsused_hidden_plugins;
	if ( empty( $plugins_used ) ) {
		$plugins_used = array();
		$active_plugins = get_option( 'active_plugins' );
		$plugins = get_pluginsused();
		$plugins_allowedtags = array( 'a' => array( 'href' => array(),'title' => array() ),'abbr' => array( 'title' => array() ),'acronym' => array( 'title' => array() ),'code' => array(),'em' => array(),'strong' => array() );
		foreach ($plugins as $plugin_file => $plugin_data ) {
			if ( ! in_array( $plugin_data['Plugin_Name'], $pluginsused_hidden_plugins, true ) ) {
				$plugin_data['Plugin_Name'] = wp_kses( $plugin_data['Plugin_Name'], $plugins_allowedtags );
				$plugin_data['Plugin_URI']  = wp_kses( $plugin_data['Plugin_URI'], $plugins_allowedtags );
				$plugin_data['Description'] = wp_kses( $plugin_data['Description'], $plugins_allowedtags );
				$plugin_data['Author']      = wp_kses( $plugin_data['Author'], $plugins_allowedtags );
				$plugin_data['Author_URI']  = wp_kses( $plugin_data['Author_URI'], $plugins_allowedtags );
				if ( PLUGINSUSED_SHOW_VERSION ) {
					$plugin_data['Version'] = wp_kses( $plugin_data['Version'], $plugins_allowedtags );
				} else {
					$plugin_data['Version'] = '';
				}
				if ( ! empty( $active_plugins ) && in_array( $plugin_file, $active_plugins, true ) ) {
					$plugins_used['active'][] = $plugin_data;
				} else {
					$plugins_used['inactive'][] = $plugin_data;
				}
			}
		}
	}
}


### Function: Display Plugins
function display_pluginsused($type, $display = false) {
	global $plugins_used;
	$temp = '';
	if ( empty( $plugins_used ) ) {
		process_pluginsused();
	}
	if ( $type === 'stats' ) {
		$total_active_pluginsused   = ! empty( $plugins_used['active'] ) ? count( $plugins_used['active'] ) : 0;
		$total_inactive_pluginsused = ! empty( $plugins_used['inactive'] ) ? count( $plugins_used['inactive'] ) : 0;
		$total_pluginsused = ( $total_active_pluginsused + $total_inactive_pluginsused );
		$temp = sprintf( _n( 'There is <strong>%s</strong> plugin used:', 'There are <strong>%s</strong> plugins used:', $total_pluginsused, 'wp-pluginsused' ), number_format_i18n( $total_pluginsused ) ) .' ' . sprintf( _n( '<strong>%s active plugin</strong>','<strong>%s active plugins</strong>', $total_active_pluginsused, 'wp-pluginsused' ), number_format_i18n( $total_active_pluginsused ) ) . ' ' . __( 'and', 'wp-pluginsused' ) . ' ' . sprintf( _n( '<strong>%s inactive plugin</strong>.', '<strong>%s inactive plugins</strong>.', $total_inactive_pluginsused, 'wp-pluginsused' ), number_format_i18n( $total_inactive_pluginsused ) );
	} else if ( $type === 'active' ) {
		if ( ! empty( $plugins_used['active'] ) ) {
			foreach( (array) $plugins_used['active'] as $active_plugin ) {
				$temp .= pluginsused_format_display( $active_plugin );
			}
		}
	} else {
		if ( ! empty( $plugins_used['inactive'] ) ) {
			foreach( (array) $plugins_used['inactive'] as $inactive_plugin ) {
				$temp .= pluginsused_format_display( $inactive_plugin, 'inactive' );
			}
		}
	}
	if( $display ) {
		echo $temp;
	} else {
		return $temp;
	}
}


function pluginsused_format_display( $plugin, $plugin_type = 'active' ) {
	$plugin['Plugin_Name']    = strip_tags( $plugin['Plugin_Name'] );
	$plugin['Plugin_URI']     = strip_tags( $plugin['Plugin_URI'] );
	$plugin['Description']    = strip_tags( $plugin['Description'] );
	$plugin['Version']        = strip_tags( $plugin['Version'] );
	$plugin['Author']         = strip_tags( $plugin['Author'] );
	$plugin['Author_URI']     = strip_tags( $plugin['Author_URI'] );
	$plugin['Version']        = strip_tags( $plugin['Version'] );
	$icon = plugins_url( 'wp-pluginsused/images/plugin_active.gif' );
	if ( $plugin_type === 'inactive') {
		$icon = plugins_url( 'wp-pluginsused/images/plugin_inactive.gif' );
	}

	return '<p><img src="' . $icon . '" alt="' . $plugin['Plugin_Name'] . ' ' . $plugin['Version'] . '" title="' . $plugin['Plugin_Name'] . ' ' . $plugin['Version'] . '" style="vertical-align: middle;" />&nbsp;&nbsp;<strong><a href="' . $plugin['Plugin_URI'] . '" title="' . $plugin['Plugin_Name'] . ' ' . $plugin['Version'] . '">' . $plugin['Plugin_Name'] . ' ' . $plugin['Version'] . '</a></strong><br /><strong>&raquo; ' . $plugin['Author'] . ' (<a href="' . $plugin['Author_URI'] . '" title="' . $plugin['Author'] . '">' . __( 'url', 'wp-pluginsused' ) . '</a>)</strong><br />' . $plugin['Description'] . '</p>';
}


### Function: Short Code For Inserting Plugins Used Into Page
add_shortcode( 'stats_pluginsused', 'pluginsused_stats_shortcode' );
add_shortcode( 'active_pluginsused', 'pluginsused_active_shortcode' );
add_shortcode( 'inactive_pluginsused', 'pluginsused_inactive_shortcode' );
function pluginsused_stats_shortcode() {
	return display_pluginsused( 'stats' );
}
function pluginsused_active_shortcode() {
	return display_pluginsused( 'active' );
}
function pluginsused_inactive_shortcode() {
	return display_pluginsused( 'inactive' );
}