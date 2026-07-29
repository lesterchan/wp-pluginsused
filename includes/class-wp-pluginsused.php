<?php
/**
 * WP-PluginsUsed bootstrap.
 *
 * @package WP-PluginsUsed
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's shortcodes and admin screen up.
 */
class WP_PluginsUsed {

	/**
	 * Sole instance.
	 *
	 * @var WP_PluginsUsed|null
	 */
	private static $instance = null;

	/**
	 * Retrieve, creating on first call.
	 *
	 * @return WP_PluginsUsed
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	private function __construct() {
		add_shortcode( 'stats_pluginsused', array( $this, 'shortcode_stats' ) );
		add_shortcode( 'active_pluginsused', array( $this, 'shortcode_active' ) );
		add_shortcode( 'inactive_pluginsused', array( $this, 'shortcode_inactive' ) );

		if ( is_admin() ) {
			WP_PluginsUsed_Settings::init();
		}
	}

	/**
	 * Handle [stats_pluginsused].
	 *
	 * @return string
	 */
	public function shortcode_stats() {
		return WP_PluginsUsed_Template::render( 'stats' );
	}

	/**
	 * Handle [active_pluginsused].
	 *
	 * @return string
	 */
	public function shortcode_active() {
		return WP_PluginsUsed_Template::render( 'active' );
	}

	/**
	 * Handle [inactive_pluginsused].
	 *
	 * @return string
	 */
	public function shortcode_inactive() {
		return WP_PluginsUsed_Template::render( 'inactive' );
	}
}
