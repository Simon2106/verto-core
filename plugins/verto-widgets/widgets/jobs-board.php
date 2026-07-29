<?php
defined( 'ABSPATH' ) || exit;

/**
 * Jobs Board wrapper — renders the styled shell (heading, layout classes)
 * around the Vincere feed. The inner content is whatever the chosen
 * Vincere→WP integration outputs (shortcode set in the widget), wrapped in
 * .verto-jobs so the prototype's skin applies.
 *
 * ⚠️ Until the client confirms the Vincere plugin/licence, the shortcode
 * field can be left empty and the widget shows a placeholder.
 */
class Verto_Widget_Jobs_Board extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-jobs-board'; }
	public function get_title() { return 'Verto Jobs Board (Vincere)'; }
	public function get_icon() { return 'eicon-post-list'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Jobs' ] );
		$this->add_control( 'heading', [
			'label'   => 'Heading',
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => "Roles we're hiring now.",
		] );
		$this->add_control( 'vincere_shortcode', [
			'label'       => 'Vincere shortcode',
			'type'        => \Elementor\Controls_Manager::TEXT,
			'description' => 'Paste the shortcode from the Vincere→WordPress plugin once installed, e.g. [vincere_jobs]. Leave empty for placeholder.',
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="verto-jobs">';
		printf( '<h2 class="verto-title-reveal"><span class="line-mask"><span class="line-inner">%s</span></span></h2>', esc_html( $s['heading'] ) );
		echo '<div class="verto-jobs__layout">';
		echo '<div class="verto-jobs__list">';
		if ( ! empty( $s['vincere_shortcode'] ) ) {
			echo do_shortcode( wp_kses_post( $s['vincere_shortcode'] ) );
		} else {
			echo '<p style="opacity:.7">⚠️ Vincere feed placeholder — set the plugin shortcode in this widget once the integration is installed.</p>';
		}
		echo '</div>';
		echo '<aside class="verto-jobs__filters"><!-- Vincere plugin filters render here or get moved via its template settings --></aside>';
		echo '</div></div>';
	}
}
