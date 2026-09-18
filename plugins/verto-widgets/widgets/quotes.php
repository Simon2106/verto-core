<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Quote Marquee – auto-scrolling employee quotes, pause on hover.
 * Carries the client's REAL employee quotes; attribution is job title only.
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
		$rep->add_control( 'who', [ 'label' => 'Job title', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$this->add_control( 'items', [
			'label' => 'Quotes', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ who }}}',
			'default' => [
				[ 'quote' => "I like the togetherness. Everyone has the same goals and it's great to work in an environment that matches how you want to shape your future. Well looked after, and the opportunity to earn life-changing money is fantastic.", 'who' => 'Team Leader' ],
				[ 'quote' => "The best thing about Verto is the earning potential, and the flexibility. I've earned more commission in one month at Verto than I did across the year in previous companies. I'm also able to work around my life schedule, and trusted to be left to my own devices to build my own desk.", 'who' => 'Senior Recruitment Consultant' ],
				[ 'quote' => "Verto is a dynamic and exciting place to work. The pace of growth and change is unlike anything I've experienced before, and there's a real sense that we're constantly moving forward. What really makes Verto stand out is the team. It's a fun, ambitious and engaging environment where everyone gets involved. From team sprints and sales days to the everyday buzz around the office, there's always something driving us forward. The opportunity to grow here feels wide open. Combine that with great earning potential, genuine career progression and a brilliant team to work alongside, and there's only one question: why didn't I get the call to join Verto sooner?", 'who' => 'VP of Engineering' ],
				[ 'quote' => "Verto gives you the opportunity to thrive and really sees the potential in you. There's no limit to how far you can go, and you're always encouraged and supported to grow and achieve more. I also really like how transparent Verto is. You always know where you stand, and your hard work is recognised. Coming from an architecture background, Verto has given me the opportunity to use my industry knowledge and turn it into a career in recruitment, while continuing to learn and grow every day.", 'who' => 'Recruitment Consultant' ],
				[ 'quote' => "Verto People gives me the best of both worlds: real autonomy to run my desk like my own business, plus the team and infrastructure to back it up. It's a place that rewards hustle, and it keeps me hungry to hit bigger numbers.", 'who' => 'Recruitment Consultant' ],
				[ 'quote' => "Working at Verto is a good mix of hard work, focus and fun. The team culture is both collaborative and competitive, and that gets emphasised in the monthly Sales Days, which are always good fun. It's a business that genuinely gives you the opportunity to be the best version of yourself and build a fantastic career.", 'who' => 'Recruitment Consultant' ],
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
			// Long quotes take a wider card so the row doesn't tower; the
			// track's align-items:stretch keeps every card equal height.
			$wide = mb_strlen( (string) $q['quote'] ) > 380 ? ' verto-quote--wide' : '';
			printf(
				'<figure class="verto-quote%s"><span class="verto-quote__mark" aria-hidden="true">&ldquo;</span><blockquote class="verto-quote__text">%s</blockquote><figcaption><div class="verto-quote__who">%s</div></figcaption></figure>',
				esc_attr( $wide ),
				esc_html( $q['quote'] ),
				esc_html( $q['who'] )
			);
		}
		echo '</div></div>';
	}
}
