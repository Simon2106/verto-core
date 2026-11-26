<?php
defined( 'ABSPATH' ) || exit;

/** Verto Principles – numbered grid (Verto standard, career path, etc.). */
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
				// Round 51 (Nov 2026, Alex): his rewrite of all four principles.
				[ 'title' => 'Own Your Day', 'body' => 'We believe success starts with accountability. Our consultants take ownership of their markets, drive their own performance and approach every day with purpose. We set ambitious goals, take initiative and deliver on our commitments.' ],
				[ 'title' => 'Phone First', 'body' => 'Great recruitment is built on real conversations. We prioritise meaningful interactions with clients and candidates, building stronger relationships, developing deeper market knowledge and uncovering opportunities that others miss.' ],
				[ 'title' => 'Ask Better Questions', 'body' => 'We go beyond the obvious. By challenging assumptions, staying curious and asking the right questions, we develop a deeper understanding of our clients, candidates and specialist markets.' ],
				[ 'title' => 'Win as a Team', 'body' => 'We champion individual and collective success. Across every brand, we share knowledge, support one another and hold ourselves to the same high standards.' ],
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
