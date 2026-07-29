<?php
defined( 'ABSPATH' ) || exit;

/**
 * Title Reveal — LHi-style headline where each line rises out of an
 * overflow mask, staggered, when scrolled into view.
 * Markup pairs with .verto-title-reveal CSS + verto-effects.js observer.
 */
class Verto_Widget_Title_Reveal extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-title-reveal'; }
	public function get_title() { return 'Verto Title Reveal'; }
	public function get_icon() { return 'eicon-heading'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Headline' ] );

		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'line', [
			'label'   => 'Line',
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => 'Headline line',
		] );
		$this->add_control( 'lines', [
			'label'       => 'Lines',
			'type'        => \Elementor\Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'default'     => [ [ 'line' => 'Precision talent.' ], [ 'line' => 'One group.' ] ],
			'title_field' => '{{{ line }}}',
		] );
		$this->add_control( 'tag', [
			'label'   => 'HTML tag',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'h1' => 'h1', 'h2' => 'h2', 'h3' => 'h3' ],
			'default' => 'h2',
		] );
		$this->end_controls_section();

		$this->start_controls_section( 'style', [ 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [
			'name'     => 'typography',
			'selector' => '{{WRAPPER}} .verto-title-reveal',
		] );
		$this->add_control( 'color', [
			'label'     => 'Colour',
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .verto-title-reveal' => 'color: {{VALUE}};' ],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s   = $this->get_settings_for_display();
		$tag = in_array( $s['tag'], [ 'h1', 'h2', 'h3' ], true ) ? $s['tag'] : 'h2';
		printf( '<%s class="verto-title-reveal">', esc_attr( $tag ) );
		foreach ( $s['lines'] as $i => $item ) {
			printf(
				'<span class="line-mask"><span class="line-inner" style="transition-delay:%dms">%s</span></span>',
				(int) $i * 110,
				esc_html( $item['line'] )
			);
		}
		printf( '</%s>', esc_attr( $tag ) );
	}
}
