<?php
/**
 * Eligibility: the automatic checks and flags, including the official ABN checksum.
 *
 * Checks only ever flag an application. They never delete or reject it,
 * because Nick always has the final say. "Mark as eligible" clears the flags
 * and records when and by whom.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the eligibility checks.
 */
class KDNA_Launchpad_Eligibility {

	const FLAG_ABN             = 'abn';
	const FLAG_STAFF           = 'staff';
	const FLAG_EXISTING_CLIENT = 'existing_client';
	const FLAG_DUPLICATE       = 'duplicate';
	const FLAG_PAST_WINNER     = 'past_winner';

	/**
	 * Words dropped from business names before comparing them, so "Acme Pty Ltd" matches "Acme".
	 *
	 * @var string[]
	 */
	const BUSINESS_SUFFIXES = array( 'pty', 'ltd', 'limited', 'proprietary', 'inc', 'co', 'company', 'the', 'and' );

	/**
	 * Short label for each flag.
	 *
	 * @return array
	 */
	public static function flag_labels() {
		return array(
			self::FLAG_ABN             => __( 'ABN', 'kdna-launchpad' ),
			self::FLAG_STAFF           => __( 'Staff', 'kdna-launchpad' ),
			self::FLAG_EXISTING_CLIENT => __( 'Existing client', 'kdna-launchpad' ),
			self::FLAG_DUPLICATE       => __( 'Duplicate', 'kdna-launchpad' ),
			self::FLAG_PAST_WINNER     => __( 'Past winner', 'kdna-launchpad' ),
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Running the checks
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Runs every check on an application and saves the flags.
	 *
	 * @param int      $application_id Application ID.
	 * @param string[] $extra_clients  Extra existing-client lines (used by the test tool only).
	 * @return array The flags saved.
	 */
	public static function check( $application_id, $extra_clients = array() ) {
		$application = KDNA_Launchpad_Applications::get( $application_id );

		if ( ! $application ) {
			return array();
		}

		$flags = array();

		$reason = self::check_abn( $application['abn'] );
		if ( $reason ) {
			$flags[] = self::flag( self::FLAG_ABN, $reason );
		}

		$reason = self::check_staff( $application['staff'] );
		if ( $reason ) {
			$flags[] = self::flag( self::FLAG_STAFF, $reason );
		}

		$reason = self::check_existing_client( $application, $extra_clients );
		if ( $reason ) {
			$flags[] = self::flag( self::FLAG_EXISTING_CLIENT, $reason );
		}

		$reason = self::check_duplicate( $application );
		if ( $reason ) {
			$flags[] = self::flag( self::FLAG_DUPLICATE, $reason );
		}

		$reason = self::check_past_winner( $application );
		if ( $reason ) {
			$flags[] = self::flag( self::FLAG_PAST_WINNER, $reason );
		}

		KDNA_Launchpad_Applications::update( $application_id, array( 'eligibility_flags' => $flags ) );

		return $flags;
	}

	/**
	 * Builds one flag.
	 *
	 * @param string $code   Flag code.
	 * @param string $reason Short reason in plain English.
	 * @return array
	 */
	private static function flag( $code, $reason ) {
		return array(
			'code'    => $code,
			'reason'  => $reason,
			'cleared' => false,
		);
	}

	/**
	 * True when an application has no flags left that haven't been cleared.
	 *
	 * @param array $application Application row.
	 * @return bool
	 */
	public static function is_eligible( $application ) {
		foreach ( (array) $application['eligibility_flags'] as $flag ) {
			if ( empty( $flag['cleared'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Clears every flag on an application ("Mark as eligible"), recording the date and who did it.
	 *
	 * @param int $application_id Application ID.
	 * @return bool
	 */
	public static function mark_eligible( $application_id ) {
		$application = KDNA_Launchpad_Applications::get( $application_id );

		if ( ! $application ) {
			return false;
		}

		$user  = wp_get_current_user();
		$flags = $application['eligibility_flags'];

		foreach ( $flags as &$flag ) {
			if ( empty( $flag['cleared'] ) ) {
				$flag['cleared']    = true;
				$flag['cleared_at'] = KDNA_Launchpad_Rounds::now_utc();
				$flag['cleared_by'] = $user && $user->exists() ? $user->display_name : '';
			}
		}
		unset( $flag );

		return KDNA_Launchpad_Applications::update( $application_id, array( 'eligibility_flags' => $flags ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * ABN
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Checks an ABN. Returns a reason if it should be flagged, or '' if it's fine.
	 *
	 * @param string $abn The ABN as entered.
	 * @return string
	 */
	public static function check_abn( $abn ) {
		$digits = self::abn_digits( $abn );

		if ( '' === trim( (string) $abn ) ) {
			return __( 'No ABN was given.', 'kdna-launchpad' );
		}

		if ( 11 !== strlen( $digits ) ) {
			return sprintf(
				/* translators: %s: the ABN as entered. */
				__( '"%s" is not 11 digits long.', 'kdna-launchpad' ),
				$abn
			);
		}

		if ( ! self::is_valid_abn( $digits ) ) {
			return sprintf(
				/* translators: %s: the ABN, formatted. */
				__( '%s fails the official ABN check, so it may have been mistyped or made up.', 'kdna-launchpad' ),
				self::format_abn( $digits )
			);
		}

		return '';
	}

	/**
	 * The official ABN checksum used by the Australian Business Register.
	 *
	 * Take 1 from the first digit, multiply each digit by its weight
	 * (10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19), add them up, and the total
	 * must divide exactly by 89.
	 *
	 * @param string $abn The ABN (spaces allowed).
	 * @return bool
	 */
	public static function is_valid_abn( $abn ) {
		$digits = self::abn_digits( $abn );

		if ( 11 !== strlen( $digits ) || '0' === $digits[0] ) {
			return false;
		}

		$weights = array( 10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19 );
		$total   = 0;

		for ( $i = 0; $i < 11; $i++ ) {
			$digit  = (int) $digits[ $i ];
			$digit  = 0 === $i ? $digit - 1 : $digit;
			$total += $digit * $weights[ $i ];
		}

		return 0 === $total % 89;
	}

	/**
	 * The digits of an ABN, with spaces and other characters removed.
	 *
	 * @param string $abn The ABN as entered.
	 * @return string
	 */
	public static function abn_digits( $abn ) {
		return preg_replace( '/\D+/', '', (string) $abn );
	}

	/**
	 * Writes an 11-digit ABN in the usual way, for example "51 824 753 556".
	 *
	 * @param string $abn The ABN.
	 * @return string
	 */
	public static function format_abn( $abn ) {
		$digits = self::abn_digits( $abn );

		if ( 11 !== strlen( $digits ) ) {
			return (string) $abn;
		}

		return substr( $digits, 0, 2 ) . ' ' . substr( $digits, 2, 3 ) . ' ' . substr( $digits, 5, 3 ) . ' ' . substr( $digits, 8, 3 );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Staff
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Checks the number of staff against the limit in Settings (fewer than 20 by default).
	 *
	 * Handles plain numbers ("12") and ranges or choices ("5 to 19", "20+",
	 * "Fewer than 5"). The highest number in the answer is used, or one less than
	 * it when the answer says "under", "less than", "fewer than" or "below".
	 *
	 * @param string $staff The answer as entered.
	 * @return string Reason to flag, or ''.
	 */
	public static function check_staff( $staff ) {
		$limit = max( 1, absint( kdna_launchpad_get_setting( 'staff_limit', 20 ) ) );
		$count = self::staff_count( $staff );

		if ( null === $count ) {
			return '' === trim( (string) $staff )
				? __( 'The number of staff was left blank.', 'kdna-launchpad' )
				: sprintf(
					/* translators: %s: the answer as entered. */
					__( 'Couldn\'t read a number of staff from "%s". Please check it.', 'kdna-launchpad' ),
					$staff
				);
		}

		if ( $count >= $limit ) {
			return sprintf(
				/* translators: 1: the answer as entered, 2: the staff limit. */
				__( 'Staff answer "%1$s" is not fewer than %2$d.', 'kdna-launchpad' ),
				$staff,
				$limit
			);
		}

		return '';
	}

	/**
	 * Reads a number of staff from an answer, or null if there's no number in it.
	 *
	 * @param string $staff The answer as entered.
	 * @return int|null
	 */
	public static function staff_count( $staff ) {
		$staff = strtolower( (string) $staff );

		if ( ! preg_match_all( '/\d+/', str_replace( ',', '', $staff ), $matches ) ) {
			return null;
		}

		$highest = max( array_map( 'intval', $matches[0] ) );

		if ( preg_match( '/\b(under|less than|fewer than|below)\b/', $staff ) ) {
			return max( 0, $highest - 1 );
		}

		return $highest;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Existing clients
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Checks the email domain and business name against the existing client list in Settings.
	 *
	 * Each line of the list is either an email domain (anything with a dot and no spaces,
	 * such as "acme.com.au" or "@acme.com.au") or a business name.
	 *
	 * @param array    $application   Application row.
	 * @param string[] $extra_clients Extra lines (test tool only).
	 * @return string Reason to flag, or ''.
	 */
	public static function check_existing_client( $application, $extra_clients = array() ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) kdna_launchpad_get_setting( 'existing_clients', '' ) );
		$lines = array_merge( (array) $lines, (array) $extra_clients );

		$email_domain   = self::email_domain( $application['email'] );
		$business_names = array_filter( array( self::normalise_business( $application['business_name'] ), self::normalise_business( isset( $application['trading_as'] ) ? $application['trading_as'] : '' ) ) );

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );

			if ( '' === $line ) {
				continue;
			}

			if ( self::looks_like_domain( $line ) ) {
				$domain = self::clean_domain( $line );

				if ( '' !== $email_domain && ( $email_domain === $domain || self::ends_with( $email_domain, '.' . $domain ) ) ) {
					return sprintf(
						/* translators: %s: the email domain. */
						__( 'Email domain %s is on your existing client list.', 'kdna-launchpad' ),
						$email_domain
					);
				}

				continue;
			}

			$name = self::normalise_business( $line );
			if ( '' !== $name && in_array( $name, $business_names, true ) ) {
				return sprintf(
					/* translators: %s: the client name from the list. */
					__( 'Business name matches "%s" on your existing client list.', 'kdna-launchpad' ),
					$line
				);
			}
		}

		return '';
	}

	/**
	 * True when a line from the client list is an email domain rather than a business name.
	 *
	 * @param string $line One line.
	 * @return bool
	 */
	private static function looks_like_domain( $line ) {
		return false === strpos( $line, ' ' ) && false !== strpos( $line, '.' );
	}

	/**
	 * Tidies a domain from the list: lowercase, without "@", "http://", "www." or a trailing slash.
	 *
	 * @param string $domain Domain as typed.
	 * @return string
	 */
	private static function clean_domain( $domain ) {
		$domain = strtolower( trim( $domain ) );
		$domain = preg_replace( '#^https?://#', '', $domain );
		$domain = ltrim( $domain, '@' );
		$domain = preg_replace( '/^www\./', '', $domain );

		if ( false !== strpos( $domain, '@' ) ) {
			$domain = substr( $domain, strrpos( $domain, '@' ) + 1 );
		}

		return rtrim( $domain, '/' );
	}

	/**
	 * The domain part of an email address, lowercase.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private static function email_domain( $email ) {
		$email = strtolower( trim( (string) $email ) );
		$at    = strrpos( $email, '@' );

		return false === $at ? '' : substr( $email, $at + 1 );
	}

	/**
	 * Simplifies a business name for comparing: lowercase, letters and numbers only, common suffixes dropped.
	 *
	 * @param string $name Business name.
	 * @return string
	 */
	private static function normalise_business( $name ) {
		$name  = strtolower( remove_accents( (string) $name ) );
		$name  = str_replace( '&', ' and ', $name );
		$words = preg_split( '/[^a-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY );
		$words = array_diff( (array) $words, self::BUSINESS_SUFFIXES );

		return implode( '', $words );
	}

	/**
	 * True when $text ends with $end.
	 *
	 * @param string $text Text.
	 * @param string $end  Ending.
	 * @return bool
	 */
	private static function ends_with( $text, $end ) {
		return '' !== $end && substr( $text, -strlen( $end ) ) === $end;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Duplicates and past winners
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Flags an application when an earlier application in the same round has the same ABN or email.
	 *
	 * @param array $application Application row.
	 * @return string Reason to flag, or ''.
	 */
	public static function check_duplicate( $application ) {
		$match = self::find_match(
			$application,
			array_filter(
				KDNA_Launchpad_Applications::get_for_round( $application['round_id'] ),
				function ( $other ) use ( $application ) {
					return $other['id'] < $application['id'];
				}
			)
		);

		if ( ! $match ) {
			return '';
		}

		return sprintf(
			/* translators: 1: "ABN" or "email", 2: business name of the earlier application, 3: its date. */
			__( 'Same %1$s as an earlier application in this round (%2$s, %3$s).', 'kdna-launchpad' ),
			$match['by'],
			'' !== $match['application']['display_name'] ? $match['application']['display_name'] : __( 'no business name', 'kdna-launchpad' ),
			KDNA_Launchpad_Rounds::format( $match['application']['created_at'], false )
		);
	}

	/**
	 * Flags an application when its ABN or email belongs to a previous winner.
	 *
	 * Test rounds are only compared with other test rounds, so test data never flags real applicants.
	 *
	 * @param array $application Application row.
	 * @return string Reason to flag, or ''.
	 */
	public static function check_past_winner( $application ) {
		global $wpdb;

		$round = KDNA_Launchpad_Rounds::get( $application['round_id'] );
		if ( ! $round ) {
			return '';
		}

		$apps   = KDNA_Launchpad_Install::applications_table();
		$rounds = KDNA_Launchpad_Install::rounds_table();
		$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT a.*, r.name AS round_name FROM {$apps} a INNER JOIN {$rounds} r ON r.id = a.round_id WHERE a.outcome = %s AND a.round_id <> %d AND r.is_test = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'winner',
				$application['round_id'],
				$round['is_test'] ? 1 : 0
			),
			ARRAY_A
		);

		$match = self::find_match( $application, (array) $rows );

		if ( ! $match ) {
			return '';
		}

		return sprintf(
			/* translators: 1: "ABN" or "email", 2: round name. */
			__( 'Same %1$s as the winner of %2$s.', 'kdna-launchpad' ),
			$match['by'],
			$match['application']['round_name']
		);
	}

	/**
	 * Finds the first application in a list with the same ABN or email.
	 *
	 * @param array   $application The application being checked.
	 * @param array[] $others      Applications to compare with.
	 * @return array|null array( 'application' => matching row, 'by' => 'ABN' or 'email' ).
	 */
	private static function find_match( $application, $others ) {
		$abn   = self::abn_digits( $application['abn'] );
		$email = strtolower( trim( (string) $application['email'] ) );

		foreach ( $others as $other ) {
			if ( '' !== $abn && self::abn_digits( $other['abn'] ) === $abn ) {
				return array(
					'application' => $other,
					'by'          => __( 'ABN', 'kdna-launchpad' ),
				);
			}

			if ( '' !== $email && strtolower( trim( (string) $other['email'] ) ) === $email ) {
				return array(
					'application' => $other,
					'by'          => __( 'email', 'kdna-launchpad' ),
				);
			}
		}

		return null;
	}
}
