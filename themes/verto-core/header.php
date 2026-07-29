<?php
/**
 * Verto Core header — overrides Hello Elementor's header with the
 * prototype's sticky, blurred site header: logo left, nav centre,
 * "Join us" pill right. Menu = the "verto-primary" location
 * (assigned by the installer).
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="verto-header">
	<div class="verto-header__inner">
		<a class="verto-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php bloginfo( 'name' ); ?> — home">
			<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/verto-logo.svg' ); ?>" alt="<?php bloginfo( 'name' ); ?>" />
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
		<a class="verto-header__cta" href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Join us</a>
		<button class="verto-header__burger" aria-label="Toggle menu" aria-expanded="false" onclick="document.body.classList.toggle('verto-nav-open');this.setAttribute('aria-expanded',document.body.classList.contains('verto-nav-open'))">
			<span></span><span></span><span></span>
		</button>
	</div>
</header>
