<?php
/**
 * KDNA Update Client: loader.
 *
 * Every KDNA plugin bundles its own copy of this folder at
 * vendor/kdna-update-client/. When several KDNA plugins sit on one site,
 * there are several copies, possibly at different versions. This file:
 *
 * 1. Records this copy's version and folder in a shared list.
 * 2. Defines kdna_update_client_register(), which only records data, so it
 *    is safe whichever copy defines it first.
 * 3. On plugins_loaded, loads the classes from the highest version found,
 *    exactly once. Result: one set of hooks and one combined check per site.
 *
 * Keep this file small and stable. Older copies of it may end up making
 * the choice, so it must never need to change.
 *
 * @package KDNA_Update_Client
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// The shared list of copies and registered plugins.
if ( ! isset( $GLOBALS['kdna_update_client'] ) || ! is_array( $GLOBALS['kdna_update_client'] ) ) {
	$GLOBALS['kdna_update_client'] = array(
		'copies'  => array(),
		'plugins' => array(),
		'booted'  => false,
	);
}

// Record this copy. If two plugins carry the same version, the first one found is used.
if ( ! isset( $GLOBALS['kdna_update_client']['copies']['1.1.0'] ) ) {
	$GLOBALS['kdna_update_client']['copies']['1.1.0'] = __DIR__;
}

if ( ! function_exists( 'kdna_update_client_register' ) ) {
	/**
	 * Tells the update client about a plugin. Call it from the plugin's main file:
	 *
	 *     require_once __DIR__ . '/vendor/kdna-update-client/load.php';
	 *     kdna_update_client_register( __FILE__, 'kdna-icon-button' );
	 *
	 * @param string $plugin_file Full path of the plugin's main file (__FILE__).
	 * @param string $slug        The plugin's folder name.
	 */
	function kdna_update_client_register( $plugin_file, $slug ) {
		$GLOBALS['kdna_update_client']['plugins'][ (string) $slug ] = (string) $plugin_file;
	}
}

if ( ! function_exists( 'kdna_update_client_boot' ) ) {
	/**
	 * Loads the newest copy of the client, once, after every plugin has loaded.
	 */
	function kdna_update_client_boot() {
		$registry = &$GLOBALS['kdna_update_client'];

		if ( ! empty( $registry['booted'] ) || empty( $registry['copies'] ) ) {
			return;
		}
		$registry['booted'] = true;

		// Pick the highest version number.
		$versions = array_keys( $registry['copies'] );
		usort( $versions, 'version_compare' );
		$version = end( $versions );
		$dir     = $registry['copies'][ $version ];

		foreach ( array( 'core', 'transport', 'verifier', 'updater' ) as $part ) {
			$file = $dir . '/src/class-kdna-updateclient-' . $part . '.php';
			if ( ! is_readable( $file ) ) {
				return;
			}
			require_once $file;
		}

		KDNA_UpdateClient_Core::boot( $dir, $version, $registry['plugins'], $registry['copies'] );
	}
	add_action( 'plugins_loaded', 'kdna_update_client_boot', 1 );
}
