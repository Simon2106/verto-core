<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Posts Grid — latest posts as prototype-style cards:
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
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$q = new \WP_Query( [
			'post_type'      => 'post',
			'posts_per_page' => (int) ( $s['count'] ?: 3 ),
			'post_status'    => 'publish',
		] );
		if ( ! $q->have_posts() ) {
			echo '<p style="opacity:.7">No posts yet — publish a post and it appears here.</p>';
			return;
		}
		echo '<div class="verto-posts">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$cats     = get_the_category();
			$cat_name = $cats ? $cats[0]->name : 'News';
			$words    = str_word_count( wp_strip_all_tags( get_the_content() ) );
			$mins     = max( 1, (int) round( $words / 200 ) );
			?>
			<article class="verto-post-card">
				<a class="verto-post-card__media" href="<?php the_permalink(); ?>">
					<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large' ); } ?>
					<span class="verto-post-card__type"><span class="dot"></span>Article</span>
					<span class="verto-post-card__cat"><?php echo esc_html( $cat_name ); ?></span>
				</a>
				<div class="verto-post-card__body">
					<div class="verto-post-card__meta">
						<span class="verto-post-card__kicker"><?php echo esc_html( $s['kicker'] ); ?></span>
						<span>·</span>
						<span><?php echo esc_html( $cat_name ); ?></span>
					</div>
					<h3 class="verto-post-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<p class="verto-post-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 26 ) ); ?></p>
					<div class="verto-post-card__foot">
						<span><?php echo esc_html( $mins ); ?> min read</span>
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
