<?php
/**
 * Job detail page — /jobs/{slug}/ for the public verto_job CPT
 * (plugins/verto-widgets/includes/vincere.php registers it; jobs come from
 * the Vincere sync OR the installer's standing internal vacancies).
 *
 * "If they click on a senior recruiter job for Edison Lux in Austin, it takes
 * them to an Austin page with photos of Austin and the team plus the job
 * advert and apply button" — so, top to bottom:
 *   1. HERO in the job's BRAND styling — brand logo + colour (inline
 *      --job-brand vars, body-class independent: an Edison Lux job renders
 *      Edison-blue even on the Verto group site), kicker, title, package,
 *      Apply button anchoring to the inline form.
 *   2. OFFICE — skyline + photo strip + blurb for the job's _location
 *      (Verto_Installer::location_gallery(): Solent / Austin / Miami-soon).
 *   3. TEAM — up to six verto_team people on the job's brand (fallback verto).
 *   4. ADVERT — post_content (Vincere public_description for synced jobs,
 *      authored copy for manual ones), .verto-prose.
 *   5. APPLY — the application form INLINE at #apply, same admin-post handler
 *      as the board's modal, job prefilled (Verto_Applications::render_inline).
 *
 * Degrades safely anywhere: every plugin/installer touchpoint is guarded, so
 * a brand site (or a site that never ran the installer) still renders hero +
 * advert + a contact link.
 */

defined( 'ABSPATH' ) || exit;

$verto_job = get_queried_object();
if ( ! $verto_job instanceof WP_Post ) {
	get_header();
	get_footer();
	return;
}

/* ── Job data ── */
$verto_job_title   = get_the_title( $verto_job );
$verto_job_brand   = (string) get_post_meta( $verto_job->ID, '_brand', true );
$verto_job_loc     = (string) get_post_meta( $verto_job->ID, '_location', true );
$verto_job_level   = (string) get_post_meta( $verto_job->ID, '_level', true );
$verto_job_package = (string) get_post_meta( $verto_job->ID, '_package', true );
if ( '' === $verto_job_package ) {
	$verto_job_package = (string) get_post_meta( $verto_job->ID, '_job_type', true );
}
if ( '' === $verto_job_package ) {
	$verto_job_package = 'Competitive package';
}
$verto_job_vid    = (string) get_post_meta( $verto_job->ID, '_vincere_id', true );
$verto_job_open   = '0' !== (string) get_post_meta( $verto_job->ID, '_active', true );
$verto_apply_url  = (string) get_post_meta( $verto_job->ID, '_apply_url', true ); // optional external portal

/* ── Brand styling map (colour pairs chosen for the dark hero) ── */
$verto_job_brands = [
	'verto'      => [ 'label' => 'Verto Group', 'color' => '#d19f2f', 'fg' => '#1F2A44' ],
	'edison-lux' => [ 'label' => 'Edison Lux', 'color' => '#2B8EE5', 'fg' => '#ffffff' ],
	'vertek'     => [ 'label' => 'Vertek', 'color' => '#F82B60', 'fg' => '#ffffff' ],
	'modulr'     => [ 'label' => 'ModulR', 'color' => '#7FA8FC', 'fg' => '#000A3B' ], // royal is too dark on ink — lifted per the board rows
];
if ( ! isset( $verto_job_brands[ $verto_job_brand ] ) ) {
	$verto_job_brand = 'verto';
}
$verto_job_b = $verto_job_brands[ $verto_job_brand ];

