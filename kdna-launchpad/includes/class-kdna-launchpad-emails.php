<?php
/**
 * Emails: templates, merge tags and batch sending through WP Cron.
 *
 * The winner and unsuccessful emails are queued when a winner is chosen and
 * sent in small batches in the background, so choosing a winner never times
 * out however many people applied. Everything goes through wp_mail(), so it
 * works with whatever email sending plugin the site already uses.
 *
 * The applicant confirmation email is sent by KDNA Forms, not here.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and sends the emails.
 */
class KDNA_Launchpad_Emails {

	const STATUS_QUEUED = 'queued';
	const STATUS_SENT   = 'sent';
	const STATUS_FAILED = 'failed';

	/**
	 * WP Cron task that sends the next batch.
	 */
	const CRON_HOOK = 'kdna_launchpad_send_emails';

	/**
	 * How many emails each batch sends.
	 */
	const BATCH_SIZE = 20;

	/**
	 * Hooks into WordPress. Called once KDNA Forms is available.
	 */
	public static function register() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_batch' ) );

		// The application form's own emails (sent by KDNA Forms) get the same design.
		$form_id = absint( kdna_launchpad_get_setting( 'form_id', 0 ) );
		if ( $form_id ) {
			KDNA_Launchpad_Forms_Bridge::style_notifications( $form_id, array( 'KDNA_Launchpad_Email_Design', 'template' ) );
		}
	}

	/**
	 * The templates people can edit, with their names.
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'winner'       => __( 'Winner email', 'kdna-launchpad' ),
			'unsuccessful' => __( 'Unsuccessful email', 'kdna-launchpad' ),
		);
	}

	/**
	 * The merge tags, with a short description of each.
	 *
	 * @return array
	 */
	public static function merge_tags() {
		return array(
			'{first_name}'       => __( 'Applicant\'s first name', 'kdna-launchpad' ),
			'{business_name}'    => __( 'Business name', 'kdna-launchpad' ),
			'{round_name}'       => __( 'Round name', 'kdna-launchpad' ),
			'{close_date}'       => __( 'Date the round closed', 'kdna-launchpad' ),
			'{announce_date}'    => __( 'Winner announcement date', 'kdna-launchpad' ),
			'{next_open_date}'   => __( 'Date the next round opens', 'kdna-launchpad' ),
			'{offer_code}'       => __( 'Their offer code (unsuccessful email)', 'kdna-launchpad' ),
			'{offer_percent}'    => __( 'The offer discount, for example 25', 'kdna-launchpad' ),
			'{offer_expiry}'     => __( 'Date the offer expires', 'kdna-launchpad' ),
			'{offer_cap}'        => __( 'The offer cap, if you set one', 'kdna-launchpad' ),
			'[offer]...[/offer]' => __( 'Wrap the offer wording in these, so it only shows to applicants who get an offer', 'kdna-launchpad' ),
		);
	}

	/**
	 * A saved template, falling back to the default copy.
	 *
	 * @param string $type 'winner' or 'unsuccessful'.
	 * @return array 'subject' and 'body'.
	 */
	public static function get_template( $type ) {
		$defaults = KDNA_Launchpad_Install::default_emails();
		$saved    = (array) kdna_launchpad_get_setting( 'emails', array() );
		$template = isset( $saved[ $type ] ) && is_array( $saved[ $type ] ) ? $saved[ $type ] : array();

		return array(
			'subject' => isset( $template['subject'] ) && '' !== trim( $template['subject'] ) ? $template['subject'] : $defaults[ $type ]['subject'],
			'body'    => isset( $template['body'] ) && '' !== trim( $template['body'] ) ? $template['body'] : $defaults[ $type ]['body'],
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Building an email
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The merge tag values for an application.
	 *
	 * @param array $application Application row.
	 * @param array $round       Round row.
	 * @return array Tag => plain text value.
	 */
	public static function merge_values( $application, $round ) {
		$next = KDNA_Launchpad_Rounds::get_next_round();

		return array_merge(
			array(
				'{first_name}'     => $application['first_name'],
				'{business_name}'  => $application['display_name'],
				'{round_name}'     => $round['name'],
				'{close_date}'     => KDNA_Launchpad_Rounds::format( $round['closes_at'], false ),
				'{announce_date}'  => KDNA_Launchpad_Rounds::format_date( $round['announce_date'] ),
				'{next_open_date}' => $next ? KDNA_Launchpad_Rounds::format( $next['opens_at'], false ) : __( 'a date to be announced soon', 'kdna-launchpad' ),
			),
			KDNA_Launchpad_Offers::merge_values( $application )
		);
	}

	/**
	 * Fills in a template.
	 *
	 * @param array $template 'subject' and 'body'.
	 * @param array $values   Merge tag values.
	 * @return array 'subject' (plain text) and 'body' (HTML).
	 */
	public static function render( $template, $values ) {
		// [offer]...[/offer] sections only show when the applicant has an offer code.
		$has_offer = ! empty( $values['{offer_code}'] );
		$subject   = strtr( KDNA_Launchpad_Offers::apply_blocks( $template['subject'], $has_offer ), $values );
		$body      = strtr( wpautop( KDNA_Launchpad_Offers::apply_blocks( $template['body'], $has_offer ) ), array_map( 'esc_html', $values ) );

		return array(
			'subject' => wp_strip_all_tags( $subject ),
			'body'    => $body,
		);
	}

	/**
	 * Sends one email as HTML, with replies going to Nick's address.
	 *
	 * @param string $to       Email address.
	 * @param array  $email    'subject' and 'body'.
	 * @param string $reply_to Reply-To address, or '' for the Reply-To setting.
	 * @return bool Whether wp_mail() accepted it.
	 */
	public static function send( $to, $email, $reply_to = '' ) {
		if ( ! is_email( $to ) ) {
			return false;
		}

		$reply_to = is_email( $reply_to ) ? $reply_to : KDNA_Launchpad_Contact::reply_to();

		// The designed email: logo, fonts and colours from Settings > Emails and messages.
		$html = KDNA_Launchpad_Email_Design::wrap( $email['body'], $email['subject'] );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		return (bool) wp_mail( $to, $email['subject'], $html, $headers );
	}

	/**
	 * Sends a test of a template to the given address, filled in with sample data.
	 *
	 * @param string     $type     'winner' or 'unsuccessful'.
	 * @param string     $to       Email address.
	 * @param array|null $template Unsaved subject and body to test, or null to use the saved template.
	 * @return bool
	 */
	public static function send_test( $type, $to, $template = null ) {
		$template = $template ? $template : self::get_template( $type );

		$email            = self::render( $template, self::sample_values() );
		$email['subject'] = '[' . __( 'Test', 'kdna-launchpad' ) . '] ' . $email['subject'];

		return self::send( $to, $email );
	}

	/**
	 * Sample details for test emails and the design preview.
	 *
	 * @return array Merge tag => value.
	 */
	public static function sample_values() {
		$next = KDNA_Launchpad_Rounds::get_next_round();

		return array(
			'{first_name}'     => 'Sam',
			'{business_name}'  => 'Brightside Bakery',
			'{round_name}'     => __( 'Q1 2027', 'kdna-launchpad' ),
			'{close_date}'     => wp_date( 'j F Y' ),
			'{announce_date}'  => wp_date( 'j F Y', time() + 14 * DAY_IN_SECONDS ),
			'{next_open_date}' => $next ? KDNA_Launchpad_Rounds::format( $next['opens_at'], false ) : wp_date( 'j F Y', time() + 60 * DAY_IN_SECONDS ),
			'{offer_code}'     => 'KDNA-BRIGH-7Q4X',
			'{offer_percent}'  => (string) absint( kdna_launchpad_get_setting( 'offer_percent', 25 ) ),
			'{offer_expiry}'   => wp_date( 'j F Y', time() + absint( kdna_launchpad_get_setting( 'offer_days', 90 ) ) * DAY_IN_SECONDS ),
			'{offer_cap}'      => (string) kdna_launchpad_get_setting( 'offer_cap', '' ),
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Queue and batches
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Queues the winner and unsuccessful emails for every applicant in a decided round.
	 *
	 * @param int $round_id Round ID.
	 */
	public static function queue_round( $round_id ) {
		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'UPDATE ' . KDNA_Launchpad_Install::applications_table() . " SET email_status = %s WHERE round_id = %d AND outcome IN ('winner','unsuccessful')", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				self::STATUS_QUEUED,
				absint( $round_id )
			)
		);

		self::schedule();
	}

	/**
	 * Puts one application's email back in the queue, for example after it failed.
	 *
	 * @param int $application_id Application ID.
	 * @return bool
	 */
	public static function resend( $application_id ) {
		$application = KDNA_Launchpad_Applications::get( $application_id );

		if ( ! $application || ! in_array( $application['outcome'], array( 'winner', 'unsuccessful' ), true ) ) {
			return false;
		}

		KDNA_Launchpad_Applications::update( $application_id, array( 'email_status' => self::STATUS_QUEUED ) );
		self::schedule();

		return true;
	}

	/**
	 * Schedules the next batch straight away, and nudges WP Cron so it runs without waiting for a visitor.
	 *
	 * @param int $delay Seconds to wait first.
	 */
	public static function schedule( $delay = 0 ) {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + $delay, self::CRON_HOOK );
		}

		if ( ! $delay && function_exists( 'spawn_cron' ) && ! wp_doing_cron() ) {
			spawn_cron();
		}
	}

	/**
	 * Sends the next batch of queued emails, then schedules another batch if any are left.
	 */
	public static function process_batch() {
		global $wpdb;

		// Only one batch at a time.
		if ( get_transient( 'kdna_launchpad_sending' ) ) {
			self::schedule( 60 );
			return;
		}
		set_transient( 'kdna_launchpad_sending', 1, 5 * MINUTE_IN_SECONDS );

		$table = KDNA_Launchpad_Install::applications_table();
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE email_status = %s ORDER BY id ASC LIMIT %d", self::STATUS_QUEUED, self::BATCH_SIZE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$cache = array();

		foreach ( $ids as $id ) {
			$application = KDNA_Launchpad_Applications::get( $id );

			if ( ! $application || ! in_array( $application['outcome'], array( 'winner', 'unsuccessful' ), true ) ) {
				KDNA_Launchpad_Applications::update( $id, array( 'email_status' => '' ) );
				continue;
			}

			$round_id = $application['round_id'];
			if ( ! isset( $cache[ $round_id ] ) ) {
				$cache[ $round_id ] = KDNA_Launchpad_Rounds::get( $round_id );
			}

			$email = self::render( self::get_template( $application['outcome'] ), self::merge_values( $application, $cache[ $round_id ] ) );
			$sent  = self::send( $application['email'], $email );

			KDNA_Launchpad_Applications::update( $id, array( 'email_status' => $sent ? self::STATUS_SENT : self::STATUS_FAILED ) );
		}

		delete_transient( 'kdna_launchpad_sending' );

		$remaining = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE email_status = %s", self::STATUS_QUEUED ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		if ( $remaining > 0 ) {
			self::schedule( 10 );
		}
	}

	/**
	 * Plain-English name of an email status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			self::STATUS_QUEUED => __( 'Queued', 'kdna-launchpad' ),
			self::STATUS_SENT   => __( 'Sent', 'kdna-launchpad' ),
			self::STATUS_FAILED => __( 'Failed', 'kdna-launchpad' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : '';
	}
}
