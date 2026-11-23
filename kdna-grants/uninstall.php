<?php
/**
 * Runs when KDNA Grants is deleted from the Plugins screen.
 *
 * Data is only removed if "Delete all KDNA Grants data when the plugin is
 * deleted" is ticked in KDNA Grants > Settings (off by default, and ticking it
 * asks for confirmation). KDNA Forms entries are never touched.
 *
 * @package KDNA_Grants
 */

// Only run when WordPress itself is uninstalling the plugin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$kdna_grants_settings = get_option( 'kdna_grants_settings', array() );

if ( ! is_array( $kdna_grants_settings ) || empty( $kdna_grants_settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Remove the two KDNA Grants tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}kdna_grants_applications" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}kdna_grants_rounds" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}kdna_grants_offers" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Remove settings.
delete_option( 'kdna_grants_settings' );
delete_option( 'kdna_grants_db_version' );
delete_option( 'kdna_grants_claude_key' );

// Remove the judge capability from every role.
foreach ( wp_roles()->role_objects as $kdna_grants_role ) {
	$kdna_grants_role->remove_cap( 'kdna_grants_judge' );
}

// Remove every scheduled KDNA Grants task.
wp_unschedule_hook( 'kdna_grants_cron' );
wp_unschedule_hook( 'kdna_grants_round_tick' );
wp_unschedule_hook( 'kdna_grants_send_emails' );
wp_unschedule_hook( 'kdna_grants_retention' );
wp_unschedule_hook( 'kdna_grants_offers_daily' );
wp_unschedule_hook( 'kdna_grants_claude_process' );
