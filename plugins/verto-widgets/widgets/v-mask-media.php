<?php
defined( 'ABSPATH' ) || exit;

/**
 * V-Mask Media — clips a video or image inside the Verto "V" mark
 * (multi-polygon SVG used as a CSS mask). Videos autoplay muted/looped
 * via verto-effects.js (.verto-autoplay).
 */
class Verto_Widget_V_Mask_Media extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-v-mask-media'; }
	public function get_title() { return 'Verto V-Mask Media'; }
	public function get_icon() { return 'eicon-play'; }
	public function get_categories() { return [ 'verto' ]; }

	/** The Verto V mark as a data-URI SVG mask (from brand artwork). */
	private function mask_url(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 81 80"><g fill="#fff">'
			. '<polygon points="48.81,66.34 43.18,76.08 1.64,4.14 12.9,4.14 34.11,40.89 37.08,46.04 48.81,66.34"/>'
			. '<polygon points="37.59,66.34 43.22,76.08 79.35,13.51 68.09,13.51 52.28,40.89 49.31,46.04 37.59,66.34"/>'
			. '<polygon points="48.81,39.2 43.2,48.91 43.18,48.94 22.72,13.51 33.97,13.51 34.11,13.75 37.08,18.9 43.2,29.49 48.81,39.2"/>'
			. '<polygon points="69.09,4.14 43.22,48.94 43.2,48.91 37.59,39.2 43.2,29.49 49.31,18.9 52.28,13.75 57.83,4.14 69.09,4.14"/>'
			. '</g></svg>';
		return "url('data:image/svg+xml," . rawurlencode( $svg ) . "')";
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Media' ] );
		$this->add_control( 'media_type', [
			'label'   => 'Type',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'video' => 'Video', 'image' => 'Image' ],
			'default' => 'video',
		] );
		$this->add_control( 'video', [
			'label'      => 'Video (mp4)',
			'type'       => \Elementor\Controls_Manager::MEDIA,
			'media_types'=> [ 'video' ],
			'condition'  => [ 'media_type' => 'video' ],
		] );
		$this->add_control( 'poster', [
			'label'     => 'Poster image',
			'type'      => \Elementor\Controls_Manager::MEDIA,
			'condition' => [ 'media_type' => 'video' ],
		] );
		$this->add_control( 'image', [
			'label'     => 'Image',
			'type'      => \Elementor\Controls_Manager::MEDIA,
			'condition' => [ 'media_type' => 'image' ],
		] );
		$this->add_responsive_control( 'height', [
			'label'      => 'Height',
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px', 'vh' ],
			'range'      => [ 'px' => [ 'min' => 200, 'max' => 1200 ], 'vh' => [ 'min' => 20, 'max' => 100 ] ],
			'default'    => [ 'size' => 70, 'unit' => 'vh' ],
			'selectors'  => [ '{{WRAPPER}} .verto-v-mask' => 'height: {{SIZE}}{{UNIT}};' ],
		] );
		$this->add_control( 'overlay_opacity', [
			'label'   => 'Ink overlay %',
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
			'default' => [ 'size' => 20 ],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$mask    = $this->mask_url();
		$overlay = (int) ( $s['overlay_opacity']['size'] ?? 20 );

		echo '<div class="verto-v-mask">';
		printf( '<div class="verto-v-mask__media" style="-webkit-mask-image:%1$s;mask-image:%1$s;">', $mask );

		if ( 'video' === $s['media_type'] && ! empty( $s['video']['url'] ) ) {
			printf(
				'<video class="verto-autoplay" src="%s"%s autoplay muted loop playsinline preload="auto" aria-label="Brand film"></video>',
				esc_url( $s['video']['url'] ),
				! empty( $s['poster']['url'] ) ? ' poster="' . esc_url( $s['poster']['url'] ) . '"' : ''
			);
		} elseif ( ! empty( $s['image']['url'] ) ) {
			printf( '<img src="%s" alt="" aria-hidden="true" loading="lazy" />', esc_url( $s['image']['url'] ) );
		}

		if ( $overlay > 0 ) {
			printf(
				'<div style="position:absolute;inset:0;background:color-mix(in oklab, var(--ink) %d%%, transparent);"></div>',
				$overlay
			);
		}
		echo '</div></div>';
	}
}
