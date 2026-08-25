<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Values Accordion — the brand-about "Our values" section
 * (brands.$brand.about.tsx): sticky intro column left, numbered
 * <details> accordion right with brand-tinted rules and a rotating
 * plus glyph. First value open by default. Prefilled with Vertek's
 * values (the only brand carrying `values` in the prototype data).
 */
class Verto_Widget_Values_Accordion extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-values-accordion'; }
	public function get_title() { return 'Verto Values Accordion'; }
	public function get_icon() { return 'eicon-accordion'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Values' ] );
		$this->add_control( 'eyebrow', [ 'label' => 'Eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Our values' ] );
		$this->add_control( 'heading', [ 'label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'What we stand for.' ] );
		$this->add_control( 'body', [
			'label' => 'Intro body', 'type' => \Elementor\Controls_Manager::TEXTAREA,
			'default' => 'Five principles that shape every conversation, every search and every introduction. The reason 94% of our clients hire with us a second time.',
		] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'title', [ 'label' => 'Value', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'body', [ 'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$this->add_control( 'items', [
			'label' => 'Values', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ title }}}',
			'default' => [
				[ 'title' => 'Straightforward. No overpromising.',
				  'body'  => "We say what we mean and mean what we say. Candidates are sold on their merits, feedback is honest, and we never promise what we can't deliver." ],
				[ 'title' => 'Process over chance.',
				  'body'  => 'Great recruitment isn\'t luck. Our methodology has been built over years to get it right first time — frequent updates, thorough briefings, structure that removes failure at every stage.' ],
				[ 'title' => 'An extension of your team.',
				  'body'  => '94% of our clients work with us again. We understand the business properly, represent it well and build relationships that outlast a single hire.' ],
				[ 'title' => 'High-conviction introductions.',
				  'body'  => "We sell the opportunity as hard as we'd want someone to sell ours. The right candidates come energised, not just informed." ],
				[ 'title' => 'Product knowledge, non-negotiable.',
				  'body'  => "Every consultant specialises in a product area. We don't generalise across engineering because our clients and candidates don't — and neither should we." ],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="vbs-valacc">
			<div class="container-wide">
				<div class="vbs-valacc__grid">
					<div class="vbs-valacc__intro">
						<?php if ( $s['eyebrow'] ) : ?><span class="eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span><?php endif; ?>
						<h2 class="display-2 vbs-mt5"><?php echo esc_html( $s['heading'] ); ?></h2>
						<?php if ( $s['body'] ) : ?><p class="vbs-valacc__body"><?php echo esc_html( $s['body'] ); ?></p><?php endif; ?>
					</div>
					<div class="vbs-valacc__list" style="border-color:color-mix(in oklab, var(--brand) 25%, transparent);">
						<?php foreach ( $s['items'] as $i => $it ) : ?>
							<details class="vbs-valacc__item" style="border-color:color-mix(in oklab, var(--brand) 25%, transparent);"<?php echo 0 === $i ? ' open' : ''; ?>>
								<summary class="vbs-valacc__summary">
									<span class="vbs-valacc__num" style="color:var(--brand);">0<?php echo (int) $i + 1; ?></span>
									<h3 class="vbs-valacc__title"><?php echo esc_html( $it['title'] ); ?></h3>
									<span class="vbs-valacc__plus" aria-hidden="true">
										<span style="background:var(--brand);"></span>
										<span style="background:var(--brand);"></span>
									</span>
								</summary>
								<div class="vbs-valacc__panel">
									<p><?php echo esc_html( $it['body'] ); ?></p>
								</div>
							</details>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
