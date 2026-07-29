<?php
/**
 * Posts index — the "What's Going On" hub, styled to match the prototype.
 */
get_header();
?>
<main class="verto-container">
	<div class="verto-hub-head">
		<span class="verto-eyebrow">What's going on</span>
		<h1 class="verto-title-reveal verto-display-1" style="margin-top:1.25rem;">
			<span class="line-mask"><span class="line-inner">What's going on</span></span>
			<span class="line-mask"><span class="line-inner" style="transition-delay:110ms">at Verto.</span></span>
		</h1>
		<p class="verto-intro__body">Incentive trips, awards, promotions and the occasional market note — straight from the team.</p>
	</div>
	<?php if ( have_posts() ) : ?>
		<div class="verto-posts" style="padding-bottom:6rem;">
			<?php while ( have_posts() ) : the_post();
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
						<div class="verto-post-card__meta"><span class="verto-post-card__kicker">Verto Group</span><span>·</span><span><?php echo esc_html( $cat_name ); ?></span></div>
						<h3 class="verto-post-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p class="verto-post-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 26 ) ); ?></p>
						<div class="verto-post-card__foot"><span><?php echo esc_html( $mins ); ?> min read</span><span aria-hidden="true">↗</span></div>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
