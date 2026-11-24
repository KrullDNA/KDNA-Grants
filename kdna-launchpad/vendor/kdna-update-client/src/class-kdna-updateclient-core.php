<?php
/**
 * Core: settings, the list of KDNA plugins on this site, and the cached check.
 *
 * @package KDNA_Update_Client
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The heart of the client. Holds the settings from config.php, knows which
 * KDNA plugins are installed, and keeps the result of the last combined
 * check in the database for 12 hours.
 *
 * Behaviour rules:
 * - Checks never run on front-end page loads, only in wp-admin, WP-Cron or WP-CLI.
 * - One combined check per site, cached for 12 hours.
 * - If the server cannot be reached, wait an hour before trying again,
 *   so an outage never slows wp-admin down.
 */
class KDNA_UpdateClient_Core {

	/**
	 * Option holding this site's registration: install ID, site ID, token and address.
	 */
	const OPTION_STATE = 'kdna_uc_state';

	/**
	 * Option holding the last check result.
	 */
	const OPTION_CACHE = 'kdna_uc_cache';

	/**
	 * Option listing the plugins whose starting auto-update choice has been set.
	 */
	const OPTION_SEEDED = 'kdna_uc_seeded';

	/**
	 * How long a check result is used before checking again.
	 */
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * How long to wait after the server could not be reached.
	 */
	const RETRY_AFTER = HOUR_IN_SECONDS;

	/**
	 * How long to wait after the server refused this site (blocked).
	 */
	const BLOCKED_RETRY = 12 * HOUR_IN_SECONDS;

	/**
	 * Folder of the copy of the client that is running.
	 *
	 * @var string
	 */
	public static $dir = '';

	/**
	 * Version of the copy of the client that is running.
	 *
	 * @var string
	 */
	public static $version = '';

	/**
	 * Every copy found on this site: version => folder. Shown in the debug panel.
	 *
	 * @var array
	 */
	public static $copies = array();

	/**
	 * Settings from config.php.
	 *
	 * @var array
	 */
	public static $config = array();

	/**
	 * Problems found while starting up, shown in the debug panel.
	 *
	 * @var array
	 */
	public static $notes = array();

	/**
	 * Plugins registered with kdna_update_client_register(): slug => main file path.
	 *
	 * @var array
	 */
	private static $registered = array();

	/**
	 * The KDNA plugins on this site, worked out once per request.
	 *
	 * @var array|null
	 */
	private static $plugins = null;

	/**
	 * The last check result, loaded once per request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Whether a check has already been attempted in this request.
	 *
	 * @var bool
	 */
	private static $fetched = false;

	/* ---------------------------------------------------------------------
	 * Start up
	 * ------------------------------------------------------------------ */

	/**
	 * Starts the client. Called once by load.php from the newest copy.
	 *
	 * @param string $dir        Folder of this copy.
	 * @param string $version    Version of this copy.
	 * @param array  $registered slug => plugin file.
	 * @param array  $copies     version => folder of every copy found.
	 */
	public static function boot( $dir, $version, $registered, $copies ) {
		self::$dir        = $dir;
		self::$version    = $version;
		self::$copies     = $copies;
		self::$registered = $registered;
		self::$config     = self::load_config( $dir );

		// If the newest copy has the placeholder (or a broken) config.php, use the
		// settings from the newest other copy that has a working one, so one plugin
		// shipped without a Client Kit cannot switch updates off for the whole site.
		if ( ! self::is_configured() ) {
			$versions = array_keys( $copies );
			usort( $versions, 'version_compare' );
			foreach ( array_reverse( $versions ) as $other ) {
				if ( $copies[ $other ] === $dir ) {
					continue;
				}
				$running      = self::$config;
				self::$config = self::load_config( $copies[ $other ] );
				if ( self::is_configured() ) {
					self::$notes[] = sprintf( 'The newest client copy (%s) has no working config.php, so the settings from the copy in %s are used.', $dir, $copies[ $other ] );
					break;
				}
				self::$config = $running;
			}
		}

		if ( ! self::is_configured() ) {
			self::$notes[] = 'config.php is the placeholder, is incomplete, or does not use HTTPS. Replace the folder with a Client Kit from the update server.';
		}

		new KDNA_UpdateClient_Updater();
	}

