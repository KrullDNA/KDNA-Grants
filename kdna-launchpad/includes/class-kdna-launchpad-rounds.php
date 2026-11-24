<?php
/**
 * Rounds: creating rounds, and opening and closing them on their dates.
 *
 * A round is open purely because "now" is between its opening and closing
 * times. That is worked out fresh every time it matters (when the form is
 * shown, when it is submitted, on admin screens and on WP Cron), so a round
 * never stays open late just because WP Cron didn't run. The stored status is
 * kept in step for lists and history, and is the only place "Decided" lives.
 *
 * All dates are stored in UTC and shown in the site's time zone.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads, saves and schedules rounds.
 */
class KDNA_Launchpad_Rounds {

	const STATUS_SCHEDULED = 'scheduled';
	const STATUS_OPEN      = 'open';
	const STATUS_JUDGING   = 'judging';
	const STATUS_DECIDED   = 'decided';

	/**
	 * Hourly WP Cron task that keeps round statuses in step.
	 */
	const CRON_HOOK = 'kdna_launchpad_cron';

	/**
	 * One-off WP Cron task scheduled for each round's exact opening and closing times.
	 */
	const TICK_HOOK = 'kdna_launchpad_round_tick';

	/**
	 * Date format used in the database.
	 */
	const DB_FORMAT = 'Y-m-d H:i:s';

