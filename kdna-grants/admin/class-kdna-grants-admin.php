<?php
/**
 * Admin: the KDNA Launchpad menu, the KDNA Forms dependency notice and the Settings screen.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything KDNA Launchpad adds to the WordPress admin.
 */
class KDNA_Grants_Admin {

	/**
	 * Page slug of the Applications screen (the main KDNA Launchpad page).
	 */
	const APPLICATIONS_PAGE = 'kdna-grants';

	/**
	 * Page slug of the Rounds screen.
	 */
	const ROUNDS_PAGE = 'kdna-grants-rounds';

	/**
	 * Page slug of the Claude's picks screen.
	 */
	const PICKS_PAGE = 'kdna-grants-picks';

	/**
	 * Page slug of the Offers screen.
	 */
	const OFFERS_PAGE = 'kdna-grants-offers';

	/**
	 * Page slug of the Settings screen.
	 */
	const SETTINGS_PAGE = 'kdna-grants-settings';

	/**
	 * Action name used by the Settings form (also used for its nonce).
	 */
	const SAVE_ACTION = 'kdna_grants_save_settings';

	/**
	 * The hook suffixes WordPress gives our screens, used to load scripts only there.
	 *
	 * @var string[]
	 */
	private $screen_hooks = array();

	/**
	 * The Rounds screen.
	 *
	 * @var KDNA_Grants_Admin_Rounds
	 */
	private $rounds;

	/**
	 * The Applications screen.
	 *
	 * @var KDNA_Grants_Admin_Applications
	 */
	private $applications;

	/**
	 * The Claude's picks screen.
	 *
	 * @var KDNA_Grants_Admin_Picks
	 */
	private $picks;

	/**
	 * The Offers screen.
	 *
	 * @var KDNA_Grants_Admin_Offers
	 */
	private $offers;

	/**
	 * The dashboard email window.
	 *
	 * @var KDNA_Grants_Admin_Contact
	 */
	private $contact;

