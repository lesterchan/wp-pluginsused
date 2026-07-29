<?php
/**
 * Collects the installed plugins and renders the listings.
 *
 * @package WP-PluginsUsed
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the active/inactive plugin listings and their markup.
 */
class WP_PluginsUsed_Template {

	/**
	 * Per-request cache of the collected listing.
	 *
	 * Mirrors the pre-2.0.0 `$plugins_used` global: the list is gathered once
	 * and reused by all three shortcodes on the same page.
	 *
	 * @var array|null
	 */
	protected static $plugins_used = null;

	/**
	 * Discard the cached listing.
	 *
	 * Needed by the test suite, which changes settings and filters between
	 * assertions within a single request.
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$plugins_used = null;
	}

	/**
	 * Collect the installed plugins, split into active and inactive.
	 *
	 * @return array {
	 *     @type array[] $active   Active plugins, each in the legacy field shape.
	 *     @type array[] $inactive Inactive plugins, each in the legacy field shape.
	 * }
	 */
	public static function get_plugins_used() {
		if ( null !== self::$plugins_used ) {
			return self::$plugins_used;
		}

		/*
		 * get_plugins() lives in an admin include that is not loaded on the
		 * front end, which is where these shortcodes actually run.
		 */
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Already sorted by name with strnatcasecmp(), which is exactly the
		// ordering the plugin's own sort callback applied before 2.0.0.
		$installed = get_plugins();

		$active = (array) get_option( 'active_plugins', array() );

		/*
		 * Network-activated plugins are recorded in a site option instead, and
		 * were previously reported as inactive on multisite. On single site the
		 * option simply does not exist, so this is safe to read unconditionally.
		 */
		$sitewide = (array) get_site_option( 'active_sitewide_plugins', array() );
		$active   = array_merge( $active, array_keys( $sitewide ) );

		$hidden       = WP_PluginsUsed_Options::hidden_plugins();
		$show_version = WP_PluginsUsed_Options::show_version();

		$plugins_used = array(
			'active'   => array(),
			'inactive' => array(),
		);

		foreach ( $installed as $plugin_file => $data ) {
			$name = isset( $data['Name'] ) ? $data['Name'] : '';

			if ( '' === $name || in_array( $name, $hidden, true ) ) {
				continue;
			}

			/*
			 * Legacy field names. pluginsused_format_display() is still
			 * callable from themes and takes an array in this shape.
			 *
			 * Every value is plain text by the time it leaves here: core
			 * returns plugin headers unmarkup'd and untranslated, and any tag
			 * a header happens to contain was stripped before 2.0.0 too.
			 */
			$plugin = array(
				'Plugin_Name' => wp_strip_all_tags( $name ),
				'Plugin_URI'  => wp_strip_all_tags( isset( $data['PluginURI'] ) ? $data['PluginURI'] : '' ),
				'Description' => wp_strip_all_tags( wptexturize( isset( $data['Description'] ) ? $data['Description'] : '' ) ),
				'Author'      => wp_strip_all_tags( isset( $data['Author'] ) ? $data['Author'] : '' ),
				'Author_URI'  => wp_strip_all_tags( isset( $data['AuthorURI'] ) ? $data['AuthorURI'] : '' ),
				'Version'     => $show_version ? wp_strip_all_tags( isset( $data['Version'] ) ? $data['Version'] : '' ) : '',
			);

			$state = in_array( $plugin_file, $active, true ) ? 'active' : 'inactive';

			$plugins_used[ $state ][] = $plugin;
		}

		/**
		 * Filters the collected plugin listing before it is rendered.
		 *
		 * @since 2.0.0
		 *
		 * @param array $plugins_used Listing keyed by 'active' and 'inactive'.
		 */
		self::$plugins_used = apply_filters( 'pluginsused_plugins_used', $plugins_used );

		return self::$plugins_used;
	}

	/**
	 * Render one of the three listings.
	 *
	 * @param string $type One of 'stats', 'active' or 'inactive'. Anything
	 *                     other than 'stats' or 'active' renders the inactive
	 *                     listing, as it did before 2.0.0.
	 * @return string HTML, escaped at every sink.
	 */
	public static function render( $type ) {
		$plugins_used = self::get_plugins_used();

		if ( 'stats' === $type ) {
			return self::render_stats( $plugins_used );
		}

		$state = ( 'active' === $type ) ? 'active' : 'inactive';
		$out   = '';

		foreach ( $plugins_used[ $state ] as $plugin ) {
			$out .= self::format( $plugin, $state );
		}

		return $out;
	}

