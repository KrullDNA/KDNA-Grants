<?php
/**
 * Admin: the Rounds screen, the round editor and the test tool buttons.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Rounds screen.
 */
class KDNA_Grants_Admin_Rounds {

	const SAVE_ACTION        = 'kdna_grants_save_round';
	const DELETE_ACTION      = 'kdna_grants_delete_round';
	const CREATE_TEST_ACTION = 'kdna_grants_create_test';
	const DELETE_TEST_ACTION = 'kdna_grants_delete_test';

	/**
	 * Hooks the form handlers.
	 */
	public function register() {
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::DELETE_ACTION, array( $this, 'handle_delete' ) );
		add_action( 'admin_post_' . self::CREATE_TEST_ACTION, array( $this, 'handle_create_test' ) );
		add_action( 'admin_post_' . self::DELETE_TEST_ACTION, array( $this, 'handle_delete_test' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Screens
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Shows the rounds list, or the round editor when adding or editing a round.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this page.', 'kdna-grants' ) );
		}

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.

		if ( 'edit' === $view ) {
			$this->render_edit();
			return;
		}

		$rounds      = KDNA_Grants_Rounds::get_all( true );
		$counts      = KDNA_Grants_Applications::counts_by_round();
		$open_round  = KDNA_Grants_Rounds::get_open_round();
		$has_rounds  = (bool) KDNA_Grants_Rounds::get_latest_round();
		$test_exists = KDNA_Grants_Test_Tool::exists();
		$winners     = array();

		foreach ( $rounds as $round ) {
			if ( $round['winner_application_id'] ) {
				$winners[ $round['id'] ] = KDNA_Grants_Applications::get( $round['winner_application_id'] );
			}
		}

		include KDNA_GRANTS_PATH . 'admin/views/rounds.php';
	}

	/**
	 * Shows the round editor, for a new round or an existing one.
	 */
	private function render_edit() {
		$round_id = isset( $_GET['round'] ) ? absint( wp_unslash( $_GET['round'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$round    = $round_id ? KDNA_Grants_Rounds::get( $round_id ) : null;

		if ( $round_id && ( ! $round || $round['is_test'] ) ) {
			KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::ROUNDS_PAGE, 'not_found' );
		}

		// Values to show: what was just typed (if saving failed), the round's own values, or suggestions.
		$errors = array();
		$sticky = get_transient( $this->sticky_key() );
		delete_transient( $this->sticky_key() );

		if ( $round ) {
			$values = array(
				'name'     => $round['name'],
				'opens'    => KDNA_Grants_Rounds::to_site( $round['opens_at'] ),
				'closes'   => KDNA_Grants_Rounds::to_site( $round['closes_at'] ),
				'announce' => (string) $round['announce_date'],
			);
		} else {
			$values = KDNA_Grants_Rounds::suggest_next();
		}

		if ( is_array( $sticky ) && (int) $sticky['round_id'] === $round_id ) {
			$values = array_merge( $values, $sticky['values'] );
			$errors = $sticky['errors'];
		}

		$is_locked = $round && KDNA_Grants_Rounds::STATUS_DECIDED === $round['status'];
		$timezone  = wp_timezone_string();

		include KDNA_GRANTS_PATH . 'admin/views/round-edit.php';
	}

	/*
	 * -----------------------------------------------------------------------
	 * Form handlers
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Saves a round from the round editor.
	 */
	public function handle_save() {
		$this->check( self::SAVE_ACTION );

		$round_id = isset( $_POST['round_id'] ) ? absint( wp_unslash( $_POST['round_id'] ) ) : 0;
		$round    = $round_id ? KDNA_Grants_Rounds::get( $round_id ) : null;

		if ( $round_id && ( ! $round || $round['is_test'] ) ) {
			KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::ROUNDS_PAGE, 'not_found' );
		}

		if ( $round && KDNA_Grants_Rounds::STATUS_DECIDED === $round['status'] ) {
			KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::ROUNDS_PAGE, 'round_locked' );
		}

		$typed = array(
			'name'     => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'opens'    => isset( $_POST['opens'] ) ? sanitize_text_field( wp_unslash( $_POST['opens'] ) ) : '',
			'closes'   => isset( $_POST['closes'] ) ? sanitize_text_field( wp_unslash( $_POST['closes'] ) ) : '',
			'announce' => isset( $_POST['announce'] ) ? sanitize_text_field( wp_unslash( $_POST['announce'] ) ) : '',
		);

		$announce = DateTime::createFromFormat( '!Y-m-d', $typed['announce'] );

		$data = array(
			'name'          => $typed['name'],
			'opens_at'      => KDNA_Grants_Rounds::from_site( $typed['opens'] ),
			'closes_at'     => KDNA_Grants_Rounds::from_site( $typed['closes'] ),
			'announce_date' => $announce ? $announce->format( 'Y-m-d' ) : '',
		);

		$errors = KDNA_Grants_Rounds::validate( $data, $round_id );

		if ( $errors ) {
			set_transient(
				$this->sticky_key(),
				array(
					'round_id' => $round_id,
					'values'   => $typed,
					'errors'   => $errors,
				),
				5 * MINUTE_IN_SECONDS
			);

			$args = array( 'view' => 'edit' );
			if ( $round_id ) {
				$args['round'] = $round_id;
			}
			KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::ROUNDS_PAGE, '', $args );
		}

		KDNA_Grants_Rounds::save( $data, $round_id );

		KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::ROUNDS_PAGE, 'round_saved' );
	}

	/**
	 * Deletes a round with no applications.
	 */
	public function handle_delete() {
		$this->check( self::DELETE_ACTION );

		$round_id = isset( $_POST['round_id'] ) ? absint( wp_unslash( $_POST['round_id'] ) ) : 0;
		$round    = KDNA_Grants_Rounds::get( $round_id );

		if ( ! $round || $round['is_test'] ) {
			KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::ROUNDS_PAGE, 'not_found' );
		}

		$deleted = KDNA_Grants_Rounds::delete( $round_id );

		KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::ROUNDS_PAGE, $deleted ? 'round_deleted' : 'round_not_deleted' );
	}

	/**
	 * Creates the test rounds, then shows the test round's applications.
	 */
	public function handle_create_test() {
		$this->check( self::CREATE_TEST_ACTION );

		$round_id = KDNA_Grants_Test_Tool::create();

		if ( ! $round_id ) {
			KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::ROUNDS_PAGE, 'test_exists' );
		}

		KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::APPLICATIONS_PAGE, 'test_created', array( 'round' => $round_id ) );
	}

	/**
	 * Deletes the test rounds and everything the test tool created.
	 */
	public function handle_delete_test() {
		$this->check( self::DELETE_TEST_ACTION );

		KDNA_Grants_Test_Tool::delete();

		KDNA_Grants_Admin::redirect( KDNA_Grants_Admin::ROUNDS_PAGE, 'test_deleted' );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Helpers
	 * -----------------------------------------------------------------------
	 */

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
	 * Where the round editor keeps typed values for a moment when saving fails, per user.
	 *
	 * @return string
	 */
	private function sticky_key() {
		return 'kdna_grants_round_form_' . get_current_user_id();
	}
}
