<?php
/**
 * Deprecated functions kept for backwards compatibility.
 *
 * These were the plugin's internals before 2.0.0, but they lived in the global
 * namespace and a theme may well be calling them. They still work; they just
 * forward to the classes now, and warn when WP_DEBUG is on.
 *
 * @package WP-PluginsUsed
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'get_pluginsused_data' ) ) {
	/**
	 * Read a plugin file's headers.
	 *
	 * @deprecated 2.0.0 Use get_plugin_data() from wp-admin/includes/plugin.php.
	 *
	 * @param string $plugin_file Absolute path to the plugin file.
	 * @return array Plugin fields in the legacy shape.
	 */
	function get_pluginsused_data( $plugin_file ) {
		_deprecated_function( __FUNCTION__, '2.0.0', 'get_plugin_data()' );

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$data = get_plugin_data( $plugin_file, false, false );

		return array(
			'Plugin_Name' => $data['Name'],
			'Plugin_URI'  => $data['PluginURI'],
			'Description' => wptexturize( $data['Description'] ),
			'Author'      => $data['Author'],
			'Author_URI'  => $data['AuthorURI'],
			'Version'     => $data['Version'],
		);
	}
}

if ( ! function_exists( 'get_pluginsused' ) ) {
	/**
	 * List every installed plugin, keyed by plugin file.
	 *
	 * @deprecated 2.0.0 Use get_plugins() from wp-admin/includes/plugin.php.
	 *
	 * @return array[] Plugin fields in the legacy shape, keyed by plugin file.
	 */
	function get_pluginsused() {
		_deprecated_function( __FUNCTION__, '2.0.0', 'get_plugins()' );

		global $wp_plugins;

		if ( isset( $wp_plugins ) ) {
			return $wp_plugins;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$wp_plugins = array();

		foreach ( get_plugins() as $plugin_file => $data ) {
			if ( empty( $data['Name'] ) ) {
				continue;
			}

			$wp_plugins[ $plugin_file ] = array(
				'Plugin_Name' => $data['Name'],
				'Plugin_URI'  => $data['PluginURI'],
				'Description' => wptexturize( $data['Description'] ),
				'Author'      => $data['Author'],
				'Author_URI'  => $data['AuthorURI'],
				'Version'     => $data['Version'],
			);
		}

		return $wp_plugins;
	}
}

if ( ! function_exists( 'pluginsused_sort' ) ) {
	/**
	 * Sort two plugins by name.
	 *
	 * @deprecated 2.0.0 get_plugins() already returns its result sorted this way.
	 *
	 * @param array $a First plugin.
	 * @param array $b Second plugin.
	 * @return int
	 */
	function pluginsused_sort( $a, $b ) {
		_deprecated_function( __FUNCTION__, '2.0.0' );

		return strnatcasecmp( $a['Plugin_Name'], $b['Plugin_Name'] );
	}
}

if ( ! function_exists( 'process_pluginsused' ) ) {
	/**
	 * Populate the $plugins_used global.
	 *
	 * @deprecated 2.0.0 Use PluginsUsed_Template::get_plugins_used().
	 *
	 * @return void
	 */
	function process_pluginsused() {
		_deprecated_function( __FUNCTION__, '2.0.0', 'PluginsUsed_Template::get_plugins_used()' );

		$GLOBALS['plugins_used'] = PluginsUsed_Template::get_plugins_used();
	}
}

if ( ! function_exists( 'pluginsused_format_display' ) ) {
	/**
	 * Render a single plugin entry.
	 *
	 * @deprecated 2.0.0 Use PluginsUsed_Template::format().
	 *
	 * @param array  $plugin      Plugin fields in the legacy shape.
	 * @param string $plugin_type 'active' or 'inactive'.
	 * @return string
	 */
	function pluginsused_format_display( $plugin, $plugin_type = 'active' ) {
		_deprecated_function( __FUNCTION__, '2.0.0', 'PluginsUsed_Template::format()' );

		return PluginsUsed_Template::format( $plugin, $plugin_type );
	}
}
