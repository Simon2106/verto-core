<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Posts Grid – latest posts as prototype-style cards:
 * featured image with category label overlay, meta row, display title,
 * excerpt, read time. Powers the "What's going on" section from real
 * WP posts so the client publishes news natively.
 */
class Verto_Widget_Posts_Grid extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-posts-grid'; }
	public function get_title() { return 'Verto Posts Grid'; }
	public function get_icon() { return 'eicon-posts-grid'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Posts' ] );
		$this->add_control( 'count', [
			'label' => 'Number of posts', 'type' => \Elementor\Controls_Manager::NUMBER,
			'default' => 3, 'min' => 1, 'max' => 12,
		] );
		$this->add_control( 'kicker', [
			'label' => 'Card kicker', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Verto Group',
		] );
		$this->add_control( 'category', [
			'label' => 'Category slug filter (brand sites)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '',
		] );
		$this->add_control( 'header_eyebrow', [ 'label' => 'Header eyebrow (optional)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ] );
		$this->add_control( 'header_heading', [ 'label' => 'Header heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ] );
		$this->add_control( 'header_link_text', [ 'label' => 'Header link text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ] );
		$this->add_control( 'header_link', [ 'label' => 'Header link', 'type' => \Elementor\Controls_Manager::URL ] );
		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$args = [
			'post_type'      => 'post',
			'posts_per_page' => (int) ( $s['count'] ?: 3 ),
			'post_status'    => 'publish',
		];
		if ( ! empty( $s['category'] ) ) {
			$args['category_name'] = sanitize_title( $s['category'] );
		}
		$q = new \WP_Query( $args );
		if ( ! $q->have_posts() ) {
			echo '<p style="opacity:.7">No posts yet – publish a post and it appears here.</p>';
			return;
		}
		if ( ! empty( $s['header_heading'] ) ) {
			echo '<div class="vbs-insights__head">';
			echo '<div>';
			if ( ! empty( $s['header_eyebrow'] ) ) {
				printf( '<span class="eyebrow">%s</span>', esc_html( $s['header_eyebrow'] ) );
			}
			printf( '<h2 class="display-2 vbs-mt5">%s</h2>', esc_html( $s['header_heading'] ) );
			echo '</div>';
			if ( ! empty( $s['header_link_text'] ) ) {
				printf(
					'<a class="vbs-insights__all" style="color:var(--brand);" href="%s">%s %s</a>',
					esc_url( $s['header_link']['url'] ?? '#' ),
					esc_html( $s['header_link_text'] ),
					function_exists( 'verto_icon' ) ? verto_icon( 'arrow-up-right', [ 'class' => 'vbs-icon-16' ] ) : ''
				);
			}
			echo '</div>';
		}
		echo '<div class="verto-posts">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$cats     = get_the_category();
			$cat_name = $cats ? $cats[0]->name : 'News';
			$raw      = (string) get_post_field( 'post_content', get_the_ID() );
			$words    = str_word_count( wp_strip_all_tags( get_the_content() ) );
			$mins     = max( 1, (int) round( $words / 200 ) );
			// Round 6, item 3: posts embedding a video ([video] shortcode or a
			// <video> tag) read as video – play badge + "Video" type label.
			$is_video = has_shortcode( $raw, 'video' ) || false !== stripos( $raw, '<video' );
			// Nov 2026 (Alex/Simon): LinkedIn items synced into the Insights
			// grid – the card links straight OUT to the LinkedIn post, badges
			// itself "LinkedIn" and shows the consultant as the byline.
			$li_url    = (string) get_post_meta( get_the_ID(), '_verto_external_url', true );
			$li_author = (string) get_post_meta( get_the_ID(), '_verto_li_author', true );
			$is_li     = '' !== $li_url;
			$card_href = $is_li ? $li_url : get_permalink();
			$card_tgt  = $is_li ? ' target="_blank" rel="noopener"' : '';
			$type_lbl  = $is_li ? 'LinkedIn' : ( $is_video ? 'Video' : 'Article' );
			?>
			<article class="verto-post-card<?php echo $is_li ? ' verto-post-card--li' : ''; ?>">
				<a class="verto-post-card__media" href="<?php echo esc_url( $card_href ); ?>"<?php echo $card_tgt; ?>>
					<?php if ( has_post_thumbnail() ) {
						the_post_thumbnail( 'large' );
					} elseif ( $is_li ) { ?>
						<span class="verto-post-card__liph" aria-hidden="true">in</span>
					<?php } ?>
					<span class="verto-post-card__type"><span class="dot"></span><?php echo esc_html( $type_lbl ); ?></span>
					<?php if ( $is_video && ! $is_li ) : ?>
						<span class="verto-post-card__play" aria-hidden="true"></span>
					<?php endif; ?>
					<span class="verto-post-card__cat"><?php echo esc_html( $is_li ? 'LinkedIn' : $cat_name ); ?></span>
				</a>
				<div class="verto-post-card__body">
					<div class="verto-post-card__meta">
						<span class="verto-post-card__kicker"><?php echo esc_html( $is_li && $li_author ? $li_author : $s['kicker'] ); ?></span>
						<span>·</span>
						<span><?php echo esc_html( $is_li ? 'LinkedIn' : $cat_name ); ?></span>
					</div>
					<h3 class="verto-post-card__title"><a href="<?php echo esc_url( $card_href ); ?>"<?php echo $card_tgt; ?>><?php the_title(); ?></a></h3>
					<p class="verto-post-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 26 ) ); ?></p>
					<div class="verto-post-card__foot">
						<span><?php echo $is_li ? 'View on LinkedIn' : esc_html( $mins . ' min read' ); ?></span>
						<span aria-hidden="true">↗</span>
					</div>
				</div>
			</article>
			<?php
		}
		echo '</div>';
		wp_reset_postdata();
	}
}
