<?php
/**
 * Applications: capturing new KDNA Forms entries into the open round, and loading them.
 *
 * KDNA Launchpad keeps a link to the KDNA Forms entry (its entry ID), plus the few
 * mapped answers it needs for checks and lists. The full answers stay in KDNA Forms.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Captures, loads and updates applications.
 */
class KDNA_Launchpad_Applications {

	/**
	 * Set when this request refused a submission because no round was open.
	 *
	 * @var bool
	 */
	private static $refused = false;

	/**
	 * Hooks into the Launchpad form in KDNA Forms. Called once KDNA Forms is available.
	 */
	public static function register() {
		$form_id = absint( kdna_launchpad_get_setting( 'form_id', 0 ) );

		if ( ! $form_id ) {
			return;
		}

		KDNA_Launchpad_Forms_Bridge::on_form_display( $form_id, array( __CLASS__, 'on_form_display' ) );
		KDNA_Launchpad_Forms_Bridge::on_validation( $form_id, array( __CLASS__, 'validate_round_open' ) );
		KDNA_Launchpad_Forms_Bridge::on_validation_message( $form_id, array( __CLASS__, 'closed_validation_message' ) );
		KDNA_Launchpad_Forms_Bridge::on_submission( $form_id, array( __CLASS__, 'capture' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Form display and submission
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Runs whenever the Launchpad form is shown: brings round statuses up to date.
	 */
	public static function on_form_display() {
		KDNA_Launchpad_Rounds::sync_statuses();
	}

	/**
	 * Refuses a submission when no round is open, before KDNA Forms saves anything.
	 *
	 * @param bool $is_valid Whether KDNA Forms found the submission valid so far.
	 * @return bool
	 */
	public static function validate_round_open( $is_valid ) {
		KDNA_Launchpad_Rounds::sync_statuses();

		if ( KDNA_Launchpad_Rounds::get_open_round() ) {
			return $is_valid;
		}

		self::$refused = true;

		return false;
	}

	/**
	 * Replaces KDNA Forms' usual error heading with the polite "applications have closed" message.
	 *
	 * @param string $markup KDNA Forms' validation message markup.
	 * @return string
	 */
	public static function closed_validation_message( $markup ) {
		if ( ! self::$refused ) {
			return $markup;
		}

		return '<h2 class="kdnaform_submission_error kdna-launchpad-closed-error">' . esc_html( self::closed_submission_message() ) . '</h2>';
	}

	/**
	 * The message shown when someone submits after the round has closed.
	 *
	 * @return string
	 */
	public static function closed_submission_message() {
		$next = KDNA_Launchpad_Rounds::get_next_round();

		if ( $next ) {
			return sprintf(
				/* translators: %s: date the next round opens. */
				__( 'Sorry, applications for this round have just closed. The next round opens on %s.', 'kdna-launchpad' ),
				KDNA_Launchpad_Rounds::format( $next['opens_at'], false )
			);
		}

		return __( 'Sorry, applications for this round have just closed. Follow KDNA for news of the next round.', 'kdna-launchpad' );
	}

	/**
	 * Adds a new KDNA Forms entry to the open round and runs the eligibility checks.
	 *
	 * @param array $entry The KDNA Forms entry.
	 * @param array $form  The KDNA Forms form.
	 */
	public static function capture( $entry, $form ) {
		$entry_id = isset( $entry['id'] ) ? absint( $entry['id'] ) : 0;

		// Ignore spam and anything already captured.
		if ( ! $entry_id || KDNA_Launchpad_Forms_Bridge::entry_is_spam( $entry ) || self::get_by_entry( $entry_id ) ) {
			return;
		}

		$round = KDNA_Launchpad_Rounds::get_open_round();
		if ( ! $round ) {
			return;
		}

		$values = self::mapped_values( $entry, $form );

		// Keep the registered name and state from the official ABN Lookup, when it's set up.
		$abn = KDNA_Launchpad_Abn::lookup( $values['abn'] );
		if ( ! is_wp_error( $abn ) ) {
			$values['abn_name']  = $abn['entity_name'];
			$values['abn_state'] = $abn['state'];
		}

		$application_id = self::create( $round['id'], $entry_id, $values );

		if ( ! $application_id ) {
			return;
		}

		KDNA_Launchpad_Eligibility::check( $application_id );

		/**
		 * Fires when a new application has been captured and checked.
		 *
		 * @param int   $application_id KDNA Launchpad application ID.
		 * @param int   $round_id       Round ID.
		 * @param array $entry          The KDNA Forms entry.
		 */
		do_action( 'kdna_launchpad_application_captured', $application_id, $round['id'], $entry );
	}

	/**
	 * Reads the mapped answers (name, email, business name, ABN, staff) from an entry.
	 *
	 * @param array $entry The KDNA Forms entry.
	 * @param array $form  The KDNA Forms form.
	 * @return array
	 */
	public static function mapped_values( $entry, $form = null ) {
		$map    = (array) kdna_launchpad_get_setting( 'field_map', array() );
		$values = array();

		foreach ( array( 'first_name', 'last_name', 'email', 'business_name', 'trading_as', 'industry', 'abn', 'staff' ) as $key ) {
			$field_id       = isset( $map[ $key ] ) ? (string) $map[ $key ] : '';
			$values[ $key ] = '' === $field_id ? '' : KDNA_Launchpad_Forms_Bridge::get_entry_value( $entry, $field_id, $form );
		}

		return $values;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Saving
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Saves a new application.
	 *
	 * @param int   $round_id Round ID.
	 * @param int   $entry_id KDNA Forms entry ID (0 for a test application without an entry).
	 * @param array $values   first_name, last_name, email, business_name, industry, abn, staff.
	 * @return int The application ID, or 0 on failure.
	 */
	public static function create( $round_id, $entry_id, $values ) {
		global $wpdb;

		$now = KDNA_Launchpad_Rounds::now_utc();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			KDNA_Launchpad_Install::applications_table(),
			array(
				'round_id'          => absint( $round_id ),
				'entry_id'          => absint( $entry_id ),
				'first_name'        => self::clip( $values, 'first_name' ),
				'last_name'         => self::clip( $values, 'last_name' ),
				'email'             => self::clip( $values, 'email', 191, 'sanitize_email' ),
				'business_name'     => self::clip( $values, 'business_name' ),
				'trading_as'        => self::clip( $values, 'trading_as' ),
				'abn_name'          => self::clip( $values, 'abn_name' ),
				'abn_state'         => self::clip( $values, 'abn_state', 10 ),
				'industry'          => self::clip( $values, 'industry' ),
				'abn'               => self::clip( $values, 'abn', 20 ),
				'staff'             => self::clip( $values, 'staff', 100 ),
				'eligibility_flags' => wp_json_encode( array() ),
				'scores'            => wp_json_encode( array() ),
				'outcome'           => 'pending',
				'created_at'        => $now,
				'updated_at'        => $now,
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates some columns of an application.
	 *
	 * @param int   $application_id Application ID.
	 * @param array $data           Column => value. Arrays and objects are stored as JSON.
	 * @return bool
	 */
	public static function update( $application_id, $data ) {
		global $wpdb;

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				$data[ $key ] = wp_json_encode( $value );
			}
		}

		$data['updated_at'] = KDNA_Launchpad_Rounds::now_utc();

		return false !== $wpdb->update( KDNA_Launchpad_Install::applications_table(), $data, array( 'id' => absint( $application_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Cleans a value and shortens it to fit its database column.
	 *
	 * @param array    $values   Values.
	 * @param string   $key      Which value.
	 * @param int      $length   Maximum length.
	 * @param callable $sanitize Cleaning function.
	 * @return string
	 */
	private static function clip( $values, $key, $length = 191, $sanitize = 'sanitize_text_field' ) {
		$value = isset( $values[ $key ] ) ? call_user_func( $sanitize, (string) $values[ $key ] ) : '';

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Loading
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Loads one application, or null.
	 *
	 * @param int $application_id Application ID.
	 * @return array|null
	 */
	public static function get( $application_id ) {
		global $wpdb;

		$table = KDNA_Launchpad_Install::applications_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $application_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return $row ? self::prepare( $row ) : null;
	}

	/**
	 * Loads the application linked to a KDNA Forms entry, or null.
	 *
	 * @param int $entry_id KDNA Forms entry ID.
	 * @return array|null
	 */
	public static function get_by_entry( $entry_id ) {
		global $wpdb;

		if ( ! $entry_id ) {
			return null;
		}

		$table = KDNA_Launchpad_Install::applications_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE entry_id = %d LIMIT 1", absint( $entry_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return $row ? self::prepare( $row ) : null;
	}

	/**
	 * Loads every application in a round, oldest first.
	 *
	 * @param int $round_id Round ID.
	 * @return array[]
	 */
	public static function get_for_round( $round_id ) {
		global $wpdb;

		$table = KDNA_Launchpad_Install::applications_table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE round_id = %d ORDER BY created_at ASC, id ASC", absint( $round_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return array_map( array( __CLASS__, 'prepare' ), (array) $rows );
	}

	/**
	 * Counts the applications in a round.
	 *
	 * @param int $round_id Round ID.
	 * @return int
	 */
	public static function count_for_round( $round_id ) {
		global $wpdb;

		$table = KDNA_Launchpad_Install::applications_table();

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE round_id = %d", absint( $round_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Counts applications for several rounds at once, as round ID => count.
	 *
	 * @return array
	 */
	public static function counts_by_round() {
		global $wpdb;

		$table  = KDNA_Launchpad_Install::applications_table();
		$rows   = $wpdb->get_results( "SELECT round_id, COUNT(*) AS total FROM {$table} GROUP BY round_id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['round_id'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Deletes every application in a round. Used by the test tool.
	 *
	 * @param int $round_id Round ID.
	 */
	public static function delete_for_round( $round_id ) {
		global $wpdb;

		$wpdb->delete( KDNA_Launchpad_Install::applications_table(), array( 'round_id' => absint( $round_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Tidies a database row: numbers as numbers and JSON decoded.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	private static function prepare( $row ) {
		foreach ( array( 'id', 'round_id', 'entry_id', 'total_score' ) as $key ) {
			$row[ $key ] = (int) $row[ $key ];
		}

		$row['notes']             = (string) $row['notes'];
		$row['trading_as']        = isset( $row['trading_as'] ) ? (string) $row['trading_as'] : '';
		$row['abn_name']          = isset( $row['abn_name'] ) ? (string) $row['abn_name'] : '';
		$row['abn_state']         = isset( $row['abn_state'] ) ? (string) $row['abn_state'] : '';
		$row['display_name']      = '' !== $row['trading_as'] ? $row['trading_as'] : (string) $row['business_name'];
		$row['ai_status']         = (string) $row['ai_status'];
		$row['ai_error']          = (string) $row['ai_error'];
		$row['ai_assessment']     = $row['ai_assessment'] ? (array) json_decode( $row['ai_assessment'], true ) : array();
		$row['contact_log']       = $row['contact_log'] ? (array) json_decode( $row['contact_log'], true ) : array();
		$row['email_status']      = (string) $row['email_status'];
		$row['shortlisted']       = (bool) $row['shortlisted'];
		$row['eligibility_flags'] = $row['eligibility_flags'] ? (array) json_decode( $row['eligibility_flags'], true ) : array();
		$row['scores']            = $row['scores'] ? (array) json_decode( $row['scores'], true ) : array();
		$row['is_eligible']       = KDNA_Launchpad_Eligibility::is_eligible( $row );

		return $row;
	}
}
