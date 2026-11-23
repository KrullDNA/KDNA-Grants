<?php
/**
 * Offers: a discount on the first brief for unsuccessful applicants.
 *
 * When a winner is chosen, every unsuccessful applicant who qualifies gets a
 * personal code (25% off by default, valid for 90 days) in their unsuccessful
 * email. Nick records the outcome by hand, because quotes and invoices happen
 * outside WordPress: Accept (the first brief got the discount), Decline, or it
 * expires on its own. Each business only ever gets one offer, matched by email,
 * ABN or business name, so reapplying never brings a second discount.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Issues and tracks offers.
 */
class KDNA_Grants_Offers {

	const STATUS_ACTIVE   = 'active';
	const STATUS_ACCEPTED = 'accepted';
	const STATUS_DECLINED = 'declined';
	const STATUS_EXPIRED  = 'expired';

	/**
	 * Daily task that marks old offers as expired.
	 */
	const CRON_HOOK = 'kdna_grants_offers_daily';

	/**
	 * Characters used in codes: no 0/O or 1/I, so they are easy to read out.
	 */
	const CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

	/**
	 * Hooks into WordPress. Called once KDNA Forms is available.
	 */
	public static function register() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'expire_old' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ) );
	}

	/**
	 * Makes sure the daily expiry check is scheduled.
	 */
	public static function ensure_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Plain-English status names.
	 *
	 * @return array
	 */
	public static function status_labels() {
		return array(
			self::STATUS_ACTIVE   => __( 'Active', 'kdna-grants' ),
			self::STATUS_ACCEPTED => __( 'Offer used', 'kdna-grants' ),
			self::STATUS_DECLINED => __( 'Declined', 'kdna-grants' ),
			self::STATUS_EXPIRED  => __( 'Expired', 'kdna-grants' ),
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Loading
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Loads one offer, or null.
	 *
	 * @param int $offer_id Offer ID.
	 * @return array|null
	 */
	public static function get( $offer_id ) {
		global $wpdb;

		$table = KDNA_Grants_Install::offers_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $offer_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return $row ? self::prepare( $row ) : null;
	}

	/**
	 * Loads offers, newest first, optionally by status.
	 *
	 * @param string $status Status, or '' for all.
	 * @param bool   $test   Include offers from test rounds.
	 * @return array[]
	 */
	public static function get_all( $status = '', $test = true ) {
		global $wpdb;

		self::expire_old();

		$table = KDNA_Grants_Install::offers_table();
		$where = array( '1=1' );
		$args  = array();

		if ( '' !== $status ) {
			$where[] = 'status = %s';
			$args[]  = $status;
		}
		if ( ! $test ) {
			$where[] = 'is_test = 0';
		}

		$sql  = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY issued_at DESC, id DESC';
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

		return array_map( array( __CLASS__, 'prepare' ), (array) $rows );
	}

	/**
	 * How many offers have each status, as status => count.
	 *
	 * @return array
	 */
	public static function counts() {
		global $wpdb;

		$table  = KDNA_Grants_Install::offers_table();
		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$counts = array_fill_keys( array_keys( self::status_labels() ), 0 );

		foreach ( (array) $rows as $row ) {
			$counts[ $row['status'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Tidies a database row.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	private static function prepare( $row ) {
		foreach ( array( 'id', 'application_id', 'round_id', 'percent' ) as $key ) {
			$row[ $key ] = (int) $row[ $key ];
		}

		$row['is_test'] = (bool) $row['is_test'];
		$row['note']    = (string) $row['note'];

		return $row;
	}

	/**
	 * Every offer that belongs to the same business as these details, matched by email, ABN or business name.
	 *
	 * @param string $email         Email address.
	 * @param string $abn           ABN.
	 * @param string $business_name Business name.
	 * @param bool   $is_test       Look at test offers (true) or real ones (false).
	 * @return array[]
	 */
	public static function find_matching( $email, $abn, $business_name, $is_test = false ) {
		global $wpdb;

		$email    = strtolower( trim( (string) $email ) );
		$abn      = KDNA_Grants_Eligibility::abn_digits( $abn );
		$business = self::normalise( $business_name );

		if ( '' === $email && '' === $abn && '' === $business ) {
			return array();
		}

		$table = KDNA_Grants_Install::offers_table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE is_test = %d ORDER BY issued_at ASC", $is_test ? 1 : 0 ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$found = array();

		foreach ( (array) $rows as $row ) {
			$same = ( '' !== $email && strtolower( $row['email'] ) === $email )
				|| ( '' !== $abn && KDNA_Grants_Eligibility::abn_digits( $row['abn'] ) === $abn )
				|| ( '' !== $business && self::normalise( $row['business_name'] ) === $business );

			if ( $same ) {
				$found[] = self::prepare( $row );
			}
		}

		return $found;
	}

	/**
	 * The offer that relates to an application: one issued in its own round, or else any earlier one for the same business.
	 *
	 * @param array $application Application row.
	 * @return array|null
	 */
	public static function get_for_application( $application ) {
		$round   = KDNA_Grants_Rounds::get( $application['round_id'] );
		$is_test = $round && $round['is_test'];
		$matches = self::find_matching( $application['email'], $application['abn'], $application['business_name'], $is_test );

		foreach ( $matches as $offer ) {
			if ( $offer['round_id'] === $application['round_id'] ) {
				return $offer;
			}
		}

		return $matches ? end( $matches ) : null;
	}

	/**
	 * Simplifies a business name for matching (the same way as the existing client check).
	 *
	 * @param string $name Business name.
	 * @return string
	 */
	private static function normalise( $name ) {
		$name  = strtolower( remove_accents( (string) $name ) );
		$words = preg_split( '/[^a-z0-9]+/', str_replace( '&', ' and ', $name ), -1, PREG_SPLIT_NO_EMPTY );
		$words = array_diff( (array) $words, KDNA_Grants_Eligibility::BUSINESS_SUFFIXES );

		return implode( '', $words );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Issuing
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Gives every qualifying unsuccessful applicant in a decided round their offer. Called just before the emails are queued.
	 *
	 * Qualifies: outcome unsuccessful, not flagged as an existing client or past
	 * winner, not the winner's own second application, and no offer ever given to
	 * the same business before.
	 *
	 * @param int $round_id Round ID.
	 * @return int How many offers were issued.
	 */
	public static function issue_for_round( $round_id ) {
		global $wpdb;

		if ( ! kdna_grants_get_setting( 'offers_enabled', true ) ) {
			return 0;
		}

		$round = KDNA_Grants_Rounds::get( $round_id );
		if ( ! $round ) {
			return 0;
		}

		$percent = max( 1, min( 100, absint( kdna_grants_get_setting( 'offer_percent', 25 ) ) ) );
		$days    = max( 1, absint( kdna_grants_get_setting( 'offer_days', 90 ) ) );
		$cap     = sanitize_text_field( (string) kdna_grants_get_setting( 'offer_cap', '' ) );
		$now     = KDNA_Grants_Rounds::now_utc();
		$issued  = 0;

		$applications = KDNA_Grants_Applications::get_for_round( $round_id );
		$winner       = null;
		foreach ( $applications as $application ) {
			if ( 'winner' === $application['outcome'] ) {
				$winner = $application;
			}
		}

		foreach ( $applications as $application ) {
			if ( 'unsuccessful' !== $application['outcome'] || ! self::qualifies( $application ) ) {
				continue;
			}

			// The winner's own second application (same email or ABN) doesn't get an offer.
			if ( $winner && self::same_business( $application, $winner ) ) {
				continue;
			}

			// One per business: anything matching, in this round or any earlier one, means no new code.
			if ( self::find_matching( $application['email'], $application['abn'], $application['business_name'], $round['is_test'] ) ) {
				continue;
			}

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				KDNA_Grants_Install::offers_table(),
				array(
					'application_id' => $application['id'],
					'round_id'       => $round_id,
					'code'           => self::new_code( $application['business_name'] ),
					'first_name'     => $application['first_name'],
					'email'          => $application['email'],
					'abn'            => $application['abn'],
					'business_name'  => $application['business_name'],
					'percent'        => $percent,
					'cap'            => $cap,
					'status'         => self::STATUS_ACTIVE,
					'issued_at'      => $now,
					'expires_at'     => gmdate( KDNA_Grants_Rounds::DB_FORMAT, time() + $days * DAY_IN_SECONDS ),
					'is_test'        => $round['is_test'] ? 1 : 0,
					'created_at'     => $now,
					'updated_at'     => $now,
				)
			);

			++$issued;
		}

		if ( $issued ) {
			KDNA_Grants_Rounds::log(
				$round_id,
				sprintf(
					/* translators: 1: number of offers, 2: percentage. */
					_n( 'Issued %1$d offer of %2$d%% off a first brief.', 'Issued %1$d offers of %2$d%% off a first brief.', $issued, 'kdna-grants' ),
					$issued,
					$percent
				)
			);
		}

		return $issued;
	}

	/**
	 * True when an application can be given an offer: not an existing client or past winner.
	 * A flag Nick has cleared with Mark as eligible no longer counts.
	 *
	 * @param array $application Application row.
	 * @return bool
	 */
	public static function qualifies( $application ) {
		foreach ( $application['eligibility_flags'] as $flag ) {
			if ( empty( $flag['cleared'] ) && in_array( $flag['code'], array( KDNA_Grants_Eligibility::FLAG_EXISTING_CLIENT, KDNA_Grants_Eligibility::FLAG_PAST_WINNER ), true ) ) {
				return false;
			}
		}

		return '' !== trim( (string) $application['email'] );
	}

	/**
	 * True when two applications share an email address or ABN.
	 *
	 * @param array $a Application row.
	 * @param array $b Application row.
	 * @return bool
	 */
	private static function same_business( $a, $b ) {
		$email = strtolower( trim( (string) $a['email'] ) );
		$abn   = KDNA_Grants_Eligibility::abn_digits( $a['abn'] );

		return ( '' !== $email && strtolower( trim( (string) $b['email'] ) ) === $email )
			|| ( '' !== $abn && KDNA_Grants_Eligibility::abn_digits( $b['abn'] ) === $abn );
	}

	/**
	 * A new, unique code such as "KDNA-BRIGH-7Q4X".
	 *
	 * @param string $business_name Business name.
	 * @return string
	 */
	private static function new_code( $business_name ) {
		global $wpdb;

		$letters = strtoupper( preg_replace( '/[^A-Za-z]/', '', remove_accents( (string) $business_name ) ) );
		$prefix  = 'KDNA-' . ( '' !== $letters ? substr( $letters, 0, 5 ) : 'GRANT' ) . '-';
		$table   = KDNA_Grants_Install::offers_table();

		do {
			$suffix = '';
			for ( $i = 0; $i < 4; $i++ ) {
				$suffix .= substr( self::CODE_CHARS, wp_rand( 0, strlen( self::CODE_CHARS ) - 1 ), 1 );
			}
			$code   = $prefix . $suffix;
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE code = %s", $code ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		} while ( $exists );

		return $code;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Recording the outcome
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Marks an offer as used (Accept), declined, or back to active (Undo).
	 *
	 * @param int    $offer_id Offer ID.
	 * @param string $status   New status.
	 * @param string $note     What the first brief covers (Accept), or a reason (Decline).
	 * @param string $date     Date it happened, Y-m-d, or '' for today.
	 * @return bool
	 */
	public static function set_status( $offer_id, $status, $note = '', $date = '' ) {
		global $wpdb;

		$offer = self::get( $offer_id );
		if ( ! $offer || ! array_key_exists( $status, self::status_labels() ) ) {
			return false;
		}

		$user = wp_get_current_user();
		$data = array(
			'status'     => $status,
			'updated_at' => KDNA_Grants_Rounds::now_utc(),
		);

		if ( self::STATUS_ACTIVE === $status ) {
			// Undo: back to active, or straight to expired if the offer period has passed.
			$data['status']    = $offer['expires_at'] <= KDNA_Grants_Rounds::now_utc() ? self::STATUS_EXPIRED : self::STATUS_ACTIVE;
			$data['closed_at'] = null;
			$data['closed_by'] = '';
			$data['note']      = '';
		} else {
			$when              = DateTime::createFromFormat( '!Y-m-d', (string) $date, wp_timezone() );
			$data['closed_at'] = $when ? $when->setTimezone( new DateTimeZone( 'UTC' ) )->format( KDNA_Grants_Rounds::DB_FORMAT ) : KDNA_Grants_Rounds::now_utc();
			$data['closed_by'] = $user && $user->exists() ? $user->display_name : '';
			$data['note']      = sanitize_textarea_field( (string) $note );
		}

		return false !== $wpdb->update( KDNA_Grants_Install::offers_table(), $data, array( 'id' => (int) $offer_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Marks active offers past their expiry date as expired.
	 */
	public static function expire_old() {
		global $wpdb;

		$table = KDNA_Grants_Install::offers_table();
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = %s, updated_at = %s WHERE status = %s AND expires_at <= %s", self::STATUS_EXPIRED, KDNA_Grants_Rounds::now_utc(), self::STATUS_ACTIVE, KDNA_Grants_Rounds::now_utc() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/*
	 * -----------------------------------------------------------------------
	 * Email merge tags
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The offer merge tag values for an application: {offer_code}, {offer_expiry}, {offer_percent}, {offer_cap}.
	 * Empty when the application has no active offer from its own round.
	 *
	 * @param array $application Application row.
	 * @return array
	 */
	public static function merge_values( $application ) {
		$offer = self::get_for_application( $application );

		if ( ! $offer || $offer['round_id'] !== $application['round_id'] || self::STATUS_ACTIVE !== $offer['status'] ) {
			return array(
				'{offer_code}'    => '',
				'{offer_expiry}'  => '',
				'{offer_percent}' => '',
				'{offer_cap}'     => '',
			);
		}

		return array(
			'{offer_code}'    => $offer['code'],
			'{offer_expiry}'  => KDNA_Grants_Rounds::format( $offer['expires_at'], false ),
			'{offer_percent}' => (string) $offer['percent'],
			'{offer_cap}'     => $offer['cap'],
		);
	}

	/**
	 * Shows or removes [offer]...[/offer] sections, depending on whether there is an offer code.
	 *
	 * @param string $text Template text.
	 * @param bool   $show Whether the applicant has an offer.
	 * @return string
	 */
	public static function apply_blocks( $text, $show ) {
		return (string) preg_replace( '#\[offer\](.*?)\[/offer\]#s', $show ? '$1' : '', (string) $text );
	}
}
