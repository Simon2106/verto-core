<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Proof List — "Proof points" section (prototype about page):
 * muted background, numbered 01… items in a 2-col grid where every second
 * item drops 2rem.
 */
class Verto_Widget_Proof_List extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-proof-list'; }
	public function get_title() { return 'Verto Proof List'; }
	public function get_icon() { return 'eicon-number-field'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Proof' ] );
		$this->add_control( 'eyebrow', [ 'label' => 'Eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Proof points' ] );
		$this->add_control( 'heading', [ 'label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Why clients hire us a second time.' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'text', [ 'label' => 'Proof point', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 2 ] );
		$this->add_control( 'items', [
			'label' => 'Items', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ text }}}',
			'default' => [
				[ 'text' => 'Trusted by global operators, developers and celebrated US practices' ],
				[ 'text' => 'Active networks across the UK, EU and US markets' ],
				[ 'text' => 'NDA-grade discretion on every sensitive and pre-announcement search' ],
				[ 'text' => 'Inclusion work championing women in architecture and EDI in technical built-environment roles' ],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="vbs-proof" style="background:var(--muted);">
			<div class="container-wide">
				<div class="vbs-proof__head">
					<span class="eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span>
					<h2 class="display-3 vbs-mt5"><?php echo esc_html( $s['heading'] ); ?></h2>
				</div>
				<ul class="vbs-proof__grid">
					<?php foreach ( $s['items'] as $i => $it ) : ?>
						<li class="vbs-proof__item<?php echo 1 === $i % 2 ? ' vbs-proof__item--drop' : ''; ?>">
							<div class="vbs-proof__num" style="color:var(--brand);">0<?php echo (int) $i + 1; ?></div>
							<p class="vbs-proof__text"><?php echo esc_html( $it['text'] ); ?></p>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}
}
