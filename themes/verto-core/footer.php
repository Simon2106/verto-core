<?php
/**
 * Verto Core footer.
 * Verto (group) site: logo + strapline, Group nav / Brands / Connect columns.
 * Brand sites: BrandFooter.tsx port — brand logo + focus line, Explore /
 * Other brands / Connect columns, "© {year} {Brand}, a Verto Group brand."
 */
$verto_brand = function_exists( 'verto_current_brand' ) ? verto_current_brand() : 'verto';
$verto_is_brand_site = 'verto' !== $verto_brand;
?>
<?php if ( $verto_is_brand_site ) :
	$verto_names = [ 'modulr' => 'Modulr', 'vertek' => 'Vertek', 'edison-lux' => 'Edison Lux' ];
	$verto_focus = [
		'modulr'     => 'Architecture & Data Centres',
		'vertek'     => 'Technical Sales, Service & Engineering',
		'edison-lux' => 'US Energy Staffing',
	];
	$verto_media    = get_option( 'verto_installer_media', [] );
	$verto_logo_key = [ 'modulr' => 'logo_modulr_png', 'vertek' => 'logo_vertek', 'edison-lux' => 'logo_edison' ][ $verto_brand ] ?? '';
	$verto_logo     = '';
	foreach ( [ 'svg', 'png' ] as $verto_ext ) {
		if ( file_exists( get_stylesheet_directory() . "/assets/img/{$verto_brand}-logo.{$verto_ext}" ) ) {
			$verto_logo = get_stylesheet_directory_uri() . "/assets/img/{$verto_brand}-logo.{$verto_ext}";
			break;
		}
	}
	if ( ! $verto_logo ) {
		$verto_logo = $verto_media[ $verto_logo_key ]['url'] ?? '';
	}
	$verto_name = $verto_names[ $verto_brand ] ?? ucfirst( $verto_brand );
	?>
<footer class="verto-footer verto-footer--brand">
	<div class="verto-footer__inner">
		<div class="verto-footer__grid">
			<div class="verto-footer__brand">
				<?php if ( $verto_logo ) : ?>
					<img src="<?php echo esc_url( $verto_logo ); ?>" alt="<?php echo esc_attr( $verto_name ); ?>" class="verto-footer__logo" />
				<?php endif; ?>
				<p class="verto-footer__strap"><?php echo esc_html( $verto_focus[ $verto_brand ] ?? '' ); ?></p>
			</div>
			<div class="verto-footer__col">
				<h4>Explore</h4>
				<ul class="verto-footer__menu">
					<li><a href="<?php echo esc_url( home_url( '/about' ) ); ?>">About</a></li>
					<li><a href="<?php echo esc_url( home_url( '/clients' ) ); ?>">Clients</a></li>
					<li><a href="<?php echo esc_url( home_url( '/candidates' ) ); ?>">Candidates</a></li>
					<li><a href="<?php echo esc_url( home_url( '/insights' ) ); ?>">Insights</a></li>
				</ul>
			</div>
			<div class="verto-footer__col">
				<h4>Other brands</h4>
				<ul class="verto-footer__menu">
					<?php foreach ( $verto_names as $verto_slug => $verto_other ) :
						if ( $verto_slug === $verto_brand ) continue; ?>
						<li><a href="#"><?php echo esc_html( $verto_other ); ?></a></li>
					<?php endforeach; ?>
					<li><a href="#">Verto Group</a></li>
				</ul>
			</div>
			<div class="verto-footer__col">
				<h4>Connect</h4>
				<ul class="verto-footer__menu">
					<li><a href="mailto:hello@vertogroup.com">hello@vertogroup.com</a></li>
					<li><a href="#">LinkedIn</a></li>
				</ul>
			</div>
		</div>
		<div class="verto-footer__legal">
			<span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $verto_name ); ?>, a Verto Group brand.</span>
			<span class="verto-footer__legal-links"><a href="#">Privacy</a><a href="#">Terms</a></span>
		</div>
	</div>
</footer>
<?php else : ?>
<footer class="verto-footer">
	<div class="verto-footer__inner">
		<div class="verto-footer__grid">
			<div class="verto-footer__brand">
				<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/verto-logo.svg' ); ?>" alt="<?php bloginfo( 'name' ); ?>" class="verto-footer__logo" />
				<p class="verto-footer__strap">Verto Group · Edison Lux · Vertek · ModulR — precision talent for the industries that build, power and run the world.</p>
				<p class="verto-footer__locations">Solent · Austin · Miami (soon)</p>
			</div>
			<div class="verto-footer__col">
				<h4>Group</h4>
				<?php
				if ( has_nav_menu( 'verto-primary' ) ) {
					wp_nav_menu( [ 'theme_location' => 'verto-primary', 'container' => false, 'menu_class' => 'verto-footer__menu', 'depth' => 1 ] );
				}
				?>
			</div>
			<div class="verto-footer__col">
				<h4>Brands</h4>
				<ul class="verto-footer__menu">
					<li><a href="#">Edison Lux</a></li>
					<li><a href="#">Vertek</a></li>
					<li><a href="#">ModulR</a></li>
				</ul>
			</div>
			<div class="verto-footer__col">
				<h4>Connect</h4>
				<ul class="verto-footer__menu">
					<li><a href="mailto:info@vertopeople.com">info@vertopeople.com</a></li>
					<li><a href="https://www.instagram.com/verto_people/" target="_blank" rel="noopener">Instagram</a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Join us</a></li>
				</ul>
			</div>
		</div>
		<div class="verto-footer__legal">
			<span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> Verto Group. All rights reserved.</span>
			<span class="verto-footer__legal-links"><a href="#">Privacy</a><a href="#">Terms</a><a href="#">Cookies</a></span>
		</div>
	</div>
</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