/* Brand logo — theme asset first, then installer media (same as header.php). */
$verto_job_logo = '';
if ( 'verto' === $verto_job_brand ) {
	$verto_job_logo = get_stylesheet_directory_uri() . '/assets/img/verto-logo.svg';
} else {
	foreach ( [ 'svg', 'png' ] as $verto_job_ext ) {
		if ( file_exists( get_stylesheet_directory() . "/assets/img/{$verto_job_brand}-logo.{$verto_job_ext}" ) ) {
			$verto_job_logo = get_stylesheet_directory_uri() . "/assets/img/{$verto_job_brand}-logo.{$verto_job_ext}";
			break;
		}
	}
}
$verto_job_media = get_option( 'verto_installer_media', [] );
if ( ! is_array( $verto_job_media ) ) {
	$verto_job_media = [];
}
if ( ! $verto_job_logo ) {
	$verto_job_logo_key = [ 'modulr' => 'logo_modulr_png', 'vertek' => 'logo_vertek', 'edison-lux' => 'logo_edison' ][ $verto_job_brand ] ?? '';
	$verto_job_logo     = $verto_job_media[ $verto_job_logo_key ]['url'] ?? '';
}

/* ── Office gallery for the job's location ── */
$verto_job_office = null;
if ( class_exists( 'Verto_Installer' ) && method_exists( 'Verto_Installer', 'location_gallery' ) ) {
	$verto_job_lockey = Verto_Installer::location_key( $verto_job_loc );
	if ( '' !== $verto_job_lockey ) {
		$verto_job_office = Verto_Installer::location_gallery()[ $verto_job_lockey ] ?? null;
	}
}
$verto_job_img = static function ( string $key ) use ( $verto_job_media ): string {
	return isset( $verto_job_media[ $key ]['url'] ) ? (string) $verto_job_media[ $key ]['url'] : '';
};

/* ── Team on this brand (fallback: the group) ── */
$verto_job_team = get_posts( [
	'post_type'      => 'verto_team',
	'posts_per_page' => 6,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'meta_query'     => [ [ 'key' => '_verto_brand', 'value' => $verto_job_brand, 'compare' => 'LIKE' ] ],
] );
if ( ! $verto_job_team && 'verto' !== $verto_job_brand ) {
	$verto_job_team = get_posts( [
		'post_type'      => 'verto_team',
		'posts_per_page' => 6,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'meta_query'     => [ [ 'key' => '_verto_brand', 'value' => 'verto', 'compare' => 'LIKE' ] ],
	] );
}

/* ── SEO: document title + meta description + OG (hooked before wp_head) ── */
$verto_job_seo_title = $verto_job_title . ' — ' . $verto_job_b['label'] . ', ' . ( $verto_job_loc ? $verto_job_loc : 'Verto' );
$verto_job_seo_desc  = wp_trim_words( wp_strip_all_tags( $verto_job->post_content ), 28 );
if ( '' === $verto_job_seo_desc ) {
	$verto_job_seo_desc = $verto_job_title . ' at ' . $verto_job_b['label'] . ' (' . $verto_job_loc . '). ' . $verto_job_package . '.';
}
$verto_job_seo_img = $verto_job_office ? $verto_job_img( (string) $verto_job_office['skyline'] ) : $verto_job_logo;

add_filter( 'pre_get_document_title', static function () use ( $verto_job_seo_title ) {
	return $verto_job_seo_title . ' | ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
} );
add_action( 'wp_head', static function () use ( $verto_job, $verto_job_seo_title, $verto_job_seo_desc, $verto_job_seo_img ) {
	printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $verto_job_seo_desc ) );
	printf( '<meta property="og:type" content="article" />' . "\n" );
	printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $verto_job_seo_title ) );
	printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $verto_job_seo_desc ) );
	printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( get_permalink( $verto_job ) ) );
	if ( $verto_job_seo_img ) {
		printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $verto_job_seo_img ) );
	}
	printf( '<meta name="twitter:card" content="summary_large_image" />' . "\n" );
}, 5 );

/* Back link: the careers board when that page exists on this site. */
$verto_job_back = get_page_by_path( 'careers' ) ? get_permalink( get_page_by_path( 'careers' ) ) : '';

