<?php
/**
 * Verifier: checks a downloaded zip has not been tampered with.
 *
 * @package KDNA_Update_Client
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks a downloaded zip in two steps before WordPress may install it:
 *
 * 1. SHA-256 fingerprint: the file must match the fingerprint the server
 *    recorded when the release was uploaded.
 * 2. Ed25519 signature: the fingerprint, plugin slug and version must
 *    carry a valid signature from the update server's private key, checked
 *    with the public key built into config.php.
 *
 * The signed text is (joined with new lines):
 *     kdna-update-release-v1
 *     {slug}
 *     {version}
 *     {sha256}
 *
 * Even someone who controlled the update server's files, or the network,
 * cannot produce a valid signature without the private key, which never
 * leaves the server.
 */
class KDNA_UpdateClient_Verifier {

	/**
	 * Label the server adds to every signed message.
	 */
	const SIGNATURE_CONTEXT = 'kdna-update-release-v1';

	/**
	 * Makes sure the sodium signing functions are available, using the copy
	 * bundled with WordPress if PHP's own extension is missing.
	 *
	 * @return bool
	 */
	public static function load_sodium() {
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			$compat = ABSPATH . WPINC . '/sodium_compat/autoload.php';
			if ( is_readable( $compat ) ) {
				require_once $compat;
			}
		}
		return function_exists( 'sodium_crypto_sign_verify_detached' );
	}

	/**
	 * Checks a downloaded zip.
	 *
	 * @param string $path      The downloaded file.
	 * @param string $slug      Plugin slug.
	 * @param string $version   Version being installed.
	 * @param string $sha256    Fingerprint the server reported.
	 * @param string $signature Base64 signature the server reported.
	 * @return true|WP_Error
	 */
	public static function verify( $path, $slug, $version, $sha256, $signature ) {
		$fail = function ( $why ) {
			return new WP_Error( 'kdna_uc_security', 'This update failed a security check.', array( 'reason' => $why ) );
		};

		if ( ! is_readable( $path ) ) {
			return $fail( 'file_missing' );
		}

		// Step 1: the fingerprint of the file we actually received.
		$actual = hash_file( 'sha256', $path );
		if ( ! is_string( $sha256 ) || ! preg_match( '/^[a-f0-9]{64}$/', $sha256 ) || ! hash_equals( $sha256, (string) $actual ) ) {
			return $fail( 'sha256_mismatch' );
		}

		// Step 2: the signature over that fingerprint, slug and version.
		if ( ! self::load_sodium() ) {
			return $fail( 'no_sodium' );
		}

		$public = base64_decode( (string) KDNA_UpdateClient_Core::$config['public_key'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$sig    = base64_decode( (string) $signature, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $public || 32 !== strlen( $public ) || false === $sig || 64 !== strlen( $sig ) ) {
			return $fail( 'bad_key_or_signature' );
		}

		$message = implode( "\n", array( self::SIGNATURE_CONTEXT, $slug, $version, $actual ) );

		try {
			$valid = sodium_crypto_sign_verify_detached( $sig, $message, $public );
		} catch ( \Throwable $e ) {
			$valid = false;
		}

		return $valid ? true : $fail( 'signature_invalid' );
	}
}
