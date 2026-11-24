<?php
/**
 * Admin: the Offers tab, where Nick records when an applicant takes up their discount.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Offers screen.
 */
class KDNA_Launchpad_Admin_Offers {

	const STATUS_ACTION = 'kdna_launchpad_offer_status';

	/**
	 * Hooks the form handler.
	 */
	public function register() {
		add_action( 'admin_post_' . self::STATUS_ACTION, array( $this, 'handle_status' ) );
	}

	/**
	 * Shows every offer, with Accept, Decline and Undo.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this page.', 'kdna-launchpad' ) );
		}

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( ! array_key_exists( $status, KDNA_Launchpad_Offers::status_labels() ) ) {
			$status = '';
		}

		$offers = KDNA_Launchpad_Offers::get_all( $status );
		$counts = KDNA_Launchpad_Offers::counts();
		$labels = KDNA_Launchpad_Offers::status_labels();

		// The application behind each offer, for the email button and link.
		$applications = array();
		$rounds       = array();
		foreach ( $offers as $offer ) {
			if ( $offer['application_id'] && ! isset( $applications[ $offer['application_id'] ] ) ) {
				$applications[ $offer['application_id'] ] = KDNA_Launchpad_Applications::get( $offer['application_id'] );
			}
			if ( ! isset( $rounds[ $offer['round_id'] ] ) ) {
				$rounds[ $offer['round_id'] ] = KDNA_Launchpad_Rounds::get( $offer['round_id'] );
			}
		}

		include KDNA_LAUNCHPAD_PATH . 'admin/views/offers.php';
	}

	/**
	 * Accept, Decline or Undo.
	 */
	public function handle_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'kdna-launchpad' ) );
		}

		check_admin_referer( self::STATUS_ACTION );

		$offer_id = isset( $_POST['offer_id'] ) ? absint( wp_unslash( $_POST['offer_id'] ) ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$note     = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$date     = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$back     = isset( $_POST['back'] ) ? sanitize_key( wp_unslash( $_POST['back'] ) ) : '';

		$messages = array(
			KDNA_Launchpad_Offers::STATUS_ACCEPTED => 'offer_accepted',
			KDNA_Launchpad_Offers::STATUS_DECLINED => 'offer_declined',
			KDNA_Launchpad_Offers::STATUS_ACTIVE   => 'offer_undone',
		);

		if ( ! isset( $messages[ $status ] ) || ! KDNA_Launchpad_Offers::set_status( $offer_id, $status, $note, $date ) ) {
			KDNA_Launchpad_Admin::redirect( KDNA_Launchpad_Admin::OFFERS_PAGE, 'not_found' );
		}

		KDNA_Launchpad_Admin::redirect( KDNA_Launchpad_Admin::OFFERS_PAGE, $messages[ $status ], $back ? array( 'status' => $back ) : array() );
	}

	/**
	 * A small coloured label for an offer's status.
	 *
	 * @param string $status Status.
	 * @return string HTML.
	 */
	public static function badge( $status ) {
		$labels = KDNA_Launchpad_Offers::status_labels();

		return sprintf(
			'<span class="kdna-launchpad-badge kdna-launchpad-badge--offer-%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $status )
		);
	}

	/**
	 * One line describing an applicant's offer, for the Applications list and judging screen.
	 * For example "Offer used 12 May 2026. No further discounts for this client."
	 *
	 * @param array $application Application row.
	 * @return string HTML, or '' when there is no offer.
	 */
	public static function summary( $application ) {
		$offer = KDNA_Launchpad_Offers::get_for_application( $application );
		if ( ! $offer ) {
			return '';
		}

		$earlier = $offer['round_id'] !== $application['round_id'];

		switch ( $offer['status'] ) {
			case KDNA_Launchpad_Offers::STATUS_ACCEPTED:
				/* translators: %s: date. */
				$text = sprintf( __( 'Offer used %s. No further discounts for this client.', 'kdna-launchpad' ), KDNA_Launchpad_Rounds::format( $offer['closed_at'], false ) );
				break;
			case KDNA_Launchpad_Offers::STATUS_EXPIRED:
				/* translators: %s: date. */
				$text = sprintf( __( 'Offer expired %s. No new offer for this client.', 'kdna-launchpad' ), KDNA_Launchpad_Rounds::format( $offer['expires_at'], false ) );
				break;
			case KDNA_Launchpad_Offers::STATUS_DECLINED:
				$text = __( 'Offer declined. No new offer for this client.', 'kdna-launchpad' );
				break;
			default:
				/* translators: 1: code, 2: date. */
				$text = sprintf( __( 'Offer %1$s active until %2$s.', 'kdna-launchpad' ), $offer['code'], KDNA_Launchpad_Rounds::format( $offer['expires_at'], false ) );
		}

		if ( $earlier ) {
			$text = __( 'From an earlier round:', 'kdna-launchpad' ) . ' ' . $text;
		}

		return '<span class="kdna-launchpad-offer-note kdna-launchpad-offer-note--' . esc_attr( $offer['status'] ) . '">' . esc_html( $text ) . '</span>';
	}
}
