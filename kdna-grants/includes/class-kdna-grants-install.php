<?php
/**
 * Install: database tables, default settings and the judge capability.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sets up and upgrades everything KDNA Grants stores in the database.
 */
class KDNA_Grants_Install {

	/**
	 * Option that remembers which version of the tables is installed.
	 */
	const DB_VERSION_OPTION = 'kdna_grants_db_version';

	/*
	 * -----------------------------------------------------------------------
	 * Table names
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Full name of the rounds table, including the site's table prefix.
	 *
	 * @return string
	 */
	public static function rounds_table() {
		global $wpdb;
		return $wpdb->prefix . 'kdna_grants_rounds';
	}

	/**
	 * Full name of the applications table, including the site's table prefix.
	 *
	 * @return string
	 */
	public static function applications_table() {
		global $wpdb;
		return $wpdb->prefix . 'kdna_grants_applications';
	}

	/*
	 * -----------------------------------------------------------------------
	 * Activation and upgrades
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Runs when the plugin is activated. Works whether or not KDNA Forms is active.
	 */
	public static function activate() {
		self::create_tables();
		self::add_default_settings();
		self::add_capability();
	}

	/**
	 * Re-runs the table set-up if the plugin was updated to a version with a newer table layout.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== KDNA_GRANTS_DB_VERSION ) {
			self::activate();
		}
	}

	/**
	 * Creates (or updates) the two custom tables.
	 *
	 * dbDelta() compares these definitions with what is in the database and
	 * only adds what is missing, so it is safe to run more than once.
	 * All dates are stored in UTC and shown in the site's time zone.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$rounds          = self::rounds_table();
		$applications    = self::applications_table();

		// Rounds: one row per application period.
		$sql_rounds = "CREATE TABLE {$rounds} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			opens_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			closes_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			announce_date date DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'scheduled',
			winner_application_id bigint(20) unsigned DEFAULT NULL,
			decision_note text,
			criteria longtext,
			activity_log longtext,
			is_test tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY opens_at (opens_at),
			KEY closes_at (closes_at)
		) {$charset_collate};";

		// Applications: one row per KDNA Forms entry. The answers themselves stay in KDNA Forms.
		$sql_applications = "CREATE TABLE {$applications} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			round_id bigint(20) unsigned NOT NULL DEFAULT 0,
			entry_id bigint(20) unsigned NOT NULL DEFAULT 0,
			first_name varchar(191) NOT NULL DEFAULT '',
			last_name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			business_name varchar(191) NOT NULL DEFAULT '',
			abn varchar(20) NOT NULL DEFAULT '',
			staff varchar(100) NOT NULL DEFAULT '',
			eligibility_flags longtext,
			scores longtext,
			total_score smallint(5) unsigned NOT NULL DEFAULT 0,
			notes longtext,
			shortlisted tinyint(1) unsigned NOT NULL DEFAULT 0,
			outcome varchar(20) NOT NULL DEFAULT 'pending',
			email_status varchar(20) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY round_id (round_id),
			KEY entry_id (entry_id),
			KEY email (email),
			KEY abn (abn),
			KEY outcome (outcome)
		) {$charset_collate};";

		dbDelta( $sql_rounds );
		dbDelta( $sql_applications );

		update_option( self::DB_VERSION_OPTION, KDNA_GRANTS_DB_VERSION, false );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Settings
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The fields KDNA Grants needs to read from the application form.
	 *
	 * Key => label and help text shown on the Settings screen. 'types' limits
	 * the dropdown to suitable KDNA Forms field types (empty means any field).
	 *
	 * @return array
	 */
	public static function mappable_fields() {
		return array(
			'first_name'    => array(
				'label' => __( 'First name', 'kdna-grants' ),
				'help'  => __( 'Used in emails, for example "Hi {first_name}".', 'kdna-grants' ),
				'types' => array(),
			),
			'last_name'     => array(
				'label' => __( 'Last name', 'kdna-grants' ),
				'help'  => __( 'Shown on the applications list.', 'kdna-grants' ),
				'types' => array(),
			),
			'email'         => array(
				'label' => __( 'Email', 'kdna-grants' ),
				'help'  => __( 'Where the winner and unsuccessful emails go. Also used for the duplicate, past winner and existing client checks.', 'kdna-grants' ),
				'types' => array(),
			),
			'business_name' => array(
				'label' => __( 'Business name', 'kdna-grants' ),
				'help'  => __( 'Shown on the applications list and checked against your existing client list.', 'kdna-grants' ),
				'types' => array(),
			),
			'abn'           => array(
				'label' => __( 'ABN', 'kdna-grants' ),
				'help'  => __( 'Checked with the official ABN checksum, and for duplicates and past winners.', 'kdna-grants' ),
				'types' => array(),
			),
			'staff'         => array(
				'label' => __( 'Number of staff', 'kdna-grants' ),
				'help'  => __( 'Checked against the staff limit.', 'kdna-grants' ),
				'types' => array(),
			),
			'logo'          => array(
				'label' => __( 'Logo upload', 'kdna-grants' ),
				'help'  => __( 'Shown as an image on the judging screen. Only file upload fields are listed.', 'kdna-grants' ),
				'types' => array( 'fileupload' ),
			),
		);
	}

	/**
	 * Every setting with its default value. Later stages read the ones they need from here.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			// Stage 1: form selection and field mapping.
			'form_id'                  => 0,
			'field_map'                => array_fill_keys( array_keys( self::mappable_fields() ), '' ),

			// Stage 2: rounds and eligibility.
			'round_gap_months'         => 2,
			'staff_limit'              => 20,
			'existing_clients'         => '',

			// Stage 3: judging. Each criterion is scored 1 to 5.
			'criteria'                 => array(
				array( 'key' => 'fit', 'label' => __( 'Fit', 'kdna-grants' ) ),
				array( 'key' => 'impact', 'label' => __( 'Impact', 'kdna-grants' ) ),
				array( 'key' => 'readiness', 'label' => __( 'Readiness', 'kdna-grants' ) ),
				array( 'key' => 'story', 'label' => __( 'Story', 'kdna-grants' ) ),
			),
			'tiebreak_criterion'       => 'readiness',

			// Stage 4: privacy.
			'retention_months'         => 24,

			// Uninstall: off by default, so deleting the plugin never removes records by accident.
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * Saves the default settings the first time the plugin is activated. Never overwrites saved settings.
	 */
	public static function add_default_settings() {
		if ( false === get_option( KDNA_GRANTS_OPTION, false ) ) {
			add_option( KDNA_GRANTS_OPTION, self::default_settings(), '', false );
		}
	}

	/*
	 * -----------------------------------------------------------------------
	 * Capability
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Gives administrators the judge capability. It can later be given to a guest judge's role.
	 */
	public static function add_capability() {
		$role = get_role( 'administrator' );

		if ( $role && ! $role->has_cap( KDNA_GRANTS_CAP ) ) {
			$role->add_cap( KDNA_GRANTS_CAP );
		}
	}
}
