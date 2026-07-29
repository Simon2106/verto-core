<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Values — the five values as hover-fill cards on ink.
 * Prefilled with the client's real values (vertopeople.com wording).
 */
class Verto_Widget_Values extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-values'; }
	public function get_title() { return 'Verto Values'; }
	public function get_icon() { return 'eicon-columns'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Values' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'title', [ 'label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'body', [ 'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$this->add_control( 'items', [
			'label' => 'Values', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ title }}}',
			'default' => [
				[ 'title' => 'Committed', 'body' => 'Passionate about working hard by doing everything in your power to hit results and ensure our candidates and clients receive the best possible experience. Be committed to own your day, be results driven and take satisfaction from doing what we say we are going to do.' ],
				[ 'title' => 'Competitive', 'body' => 'We strive for excellence; this drives us to proactively overcome challenges ensuring optimal solutions for you. Being competitive in ourselves allows us to continuously enhance and evolve our recruitment processes to deliver high-quality service.' ],
				[ 'title' => 'Curious', 'body' => 'Driven by curiosity and being inquisitive, we continually ask great questions, truly believing that curiosity leads to greater knowledge.' ],
				[ 'title' => 'A Team', 'body' => 'By having an optimistic attitude and working as a team, together we celebrate success as a group of people orientated, optimistic and positive people.' ],
				[ 'title' => 'Love Our Processes', 'body' => 'Our recruitment processes are built off years of successful recruiting and learning from our mistakes. By being a "phone first" business, sticking to our plans and ensuring we work in the best way possible, then we will all succeed.' ],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="verto-values">';
		foreach ( $s['items'] as $i => $v ) {
			printf(
				'<div class="verto-value"><span class="verto-value__accent" aria-hidden="true"></span><div class="verto-value__num">0%d</div><h3 class="verto-value__title">%s</h3><p class="verto-value__body">%s</p></div>',
				(int) $i + 1,
				esc_html( $v['title'] ),
				esc_html( $v['body'] )
			);
		}
		echo '</div>';
	}
}
