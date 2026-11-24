<?php
/**
 * Form: builds the complete Launchpad application form in KDNA Forms with one click,
 * matches its fields, and checks the word limits on the longer answers.
 *
 * KDNA Forms still does the form work (spam protection, secure uploads, entries and
 * the confirmation email). Launchpad just sets it all up, so nothing needs matching by hand.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Launchpad application form.
 */
class KDNA_Launchpad_Form {

	/**
	 * Hooks the word limits into the form. Called once KDNA Forms is available.
	 */
	public static function register() {
		// {launchpad_business_name}: the trading name if given, otherwise the business name.
		KDNA_Launchpad_Forms_Bridge::add_merge_tag(
			'{launchpad_business_name}',
			function ( $entry ) {
				$values = KDNA_Launchpad_Applications::mapped_values( $entry );
				return '' !== $values['trading_as'] ? $values['trading_as'] : $values['business_name'];
			}
		);

		$form_id = absint( kdna_launchpad_get_setting( 'form_id', 0 ) );

		if ( ! $form_id ) {
			return;
		}

		foreach ( self::word_limits() as $field_id => $limit ) {
			KDNA_Launchpad_Forms_Bridge::on_field_validation(
				$form_id,
				$field_id,
				function ( $is_valid, $value ) use ( $limit ) {
					if ( $is_valid && self::count_words( $value ) > $limit ) {
						return sprintf(
							/* translators: 1: word limit, 2: words used. */
							__( 'Please keep this to %1$d words or fewer. You\'ve used %2$d.', 'kdna-launchpad' ),
							$limit,
							self::count_words( $value )
						);
					}
					return true;
				}
			);
		}
	}

	/**
	 * Word limits for the longer answers, as field ID => maximum words.
	 *
	 * @return array
	 */
	public static function word_limits() {
		$limits = array();

		foreach ( (array) kdna_launchpad_get_setting( 'word_limits', array() ) as $field_id => $limit ) {
			if ( absint( $field_id ) && absint( $limit ) ) {
				$limits[ absint( $field_id ) ] = absint( $limit );
			}
		}

		return $limits;
	}

	/**
	 * Counts the words in an answer.
	 *
	 * @param string $text The answer.
	 * @return int
	 */
	public static function count_words( $text ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );

