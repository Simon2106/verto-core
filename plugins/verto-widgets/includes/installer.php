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
			<?php $missing = get_option( 'verto_team_missing_photos', [] ); if ( $missing ) : ?>
				<div class="notice notice-warning"><p><strong>No bundled headshot for:</strong> <?php echo esc_html( implode( ', ', (array) $missing ) ); ?>.<br>They render with an initials placeholder until the client supplies photos — set each one via Team → Featured Image.</p></div>
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

		$brand = function_exists( 'verto_current_brand' ) ? verto_current_brand() : 'verto';

		if ( 'verto' !== $brand ) {
			// Brand-site install (modulr / vertek / edison-lux): build that
			// brand's standalone site instead of the Verto Group site.
			$media = self::import_media();
			self::seed_team();
			self::seed_brand_posts( $brand, $media );
			self::create_brand_site_pages( $brand, $media );
			self::setup_brand_menu( $brand );
			wp_safe_redirect( admin_url( 'admin.php?page=verto-setup&built=1' ) );
			exit;
		}

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
			'logo_edison_colour' => 'edison-lux-logo-colour.png',
			'logo_modulr'   => 'modulr-logo.svg',
			'logo_modulr_png' => 'modulr-logo.png',
			'logo_vertek'   => 'vertek-logo-light.png',
			'ibiza8'        => 'ibiza8.jpg',
			'ibiza9'        => 'ibiza9.jpg',
			'award_bptw'    => 'BPTW_2026_SMALL_ORGANISATION_WHITE.png',
			'award_recruiter' => 'weve-been-shortlisted.png',
			'skyline_uk'    => 'skyline-uk.jpg',
			'skyline_us'    => 'skyline-us.jpg',
			'skyline_eu'    => 'skyline-eu.jpg',
			// Brand-site media (ModulR)
			'modulr_hero'       => 'modulr-hero.png',
			'modulr_datacentre' => 'modulr-datacentre.webp',
			'about_image'       => 'about-image.jpg',
			'insight_datacentre'   => 'insight-datacentre.jpg',
			'insight_architecture' => 'insight-architecture.jpg',
			// Brand-site media (Edison Lux)
			'edison_hero'   => 'edison-hero.webp',
			'edison_pylon'  => 'edison-pylon.webp',
			'insight_power' => 'insight-power.jpg',
			'insight_epc'   => 'insight-epc.jpg',
			// Brand-site media (Vertek) — hero doubles as the landing-about
			// image (no images.landingAbout override in the prototype data)
			'vertek_hero'           => 'vertek-hero.jpg',
			'insight_fluidpower'    => 'insight-fluidpower.jpg',
			'insight_manufacturing' => 'insight-manufacturing.jpg',
			'insight_hvac'          => 'insight-hvac.jpg',
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
	private static function section2( array $widgets_a, array $widgets_b, string $css_class = '', int $left = 66, array $extra = [] ): array {
		return [
			'id'       => self::eid(),
			'elType'   => 'section',
			'settings' => array_merge( [ 'layout' => 'full_width', 'css_classes' => $css_class ], $extra ),
			'elements' => [
				[ 'id' => self::eid(), 'elType' => 'column', 'settings' => [ '_column_size' => $left, '_inline_size' => $left ], 'elements' => $widgets_a ],
				[ 'id' => self::eid(), 'elType' => 'column', 'settings' => [ '_column_size' => 100 - $left, '_inline_size' => 100 - $left ], 'elements' => $widgets_b ],
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



	/** Team-structure schema version — bump when the master map changes so
	 *  existing installs re-run the migration on their next Rebuild. */
	const TEAM_STRUCTURE = 'structure-0.10.0';

	/**
	 * The client's definitive team structure (Alex Hatfield, Aug 2026).
	 * name => brands (→ _verto_brand, comma-separated — a person can sit on
	 * several sites; 'verto' = the group site only), tier (→ _verto_tier:
	 * leadership | management | ops | team), role (→ _verto_role), photo
	 * (bundled headshot in assets/import/, or null — the widgets render an
	 * initials placeholder until the client supplies one), leader
	 * (→ _verto_leader, the group leadership trio). Array order = display
	 * order within each tier (stamped as menu_order).
	 */
	private static function team_map(): array {
		return [
			/* ── Leadership (group-wide; Alex + Robbie also on the Vertek site, Martin on ModulR) ── */
			'Alex Hatfield'   => [ 'brands' => [ 'verto', 'vertek' ], 'tier' => 'leadership', 'role' => 'President', 'photo' => 'alex-hatfield.webp', 'leader' => true ],
			'Martin Doig'     => [ 'brands' => [ 'verto', 'modulr' ], 'tier' => 'leadership', 'role' => 'Founder', 'photo' => 'martin-doig.jpg', 'leader' => true ],
			'Robbie Sturgess' => [ 'brands' => [ 'verto', 'vertek' ], 'tier' => 'leadership', 'role' => 'President', 'photo' => 'robbie-sturgess.webp', 'leader' => true ],
			/* ── Management (⚠ "Manager" = placeholder title — exact titles for
			      George East / Ben Cranston / Sade Kendall awaited from client) ── */
			'Dan Bisset'   => [ 'brands' => [ 'edison-lux' ], 'tier' => 'management', 'role' => 'VP of Engineering', 'photo' => 'vertek-dan-bisset.jpg' ],
			'George East'  => [ 'brands' => [ 'vertek' ], 'tier' => 'management', 'role' => 'Manager', 'photo' => 'vertek-george-east.jpg' ],
			'Ben Tiffin'   => [ 'brands' => [ 'vertek' ], 'tier' => 'management', 'role' => 'Team Leader', 'photo' => 'vertek-ben-tiffin.jpg' ],
			'Gary Hunt'    => [ 'brands' => [ 'vertek' ], 'tier' => 'management', 'role' => 'Head of Sales Recruitment', 'photo' => 'vertek-gary-hunt.jpg' ],
			'Ben Cranston' => [ 'brands' => [ 'vertek' ], 'tier' => 'management', 'role' => 'Manager', 'photo' => null ],
			'Sade Kendall' => [ 'brands' => [ 'modulr' ], 'tier' => 'management', 'role' => 'Manager', 'photo' => 'modulr-sade-kendall.webp' ],
			/* ── Ops (Verto group pages only — fold into "The team" section) ── */
			'Karabo Mothopeng' => [ 'brands' => [ 'verto' ], 'tier' => 'ops', 'role' => 'Data Administrator', 'photo' => null ],
			'Angel Ndlovu'     => [ 'brands' => [ 'verto' ], 'tier' => 'ops', 'role' => 'Data Administrator', 'photo' => null ],
			'Alice Fryer'      => [ 'brands' => [ 'verto' ], 'tier' => 'ops', 'role' => 'Operations & Executive Assistant', 'photo' => null ],
			'Megan Grant'      => [ 'brands' => [ 'verto' ], 'tier' => 'ops', 'role' => 'Senior Marketing Executive', 'photo' => null ],
			'Alfie Gray'       => [ 'brands' => [ 'verto' ], 'tier' => 'ops', 'role' => 'Digital Marketing Executive', 'photo' => null ],
			/* ── Consultants — Vertek ── */
			'Olivia Pinhorne'  => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'vertek-olivia-pinhorne.jpg' ],
			'Rex Reavley'      => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'vertek-rex-reavley.jpg' ],
			'Jake Massingham'  => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'vertek-jake-massingham.jpg' ],
			'Sam Parnell'      => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'vertek-sam-parnell.jpg' ],
			'Saman Akbari'     => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => null ],
			'Harvey Earl'      => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'vertek-harvey-earl.jpg' ],
			'Lewis Sullivan'   => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'vertek-lewis-sullivan.jpg' ],
			'Frank Warner'     => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'vertek-frank-warner.jpg' ],
			'Alex Wright'      => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'vertek-alex-wright.jpg' ],
			'Lethu Zwane'      => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'vertek-lethu-zwane.jpg' ],
			'Lewis Mason'      => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'vertek-lewis-mason.webp' ],
			"Harley O'Connell" => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'vertek-harley-oconnell.jpg' ],
			'Alice Schofield'  => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'vertek-alice-schofield.jpg' ],
			/* ── Consultants — Verto Life Sciences (sits with the group) ── */
			'Martyn Jamieson'  => [ 'brands' => [ 'verto' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => null ],
			/* ── Consultants — ModulR ── */
			'Lewis Wright'      => [ 'brands' => [ 'modulr' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'modulr-lewis-wright.jpg' ],
			'Monira Akter'      => [ 'brands' => [ 'modulr' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'modulr-monira-aktar.jpg' ],
			'Charlotte Northam' => [ 'brands' => [ 'modulr' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'modulr-charlotte-northam.jpg' ],
			'Forough Rezaei'    => [ 'brands' => [ 'modulr' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => null ],
			'Natasha Sykes'     => [ 'brands' => [ 'modulr' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'vertek-natasha-sykes.jpg' ],
			/* ── Consultants — Edison Lux ── */
			'Joe Williams'       => [ 'brands' => [ 'edison-lux' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'edison-joe-williams.jpg' ],
			'Matthew Pearce'     => [ 'brands' => [ 'edison-lux' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'edison-matthew-pearce.jpg' ],
			'Lewis Dominy'       => [ 'brands' => [ 'edison-lux' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'edison-lewis-dominy.jpg' ],
			'Noah Ward'          => [ 'brands' => [ 'edison-lux' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'edison-noah-ward.jpg' ],
			'Ollie Hesmondhalgh' => [ 'brands' => [ 'edison-lux' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'vertek-oliver-hesmondhalgh.jpg' ],
			'Milly Compton'      => [ 'brands' => [ 'edison-lux' ], 'tier' => 'team', 'role' => 'Consultant', 'photo' => 'edison-milly-compton.jpg' ],
		];
	}

	/**
	 * Seed / migrate the Team CPT from the master map. Runs once per
	 * TEAM_STRUCTURE version: fresh installs get the full roster; installs
	 * seeded by the pre-0.10 filename-guessed seeder are migrated in place —
	 * matched by post_title (with rename aliases), meta re-asserted
	 * (brands / tier / role / leader), missing people created, and people no
	 * longer in the official structure unpublished (drafted, never deleted).
	 */
	private static function seed_team(): void {
		if ( self::TEAM_STRUCTURE === get_option( 'verto_installer_team' ) ) return;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$dir = dirname( __DIR__ ) . '/assets/import/';
		// Titles the old filename-derived seeder produced → official names.
		$aliases = [
			'Monira Aktar'        => 'Monira Akter',
			'Oliver Hesmondhalgh' => 'Ollie Hesmondhalgh',
			'Harley Oconnell'     => "Harley O'Connell",
		];
		// Previously seeded people who are NOT in the client's official
		// structure — unpublished on migration (kept as drafts, not deleted).
		$retired = [ 'Abi Ward', 'Cj Edwards', 'CJ Edwards', 'Chris J Simmons', 'Chris J. Simmons' ];

		$existing = [];
		foreach ( get_posts( [ 'post_type' => 'verto_team', 'numberposts' => -1, 'post_status' => 'any' ] ) as $p ) {
			$existing[ $p->post_title ] = $p->ID;
		}

		$order   = 0;
		$missing = [];
		foreach ( self::team_map() as $name => $m ) {
			$id = $existing[ $name ] ?? 0;
			if ( ! $id ) { // renamed since the old seeder? migrate the post in place
				foreach ( $aliases as $old => $new ) {
					if ( $new === $name && ! empty( $existing[ $old ] ) ) {
						$id = $existing[ $old ];
						break;
					}
				}
			}
			if ( $id ) {
				wp_update_post( [ 'ID' => $id, 'post_title' => $name, 'menu_order' => $order, 'post_status' => 'publish' ] );
			} else {
				$id = wp_insert_post( [
					'post_title'  => $name,
					'post_type'   => 'verto_team',
					'post_status' => 'publish',
					'menu_order'  => $order,
				] );
			}
			$order++;
			if ( ! $id || is_wp_error( $id ) ) continue;
			update_post_meta( $id, '_verto_role', $m['role'] );
			update_post_meta( $id, '_verto_brand', implode( ',', $m['brands'] ) );
			update_post_meta( $id, '_verto_tier', $m['tier'] );
			if ( ! empty( $m['leader'] ) ) {
				update_post_meta( $id, '_verto_leader', '1' );
			} else {
				delete_post_meta( $id, '_verto_leader' );
			}
			if ( empty( $m['photo'] ) || ! file_exists( $dir . $m['photo'] ) ) {
				if ( ! has_post_thumbnail( $id ) ) $missing[] = $name; // initials placeholder renders instead
				continue;
			}
			if ( has_post_thumbnail( $id ) ) continue; // photo already attached
			$tmp = wp_tempnam( $m['photo'] );
			copy( $dir . $m['photo'], $tmp );
			$att = media_handle_sideload( [ 'name' => $m['photo'], 'tmp_name' => $tmp ], $id );
			if ( ! is_wp_error( $att ) ) set_post_thumbnail( $id, $att );
		}
		foreach ( $retired as $name ) {
			if ( ! empty( $existing[ $name ] ) ) {
				wp_update_post( [ 'ID' => $existing[ $name ], 'post_status' => 'draft' ] );
			}
		}
		update_option( 'verto_team_missing_photos', $missing );
		update_option( 'verto_installer_team', self::TEAM_STRUCTURE );
	}

	/** Seed the three "What's going on" placeholder posts (once).
	 *  Magazine categories (Trips / Wins / Community / News) power the
	 *  category chips on the WGO hub — all four terms are created so the
	 *  client can file new stories straight away. */
	private static function seed_posts( array $media ): void {
		$cat_ids = [];
		foreach ( [ 'Trips', 'Wins', 'Community', 'News' ] as $cat_name ) {
			$t = wp_insert_term( $cat_name, 'category' );
			$cat_ids[ $cat_name ] = is_wp_error( $t ) ? ( get_term_by( 'name', $cat_name, 'category' )->term_id ?? 0 ) : $t['term_id'];
		}
		// Migration: earlier builds filed the seeded posts under "Life at Verto" —
		// on rebuild, move any seeded post still in that category to its new one.
		$existing = get_option( 'verto_installer_posts' );
		if ( $existing ) {
			$map = [ 'Sunday Times' => 'Wins', 'Prague' => 'Trips', 'Ibiza' => 'Trips' ];
			foreach ( (array) $existing as $pid ) {
				$post = get_post( $pid );
				if ( ! $post ) continue;
				foreach ( $map as $needle => $cat ) {
					if ( str_contains( $post->post_title, $needle ) && ! empty( $cat_ids[ $cat ] ) ) {
						wp_set_post_categories( $pid, [ $cat_ids[ $cat ] ] );
						break;
					}
				}
			}
			$old = get_term_by( 'name', 'Life at Verto', 'category' );
			if ( $old && 0 === (int) $old->count ) wp_delete_term( $old->term_id, 'category' );
			return; // posts already seeded — categories now corrected
		}
		$posts = [
			[ 'title' => 'Verto named in The Sunday Times Best Places to Work 2026',
			  'excerpt' => "Officially one of the UK's best small organisations to work for. Six years from a lockdown start-up to a Sunday Times listing — built on the same five values we started with.",
			  'image' => 'award_bptw', 'category' => 'Wins' ],
			[ 'title' => 'Prague 2026 — the whole company, one incentive trip',
			  'excerpt' => 'Our second international incentive trip. Everyone who hit target, flights and all — this is what the 2× annual holiday incentive actually looks like.',
			  'image' => 'ibiza8', 'category' => 'Trips' ],
			[ 'title' => 'Next stop: Ibiza — the 2026 summer incentive revealed',
			  'excerpt' => 'Barcelona 2025. Prague, January 2026. And this summer, the team that delivers gets Ibiza. The countdown is on.',
			  'image' => 'ibiza9', 'category' => 'Trips' ],
		];
		$ids = [];
		foreach ( $posts as $post ) {
			$cat_id = $cat_ids[ $post['category'] ] ?? 0;
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
			// Client feedback round 3, item 3 — home order: Hero → Brands →
			// Jobs → What's Going On → Employee voices/quotes + awards →
			// Values → Instagram. Standalone sector coverage is gone from
			// Home (it lives on the brand tiles' hover faces); it stays on About.
			self::section( [ self::widget( 'verto-jobs-board' ) ], 'verto-ink verto-container-pad' ),
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
				self::widget( 'verto-awards-strip', [
					'badge'  => self::media_setting( $media, 'award_bptw' ),
					'badge2' => self::media_setting( $media, 'award_recruiter' ),
				] ),
			], 'verto-ink verto-awards-pad' ),
			// Values moved below the voices/awards block (round 3, item 3 swap
			// with What's Going On, which now sits directly under the jobs board).
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
			// Client feedback round 2, item 13: Instagram feed on the homepage.
			self::section( [ self::widget( 'verto-socials' ) ], 'verto-container-pad' ),
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
				// Photo collage replaces the single Ibiza hero image (approved design).
				self::widget( 'verto-collage', [ 'items' => [
					[ '_id' => self::eid(), 'size' => 'big',  'image' => self::media_setting( $media, 'ibiza9' ),        'alt' => 'The Verto team in Ibiza',            'caption' => 'Ibiza — the 2026 summer incentive' ],
					[ '_id' => self::eid(), 'size' => 'wide', 'image' => self::media_setting( $media, 'summit_poster' ), 'alt' => 'The Verto summer summit',            'caption' => 'The summer summit' ],
					[ '_id' => self::eid(), 'size' => 'std',  'image' => self::media_setting( $media, 'ibiza8' ),        'alt' => 'The team on an incentive trip',      'caption' => '' ],
					[ '_id' => self::eid(), 'size' => 'std',  'image' => self::media_setting( $media, 'about_image' ),   'alt' => 'The team at work',                   'caption' => '' ],
					[ '_id' => self::eid(), 'size' => 'wide', 'image' => self::media_setting( $media, 'skyline_uk' ),    'alt' => 'Solent, UK — where it started',      'caption' => 'Solent, UK' ],
					[ '_id' => self::eid(), 'size' => 'wide', 'image' => self::media_setting( $media, 'skyline_us' ),    'alt' => 'Austin, TX — the US build-out',      'caption' => 'Austin, TX' ],
				] ] ),
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
					'eyebrow'       => 'The story so far',
					'eyebrow_plain' => 'yes',
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
			// Sector coverage duplicated onto About (client feedback round 2, item 9).
			self::section( [ self::widget( 'verto-sector-coverage' ) ], 'verto-muted verto-container-pad' ),
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
				// Client's official structure: Leadership → Management → The team
				// (ops fold into the team section on the group pages).
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Management',
					'size'    => 'verto-display-3',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'The people running the desks.' ] ],
				] ),
				self::widget( 'verto-team-grid', [ 'mode' => 'all', 'tier' => 'management' ] ),
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'The team',
					'size'    => 'verto-display-3',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'Everyone. Not just the leadership page.' ] ],
				] ),
				self::widget( 'verto-team-grid', [ 'mode' => 'all', 'tier' => 'team' ] ),
			], 'verto-container-pad' ),
			// Community & DE&I — placeholder cards awaiting client photography
			// (approved design; sits directly before the socials section).
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Community & DE&I',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'More than the numbers.' ] ],
					'body'    => 'Gala nights, fundraising and a genuine commitment to building a diverse group — the parts of Verto that never make a sales deck.',
				] ),
				self::widget( 'text-editor', [ 'editor' => self::community_cards_html() ] ),
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

	/** Community & DE&I placeholder cards (⚠ photos coming from client). */
	private static function community_cards_html(): string {
		$cards = [
			[ 'Gala nights', 'Black-tie charity galas — including the night that raised £15,504 for the Amelia-Mae Foundation.' ],
			[ 'Charity & fundraising', 'Every office backs a cause the team chooses — fundraisers, sponsored events and hands-on volunteering through the year.' ],
			[ 'DE&I commitments', 'Hiring on ability, progressing on results. Our DE&I commitments — and the numbers behind them — publish here soon.' ],
		];
		$html = '<div class="verto-community">';
		foreach ( $cards as [ $title, $body ] ) {
			$html .= '<article class="verto-community__card">'
				. '<div class="verto-community__ph">'
				. ( function_exists( 'verto_icon' ) ? verto_icon( 'camera' ) : '' )
				. '<span class="verto-community__phnote">Photos coming from client</span>'
				. '</div>'
				. '<div class="verto-community__body">'
				. '<h3 class="verto-community__title">' . esc_html( $title ) . '</h3>'
				. '<p class="verto-community__text">' . esc_html( $body ) . '</p>'
				. '</div>'
				. '</article>';
		}
		return $html . '</div>';
	}

	private static function brand_tiles_items( array $media ): array {
		return [
			[ '_id' => self::eid(), 'name' => 'Edison Lux', 'focus' => 'US Energy Staffing', 'color' => '#2B8EE5', 'bg' => '#0B1A2B',
			  // Client feedback round 3, item 2: the tile shows the COLOURED
			  // Edison primary logo (gradient mark + dark text). It clashes on
			  // the round-2 gradient face, so the face is white/very-light with
			  // dark text and no blue top stripe.
			  'light_face' => 'yes',
			  'sectors' => "Critical Power & CCGT\nRenewables & Storage\nEPC & Project Delivery\nO&M (Operations & Maintenance)",
			  'logo' => self::media_setting( $media, 'logo_edison_colour' ),
			  'positioning' => 'Edison Lux delivers talent solutions for the US energy sector — from control room operators to the C-suite leaders responsible for billion-dollar assets. One market. Done properly.',
			  'link' => [ 'url' => '#' ] ],
			[ '_id' => self::eid(), 'name' => 'ModulR', 'focus' => 'Architecture & Data Centres', 'color' => '#0464FA', 'bg' => '#000724',
			  'sectors' => "Hyperscale Data Centres\nUS Architecture\nMEP Engineering\nInterior Design & Fit-out",
			  'logo' => self::media_setting( $media, 'logo_modulr_png' ),
			  'positioning' => "ModulR connects standout architecture and data centre professionals with the built environment's most ambitious work — hyperscale campuses and award-winning practices.",
			  'link' => [ 'url' => '#' ] ],
			[ '_id' => self::eid(), 'name' => 'Vertek', 'focus' => 'Technical Sales, Service & Engineering', 'color' => '#F82B60', 'bg' => '#0E1013',
			  'sectors' => "Fluid Power & Hydraulics\nHVAC & Refrigeration\nAdvanced Manufacturing\nInstrumentation & Controls",
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

	/* ═══════════════════ Brand sites (ModulR first) ═══════════════════ */

	/**
	 * All per-brand copy, ported verbatim from the prototype's
	 * src/lib/brands.ts BRANDS[...] (+ FEATURES / HERO_SUB /
	 * SPECIALISM_ICONS in brands.$brand.index.tsx).
	 * Vertek / Edison Lux later = fill in their arrays here; the page
	 * builders below are brand-agnostic.
	 */
	private static function brand_content( string $brand ): array {
		$all = [
			'modulr' => [
				'name'        => 'Modulr',
				'focus'       => 'Architecture & Data Centres',
				'focus_lower' => 'architecture & data centres',
				'hero'        => [
					'line1'  => 'Connecting talent.',
					'line2'  => 'Powering progress',
					'sub'    => "Modulr connects standout architecture and data centre professionals with the built environment's most ambitious work — hyperscale campuses, award-winning practices and the projects you won't find advertised.",
					'image'  => 'modulr_hero',
					'alt'    => 'Glowing globe at night with arcs of light connecting cities',
					'scale'  => 1,
					'offset' => 30,
				],
				'features' => [
					[ 'icon' => 'globe-2',   'title' => 'UK, EU & US',             'body' => 'Hyperscale, colocation and celebrated US architecture — three regions, one network.' ],
					[ 'icon' => 'compass',   'title' => 'Curated Introductions',   'body' => 'Considered shortlists with real context. Never CVs into the void.' ],
					[ 'icon' => 'lock',      'title' => 'NDA-Grade Discretion',    'body' => 'Sensitive, pre-announcement and competitor-adjacent search handled as standard.' ],
					[ 'icon' => 'handshake', 'title' => 'Long-Game Relationships', 'body' => 'We track careers and project pipelines to add value before the urgent need arises.' ],
				],
				'about'        => [
					'headline' => 'Considered introductions, not CVs into the void.',
					'mission'  => "Build teams for the projects that will define a generation — staffing what others can't, working quickly and discreetly, and to the standard those projects demand.",
					'vision'   => 'A world where no exceptional architect or built-environment professional is stuck because the right opportunity was invisible to them — and the first call every project director makes when they need to scale.',
					'purpose'  => 'Championing the professionals who build our world — opening doors that don\'t exist yet, so the most ambitious projects in architecture and critical infrastructure are built by the best people, not whoever applied first.',
				],
				'about_image'     => 'modulr_datacentre',
				'about_image_alt' => 'Data centre corridor lined with server racks and glowing status lights',
				'stats' => [
					[ 'value' => '3 regions',      'label' => 'UK, EU and US coverage' ],
					[ 'value' => 'Full lifecycle', 'label' => 'Concept design to commissioning' ],
					[ 'value' => 'NDA-grade',      'label' => 'Discretion on every search' ],
				],
				'specialisms' => [
					[ 'icon' => 'server',          'title' => 'Hyperscale Data Centres', 'description' => 'Construction directors, regional heads and project leadership across operators, developers and contractors.' ],
					[ 'icon' => 'network',         'title' => 'Colocation & Edge',       'description' => 'Delivery and operations talent for colo and edge programmes at every stage.' ],
					[ 'icon' => 'building-2',      'title' => 'US Architecture',         'description' => 'Registered architects, project architects, directors, principals and partners.' ],
					[ 'icon' => 'zap',             'title' => 'MEP Engineering',         'description' => 'Mechanical, electrical and plumbing leadership across the US project landscape.' ],
					[ 'icon' => 'layers',          'title' => 'Project Lifecycle',       'description' => 'CD → SD → DD → CD → CA. Concept design through construction administration.' ],
					[ 'icon' => 'heart-handshake', 'title' => 'Inclusion & EDI',         'description' => 'Championing women in architecture and EDI across technical built-environment roles.' ],
				],
				'audiences' => [
					'company' => [
						'headline' => "Your next project is out there. It just isn't advertised.",
						'body'     => "Whether you're scaling a data centre programme or shaping a skyline, one conversation opens doors that don't exist yet. Considered introductions, never CVs into the void — and discretion as standard.",
						'bullets'  => "Curated introductions, not mass outreach\nConfidential and NDA-grade search handled as standard\nProject team builds — contract and permanent\nLong-game relationships across the project pipeline",
						'cta'      => 'Scale your project team',
					],
					'candidate' => [
						'headline' => 'The best projects are rarely advertised.',
						'body'     => 'The best talent is rarely searching. Modulr exists in that gap — making precise, considered introductions rather than firing CVs into the void, and protecting reputations on every engagement.',
						'bullets'  => "Hyperscale, colo, US architecture and MEP opportunities\nExclusive, often NDA-protected briefs\nCareer trajectory advice across the full project lifecycle\nDiscreet, considered, never transactional",
						'cta'      => 'Find your next project',
					],
				],
				'about_hero' => [ 'pre' => 'The projects that define', 'accent' => 'a generation.', 'post' => 'Built by the right people.' ],
				'positioning' => "Modulr connects standout architecture and data centre professionals with the built environment's most ambitious work — hyperscale campuses, award-winning practices, and the projects you won't find advertised.",
				'what_we_do' => [
					'headline'   => 'Embedded in the projects that define a generation.',
					'paragraphs' => "Hyperscale data centres, colocation and edge, US architecture, MEP engineering and the full concept-to-commissioning lifecycle — this is where our network runs deepest. Every consultant works one part of the built environment, not the whole map.\n\nFor project directors, developers and practice principals, we operate as a discreet extension of the leadership team — considered introductions rather than CVs into the void, with NDA-grade discretion as standard.",
				],
				'proof' => [
					'Trusted by global operators, developers and celebrated US practices',
					'Active networks across the UK, EU and US markets',
					'NDA-grade discretion on every sensitive and pre-announcement search',
					'Inclusion work championing women in architecture and EDI in technical built-environment roles',
				],
				'team_focus' => 'Built environment search.',
				'insights'   => [
					[ 'title' => 'Case study: scaling a hyperscale build team across three regions in 90 days',
					  'excerpt' => 'How we delivered a 14-strong project leadership team for a Tier-1 operator under NDA, on schedule, with zero attrition.',
					  'image' => 'insight_datacentre' ],
					[ 'title' => 'US Architecture & AOR market update — Q2 2026',
					  'excerpt' => 'Where the principals, directors and partners are moving across mixed-use, hospitality and healthcare practices.',
					  'image' => 'insight_architecture' ],
					[ 'title' => 'Building the women-in-architecture pipeline: what actually works',
					  'excerpt' => 'Three years of EDI-led search work distilled into the interventions that move the needle on retention and promotion.',
					  'image' => 'insight_architecture' ],
				],
			],
			'edison-lux' => [
				'name'        => 'Edison Lux',
				'focus'       => 'US Energy Staffing',
				'focus_lower' => 'us energy staffing',
				'hero'        => [
					'line1'  => 'Powering progress.',
					'line2'  => 'Together',
					'sub'    => 'Edison Lux delivers talent solutions for the US energy sector — from control room operators to the C-suite leaders responsible for billion-dollar assets. One market. Done properly.',
					'image'  => 'edison_hero',
					'alt'    => 'Hydroelectric dam releasing water through spillway gates with forested mountains behind',
					'scale'  => 1.18,
					'offset' => 0,
				],
				'features' => [
					[ 'icon' => 'users',       'title' => "US Energy.\nNothing Else.",   'body' => "It's all we do — so no client brief sits outside our knowledge base." ],
					[ 'icon' => 'star',        'title' => 'Basement To Boardroom',       'body' => 'Control room operators, shift supervisors, VPs, C-suite — the full talent hierarchy staffed.' ],
					[ 'icon' => 'handshake',   'title' => "World-Class\nNPS",            'body' => 'Feedback captured from every candidate and client interaction — and it shows.' ],
					[ 'icon' => 'trending-up', 'title' => "100% Engaged\nSuccess",       'body' => "Speed and accuracy together — because when a COD is at risk, you shouldn't have to choose." ],
				],
				'about'        => [
					'headline' => 'One market, known completely.',
					'mission'  => 'Deliver talent solutions with the precision the industry demands — speed and accuracy together, so when a plant is short-staffed or a COD is at risk you never have to choose between them.',
					'vision'   => "To be the staffing partner the US energy sector reaches out to first — the most knowledgeable and connected specialist in America's energy transition.",
					'purpose'  => 'Named for the man who lit up the world, Edison Lux shines a light on the talent that powers everything — bridging the energy skills gap before it becomes a crisis.',
				],
				'about_image'     => 'edison_pylon',
				'about_image_alt' => 'Silhouetted electricity transmission pylons at sunset with a substation on the horizon',
				'stats' => [
					[ 'value' => '100%',                 'label' => 'Success rate on engaged search' ],
					[ 'value' => 'Basement → Boardroom', 'label' => 'Operator to C-suite coverage' ],
					[ 'value' => 'US-only',              'label' => 'Undivided sector focus' ],
				],
				'specialisms' => [
					[ 'icon' => 'flame',     'title' => 'Critical & Mission-Critical Power',   'description' => 'Control rooms, O&M and engineering leadership for facilities where uptime is non-negotiable.' ],
					[ 'icon' => 'wind',      'title' => 'Combined Cycle & Gas Generation',     'description' => 'Combined cycle, simple cycle, recips and gas compression — outages, upgrades and permanent operations.' ],
					[ 'icon' => 'hard-hat',  'title' => 'Renewables & Energy Transition',      'description' => 'Solar, wind, battery storage, hydrogen and RNG — construction, commissioning and operations.' ],
					[ 'icon' => 'wrench',    'title' => 'Biomass, EFW & Waste-to-Energy',      'description' => 'Biomass, EfW, coal and CHP — teams that know the fuel, the plant and the regulations.' ],
					[ 'icon' => 'atom',      'title' => 'Nuclear',                             'description' => 'New build, SMR, fusion, decommissioning and defence — engineering, operations and maintenance.' ],
					[ 'icon' => 'briefcase', 'title' => 'EPC — Construction & Commissioning',  'description' => "FEED, detailed design, construction, commissioning and project delivery." ],
				],
				'audiences' => [
					'company' => [
						'headline' => 'The right people. The right level. Zero compromise on calibre.',
						'body'     => "Whether you're staffing a single critical seat or building a team for a new asset, one conversation is all it takes to put our network to work. VPs of engineering and plant owners don't have time to run a search — we run it for you.",
						'bullets'  => "Engaged search — our flagship model, 100% success rate\nRetained executive search for boardroom-level appointments\nTeam builds for new plants, projects and regions\nDirect hire and contract across the full talent hierarchy",
						'cta'      => 'Staff your asset',
					],
					'candidate' => [
						'headline' => "Power your career.\nOn your terms.",
						'body'     => "Shift supervisor moving up. EPC director chasing COD. VP of engineering eyeing the next chapter. We only call when there's a role worth your time — and we sell your experience before you sit in an interview.",
						'bullets'  => "Confidential, partnership-led conversations\nRoles across critical power, renewables, EPC and nuclear\nRelocation, comp and market intelligence guidance\nWe sell your experience before the first interview",
						'cta'      => 'Power your career',
					],
				],
				'about_hero' => [ 'pre' => 'One sector.', 'accent' => "\nUS energy, end to end.", 'post' => 'Lived, not learned.' ],
				'positioning' => 'Edison Lux delivers talent solutions for the US energy sector — from control room operators and shift supervisors to the directors and C-suite leaders responsible for billion-dollar assets. One market. Done properly.',
				'what_we_do' => [
					'headline'   => 'Embedded in the industries that keep the lights on.',
					'paragraphs' => "Critical power, combined-cycle and gas generation, renewables and energy transition, biomass and EFW, nuclear, and EPC construction — these are the corners of US energy we know inside out. Every consultant lives in the sector, not adjacent to it.\n\nFor plant owners, developers, EPCs and VPs of engineering, we operate as an extension of the leadership team — discreet, accountable and never transactional. When a COD is at risk or a plant is short-staffed, one conversation is all it takes.",
				],
				'proof' => [
					'100% success rate on engaged search assignments',
					'World-class NPS across operators, developers and EPCs',
					'Feedback captured from every candidate and client interaction',
					'US-only focus — no client brief sits outside our knowledge base',
				],
				'team_focus' => 'US power & energy search.',
				'insights'   => [
					[ 'title' => "The US Energy Skills Gap: who's hiring, who's leaving, and what it costs",
					  'excerpt' => "America is building more generating capacity than at any point in a generation. The talent pipeline isn't keeping up — here's what plant owners and EPCs need to know in 2026.",
					  'image' => 'insight_power' ],
					[ 'title' => 'CCGT Shift Supervisor Salary Guide — US, 2026',
					  'excerpt' => 'Base, shift premium, total comp and relocation packages benchmarked across PJM, ERCOT and CAISO.',
					  'image' => 'insight_power' ],
					[ 'title' => 'Reducing time-to-COD on large-scale EPC projects',
					  'excerpt' => "Why the bottleneck is rarely concrete, steel or turbines — and almost always the people you can't find fast enough.",
					  'image' => 'insight_epc' ],
				],
			],
			'vertek' => [
				'name'        => 'Vertek',
				'focus'       => 'Technical Sales, Service & Engineering',
				'focus_lower' => 'technical sales, service & engineering',
				'hero'        => [
					'line1'  => 'Engineering',
					'line2'  => "what's next",
					'sub'    => 'Vertek recruits technical sales, service and engineering professionals for the manufacturers and distributors that keep industry moving — across the UK and US. Every consultant owns one product area.',
					'image'  => 'vertek_hero',
					'alt'    => 'Cable-stayed bridge at night with crimson motion light trails',
					'scale'  => 1.18,
					'offset' => 0,
				],
				'features' => [
					[ 'icon' => 'crosshair',   'title' => 'Product-Owned Desks', 'body' => 'Every consultant owns one product area. Fluid power, HVAC, rotating equipment, automation — no generalists.' ],
					[ 'icon' => 'bar-chart-3', 'title' => 'Verto Engage',        'body' => 'Our committed model, 100% success rate. Structured process, guaranteed shortlist, get it right first time.' ],
					[ 'icon' => 'shield',      'title' => '94% Second Hire',     'body' => "Nearly all our clients come back. We're an extension of the commercial team, not a vendor." ],
					[ 'icon' => 'leaf',        'title' => '14,000+ On CRM',      'body' => "A specialist database of technical sales, service and engineering talent LinkedIn can't surface." ],
				],
				'about'        => [
					'headline' => 'One product area, per consultant. Every time.',
					'mission'  => 'Deliver the right hire, first time — combining a 14,000-strong specialist database with a process refined over years so speed never comes at the cost of quality.',
					'vision'   => 'To be the firm every VP of Sales, MD and founder in technical sales and engineering reaches out to first — on both sides of the Atlantic.',
					'purpose'  => "Product knowledge can't be faked. Neither can ours. We exist so that manufacturers and distributors never have to explain their own product to their recruiter — and so that engineers get sold on their merits.",
				],
				// No images.landingAbout override in the prototype — the hero
				// image doubles as the landing about visual (getBrandImage fallback).
				'about_image'     => 'vertek_hero',
				'about_image_alt' => 'Cable-stayed bridge at night with crimson motion light trails',
				'stats' => [
					[ 'value' => '14,000+', 'label' => 'Technical sales candidates on CRM' ],
					[ 'value' => '100%',    'label' => 'Success rate on Verto Engage' ],
					[ 'value' => '94%',     'label' => 'Of clients hire with us again' ],
				],
				'specialisms' => [
					[ 'icon' => 'gauge',       'title' => 'Fluid Power & Flow Control',              'description' => 'Hydraulics, pneumatics, compressed air, pumps, valves, actuators, instrumentation, filtration and seals.' ],
					[ 'icon' => 'thermometer', 'title' => 'Rotating Equipment & Turbomachinery',     'description' => 'Steam turbines, gas compression, electric motors, gearboxes and power transmission.' ],
					[ 'icon' => 'cog',         'title' => 'HVAC',                                    'description' => 'Air handlers, ventilation, refrigeration, heat pumps, boilers, plumbing and aftermarket — UK and US.' ],
					[ 'icon' => 'factory',     'title' => 'CNC & Precision Engineering (US)',        'description' => 'Cutting tools, workholding, toolholding, metrology, CMM and metalworking.' ],
					[ 'icon' => 'cpu',         'title' => 'Industrial Automation (US)',              'description' => 'Sensors, PLCs, HMI, connectors, automated machinery and conveyors.' ],
					[ 'icon' => 'line-chart',  'title' => 'Advanced Manufacturing (US)',             'description' => 'Defence, aerospace, space, semiconductor and robotics — ITAR and clearance handled.' ],
				],
				'audiences' => [
					'company' => [
						'headline' => 'Straightforward. No overpromising. Just the right hire.',
						'body'     => "Tell us the product, the patch and the profile. We'll tell you honestly whether we can deliver — and then we will. Every consultant specialises by product because our clients and candidates don't generalise either.",
						'bullets'  => "Verto Engage — our committed model, 100% success rate\nDirect hire across 14,000+ specialist candidates\nTeam builds — land one hire, then scale the function\nFrequent updates, structured briefings, no surprises",
						'cta'      => 'Build your team',
					],
					'candidate' => [
						'headline' => 'Options, not applications.',
						'body'     => 'Put a role on a job board and it gets hundreds of resumes. Work with us and it works the other way round — we put you and your experience front and centre, and we sell the opportunity before you sit in an interview.',
						'bullets'  => "UK, EU and US roles across the product landscape\nTotal comp, equity, progression and work-life on the table\nTime-served engineers and product specialists — spoken to as equals\nHonest feedback. No oversell. No fluff.",
						'cta'      => 'See live roles',
					],
				],
				'about_hero' => [ 'pre' => 'Product knowledge,', 'accent' => 'one desk at a time.', 'post' => 'Never generalist.' ],
				'positioning' => "Vertek recruits technical sales, service and engineering professionals for the manufacturers and distributors that keep industry moving — across the UK and US. Every consultant owns one product area. That's why it works.",
				'what_we_do' => [
					'headline'   => 'Embedded in the industries that build the world.',
					'paragraphs' => "Fluid power, HVAC, rotating equipment, industrial automation and US advanced manufacturing — these are the industries we know inside out. Every consultant specialises in a product area and stays close enough to add genuine insight to every conversation.\n\nFor VPs of Sales, Managing Directors and Founders, we operate as an extension of the leadership team — discreet, accountable and never transactional.",
				],
				'proof' => [
					'100% success rate on Verto Engage',
					'94% of clients return for a second hire',
					'14,000+ specialist sales and engineering candidates on the CRM',
					'Feedback captured from every candidate and client interaction — then acted on',
				],
				'team_focus' => 'Technical sales & engineering search.',
				'insights'   => [
					[ 'title' => 'Fluid Power Sales Engineer Salary Guide — UK & US, 2026',
					  'excerpt' => 'Base, OTE, equity and benefit benchmarks across hydraulics, pneumatics and compressed air distributors.',
					  'image' => 'insight_fluidpower' ],
					[ 'title' => 'Advanced manufacturing talent trends: defence, semis and robotics',
					  'excerpt' => 'Where the next wave of US engineering and commercial talent will come from — and what founders are paying to secure it.',
					  'image' => 'insight_manufacturing' ],
					[ 'title' => 'The HVAC aftermarket hiring playbook',
					  'excerpt' => 'Service managers, aftermarket sales leads and field engineers — building the commercial muscle behind the install base.',
					  'image' => 'insight_hvac' ],
				],
				/* ── Vertek-only data (the prototype renders these sections
				      conditionally; modulr / edison-lux carry none of them) ── */
				'pillars' => [
					[ 'title' => 'Product-owned desks',
					  'body'  => "Every Vertek consultant owns a product area — fluid power, HVAC, rotating equipment, automation, advanced manufacturing. We don't generalise across engineering because our clients and candidates don't. Anyone can post a job spec; very few can tell the difference between a fluid power sales engineer with real product experience and one who just learned the words." ],
					[ 'title' => 'Partnership, not transaction',
					  'body'  => "94% of our clients come back. The other 6% haven't had a second role yet. We earn that by understanding the business properly, representing it well in the market and operating as an extension of the commercial team — not a vendor." ],
					[ 'title' => 'Process over chance',
					  'body'  => "Structured briefings, frequent updates at every stage and a methodology built over years to get it right first time. Recruitment isn't luck — and our 100% success rate on Verto Engage proves it." ],
				],
				'values' => [
					[ 'title' => 'Straightforward. No overpromising.',
					  'body'  => "We say what we mean and mean what we say. Candidates are sold on their merits, feedback is honest, and we never promise what we can't deliver." ],
					[ 'title' => 'Process over chance.',
					  'body'  => "Great recruitment isn't luck. Our methodology has been built over years to get it right first time — frequent updates, thorough briefings, structure that removes failure at every stage." ],
					[ 'title' => 'An extension of your team.',
					  'body'  => '94% of our clients work with us again. We understand the business properly, represent it well and build relationships that outlast a single hire.' ],
					[ 'title' => 'High-conviction introductions.',
					  'body'  => "We sell the opportunity as hard as we'd want someone to sell ours. The right candidates come energised, not just informed." ],
					[ 'title' => 'Product knowledge, non-negotiable.',
					  'body'  => "Every consultant specialises in a product area. We don't generalise across engineering because our clients and candidates don't — and neither should we." ],
				],
				'journey' => [
					[ 'year' => '2011', 'title' => 'Founded in technical sales — the roots of the Verto Group' ],
					[ 'year' => '2020', 'title' => 'Vertek brand established for sales, service and engineering search' ],
					[ 'year' => '2022', 'title' => 'US expansion into fluid power, HVAC and rotating equipment' ],
					[ 'year' => '2024', 'title' => 'Advanced manufacturing practice launched — defence, aerospace, semiconductor, robotics' ],
					[ 'year' => '2026', 'title' => '14,000+ specialist candidates on the CRM and counting' ],
				],
				'testimonials' => [
					[ 'quote' => "Vertek's understanding of our product, our distribution model and the talent market was the difference. They didn't send resumes — they sent the right people, fully briefed, every time.",
					  'attribution' => 'VP of Sales, Global Fluid Power Manufacturer' ],
					[ 'quote' => "We've used a lot of recruiters. Vertek is the only one that consistently understood the difference between a sales engineer who can talk hydraulics and one who's actually time-served. That's why we keep coming back.",
					  'attribution' => 'Managing Director, UK Pneumatics Distributor' ],
					[ 'quote' => 'They built our entire US commercial team from the ground up — sales engineers, service leaders, a regional director — in under twelve months. No drama, no surprises, no oversell.',
					  'attribution' => 'Founder, US Advanced Manufacturing OEM' ],
					[ 'quote' => "I wasn't actively looking. Vertek took the time to understand what I actually wanted next, brought one opportunity, and represented me brilliantly. I started six weeks later.",
					  'attribution' => 'HVAC Service Manager (now Regional Director)' ],
				],
				'process' => [
					[ 'title' => 'Discover', 'body' => 'We sit down with the business, the product line and the territory — not just the job spec. Success at 90 days, 6 months and 12 months is mapped before we name a single candidate.' ],
					[ 'title' => 'Map',      'body' => 'We map the entire competitor, manufacturer and distributor landscape on patch. Time-served engineers, sales specialists, service leaders — visible and invisible.' ],
					[ 'title' => 'Engage',   'body' => 'Every approach is briefed properly — your product, your culture, the opportunity. No mass outreach. No resumes into the void.' ],
					[ 'title' => 'Deliver',  'body' => "Structured shortlist, candidate context resumes can't capture, offer management and post-placement check-ins. Frequent updates throughout. No surprises." ],
				],
				'case_study' => [
					'client'    => 'Global Fluid Power OEM',
					'sector'    => 'Hydraulics & motion control',
					'challenge' => 'A European fluid power manufacturer needed to build a US commercial team from scratch — Regional Sales Director, three product-specialist sales engineers and a service manager — in a market where time-served hydraulics talent is notoriously hard to find. Two previous contingent partners had stalled out.',
					'solution'  => "Vertek mapped every relevant hydraulics, motion control and pneumatics OEM and distributor across the target US regions. We worked exclusively on engaged terms, ran weekly market read-outs and represented the client's story end-to-end — including total comp, equity and relocation context the client had previously underplayed.",
					'result'    => "All five hires made within 10 months. 100% retention at 18 months. Vertek has since been engaged on a further 14 mandates including a VP of Sales appointment and the company's first US service leadership team.",
				],
				'candidate_process' => [
					[ 'title' => 'Confidential conversation', 'body' => 'We start by understanding what you actually want next — product area, market, total comp, relocation, work-life. No oversell. We only bring you roles that genuinely fit.' ],
					[ 'title' => 'Briefed representation',    'body' => 'We sell your experience before the first interview. Hiring managers see your context, your patch and your product knowledge — not just a resume.' ],
					[ 'title' => 'Interview preparation',     'body' => "Full briefing on the company, the panel, the product line and the likely lines of questioning. We've usually placed there before." ],
					[ 'title' => 'Offer & beyond',            'body' => "Honest comp guidance, equity context for US advanced manufacturing, counter-offer support and check-ins long after you've started." ],
				],
				'sectors_served' => [
					'Fluid power & flow control',
					'HVAC & refrigeration',
					'Rotating equipment & turbomachinery',
					'CNC & precision engineering (US)',
					'Industrial automation (US)',
					'Advanced manufacturing (US)',
					'MRO & aftermarket',
					'Commercial leadership (VP / GM / Director)',
				],
			],
		];
		return $all[ $brand ] ?? [];
	}

	/** Seed the brand's insight posts + category (once). */
	private static function seed_brand_posts( string $brand, array $media ): void {
		if ( get_option( 'verto_installer_posts' ) ) return;
		$c = self::brand_content( $brand );
		if ( ! $c ) return;
		$cat    = wp_insert_term( $c['name'], 'category', [ 'slug' => $brand ] );
		$cat_id = is_wp_error( $cat ) ? ( get_term_by( 'slug', $brand, 'category' )->term_id ?? 0 ) : $cat['term_id'];
		$ids    = [];
		foreach ( $c['insights'] as $post ) {
			$id = wp_insert_post( [
				'post_title'    => $post['title'],
				'post_excerpt'  => $post['excerpt'],
				'post_content'  => $post['excerpt'] . "\n\n⚠️ Placeholder insight from the prototype — replace with the real article.",
				'post_status'   => 'publish',
				'post_type'     => 'post',
				'post_category' => $cat_id ? [ $cat_id ] : [],
			] );
			if ( $id && ! is_wp_error( $id ) && ! empty( $media[ $post['image'] ]['id'] ) ) {
				set_post_thumbnail( $id, $media[ $post['image'] ]['id'] );
			}
			$ids[] = $id;
		}
		update_option( 'verto_installer_posts', $ids );
	}

	/** Build the standalone brand site: Home, About, Clients, Candidates, Insights. */
	private static function create_brand_site_pages( string $brand, array $media ): void {
		$c = self::brand_content( $brand );
		if ( ! $c ) return;

		$name       = $c['name'];
		$logo_key   = [ 'modulr' => 'logo_modulr_png', 'vertek' => 'logo_vertek', 'edison-lux' => 'logo_edison' ][ $brand ] ?? '';
		$stats      = array_map( fn( $st ) => [ '_id' => self::eid() ] + $st, $c['stats'] );
		$team_strip = self::widget( 'verto-team-grid', [
			'mode'       => 'strip',
			'brand'      => $brand,
			'eyebrow'    => 'The team',
			'heading'    => "Meet the $name desk.",
			'body'       => "Operators, engineers and market specialists. The people you'll actually talk to when you engage $name.",
			'focus_text' => $c['team_focus'],
		] );
		$contact = function ( string $eyebrow, string $heading, string $bullets ) {
			$lis = '';
			foreach ( array_filter( array_map( 'trim', explode( "\n", $bullets ) ) ) as $bp ) {
				$lis .= '<li><span class="vbs-dot" style="background:var(--brand);"></span><span>' . esc_html( $bp ) . '</span></li>';
			}
			return self::section2( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => $eyebrow,
					'lines'   => [ [ '_id' => self::eid(), 'line' => $heading ] ],
				] ),
				self::widget( 'text-editor', [ 'editor' => '<ul class="vbs-bullets">' . $lis . '</ul>' ] ),
			], [
				self::widget( 'text-editor', [
					'editor'       => '<div class="card-surface vbs-form-card"><div class="verto-form">[CF7-SHORTCODE-HERE — create the form in Contact → Contact Forms, then paste its shortcode into this text widget]</div></div>',
				] ),
			], 'verto-bs vbs-contact', 45, [ '_element_id' => 'contact' ] );
		};

		/* ── HOME ── */
		$home = [
			self::section( [ self::widget( 'verto-brand-hero', [
				'line1'           => $c['hero']['line1'],
				'line2'           => $c['hero']['line2'],
				'sub'             => $c['hero']['sub'],
				'image'           => self::media_setting( $media, $c['hero']['image'] ),
				'image_alt'       => $c['hero']['alt'],
				'parallax_scale'  => $c['hero']['scale'],
				'parallax_offset' => $c['hero']['offset'],
				'cta1_text'       => 'Our Solutions',
				'cta1_link'       => [ 'url' => '/clients' ],
				'cta2_text'       => "Discover $name",
				'cta2_link'       => [ 'url' => '/about' ],
			] ) ], 'verto-bs' ),
			self::section( [ self::widget( 'verto-feature-row', [
				'items' => array_map( fn( $f ) => [ '_id' => self::eid() ] + $f, $c['features'] ),
			] ) ], 'verto-bs' ),
			self::section( [ self::widget( 'verto-about-split', [
				'variant'   => 'landing',
				'eyebrow'   => "About $name",
				'headline'  => $c['about']['headline'],
				'body'      => $c['about']['mission'],
				'cta_text'  => 'Learn more about us',
				'cta_link'  => [ 'url' => '/about' ],
				'image'     => self::media_setting( $media, $c['about_image'] ),
				'image_alt' => $c['about_image_alt'],
				'stats'     => $stats,
			] ) ], 'verto-bs' ),
			self::section( [ self::widget( 'verto-specialisms', [
				'items' => array_map( fn( $sp ) => [ '_id' => self::eid() ] + $sp, $c['specialisms'] ),
			] ) ], 'verto-bs' ),
			self::section( [ self::widget( 'verto-logo-marquee', [] ) ], 'verto-bs' ),
			self::section( [ self::widget( 'verto-audience-cards', [ 'items' => [
				[ '_id' => self::eid(), 'style' => 'ink', 'kicker' => 'For companies',
				  'headline' => $c['audiences']['company']['headline'], 'body' => $c['audiences']['company']['body'],
				  'bullets' => $c['audiences']['company']['bullets'], 'cta_text' => $c['audiences']['company']['cta'],
				  'cta_link' => [ 'url' => '/clients' ] ],
				[ '_id' => self::eid(), 'style' => 'surface', 'kicker' => 'For candidates',
				  'headline' => $c['audiences']['candidate']['headline'], 'body' => $c['audiences']['candidate']['body'],
				  'bullets' => $c['audiences']['candidate']['bullets'], 'cta_text' => $c['audiences']['candidate']['cta'],
				  'cta_link' => [ 'url' => '/candidates' ] ],
			] ] ) ], 'verto-bs' ),
			self::section( [ $team_strip ], 'verto-bs' ),
			self::section( [ self::widget( 'verto-posts-grid', [
				'count'            => 3,
				'kicker'           => $name,
				'category'         => $brand,
				'header_eyebrow'   => 'Insights',
				'header_heading'   => 'From inside the market.',
				'header_link_text' => 'View all',
				'header_link'      => [ 'url' => '/insights' ],
			] ) ], 'verto-bs vbs-insights-sec' ),
		];
		$home_id = self::upsert_page( 'home', 'Home', $home );

		/* ── ABOUT ── */
		$about_hero_body = $c['about']['headline'] . " — and it's how we've built $name into the firm clients and candidates in " . $c['focus_lower'] . ' reach out to first.';
		$about = [
			self::section( [ self::widget( 'verto-quote-band', [
				'image'          => self::media_setting( $media, $c['hero']['image'] ),
				'image_alt'      => $c['hero']['alt'],
				'overlay'        => 'hero-left',
				'pad'            => 'hero',
				'parallax_speed' => 0.3,
				'eyebrow'        => "About $name",
				'eyebrow_style'  => 'brand',
				'heading_pre'    => $c['about_hero']['pre'],
				'heading_accent' => $c['about_hero']['accent'],
				'heading_post'   => $c['about_hero']['post'],
				'body'           => $about_hero_body,
				'stat_value'     => $c['stats'][0]['value'],
				'stat_label'     => $c['stats'][0]['label'],
			] ) ], 'verto-bs' ),
			self::section( [ self::widget( 'verto-about-split', [
				'variant'         => 'story',
				'eyebrow'         => "About $name",
				'headline'        => 'The story behind',
				'headline_italic' => "$name.",
				'body'            => $c['positioning'] . "\n\nWe started in technical sales in 2011 — the roots of the Verto Group. $name is the brand built specifically for the part of the market we know best: the engineers, operators and commercial leaders our sector runs on.",
				'logo'            => self::media_setting( $media, $logo_key ),
				'stats'           => $stats,
				'cta_text'        => '',
			] ) ], 'verto-bs' ),
		];
		// Pillars — staggered 3-up cards (prototype: only brands with `pillars`;
		// currently Vertek). process-rail cards3 without kicker/bullets.
		if ( ! empty( $c['pillars'] ) ) {
			$about[] = self::section( [ self::widget( 'verto-process-rail', [
				'layout'    => 'cards3',
				'bg'        => 'default',
				'eyebrow'   => 'What separates us',
				'heading'   => "Three principles.\nApplied to every search.",
				'side_text' => '',
				'items'     => array_map( fn( $p ) => [ '_id' => self::eid(), 'kicker' => '', 'bullets' => '' ] + $p, $c['pillars'] ),
			] ) ], 'verto-bs' );
		}
		// Values — sticky-intro accordion (Vertek-only in the prototype data).
		if ( ! empty( $c['values'] ) ) {
			$about[] = self::section( [ self::widget( 'verto-values-accordion', [
				'items' => array_map( fn( $v ) => [ '_id' => self::eid() ] + $v, $c['values'] ),
			] ) ], 'verto-bs' );
		}
		$about_tail = [
			self::section( [ self::widget( 'verto-about-split', [
				'variant'   => 'panel',
				'reverse'   => 'yes',
				'eyebrow'   => 'What we do today',
				'headline'  => $c['what_we_do']['headline'],
				'body'      => $c['what_we_do']['paragraphs'],
				'image'     => self::media_setting( $media, 'about_image' ),
				'image_alt' => 'A specialist team at work',
				'panel_bg'  => '#ffffff',
				'stats'     => [],
				'cta_text'  => '',
			] ) ], 'verto-bs' ),
			self::section( [ self::widget( 'verto-quote-band', [
				'image'          => self::media_setting( $media, $c['hero']['image'] ),
				'overlay'        => 'mission',
				'pad'            => 'band',
				'parallax_speed' => 0.35,
				'eyebrow'        => 'The compass',
				'eyebrow_style'  => 'dim',
				'heading_pre'    => 'Mission. Vision. Purpose.',
				'heading_accent' => '',
				'heading_post'   => '',
				'heading_size'   => 'display-3',
				'body'           => '',
				'stat_value'     => '',
				'stat_label'     => '',
				'columns'        => [
					[ '_id' => self::eid(), 'label' => 'Mission', 'body' => $c['about']['mission'] ],
					[ '_id' => self::eid(), 'label' => 'Vision',  'body' => $c['about']['vision'] ],
					[ '_id' => self::eid(), 'label' => 'Purpose', 'body' => $c['about']['purpose'] ],
				],
			] ) ], 'verto-bs' ),
		];
		$about = array_merge( $about, $about_tail );
		// Journey — horizontal rail with brand-colour years (Vertek-only data).
		if ( ! empty( $c['journey'] ) ) {
			$about[] = self::section( [ self::widget( 'verto-process-rail', [
				'layout'     => 'line',
				'line_style' => 'journey',
				'bg'         => 'default',
				'eyebrow'    => 'Our story so far',
				'heading'    => 'Built decade by decade.',
				'side_text'  => '',
				'items'      => array_map( fn( $m ) => [
					'_id'     => self::eid(),
					'title'   => $m['year'],
					'kicker'  => '',
					'body'    => $m['title'],
					'bullets' => '',
				], $c['journey'] ),
			] ) ], 'verto-bs' );
		}
		$about[] = self::section( [ self::widget( 'verto-proof-list', [
			'items' => array_map( fn( $p ) => [ '_id' => self::eid(), 'text' => $p ], $c['proof'] ),
		] ) ], 'verto-bs' );
		$about[] = self::section( [ $team_strip ], 'verto-bs' );
		$about[] = self::section( [ self::widget( 'verto-cta-band', [
			'heading'   => 'Ready to talk?',
			'cta1_text' => "Hire with $name",
			'cta1_link' => [ 'url' => '/clients' ],
			'cta2_text' => 'Explore roles',
			'cta2_link' => [ 'url' => '/candidates' ],
		] ) ], 'verto-bs' );
		self::upsert_page( 'about', 'About', $about );

		/* ── CLIENTS (prototype for-companies) ── */
		$co      = $c['audiences']['company'];
		$clients = [
			self::section( [ self::widget( 'verto-quote-band', [
				'image'          => self::media_setting( $media, $c['hero']['image'] ),
				'image_alt'      => $c['hero']['alt'],
				'overlay'        => 'hero-left',
				'pad'            => 'hero',
				'parallax_speed' => 0.3,
				'eyebrow'        => 'For companies',
				'eyebrow_style'  => 'dim',
				'heading_pre'    => $co['headline'],
				'heading_accent' => '',
				'heading_post'   => '',
				'body'           => $co['body'],
				'cta1_text'      => 'Submit a vacancy',
				'cta1_link'      => [ 'url' => '#contact' ],
				'cta2_text'      => "How $name works",
				'cta2_link'      => [ 'url' => '/about' ],
				'stat_value'     => '',
				'stat_label'     => '',
			] ) ], 'verto-bs' ),
			self::section( [ self::widget( 'verto-about-split', [
				'variant'   => 'panel',
				'eyebrow'   => "About $name",
				'headline'  => 'A partnership, not a placement.',
				'body'      => "We exist to find you the best technical commercial talent on the market — and we've earned that right by building trust with our partners over more than a decade.\n\nEvery consultant specialises in a product area. We recruit across the manufacturer and distributor landscape and represent your business as if it were our own.",
				'image'     => self::media_setting( $media, $c['hero']['image'] ),
				'image_alt' => $c['hero']['alt'],
				'grayscale' => 'yes',
				'panel_bg'  => '#ffffff',
				'stats'     => [
					[ '_id' => self::eid(), 'value' => '94%', 'label' => 'Clients hire with us a second time' ],
					[ '_id' => self::eid(), 'value' => $c['stats'][0]['value'], 'label' => $c['stats'][0]['label'] ],
					[ '_id' => self::eid(), 'value' => '1:1', 'label' => 'Consultant handles brief to offer' ],
				],
				'cta_text'  => "How $name works",
				'cta_link'  => [ 'url' => '/about' ],
			] ) ], 'verto-bs' ),
			self::section( [ self::widget( 'verto-process-rail', [
				'layout'    => 'cards3',
				'bg'        => 'muted',
				'eyebrow'   => 'Hiring solutions',
				'heading'   => "Sized to the project.\nBuilt for the market.",
				'side_text' => "We construct a tailored hiring plan to meet your requirements — whether you're filling one role or building an entire commercial team.",
				'items'     => [
					[ '_id' => self::eid(), 'title' => 'Engaged Search', 'kicker' => 'Our flagship model',
					  'body' => 'A committed partnership with a structured process — market mapping, verified shortlists, offer management. Built to remove the chance of failure and get it right first time. 100% success rate on the Engage model.',
					  'bullets' => "Exclusive partnership\nStructured milestones\nFrequent read-outs" ],
					[ '_id' => self::eid(), 'title' => 'Retained Executive Search', 'kicker' => 'Director and C-suite mandates',
					  'body' => "Discreet, confidential search for VP, MD, director and C-suite appointments. Off-market approaches, NDA-protected mandates and full lifecycle stakeholder management for the roles that can't be advertised.",
					  'bullets' => "Retained, fully confidential\nNDA-protected searches\nStakeholder & offer management" ],
					[ '_id' => self::eid(), 'title' => 'Team Builds', 'kicker' => 'Partnerships, not placements',
					  'body' => 'When a new plant, project or region needs staffing from the ground up — we build the whole team. Proactively, against your timeline, reducing time-to-hire and the cost of the empty seat.',
					  'bullets' => "Land-and-expand\nContract and permanent\nAgainst your project timeline" ],
				],
			] ) ], 'verto-bs' ),
		];
		// Process — horizontal rail with connector line (Vertek-only data).
		if ( ! empty( $c['process'] ) ) {
			$clients[] = self::section( [ self::widget( 'verto-process-rail', [
				'layout'     => 'line',
				'line_style' => 'process',
				'bg'         => 'default',
				'eyebrow'    => 'How we work',
				'heading'    => 'A process built to remove chance.',
				'side_text'  => '',
				'items'      => array_map( fn( $st ) => [ '_id' => self::eid(), 'kicker' => '', 'bullets' => '' ] + $st, $c['process'] ),
			] ) ], 'verto-bs' );
		}
		// Case study — dark parallax band, Client/Sector meta + C/S/R grid.
		if ( ! empty( $c['case_study'] ) ) {
			$cs = $c['case_study'];
			$clients[] = self::section( [ self::widget( 'verto-quote-band', [
				'image'          => self::media_setting( $media, $c['hero']['image'] ),
				'image_alt'      => $c['hero']['alt'],
				'overlay'        => 'case',
				'pad'            => 'band',
				'parallax_speed' => 0.32,
				'eyebrow'        => 'Case study',
				'eyebrow_style'  => 'dim',
				'heading_pre'    => 'We make success happen for those we partner with.',
				'heading_accent' => '',
				'heading_post'   => '',
				'body'           => '',
				'stat_value'     => '',
				'stat_label'     => '',
				'case_client'    => $cs['client'],
				'case_sector'    => $cs['sector'],
				'columns'        => [
					[ '_id' => self::eid(), 'label' => 'Challenge', 'body' => $cs['challenge'] ],
					[ '_id' => self::eid(), 'label' => 'Solution',  'body' => $cs['solution'] ],
					[ '_id' => self::eid(), 'label' => 'Result',    'body' => $cs['result'] ],
				],
			] ) ], 'verto-bs' );
		}
		// Testimonials — light section, brand-rule quotes (Vertek-only data).
		if ( ! empty( $c['testimonials'] ) ) {
			$clients[] = self::section( [ self::widget( 'verto-quote-band', [
				'quotes_style'   => 'light',
				'pad'            => 'band',
				'eyebrow'        => 'In their words',
				'eyebrow_style'  => 'brand',
				'heading_pre'    => 'Trusted by the businesses we serve.',
				'heading_accent' => '',
				'heading_post'   => '',
				'body'           => '',
				'stat_value'     => '',
				'stat_label'     => '',
				'quotes'         => array_map( fn( $q ) => [ '_id' => self::eid() ] + $q, $c['testimonials'] ),
			] ) ], 'verto-bs' );
		}
		$clients[] = self::section( [ self::widget( 'verto-feature-row', [
			'variant' => 'stats',
			'items'   => array_map( fn( $st ) => [ '_id' => self::eid(), 'icon' => '', 'title' => $st['value'], 'body' => $st['label'] ], $c['stats'] ),
		] ) ], 'verto-bs' );
		$clients[] = self::section( [ $team_strip ], 'verto-bs' );
		$clients[] = $contact( 'Talk to us', 'Tell us what you need to build.', $co['bullets'] );
		self::upsert_page( 'clients', 'Clients', $clients );

		/* ── CANDIDATES (prototype for-candidates) ── */
		$ca         = $c['audiences']['candidate'];
		$candidates = [
			self::section( [ self::widget( 'verto-quote-band', [
				'image'          => self::media_setting( $media, $c['hero']['image'] ),
				'image_alt'      => $c['hero']['alt'],
				'overlay'        => 'hero-right',
				'pad'            => 'hero',
				'align'          => 'right',
				'parallax_speed' => 0.3,
				'eyebrow'        => 'For candidates',
				'eyebrow_style'  => 'brand',
				'heading_pre'    => $ca['headline'],
				'heading_accent' => '',
				'heading_post'   => '',
				'body'           => $ca['body'],
				'cta1_text'      => 'Start a conversation',
				'cta1_link'      => [ 'url' => '#contact' ],
				'cta2_text'      => "About $name",
				'cta2_link'      => [ 'url' => '/about' ],
				'stat_value'     => '',
				'stat_label'     => '',
			] ) ], 'verto-bs' ),
			self::section( [ self::widget( 'verto-about-split', [
				'variant'   => 'panel',
				'reverse'   => 'yes',
				'eyebrow'   => 'Represented properly',
				'headline'  => 'Sold on your merits.',
				'body'      => "A job posted on LinkedIn gets hundreds of CVs. Working with $name means you and your experience are put front and centre — sold to the hiring manager before your first interview.\n\nWe only call when there's a role genuinely worth your time. Honest feedback, no fluff, no promises we can't deliver.",
				'image'     => self::media_setting( $media, $c['hero']['image'] ),
				'image_alt' => $c['hero']['alt'],
				'grayscale' => 'yes',
				'panel_bg'  => '#ffffff',
				'stats'     => [
					[ '_id' => self::eid(), 'value' => '72h',  'label' => 'First feedback after your intro call' ],
					[ '_id' => self::eid(), 'value' => '100%', 'label' => 'Confidential — always' ],
					[ '_id' => self::eid(), 'value' => '1:1',  'label' => 'Same consultant, brief to offer' ],
				],
				'cta_text'  => "About $name",
				'cta_link'  => [ 'url' => '/about' ],
			] ) ], 'verto-bs' ),
		];
		// Sectors served — dense chip grid (Vertek-only data).
		if ( ! empty( $c['sectors_served'] ) ) {
			$candidates[] = self::section( [ self::widget( 'verto-chip-grid', [
				'eyebrow' => 'Where we recruit',
				'heading' => 'Specialists across the industries we serve.',
				'items'   => array_map( fn( $sec ) => [ '_id' => self::eid(), 'label' => $sec ], $c['sectors_served'] ),
			] ) ], 'verto-bs' );
		}
		// Candidate process — 4-up zigzag cards (Vertek-only data).
		if ( ! empty( $c['candidate_process'] ) ) {
			$candidates[] = self::section( [ self::widget( 'verto-process-rail', [
				'layout'    => 'zigzag',
				'bg'        => 'default',
				'eyebrow'   => 'What to expect',
				'heading'   => 'From first call to first day — and beyond.',
				'side_text' => '',
				'items'     => array_map( fn( $st ) => [ '_id' => self::eid(), 'kicker' => '', 'bullets' => '' ] + $st, $c['candidate_process'] ),
			] ) ], 'verto-bs' );
		}
		// Candidate testimonials — dark parallax band, quotes 3–4 (prototype slice(2, 4)).
		if ( ! empty( $c['testimonials'] ) && count( $c['testimonials'] ) > 2 ) {
			$candidates[] = self::section( [ self::widget( 'verto-quote-band', [
				'image'          => self::media_setting( $media, $c['hero']['image'] ),
				'image_alt'      => '',
				'overlay'        => 'testimonial',
				'pad'            => 'band',
				'parallax_speed' => 0.32,
				'eyebrow'        => "From the people we've placed",
				'eyebrow_style'  => 'dim',
				'heading_pre'    => 'Career moves that actually fit.',
				'heading_accent' => '',
				'heading_post'   => '',
				'body'           => '',
				'stat_value'     => '',
				'stat_label'     => '',
				'quotes'         => array_map( fn( $q ) => [ '_id' => self::eid() ] + $q, array_slice( $c['testimonials'], 2, 2 ) ),
			] ) ], 'verto-bs' );
		}
		$candidates[] = self::section( [ $team_strip ], 'verto-bs' );
		$candidates[] = $contact( 'Start a conversation', 'A confidential chat. No spam, no fluff.', $ca['bullets'] );
		self::upsert_page( 'candidates', 'Candidates', $candidates );

		/* ── INSIGHTS (posts archive page) ── */
		$pages = get_option( self::PAGES_OPTION, [] );
		if ( empty( $pages['insights'] ) || ! get_post( $pages['insights'] ) ) {
			$insights_id = wp_insert_post( [
				'post_title'  => 'Insights',
				'post_name'   => 'insights',
				'post_type'   => 'page',
				'post_status' => 'publish',
			] );
			$pages['insights'] = $insights_id;
			update_option( self::PAGES_OPTION, $pages );
		}
		update_option( 'page_for_posts', $pages['insights'] );

		/* Front page */
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_id );
	}

	/** Brand-site Primary menu: Home · About · Clients · Candidates · Insights. */
	private static function setup_brand_menu( string $brand ): void {
		$menu    = wp_get_nav_menu_object( 'Primary' );
		$menu_id = $menu ? $menu->term_id : wp_create_nav_menu( 'Primary' );

		foreach ( (array) wp_get_nav_menu_items( $menu_id, [ 'post_status' => 'any' ] ) as $item ) {
			if ( $item ) wp_delete_post( $item->ID, true );
		}

		$pages = get_option( self::PAGES_OPTION, [] );
		$order = [
			'home'       => 'Home',
			'about'      => 'About',
			'clients'    => 'Clients',
			'candidates' => 'Candidates',
			'insights'   => 'Insights',
		];
		$i = 1;
		foreach ( $order as $slug => $title ) {
			if ( empty( $pages[ $slug ] ) ) continue;
			wp_update_nav_menu_item( $menu_id, 0, [
				'menu-item-title'     => $title,
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $pages[ $slug ],
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $i++,
			] );
		}
		$locations = get_theme_mod( 'nav_menu_locations', [] );
		$locations['verto-primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
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
