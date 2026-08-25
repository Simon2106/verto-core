<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Collage — masonry/tile photo grid (About page hero collage).
 * 4-column grid with varied tile spans (big 2×2 / wide 2×1 / standard 1×1),
 * rounded corners, optional caption scrim, and a subtle stagger reveal
 * driven by the section scroll-reveal (--tile-delay per tile).
 */
class Verto_Widget_Collage extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-collage'; }
	public function get_title() { return 'Verto Collage'; }
	public function get_icon() { return 'eicon-gallery-masonry'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Collage' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'image', [ 'label' => 'Image', 'type' => \Elementor\Controls_Manager::MEDIA ] );
		$rep->add_control( 'alt', [ 'label' => 'Alt text', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'caption', [ 'label' => 'Caption (optional)', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'size', [
			'label' => 'Tile size', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'std' => 'Standard (1×1)', 'wide' => 'Wide (2×1)', 'big' => 'Big (2×2)' ],
			'default' => 'std',
		] );
		$this->add_control( 'items', [
			'label' => 'Tiles', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ alt }}}',
			'default' => [],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		if ( empty( $s['items'] ) ) return;
		echo '<div class="verto-collage">';
		foreach ( $s['items'] as $i => $tile ) {
			if ( empty( $tile['image']['url'] ) ) continue;
			$size_class = [ 'big' => ' verto-collage__tile--big', 'wide' => ' verto-collage__tile--wide' ][ $tile['size'] ?? 'std' ] ?? '';
			printf(
				'<div class="verto-collage__tile%s" style="--tile-delay:%dms;">',
				esc_attr( $size_class ),
				(int) $i * 90
			);
			printf(
				'<img src="%s" alt="%s" loading="lazy" />',
				esc_url( $tile['image']['url'] ),
				esc_attr( $tile['alt'] ?? '' )
			);
			if ( ! empty( $tile['caption'] ) ) {
				echo '<div class="verto-collage__scrim" aria-hidden="true"></div>';
				printf( '<div class="verto-collage__caption">%s</div>', esc_html( $tile['caption'] ) );
			}
			echo '</div>';
		}
		echo '</div>';
	}
}
