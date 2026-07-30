<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Chip Grid — dense sectors grid (prototype "Where we recruit"):
 * 1px brand-tinted grout via gap-px on an 18% brand mix, cells on the page
 * background with a faded 01… index and the sector name.
 */
class Verto_Widget_Chip_Grid extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-chip-grid'; }
	public function get_title() { return 'Verto Chip Grid'; }
	public function get_icon() { return 'eicon-gallery-group'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Sectors' ] );
		$this->add_control( 'eyebrow', [ 'label' => 'Eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Where we recruit' ] );
		$this->add_control( 'heading', [ 'label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Specialists across the industries we serve.' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'label', [ 'label' => 'Sector', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$this->add_control( 'items', [
			'label' => 'Sectors', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ label }}}',
			// ModulR has no sectorsServed in the prototype data; these Vertek
			// sectors are the prototype's only real chip-grid content.
			'default' => [
				[ 'label' => 'Fluid power & flow control' ],
				[ 'label' => 'HVAC & refrigeration' ],
				[ 'label' => 'Rotating equipment & turbomachinery' ],
				[ 'label' => 'CNC & precision engineering (US)' ],
				[ 'label' => 'Industrial automation (US)' ],
				[ 'label' => 'Advanced manufacturing (US)' ],
				[ 'label' => 'MRO & aftermarket' ],
				[ 'label' => 'Commercial leadership (VP / GM / Director)' ],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="vbs-chips">
			<div class="container-wide">
				<div class="vbs-chips__head">
					<div>
						<span class="eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span>
						<h2 class="display-2 vbs-mt5"><?php echo esc_html( $s['heading'] ); ?></h2>
					</div>
				</div>
				<div class="vbs-chips__frame" style="background:color-mix(in oklab, var(--brand) 18%, transparent);">
					<div class="vbs-chips__grid">
						<?php foreach ( $s['items'] as $i => $it ) : ?>
							<div class="vbs-chips__cell" style="background:var(--background);">
								<span class="vbs-chips__num">0<?php echo (int) $i + 1; ?></span>
								<span><?php echo esc_html( $it['label'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
