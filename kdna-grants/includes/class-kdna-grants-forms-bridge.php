<?php
/**
 * Forms bridge: the only file in KDNA Grants that talks to KDNA Forms.
 *
 * If KDNA Forms ever changes how it works, this is the only file that needs
 * updating. Every other part of KDNA Grants asks this class instead of
 * calling KDNA Forms directly.
 *
 * Confirmed against KDNA Forms 3.7.1:
 * - New submissions: action 'kdnaform_after_submission_{form_id}' ($entry, $form).
 * - Form about to be shown: filter 'kdnaform_pre_render_{form_id}'.
 * - Refusing a submission: filter 'kdnaform_validation_{form_id}' (runs before saving),
 *   with the error heading changed through 'kdnaform_validation_message_{form_id}'.
 * - Test entries: KDNAAPI::add_entry() and KDNAAPI::delete_entry().
 * - Showing answers: KDNAFormsModel::get_lead_field_value() and KDNACommon::get_lead_field_display(),
 *   the same as KDNA Forms' own Entries screen.
 * - Reading an entry: KDNAAPI::get_entry( $entry_id ).
 * - Listing forms: KDNAAPI::get_forms(). Reading one form: KDNAAPI::get_form( $form_id ).
 * - Displaying a form: kdna_form() (the same as the [kdnaform] shortcode), in render_form().
 * - Files: secure download links from KDNA_Field_FileUpload::get_download_url().
 * - The applicant confirmation email is a Notification set up on the form in KDNA Forms.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps every KDNA Forms function KDNA Grants uses.
 */
class KDNA_Grants_Forms_Bridge {

	/**
	 * KDNA Forms field types that only display content and never hold an answer.
	 *
	 * @var string[]
	 */
	const DISPLAY_ONLY_TYPES = array( 'html', 'section', 'page', 'captcha', 'submit' );

	/*
	 * -----------------------------------------------------------------------
	 * Is KDNA Forms there?
	 * -----------------------------------------------------------------------
	 */

	/**
	 * True when KDNA Forms is active and recent enough for KDNA Grants to use.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return self::is_active() && self::is_supported_version();
	}

	/**
	 * True when KDNA Forms is active and its main classes have loaded.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return class_exists( 'KDNAForms' ) && class_exists( 'KDNAAPI' ) && function_exists( 'kdna_form' );
	}

	/**
	 * The installed KDNA Forms version, or an empty string if it isn't active.
	 *
	 * @return string
	 */
	public static function get_version() {
		if ( ! class_exists( 'KDNAForms' ) || ! isset( KDNAForms::$version ) ) {
			return '';
		}

		return (string) KDNAForms::$version;
	}

