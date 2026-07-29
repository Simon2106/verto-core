<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Team Grid — renders the "verto_team" custom post type (client
 * adds/edits people in wp-admin → Team). Two modes: leaders (large,
 * circular) or everyone.
 */
class Verto_Widget_Team_Grid extends \Elementor\Widget_Base {
	public function get_name() { return 'verto-team-grid'; }
	public function get_title() { return 'Verto Team Grid'; }
	public function get_icon() { return 'eicon-person'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Team' ] );
		$this->add_control( 'mode', [
			'label' => 'Mode', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'leaders' => 'Leadership (large)', 'all' => 'Everyone (compact)' ],
			'default' => 'all',
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$leaders = 'leaders' === $s['mode'];
		$q = new \WP_Query( [
			'post_type'      => 'verto_team',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'meta_query'     => $leaders
				? [ [ 'key' => '_verto_leader', 'value' => '1' ] ]
				: [],
		] );
		if ( ! $q->have_posts() ) {
			echo '<p style="opacity:.7">No team members yet — add them under Team in wp-admin.</p>';
			return;
		}
		printf( '<div class="verto-team%s">', $leaders ? ' verto-team--leaders' : '' );
		while ( $q->have_posts() ) {
			$q->the_post();
			$role = get_post_meta( get_the_ID(), '_verto_role', true ) ?: 'Consultant';
			echo '<div class="verto-team__card"><div class="verto-team__photo">';
			if ( has_post_thumbnail() ) { the_post_thumbnail( 'medium' ); }
			echo '</div>';
			printf( '<div class="verto-team__name">%s</div><div class="verto-team__role">%s</div></div>', esc_html( get_the_title() ), esc_html( $role ) );
		}
		echo '</div>';
		wp_reset_postdata();
	}
}
