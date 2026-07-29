<?php
/**
 * Plugin Name: Verto Widgets
 * Plugin URI: https://github.com/Simon2106/verto-core
 * Description: Custom Elementor widgets for the Verto site family — V-mask media hero, line-by-line title reveal, and the Vincere jobs-board wrapper.
 * Version: 0.5.0
 * Requires Plugins: elementor
 * Author: ICE
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/installer.php';

add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
	require_once __DIR__ . '/widgets/title-reveal.php';
	require_once __DIR__ . '/widgets/v-mask-media.php';
	require_once __DIR__ . '/widgets/jobs-board.php';
	require_once __DIR__ . '/widgets/hero.php';
	require_once __DIR__ . '/widgets/brand-tiles.php';
	require_once __DIR__ . '/widgets/values.php';
	require_once __DIR__ . '/widgets/quotes.php';
	require_once __DIR__ . '/widgets/section-intro.php';
	require_once __DIR__ . '/widgets/awards-strip.php';
	require_once __DIR__ . '/widgets/posts-grid.php';
	require_once __DIR__ . '/widgets/timeline.php';
	require_once __DIR__ . '/widgets/principles.php';
	require_once __DIR__ . '/widgets/footprint.php';
	require_once __DIR__ . '/widgets/team-grid.php';
	require_once __DIR__ . '/widgets/socials-embed.php';
	require_once __DIR__ . '/widgets/perks.php';

	$widgets_manager->register( new \Verto_Widget_Title_Reveal() );
	$widgets_manager->register( new \Verto_Widget_V_Mask_Media() );
	$widgets_manager->register( new \Verto_Widget_Jobs_Board() );
	$widgets_manager->register( new \Verto_Widget_Hero() );
	$widgets_manager->register( new \Verto_Widget_Brand_Tiles() );
	$widgets_manager->register( new \Verto_Widget_Values() );
	$widgets_manager->register( new \Verto_Widget_Quotes() );
	$widgets_manager->register( new \Verto_Widget_Section_Intro() );
	$widgets_manager->register( new \Verto_Widget_Awards_Strip() );
	$widgets_manager->register( new \Verto_Widget_Posts_Grid() );
	$widgets_manager->register( new \Verto_Widget_Timeline() );
	$widgets_manager->register( new \Verto_Widget_Principles() );
	$widgets_manager->register( new \Verto_Widget_Footprint() );
	$widgets_manager->register( new \Verto_Widget_Team_Grid() );
	$widgets_manager->register( new \Verto_Widget_Socials() );
	$widgets_manager->register( new \Verto_Widget_Perks() );
} );

/** Widget category so they group together in the Elementor panel. */
add_action( 'elementor/elements/categories_registered', function ( $elements_manager ) {
	$elements_manager->add_category( 'verto', [ 'title' => 'Verto', 'icon' => 'fa fa-bolt' ] );
} );

/** Allow SVG upload for admins (needed for the ModulR logo import). */
add_filter( 'upload_mimes', function ( $mimes ) {
	if ( current_user_can( 'manage_options' ) ) {
		$mimes['svg'] = 'image/svg+xml';
	}
	return $mimes;
} );

/** Team custom post type — client-editable people (photo = featured image,
 *  role + leader flag in the Team Details box). */
add_action( 'init', function () {
	register_post_type( 'verto_team', [
		'labels'   => [ 'name' => 'Team', 'singular_name' => 'Team member' ],
		'public'   => false,
		'show_ui'  => true,
		'menu_icon'=> 'dashicons-groups',
		'supports' => [ 'title', 'thumbnail', 'page-attributes' ],
	] );
} );
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'verto_team_details', 'Team Details', function ( $post ) {
		wp_nonce_field( 'verto_team_details', 'verto_team_nonce' );
		$role   = get_post_meta( $post->ID, '_verto_role', true );
		$leader = get_post_meta( $post->ID, '_verto_leader', true );
		printf( '<p><label>Role<br/><input type="text" name="verto_role" value="%s" class="widefat"/></label></p>', esc_attr( $role ) );
		printf( '<p><label><input type="checkbox" name="verto_leader" value="1" %s/> Show in Leadership</label></p>', checked( $leader, '1', false ) );
	}, 'verto_team', 'side' );
} );
add_action( 'save_post_verto_team', function ( $post_id ) {
	if ( ! isset( $_POST['verto_team_nonce'] ) || ! wp_verify_nonce( $_POST['verto_team_nonce'], 'verto_team_details' ) ) return;
	update_post_meta( $post_id, '_verto_role', sanitize_text_field( $_POST['verto_role'] ?? '' ) );
	update_post_meta( $post_id, '_verto_leader', isset( $_POST['verto_leader'] ) ? '1' : '' );
} );
