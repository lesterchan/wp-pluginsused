<?php
/**
 * Template tags for WP-PluginsUsed.
 *
 * These are the plugin's public API and their signatures do not change.
 *
 * @package WP-PluginsUsed
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'display_pluginsused' ) ) {
	/**
	 * Render one of the plugin listings.
	 *
	 * @param string $type    'stats', 'active', or 'inactive'. Any other value
	 *                        renders the inactive listing.
	 * @param bool   $display Echo the markup instead of returning it.
	 * @return string|void Markup, or nothing when $display is true.
	 */
	function display_pluginsused( $type, $display = false ) {
		$out = PluginsUsed_Template::render( $type );

		if ( $display ) {
			// Assembled by PluginsUsed_Template, which escapes at every sink.
			echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		return $out;
	}
}
