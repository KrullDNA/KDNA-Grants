<?php
/**
 * Updater: connects KDNA updates to WordPress's normal update screens.
 *
 * @package KDNA_Update_Client
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes KDNA plugin updates behave exactly like wordpress.org updates:
 * the "update available" notice, the "View version details" window, one
 * click updates, bulk updates and automatic background updates.
 *
 * The update "package" WordPress stores is a placeholder such as
 * kdna-update://kdna-icon-button/1.2.0, never a real link, so an update
 * notice can wait for days. When the update actually runs, a fresh
 * single-use link is requested, the zip is downloaded and verified, and
 * only then handed to WordPress to install.
 */
class KDNA_UpdateClient_Updater {

	/**
	 * Start of every placeholder package address.
	 */
	const SCHEME = 'kdna-update://';

	/**
	 * Versions before each update in this request, for the report: slug => from version.
	 *
	 * @var array
	 */
	private $pending = array();

	/**
	 * Slug of the verified KDNA zip currently being installed, if any.
	 *
	 * @var string
	 */
	private $in_flight = '';

	/**
	 * Connects the updater to WordPress.
	 */
	public function __construct() {
		// The debug panel works even before the client is configured.
		add_action( 'admin_notices', array( $this, 'debug_panel' ) );
		add_action( 'network_admin_notices', array( $this, 'debug_panel' ) );

		if ( ! KDNA_UpdateClient_Core::is_configured() ) {
			return;
		}

		$host = KDNA_UpdateClient_Core::server_host();

		// Checking: on WordPress's own twice-daily update run, and when update screens load.
		add_action( 'wp_update_plugins', array( $this, 'scheduled_check' ) );
		add_action( 'load-plugins.php', array( $this, 'screen_check' ) );
		add_action( 'load-update-core.php', array( $this, 'screen_check' ) );
		add_action( 'load-update.php', array( $this, 'screen_check' ) );

		// Showing updates.
		add_filter( "update_plugins_{$host}", array( $this, 'filter_update_uri' ), 10, 4 );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject' ), 20 );
		add_filter( 'site_transient_update_plugins', array( $this, 'inject' ), 20 );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 20, 3 );

