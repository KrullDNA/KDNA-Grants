<?php
/**
 * Test tool: creates a test round of 6 sample applications covering every eligibility flag.
 *
 * Lets Nick check the eligibility logic without real applicants. Test rounds
 * never open for real applications, are never compared with real rounds,
 * and are removed in one click.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and removes the test rounds.
 */
class KDNA_Grants_Test_Tool {

	/**
	 * Email domain treated as an existing client, for the test applications only.
	 */
	const TEST_CLIENT_DOMAIN = 'kdna-test-client.example';

	/**
	 * True when test rounds exist.
	 *
	 * @return bool
	 */
	public static function exists() {
		return (bool) self::get_test_rounds();
	}

	/**
	 * All test rounds.
	 *
	 * @return array[]
	 */
	public static function get_test_rounds() {
		return array_values(
			array_filter(
				KDNA_Grants_Rounds::get_all( true ),
				function ( $round ) {
					return $round['is_test'];
				}
			)
		);
	}

	/**
	 * Creates the test rounds and sample applications, and runs the eligibility checks on them.
	 *
	 * Two test rounds are made: a decided "past" round with one winner (so the
	 * past winner check has something to find), and the main test round with
	 * 6 applications, each set up to trigger one flag, plus one with no flags.
	 *
	 * @return int The main test round's ID, or 0 if test rounds already exist.
	 */
	public static function create() {
		global $wpdb;

		if ( self::exists() ) {
			return 0;
		}

		$day = DAY_IN_SECONDS;

		// The past round, with a winner.
		$past_id = KDNA_Grants_Rounds::save(
			array(
				'name'          => __( 'Test round, past winner', 'kdna-grants' ),
				'opens_at'      => gmdate( KDNA_Grants_Rounds::DB_FORMAT, time() - 90 * $day ),
				'closes_at'     => gmdate( KDNA_Grants_Rounds::DB_FORMAT, time() - 60 * $day ),
				'announce_date' => gmdate( 'Y-m-d', time() - 45 * $day ),
				'is_test'       => true,
			)
		);

		$winner_id = self::add_application(
			$past_id,
			array(
				'first_name'    => 'Priya',
				'last_name'     => 'Shah',
				'email'         => 'priya@fernlanefloristry.example',
				'business_name' => 'Fern Lane Floristry',
				'industry'      => 'Retail',
				'abn'           => '50 677 448 539',
				'staff'         => '1 to 4',
			),
			false
		);

		KDNA_Grants_Applications::update( $winner_id, array( 'outcome' => 'winner' ) );

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			KDNA_Grants_Install::rounds_table(),
			array(
				'status'                => KDNA_Grants_Rounds::STATUS_DECIDED,
				'winner_application_id' => $winner_id,
			),
			array( 'id' => $past_id )
		);

		// The main test round: closed yesterday, so it is ready for judging.
		$round_id = KDNA_Grants_Rounds::save(
			array(
				'name'          => __( 'Test round', 'kdna-grants' ),
				'opens_at'      => gmdate( KDNA_Grants_Rounds::DB_FORMAT, time() - 14 * $day ),
				'closes_at'     => gmdate( KDNA_Grants_Rounds::DB_FORMAT, time() - $day ),
				'announce_date' => gmdate( 'Y-m-d', time() + 14 * $day ),
				'is_test'       => true,
			)
		);

		foreach ( self::samples() as $sample ) {
			self::add_application( $round_id, $sample, true );
		}

