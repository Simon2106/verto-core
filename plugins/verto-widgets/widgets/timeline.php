<?php
defined( 'ABSPATH' ) || exit;

/** Verto Timeline — horizontal scrolling company history (prefilled from the client's real timeline). */
class Verto_Widget_Timeline extends \Elementor\Widget_Base {
	public function get_name() { return 'verto-timeline'; }
	public function get_title() { return 'Verto Timeline'; }
	public function get_icon() { return 'eicon-time-line'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Timeline' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'date', [ 'label' => 'Date', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'title', [ 'label' => 'Event', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'highlight', [ 'label' => 'Highlight', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes' ] );
		$this->add_control( 'items', [
			'label' => 'Events', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ date }}} — {{{ title }}}',
			'default' => [
				[ 'date' => 'Feb 2020', 'title' => 'Verto People founded', 'highlight' => 'yes' ],
				[ 'date' => 'Mar 2020', 'title' => 'Lockdown announced' ],
				[ 'date' => 'Jul 2020', 'title' => 'Back to the office' ],
				[ 'date' => 'Aug 2020', 'title' => 'Moved to our 2nd office' ],
				[ 'date' => 'Nov 2020', 'title' => 'First international placement', 'highlight' => 'yes' ],
				[ 'date' => 'Feb 2021', 'title' => 'First employee joins' ],
				[ 'date' => 'Jun 2021', 'title' => 'Moved to our 3rd office' ],
				[ 'date' => 'Aug 2022', 'title' => 'Moved to our 4th office' ],
				[ 'date' => 'Oct 2022', 'title' => 'The Verto rebrand', 'highlight' => 'yes' ],
				[ 'date' => 'Dec 2022', 'title' => 'First US placement', 'highlight' => 'yes' ],
				[ 'date' => 'Mar 2023', 'title' => 'Winner of 2 categories — Business Awards UK' ],
				[ 'date' => 'Jul 2023', 'title' => 'Shortlisted for Best New Agency — Recruiter Awards' ],
				[ 'date' => 'Sep 2023', 'title' => 'Charity gala for the Amelia-Mae Foundation' ],
				[ 'date' => 'Nov 2023', 'title' => 'Best New Recruitment Agency of the Year — British Recruitment Awards', 'highlight' => 'yes' ],
				[ 'date' => 'Feb 2024', 'title' => 'Finalist — News Business Excellence Awards' ],
				[ 'date' => 'Mar 2024', 'title' => '£15,504 raised for the Amelia-Mae Foundation', 'highlight' => 'yes' ],
				[ 'date' => 'Sep 2025', 'title' => 'First international incentive trip — Barcelona' ],
				[ 'date' => 'Jan 2026', 'title' => 'Prague incentive trip' ],
				[ 'date' => '2026', 'title' => 'The Sunday Times Best Places to Work', 'highlight' => 'yes' ],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="verto-timeline"><div class="verto-timeline__track">';
		foreach ( $s['items'] as $item ) {
			printf(
				'<div class="verto-timeline__item%s"><div class="verto-timeline__dot"></div><div class="verto-timeline__date">%s</div><div class="verto-timeline__title">%s</div></div>',
				( $item['highlight'] ?? '' ) === 'yes' ? ' is-highlight' : '',
				esc_html( $item['date'] ),
				esc_html( $item['title'] )
			);
		}
		echo '</div></div>';
	}
}