	/**
	 * Hooks into WordPress. Called once KDNA Forms is available.
	 */
	public static function register() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'sync_statuses' ) );
		add_action( self::TICK_HOOK, array( __CLASS__, 'sync_statuses' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ) );
	}

	/**
	 * Makes sure the hourly status check is scheduled.
	 */
	public static function ensure_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Removes every scheduled KDNA Launchpad rounds task. Used when the plugin is deactivated.
	 */
	public static function clear_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_unschedule_hook( self::TICK_HOOK );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Loading rounds
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Loads one round, or null if it doesn't exist.
	 *
	 * @param int $round_id Round ID.
	 * @return array|null
	 */
	public static function get( $round_id ) {
		global $wpdb;

		$table = KDNA_Launchpad_Install::rounds_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $round_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return $row ? self::prepare( $row ) : null;
	}

	/**
	 * Loads every round, newest first.
	 *
	 * @param bool $include_test Whether to include test rounds.
	 * @return array[]
	 */
	public static function get_all( $include_test = true ) {
		global $wpdb;

		$table = KDNA_Launchpad_Install::rounds_table();
		$where = $include_test ? '' : 'WHERE is_test = 0';
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY opens_at DESC, id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return array_map( array( __CLASS__, 'prepare' ), (array) $rows );
	}

	/**
	 * The round that is open right now, worked out from the dates, or null. Test rounds never count.
	 *
	 * @return array|null
	 */
	public static function get_open_round() {
		global $wpdb;

		$table = KDNA_Launchpad_Install::rounds_table();
		$now   = self::now_utc();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE is_test = 0 AND status <> %s AND opens_at <= %s AND closes_at > %s ORDER BY opens_at ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::STATUS_DECIDED,
				$now,
				$now
			),
			ARRAY_A
		);

		return $row ? self::prepare( $row ) : null;
	}

	/**
	 * The next round that hasn't opened yet, or null. Used for "The next round opens on ...".
	 *
	 * @return array|null
	 */
	public static function get_next_round() {
		global $wpdb;

		$table = KDNA_Launchpad_Install::rounds_table();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE is_test = 0 AND opens_at > %s ORDER BY opens_at ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::now_utc()
			),
			ARRAY_A
		);

		return $row ? self::prepare( $row ) : null;
	}

	/**
	 * The most recent real round by opening date, or null. Used for "Create next round".
	 *
	 * @return array|null
	 */
	public static function get_latest_round() {
		global $wpdb;

		$table = KDNA_Launchpad_Install::rounds_table();
		$row   = $wpdb->get_row( "SELECT * FROM {$table} WHERE is_test = 0 ORDER BY opens_at DESC, id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return $row ? self::prepare( $row ) : null;
	}

	/**
	 * Tidies a database row: numbers as numbers, JSON decoded, and the live status worked out.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	private static function prepare( $row ) {
		$row['id']                    = (int) $row['id'];
		$row['winner_application_id'] = $row['winner_application_id'] ? (int) $row['winner_application_id'] : 0;
		$row['is_test']               = (bool) $row['is_test'];
		$row['decision_note']         = (string) $row['decision_note'];
		$row['ai_status']             = (string) $row['ai_status'];
		$row['ai_overview']           = $row['ai_overview'] ? (array) json_decode( $row['ai_overview'], true ) : array();
		$row['ai_usage']              = $row['ai_usage'] ? (array) json_decode( $row['ai_usage'], true ) : array();
		$row['announce_date']         = (string) $row['announce_date'];
		$row['criteria']              = $row['criteria'] ? json_decode( $row['criteria'], true ) : null;
		$row['activity_log']          = $row['activity_log'] ? (array) json_decode( $row['activity_log'], true ) : array();
		$row['status']                = self::status_for( $row );

		return $row;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Status
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Works out a round's status from its dates. "Decided" is only ever set when a winner is chosen.
	 *
	 * @param array $round Round row (dates in UTC).
	 * @return string
	 */
	public static function status_for( $round ) {
		if ( self::STATUS_DECIDED === $round['status'] ) {
			return self::STATUS_DECIDED;
		}

		$now = self::now_utc();

		if ( $now < $round['opens_at'] ) {
			return self::STATUS_SCHEDULED;
		}

		if ( $now < $round['closes_at'] ) {
			return self::STATUS_OPEN;
		}

		return self::STATUS_JUDGING;
	}

	/**
	 * Brings every stored status in step with the dates, and announces rounds that just opened or closed.
	 *
	 * Runs on WP Cron and also whenever the form is shown or submitted and on admin screens.
	 * Each change is made with a check on the old status, so the announcement fires only once
	 * even if two visitors trigger it at the same moment.
	 */
	public static function sync_statuses() {
		global $wpdb;

		$table = KDNA_Launchpad_Install::rounds_table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, status, opens_at, closes_at, is_test FROM {$table} WHERE status <> %s", self::STATUS_DECIDED ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		foreach ( (array) $rows as $row ) {
			$new = self::status_for( $row );

			if ( $new === $row['status'] ) {
				continue;
			}

			$changed = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array( 'status' => $new ),
				array(
					'id'     => (int) $row['id'],
					'status' => $row['status'],
				)
			);

			// Test rounds never announce anything, so add-ons can't act on test data.
			if ( ! $changed || ! empty( $row['is_test'] ) ) {
				continue;
			}

			if ( self::STATUS_OPEN === $new ) {
				/**
				 * Fires once when a round opens for applications.
				 *
				 * @param int $round_id Round ID.
				 */
				do_action( 'kdna_launchpad_round_opened', (int) $row['id'] );
			}

			if ( self::STATUS_JUDGING === $new ) {
				/**
				 * Fires once when a round closes for applications.
				 *
				 * @param int $round_id Round ID.
				 */
				do_action( 'kdna_launchpad_round_closed', (int) $row['id'] );
			}
		}
	}

	/**
	 * Plain-English name of a status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			self::STATUS_SCHEDULED => __( 'Scheduled', 'kdna-launchpad' ),
			self::STATUS_OPEN      => __( 'Open', 'kdna-launchpad' ),
			self::STATUS_JUDGING   => __( 'Judging', 'kdna-launchpad' ),
			self::STATUS_DECIDED   => __( 'Decided', 'kdna-launchpad' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Saving rounds
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Checks a round's details before saving. Returns a list of problems in plain English (empty if fine).
	 *
	 * @param array $data     Round details: opens_at and closes_at in UTC, announce_date as Y-m-d.
	 * @param int   $round_id ID of the round being edited, or 0 for a new round.
	 * @return string[]
	 */
	public static function validate( $data, $round_id = 0 ) {
		$errors = array();

		if ( empty( $data['opens_at'] ) ) {
			$errors[] = __( 'Please enter when the round opens.', 'kdna-launchpad' );
		}

		if ( empty( $data['closes_at'] ) ) {
			$errors[] = __( 'Please enter when the round closes.', 'kdna-launchpad' );
		}

		if ( $errors ) {
			return $errors;
		}

		if ( $data['closes_at'] <= $data['opens_at'] ) {
			$errors[] = __( 'The closing date must be after the opening date.', 'kdna-launchpad' );
		}

		if ( ! empty( $data['announce_date'] ) && $data['announce_date'] < self::to_site( $data['closes_at'], 'Y-m-d' ) ) {
			$errors[] = __( 'The winner announcement date can\'t be before the round closes.', 'kdna-launchpad' );
		}

		$overlap = self::find_overlap( $data['opens_at'], $data['closes_at'], $round_id );
		if ( $overlap ) {
			$errors[] = sprintf(
				/* translators: 1: round name, 2: opening date, 3: closing date. */
				__( 'These dates overlap with %1$s (%2$s to %3$s). Only one round can be open at a time.', 'kdna-launchpad' ),
				$overlap['name'],
				self::format( $overlap['opens_at'] ),
				self::format( $overlap['closes_at'] )
			);
		}

		return $errors;
	}

	/**
	 * Finds a real round whose dates overlap the given dates, or null.
	 *
	 * @param string $opens_at   UTC opening time.
	 * @param string $closes_at  UTC closing time.
	 * @param int    $exclude_id Round to ignore (the one being edited).
	 * @return array|null
	 */
	public static function find_overlap( $opens_at, $closes_at, $exclude_id = 0 ) {
		global $wpdb;

		$table = KDNA_Launchpad_Install::rounds_table();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE is_test = 0 AND id <> %d AND opens_at < %s AND closes_at > %s ORDER BY opens_at ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $exclude_id ),
				$closes_at,
				$opens_at
			),
			ARRAY_A
		);

		return $row ? self::prepare( $row ) : null;
	}

	/**
	 * Saves a new round or changes an existing one. Call validate() first.
	 *
	 * @param array $data     name, opens_at, closes_at (UTC), announce_date (Y-m-d), optional is_test.
	 * @param int   $round_id Round to update, or 0 to create one.
	 * @return int The round ID, or 0 on failure.
	 */
	public static function save( $data, $round_id = 0 ) {
		global $wpdb;

		$table = KDNA_Launchpad_Install::rounds_table();
		$now   = self::now_utc();

		$name = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';
		if ( '' === $name ) {
			$name = self::default_name( $data['opens_at'], $data['closes_at'] );
		}

		$row = array(
			'name'          => $name,
			'opens_at'      => $data['opens_at'],
			'closes_at'     => $data['closes_at'],
			'announce_date' => ! empty( $data['announce_date'] ) ? $data['announce_date'] : null,
			'updated_at'    => $now,
		);

		if ( $round_id ) {
			$wpdb->update( $table, $row, array( 'id' => absint( $round_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$row['status']     = self::STATUS_SCHEDULED;
			$row['is_test']    = ! empty( $data['is_test'] ) ? 1 : 0;
			$row['created_at'] = $now;
			$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$round_id = (int) $wpdb->insert_id;
		}

		if ( ! $round_id ) {
			return 0;
		}

		self::sync_statuses();
		self::schedule_ticks( $data['opens_at'], $data['closes_at'] );

		return (int) $round_id;
	}

	/**
	 * Deletes a round, as long as it has no applications. Test rounds are removed with the test tool instead.
	 *
	 * @param int $round_id Round ID.
	 * @return bool
	 */
	public static function delete( $round_id ) {
		global $wpdb;

		if ( KDNA_Launchpad_Applications::count_for_round( $round_id ) > 0 ) {
			return false;
		}

		return (bool) $wpdb->delete( KDNA_Launchpad_Install::rounds_table(), array( 'id' => absint( $round_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Adds a line to a round's activity log (for example "Reopened judging"), with the date and who did it.
	 *
	 * @param int    $round_id Round ID.
	 * @param string $message  What happened.
	 */
	public static function log( $round_id, $message ) {
		global $wpdb;

		$round = self::get( $round_id );
		if ( ! $round ) {
			return;
		}

		$user                    = wp_get_current_user();
		$round['activity_log'][] = array(
			'time'    => self::now_utc(),
			'user'    => $user && $user->exists() ? $user->display_name : '',
			'message' => (string) $message,
		);

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			KDNA_Launchpad_Install::rounds_table(),
			array( 'activity_log' => wp_json_encode( $round['activity_log'] ) ),
			array( 'id' => (int) $round_id )
		);
	}

	/**
	 * Schedules one-off status checks at the exact opening and closing times, as a back-up to the page checks.
	 *
	 * @param string $opens_at  UTC opening time.
	 * @param string $closes_at UTC closing time.
	 */
	private static function schedule_ticks( $opens_at, $closes_at ) {
		foreach ( array( $opens_at, $closes_at ) as $time ) {
			$timestamp = strtotime( $time . ' UTC' );

			if ( $timestamp > time() && ! wp_next_scheduled( self::TICK_HOOK, array( $timestamp ) ) ) {
				wp_schedule_single_event( $timestamp, self::TICK_HOOK, array( $timestamp ) );
			}
		}
	}

	/*
	 * -----------------------------------------------------------------------
	 * Suggested names and dates
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Suggests a round name from the quarter it opens in, such as "Q1 2027".
	 *
	 * Quarters follow the calendar year: Q1 is January to March, Q2 April to
	 * June, Q3 July to September and Q4 October to December. In emails this
	 * reads as "KDNA Launchpad (Q1 2027)".
	 *
	 * @param string $opens_at  UTC opening time.
	 * @param string $closes_at UTC closing time (kept so older callers still work).
	 * @return string
	 */
	public static function default_name( $opens_at, $closes_at = '' ) {
		$month = (int) self::to_site( $opens_at, 'n' );

		return sprintf(
			/* translators: 1: quarter number 1 to 4, 2: year. */
			__( 'Q%1$d %2$s', 'kdna-launchpad' ),
			(int) ceil( max( 1, $month ) / 3 ),
			self::to_site( $opens_at, 'Y' )
		);
	}

	/**
	 * Suggested dates for a new round, in the site's time zone.
	 *
	 * After an existing round: the same dates, moved forward by the gap set in Settings.
	 * For the very first round: opens tomorrow at 9am, closes a month later at 5pm,
	 * and the winner is announced two weeks after closing.
	 *
	 * @return array opens (Y-m-d\TH:i), closes (Y-m-d\TH:i), announce (Y-m-d), name.
	 */
	public static function suggest_next() {
		$tz     = wp_timezone();
		$latest = self::get_latest_round();

		if ( $latest ) {
			$gap = max( 1, absint( kdna_launchpad_get_setting( 'round_gap_months', 3 ) ) );

			$opens  = self::add_months( ( new DateTime( $latest['opens_at'], new DateTimeZone( 'UTC' ) ) )->setTimezone( $tz ), $gap );
			$closes = self::add_months( ( new DateTime( $latest['closes_at'], new DateTimeZone( 'UTC' ) ) )->setTimezone( $tz ), $gap );

			if ( $latest['announce_date'] ) {
				$announce = self::add_months( new DateTime( $latest['announce_date'], $tz ), $gap );
			} else {
				$announce = ( clone $closes )->modify( '+14 days' );
			}
		} else {
			// The first round: opens at the start of the next calendar quarter (1 January, April, July or
			// October), stays open for two months, and the winner is announced by the 15th of the month after.
			$now     = new DateTime( 'now', $tz );
			$month   = (int) $now->format( 'n' );
			$quarter = (int) ( floor( ( $month - 1 ) / 3 ) * 3 + 4 );
			$year    = (int) $now->format( 'Y' );

			if ( $quarter > 12 ) {
				$quarter -= 12;
				++$year;
			}

			$opens = new DateTime( 'now', $tz );
			$opens->setDate( $year, $quarter, 1 )->setTime( 0, 0 );

			$closes = ( clone $opens )->modify( 'last day of +1 month' )->setTime( 23, 59 );

			$announce = ( clone $opens )->modify( 'first day of +2 months' );
			$announce->setDate( (int) $announce->format( 'Y' ), (int) $announce->format( 'n' ), 15 );
		}

		return array(
			'opens'    => $opens->format( 'Y-m-d\TH:i' ),
			'closes'   => $closes->format( 'Y-m-d\TH:i' ),
			'announce' => $announce->format( 'Y-m-d' ),
			'name'     => self::default_name( self::from_site( $opens->format( 'Y-m-d\TH:i' ) ), self::from_site( $closes->format( 'Y-m-d\TH:i' ) ) ),
		);
	}

	/**
	 * Moves a date forward by whole months without spilling into the month after.
	 *
	 * A date on the last day of its month stays on the last day (28 February plus
	 * three months is 31 May), and 31 January plus one month is 28 or 29 February,
	 * not early March. The time of day is kept.
	 *
	 * @param DateTime $date   The date.
	 * @param int      $months How many months to add.
	 * @return DateTime A new date.
	 */
	public static function add_months( DateTime $date, $months ) {
		$day     = (int) $date->format( 'j' );
		$is_last = (int) $date->format( 't' ) === $day;

		$moved = clone $date;
		$moved->modify( 'first day of +' . (int) $months . ' months' );

		$days_in_month = (int) $moved->format( 't' );
		$moved->setDate( (int) $moved->format( 'Y' ), (int) $moved->format( 'n' ), $is_last ? $days_in_month : min( $day, $days_in_month ) );

		return $moved;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Dates and times
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The current time in UTC, in database format.
	 *
	 * @return string
	 */
	public static function now_utc() {
		return gmdate( self::DB_FORMAT );
	}

	/**
	 * Turns a date and time typed in the site's time zone (Y-m-d\TH:i) into UTC for the database.
	 *
	 * @param string $local Local date and time.
	 * @return string UTC date and time, or '' if it couldn't be read.
	 */
	public static function from_site( $local ) {
		$local = trim( (string) $local );
		$date  = DateTime::createFromFormat( 'Y-m-d\TH:i', $local, wp_timezone() );

		if ( ! $date ) {
			$date = DateTime::createFromFormat( 'Y-m-d H:i:s', $local, wp_timezone() );
		}

		if ( ! $date ) {
			return '';
		}

		return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( self::DB_FORMAT );
	}

	/**
	 * Turns a UTC database time into the site's time zone, in any PHP date format.
	 *
	 * @param string $utc    UTC date and time.
	 * @param string $format PHP date format.
	 * @return string
	 */
	public static function to_site( $utc, $format = 'Y-m-d\TH:i' ) {
		if ( empty( $utc ) || '0000-00-00 00:00:00' === $utc ) {
			return '';
		}

		$timestamp = strtotime( $utc . ' UTC' );

		return $timestamp ? wp_date( $format, $timestamp ) : '';
	}

	/**
	 * A UTC time written out nicely in the site's time zone, for example "3 February 2027, 9:00 am".
	 *
	 * @param string $utc       UTC date and time.
	 * @param bool   $with_time Whether to include the time.
	 * @return string
	 */
	public static function format( $utc, $with_time = true ) {
		$format = $with_time ? 'j F Y, g:i a' : 'j F Y';

		return self::to_site( $utc, $format );
	}

	/**
	 * A plain date (Y-m-d, such as the announcement date) written out nicely, for example "17 March 2027".
	 *
	 * @param string $date Date as Y-m-d.
	 * @return string
	 */
	public static function format_date( $date ) {
		if ( empty( $date ) || '0000-00-00' === $date ) {
			return '';
		}

		$parsed = DateTime::createFromFormat( '!Y-m-d', $date, wp_timezone() );

		return $parsed ? wp_date( 'j F Y', $parsed->getTimestamp() ) : '';
	}
}
