<?php
/**
 * Plugin Name: Verto Widgets
 * Plugin URI: https://github.com/Simon2106/verto-core
 * Description: Custom Elementor widgets for the Verto site family — V-mask media hero, line-by-line title reveal, and the Vincere jobs-board wrapper.
 * Version: 0.3.0
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

	$widgets_manager->register( new \Verto_Widget_Title_Reveal() );
	$widgets_manager->register( new \Verto_Widget_V_Mask_Media() );
	$widgets_manager->register( new \Verto_Widget_Jobs_Board() );
	$widgets_manager->register( new \Verto_Widget_Hero() );
	$widgets_manager->register( new \Verto_Widget_Brand_Tiles() );
	$widgets_manager->register( new \Verto_Widget_Values() );
	$widgets_manager->register( new \Verto_Widget_Quotes() );
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
