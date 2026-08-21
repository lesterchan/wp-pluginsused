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

		/*
		 * The plugin headers are cached across requests because reading them
		 * means opening every plugin file, and a visitor on the front end
		 * should not be paying for that. Installing, updating or deleting a
		 * plugin changes what is on disk, so each has to discard the copy.
		 *
		 * Activation and deactivation do not -- the active/inactive split is
		 * read fresh every time -- but they are the moment a plugin uploaded
		 * over FTP is first noticed, and a plugin the cache has never seen is
		 * missing from the listing altogether rather than merely on the wrong
		 * side of it.
		 */
		foreach ( array( 'activated_plugin', 'deactivated_plugin', 'deleted_plugin', 'upgrader_process_complete' ) as $changed ) {
			add_action( $changed, array( 'WP_PluginsUsed_Template', 'flush_headers' ) );
		}

		// Must be registered while the plugin file is still being loaded, which
		// is when this constructor runs.
		register_activation_hook( WP_PLUGINSUSED_MAIN_FILE, array( __CLASS__, 'activate' ) );

		if ( is_admin() ) {
			WP_PluginsUsed_Settings::init();
		}
	}

	/**
	 * Activation: run the upgrade routine so the rows are in their current shape.
	 *
	 * That folds in the pre-2.0.0 settings row and stamps the version markers. The
	 * plugin works correctly with no settings row at all, because the options are
	 * merged over the defaults on every read, so this is about carrying an existing
	 * install forward rather than about seeding a new one.
	 *
	 * The network branch matters because the settings live per site. Without it a
	 * network activation upgrades only whichever site happened to be current, and
	 * every other site keeps its legacy row unread until somebody loads its admin
	 * -- which is the only other thing that runs the upgrade.
	 * 'number' => 0 lifts WP_Site_Query's default cap of 100, and
	 * restore_current_blog() runs inside the loop because switch_to_blog() pushes
	 * onto a stack.
	 *
	 * @param bool $network_wide Whether the plugin is being activated network-wide.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				WP_PluginsUsed_Options::maybe_upgrade();
				restore_current_blog();
			}

			return;
		}

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
