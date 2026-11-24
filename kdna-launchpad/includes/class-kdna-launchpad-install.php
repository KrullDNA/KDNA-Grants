<?php
/**
 * Install: database tables, default settings and the judge capability.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sets up and upgrades everything KDNA Launchpad stores in the database.
 */
class KDNA_Launchpad_Install {

	/**
	 * Option that remembers which version of the tables is installed.
	 */
	const DB_VERSION_OPTION = 'kdna_launchpad_db_version';

	/*
	 * -----------------------------------------------------------------------
	 * Table names
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Full name of the rounds table, including the site's table prefix.
	 *
	 * @return string
	 */
	public static function rounds_table() {
		global $wpdb;
		return $wpdb->prefix . 'kdna_launchpad_rounds';
	}

	/**
	 * Full name of the applications table, including the site's table prefix.
	 *
	 * @return string
	 */
	public static function applications_table() {
		global $wpdb;
		return $wpdb->prefix . 'kdna_launchpad_applications';
	}

	/**
	 * Full name of the offers table, including the site's table prefix.
	 *
	 * @return string
	 */
	public static function offers_table() {
		global $wpdb;
		return $wpdb->prefix . 'kdna_launchpad_offers';
	}

	/*
	 * -----------------------------------------------------------------------
	 * Activation and upgrades
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Runs when the plugin is activated. Works whether or not KDNA Forms is active.
	 */
	public static function activate() {
		self::create_tables();
		self::add_default_settings();
		self::add_capability();
	}

	/**
	 * Re-runs the table set-up if the plugin was updated to a version with a newer table layout.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== KDNA_LAUNCHPAD_DB_VERSION ) {
			self::activate();
		}

		self::maybe_upgrade_settings();
	}

	/**
	 * One-off tidy-ups to saved settings when the plugin is updated.
	 *
	 * 1.1.1: adds the "Hosting and next steps" email template, moves offers still
	 * on the old 90-day default to the agreed 28 days, and moves the round gap from
	 * the old two-month default to three months (quarterly rounds).
	 */
	private static function maybe_upgrade_settings() {
		$done = get_option( 'kdna_launchpad_settings_version', '' );

		if ( version_compare( (string) $done, '1.1.1', '>=' ) ) {
			return;
		}

		$settings = get_option( KDNA_LAUNCHPAD_OPTION, array() );

		if ( is_array( $settings ) && $settings ) {
			if ( isset( $settings['compose_templates'] ) && is_array( $settings['compose_templates'] ) ) {
				$names = wp_list_pluck( $settings['compose_templates'], 'name' );
				foreach ( self::default_compose_templates() as $template ) {
					if ( __( 'Hosting and next steps', 'kdna-launchpad' ) === $template['name'] && ! in_array( $template['name'], $names, true ) ) {
						$settings['compose_templates'][] = $template;
					}
				}
			}

			if ( isset( $settings['offer_days'] ) && 90 === (int) $settings['offer_days'] ) {
				$settings['offer_days'] = 28;
			}

			// Quarterly rounds: move the old two-month default gap to three months.
			if ( isset( $settings['round_gap_months'] ) && 2 === (int) $settings['round_gap_months'] ) {
				$settings['round_gap_months'] = 3;
			}

			update_option( KDNA_LAUNCHPAD_OPTION, $settings, false );
		}

		update_option( 'kdna_launchpad_settings_version', '1.1.1', false );
	}

