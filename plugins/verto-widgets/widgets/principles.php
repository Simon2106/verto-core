<?php
defined( 'ABSPATH' ) || exit;

/** Verto Principles — numbered grid (Verto standard, career path, etc.). */
class Verto_Widget_Principles extends \Elementor\Widget_Base {
	public function get_name() { return 'verto-principles'; }
	public function get_title() { return 'Verto Principles Grid'; }
	public function get_icon() { return 'eicon-number-field'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Items' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'kicker', [ 'label' => 'Kicker (optional, e.g. Year 1–2)', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'title', [ 'label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'body', [ 'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$this->add_control( 'items', [
			'label' => 'Items', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ title }}}',
			'default' => [
				[ 'title' => 'Own your day', 'body' => "Nobody here waits to be told. Every consultant plans their market, runs their desk and takes satisfaction from doing exactly what they said they'd do." ],
				[ 'title' => 'Phone first', 'body' => 'Markets move in conversations, not inboxes. We pick up the phone first — to candidates, to clients, to each other — and everything we know comes from that.' ],
				[ 'title' => 'Ask better questions', 'body' => 'Curiosity is a working tool here. The best shortlist starts with the question nobody else asked — of the client, the candidate and ourselves.' ],
				[ 'title' => 'Win as a team', 'body' => 'Deals are individual; success isn\'t. We celebrate together, travel together and hold each other to the same standard — whichever brand the placement lands in.' ],
			],
		] );
		$this->add_control( 'columns', [
			'label' => 'Columns (desktop)', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ '2' => '2', '4' => '4' ], 'default' => '2',
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$cols = $s['columns'] === '4' ? ' style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));"' : '';
		echo '<div class="verto-principles"' . $cols . '>';
		foreach ( $s['items'] as $i => $p ) {
			echo '<div class="verto-principle">';
			printf( '<div class="verto-principle__num">0%d</div>', (int) $i + 1 );
			if ( ! empty( $p['kicker'] ) ) {
				printf( '<div class="verto-footprint__note" style="margin-top:.5rem;">%s</div>', esc_html( $p['kicker'] ) );
			}
			printf( '<h3 class="verto-principle__title">%s</h3><p class="verto-principle__body">%s</p></div>', esc_html( $p['title'] ), esc_html( $p['body'] ) );
		}
		echo '</div>';
	}
}
