<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Specialist Band (Oct 2026, Martin's idea via Alex): "Speak to a
 * specialist" – a slim dark band that cycles through the brand's consultants
 * (photo, name, role) with a contact CTA, and surfaces the newest live
 * client vacancy when the Vincere sync has one. Fills the quiet stretch at
 * the bottom of the brand pages; rotation is in verto-effects.js §15.
 */
class Verto_Widget_Specialist_Band extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-specialist-band'; }
	public function get_title() { return 'Verto Specialist Band'; }
	public function get_icon() { return 'eicon-person'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Specialist band' ] );
		$this->add_control( 'brand', [ 'label' => 'Brand (_verto_brand meta)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ] );
		$this->add_control( 'heading', [ 'label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Speak to a specialist.' ] );
		$this->add_control( 'body', [ 'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Real people, in your market, on the phone the same day.' ] );
		$this->add_control( 'cta_text', [ 'label' => 'CTA', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Start a conversation' ] );
		$this->add_control( 'cta_link', [ 'label' => 'CTA link', 'type' => \Elementor\Controls_Manager::URL, 'default' => [ 'url' => '#contact' ] ] );
		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$brand = sanitize_key( (string) ( $s['brand'] ?? '' ) );

		$meta_query = [];
		if ( $brand ) $meta_query[] = [ 'key' => '_verto_brand', 'value' => $brand, 'compare' => 'LIKE' ];
		$people = get_posts( [
			'post_type'      => 'verto_team',
			'posts_per_page' => 12,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'meta_query'     => $meta_query,
			'no_found_rows'  => true,
		] );
		// Consultants only – the band is the desk, not the founders – and only
		// people with a real headshot (an initials tile would undercut it).
		$people = array_values( array_filter( $people, static function ( $p ) {
			return 'leadership' !== ( get_post_meta( $p->ID, '_verto_tier', true ) ?: 'team' ) && has_post_thumbnail( $p );
		} ) );
		if ( ! $people ) return;

		// Newest live CLIENT vacancy for this brand (if the sync has one).
		$job = null;
		if ( function_exists( 'verto_vincere_get_client_jobs' ) ) {
			foreach ( verto_vincere_get_client_jobs() as $row ) {
				if ( ! $brand || $brand === ( $row['brand'] ?? '' ) ) { $job = $row; break; }
			}
		}
		?>
		<div class="verto-specialist" style="background:var(--ink);color:var(--ink-foreground);">
			<div class="container-wide verto-specialist__inner">
				<div class="verto-specialist__copy">
					<h2 class="display-3"><?php echo esc_html( $s['heading'] ); ?></h2>
					<?php if ( $s['body'] ) : ?><p class="verto-specialist__body"><?php echo esc_html( $s['body'] ); ?></p><?php endif; ?>
					<a class="btn-base btn-primary verto-specialist__cta" href="<?php echo esc_url( $s['cta_link']['url'] ?? '#contact' ); ?>"><?php echo esc_html( $s['cta_text'] ); ?></a>
					<?php if ( $job && ! empty( $job['permalink'] ) ) : ?>
						<a class="verto-specialist__job" href="<?php echo esc_url( $job['permalink'] ); ?>">
							<span class="verto-specialist__joblabel">Newest vacancy</span>
							<span class="verto-specialist__jobtitle"><?php echo esc_html( $job['title'] ); ?> &rarr;</span>
						</a>
					<?php endif; ?>
				</div>
				<div class="verto-specialist__faces" data-specialist-rotate>
					<?php foreach ( $people as $i => $p ) :
						$role = get_post_meta( $p->ID, '_verto_role', true ) ?: 'Consultant';
						?>
						<figure class="verto-specialist__face<?php echo 0 === $i ? ' is-active' : ''; ?>">
							<?php echo get_the_post_thumbnail( $p, 'medium_large', [ 'loading' => 'lazy', 'alt' => esc_attr( $p->post_title ) ] ); ?>
							<figcaption>
								<span class="verto-specialist__name"><?php echo esc_html( $p->post_title ); ?></span>
								<span class="verto-specialist__role"><?php echo esc_html( $role ); ?></span>
							</figcaption>
						</figure>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
