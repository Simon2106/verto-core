<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Logo Marquee — "Trusted by" infinite logo scroller (prototype
 * LogoMarquee): edge-masked track, 45s linear loop, pause on hover,
 * grayscale logos at 60% opacity that lift to 100% on hover.
 * Items are logo.dev domains (placeholder logos) or uploaded images.
 */
class Verto_Widget_Logo_Marquee extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-logo-marquee'; }
	public function get_title() { return 'Verto Logo Marquee'; }
	public function get_icon() { return 'eicon-slider-push'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Logos' ] );
		$this->add_control( 'label', [ 'label' => 'Label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Trusted by' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'domain', [ 'label' => 'Company domain (logo.dev placeholder)', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'image', [ 'label' => 'Logo image (overrides domain)', 'type' => \Elementor\Controls_Manager::MEDIA ] );
		$this->add_control( 'items', [
			'label' => 'Logos', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ domain }}}',
			'default' => [
				[ 'domain' => 'siemens.com' ], [ 'domain' => 'ge.com' ], [ 'domain' => 'bechtel.com' ],
				[ 'domain' => 'fluor.com' ], [ 'domain' => 'aecom.com' ], [ 'domain' => 'jacobs.com' ],
				[ 'domain' => 'nextera.com' ], [ 'domain' => 'duke-energy.com' ], [ 'domain' => 'vestas.com' ],
				[ 'domain' => 'orsted.com' ], [ 'domain' => 'eaton.com' ], [ 'domain' => 'abb.com' ],
				[ 'domain' => 'schneider-electric.com' ], [ 'domain' => 'mitsubishipower.com' ],
				[ 'domain' => 'kiewit.com' ], [ 'domain' => 'blackandveatch.com' ],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$items = array_merge( $s['items'], $s['items'] ); // doubled for the seamless -50% loop
		?>
		<div class="vbs-marquee" style="background:var(--background);border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
			<div class="container-wide vbs-marquee__head">
				<div class="vbs-asplit__kick">
					<span class="vbs-dash" style="background:var(--brand);"></span>
					<span class="vbs-kicker" style="color:var(--brand);"><?php echo esc_html( $s['label'] ); ?></span>
				</div>
			</div>
			<div class="logo-marquee">
				<div class="logo-marquee-track">
					<?php foreach ( $items as $i => $it ) :
						$src = ! empty( $it['image']['url'] )
							? $it['image']['url']
							: 'https://img.logo.dev/' . rawurlencode( $it['domain'] ) . '?size=200&format=png&greyscale=true&fallback=monogram';
						?>
						<div class="logo-marquee-item">
							<img src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( $it['domain'] ?: 'client' ); ?> logo" loading="lazy" class="vbs-marquee__logo" style="filter:grayscale(100%);" />
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
