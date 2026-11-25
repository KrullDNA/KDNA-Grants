<?php
/**
 * Round details anywhere in a layout: the round name, time left, closing date and next round.
 *
 * Available two ways:
 * - The [launchpad_info] shortcode, for example [launchpad_info show="round"].
 * - An Elementor dynamic tag, "Launchpad round info", for headings and text in any widget.
 *
 * @package KDNA_Launchpad
 */

defined( 'ABSPATH' ) || exit;

/**
 * Launchpad round details for shortcodes and Elementor dynamic tags.
 */
class KDNA_Launchpad_Tags {

	/**
	 * Shortcode name.
	 */
	const SHORTCODE = 'launchpad_info';

	/**
	 * The round state for this page load, worked out once.
	 *
	 * @var array|null
	 */
	private static $state = null;

	/**
	 * Hooks the shortcode and the Elementor dynamic tag.
	 */
	public static function register() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'elementor/dynamic_tags/register', array( __CLASS__, 'register_elementor_tag' ) );
	}

	/**
	 * What can be shown, with labels for the Elementor control.
	 *
	 * @return array
	 */
	public static function options() {
		return array(
			'round'          => __( 'Round name (open round)', 'kdna-launchpad' ),
			'time_left'      => __( 'Time left (live countdown)', 'kdna-launchpad' ),
			'close_date'     => __( 'Closing date', 'kdna-launchpad' ),
			'next_round'     => __( 'Next round name', 'kdna-launchpad' ),
			'next_open_date' => __( 'Next round opening date', 'kdna-launchpad' ),
		);
	}

	/**
	 * [launchpad_info show="round|time_left|close_date|next_round|next_open_date" fallback="..."]
	 *
	 * @param array $atts Shortcode options.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'show'     => 'round',
				'fallback' => '',
			),
			$atts,
			self::SHORTCODE
		);

		return self::value( $atts['show'], $atts['fallback'] );
	}

	/**
	 * One round detail as HTML. The fallback is shown when there's nothing to show,
	 * for example the time left while no round is open.
	 *
	 * @param string $show     What to show (a key from options()).
	 * @param string $fallback Text to show instead when there's nothing to show.
	 * @return string
	 */
	public static function value( $show, $fallback = '' ) {
		$state = self::state();
		$open  = 'open' === $state['state'];
		$text  = '';

		switch ( $show ) {
			case 'round':
				$text = $open ? $state['round'] : '';
				break;

			case 'close_date':
				$text = $open && $state['closes'] ? wp_date( 'j F Y', (int) $state['closes'] ) : '';
				break;

			case 'next_round':
				$text = $state['next_round'];
				break;

			case 'next_open_date':
				$text = $state['next_opens'] ? KDNA_Launchpad_Rounds::format( $state['next_opens'], false ) : '';
				break;

			case 'time_left':
				if ( $open && $state['closes'] ) {
					KDNA_Launchpad_Frontend::enqueue();

					return sprintf(
						'<span class="kdna-launchpad-time-left" data-closes="%1$d">%2$s</span>',
						(int) $state['closes'],
						esc_html( KDNA_Launchpad_Frontend::time_left( (int) $state['closes'] - time() ) )
					);
				}
				break;
		}

		return esc_html( '' !== $text ? $text : (string) $fallback );
	}

	/**
	 * The live round state, checked once per page load.
	 *
	 * @return array
	 */
	private static function state() {
		if ( null === self::$state ) {
			self::$state = KDNA_Launchpad_Frontend::get_state();
		}

		return self::$state;
	}

	/**
	 * Registers the "Launchpad round info" dynamic tag with Elementor.
	 *
	 * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags Elementor's dynamic tags manager.
	 */
	public static function register_elementor_tag( $dynamic_tags ) {
		if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {
			return;
		}

		require_once KDNA_LAUNCHPAD_PATH . 'elementor/class-kdna-launchpad-dynamic-tag.php';

		$dynamic_tags->register_group(
			'kdna-launchpad',
			array( 'title' => esc_html__( 'Launchpad', 'kdna-launchpad' ) )
		);

		$dynamic_tags->register( new KDNA_Launchpad_Dynamic_Tag() );
	}
}
