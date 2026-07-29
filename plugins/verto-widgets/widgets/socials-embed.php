<?php
defined( 'ABSPATH' ) || exit;

/** Verto Socials — intro + Instagram profile embed (@verto_people). */
class Verto_Widget_Socials extends \Elementor\Widget_Base {
	public function get_name() { return 'verto-socials'; }
	public function get_title() { return 'Verto Socials Feed'; }
	public function get_icon() { return 'eicon-instagram-gallery'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Socials' ] );
		$this->add_control( 'eyebrow', [ 'label' => 'Eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Life at Verto' ] );
		$this->add_control( 'heading', [ 'label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'The moments between the meetings.' ] );
		$this->add_control( 'body', [ 'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'Awards, incentive trips, sales days and the occasional inflatable — what working here actually looks like.' ] );
		$this->add_control( 'handle', [ 'label' => 'Instagram handle', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'verto_people' ] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$h = sanitize_title( $s['handle'] );
		?>
		<div class="verto-socials">
			<div>
				<span class="verto-eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span>
				<h2 class="verto-title-reveal verto-display-3" style="margin-top:1.25rem;"><span class="line-mask"><span class="line-inner"><?php echo esc_html( $s['heading'] ); ?></span></span></h2>
				<p class="verto-intro__body"><?php echo esc_html( $s['body'] ); ?></p>
				<a class="verto-socials__btn" href="https://www.instagram.com/<?php echo esc_attr( $h ); ?>/" target="_blank" rel="noopener">Follow @<?php echo esc_html( $h ); ?></a>
			</div>
			<div class="verto-socials__frame">
				<iframe src="https://www.instagram.com/<?php echo esc_attr( $h ); ?>/embed" title="Instagram" loading="lazy"></iframe>
			</div>
		</div>
		<?php
	}
}
