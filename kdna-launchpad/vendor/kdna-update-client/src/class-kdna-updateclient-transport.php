<?php
/**
 * Transport: signed requests to the update server.
 *
 * @package KDNA_Update_Client
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Talks to the update server's kdna-us/v1 API, signing every request
 * exactly as the server expects:
 *
 *     HMAC-SHA256( METHOD \n route \n timestamp \n nonce \n sha256(body) )
 *
 * sent in the X-KDNA-Site, X-KDNA-Timestamp, X-KDNA-Nonce and
 * X-KDNA-Signature headers. /register is signed with the fleet secret,
 * everything else with this site's own token.
 */
class KDNA_UpdateClient_Transport {

	/**
	 * How long to wait for the server, in seconds.
	 */
	const TIMEOUT = 5;

	/**
	 * How long to allow for a zip download, in seconds.
	 */
	const DOWNLOAD_TIMEOUT = 300;

	/**
	 * The largest zip the client will download, in bytes (200 MB).
	 */
	const MAX_DOWNLOAD = 209715200;

	/* ---------------------------------------------------------------------
	 * Sending requests
	 * ------------------------------------------------------------------ */

	/**
	 * Returns the full address of an API route on the update server.
	 *
	 * @param string $route For example "/kdna-us/v1/check".
	 * @return string
	 */
	public static function url( $route ) {
		$url = KDNA_UpdateClient_Core::$config['server_url'] . '/wp-json' . $route;
		return (string) apply_filters( 'kdna_uc_api_url', $url, $route );
	}

