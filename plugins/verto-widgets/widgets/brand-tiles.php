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
		$rep->add_control( 'light_face', [
			'label' => 'Light face (white tile for a coloured logo)', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes',
			'description' => 'White/very-light front face with dark text and no top stripe — for coloured logos that clash on dark or gradient grounds (e.g. the Edison Lux gradient mark).',
		] );
		$rep->add_control( 'bg', [ 'label' => 'Tile background (brand ink)', 'type' => \Elementor\Controls_Manager::COLOR ] );
		$rep->add_control( 'face_gradient', [
			'label' => 'Face gradient (CSS, optional)', 'type' => \Elementor\Controls_Manager::TEXT,
			'description' => 'Full CSS gradient for the tile front face, e.g. Edison Lux linear-gradient(90deg, #3CC739 0%, #2B8EE5 100%). Overrides the ink background.',
		] );
		$rep->add_control( 'sectors', [
			'label' => 'Sector coverage (one per line)', 'type' => \Elementor\Controls_Manager::TEXTAREA,
			'description' => 'Shown on the hover (back) face of the tile.',
		] );
		$rep->add_control( 'link', [ 'label' => 'Link', 'type' => \Elementor\Controls_Manager::URL ] );
		$this->add_control( 'items', [
			'label' => 'Tiles', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ name }}}',
			'default' => [
				[ 'name' => 'Edison Lux', 'focus' => 'US Energy Staffing', 'color' => '#2B8EE5', 'bg' => '#0B1A2B',
				  // Coloured gradient-mark logo → white face (round 3, item 2).
				  'light_face' => 'yes',
				  'sectors' => "Critical Power & CCGT\nRenewables & Storage\nEPC & Project Delivery\nO&M (Operations & Maintenance)",
				  'positioning' => 'Edison Lux delivers talent solutions for the US energy sector — from control room operators to the C-suite leaders responsible for billion-dollar assets. One market. Done properly.' ],
				[ 'name' => 'ModulR', 'focus' => 'Architecture & Data Centres', 'color' => '#0464FA', 'bg' => '#000724',
				  'sectors' => "Hyperscale Data Centres\nUS Architecture\nMEP Engineering\nInterior Design & Fit-out",
				  'positioning' => "ModulR connects standout architecture and data centre professionals with the built environment's most ambitious work — hyperscale campuses and award-winning practices." ],
				[ 'name' => 'Vertek', 'focus' => 'Technical Sales, Service & Engineering', 'color' => '#F82B60', 'bg' => '#0E1013',
				  'sectors' => "Fluid Power & Hydraulics\nHVAC & Refrigeration\nAdvanced Manufacturing\nInstrumentation & Controls",
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
			/* Glow stays baked into the face background — a separate glow layer
			   composites as a solid block during the 3D flip. A brand gradient
			   replaces the ink ground when set, with a soft navy vignette keeping
			   white content legible. A light face (round 3, item 2 — Edison Lux's
			   coloured gradient-mark logo) uses a white/very-light ground with
			   dark text and no top stripe instead. */
			$light = ( $t['light_face'] ?? '' ) === 'yes';
			if ( $light ) {
				$face_bg = 'linear-gradient(180deg, #FFFFFF 0%, #F2F6F4 100%)';
			} elseif ( ! empty( $t['face_gradient'] ) ) {
				$face_bg = 'radial-gradient(ellipse 70% 55% at 50% 55%, rgba(11,26,43,0.5) 0%, transparent 75%), ' . $t['face_gradient'];
			} else {
				$face_bg = 'radial-gradient(ellipse 70% 50% at 50% 55%, color-mix(in oklab, ' . $color . ' 14%, transparent) 0%, transparent 70%) ' . $bg;
			}
			$sectors = array_filter( array_map( 'trim', explode( "\n", $t['sectors'] ?? '' ) ) );
			?>
			<a class="verto-tile" href="<?php echo esc_url( $url ); ?>" aria-label="Enter <?php echo esc_attr( $t['name'] ); ?>">
				<div class="verto-tile__flip">
					<div class="verto-tile__face verto-tile__face--front<?php echo $light ? ' verto-tile__face--light' : ''; ?>" style="background:<?php echo esc_attr( $face_bg ); ?>;">
						<?php if ( ! $light ) : ?>
							<div class="verto-tile__stripe" style="background:<?php echo esc_attr( $color ); ?>;"></div>
						<?php endif; ?>
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
						<?php if ( $sectors ) : ?>
							<div class="verto-tile__seclabel">Sector coverage</div>
							<ul class="verto-tile__sectors">
								<?php foreach ( $sectors as $sec ) : ?>
									<li><span class="verto-tile__tick" style="background:<?php echo esc_attr( $color ); ?>;"></span><?php echo esc_html( $sec ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<div class="verto-tile__cta" style="color:<?php echo esc_attr( $color ); ?>;">Enter <?php echo esc_html( $t['name'] ); ?> →</div>
					</div>
				</div>
			</a>
			<?php
		}
		echo '</div>';
	}
}
