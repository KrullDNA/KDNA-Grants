<?php
/**
 * Front end: what visitors see where the grant application goes, used by both
 * the Elementor widget and the [kdna_grant_application] shortcode.
 *
 * Round open: an optional countdown, then the KDNA Forms form.
 * Round closed: the closed message, with the next opening date if there is one.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the grant application area.
 */
class KDNA_Grants_Frontend {

	const SHORTCODE = 'kdna_grant_application';

	/**
	 * Preview states for the Elementor editor.
	 */
	const PREVIEW_OPEN_COUNTDOWN = 'open_countdown';
	const PREVIEW_OPEN           = 'open';
	const PREVIEW_CLOSED_NEXT    = 'closed_next';
	const PREVIEW_CLOSED_NONE    = 'closed_none';

	/**
	 * Hooks into WordPress. Called once KDNA Forms is available.
	 */
	public static function register() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_for_shortcode' ) );

		// Elementor: the KDNA Tools category and the Launchpad Application widget.
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_elementor_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_elementor_widget' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_assets' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Elementor
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Adds the KDNA Tools widget category, unless another KDNA plugin already added it.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor's elements manager.
	 */
	public static function register_elementor_category( $elements_manager ) {
		$categories = method_exists( $elements_manager, 'get_categories' ) ? $elements_manager->get_categories() : array();

		if ( isset( $categories['kdna-tools'] ) ) {
			return;
		}

		$elements_manager->add_category(
			'kdna-tools',
			array(
				'title' => esc_html__( 'KDNA Tools', 'kdna-grants' ),
				'icon'  => 'eicon-apps',
			)
		);
	}