	/**
	 * Sends one signed POST request and returns the status and decoded JSON.
	 *
	 * @param string $route  REST route.
	 * @param array  $body   Data to send.
	 * @param string $who    "fleet" for /register, "site" for everything else.
	 * @return array{code:int,data:array,server_time:int}|WP_Error
	 */
	private static function post( $route, $body, $who ) {
		$state = KDNA_UpdateClient_Core::state();

		if ( 'fleet' === $who ) {
			$site_header = 'fleet';
			$key         = base64_decode( (string) KDNA_UpdateClient_Core::$config['fleet_secret'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		} else {
			$site_header = (string) (int) $state['site_id'];
			$key         = preg_match( '/^[a-f0-9]{64}$/', $state['token'] ) ? hex2bin( $state['token'] ) : false;
		}

		if ( false === $key || '' === $key ) {
			return new WP_Error( 'kdna_uc_no_key', 'The client has no key to sign with.' );
		}

		$json      = wp_json_encode( $body );
		$timestamp = (string) ( time() + (int) $state['offset'] );
		$nonce     = bin2hex( random_bytes( 16 ) );
		$sign      = implode( "\n", array( 'POST', $route, $timestamp, $nonce, hash( 'sha256', $json ) ) );

		$response = wp_remote_post(
			self::url( $route ),
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'headers'     => array(
					'Content-Type'     => 'application/json',
					'Accept'           => 'application/json',
					'X-KDNA-Site'      => $site_header,
					'X-KDNA-Timestamp' => $timestamp,
					'X-KDNA-Nonce'     => $nonce,
					'X-KDNA-Signature' => hash_hmac( 'sha256', $sign, $key ),
				),
				'body'        => $json,
				'user-agent'  => 'KDNA-Update-Client/' . KDNA_UpdateClient_Core::$version . '; WordPress/' . get_bloginfo( 'version' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'kdna_uc_unreachable', 'The update server could not be reached: ' . $response->get_error_message() );
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return array(
			'code'        => (int) wp_remote_retrieve_response_code( $response ),
			'data'        => is_array( $data ) ? $data : array(),
			'server_time' => (int) wp_remote_retrieve_header( $response, 'x-kdna-server-time' ),
		);
	}

	/**
	 * Sends a request signed with this site's token, registering first if
	 * needed, and recovering once from the usual problems:
	 *
	 * - 401 (token not accepted, or the clock is wrong): fix the clock
	 *   difference or register again, then retry once.
	 * - 409 "reregister" (the site has moved or been cloned): register
	 *   again as a new site, then retry once.
	 * - 403 (blocked): stop.
	 *
	 * @param string $route REST route.
	 * @param array  $body  Data to send.
	 * @return array{code:int,data:array}|WP_Error
	 */
	private static function site_request( $route, $body ) {
		$registered = self::ensure_registered();
		if ( is_wp_error( $registered ) ) {
			return $registered;
		}

		$result = self::post( $route, $body, 'site' );

		if ( ! is_wp_error( $result ) && in_array( $result['code'], array( 401, 409 ), true ) ) {
			$retry = false;

			if ( 401 === $result['code'] && self::fix_clock( $result['server_time'] ) ) {
				$retry = true;
			} elseif ( 401 === $result['code'] || ( 409 === $result['code'] && 'reregister' === ( $result['data']['code'] ?? '' ) ) ) {
				$registered = self::register();
				if ( is_wp_error( $registered ) ) {
					return $registered;
				}
				$retry = true;
			}

			if ( $retry ) {
				$result = self::post( $route, $body, 'site' );
			}
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( 403 === $result['code'] ) {
			return new WP_Error( 'kdna_uc_blocked', 'The update server refused this site (403). It may have been blocked.' );
		}

		return $result;
	}

	/**
	 * If the server says its clock is more than 4 minutes away from ours,
	 * remembers the difference so signed timestamps match the server.
	 *
	 * @param int $server_time The server's time from its response header.
	 * @return bool True if the difference was large and has been corrected.
	 */
	private static function fix_clock( $server_time ) {
		if ( $server_time <= 0 ) {
			return false;
		}
		$state  = KDNA_UpdateClient_Core::state();
		$offset = $server_time - time();

		if ( abs( $offset - (int) $state['offset'] ) < 240 ) {
			return false;
		}

		$state['offset'] = $offset;
		KDNA_UpdateClient_Core::save_state( $state );
		return true;
	}

	/* ---------------------------------------------------------------------
	 * Registration
	 * ------------------------------------------------------------------ */

	/**
	 * Registers if this site has no token yet, or its address has changed
	 * since it registered (it was moved, or cloned to staging).
	 *
	 * @return true|WP_Error
	 */
	public static function ensure_registered() {
		$state = KDNA_UpdateClient_Core::state();

		if ( $state['site_id'] && preg_match( '/^[a-f0-9]{64}$/', $state['token'] ) && KDNA_UpdateClient_Core::site_url() === $state['site_url'] ) {
			return true;
		}

		return self::register();
	}

	/**
	 * First contact: sends the site address and install ID, signed with the
	 * fleet secret, and stores the site ID and token the server returns.
	 *
	 * @return true|WP_Error
	 */
	public static function register() {
		$state = KDNA_UpdateClient_Core::state();

		$result = self::post(
			'/kdna-us/v1/register',
			array(
				'site_url'    => KDNA_UpdateClient_Core::site_url(),
				'install_id'  => $state['install_id'],
				'wp_version'  => get_bloginfo( 'version' ),
				'php_version' => PHP_VERSION,
			),
			'fleet'
		);

		// A wrong clock is the most common reason a first registration fails.
		if ( ! is_wp_error( $result ) && 401 === $result['code'] && self::fix_clock( $result['server_time'] ) ) {
			$result = self::post(
				'/kdna-us/v1/register',
				array(
					'site_url'    => KDNA_UpdateClient_Core::site_url(),
					'install_id'  => $state['install_id'],
					'wp_version'  => get_bloginfo( 'version' ),
					'php_version' => PHP_VERSION,
				),
				'fleet'
			);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( 403 === $result['code'] ) {
			return new WP_Error( 'kdna_uc_blocked', 'The update server refused this site (403). It may have been blocked.' );
		}
		if ( 200 !== $result['code'] || empty( $result['data']['site_id'] ) || ! preg_match( '/^[a-f0-9]{64}$/', (string) ( $result['data']['site_token'] ?? '' ) ) ) {
			return new WP_Error( 'kdna_uc_register_failed', sprintf( 'Registration with the update server failed (HTTP %d).', $result['code'] ) );
		}

		$state             = KDNA_UpdateClient_Core::state();
		$state['site_id']  = (int) $result['data']['site_id'];
		$state['token']    = (string) $result['data']['site_token'];
		$state['site_url'] = KDNA_UpdateClient_Core::site_url();
		KDNA_UpdateClient_Core::save_state( $state );

		return true;
	}

	/* ---------------------------------------------------------------------
	 * The API calls
	 * ------------------------------------------------------------------ */

	/**
	 * The combined check: every KDNA plugin and version on this site, in one request.
	 *
	 * @param array $versions slug => installed version.
	 * @param array $auto     slug => the site's own auto-update choice (true or false).
	 * @return array|WP_Error The server's reply (site_id, plugins).
	 */
	public static function check( $versions, $auto = array() ) {
		$result = self::site_request(
			'/kdna-us/v1/check',
			array(
				'site_url'       => KDNA_UpdateClient_Core::site_url(),
				'wp_version'     => get_bloginfo( 'version' ),
				'php_version'    => PHP_VERSION,
				'client_version' => KDNA_UpdateClient_Core::$version,
				'plugins'        => (object) $versions,
				'auto'           => (object) $auto,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( 200 !== $result['code'] ) {
			return new WP_Error( 'kdna_uc_check_failed', sprintf( 'The update check failed (HTTP %d).', $result['code'] ) );
		}

		return $result['data'];
	}

	/**
	 * Asks for a fresh single-use download link, at the moment an update starts.
	 *
	 * @param string $slug    Plugin slug.
	 * @param string $version The version being installed.
	 * @return array|WP_Error url, version, sha256, signature.
	 */
	public static function link( $slug, $version ) {
		$result = self::site_request(
			'/kdna-us/v1/link',
			array(
				'slug'    => $slug,
				'version' => $version,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( 409 === $result['code'] && 'target_changed' === ( $result['data']['code'] ?? '' ) ) {
			return new WP_Error( 'kdna_uc_target_changed', 'This update is out of date because the version on offer has changed. Go back to the Plugins screen, refresh, and try again.' );
		}
		if ( 200 !== $result['code'] || empty( $result['data']['url'] ) ) {
			return new WP_Error( 'kdna_uc_link_failed', sprintf( 'The update server did not provide a download link (HTTP %d).', $result['code'] ) );
		}

		return $result['data'];
	}

	/**
	 * Tells the server how an update went.
	 *
	 * @param string $slug    Plugin slug.
	 * @param string $from    Version before.
	 * @param string $to      Version being installed.
	 * @param bool   $success Whether it worked.
	 * @param string $error   Error message on failure.
	 * @param string $code    Short code, e.g. "security_check".
	 */
	public static function report( $slug, $from, $to, $success, $error = '', $code = '' ) {
		self::site_request(
			'/kdna-us/v1/report',
			array(
				'slug'    => $slug,
				'from'    => $from,
				'to'      => $to,
				'success' => (bool) $success,
				'error'   => $error,
				'code'    => $code,
			)
		);
	}

	/**
	 * Downloads a zip from a link the server provided, to a temporary file.
	 *
	 * Only links on the update server itself are accepted.
	 *
	 * @param string $url The single-use link.
	 * @return string|WP_Error Path of the downloaded file.
	 */
	public static function download( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( $host !== KDNA_UpdateClient_Core::server_host() || ! KDNA_UpdateClient_Core::scheme_allowed( $url ) ) {
			return new WP_Error( 'kdna_uc_bad_link', 'The download link does not point at the update server.' );
		}

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp = wp_tempnam( 'kdna-update.zip' );
		if ( ! $tmp ) {
			return new WP_Error( 'kdna_uc_tmp', 'Could not create a temporary file for the download.' );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => self::DOWNLOAD_TIMEOUT,
				'redirection' => 0,
				'stream'      => true,
				'filename'    => $tmp,
				// No KDNA plugin should come close to this; it stops a bad server filling the disk.
				'limit_response_size' => self::MAX_DOWNLOAD,
				'user-agent'  => 'KDNA-Update-Client/' . KDNA_UpdateClient_Core::$version,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'kdna_uc_download_failed', 'The download failed: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'kdna_uc_download_failed', sprintf( 'The download failed (HTTP %d). The link may have been used already or expired.', $code ) );
		}

		return $tmp;
	}
}
