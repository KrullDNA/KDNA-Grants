<?php
/**
 * Plugin Name:       KDNA Grants
 * Plugin URI:        https://krulldna.com
 * Description:       Runs the KDNA Website Grant on top of KDNA Forms: rounds, eligibility checks, judging, the winner decision and a full history.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Krull Design & Advertising (KDNA)
 * Author URI:        https://krulldna.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kdna-grants
 * Update URI:        https://updates.krulldna.com
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// KDNA Updates client: lets this plugin receive updates from updates.krulldna.com.
require_once __DIR__ . '/vendor/kdna-update-client/load.php';
kdna_update_client_register( __FILE__, 'kdna-grants' );

/*
 * ---------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------------
 */

define( 'KDNA_GRANTS_VERSION', '1.0.0' );
define( 'KDNA_GRANTS_DB_VERSION', '1.0.0' );
define( 'KDNA_GRANTS_FILE', __FILE__ );
define( 'KDNA_GRANTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'KDNA_GRANTS_URL', plugin_dir_url( __FILE__ ) );

// The oldest KDNA Forms version this plugin has been checked against.
define( 'KDNA_GRANTS_MIN_FORMS_VERSION', '3.7.1' );

// The name of the single option that holds every KDNA Grants setting.
define( 'KDNA_GRANTS_OPTION', 'kdna_grants_settings' );

// The custom capability that lets someone see and score applications.
define( 'KDNA_GRANTS_CAP', 'kdna_grants_judge' );

/*
 * ---------------------------------------------------------------------------
 * Includes
 * ---------------------------------------------------------------------------
 */

require_once KDNA_GRANTS_PATH . 'includes/class-kdna-grants-install.php';
require_once KDNA_GRANTS_PATH . 'includes/class-kdna-grants-forms-bridge.php';
require_once KDNA_GRANTS_PATH . 'includes/class-kdna-grants-rounds.php';
require_once KDNA_GRANTS_PATH . 'includes/class-kdna-grants-applications.php';
require_once KDNA_GRANTS_PATH . 'includes/class-kdna-grants-eligibility.php';
require_once KDNA_GRANTS_PATH . 'includes/class-kdna-grants-judging.php';
require_once KDNA_GRANTS_PATH . 'includes/class-kdna-grants-emails.php';
require_once KDNA_GRANTS_PATH . 'includes/class-kdna-grants-privacy.php';
require_once KDNA_GRANTS_PATH . 'admin/class-kdna-grants-admin.php';

/*
 * ---------------------------------------------------------------------------
 * Activation
 * ---------------------------------------------------------------------------
 */

// Creates the tables, default settings and the judge capability. Safe without KDNA Forms.
register_activation_hook( __FILE__, array( 'KDNA_Grants_Install', 'activate' ) );

/*
 * ---------------------------------------------------------------------------
 * Settings helpers
 * ---------------------------------------------------------------------------
 */

/**
 * Returns all KDNA Grants settings, with defaults filled in for anything not yet saved.
 *
 * @return array
 */
function kdna_grants_get_settings() {
	$saved = get_option( KDNA_GRANTS_OPTION, array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	$settings = array_merge( KDNA_Grants_Install::default_settings(), $saved );

	// Make sure every field map key exists, even if new ones are added in later versions.
	$settings['field_map'] = array_merge(
		KDNA_Grants_Install::default_settings()['field_map'],
		is_array( $settings['field_map'] ) ? $settings['field_map'] : array()
	);

	return $settings;
}

/**
 * Returns one KDNA Grants setting.
 *
 * @param string $key     The setting name.
 * @param mixed  $default Returned if the setting does not exist.
 * @return mixed
 */
function kdna_grants_get_setting( $key, $default = null ) {
	$settings = kdna_grants_get_settings();

	return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
}

/*
 * ---------------------------------------------------------------------------
 * Boot
 * ---------------------------------------------------------------------------
 */

/**
 * Starts the plugin once every plugin has loaded, so we can tell whether KDNA Forms is there.
 *
 * If KDNA Forms is missing or too old, only the admin notice and the Settings
 * screen's notice run. Nothing else is loaded, so nothing can cause an error.
 */
function kdna_grants_boot() {
	// Keep the database up to date after an update (activation does not run on updates).
	// Runs on init, because the default settings contain translatable text.
	add_action( 'init', array( 'KDNA_Grants_Install', 'maybe_upgrade' ), 5 );

	$admin = new KDNA_Grants_Admin();
	$admin->register();

	if ( ! KDNA_Grants_Forms_Bridge::is_available() ) {
		return;
	}

	/*
	 * Everything below needs KDNA Forms. Later stages hook in here:
	 * rounds, capture, eligibility, judging, emails, privacy and the front end.
	 */
	do_action( 'kdna_grants_loaded' );
}
add_action( 'plugins_loaded', 'kdna_grants_boot', 20 );
