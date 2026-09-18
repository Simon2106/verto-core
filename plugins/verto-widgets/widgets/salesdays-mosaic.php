<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Sales Days Mosaic – ten compact portrait film tiles in one dense
 * hover-to-play grid (careers page). Client brief, Sep 2026: "Sales days
 * are massive for us and something we've been leading in the local area.
 * Don't want massive video windows but if we could show these somehow all
 * on one section and hover over to play."
 *
 * Performance contract: only the poster JPGs load with the page. Every
 * <video> ships preload="none" with its file in data-src – the JS module
 * (verto-effects.js §13) attaches the src on the first hover/tap only,
 * plays muted on hover for fine pointers (pause + rewind on leave),
 * tap-to-toggle on touch, click-to-play under prefers-reduced-motion,
 * and caps concurrent playback at two tiles.
 */
class Verto_Widget_Salesdays_Mosaic extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-salesdays-mosaic'; }
	public function get_title() { return 'Verto Sales Days Mosaic'; }
	public function get_icon() { return 'eicon-gallery-grid'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Films' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'video', [
			'label' => 'Video (mp4, muted loop)', 'type' => \Elementor\Controls_Manager::MEDIA,
			'media_types' => [ 'video' ],
		] );
		$rep->add_control( 'poster', [ 'label' => 'Poster frame', 'type' => \Elementor\Controls_Manager::MEDIA ] );
		$rep->add_control( 'label', [ 'label' => 'Accessible label', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$this->add_control( 'items', [
			'label' => 'Films', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ label }}}',
			'default' => [],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		if ( empty( $s['items'] ) ) return;
		echo '<div class="verto-salesdays" data-verto-salesdays>';
		echo '<div class="verto-salesdays__grid">';
		$first = true;
		foreach ( $s['items'] as $item ) {
			if ( empty( $item['video']['url'] ) ) continue;
			$label  = ! empty( $item['label'] ) ? $item['label'] : 'Sales day film';
			$poster = empty( $item['poster']['url'] ) ? '' : $item['poster']['url'];
			echo '<figure class="verto-salesdays__tile" data-salesday-tile>';
			if ( $poster ) {
				printf(
					'<img class="verto-salesdays__poster" src="%s" alt="%s" loading="lazy" />',
					esc_url( $poster ), esc_attr( $label )
				);
			}
			// No src on purpose: preload="none" alone still lets some browsers
			// probe the file – data-src keeps the page at zero video bytes
			// until the JS attaches it on the first hover/tap.
			printf(
				'<video class="verto-salesdays__video" muted playsinline loop preload="none"%s data-src="%s" aria-label="%s" tabindex="-1"></video>',
				$poster ? ' poster="' . esc_url( $poster ) . '"' : '',
				esc_url( $item['video']['url'] ),
				esc_attr( $label )
			);
			if ( $first ) {
				echo '<span class="verto-salesdays__hint" data-salesday-hint aria-hidden="true">Hover to play</span>';
				$first = false;
			}
			echo '</figure>';
		}
		echo '</div></div>';
	}
}
