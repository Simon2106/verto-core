<?php
defined( 'ABSPATH' ) || exit;

/** Verto Perks — the four "Why Verto" cards (40% comms, share scheme, holidays, relocation). */
class Verto_Widget_Perks extends \Elementor\Widget_Base {
	public function get_name() { return 'verto-perks'; }
	public function get_title() { return 'Verto Perks Grid'; }
	public function get_icon() { return 'eicon-icon-box'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Perks' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'stat', [ 'label' => 'Big stat/word', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'title', [ 'label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'body', [ 'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$this->add_control( 'items', [
			'label' => 'Perks', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ title }}}',
			'default' => [
				[ 'stat' => '40%', 'title' => '40% commission', 'body' => 'One of the best splits in the market, transparent from day one. No thresholds designed to be missed, no clawbacks buried in a handbook.' ],
				[ 'stat' => 'Equity', 'title' => 'Share scheme', 'body' => "Everyone owns a piece. Not a senior-only perk — every person in the business is in the share scheme, so the group's growth is your growth." ],
				[ 'stat' => '2×', 'title' => '2 holiday incentives a year', 'body' => "Barcelona 2025. Prague, January 2026. Ibiza this summer. Hit target and you're on the plane with the whole company — twice a year." ],
				[ 'stat' => 'UK · US', 'title' => 'International relocation', 'body' => "UK to Austin. Austin to Miami. When you've built a market, we'll back you to take it abroad — desk, visa and first 90 days planned before you fly." ],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="verto-principles" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">';
		foreach ( $s['items'] as $p ) {
			printf(
				'<div class="verto-principle"><div class="verto-principle__num" style="color:var(--accent);">%s</div><h3 class="verto-principle__title">%s</h3><p class="verto-principle__body">%s</p></div>',
				esc_html( $p['stat'] ), esc_html( $p['title'] ), esc_html( $p['body'] )
			);
		}
		echo '</div>';
	}
}
