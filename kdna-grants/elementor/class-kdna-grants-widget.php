<?php
/**
 * Elementor widget: Launchpad Application.
 *
 * Shows the right thing for the round: while a round is open, an optional
 * countdown and then the KDNA Forms form; while closed, the closed message with
 * the next opening date. The form keeps KDNA Forms' own styling, so this widget
 * only carries style controls for what it adds: the container, the countdown and
 * the closed message.
 *
 * Built for Elementor's Atomic (optimised markup) mode: one wrapper div, no
 * inner wrapper when optimised markup is on, and no CSS relying on
 * .elementor-widget-container.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Plugin;
use Elementor\Widget_Base;

/**
 * The Launchpad Application widget.
 */
class KDNA_Grants_Widget extends Widget_Base {

	/**
	 * Widget name used by Elementor.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-grant-application';
	}

	/**
	 * Widget title shown in the panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Launchpad Application', 'kdna-grants' );
	}

	/**
	 * Widget icon shown in the panel.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	/**
	 * Puts the widget in the KDNA Tools category.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'kdna-tools' );
	}

	/**
	 * Search words for the widget panel.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'launchpad', 'grant', 'application', 'kdna', 'form', 'countdown' );
	}

	/**
	 * Front-end style this widget needs. Elementor loads it only on pages using the widget.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'kdna-grants' );
	}

	/**
	 * Front-end script this widget needs (the countdown).
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'kdna-grants' );
	}

	/**
	 * No inner wrapper div when Elementor's optimised markup is on.
	 *
	 * @return bool
	 */
	public function has_widget_inner_wrapper(): bool {
		if ( ! isset( Plugin::$instance->experiments ) || ! method_exists( Plugin::$instance->experiments, 'is_feature_active' ) ) {
			return true;
		}

		return ! Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/**
	 * Clears Elementor's cache of the output, because the content depends on the round dates.
	 *
	 * @return bool
	 */
	protected function is_dynamic_content(): bool {
		return true;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Controls
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Adds all the widget's settings.
	 */
	protected function register_controls() {
		$this->register_content_controls();
		$this->register_container_style_controls();
		$this->register_countdown_style_controls();
		$this->register_closed_style_controls();
	}

	/**
	 * Content tab: preview state, countdown and wording.
	 */
	private function register_content_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'Launchpad Application', 'kdna-grants' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'preview_state',
			array(
				'label'       => esc_html__( 'Preview state (editor only)', 'kdna-grants' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => KDNA_Grants_Frontend::PREVIEW_OPEN_COUNTDOWN,
				'options'     => array(
					KDNA_Grants_Frontend::PREVIEW_OPEN_COUNTDOWN => esc_html__( 'Open with countdown', 'kdna-grants' ),
					KDNA_Grants_Frontend::PREVIEW_OPEN => esc_html__( 'Open without countdown', 'kdna-grants' ),
					KDNA_Grants_Frontend::PREVIEW_CLOSED_NEXT => esc_html__( 'Closed with next date', 'kdna-grants' ),
					KDNA_Grants_Frontend::PREVIEW_CLOSED_NONE => esc_html__( 'Closed with no next date', 'kdna-grants' ),
				),
				'description' => esc_html__( 'Choose a state to style it here. This has no effect on the live site, which always shows the real state of the round.', 'kdna-grants' ),
			)
		);

		$this->add_control(
			'show_countdown',
			array(
				'label'        => esc_html__( 'Show countdown', 'kdna-grants' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'kdna-grants' ),
				'label_off'    => esc_html__( 'Hide', 'kdna-grants' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'countdown_label',
			array(
				'label'     => esc_html__( 'Countdown wording', 'kdna-grants' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => KDNA_Grants_Frontend::default_countdown_label(),
				'condition' => array( 'show_countdown' => 'yes' ),
			)
		);

		$this->add_control(
			'show_form_title',
			array(
				'label'        => esc_html__( 'Show form title', 'kdna-grants' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'show_form_description',
			array(
				'label'        => esc_html__( 'Show form description', 'kdna-grants' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'closed_heading',
			array(
				'label'       => esc_html__( 'Closed heading', 'kdna-grants' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => KDNA_Grants_Frontend::default_closed_heading(),
				'separator'   => 'before',
				'label_block' => true,
			)
		);

		$this->add_control(
			'closed_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'The closed message itself is edited in Launchpad > Settings > Emails and messages, so it stays the same everywhere.', 'kdna-grants' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: the container around everything.
	 */
	private function register_container_style_controls() {
		$selector = '{{WRAPPER}} .kdna-grants-application';

		$this->start_controls_section(
			'section_style_container',
			array(
				'label' => esc_html__( 'Container', 'kdna-grants' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'container_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => $selector,
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'container_border',
				'selector' => $selector,
			)
		);

		$this->add_responsive_control(
			'container_radius',
			array(
				'label'      => esc_html__( 'Border radius', 'kdna-grants' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array(
					$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'container_shadow',
				'selector' => $selector,
			)
		);

		$this->add_responsive_control(
			'container_padding',
			array(
				'label'      => esc_html__( 'Padding', 'kdna-grants' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array(
					$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'container_gap',
			array(
				'label'      => esc_html__( 'Space between countdown and form', 'kdna-grants' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'selectors'  => array(
					$selector => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: the countdown.
	 */
	private function register_countdown_style_controls() {
		$selector = '{{WRAPPER}} .kdna-grants-countdown';

		$this->start_controls_section(
			'section_style_countdown',
			array(
				'label' => esc_html__( 'Countdown', 'kdna-grants' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'countdown_align',
			array(
				'label'     => esc_html__( 'Alignment', 'kdna-grants' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => esc_html__( 'Left', 'kdna-grants' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centre', 'kdna-grants' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Right', 'kdna-grants' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					$selector => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'countdown_typography',
				'selector' => $selector,
			)
		);

		$this->add_control(
			'countdown_color',
			array(
				'label'     => esc_html__( 'Text colour', 'kdna-grants' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$selector => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'countdown_time_typography',
				'label'    => esc_html__( 'Time typography', 'kdna-grants' ),
				'selector' => $selector . ' .kdna-grants-countdown__time',
			)
		);

		$this->add_control(
			'countdown_time_color',
			array(
				'label'     => esc_html__( 'Time colour', 'kdna-grants' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$selector . ' .kdna-grants-countdown__time' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'countdown_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => $selector,
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'countdown_border',
				'selector' => $selector,
			)
		);

		$this->add_responsive_control(
			'countdown_radius',
			array(
				'label'      => esc_html__( 'Border radius', 'kdna-grants' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array(
					$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'countdown_padding',
			array(
				'label'      => esc_html__( 'Padding', 'kdna-grants' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array(
					$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style tab: the closed message box, heading and body.
	 */
	private function register_closed_style_controls() {
		$selector = '{{WRAPPER}} .kdna-grants-closed';

		$this->start_controls_section(
			'section_style_closed',
			array(
				'label' => esc_html__( 'Closed message', 'kdna-grants' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'closed_align',
			array(
				'label'     => esc_html__( 'Alignment', 'kdna-grants' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => esc_html__( 'Left', 'kdna-grants' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centre', 'kdna-grants' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Right', 'kdna-grants' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					$selector => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'closed_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => $selector,
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'closed_border',
				'selector' => $selector,
			)
		);

		$this->add_responsive_control(
			'closed_radius',
			array(
				'label'      => esc_html__( 'Border radius', 'kdna-grants' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array(
					$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'closed_shadow',
				'selector' => $selector,
			)
		);

		$this->add_responsive_control(
			'closed_padding',
			array(
				'label'      => esc_html__( 'Padding', 'kdna-grants' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array(
					$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'closed_heading_style',
			array(
				'label'     => esc_html__( 'Heading', 'kdna-grants' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'closed_heading_typography',
				'selector' => $selector . ' .kdna-grants-closed__heading',
			)
		);

		$this->add_control(
			'closed_heading_color',
			array(
				'label'     => esc_html__( 'Colour', 'kdna-grants' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$selector . ' .kdna-grants-closed__heading' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'closed_heading_spacing',
			array(
				'label'      => esc_html__( 'Space below', 'kdna-grants' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'selectors'  => array(
					$selector . ' .kdna-grants-closed__heading' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'closed_body_style',
			array(
				'label'     => esc_html__( 'Message', 'kdna-grants' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'closed_body_typography',
				'selector' => $selector . ' .kdna-grants-closed__message, ' . $selector . ' .kdna-grants-closed__message p',
			)
		);

		$this->add_control(
			'closed_body_color',
			array(
				'label'     => esc_html__( 'Colour', 'kdna-grants' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$selector . ' .kdna-grants-closed__message' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * Output
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Prints the widget: one wrapper div with the countdown and form, or the closed message.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$html = KDNA_Grants_Frontend::render(
			array(
				'countdown'        => isset( $settings['show_countdown'] ) && 'yes' === $settings['show_countdown'],
				'countdown_label'  => isset( $settings['countdown_label'] ) ? $settings['countdown_label'] : '',
				'closed_heading'   => isset( $settings['closed_heading'] ) ? $settings['closed_heading'] : '',
				'show_title'       => isset( $settings['show_form_title'] ) && 'yes' === $settings['show_form_title'],
				'show_description' => isset( $settings['show_form_description'] ) && 'yes' === $settings['show_form_description'],
				'preview'          => self::is_editor() && ! empty( $settings['preview_state'] ) ? $settings['preview_state'] : null,
			)
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside; the form HTML comes from KDNA Forms.
	}

	/**
	 * True inside the Elementor editor or its preview, where the preview state applies.
	 *
	 * @return bool
	 */
	private static function is_editor() {
		$plugin = Plugin::$instance;

		$editing    = isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode();
		$previewing = isset( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode();

		return $editing || $previewing;
	}
}