	/**
	 * Reads config.php from the running copy.
	 *
	 * Developers can adjust it with the "kdna_uc_config" filter, for example
	 * to point a test site at a test server.
	 *
	 * @param string $dir Folder of this copy.
	 * @return array
	 */
	private static function load_config( $dir ) {
		$config = array();
		$file   = $dir . '/config.php';

		if ( is_readable( $file ) ) {
			$loaded = include $file;
			if ( is_array( $loaded ) ) {
				$config = $loaded;
			}
		}

		$config = wp_parse_args(
			(array) apply_filters( 'kdna_uc_config', $config ),
			array(
				'server_url'   => '',
				'fleet_secret' => '',
				'public_key'   => '',
				'kit_version'  => '',
				'generated_at' => '',
			)
		);

		$config['server_url'] = untrailingslashit( (string) $config['server_url'] );
		return $config;
	}

	/**
	 * Checks config.php holds a usable server address, fleet secret and public key.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$fleet = base64_decode( (string) self::$config['fleet_secret'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$key   = base64_decode( (string) self::$config['public_key'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		return '' !== self::server_host()
			&& self::scheme_allowed( self::$config['server_url'] )
			&& false !== $fleet && 32 === strlen( $fleet )
			&& false !== $key && 32 === strlen( $key );
	}

	/**
	 * Checks an address uses HTTPS. Plain HTTP is only accepted for local
	 * testing, when KDNA_UC_ALLOW_HTTP is defined as true in wp-config.php.
	 *
	 * @param string $url The address.
	 * @return bool
	 */
	public static function scheme_allowed( $url ) {
		$scheme = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_SCHEME ) );
		return 'https' === $scheme || ( 'http' === $scheme && defined( 'KDNA_UC_ALLOW_HTTP' ) && KDNA_UC_ALLOW_HTTP );
	}

	/**
	 * Returns the update server's host name, e.g. "updates.krulldna.com".
	 *
	 * @return string
	 */
	public static function server_host() {
		return strtolower( (string) wp_parse_url( self::$config['server_url'], PHP_URL_HOST ) );
	}

	/**
	 * Returns this site's address, as sent to the server.
	 *
	 * @return string
	 */
	public static function site_url() {
		return untrailingslashit( is_multisite() ? network_home_url() : home_url() );
	}

	/* ---------------------------------------------------------------------
	 * The KDNA plugins on this site
	 * ------------------------------------------------------------------ */

	/**
	 * Returns every KDNA plugin on the site: slug => basename, file, version, name.
	 *
	 * Includes plugins registered by their own code (active ones), plus any
	 * installed plugin whose "Update URI" header points at the update
	 * server, so inactive KDNA plugins receive updates too.
	 *
	 * @return array
	 */
	public static function plugins() {
		if ( null !== self::$plugins ) {
			return self::$plugins;
		}

		$plugins = array();
		$host    = self::server_host();

		// Plugins that registered themselves.
		foreach ( self::$registered as $slug => $file ) {
			$basename = plugin_basename( $file );
			if ( dirname( $basename ) !== $slug ) {
				self::$notes[] = sprintf( 'Plugin "%s" registered with the slug "%s", but its folder is "%s". The slug must match the folder name, so it was skipped.', $basename, $slug, dirname( $basename ) );
				continue;
			}
			$plugins[ $slug ] = $basename;
		}

		// Installed plugins pointing at the update server (includes inactive ones).
		// Only scanned where updates are managed, never on front-end page loads.
		if ( '' !== $host && ( is_admin() || self::can_fetch() ) ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			foreach ( get_plugins() as $basename => $data ) {
				$uri_host = strtolower( (string) wp_parse_url( (string) ( $data['UpdateURI'] ?? '' ), PHP_URL_HOST ) );
				$slug     = dirname( $basename );
				if ( $uri_host === $host && '.' !== $slug && ! isset( $plugins[ $slug ] ) ) {
					$plugins[ $slug ] = $basename;
				}
			}
		}

		self::$plugins = array();
		foreach ( $plugins as $slug => $basename ) {
			$file = WP_PLUGIN_DIR . '/' . $basename;
			if ( ! is_readable( $file ) ) {
				continue;
			}
			$headers = get_file_data( $file, array( 'Name' => 'Plugin Name', 'Version' => 'Version', 'Author' => 'Author' ) );

			self::$plugins[ $slug ] = array(
				'slug'     => $slug,
				'basename' => $basename,
				'file'     => $file,
				'name'     => $headers['Name'],
				'version'  => $headers['Version'],
				'author'   => $headers['Author'],
			);
		}

		ksort( self::$plugins );
		return self::$plugins;
	}

	/**
	 * Finds one of our plugins by its basename ("folder/file.php").
	 *
	 * @param string $basename Plugin basename.
	 * @return array|null
	 */
	public static function plugin_by_basename( $basename ) {
		$slug    = dirname( (string) $basename );
		$plugins = self::plugins();
		return ( isset( $plugins[ $slug ] ) && $plugins[ $slug ]['basename'] === $basename ) ? $plugins[ $slug ] : null;
	}

	/**
	 * Forgets the plugin list so versions are read again (after an update).
	 */
	public static function refresh_plugins() {
		self::$plugins = null;
	}

	/* ---------------------------------------------------------------------
	 * The cached check
	 * ------------------------------------------------------------------ */

	/**
	 * Returns the stored check result.
	 *
	 * @return array checked_at, slugs, plugins, error, error_at, next_attempt, blocked.
	 */
	public static function cache() {
		if ( null === self::$cache ) {
			$stored      = get_site_option( self::OPTION_CACHE, array() );
			self::$cache = wp_parse_args(
				is_array( $stored ) ? $stored : array(),
				array(
					'checked_at'   => 0,
					'slugs'        => '',
					'plugins'      => array(),
					'error'        => '',
					'error_at'     => 0,
					'next_attempt' => 0,
					'blocked'      => false,
				)
			);
		}
		return self::$cache;
	}

	/**
	 * Saves the check result.
	 *
	 * @param array $cache The result.
	 */
	private static function save_cache( $cache ) {
		self::$cache = $cache;
		update_site_option( self::OPTION_CACHE, $cache );
	}

	/**
	 * Returns what the server said about one plugin, or null.
	 *
	 * @param string $slug Plugin slug.
	 * @return array|null
	 */
	public static function server_data( $slug ) {
		$cache = self::cache();
		return isset( $cache['plugins'][ $slug ] ) && is_array( $cache['plugins'][ $slug ] ) ? $cache['plugins'][ $slug ] : null;
	}

	/**
	 * A short fingerprint of which KDNA plugins are installed, so a new
	 * one appearing triggers a fresh check.
	 *
	 * @return string
	 */
	private static function slugs_hash() {
		return md5( implode( ',', array_keys( self::plugins() ) ) );
	}

	/**
	 * Says whether this request is allowed to contact the server.
	 *
	 * Only wp-admin (not background AJAX), WP-Cron and WP-CLI. Never the front end.
	 *
	 * @return bool
	 */
	public static function can_fetch() {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
			return true;
		}
		return is_admin() && ! wp_doing_ajax();
	}

	/**
	 * Checks with the server if the stored result is out of date.
	 *
	 * Safe to call often: it does nothing unless 12 hours have passed, a
	 * new KDNA plugin has appeared, or $force is true. It also respects the
	 * back-off after a failure, unless forced.
	 *
	 * @param bool $force Check now, whatever the cache says.
	 * @return bool True if a check was made and succeeded.
	 */
	public static function maybe_fetch( $force = false ) {
		if ( ! self::is_configured() || ! self::can_fetch() || empty( self::plugins() ) ) {
			return false;
		}

		// Never check twice in one request.
		if ( self::$fetched ) {
			return false;
		}

		$cache = self::cache();
		$fresh = $cache['checked_at'] > time() - self::CACHE_TTL && $cache['slugs'] === self::slugs_hash();

		if ( ! $force && ( $fresh || $cache['next_attempt'] > time() ) ) {
			return false;
		}

		self::$fetched = true;
		return self::fetch();
	}

	/**
	 * Sends the combined check and stores the result.
	 *
	 * @return bool True on success.
	 */
	private static function fetch() {
		$versions = array();
		foreach ( self::plugins() as $slug => $plugin ) {
			$versions[ $slug ] = $plugin['version'];
		}

		$result = KDNA_UpdateClient_Transport::check( $versions, self::auto_choices() );
		$cache  = self::cache();

		if ( is_wp_error( $result ) ) {
			$blocked               = 'kdna_uc_blocked' === $result->get_error_code();
			$cache['error']        = $result->get_error_message();
			$cache['error_at']     = time();
			$cache['blocked']      = $blocked;
			$cache['next_attempt'] = time() + ( $blocked ? self::BLOCKED_RETRY : self::RETRY_AFTER );
			// A blocked site is shown no KDNA updates at all.
			if ( $blocked ) {
				$cache['plugins'] = array();
			}
			self::save_cache( $cache );
			return false;
		}

		$plugins = array();
		foreach ( (array) ( $result['plugins'] ?? array() ) as $slug => $data ) {
			if ( is_array( $data ) && isset( self::plugins()[ $slug ] ) ) {
				$plugins[ $slug ] = self::clean_entry( $data );
			}
		}

		self::save_cache(
			array(
				'checked_at'   => time(),
				'slugs'        => self::slugs_hash(),
				'plugins'      => $plugins,
				'error'        => '',
				'error_at'     => 0,
				'next_attempt' => 0,
				'blocked'      => false,
			)
		);

		self::seed_auto_updates( $plugins );

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Automatic updates: the site's own choice
	 * ------------------------------------------------------------------ */

	/**
	 * Returns WordPress's list of plugins with auto-updates switched on.
	 *
	 * @return array Plugin basenames.
	 */
	private static function wp_auto_list() {
		return array_values( array_filter( (array) get_site_option( 'auto_update_plugins', array() ), 'is_string' ) );
	}

	/**
	 * Returns the site's auto-update choice for each KDNA plugin whose
	 * starting choice has already been set, to send to the server.
	 *
	 * Plugins not set up yet are left out, so the server's starting choice
	 * is used for them instead of an accidental "off".
	 *
	 * @return array slug => true (automatic) or false (manual).
	 */
	public static function auto_choices() {
		$seeded  = (array) get_site_option( self::OPTION_SEEDED, array() );
		$enabled = self::wp_auto_list();
		$choices = array();

		foreach ( self::plugins() as $slug => $plugin ) {
			if ( in_array( $plugin['basename'], $seeded, true ) ) {
				$choices[ $slug ] = in_array( $plugin['basename'], $enabled, true );
			}
		}
		return $choices;
	}

	/**
	 * The first time a KDNA plugin is seen, switches WordPress's own
	 * auto-update setting on or off to match the server's starting choice.
	 * After that the site is in charge: whatever someone clicks wins.
	 *
	 * @param array $server slug => what the server said.
	 */
	private static function seed_auto_updates( $server ) {
		$seeded  = (array) get_site_option( self::OPTION_SEEDED, array() );
		$enabled = self::wp_auto_list();
		$changed = false;

		foreach ( self::plugins() as $slug => $plugin ) {
			$basename = $plugin['basename'];
			if ( in_array( $basename, $seeded, true ) || ! isset( $server[ $slug ] ) ) {
				continue;
			}

			if ( ! empty( $server[ $slug ]['auto_update'] ) && ! in_array( $basename, $enabled, true ) ) {
				$enabled[] = $basename;
				$changed   = true;
			}
			$seeded[] = $basename;

			update_site_option( self::OPTION_SEEDED, array_values( array_unique( $seeded ) ) );
		}

		if ( $changed ) {
			// Saving this would normally trigger a fresh check; not needed here.
			remove_action( 'update_option_auto_update_plugins', array( __CLASS__, 'expire' ) );
			remove_action( 'update_site_option_auto_update_plugins', array( __CLASS__, 'expire' ) );
			update_site_option( 'auto_update_plugins', array_values( array_unique( $enabled ) ) );
		}
	}

	/**
	 * Cleans what the server said about one plugin before it is stored.
	 *
	 * WordPress prints some of these values on its own screens without
	 * escaping them (the version on Dashboard > Updates, the PHP requirement
	 * in "View details"). So every field is checked against the exact shape
	 * it should have, and anything else is thrown away. Even a compromised
	 * update server cannot slip script into a client site's admin this way.
	 *
	 * @param array $data The server's reply for one plugin.
	 * @return array
	 */
	public static function clean_entry( $data ) {
		$version = function ( $value, $pattern ) {
			$value = is_scalar( $value ) ? trim( (string) $value ) : '';
			return preg_match( $pattern, $value ) ? $value : '';
		};

		// The same version format the server accepts on upload.
		$release = '/^\d+(?:\.\d+){0,3}(?:-[0-9A-Za-z]+(?:[.\-][0-9A-Za-z]+)*)?$/';
		// WordPress and PHP requirement numbers, e.g. 6.4 or 8.0.30.
		$plain = '/^\d+(?:\.\d+){0,3}$/';

		$clean = array(
			'version'        => $version( $data['version'] ?? '', $release ),
			'requires_wp'    => $version( $data['requires_wp'] ?? '', $plain ),
			'requires_php'   => $version( $data['requires_php'] ?? '', $plain ),
			'tested_wp'      => $version( $data['tested_wp'] ?? '', $plain ),
			'auto_update'    => ! empty( $data['auto_update'] ),
			'pinned'         => ! empty( $data['pinned'] ),
			'notice'         => mb_substr( sanitize_text_field( is_scalar( $data['notice'] ?? '' ) ? (string) ( $data['notice'] ?? '' ) : '' ), 0, 500 ),
			'changelog_html' => wp_kses_post( is_scalar( $data['changelog_html'] ?? '' ) ? (string) ( $data['changelog_html'] ?? '' ) : '' ),
			'released_at'    => $version( $data['released_at'] ?? '', '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/' ),
		);

		// No usable version means nothing is offered for this plugin.
		if ( '' === $clean['version'] ) {
			$clean['version'] = null;
		}

		return $clean;
	}

	/**
	 * Marks the stored result as out of date so the next chance checks again.
	 */
	public static function expire() {
		$cache                 = self::cache();
		$cache['checked_at']   = 0;
		$cache['next_attempt'] = 0;
		self::save_cache( $cache );
		self::$fetched = false;
	}

	/* ---------------------------------------------------------------------
	 * Registration state
	 * ------------------------------------------------------------------ */

	/**
	 * Returns this site's stored registration, creating an install ID once.
	 *
	 * @return array install_id, site_id, token, site_url, offset.
	 */
	public static function state() {
		$state = get_site_option( self::OPTION_STATE, array() );
		$state = wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'install_id' => '',
				'site_id'    => 0,
				'token'      => '',
				'site_url'   => '',
				'offset'     => 0,
			)
		);

		if ( ! preg_match( '/^[a-f0-9]{32}$/', $state['install_id'] ) ) {
			$state['install_id'] = bin2hex( random_bytes( 16 ) );
			self::save_state( $state );
		}

		return $state;
	}

	/**
	 * Saves this site's registration.
	 *
	 * @param array $state The registration.
	 */
	public static function save_state( $state ) {
		update_site_option( self::OPTION_STATE, $state );
	}
}