	/**
	 * Loads and registers the Launchpad Application widget. Only runs when Elementor is active.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor's widgets manager.
	 */
	public static function register_elementor_widget( $widgets_manager ) {
		require_once KDNA_GRANTS_PATH . 'elementor/class-kdna-grants-widget.php';

		$widgets_manager->register( new KDNA_Grants_Widget() );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Assets
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Registers the front-end style and countdown script. They only load where the widget or shortcode is used.
	 */
	public static function register_assets() {
		if ( wp_script_is( 'kdna-grants', 'registered' ) ) {
			return;
		}

		wp_register_style( 'kdna-grants', KDNA_GRANTS_URL . 'assets/css/kdna-grants.css', array(), KDNA_GRANTS_VERSION );
		wp_register_script( 'kdna-grants', KDNA_GRANTS_URL . 'assets/js/kdna-grants.js', array(), KDNA_GRANTS_VERSION, true );

		wp_localize_script(
			'kdna-grants',
			'kdnaGrantsCountdown',
			array(
				/* translators: %d: 1 (one day). */
				'day'     => __( '%d day', 'kdna-grants' ),
				/* translators: %d: number of days. */
				'days'    => __( '%d days', 'kdna-grants' ),
				/* translators: %d: 1 (one hour). */
				'hour'    => __( '%d hour', 'kdna-grants' ),
				/* translators: %d: number of hours. */
				'hours'   => __( '%d hours', 'kdna-grants' ),
				/* translators: %d: 1 (one minute). */
				'minute'  => __( '%d minute', 'kdna-grants' ),
				/* translators: %d: number of minutes. */
				'minutes' => __( '%d minutes', 'kdna-grants' ),
				'soon'    => __( 'less than a minute', 'kdna-grants' ),
				'closed'  => __( 'Applications for this round have now closed.', 'kdna-grants' ),
			)
		);
	}

	/**
	 * Loads the style in the page head when the shortcode is in the page content, to avoid a flash of unstyled content.
	 */
	public static function maybe_enqueue_for_shortcode() {
		$post = get_post();

		if ( is_singular() && $post && has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
			self::enqueue();
		}
	}

	/**
	 * Loads the front-end style and script.
	 */
	public static function enqueue() {
		self::register_assets();

		wp_enqueue_style( 'kdna-grants' );
		wp_enqueue_script( 'kdna-grants' );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Shortcode
	 * -----------------------------------------------------------------------
	 */

	/**
	 * [kdna_grant_application] shows the same as the Elementor widget, with default styling.
	 *
	 * Options: countdown="yes|no", countdown_label="Applications close in",
	 * closed_heading="Applications closed", title="no", description="no".
	 *
	 * @param array $atts Shortcode options.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'countdown'       => 'yes',
				'countdown_label' => self::default_countdown_label(),
				'closed_heading'  => self::default_closed_heading(),
				'title'           => 'no',
				'description'     => 'no',
			),
			$atts,
			self::SHORTCODE
		);

		self::enqueue();

		return self::render(
			array(
				'countdown'        => 'yes' === strtolower( $atts['countdown'] ),
				'countdown_label'  => $atts['countdown_label'],
				'closed_heading'   => $atts['closed_heading'],
				'show_title'       => 'yes' === strtolower( $atts['title'] ),
				'show_description' => 'yes' === strtolower( $atts['description'] ),
			)
		);
	}

	/**
	 * Default countdown wording.
	 *
	 * @return string
	 */
	public static function default_countdown_label() {
		return __( 'Applications close in', 'kdna-grants' );
	}

	/**
	 * Default heading above the closed message.
	 *
	 * @return string
	 */
	public static function default_closed_heading() {
		return __( 'Applications closed', 'kdna-grants' );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Rendering
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Builds the grant application area as one wrapper div.
	 *
	 * @param array $args {
	 *     Render options.
	 *
	 *     @type bool        $countdown        Show the countdown while open.
	 *     @type string      $countdown_label  Words before the time left.
	 *     @type string      $closed_heading   Heading above the closed message ('' for none).
	 *     @type bool        $show_title       Show the form title.
	 *     @type bool        $show_description Show the form description.
	 *     @type string|null $preview          Editor preview state, or null for the live state.
	 * }
	 * @return string HTML.
	 */
	public static function render( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'countdown'        => true,
				'countdown_label'  => self::default_countdown_label(),
				'closed_heading'   => self::default_closed_heading(),
				'show_title'       => false,
				'show_description' => false,
				'preview'          => null,
			)
		);

		$state = self::get_state( $args['preview'] );

		if ( 'open' === $state['state'] ) {
			$html = '';

			if ( $args['countdown'] && $state['show_countdown'] ) {
				$html .= self::countdown_html( $state['closes'], $args['countdown_label'] );
			}

			$form = KDNA_Grants_Forms_Bridge::render_form( kdna_grants_get_setting( 'form_id', 0 ), $args['show_title'], $args['show_description'] );

			if ( '' === $form ) {
				$form = current_user_can( 'manage_options' ) || $args['preview']
					? '<p class="kdna-grants-application__notice">' . esc_html__( 'The grant application form appears here. Choose it in Launchpad > Settings.', 'kdna-grants' ) . '</p>'
					: '';
			}

			return '<div class="kdna-grants-application kdna-grants-application--open">' . $html . '<div class="kdna-grants-application__form">' . $form . '</div></div>';
		}

		$heading = '' !== trim( (string) $args['closed_heading'] )
			? '<h3 class="kdna-grants-closed__heading">' . esc_html( $args['closed_heading'] ) . '</h3>'
			: '';

		return '<div class="kdna-grants-application kdna-grants-application--closed">'
			. '<div class="kdna-grants-closed">'
			. $heading
			. '<div class="kdna-grants-closed__message">' . wpautop( esc_html( self::closed_message( $state['next_opens'] ) ) ) . '</div>'
			. '</div></div>';
	}

	/**
	 * Works out what to show: open (with the closing time) or closed (with the next opening time).
	 *
	 * @param string|null $preview Editor preview state, or null for the live state.
	 * @return array 'state' (open/closed), 'closes' (timestamp), 'show_countdown', 'next_opens' (UTC or '').
	 */
	public static function get_state( $preview = null ) {
		switch ( $preview ) {
			case self::PREVIEW_OPEN_COUNTDOWN:
			case self::PREVIEW_OPEN:
				return array(
					'state'          => 'open',
					'closes'         => time() + 12 * DAY_IN_SECONDS + 3 * HOUR_IN_SECONDS,
					'show_countdown' => self::PREVIEW_OPEN_COUNTDOWN === $preview,
					'next_opens'     => '',
				);

			case self::PREVIEW_CLOSED_NEXT:
				return array(
					'state'          => 'closed',
					'closes'         => 0,
					'show_countdown' => false,
					'next_opens'     => gmdate( KDNA_Grants_Rounds::DB_FORMAT, time() + 40 * DAY_IN_SECONDS ),
				);

			case self::PREVIEW_CLOSED_NONE:
				return array(
					'state'          => 'closed',
					'closes'         => 0,
					'show_countdown' => false,
					'next_opens'     => '',
				);
		}

		// The live state, checked fresh every time the page is shown.
		KDNA_Grants_Rounds::sync_statuses();

		$open = KDNA_Grants_Rounds::get_open_round();
		if ( $open ) {
			return array(
				'state'          => 'open',
				'closes'         => strtotime( $open['closes_at'] . ' UTC' ),
				'show_countdown' => true,
				'next_opens'     => '',
			);
		}

		$next = KDNA_Grants_Rounds::get_next_round();

		return array(
			'state'          => 'closed',
			'closes'         => 0,
			'show_countdown' => false,
			'next_opens'     => $next ? $next['opens_at'] : '',
		);
	}

	/**
	 * The closed message from Settings, with the next opening date filled in.
	 *
	 * @param string $next_opens UTC time the next round opens, or ''.
	 * @return string
	 */
	public static function closed_message( $next_opens ) {
		if ( $next_opens ) {
			return str_replace(
				'{next_open_date}',
				KDNA_Grants_Rounds::format( $next_opens, false ),
				(string) kdna_grants_get_setting( 'closed_message', '' )
			);
		}

		return (string) kdna_grants_get_setting( 'closed_message_none', '' );
	}

	/**
	 * The countdown, for example "Applications close in 12 days". The script keeps it up to date.
	 *
	 * @param int    $closes Closing time (timestamp).
	 * @param string $label  Words before the time left.
	 * @return string
	 */
	private static function countdown_html( $closes, $label ) {
		return sprintf(
			'<div class="kdna-grants-countdown" data-closes="%1$d"><span class="kdna-grants-countdown__label">%2$s</span> <span class="kdna-grants-countdown__time">%3$s</span></div>',
			(int) $closes,
			esc_html( $label ),
			esc_html( self::time_left( $closes - time() ) )
		);
	}

	/**
	 * Time left in plain English: days, then hours, then minutes.
	 *
	 * @param int $seconds Seconds left.
	 * @return string
	 */
	public static function time_left( $seconds ) {
		if ( $seconds >= DAY_IN_SECONDS ) {
			$days = (int) floor( $seconds / DAY_IN_SECONDS );
			/* translators: %d: number of days. */
			return sprintf( _n( '%d day', '%d days', $days, 'kdna-grants' ), $days );
		}

		if ( $seconds >= HOUR_IN_SECONDS ) {
			$hours = (int) floor( $seconds / HOUR_IN_SECONDS );
			/* translators: %d: number of hours. */
			return sprintf( _n( '%d hour', '%d hours', $hours, 'kdna-grants' ), $hours );
		}

		if ( $seconds >= MINUTE_IN_SECONDS ) {
			$minutes = (int) floor( $seconds / MINUTE_IN_SECONDS );
			/* translators: %d: number of minutes. */
			return sprintf( _n( '%d minute', '%d minutes', $minutes, 'kdna-grants' ), $minutes );
		}

		return __( 'less than a minute', 'kdna-grants' );
	}
}