	/**
	 * Creates (or updates) the two custom tables.
	 *
	 * The WordPress dbDelta() function compares these definitions with what is in the database and
	 * only adds what is missing, so it is safe to run more than once.
	 * All dates are stored in UTC and shown in the site's time zone.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$rounds          = self::rounds_table();
		$applications    = self::applications_table();

		// Rounds: one row per application period.
		$sql_rounds = "CREATE TABLE {$rounds} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			opens_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			closes_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			announce_date date DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'scheduled',
			winner_application_id bigint(20) unsigned DEFAULT NULL,
			decision_note text,
			criteria longtext,
			activity_log longtext,
			ai_status varchar(20) NOT NULL DEFAULT '',
			ai_overview longtext,
			ai_overview_at datetime DEFAULT NULL,
			ai_usage longtext,
			is_test tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY opens_at (opens_at),
			KEY closes_at (closes_at)
		) {$charset_collate};";

		// Applications: one row per KDNA Forms entry. The answers themselves stay in KDNA Forms.
		$sql_applications = "CREATE TABLE {$applications} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			round_id bigint(20) unsigned NOT NULL DEFAULT 0,
			entry_id bigint(20) unsigned NOT NULL DEFAULT 0,
			first_name varchar(191) NOT NULL DEFAULT '',
			last_name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			business_name varchar(191) NOT NULL DEFAULT '',
			trading_as varchar(191) NOT NULL DEFAULT '',
			industry varchar(191) NOT NULL DEFAULT '',
			abn varchar(20) NOT NULL DEFAULT '',
			abn_name varchar(191) NOT NULL DEFAULT '',
			abn_state varchar(10) NOT NULL DEFAULT '',
			staff varchar(100) NOT NULL DEFAULT '',
			eligibility_flags longtext,
			scores longtext,
			total_score smallint(5) unsigned NOT NULL DEFAULT 0,
			notes longtext,
			shortlisted tinyint(1) unsigned NOT NULL DEFAULT 0,
			outcome varchar(20) NOT NULL DEFAULT 'pending',
			email_status varchar(20) NOT NULL DEFAULT '',
			ai_status varchar(20) NOT NULL DEFAULT '',
			ai_assessment longtext,
			ai_assessed_at datetime DEFAULT NULL,
			ai_error text,
			contact_log longtext,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY round_id (round_id),
			KEY entry_id (entry_id),
			KEY email (email),
			KEY abn (abn),
			KEY outcome (outcome)
		) {$charset_collate};";

		dbDelta( $sql_rounds );
		// Offers: the 25% first-brief discount given to unsuccessful applicants. One per business, ever.
		$offers     = self::offers_table();
		$sql_offers = "CREATE TABLE {$offers} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			application_id bigint(20) unsigned NOT NULL DEFAULT 0,
			round_id bigint(20) unsigned NOT NULL DEFAULT 0,
			code varchar(40) NOT NULL DEFAULT '',
			first_name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			abn varchar(20) NOT NULL DEFAULT '',
			business_name varchar(191) NOT NULL DEFAULT '',
			percent smallint(5) unsigned NOT NULL DEFAULT 0,
			cap varchar(50) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			issued_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			expires_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			closed_at datetime DEFAULT NULL,
			closed_by varchar(191) NOT NULL DEFAULT '',
			note text,
			is_test tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY round_id (round_id),
			KEY application_id (application_id),
			KEY email (email),
			KEY abn (abn),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql_applications );
		dbDelta( $sql_offers );

		update_option( self::DB_VERSION_OPTION, KDNA_LAUNCHPAD_DB_VERSION, false );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Settings
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The fields KDNA Launchpad needs to read from the application form.
	 *
	 * Key => label and help text shown on the Settings screen. 'types' limits
	 * the dropdown to suitable KDNA Forms field types (empty means any field).
	 * 'required' fields are listed under "Still to do" until they are matched.
	 *
	 * @return array
	 */
	public static function mappable_fields() {
		return array(
			'first_name'    => array(
				'label'    => __( 'First name', 'kdna-launchpad' ),
				'help'     => __( 'Used in emails, for example "Hi {first_name}".', 'kdna-launchpad' ),
				'types'    => array(),
				'required' => true,
			),
			'last_name'     => array(
				'label'    => __( 'Last name', 'kdna-launchpad' ),
				'help'     => __( 'Shown on the applications list.', 'kdna-launchpad' ),
				'types'    => array(),
				'required' => true,
			),
			'email'         => array(
				'label'    => __( 'Email', 'kdna-launchpad' ),
				'help'     => __( 'Where the winner and unsuccessful emails go. Also used for the duplicate, past winner and existing client checks.', 'kdna-launchpad' ),
				'types'    => array(),
				'required' => true,
			),
			'business_name' => array(
				'label'    => __( 'Business name', 'kdna-launchpad' ),
				'help'     => __( 'Shown on the applications list and checked against your existing client list.', 'kdna-launchpad' ),
				'types'    => array(),
				'required' => true,
			),
			'trading_as'    => array(
				'label'    => __( 'Trading as (optional)', 'kdna-launchpad' ),
				'help'     => __( 'Used in place of the business name everywhere you and the applicant see it, when they fill it in.', 'kdna-launchpad' ),
				'types'    => array(),
				'required' => false,
			),
			'industry'      => array(
				'label'    => __( 'Industry (optional)', 'kdna-launchpad' ),
				'help'     => __( 'Shown on the applications list. Leave unmatched if the form doesn\'t ask for it.', 'kdna-launchpad' ),
				'types'    => array(),
				'required' => false,
			),
			'abn'           => array(
				'label'    => __( 'ABN', 'kdna-launchpad' ),
				'help'     => __( 'Checked with the official ABN checksum, and for duplicates and past winners.', 'kdna-launchpad' ),
				'types'    => array(),
				'required' => true,
			),
			'staff'         => array(
				'label'    => __( 'Number of staff', 'kdna-launchpad' ),
				'help'     => __( 'Checked against the staff limit.', 'kdna-launchpad' ),
				'types'    => array(),
				'required' => true,
			),
			'logo'          => array(
				'label'    => __( 'Logo upload', 'kdna-launchpad' ),
				'help'     => __( 'Shown as an image on the judging screen. Only file upload fields are listed.', 'kdna-launchpad' ),
				'types'    => array( 'fileupload' ),
				'required' => true,
			),
		);
	}

