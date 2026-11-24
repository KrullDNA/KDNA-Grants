<?php
/**
 * Admin: the email window for contacting an applicant from the dashboard.
 *
 * An email button can be placed anywhere an applicant is listed. It opens one
 * shared window with To filled in, a template picker, "Draft with Claude", and Send.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The dashboard email window.
 */
class KDNA_Launchpad_Admin_Contact {

	const NONCE = 'kdna_launchpad_contact';

	/**
	 * Whether the window has been added to this page yet.
	 *
	 * @var bool
	 */
	private static $dialog_needed = false;

	/**
	 * Hooks the browser requests.
	 */
	public function register() {
		add_action( 'wp_ajax_kdna_launchpad_contact_template', array( $this, 'ajax_template' ) );
		add_action( 'wp_ajax_kdna_launchpad_contact_draft', array( $this, 'ajax_draft' ) );
		add_action( 'wp_ajax_kdna_launchpad_contact_send', array( $this, 'ajax_send' ) );
		add_action( 'admin_footer', array( $this, 'print_dialog' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The button and the window
	 * -----------------------------------------------------------------------
	 */

	/**
	 * An email button for an applicant. Only shown to administrators.
	 *
	 * @param array  $application Application row.
	 * @param string $style       'link' (the email address as a link) or 'button'.
	 * @return string HTML.
	 */
	public static function button( $application, $style = 'link' ) {
		if ( ! current_user_can( 'manage_options' ) || ! is_email( $application['email'] ) ) {
			return $application['email'] ? '<span>' . esc_html( $application['email'] ) . '</span>' : '';
		}

		self::$dialog_needed = true;

		$last  = KDNA_Launchpad_Contact::last_contacted( $application );
		$label = 'button' === $style ? esc_html__( 'Email applicant', 'kdna-launchpad' ) : esc_html( $application['email'] );

		$html = sprintf(
			'<button type="button" class="%1$s kdna-launchpad-contact" data-application="%2$d" data-email="%3$s" data-name="%4$s" data-business="%5$s">%6$s</button>',
			'button' === $style ? 'button' : 'button-link kdna-launchpad-contact--link',
			(int) $application['id'],
			esc_attr( $application['email'] ),
			esc_attr( trim( $application['first_name'] . ' ' . $application['last_name'] ) ),
			esc_attr( $application['business_name'] ),
			$label
		);

		if ( $last ) {
			$html .= ' <span class="kdna-launchpad-contacted" title="' . esc_attr(
				sprintf(
					/* translators: %s: date. */
					__( 'Last emailed %s', 'kdna-launchpad' ),
					KDNA_Launchpad_Rounds::format( $last )
				)
			) . '">' . esc_html(
				sprintf(
					/* translators: %s: date. */
					__( 'Contacted %s', 'kdna-launchpad' ),
					KDNA_Launchpad_Rounds::format( $last, false )
				)
			) . '</span>';
		}

		return $html;
	}

	/**
	 * Prints the shared email window at the foot of any page that has an email button.
	 */
	public function print_dialog() {
		if ( ! self::$dialog_needed ) {
			return;
		}

		$templates = KDNA_Launchpad_Contact::templates();
		$claude    = KDNA_Launchpad_Claude::is_enabled();
		$reply_to  = KDNA_Launchpad_Contact::reply_to();

		include KDNA_LAUNCHPAD_PATH . 'admin/views/contact-dialog.php';
	}

	/*
	 * -----------------------------------------------------------------------
	 * Browser requests
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Stops unless the person is an administrator and the request came from this site. Returns the application.
	 *
	 * @return array
	 */
	private function check() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'kdna-launchpad' ) ), 403 );
		}

		$application_id = isset( $_POST['application_id'] ) ? absint( wp_unslash( $_POST['application_id'] ) ) : 0;
		$application    = KDNA_Launchpad_Applications::get( $application_id );

		if ( ! $application ) {
			wp_send_json_error( array( 'message' => __( 'This application could not be found.', 'kdna-launchpad' ) ), 404 );
		}

		return $application;
	}

	/**
	 * Fills in a saved template for this applicant.
	 */
	public function ajax_template() {
		$application = $this->check();
		$index       = isset( $_POST['template'] ) ? absint( wp_unslash( $_POST['template'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in check().
		$filled      = KDNA_Launchpad_Contact::fill_template( $index, $application );

		if ( ! $filled ) {
			wp_send_json_error( array( 'message' => __( 'That template could not be found.', 'kdna-launchpad' ) ), 404 );
		}

		wp_send_json_success( $filled );
	}

	/**
	 * Asks Claude to draft the email.
	 */
	public function ajax_draft() {
		$application = $this->check();
		$purpose     = isset( $_POST['purpose'] ) ? sanitize_textarea_field( wp_unslash( $_POST['purpose'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in check().

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- A Claude request can take a minute or two.
		}

		$draft = KDNA_Launchpad_Claude::draft_email( $application['id'], $purpose );

		if ( is_wp_error( $draft ) ) {
			wp_send_json_error( array( 'message' => $draft->get_error_message() ) );
		}

		wp_send_json_success( $draft );
	}

	/**
	 * Sends the email and records it.
	 */
	public function ajax_send() {
		$application = $this->check();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in check().
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body    = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$result = KDNA_Launchpad_Contact::send( $application['id'], $subject, $body );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: email address. */
					__( 'Sent to %s.', 'kdna-launchpad' ),
					$application['email']
				),
				'label'   => sprintf(
					/* translators: %s: date. */
					__( 'Contacted %s', 'kdna-launchpad' ),
					KDNA_Launchpad_Rounds::format( KDNA_Launchpad_Rounds::now_utc(), false )
				),
			)
		);
	}
}
