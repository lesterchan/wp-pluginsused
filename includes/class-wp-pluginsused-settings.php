<?php
/**
 * The Settings screen for WP-PluginsUsed.
 *
 * Before 2.0.0 the plugin had no admin screen at all: hiding plugins and
 * hiding version numbers both meant editing the plugin's own source, which
 * WordPress then overwrote on the next update.
 *
 * @package WP-PluginsUsed
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders Settings -> WP-PluginsUsed.
 */
class WP_PluginsUsed_Settings {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE = 'wp-pluginsused';

	/**
	 * Settings group passed to register_setting()/settings_fields().
	 *
	 * The group and the row it saves share one name, so there is one string to
	 * get right rather than two that have to be kept in step.
	 *
	 * @var string
	 */
	const GROUP = 'wp_pluginsused_options';

	/**
	 * The capability a settings screen requires.
	 *
	 * WP-PluginsUsed has no data screens and so ships no capability of its own.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * The display section, holding the version-number setting.
	 *
	 * @var string
	 */
	const SECTION_DISPLAY = 'wp_pluginsused_display';

	/**
	 * The hidden-plugins section.
	 *
	 * @var string
	 */
	const SECTION_HIDDEN = 'wp_pluginsused_hidden';

	/**
	 * Hook the screen up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		// Activation hooks do not fire when a plugin is updated, so the upgrade
		// routine is also run on every admin load.
		add_action( 'admin_init', array( 'WP_PluginsUsed_Options', 'maybe_upgrade' ) );

		add_filter(
			'plugin_action_links_' . plugin_basename( WP_PLUGINSUSED_MAIN_FILE ),
			array( __CLASS__, 'action_links' )
		);
	}

	/**
	 * The capability required for a given context.
	 *
	 * Every capability check in the plugin goes through here, so a site that
	 * hands this screen to somebody other than an administrator changes one
	 * thing rather than hunting for current_user_can() calls.
	 *
	 * @param string $context What the capability is being checked for.
	 * @return string
	 */
	public static function capability( $context = 'settings' ) {
		/**
		 * Filters the capability required to manage the plugin.
		 *
		 * @since 2.0.0
		 *
		 * @param string $capability The required capability.
		 * @param string $context    What the capability is being checked for.
		 */
		return (string) apply_filters( 'wp_pluginsused_capability', self::CAPABILITY, $context );
	}

	/**
	 * Add a Settings link on the Plugins screen row.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		$link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ),
			esc_html__( 'Settings', 'wp-pluginsused' )
		);

		array_unshift( $links, $link );

		return $links;
	}

	/**
	 * Register the submenu page.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_options_page(
			__( 'Plugins Used Settings', 'wp-pluginsused' ),
			__( 'WP-PluginsUsed', 'wp-pluginsused' ),
			self::capability(),
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the setting, its sections and its fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			self::GROUP,
			WP_PluginsUsed_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WP_PluginsUsed_Options', 'sanitize' ),
				'default'           => WP_PluginsUsed_Options::defaults(),
			)
		);

		add_settings_section(
			self::SECTION_DISPLAY,
			__( 'Display', 'wp-pluginsused' ),
			'__return_false',
			self::PAGE
		);

		add_settings_field(
			'wp_pluginsused_show_version',
			__( 'Version numbers', 'wp-pluginsused' ),
			array( __CLASS__, 'field_show_version' ),
			self::PAGE,
			self::SECTION_DISPLAY
		);

		add_settings_section(
			self::SECTION_HIDDEN,
			__( 'Hidden Plugins', 'wp-pluginsused' ),
			array( __CLASS__, 'section_hidden' ),
			self::PAGE
		);

		add_settings_field(
			'wp_pluginsused_hidden_plugins',
			__( 'Plugins to hide', 'wp-pluginsused' ),
			array( __CLASS__, 'field_hidden_plugins' ),
			self::PAGE,
			self::SECTION_HIDDEN
		);
	}

	/**
	 * The "show version numbers" checkbox.
	 *
	 * @return void
	 */
	public static function field_show_version() {
		$options = WP_PluginsUsed_Options::get();
		$name    = WP_PluginsUsed_Options::OPTION . '[show_version]';

		printf(
			'<label><input type="checkbox" name="%s" value="1"%s /> %s</label>',
			esc_attr( $name ),
			checked( ! empty( $options['show_version'] ), true, false ),
			esc_html__( 'Show each plugin&#8217;s version number next to its name', 'wp-pluginsused' )
		);
	}

	/**
	 * Explanatory copy for the hidden plugins section.
	 *
	 * @return void
	 */
	public static function section_hidden() {
		printf(
			'<p>%s</p>',
			esc_html__( 'Tick any plugin you do not want listed. Hidden plugins are left out of the counts as well as the listings.', 'wp-pluginsused' )
		);
	}

	/**
	 * The hidden-plugins checkbox list.
	 *
	 * @return void
	 */
	public static function field_hidden_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$options = WP_PluginsUsed_Options::get();
		$hidden  = $options['hidden_plugins'];
		$name    = WP_PluginsUsed_Options::OPTION . '[hidden_plugins][]';

		$names = array();

		foreach ( get_plugins() as $data ) {
			if ( ! empty( $data['Name'] ) ) {
				$names[] = $data['Name'];
			}
		}

		$names = array_values( array_unique( $names ) );

		if ( empty( $names ) ) {
			printf( '<p>%s</p>', esc_html__( 'No plugins found.', 'wp-pluginsused' ) );

			return;
		}

		echo '<fieldset>';
		printf(
			'<legend class="screen-reader-text"><span>%s</span></legend>',
			esc_html__( 'Plugins to hide', 'wp-pluginsused' )
		);

		// One per line, the way core's own checkbox lists do it. A style
		// attribute here would be the only inline CSS in the plugin.
		foreach ( $names as $plugin_name ) {
			printf(
				'<label><input type="checkbox" name="%s" value="%s"%s /> %s</label><br />',
				esc_attr( $name ),
				esc_attr( $plugin_name ),
				checked( in_array( $plugin_name, $hidden, true ), true, false ),
				esc_html( $plugin_name )
			);
		}

		echo '</fieldset>';

		/*
		 * The filter merges into whatever is ticked here, so a site can still
		 * hide a plugin this screen cannot see.
		 */
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Plugins hidden through the wp_pluginsused_hidden_plugins filter are not shown here, and stay hidden regardless.', 'wp-pluginsused' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'Plugins Used Settings', 'wp-pluginsused' ) );

		printf(
			'<p>%s</p>',
			esc_html__( 'Add the shortcodes below to any post or page to list the plugins this site uses.', 'wp-pluginsused' )
		);

		echo '<p><code>[stats_pluginsused]</code> <code>[active_pluginsused]</code> <code>[inactive_pluginsused]</code></p>';

		echo '<form action="options.php" method="post">';
		settings_fields( self::GROUP );
		do_settings_sections( self::PAGE );
		submit_button();
		echo '</form>';
		echo '</div>';
	}
}
