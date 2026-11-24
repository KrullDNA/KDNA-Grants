<?php
/**
 * ABN: the official ABN Lookup, and the live ABN check on the application form.
 *
 * As the applicant types their ABN, the form checks it with the official
 * checksum and then (with a free ABN Lookup key, called a GUID, from the
 * Australian Business Register) looks it up: the business name fills itself
 * in, registered trading names are offered for "Trading as", and an ABN that
 * doesn't exist or has been cancelled can't be submitted.
 *
 * ABN details are public information. Lookups are cached for a day.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ABN Lookup and the form's ABN field.
 */
class KDNA_Launchpad_Abn {

	/**
	 * The Australian Business Register's ABN Lookup service (JSON).
	 */
	const API_URL = 'https://abr.business.gov.au/json/AbnDetails.aspx';

	/**
	 * Option that holds the ABN Lookup key (GUID), kept apart from the other settings.
	 */
	const GUID_OPTION = 'kdna_launchpad_abr_guid';

	/**
	 * Browser request that looks up an ABN while the applicant types.
	 */
	const AJAX_ACTION = 'kdna_launchpad_abn';

	/**
	 * Most lookups one visitor can make in an hour, to stop the form being used as a free ABN search.
	 */
	const HOURLY_LIMIT = 30;

	/**
	 * Hooks into WordPress and the application form. Called once KDNA Forms is available.
	 */
	public static function register() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_lookup' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_lookup' ) );

		$form_id = absint( kdna_launchpad_get_setting( 'form_id', 0 ) );
		$map     = (array) kdna_launchpad_get_setting( 'field_map', array() );

		if ( ! $form_id || empty( $map['abn'] ) ) {
			return;
		}

		KDNA_Launchpad_Forms_Bridge::on_form_display( $form_id, array( __CLASS__, 'enqueue' ) );
		KDNA_Launchpad_Forms_Bridge::on_field_validation( $form_id, $map['abn'], array( __CLASS__, 'validate_field' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Settings
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The ABN Lookup key: the KDNA_LAUNCHPAD_ABR_GUID constant in wp-config.php if set, otherwise the saved key.
	 *
	 * @return string
	 */
	public static function get_guid() {
		if ( defined( 'KDNA_LAUNCHPAD_ABR_GUID' ) && KDNA_LAUNCHPAD_ABR_GUID ) {
			return (string) KDNA_LAUNCHPAD_ABR_GUID;
		}

		return (string) get_option( self::GUID_OPTION, '' );
	}

	/**
	 * True when the official lookup can be used.
	 *
	 * @return bool
	 */
	public static function has_lookup() {
		return '' !== self::get_guid();
	}

	/*
	 * -----------------------------------------------------------------------
	 * The lookup
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Looks up an ABN on the Australian Business Register.
	 *
	 * @param string $abn The ABN (spaces allowed).
	 * @return array|WP_Error array( 'abn', 'status' ('active'|'cancelled'), 'entity_name', 'business_names' (list), 'state' ),
	 *                        or an error: 'invalid' (fails the checksum), 'not_found', 'no_key' or 'unavailable'.
	 */
	public static function lookup( $abn ) {
		$digits = KDNA_Launchpad_Eligibility::abn_digits( $abn );

		if ( ! KDNA_Launchpad_Eligibility::is_valid_abn( $digits ) ) {
			return new WP_Error( 'invalid', __( 'That ABN doesn\'t look right. Please check the 11 digits.', 'kdna-launchpad' ) );
		}

		if ( ! self::has_lookup() ) {
			return new WP_Error( 'no_key', __( 'ABN Lookup isn\'t set up yet.', 'kdna-launchpad' ) );
		}

		$cache_key = 'kdna_launchpad_abn_' . $digits;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			add_query_arg(
				array(
					'abn'      => $digits,
					'guid'     => self::get_guid(),
					'callback' => 'kdna',
				),
				self::API_URL
			),
			array( 'timeout' => 8 )
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'unavailable', __( 'ABN Lookup isn\'t answering right now.', 'kdna-launchpad' ) );
		}

		// The service answers as kdna({...}); take the JSON from inside the brackets.
		$body = trim( (string) wp_remote_retrieve_body( $response ) );
		$body = preg_replace( '/^[^(]*\(|\);?\s*$/', '', $body );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'unavailable', __( 'ABN Lookup isn\'t answering right now.', 'kdna-launchpad' ) );
		}

		if ( empty( $data['Abn'] ) ) {
			$message = isset( $data['Message'] ) ? (string) $data['Message'] : '';

			// A problem with the key is the site's problem, not the applicant's.
			if ( false !== stripos( $message, 'guid' ) ) {
				return new WP_Error( 'unavailable', __( 'ABN Lookup isn\'t answering right now.', 'kdna-launchpad' ) );
			}

			return new WP_Error( 'not_found', __( 'We couldn\'t find that ABN on the Australian Business Register. Please check it.', 'kdna-launchpad' ) );
		}

		$names = array();
		foreach ( (array) ( isset( $data['BusinessName'] ) ? $data['BusinessName'] : array() ) as $name ) {
			$name = self::tidy_name( (string) $name );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}

		$result = array(
			'abn'            => $digits,
			'status'         => 0 === strcasecmp( (string) ( isset( $data['AbnStatus'] ) ? $data['AbnStatus'] : '' ), 'Active' ) ? 'active' : 'cancelled',
			'entity_name'    => self::tidy_name( (string) ( isset( $data['EntityName'] ) ? $data['EntityName'] : '' ) ),
			'business_names' => array_values( array_unique( $names ) ),
			'state'          => sanitize_text_field( (string) ( isset( $data['AddressState'] ) ? $data['AddressState'] : '' ) ),
		);

		set_transient( $cache_key, $result, DAY_IN_SECONDS );

		return $result;
	}

	/**
	 * Tidies a name from the register: names in all capitals become normal capitalisation,
	 * for example "NGUYEN FAMILY HOLDINGS PTY LTD" becomes "Nguyen Family Holdings Pty Ltd".
	 * Names with mixed capitals are left exactly as registered.
	 *
	 * @param string $name Name from the register.
	 * @return string
	 */
	private static function tidy_name( $name ) {
		$name = trim( sanitize_text_field( $name ) );

		if ( '' === $name || strtoupper( $name ) !== $name ) {
			return $name;
		}

		$name = ucwords( strtolower( $name ), " \t-/(&'." );

		// Small words and common abbreviations, then a capital at the start.
		$name = preg_replace_callback(
			'/\b(And|Of|The|For)\b|\b(Abn|Acn|Nsw|Vic|Qld|Wa|Sa|Tas|Act|Nt)\b/',
			function ( $m ) {
				return ! empty( $m[1] ) ? strtolower( $m[1] ) : strtoupper( $m[2] );
			},
			$name
		);

		return ucfirst( $name );
	}

	/**
	 * A one-line summary of a lookup, for example "Brightside Bakery Pty Ltd, Active, NSW".
	 *
	 * @param array $result From lookup().
	 * @return string
	 */
	public static function summary( $result ) {
		$parts = array_filter(
			array(
				$result['entity_name'],
				'active' === $result['status'] ? __( 'Active', 'kdna-launchpad' ) : __( 'Cancelled', 'kdna-launchpad' ),
				$result['state'],
			)
		);

		return implode( ', ', $parts );
	}

	/*
	 * -----------------------------------------------------------------------
	 * On the form
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Loads the live ABN check whenever the Launchpad form is shown, however it's displayed
	 * (the Launchpad widget, an Elementor template, the shortcode or KDNA Forms' own widget).
	 */
	public static function enqueue() {
		$form_id = absint( kdna_launchpad_get_setting( 'form_id', 0 ) );
		$map     = (array) kdna_launchpad_get_setting( 'field_map', array() );

		$input_id = function ( $key ) use ( $form_id, $map ) {
			return empty( $map[ $key ] ) ? '' : 'input_' . $form_id . '_' . str_replace( '.', '_', (string) $map[ $key ] );
		};

		wp_enqueue_script( 'kdna-launchpad-abn', KDNA_LAUNCHPAD_URL . 'assets/js/kdna-launchpad-abn.js', array(), KDNA_LAUNCHPAD_VERSION, true );
		wp_enqueue_style( 'kdna-launchpad-abn', KDNA_LAUNCHPAD_URL . 'assets/css/kdna-launchpad-abn.css', array(), KDNA_LAUNCHPAD_VERSION );

		wp_localize_script(
			'kdna-launchpad-abn',
			'kdnaLaunchpadAbn',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::AJAX_ACTION ),
				'lookup'   => self::has_lookup(),
				'abn'      => $input_id( 'abn' ),
				'business' => $input_id( 'business_name' ),
				'trading'  => $input_id( 'trading_as' ),
				'words'    => (object) array_combine(
					array_map(
						function ( $field_id ) use ( $form_id ) {
							return 'input_' . $form_id . '_' . $field_id;
						},
						array_keys( KDNA_Launchpad_Form::word_limits() )
					),
					array_values( KDNA_Launchpad_Form::word_limits() )
				),
				'i18n'     => array(
					'enterAbn'  => __( 'Enter your ABN above first', 'kdna-launchpad' ),
					'looking'   => __( 'Looking up your business name…', 'kdna-launchpad' ),
					'typeName'  => __( 'Type your business name', 'kdna-launchpad' ),
					'checking'  => __( 'Checking your ABN…', 'kdna-launchpad' ),
					'invalid'   => __( 'That ABN doesn\'t look right. Please check the 11 digits.', 'kdna-launchpad' ),
					'valid'     => __( 'ABN looks right.', 'kdna-launchpad' ),
					'cancelled' => __( 'This ABN has been cancelled, so it can\'t be used to apply.', 'kdna-launchpad' ),
					'replace'   => __( 'Replace the business name you typed with the registered name?', 'kdna-launchpad' ),
					/* translators: 1: words used, 2: word limit. */
					'words'     => __( '%1$d of %2$d words', 'kdna-launchpad' ),
				),
			)
		);
	}

	/**
	 * Looks up an ABN for the form, as the applicant types.
	 */
	public static function ajax_lookup() {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		// A light limit per visitor, so the form can't be used as a free ABN search tool.
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'kdna_launchpad_abn_rate_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::HOURLY_LIMIT ) {
			wp_send_json_error(
				array(
					'code'    => 'unavailable',
					'message' => __( 'Too many lookups. Please type your business name.', 'kdna-launchpad' ),
				)
			);
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		$abn    = isset( $_POST['abn'] ) ? sanitize_text_field( wp_unslash( $_POST['abn'] ) ) : '';
		$result = self::lookup( $abn );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'status'         => $result['status'],
				'entity_name'    => $result['entity_name'],
				'business_names' => $result['business_names'],
				'summary'        => self::summary( $result ),
			)
		);
	}

	/**
	 * Checks the ABN when the form is submitted. An ABN that fails the checksum, isn't on the
	 * register or has been cancelled can't be submitted. If the lookup can't be reached, the
	 * checksum alone decides, so the form never stops working.
	 *
	 * @param bool   $is_valid Whether KDNA Forms found the field valid so far.
	 * @param string $value    The ABN as entered.
	 * @return true|string True, or the error message to show under the field.
	 */
	public static function validate_field( $is_valid, $value ) {
		if ( ! $is_valid || '' === trim( (string) $value ) ) {
			return true;
		}

		$result = self::lookup( $value );

		if ( is_wp_error( $result ) ) {
			return in_array( $result->get_error_code(), array( 'invalid', 'not_found' ), true ) ? $result->get_error_message() : true;
		}

		if ( 'active' !== $result['status'] ) {
			return __( 'This ABN has been cancelled, so it can\'t be used to apply.', 'kdna-launchpad' );
		}

		return true;
	}
}
