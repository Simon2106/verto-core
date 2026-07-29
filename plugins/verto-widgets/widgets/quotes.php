<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Quote Marquee — auto-scrolling employee quotes, pause on hover.
 * ⚠️ Prefilled with PLACEHOLDER quotes; replace when the client's real
 * employee quotes arrive.
 */
class Verto_Widget_Quotes extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-quotes'; }
	public function get_title() { return 'Verto Quote Marquee'; }
	public function get_icon() { return 'eicon-blockquote'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Quotes' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'quote', [ 'label' => 'Quote', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$rep->add_control( 'who', [ 'label' => 'Name / role', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'org', [ 'label' => 'Detail (joined year · office)', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$this->add_control( 'items', [
			'label' => 'Quotes', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ who }}}',
			'default' => [
				[ 'quote' => "I joined as a graduate with no recruitment experience. Four years on I run my own market, I've been to Barcelona and Prague on incentive trips, and I own a piece of the business I helped build.", 'who' => 'Placeholder — Senior Consultant', 'org' => 'Joined 2022 · Solent' ],
				[ 'quote' => "The 40% commission is what got my attention. The reason I've stayed is the way we work — phone first, plan led, and a team that actually celebrates each other's deals.", 'who' => 'Placeholder — Recruitment Consultant', 'org' => 'Joined 2023 · Solent' ],
				[ 'quote' => "I moved from the UK to Austin with Verto. The relocation wasn't a perk buried in a handbook — the business planned my desk, my visa and my first three months before I flew.", 'who' => 'Placeholder — Principal Consultant', 'org' => 'Joined 2021 · Austin' ],
				[ 'quote' => "Two incentive holidays a year sounds like a gimmick until you're on the second one, sat with the whole company, and nobody's checking their phone.", 'who' => 'Placeholder — Consultant', 'org' => 'Joined 2024 · Solent' ],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$items = $s['items'];
		if ( empty( $items ) ) return;
		$loop = array_merge( $items, $items ); // duplicate for seamless scroll
		echo '<div class="verto-quotes"><div class="verto-quotes__track">';
		foreach ( $loop as $q ) {
			printf(
				'<figure class="verto-quote"><span class="verto-quote__mark" aria-hidden="true">&ldquo;</span><blockquote class="verto-quote__text">%s</blockquote><figcaption><div class="verto-quote__who">%s</div><div class="verto-quote__org">%s</div></figcaption></figure>',
				esc_html( $q['quote'] ),
				esc_html( $q['who'] ),
				esc_html( $q['org'] )
			);
		}
		echo '</div></div>';
	}
}
