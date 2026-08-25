<?php
/**
 * Posts index — the "What's Going On" magazine hub (Verto) or the brand
 * "Insights" listing (modulr / vertek / edison-lux).
 *
 * Magazine layout (client-approved design, mirrors whats-going-on.tsx):
 *  1. Featured story — newest post rendered large (image left ~60%,
 *     category chip + display-2 title + excerpt + date/read time right).
 *  2. Card grid of the remaining posts with category chips.
 *  3. Verto only: "Stories" video slot placeholders (client videos to come).
 *  4. Verto only: the Instagram socials section.
 */
$verto_brand = function_exists( 'verto_current_brand' ) ? verto_current_brand() : 'verto';
$verto_hub   = [
	'verto'      => [ 'eyebrow' => "What's going on", 'l1' => "What's going on", 'l2' => 'at Verto.', 'body' => 'Incentive trips, awards, promotions and the occasional market note — straight from the team.' ],
	'modulr'     => [ 'eyebrow' => 'Insights', 'l1' => 'Field notes from inside', 'l2' => 'architecture & data centres.', 'body' => 'The thinking from the consultants closest to the architecture & data centres market.' ],
	'vertek'     => [ 'eyebrow' => 'Insights', 'l1' => 'Field notes from inside', 'l2' => 'technical sales, service & engineering.', 'body' => 'The thinking from the consultants closest to the technical sales, service & engineering market.' ],
	'edison-lux' => [ 'eyebrow' => 'Insights', 'l1' => 'Field notes from inside', 'l2' => 'US energy staffing.', 'body' => 'The thinking from the consultants closest to the US energy staffing market.' ],
];
$verto_head   = $verto_hub[ $verto_brand ] ?? $verto_hub['verto'];
$verto_kicker = 'verto' === $verto_brand ? 'Verto Group' : ucwords( str_replace( '-', ' ', $verto_brand ) );
get_header();
?>
<main class="verto-container">
	<div class="verto-hub-head">
		<span class="verto-eyebrow"><?php echo esc_html( $verto_head['eyebrow'] ); ?></span>
		<h1 class="verto-title-reveal verto-display-1" style="margin-top:1.25rem;">
			<span class="line-mask"><span class="line-inner"><?php echo esc_html( $verto_head['l1'] ); ?></span></span>
			<span class="line-mask"><span class="line-inner" style="transition-delay:110ms"><?php echo esc_html( $verto_head['l2'] ); ?></span></span>
		</h1>
		<p class="verto-intro__body"><?php echo esc_html( $verto_head['body'] ); ?></p>
	</div>
	<?php if ( have_posts() ) : ?>
		<?php $verto_first = ! is_paged(); // featured slot only on page 1 ?>
		<?php if ( $verto_first ) : the_post();
			$cats     = get_the_category();
			$cat_name = $cats ? $cats[0]->name : 'News';
			$words    = str_word_count( wp_strip_all_tags( get_the_content() ) );
			$mins     = max( 1, (int) round( $words / 200 ) );
			?>
			<article class="verto-featured">
				<a class="verto-featured__media" href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">
					<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'full' ); } ?>
				</a>
				<div class="verto-featured__body">
					<div class="verto-featured__top">
						<span class="verto-cat-chip verto-cat-chip--dark"><?php echo esc_html( $cat_name ); ?></span>
						<span class="verto-featured__label">Featured story</span>
					</div>
					<h2 class="verto-featured__title verto-display-2"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<p class="verto-featured__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 34 ) ); ?></p>
					<div class="verto-featured__meta">
						<span class="brand"><?php echo esc_html( $verto_kicker ); ?></span>
						<span aria-hidden="true">·</span>
						<span><?php echo esc_html( get_the_date( 'j M Y' ) ); ?></span>
						<span aria-hidden="true">·</span>
						<span><?php echo esc_html( $mins ); ?> min read</span>
					</div>
				</div>
			</article>
		<?php endif; ?>
		<div class="verto-posts">
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
						<span class="verto-cat-chip verto-cat-chip--dark"><?php echo esc_html( $cat_name ); ?></span>
					</a>
					<div class="verto-post-card__body">
						<div class="verto-post-card__meta"><span class="verto-post-card__kicker"><?php echo esc_html( $verto_kicker ); ?></span><span>·</span><span><?php echo esc_html( $cat_name ); ?></span></div>
						<h3 class="verto-post-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p class="verto-post-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 26 ) ); ?></p>
						<div class="verto-post-card__foot"><span><?php echo esc_html( get_the_date( 'j M Y' ) ); ?> · <?php echo esc_html( $mins ); ?> min read</span><span aria-hidden="true">↗</span></div>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php endif; ?>

	<?php if ( 'verto' === $verto_brand ) : ?>
		<!-- STORIES — video slots for the client's people-story films (placeholder) -->
		<section class="verto-stories">
			<div class="verto-stories__head">
				<div class="verto-stories__intro">
					<span class="verto-eyebrow">Stories</span>
					<h2 class="verto-display-3" style="margin-top:1.25rem;">People&rsquo;s stories.</h2>
					<p class="verto-stories__body">The team, on camera &mdash; first placements, first incentive trips, the move to the US. Video interviews are being filmed now.</p>
				</div>
				<span class="verto-stories__note">&#9888; Placeholder &mdash; client videos to come</span>
			</div>
			<div class="verto-stories__grid">
				<?php foreach ( [ 'A first placement', 'Hitting the incentive trip', 'Building a US desk' ] as $verto_i => $verto_story ) : ?>
					<div class="verto-stories__slot">
						<span class="verto-stories__play" aria-hidden="true"><?php echo function_exists( 'verto_icon' ) ? verto_icon( 'play' ) : '&#9654;'; ?></span>
						<div>
							<div class="verto-stories__slottitle"><?php echo esc_html( $verto_story ); ?></div>
							<div class="verto-stories__slotnote">People&rsquo;s stories &mdash; video coming soon</div>
						</div>
						<span class="verto-stories__num">0<?php echo (int) $verto_i + 1; ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<!-- INSTAGRAM — same embed as the verto-socials widget -->
		<section class="verto-hub-socials">
			<div class="verto-socials">
				<div>
					<span class="verto-eyebrow">Life at Verto</span>
					<h2 class="verto-title-reveal verto-display-3" style="margin-top:1.25rem;"><span class="line-mask"><span class="line-inner">The moments between the meetings.</span></span></h2>
					<p class="verto-intro__body">Awards, incentive trips, sales days and the occasional inflatable &mdash; what working here actually looks like.</p>
					<a class="verto-socials__btn" href="https://www.instagram.com/verto_people/" target="_blank" rel="noopener">Follow @verto_people</a>
				</div>
				<div class="verto-socials__frame">
					<iframe src="https://www.instagram.com/verto_people/embed" title="Instagram" loading="lazy"></iframe>
				</div>
			</div>
		</section>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
