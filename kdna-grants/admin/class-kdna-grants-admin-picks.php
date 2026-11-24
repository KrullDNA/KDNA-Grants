<?php
/**
 * Admin: the Claude's picks tab, and the "Assess with Claude" button on the judging screen.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Claude's picks screen.
 */
class KDNA_Grants_Admin_Picks {

	const QUEUE_ACTION    = 'kdna_grants_claude_queue';
	const OVERVIEW_ACTION = 'kdna_grants_claude_overview';
	const ASSESS_ACTION   = 'kdna_grants_claude_assess';

	/**
	 * Hooks the form handlers.
	 */
	public function register() {
		add_action( 'admin_post_' . self::QUEUE_ACTION, array( $this, 'handle_queue' ) );
		add_action( 'admin_post_' . self::OVERVIEW_ACTION, array( $this, 'handle_overview' ) );
		add_action( 'wp_ajax_' . self::ASSESS_ACTION, array( $this, 'ajax_assess' ) );
	}

	/**
	 * Shows Claude's picks for the chosen round.
	 */
	public function render() {
		if ( ! current_user_can( KDNA_GRANTS_CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this page.', 'kdna-grants' ) );
		}

		$rounds   = KDNA_Grants_Rounds::get_all( true );
		$round_id = isset( $_GET['round'] ) ? absint( wp_unslash( $_GET['round'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$round    = $round_id ? KDNA_Grants_Rounds::get( $round_id ) : $this->default_round( $rounds );

		$applications = $round ? KDNA_Grants_Applications::get_for_round( $round['id'] ) : array();
		$by_id        = array();
		foreach ( $applications as $application ) {
			$by_id[ $application['id'] ] = $application;
		}

		// Everyone Claude has read, most interesting first.
		$assessed = array_values(
			array_filter(
				$applications,
				function ( $application ) {
					return ! empty( $application['ai_assessment'] );
				}
			)
		);
		usort(
			$assessed,
			function ( $a, $b ) {
				return $b['ai_assessment']['interest_rating'] - $a['ai_assessment']['interest_rating'];
			}
		);

		$counts = array_count_values( wp_list_pluck( $applications, 'ai_status' ) );
		$labels = KDNA_Grants_Eligibility::flag_labels();
		$winner = $round && $round['winner_application_id'] && isset( $by_id[ $round['winner_application_id'] ] ) ? $by_id[ $round['winner_application_id'] ] : null;

		include KDNA_GRANTS_PATH . 'admin/views/picks.php';
	}

	/**
	 * The round to show first: the most recent one Claude has read, else the latest round.
	 *
	 * @param array[] $rounds Every round, newest first.
	 * @return array|null
	 */
	private function default_round( $rounds ) {
		foreach ( $rounds as $round ) {
			if ( ! $round['is_test'] && '' !== $round['ai_status'] ) {
				return $round;
			}
		}

		foreach ( $rounds as $round ) {
			if ( ! $round['is_test'] ) {
				return $round;
			}
		}

		return $rounds ? $rounds[0] : null;
	}

	/**
	 * Link to this screen for a round.
	 *
	 * @param int $round_id Round ID.
	 * @return string
	 */
	public static function url( $round_id = 0 ) {
		$args = array( 'page' => KDNA_Grants_Admin::PICKS_PAGE );
		if ( $round_id ) {
			$args['round'] = absint( $round_id );
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Queues a round's applications for Claude: all of them, or only the ones not read yet.
	 */
	public function handle_queue() {
		$this->check( self::QUEUE_ACTION );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in check().
		$round_id = isset( $_POST['round_id'] ) ? absint( wp_unslash( $_POST['round_id'] ) ) : 0;
		$only_new = ! empty( $_POST['only_new'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! KDNA_Grants_Claude::is_enabled() ) {
			KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::PICKS_PAGE, 'claude_off', array( 'round' => $round_id ) );
		}

		KDNA_Grants_Claude::queue_round( $round_id, $only_new );

		KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::PICKS_PAGE, 'claude_queued', array( 'round' => $round_id ) );
	}

	/**
	 * Queues a fresh round overview.
	 */
	public function handle_overview() {
		global $wpdb;

		$this->check( self::OVERVIEW_ACTION );

		$round_id = isset( $_POST['round_id'] ) ? absint( wp_unslash( $_POST['round_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in check().

		if ( ! KDNA_Grants_Claude::is_enabled() ) {
			KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::PICKS_PAGE, 'claude_off', array( 'round' => $round_id ) );
		}

		$wpdb->update( KDNA_Grants_Install::rounds_table(), array( 'ai_status' => KDNA_Grants_Claude::STATUS_QUEUED ), array( 'id' => $round_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		KDNA_Grants_Claude::schedule();

		KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::PICKS_PAGE, 'claude_queued', array( 'round' => $round_id ) );
	}

	/**
	 * Assesses one application straight away (the button on the judging screen).
	 */
	public function ajax_assess() {
		check_ajax_referer( self::ASSESS_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'kdna-grants' ) ), 403 );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- A Claude request can take a minute or two.
		}

		$application_id = isset( $_POST['application_id'] ) ? absint( wp_unslash( $_POST['application_id'] ) ) : 0;
		$result         = KDNA_Grants_Claude::assess( $application_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success();
	}

	/**
	 * Stops unless the person is an administrator and the form came from this site.
	 *
	 * @param string $action Nonce action.
	 */
	private function check( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'kdna-grants' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * The interest rating as filled and empty dots, for example ●●●●○.
	 *
	 * @param int $rating 1 to 5.
	 * @return string HTML.
	 */
	public static function rating( $rating ) {
		$rating = min( 5, max( 0, (int) $rating ) );

		return sprintf(
			'<span class="kdna-grants-rating" title="%1$s"><span aria-hidden="true">%2$s</span><span class="screen-reader-text">%1$s</span></span>',
			esc_attr(
				sprintf(
					/* translators: %d: rating out of 5. */
					__( 'Interesting brief: %d out of 5', 'kdna-grants' ),
					$rating
				)
			),
			str_repeat( '●', $rating ) . '<span class="kdna-grants-rating__empty">' . str_repeat( '●', 5 - $rating ) . '</span>'
		);
	}
}