	/**
	 * Every setting with its default value. Later stages read the ones they need from here.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			// Stage 1: form selection and field mapping.
			'form_id'                  => 0,
			'field_map'                => array_fill_keys( array_keys( self::mappable_fields() ), '' ),

			// Stage 2: rounds and eligibility.
			'round_gap_months'         => 3,
			'staff_limit'              => 20,
			'existing_clients'         => '',

			// Stage 3: judging. Each criterion is scored 1 to 5.
			'criteria'                 => array(
				array(
					'key'   => 'fit',
					'label' => __( 'Fit', 'kdna-launchpad' ),
				),
				array(
					'key'   => 'impact',
					'label' => __( 'Impact', 'kdna-launchpad' ),
				),
				array(
					'key'   => 'readiness',
					'label' => __( 'Readiness', 'kdna-launchpad' ),
				),
				array(
					'key'   => 'story',
					'label' => __( 'Story', 'kdna-launchpad' ),
				),
			),
			'tiebreak_criterion'       => 'readiness',

			// Stage 3: emails. The applicant confirmation email is sent by KDNA Forms.
			'emails'                   => self::default_emails(),

			// 1.3.0: the terms page linked from the application form's tick box.
			'terms_url'                => '',
			'word_limits'              => array(),

			// Stage 4: the message shown in place of the form between rounds.
			'closed_message'           => __( 'Applications for this round have closed. The next round opens on {next_open_date}.', 'kdna-launchpad' ),
			'closed_message_none'      => __( 'Applications are closed for now. Follow KDNA for news of the next round.', 'kdna-launchpad' ),

			// 1.1.0: Claude. The API key is stored separately (see KDNA_Launchpad_Claude::get_api_key()).
			'claude_enabled'           => false,
			'claude_auto'              => true,
			'claude_include_logo'      => false,
			'claude_profile'           => '',

			// 1.1.0: contacting applicants from the dashboard.
			'reply_to'                 => '',
			'compose_templates'        => self::default_compose_templates(),

			// 1.1.0: offers for unsuccessful applicants.
			'offers_enabled'           => true,
			'offer_percent'            => 25,
			'offer_days'               => 28,
			'offer_cap'                => '',

			// Stage 4: privacy. Records are removed this many months after a round is decided (0 keeps them).
			'retention_months'         => 24,

			// Uninstall: off by default, so deleting the plugin never removes records by accident.
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * Default winner and unsuccessful emails.
	 *
	 * PLACEHOLDER COPY: to be replaced with the copy from the KDNA Launchpad
	 * application form document once it is in the repository.
	 *
	 * @return array
	 */
	public static function default_emails() {
		return array(
			'winner'       => array(
				'subject' => __( 'Congratulations {first_name}, {business_name} has won KDNA Launchpad', 'kdna-launchpad' ),
				'body'    => '<p>' . __( 'Hi {first_name},', 'kdna-launchpad' ) . '</p>'
					. '<p>' . __( 'Thank you for applying for KDNA Launchpad ({round_name}). We\'re delighted to tell you that {business_name} has been chosen as the winner.', 'kdna-launchpad' ) . '</p>'
					. '<p>' . __( 'We\'ll be in touch in the next few days to book a kick-off meeting. In the meantime, you can get ready by setting up your domain name and WordPress hosting (we recommend VentraIP), and gathering your words, photos, logo files, brand colours and fonts.', 'kdna-launchpad' ) . '</p>'
					. '<p>' . __( 'If you\'d like more than Launchpad covers, such as extra pages or an online shop, we\'ll quote the extras separately at 25% off our standard rates.', 'kdna-launchpad' ) . '</p>'
					. '<p>' . __( 'Congratulations again,', 'kdna-launchpad' ) . '<br />' . __( 'Nick Krull, KDNA', 'kdna-launchpad' ) . '</p>',
			),
			'unsuccessful' => array(
				'subject' => __( 'Your KDNA Launchpad application, {round_name}', 'kdna-launchpad' ),
				'body'    => '<p>' . __( 'Hi {first_name},', 'kdna-launchpad' ) . '</p>'
					. '<p>' . __( 'Thank you for applying for KDNA Launchpad ({round_name}), and for telling us about {business_name}. We had many strong applications, and this time {business_name} wasn\'t chosen.', 'kdna-launchpad' ) . '</p>'
					. '<p>' . __( 'You\'re very welcome to apply again. The next round opens on {next_open_date}.', 'kdna-launchpad' ) . '</p>'
					. '[offer]<p>' . __( 'We\'d still love to help {business_name}. As a thank you for applying, you can have {offer_percent}% off your first brief with KDNA, whether that\'s a logo, a website, a presentation or a mix. Just quote {offer_code} when you get in touch, then engage us and approve your quote by {offer_expiry}. The quote is based on your requirements, and the discount applies to your first brief only. Later work is at our standard rates.', 'kdna-launchpad' ) . '</p>[/offer]'
					. '<p>' . __( 'Thank you again, and all the best,', 'kdna-launchpad' ) . '<br />' . __( 'Nick Krull, KDNA', 'kdna-launchpad' ) . '</p>',
			),
		);
	}

