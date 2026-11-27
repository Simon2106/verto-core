<?php
/**
 * Posts index – the "What's Going On" magazine hub (Verto) or the brand
 * "Insights" listing (modulr / vertek / edison-lux).
 *
 * Magazine layout (client-approved design, mirrors whats-going-on.tsx):
 *  1. Featured story – newest post rendered large (image left ~60%,
 *     category chip + display-2 title + excerpt + date/read time right).
 *  2. Card grid of the remaining posts with category chips.
 *  3. Verto only: "Recent events" – a compact rail of the client's event
 *     films (summit / Ibiza / charity night / the promotion films),
 *     poster tiles with play badges, click-to-play inline.
 *  4. Verto only: the Instagram socials section.
 */
$verto_brand = function_exists( 'verto_current_brand' ) ? verto_current_brand() : 'verto';
$verto_hub   = [
	'verto'      => [ 'eyebrow' => "What's going on", 'l1' => "What's going on", 'l2' => 'at Verto.', 'body' => 'Take a look behind the scenes at life at Verto. From unforgettable incentive trips and action-packed sales days to career milestones, promotions, and award celebrations. Discover the people, personalities and experiences that make our culture what it is.' ],
	'modulr'     => [ 'eyebrow' => 'Insights', 'l1' => 'Field notes from inside', 'l2' => 'architecture & data centres.', 'body' => 'The thinking from the consultants closest to the architecture & data centres market.' ],
	'vertek'     => [ 'eyebrow' => 'Insights', 'l1' => 'Field notes from inside', 'l2' => 'technical sales, service & engineering.', 'body' => 'The thinking from the consultants closest to the technical sales, service & engineering market.' ],
	// Edison copy doc (Oct 2026), page 13: "Inside the industry."
	'edison-lux' => [ 'eyebrow' => 'Insights', 'l1' => 'Inside the', 'l2' => 'industry.', 'body' => 'Thoughts from the consultants working closest to your market.' ],
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
	<?php
	// Nov 2026 (Alex, round 21): "I love the sales day videos and tiles –
	// duplicate and add to the top of whats going on page". Same markup and
	// classes as the verto-salesdays-mosaic widget, so the hover-play JS
	// (verto-effects.js) and CSS pick it up automatically. Group site only;
	// the films resolve from the installer's imported-media map.
	if ( 'verto' === $verto_brand && ! is_paged() ) :
		$verto_sd_media = (array) get_option( 'verto_installer_media', [] );
		$verto_sd_first = true;
		$verto_sd_out   = '';
		for ( $verto_sd_i = 1; $verto_sd_i <= 10; $verto_sd_i++ ) {
			$verto_sd_key = sprintf( 'salesday_%02d', $verto_sd_i );
			$verto_sd_url = (string) ( $verto_sd_media[ $verto_sd_key ]['url'] ?? '' );
			if ( '' === $verto_sd_url ) {
				continue;
			}
			$verto_sd_poster = (string) ( $verto_sd_media[ $verto_sd_key . '_poster' ]['url'] ?? '' );
			$verto_sd_label  = sprintf( 'Sales day film %d of 10', $verto_sd_i );
			$verto_sd_out   .= '<figure class="verto-salesdays__tile" data-salesday-tile>';
			if ( '' !== $verto_sd_poster ) {
				$verto_sd_out .= '<img class="verto-salesdays__poster" src="' . esc_url( $verto_sd_poster ) . '" alt="' . esc_attr( $verto_sd_label ) . '" loading="lazy" />';
			}
			$verto_sd_out .= '<video class="verto-salesdays__video" muted playsinline loop preload="none"' . ( '' !== $verto_sd_poster ? ' poster="' . esc_url( $verto_sd_poster ) . '"' : '' ) . ' data-src="' . esc_url( $verto_sd_url ) . '" aria-label="' . esc_attr( $verto_sd_label ) . '" tabindex="-1"></video>';
			if ( $verto_sd_first ) {
				$verto_sd_out  .= '<span class="verto-salesdays__hint" data-salesday-hint aria-hidden="true">Hover to play</span>';
				$verto_sd_first = false;
			}
			$verto_sd_out .= '</figure>';
		}
		if ( '' !== $verto_sd_out ) : ?>
			<div class="verto-salesdays verto-hub-salesdays" data-verto-salesdays>
				<div class="verto-salesdays__grid"><?php echo $verto_sd_out; // phpcs:ignore WordPress.Security.EscapeOutput -- built above with esc_url/esc_attr ?></div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( have_posts() ) : ?>
		<?php $verto_first = ! is_paged(); // featured slot only on page 1 ?>
		<?php if ( $verto_first ) : the_post();
			$cats     = get_the_category();
			$cat_name = $cats ? $cats[0]->name : 'News';
			$verto_raw = (string) get_post_field( 'post_content', get_the_ID() );
			$words    = str_word_count( wp_strip_all_tags( get_the_content() ) );
			$mins     = max( 1, (int) round( $words / 200 ) );
			// Round 6, item 3: video stories carry a play badge on the media.
			$verto_is_video = has_shortcode( $verto_raw, 'video' ) || false !== stripos( $verto_raw, '<video' );
			?>
			<article class="verto-featured">
				<?php // Oct 2026 (Alex): video stories preview on hover, like the
				// sales-day mosaic – the card carries the post's film, muted,
				// loaded only when the pointer arrives (verto-effects.js §16). ?>
				<?php $verto_vid_src = $verto_is_video && preg_match( '/<video[^>]*\ssrc="([^"]+)"/i', $verto_raw, $vm ) ? $vm[1] : ''; ?>
				<a class="verto-featured__media<?php echo $verto_vid_src ? ' verto-hovervid' : ''; ?>" <?php echo $verto_vid_src ? 'data-hover-video="' . esc_url( $verto_vid_src ) . '"' : ''; ?> href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">
					<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'full' ); } ?>
					<?php if ( $verto_is_video ) : ?>
						<span class="verto-post-card__play" aria-hidden="true"></span>
					<?php endif; ?>
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
				$verto_raw = (string) get_post_field( 'post_content', get_the_ID() );
				$words    = str_word_count( wp_strip_all_tags( get_the_content() ) );
				$mins     = max( 1, (int) round( $words / 200 ) );
				// Round 6, item 3: video stories carry a play badge + "Video" label.
				$verto_is_video = has_shortcode( $verto_raw, 'video' ) || false !== stripos( $verto_raw, '<video' );
				?>
				<article class="verto-post-card">
					<?php // Oct 2026 (Alex): hover-preview for video cards (§16). ?>
					<?php $verto_vid_src = $verto_is_video && preg_match( '/<video[^>]*\ssrc="([^"]+)"/i', $verto_raw, $vm ) ? $vm[1] : ''; ?>
					<a class="verto-post-card__media<?php echo $verto_vid_src ? ' verto-hovervid' : ''; ?>" <?php echo $verto_vid_src ? 'data-hover-video="' . esc_url( $verto_vid_src ) . '"' : ''; ?> href="<?php the_permalink(); ?>">
						<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large' ); } ?>
						<span class="verto-post-card__type"><span class="dot"></span><?php echo $verto_is_video ? 'Video' : 'Article'; ?></span>
						<?php if ( $verto_is_video ) : ?>
							<span class="verto-post-card__play" aria-hidden="true"></span>
						<?php endif; ?>
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
		<?php // Round 54 (Simon): "12Next" – proper pill pagination.
		the_posts_pagination( [
			'mid_size'  => 2,
			'prev_text' => '&larr; Previous',
			'next_text' => 'Next &rarr;',
		] ); ?>
	<?php endif; ?>

	<?php /* Nov 2026 (Alex): services band removed from the Insights
	   listing on every brand site ("remove how we work on the insights
	   page for all brand sites"). */ ?>

	<?php if ( 'verto' === $verto_brand ) : ?>
		<!-- RECENT EVENTS – a compact rail of the client's event films
		     (Sep-2026 drop: summit / Ibiza / charity night, plus the Milly /
		     Sade / Harvey / Frank promotion films). Poster tiles with play
		     badges; the video
		     only starts (and loads – preload="none") when the badge is
		     pressed (verto-effects.js §12). Supersedes the old People's
		     stories placeholder slots – these are the real films. A tile
		     whose media hasn't been imported yet is skipped, and a pending
		     note tells the admin to run the build. -->
		<?php
		$verto_media  = get_option( 'verto_installer_media', [] );
		$verto_events = [
			// Round 56 (Nov 2026, Alex): his caption copy.
			[ 'title' => 'Summer Summit',        'note' => 'The whole group, one castle. Take a look at what our Summer Summit was like.', 'video' => 'summit_film',     'poster' => 'summit_film_poster' ],
			[ 'title' => 'Verto Takes Ibiza',    'note' => 'An incentive trip to remember.',         'video' => 'ibiza_trip_film', 'poster' => 'ibiza_trip_poster' ],
			[ 'title' => 'Charity Night',        'note' => 'Our charity Gala for Maeve&rsquo;s Mission.', 'video' => 'charity_film', 'poster' => 'charity_film_poster' ],
			[ 'title' => 'Milly&rsquo;s Promotion', 'note' => 'Confetti in the Edison Lux corner with Milly being promoted from Lead to Principal Power and Energy Talent Specialist.', 'video' => 'milly_video', 'poster' => 'milly_poster' ],
			[ 'title' => 'Sade&rsquo;s Promotion',  'note' => 'The moment that Sade was promoted from Senior to Team Leader of the European Data Centre team.', 'video' => 'sade_video', 'poster' => 'sade_poster' ],
			[ 'title' => 'Harvey&rsquo;s promotion', 'note' => 'The office knew before he did',      'video' => 'harvey_video',    'poster' => 'harvey_poster' ],
			[ 'title' => 'Frank&rsquo;s promotion',  'note' => 'Confetti underfoot, the walk-in',    'video' => 'frank_video',     'poster' => 'frank_poster' ],
		];
		$verto_missing = 0;
		foreach ( $verto_events as $verto_s ) { if ( empty( $verto_media[ $verto_s['video'] ]['url'] ) ) $verto_missing++; }
		?>
		<section class="verto-events">
			<div class="verto-events__head">
				<div class="verto-events__intro">
					<span class="verto-eyebrow">Recent events</span>
					<h2 class="verto-display-3" style="margin-top:1.25rem;">The last few months, on film.</h2>
					<?php // Round 49 (Nov 2026, Alex): description + "Hover to watch" removed. ?>
				</div>
				<?php if ( $verto_missing ) : ?>
					<span class="verto-events__note">&#9888; <?php echo (int) $verto_missing; ?> film<?php echo 1 === $verto_missing ? '' : 's'; ?> pending &ndash; run Verto Setup &rarr; Rebuild to import the videos</span>
				<?php endif; ?>
			</div>
			<div class="verto-events__rail">
				<?php foreach ( $verto_events as $verto_s ) : ?>
					<?php if ( empty( $verto_media[ $verto_s['video'] ]['url'] ) ) continue; ?>
					<figure class="verto-events__tile">
						<div class="verto-events__media">
							<video preload="none" playsinline
								<?php if ( ! empty( $verto_media[ $verto_s['poster'] ]['url'] ) ) : ?>poster="<?php echo esc_url( $verto_media[ $verto_s['poster'] ]['url'] ); ?>"<?php endif; ?>
								src="<?php echo esc_url( $verto_media[ $verto_s['video'] ]['url'] ); ?>"></video>
							<button type="button" class="verto-events__play" aria-label="Play &ndash; <?php echo esc_attr( wp_strip_all_tags( html_entity_decode( $verto_s['title'] ) ) ); ?>">
								<span class="verto-post-card__play" aria-hidden="true"></span>
							</button>
						</div>
						<?php // Oct 2026 (Alex): dash gone, title and note on their own
						// lines, brighter so it reads on the ink background. ?>
						<figcaption class="verto-events__caption"><strong><?php echo wp_kses_post( $verto_s['title'] ); ?></strong><br /><?php echo wp_kses_post( $verto_s['note'] ); ?></figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
		</section>

		<!-- INSTAGRAM – same embed as the verto-socials widget -->
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