get_header();
?>
<main class="verto-jobdetail" style="--job-brand:<?php echo esc_attr( $verto_job_b['color'] ); ?>;--job-brand-fg:<?php echo esc_attr( $verto_job_b['fg'] ); ?>;">

	<?php /* ── 1. HERO — the job's brand styling on ink ── */ ?>
	<section class="verto-jobhero">
		<div class="verto-container verto-jobhero__inner">
			<?php if ( $verto_job_back ) : ?>
				<a class="verto-jobhero__back" href="<?php echo esc_url( $verto_job_back ); ?>">&larr; All openings</a>
			<?php endif; ?>
			<?php if ( $verto_job_logo ) : ?>
				<img class="verto-jobhero__logo" src="<?php echo esc_url( $verto_job_logo ); ?>" alt="<?php echo esc_attr( $verto_job_b['label'] ); ?>" />
			<?php endif; ?>
			<div class="verto-jobhero__kicker">
				<?php echo esc_html( $verto_job_b['label'] ); ?>
				<?php if ( $verto_job_loc ) : ?><span class="sep" aria-hidden="true">·</span> <?php echo esc_html( $verto_job_loc ); ?><?php endif; ?>
				<?php if ( $verto_job_level ) : ?><span class="sep" aria-hidden="true">·</span> <?php echo esc_html( $verto_job_level ); ?><?php endif; ?>
			</div>
			<h1 class="verto-title-reveal verto-display-2 verto-jobhero__title">
				<span class="line-mask"><span class="line-inner"><?php echo esc_html( $verto_job_title ); ?></span></span>
			</h1>
			<p class="verto-jobhero__package"><?php echo esc_html( $verto_job_package ); ?></p>
			<div class="verto-jobhero__ctas">
				<?php if ( $verto_job_open ) : ?>
					<a class="btn-base verto-jobhero__apply" href="#apply">Apply for this role</a>
				<?php endif; ?>
				<a class="btn-base verto-jobhero__more" href="#advert">Read the full advert &darr;</a>
			</div>
		</div>
	</section>

	<?php /* ── 2. OFFICE — photos of the location + the blurb ── */ ?>
	<?php if ( $verto_job_office ) : ?>
	<section class="verto-joboffice">
		<div class="verto-container">
			<span class="verto-jobs__eyebrow">The office</span>
			<h2 class="verto-display-3 verto-joboffice__heading">Life in <?php echo esc_html( preg_replace( '/,.*$/', '', (string) $verto_job_office['name'] ) ); ?>.</h2>
			<p class="verto-joboffice__address"><?php echo esc_html( (string) $verto_job_office['address'] ); ?></p>
			<?php if ( ! empty( $verto_job_office['note'] ) ) : ?>
				<p class="verto-joboffice__note"><?php echo esc_html( (string) $verto_job_office['note'] ); ?></p>
			<?php endif; ?>
			<p class="verto-joboffice__blurb"><?php echo esc_html( (string) $verto_job_office['blurb'] ); ?></p>
			<?php
			$verto_job_shots = [];
			$verto_job_sky   = $verto_job_img( (string) $verto_job_office['skyline'] );
			if ( $verto_job_sky ) {
				$verto_job_shots[] = [ 'url' => $verto_job_sky, 'wide' => true ];
			}
			foreach ( (array) $verto_job_office['photos'] as $verto_job_pkey ) {
				$verto_job_purl = $verto_job_img( (string) $verto_job_pkey );
				if ( $verto_job_purl ) {
					$verto_job_shots[] = [ 'url' => $verto_job_purl, 'wide' => false ];
				}
			}
			?>
			<?php if ( $verto_job_shots ) : ?>
				<div class="verto-jobgallery">
					<?php foreach ( $verto_job_shots as $verto_job_shot ) : ?>
						<figure class="verto-jobgallery__item<?php echo $verto_job_shot['wide'] ? ' verto-jobgallery__item--wide' : ''; ?>">
							<img src="<?php echo esc_url( $verto_job_shot['url'] ); ?>" alt="" loading="lazy" />
						</figure>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php /* ── 3. TEAM — who you'd sit with ── */ ?>
	<?php if ( $verto_job_team ) : ?>
	<section class="verto-jobteam">
		<div class="verto-container">
			<span class="verto-jobs__eyebrow">The team</span>
			<h2 class="verto-display-3 verto-jobteam__heading">Who you&rsquo;d sit with.</h2>
			<div class="verto-jobteam__grid">
				<?php foreach ( $verto_job_team as $verto_job_person ) :
					$verto_job_role = get_post_meta( $verto_job_person->ID, '_verto_role', true );
					$verto_job_role = $verto_job_role ? $verto_job_role : 'Consultant';
					?>
					<div class="verto-jobteam__card">
						<div class="verto-jobteam__photo">
							<?php if ( has_post_thumbnail( $verto_job_person ) ) : ?>
								<?php echo get_the_post_thumbnail( $verto_job_person, 'medium', [ 'loading' => 'lazy' ] ); ?>
							<?php else : ?>
								<span class="verto-jobteam__initials"><?php
									echo esc_html( strtoupper( implode( '', array_map(
										static fn( $verto_job_np ) => mb_substr( $verto_job_np, 0, 1 ),
										array_slice( preg_split( '/\s+/', $verto_job_person->post_title ), 0, 2 )
									) ) ) );
								?></span>
							<?php endif; ?>
						</div>
						<div class="verto-jobteam__name"><?php echo esc_html( $verto_job_person->post_title ); ?></div>
						<div class="verto-jobteam__role"><?php echo esc_html( $verto_job_role ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<?php /* ── 4. ADVERT — the job description ── */ ?>
	<section class="verto-jobadvert" id="advert">
		<div class="verto-container">
			<span class="verto-jobs__eyebrow">The role</span>
			<div class="verto-prose verto-jobadvert__body">
				<?php
				$verto_job_content = apply_filters( 'the_content', $verto_job->post_content );
				if ( trim( wp_strip_all_tags( (string) $verto_job_content ) ) ) {
					echo $verto_job_content; // phpcs:ignore WordPress.Security.EscapeOutput -- post_content through the_content filters
				} else {
					echo '<p>Full details on application — tell us a little about yourself below and the consultant who owns this desk will come straight back to you.</p>';
				}
				?>
			</div>
		</div>
	</section>

	<?php /* ── 5. APPLY — inline form (same handler as the board's modal) ── */ ?>
	<section class="verto-jobapply" id="apply">
		<div class="verto-container">
			<?php if ( ! $verto_job_open ) : ?>
				<div class="verto-apply-inline">
					<h2 class="verto-apply-inline__title">This role has been filled.</h2>
					<p class="verto-apply-inline__sub">The desk isn&rsquo;t always listed but the door is always open — <a href="<?php echo esc_url( $verto_job_back ? $verto_job_back : home_url( '/contact' ) ); ?>">see the current openings</a> or send us a note anyway.</p>
				</div>
			<?php elseif ( '' !== $verto_apply_url ) : ?>
				<div class="verto-apply-inline">
					<h2 class="verto-apply-inline__title">Apply — <?php echo esc_html( $verto_job_title ); ?></h2>
					<p class="verto-apply-inline__sub">Applications for this role are handled on our careers portal.</p>
					<a class="btn-base btn-primary" href="<?php echo esc_url( $verto_apply_url ); ?>">Apply on the portal &nearr;</a>
				</div>
			<?php elseif ( class_exists( 'Verto_Applications' ) ) : ?>
				<?php Verto_Applications::render_inline( $verto_job_vid, $verto_job_title, get_permalink( $verto_job ) ); ?>
			<?php else : ?>
				<div class="verto-apply-inline">
					<h2 class="verto-apply-inline__title">Apply — <?php echo esc_html( $verto_job_title ); ?></h2>
					<p class="verto-apply-inline__sub">Email your CV to <a href="mailto:info@vertopeople.com">info@vertopeople.com</a> with the role in the subject line.</p>
				</div>
			<?php endif; ?>
		</div>
	</section>

</main>
<?php
get_footer();