		// Installing updates.
		add_filter( 'upgrader_pre_download', array( $this, 'pre_download' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( $this, 'check_folder' ), 1, 4 );
		add_filter( 'upgrader_install_package_result', array( $this, 'install_result' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'process_complete' ), 10, 2 );

		// Automatic updates: each site chooses with WordPress's own Enable/Disable
		// auto-updates link, so no auto_update_plugin filter is added (any answer
		// from a filter makes WordPress hide that link). Updates a site cannot run
		// are never in the update list, so they are never installed automatically.
		// A change of choice triggers a fresh check so the server hears about it.
		add_action( 'update_option_auto_update_plugins', array( 'KDNA_UpdateClient_Core', 'expire' ) );
		add_action( 'update_site_option_auto_update_plugins', array( 'KDNA_UpdateClient_Core', 'expire' ) );

		// Plugins screen extras.
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'handle_check_link' ) );
		add_action( 'admin_notices', array( $this, 'check_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'check_notice' ) );
		add_action( 'admin_footer-plugins.php', array( $this, 'rollback_labels' ) );
		add_action( 'load-plugins.php', array( $this, 'register_row_hooks' ) );
	}

	/* ---------------------------------------------------------------------
	 * Checking
	 * ------------------------------------------------------------------ */

	/**
	 * Runs with WordPress's own twice-daily update check (WP-Cron).
	 */
	public function scheduled_check() {
		KDNA_UpdateClient_Core::maybe_fetch();
	}

	/**
	 * Runs when the Plugins or Updates screen loads. "Check again" on the
	 * Updates screen forces a fresh check.
	 */
	public function screen_check() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress's own "Check again" link; only triggers a read-only check.
		$force = isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' );
		KDNA_UpdateClient_Core::maybe_fetch( $force );
	}

	/* ---------------------------------------------------------------------
	 * Building update entries
	 * ------------------------------------------------------------------ */

	/**
	 * Says why an update cannot be installed on this site, if it cannot.
	 *
	 * @param array $data What the server said about the plugin.
	 * @return string Empty when the site meets the requirements.
	 */
	private function requirement_problem( $data ) {
		if ( ! empty( $data['requires_wp'] ) && ! is_wp_version_compatible( $data['requires_wp'] ) ) {
			/* translators: 1: version on offer, 2: WordPress version needed, 3: current WordPress version. */
			return sprintf( __( 'Version %1$s is available but needs WordPress %2$s or newer. This site runs %3$s.', 'kdna-update-client' ), $data['version'], $data['requires_wp'], get_bloginfo( 'version' ) );
		}
		if ( ! empty( $data['requires_php'] ) && ! is_php_version_compatible( $data['requires_php'] ) ) {
			/* translators: 1: version on offer, 2: PHP version needed, 3: current PHP version. */
			return sprintf( __( 'Version %1$s is available but needs PHP %2$s or newer. This server runs %3$s.', 'kdna-update-client' ), $data['version'], $data['requires_php'], PHP_VERSION );
		}
		return '';
	}

	/**
	 * Works out what to show for one plugin.
	 *
	 * @param string $slug Plugin slug.
	 * @return array|null status (update, rollback, current, blocked), item (object), data, plugin, reason.
	 */
	private function entry( $slug ) {
		$plugins = KDNA_UpdateClient_Core::plugins();
		$data    = KDNA_UpdateClient_Core::server_data( $slug );

		if ( ! isset( $plugins[ $slug ] ) || ! $data || empty( $data['version'] ) ) {
			return null;
		}

		$plugin  = $plugins[ $slug ];
		$compare = version_compare( $data['version'], $plugin['version'] );
		$reason  = $this->requirement_problem( $data );

		if ( 0 === $compare ) {
			$status = 'current';
		} elseif ( '' !== $reason ) {
			$status = 'blocked';
		} else {
			$status = $compare > 0 ? 'update' : 'rollback';
		}

		$item = (object) array(
			'id'             => KDNA_UpdateClient_Core::$config['server_url'],
			'slug'           => $slug,
			'plugin'         => $plugin['basename'],
			'new_version'    => $data['version'],
			'version'        => $data['version'],
			'url'            => KDNA_UpdateClient_Core::$config['server_url'],
			'package'        => self::SCHEME . $slug . '/' . $data['version'],
			'tested'         => (string) ( $data['tested_wp'] ?? '' ),
			'requires'       => (string) ( $data['requires_wp'] ?? '' ),
			'requires_php'   => (string) ( $data['requires_php'] ?? '' ),
			// Never forced: WordPress follows the site's own auto-update choice.
			'autoupdate'     => false,
			'upgrade_notice' => (string) ( $data['notice'] ?? '' ),
			'icons'          => array(),
			'banners'        => array(),
			'banners_rtl'    => array(),
			'compatibility'  => new stdClass(),
		);

		return array(
			'status' => $status,
			'item'   => $item,
			'data'   => $data,
			'plugin' => $plugin,
			'reason' => $reason,
		);
	}

	/**
	 * The official WordPress 5.8+ route for plugins with an Update URI.
	 *
	 * @param array|false $update      Update data so far.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @param array       $locales     Site locales.
	 * @return array|false
	 */
	public function filter_update_uri( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $plugin_data, $locales );

		$entry = $this->entry( dirname( $plugin_file ) );
		if ( ! $entry || 'blocked' === $entry['status'] || $entry['plugin']['basename'] !== $plugin_file ) {
			return $update;
		}

		return (array) $entry['item'];
	}

	/**
	 * Puts KDNA updates into WordPress's list of available updates.
	 *
	 * This also covers two things the official route cannot do:
	 * - roll backs (WordPress only lists higher versions as updates), and
	 * - showing updates even when wordpress.org cannot be reached (WordPress
	 *   stops its check early in that case and never asks private servers).
	 *
	 * Runs both when the list is saved and when it is read.
	 *
	 * @param object|false $transient WordPress's update list.
	 * @return object|false
	 */
	public function inject( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		foreach ( KDNA_UpdateClient_Core::plugins() as $slug => $plugin ) {
			$basename = $plugin['basename'];
			$entry    = $this->entry( $slug );

			// Remove any entry for this plugin, ours or not, then add what is true now.
			// A KDNA plugin must only ever be updated from the KDNA server, never
			// from wordpress.org (where another plugin could share its folder name).
			unset( $transient->response[ $basename ] );

			if ( ! $entry ) {
				continue;
			}

			if ( 'update' === $entry['status'] || 'rollback' === $entry['status'] ) {
				$transient->response[ $basename ] = $entry['item'];
				unset( $transient->no_update[ $basename ] );
			} elseif ( 'current' === $entry['status'] ) {
				$transient->no_update[ $basename ] = $entry['item'];
			} else {
				unset( $transient->no_update[ $basename ] );
			}
		}

		return $transient;
	}

	/**
	 * Says whether an update entry was created by this client.
	 *
	 * @param mixed $item Update entry.
	 * @return bool
	 */
	private function is_ours( $item ) {
		$package = is_object( $item ) ? ( $item->package ?? '' ) : ( is_array( $item ) ? ( $item['package'] ?? '' ) : '' );
		return is_string( $package ) && 0 === strpos( $package, self::SCHEME );
	}

	/* ---------------------------------------------------------------------
	 * "View version details" window
	 * ------------------------------------------------------------------ */

	/**
	 * Fills the "View version details" window with the changelog and requirements.
	 *
	 * @param false|object|array $result The result so far.
	 * @param string             $action The type of information requested.
	 * @param object             $args   Request details, including the slug.
	 * @return false|object|WP_Error
	 */
	public function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}

		$plugins = KDNA_UpdateClient_Core::plugins();
		$slug    = (string) $args->slug;
		if ( ! isset( $plugins[ $slug ] ) ) {
			return $result;
		}

		$data = KDNA_UpdateClient_Core::server_data( $slug );
		if ( ! $data || empty( $data['version'] ) ) {
			return new WP_Error( 'plugins_api_failed', __( 'KDNA Updates has no details for this plugin yet. Use "Check for updates" on the Plugins screen and try again.', 'kdna-update-client' ) );
		}

		$plugin  = $plugins[ $slug ];
		$entry   = $this->entry( $slug );
		$changes = ! empty( $data['changelog_html'] ) ? $data['changelog_html'] : '<p>' . esc_html__( 'No changelog was provided for this version.', 'kdna-update-client' ) . '</p>';

		$sections = array( 'changelog' => wp_kses_post( $changes ) );

		if ( $entry && 'rollback' === $entry['status'] ) {
			$notice = '<p><strong>' . esc_html(
				/* translators: %s: version number. */
				sprintf( __( 'Roll back to %s', 'kdna-update-client' ), $data['version'] )
			) . '</strong></p>';
			if ( ! empty( $data['notice'] ) ) {
				$notice .= '<p>' . esc_html( $data['notice'] ) . '</p>';
			}
			$sections = array( 'description' => $notice ) + $sections;
		}

		return (object) array(
			'name'          => $plugin['name'],
			'slug'          => $slug,
			'version'       => $data['version'],
			'author'        => wp_kses_post( $plugin['author'] ),
			'homepage'      => KDNA_UpdateClient_Core::$config['server_url'],
			'requires'      => (string) ( $data['requires_wp'] ?? '' ),
			'tested'        => (string) ( $data['tested_wp'] ?? '' ),
			'requires_php'  => (string) ( $data['requires_php'] ?? '' ),
			'last_updated'  => (string) ( $data['released_at'] ?? '' ),
			'sections'      => $sections,
			'download_link' => '',
			'banners'       => array(),
		);
	}

	/* ---------------------------------------------------------------------
	 * Installing
	 * ------------------------------------------------------------------ */

	/**
	 * Swaps the placeholder package for a verified zip, at the moment the update runs.
	 *
	 * 1. Ask the server for a fresh single-use link.
	 * 2. Download the zip.
	 * 3. Check its SHA-256 fingerprint and Ed25519 signature.
	 * 4. Only then hand the file to WordPress.
	 *
	 * Any failure stops the update, and a failed security check is reported
	 * to the server.
	 *
	 * @param false|string|WP_Error $reply      Result so far (false means "not handled").
	 * @param string                $package    The package address.
	 * @param WP_Upgrader           $upgrader   The upgrader running.
	 * @param array                 $hook_extra Details of what is being updated.
	 * @return false|string|WP_Error
	 */
	public function pre_download( $reply, $package, $upgrader, $hook_extra = array() ) {
		if ( ! is_string( $package ) || 0 !== strpos( $package, self::SCHEME ) ) {
			return $reply;
		}

		// Something else already answered for a KDNA package. Never install a
		// KDNA update that did not go through the checks below.
		if ( false !== $reply ) {
			return new WP_Error( 'kdna_uc_security', __( 'This update failed a security check.', 'kdna-update-client' ) );
		}

		if ( ! preg_match( '#^kdna-update://([a-z0-9]+(?:-[a-z0-9]+)*)/([0-9A-Za-z.\-+]+)$#', $package, $match ) ) {
			return new WP_Error( 'kdna_uc_package', __( 'This KDNA update has an invalid package address.', 'kdna-update-client' ) );
		}

		$slug    = $match[1];
		$version = $match[2];
		$plugins = KDNA_UpdateClient_Core::plugins();

		if ( ! isset( $plugins[ $slug ] ) ) {
			return new WP_Error( 'kdna_uc_package', __( 'This KDNA update is for a plugin that is not installed here.', 'kdna-update-client' ) );
		}

		// The package must belong to the plugin WordPress says it is updating.
		if ( ! empty( $hook_extra['plugin'] ) && $hook_extra['plugin'] !== $plugins[ $slug ]['basename'] ) {
			return new WP_Error( 'kdna_uc_package', __( 'This KDNA update does not match the plugin being updated.', 'kdna-update-client' ) );
		}

		$from                   = $plugins[ $slug ]['version'];
		$this->pending[ $slug ] = array(
			'from' => $from,
			'to'   => $version,
		);

		if ( isset( $upgrader->skin ) && is_object( $upgrader->skin ) ) {
			$upgrader->strings['kdna_uc_link']   = __( 'Requesting a secure download link from KDNA Updates&#8230;', 'kdna-update-client' );
			$upgrader->strings['kdna_uc_verify'] = __( 'Checking the update&#8217;s fingerprint and digital signature&#8230;', 'kdna-update-client' );
			$upgrader->skin->feedback( 'kdna_uc_link' );
		}

		$link = KDNA_UpdateClient_Transport::link( $slug, $version );
		if ( is_wp_error( $link ) ) {
			if ( 'kdna_uc_target_changed' === $link->get_error_code() ) {
				KDNA_UpdateClient_Core::expire();
			}
			unset( $this->pending[ $slug ] );
			return $link;
		}

		if ( ( $link['version'] ?? '' ) !== $version ) {
			unset( $this->pending[ $slug ] );
			return new WP_Error( 'kdna_uc_link_failed', __( 'The update server offered a different version than expected. Refresh the Plugins screen and try again.', 'kdna-update-client' ) );
		}

		$file = KDNA_UpdateClient_Transport::download( $link['url'] );
		if ( is_wp_error( $file ) ) {
			KDNA_UpdateClient_Transport::report( $slug, $from, $version, false, $file->get_error_message(), 'download_failed' );
			unset( $this->pending[ $slug ] );
			return $file;
		}

		if ( isset( $upgrader->skin ) && is_object( $upgrader->skin ) ) {
			$upgrader->skin->feedback( 'kdna_uc_verify' );
		}

		$verified = KDNA_UpdateClient_Verifier::verify( $file, $slug, $version, (string) ( $link['sha256'] ?? '' ), (string) ( $link['signature'] ?? '' ) );
		if ( is_wp_error( $verified ) ) {
			wp_delete_file( $file );
			$data = $verified->get_error_data();
			KDNA_UpdateClient_Transport::report( $slug, $from, $version, false, 'Security check failed: ' . ( $data['reason'] ?? 'unknown' ), 'security_check' );
			unset( $this->pending[ $slug ] );
			return $verified;
		}

		// Remember which folder this verified zip must unpack to.
		$this->in_flight = $slug;

		return $file;
	}

	/**
	 * Makes sure a verified KDNA zip unpacks into its own plugin's folder.
	 *
	 * The server already checks this before signing, but checking again here
	 * means even a server mistake could never overwrite a different plugin.
	 *
	 * @param string|WP_Error $source        Folder WordPress unpacked the zip to.
	 * @param string          $remote_source Working folder.
	 * @param WP_Upgrader     $upgrader      The upgrader.
	 * @param array           $hook_extra    Details of what is being updated.
	 * @return string|WP_Error
	 */
	public function check_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		unset( $remote_source, $upgrader );

		if ( '' === $this->in_flight || is_wp_error( $source ) ) {
			return $source;
		}

		$slug            = $this->in_flight;
		$this->in_flight = '';

		// In a bulk update, only check the plugin this zip was verified for.
		if ( ! empty( $hook_extra['plugin'] ) && dirname( (string) $hook_extra['plugin'] ) !== $slug ) {
			return $source;
		}

		if ( basename( untrailingslashit( (string) $source ) ) !== $slug ) {
			return new WP_Error( 'kdna_uc_security', __( 'This update failed a security check.', 'kdna-update-client' ) );
		}
		return $source;
	}

	/**
	 * Reports an install that failed after the download was verified
	 * (for example, the files could not be copied).
	 *
	 * @param array|WP_Error $result     The install result.
	 * @param array          $hook_extra Details of what was being updated.
	 * @return array|WP_Error Unchanged.
	 */
	public function install_result( $result, $hook_extra ) {
		// Whatever happened, this install is over.
		$this->in_flight = '';

		if ( is_wp_error( $result ) && ! empty( $hook_extra['plugin'] ) ) {
			$slug = dirname( (string) $hook_extra['plugin'] );
			if ( isset( $this->pending[ $slug ] ) ) {
				KDNA_UpdateClient_Transport::report( $slug, $this->pending[ $slug ]['from'], $this->pending[ $slug ]['to'], false, $result->get_error_message(), $result->get_error_code() );
				unset( $this->pending[ $slug ] );
			}
		}
		return $result;
	}

	/**
	 * After updates finish, reads each updated plugin's new version and
	 * reports success (or failure if the version did not change) to the server.
	 *
	 * @param WP_Upgrader $upgrader   The upgrader.
	 * @param array       $hook_extra Details of what was updated.
	 */
	public function process_complete( $upgrader, $hook_extra ) {
		unset( $upgrader );

		if ( empty( $this->pending ) || 'plugin' !== ( $hook_extra['type'] ?? '' ) || 'update' !== ( $hook_extra['action'] ?? '' ) ) {
			return;
		}

		$basenames = array();
		if ( ! empty( $hook_extra['plugins'] ) ) {
			$basenames = (array) $hook_extra['plugins'];
		} elseif ( ! empty( $hook_extra['plugin'] ) ) {
			$basenames = array( $hook_extra['plugin'] );
		}

		foreach ( $basenames as $basename ) {
			$slug = dirname( (string) $basename );
			if ( ! isset( $this->pending[ $slug ] ) ) {
				continue;
			}

			$file    = WP_PLUGIN_DIR . '/' . $basename;
			$headers = is_readable( $file ) ? get_file_data( $file, array( 'Version' => 'Version' ) ) : array( 'Version' => '' );
			$to      = $this->pending[ $slug ]['to'];
			$ok      = $headers['Version'] === $to;

			KDNA_UpdateClient_Transport::report(
				$slug,
				$this->pending[ $slug ]['from'],
				$to,
				$ok,
				$ok ? '' : sprintf( 'After the update the plugin reports version %s instead of %s.', $headers['Version'], $to ),
				$ok ? '' : 'version_mismatch'
			);
			unset( $this->pending[ $slug ] );
		}

		KDNA_UpdateClient_Core::refresh_plugins();
	}

	/* ---------------------------------------------------------------------
	 * Automatic updates
	 * ------------------------------------------------------------------ */

	/* ---------------------------------------------------------------------
	 * Plugins screen extras
	 * ------------------------------------------------------------------ */

	/**
	 * Adds a "Check for updates" link to each KDNA plugin row.
	 *
	 * @param array  $meta        Existing row links.
	 * @param string $plugin_file Plugin basename.
	 * @return array
	 */
	public function row_meta( $meta, $plugin_file ) {
		if ( ! current_user_can( 'update_plugins' ) || ! KDNA_UpdateClient_Core::plugin_by_basename( $plugin_file ) ) {
			return $meta;
		}

		$url    = wp_nonce_url( self_admin_url( 'plugins.php?kdna_uc_check=1' ), 'kdna_uc_check' );
		$meta[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'kdna-update-client' ) . '</a>';
		return $meta;
	}

	/**
	 * Handles the "Check for updates" link: checks now, then returns to the Plugins screen.
	 */
	public function handle_check_link() {
		if ( empty( $_GET['kdna_uc_check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked below.
			return;
		}
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You are not allowed to update plugins on this site.', 'kdna-update-client' ), 403 );
		}
		check_admin_referer( 'kdna_uc_check' );

		KDNA_UpdateClient_Core::expire();
		$ok = KDNA_UpdateClient_Core::maybe_fetch( true );

		$cache   = KDNA_UpdateClient_Core::cache();
		$updates = 0;
		foreach ( array_keys( KDNA_UpdateClient_Core::plugins() ) as $slug ) {
			$entry = $this->entry( $slug );
			if ( $entry && in_array( $entry['status'], array( 'update', 'rollback' ), true ) ) {
				++$updates;
			}
		}

		set_transient(
			'kdna_uc_notice_' . get_current_user_id(),
			$ok
				? array(
					'type'    => 'success',
					'message' => 0 === $updates
						? __( 'KDNA Updates: checked just now. All KDNA plugins are up to date.', 'kdna-update-client' )
						/* translators: %d: number of updates. */
						: sprintf( _n( 'KDNA Updates: checked just now. %d update available.', 'KDNA Updates: checked just now. %d updates available.', $updates, 'kdna-update-client' ), $updates ),
				)
				: array(
					'type'    => 'error',
					/* translators: %s: error message. */
					'message' => sprintf( __( 'KDNA Updates could not check for updates: %s', 'kdna-update-client' ), $cache['error'] ),
				),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( self_admin_url( 'plugins.php' ) );
		exit;
	}

	/**
	 * Shows the result of "Check for updates" once.
	 */
	public function check_notice() {
		$key    = 'kdna_uc_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( $key );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			'success' === $notice['type'] ? 'success' : 'error',
			esc_html( $notice['message'] )
		);
	}

	/**
	 * Adds per-plugin hooks on the Plugins screen: requirement messages and
	 * the roll back explanation.
	 */
	public function register_row_hooks() {
		foreach ( KDNA_UpdateClient_Core::plugins() as $plugin ) {
			add_action( 'after_plugin_row_' . $plugin['basename'], array( $this, 'requirement_row' ), 10, 2 );
			add_action( 'in_plugin_update_message-' . $plugin['basename'], array( $this, 'rollback_message' ), 10, 2 );
		}
	}

	/**
	 * Shows "available but needs a newer WordPress or PHP" under a plugin
	 * whose update cannot be installed here.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @param array  $plugin_data Plugin headers.
	 */
	public function requirement_row( $plugin_file, $plugin_data ) {
		unset( $plugin_data );

		$plugin = KDNA_UpdateClient_Core::plugin_by_basename( $plugin_file );
		$entry  = $plugin ? $this->entry( $plugin['slug'] ) : null;
		if ( ! $entry || 'blocked' !== $entry['status'] ) {
			return;
		}

		$columns = 4;
		if ( function_exists( '_get_list_table' ) ) {
			$table = _get_list_table( 'WP_Plugins_List_Table' );
			if ( $table ) {
				$columns = $table->get_column_count();
			}
		}

		printf(
			'<tr class="plugin-update-tr active"><td colspan="%1$d" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt"><p>%2$s</p></div></td></tr>',
			(int) $columns,
			esc_html( $entry['reason'] )
		);
	}

	/**
	 * Adds the roll back explanation to the update notice of a roll back.
	 *
	 * @param array  $plugin_data Plugin headers.
	 * @param object $response    The update entry.
	 */
	public function rollback_message( $plugin_data, $response ) {
		unset( $plugin_data );

		if ( ! is_object( $response ) || ! $this->is_ours( $response ) ) {
			return;
		}

		$plugin = KDNA_UpdateClient_Core::plugin_by_basename( $response->plugin ?? '' );
		$entry  = $plugin ? $this->entry( $plugin['slug'] ) : null;
		if ( ! $entry || 'rollback' !== $entry['status'] ) {
			return;
		}

		echo ' <strong>' . esc_html(
			/* translators: %s: version number. */
			sprintf( __( 'This is a roll back to %s, chosen by KDNA.', 'kdna-update-client' ), $entry['data']['version'] )
		) . '</strong>';

		if ( ! empty( $entry['data']['notice'] ) ) {
			echo ' ' . esc_html( $entry['data']['notice'] );
		}
	}

	/**
	 * Relabels the "update now" link as "Roll back to x.y.z" for roll backs.
	 */
	public function rollback_labels() {
		$labels = array();
		foreach ( array_keys( KDNA_UpdateClient_Core::plugins() ) as $slug ) {
			$entry = $this->entry( $slug );
			if ( $entry && 'rollback' === $entry['status'] ) {
				/* translators: %s: version number. */
				$labels[ $entry['plugin']['basename'] ] = sprintf( __( 'Roll back to %s', 'kdna-update-client' ), $entry['data']['version'] );
			}
		}
		if ( empty( $labels ) ) {
			return;
		}
		?>
		<script>
		( function () {
			var labels = <?php echo wp_json_encode( $labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
			Object.keys( labels ).forEach( function ( file ) {
				document.querySelectorAll( 'tr.plugin-update-tr[data-plugin="' + file + '"] a.update-link' ).forEach( function ( link ) {
					link.textContent = labels[ file ];
				} );
			} );
		} )();
		</script>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Debug panel
	 * ------------------------------------------------------------------ */

	/**
	 * Shows the debug panel on the Plugins screen when ?kdna_uc_debug=1 is
	 * added to the address, for administrators only. Never shows secrets.
	 */
	public function debug_panel() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display for administrators.
		if ( empty( $_GET['kdna_uc_debug'] ) || ! current_user_can( 'manage_options' ) || ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) ) {
			return;
		}

		$cache  = KDNA_UpdateClient_Core::cache();
		$state  = KDNA_UpdateClient_Core::state();
		$config = KDNA_UpdateClient_Core::$config;
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$when   = function ( $ts ) use ( $format ) {
			return $ts ? wp_date( $format, $ts ) : '–';
		};

		$rows = array(
			'Client version running' => KDNA_UpdateClient_Core::$version . ' (' . KDNA_UpdateClient_Core::$dir . ')',
			'Copies found'           => implode( ', ', array_keys( KDNA_UpdateClient_Core::$copies ) ),
			'Configured'             => KDNA_UpdateClient_Core::is_configured() ? 'Yes' : 'No',
			'Update server'          => $config['server_url'] ? $config['server_url'] : '–',
			'Client Kit generated'   => $config['generated_at'] ? $config['generated_at'] : '–',
			'Registered as site'     => $state['site_id'] ? '#' . $state['site_id'] . ' (' . $state['site_url'] . ')' : 'Not yet',
			'This site address'      => KDNA_UpdateClient_Core::site_url(),
			'Clock correction'       => (int) $state['offset'] . ' seconds',
			'Last successful check'  => $when( $cache['checked_at'] ),
			'Next check due'         => $cache['checked_at'] ? $when( $cache['checked_at'] + KDNA_UpdateClient_Core::CACHE_TTL ) : 'At the next chance',
			'Last error'             => $cache['error'] ? $cache['error'] . ' (' . $when( $cache['error_at'] ) . ')' : 'None',
			'Waiting after a failure' => $cache['next_attempt'] > time() ? 'Yes, until ' . $when( $cache['next_attempt'] ) : 'No',
		);
		?>
		<div class="notice notice-info kdna-uc-debug">
			<h2 style="margin:12px 0 8px;">KDNA Update Client: debug</h2>
			<table class="widefat striped" style="max-width:960px;margin-bottom:12px;">
				<tbody>
					<?php foreach ( $rows as $label => $value ) : ?>
						<tr><th style="width:240px;"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $value ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<table class="widefat striped" style="max-width:960px;margin-bottom:12px;">
				<thead><tr><th>Plugin</th><th>Installed</th><th>Server offers</th><th>Status</th><th>Auto</th><th>Notes</th></tr></thead>
				<tbody>
					<?php foreach ( KDNA_UpdateClient_Core::plugins() as $slug => $plugin ) : ?>
						<?php
						$data  = KDNA_UpdateClient_Core::server_data( $slug );
						$entry = $this->entry( $slug );
						?>
						<tr>
							<td><?php echo esc_html( $slug ); ?></td>
							<td><?php echo esc_html( $plugin['version'] ); ?></td>
							<td><?php echo esc_html( $data && $data['version'] ? $data['version'] : '–' ); ?></td>
							<td><?php echo esc_html( $entry ? $entry['status'] : ( $data ? 'nothing live' : 'not checked' ) ); ?></td>
							<td><?php echo esc_html( in_array( $plugin['basename'], (array) get_site_option( 'auto_update_plugins', array() ), true ) ? 'Automatic' : 'Manual' ); ?></td>
							<td><?php echo esc_html( trim( ( $entry ? $entry['reason'] : '' ) . ( $data && ! empty( $data['pinned'] ) ? ' Pinned on the server.' : '' ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php foreach ( KDNA_UpdateClient_Core::$notes as $note ) : ?>
				<p><strong>Note:</strong> <?php echo esc_html( $note ); ?></p>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
