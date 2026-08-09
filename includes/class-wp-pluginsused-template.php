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
	 * Site transient holding the installed plugins' headers.
	 *
	 * @var string
	 */
	const HEADERS_TRANSIENT = 'wp_pluginsused_headers';

	/**
	 * How long the headers are kept before being read off disk again.
	 *
	 * The backstop under the fingerprint below, for the one change neither it
	 * nor any hook can see: a plugin file edited in place, keeping its name, so
	 * that only a header inside it moved.
	 *
	 * @var int
	 */
	const HEADERS_TTL = HOUR_IN_SECONDS;

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
	 * Discard the cached plugin headers.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function flush_headers() {
		delete_site_transient( self::HEADERS_TRANSIENT );
		self::reset_cache();
	}

	/**
	 * The installed plugins' headers, read from disk at most once a day.
	 *
	 * Core's get_plugins() opens the plugins directory, opens every plugin file
	 * in it and reads the first 8KB of each. Core caches that, but in the `plugins`
	 * group, which core registers as non-persistent -- so the scan happens on
	 * every request even on a site running a persistent object cache. That is
	 * an admin-screen cost being paid on a public page: these shortcodes run on
	 * the front end, where any visitor arriving past the page cache pays it, as
	 * many times a second as they care to ask.
	 *
	 * A site transient rather than a plain one because the plugins directory
	 * is one directory for the whole network: a plugin deleted while site A
	 * was in scope is gone from site B too, and a per-site transient would
	 * have left every other site in the network reading it off a stale copy
	 * until the day ran out.
	 *
	 * Everything that changes the answer through WordPress discards this, the
	 * fingerprint catches a plugin that arrived or left without WordPress
	 * involved, and the expiry covers the rest. The hidden-plugins list and the
	 * version setting are deliberately *not* baked in: filtering the headers is
	 * free, so caching before that step means a settings save invalidates
	 * nothing.
	 *
	 * @since 2.0.0
	 *
	 * @return array Installed plugins, keyed by plugin file, in get_plugins() shape.
	 */
	protected static function plugin_headers() {
		$fingerprint = self::plugins_fingerprint();
		$cached      = get_site_transient( self::HEADERS_TRANSIENT );

		if ( isset( $cached['fingerprint'], $cached['plugins'] )
			&& is_array( $cached['plugins'] )
			&& $cached['fingerprint'] === $fingerprint
		) {
			return $cached['plugins'];
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

		set_site_transient(
			self::HEADERS_TRANSIENT,
			array(
				'fingerprint' => $fingerprint,
				'plugins'     => $installed,
			),
			self::HEADERS_TTL
		);

		return $installed;
	}

	/**
	 * What the plugins directory looks like from outside, without opening it up.
	 *
	 * A plugin uploaded over FTP, unzipped by hand or dropped in by a
	 * provisioning script fires none of the hooks that discard the stored
	 * headers, and a plugin listing that does not list a plugin the owner just
	 * installed is the plugin failing at its one job. Waiting for an expiry is
	 * not an answer either: the owner refreshes, sees the old list, and
	 * concludes the thing is broken.
	 *
	 * So the entry names are read every request and the headers are only reused
	 * while they match. That is one directory read against get_plugins()'
	 * directory read plus a file opened and parsed for every plugin in it,
	 * which is the whole of the saving -- listing a directory was never the
	 * expensive part.
	 *
	 * It does not see a header edited inside a file whose name did not change.
	 * upgrader_process_complete covers that when WordPress did it and the
	 * expiry covers it when something else did.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected static function plugins_fingerprint() {
		if ( ! is_dir( WP_PLUGIN_DIR ) ) {
			return '';
		}

		$entries = scandir( WP_PLUGIN_DIR );

		if ( ! is_array( $entries ) ) {
			return '';
		}

		sort( $entries );

		return md5( implode( '|', $entries ) );
	}

	/**
	 * Reduce a plugin name to the form the hidden-plugins list is stored in.
	 *
	 * One place, called on both sides of the comparison, because the two sides
	 * come from different worlds: the stored list has been through the settings
	 * sanitiser and the Name header has been through nothing at all. Whatever
	 * that sanitiser does to a name, this does to the name it is matched
	 * against.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name Plugin name.
	 * @return string
	 */
	public static function normalize_name( $name ) {
		return sanitize_text_field( (string) $name );
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

		$installed = self::plugin_headers();

		$active = (array) get_option( 'active_plugins', array() );

		/*
		 * Network-activated plugins are recorded in a site option instead, and
		 * were previously reported as inactive on multisite. On single site the
		 * option simply does not exist, so this is safe to read unconditionally.
		 */
		$sitewide = (array) get_site_option( 'active_sitewide_plugins', array() );
		$active   = array_merge( $active, array_keys( $sitewide ) );

		/*
		 * Both sides through the same normaliser, and that is the whole of this.
		 *
		 * The list is stored through sanitize_text_field() by the settings
		 * sanitiser, and was compared against the *raw* Name header --
		 * get_plugins() applies nothing but trim() to it. sanitize_text_field()
		 * collapses runs of whitespace, deletes percent-octets and strips tags,
		 * so any plugin whose name contains a double space, a tab, a "%2f"-shaped
		 * substring or a "<" could be ticked as hidden, saved with a success
		 * notice, and go on being listed -- name, author, description and version
		 * -- with the checkbox coming back unticked, which reads as "the setting
		 * did not save" rather than "the suppression is broken".
		 */
		$hidden       = array_map( array( __CLASS__, 'normalize_name' ), WP_PluginsUsed_Options::hidden_plugins() );
		$show_version = WP_PluginsUsed_Options::show_version();

		$plugins_used = array(
			'active'   => array(),
			'inactive' => array(),
		);

		foreach ( $installed as $plugin_file => $data ) {
			$name = isset( $data['Name'] ) ? $data['Name'] : '';

			if ( '' === $name || in_array( self::normalize_name( $name ), $hidden, true ) ) {
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
		 * Renamed from `pluginsused_plugins_used` in 2.0.0.
		 *
		 * @since 2.0.0
		 *
		 * @param array $plugins_used Listing keyed by 'active' and 'inactive'.
		 */
		self::$plugins_used = apply_filters( 'wp_pluginsused_plugins_used', $plugins_used );

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
		 * Three sentences joined by a space, rendering exactly the same string
		 * as before 2.0.0. The <strong> tags moved out of the source strings
		 * and into the concatenation: a translator has no reason to be handed
		 * markup, and a msgid wrapped in a tag is a translation waiting to lose
		 * it. The cost is that these three msgids changed, so their existing
		 * translations fall back to English until they are redone -- recorded
		 * as a NOTE in the changelog.
		 */
		$total_text = sprintf(
			/* translators: %s: total number of plugins, wrapped in <strong>. */
			_n( 'There is %s plugin used:', 'There are %s plugins used:', $total, 'wp-pluginsused' ),
			'<strong>' . number_format_i18n( $total ) . '</strong>'
		);

		$active_text = sprintf(
			/* translators: %s: number of active plugins. */
			_n( '%s active plugin', '%s active plugins', $active, 'wp-pluginsused' ),
			number_format_i18n( $active )
		);

		$inactive_text = sprintf(
			/* translators: %s: number of inactive plugins. */
			_n( '%s inactive plugin', '%s inactive plugins', $inactive, 'wp-pluginsused' ),
			number_format_i18n( $inactive )
		);

		/*
		 * One joining string rather than a bare "and" between concatenations.
		 * The three counts stay separately pluralised -- that part was always
		 * right -- but the order they appear in, and the full stop, were facts
		 * about English held in PHP. A translator handed only "and" cannot move
		 * the inactive count in front of the active one, and several languages
		 * want to. The <strong> stays outside the msgid for the reason above.
		 */
		return sprintf(
			/* translators: 1: the total-plugins sentence, 2: the active count, 3: the inactive count. */
			__( '%1$s %2$s and %3$s.', 'wp-pluginsused' ),
			$total_text,
			'<strong>' . $active_text . '</strong>',
			'<strong>' . $inactive_text . '</strong>'
		);
	}

	/**
	 * The markup the listings are allowed to contain.
	 *
	 * The template tag echoes, so it escapes at its sink like everything else.
	 * Every value inside the listing has already been through esc_html(),
	 * esc_attr() or esc_url(); this allow-list is about the structure around
	 * them, and is written to match exactly what format() and render_stats()
	 * emit -- tests/test-template.php asserts the two agree, so widening the
	 * markup without widening this list fails the suite rather than silently
	 * stripping half the listing.
	 *
	 * @return array Allowed tags in wp_kses() shape.
	 */
	public static function allowed_html() {
		return array(
			'p'      => array(),
			'br'     => array(),
			'strong' => array(),
			'a'      => array(
				'href'  => true,
				'title' => true,
			),
			'svg'    => array(
				'class'      => true,
				'width'      => true,
				'height'     => true,
				'viewbox'    => true,
				'role'       => true,
				'aria-label' => true,
			),
			'path'   => array(
				'fill' => true,
				'd'    => true,
			),
			'circle' => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'opacity'      => true,
			),
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
	 * The class names are scoped under the plugin's slug so a theme can style
	 * them without guessing, and the markup carries no style attribute: the
	 * plugin paints no surface of its own, inherits colour through
	 * currentColor, and ships no stylesheet for a theme to have to override.
	 *
	 * @param string $state 'active' or 'inactive'.
	 * @return string
	 */
	protected static function icon( $state ) {
		/*
		 * Written exactly as wp_kses() would leave it: attribute names
		 * lowercased, and a space before the self-closing slash.
		 *
		 * That matters because there are two ways out of this class. The
		 * shortcodes return their markup, while display_pluginsused() echoes it
		 * through wp_kses(). Spelling this "viewBox" meant the two paths emitted
		 * different bytes for the same icon -- kses lowercased it on the echoing
		 * path only -- so a theme calling the template tag got markup the
		 * shortcode never produced.
		 *
		 * Lowercase is safe: an HTML parser applies its SVG attribute
		 * case-fixup table, so "viewbox" reaches the DOM as viewBox and the icon
		 * scales. It is also what the echoing path has always shipped.
		 */
		$common = ' width="14" height="14" viewbox="0 0 16 16" role="img"';

		if ( 'active' === $state ) {
			return '<svg class="wp-pluginsused-icon wp-pluginsused-icon-active"' . $common
				. ' aria-label="' . esc_attr__( 'Active plugin', 'wp-pluginsused' ) . '">'
				. '<path fill="currentColor" d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1Zm-.9 10.2L3.6 7.7l1.3-1.3 2.2 2.2 4.1-4.1 1.3 1.3-5.4 5.4Z" />'
				. '</svg>';
		}

		return '<svg class="wp-pluginsused-icon wp-pluginsused-icon-inactive"' . $common
			. ' aria-label="' . esc_attr__( 'Inactive plugin', 'wp-pluginsused' ) . '">'
			. '<circle cx="8" cy="8" r="6.2" fill="none" stroke="currentColor" stroke-width="1.6" opacity="0.55" />'
			. '</svg>';
	}
}
