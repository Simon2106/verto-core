<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Section Intro — eyebrow + line-reveal display heading + body + link,
 * matching the prototype's section headers.
 */
class Verto_Widget_Section_Intro extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-section-intro'; }
	public function get_title() { return 'Verto Section Intro'; }
	public function get_icon() { return 'eicon-post-title'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Intro' ] );
		$this->add_control( 'eyebrow', [ 'label' => 'Eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'The brands' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'line', [ 'label' => 'Line', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$this->add_control( 'lines', [
			'label' => 'Heading lines', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ line }}}',
			'default' => [ [ 'line' => 'Three brands.' ], [ 'line' => 'One process-driven standard.' ] ],
		] );
		$this->add_control( 'size', [
			'label' => 'Display size', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'verto-display-1' => 'Display 1', 'verto-display-2' => 'Display 2', 'verto-display-3' => 'Display 3' ],
			'default' => 'verto-display-2',
		] );
		$this->add_control( 'tag', [
			'label' => 'HTML tag', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'h1' => 'h1', 'h2' => 'h2', 'h3' => 'h3' ], 'default' => 'h2',
		] );
		$this->add_control( 'body', [ 'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => '' ] );
		$this->add_control( 'link_text', [ 'label' => 'Link text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ] );
		$this->add_control( 'link', [ 'label' => 'Link', 'type' => \Elementor\Controls_Manager::URL ] );
		$this->end_controls_section();
	}

	protected function render() {
		$s   = $this->get_settings_for_display();
		$tag = in_array( $s['tag'], [ 'h1', 'h2', 'h3' ], true ) ? $s['tag'] : 'h2';
		echo '<div class="verto-intro">';
		if ( $s['eyebrow'] ) {
			printf( '<span class="verto-eyebrow">%s</span>', esc_html( $s['eyebrow'] ) );
		}
		printf( '<%s class="verto-title-reveal %s" style="margin-top:1.25rem;">', esc_attr( $tag ), esc_attr( $s['size'] ) );
		foreach ( $s['lines'] as $i => $item ) {
			printf( '<span class="line-mask"><span class="line-inner" style="transition-delay:%dms">%s</span></span>', (int) $i * 110, esc_html( $item['line'] ) );
		}
		printf( '</%s>', esc_attr( $tag ) );
		if ( $s['body'] ) {
			printf( '<p class="verto-intro__body">%s</p>', esc_html( $s['body'] ) );
		}
		if ( $s['link_text'] ) {
			printf( '<a class="verto-intro__link" href="%s">%s →</a>', esc_url( $s['link']['url'] ?? '#' ), esc_html( $s['link_text'] ) );
		}
		echo '</div>';
	}
}
