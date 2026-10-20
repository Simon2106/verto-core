<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Proof List – "Proof points" section (prototype about page):
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
		// Oct 2026 (Edison): optional dash markers instead of the 01-04 numbers.
		$this->add_control( 'numbered', [
			'label' => 'Markers', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'yes' => 'Numbers (01…)', 'no' => 'Brand dash' ], 'default' => 'yes',
		] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'text', [ 'label' => 'Proof point', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 2 ] );
		$this->add_control( 'items', [
			'label' => 'Items', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ text }}}',
			'default' => [
				[ 'text' => 'Trusted by global operators, developers and celebrated US practices' ],
				[ 'text' => 'Active networks across the US and EU markets' ],
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
					<?php if ( $s['eyebrow'] ) : ?><span class="eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span><?php endif; ?>
					<h2 class="display-3 vbs-mt5"><?php echo esc_html( $s['heading'] ); ?></h2>
				</div>
				<?php // Round 9, item 14: clean 2-col grid – the alternating
				// 2rem drop is gone so the 01-04 numbers sit on one line.
				// Oct 2026 (Edison): numbers optional – brand dash instead.
				$numbered = 'no' !== ( $s['numbered'] ?? 'yes' ); ?>
				<ul class="vbs-proof__grid">
					<?php foreach ( $s['items'] as $i => $it ) : ?>
						<li class="vbs-proof__item">
							<?php if ( $numbered ) : ?>
								<div class="vbs-proof__num" style="color:var(--brand);">0<?php echo (int) $i + 1; ?></div>
							<?php else : ?>
								<?php // Oct 2026 (Alex, "stumped with the dashes"): gradient
							// tick badge instead. ?>
							<span class="vbs-proof__tick" style="background:var(--brand-gradient, var(--brand));" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="14" height="14" fill="none"><path d="M5 13l4 4L19 7" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
							</span>
							<?php endif; ?>
							<p class="vbs-proof__text"><?php echo esc_html( $it['text'] ); ?></p>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}
}
