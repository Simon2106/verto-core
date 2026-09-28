<?php
defined( 'ABSPATH' ) || exit;

/** Verto Footprint – location cards over imagery (Solent / Austin / Miami). */
class Verto_Widget_Footprint extends \Elementor\Widget_Base {
	public function get_name() { return 'verto-footprint'; }
	public function get_title() { return 'Verto Footprint'; }
	public function get_icon() { return 'eicon-google-maps'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Locations' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'note', [ 'label' => 'Kicker', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'name', [ 'label' => 'Location', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'line1', [ 'label' => 'Line 1 (address)', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'line2', [ 'label' => 'Line 2 (desks)', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'image', [ 'label' => 'Image', 'type' => \Elementor\Controls_Manager::MEDIA ] );
		// Round 9, item 25: optional link – the whole tile becomes a link
		// (About footprint tiles link to the careers locations section).
		$rep->add_control( 'link', [ 'label' => 'Link (optional)', 'type' => \Elementor\Controls_Manager::URL ] );
		$this->add_control( 'items', [
			'label' => 'Locations', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ name }}}',
			'default' => [
				[ 'note' => 'Where it started – Feb 2020', 'name' => 'Solent, UK', 'line1' => 'Arena Business Centre, Havant, Portsmouth', 'line2' => 'Vertek · ModulR · Verto Life Sciences' ],
				[ 'note' => 'US HQ', 'name' => 'Austin, TX', 'line1' => '5900 Balcones Drive, Austin', 'line2' => 'Edison Lux · Vertek US' ],
				[ 'note' => 'Coming soon', 'name' => 'Miami, FL', 'line1' => 'Opening soon', 'line2' => 'ModulR US' ],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="verto-footprint">';
		foreach ( $s['items'] as $l ) {
			// Round 9, item 25: a tile with a link renders as <a> (same
			// classes, hover affordance in verto-ui.css).
			$href = (string) ( $l['link']['url'] ?? '' );
			$tag  = '' !== $href ? 'a' : 'div';
			printf( '<%s class="verto-footprint__card%s"%s>', $tag, '' !== $href ? ' verto-footprint__card--link' : '', '' !== $href ? ' href="' . esc_url( $href ) . '"' : '' );
			if ( ! empty( $l['image']['url'] ) ) {
				printf( '<img src="%s" alt="" aria-hidden="true" loading="lazy" />', esc_url( $l['image']['url'] ) );
			}
			echo '<div class="verto-footprint__scrim"></div><div class="verto-footprint__body">';
			printf( '<div class="verto-footprint__note">%s</div><div class="verto-footprint__name">%s</div><div class="verto-footprint__line">%s</div><div class="verto-footprint__line" style="opacity:.7">%s</div>', esc_html( $l['note'] ), esc_html( $l['name'] ), esc_html( $l['line1'] ), esc_html( $l['line2'] ) );
			if ( '' !== $href ) {
				echo '<div class="verto-footprint__go">Explore this location →</div>';
			}
			echo '</div>';
			printf( '</%s>', $tag );
		}
		echo '</div>';
	}
}
