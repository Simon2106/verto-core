<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Site Installer — one-click site build.
 *
 * Adds wp-admin → Verto Setup with a "Build the Verto site" button that:
 *  1. Imports bundled media (logos, summit video/poster, Ibiza photos, award)
 *  2. Creates the pages (Home, About, Why Join Us, Contact) with our
 *     Elementor widgets already placed and configured
 *  3. Sets Home as the static front page
 *  4. Creates the Primary menu
 *
 * Idempotent: re-running updates the same pages (tracked via options).
 * Widgets left with empty settings fall back to their control defaults,
 * which are prefilled with the prototype content.
 */

class Verto_Installer {

	const MEDIA_OPTION = 'verto_installer_media';
	const PAGES_OPTION = 'verto_installer_pages';

	public static function boot() {
		add_action( 'admin_menu', function () {
			add_menu_page( 'Verto Setup', 'Verto Setup', 'manage_options', 'verto-setup', [ self::class, 'render_page' ], 'dashicons-hammer', 59 );
		} );
		add_action( 'admin_post_verto_build_site', [ self::class, 'handle_build' ] );
	}

	public static function render_page() {
		$built = get_option( self::PAGES_OPTION );
		?>
		<div class="wrap">
			<h1>Verto Setup</h1>
			<p>Builds the site from the prototype: imports media, creates the pages with Verto widgets pre-configured, sets the homepage and menu.</p>
			<?php if ( ! did_action( 'elementor/loaded' ) ) : ?>
				<div class="notice notice-error"><p><strong>Elementor is not active.</strong> Activate Elementor first, then build.</p></div>
			<?php endif; ?>
			<?php if ( $built ) : ?>
				<div class="notice notice-info"><p>Site was built previously. Building again updates the existing pages (your manual edits to those pages will be overwritten).</p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="verto_build_site" />
				<?php wp_nonce_field( 'verto_build_site' ); ?>
				<?php submit_button( $built ? 'Rebuild the Verto site' : 'Build the Verto site' ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_build() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nope.' );
		check_admin_referer( 'verto_build_site' );

		$media = self::import_media();
		self::seed_posts( $media );
		self::seed_team();
		self::create_pages( $media );
		self::create_brand_pages();
		self::setup_menu();

		wp_safe_redirect( admin_url( 'admin.php?page=verto-setup&built=1' ) );
		exit;
	}

	/** Import bundled files into the media library once; returns [name => [id, url]]. */
	private static function import_media(): array {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$map   = get_option( self::MEDIA_OPTION, [] );
		$dir   = dirname( __DIR__ ) . '/assets/import/';
		$files = [
			'summit_video'  => 'summit-video.mp4',
			'summit_poster' => 'summit-poster.jpg',
			'logo_edison'   => 'edison-lux-logo.png',
			'logo_modulr'   => 'modulr-logo.svg',
			'logo_modulr_png' => 'modulr-logo.png',
			'logo_vertek'   => 'vertek-logo-light.png',
			'ibiza8'        => 'ibiza8.jpg',
			'ibiza9'        => 'ibiza9.jpg',
			'award_bptw'    => 'BPTW_2026_SMALL_ORGANISATION_WHITE.png',
			'skyline_uk'    => 'skyline-uk.jpg',
			'skyline_us'    => 'skyline-us.jpg',
			'skyline_eu'    => 'skyline-eu.jpg',
		];

		foreach ( $files as $key => $file ) {
			if ( ! empty( $map[ $key ]['id'] ) && get_post( $map[ $key ]['id'] ) ) continue; // already imported
			$src = $dir . $file;
			if ( ! file_exists( $src ) ) continue;
			$tmp = wp_tempnam( $file );
			copy( $src, $tmp );
			$id = media_handle_sideload( [ 'name' => $file, 'tmp_name' => $tmp ], 0 );
			if ( is_wp_error( $id ) ) { @unlink( $tmp ); continue; }
			$map[ $key ] = [ 'id' => $id, 'url' => wp_get_attachment_url( $id ) ];
		}
		update_option( self::MEDIA_OPTION, $map );
		return $map;
	}

	private static function eid(): string {
		return substr( md5( uniqid( '', true ) ), 0, 7 );
	}

	/** Wrap widgets into a full-width section/column, with optional CSS class. */
	private static function section( array $widgets, string $css_class = '', array $extra = [] ): array {
		return [
			'id'       => self::eid(),
			'elType'   => 'section',
			'settings' => array_merge( [ 'layout' => 'full_width', 'css_classes' => $css_class ], $extra ),
			'elements' => [ [
				'id'       => self::eid(),
				'elType'   => 'column',
				'settings' => [ '_column_size' => 100 ],
				'elements' => $widgets,
			] ],
		];
	}

	private static function widget( string $type, array $settings = [] ): array {
		return [ 'id' => self::eid(), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [] ];
	}

	/** Two-column section: widgets_a left, widgets_b right (size = left col %). */
	private static function section2( array $widgets_a, array $widgets_b, string $css_class = '', int $left = 66 ): array {
		return [
			'id'       => self::eid(),
			'elType'   => 'section',
			'settings' => [ 'layout' => 'full_width', 'css_classes' => $css_class ],
			'elements' => [
				[ 'id' => self::eid(), 'elType' => 'column', 'settings' => [ '_column_size' => $left ], 'elements' => $widgets_a ],
				[ 'id' => self::eid(), 'elType' => 'column', 'settings' => [ '_column_size' => 100 - $left ], 'elements' => $widgets_b ],
			],
		];
	}

	private static function media_setting( array $map, string $key ): array {
		return empty( $map[ $key ] ) ? [] : [ 'url' => $map[ $key ]['url'], 'id' => $map[ $key ]['id'] ];
	}

	/** Create/update a page with Elementor data; returns page ID. */
	private static function upsert_page( string $slug, string $title, array $elements, int $parent = 0 ): int {
		$pages = get_option( self::PAGES_OPTION, [] );
		$id    = $pages[ $slug ] ?? 0;
		if ( ! $id || ! get_post( $id ) ) {
			$id = wp_insert_post( [
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_parent' => $parent,
			] );
			$pages[ $slug ] = $id;
			update_option( self::PAGES_OPTION, $pages );
		}
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
		if ( did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		return $id;
	}



	/** Seed the Team CPT from bundled headshots (once). */
	private static function seed_team(): void {
		if ( get_option( 'verto_installer_team' ) ) return;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$dir     = dirname( __DIR__ ) . '/assets/import/';
		$special = [
			'martin-doig'     => [ 'role' => 'Founder', 'leader' => true ],
			'robbie-sturgess' => [ 'role' => 'President', 'leader' => true ],
			'alex-hatfield'   => [ 'role' => 'Recruitment Leader', 'leader' => true ],
			'vertek-dan-bisset' => [ 'role' => 'VP of Engineering' ],
			'vertek-gary-hunt'  => [ 'role' => 'Head of Sales Recruitment' ],
			'vertek-ben-tiffin' => [ 'role' => 'Team Leader' ],
		];
		$order = 0;
		foreach ( glob( $dir . '*.{jpg,webp}', GLOB_BRACE ) as $file ) {
			$base = pathinfo( $file, PATHINFO_FILENAME );
			// only person photos: brand-prefixed or the three leaders
			$is_person = isset( $special[ $base ] ) || preg_match( '/^(edison|vertek|modulr)-/', $base );
			if ( ! $is_person || in_array( $base, [ 'skyline-uk', 'skyline-us', 'skyline-eu', 'ibiza8', 'ibiza9', 'summit-poster' ], true ) ) continue;
			$name_part = preg_replace( '/^(edison|vertek|modulr)-/', '', $base );
			$name      = ucwords( str_replace( '-', ' ', $name_part ) );
			$meta      = $special[ $base ] ?? [ 'role' => 'Consultant' ];
			$post_id   = wp_insert_post( [
				'post_title'  => $name,
				'post_type'   => 'verto_team',
				'post_status' => 'publish',
				'menu_order'  => ! empty( $meta['leader'] ) ? $order : $order + 100,
			] );
			$order++;
			if ( ! $post_id || is_wp_error( $post_id ) ) continue;
			update_post_meta( $post_id, '_verto_role', $meta['role'] );
			if ( ! empty( $meta['leader'] ) ) update_post_meta( $post_id, '_verto_leader', '1' );
			$tmp = wp_tempnam( basename( $file ) );
			copy( $file, $tmp );
			$att = media_handle_sideload( [ 'name' => basename( $file ), 'tmp_name' => $tmp ], $post_id );
			if ( ! is_wp_error( $att ) ) set_post_thumbnail( $post_id, $att );
		}
		update_option( 'verto_installer_team', 1 );
	}

	/** Seed the three "What's going on" placeholder posts (once). */
	private static function seed_posts( array $media ): void {
		if ( get_option( 'verto_installer_posts' ) ) return;
		$cat = wp_insert_term( 'Life at Verto', 'category' );
		$cat_id = is_wp_error( $cat ) ? ( get_term_by( 'name', 'Life at Verto', 'category' )->term_id ?? 0 ) : $cat['term_id'];
		$posts = [
			[ 'title' => 'Verto named in The Sunday Times Best Places to Work 2026',
			  'excerpt' => "Officially one of the UK's best small organisations to work for. Six years from a lockdown start-up to a Sunday Times listing — built on the same five values we started with.",
			  'image' => 'award_bptw' ],
			[ 'title' => 'Prague 2026 — the whole company, one incentive trip',
			  'excerpt' => 'Our second international incentive trip. Everyone who hit target, flights and all — this is what the 2× annual holiday incentive actually looks like.',
			  'image' => 'ibiza8' ],
			[ 'title' => 'Next stop: Ibiza — the 2026 summer incentive revealed',
			  'excerpt' => 'Barcelona 2025. Prague, January 2026. And this summer, the team that delivers gets Ibiza. The countdown is on.',
			  'image' => 'ibiza9' ],
		];
		$ids = [];
		foreach ( $posts as $post ) {
			$id = wp_insert_post( [
				'post_title'   => $post['title'],
				'post_excerpt' => $post['excerpt'],
				'post_content' => $post['excerpt'] . "\n\n⚠️ Placeholder story — replace with the client's real \"what's going on\" content.",
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_category'=> $cat_id ? [ $cat_id ] : [],
			] );
			if ( $id && ! is_wp_error( $id ) && ! empty( $media[ $post['image'] ]['id'] ) ) {
				set_post_thumbnail( $id, $media[ $post['image'] ]['id'] );
			}
			$ids[] = $id;
		}
		update_option( 'verto_installer_posts', $ids );
	}

	private static function create_pages( array $media ): void {
		/* ── HOME ── */
		$home = [
			self::section( [ self::widget( 'verto-hero', [
				'video'  => self::media_setting( $media, 'summit_video' ),
				'poster' => self::media_setting( $media, 'summit_poster' ),
			] ) ] ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'The brands',
					'lines'   => [
						[ '_id' => self::eid(), 'line' => 'Three brands. One' ],
						[ '_id' => self::eid(), 'line' => 'process-driven standard.' ],
					],
					'body'      => 'Founded in 2020, Verto connects exceptional technical and commercial people with the businesses that need them. Today, three focused brands — each with its own market, its own network and its own consultants — united by how we work.',
					'link_text' => 'Explore the Verto brands',
					'link'      => [ 'url' => '/about' ],
				] ),
				self::widget( 'verto-brand-tiles', [ 'items' => self::brand_tiles_items( $media ) ] ),
			], 'verto-muted verto-container-pad' ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => "Verto's values",
					'lines'   => [
						[ '_id' => self::eid(), 'line' => 'Five values.' ],
						[ '_id' => self::eid(), 'line' => 'Every desk, every day.' ],
					],
					'body' => "Every desk runs its own market and its own network. What's shared is what we stand for — the five values every person across the group works by.",
				] ),
				self::widget( 'verto-values' ),
			], 'verto-ink verto-glow verto-container-pad verto-container-pad--values' ),
			self::section2( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'What employees say about us',
					'size'    => 'verto-display-1',
					'lines'   => [
						[ '_id' => self::eid(), 'line' => "Don't take our" ],
						[ '_id' => self::eid(), 'line' => 'word for it.' ],
					],
					'body' => 'Real quotes from the team are on their way — these are placeholders while we collect them.',
				] ),
			], [
				self::widget( 'verto-v-mask-media', [
					'media_type'      => 'image',
					'image'           => self::media_setting( $media, 'ibiza8' ),
					'height'          => [ 'size' => 280, 'unit' => 'px' ],
					'overlay_opacity' => [ 'size' => 15 ],
				] ),
			], 'verto-ink verto-container-pad', 66 ),
			self::section( [
				self::widget( 'verto-quotes' ),
			], 'verto-ink verto-quotes-strip' ),
			self::section( [
				self::widget( 'verto-awards-strip', [ 'badge' => self::media_setting( $media, 'award_bptw' ) ] ),
			], 'verto-ink verto-awards-pad' ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => "What's going on",
					'lines'   => [
						[ '_id' => self::eid(), 'line' => 'Life inside' ],
						[ '_id' => self::eid(), 'line' => 'the group.' ],
					],
					'body'      => 'Incentive trips, awards, sales days and everything in between — straight from the team, not a marketing department.',
					'link_text' => "See everything that's going on",
					'link'      => [ 'url' => '/whats-going-on' ],
				] ),
				self::widget( 'verto-posts-grid' ),
			], 'verto-container-pad' ),
			self::section( [ self::widget( 'verto-jobs-board' ) ], 'verto-ink verto-container-pad' ),
		];
		// One-time migration: the careers page used to live at /why-join-us.
		$existing = get_option( self::PAGES_OPTION, [] );
		if ( ! empty( $existing['why-join-us'] ) && empty( $existing['careers'] ) ) {
			$existing['careers'] = $existing['why-join-us'];
			unset( $existing['why-join-us'] );
			update_option( self::PAGES_OPTION, $existing );
			wp_update_post( [ 'ID' => $existing['careers'], 'post_name' => 'careers', 'post_title' => 'Careers' ] );
		}

		$home_id = self::upsert_page( 'home', 'Home', $home );

		/* ── WHY JOIN US ── */
		$careers = [
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Why join us',
					'size'    => 'verto-display-1',
					'tag'     => 'h1',
					'lines'   => [
						[ '_id' => self::eid(), 'line' => 'Back yourself.' ],
						[ '_id' => self::eid(), 'line' => "We'll match it." ],
					],
					'body' => "40% commission. A share scheme that includes everyone. Two incentive holidays a year and a genuine route to the US. If you're going to work this hard anyway, do it somewhere that pays you properly — in money, ownership and experiences.",
				] ),
			], 'verto-container-pad' ),
			self::section( [ self::widget( 'verto-jobs-board', [ 'heading' => "Roles we're hiring right now." ] ) ], 'verto-ink verto-container-pad' ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Why Verto',
					'size'    => 'verto-display-3',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'Four reasons people join. One reason they stay.' ] ],
					'body'    => 'The package gets you in the door. The team is why the average consultant is still here years later.',
				] ),
				self::widget( 'verto-perks' ),
			], 'verto-container-pad' ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Career path',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'Where a desk here takes you.' ] ],
				] ),
				self::widget( 'verto-principles', [ 'columns' => '4', 'items' => [
					[ '_id' => self::eid(), 'kicker' => 'Months 0–12', 'title' => 'Trainee Consultant', 'body' => 'Phone-first training inside a live team. Structured L&D, a named mentor and your first placements.' ],
					[ '_id' => self::eid(), 'kicker' => 'Year 1–2', 'title' => 'Consultant', 'body' => 'Your own market and your own clients. Full 40% commission and your first incentive trips.' ],
					[ '_id' => self::eid(), 'kicker' => 'Year 2–4', 'title' => 'Senior Consultant', 'body' => "A market you're known in. Bigger deals, international briefs, and the option to relocate with your desk." ],
					[ '_id' => self::eid(), 'kicker' => 'Year 4+', 'title' => 'Principal / Team Manager', 'body' => 'Lead a team or go deeper as a biller — both paths carry equity and a seat in how the group grows.' ],
				] ] ),
			], 'verto-muted verto-container-pad' ),
			self::section2( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Incentives',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'Hit target. Board the plane.' ] ],
					'body'    => "Two international incentive trips a year, winners' lunches, sales days and personal training sessions. Barcelona 2025, Prague in January — Ibiza is next. (⚠️ Placeholder imagery — client's incentive clips and photos to come.)",
				] ),
			], [
				self::widget( 'image', [ 'image' => self::media_setting( $media, 'ibiza8' ), 'image_size' => 'full', '_css_classes' => 'verto-rounded-photo' ] ),
			], 'verto-ink verto-container-pad', 50 ),
			self::section( [ self::widget( 'verto-socials' ) ], 'verto-container-pad' ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Our locations',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'Three places to build from.' ] ],
				] ),
				self::widget( 'verto-footprint', [ 'items' => [
					[ '_id' => self::eid(), 'note' => 'Founding office', 'name' => 'Solent, UK', 'line1' => 'Where Verto started in 2020. Our largest office.', 'line2' => 'Vertek · ModulR · Life Sciences', 'image' => self::media_setting( $media, 'skyline_uk' ) ],
					[ '_id' => self::eid(), 'note' => 'US headquarters', 'name' => 'Austin, TX', 'line1' => 'Edison Lux and the Vertek US build-out.', 'line2' => 'The fastest-growing part of the group', 'image' => self::media_setting( $media, 'skyline_us' ) ],
					[ '_id' => self::eid(), 'note' => 'Coming soon', 'name' => 'Miami, FL', 'line1' => "ModulR's US practice and founding desks.", 'line2' => 'Ground-floor opportunity', 'image' => self::media_setting( $media, 'skyline_eu' ) ],
				] ] ),
			], 'verto-ink verto-container-pad' ),
		];
		self::upsert_page( 'careers', 'Careers', $careers );

		/* ── ABOUT ── */
		$about = [
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'About the Verto Group',
					'size'    => 'verto-display-1',
					'tag'     => 'h1',
					'lines'   => [
						[ '_id' => self::eid(), 'line' => 'Made in 2020.' ],
						[ '_id' => self::eid(), 'line' => 'Built the hard way.' ],
					],
					'body' => 'We opened our doors in February 2020 — and you know what happened next. Powered by determination and a lack of other options, Verto took its first steps as many others shut down. Today that lockdown business is all grown up: three specialist brands, a life sciences desk, and teams across the UK and US.',
				] ),
				self::widget( 'image', [
					'image'        => self::media_setting( $media, 'ibiza9' ),
					'image_size'   => 'full',
					'_css_classes' => 'verto-rounded-photo',
				] ),
				self::widget( 'heading', [
					'title'        => 'Ibiza — the 2026 summer incentive',
					'header_size'  => 'div',
					'_css_classes' => 'verto-photo-caption',
				] ),
			], 'verto-container-pad' ),
			self::section2( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'The thesis',
					'size'    => 'verto-display-3',
					'lines'   => [
						[ '_id' => self::eid(), 'line' => 'Why the group' ],
						[ '_id' => self::eid(), 'line' => 'exists.' ],
					],
				] ),
			], [
				self::widget( 'text-editor', [
					'editor'       => "<p>Verto is a group of specialist brands, each with its own market, its own networks and its own consultants who work nowhere else. What unites them is the Verto way of working: process-driven search built to get it right first time, feedback taken from every client and candidate interaction, and a belief that we're here to build teams — not fill seats.</p><p>A power operator hiring a Head of Commissioning shouldn't have to explain CCGT to their recruiter. A hydraulic OEM briefing a technical sales role shouldn't have to walk their consultant through what a proportional valve does. A hyperscaler filling a data-centre PM slot shouldn't receive commercial-fit-out CVs. Yet in generalist search, all three happen every week.</p><p>So we built the opposite. Every consultant sits inside one of three brands, each led by people who've worked or recruited inside that sector. Every desk owns its own network, its own reference-checked shortlist and its own view of who's moving in the market. The group behind them provides the research bench, the ops platform and the quality bar — and stays out of the search itself.</p>",
					'_css_classes' => 'verto-prose',
				] ),
			], 'verto-container-pad', 33 ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'The story so far',
					'lines'   => [ [ '_id' => self::eid(), 'line' => '2020 → today.' ] ],
					'body'    => 'From a lockdown start-up to The Sunday Times Best Places to Work — scroll the journey.',
				] ),
				self::widget( 'verto-timeline' ),
			], 'verto-ink verto-container-pad' ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'The brands',
					'lines'   => [
						[ '_id' => self::eid(), 'line' => 'Three teams. Three markets.' ],
						[ '_id' => self::eid(), 'line' => 'One standard.' ],
					],
					'body' => "Each brand runs independently — its own P&L, its own MD, its own client relationships. What's shared is the standard every search is held to. Our life sciences desk sits with the group while it grows.",
				] ),
				self::widget( 'verto-brand-tiles', [ 'items' => self::brand_tiles_items( $media ) ] ),
			], 'verto-muted verto-container-pad' ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'The Verto standard',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'Four principles every desk is held to.' ] ],
				] ),
				self::widget( 'verto-principles' ),
			], 'verto-container-pad' ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Our footprint',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'Solent. Austin. Soon, Miami.' ] ],
					'body'    => "Where it started, where it's grown, and where it's going next. Every location runs on the same platform, so a US brief with UK candidates — or the reverse — moves through one team.",
				] ),
				self::widget( 'verto-footprint', [ 'items' => [
					[ '_id' => self::eid(), 'note' => 'Where it started — Feb 2020', 'name' => 'Solent, UK', 'line1' => 'Arena Business Centre, Havant, Portsmouth', 'line2' => 'Vertek · ModulR · Verto Life Sciences', 'image' => self::media_setting( $media, 'skyline_uk' ) ],
					[ '_id' => self::eid(), 'note' => 'US HQ', 'name' => 'Austin, TX', 'line1' => '5900 Balcones Drive, Austin', 'line2' => 'Edison Lux · Vertek US', 'image' => self::media_setting( $media, 'skyline_us' ) ],
					[ '_id' => self::eid(), 'note' => 'Coming soon', 'name' => 'Miami, FL', 'line1' => 'Opening soon', 'line2' => 'ModulR US', 'image' => self::media_setting( $media, 'skyline_eu' ) ],
				] ] ),
			], 'verto-ink verto-container-pad' ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Leadership',
					'size'    => 'verto-display-3',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'Run by the people who built it.' ] ],
					'body'    => "Verto Group is founder-owned and independently financed. Every leader across the group has come up through the desk — either as a recruiter inside their sector, or as an operator hired by one of ours.",
				] ),
				self::widget( 'verto-team-grid', [ 'mode' => 'leaders' ] ),
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'The whole team',
					'size'    => 'verto-display-3',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'Everyone. Not just the leadership page.' ] ],
				] ),
				self::widget( 'verto-team-grid', [ 'mode' => 'all' ] ),
			], 'verto-container-pad' ),
			self::section( [ self::widget( 'verto-socials', [ 'eyebrow' => 'Behind the scenes', 'heading' => 'Us, off the phones.' ] ) ], 'verto-container-pad' ),
		];
		self::upsert_page( 'about', 'About', $about );

		/* ── CONTACT ── */
		$contact = [
			self::section( [
				self::widget( 'verto-title-reveal', [
					'tag'   => 'h1',
					'lines' => [
						[ '_id' => self::eid(), 'line' => 'Start the' ],
						[ '_id' => self::eid(), 'line' => 'conversation.' ],
					],
				] ),
				self::widget( 'text-editor', [ 'editor' => '<p>Thinking about joining Verto? Tell us about yourself and we\'ll come back within one business day.</p><div class="verto-form">[CF7-SHORTCODE-HERE — create the form in Contact → Contact Forms, then paste its shortcode into this text widget]</div>' ] ),
			], 'verto-container-pad' ),
		];
		self::upsert_page( 'contact', 'Contact', $contact );

		/* Posts hub ("What's going on") */
		$pages = get_option( self::PAGES_OPTION, [] );
		if ( empty( $pages['whats-going-on'] ) || ! get_post( $pages['whats-going-on'] ) ) {
			$news_id = wp_insert_post( [
				'post_title'  => "What's Going On",
				'post_name'   => 'whats-going-on',
				'post_type'   => 'page',
				'post_status' => 'publish',
			] );
			$pages['whats-going-on'] = $news_id;
			update_option( self::PAGES_OPTION, $pages );
		}
		update_option( 'page_for_posts', $pages['whats-going-on'] );

		/* Front page */
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_id );
	}

	private static function brand_tiles_items( array $media ): array {
		return [
			[ '_id' => self::eid(), 'name' => 'Edison Lux', 'focus' => 'US Energy Staffing', 'color' => '#2B8EE5', 'bg' => '#0B1A2B',
			  'invert_logo' => 'yes',
			  'logo' => self::media_setting( $media, 'logo_edison' ),
			  'positioning' => 'Edison Lux delivers talent solutions for the US energy sector — from control room operators to the C-suite leaders responsible for billion-dollar assets. One market. Done properly.',
			  'link' => [ 'url' => '#' ] ],
			[ '_id' => self::eid(), 'name' => 'ModulR', 'focus' => 'Architecture & Data Centres', 'color' => '#0464FA', 'bg' => '#000724',
			  'logo' => self::media_setting( $media, 'logo_modulr_png' ),
			  'positioning' => "ModulR connects standout architecture and data centre professionals with the built environment's most ambitious work — hyperscale campuses and award-winning practices.",
			  'link' => [ 'url' => '#' ] ],
			[ '_id' => self::eid(), 'name' => 'Vertek', 'focus' => 'Technical Sales, Service & Engineering', 'color' => '#F82B60', 'bg' => '#0E1013',
			  'logo' => self::media_setting( $media, 'logo_vertek' ),
			  'positioning' => 'Vertek recruits technical sales, service and engineering professionals for the manufacturers and distributors that keep industry moving — across the UK and US.',
			  'link' => [ 'url' => '#' ] ],
		];
	}


	/** Placeholder pages for the three brand sites (each becomes its own install later). */
	private static function create_brand_pages(): void {
		$parent = self::upsert_page( 'brands', 'Brands', [
			self::section( [ self::widget( 'verto-section-intro', [
				'eyebrow' => 'The Verto Group brands',
				'lines'   => [ [ 'line' => 'Three specialist brands,', '_id' => self::eid() ], [ 'line' => 'one group.', '_id' => self::eid() ] ],
				'body'    => 'Edison Lux, ModulR and Vertek each get their own standalone site. Until those launch, these pages hold their place.',
			] ) ], 'verto-container-pad' ),
		] );
		$brands = [
			'edison-lux' => [ 'Edison Lux', 'Executive search for energy & infrastructure. The standalone Edison Lux site is being built now — this link will point to it at launch.' ],
			'modulr'     => [ 'ModulR', 'Talent for modular construction & offsite manufacturing. The standalone ModulR site is being built now — this link will point to it at launch.' ],
			'vertek'     => [ 'Vertek', 'Technical sales, service & engineering recruitment. The standalone Vertek site is being built now — this link will point to it at launch.' ],
		];
		foreach ( $brands as $slug => [ $title, $body ] ) {
			self::upsert_page( $slug, $title, [
				self::section( [ self::widget( 'verto-section-intro', [
					'eyebrow'   => 'A Verto Group brand',
					'lines'     => [ [ 'line' => $title, '_id' => self::eid() ] ],
					'body'      => $body,
					'link_text' => 'Back to Verto Group',
					'link'      => [ 'url' => home_url( '/' ) ],
				] ) ], 'verto-container-pad' ),
			], $parent );
		}
	}

	private static function setup_menu(): void {
		$menu    = wp_get_nav_menu_object( 'Primary' );
		$menu_id = $menu ? $menu->term_id : wp_create_nav_menu( 'Primary' );

		// Rebuild from scratch every time so the menu always matches the prototype
		// header exactly (order, labels, slugs). Edit nav content via the installer.
		foreach ( (array) wp_get_nav_menu_items( $menu_id, [ 'post_status' => 'any' ] ) as $item ) {
			if ( $item ) wp_delete_post( $item->ID, true );
		}

		$pages = get_option( self::PAGES_OPTION, [] );
		$order = [
			'edison-lux'     => [ 'Edison Lux', 'verto-nav-brand' ],
			'modulr'         => [ 'ModulR', 'verto-nav-brand' ],
			'vertek'         => [ 'Vertek', 'verto-nav-brand verto-nav-last-brand' ],
			'whats-going-on' => [ "What's Going On", '' ],
			'about'          => [ 'About', '' ],
			'careers'        => [ 'Careers', '' ],
			'contact'        => [ 'Contact', '' ],
		];
		$i = 1;
		foreach ( $order as $slug => [ $title, $class ] ) {
			if ( empty( $pages[ $slug ] ) ) continue;
			wp_update_nav_menu_item( $menu_id, 0, [
				'menu-item-title'     => $title,
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $pages[ $slug ],
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $i++,
				'menu-item-classes'   => $class,
			] );
		}
		$locations = get_theme_mod( 'nav_menu_locations', [] );
		$locations['verto-primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}
}

Verto_Installer::boot();
