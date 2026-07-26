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
class PluginsUsed {

	/**
	 * Sole instance.
	 *
	 * @var PluginsUsed|null
	 */
	private static $instance = null;

	/**
	 * Retrieve, creating on first call.
	 *
	 * @return PluginsUsed
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
			PluginsUsed_Settings::init();
		}
	}

	/**
	 * Handle [stats_pluginsused].
	 *
	 * @return string
	 */
	public function shortcode_stats() {
		return PluginsUsed_Template::render( 'stats' );
	}

	/**
	 * Handle [active_pluginsused].
	 *
	 * @return string
	 */
	public function shortcode_active() {
		return PluginsUsed_Template::render( 'active' );
	}

	/**
	 * Handle [inactive_pluginsused].
	 *
	 * @return string
	 */
	public function shortcode_inactive() {
		return PluginsUsed_Template::render( 'inactive' );
	}
}
