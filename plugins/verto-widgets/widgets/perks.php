<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto "What We Offer" grid – round 4, item 11 (replaces the old four-card
 * perks layout, and the client-logo strip that will never happen).
 * Dark notched-corner cards (top-right corner clipped, gold seam) on a light
 * section, per Martin's reference. 14 client-approved perks, fully editable
 * in the repeater. 3 columns desktop / 2 tablet / 1 mobile, with a
 * scroll-reveal stagger driven by --card-delay (verto-ui.css).
 */
class Verto_Widget_Perks extends \Elementor\Widget_Base {
	public function get_name() { return 'verto-perks'; }
	public function get_title() { return 'Verto – What We Offer'; }
	public function get_icon() { return 'eicon-icon-box'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'What we offer' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'title', [ 'label' => 'Perk', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'sub', [ 'label' => 'One-liner', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$this->add_control( 'items', [
			'label' => 'Perks', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ title }}}',
			'default' => [
				[ 'title' => '40% commission', 'sub' => 'One of the strongest splits in the market – transparent from day one.' ],
				[ 'title' => 'Share scheme', 'sub' => 'Rewarding the people who build the business.' ],
				[ 'title' => 'Two international trips a year', 'sub' => "Barcelona, Prague, Ibiza – hit target and you're on the plane." ],
				[ 'title' => 'Award-winning culture', 'sub' => 'The Sunday Times Best Places to Work 2026.' ],
				[ 'title' => 'Clear progression', 'sub' => 'A published ladder from trainee to principal – no mystery promotions.' ],
				[ 'title' => 'Structured L&D', 'sub' => 'Training built around you, from day one onwards.' ],
				[ 'title' => "Winners' lunches", 'sub' => 'Hit the number, book the table – on us.' ],
				[ 'title' => 'Monthly sales days', 'sub' => 'A day of competition, prizes and noise, every month.' ],
				[ 'title' => 'Milestone Miles', 'sub' => 'Three years in: a week working from any international office.' ],
				[ 'title' => 'Wear Your Success', 'sub' => 'Billing milestones, marked in Nike.' ],
				[ 'title' => 'The 3650 Club', 'sub' => 'Ten years in: a Rolex, a designer handbag – or four weeks off.' ],
				[ 'title' => 'Referral scheme', 'sub' => 'Bring good people with you and get paid for it.' ],
				[ 'title' => 'Healthcare cash-back', 'sub' => 'Dental, optical, physio – everyday health costs claimed back.' ],
				[ 'title' => 'Pension', 'sub' => 'Company pension from day one, on top of everything above.' ],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="verto-offer">';
		foreach ( $s['items'] as $i => $p ) {
			printf(
				'<div class="verto-offer__card" style="--card-delay:%dms;"><h3 class="verto-offer__title">%s</h3><p class="verto-offer__sub">%s</p></div>',
				min( (int) $i * 60, 780 ),
				esc_html( $p['title'] ),
				esc_html( $p['sub'] )
			);
		}
		echo '</div>';
	}
}
