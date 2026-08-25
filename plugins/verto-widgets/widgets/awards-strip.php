<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Awards Strip — bordered card on ink: award badge left,
 * eyebrow + heading + supporting copy right.
 */
class Verto_Widget_Awards_Strip extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-awards-strip'; }
	public function get_title() { return 'Verto Awards Strip'; }
	public function get_icon() { return 'eicon-star'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Award' ] );
		$this->add_control( 'badge', [ 'label' => 'Badge image', 'type' => \Elementor\Controls_Manager::MEDIA ] );
		$this->add_control( 'badge2', [ 'label' => 'Second badge image (optional)', 'type' => \Elementor\Controls_Manager::MEDIA ] );
		$this->add_control( 'badge2_alt', [ 'label' => 'Second badge alt text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => "Recruiter Awards 2026 — We've been shortlisted" ] );
		$this->add_control( 'eyebrow', [ 'label' => 'Eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Company awards' ] );
		$this->add_control( 'heading', [ 'label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'The Sunday Times Best Places to Work 2026. Shortlisted, Recruiter Awards 2026.' ] );
		$this->add_control( 'body', [
			'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXTAREA,
			'default' => 'Alongside Best New Recruitment Agency of the Year at the British Recruitment Awards (2023), two category wins at the Business Awards UK (2023), Recruiter Awards shortlists in 2023 and 2026, and a finalist place at the News Business Excellence Awards (2024).',
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="verto-awards">
			<div class="verto-awards__badges">
				<?php if ( ! empty( $s['badge']['url'] ) ) : ?>
					<img class="verto-awards__badge" src="<?php echo esc_url( $s['badge']['url'] ); ?>" alt="<?php echo esc_attr( $s['heading'] ); ?>" loading="lazy" />
				<?php endif; ?>
				<?php if ( ! empty( $s['badge2']['url'] ) ) : ?>
					<img class="verto-awards__badge" src="<?php echo esc_url( $s['badge2']['url'] ); ?>" alt="<?php echo esc_attr( $s['badge2_alt'] ?? '' ); ?>" loading="lazy" />
				<?php endif; ?>
			</div>
			<div>
				<div class="verto-awards__eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></div>
				<h3 class="verto-awards__heading"><?php echo esc_html( $s['heading'] ); ?></h3>
				<p class="verto-awards__body"><?php echo esc_html( $s['body'] ); ?></p>
			</div>
		</div>
		<?php
	}
}