		return $round_id;
	}

	/**
	 * The 6 sample applications, in the order they are added. Each note says which flag it should get.
	 *
	 * @return array[]
	 */
	private static function samples() {
		return array(
			// 1. No flags.
			array(
				'first_name'    => 'Sam',
				'last_name'     => 'Nguyen',
				'email'         => 'hello@brightsidebakery.example',
				'business_name' => 'Brightside Bakery',
				'industry'      => 'Food and hospitality',
				'abn'           => '53 239 173 164',
				'staff'         => '5 to 19',
			),
			// 2. ABN: fails the checksum.
			array(
				'first_name'    => 'Lena',
				'last_name'     => 'Morris',
				'email'         => 'lena@coastalclean.example',
				'business_name' => 'Coastal Clean Co',
				'industry'      => 'Cleaning services',
				'abn'           => '12 345 678 901',
				'staff'         => '1 to 4',
			),
			// 3. Staff: 20 or more.
			array(
				'first_name'    => 'Tom',
				'last_name'     => 'Walker',
				'email'         => 'tom@harbourphysio.example',
				'business_name' => 'Harbour Physio',
				'industry'      => 'Health',
				'abn'           => '53 488 976 635',
				'staff'         => '20 or more',
			),
			// 4. Existing client: email domain is on the (test) client list.
			array(
				'first_name'    => 'Jo',
				'last_name'     => 'Bennett',
				'email'         => 'jo@' . self::TEST_CLIENT_DOMAIN,
				'business_name' => 'Mountain Coffee Roasters',
				'industry'      => 'Food and hospitality',
				'abn'           => '81 173 821 296',
				'staff'         => '1 to 4',
			),
			// 5. Duplicate: same email as sample 1.
			array(
				'first_name'    => 'Sam',
				'last_name'     => 'Nguyen',
				'email'         => 'hello@brightsidebakery.example',
				'business_name' => 'Brightside Catering',
				'industry'      => 'Food and hospitality',
				'abn'           => '65 957 077 061',
				'staff'         => '1 to 4',
			),
			// 6. Past winner: same ABN as the winner of the past test round.
			array(
				'first_name'    => 'Priya',
				'last_name'     => 'Shah',
				'email'         => 'studio@fernlane.example',
				'business_name' => 'Fern Lane Events',
				'industry'      => 'Events',
				'abn'           => '50 677 448 539',
				'staff'         => '1 to 4',
			),
		);
	}

	/**
	 * Adds one sample application, with a matching KDNA Forms entry when the grant form is set up.
	 *
	 * The entry gives the judging screen (Stage 3) real answers to show. It is saved
	 * directly, so no notification emails are sent.
	 *
	 * @param int   $round_id  Round ID.
	 * @param array $values    Mapped values.
	 * @param bool  $run_check Whether to run the eligibility checks.
	 * @return int Application ID.
	 */
	private static function add_application( $round_id, $values, $run_check ) {
		$entry_id = self::add_entry( $values );
		$app_id   = KDNA_Grants_Applications::create( $round_id, $entry_id, $values );

		if ( $app_id && $run_check ) {
			KDNA_Grants_Eligibility::check( $app_id, array( self::TEST_CLIENT_DOMAIN ) );
		}

		return $app_id;
	}

	/**
	 * Saves a KDNA Forms entry for a sample, using the field mapping. Returns 0 if the form isn't set up.
	 *
	 * @param array $values Mapped values.
	 * @return int Entry ID or 0.
	 */
	private static function add_entry( $values ) {
		$form_id = absint( kdna_grants_get_setting( 'form_id', 0 ) );
		$map     = (array) kdna_grants_get_setting( 'field_map', array() );

		if ( ! $form_id ) {
			return 0;
		}

		$entry = array();
		foreach ( $values as $key => $value ) {
			if ( ! empty( $map[ $key ] ) ) {
				$entry[ $map[ $key ] ] = $value;
			}
		}

		if ( ! $entry ) {
			return 0;
		}

		return KDNA_Grants_Forms_Bridge::add_test_entry( $form_id, $entry );
	}

	/**
	 * Removes every test round, its applications and the KDNA Forms entries the test tool created.
	 *
	 * @return int Number of test rounds removed.
	 */
	public static function delete() {
		global $wpdb;

		$rounds = self::get_test_rounds();

		foreach ( $rounds as $round ) {
			foreach ( KDNA_Grants_Applications::get_for_round( $round['id'] ) as $application ) {
				KDNA_Grants_Forms_Bridge::delete_test_entry( $application['entry_id'] );
			}

			KDNA_Grants_Applications::delete_for_round( $round['id'] );

			$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				KDNA_Grants_Install::rounds_table(),
				array(
					'id'      => $round['id'],
					'is_test' => 1,
				)
			);
		}

		return count( $rounds );
	}
}