	/**
	 * Hooks into WordPress. If KDNA Forms isn't available, only the notice is shown.
	 */
	public function register() {
		if ( ! KDNA_Grants_Forms_Bridge::is_available() ) {
			add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
			return;
		}

		$this->rounds       = new KDNA_Grants_Admin_Rounds();
		$this->applications = new KDNA_Grants_Admin_Applications();
		$this->picks        = new KDNA_Grants_Admin_Picks();
		$this->offers       = new KDNA_Grants_Admin_Offers();
		$this->contact      = new KDNA_Grants_Admin_Contact();
		$this->rounds->register();
		$this->applications->register();
		$this->picks->register();
		$this->offers->register();
		$this->contact->register();

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_kdna_grants_test_email', array( $this, 'ajax_test_email' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KDNA_GRANTS_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * KDNA Forms dependency notice
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Explains that KDNA Launchpad needs KDNA Forms. Shown only to people who can manage plugins.
	 */
	public function dependency_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( KDNA_Grants_Forms_Bridge::is_active() ) {
			$message = sprintf(
				/* translators: 1: installed KDNA Forms version, 2: required version. */
				__( 'KDNA Launchpad needs KDNA Forms %2$s or newer. This site has version %1$s. Please update KDNA Forms. Until then, KDNA Launchpad is paused and the grant form works as a normal KDNA Forms form.', 'kdna-grants' ),
				KDNA_Grants_Forms_Bridge::get_version(),
				KDNA_GRANTS_MIN_FORMS_VERSION
			);
		} else {
			$message = __( 'KDNA Launchpad needs the KDNA Forms plugin to collect applications. Please install and activate KDNA Forms. Until then, KDNA Launchpad is paused and does nothing.', 'kdna-grants' );
		}
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'KDNA Launchpad', 'kdna-grants' ); ?>:</strong>
				<?php echo esc_html( $message ); ?>
				<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Go to Plugins', 'kdna-grants' ); ?></a>
			</p>
		</div>
		<?php
	}

	/*
	 * -----------------------------------------------------------------------
	 * Menu and assets
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Adds the KDNA Launchpad menu: Applications, Rounds and Settings.
	 *
	 * Applications needs the judge capability, so it could later be given to a
	 * guest judge. Rounds and Settings are for administrators only.
	 */
	public function add_menu() {
		add_menu_page(
			__( 'KDNA Launchpad', 'kdna-grants' ),
			__( 'Launchpad', 'kdna-grants' ),
			KDNA_GRANTS_CAP,
			self::APPLICATIONS_PAGE,
			array( $this->applications, 'render' ),
			'dashicons-awards',
			58
		);

		$this->screen_hooks[] = add_submenu_page(
			self::APPLICATIONS_PAGE,
			__( 'Applications', 'kdna-grants' ),
			__( 'Applications', 'kdna-grants' ),
			KDNA_GRANTS_CAP,
			self::APPLICATIONS_PAGE,
			array( $this->applications, 'render' )
		);

		$this->screen_hooks[] = add_submenu_page(
			self::APPLICATIONS_PAGE,
			__( 'Claude\'s picks', 'kdna-grants' ),
			__( 'Claude\'s picks', 'kdna-grants' ),
			KDNA_GRANTS_CAP,
			self::PICKS_PAGE,
			array( $this->picks, 'render' )
		);

		$this->screen_hooks[] = add_submenu_page(
			self::APPLICATIONS_PAGE,
			__( 'Offers', 'kdna-grants' ),
			__( 'Offers', 'kdna-grants' ),
			'manage_options',
			self::OFFERS_PAGE,
			array( $this->offers, 'render' )
		);

		$this->screen_hooks[] = add_submenu_page(
			self::APPLICATIONS_PAGE,
			__( 'Rounds', 'kdna-grants' ),
			__( 'Rounds', 'kdna-grants' ),
			'manage_options',
			self::ROUNDS_PAGE,
			array( $this->rounds, 'render' )
		);

		$this->screen_hooks[] = add_submenu_page(
			self::APPLICATIONS_PAGE,
			__( 'Launchpad Settings', 'kdna-grants' ),
			__( 'Settings', 'kdna-grants' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Loads the admin styles and script, only on KDNA Launchpad screens.
	 *
	 * @param string $hook The current admin screen's hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array_filter( $this->screen_hooks ), true ) ) {
			return;
		}

		// Every admin screen view stays in step with the dates.
		KDNA_Grants_Rounds::sync_statuses();

		wp_enqueue_style( 'kdna-grants-admin', KDNA_GRANTS_URL . 'admin/assets/admin.css', array(), KDNA_GRANTS_VERSION );
		wp_enqueue_script( 'kdna-grants-admin', KDNA_GRANTS_URL . 'admin/assets/admin.js', array(), KDNA_GRANTS_VERSION, true );

		$targets = array();
		foreach ( KDNA_Grants_Install::mappable_fields() as $key => $def ) {
			$targets[ $key ] = array( 'types' => $def['types'] );
		}

		$is_settings = isset( $_GET['page'] ) && self::SETTINGS_PAGE === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.

		wp_localize_script(
			'kdna-grants-admin',
			'kdnaGrantsSettings',
			array(
				'fields'       => $is_settings ? KDNA_Grants_Forms_Bridge::get_all_mappable_fields() : array(),
				'targets'      => $targets,
				'editUrl'      => admin_url( 'admin.php?page=kdna_edit_forms&id=' ),
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'scoreNonce'   => wp_create_nonce( KDNA_Grants_Admin_Applications::SAVE_SCORE_ACTION ),
				'testNonce'    => wp_create_nonce( 'kdna_grants_test_email' ),
				'contactNonce' => wp_create_nonce( KDNA_Grants_Admin_Contact::NONCE ),
				'assessNonce'  => wp_create_nonce( KDNA_Grants_Admin_Picks::ASSESS_ACTION ),
				'i18n'         => array(
					'saving'             => __( 'Saving…', 'kdna-grants' ),
					'saved'              => __( 'Saved', 'kdna-grants' ),
					'saveFailed'         => __( 'Not saved. Please try again.', 'kdna-grants' ),
					'sending'            => __( 'Sending…', 'kdna-grants' ),
					'confirmReassess'    => __( 'Ask Claude to read every application in this round again? This replaces its current notes and costs a few dollars.', 'kdna-grants' ),
					'confirmDecline'     => __( 'Mark this offer as declined? The client won\'t get another offer if they apply again.', 'kdna-grants' ),
					'confirmUndo'        => __( 'Undo this? The offer goes back to active (or expired, if its date has passed).', 'kdna-grants' ),
					'confirmCloseDraft'  => __( 'Close without sending? Your message will be lost.', 'kdna-grants' ),
					'drafting'           => __( 'Claude is writing a draft. This can take up to a minute…', 'kdna-grants' ),
					'assessing'          => __( 'Claude is reading this application. This can take up to a minute…', 'kdna-grants' ),
					'loading'            => __( 'Loading…', 'kdna-grants' ),
					'sent'               => __( 'Sent', 'kdna-grants' ),
					'confirmReopen'      => __( 'Reopen judging? Scores can be changed again and a new winner chosen. Emails already sent can\'t be unsent, and if you choose a winner again, the winner and unsuccessful emails are sent again. This is recorded in the round history.', 'kdna-grants' ),
					'reasonNeeded'       => __( 'Please give a short reason.', 'kdna-grants' ),
					'removeCriterion'    => __( 'Remove', 'kdna-grants' ),
					'newCriterion'       => __( 'New criterion', 'kdna-grants' ),
					'choose'             => __( 'Choose a field', 'kdna-grants' ),
					'noFields'           => __( 'No suitable fields in this form', 'kdna-grants' ),
					'confirmDeleteTest'  => __( 'Delete the test rounds, their sample applications and the sample KDNA Forms entries?', 'kdna-grants' ),
					'confirmDeleteRound' => __( 'Delete this round? This can\'t be undone.', 'kdna-grants' ),
					'confirmUninstall'   => __( 'Are you sure? With this ticked, deleting KDNA Launchpad from the Plugins screen permanently removes every round, application, score and note. KDNA Forms entries are not affected.', 'kdna-grants' ),
				),
			)
		);
	}

	/**
	 * Adds a "Settings" link under KDNA Launchpad on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::SETTINGS_PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'kdna-grants' ) . '</a>' );

		return $links;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Settings screen
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Shows the Settings screen.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this page.', 'kdna-grants' ) );
		}

		$settings   = kdna_grants_get_settings();
		$forms      = KDNA_Grants_Forms_Bridge::get_forms_list();
		$form_id    = absint( $settings['form_id'] );
		$form_found = $form_id && isset( $forms[ $form_id ] );
		$fields     = $form_found ? KDNA_Grants_Forms_Bridge::get_mappable_fields( $form_id ) : array();
		$targets    = KDNA_Grants_Install::mappable_fields();
		$warnings   = $this->get_settings_warnings( $settings, $forms, $fields );
		$saved      = isset( $_GET['kdna_grants_saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.

		include KDNA_GRANTS_PATH . 'admin/views/settings.php';
	}

	/**
	 * Saves the Settings screen. Only the settings on this screen are changed; all others are kept.
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change these settings.', 'kdna-grants' ) );
		}

		check_admin_referer( self::SAVE_ACTION );

		$settings = kdna_grants_get_settings();
		$forms    = KDNA_Grants_Forms_Bridge::get_forms_list();

		// The application form must be one of the active KDNA Forms forms.
		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		if ( ! isset( $forms[ $form_id ] ) ) {
			$form_id = 0;
		}
		$settings['form_id'] = $form_id;

		// Each mapped field must exist in that form and be a suitable type.
		$posted_map = isset( $_POST['field_map'] ) && is_array( $_POST['field_map'] )
			? map_deep( wp_unslash( $_POST['field_map'] ), 'sanitize_text_field' )
			: array();

		$allowed = array();
		foreach ( KDNA_Grants_Forms_Bridge::get_mappable_fields( $form_id ) as $choice ) {
			$allowed[ $choice['value'] ] = $choice['type'];
		}

		$field_map = array();
		foreach ( KDNA_Grants_Install::mappable_fields() as $key => $def ) {
			$value = isset( $posted_map[ $key ] ) ? (string) $posted_map[ $key ] : '';

			$is_valid = isset( $allowed[ $value ] )
				&& ( empty( $def['types'] ) || in_array( $allowed[ $value ], $def['types'], true ) );

			$field_map[ $key ] = $is_valid ? $value : '';
		}
		$settings['field_map'] = $field_map;

		// Rounds and eligibility.
		$gap                          = isset( $_POST['round_gap_months'] ) ? absint( wp_unslash( $_POST['round_gap_months'] ) ) : 2;
		$settings['round_gap_months'] = min( 12, max( 1, $gap ) );

		$limit                   = isset( $_POST['staff_limit'] ) ? absint( wp_unslash( $_POST['staff_limit'] ) ) : 20;
		$settings['staff_limit'] = min( 100000, max( 1, $limit ) );

		$clients = isset( $_POST['existing_clients'] ) ? sanitize_textarea_field( wp_unslash( $_POST['existing_clients'] ) ) : '';
		$clients = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $clients ) ), 'strlen' );

		$settings['existing_clients'] = implode( "\n", $clients );

		// Judging criteria and tie-break.
		$posted_criteria                = isset( $_POST['criteria'] ) && is_array( $_POST['criteria'] ) ? wp_unslash( $_POST['criteria'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised in sanitize_criteria().
		$settings['criteria']           = $this->sanitize_criteria( $posted_criteria );
		$tiebreak                       = isset( $_POST['tiebreak_criterion'] ) ? sanitize_text_field( wp_unslash( $_POST['tiebreak_criterion'] ) ) : '';
		$settings['tiebreak_criterion'] = $this->resolve_tiebreak( $tiebreak, $posted_criteria, $settings['criteria'] );

		// Email templates. The body allows the same HTML as a post.
		$emails = array();
		foreach ( array_keys( KDNA_Grants_Emails::types() ) as $type ) {
			$emails[ $type ] = array(
				'subject' => isset( $_POST['emails'][ $type ]['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['emails'][ $type ]['subject'] ) ) : '',
				'body'    => isset( $_POST['emails'][ $type ]['body'] ) ? wp_kses_post( wp_unslash( $_POST['emails'][ $type ]['body'] ) ) : '',
			);
		}
		$settings['emails'] = $emails;

		// Closed messages.
		$defaults = KDNA_Grants_Install::default_settings();
		foreach ( array( 'closed_message', 'closed_message_none' ) as $key ) {
			$value            = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
			$settings[ $key ] = '' !== trim( $value ) ? $value : $defaults[ $key ];
		}

		// Data retention: 0 keeps records, otherwise 1 to 120 months.
		$retention                    = isset( $_POST['retention_months'] ) ? absint( wp_unslash( $_POST['retention_months'] ) ) : 24;
		$settings['retention_months'] = min( 120, $retention );

		// Claude.
		$settings['claude_enabled']      = ! empty( $_POST['claude_enabled'] );
		$settings['claude_auto']         = ! empty( $_POST['claude_auto'] );
		$settings['claude_include_logo'] = ! empty( $_POST['claude_include_logo'] );
		$settings['claude_profile']      = isset( $_POST['claude_profile'] ) ? sanitize_textarea_field( wp_unslash( $_POST['claude_profile'] ) ) : '';

		// The API key is only replaced when a new one is typed, and is kept out of the main settings.
		$new_key = isset( $_POST['claude_api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['claude_api_key'] ) ) ) : '';
		if ( '' !== $new_key ) {
			update_option( KDNA_Grants_Claude::KEY_OPTION, $new_key, false );
		}
		if ( ! empty( $_POST['claude_remove_key'] ) ) {
			delete_option( KDNA_Grants_Claude::KEY_OPTION );
		}

		// Contacting applicants.
		$reply_to             = isset( $_POST['reply_to'] ) ? sanitize_email( wp_unslash( $_POST['reply_to'] ) ) : '';
		$settings['reply_to'] = is_email( $reply_to ) ? $reply_to : '';

		$compose = array();
		$posted  = isset( $_POST['compose_templates'] ) && is_array( $_POST['compose_templates'] ) ? wp_unslash( $_POST['compose_templates'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised below.
		foreach ( $posted as $template ) {
			$name = isset( $template['name'] ) ? sanitize_text_field( $template['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$compose[] = array(
				'name'    => $name,
				'subject' => isset( $template['subject'] ) ? sanitize_text_field( $template['subject'] ) : '',
				'body'    => isset( $template['body'] ) ? sanitize_textarea_field( $template['body'] ) : '',
			);
		}
		$settings['compose_templates'] = $compose;

		// Offers.
		$settings['offers_enabled'] = ! empty( $_POST['offers_enabled'] );
		$settings['offer_percent']  = isset( $_POST['offer_percent'] ) ? min( 100, max( 1, absint( wp_unslash( $_POST['offer_percent'] ) ) ) ) : 25;
		$settings['offer_days']     = isset( $_POST['offer_days'] ) ? min( 3650, max( 1, absint( wp_unslash( $_POST['offer_days'] ) ) ) ) : 90;
		$settings['offer_cap']      = isset( $_POST['offer_cap'] ) ? sanitize_text_field( wp_unslash( $_POST['offer_cap'] ) ) : '';

		$settings['delete_data_on_uninstall'] = ! empty( $_POST['delete_data_on_uninstall'] );

		update_option( KDNA_GRANTS_OPTION, $settings, false );

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';

		wp_safe_redirect( add_query_arg( 'kdna_grants_saved', '1', admin_url( 'admin.php?page=' . self::SETTINGS_PAGE ) ) . ( $tab ? '#' . $tab : '' ) );
		exit;
	}

	/**
	 * Cleans the criteria list from the Settings screen.
	 *
	 * Each row keeps its key when it already has one, so renaming a criterion
	 * doesn't lose its scores. New rows get a key made from their name. Empty
	 * rows are dropped, and there is always at least one criterion (at most 10).
	 *
	 * @param array $rows Posted rows, each with 'key' and 'label'.
	 * @return array
	 */
	private function sanitize_criteria( $rows ) {
		$criteria = array();
		$used     = array();

		foreach ( (array) $rows as $row ) {
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}

			$key = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';
			if ( '' === $key ) {
				$key = sanitize_key( str_replace( ' ', '_', strtolower( remove_accents( $label ) ) ) );
			}
			if ( '' === $key ) {
				$key = 'criterion';
			}

			$base   = $key;
			$suffix = 2;
			while ( isset( $used[ $key ] ) ) {
				$key = $base . '_' . $suffix;
				++$suffix;
			}
			$used[ $key ] = true;

			$criteria[] = array(
				'key'   => $key,
				'label' => $label,
			);

			if ( count( $criteria ) >= 10 ) {
				break;
			}
		}

		return $criteria ? $criteria : KDNA_Grants_Install::default_settings()['criteria'];
	}

	/**
	 * Works out the tie-break criterion's key. The dropdown sends the row number,
	 * because a new criterion has no key until it is saved.
	 *
	 * @param string $choice   Posted choice: a row number.
	 * @param array  $posted   Posted rows.
	 * @param array  $criteria Cleaned criteria.
	 * @return string
	 */
	private function resolve_tiebreak( $choice, $posted, $criteria ) {
		$keys = wp_list_pluck( $criteria, 'key' );

		// Find the chosen row's label, then the cleaned criterion with that label.
		$rows = array_values( (array) $posted );
		if ( is_numeric( $choice ) && isset( $rows[ (int) $choice ]['label'] ) ) {
			$label = sanitize_text_field( $rows[ (int) $choice ]['label'] );
			foreach ( $criteria as $item ) {
				if ( $item['label'] === $label ) {
					return $item['key'];
				}
			}
		}

		if ( in_array( 'readiness', $keys, true ) ) {
			return 'readiness';
		}

		return $keys[0];
	}

	/**
	 * Sends a test of an email template to the admin email address, using what is typed on screen.
	 */
	public function ajax_test_email() {
		check_ajax_referer( 'kdna_grants_test_email', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'kdna-grants' ) ), 403 );
		}

		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		if ( ! array_key_exists( $type, KDNA_Grants_Emails::types() ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown email.', 'kdna-grants' ) ), 400 );
		}

		$template = array(
			'subject' => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
			'body'    => isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '',
		);
		if ( '' === trim( $template['subject'] ) || '' === trim( $template['body'] ) ) {
			$template = null;
		}

		$to   = get_option( 'admin_email' );
		$sent = KDNA_Grants_Emails::send_test( $type, $to, $template );

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'The test email could not be sent. Check the site\'s email settings or email sending plugin.', 'kdna-grants' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: email address. */
					__( 'Test sent to %s.', 'kdna-grants' ),
					$to
				),
			)
		);
	}

	/**
	 * Works out what still needs attention on the Settings screen, in plain English.
	 *
	 * @param array $settings Current settings.
	 * @param array $forms    Active forms, ID => title.
	 * @param array $fields   Mappable fields of the chosen form.
	 * @return string[]
	 */
	private function get_settings_warnings( $settings, $forms, $fields ) {
		$warnings = array();
		$form_id  = absint( $settings['form_id'] );

		if ( empty( $forms ) ) {
			$warnings[] = __( 'There are no active forms in KDNA Forms yet. Build the grant application form in KDNA Forms first, then come back here to choose it.', 'kdna-grants' );
			return $warnings;
		}

		if ( ! $form_id ) {
			$warnings[] = __( 'Choose the grant application form below, then match its fields.', 'kdna-grants' );
			return $warnings;
		}

		if ( ! isset( $forms[ $form_id ] ) ) {
			$warnings[] = __( 'The form chosen earlier can no longer be found. It may have been deleted, trashed or made inactive in KDNA Forms. Please choose the form again.', 'kdna-grants' );
			return $warnings;
		}

		// Fields not yet matched.
		$missing = array();
		foreach ( KDNA_Grants_Install::mappable_fields() as $key => $def ) {
			if ( ! empty( $def['required'] ) && empty( $settings['field_map'][ $key ] ) ) {
				$missing[] = $def['label'];
			}
		}
		if ( $missing ) {
			$warnings[] = sprintf(
				/* translators: %s: list of field names. */
				__( 'Still to match: %s.', 'kdna-grants' ),
				implode( ', ', $missing )
			);
		}

		// The same form field matched to two different things.
		$used = array_filter( (array) $settings['field_map'] );
		if ( count( $used ) !== count( array_unique( $used ) ) ) {
			$warnings[] = __( 'The same form field is matched more than once. Please check each one is matched to the right field.', 'kdna-grants' );
		}

		// No file upload field for the logo.
		$has_upload = false;
		foreach ( $fields as $field ) {
			if ( 'fileupload' === $field['type'] ) {
				$has_upload = true;
				break;
			}
		}
		if ( ! $has_upload ) {
			$warnings[] = __( 'This form has no file upload field, so there is nothing to match the logo to. Add a File Upload field to the form in KDNA Forms.', 'kdna-grants' );
		}

		// The applicant confirmation email is sent by KDNA Forms.
		// Claude switched on without a key.
		if ( ! empty( $settings['claude_enabled'] ) && '' === KDNA_Grants_Claude::get_api_key() ) {
			$warnings[] = __( 'Claude is switched on but has no API key yet. Add it on the Claude tab.', 'kdna-grants' );
		}

		// Offers switched on, but the unsuccessful email doesn't mention the code.
		if ( ! empty( $settings['offers_enabled'] ) ) {
			$unsuccessful = KDNA_Grants_Emails::get_template( 'unsuccessful' );
			if ( false === strpos( $unsuccessful['body'] . $unsuccessful['subject'], '{offer_code}' ) ) {
				$warnings[] = __( 'Offers are switched on, but the unsuccessful email doesn\'t include {offer_code}, so applicants won\'t see their code. Add it on the Emails and messages tab, ideally inside [offer]...[/offer].', 'kdna-grants' );
			}
		}

		if ( ! KDNA_Grants_Forms_Bridge::has_applicant_notification( $form_id ) ) {
			$warnings[] = __( 'This form does not seem to email the applicant yet. The confirmation email is sent by KDNA Forms, so add a Notification to the form that is sent to the applicant\'s email field.', 'kdna-grants' );
		}

		return $warnings;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Shared helpers for the admin screens
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Sends the browser back to an admin screen with a message code, then stops.
	 *
	 * @param string $page    Page slug.
	 * @param string $message Message code (see message_text()).
	 * @param array  $args    Extra query arguments.
	 */
	public static function redirect( $page, $message = '', $args = array() ) {
		$args['page'] = $page;

		if ( $message ) {
			$args['kdna_grants_msg'] = $message;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Shows the message for the code in the address bar, if there is one.
	 */
	public static function render_message() {
		$code = isset( $_GET['kdna_grants_msg'] ) ? sanitize_key( wp_unslash( $_GET['kdna_grants_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.

		$messages = array(
			'round_saved'       => array( 'success', __( 'Round saved.', 'kdna-grants' ) ),
			'round_deleted'     => array( 'success', __( 'Round deleted.', 'kdna-grants' ) ),
			'round_not_deleted' => array( 'error', __( 'That round has applications, so it can\'t be deleted.', 'kdna-grants' ) ),
			'round_locked'      => array( 'error', __( 'That round has been decided, so its dates can\'t be changed.', 'kdna-grants' ) ),
			'test_created'      => array( 'success', __( 'Test rounds created. The test round is selected below, with 6 sample applications.', 'kdna-grants' ) ),
			'test_exists'       => array( 'error', __( 'Test rounds already exist. Delete them first to create fresh ones.', 'kdna-grants' ) ),
			'test_deleted'      => array( 'success', __( 'Test rounds deleted.', 'kdna-grants' ) ),
			'marked_eligible'   => array( 'success', __( 'Marked as eligible. The flags are kept on record as cleared.', 'kdna-grants' ) ),
			'not_found'         => array( 'error', __( 'That item could not be found. It may have been deleted.', 'kdna-grants' ) ),
			'winner_chosen'     => array( 'success', __( 'Winner chosen. The round is decided and the emails are sending in the background. Their status shows in the Email column.', 'kdna-grants' ) ),
			'judging_reopened'  => array( 'success', __( 'Judging reopened. Scores can be changed again.', 'kdna-grants' ) ),
			'email_queued'      => array( 'success', __( 'Email queued to send again.', 'kdna-grants' ) ),
			'claude_queued'     => array( 'success', __( 'Claude is on it. It works in the background, one application at a time, so refresh this page in a few minutes.', 'kdna-grants' ) ),
			'claude_off'        => array( 'error', __( 'Claude isn\'t switched on. Add your API key in Settings > Claude.', 'kdna-grants' ) ),
			'offer_accepted'    => array( 'success', __( 'Offer accepted. No further discounts will be offered to this client.', 'kdna-grants' ) ),
			'offer_declined'    => array( 'success', __( 'Offer marked as declined.', 'kdna-grants' ) ),
			'offer_undone'      => array( 'success', __( 'Done. The offer is back to how it was.', 'kdna-grants' ) ),
		);

		if ( 'error' === $code ) {
			$key     = 'kdna_grants_error_' . get_current_user_id();
			$message = get_transient( $key );
			delete_transient( $key );

			if ( $message ) {
				$messages['error'] = array( 'error', $message );
			}
		}

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $code ][0] ),
			esc_html( $messages[ $code ][1] )
		);
	}

	/**
	 * A small coloured label showing a round's status.
	 *
	 * @param string $status Status key.
	 * @return string HTML.
	 */
	public static function status_badge( $status ) {
		return sprintf(
			'<span class="kdna-grants-badge kdna-grants-badge--%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( KDNA_Grants_Rounds::status_label( $status ) )
		);
	}
}