	/**
	 * Render the summary sentence.
	 *
	 * @param array $plugins_used Collected listing.
	 * @return string
	 */
	protected static function render_stats( $plugins_used ) {
		$active   = count( $plugins_used['active'] );
		$inactive = count( $plugins_used['inactive'] );
		$total    = $active + $inactive;

		/*
		 * Three separate sentences joined by a space, kept exactly as they were
		 * before 2.0.0 so existing translations still match. The <strong> tags
		 * are part of the source strings; the only substitution is a formatted
		 * integer, so the result is safe to concatenate.
		 */
		return sprintf(
			/* translators: %s: total number of plugins. */
			_n( 'There is <strong>%s</strong> plugin used:', 'There are <strong>%s</strong> plugins used:', $total, 'wp-pluginsused' ),
			number_format_i18n( $total )
		) . ' ' . sprintf(
			/* translators: %s: number of active plugins. */
			_n( '<strong>%s active plugin</strong>', '<strong>%s active plugins</strong>', $active, 'wp-pluginsused' ), // phpcs:ignore WordPress.WP.I18n.NoHtmlWrappedStrings -- Pre-2.0.0 msgid; rewording it would orphan every existing translation.
			number_format_i18n( $active )
		) . ' ' . __( 'and', 'wp-pluginsused' ) . ' ' . sprintf(
			/* translators: %s: number of inactive plugins. */
			_n( '<strong>%s inactive plugin</strong>.', '<strong>%s inactive plugins</strong>.', $inactive, 'wp-pluginsused' ),
			number_format_i18n( $inactive )
		);
	}

	/**
	 * Render a single plugin entry.
	 *
	 * Plugin headers are attacker-controlled for anyone able to place a file in
	 * wp-content/plugins, and stripping tags does not stop a value containing a
	 * double quote from breaking out of an attribute. Every value is therefore
	 * escaped at its sink.
	 *
	 * Public because the deprecated pluginsused_format_display() shim forwards
	 * to it.
	 *
	 * @param array  $plugin Plugin fields in the legacy shape.
	 * @param string $state  'active' or 'inactive'.
	 * @return string
	 */
	public static function format( $plugin, $state = 'active' ) {
		$label = trim( $plugin['Plugin_Name'] . ' ' . $plugin['Version'] );

		$out  = '<p>';
		$out .= self::icon( $state );
		$out .= '&nbsp;&nbsp;<strong>';

		$plugin_uri = esc_url( $plugin['Plugin_URI'] );

		if ( '' !== $plugin_uri ) {
			$out .= '<a href="' . $plugin_uri . '" title="' . esc_attr( $label ) . '">' . esc_html( $label ) . '</a>';
		} else {
			$out .= esc_html( $label );
		}

		$out .= '</strong><br /><strong>&raquo; ' . esc_html( $plugin['Author'] );

		$author_uri = esc_url( $plugin['Author_URI'] );

		if ( '' !== $author_uri ) {
			$out .= ' (<a href="' . $author_uri . '" title="' . esc_attr( $plugin['Author'] ) . '">' . esc_html__( 'url', 'wp-pluginsused' ) . '</a>)';
		}

		$out .= '</strong><br />' . esc_html( $plugin['Description'] ) . '</p>';

		return $out;
	}

	/**
	 * The active/inactive marker.
	 *
	 * Inline SVG rather than the GIFs shipped before 2.0.0: it stays crisp at
	 * any density, inherits the surrounding text colour, and costs no HTTP
	 * request. State is conveyed by shape (filled versus outlined) as well as
	 * by an aria-label, so it does not rely on colour alone.
	 *
	 * @param string $state 'active' or 'inactive'.
	 * @return string
	 */
	protected static function icon( $state ) {
		$common = ' width="14" height="14" viewBox="0 0 16 16" role="img" style="vertical-align: middle;"';

		if ( 'active' === $state ) {
			return '<svg class="pluginsused-icon pluginsused-icon-active"' . $common
				. ' aria-label="' . esc_attr__( 'Active plugin', 'wp-pluginsused' ) . '">'
				. '<path fill="currentColor" d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1Zm-.9 10.2L3.6 7.7l1.3-1.3 2.2 2.2 4.1-4.1 1.3 1.3-5.4 5.4Z"/>'
				. '</svg>';
		}

		return '<svg class="pluginsused-icon pluginsused-icon-inactive"' . $common
			. ' aria-label="' . esc_attr__( 'Inactive plugin', 'wp-pluginsused' ) . '">'
			. '<circle cx="8" cy="8" r="6.2" fill="none" stroke="currentColor" stroke-width="1.6" opacity="0.55"/>'
			. '</svg>';
	}
}
