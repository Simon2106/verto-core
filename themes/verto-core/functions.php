<?php
/**
 * Verto Core — child theme of Hello Elementor.
 *
 * One repo serves all four sites (Verto Group, Edison Lux, Vertek, ModulR).
 * Each site declares which brand it is via the `verto_brand` option
 * (Settings → General → Verto Brand, added below) or a VERTO_BRAND
 * constant in wp-config.php. The brand determines which token stylesheet
 * and font set load — everything else is shared.
 */

defined( 'ABSPATH' ) || exit;

const VERTO_BRANDS = [ 'verto', 'edison-lux', 'vertek', 'modulr' ];

function verto_current_brand(): string {
	if ( defined( 'VERTO_BRAND' ) && in_array( VERTO_BRAND, VERTO_BRANDS, true ) ) {
		return VERTO_BRAND;
	}
	$opt = get_option( 'verto_brand', 'verto' );
	return in_array( $opt, VERTO_BRANDS, true ) ? $opt : 'verto';
}

/** Google Fonts per brand (guideline typography). */
function verto_font_url(): string {
	$sets = [
		'verto'      => 'family=Sora:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700',
		'edison-lux' => 'family=Jost:wght@300;400;500;600&family=Inter:wght@300;400;500;600',
		'vertek'     => 'family=Saira:wght@400;500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500',
		'modulr'     => 'family=Montserrat:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600',
	];
	return 'https://fonts.googleapis.com/css2?' . $sets[ verto_current_brand() ] . '&display=swap';
}

add_action( 'wp_enqueue_scripts', function () {
	$brand = verto_current_brand();
	$dir   = get_stylesheet_directory_uri();
	$ver   = wp_get_theme()->get( 'Version' );

	wp_enqueue_style( 'verto-fonts', verto_font_url(), [], null );
	wp_enqueue_style( 'verto-tokens', "$dir/assets/css/tokens-$brand.css", [], $ver );
	wp_enqueue_style( 'verto-ui', "$dir/assets/css/verto-ui.css", [ 'verto-tokens' ], $ver );
	wp_enqueue_script( 'verto-effects', "$dir/assets/js/verto-effects.js", [], $ver, [ 'strategy' => 'defer' ] );
}, 20 );

/** Brand attribute on <body> so shared CSS can brand-scope if needed. */
add_filter( 'body_class', function ( $classes ) {
	$classes[] = 'verto-brand-' . verto_current_brand();
	return $classes;
} );

/** Settings → General → Verto Brand selector. */
add_action( 'admin_init', function () {
	register_setting( 'general', 'verto_brand', [
		'type'              => 'string',
		'default'           => 'verto',
		'sanitize_callback' => fn( $v ) => in_array( $v, VERTO_BRANDS, true ) ? $v : 'verto',
	] );
	add_settings_field( 'verto_brand', 'Verto Brand', function () {
		$current = verto_current_brand();
		echo '<select name="verto_brand" ' . ( defined( 'VERTO_BRAND' ) ? 'disabled' : '' ) . '>';
		foreach ( VERTO_BRANDS as $b ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $b ), selected( $current, $b, false ), esc_html( $b ) );
		}
		echo '</select>';
		if ( defined( 'VERTO_BRAND' ) ) {
			echo '<p class="description">Locked by VERTO_BRAND constant in wp-config.php</p>';
		}
	}, 'general' );
} );

/** Menu location used by the coded header/footer. */
add_action( 'after_setup_theme', function () {
	register_nav_menus( [ 'verto-primary' => 'Verto Primary' ] );
} );

/**
 * Cross-site brand URLs. Staging defaults below; when the production domains
 * go live, set the 'verto_brand_urls' option (or use the filter) — one place.
 */
function verto_brand_url( string $brand ): string {
	$defaults = [
		'verto'      => 'https://verto-wp.on-forge.com',
		'edison-lux' => 'https://edisonlux-wp.on-forge.com',
		'modulr'     => 'https://modulr-wp.on-forge.com',
		'vertek'     => 'https://vertek-wp.on-forge.com',
	];
	$urls = wp_parse_args( (array) get_option( 'verto_brand_urls', [] ), $defaults );
	$urls = apply_filters( 'verto/brand_urls', $urls );
	return $urls[ $brand ] ?? '#';
}
