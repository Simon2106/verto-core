<?php
/**
 * Plugin Name: Verto Widgets
 * Plugin URI: https://github.com/Simon2106/verto-core
 * Description: Custom Elementor widgets for the Verto site family – V-mask media hero, line-by-line title reveal, and the Vincere jobs-board wrapper.
 * Version: 0.15.1
 * Requires Plugins: elementor
 * Author: ICE
 */

defined( 'ABSPATH' ) || exit;

define( 'VERTO_WIDGETS_VERSION', '0.15.1' );

/**
 * Round 5, item 5 – compact services band for the brand sites: the three
 * engagement models from the Clients page, one line each, linking through
 * to /clients. Shared by the installer (About / Candidates pages, via the
 * HTML widget) and the theme's brand Insights template (home.php).
 */
function verto_services_band_html(): string {
	$models = [
		[ 'Engaged Search',            'Our flagship model',            'A committed partnership with a structured process – market mapping, verified shortlists, offer management. 100% success rate.' ],
		[ 'Retained Executive Search', 'Director & C-suite mandates',   "Discreet, confidential search for the roles that can't be advertised – off-market approaches, NDA-protected mandates." ],
		[ 'Team Builds',               'Partnerships, not placements',  'A new plant, project or region staffed from the ground up – proactively, against your timeline.' ],
	];
	$cards = '';
	foreach ( $models as $i => [ $title, $kicker, $body ] ) {
		$cards .= '<a class="verto-services__card" href="/clients">'
			. '<span class="verto-services__bar" style="background:var(--brand);"></span>'
			. '<span class="verto-services__kicker">' . esc_html( $kicker ) . '</span>'
			. '<span class="verto-services__title">' . esc_html( $title ) . '</span>'
			. '<span class="verto-services__body">' . esc_html( $body ) . '</span>'
			. '</a>';
	}
	return '<div class="verto-services">'
		. '<div class="container-wide">'
		. '<div class="verto-services__head">'
		. '<div class="vbs-asplit__kick"><span class="vbs-dash" style="background:var(--brand);"></span><span class="vbs-kicker" style="color:var(--brand);">Our solutions</span></div>'
		. '<a class="verto-services__link" style="color:var(--brand);" href="/clients">How we work &rarr;</a>'
		. '</div>'
		. '<div class="verto-services__grid">' . $cards . '</div>'
		. '</div></div>';
}

require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/installer.php';
require_once __DIR__ . '/includes/vincere.php';
require_once __DIR__ . '/includes/applications.php';

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
	require_once __DIR__ . '/widgets/brand-hero.php';
	require_once __DIR__ . '/widgets/feature-row.php';
	require_once __DIR__ . '/widgets/about-split.php';
	require_once __DIR__ . '/widgets/specialisms.php';
	require_once __DIR__ . '/widgets/sector-coverage.php';
	require_once __DIR__ . '/widgets/logo-marquee.php';
	require_once __DIR__ . '/widgets/audience-cards.php';
	require_once __DIR__ . '/widgets/process-rail.php';
	require_once __DIR__ . '/widgets/chip-grid.php';
	require_once __DIR__ . '/widgets/quote-band.php';
	require_once __DIR__ . '/widgets/proof-list.php';
	require_once __DIR__ . '/widgets/cta-band.php';
	require_once __DIR__ . '/widgets/collage.php';
	require_once __DIR__ . '/widgets/values-accordion.php';

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
	$widgets_manager->register( new \Verto_Widget_Brand_Hero() );
	$widgets_manager->register( new \Verto_Widget_Feature_Row() );
	$widgets_manager->register( new \Verto_Widget_About_Split() );
	$widgets_manager->register( new \Verto_Widget_Specialisms() );
	$widgets_manager->register( new \Verto_Widget_Sector_Coverage() );
	$widgets_manager->register( new \Verto_Widget_Logo_Marquee() );
	$widgets_manager->register( new \Verto_Widget_Audience_Cards() );
	$widgets_manager->register( new \Verto_Widget_Process_Rail() );
	$widgets_manager->register( new \Verto_Widget_Chip_Grid() );
	$widgets_manager->register( new \Verto_Widget_Quote_Band() );
	$widgets_manager->register( new \Verto_Widget_Proof_List() );
	$widgets_manager->register( new \Verto_Widget_Cta_Band() );
	$widgets_manager->register( new \Verto_Widget_Collage() );
	$widgets_manager->register( new \Verto_Widget_Values_Accordion() );
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

/** Team custom post type – client-editable people (photo = featured image,
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
