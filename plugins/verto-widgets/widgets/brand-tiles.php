<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Brand Tiles — logo-led 3D flip cards for the sub-brands.
 * Front: brand logo + focus line on ink with brand-coloured glow/stripe.
 * Back: name, positioning copy, CTA. Colour-coded via per-item brand colour.
 */
class Verto_Widget_Brand_Tiles extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-brand-tiles'; }
	public function get_title() { return 'Verto Brand Tiles'; }
	public function get_icon() { return 'eicon-gallery-grid'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'tiles', [ 'label' => 'Brands' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'name', [ 'label' => 'Brand name', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'logo', [ 'label' => 'Logo (light version)', 'type' => \Elementor\Controls_Manager::MEDIA ] );
		$rep->add_control( 'focus', [ 'label' => 'Focus line', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'positioning', [ 'label' => 'Positioning copy', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$rep->add_control( 'color', [ 'label' => 'Brand colour', 'type' => \Elementor\Controls_Manager::COLOR ] );
		$rep->add_control( 'invert_logo', [ 'label' => 'Invert logo (for dark logos on the dark tile)', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes' ] );
		$rep->add_control( 'bg', [ 'label' => 'Tile background (brand ink)', 'type' => \Elementor\Controls_Manager::COLOR ] );
		$rep->add_control( 'link', [ 'label' => 'Link', 'type' => \Elementor\Controls_Manager::URL ] );
		$this->add_control( 'items', [
			'label' => 'Tiles', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ name }}}',
			'default' => [
				[ 'name' => 'Edison Lux', 'focus' => 'US Energy Staffing', 'color' => '#2B8EE5', 'bg' => '#0B1A2B',
				  'positioning' => 'Edison Lux delivers talent solutions for the US energy sector — from control room operators to the C-suite leaders responsible for billion-dollar assets. One market. Done properly.' ],
				[ 'name' => 'ModulR', 'focus' => 'Architecture & Data Centres', 'color' => '#0464FA', 'bg' => '#000724',
				  'positioning' => "ModulR connects standout architecture and data centre professionals with the built environment's most ambitious work — hyperscale campuses and award-winning practices." ],
				[ 'name' => 'Vertek', 'focus' => 'Technical Sales, Service & Engineering', 'color' => '#F82B60', 'bg' => '#0E1013',
				  'positioning' => 'Vertek recruits technical sales, service and engineering professionals for the manufacturers and distributors that keep industry moving — across the UK and US.' ],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="verto-tiles">';
		foreach ( $s['items'] as $t ) {
			$color = $t['color'] ?: 'var(--accent)';
			$bg    = $t['bg'] ?: 'var(--ink)';
			$url   = $t['link']['url'] ?? '#';
			?>
			<a class="verto-tile" href="<?php echo esc_url( $url ); ?>" aria-label="Enter <?php echo esc_attr( $t['name'] ); ?>">
				<div class="verto-tile__flip">
					<div class="verto-tile__face verto-tile__face--front" style="background:radial-gradient(ellipse 70% 50% at 50% 55%, color-mix(in oklab, <?php echo esc_attr( $color ); ?> 14%, transparent) 0%, transparent 70%) <?php echo esc_attr( $bg ); ?>;">
						<div class="verto-tile__stripe" style="background:<?php echo esc_attr( $color ); ?>;"></div>
						<?php if ( ! empty( $t['logo']['url'] ) ) : ?>
							<img class="verto-tile__logo<?php echo ( $t['invert_logo'] ?? '' ) === 'yes' ? ' verto-tile__logo--invert' : ''; ?>" src="<?php echo esc_url( $t['logo']['url'] ); ?>" alt="<?php echo esc_attr( $t['name'] ); ?> logo" loading="lazy" />
						<?php else : ?>
							<div class="verto-tile__name" style="position:relative;"><?php echo esc_html( $t['name'] ); ?></div>
						<?php endif; ?>
						<div class="verto-tile__focus"><?php echo esc_html( $t['focus'] ); ?></div>
					</div>
					<div class="verto-tile__face verto-tile__face--back" style="background:<?php echo esc_attr( $bg ); ?>;">
						<div class="verto-tile__stripe" style="background:<?php echo esc_attr( $color ); ?>;"></div>
						<div class="verto-tile__kicker" style="color:<?php echo esc_attr( $color ); ?>;"><?php echo esc_html( $t['focus'] ); ?></div>
						<div class="verto-tile__name"><?php echo esc_html( $t['name'] ); ?></div>
						<p class="verto-tile__body"><?php echo esc_html( $t['positioning'] ); ?></p>
						<div class="verto-tile__cta" style="color:<?php echo esc_attr( $color ); ?>;">Enter <?php echo esc_html( $t['name'] ); ?> →</div>
					</div>
				</div>
			</a>
			<?php
		}
		echo '</div>';
	}
}
