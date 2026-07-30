<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Team Grid — renders the "verto_team" custom post type (client
 * adds/edits people in wp-admin → Team). Three modes: leaders (large,
 * circular), everyone, or "strip" — the brand-site TeamStrip port
 * (muted section, eyebrow + "Meet the … desk." header, 4 square photo
 * cards with a brand-gradient hover overlay). Optionally filtered by
 * the _verto_brand meta the installer stamps on seeded people.
 */
class Verto_Widget_Team_Grid extends \Elementor\Widget_Base {
	public function get_name() { return 'verto-team-grid'; }
	public function get_title() { return 'Verto Team Grid'; }
	public function get_icon() { return 'eicon-person'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Team' ] );
		$this->add_control( 'mode', [
			'label' => 'Mode', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'leaders' => 'Leadership (large)', 'all' => 'Everyone (compact)', 'strip' => 'Brand strip (4 cards + header)' ],
			'default' => 'all',
		] );
		$this->add_control( 'brand', [ 'label' => 'Filter by brand (_verto_brand meta, e.g. modulr)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ] );
		$this->add_control( 'eyebrow', [ 'label' => 'Strip eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'The team' ] );
		$this->add_control( 'heading', [ 'label' => 'Strip heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Meet the Modulr desk.' ] );
		$this->add_control( 'body', [ 'label' => 'Strip body', 'type' => \Elementor\Controls_Manager::TEXTAREA,
			'default' => "Operators, engineers and market specialists. The people you'll actually talk to when you engage Modulr." ] );
		$this->add_control( 'focus_text', [ 'label' => 'Card focus line', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Built environment search.' ] );
		$this->add_control( 'limit', [ 'label' => 'Strip card count', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 4, 'min' => 1, 'max' => 12 ] );
		$this->end_controls_section();
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$mode    = $s['mode'] ?: 'all';
		$leaders = 'leaders' === $mode;
		$strip   = 'strip' === $mode;

		$meta_query = [];
		if ( $leaders ) $meta_query[] = [ 'key' => '_verto_leader', 'value' => '1' ];
		if ( ! empty( $s['brand'] ) ) $meta_query[] = [ 'key' => '_verto_brand', 'value' => sanitize_key( $s['brand'] ) ];

		$q = new \WP_Query( [
			'post_type'      => 'verto_team',
			'posts_per_page' => $strip ? (int) ( $s['limit'] ?: 4 ) : -1,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'meta_query'     => $meta_query,
		] );
		if ( ! $q->have_posts() ) {
			echo '<p style="opacity:.7">No team members yet — add them under Team in wp-admin.</p>';
			return;
		}

		if ( $strip ) {
			?>
			<div class="vbs-team" style="background:var(--muted);">
				<div class="container-wide">
					<div class="vbs-team__head">
						<div class="vbs-team__intro">
							<span class="eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span>
							<h2 class="display-2 vbs-mt5"><?php echo esc_html( $s['heading'] ); ?></h2>
							<?php if ( $s['body'] ) : ?><p class="vbs-team__body"><?php echo esc_html( $s['body'] ); ?></p><?php endif; ?>
						</div>
					</div>
					<div class="vbs-team__grid">
						<?php while ( $q->have_posts() ) : $q->the_post();
							$role = get_post_meta( get_the_ID(), '_verto_role', true ) ?: 'Consultant';
							?>
							<div class="vbs-team__card" style="background:color-mix(in oklab, var(--brand) 6%, var(--muted));">
								<?php if ( has_post_thumbnail() ) : ?>
									<?php the_post_thumbnail( 'medium_large', [ 'class' => 'vbs-team__photo', 'loading' => 'lazy' ] ); ?>
								<?php else : ?>
									<div class="vbs-team__initials" style="background:linear-gradient(160deg, color-mix(in oklab, var(--brand) 22%, var(--muted)) 0%, color-mix(in oklab, var(--brand) 6%, var(--muted)) 100%);color:var(--brand);">
										<?php echo esc_html( strtoupper( implode( '', array_map( fn( $p ) => mb_substr( $p, 0, 1 ), array_slice( preg_split( '/\s+/', get_the_title() ), 0, 2 ) ) ) ) ); ?>
									</div>
								<?php endif; ?>
								<div class="vbs-team__overlay" style="background:linear-gradient(180deg, color-mix(in oklab, var(--brand) 0%, transparent) 30%, color-mix(in oklab, var(--brand) 85%, #000) 100%);color:var(--brand-foreground, #fff);">
									<div class="vbs-team__name"><?php the_title(); ?></div>
									<div class="vbs-team__role"><?php echo esc_html( $role ); ?></div>
									<?php if ( $s['focus_text'] ) : ?><p class="vbs-team__focus"><?php echo esc_html( $s['focus_text'] ); ?></p><?php endif; ?>
								</div>
							</div>
						<?php endwhile; ?>
					</div>
				</div>
			</div>
			<?php
			wp_reset_postdata();
			return;
		}

		printf( '<div class="verto-team%s">', $leaders ? ' verto-team--leaders' : '' );
		while ( $q->have_posts() ) {
			$q->the_post();
			$role = get_post_meta( get_the_ID(), '_verto_role', true ) ?: 'Consultant';
			echo '<div class="verto-team__card"><div class="verto-team__photo">';
			if ( has_post_thumbnail() ) { the_post_thumbnail( 'medium' ); }
			echo '</div>';
			printf( '<div class="verto-team__name">%s</div><div class="verto-team__role">%s</div></div>', esc_html( get_the_title() ), esc_html( $role ) );
		}
		echo '</div>';
		wp_reset_postdata();
	}
}
