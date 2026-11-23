<?php
/**
 * Admin: the Applications screen.
 *
 * Stage 2 shows each application's details and eligibility flags, with
 * "Mark as eligible". Stage 3 adds scoring, sorting, filters and the
 * single application screen.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Applications screen.
 */
class KDNA_Grants_Admin_Applications {

	const MARK_ELIGIBLE_ACTION = 'kdna_grants_mark_eligible';

	/**
	 * Hooks the form handlers.
	 */
	public function register() {
		add_action( 'admin_post_' . self::MARK_ELIGIBLE_ACTION, array( $this, 'handle_mark_eligible' ) );
	}

	/**
	 * Shows the applications in the chosen round.
	 */
	public function render() {
		if ( ! current_user_can( KDNA_GRANTS_CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this page.', 'kdna-grants' ) );
		}

		$rounds   = KDNA_Grants_Rounds::get_all( true );
		$round_id = isset( $_GET['round'] ) ? absint( wp_unslash( $_GET['round'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$round    = $round_id ? KDNA_Grants_Rounds::get( $round_id ) : $this->default_round( $rounds );

		$applications = $round ? KDNA_Grants_Applications::get_for_round( $round['id'] ) : array();
		$flagged      = count(
			array_filter(
				$applications,
				function ( $application ) {
					return ! $application['is_eligible'];
				}
			)
		);
		$labels       = KDNA_Grants_Eligibility::flag_labels();
		$settings_ok  = (bool) absint( kdna_grants_get_setting( 'form_id', 0 ) );

		include KDNA_GRANTS_PATH . 'admin/views/applications.php';
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

	/**
	 * Clears every flag on an application ("Mark as eligible").
	 */
	public function handle_mark_eligible() {
		if ( ! current_user_can( KDNA_GRANTS_CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'kdna-grants' ) );
		}

		check_admin_referer( self::MARK_ELIGIBLE_ACTION );

		$application_id = isset( $_POST['application_id'] ) ? absint( wp_unslash( $_POST['application_id'] ) ) : 0;
		$application    = KDNA_Grants_Applications::get( $application_id );

		if ( ! $application ) {
			KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::APPLICATIONS_PAGE, 'not_found' );
		}

		KDNA_Grants_Eligibility::mark_eligible( $application_id );

		KDNA_Grants_Admin::redirect(
			KDNA_Grants_Admin::APPLICATIONS_PAGE,
			'marked_eligible',
			array( 'round' => $application['round_id'] )
		);
	}
}
