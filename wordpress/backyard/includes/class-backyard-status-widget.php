<?php
defined( 'ABSPATH' ) || exit;

/** Loaded only when Elementor registers its widgets. */
class Backyard_Status_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'backyard-status'; }
	public function get_title() { return 'Backyard status'; }
	public function get_icon() { return 'eicon-circle'; }
	public function get_categories() { return array( 'general' ); }
	public function get_keywords() { return array( 'backyard', 'status', 'microfoon', 'remote' ); }
	public function get_style_depends() { return array( 'backyard-system-status' ); }
	public function get_script_depends() { return array( 'backyard-system-status' ); }
	protected function is_dynamic_content(): bool { return true; }

	protected function register_controls() {
		$this->start_controls_section( 'status_content', array( 'label' => 'Status' ) );
		$this->add_control( 'component', array( 'label' => 'Onderdeel', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'backyard', 'options' => backyard_status_components() ) );
		$this->add_control( 'label', array( 'label' => 'Eigen label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '', 'placeholder' => 'Standaardnaam gebruiken' ) );
		$this->add_control( 'show_checked', array( 'label' => 'Laatst gecontroleerd tonen', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '' ) );
		$this->add_control( 'meaning', array( 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => 'Microfoon online betekent actieve audio-overdracht, niet bewezen BirdNET-verwerking. Zonder nieuwe dierdetecties kan de microfoon gewoon online zijn.' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'status_style', array( 'label' => 'Weergave', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'status_typography', 'selector' => '{{WRAPPER}} .backyard-status-line' ) );
		$this->add_control( 'text_color', array( 'label' => 'Tekstkleur', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .backyard-system-status' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'dot_size', array( 'label' => 'Grootte statusbolletje', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 6, 'max' => 32 ) ), 'selectors' => array( '{{WRAPPER}} .backyard-status-dot' => 'width: {{SIZE}}px; height: {{SIZE}}px;' ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Tussenruimte', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 4, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .backyard-status-line' => 'gap: {{SIZE}}px;' ) ) );
		$this->end_controls_section();
	}
	protected function render() {
		$settings = $this->get_settings_for_display();
		echo backyard_status_html( $settings['component'] ?? 'backyard', $settings['label'] ?? '', 'yes' === ( $settings['show_checked'] ?? '' ) );
	}
}
