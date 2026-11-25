<?php
/**
 * Elementor dynamic tag: Launchpad round info.
 *
 * Shows the round name, time left, closing date or next round in any widget that
 * takes dynamic text, such as a Heading or Text Editor.
 *
 * @package KDNA_Launchpad
 */

defined( 'ABSPATH' ) || exit;

/**
 * "Launchpad round info" dynamic tag.
 */
class KDNA_Launchpad_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag {

	/**
	 * Tag name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-launchpad-round-info';
	}

	/**
	 * Tag title shown in the dynamic tags list.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Launchpad round info', 'kdna-launchpad' );
	}

	/**
	 * Group the tag is listed under.
	 *
	 * @return string
	 */
	public function get_group() {
		return 'kdna-launchpad';
	}

	/**
	 * Works anywhere Elementor takes dynamic text.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	/**
	 * The tag's settings: what to show, and what to show when there's nothing to show.
	 */
	protected function register_controls() {
		$this->add_control(
			'show',
			array(
				'label'   => esc_html__( 'Show', 'kdna-launchpad' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'round',
				'options' => KDNA_Launchpad_Tags::options(),
			)
		);

		$this->add_control(
			'fallback_text',
			array(
				'label'       => esc_html__( 'When there\'s nothing to show', 'kdna-launchpad' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'description' => esc_html__( 'For example, when no round is open. Leave empty to show nothing.', 'kdna-launchpad' ),
			)
		);
	}

	/**
	 * Prints the value.
	 */
	public function render() {
		$settings = $this->get_settings();

		echo wp_kses_post(
			KDNA_Launchpad_Tags::value(
				isset( $settings['show'] ) ? $settings['show'] : 'round',
				isset( $settings['fallback_text'] ) ? $settings['fallback_text'] : ''
			)
		);
	}
}
