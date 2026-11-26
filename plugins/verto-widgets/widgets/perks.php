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
				// Round 47 (Nov 2026, Alex): his rewrite of all fourteen cards.
				[ 'title' => '40% Commission', 'sub' => 'One of the strongest commission structures in the industry, with complete transparency from day one.' ],
				[ 'title' => 'Share Scheme', 'sub' => 'Rewarding the people who build the business.' ],
				[ 'title' => 'Two International Trips a Year', 'sub' => "From Barcelona to Prague and Ibiza, hit your targets and you're on the plane." ],
				[ 'title' => 'Award-Winning Culture', 'sub' => 'Proudly recognised by The Sunday Times Best Places to Work 2026.' ],
				[ 'title' => 'Clear Career Progression', 'sub' => 'A transparent career pathway from Trainee upwards, so you always know what it takes to reach the next level.' ],
				[ 'title' => 'Structured Learning & Development', 'sub' => 'Training built around you from day one onwards with continuous development throughout your career.' ],
				[ 'title' => "Winners' Lunches", 'sub' => 'Celebrate your achievements with exclusive dining experiences, courtesy of Verto.' ],
				[ 'title' => 'Monthly Sales Days', 'sub' => 'A full day of friendly competition, prizes and noise! Every month.' ],
				[ 'title' => 'Milestone Miles', 'sub' => 'Celebrate three years with Verto by spending a week working from any of our international offices.' ],
				[ 'title' => 'Wear Your Success', 'sub' => 'Billing milestones, marked in Nike.' ],
				[ 'title' => 'The 3650 Club', 'sub' => 'Celebrate ten years with Verto by choosing a Rolex, a designer handbag or four weeks off.' ],
				[ 'title' => 'Referral Scheme', 'sub' => 'Bring good people with you and get paid for it.' ],
				[ 'title' => 'Healthcare Cashback', 'sub' => 'Claim back healthcare costs, including dental care, optical appointments and physiotherapy.' ],
				[ 'title' => 'Company Pension', 'sub' => 'Company pension from day one.' ],
			],
,
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
