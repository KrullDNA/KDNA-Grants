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
 * - Refusing a submission: filter 'kdnaform_validation_{form_id}' (runs before saving).
 * - Reading an entry: KDNAAPI::get_entry( $entry_id ).
 * - Listing forms: KDNAAPI::get_forms(). Reading one form: KDNAAPI::get_form( $form_id ).
 * - Displaying a form: kdna_form() (the same as the [kdnaform] shortcode).
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

			if ( ! empty( $field->displayOnly ) || in_array( $type, self::DISPLAY_ONLY_TYPES, true ) ) {
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
