<?php
/**
 * Judging: scores, ranking, the tie-break and the winner decision.
 *
 * Each criterion is scored 1 to 5. The criteria (and the tie-break) are copied
 * onto a round the first time anything in it is scored, so changing criteria in
 * Settings later only affects rounds that haven't started judging, and past
 * scores always keep their meaning.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scores applications and decides rounds.
 */
class KDNA_Grants_Judging {

	const MIN_SCORE = 1;
	const MAX_SCORE = 5;

	/**
	 * How many applications the Choose winner panel shows.
	 */
	const SHORTLIST_SIZE = 3;

	/*
	 * -----------------------------------------------------------------------
	 * Criteria
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The criteria and tie-break used for a round: its saved copy if judging has started, otherwise Settings.
	 *
	 * @param array|null $round Round row.
	 * @return array array( 'items' => array( array( 'key', 'label' ), ... ), 'tiebreak' => key ).
	 */
	public static function get_criteria( $round = null ) {
		if ( $round && is_array( $round['criteria'] ) && ! empty( $round['criteria']['items'] ) ) {
			return $round['criteria'];
		}

		return self::current_criteria();
	}

	/**
	 * The criteria and tie-break currently set in Settings.
	 *
	 * @return array
	 */
	public static function current_criteria() {
		$items = array();

		foreach ( (array) kdna_grants_get_setting( 'criteria', array() ) as $item ) {
			if ( ! empty( $item['key'] ) && isset( $item['label'] ) ) {
				$items[] = array(
					'key'   => sanitize_key( $item['key'] ),
					'label' => (string) $item['label'],
				);
			}
		}

		if ( ! $items ) {
			$items = KDNA_Grants_Install::default_settings()['criteria'];
		}

		$keys     = wp_list_pluck( $items, 'key' );
		$tiebreak = (string) kdna_grants_get_setting( 'tiebreak_criterion', '' );

		return array(
			'items'    => $items,
			'tiebreak' => in_array( $tiebreak, $keys, true ) ? $tiebreak : $keys[0],
		);
	}

	/**
	 * Copies the current criteria onto a round, once. Called when its first score is saved.
	 *
	 * @param array $round Round row.
	 * @return array The round's criteria.
	 */
	private static function lock_criteria( $round ) {
		global $wpdb;

		if ( is_array( $round['criteria'] ) && ! empty( $round['criteria']['items'] ) ) {
			return $round['criteria'];
		}

		$criteria = self::current_criteria();

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			KDNA_Grants_Install::rounds_table(),
			array( 'criteria' => wp_json_encode( $criteria ) ),
			array( 'id' => $round['id'] )
		);

