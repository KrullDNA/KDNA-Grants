<?php
/**
 * Admin: the Applications list, the single application (judging) screen,
 * the Choose winner panel, and the scorecard autosave.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Applications screens.
 */
class KDNA_Grants_Admin_Applications {

	const MARK_ELIGIBLE_ACTION = 'kdna_grants_mark_eligible';
	const CHOOSE_WINNER_ACTION = 'kdna_grants_choose_winner';
	const REOPEN_ACTION        = 'kdna_grants_reopen_judging';
	const RESEND_ACTION        = 'kdna_grants_resend_email';
	const SAVE_SCORE_ACTION    = 'kdna_grants_save_score';

	/**
	 * Filters on the list, key => label.
	 *
	 * @return array
	 */
	public static function filters() {
		return array(
			''            => __( 'All', 'kdna-grants' ),
			'eligible'    => __( 'Eligible', 'kdna-grants' ),
			'flagged'     => __( 'Flagged', 'kdna-grants' ),
			'unscored'    => __( 'Unscored', 'kdna-grants' ),
			'shortlisted' => __( 'Shortlisted', 'kdna-grants' ),
		);
	}

	/**
	 * Hooks the form handlers and the autosave.
	 */
	public function register() {
		add_action( 'admin_post_' . self::MARK_ELIGIBLE_ACTION, array( $this, 'handle_mark_eligible' ) );
		add_action( 'admin_post_' . self::CHOOSE_WINNER_ACTION, array( $this, 'handle_choose_winner' ) );
		add_action( 'admin_post_' . self::REOPEN_ACTION, array( $this, 'handle_reopen' ) );
		add_action( 'admin_post_' . self::RESEND_ACTION, array( $this, 'handle_resend' ) );
		add_action( 'wp_ajax_' . self::SAVE_SCORE_ACTION, array( $this, 'ajax_save_score' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Screens
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Shows the list, or a single application.
	 */
	public function render() {
		if ( ! current_user_can( KDNA_GRANTS_CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this page.', 'kdna-grants' ) );
		}

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.

		if ( 'application' === $view ) {
			$this->render_single();
			return;
		}

		$this->render_list();
	}

	/**
	 * Shows the applications in the chosen round, with filters, sorting and the Choose winner panel.
	 */
	private function render_list() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		$rounds   = KDNA_Grants_Rounds::get_all( true );
		$round_id = isset( $_GET['round'] ) ? absint( wp_unslash( $_GET['round'] ) ) : 0;
		$filter   = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : '';
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		$order    = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'asc' : 'desc';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$round = $round_id ? KDNA_Grants_Rounds::get( $round_id ) : $this->default_round( $rounds );

		if ( ! array_key_exists( $filter, self::filters() ) ) {
			$filter = '';
		}

		$all_applications = $round ? KDNA_Grants_Applications::get_for_round( $round['id'] ) : array();
		$criteria         = KDNA_Grants_Judging::get_criteria( $round );
		$decision         = $round ? KDNA_Grants_Judging::decision_status( $round, $all_applications ) : null;
		$applications     = $this->filter_applications( $all_applications, $filter, $criteria );
		$filter_counts    = array();

		foreach ( array_keys( self::filters() ) as $key ) {
			$filter_counts[ $key ] = count( $this->filter_applications( $all_applications, $key, $criteria ) );
		}

		// Sorting by total uses the tie-break too, so the order matches the ranking.
		if ( 'total' === $orderby ) {
			$tiebreak = $criteria['tiebreak'];
			usort(
				$applications,
				function ( $a, $b ) use ( $order, $tiebreak ) {
					$diff = $a['total_score'] - $b['total_score'];
					if ( 0 === $diff ) {
						$diff = ( isset( $a['scores'][ $tiebreak ] ) ? (int) $a['scores'][ $tiebreak ] : 0 ) - ( isset( $b['scores'][ $tiebreak ] ) ? (int) $b['scores'][ $tiebreak ] : 0 );
					}
					return 'asc' === $order ? $diff : -$diff;
				}
			);
		}

		$winner       = $round && $round['winner_application_id'] ? KDNA_Grants_Applications::get( $round['winner_application_id'] ) : null;
		$labels       = KDNA_Grants_Eligibility::flag_labels();
		$settings_ok  = (bool) absint( kdna_grants_get_setting( 'form_id', 0 ) );
		$max_total    = KDNA_Grants_Judging::max_total( $criteria );
		$base_url     = add_query_arg(
			array(
				'page'  => KDNA_Grants_Admin::APPLICATIONS_PAGE,
				'round' => $round ? $round['id'] : 0,
			),
			admin_url( 'admin.php' )
		);

		include KDNA_GRANTS_PATH . 'admin/views/applications.php';
	}

	/**
	 * Shows one application: every answer on the left, the scorecard on the right.
	 */
	private function render_single() {
		$application_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$application    = KDNA_Grants_Applications::get( $application_id );
		$round          = $application ? KDNA_Grants_Rounds::get( $application['round_id'] ) : null;

		if ( ! $application || ! $round ) {
			KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::APPLICATIONS_PAGE, 'not_found' );
		}

		$entry    = $application['entry_id'] ? KDNA_Grants_Forms_Bridge::get_entry( $application['entry_id'] ) : false;
		$answers  = $entry ? KDNA_Grants_Forms_Bridge::get_entry_answers( $entry ) : array();
		$map      = (array) kdna_grants_get_setting( 'field_map', array() );
		$logos    = $entry && ! empty( $map['logo'] ) ? KDNA_Grants_Forms_Bridge::get_file_urls( $entry, $map['logo'] ) : array();
		$criteria = KDNA_Grants_Judging::get_criteria( $round );
		$state    = KDNA_Grants_Judging::state( $application, $round );
		$locked   = ! KDNA_Grants_Judging::can_score( $round );
		$labels   = KDNA_Grants_Eligibility::flag_labels();

		// Previous and Next, in the order applications arrived.
		$siblings = wp_list_pluck( KDNA_Grants_Applications::get_for_round( $round['id'] ), 'id' );
		$position = array_search( $application['id'], $siblings, true );
		$prev_id  = false !== $position && $position > 0 ? $siblings[ $position - 1 ] : 0;
		$next_id  = false !== $position && $position < count( $siblings ) - 1 ? $siblings[ $position + 1 ] : 0;

		$list_url = add_query_arg(
			array(
				'page'  => KDNA_Grants_Admin::APPLICATIONS_PAGE,
				'round' => $round['id'],
			),
			admin_url( 'admin.php' )
		);

		include KDNA_GRANTS_PATH . 'admin/views/application-single.php';
	}

	/**
	 * Link to the single application screen.
	 *
	 * @param int $application_id Application ID.
	 * @return string
	 */
	public static function single_url( $application_id ) {
		return add_query_arg(
			array(
				'page' => KDNA_Grants_Admin::APPLICATIONS_PAGE,
				'view' => 'application',
				'id'   => absint( $application_id ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Applies a list filter.
	 *
	 * @param array[] $applications Applications.
	 * @param string  $filter       Filter key.
	 * @param array   $criteria     Criteria from KDNA_Grants_Judging::get_criteria().
	 * @return array[]
	 */
	private function filter_applications( $applications, $filter, $criteria ) {
		if ( '' === $filter ) {
			return $applications;
		}

		return array_values(
			array_filter(
				$applications,
				function ( $application ) use ( $filter, $criteria ) {
					switch ( $filter ) {
						case 'eligible':
							return $application['is_eligible'];
						case 'flagged':
							return ! $application['is_eligible'];
						case 'unscored':
							return ! KDNA_Grants_Judging::is_scored( $application, $criteria );
						case 'shortlisted':
							return $application['shortlisted'];
					}
					return true;
				}
			)
		);
	}

	/**
	 * The round to show when none is picked: the open round, else the most recent real round, else any round.
	 *
	 * @param array[] $rounds Every round, newest first.
	 * @return array|null
	 */
	private function default_round( $rounds ) {
		$open = KDNA_Grants_Rounds::get_open_round();
		if ( $open ) {
			return $open;
		}

		$now = KDNA_Grants_Rounds::now_utc();
		foreach ( $rounds as $round ) {
			if ( ! $round['is_test'] && $round['opens_at'] <= $now ) {
				return $round;
			}
		}

		return $rounds ? $rounds[0] : null;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Scorecard autosave
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Saves one scorecard change sent from the browser, and sends back the new total.
	 */
	public function ajax_save_score() {
		check_ajax_referer( self::SAVE_SCORE_ACTION, 'nonce' );

		if ( ! current_user_can( KDNA_GRANTS_CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'kdna-grants' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked above.
		$application_id = isset( $_POST['application_id'] ) ? absint( wp_unslash( $_POST['application_id'] ) ) : 0;
		$field          = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$key            = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		$value          = isset( $_POST['value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['value'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$result = KDNA_Grants_Judging::save( $application_id, $field, $value, $key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Form handlers
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Clears every flag on an application ("Mark as eligible").
	 */
	public function handle_mark_eligible() {
		$this->check( self::MARK_ELIGIBLE_ACTION, KDNA_GRANTS_CAP );

		$application = $this->posted_application();
		KDNA_Grants_Eligibility::mark_eligible( $application['id'] );

		$this->back( $application, 'marked_eligible' );
	}

	/**
	 * Chooses the winner, after the confirmation box.
	 */
	public function handle_choose_winner() {
		$this->check( self::CHOOSE_WINNER_ACTION, 'manage_options' );

		$application = $this->posted_application();
		$reason      = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in check().
		$result      = KDNA_Grants_Judging::choose_winner( $application['round_id'], $application['id'], $reason );

		if ( is_wp_error( $result ) ) {
			set_transient( 'kdna_grants_error_' . get_current_user_id(), $result->get_error_message(), MINUTE_IN_SECONDS );
			KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::APPLICATIONS_PAGE, 'error', array( 'round' => $application['round_id'] ) );
		}

		KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::APPLICATIONS_PAGE, 'winner_chosen', array( 'round' => $application['round_id'] ) );
	}

	/**
	 * Reopens judging on a decided round, after a confirmation.
	 */
	public function handle_reopen() {
		$this->check( self::REOPEN_ACTION, 'manage_options' );

		$round_id = isset( $_POST['round_id'] ) ? absint( wp_unslash( $_POST['round_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in check().
		KDNA_Grants_Judging::reopen( $round_id );

		KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::APPLICATIONS_PAGE, 'judging_reopened', array( 'round' => $round_id ) );
	}

	/**
	 * Puts a failed email back in the queue.
	 */
	public function handle_resend() {
		$this->check( self::RESEND_ACTION, 'manage_options' );

		$application = $this->posted_application();
		KDNA_Grants_Emails::resend( $application['id'] );

		$this->back( $application, 'email_queued' );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Helpers
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Stops unless the person has the capability and the form came from this site.
	 *
	 * @param string $action     Nonce action.
	 * @param string $capability Capability needed.
	 */
	private function check( $action, $capability ) {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'kdna-grants' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * The application named in the submitted form, or back to the list if it doesn't exist.
	 *
	 * @return array
	 */
	private function posted_application() {
		$application_id = isset( $_POST['application_id'] ) ? absint( wp_unslash( $_POST['application_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in check().
		$application    = KDNA_Grants_Applications::get( $application_id );

		if ( ! $application ) {
			KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::APPLICATIONS_PAGE, 'not_found' );
		}

		return $application;
	}

	/**
	 * Goes back to wherever the button was pressed: the single application screen or the list.
	 *
	 * @param array  $application Application row.
	 * @param string $message     Message code.
	 */
	private function back( $application, $message ) {
		$from = isset( $_POST['from'] ) ? sanitize_key( wp_unslash( $_POST['from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in check().

		if ( 'single' === $from ) {
			KDNA_Grants_Admin::redirect(
				KDNA_Grants_Admin::APPLICATIONS_PAGE,
				$message,
				array(
					'view' => 'application',
					'id'   => $application['id'],
				)
			);
		}

		KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::APPLICATIONS_PAGE, $message, array( 'round' => $application['round_id'] ) );
	}
}
