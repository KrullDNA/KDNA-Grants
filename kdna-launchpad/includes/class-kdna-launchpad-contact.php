<?php
/**
 * Contact: emailing an applicant from the dashboard, with saved templates,
 * and a record of every message sent.
 *
 * Messages go through wp_mail(), so they use whatever email sending plugin the
 * site already has, with Reply-To set to Nick's address so replies land in his
 * normal inbox.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends and logs messages to applicants.
 */
class KDNA_Launchpad_Contact {

	/**
	 * The address replies go to: the Reply-To setting, or the site's admin email.
	 *
	 * @return string
	 */
	public static function reply_to() {
		$reply = sanitize_email( (string) kdna_launchpad_get_setting( 'reply_to', '' ) );

		return is_email( $reply ) ? $reply : (string) get_option( 'admin_email' );
	}

	/**
	 * The saved message templates.
	 *
	 * @return array List of array( 'name', 'subject', 'body' ).
	 */
	public static function templates() {
		$templates = array();

		foreach ( (array) kdna_launchpad_get_setting( 'compose_templates', array() ) as $template ) {
			if ( ! empty( $template['name'] ) ) {
				$templates[] = array(
					'name'    => (string) $template['name'],
					'subject' => isset( $template['subject'] ) ? (string) $template['subject'] : '',
					'body'    => isset( $template['body'] ) ? (string) $template['body'] : '',
				);
			}
		}

		return $templates;
	}

	/**
	 * Merge tag values for one applicant.
	 *
	 * @param array $application Application row.
	 * @return array
	 */
	public static function merge_values( $application ) {
		$round = KDNA_Launchpad_Rounds::get( $application['round_id'] );
		$offer = KDNA_Launchpad_Offers::get_for_application( $application );

		return array(
			'{first_name}'    => '' !== $application['first_name'] ? $application['first_name'] : __( 'there', 'kdna-launchpad' ),
			'{business_name}' => $application['display_name'],
			'{round_name}'    => $round ? $round['name'] : '',
			'{offer_code}'    => $offer ? $offer['code'] : '',
			'{offer_percent}' => $offer ? (string) $offer['percent'] : (string) absint( kdna_launchpad_get_setting( 'offer_percent', 25 ) ),
			'{offer_expiry}'  => $offer ? KDNA_Launchpad_Rounds::format( $offer['expires_at'], false ) : '',
		);
	}

	/**
	 * A template filled in for one applicant.
	 *
	 * @param int   $index       Template number.
	 * @param array $application Application row.
	 * @return array|null 'subject' and 'body', or null if there is no such template.
	 */
	public static function fill_template( $index, $application ) {
		$templates = self::templates();

		if ( ! isset( $templates[ $index ] ) ) {
			return null;
		}

		$values = self::merge_values( $application );

		return array(
			'subject' => strtr( $templates[ $index ]['subject'], $values ),
			'body'    => strtr( $templates[ $index ]['body'], $values ),
		);
	}

	/**
	 * Sends a message to an applicant and records it on their application.
	 *
	 * @param int    $application_id Application ID.
	 * @param string $subject        Subject line.
	 * @param string $body           Message, plain text with blank lines between paragraphs.
	 * @return true|WP_Error
	 */
	public static function send( $application_id, $subject, $body ) {
		$application = KDNA_Launchpad_Applications::get( $application_id );

		if ( ! $application ) {
			return new WP_Error( 'not_found', __( 'This application could not be found.', 'kdna-launchpad' ) );
		}

		if ( ! is_email( $application['email'] ) ) {
			return new WP_Error( 'no_email', __( 'This applicant has no valid email address.', 'kdna-launchpad' ) );
		}

		$subject = trim( sanitize_text_field( $subject ) );
		$body    = trim( sanitize_textarea_field( $body ) );

		if ( '' === $subject || '' === $body ) {
			return new WP_Error( 'empty', __( 'Please write a subject and a message.', 'kdna-launchpad' ) );
		}

		$sent = KDNA_Launchpad_Emails::send(
			$application['email'],
			array(
				'subject' => $subject,
				'body'    => wpautop( esc_html( $body ) ),
			),
			self::reply_to()
		);

		if ( ! $sent ) {
			return new WP_Error( 'not_sent', __( 'The email could not be sent. Check the site\'s email settings or email sending plugin.', 'kdna-launchpad' ) );
		}

		$user                         = wp_get_current_user();
		$application['contact_log'][] = array(
			'time'    => KDNA_Launchpad_Rounds::now_utc(),
			'user'    => $user && $user->exists() ? $user->display_name : '',
			'subject' => $subject,
		);

		KDNA_Launchpad_Applications::update( $application_id, array( 'contact_log' => $application['contact_log'] ) );

		return true;
	}

	/**
	 * When this applicant was last emailed from the dashboard, or ''.
	 *
	 * @param array $application Application row.
	 * @return string UTC time.
	 */
	public static function last_contacted( $application ) {
		$log = (array) $application['contact_log'];

		return $log ? (string) end( $log )['time'] : '';
	}
}