		return '' === $text ? 0 : count( preg_split( '/\s+/u', $text ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Building the form
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Creates the Launchpad form in KDNA Forms, then points Launchpad at it with every field matched.
	 *
	 * @param string $terms_url Address of the terms and conditions page, linked from the tick box.
	 * @return int|WP_Error The new form's ID.
	 */
	public static function create( $terms_url ) {
		$terms_url = esc_url_raw( $terms_url );
		$form_id   = KDNA_Launchpad_Forms_Bridge::create_form( self::definition( $terms_url ) );

		if ( is_wp_error( $form_id ) || ! $form_id ) {
			return is_wp_error( $form_id ) ? $form_id : new WP_Error( 'not_created', __( 'KDNA Forms couldn\'t create the form.', 'kdna-launchpad' ) );
		}

		$settings                = kdna_launchpad_get_settings();
		$settings['form_id']     = $form_id;
		$settings['terms_url']   = $terms_url;
		$settings['word_limits'] = array(
			14 => 250,
			16 => 200,
			17 => 200,
		);
		$settings['field_map']   = array(
			'first_name'    => '10.3',
			'last_name'     => '10.6',
			'email'         => '11',
			'business_name' => '3',
			'trading_as'    => '4',
			'industry'      => '5',
			'abn'           => '2',
			'staff'         => '6',
			'logo'          => '8',
		);

		update_option( KDNA_LAUNCHPAD_OPTION, $settings, false );

		return $form_id;
	}

	/**
	 * The whole form: questions in the agreed order, the applicant confirmation email and the thank-you message.
	 *
	 * Field IDs are fixed here, so the field matching and word limits in create() line up.
	 *
	 * @param string $terms_url Address of the terms page.
	 * @return array
	 */
	private static function definition( $terms_url ) {
		$choices = function ( $items ) {
			return array_map(
				function ( $item ) {
					return array(
						'text'  => $item,
						'value' => $item,
					);
				},
				$items
			);
		};

		$terms_link = $terms_url
			? '<a href="' . esc_url( $terms_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Read the KDNA Launchpad terms and conditions', 'kdna-launchpad' ) . '</a>'
			: esc_html__( 'Read the KDNA Launchpad terms and conditions', 'kdna-launchpad' );

		$fields = array(
			array(
				'id'    => 1,
				'type'  => 'section',
				'label' => __( 'Your business', 'kdna-launchpad' ),
			),
			array(
				'id'          => 2,
				'type'        => 'text',
				'label'       => __( 'ABN', 'kdna-launchpad' ),
				'isRequired'  => true,
				'maxLength'   => 14,
				'placeholder' => __( 'e.g. 51 824 753 556', 'kdna-launchpad' ),
				'description' => __( 'We\'ll look up your business on the Australian Business Register.', 'kdna-launchpad' ),
				'cssClass'    => 'kdna-launchpad-abn',
			),
			array(
				'id'          => 3,
				'type'        => 'text',
				'label'       => __( 'Business name', 'kdna-launchpad' ),
				'isRequired'  => true,
				'placeholder' => __( 'Enter your ABN above first', 'kdna-launchpad' ),
				'cssClass'    => 'kdna-launchpad-business-name',
			),
			array(
				'id'          => 4,
				'type'        => 'text',
				'label'       => __( 'Trading as (if applicable)', 'kdna-launchpad' ),
				'description' => __( 'If your business trades under a different name, add it here.', 'kdna-launchpad' ),
				'cssClass'    => 'kdna-launchpad-trading-as',
			),
			array(
				'id'          => 5,
				'type'        => 'select',
				'label'       => __( 'Industry', 'kdna-launchpad' ),
				'isRequired'  => true,
				'placeholder' => __( 'Choose your industry', 'kdna-launchpad' ),
				'choices'     => $choices(
					array(
						__( 'Hospitality and food', 'kdna-launchpad' ),
						__( 'Retail', 'kdna-launchpad' ),
						__( 'Health and wellness', 'kdna-launchpad' ),
						__( 'Trades and construction', 'kdna-launchpad' ),
						__( 'Professional services', 'kdna-launchpad' ),
						__( 'Creative and design', 'kdna-launchpad' ),
						__( 'Community and not-for-profit', 'kdna-launchpad' ),
						__( 'Other', 'kdna-launchpad' ),
					)
				),
			),
			array(
				'id'          => 6,
				'type'        => 'select',
				'label'       => __( 'Number of staff, including owners', 'kdna-launchpad' ),
				'isRequired'  => true,
				'placeholder' => __( 'Choose one', 'kdna-launchpad' ),
				'choices'     => $choices(
					array(
						__( '1 to 4', 'kdna-launchpad' ),
						__( '5 to 9', 'kdna-launchpad' ),
						__( '10 to 19', 'kdna-launchpad' ),
						__( '20 or more', 'kdna-launchpad' ),
					)
				),
			),
			array(
				'id'    => 7,
				'type'  => 'website',
				'label' => __( 'Current website, if you have one', 'kdna-launchpad' ),
			),
			array(
				'id'                => 8,
				'type'              => 'fileupload',
				'label'             => __( 'Upload your logo', 'kdna-launchpad' ),
				'isRequired'        => true,
				'allowedExtensions' => 'png,jpg,jpeg,svg,pdf',
				'maxFileSize'       => 5,
			),
			array(
				'id'    => 9,
				'type'  => 'section',
				'label' => __( 'About you', 'kdna-launchpad' ),
			),
			array(
				'id'         => 10,
				'type'       => 'name',
				'label'      => __( 'Your name', 'kdna-launchpad' ),
				'isRequired' => true,
				'nameFormat' => 'advanced',
				'inputs'     => array(
					array(
						'id'       => '10.2',
						'label'    => __( 'Prefix', 'kdna-launchpad' ),
						'isHidden' => true,
					),
					array(
						'id'    => '10.3',
						'label' => __( 'First', 'kdna-launchpad' ),
					),
					array(
						'id'       => '10.4',
						'label'    => __( 'Middle', 'kdna-launchpad' ),
						'isHidden' => true,
					),
					array(
						'id'    => '10.6',
						'label' => __( 'Last', 'kdna-launchpad' ),
					),
					array(
						'id'       => '10.8',
						'label'    => __( 'Suffix', 'kdna-launchpad' ),
						'isHidden' => true,
					),
				),
			),
			array(
				'id'         => 11,
				'type'       => 'email',
				'label'      => __( 'Email address', 'kdna-launchpad' ),
				'isRequired' => true,
			),
			array(
				'id'          => 12,
				'type'        => 'phone',
				'label'       => __( 'Phone number', 'kdna-launchpad' ),
				'phoneFormat' => 'international',
			),
			array(
				'id'    => 13,
				'type'  => 'section',
				'label' => __( 'Your story', 'kdna-launchpad' ),
			),
			array(
				'id'          => 14,
				'type'        => 'textarea',
				'label'       => __( 'Tell us about your business and how it started.', 'kdna-launchpad' ),
				'isRequired'  => true,
				'description' => __( 'Up to 250 words.', 'kdna-launchpad' ),
			),
			array(
				'id'    => 15,
				'type'  => 'section',
				'label' => __( 'Your goals', 'kdna-launchpad' ),
			),
			array(
				'id'          => 16,
				'type'        => 'textarea',
				'label'       => __( 'What do you want your new website to do for your business?', 'kdna-launchpad' ),
				'isRequired'  => true,
				'description' => __( 'Up to 200 words.', 'kdna-launchpad' ),
			),
			array(
				'id'          => 17,
				'type'        => 'textarea',
				'label'       => __( 'What difference would winning KDNA Launchpad make to your business?', 'kdna-launchpad' ),
				'isRequired'  => true,
				'description' => __( 'Up to 200 words.', 'kdna-launchpad' ),
			),
			array(
				'id'    => 18,
				'type'  => 'section',
				'label' => __( 'Your new website', 'kdna-launchpad' ),
			),
			array(
				'id'          => 19,
				'type'        => 'list',
				'label'       => __( 'Which pages would your website need?', 'kdna-launchpad' ),
				'isRequired'  => true,
				'maxRows'     => 5,
				'description' => __( 'Up to 5 pages, for example Home, About, Services, Gallery and Contact.', 'kdna-launchpad' ),
			),
			array(
				'id'         => 20,
				'type'       => 'radio',
				'label'      => __( 'Do you have the words for your website ready?', 'kdna-launchpad' ),
				'isRequired' => true,
				'choices'    => $choices( array( __( 'Yes', 'kdna-launchpad' ), __( 'Partly', 'kdna-launchpad' ), __( 'Not yet', 'kdna-launchpad' ) ) ),
			),
			array(
				'id'         => 21,
				'type'       => 'radio',
				'label'      => __( 'Do you have good-quality, high-resolution images you can use?', 'kdna-launchpad' ),
				'isRequired' => true,
				'choices'    => $choices( array( __( 'Yes', 'kdna-launchpad' ), __( 'Some', 'kdna-launchpad' ), __( 'Not yet', 'kdna-launchpad' ) ) ),
			),
			array(
				'id'         => 22,
				'type'       => 'radio',
				'label'      => __( 'Do you have your logo and brand colours?', 'kdna-launchpad' ),
				'isRequired' => true,
				'choices'    => $choices( array( __( 'Yes', 'kdna-launchpad' ), __( 'No', 'kdna-launchpad' ) ) ),
			),
			array(
				'id'      => 23,
				'type'    => 'radio',
				'label'   => __( 'Do you already have a domain name and hosting?', 'kdna-launchpad' ),
				'choices' => $choices( array( __( 'Yes', 'kdna-launchpad' ), __( 'Domain only', 'kdna-launchpad' ), __( 'Not yet', 'kdna-launchpad' ) ) ),
			),
			array(
				'id'    => 24,
				'type'  => 'textarea',
				'label' => __( 'Share up to 3 websites you love, and what you like about them.', 'kdna-launchpad' ),
			),
			array(
				'id'    => 25,
				'type'  => 'section',
				'label' => __( 'Finishing up', 'kdna-launchpad' ),
			),
			array(
				'id'          => 26,
				'type'        => 'select',
				'label'       => __( 'How did you hear about KDNA Launchpad?', 'kdna-launchpad' ),
				'placeholder' => __( 'Choose one', 'kdna-launchpad' ),
				'choices'     => $choices(
					array(
						__( 'Instagram', 'kdna-launchpad' ),
						__( 'Facebook', 'kdna-launchpad' ),
						__( 'LinkedIn', 'kdna-launchpad' ),
						__( 'Google search', 'kdna-launchpad' ),
						__( 'Word of mouth', 'kdna-launchpad' ),
						__( 'KDNA newsletter', 'kdna-launchpad' ),
						__( 'Other', 'kdna-launchpad' ),
					)
				),
			),
			array(
				'id'            => 27,
				'type'          => 'consent',
				'label'         => __( 'Terms and conditions', 'kdna-launchpad' ),
				'isRequired'    => true,
				'checkboxLabel' => __( 'I have read and agree to the KDNA Launchpad terms and conditions', 'kdna-launchpad' ),
				'description'   => $terms_link,
				'inputs'        => array(
					array(
						'id'    => '27.1',
						'label' => __( 'Consent', 'kdna-launchpad' ),
					),
					array(
						'id'       => '27.2',
						'label'    => __( 'Text', 'kdna-launchpad' ),
						'isHidden' => true,
					),
					array(
						'id'       => '27.3',
						'label'    => __( 'Description', 'kdna-launchpad' ),
						'isHidden' => true,
					),
				),
			),
			array(
				'id'      => 28,
				'type'    => 'checkbox',
				'label'   => __( 'Updates', 'kdna-launchpad' ),
				'choices' => $choices( array( __( 'Keep me updated about KDNA news and future Launchpad rounds', 'kdna-launchpad' ) ) ),
				'inputs'  => array(
					array(
						'id'    => '28.1',
						'label' => __( 'Keep me updated about KDNA news and future Launchpad rounds', 'kdna-launchpad' ),
					),
				),
			),
		);

		$confirmation_id = uniqid();
		$admin_id        = uniqid();
		$applicant_id    = uniqid();

		return array(
			'title'                => __( 'KDNA Launchpad application', 'kdna-launchpad' ),
			'description'          => '',
			'labelPlacement'       => 'top_label',
			'descriptionPlacement' => 'below',
			'button'               => array(
				'type' => 'text',
				'text' => __( 'Submit my application', 'kdna-launchpad' ),
			),
			'fields'               => $fields,
			'confirmations'        => array(
				$confirmation_id => array(
					'id'        => $confirmation_id,
					'name'      => __( 'Default Confirmation', 'kdna-launchpad' ),
					'isDefault' => true,
					'type'      => 'message',
					'message'   => __( 'Thank you for applying for KDNA Launchpad! We\'ve emailed you a copy of your application. Winners are announced by the date shown on this page.', 'kdna-launchpad' ),
				),
			),
			'notifications'        => array(
				$admin_id     => array(
					'id'       => $admin_id,
					'isActive' => true,
					'name'     => __( 'New application (to KDNA)', 'kdna-launchpad' ),
					'event'    => 'form_submission',
					'toType'   => 'email',
					'to'       => '{admin_email}',
					'subject'  => __( 'New KDNA Launchpad application: {launchpad_business_name}', 'kdna-launchpad' ),
					'message'  => '{all_fields}',
				),
				$applicant_id => array(
					'id'       => $applicant_id,
					'isActive' => true,
					'name'     => __( 'Application received (to applicant)', 'kdna-launchpad' ),
					'event'    => 'form_submission',
					'toType'   => 'field',
					'to'       => '11',
					'replyTo'  => '{admin_email}',
					'subject'  => __( 'We\'ve received your KDNA Launchpad application', 'kdna-launchpad' ),
					'message'  => '<p>' . __( 'Hi {Your name (First):10.3},', 'kdna-launchpad' ) . '</p>'
						. '<p>' . __( 'Thank you for applying for KDNA Launchpad. We\'ve received your application for {launchpad_business_name}.', 'kdna-launchpad' ) . '</p>'
						. '<p>' . __( 'Applications are judged after the round closes, and every applicant will hear from us by the winner announcement date shown on our website.', 'kdna-launchpad' ) . '</p>'
						. '<p>' . __( 'Thanks again,', 'kdna-launchpad' ) . '<br />' . __( 'Nick Krull, KDNA', 'kdna-launchpad' ) . '</p>'
						. '<hr />{all_fields}',
				),
			),
		);
	}
}
