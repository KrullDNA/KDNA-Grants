<?php
/**
 * Plugin Name:       KDNA Launchpad
 * Plugin URI:        https://krulldna.com
 * Description:       Runs KDNA Launchpad, KDNA's quarterly free website program, on top of KDNA Forms: rounds, eligibility checks, judging, the winner decision and a full history.
 * Version:           1.3.9
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Krull Design & Advertising (KDNA)
 * Author URI:        https://krulldna.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kdna-launchpad
 * Update URI:        https://updates.krulldna.com
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// KDNA Updates client: lets this plugin receive updates from updates.krulldna.com.
require_once __DIR__ . '/vendor/kdna-update-client/load.php';
kdna_update_client_register( __FILE__, 'kdna-launchpad' );

/*
 * ---------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------------
 */

define( 'KDNA_LAUNCHPAD_VERSION', '1.3.9' );
define( 'KDNA_LAUNCHPAD_DB_VERSION', '1.3.0' );
define( 'KDNA_LAUNCHPAD_FILE', __FILE__ );
define( 'KDNA_LAUNCHPAD_PATH', plugin_dir_path( __FILE__ ) );
define( 'KDNA_LAUNCHPAD_URL', plugin_dir_url( __FILE__ ) );

// The oldest KDNA Forms version this plugin has been checked against.
define( 'KDNA_LAUNCHPAD_MIN_FORMS_VERSION', '3.7.1' );

// The name of the single option that holds every KDNA Launchpad setting.
define( 'KDNA_LAUNCHPAD_OPTION', 'kdna_launchpad_settings' );

// The custom capability that lets someone see and score applications.
define( 'KDNA_LAUNCHPAD_CAP', 'kdna_launchpad_judge' );

/*
 * ---------------------------------------------------------------------------
 * Includes
 * ---------------------------------------------------------------------------
 */

require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-install.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-forms-bridge.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-rounds.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-applications.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-eligibility.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-judging.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-emails.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-email-design.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-privacy.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-test-tool.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-export.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-frontend.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-tags.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-offers.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-claude.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-contact.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-abn.php';
require_once KDNA_LAUNCHPAD_PATH . 'includes/class-kdna-launchpad-form.php';
require_once KDNA_LAUNCHPAD_PATH . 'admin/class-kdna-launchpad-admin.php';
require_once KDNA_LAUNCHPAD_PATH . 'admin/class-kdna-launchpad-admin-rounds.php';
require_once KDNA_LAUNCHPAD_PATH . 'admin/class-kdna-launchpad-admin-applications.php';
require_once KDNA_LAUNCHPAD_PATH . 'admin/class-kdna-launchpad-admin-picks.php';
require_once KDNA_LAUNCHPAD_PATH . 'admin/class-kdna-launchpad-admin-offers.php';
require_once KDNA_LAUNCHPAD_PATH . 'admin/class-kdna-launchpad-admin-contact.php';

/*
 * ---------------------------------------------------------------------------
 * Activation
 * ---------------------------------------------------------------------------
 */

// Creates the tables, default settings and the judge capability. Safe without KDNA Forms.
register_activation_hook( __FILE__, array( 'KDNA_Launchpad_Install', 'activate' ) );

/*
 * ---------------------------------------------------------------------------
 * Settings helpers
 * ---------------------------------------------------------------------------
 */

/**
 * Returns all KDNA Launchpad settings, with defaults filled in for anything not yet saved.
 *
 * @return array
 */
function kdna_launchpad_get_settings() {
	$saved = get_option( KDNA_LAUNCHPAD_OPTION, array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	$settings = array_merge( KDNA_Launchpad_Install::default_settings(), $saved );

	// Make sure every field map key exists, even if new ones are added in later versions.
	$settings['field_map'] = array_merge(
		KDNA_Launchpad_Install::default_settings()['field_map'],
		is_array( $settings['field_map'] ) ? $settings['field_map'] : array()
	);

	return $settings;
}

/**
 * Returns one KDNA Launchpad setting.
 *
 * @param string $key     The setting name.
 * @param mixed  $fallback Returned if the setting does not exist.
 * @return mixed
 */
function kdna_launchpad_get_setting( $key, $fallback = null ) {
	$settings = kdna_launchpad_get_settings();

	return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
}

/*
 * ---------------------------------------------------------------------------
 * Boot
 * ---------------------------------------------------------------------------
 */

/**
 * Starts the plugin on init, once every plugin has loaded and translations are ready.
 *
 * If KDNA Forms is missing or too old, only the admin notice runs. Nothing
 * else is loaded, so nothing can cause an error.
 */
function kdna_launchpad_boot() {
	// Keep the database up to date after an update (activation does not run on updates).
	KDNA_Launchpad_Install::maybe_upgrade();

	$admin = new KDNA_Launchpad_Admin();
	$admin->register();

	if ( ! KDNA_Launchpad_Forms_Bridge::is_available() ) {
		return;
	}

	KDNA_Launchpad_Rounds::register();
	KDNA_Launchpad_Applications::register();
	KDNA_Launchpad_Emails::register();
	KDNA_Launchpad_Privacy::register();
	KDNA_Launchpad_Frontend::register();
	KDNA_Launchpad_Tags::register();
	KDNA_Launchpad_Offers::register();
	KDNA_Launchpad_Claude::register();
	KDNA_Launchpad_Abn::register();
	KDNA_Launchpad_Form::register();

	/**
	 * Fires once KDNA Launchpad is fully running with KDNA Forms.
	 */
	do_action( 'kdna_launchpad_loaded' );
}
add_action( 'init', 'kdna_launchpad_boot', 5 );

/**
 * Stops the scheduled KDNA Launchpad tasks when the plugin is deactivated.
 */
function kdna_launchpad_deactivate() {
	KDNA_Launchpad_Rounds::clear_cron();
	wp_clear_scheduled_hook( KDNA_Launchpad_Emails::CRON_HOOK );
	wp_clear_scheduled_hook( KDNA_Launchpad_Privacy::CRON_HOOK );
	wp_clear_scheduled_hook( KDNA_Launchpad_Offers::CRON_HOOK );
	wp_clear_scheduled_hook( KDNA_Launchpad_Claude::CRON_HOOK );
}
register_deactivation_hook( __FILE__, 'kdna_launchpad_deactivate' );
