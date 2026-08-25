<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Sector Coverage — per-brand desk lists (prototype SectorCoverage):
 * intro left (4/12), brand groups right (8/12). Each group: colour-coded
 * header rule with dot + wordmark + descriptor + enter-link, then a
 * two-column list of desks with brand tick bars.
 *
 * Client feedback round 2 (img004): Edison Lux reads as Edison — Energy
 * Green marks, with the small text link darkened for contrast on ivory.
 */
class Verto_Widget_Sector_Coverage extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-sector-coverage'; }
	public function get_title() { return 'Verto Sector Coverage'; }
	public function get_icon() { return 'eicon-bullet-list'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Sector coverage' ] );
		$this->add_control( 'eyebrow', [ 'label' => 'Eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Sector coverage' ] );
		$this->add_control( 'line1', [ 'label' => 'Heading line 1', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => "Whatever you're building," ] );
		$this->add_control( 'line2', [ 'label' => 'Heading line 2', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'we know who builds it.' ] );
		$this->add_control( 'body1', [
			'label' => 'Body paragraph 1', 'type' => \Elementor\Controls_Manager::TEXTAREA,
			'default' => "Every consultant at Verto is a former operator, engineer or in-market recruiter — not a generalist. The sectors below aren't categories on a website; they're desks that ship hires every month.",
		] );
		$this->add_control( 'body2', [
			'label' => 'Body paragraph 2', 'type' => \Elementor\Controls_Manager::TEXTAREA,
			'default' => 'Each links through to the brand that owns it. Our life sciences desk sits with the group while it grows.',
		] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'wordmark', [ 'label' => 'Wordmark', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'descriptor', [ 'label' => 'Descriptor', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'color', [ 'label' => 'Brand colour (marks & rule)', 'type' => \Elementor\Controls_Manager::COLOR ] );
		$rep->add_control( 'link_color', [ 'label' => 'Link text colour (defaults to brand colour)', 'type' => \Elementor\Controls_Manager::COLOR ] );
		$rep->add_control( 'link_text', [ 'label' => 'Link text', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'link', [ 'label' => 'Link', 'type' => \Elementor\Controls_Manager::URL ] );
		$rep->add_control( 'items', [ 'label' => 'Desks (one per line)', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$this->add_control( 'groups', [
			'label' => 'Brand groups', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ wordmark }}}',
			'default' => [
				[ 'wordmark' => 'EDISON LUX', 'descriptor' => 'Power & Energy',
				  'color' => '#3CC739', 'link_color' => '#23761A',
				  'link_text' => 'Enter Edison Lux', 'link' => [ 'url' => '#' ],
				  'items' => "Critical Power & CCGT\nRenewables & Storage\nEPC & Project Delivery\nO&M (Operations & Maintenance)" ],
				[ 'wordmark' => 'VERTEK', 'descriptor' => 'Engineering, Sales & Manufacturing',
				  'color' => '#F82B60',
				  'link_text' => 'Enter Vertek', 'link' => [ 'url' => '#' ],
				  'items' => "Fluid Power & Hydraulics\nHVAC & Refrigeration\nAdvanced Manufacturing\nInstrumentation & Controls" ],
				[ 'wordmark' => 'MODULR', 'descriptor' => 'Built Environment',
				  'color' => '#0464FA',
				  'link_text' => 'Enter Modulr', 'link' => [ 'url' => '#' ],
				  'items' => "Hyperscale Data Centres\nUS Architecture\nMEP Engineering\nInterior Design & Fit-out" ],
				[ 'wordmark' => 'VERTO GROUP', 'descriptor' => 'Life Sciences — held at group level',
				  'color' => '#d19f2f',
				  'link_text' => 'Talk to the group', 'link' => [ 'url' => '/contact' ],
				  'items' => "Drug Development\nClinical Operations\nBiometrics & Data\nCommercial & Medical Affairs" ],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="verto-sectors">
			<div class="verto-sectors__intro">
				<span class="verto-eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span>
				<h2 class="verto-title-reveal verto-display-2" style="margin-top:1.25rem;">
					<span class="line-mask"><span class="line-inner"><?php echo esc_html( $s['line1'] ); ?></span></span>
					<span class="line-mask"><span class="line-inner"><?php echo esc_html( $s['line2'] ); ?></span></span>
				</h2>
				<p class="verto-sectors__body"><?php echo esc_html( $s['body1'] ); ?></p>
				<p class="verto-sectors__body"><?php echo esc_html( $s['body2'] ); ?></p>
			</div>
			<div class="verto-sectors__groups">
				<?php foreach ( $s['groups'] as $g ) :
					$color = $g['color'] ?: 'var(--accent)';
					$link_color = $g['link_color'] ?: $color;
					$url   = $g['link']['url'] ?? '#';
					$items = array_filter( array_map( 'trim', explode( "\n", $g['items'] ?? '' ) ) );
					?>
					<div class="verto-sectors__group">
						<div class="verto-sectors__head" style="border-bottom:1px solid <?php echo esc_attr( $color ); ?>;">
							<div class="verto-sectors__id">
								<span class="verto-sectors__dot" style="background:<?php echo esc_attr( $color ); ?>;"></span>
								<span class="verto-sectors__wordmark"><?php echo esc_html( $g['wordmark'] ); ?></span>
								<span class="verto-sectors__descriptor"><?php echo esc_html( $g['descriptor'] ); ?></span>
							</div>
							<?php if ( ! empty( $g['link_text'] ) ) : ?>
								<a class="verto-sectors__enter" href="<?php echo esc_url( $url ); ?>" style="color:<?php echo esc_attr( $link_color ); ?>;"><?php echo esc_html( $g['link_text'] ); ?> ↗</a>
							<?php endif; ?>
						</div>
						<div class="verto-sectors__grid">
							<?php foreach ( $items as $i => $item ) : ?>
								<a class="verto-sectors__item<?php echo $i < 2 ? ' verto-sectors__item--first' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
									<span class="verto-sectors__label"><span class="verto-sectors__tick" style="background:<?php echo esc_attr( $color ); ?>;"></span><?php echo esc_html( $item ); ?></span>
									<span class="verto-sectors__arrow" style="color:<?php echo esc_attr( $color ); ?>;">↗</span>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
