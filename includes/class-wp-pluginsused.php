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

		/*
		 * The collected listing is cached for the request, and on multisite a
		 * request can render for more than one site -- a network aggregation
		 * plugin, a REST call that switches blogs, any cross-site loop. Without
		 * this, site A's active/inactive split, A's hidden-plugins list and A's
		 * version setting were applied to site B's page, so a plugin hidden on B
		 * could be published on B.
		 */
		add_action( 'switch_blog', array( 'WP_PluginsUsed_Template', 'reset_cache' ) );

		// Must be registered while the plugin file is still being loaded, which
		// is when this constructor runs.
		register_activation_hook( WP_PLUGINSUSED_MAIN_FILE, array( __CLASS__, 'activate' ) );

		if ( is_admin() ) {
			WP_PluginsUsed_Settings::init();
		}
	}

	/**
	 * Activation: run the upgrade routine so the version row is stamped.
	 *
	 * @return void
	 */
	public static function activate() {
		WP_PluginsUsed_Options::maybe_upgrade();
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
