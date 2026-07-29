<?php
/**
 * Plugin Name: Verto Widgets
 * Plugin URI: https://github.com/Simon2106/verto-core
 * Description: Custom Elementor widgets for the Verto site family — V-mask media hero, line-by-line title reveal, and the Vincere jobs-board wrapper.
 * Version: 0.1.0
 * Requires Plugins: elementor
 * Author: ICE
 */

defined( 'ABSPATH' ) || exit;

add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
	require_once __DIR__ . '/widgets/title-reveal.php';
	require_once __DIR__ . '/widgets/v-mask-media.php';
	require_once __DIR__ . '/widgets/jobs-board.php';

	$widgets_manager->register( new \Verto_Widget_Title_Reveal() );
	$widgets_manager->register( new \Verto_Widget_V_Mask_Media() );
	$widgets_manager->register( new \Verto_Widget_Jobs_Board() );
} );

/** Widget category so they group together in the Elementor panel. */
add_action( 'elementor/elements/categories_registered', function ( $elements_manager ) {
	$elements_manager->add_category( 'verto', [ 'title' => 'Verto', 'icon' => 'fa fa-bolt' ] );
} );
