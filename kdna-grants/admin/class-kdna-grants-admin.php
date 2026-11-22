<?php
/**
 * Admin: the KDNA Grants menu, the KDNA Forms dependency notice and the Settings screen.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything KDNA Grants adds to the WordPress admin.
 */
class KDNA_Grants_Admin {

	/**
	 * Page slug of the Settings screen.
	 */
	const SETTINGS_PAGE = 'kdna-grants-settings';

	/**
	 * Action name used by the Settings form (also used for its nonce).
	 */
	const SAVE_ACTION = 'kdna_grants_save_settings';

	/**
	 * The hook suffix WordPress gives our Settings screen, used to load scripts only there.
	 *
	 * @var string
	 */
	private $settings_hook = '';

	/**
	 * Hooks into WordPress. If KDNA Forms isn't available, only the notice is shown.
	 */
	public function register() {
		if ( ! KDNA_Grants_Forms_Bridge::is_available() ) {
			add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
			return;
		}

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'save_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KDNA_GRANTS_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * KDNA Forms dependency notice
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Explains that KDNA Grants needs KDNA Forms. Shown only to people who can manage plugins.
	 */
	public function dependency_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( KDNA_Grants_Forms_Bridge::is_active() ) {
			$message = sprintf(
				/* translators: 1: installed KDNA Forms version, 2: required version. */
				__( 'KDNA Grants needs KDNA Forms %2$s or newer. This site has version %1$s. Please update KDNA Forms. Until then, KDNA Grants is paused and the grant form works as a normal KDNA Forms form.', 'kdna-grants' ),
				KDNA_Grants_Forms_Bridge::get_version(),
				KDNA_GRANTS_MIN_FORMS_VERSION
			);
		} else {
			$message = __( 'KDNA Grants needs the KDNA Forms plugin to collect applications. Please install and activate KDNA Forms. Until then, KDNA Grants is paused and does nothing.', 'kdna-grants' );
		}
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'KDNA Grants', 'kdna-grants' ); ?>:</strong>
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
	 * Adds the KDNA Grants menu. Applications and Rounds screens are added in later stages.
	 */
	public function add_menu() {
		add_menu_page(
			__( 'KDNA Grants', 'kdna-grants' ),
			__( 'KDNA Grants', 'kdna-grants' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( $this, 'render_settings_page' ),
			'dashicons-awards',
			58
		);

		$this->settings_hook = add_submenu_page(
			self::SETTINGS_PAGE,
			__( 'KDNA Grants Settings', 'kdna-grants' ),
			__( 'Settings', 'kdna-grants' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Loads the admin styles and script, only on KDNA Grants screens.
	 *
	 * @param string $hook The current admin screen's hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! $this->settings_hook || $hook !== $this->settings_hook ) {
			return;
		}

		wp_enqueue_style( 'kdna-grants-admin', KDNA_GRANTS_URL . 'admin/assets/admin.css', array(), KDNA_GRANTS_VERSION );
		wp_enqueue_script( 'kdna-grants-admin', KDNA_GRANTS_URL . 'admin/assets/admin.js', array(), KDNA_GRANTS_VERSION, true );

		$targets = array();
		foreach ( KDNA_Grants_Install::mappable_fields() as $key => $def ) {
			$targets[ $key ] = array( 'types' => $def['types'] );
		}

		wp_localize_script(
			'kdna-grants-admin',
			'kdnaGrantsSettings',
			array(
				'fields'   => KDNA_Grants_Forms_Bridge::get_all_mappable_fields(),
				'targets'  => $targets,
				'editUrl'  => admin_url( 'admin.php?page=kdna_edit_forms&id=' ),
				'i18n'     => array(
					'choose'           => __( 'Choose a field', 'kdna-grants' ),
					'noFields'         => __( 'No suitable fields in this form', 'kdna-grants' ),
					'confirmUninstall' => __( 'Are you sure? With this ticked, deleting KDNA Grants from the Plugins screen permanently removes every round, application, score and note. KDNA Forms entries are not affected.', 'kdna-grants' ),
				),
			)
		);
	}

	/**
	 * Adds a "Settings" link under KDNA Grants on the Plugins screen.
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

		$settings['delete_data_on_uninstall'] = ! empty( $_POST['delete_data_on_uninstall'] );

		update_option( KDNA_GRANTS_OPTION, $settings, false );

		wp_safe_redirect( add_query_arg( 'kdna_grants_saved', '1', admin_url( 'admin.php?page=' . self::SETTINGS_PAGE ) ) );
		exit;
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
			if ( empty( $settings['field_map'][ $key ] ) ) {
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
		if ( ! KDNA_Grants_Forms_Bridge::has_applicant_notification( $form_id ) ) {
			$warnings[] = __( 'This form does not seem to email the applicant yet. The confirmation email is sent by KDNA Forms, so add a Notification to the form that is sent to the applicant\'s email field.', 'kdna-grants' );
		}

		return $warnings;
	}
}