		return $criteria;
	}

	/**
	 * The highest possible total, for example 20 for four criteria.
	 *
	 * @param array $criteria Criteria from get_criteria().
	 * @return int
	 */
	public static function max_total( $criteria ) {
		return count( $criteria['items'] ) * self::MAX_SCORE;
	}

	/**
	 * The label of a criterion.
	 *
	 * @param array  $criteria Criteria from get_criteria().
	 * @param string $key      Criterion key.
	 * @return string
	 */
	public static function criterion_label( $criteria, $key ) {
		foreach ( $criteria['items'] as $item ) {
			if ( $item['key'] === $key ) {
				return $item['label'];
			}
		}

		return $key;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Scoring
	 * -----------------------------------------------------------------------
	 */

	/**
	 * True when a round's scores can be changed (it hasn't been decided).
	 *
	 * @param array $round Round row.
	 * @return bool
	 */
	public static function can_score( $round ) {
		return $round && KDNA_Grants_Rounds::STATUS_DECIDED !== $round['status'];
	}

	/**
	 * Saves one change from the scorecard: a score, the notes or the shortlist toggle.
	 *
	 * @param int    $application_id Application ID.
	 * @param string $field          'score', 'notes' or 'shortlisted'.
	 * @param mixed  $value          The new value. For a score, 0 clears it.
	 * @param string $key            Criterion key (scores only).
	 * @return array|WP_Error The application's new scorecard state.
	 */
	public static function save( $application_id, $field, $value, $key = '' ) {
		$application = KDNA_Grants_Applications::get( $application_id );
		$round       = $application ? KDNA_Grants_Rounds::get( $application['round_id'] ) : null;

		if ( ! $application || ! $round ) {
			return new WP_Error( 'not_found', __( 'This application could not be found.', 'kdna-grants' ) );
		}

		if ( ! self::can_score( $round ) ) {
			return new WP_Error( 'locked', __( 'This round has been decided, so its scores are locked.', 'kdna-grants' ) );
		}

		switch ( $field ) {
			case 'score':
				$criteria = self::lock_criteria( $round );
				$keys     = wp_list_pluck( $criteria['items'], 'key' );
				$key      = sanitize_key( $key );
				$value    = absint( $value );

				if ( ! in_array( $key, $keys, true ) || $value > self::MAX_SCORE ) {
					return new WP_Error( 'invalid', __( 'That score isn\'t valid.', 'kdna-grants' ) );
				}

				$scores = $application['scores'];
				if ( $value < self::MIN_SCORE ) {
					unset( $scores[ $key ] );
				} else {
					$scores[ $key ] = $value;
				}

				KDNA_Grants_Applications::update(
					$application_id,
					array(
						'scores'      => (object) $scores,
						'total_score' => self::total( $scores, $criteria ),
					)
				);
				break;

			case 'notes':
				KDNA_Grants_Applications::update( $application_id, array( 'notes' => sanitize_textarea_field( (string) $value ) ) );
				break;

			case 'shortlisted':
				KDNA_Grants_Applications::update( $application_id, array( 'shortlisted' => $value ? 1 : 0 ) );
				break;

			default:
				return new WP_Error( 'invalid', __( 'Unknown change.', 'kdna-grants' ) );
		}

		return self::state( KDNA_Grants_Applications::get( $application_id ), KDNA_Grants_Rounds::get( $round['id'] ) );
	}

	/**
	 * Adds up an application's scores for the given criteria.
	 *
	 * @param array $scores   Criterion key => score.
	 * @param array $criteria Criteria from get_criteria().
	 * @return int
	 */
	public static function total( $scores, $criteria ) {
		$total = 0;

		foreach ( $criteria['items'] as $item ) {
			if ( isset( $scores[ $item['key'] ] ) ) {
				$total += (int) $scores[ $item['key'] ];
			}
		}

		return $total;
	}

	/**
	 * True when every criterion has a score.
	 *
	 * @param array $application Application row.
	 * @param array $criteria    Criteria from get_criteria().
	 * @return bool
	 */
	public static function is_scored( $application, $criteria ) {
		foreach ( $criteria['items'] as $item ) {
			if ( empty( $application['scores'][ $item['key'] ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The scorecard state sent back to the browser after each autosave.
	 *
	 * @param array $application Application row.
	 * @param array $round       Round row.
	 * @return array
	 */
	public static function state( $application, $round ) {
		$criteria = self::get_criteria( $round );

		return array(
			'scores'      => (object) $application['scores'],
			'total'       => self::total( $application['scores'], $criteria ),
			'max'         => self::max_total( $criteria ),
			'scored'      => self::is_scored( $application, $criteria ),
			'shortlisted' => (bool) $application['shortlisted'],
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Ranking
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Ranks the eligible applications: highest total first, then the tie-break criterion,
	 * then whoever applied first.
	 *
	 * @param array[] $applications Applications in the round.
	 * @param array   $criteria     Criteria from get_criteria().
	 * @return array[]
	 */
	public static function rank( $applications, $criteria ) {
		$eligible = array_values(
			array_filter(
				$applications,
				function ( $application ) {
					return $application['is_eligible'];
				}
			)
		);

		$tiebreak = $criteria['tiebreak'];

		usort(
			$eligible,
			function ( $a, $b ) use ( $criteria, $tiebreak ) {
				$total_a = self::total( $a['scores'], $criteria );
				$total_b = self::total( $b['scores'], $criteria );

				if ( $total_a !== $total_b ) {
					return $total_b - $total_a;
				}

				$tie_a = isset( $a['scores'][ $tiebreak ] ) ? (int) $a['scores'][ $tiebreak ] : 0;
				$tie_b = isset( $b['scores'][ $tiebreak ] ) ? (int) $b['scores'][ $tiebreak ] : 0;

				if ( $tie_a !== $tie_b ) {
					return $tie_b - $tie_a;
				}

				return strcmp( $a['created_at'], $b['created_at'] ) ?: $a['id'] - $b['id'];
			}
		);

		return $eligible;
	}

	/**
	 * Where a round is on its way to a decision, for the Choose winner panel.
	 *
	 * @param array   $round        Round row.
	 * @param array[] $applications Applications in the round.
	 * @return array 'state' (open, no_eligible, scoring, ready, decided), 'scored', 'eligible', 'ranked'.
	 */
	public static function decision_status( $round, $applications ) {
		$criteria = self::get_criteria( $round );
		$ranked   = self::rank( $applications, $criteria );
		$scored   = count(
			array_filter(
				$ranked,
				function ( $application ) use ( $criteria ) {
					return self::is_scored( $application, $criteria );
				}
			)
		);

		$result = array(
			'scored'   => $scored,
			'eligible' => count( $ranked ),
			'ranked'   => $ranked,
		);

		if ( KDNA_Grants_Rounds::STATUS_DECIDED === $round['status'] ) {
			$result['state'] = 'decided';
		} elseif ( KDNA_Grants_Rounds::STATUS_JUDGING !== $round['status'] ) {
			$result['state'] = 'open';
		} elseif ( ! $ranked ) {
			$result['state'] = 'no_eligible';
		} elseif ( $scored < count( $ranked ) ) {
			$result['state'] = 'scoring';
		} else {
			$result['state'] = 'ready';
		}

		return $result;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Deciding
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Chooses the winner: records the outcome for everyone, locks the round and queues the emails.
	 *
	 * @param int    $round_id       Round ID.
	 * @param int    $application_id The winning application.
	 * @param string $reason         Why, when the winner isn't the top-scoring application.
	 * @return true|WP_Error
	 */
	public static function choose_winner( $round_id, $application_id, $reason = '' ) {
		global $wpdb;

		$round = KDNA_Grants_Rounds::get( $round_id );
		if ( ! $round ) {
			return new WP_Error( 'not_found', __( 'This round could not be found.', 'kdna-grants' ) );
		}

		$applications = KDNA_Grants_Applications::get_for_round( $round_id );
		$status       = self::decision_status( $round, $applications );

		if ( 'ready' !== $status['state'] ) {
			return new WP_Error( 'not_ready', __( 'A winner can only be chosen once the round has closed and every eligible application is scored.', 'kdna-grants' ) );
		}

		$winner = null;
		foreach ( $status['ranked'] as $application ) {
			if ( $application['id'] === (int) $application_id ) {
				$winner = $application;
				break;
			}
		}

		if ( ! $winner ) {
			return new WP_Error( 'not_eligible', __( 'Only an eligible application from this round can be chosen.', 'kdna-grants' ) );
		}

		$is_top = $status['ranked'][0]['id'] === $winner['id'];
		$reason = trim( sanitize_textarea_field( (string) $reason ) );

		if ( ! $is_top && '' === $reason ) {
			return new WP_Error( 'reason_needed', __( 'Please give a short reason for choosing an application that isn\'t the top score.', 'kdna-grants' ) );
		}

		// Outcomes: the winner, and everyone else (including flagged applications) unsuccessful.
		foreach ( $applications as $application ) {
			KDNA_Grants_Applications::update(
				$application['id'],
				array( 'outcome' => $application['id'] === $winner['id'] ? 'winner' : 'unsuccessful' )
			);
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			KDNA_Grants_Install::rounds_table(),
			array(
				'status'                => KDNA_Grants_Rounds::STATUS_DECIDED,
				'winner_application_id' => $winner['id'],
				'decision_note'         => $reason,
				'updated_at'            => KDNA_Grants_Rounds::now_utc(),
			),
			array( 'id' => $round_id )
		);

		$total = self::total( $winner['scores'], self::get_criteria( $round ) );
		KDNA_Grants_Rounds::log(
			$round_id,
			$is_top
				? sprintf(
					/* translators: 1: business name, 2: total score. */
					__( 'Chose %1$s as the winner (top score, %2$d).', 'kdna-grants' ),
					$winner['business_name'],
					$total
				)
				: sprintf(
					/* translators: 1: business name, 2: total score, 3: reason. */
					__( 'Chose %1$s as the winner (score %2$d, not the top score). Reason: %3$s', 'kdna-grants' ),
					$winner['business_name'],
					$total,
					$reason
				)
		);

		KDNA_Grants_Emails::queue_round( $round_id );

		if ( ! $round['is_test'] ) {
			/**
			 * Fires when a winner is chosen and the round is decided.
			 *
			 * @param int $round_id       Round ID.
			 * @param int $application_id The winning application ID.
			 */
			do_action( 'kdna_grants_round_decided', $round_id, $winner['id'] );
		}

		return true;
	}

	/**
	 * Reopens judging on a decided round. Scores can be changed again and a winner chosen again.
	 *
	 * Emails already sent can't be unsent. Any still waiting to send are cancelled.
	 *
	 * @param int $round_id Round ID.
	 * @return bool
	 */
	public static function reopen( $round_id ) {
		global $wpdb;

		$round = KDNA_Grants_Rounds::get( $round_id );
		if ( ! $round || KDNA_Grants_Rounds::STATUS_DECIDED !== $round['status'] ) {
			return false;
		}

		$applications = KDNA_Grants_Install::applications_table();

		// Cancel emails that haven't gone yet, and clear the outcomes.
		$wpdb->query( $wpdb->prepare( "UPDATE {$applications} SET email_status = '' WHERE round_id = %d AND email_status = %s", $round_id, KDNA_Grants_Emails::STATUS_QUEUED ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$applications} SET outcome = 'pending' WHERE round_id = %d", $round_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE " . KDNA_Grants_Install::rounds_table() . " SET status = %s, winner_application_id = NULL, updated_at = %s WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				KDNA_Grants_Rounds::STATUS_JUDGING,
				KDNA_Grants_Rounds::now_utc(),
				$round_id
			)
		);

		KDNA_Grants_Rounds::sync_statuses();
		KDNA_Grants_Rounds::log( $round_id, __( 'Reopened judging. The previous decision was cleared.', 'kdna-grants' ) );

		return true;
	}
}
