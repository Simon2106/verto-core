<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto CTA Band — closing call to action (prototype about page):
 * rounded-3xl ink card, centred display-3 headline, solid + outline CTAs.
 */
class Verto_Widget_Cta_Band extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-cta-band'; }
	public function get_title() { return 'Verto CTA Band'; }
	public function get_icon() { return 'eicon-button'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'CTA' ] );
		$this->add_control( 'heading', [ 'label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Ready to talk?' ] );
		$this->add_control( 'cta1_text', [ 'label' => 'CTA 1 (solid)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Hire with Modulr' ] );
		$this->add_control( 'cta1_link', [ 'label' => 'CTA 1 link', 'type' => \Elementor\Controls_Manager::URL, 'default' => [ 'url' => '/clients' ] ] );
		$this->add_control( 'cta2_text', [ 'label' => 'CTA 2 (outline)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Explore roles' ] );
		$this->add_control( 'cta2_link', [ 'label' => 'CTA 2 link', 'type' => \Elementor\Controls_Manager::URL, 'default' => [ 'url' => '/candidates' ] ] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="container-wide vbs-ctaband">
			<div class="vbs-ctaband__card" style="background:var(--ink);color:var(--ink-foreground);">
				<h2 class="display-3 vbs-ctaband__heading"><?php echo esc_html( $s['heading'] ); ?></h2>
				<div class="vbs-ctaband__ctas">
					<?php if ( $s['cta1_text'] ) : ?>
						<a class="btn-base btn-primary" href="<?php echo esc_url( $s['cta1_link']['url'] ?? '#' ); ?>"><?php echo esc_html( $s['cta1_text'] ); ?></a>
					<?php endif; ?>
					<?php if ( $s['cta2_text'] ) : ?>
						<a class="btn-base btn-ghost-outline" style="color:var(--ink-foreground);border-color:color-mix(in oklab, var(--ink-foreground) 30%, transparent);" href="<?php echo esc_url( $s['cta2_link']['url'] ?? '#' ); ?>"><?php echo esc_html( $s['cta2_text'] ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
