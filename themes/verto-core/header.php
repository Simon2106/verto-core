<?php
/**
 * Verto Core header — overrides Hello Elementor's header with the
 * prototype's sticky, blurred site header.
 *
 * Verto (group) site: logo left, uppercase nav centre, "Join us" pill right.
 * Brand sites (modulr / vertek / edison-lux): brand logo, sentence-case nav
 * (BrandHeader.tsx), "Our Solutions" brand-coloured CTA → /clients.
 */
$verto_brand = function_exists( 'verto_current_brand' ) ? verto_current_brand() : 'verto';
$verto_is_brand_site = 'verto' !== $verto_brand;

// Brand logo: prefer theme asset assets/img/{brand}-logo.(svg|png), else the
// media imported by the installer (modulr must use the PNG artwork).
$verto_logo = get_stylesheet_directory_uri() . '/assets/img/verto-logo.svg';
if ( $verto_is_brand_site ) {
	$verto_logo = '';
	foreach ( [ 'svg', 'png' ] as $verto_ext ) {
		if ( file_exists( get_stylesheet_directory() . "/assets/img/{$verto_brand}-logo.{$verto_ext}" ) ) {
			$verto_logo = get_stylesheet_directory_uri() . "/assets/img/{$verto_brand}-logo.{$verto_ext}";
			break;
		}
	}
	if ( ! $verto_logo ) {
		$verto_media    = get_option( 'verto_installer_media', [] );
		$verto_logo_key = [ 'modulr' => 'logo_modulr_png', 'vertek' => 'logo_vertek', 'edison-lux' => 'logo_edison' ][ $verto_brand ] ?? '';
		$verto_logo     = $verto_media[ $verto_logo_key ]['url'] ?? get_stylesheet_directory_uri() . '/assets/img/verto-logo.svg';
	}
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="verto-header<?php echo $verto_is_brand_site ? ' verto-header--brand' : ''; ?>">
	<div class="verto-header__inner">
		<a class="verto-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php bloginfo( 'name' ); ?> — home">
			<img src="<?php echo esc_url( $verto_logo ); ?>" alt="<?php bloginfo( 'name' ); ?>" />
		</a>
		<nav class="verto-header__nav" aria-label="Primary">
			<?php
			if ( has_nav_menu( 'verto-primary' ) ) {
				wp_nav_menu( [
					'theme_location' => 'verto-primary',
					'container'      => false,
					'menu_class'     => 'verto-header__menu',
					'depth'          => 1,
				] );
			}
			?>
		</nav>
		<?php if ( $verto_is_brand_site ) : ?>
			<a class="verto-header__cta verto-header__cta--brand" href="<?php echo esc_url( home_url( '/clients' ) ); ?>">Our Solutions</a>
		<?php else : ?>
			<a class="verto-header__cta" href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Join us</a>
		<?php endif; ?>
		<button class="verto-header__burger" aria-label="Toggle menu" aria-expanded="false" onclick="document.body.classList.toggle('verto-nav-open');this.setAttribute('aria-expanded',document.body.classList.contains('verto-nav-open'))">
			<span></span><span></span><span></span>
		</button>
	</div>
</header>
