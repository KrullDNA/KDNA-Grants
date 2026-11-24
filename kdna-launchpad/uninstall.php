<?php
/**
 * Runs when KDNA Launchpad is deleted from the Plugins screen.
 *
 * Data is only removed if "Delete all KDNA Launchpad data when the plugin is
 * deleted" is ticked in Launchpad > Settings (off by default, and ticking it
 * asks for confirmation). KDNA Forms entries are never touched.
 *
 * @package KDNA_Launchpad
 */

// Only run when WordPress itself is uninstalling the plugin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$kdna_launchpad_settings = get_option( 'kdna_launchpad_settings', array() );

if ( ! is_array( $kdna_launchpad_settings ) || empty( $kdna_launchpad_settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Remove the two KDNA Launchpad tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}kdna_launchpad_applications" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}kdna_launchpad_rounds" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}kdna_launchpad_offers" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Remove settings.
delete_option( 'kdna_launchpad_settings' );
delete_option( 'kdna_launchpad_db_version' );
delete_option( 'kdna_launchpad_settings_version' );
delete_option( 'kdna_launchpad_claude_key' );

// Remove the judge capability from every role.
foreach ( wp_roles()->role_objects as $kdna_launchpad_role ) {
	$kdna_launchpad_role->remove_cap( 'kdna_launchpad_judge' );
}

// Remove every scheduled KDNA Launchpad task.
wp_unschedule_hook( 'kdna_launchpad_cron' );
wp_unschedule_hook( 'kdna_launchpad_round_tick' );
wp_unschedule_hook( 'kdna_launchpad_send_emails' );
wp_unschedule_hook( 'kdna_launchpad_retention' );
wp_unschedule_hook( 'kdna_launchpad_offers_daily' );
wp_unschedule_hook( 'kdna_launchpad_claude_process' );
