<?php
/**
 * Verto Core footer — ink footer matching the prototype: logo + strapline,
 * link columns (Group nav / Brands / Connect), locations line, legal row.
 */
?>
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

<?php wp_footer(); ?>
</body>
</html>
