<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Brand Hero — brand-landing hero ported from the prototype
 * (brands.$brand.index.tsx): ink background, parallax image on the right
 * (78% width on desktop) behind a left→right ink gradient wash, uppercase
 * display-2 headline with a brand-coloured full stop, sub paragraph and
 * two CTAs (solid brand + outline).
 */
class Verto_Widget_Brand_Hero extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-brand-hero'; }
	public function get_title() { return 'Verto Brand Hero'; }
	public function get_icon() { return 'eicon-banner'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Hero' ] );
		$this->add_control( 'line1', [ 'label' => 'Headline line 1', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Connecting talent.' ] );
		$this->add_control( 'line2', [ 'label' => 'Headline line 2 (brand full stop is appended)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Powering progress' ] );
		$this->add_control( 'sub', [ 'label' => 'Sub paragraph', 'type' => \Elementor\Controls_Manager::TEXTAREA,
			'default' => "Modulr connects standout architecture and data centre professionals with the built environment's most ambitious work — hyperscale campuses, award-winning practices and the projects you won't find advertised." ] );
		$this->add_control( 'image', [ 'label' => 'Hero image', 'type' => \Elementor\Controls_Manager::MEDIA ] );
		$this->add_control( 'image_alt', [ 'label' => 'Image alt', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Glowing globe at night with arcs of light connecting cities' ] );
		$this->add_control( 'object_position', [ 'label' => 'Image object-position', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'center 35%' ] );
		$this->add_control( 'parallax_scale', [ 'label' => 'Parallax scale', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 1, 'step' => 0.01 ] );
		$this->add_control( 'parallax_offset', [ 'label' => 'Parallax Y offset (px)', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 30 ] );
		$this->add_control( 'cta1_text', [ 'label' => 'CTA 1 (solid)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Our Solutions' ] );
		$this->add_control( 'cta1_link', [ 'label' => 'CTA 1 link', 'type' => \Elementor\Controls_Manager::URL, 'default' => [ 'url' => '/clients' ] ] );
		$this->add_control( 'cta2_text', [ 'label' => 'CTA 2 (outline)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Discover ModulR' ] );
		$this->add_control( 'cta2_link', [ 'label' => 'CTA 2 link', 'type' => \Elementor\Controls_Manager::URL, 'default' => [ 'url' => '/about' ] ] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="vbs-hero">
			<?php if ( ! empty( $s['image']['url'] ) ) : ?>
			<div class="vbs-hero__media">
				<div class="verto-parallax" data-parallax-speed="0.25" data-parallax-scale="<?php echo esc_attr( $s['parallax_scale'] !== '' ? $s['parallax_scale'] : 1 ); ?>" data-parallax-offset="<?php echo esc_attr( $s['parallax_offset'] !== '' ? $s['parallax_offset'] : 0 ); ?>">
					<img src="<?php echo esc_url( $s['image']['url'] ); ?>" alt="<?php echo esc_attr( $s['image_alt'] ); ?>" loading="lazy" style="object-position:<?php echo esc_attr( $s['object_position'] ?: 'center center' ); ?>;" />
				</div>
				<div class="vbs-hero__wash"></div>
			</div>
			<?php endif; ?>
			<div class="container-wide vbs-hero__inner">
				<div class="vbs-hero__copy">
					<h1 class="display-2 vbs-hero__title" style="font-weight:800;">
						<?php echo esc_html( $s['line1'] ); ?><br /><?php echo esc_html( $s['line2'] ); ?><span style="color:var(--brand);">.</span>
					</h1>
					<?php if ( $s['sub'] ) : ?><p class="vbs-hero__sub"><?php echo esc_html( $s['sub'] ); ?></p><?php endif; ?>
					<div class="vbs-hero__ctas">
						<?php if ( $s['cta1_text'] ) : ?>
							<a class="btn-base btn-primary vbs-hero__cta1" href="<?php echo esc_url( $s['cta1_link']['url'] ?? '#' ); ?>"><?php echo esc_html( $s['cta1_text'] ); ?></a>
						<?php endif; ?>
						<?php if ( $s['cta2_text'] ) : ?>
							<a class="vbs-hero__cta2" style="border-color:var(--brand);color:var(--ink-foreground);" href="<?php echo esc_url( $s['cta2_link']['url'] ?? '#' ); ?>"><?php echo esc_html( $s['cta2_text'] ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
