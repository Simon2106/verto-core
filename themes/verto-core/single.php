<?php
/**
 * Single post – the Insights / What's Going On article page (round 58, Alex:
 * "how can they look better when clicking to view them?").
 *
 * Layout: eyebrow (category) + display title + meta line, the featured image
 * as a rounded hero, the article in a constrained reading measure with the
 * house typography, and a back link to the listing. LinkedIn-synced posts
 * never reach this template (template_redirect sends them to LinkedIn).
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$verto_cats  = get_the_category();
	$verto_cat   = $verto_cats ? $verto_cats[0]->name : 'Article';
	$verto_words = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', get_the_ID() ) ) );
	$verto_mins  = max( 1, (int) round( $verto_words / 200 ) );
	$verto_posts_page = (int) get_option( 'page_for_posts' );
	$verto_back       = $verto_posts_page ? get_permalink( $verto_posts_page ) : home_url( '/' );
	?>
	<main class="verto-article">
		<header class="verto-article__head">
			<div class="verto-article__inner">
				<a class="verto-article__back" href="<?php echo esc_url( $verto_back ); ?>">&larr; All articles</a>
				<span class="verto-eyebrow verto-article__eyebrow"><?php echo esc_html( $verto_cat ); ?></span>
				<h1 class="verto-display-2 verto-article__title"><?php the_title(); ?></h1>
				<p class="verto-article__meta"><?php echo esc_html( get_the_date() ); ?> &middot; <?php echo (int) $verto_mins; ?> min read</p>
			</div>
		</header>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="verto-article__hero">
				<?php the_post_thumbnail( 'full' ); ?>
			</figure>
		<?php endif; ?>
		<article class="verto-article__body verto-prose">
			<?php the_content(); ?>
		</article>
		<footer class="verto-article__foot">
			<a class="btn-base verto-article__cta" href="<?php echo esc_url( $verto_back ); ?>">&larr; Back to all articles</a>
		</footer>
	</main>
	<?php
endwhile;

get_footer();