	/**
	 * True when the installed KDNA Forms is at least the version KDNA Grants was checked against.
	 *
	 * @return bool
	 */
	public static function is_supported_version() {
		$version = self::get_version();

		return '' !== $version && version_compare( $version, KDNA_GRANTS_MIN_FORMS_VERSION, '>=' );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Listening to the grant form
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Runs a callback whenever the form is about to be shown on a page.
	 *
	 * @param int      $form_id  The KDNA Forms form ID.
	 * @param callable $callback Called with no arguments.
	 */
	public static function on_form_display( $form_id, $callback ) {
		add_filter(
			'kdnaform_pre_render_' . absint( $form_id ),
			function ( $form ) use ( $callback ) {
				call_user_func( $callback );
				return $form;
			}
		);
	}

	/**
	 * Lets a callback refuse a submission before KDNA Forms saves it.
	 *
	 * The callback receives true or false (whether the submission is valid so far)
	 * and returns true or false. Returning false stops the entry being saved.
	 *
	 * @param int      $form_id  The KDNA Forms form ID.
	 * @param callable $callback Called with ( bool $is_valid ), returns bool.
	 */
	public static function on_validation( $form_id, $callback ) {
		add_filter(
			'kdnaform_validation_' . absint( $form_id ),
			function ( $validation_result ) use ( $callback ) {
				if ( ! is_array( $validation_result ) ) {
					return $validation_result;
				}

				$is_valid                      = ! empty( $validation_result['is_valid'] );
				$validation_result['is_valid'] = (bool) call_user_func( $callback, $is_valid );

				return $validation_result;
			},
			50
		);
	}

	/**
	 * Lets a callback change the error heading KDNA Forms shows when a submission is refused.
	 *
	 * @param int      $form_id  The KDNA Forms form ID.
	 * @param callable $callback Called with ( string $markup ), returns the markup to show.
	 */
	public static function on_validation_message( $form_id, $callback ) {
		add_filter(
			'kdnaform_validation_message_' . absint( $form_id ),
			function ( $markup ) use ( $callback ) {
				return call_user_func( $callback, $markup );
			},
			50
		);
	}

	/**
	 * Runs a callback after KDNA Forms has saved a new submission.
	 *
	 * @param int      $form_id  The KDNA Forms form ID.
	 * @param callable $callback Called with ( array $entry, array $form ).
	 */
	public static function on_submission( $form_id, $callback ) {
		add_action( 'kdnaform_after_submission_' . absint( $form_id ), $callback, 10, 2 );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Entries
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Loads a KDNA Forms entry by its ID, or returns false.
	 *
	 * @param int $entry_id The KDNA Forms entry ID.
	 * @return array|false
	 */
	public static function get_entry( $entry_id ) {
		$entry_id = absint( $entry_id );

		if ( ! $entry_id || ! self::is_available() ) {
			return false;
		}

		$entry = KDNAAPI::get_entry( $entry_id );

		return is_array( $entry ) ? $entry : false;
	}

	/**
	 * True when KDNA Forms marked the entry as spam.
	 *
	 * @param array $entry The KDNA Forms entry.
	 * @return bool
	 */
	public static function entry_is_spam( $entry ) {
		return isset( $entry['status'] ) && 'spam' === $entry['status'];
	}

	/**
	 * Reads one answer from an entry as plain text.
	 *
	 * Works for single fields ("4"), parts of a field ("1.3" for a first name),
	 * and whole multi-part fields ("1" for a full name), whose parts are joined with spaces.
	 *
	 * @param array      $entry    The KDNA Forms entry.
	 * @param string     $field_id Field or part ID.
	 * @param array|null $form     The form (not needed, kept for future use).
	 * @return string
	 */
	public static function get_entry_value( $entry, $field_id, $form = null ) {
		$field_id = (string) $field_id;

		if ( '' === $field_id || ! is_array( $entry ) ) {
			return '';
		}

		if ( isset( $entry[ $field_id ] ) && '' !== (string) $entry[ $field_id ] ) {
			return trim( wp_strip_all_tags( (string) $entry[ $field_id ] ) );
		}

		// A whole multi-part field: join its parts in order.
		if ( false === strpos( $field_id, '.' ) ) {
			$parts = array();
			foreach ( $entry as $key => $value ) {
				if ( 0 === strpos( (string) $key, $field_id . '.' ) && is_scalar( $value ) && '' !== trim( (string) $value ) ) {
					$parts[ (string) $key ] = trim( (string) $value );
				}
			}
			uksort( $parts, 'strnatcmp' );

			return trim( wp_strip_all_tags( implode( ' ', $parts ) ) );
		}

		return '';
	}

	/**
	 * The grant form's HTML, using KDNA Forms' own display function (the same as its [kdnaform] shortcode),
	 * so it keeps all its KDNA Forms styling and scripts.
	 *
	 * @param int  $form_id           The KDNA Forms form ID.
	 * @param bool $show_title        Show the form title.
	 * @param bool $show_description  Show the form description.
	 * @param bool $ajax              Submit without reloading the page.
	 * @return string
	 */
	public static function render_form( $form_id, $show_title = false, $show_description = false, $ajax = true ) {
		if ( ! self::get_form( $form_id ) ) {
			return '';
		}

		return (string) kdna_form( absint( $form_id ), (bool) $show_title, (bool) $show_description, false, null, (bool) $ajax, 0, false );
	}

	/**
	 * Every answer in an entry, in the order of the form, ready to show on the judging screen.
	 *
	 * Uses KDNA Forms' own display formatting, the same as its Entries screen, so
	 * links, lists and uploaded files appear as they do there. Section headings are
	 * included; empty answers are left out.
	 *
	 * @param array $entry The KDNA Forms entry.
	 * @return array List of array( 'type' => 'section' or 'answer', 'label' => text, 'html' => formatted answer ).
	 */
	public static function get_entry_answers( $entry ) {
		$form = is_array( $entry ) && ! empty( $entry['form_id'] ) ? self::get_form( $entry['form_id'] ) : false;

		if ( ! $form || empty( $form['fields'] ) ) {
			return array();
		}

		$answers = array();

		foreach ( $form['fields'] as $field ) {
			if ( ! is_object( $field ) || ! method_exists( $field, 'get_input_type' ) ) {
				continue;
			}

			$type = $field->get_input_type();

			if ( 'section' === $type ) {
				$answers[] = array(
					'type'  => 'section',
					'label' => self::field_label( $field ),
					'html'  => '',
				);
				continue;
			}

			if ( in_array( $type, array( 'captcha', 'html', 'password', 'page', 'submit' ), true ) ) {
				continue;
			}

			$value   = KDNAFormsModel::get_lead_field_value( $entry, $field );
			$display = KDNACommon::get_lead_field_display( $field, $value, $entry );
			$display = apply_filters( 'kdnaform_entry_field_value', $display, $field, $entry, $form );

			if ( '' === trim( wp_strip_all_tags( (string) $display ) ) && false === strpos( (string) $display, '<img' ) ) {
				continue;
			}

			$answers[] = array(
				'type'  => 'answer',
				'label' => self::field_label( $field ),
				'html'  => (string) $display,
			);
		}

		// Drop section headings with no answers under them.
		$tidy = array();
		foreach ( $answers as $index => $answer ) {
			$next = isset( $answers[ $index + 1 ] ) ? $answers[ $index + 1 ] : null;
			if ( 'section' === $answer['type'] && ( ! $next || 'section' === $next['type'] ) ) {
				continue;
			}
			$tidy[] = $answer;
		}

		return $tidy;
	}

	/**
	 * Secure links to the files uploaded in a file upload field, through KDNA Forms' own file handling.
	 *
	 * The real file location is never exposed: KDNA Forms checks a security token on each link.
	 *
	 * @param array  $entry    The KDNA Forms entry.
	 * @param string $field_id The file upload field ID.
	 * @return string[] Links, one per file.
	 */
	public static function get_file_urls( $entry, $field_id ) {
		$form = is_array( $entry ) && ! empty( $entry['form_id'] ) ? self::get_form( $entry['form_id'] ) : false;

		if ( ! $form || '' === (string) $field_id || empty( $entry[ $field_id ] ) ) {
			return array();
		}

		$field = KDNAAPI::get_field( $form, $field_id );
		if ( ! $field || ! method_exists( $field, 'get_download_url' ) ) {
			return array();
		}

		$raw   = (string) $entry[ $field_id ];
		$files = json_decode( $raw, true );
		$files = is_array( $files ) ? $files : array( $raw );
		$urls  = array();

		foreach ( $files as $file ) {
			if ( is_string( $file ) && '' !== $file ) {
				$urls[] = $field->get_download_url( $file, false, absint( $entry['id'] ) );
			}
		}

		return $urls;
	}

	/**
	 * Link to an entry on KDNA Forms' own Entries screen.
	 *
	 * @param array $entry The KDNA Forms entry.
	 * @return string
	 */
	public static function get_entry_url( $entry ) {
		return admin_url( 'admin.php?page=kdna_entries&view=entry&id=' . absint( $entry['form_id'] ) . '&lid=' . absint( $entry['id'] ) );
	}

	/**
	 * Saves an entry straight into KDNA Forms without sending notifications. Used by the test tool only.
	 *
	 * @param int   $form_id The KDNA Forms form ID.
	 * @param array $values  Field or part ID => value.
	 * @return int The new entry ID, or 0.
	 */
	public static function add_test_entry( $form_id, $values ) {
		if ( ! self::get_form( $form_id ) ) {
			return 0;
		}

		$entry            = array_map( 'strval', $values );
		$entry['form_id'] = absint( $form_id );

		$entry_id = KDNAAPI::add_entry( $entry );

		return is_wp_error( $entry_id ) ? 0 : absint( $entry_id );
	}

	/**
	 * Permanently deletes a KDNA Forms entry. Used by the test tool only, for entries it created.
	 *
	 * @param int $entry_id The KDNA Forms entry ID.
	 */
	public static function delete_test_entry( $entry_id ) {
		if ( absint( $entry_id ) && self::is_available() ) {
			KDNAAPI::delete_entry( absint( $entry_id ) );
		}
	}

	/*
	 * -----------------------------------------------------------------------
	 * Forms and fields
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Lists every active KDNA Forms form, as form ID => title, sorted by title.
	 *
	 * @return array
	 */
	public static function get_forms_list() {
		if ( ! self::is_available() ) {
			return array();
		}

		$list  = array();
		$forms = KDNAAPI::get_forms( true, false, 'title', 'ASC' );

		foreach ( (array) $forms as $form ) {
			if ( empty( $form['id'] ) ) {
				continue;
			}
			$list[ absint( $form['id'] ) ] = isset( $form['title'] ) ? (string) $form['title'] : '';
		}

		return $list;
	}

	/**
	 * Loads one KDNA Forms form, or returns false if it doesn't exist.
	 *
	 * @param int $form_id The KDNA Forms form ID.
	 * @return array|false
	 */
	public static function get_form( $form_id ) {
		$form_id = absint( $form_id );

		if ( ! $form_id || ! self::is_available() ) {
			return false;
		}

		$form = KDNAAPI::get_form( $form_id );

		return is_array( $form ) ? $form : false;
	}

	/**
	 * URL of the KDNA Forms editor for a form, for the "Edit this form" link.
	 *
	 * @param int $form_id The KDNA Forms form ID.
	 * @return string
	 */
	public static function get_form_edit_url( $form_id ) {
		return admin_url( 'admin.php?page=kdna_edit_forms&id=' . absint( $form_id ) );
	}

	/**
	 * URL of a form's notification settings in KDNA Forms, where the confirmation email is set up.
	 *
	 * @param int $form_id The KDNA Forms form ID.
	 * @return string
	 */
	public static function get_form_notifications_url( $form_id ) {
		return admin_url( 'admin.php?page=kdna_edit_forms&view=settings&subview=notification&id=' . absint( $form_id ) );
	}

	/**
	 * True when the form has an active notification sent to the applicant when the form is submitted.
	 *
	 * The applicant confirmation email is sent by KDNA Forms, not KDNA Grants, so this is
	 * only used to remind Nick to set it up. A notification counts if it is sent to a form
	 * field, or to an email address written as a merge tag (other than {admin_email}).
	 *
	 * @param int $form_id The KDNA Forms form ID.
	 * @return bool
	 */
	public static function has_applicant_notification( $form_id ) {
		$form = self::get_form( $form_id );

		if ( ! $form || empty( $form['notifications'] ) || ! is_array( $form['notifications'] ) ) {
			return false;
		}

		foreach ( $form['notifications'] as $notification ) {
			if ( ! is_array( $notification ) ) {
				continue;
			}

			// KDNA Forms treats a notification with no isActive value as active.
			if ( isset( $notification['isActive'] ) && ! $notification['isActive'] ) {
				continue;
			}

			$event = isset( $notification['event'] ) ? $notification['event'] : 'form_submission';
			if ( 'form_submission' !== $event ) {
				continue;
			}

			$to_type = isset( $notification['toType'] ) ? $notification['toType'] : '';
			$to      = isset( $notification['to'] ) ? (string) $notification['to'] : '';

			if ( 'field' === $to_type && '' !== $to ) {
				return true;
			}

			if ( 'email' === $to_type && false !== strpos( $to, '{' ) && false === strpos( $to, '{admin_email}' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Lists the fields of a form that can be matched to KDNA Grants fields.
	 *
	 * Fields made of several parts (such as Name, with First and Last) are listed
	 * once per part, for example "Name (First)", with an ID like "1.3". This works
	 * the same way as KDNA Forms' own field mapping screens.
	 *
	 * @param int $form_id The KDNA Forms form ID.
	 * @return array List of array( 'value' => field or input ID, 'label' => text, 'type' => field type ).
	 */
	public static function get_mappable_fields( $form_id ) {
		$form = self::get_form( $form_id );

		if ( ! $form || empty( $form['fields'] ) ) {
			return array();
		}

		$choices = array();

		foreach ( $form['fields'] as $field ) {
			if ( ! is_object( $field ) || ! method_exists( $field, 'get_input_type' ) ) {
				continue;
			}

			$type = (string) $field->get_input_type();

			if ( ! empty( $field->displayOnly ) || in_array( $type, self::DISPLAY_ONLY_TYPES, true ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- KDNA Forms property name.
				continue;
			}

			$inputs = method_exists( $field, 'get_entry_inputs' ) ? $field->get_entry_inputs() : null;

			// Fields made of several parts: list the whole field first where that makes sense, then each visible part.
			if ( is_array( $inputs ) && ! empty( $inputs ) ) {
				if ( in_array( $type, array( 'name', 'address', 'checkbox' ), true ) ) {
					$choices[] = array(
						'value' => (string) $field->id,
						'label' => self::field_label( $field ) . ' (' . __( 'Full', 'kdna-grants' ) . ')',
						'type'  => $type,
					);
				}

				foreach ( $inputs as $input ) {
					if ( empty( $input['id'] ) || ! empty( $input['isHidden'] ) ) {
						continue;
					}
					$choices[] = array(
						'value' => (string) $input['id'],
						'label' => self::field_label( $field, $input['id'] ),
						'type'  => $type,
					);
				}

				continue;
			}

			$choices[] = array(
				'value' => (string) $field->id,
				'label' => self::field_label( $field ),
				'type'  => $type,
			);
		}

		return $choices;
	}

	/**
	 * The mappable fields of every active form, keyed by form ID. Used by the Settings screen
	 * so the field dropdowns can update straight away when a different form is picked.
	 *
	 * @return array
	 */
	public static function get_all_mappable_fields() {
		$all = array();

		foreach ( array_keys( self::get_forms_list() ) as $form_id ) {
			$all[ $form_id ] = self::get_mappable_fields( $form_id );
		}

		return $all;
	}

	/**
	 * A field's label as plain text, using KDNA Forms' own labelling where available.
	 *
	 * @param object     $field    The KDNA Forms field object.
	 * @param string|int $input_id Optional part ID, for example "1.3".
	 * @return string
	 */
	private static function field_label( $field, $input_id = 0 ) {
		$label = '';

		if ( class_exists( 'KDNACommon' ) && method_exists( 'KDNACommon', 'get_label' ) ) {
			$label = KDNACommon::get_label( $field, $input_id );
		}

		if ( '' === trim( (string) $label ) ) {
			$label = ! empty( $field->label ) ? $field->label : sprintf( /* translators: %s: field ID. */ __( 'Field %s', 'kdna-grants' ), $field->id );
		}

		return trim( wp_strip_all_tags( (string) $label ) );
	}
}