	/**
	 * Default reusable messages for the dashboard email window.
	 *
	 * @return array List of array( 'name', 'subject', 'body' ).
	 */
	public static function default_compose_templates() {
		return array(
			array(
				'name'    => __( 'Shortlist call invitation', 'kdna-launchpad' ),
				'subject' => __( 'Your KDNA Launchpad application, {business_name}', 'kdna-launchpad' ),
				'body'    => __( "Hi {first_name},\n\nThank you for applying for KDNA Launchpad. I really enjoyed reading about {business_name}, and you're on my shortlist this round.\n\nWould you be free for a 20-minute call this week so I can learn a little more? Just reply with a couple of times that suit you.\n\nNick Krull, KDNA", 'kdna-launchpad' ),
			),
			array(
				'name'    => __( 'A quick question', 'kdna-launchpad' ),
				'subject' => __( 'A quick question about your Launchpad application', 'kdna-launchpad' ),
				'body'    => __( "Hi {first_name},\n\nThanks for applying for KDNA Launchpad. I'm reading through this round's applications and had a quick question about {business_name}:\n\n\n\nNick Krull, KDNA", 'kdna-launchpad' ),
			),
			array(
				'name'    => __( 'Offer reminder', 'kdna-launchpad' ),
				'subject' => __( 'Your {offer_percent}% KDNA offer, {business_name}', 'kdna-launchpad' ),
				'body'    => __( "Hi {first_name},\n\nJust a friendly reminder that your {offer_percent}% discount on your first brief with KDNA ends on {offer_expiry}. To use it, quote {offer_code}, then engage us and approve your quote by that date.\n\nIf you'd like to talk about a logo, website or anything else for {business_name}, just reply to this email.\n\nNick Krull, KDNA", 'kdna-launchpad' ),
			),
			array(
				'name'    => __( 'Hosting and next steps', 'kdna-launchpad' ),
				'subject' => __( 'Next steps for your new website, {business_name}', 'kdna-launchpad' ),
				'body'    => __( "Hi {first_name},\n\nCongratulations again! Here's how to get ready for your kick-off meeting:\n\n1. Domain and hosting: set up your domain name and a WordPress hosting plan. We recommend VentraIP (ventraip.com.au), an Australian host with local support. If you'd prefer another host, check with me before you buy.\n\n2. Your words: up to 5 pages, with up to 400 words per page, written and proofread.\n\n3. Your images: at least 10 good-quality photos you have the right to use.\n\n4. Your brand: your logo (ideally a vector file such as SVG, AI, EPS or PDF), brand colours and fonts.\n\nPlease send everything within 4 weeks of our kick-off meeting. If you'd like more than Launchpad covers, such as extra pages or an online shop, I'll quote the extras separately at 25% off.\n\nNick Krull, KDNA", 'kdna-launchpad' ),
			),
		);
	}

	/**
	 * Saves the default settings the first time the plugin is activated. Never overwrites saved settings.
	 */
	public static function add_default_settings() {
		if ( false === get_option( KDNA_LAUNCHPAD_OPTION, false ) ) {
			add_option( KDNA_LAUNCHPAD_OPTION, self::default_settings(), '', false );
		}
	}

	/*
	 * -----------------------------------------------------------------------
	 * Capability
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Gives administrators the judge capability. It can later be given to a guest judge's role.
	 */
	public static function add_capability() {
		$role = get_role( 'administrator' );

		if ( $role && ! $role->has_cap( KDNA_LAUNCHPAD_CAP ) ) {
			$role->add_cap( KDNA_LAUNCHPAD_CAP );
		}
	}
}
