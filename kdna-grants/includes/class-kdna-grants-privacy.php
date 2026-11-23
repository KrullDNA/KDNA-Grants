<?php
/**
 * Privacy: personal data export and erase, and data retention.
 *
 * KDNA Grants joins WordPress's own Tools > Export Personal Data and
 * Tools > Erase Personal Data, matching applications by email address. Erasing
 * removes the KDNA Grants record only; the KDNA Forms entry is handled by
 * KDNA Forms. Old records are removed automatically after the retention period
 * set in Settings (24 months after a round is decided, by default).
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Privacy tools and data retention.
 */
class KDNA_Grants_Privacy {

	/**
	 * Daily WP Cron task that removes records past the retention period.
	 */
	const CRON_HOOK = 'kdna_grants_retention';

	/**
	 * Hooks into WordPress. Called once KDNA Forms is available.
	 */
	public static function register() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'add_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'add_policy_text' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'apply_retention' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ) );
	}

	/**
	 * Makes sure the daily retention check is scheduled.
	 */
	public static function ensure_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/*
	 * -----------------------------------------------------------------------
	 * Export and erase
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Adds KDNA Grants to Tools > Export Personal Data.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public static function add_exporter( $exporters ) {
		$exporters['kdna-grants'] = array(
			'exporter_friendly_name' => __( 'KDNA Grants applications', 'kdna-grants' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Adds KDNA Grants to Tools > Erase Personal Data.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public static function add_eraser( $erasers ) {
		$erasers['kdna-grants'] = array(
			'eraser_friendly_name' => __( 'KDNA Grants applications', 'kdna-grants' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Every KDNA Grants application with this email address.
	 *
	 * @param string $email Email address.
	 * @return array[]
	 */
	private static function applications_for_email( $email ) {
		global $wpdb;

		$email = strtolower( trim( (string) $email ) );
		if ( '' === $email ) {
			return array();
		}

		$table = KDNA_Grants_Install::applications_table();
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE LOWER(email) = %s ORDER BY id ASC", $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return array_filter( array_map( array( 'KDNA_Grants_Applications', 'get' ), $ids ) );
	}

	/**
	 * Exports what KDNA Grants holds about an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number (everything fits on one page).
	 * @return array
	 */
	public static function export( $email, $page = 1 ) {
		$items  = array();
		$labels = KDNA_Grants_Eligibility::flag_labels();

		foreach ( self::applications_for_email( $email ) as $application ) {
			$round    = KDNA_Grants_Rounds::get( $application['round_id'] );
			$criteria = KDNA_Grants_Judging::get_criteria( $round );
			$scores   = array();

			foreach ( $criteria['items'] as $item ) {
				if ( isset( $application['scores'][ $item['key'] ] ) ) {
					$scores[] = $item['label'] . ': ' . $application['scores'][ $item['key'] ];
				}
			}

			$flags = array();
			foreach ( $application['eligibility_flags'] as $flag ) {
				$flags[] = ( isset( $labels[ $flag['code'] ] ) ? $labels[ $flag['code'] ] : $flag['code'] ) . ': ' . $flag['reason'] . ( ! empty( $flag['cleared'] ) ? ' (' . __( 'cleared', 'kdna-grants' ) . ')' : '' );
			}

			$data = array(
				__( 'Round', 'kdna-grants' )             => $round ? $round['name'] : '',
				__( 'Applied', 'kdna-grants' )           => KDNA_Grants_Rounds::format( $application['created_at'] ),
				__( 'First name', 'kdna-grants' )        => $application['first_name'],
				__( 'Last name', 'kdna-grants' )         => $application['last_name'],
				__( 'Email', 'kdna-grants' )             => $application['email'],
				__( 'Business name', 'kdna-grants' )     => $application['business_name'],
				__( 'Industry', 'kdna-grants' )          => $application['industry'],
				__( 'ABN', 'kdna-grants' )               => $application['abn'],
				__( 'Staff', 'kdna-grants' )             => $application['staff'],
				__( 'Eligibility flags', 'kdna-grants' ) => implode( '; ', $flags ),
				__( 'Scores', 'kdna-grants' )            => implode( ', ', $scores ),
				__( 'Total score', 'kdna-grants' )       => (string) $application['total_score'],
				__( 'Notes', 'kdna-grants' )             => $application['notes'],
				__( 'Shortlisted', 'kdna-grants' )       => $application['shortlisted'] ? __( 'Yes', 'kdna-grants' ) : __( 'No', 'kdna-grants' ),
				__( 'Outcome', 'kdna-grants' )           => $application['outcome'],
			);

			$rows = array();
			foreach ( $data as $name => $value ) {
				if ( '' !== (string) $value ) {
					$rows[] = array(
						'name'  => $name,
						'value' => $value,
					);
				}
			}

			$items[] = array(
				'group_id'    => 'kdna-grants',
				'group_label' => __( 'KDNA Grants applications', 'kdna-grants' ),
				'item_id'     => 'kdna-grants-application-' . $application['id'],
				'data'        => $rows,
			);
		}

		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Removes every KDNA Grants record for an email address. The KDNA Forms entry is left to KDNA Forms.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number (everything is done in one go).
	 * @return array
	 */
	public static function erase( $email, $page = 1 ) {
		$applications = self::applications_for_email( $email );

		foreach ( $applications as $application ) {
			self::delete_application( $application );
		}

		$messages = array();
		if ( $applications ) {
			$messages[] = __( 'KDNA Grants application records were removed. The original form entries are kept by KDNA Forms and can be erased there.', 'kdna-grants' );
		}

		return array(
			'items_removed'  => (bool) $applications,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Deletes one application. If it was a round's winner, the round keeps its decision
	 * but no longer points to the deleted record, and the business name is removed from its history.
	 *
	 * @param array $application Application row.
	 */
	private static function delete_application( $application ) {
		global $wpdb;

		$round = KDNA_Grants_Rounds::get( $application['round_id'] );

		if ( $round && $round['winner_application_id'] === $application['id'] ) {
			$log = $round['activity_log'];

			if ( '' !== $application['business_name'] ) {
				foreach ( $log as &$entry ) {
					$entry['message'] = str_replace( $application['business_name'], __( '[removed]', 'kdna-grants' ), $entry['message'] );
				}
				unset( $entry );
			}

			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					'UPDATE ' . KDNA_Grants_Install::rounds_table() . ' SET winner_application_id = NULL, activity_log = %s WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					wp_json_encode( $log ),
					$round['id']
				)
			);
		}

		$wpdb->delete( KDNA_Grants_Install::applications_table(), array( 'id' => $application['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/*
	 * -----------------------------------------------------------------------
	 * Retention
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Removes rounds (and their applications) decided longer ago than the retention period.
	 * A retention period of 0 keeps everything.
	 *
	 * @return int Number of rounds removed.
	 */
	public static function apply_retention() {
		global $wpdb;

		$months = absint( kdna_grants_get_setting( 'retention_months', 24 ) );
		if ( ! $months ) {
			return 0;
		}

		$cutoff = gmdate( KDNA_Grants_Rounds::DB_FORMAT, strtotime( '-' . $months . ' months' ) );
		$rounds = KDNA_Grants_Install::rounds_table();
		$ids    = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$rounds} WHERE status = %s AND updated_at < %s", KDNA_Grants_Rounds::STATUS_DECIDED, $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		foreach ( $ids as $round_id ) {
			KDNA_Grants_Applications::delete_for_round( $round_id );
			$wpdb->delete( $rounds, array( 'id' => (int) $round_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		return count( $ids );
	}

	/**
	 * Suggests wording for the site's privacy policy (Settings > Privacy).
	 */
	public static function add_policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$months = absint( kdna_grants_get_setting( 'retention_months', 24 ) );

		$text = '<p>' . esc_html__( 'When you apply for the KDNA Website Grant, we keep your application details (name, email, business name, industry, ABN and number of staff) with our judging scores, notes and the outcome, so we can assess applications fairly and contact you about the result.', 'kdna-grants' ) . '</p>';

		$text .= '<p>' . esc_html(
			$months
				? sprintf(
					/* translators: %d: number of months. */
					__( 'We keep these records for %d months after the winner of your round is chosen, then delete them. You can ask us to export or delete your data at any time.', 'kdna-grants' ),
					$months
				)
				: __( 'You can ask us to export or delete your data at any time.', 'kdna-grants' )
		) . '</p>';

		wp_add_privacy_policy_content( __( 'KDNA Grants', 'kdna-grants' ), $text );
	}
}
