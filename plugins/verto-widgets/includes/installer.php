<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Site Installer – one-click site build.
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
	const CF7_OPTION   = 'verto_cf7_form_id';   // wpcf7_contact_form post created by us
	const CF7_MISSING  = 'verto_cf7_missing';   // flag: CF7 inactive during the last build

	public static function boot() {
		add_action( 'admin_menu', function () {
			add_menu_page( 'Verto Setup', 'Verto Setup', 'manage_options', 'verto-setup', [ self::class, 'render_page' ], 'dashicons-hammer', 59 );
		} );
		add_action( 'admin_post_verto_build_site', [ self::class, 'handle_build' ] );
		add_action( 'admin_notices', function () {
			if ( current_user_can( 'manage_options' ) && get_option( self::CF7_MISSING ) ) {
				echo '<div class="notice notice-warning"><p><strong>Verto:</strong> Contact Form 7 was not active during the last site build, so the contact pages still show a placeholder instead of the enquiry form. Activate Contact Form 7, then run <em>Verto Setup → Rebuild</em>.</p></div>';
			}
		} );
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
				<div class="notice notice-warning"><p><strong>No bundled headshot for:</strong> <?php echo esc_html( implode( ', ', (array) $missing ) ); ?>.<br>They render with an initials placeholder until the client supplies photos – set each one via Team → Featured Image.</p></div>
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
			self::migrate_team_photos();
			// Round 9, item 1: brand installs seed THEIR OWN standing
			// vacancies (the group matrix filtered to this brand), so the
			// brand vacancies sections always have live rows + detail pages.
			self::seed_internal_jobs( $brand );
			self::seed_brand_posts( $brand, $media );
			self::create_brand_site_pages( $brand, $media );
			self::setup_brand_menu( $brand );
			wp_safe_redirect( admin_url( 'admin.php?page=verto-setup&built=1' ) );
			exit;
		}

		$media = self::import_media();
		self::seed_posts( $media );
		self::seed_team();
		self::migrate_team_photos();
		self::seed_internal_jobs();
		self::create_pages( $media );
		self::create_brand_pages();
		self::sweep_em_dashes();
		self::setup_menu();

		wp_safe_redirect( admin_url( 'admin.php?page=verto-setup&built=1' ) );
		exit;
	}

	/* ── Contact Form 7 ─────────────────────────────────────────────────
	 * The build creates ONE "General enquiry" form per site (tracked via
	 * CF7_OPTION so rebuilds reuse it) and embeds its shortcode in the
	 * contact sections. CF7 stores forms as `wpcf7_contact_form` posts whose
	 * properties live in underscore-prefixed meta – save() does
	 * `update_post_meta( $post_id, '_' . $prop, … )` for form / mail /
	 * mail_2 / messages / additional_settings (CF7 source,
	 * includes/contact-form.php). We use the official API when CF7 is
	 * active and write exactly that meta structure when it isn't. */

	/** Field template shared by both creation paths (GDPR consent included –
	 *  an [acceptance] box is required-by-default in CF7). */
	private static function cf7_form_template(): string {
		return implode( "\n\n", [
			'<div class="verto-form__row">' . "\n" . '<label> Name' . "\n" . '    [text* your-name autocomplete:name] </label>' . "\n\n" . '<label> Email' . "\n" . '    [email* your-email autocomplete:email] </label>' . "\n" . '</div>',
			'<label> Phone (optional)' . "\n" . '    [tel your-phone autocomplete:tel] </label>',
			'<label> Message' . "\n" . '    [textarea* your-message] </label>',
			'[acceptance gdpr-consent] I consent to my details being stored and used to respond to this enquiry. [/acceptance]',
			'[submit "Send message"]',
		] );
	}

	/** Mail property: recipient = admin_email, envelope sender on our own
	 *  domain (deliverability), reply-to the visitor. */
	private static function cf7_mail_property(): array {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( 'www.' === substr( $host, 0, 4 ) ) {
			$host = substr( $host, 4 );
		}
		return [
			'active'             => true,
			'subject'            => '[_site_title] – enquiry from [your-name]',
			'sender'             => '[_site_title] <wordpress@' . ( $host ? $host : 'localhost' ) . '>',
			'recipient'          => (string) get_option( 'admin_email' ),
			'body'               => "From: [your-name] <[your-email]>\nPhone: [your-phone]\n\n[your-message]\n\n--\nSent from the contact form on [_site_title] ([_site_url]).\nThe sender ticked the GDPR consent box.",
			'additional_headers' => 'Reply-To: [your-email]',
			'attachments'        => '',
			'use_html'           => false,
			'exclude_blank'      => false,
		];
	}

	/**
	 * Create (once) the site's General enquiry form; returns its shortcode,
	 * or '' when it could not be created AND embedded (CF7 inactive – the
	 * placeholder stays put and boot()'s admin notice explains why).
	 */
	private static function ensure_contact_form(): string {
		$id = (int) get_option( self::CF7_OPTION );
		if ( $id && get_post( $id ) ) {
			// Form exists – but only embed the shortcode when CF7 can render it.
			if ( class_exists( 'WPCF7_ContactForm' ) ) {
				delete_option( self::CF7_MISSING );
				return '[contact-form-7 id="' . $id . '" title="General enquiry"]';
			}
			update_option( self::CF7_MISSING, 1 );
			return '';
		}

		if ( class_exists( 'WPCF7_ContactForm' ) ) {
			// Official path: template → set_properties → save().
			$form = WPCF7_ContactForm::get_template( [ 'title' => 'General enquiry' ] );
			$form->set_properties( [
				'form' => self::cf7_form_template(),
				'mail' => array_merge( (array) $form->prop( 'mail' ), self::cf7_mail_property() ),
			] );
			$form->save();
			// save()'s return type has shifted between CF7 versions – the
			// form object's own id() is the stable way to read the post ID.
			$post_id = method_exists( $form, 'id' ) ? (int) $form->id() : 0;
			if ( $post_id ) {
				update_option( self::CF7_OPTION, (int) $post_id );
				delete_option( self::CF7_MISSING );
				return '[contact-form-7 id="' . (int) $post_id . '" title="General enquiry"]';
			}
			update_option( self::CF7_MISSING, 1 );
			return '';
		}

		// CF7 inactive: create the raw post + the meta CF7 reads, so the form
		// is ready the moment the plugin activates – but do NOT embed the
		// shortcode (it would render as literal text). Placeholder stays.
		$post_id = wp_insert_post( [
			'post_type'   => 'wpcf7_contact_form',
			'post_status' => 'publish',
			'post_title'  => 'General enquiry',
		] );
		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_form', self::cf7_form_template() );
			update_post_meta( $post_id, '_mail', self::cf7_mail_property() );
			update_post_meta( $post_id, '_mail_2', array_merge( self::cf7_mail_property(), [ 'active' => false ] ) );
			update_post_meta( $post_id, '_messages', [] );
			update_post_meta( $post_id, '_additional_settings', '' );
			update_option( self::CF7_OPTION, (int) $post_id );
		}
		update_option( self::CF7_MISSING, 1 );
		return '';
	}

	/** The contact-section body: real form when possible, placeholder if not. */
	private static function cf7_form_html(): string {
		static $cached = null;
		if ( null === $cached ) {
			$shortcode = self::ensure_contact_form();
			$cached    = '' !== $shortcode
				? '<div class="verto-form">' . $shortcode . '</div>'
				: '<div class="verto-form"><p><em>The enquiry form appears here once Contact Form 7 is activated – then run Verto Setup → Rebuild.</em></p></div>';
		}
		return $cached;
	}

	/** Import bundled files into the media library once; returns [name => [id, url]]. */
	private static function import_media(): array {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$map   = get_option( self::MEDIA_OPTION, [] );
		// v0.13.1: a higher-quality summit video shipped – force a one-time
		// re-import so existing installs pick up the new file on Rebuild.
		if ( ! get_option( 'verto_media_summit_v4' ) ) {
			unset( $map['summit_video'], $map['summit_poster'] );
			update_option( 'verto_media_summit_v4', 1 );
		}
		// v0.18.0: the real Austin skyline photo replaced the interim
		// skyline-us.jpg – force a one-time re-import so existing installs
		// pick up the new file on Rebuild.
		if ( ! get_option( 'verto_media_skyline_v2' ) ) {
			unset( $map['skyline_us'] );
			update_option( 'verto_media_skyline_v2', 1 );
		}
		// v0.21.0 (round 9, item 24): the client's favourite Austin skyline
		// (daytime river/kayaks shot) ships as austin-skyline.jpg and takes
		// over every skyline_us usage – force a one-time re-import.
		if ( ! get_option( 'verto_media_skyline_v3' ) ) {
			unset( $map['skyline_us'] );
			update_option( 'verto_media_skyline_v3', 1 );
		}
		// Spinnaker Tower photo replaces the (wrong) London skyline for Solent.
		if ( ! get_option( 'verto_media_skyline_uk_v4' ) ) {
			unset( $map['skyline_uk'] );
			update_option( 'verto_media_skyline_uk_v4', 1 );
		}
		// 4K masters + designed covers replace the Canva exports for the
		// Harvey/Frank promotion films (Oct 2026).
		if ( ! get_option( 'verto_media_promos_v2' ) ) {
			unset( $map['harvey_video'], $map['harvey_poster'], $map['frank_video'], $map['frank_poster'] );
			update_option( 'verto_media_promos_v2', 1 );
		}
		// Edison hero film v2 (Oct 2026): the first webm was concat-muxed and
		// wouldn't loop in browsers – re-import the clean single-pass encode.
		if ( ! get_option( 'verto_media_edison_hero_v2' ) ) {
			unset( $map['edison_hero_webm'] );
			update_option( 'verto_media_edison_hero_v2', 1 );
		}
		$dir   = dirname( __DIR__ ) . '/assets/import/';
		$files = [
			'summit_video'  => 'summit-video.mp4',
			'summit_poster' => 'summit-poster.jpg',
			'logo_edison'   => 'edison-lux-logo.png',
			'logo_edison_white' => 'edison-lux-logo-white.png',
			'logo_edison_colour' => 'edison-lux-logo-colour.png',
			'logo_modulr'   => 'modulr-logo.svg',
			'logo_modulr_png' => 'modulr-logo.png',
			'logo_vertek'   => 'vertek-logo-light.png',
			'ibiza8'        => 'ibiza8.jpg',
			'ibiza9'        => 'ibiza9.jpg',
			'award_bptw'    => 'BPTW_2026_SMALL_ORGANISATION_WHITE.png',
			'award_recruiter' => 'weve-been-shortlisted.png',
			'skyline_uk'    => 'skyline-uk.jpg',
			// Round 9, item 24: the client's favourite real Austin skyline
			// (daytime, river + kayaks) – supersedes skyline-us.jpg.
			'skyline_us'    => 'austin-skyline.jpg',
			'skyline_eu'    => 'skyline-eu.jpg',
			// Round 9, item 24: rooftop drinks team photo for the Austin
			// office gallery.
			'austin_rooftop' => 'austin-rooftop.jpg',
			// Brand-site media (ModulR)
			'modulr_hero'       => 'modulr-hero.png',
			'modulr_datacentre' => 'modulr-datacentre.webp',
			'about_image'       => 'about-image.jpg',
			'insight_datacentre'   => 'insight-datacentre.jpg',
			'insight_architecture' => 'insight-architecture.jpg',
			// Brand-site media (Edison Lux). Round 5, item 1: the hydro dam
			// (edison-hero.webp) is GONE – Edison does zero hydro. Pylon imagery
			// is the interim everywhere until the client purchases the CCGT /
			// gas-plant photography they picked (see CLIENT-NEEDS.md).
			'edison_pylon'  => 'edison-pylon.webp',
			/* ── Oct 2026 (Alex): looping hero FILM for the Edison home page –
			      the client's licensed pylon dawn footage, mirrored so the
			      towers sit right of the copy, 1600w, muted. Poster = first
			      frames of the same film. ── */
			'edison_hero_mp4'    => 'edison-hero.mp4',
			'edison_hero_webm'   => 'edison-hero.webm',
			'edison_hero_poster' => 'edison-hero-poster.jpg',
			/* ── Oct 2026 (Alex/Simon): Vertek hero film – microchip assembly
			      line, red/blue machine glow on black (client's licensed
			      footage, 1600w, muted). Bottom-aligned: the action sits in
			      the lower half of the frame. ── */
			'vertek_hero_mp4'    => 'vertek-hero-film.mp4',
			'vertek_hero_webm'   => 'vertek-hero-film.webm',
			'vertek_hero_poster' => 'vertek-hero-poster.jpg',
			/* ── Oct 2026: ModulR hero film – networked globe, deep blue
			      (client's licensed footage, 1600w, muted). ── */
			'modulr_hero_mp4'    => 'modulr-hero-film.mp4',
			'modulr_hero_webm'   => 'modulr-hero-film.webm',
			'modulr_hero_poster' => 'modulr-hero-poster.jpg',
			'insight_power' => 'insight-power.jpg',
			'insight_epc'   => 'insight-epc.jpg',
			// Round 5, item 3: interim sector images for the Edison specialism
			// cards – 16:9 crops of the existing licensed energy assets.
			'spec_edison_01' => 'spec-edison-01.jpg',
			'spec_edison_02' => 'spec-edison-02.jpg',
			'spec_edison_03' => 'spec-edison-03.jpg',
			'spec_edison_04' => 'spec-edison-04.jpg',
			'spec_edison_05' => 'spec-edison-05.jpg',
			'spec_edison_06' => 'spec-edison-06.jpg',
			// Brand-site media (Vertek) – hero doubles as the landing-about
			// image (no images.landingAbout override in the prototype data)
			'vertek_hero'           => 'vertek-hero.jpg',
			'insight_fluidpower'    => 'insight-fluidpower.jpg',
			'insight_manufacturing' => 'insight-manufacturing.jpg',
			'insight_hvac'          => 'insight-hvac.jpg',
			/* ── Client media drop, Aug 2026 – real event photography ──
			   Sorted from the client's forwarded emails: Barcelona incentive,
			   charity gala, Ibiza incentive (continues ibiza8/9), summer summit
			   (Southsea Castle) and a general Verto pool (Prague + office life). */
			'barcelona_01' => 'barcelona-01.jpg', // group outside the W Barcelona
			'barcelona_02' => 'barcelona-02.jpg', // rooftop selfie at the W
			'barcelona_03' => 'barcelona-03.jpg', // team on the street, Eixample
			'barcelona_04' => 'barcelona-04.jpg', // team dinner
			'barcelona_05' => 'barcelona-05.jpg', // dinner trio
			'barcelona_06' => 'barcelona-06.jpg', // boarding the easyJet flight
			'gala_01'      => 'gala-01.jpg',      // gala – group at the podium
			'gala_02'      => 'gala-02.jpg',      // gala – black-tie group
			'gala_03'      => 'gala-03.jpg',      // gala – dance floor
			'gala_04'      => 'gala-04.jpg',      // gala – the room
			'ibiza_11'     => 'ibiza-11.jpg',     // the team in the sea
			'ibiza_12'     => 'ibiza-12.jpg',     // boat group
			'ibiza_13'     => 'ibiza-13.jpg',     // on the boat, champagne
			'ibiza_14'     => 'ibiza-14.jpg',     // pool group
			'ibiza_15'     => 'ibiza-15.jpg',     // Formentera water
			'summit_02'    => 'summit-02.jpg',    // VERTO letters at the summit party
			'summit_03'    => 'summit-03.jpg',    // rooftop group at the castle
			'summit_04'    => 'summit-04.jpg',    // four under the Verto flag
			'summit_05'    => 'summit-05.jpg',    // team on the ramparts
			'summit_06'    => 'summit-06.jpg',    // the summit stage
			'verto_01'     => 'verto-01.jpg',     // Prague – Pilsner Urquell brewery
			'verto_02'     => 'verto-02.jpg',     // Prague – snowy old town
			'verto_03'     => 'verto-03.jpg',     // Verto's 6th birthday cake
			'verto_04'     => 'verto-04.jpg',     // office party
			'verto_05'     => 'verto-05.jpg',     // sales-day football shirts
			// Videos (portrait 720×1280 h264/aac, faststart) + poster frames
			'milly_video'        => 'milly-promotion.mp4',            // Milly Compton's promotion – confetti walk-in
			'milly_poster'       => 'milly-promotion-poster.jpg',
			'sade_video'         => 'sade-promotion.mp4',             // Sade Kendall's promotion announcement
			'sade_poster'        => 'sade-promotion-poster.jpg',
			'celebration_video'  => 'promotion-celebration.mp4',      // a promotion landing – the office turns out
			'celebration_poster' => 'promotion-celebration-poster.jpg',
			'sade_celebration_video'  => 'sade-celebration.mp4',      // Sade's celebration, second clip
			'sade_celebration_poster' => 'sade-celebration-poster.jpg',
			/* ── Oct-2026 promotion films (client drop) – two more confetti
			   walk-ins, same treatment as the Milly / Sade clips: portrait
			   720×1280 h264/aac, faststart, posters pulled from the films. */
			'harvey_video'       => 'harvey-promotion.mp4',           // Harvey Earl's promotion – and the US move
			'harvey_poster'      => 'harvey-promotion-poster.jpg',
			'frank_video'        => 'frank-promotion.mp4',            // Frank Warner's promotion announcement
			'frank_poster'       => 'frank-promotion-poster.jpg',
			'share_video'        => 'share-scheme.mp4',               // "What does Verto's share scheme mean" interview (58s)
			'share_poster'       => 'share-scheme-poster.jpg',
			// Round 5, item 10: the share-scheme awards-night photo – the team
			// with their Verto People Share Scheme Award certificates.
			'share_certs'        => 'share-certificates.jpg',
			/* ── Sep-2026 event films (client drop) – portrait 720×1280 h264/aac,
			   faststart, poster frames pulled from the films themselves. They
			   power the "Recent events" rail on the WGO hub, the About charity
			   card, the careers incentives pairing and the WGO post embeds. */
			'summit_film'         => 'summit-film.mp4',        // inside the summer summit party
			'summit_film_poster'  => 'summit-film-poster.jpg',
			'ibiza_trip_film'     => 'ibiza-trip.mp4',         // the winners' incentive trip to Ibiza
			'ibiza_trip_poster'   => 'ibiza-trip-poster.jpg',
			'charity_film'        => 'charity-film.mp4',       // the Maeve's Mission charity gala
			'charity_film_poster' => 'charity-film-poster.jpg',
			/* ── Sep-2026 sales-day films (client drop) – ten short muted
			   loops for the careers hover-to-play mosaic. Compact portrait
			   480×854 h264, no audio, ≤30s each; posters pulled from the
			   films themselves. Client brief: "Sales days are massive for
			   us … don't want massive video windows but if we could show
			   these somehow all on one section and hover over to play." */
			'salesday_01'        => 'salesday-01.mp4',
			'salesday_01_poster' => 'salesday-01-poster.jpg',
			'salesday_02'        => 'salesday-02.mp4',
			'salesday_02_poster' => 'salesday-02-poster.jpg',
			'salesday_03'        => 'salesday-03.mp4',
			'salesday_03_poster' => 'salesday-03-poster.jpg',
			'salesday_04'        => 'salesday-04.mp4',
			'salesday_04_poster' => 'salesday-04-poster.jpg',
			'salesday_05'        => 'salesday-05.mp4',
			'salesday_05_poster' => 'salesday-05-poster.jpg',
			'salesday_06'        => 'salesday-06.mp4',
			'salesday_06_poster' => 'salesday-06-poster.jpg',
			'salesday_07'        => 'salesday-07.mp4',
			'salesday_07_poster' => 'salesday-07-poster.jpg',
			'salesday_08'        => 'salesday-08.mp4',
			'salesday_08_poster' => 'salesday-08-poster.jpg',
			'salesday_09'        => 'salesday-09.mp4',
			'salesday_09_poster' => 'salesday-09-poster.jpg',
			'salesday_10'        => 'salesday-10.mp4',
			'salesday_10_poster' => 'salesday-10-poster.jpg',
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



	/** Team-structure schema version – bump when the master map changes so
	 *  existing installs re-run the migration on their next Rebuild. */
	// 0.22.12: Edison job titles from Alex (Joe → Lead Recruiter, Milly →
	// Principal Consultant | Power & Energy).
	const TEAM_STRUCTURE = 'structure-0.22.15';

	/**
	 * The client's definitive team structure (Alex Hatfield, Aug 2026).
	 * name => brands (→ _verto_brand, comma-separated – a person can sit on
	 * several sites; 'verto' = the group site only), tier (→ _verto_tier:
	 * leadership | management | ops | team), role (→ _verto_role), photo
	 * (bundled headshot in assets/import/, or null – the widgets render an
	 * initials placeholder until the client supplies one), leader
	 * (→ _verto_leader, the group leadership trio). Array order = display
	 * order within each tier (stamped as menu_order).
	 */
	private static function team_map(): array {
		/* ── structure-0.22.15: the client's "Verto Amendments" PDF (Oct 2026)
		      – definitive roles, tiers and photos. Photos are the Oct-2026
		      "Colour fixed website pics" zip (-v4 and named files), matched
		      tile-for-tile against the PDF's team-page mock. Four new people:
		      Simon Cottenham, Louis Fuller, Hannah Gresses, Jake Price. ── */
		return [
			/* ── The Directors ── */
			'Alex Hatfield'   => [ 'brands' => [ 'verto', 'vertek' ], 'tier' => 'leadership', 'role' => 'Founder', 'photo' => 'alex-hatfield-v4.png', 'leader' => true ],
			'Martin Doig'     => [ 'brands' => [ 'verto', 'modulr' ], 'tier' => 'leadership', 'role' => 'Founder', 'photo' => 'martin-doig-v4.png', 'leader' => true ],
			'Robbie Sturgess' => [ 'brands' => [ 'verto', 'vertek' ], 'tier' => 'leadership', 'role' => 'Founder', 'photo' => 'robbie-sturgess-v4.png', 'leader' => true ],
			/* ── Management ── */
			'Dan Bisset'        => [ 'brands' => [ 'edison-lux' ], 'tier' => 'management', 'role' => 'VP of Engineering', 'photo' => 'edison-dan-bisset-v4.png' ],
			'Simon Cottenham'   => [ 'brands' => [ 'edison-lux' ], 'tier' => 'management', 'role' => 'VP of Sales', 'photo' => 'edison-simon-cottenham.png' ],
			'George East'       => [ 'brands' => [ 'vertek' ], 'tier' => 'management', 'role' => 'Team Leader', 'photo' => 'vertek-george-east-v4.png' ],
			'Ben Tiffin'        => [ 'brands' => [ 'vertek' ], 'tier' => 'management', 'role' => 'Team Leader', 'photo' => 'vertek-ben-tiffin-v4.png' ],
			'Gary Hunt'         => [ 'brands' => [ 'vertek' ], 'tier' => 'management', 'role' => 'VP of Sales', 'photo' => 'vertek-gary-hunt-v4.png' ],
			'Ben Cranston'      => [ 'brands' => [ 'vertek' ], 'tier' => 'management', 'role' => 'Team Leader', 'photo' => 'vertek-ben-cranston.png' ],
			'Sade Kendall'      => [ 'brands' => [ 'modulr' ], 'tier' => 'management', 'role' => 'Team Leader', 'photo' => 'modulr-sade-kendall-v4.png' ],
			'Charlotte Northam' => [ 'brands' => [ 'modulr' ], 'tier' => 'management', 'role' => 'VP of Architecture', 'photo' => 'modulr-charlotte-northam-v4.png' ],
			/* ── Ops (Verto group pages only – fold into "The team" section) ── */
			'Karabo Mothopeng' => [ 'brands' => [ 'verto' ], 'tier' => 'ops', 'role' => 'Data Administrator', 'photo' => 'verto-karabo-mothopeng-v4.png' ],
			'Angel Ndlovu'     => [ 'brands' => [ 'verto' ], 'tier' => 'ops', 'role' => 'Data Administrator', 'photo' => 'verto-angel-ndlovu-v4.png' ],
			'Alice Fryer'      => [ 'brands' => [ 'verto' ], 'tier' => 'ops', 'role' => 'Talent Acquisition & Operations Lead', 'photo' => 'verto-alice-fryer.png' ],
			'Jake Price'       => [ 'brands' => [ 'verto' ], 'tier' => 'ops', 'role' => 'Finance & Administration Lead', 'photo' => 'verto-jake-price.png' ],
			'Megan Grant'      => [ 'brands' => [ 'verto' ], 'tier' => 'ops', 'role' => 'Senior Marketing Executive', 'photo' => null ],
			'Alfie Gray'       => [ 'brands' => [ 'verto' ], 'tier' => 'ops', 'role' => 'Digital Marketing Executive', 'photo' => 'verto-alfie-gray.png' ],
			/* ── The team – Vertek ── */
			'Alice Schofield'  => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Senior Recruitment Consultant', 'photo' => 'vertek-alice-schofield-v4.png' ],
			"Harley O'Connell" => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Senior Recruitment Consultant', 'photo' => 'vertek-harley-oconnell-v4.png' ],
			'Sam Parnell'      => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Lead Recruitment Consultant', 'photo' => 'vertek-sam-parnell-v4.png' ],
			'Frank Warner'     => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Lead Recruitment Consultant', 'photo' => 'vertek-frank-warner-v4.png' ],
			'Harvey Earl'      => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Lead Recruitment Consultant', 'photo' => 'vertek-harvey-earl-v4.png' ],
			'Lewis Mason'      => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Lead Recruitment Consultant', 'photo' => 'vertek-lewis-mason-v4.png' ],
			'Alex Wright'      => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'vertek-alex-wright-v4.png' ],
			'Lewis Sullivan'   => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'vertek-lewis-sullivan-v4.png' ],
			'Saman Akbari'     => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'vertek-saman-akbari.png' ],
			'Rex Reavley'      => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'vertek-rex-reavley-v4.png' ],
			'Olivia Pinhorne'  => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'vertek-olivia-pinhorne-v4.png' ],
			'Hannah Gresses'   => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'vertek-hannah-gresses.png' ],
			'Lethu Zwane'      => [ 'brands' => [ 'vertek' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'vertek-lethu-zwane-v4.png' ],
			/* ── The team – Verto Life Sciences (sits with the group) ── */
			'Martyn Jamieson'  => [ 'brands' => [ 'verto' ], 'tier' => 'team', 'role' => 'VP of Life Sciences', 'photo' => 'verto-martyn-jamieson.png' ],
			/* ── The team – ModulR (PDF: Jake now sits on ModulR only) ── */
			'Natasha Sykes'     => [ 'brands' => [ 'modulr' ], 'tier' => 'team', 'role' => 'Senior Recruitment Consultant', 'photo' => 'modulr-natasha-sykes-v4.png' ],
			'Monira Akter'      => [ 'brands' => [ 'modulr' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'modulr-monira-aktar-v4.png' ],
			'Lewis Wright'      => [ 'brands' => [ 'modulr' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'modulr-lewis-wright-v4.png' ],
			'Forough Rezaei'    => [ 'brands' => [ 'modulr' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'modulr-forough-rezaei.png' ],
			'Jake Massingham'   => [ 'brands' => [ 'modulr' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'modulr-jake-massingham.png' ],
			/* ── The team – Edison Lux ── */
			'Milly Compton'      => [ 'brands' => [ 'edison-lux' ], 'tier' => 'team', 'role' => 'Principal Recruitment Consultant', 'photo' => 'edison-milly-compton-v4.png' ],
			'Joe Williams'       => [ 'brands' => [ 'edison-lux' ], 'tier' => 'team', 'role' => 'Lead Recruitment Consultant', 'photo' => 'edison-joe-williams-v4.png' ],
			'Noah Ward'          => [ 'brands' => [ 'edison-lux' ], 'tier' => 'team', 'role' => 'Lead Recruitment Consultant', 'photo' => 'edison-noah-ward-v4.png' ],
			'Lewis Dominy'       => [ 'brands' => [ 'edison-lux' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'edison-lewis-dominy-v4.png' ],
			'Ollie Hesmondhalgh' => [ 'brands' => [ 'edison-lux' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'edison-oliver-hesmondhalgh.png' ],
			'Matthew Pearce'     => [ 'brands' => [ 'edison-lux' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'edison-matthew-pearce-v4.png' ],
			'Louis Fuller'       => [ 'brands' => [ 'edison-lux' ], 'tier' => 'team', 'role' => 'Recruitment Consultant', 'photo' => 'edison-louis-fuller.png' ],
		];
	}

	/**
	 * Seed / migrate the Team CPT from the master map. Runs once per
	 * TEAM_STRUCTURE version: fresh installs get the full roster; installs
	 * seeded by the pre-0.10 filename-guessed seeder are migrated in place –
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
		// structure – unpublished on migration (kept as drafts, not deleted).
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
		// Draft ANY published team entry not in the official structure –
		// covers named leavers AND stray historical seeds (e.g. asset files
		// like "Hero" that early builds mistook for headshots). Never deletes.
		$official = array_map( 'strtolower', array_keys( self::team_map() ) );
		foreach ( get_posts( [ 'post_type' => 'verto_team', 'post_status' => 'publish', 'posts_per_page' => -1 ] ) as $p ) {
			if ( ! in_array( strtolower( $p->post_title ), $official, true ) ) {
				wp_update_post( [ 'ID' => $p->ID, 'post_status' => 'draft' ] );
			}
		}
		update_option( 'verto_team_missing_photos', $missing );
		update_option( 'verto_installer_team', self::TEAM_STRUCTURE );
	}

	/** Team-photo batch version – bump when the client sends new treated
	 *  headshots so existing installs swap them in on their next Rebuild. */
	const TEAM_PHOTOS = 'photos-0.22.15';

	/**
	 * Versioned team-photo migration. Round 9, item 22 (photos-0.21.0): the
	 * client's four "new website pics" zips – treated brand-profile photos
	 * (grayscale subject, brand glow + logo). Every file below was matched to
	 * the roster against the existing headshots; Saman Akbari is deliberately
	 * excluded ("Leave Saman off cos he's not in that brand"), and Milly
	 * Compton keeps her verified v2 (round 9, item 23). The held-back zip-3
	 * files were re-examined for the photos-0.22.3 batch: Sam Parnell and
	 * Frank Warner are now positively identified and applied; the remaining
	 * unidentified faces are still NOT applied (see CLIENT-NEEDS.md:
	 * photo naming needed).
	 * seed_team() skips people who already have a thumbnail, so EXISTING
	 * installs need this pass to replace the old photos in place. Runs once
	 * per TEAM_PHOTOS version; skips anyone whose thumbnail already points
	 * at the new file (fresh installs – seed_team attached it via team_map).
	 */
	private static function migrate_team_photos(): void {
		if ( self::TEAM_PHOTOS === get_option( 'verto_installer_team_photos' ) ) return;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$dir     = dirname( __DIR__ ) . '/assets/import/';
		// photos-0.22.15: the complete Oct-2026 "Colour fixed website pics"
		// drop – every person on the roster, matched tile-for-tile against the
		// client's "Verto Amendments" PDF (their own photo picks). Replaces
		// all earlier batches; Megan Grant is the only person still without
		// a photo.
		$updates = array_filter( array_map(
			static fn( $row ) => $row['photo'],
			self::team_map()
		) );
		foreach ( $updates as $name => $file ) {
			if ( ! file_exists( $dir . $file ) ) continue;
			$posts = get_posts( [
				'post_type'      => 'verto_team',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'title'          => $name,
			] );
			if ( ! $posts ) continue;
			$id    = $posts[0]->ID;
			$thumb = get_post_thumbnail_id( $id );
			if ( $thumb ) {
				$attached = (string) get_post_meta( $thumb, '_wp_attached_file', true );
				if ( false !== strpos( $attached, pathinfo( $file, PATHINFO_FILENAME ) ) ) continue; // already the new photo
			}
			$tmp = wp_tempnam( $file );
			copy( $dir . $file, $tmp );
			$att = media_handle_sideload( [ 'name' => $file, 'tmp_name' => $tmp ], $id );
			if ( ! is_wp_error( $att ) ) set_post_thumbnail( $id, $att );
		}
		update_option( 'verto_installer_team_photos', self::TEAM_PHOTOS );
	}

	/* ── Job detail pages: office galleries + standing internal vacancies ── */

	/**
	 * Location → office gallery for the job detail page (single-verto_job.php).
	 * Every media key here MUST exist in import_media(); the template resolves
	 * them through the verto_installer_media option and silently skips any
	 * that are missing (site not built yet). Photos favour office/team shots
	 * over party ones – the skyline sets the scene, the strip shows the people.
	 */
	public static function location_gallery(): array {
		return [
			'solent' => [
				'name'    => 'Solent, UK',
				'address' => 'Arena Business Centre, Whiteley – Solent',
				// skyline-uk.jpg is now the client-supplied Spinnaker Tower photo
				// (replaced the wrong London skyline, Sep 2026).
				'skyline' => 'skyline_uk',
				'photos'  => [ 'verto_05', 'verto_03', 'summit_05', 'gala_02' ],
				'blurb'   => 'The Arena Business Centre on the Solent is where Verto started in 2020 – a lockdown launch that grew into the group\'s home. It\'s still the biggest office: every UK desk sits here, the sales days are loudest here, and the summer summit is a short walk down the coast at Southsea Castle.',
				'note'    => '',
			],
			'austin' => [
				'name'    => 'Austin, TX',
				'address'  => '5900 Balcones Drive, Austin, TX – US headquarters',
				// Round 9, item 24: the client's favourite daytime river/kayaks
				// skyline leads; the rooftop drinks team photo joins the gallery.
				'skyline' => 'skyline_us',
				'photos'  => [ 'austin_rooftop', 'summit_04', 'barcelona_03' ],
				'blurb'   => '5900 Balcones Drive is Verto\'s US headquarters and the launch pad for the American build-out. It runs on the same playbook as the Solent office – same values, same incentives, same trips – with the US energy and industrial markets on the other end of the phone.',
				'note'    => '',
			],
			'newyork' => [
				'name'    => 'New York, NY',
				'address' => 'New York, NY – opening soon',
				'skyline' => 'skyline_eu',
				'photos'  => [ 'barcelona_01', 'summit_03', 'gala_02' ],
				'blurb'   => 'New York is next on the map: the office is opening soon as the home of ModulR\'s US data-centre and architecture desks. Founding-team seats – you\'d be helping to set the culture, not inheriting one.',
				'note'    => 'Office opening soon – early hires onboard with the existing teams until the doors open.',
			],
		];
	}

	/** Free-text _location meta ("Austin, TX", "Whiteley, Hampshire"…) → gallery key. */
	public static function location_key( string $location ): string {
		$location = strtolower( $location );
		if ( false !== strpos( $location, 'austin' ) || false !== strpos( $location, ', tx' ) || false !== strpos( $location, 'texas' ) ) {
			return 'austin';
		}
		if ( false !== strpos( $location, 'new york' ) || false !== strpos( $location, ', ny' ) || false !== strpos( $location, 'miami' ) ) {
			return 'newyork';
		}
		if ( false !== strpos( $location, 'solent' ) || false !== strpos( $location, 'whiteley' ) || false !== strpos( $location, 'hampshire' ) || false !== strpos( $location, 'uk' ) ) {
			return 'solent';
		}
		return '';
	}

	/** Standing-vacancies schema version – bump to re-assert the matrix on Rebuild. */
	// 0.22.14: Miami → New York (client relocated the planned US ModulR office).
	const JOBS_STRUCTURE = 'jobs-0.22.14';

	/** Shared "what you get" list – senior desks (perks language mirrors What We Offer). */
	private static function job_offer_senior(): string {
		return '<ul>'
			. '<li>40% commission – one of the strongest splits in the market, transparent from day one.</li>'
			. '<li>The group share scheme – every person in the business owns a piece of Verto.</li>'
			. '<li>Two international incentive trips a year – Barcelona, Prague and Ibiza so far.</li>'
			. '<li>Winners\' lunches, monthly sales days, Wear Your Success milestones and the 3650 Club at ten years.</li>'
			. '<li>Healthcare cash-back and a company pension from day one.</li>'
			. '</ul>';
	}

	/** Shared "what you get" list – entry-level desks (L&D + ladder led). */
	private static function job_offer_junior(): string {
		return '<ul>'
			. '<li>Structured L&amp;D that starts on day one and never really stops.</li>'
			. '<li>A published ladder from consultant to principal – no mystery promotions.</li>'
			. '<li>Uncapped commission and the group share scheme from day one.</li>'
			. '<li>Two international incentive trips a year – hit target and you\'re on the plane.</li>'
			. '<li>Winners\' lunches, monthly sales days, healthcare cash-back and pension.</li>'
			. '</ul>';
	}

	/**
	 * The standing internal vacancies – a senior AND an entry-level (12
	 * months' recruitment experience) recruiter seat for every brand×office
	 * the client trades from: Edison Lux (Austin), Vertek (Solent + Austin),
	 * ModulR (New York, opening soon), Verto Life Sciences (Solent). These are
	 * REAL verto_job posts (public detail pages, apply form) so the board is
	 * never placeholder-driven on a built site. Meta: _manual=1, NO
	 * _vincere_id – the Vincere sync can neither overwrite nor deactivate
	 * them (deactivate_missing skips posts without a _vincere_id).
	 */
	private static function jobs_map(): array {
		$senior = self::job_offer_senior();
		$junior = self::job_offer_junior();
		return [
			/* ── Edison Lux – Austin, TX ── */
			'edison-austin-senior' => [
				'title'    => 'Senior Recruitment Consultant – US Power & Energy',
				'brand'    => 'edison-lux',
				'location' => 'Austin, TX',
				'level'    => 'Senior',
				'package'  => '$60–80k base + 40% commission + share scheme',
				'content'  =>
					'<p>Edison Lux is Verto\'s US energy brand: power generation, transmission &amp; distribution, renewables and the EPCs building all of it. The market is short of good people and long on projects – and this desk comes with live clients, a warm network and a brand that\'s already known in the sector.</p>'
					. '<p>We\'re looking for an experienced recruiter to own a market of their own: build it, bill it, and eventually hire underneath it. You\'ll run the full 360 from our Austin office at 5900 Balcones Drive – US headquarters, same playbook and incentives as the UK.</p>'
					. '<h3>What you\'ll get</h3>' . $senior
					. '<h3>What you\'ll need</h3>'
					. '<p>A track record of billing in a 360 recruitment seat – energy or industrial markets help, but drive matters more than sector history. US work authorisation required.</p>',
			],
			'edison-austin-junior' => [
				'title'    => 'Recruitment Consultant – US Power & Energy',
				'brand'    => 'edison-lux',
				'location' => 'Austin, TX',
				'level'    => 'Entry-level',
				'package'  => '$50–60k base + uncapped commission + share scheme',
				'content'  =>
					'<p>A seat on the Edison Lux desk in Austin for a recruiter with at least 12 months\' recruitment experience who wants a bigger market and a faster ladder. US power and energy is one of the busiest staffing markets in the country – you\'ll learn it from consultants who already bill in it.</p>'
					. '<p>You\'ll start with structured training, live roles and a clear published path from consultant to senior. The commission is uncapped from day one, and every target hit counts towards the incentive trips.</p>'
					. '<h3>What you\'ll get</h3>' . $junior
					. '<h3>What you\'ll need</h3>'
					. '<p>A minimum of 12 months\' recruitment experience (any sector), the appetite to run a desk of your own, and US work authorisation.</p>',
			],
			/* ── Vertek – Solent, UK ── */
			'vertek-solent-senior' => [
				'title'    => 'Senior Recruitment Consultant – Technical Sales & Engineering',
				'brand'    => 'vertek',
				'location' => 'Solent, UK',
				'level'    => 'Senior',
				'package'  => '£35–45k base + 40% commission + share scheme',
				'content'  =>
					'<p>Vertek is the group\'s engineering brand: technical sales, service and engineering across fluid power, HVAC, industrial automation, CNC &amp; metalworking and advanced manufacturing – US and Europe. It\'s Verto\'s largest team, and it\'s still short of senior hands.</p>'
					. '<p>This is a senior 360 seat at the Arena Business Centre on the Solent – where Verto started – with the freedom to own a vertical outright: your clients, your market map, your name on it. Prove the desk and the next step is building a team under it.</p>'
					. '<h3>What you\'ll get</h3>' . $senior
					. '<h3>What you\'ll need</h3>'
					. '<p>Solid 360 billing history – engineering, industrial or technical sales recruitment ideally, but we care more about how you build relationships than which sector you built them in.</p>',
			],
			'vertek-solent-junior' => [
				'title'    => 'Recruitment Consultant – Engineering',
				'brand'    => 'vertek',
				'location' => 'Solent, UK',
				'level'    => 'Entry-level',
				'package'  => '£25–28k base + uncapped commission',
				'content'  =>
					'<p>Join the Vertek desk at Verto\'s Solent home with at least 12 months\' recruitment experience behind you. Engineering and technical sales recruitment rewards people who learn their market properly – and our structured L&amp;D is built to get you there fast, sat beside consultants who already own their verticals.</p>'
					. '<p>You\'ll take on live engineering roles from week one with a clear, published route to senior consultant. Nothing here is capped: not the commission, not the ladder, not the trips.</p>'
					. '<h3>What you\'ll get</h3>' . $junior
					. '<h3>What you\'ll need</h3>'
					. '<p>A minimum of 12 months\' recruitment experience in any sector, genuine curiosity about how things get built, and the work rate to match the desk next to you.</p>',
			],
			/* ── Vertek – Austin, TX ── */
			'vertek-austin-senior' => [
				'title'    => 'Senior Recruitment Consultant – HVAC & Industrial',
				'brand'    => 'vertek',
				'location' => 'Austin, TX',
				'level'    => 'Senior',
				'package'  => '$55–70k base + 40% commission + share scheme',
				'content'  =>
					'<p>Vertek\'s US desk runs HVAC, fluid power, compressors and industrial automation out of Austin – markets where the skills shortage is real and clients pay for people who actually know the difference between a chiller and a cooling tower.</p>'
					. '<p>We want an experienced 360 recruiter to own one of those verticals from our US headquarters at 5900 Balcones Drive. The UK team\'s client base crosses the Atlantic with you; the market you build in the US is yours.</p>'
					. '<h3>What you\'ll get</h3>' . $senior
					. '<h3>What you\'ll need</h3>'
					. '<p>A billing track record in a full-desk recruitment seat; industrial, HVAC or technical markets a bonus. US work authorisation required.</p>',
			],
			'vertek-austin-junior' => [
				'title'    => 'Recruitment Consultant – HVAC & Industrial',
				'brand'    => 'vertek',
				'location' => 'Austin, TX',
				'level'    => 'Entry-level',
				'package'  => '$50–60k base + uncapped commission + share scheme',
				'content'  =>
					'<p>Twelve months into recruitment and ready for a proper market? Vertek\'s Austin desk covers HVAC, fluid power and industrial automation across the US – deep, technical, and nowhere near saturated. You\'ll learn it with structured training and a manager whose desk you can copy.</p>'
					. '<p>Clear ladder, uncapped commission, and the same incentives as every other Verto desk: hit target and you\'re on the plane with everyone else.</p>'
					. '<h3>What you\'ll get</h3>' . $junior
					. '<h3>What you\'ll need</h3>'
					. '<p>At least 12 months\' recruitment experience, resilience on the phone, and US work authorisation.</p>',
			],
			/* ── ModulR – New York, NY (office opening soon; was Miami until Oct 2026) ── */
			'modulr-miami-senior' => [
				'title'    => 'Senior Recruitment Consultant – Data Centres & Architecture',
				'brand'    => 'modulr',
				'location' => 'New York, NY',
				'level'    => 'Senior',
				'package'  => '$60–80k base + 40% commission + share scheme',
				'content'  =>
					'<p>ModulR connects the best talent in data centres and architecture with the companies building the future, across the USA and Europe. The US side of that is about to get its own home: our New York office opens soon, and this is one of its founding desks.</p>'
					. '<p>We\'re after a senior recruiter to own a US data-centre or architecture market outright – build the client base, set the standard, and shape the office culture from seat one. Until the doors open you\'ll onboard with the existing ModulR team.</p>'
					. '<h3>What you\'ll get</h3>' . $senior
					. '<h3>What you\'ll need</h3>'
					. '<p>Proven 360 billing – built environment, construction, M&amp;E or data-centre recruitment ideal. Founder mentality essential; US work authorisation required.</p>',
			],
			'modulr-miami-junior' => [
				'title'    => 'Recruitment Consultant – Data Centres & Architecture',
				'brand'    => 'modulr',
				'location' => 'New York, NY',
				'level'    => 'Entry-level',
				'package'  => '$50–60k base + uncapped commission + share scheme',
				'content'  =>
					'<p>Get into the data-centre boom on the ground floor – literally. ModulR\'s New York office opens soon, and we\'re hiring a recruiter with at least 12 months\' experience to grow with it. Hyperscale build-outs, architecture practices, MEP engineering: the clients are hiring faster than the market can supply.</p>'
					. '<p>You\'ll get structured L&amp;D, a published ladder to senior, and a manager who has already built the desk you\'re learning. Early hires onboard with the existing ModulR team until the office opens.</p>'
					. '<h3>What you\'ll get</h3>' . $junior
					. '<h3>What you\'ll need</h3>'
					. '<p>A minimum of 12 months\' recruitment experience, comfort with a market that moves quickly, and US work authorisation.</p>',
			],
			/* ── Verto (Life Sciences, group desk) – Solent, UK ── */
			'verto-solent-senior' => [
				'title'    => 'Senior Recruitment Consultant – Life Sciences',
				'brand'    => 'verto',
				'location' => 'Solent, UK',
				'level'    => 'Senior',
				'package'  => '£35–45k base + 40% commission + share scheme',
				'content'  =>
					'<p>Verto\'s Life Sciences desk sits with the group in the Solent office – pharma, biotech, medical devices and the quality and regulatory people who keep them compliant. It\'s a market with long relationships and fees to match, and we want a senior recruiter to own more of it.</p>'
					. '<p>Full 360, your own vertical, and the group\'s resources behind you: the database, the marketing engine, and a leadership team that still bills. When the desk outgrows you, you hire into it.</p>'
					. '<h3>What you\'ll get</h3>' . $senior
					. '<h3>What you\'ll need</h3>'
					. '<p>A consistent billing record in a 360 seat – life sciences, scientific or regulated-market recruitment preferred, strong desk-builders from any sector considered.</p>',
			],
			'verto-solent-junior' => [
				'title'    => 'Recruitment Consultant – Life Sciences',
				'brand'    => 'verto',
				'location' => 'Solent, UK',
				'level'    => 'Entry-level',
				'package'  => '£25–28k base + uncapped commission',
				'content'  =>
					'<p>Bring 12 months\' recruitment experience to a market worth learning properly. The Life Sciences desk works with pharma, biotech and medical-device companies across the UK and Europe – clients who value recruiters who do their homework, which is exactly what our L&amp;D programme teaches.</p>'
					. '<p>You\'ll sit with the group team in the Solent office, work live roles from the start, and follow a published ladder with no mystery promotions. Commission is uncapped; so is how far you take it.</p>'
					. '<h3>What you\'ll get</h3>' . $junior
					. '<h3>What you\'ll need</h3>'
					. '<p>At least 12 months\' recruitment experience in any sector, attention to detail, and the patience to build relationships that outlast a single fee.</p>',
			],
		];
	}

	/**
	 * Seed / migrate the standing internal vacancies (idempotent, versioned
	 * like seed_team): runs once per JOBS_STRUCTURE version. Tracked by key
	 * in the verto_installer_jobs_ids option so re-seeds update in place;
	 * jobs dropped from the matrix in a later version are drafted (never
	 * deleted). Manual jobs carry _manual=1 and NO _vincere_id, which keeps
	 * them invisible to the Vincere sync's deactivation pass.
	 *
	 * Round 9, item 1: brand installs pass their brand slug and get ONLY that
	 * brand's rows from the matrix, tracked in per-brand options – so an
	 * Edison Lux install seeds the two Edison desks, never the whole group
	 * board. The group site keeps its original options and full matrix.
	 */
	private static function seed_internal_jobs( string $brand = 'verto' ): void {
		$scoped     = 'verto' !== $brand;
		$opt_ver    = $scoped ? 'verto_installer_jobs_' . $brand : 'verto_installer_jobs';
		$opt_ids    = $scoped ? 'verto_installer_jobs_ids_' . $brand : 'verto_installer_jobs_ids';
		if ( self::JOBS_STRUCTURE === get_option( $opt_ver ) ) return;
		if ( ! post_type_exists( 'verto_job' ) ) return; // verto-widgets vincere module not loaded

		$ids = get_option( $opt_ids, [] );
		if ( ! is_array( $ids ) ) $ids = [];
		$map = self::jobs_map();
		if ( $scoped ) {
			$map = array_filter( $map, fn( $job ) => $brand === $job['brand'] );
		}

		foreach ( $map as $key => $job ) {
			$id = isset( $ids[ $key ] ) ? (int) $ids[ $key ] : 0;
			if ( $id && ! get_post( $id ) ) $id = 0;
			$postarr = [
				'post_type'    => 'verto_job',
				'post_status'  => 'publish',
				'post_title'   => $job['title'],
				'post_content' => $job['content'],
			];
			if ( $id ) {
				$postarr['ID'] = $id;
				$result = wp_update_post( wp_slash( $postarr ), true );
			} else {
				$result = wp_insert_post( wp_slash( $postarr ), true );
			}
			if ( is_wp_error( $result ) || ! $result ) continue;
			$id = (int) $result;
			$ids[ $key ] = $id;
			foreach ( [
				'_brand'    => $job['brand'],
				'_location' => $job['location'],
				'_level'    => $job['level'],
				'_job_type' => 'Permanent',
				'_package'  => $job['package'],
				'_active'   => '1',
				'_internal' => '1',
				'_manual'   => '1',
			] as $meta_key => $value ) {
				update_post_meta( $id, $meta_key, $value );
			}
			// Defensive: a manual job must never carry a _vincere_id (that
			// would put it in the sync's deactivation scope).
			delete_post_meta( $id, '_vincere_id' );
		}

		// Draft seeded jobs whose key left the matrix (filled seats etc.).
		foreach ( $ids as $key => $id ) {
			if ( ! isset( $map[ $key ] ) && get_post( (int) $id ) ) {
				update_post_meta( (int) $id, '_active', '0' );
				wp_update_post( [ 'ID' => (int) $id, 'post_status' => 'draft' ] );
			}
		}

		update_option( $opt_ids, $ids );
		update_option( $opt_ver, self::JOBS_STRUCTURE );
	}

	/** Seed the "What's going on" posts (idempotent, batch-versioned).
	 *  Magazine categories (Trips / Wins / Community / News) power the
	 *  category chips on the WGO hub – all four terms are created so the
	 *  client can file new stories straight away.
	 *
	 *  Batches (each tracked in its own option so EXISTING installs pick up
	 *  the later batches on Rebuild, mirroring the team-seeder migration):
	 *   1. verto_installer_posts       – the original three placeholder posts.
	 *   2. verto_installer_posts_media – the Aug-2026 client media drop:
	 *      Barcelona, summer summit, and the Milly / Sade promotion videos. */
	private static function seed_posts( array $media ): void {
		$cat_ids = [];
		foreach ( [ 'Trips', 'Wins', 'Community', 'News' ] as $cat_name ) {
			$t = wp_insert_term( $cat_name, 'category' );
			$cat_ids[ $cat_name ] = is_wp_error( $t ) ? ( get_term_by( 'name', $cat_name, 'category' )->term_id ?? 0 ) : $t['term_id'];
		}
		// Migration: earlier builds filed the seeded posts under "Life at Verto" –
		// on rebuild, move any seeded post still in that category to its new one.
		// The media drop also upgrades two stock featured images to the client's
		// real photography: Prague post → the Pilsner Urquell group shot, and the
		// Ibiza post → the team in the sea (its old image was an Ibiza re-use).
		$existing = get_option( 'verto_installer_posts' );
		if ( $existing ) {
			$map    = [ 'Sunday Times' => 'Wins', 'Prague' => 'Trips', 'Ibiza' => 'Trips' ];
			$refresh = [ 'Prague' => 'verto_01', 'Ibiza' => 'ibiza_11' ];
			foreach ( (array) $existing as $pid ) {
				$post = get_post( $pid );
				if ( ! $post ) continue;
				foreach ( $map as $needle => $cat ) {
					if ( str_contains( $post->post_title, $needle ) && ! empty( $cat_ids[ $cat ] ) ) {
						wp_set_post_categories( $pid, [ $cat_ids[ $cat ] ] );
						break;
					}
				}
				foreach ( $refresh as $needle => $mkey ) {
					if ( str_contains( $post->post_title, $needle ) && ! empty( $media[ $mkey ]['id'] ) ) {
						set_post_thumbnail( $pid, $media[ $mkey ]['id'] );
						break;
					}
				}
			}
			$old = get_term_by( 'name', 'Life at Verto', 'category' );
			if ( $old && 0 === (int) $old->count ) wp_delete_term( $old->term_id, 'category' );
		} else {
			$posts = [
				[ 'title' => 'Verto named in The Sunday Times Best Places to Work 2026',
				  'excerpt' => "Officially one of the UK's best small organisations to work for. Six years from a lockdown start-up to a Sunday Times listing – built on the same five values we started with.",
				  'image' => 'award_bptw', 'category' => 'Wins' ],
				[ 'title' => 'Prague 2026 – the whole company, one incentive trip',
				  'excerpt' => 'Our second international incentive trip. Everyone who hit target, flights and all – this is what the 2× annual holiday incentive actually looks like.',
				  'image' => ! empty( $media['verto_01']['id'] ) ? 'verto_01' : 'ibiza8', 'category' => 'Trips' ],
				[ 'title' => 'Next stop: Ibiza – the 2026 summer incentive revealed',
				  'excerpt' => 'Barcelona 2025. Prague, January 2026. And this summer, the team that delivers gets Ibiza. The countdown is on.',
				  'image' => ! empty( $media['ibiza_11']['id'] ) ? 'ibiza_11' : 'ibiza9', 'category' => 'Trips' ],
			];
			$ids = [];
			foreach ( $posts as $post ) {
				$cat_id = $cat_ids[ $post['category'] ] ?? 0;
				$id = wp_insert_post( [
					'post_title'   => $post['title'],
					'post_excerpt' => $post['excerpt'],
					'post_content' => $post['excerpt'],
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
		self::seed_media_drop_posts( $media, $cat_ids );
		self::seed_share_awards_post( $media, $cat_ids );
		self::seed_event_film_embeds( $media );
		self::seed_promotion_film_posts( $media, $cat_ids );
	}

	/** Batch 5 – Oct-2026 promotion films (client drop): Harvey Earl's and
	 *  Frank Warner's promotion announcements, same confetti treatment as
	 *  the Milly / Sade clips. Versioned in its own option, mirroring the
	 *  seed_event_film_embeds() / seed_share_awards_post() migrations, so
	 *  EXISTING installs gain both posts on Rebuild; fresh installs run it
	 *  in the same build, right after the earlier batches. */
	private static function seed_promotion_film_posts( array $media, array $cat_ids ): void {
		if ( get_option( 'verto_installer_posts_promos' ) ) return;
		$vid = static function ( $video_key, $poster_key ) use ( $media ) {
			if ( empty( $media[ $video_key ]['url'] ) ) return '';
			$poster = empty( $media[ $poster_key ]['url'] ) ? '' : ' poster="' . esc_url( $media[ $poster_key ]['url'] ) . '"';
			return "\n\n" . '[video mp4="' . esc_url( $media[ $video_key ]['url'] ) . '"' . $poster . ' preload="none"]';
		};
		// The films ship with the plugin, so both should exist by now – but if
		// an import failed, leave the option unset so the next Rebuild retries.
		if ( '' === $vid( 'harvey_video', 'harvey_poster' ) || '' === $vid( 'frank_video', 'frank_poster' ) ) return;
		$posts = [
			[ 'title'   => 'Harvey Earl promoted',
			  'excerpt' => 'The office knew before Harvey did – confetti down, everyone on their feet, and Harvey Earl walking into a promotion announced the Verto way.',
			  'content' => "The whole office in on it, the confetti already on the carpet, and Harvey Earl walking into an announcement with his name on it. Promotions get the full treatment here – press play for the moment it landed." . $vid( 'harvey_video', 'harvey_poster' ),
			  'image'   => 'harvey_poster', 'category' => 'Wins', 'date' => '2026-09-29 12:00:00' ],
			[ 'title'   => 'Frank Warner promoted',
			  'excerpt' => 'Confetti on the floor and the whole office waiting – Frank Warner\'s promotion, announced in front of everyone. Watch the walk-in.',
			  'content' => "Another week, another walk-in: confetti underfoot, the whole office waiting, and Frank Warner stepping into a promotion announced the only way we know how. Press play for the moment it landed." . $vid( 'frank_video', 'frank_poster' ),
			  'image'   => 'frank_poster', 'category' => 'Wins', 'date' => '2026-10-02 12:00:00' ],
		];
		$ids = [];
		foreach ( $posts as $post ) {
			$cat_id = $cat_ids[ $post['category'] ] ?? 0;
			$id = wp_insert_post( [
				'post_title'   => $post['title'],
				'post_excerpt' => $post['excerpt'],
				'post_content' => $post['content'],
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_date'    => $post['date'],
				'post_category'=> $cat_id ? [ $cat_id ] : [],
			] );
			if ( $id && ! is_wp_error( $id ) && ! empty( $media[ $post['image'] ]['id'] ) ) {
				set_post_thumbnail( $id, $media[ $post['image'] ]['id'] );
			}
			$ids[] = $id;
		}
		update_option( 'verto_installer_posts_promos', $ids );
	}

	/** Batch 4 – Sep-2026 event films: embed the Ibiza incentive film into
	 *  "Next stop: Ibiza" (batch 1) and the summit film into "Inside the
	 *  summer summit" (batch 2), so both cards read as video (play badge).
	 *  Follows the seed_share_awards_post() migration pattern: versioned in
	 *  its own option, so EXISTING installs gain the embeds on Rebuild;
	 *  fresh installs run it in the same build, right after the posts seed. */
	private static function seed_event_film_embeds( array $media ): void {
		if ( get_option( 'verto_installer_posts_films' ) ) return;
		$vid = static function ( $video_key, $poster_key ) use ( $media ) {
			if ( empty( $media[ $video_key ]['url'] ) ) return '';
			$poster = empty( $media[ $poster_key ]['url'] ) ? '' : ' poster="' . esc_url( $media[ $poster_key ]['url'] ) . '"';
			return "\n\n" . '[video mp4="' . esc_url( $media[ $video_key ]['url'] ) . '"' . $poster . ' preload="none"]';
		};
		$embeds = [
			'Next stop: Ibiza'         => $vid( 'ibiza_trip_film', 'ibiza_trip_poster' ),
			'Inside the summer summit' => $vid( 'summit_film', 'summit_film_poster' ),
		];
		// The films ship with the plugin, so both should exist by now – but if
		// an import failed, leave the option unset so the next Rebuild retries.
		if ( in_array( '', $embeds, true ) ) return;
		$done = [];
		$pids = array_merge(
			(array) get_option( 'verto_installer_posts', [] ),
			(array) get_option( 'verto_installer_posts_media', [] )
		);
		foreach ( $pids as $pid ) {
			$post = $pid ? get_post( $pid ) : null;
			if ( ! $post ) continue;
			foreach ( $embeds as $needle => $embed ) {
				if ( false === strpos( $post->post_title, $needle ) ) continue;
				if ( ! has_shortcode( $post->post_content, 'video' ) && false === stripos( $post->post_content, '<video' ) ) {
					wp_update_post( [ 'ID' => $post->ID, 'post_content' => $post->post_content . $embed ] );
				}
				$done[] = (int) $post->ID;
				break;
			}
		}
		update_option( 'verto_installer_posts_films', $done ? $done : [ 0 ] );
	}

	/** Batch 3 – round 5, item 10: the share-scheme awards night (runs once,
	 *  on fresh AND existing installs – existing sites gain it on Rebuild).
	 *  Round 6, item 3: the post embeds the share-scheme film, so its card
	 *  reads as video (play badge); existing installs gain the embed on
	 *  Rebuild via the migration below. */
	private static function seed_share_awards_post( array $media, array $cat_ids ): void {
		$vid = '';
		if ( ! empty( $media['share_video']['url'] ) ) {
			$poster = empty( $media['share_poster']['url'] ) ? '' : ' poster="' . esc_url( $media['share_poster']['url'] ) . '"';
			$vid    = "\n\n" . '[video mp4="' . esc_url( $media['share_video']['url'] ) . '"' . $poster . ' preload="none"]';
		}
		$existing = get_option( 'verto_installer_posts_certs' );
		if ( $existing ) {
			// Migration (round 6, item 3): append the film to the already
			// seeded post if it doesn't embed a video yet.
			foreach ( (array) $existing as $pid ) {
				$post = $pid ? get_post( $pid ) : null;
				if ( $post && $vid && ! has_shortcode( $post->post_content, 'video' ) && false === stripos( $post->post_content, '<video' ) ) {
					wp_update_post( [ 'ID' => $pid, 'post_content' => $post->post_content . $vid ] );
				}
			}
			return;
		}
		$cat_id = $cat_ids['Wins'] ?? 0;
		$id = wp_insert_post( [
			'post_title'   => 'Share scheme awards night',
			'post_excerpt' => 'Gold balloons, a certificate in every pair of hands – the night the share scheme stopped being a line in the handbook and became a piece of paper with your name on it.',
			'post_content' => "Gold balloons, a stage, and a Verto People Share Scheme Award certificate in every pair of hands. The awards night is the share scheme made real: every person in the business owns a piece of Verto, and once a year we hand over the paperwork that proves it.\n\nIt's the perk we talk about most for a reason – commission pays for the month, equity pays for the years. Everyone in this photo owns part of the company they're building." . $vid,
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_date'    => '2026-09-05 19:00:00',
			'post_category'=> $cat_id ? [ $cat_id ] : [],
		] );
		if ( $id && ! is_wp_error( $id ) && ! empty( $media['share_certs']['id'] ) ) {
			set_post_thumbnail( $id, $media['share_certs']['id'] );
		}
		update_option( 'verto_installer_posts_certs', [ $id ] );
	}

	/** Batch 2 – real stories from the Aug-2026 client media drop (runs once,
	 *  on fresh AND existing installs). Promotion posts embed the transcoded
	 *  clips via the [video] shortcode with their poster frames. */
	private static function seed_media_drop_posts( array $media, array $cat_ids ): void {
		if ( get_option( 'verto_installer_posts_media' ) ) return;
		$vid = static function ( $video_key, $poster_key ) use ( $media ) {
			if ( empty( $media[ $video_key ]['url'] ) ) return '';
			$poster = empty( $media[ $poster_key ]['url'] ) ? '' : ' poster="' . esc_url( $media[ $poster_key ]['url'] ) . '"';
			return "\n\n" . '[video mp4="' . esc_url( $media[ $video_key ]['url'] ) . '"' . $poster . ' preload="none"]';
		};
		$posts = [
			[ 'title'   => 'Barcelona – where the incentive trips started',
			  'excerpt' => 'September 2025, the first international incentive: the whole qualifying team at the W Barcelona. Two trips a year has been the rhythm ever since.',
			  'content' => "September 2025, the first international incentive trip – everyone who hit target, straight off the plane and into the W Barcelona. Dinners on the marina, a rooftop or two, and the bar set for everything that followed: Prague in January, Ibiza this summer.\n\nTwo international trips a year is the standard now. This is where it started.",
			  'image'   => 'barcelona_01', 'category' => 'Trips', 'date' => '2025-09-15 10:00:00' ],
			[ 'title'   => 'Inside the summer summit',
			  'excerpt' => 'The whole group at Southsea Castle for the Verto Summer Summit – the numbers, the plans, then the Verto flag over the ramparts and letters lit up for the evening.',
			  'content' => "One day, the whole group, one castle. The Verto Summer Summit took over Southsea Castle: the half-year numbers and the US build-out plans in the morning, the Verto flag over the ramparts by the afternoon, and the light-up letters doing overtime at the party.\n\nEvery office, every brand, in one place – it happens twice a year, and it's the closest thing we have to a team photo that moves.",
			  'image'   => 'summit_03', 'category' => 'Community', 'date' => '2026-07-28 10:00:00' ],
			[ 'title'   => 'Milly Compton promoted',
			  'excerpt' => 'Confetti cannons in the Edison Lux corner – Milly Compton walked into an office that knew something she didn\'t. Promotion, announced the Verto way.',
			  'content' => "Confetti cannons, the whole office on its feet, and Milly Compton walking into a corridor that knew something she didn't. Promotion announcements get the full treatment here – press play for the moment it landed." . $vid( 'milly_video', 'milly_poster' ),
			  'image'   => 'milly_poster', 'category' => 'Wins', 'date' => '2026-07-30 12:00:00' ],
			[ 'title'   => 'Sade Kendall promoted',
			  'excerpt' => 'The ModulR desk had the confetti ready – Sade Kendall\'s promotion, announced in front of the whole office. Watch the moment it landed.',
			  'content' => "The letter, the confetti, the whole office in on it – Sade Kendall's promotion on the ModulR desk, announced the only way we know how. Press play for the walk-in." . $vid( 'sade_video', 'sade_poster' ),
			  'image'   => 'sade_poster', 'category' => 'Wins', 'date' => '2026-08-04 12:00:00' ],
		];
		$ids = [];
		foreach ( $posts as $post ) {
			$cat_id = $cat_ids[ $post['category'] ] ?? 0;
			$id = wp_insert_post( [
				'post_title'   => $post['title'],
				'post_excerpt' => $post['excerpt'],
				'post_content' => $post['content'],
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_date'    => $post['date'],
				'post_category'=> $cat_id ? [ $cat_id ] : [],
			] );
			if ( $id && ! is_wp_error( $id ) && ! empty( $media[ $post['image'] ]['id'] ) ) {
				set_post_thumbnail( $id, $media[ $post['image'] ]['id'] );
			}
			$ids[] = $id;
		}
		update_option( 'verto_installer_posts_media', $ids );
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
					'body'      => 'Founded in 2020, Verto connects exceptional technical and commercial people with the businesses that need them. Today, three focused brands – each with its own market, its own network and its own consultants – united by how we work.',
					'link_text' => 'Explore the Verto brands',
					'link'      => [ 'url' => '/about' ],
				] ),
				self::widget( 'verto-brand-tiles', [ 'items' => self::brand_tiles_items( $media ) ] ),
			], 'verto-muted verto-container-pad' ),
			// Round 6, item 6 – home order: Hero → Brands → Jobs → What We
			// Offer ("The package, in full") → What's Going On → Employee
			// voices/quotes + awards → Values → Instagram. Standalone sector
			// coverage is gone from Home (it lives on the brand tiles' hover
			// faces); it stays on About.
			self::section( [ self::widget( 'verto-jobs-board' ) ], 'verto-ink verto-container-pad' ),
			// Round 4, item 11: the client-logo strip is dead for good; 14
			// perks as a notched dark card grid instead (also on Careers).
			// Round 6, item 6: moved up – directly under the jobs board.
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'What we offer',
					'lines'   => [
						[ '_id' => self::eid(), 'line' => 'The package,' ],
						[ '_id' => self::eid(), 'line' => 'in full.' ],
					],
					'body' => "Fourteen reasons a desk here beats the one you're at – in money, ownership, travel and the things other agencies call perks and we call standard.",
				] ),
				// Round 5, item 10: the share-scheme awards-night photo – wide,
				// rounded, above the perks grid.
				self::widget( 'html', [ 'html' => self::share_certs_figure( $media ) ] ),
				self::widget( 'verto-perks' ),
			], 'verto-muted verto-container-pad' ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => "What's going on",
					'lines'   => [
						[ '_id' => self::eid(), 'line' => 'Life inside' ],
						[ '_id' => self::eid(), 'line' => 'the group.' ],
					],
					'body'      => 'Incentive trips, awards, sales days and everything in between – straight from the team, not a marketing department.',
					'link_text' => "See everything that's going on",
					'link'      => [ 'url' => '/whats-going-on' ],
				] ),
				self::widget( 'verto-posts-grid' ),
			], 'verto-container-pad' ),
			// Round 9, item 4: the awards band moves ABOVE the employee
			// voices, and the voices section goes full-width – the V-mask
			// photo media column is gone ("not keen" – Alex).
			self::section( [
				self::widget( 'verto-awards-strip', [
					'badge'  => self::media_setting( $media, 'award_bptw' ),
					'badge2' => self::media_setting( $media, 'award_recruiter' ),
				] ),
			], 'verto-ink verto-awards-lead' ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'What employees say about us',
					'size'    => 'verto-display-1',
					'lines'   => [
						[ '_id' => self::eid(), 'line' => "Don't take our" ],
						[ '_id' => self::eid(), 'line' => 'word for it.' ],
					],
					'body' => 'Straight from the team – what working here is actually like.',
				] ),
			], 'verto-ink verto-container-pad' ),
			self::section( [
				self::widget( 'verto-quotes' ),
			], 'verto-ink verto-quotes-strip' ),
			// Values moved below the voices/awards block (round 3, item 3 swap
			// with What's Going On, which now sits directly under the jobs board).
			// Round 4, items 1+8: Values goes LIGHT (ivory, gold numerals) so it
			// no longer reads as a second navy band under Awards.
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => "Verto's values",
					'lines'   => [
						[ '_id' => self::eid(), 'line' => 'Five values.' ],
						[ '_id' => self::eid(), 'line' => 'Every desk, every day.' ],
					],
					'body' => "Every desk runs its own market and its own network. What's shared is what we stand for – the five values every person across the group works by.",
				] ),
				self::widget( 'verto-values' ),
			], 'verto-values-light verto-container-pad verto-container-pad--values' ),
			// v0.22.4 (client): the full four-models section joins the GROUP
			// home too – after Values, before Instagram. Brand-neutral: the
			// group's gold --brand token drives the accents, Verto Engage
			// stays the flagship, and the section sits on the light page
			// ground ('default') rather than the muted band.
			self::four_models_section( 'default' ),
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
					'body' => "40% commission. A share scheme that includes everyone. Two incentive holidays a year and a genuine route to the US. If you're going to work this hard anyway, do it somewhere that pays you properly – in money, ownership and experiences.",
				] ),
			], 'verto-container-pad' ),
			// Round 4, item 5: heading carries no word "roles" (widget default).
			self::section( [ self::widget( 'verto-jobs-board' ) ], 'verto-ink verto-container-pad' ),
			// Sales days – client (Sep 2026): "Sales days are massive for us
			// and something we've been leading in the local area. Don't want
			// massive video windows but if we could show these somehow all on
			// one section and hover over to play." Ten compact muted films in
			// one dense mosaic; posters only on load, hover (or tap) to play.
			// Round 7, item 2: moved up to sit between the jobs board and the
			// package, per client.
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Sales days',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'One day a month, all in.' ] ],
					'body'    => "A company favourite: a full day of competition, prizes and noise, every month – and something we've been leading in the local area.",
				] ),
				self::widget( 'verto-salesdays-mosaic', [ 'items' => self::salesdays_items( $media ) ] ),
			], 'verto-muted verto-container-pad' ),
			// Round 4, item 11: the four-card "Why Verto" becomes the full
			// 14-perk "What we offer" notched card grid.
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'What we offer',
					'size'    => 'verto-display-3',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'The package, in full.' ] ],
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
					[ '_id' => self::eid(), 'kicker' => 'Year 4+', 'title' => 'Principal / Team Manager', 'body' => 'Lead a team or go deeper as a biller – both paths carry equity and a seat in how the group grows.' ],
				] ] ),
			], 'verto-muted verto-container-pad' ),
			// Incentives + share scheme – the client's share-scheme interview film
			// paired with the Ibiza incentive-trip film (Sep-2026 drop). Both are
			// click-to-play: poster + controls, nothing loads until pressed.
			// Round 7, item 4: rebalanced – full-width intro on top, then one
			// 3-up media row (awards-night photo + the two films) beneath.
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Incentives',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'Hit target. Board the plane.' ] ],
					'body'    => "Two international incentive trips a year, winners' lunches, sales days and personal training sessions. Barcelona 2025, Prague in January, Ibiza this summer – and a share scheme that includes every person in the business. Press play to hear what owning a piece of Verto actually means to the team, and to see where hitting target took the winners this summer.",
				] ),
				self::widget( 'html', [ 'html' => self::incentive_media_row_html( $media ) ] ),
			], 'verto-ink verto-container-pad' ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Our locations',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'Three places to build from.' ] ],
				] ),
				// Round 9, item 11: Solent tile – real office photography until
				// the Spinnaker photo arrives (the London skyline was wrong).
				self::widget( 'verto-footprint', [ 'items' => [
					[ '_id' => self::eid(), 'note' => 'Founding office', 'name' => 'Solent, UK', 'line1' => 'Where Verto started in 2020. Our largest office.', 'line2' => 'Vertek · ModulR · Life Sciences', 'image' => self::media_setting( $media, 'skyline_uk' ) ],
					[ '_id' => self::eid(), 'note' => 'US headquarters', 'name' => 'Austin, TX', 'line1' => 'Edison Lux and the Vertek US build-out.', 'line2' => 'The fastest-growing part of the group', 'image' => self::media_setting( $media, 'skyline_us' ) ],
					[ '_id' => self::eid(), 'note' => 'Coming soon', 'name' => 'New York, NY', 'line1' => "ModulR's US practice and founding desks.", 'line2' => 'Ground-floor opportunity', 'image' => self::media_setting( $media, 'skyline_eu' ) ],
				] ] ),
				// Round 9, item 25: #locations anchor – the About footprint
				// tiles link here.
			], 'verto-ink verto-container-pad', [ '_element_id' => 'locations' ] ),
		];
		self::upsert_page( 'careers', 'Careers', $careers );

		/* ── ABOUT ── */
		$about = [
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'About the Verto Group',
					// Round 9, item 7: page title steps down a size so the
					// collage fits the first view.
					'size'    => 'verto-display-2',
					'tag'     => 'h1',
					'lines'   => [
						[ '_id' => self::eid(), 'line' => 'Built on ambition.' ],
						[ '_id' => self::eid(), 'line' => 'Driven by people.' ],
					],
					'body' => "Founded in February 2020, Verto People was built on determination, ambition and a belief that great people create great businesses. While the world was slowing down, we were just getting started. What began with three founders is now an international recruitment business: three specialist brands, a growing Life Sciences division, and offices in Portsmouth, UK and Austin, Texas.\n\nToday we connect exceptional talent with ambitious businesses across the USA and Europe, specialising in Engineering, Energy, Data Centres, Architecture, Life Sciences and Sales. We've come a long way since 2020, but the ambition is the same: create opportunities, build lasting partnerships, and make a meaningful difference to the people and businesses we work with.",
				] ),
				// Round 9, item 7: tighter collage – smaller tiles (see the
				// .verto-collage row-height step-down in verto-ui.css), an
				// exact 4x3 fill so every photo is in view at 100%, and more
				// real photography from the barcelona-/verto-/summit- pool.
				// Item 11: the Solent tile drops the (wrong, London) skyline
				// for real office photography until the Spinnaker photo lands.
				self::widget( 'verto-collage', [ 'items' => [
					[ '_id' => self::eid(), 'size' => 'big',  'image' => self::media_setting( $media, 'ibiza_11' ),      'alt' => 'The Verto team in the sea in Ibiza', 'caption' => 'Ibiza – the 2026 summer incentive' ],
					[ '_id' => self::eid(), 'size' => 'std',  'image' => self::media_setting( $media, 'summit_02' ),     'alt' => 'The Verto summer summit',            'caption' => 'The summit' ],
					[ '_id' => self::eid(), 'size' => 'std',  'image' => self::media_setting( $media, 'barcelona_01' ),  'alt' => 'The team outside the W Barcelona',   'caption' => 'Barcelona' ],
					[ '_id' => self::eid(), 'size' => 'std',  'image' => self::media_setting( $media, 'skyline_uk' ),      'alt' => 'The Spinnaker Tower on the Solent', 'caption' => 'Solent, UK' ],
					[ '_id' => self::eid(), 'size' => 'std',  'image' => self::media_setting( $media, 'skyline_us' ),    'alt' => 'Austin, TX – the US build-out',      'caption' => 'Austin, TX' ],
					// Client (Oct 2026): keep the faces in frame – the portrait
				// ramparts photo crops to its face band (40% down) in the wide tile.
				[ '_id' => self::eid(), 'size' => 'wide', 'image' => self::media_setting( $media, 'summit_05' ),     'alt' => 'The team on the ramparts at Southsea Castle', 'caption' => '', 'focus' => '40%' ],
					[ '_id' => self::eid(), 'size' => 'std',  'image' => self::media_setting( $media, 'verto_04' ),      'alt' => 'An office party at Verto',           'caption' => '' ],
					[ '_id' => self::eid(), 'size' => 'std',  'image' => self::media_setting( $media, 'barcelona_03' ),  'alt' => 'The team on the street in Barcelona', 'caption' => '' ],
				] ] ),
			], 'verto-container-pad' ),
			// Oct 2026 (Alex): "The thesis" eyebrow gone, his reworded copy
			// (lightly tidied – typos fixed, close sharpened).
			self::section2( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => '',
					'size'    => 'verto-display-3',
					'lines'   => [
						[ '_id' => self::eid(), 'line' => 'Why the group' ],
						[ '_id' => self::eid(), 'line' => 'exists.' ],
					],
				] ),
			], [
				self::widget( 'text-editor', [
					'editor'       => "<p>Verto is a group of specialist brands, each with its own market, its own networks and its own consultants. What unites them is the Verto way of working: process-driven search built to get it right first time, feedback taken from every client and candidate interaction, and a belief that we're here to build teams, not fill seats.</p><p>A power operator hiring a Head of Commissioning shouldn't have to explain CCGT to their recruiter. A hydraulic OEM briefing a technical sales role shouldn't have to walk their consultant through what a proportional valve does. A hyperscaler filling a data-centre Project Manager shouldn't receive commercial-fit-out resumes.</p><p>So we built the opposite. Every consultant sits inside one of three brands, each led by people who've worked or recruited inside that sector. The group behind them is where it all started – and where the quality bar is set.</p>",
					'_css_classes' => 'verto-prose',
				] ),
			], 'verto-container-pad', 33 ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow'       => 'The story so far',
					'eyebrow_plain' => 'yes',
					'lines'   => [ [ '_id' => self::eid(), 'line' => '2020 → today.' ] ],
					'body'    => 'From a lockdown start-up to The Sunday Times Best Places to Work – scroll the journey.',
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
					'body' => "Each brand runs independently – its own P&L, its own MD, its own client relationships. What's shared is the standard every search is held to. Our life sciences desk sits with the group while it grows.",
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
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'Solent. Austin. Soon, New York.' ] ],
					'body'    => "Where it started, where it's grown, and where it's going next. Every location runs on the same platform, so a US brief with UK candidates – or the reverse – moves through one team.",
				] ),
				// Round 9, items 11 + 25: Solent tile swaps the wrong London
				// skyline for real office photography (Spinnaker photo still
				// awaited – CLIENT-NEEDS.md); every tile links through to the
				// careers locations section.
				self::widget( 'verto-footprint', [ 'items' => [
					[ '_id' => self::eid(), 'note' => 'Where it started – Feb 2020', 'name' => 'Solent, UK', 'line1' => 'Arena Business Centre, Havant, Portsmouth', 'line2' => 'Vertek · ModulR · Verto Life Sciences', 'image' => self::media_setting( $media, 'skyline_uk' ), 'link' => [ 'url' => '/careers#locations' ] ],
					[ '_id' => self::eid(), 'note' => 'US HQ', 'name' => 'Austin, TX', 'line1' => '5900 Balcones Drive, Austin', 'line2' => 'Edison Lux · Vertek US', 'image' => self::media_setting( $media, 'skyline_us' ), 'link' => [ 'url' => '/careers#locations' ] ],
					[ '_id' => self::eid(), 'note' => 'Coming soon', 'name' => 'New York, NY', 'line1' => 'Opening soon', 'line2' => 'ModulR US', 'image' => self::media_setting( $media, 'skyline_eu' ), 'link' => [ 'url' => '/careers#locations' ] ],
				] ] ),
			], 'verto-ink verto-container-pad' ),
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Leadership',
					'size'    => 'verto-display-3',
					// Verto Amendments PDF (Oct 2026): simple headings, dash removed.
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'The Directors.' ] ],
					'body'    => "Verto Group is founder-owned and independently financed. Every leader across the group has come up through the desk, either as a recruiter inside their sector, or as an operator hired by one of ours.",
				] ),
				self::widget( 'verto-team-grid', [ 'mode' => 'leaders' ] ),
				// Client's official structure: Leadership → Management → The team
				// (ops fold into the team section on the group pages).
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Management',
					'size'    => 'verto-display-3',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'Management.' ] ],
				] ),
				self::widget( 'verto-team-grid', [ 'mode' => 'all', 'tier' => 'management' ] ),
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'The team',
					'size'    => 'verto-display-3',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'The team.' ] ],
				] ),
				self::widget( 'verto-team-grid', [ 'mode' => 'all', 'tier' => 'team' ] ),
			], 'verto-container-pad' ),
			// Community & DE&I – gala cards now carry the client's real charity-
			// gala photography; the DE&I card stays a placeholder until the
			// client's numbers arrive (sits directly before the socials section).
			self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Community & DE&I',
					'lines'   => [ [ '_id' => self::eid(), 'line' => 'More than the numbers.' ] ],
					'body'    => 'Gala nights, fundraising and a genuine commitment to building a diverse group – the parts of Verto that never make a sales deck.',
				] ),
				self::widget( 'text-editor', [ 'editor' => self::community_cards_html( $media ) ] ),
			], 'verto-container-pad' ),
			// Round 9, item 10: the socials/Instagram section is gone from the
			// group About page (it stays on Home and the WGO hub).
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
				self::widget( 'text-editor', [ 'editor' => '<p>Thinking about joining Verto? Tell us about yourself and we\'ll come back within one business day.</p>' . self::cf7_form_html() ] ),
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

	/** Community & DE&I cards. The gala card uses the client's real
	 *  charity-gala photography (Aug-2026 drop); the charity card carries the
	 *  Sep-2026 charity-gala film (poster + click-to-play, nothing loads
	 *  until pressed); the DE&I card keeps its placeholder until the
	 *  client's commitments/numbers arrive. */
	private static function community_cards_html( array $media = [] ): string {
		$cards = [
			// Round 4, item 17: two galas now – most recent for Maeve's Mission,
			// after the 2023 Amelia-Mae Foundation gala.
			[ 'Gala nights', "Black-tie charity galas – most recently for Maeve's Mission, following the 2023 gala that raised £15,504 for the Amelia-Mae Foundation.", 'gala_01', 'The team on stage at the charity gala', null, null ],
			[ 'Charity & fundraising', 'Every office backs a cause the team chooses – fundraisers, sponsored events and hands-on volunteering through the year.', 'gala_02', 'Black-tie group at the charity gala', 'charity_film', 'charity_film_poster' ],
			[ 'DE&I commitments', 'Hiring on ability, progressing on results. Our DE&I commitments – and the numbers behind them – publish here soon.', null, '', null, null ],
		];
		$html = '<div class="verto-community">';
		foreach ( $cards as [ $title, $body, $mkey, $alt, $vkey, $pkey ] ) {
			$html .= '<article class="verto-community__card">';
			if ( $vkey && ! empty( $media[ $vkey ]['url'] ) ) {
				$poster = ( $pkey && ! empty( $media[ $pkey ]['url'] ) ) ? ' poster="' . esc_url( $media[ $pkey ]['url'] ) . '"' : '';
				$html  .= '<div class="verto-community__media verto-community__media--video">'
					. '<video controls preload="none" playsinline' . $poster . ' src="' . esc_url( $media[ $vkey ]['url'] ) . '" aria-label="' . esc_attr( $title . ' – the charity gala film' ) . '"></video>'
					. '</div>';
			} elseif ( $mkey && ! empty( $media[ $mkey ]['url'] ) ) {
				$html .= '<div class="verto-community__media"><img src="' . esc_url( $media[ $mkey ]['url'] ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" /></div>';
			} else {
				$html .= '<div class="verto-community__ph">'
					. ( function_exists( 'verto_icon' ) ? verto_icon( 'camera' ) : '' )
					. '<span class="verto-community__phnote">Photos coming from client</span>'
					. '</div>';
			}
			$html .= '<div class="verto-community__body">'
				. '<h3 class="verto-community__title">' . esc_html( $title ) . '</h3>'
				. '<p class="verto-community__text">' . esc_html( $body ) . '</p>'
				. '</div>'
				. '</article>';
		}
		return $html . '</div>';
	}

	/** Round 5, item 10 – the share-scheme awards-night photo as a wide
	 *  rounded figure with caption (home "What we offer" + careers incentives).
	 *  Renders nothing if the media hasn't been imported yet. */
	private static function share_certs_figure( array $media ): string {
		if ( empty( $media['share_certs']['url'] ) ) return '';
		return '<figure class="verto-wide-photo">'
			. '<img src="' . esc_url( $media['share_certs']['url'] ) . '" alt="The Verto team holding their share-scheme award certificates at the awards night" loading="lazy" />'
			. '<figcaption>Share scheme awards &mdash; everyone owns a piece</figcaption>'
			. '</figure>';
	}

	/** Round 6, item 7 – the share-certificates photo as a small rounded,
	 *  offset figure stacked under the V-mask image in the employee-voices
	 *  media column. Renders nothing until the media is imported. */
	private static function voices_photo_figure( array $media ): string {
		if ( empty( $media['share_certs']['url'] ) ) return '';
		return '<figure class="verto-voices-photo" style="margin-block:0;">'
			. '<img src="' . esc_url( $media['share_certs']['url'] ) . '" alt="The Verto team with their share-scheme certificates" loading="lazy" />'
			. '</figure>';
	}

	/** Portrait share-scheme interview film for the careers page –
	 *  click-to-play (poster + native controls, preload="none"). */
	private static function share_scheme_video_html( array $media ): string {
		if ( empty( $media['share_video']['url'] ) ) {
			return '<div class="verto-video-story verto-video-story--empty"><span class="verto-community__phnote">Share-scheme film coming soon</span></div>';
		}
		$poster = empty( $media['share_poster']['url'] ) ? '' : ' poster="' . esc_url( $media['share_poster']['url'] ) . '"';
		return '<figure class="verto-video-story">'
			. '<video controls preload="none" playsinline' . $poster . ' src="' . esc_url( $media['share_video']['url'] ) . '"></video>'
			. '<figcaption>&ldquo;One word for the share scheme?&rdquo; &mdash; the team, on camera</figcaption>'
			. '</figure>';
	}

	/** Round 7, item 4 – the careers incentives media row: the awards-night
	 *  photo beside the share-scheme interview film and the Ibiza
	 *  incentive-trip film (Sep-2026 drop), three equal-height cards under
	 *  the full-width intro. Films are click-to-play (poster + native
	 *  controls, preload="none"); pieces that have not been imported yet
	 *  are simply skipped. Falls back to the single share-scheme film
	 *  until the Ibiza film has been imported. */
	private static function incentive_media_row_html( array $media ): string {
		if ( empty( $media['ibiza_trip_film']['url'] ) ) {
			return self::share_scheme_video_html( $media );
		}
		$html = '<div class="verto-incentive-row">';
		if ( ! empty( $media['share_certs']['url'] ) ) {
			$html .= '<figure class="verto-incentive-row__card verto-incentive-row__photo">'
				. '<img src="' . esc_url( $media['share_certs']['url'] ) . '" alt="The Verto team holding their share-scheme award certificates at the awards night" loading="lazy" />'
				. '<figcaption>Share scheme awards &ndash; everyone owns a piece</figcaption>'
				. '</figure>';
		}
		$films = [
			[ 'share_video', 'share_poster', 'The share scheme', 'Still from the Verto share-scheme interviews' ],
			[ 'ibiza_trip_film', 'ibiza_trip_poster', 'The Ibiza trip', "The winners' incentive trip to Ibiza, summer 2026" ],
		];
		foreach ( $films as [ $vkey, $pkey, $label, $aria ] ) {
			if ( empty( $media[ $vkey ]['url'] ) ) continue;
			$poster = empty( $media[ $pkey ]['url'] ) ? '' : ' poster="' . esc_url( $media[ $pkey ]['url'] ) . '"';
			$html  .= '<figure class="verto-incentive-row__card">'
				. '<video controls preload="none" playsinline' . $poster . ' src="' . esc_url( $media[ $vkey ]['url'] ) . '" aria-label="' . esc_attr( $aria ) . '"></video>'
				. '<figcaption>' . esc_html( $label ) . '</figcaption>'
				. '</figure>';
		}
		return $html . '</div>';
	}

	/** Sales-day mosaic tiles – the ten Sep-2026 sales-day films (keys
	 *  salesday_01…salesday_10 + posters) as repeater items for the
	 *  verto-salesdays-mosaic widget. Films that have not been imported
	 *  yet are simply skipped, so the mosaic renders whatever is there. */
	private static function salesdays_items( array $media ): array {
		$items = [];
		for ( $i = 1; $i <= 10; $i++ ) {
			$key = sprintf( 'salesday_%02d', $i );
			if ( empty( $media[ $key ]['url'] ) ) continue;
			$items[] = [
				'_id'    => self::eid(),
				'video'  => self::media_setting( $media, $key ),
				'poster' => self::media_setting( $media, $key . '_poster' ),
				'label'  => sprintf( 'Sales day film %d of 10', $i ),
			];
		}
		return $items;
	}

	private static function brand_tiles_items( array $media ): array {
		return [
			[ '_id' => self::eid(), 'name' => 'Edison Lux', 'focus' => 'US Energy Staffing', 'color' => '#2B8EE5', 'bg' => '#0B1A2B',
			  // Client (Sep 2026): dark face with a touch of the brand gradient
			  // glowing through the corners (per the profile-image reference),
			  // white logo lockup, gradient wordmark kept.
			  'face_gradient' => 'radial-gradient(58% 52% at 88% 8%, rgba(60,199,55,0.38), transparent 66%), radial-gradient(62% 56% at 6% 94%, rgba(43,142,229,0.42), transparent 66%), #0B1A2B',
			  'name_gradient' => 'linear-gradient(90deg, #3CC739 0%, #2B8EE5 100%)',
			  'sectors' => "Critical Power & CCGT\nRenewables & Storage\nEPC & Project Delivery\nO&M (Operations & Maintenance)",
			  'logo' => self::media_setting( $media, 'logo_edison_white' ),
			  'positioning' => 'Edison Lux delivers talent solutions for the US & European energy sector, from control room operators to Senior Leadership responsible for billion-dollar assets.',
			  'link' => [ 'url' => verto_brand_url( 'edison-lux' ) ] ],
			// Round 4, item 13: new ModulR positioning; sectors trimmed to three
			// (Interior Design & Fit-out dropped pending client decision).
			// Round 6, item 1: "up the vibrant blue a little" – brighter
			// royal-blue (#0464FA) glows in the face, logo contrast kept.
			[ '_id' => self::eid(), 'name' => 'MODULR', 'focus' => 'Architecture, Data Centres & Built Environment', 'color' => '#0464FA', 'bg' => '#000724',
			  'face_gradient' => 'radial-gradient(60% 55% at 85% 10%, rgba(4,100,250,0.5), transparent 65%), radial-gradient(55% 60% at 10% 90%, rgba(4,100,250,0.32), transparent 65%), #000724',
			  'sectors' => "Architecture\nData Centres\nMEP Engineering",
			  'logo' => self::media_setting( $media, 'logo_modulr_png' ),
			  'positioning' => 'ModulR connects the very best talent in Data Centres and Architecture with the companies building the future. Covering both the USA and Europe.',
			  'link' => [ 'url' => verto_brand_url( 'modulr' ) ] ],
			// Round 4, item 12: Vertek is US + Europe, with the client's six
			// named sectors on the hover face.
			// Round 6, item 2: "a bit more pink" – more of the Signal Red
			// (#F82B60) from the logo, as stronger glows over the near-black.
			[ '_id' => self::eid(), 'name' => 'Vertek', 'focus' => 'Technical Sales, Service & Engineering', 'color' => '#F82B60', 'bg' => '#0E1013',
			  'face_gradient' => 'radial-gradient(60% 55% at 85% 10%, rgba(248,43,96,0.34), transparent 65%), radial-gradient(55% 60% at 10% 90%, rgba(248,43,96,0.22), transparent 65%), #0E1013',
			  'sectors' => "Fluid Power (pumps, seals, valves & hydraulics)\nHVAC\nDefense & Advanced Manufacturing\nIndustrial Automation\nCNC & Metalworking\nCompressors",
			  'logo' => self::media_setting( $media, 'logo_vertek' ),
			  'positioning' => 'Vertek recruits technical sales, service and engineering professionals for the manufacturers and distributors that keep industry moving – across the USA and Europe.',
			  'link' => [ 'url' => verto_brand_url( 'vertek' ) ] ],
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
			'edison-lux' => [ 'Edison Lux', 'Executive search for energy & infrastructure. The standalone Edison Lux site is being built now – this link will point to it at launch.' ],
			'modulr'     => [ 'ModulR', 'Talent for modular construction & offsite manufacturing. The standalone ModulR site is being built now – this link will point to it at launch.' ],
			'vertek'     => [ 'Vertek', 'Technical sales, service & engineering recruitment. The standalone Vertek site is being built now – this link will point to it at launch.' ],
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
				'focus'       => 'Architecture, Data Centres & Built Environment',
				'focus_lower' => 'architecture, data centres & the built environment',
				'hero'        => [
					'line1'  => 'Connecting talent.',
					'line2'  => 'Powering progress',
					// Round 4, item 13: client-approved positioning.
					'sub'    => 'Modulr connects the very best talent in Data Centres and Architecture with the companies building the future. Covering both the USA and Europe.',
					// Oct 2026: looping hero film – the networked globe in motion.
					'video_mp4'       => 'modulr_hero_mp4',
					'video_webm'      => 'modulr_hero_webm',
					'poster'          => 'modulr_hero_poster',
					'object_position' => 'center bottom',
					'image'  => 'modulr_hero',
					'alt'    => 'Glowing globe at night with arcs of light connecting cities',
					'scale'  => 1,
					'offset' => 30,
				],
				'features' => [
					[ 'icon' => 'bar-chart-3', 'title' => 'Verto Engage', 'body' => 'Our flagship model with a 100% success rate, an extended candidate guarantee, and the initial fee taken off on a successful hire.' ],
					[ 'icon' => 'handshake', 'title' => 'World-Class NPS', 'body' => 'We capture feedback from every candidate and client interaction to hold ourselves to the highest standard.' ],
					[ 'icon' => 'lock',      'title' => 'NDA-Grade Discretion',    'body' => 'Sensitive, pre-announcement and competitor-adjacent search handled as standard.' ],
					[ 'icon' => 'globe-2', 'title' => 'USA & Europe', 'body' => 'Data centres, colocation and celebrated architecture practices – two regions, one network.' ],
				],
				'about'        => [
					'headline' => 'Considered introductions, not resumes into the void.',
					'mission'  => "Build teams for the projects that will define a generation – staffing what others can't, working quickly and discreetly, and to the standard those projects demand.",
					'vision'   => 'A world where no exceptional architect or built-environment professional is stuck because the right opportunity was invisible to them – and the first call every project director makes when they need to scale.',
					'purpose'  => 'Championing the professionals who build our world – opening doors that don\'t exist yet, so the most ambitious projects in architecture and critical infrastructure are built by the best people, not whoever applied first.',
				],
				'about_image'     => 'modulr_datacentre',
				'about_image_alt' => 'Data centre corridor lined with server racks and glowing status lights',
				'stats' => [
					[ 'value' => 'USA & Europe',   'label' => 'Two regions, one network' ],
					[ 'value' => 'Full lifecycle', 'label' => 'Concept design to commissioning' ],
					[ 'value' => 'NDA-grade',      'label' => 'Discretion on every search' ],
				],
				// Round 4, item 13: Hyperscale/US prefixes dropped from the labels.
				'specialisms' => [
					[ 'icon' => 'server',          'title' => 'Data Centres',            'description' => 'Construction directors, regional heads and project leadership across operators, developers and contractors.' ],
					[ 'icon' => 'network',         'title' => 'Colocation & Edge',       'description' => 'Delivery and operations talent for colo and edge programmes at every stage.' ],
					[ 'icon' => 'building-2',      'title' => 'Architecture',            'description' => 'Registered architects, project architects, directors, principals and partners.' ],
					[ 'icon' => 'zap',             'title' => 'MEP Engineering',         'description' => 'Mechanical, electrical and plumbing leadership across the USA project landscape.' ],
					[ 'icon' => 'layers',          'title' => 'Project Lifecycle',       'description' => 'CD → SD → DD → CD → CA. Concept design through construction administration.' ],
					[ 'icon' => 'heart-handshake', 'title' => 'Inclusion & EDI',         'description' => 'Championing women in architecture and EDI across technical built-environment roles.' ],
				],
				/* ── Round 10: REAL feedback (client's Canva doc + pasted extras,
				      Sep 2026), curated + lightly cleaned (typos/spacing only,
				      meaning unchanged). Reviewers stay anonymous except Ochuko
				      (client instruction); 'consultant' = full roster name – the
				      quote-band resolves the verto_team headshot at render time.
				      home_testimonials = the mixed 4–5 highlight set; the full
				      curated sets live in client_quotes / candidate_quotes. ── */
				'home_testimonials' => [
					[ 'tag' => 'client',    'consultant' => 'Charlotte Northam', 'attribution' => 'Ochuko', 'quote' => "Charlotte listens to and understands the staffing needs of my office. She approaches the search with a sense of dogged commitment and responsiveness that matches the urgency. Charlotte's personable touch makes working with her a pleasure." ],
					[ 'tag' => 'candidate', 'consultant' => 'Sade Kendall', 'attribution' => '', 'quote' => 'Sade took the time to get to know me and what I was looking for, and matched it with a strong fit for both sides.' ],
					[ 'tag' => 'client',    'consultant' => 'Sade Kendall', 'attribution' => '', 'quote' => 'Sade has been fantastic to work with and has quickly established herself as a trusted recruitment partner. We have been particularly impressed with her responsiveness, market knowledge, and commitment to achieving the right outcomes.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Charlotte Northam', 'attribution' => '', 'quote' => 'I cannot speak highly enough of my experience with Charlotte. She went above and beyond to help me find my dream job. From the very beginning, Charlotte really understood me, my level of experience and what I was searching for.' ],
				// Oct 2026 (Alex): the two remaining forwarded client quotes
				// join the home rotation as well (already in client_quotes).
				[ 'tag' => 'client',    'consultant' => 'Charlotte Northam', 'attribution' => '', 'quote' => "Charlotte is an incredible partner. She is professional, efficient and attentive. We've worked together on several opportunities at more than one firm, and I often recommend her to friends and colleagues." ],
				[ 'tag' => 'client',    'consultant' => 'Sade Kendall', 'attribution' => '', 'quote' => "Sade is an absolute pleasure to work with. She's incredibly knowledgeable, approachable, and easy to talk to, which makes collaborating with her a genuinely enjoyable experience. I'd highly recommend Sade to anyone who wants to work with someone who combines professionalism with a truly warm and personable nature." ],
					[ 'tag' => 'candidate', 'consultant' => 'Natasha Sykes', 'attribution' => '', 'quote' => 'Natasha has been very supportive throughout the application, which I have found to be lacking with other recruiters. The calls surrounding interviews are appreciated because they make candidates feel valued.' ],
				],
				// All five client quotes that exist (2 Sade, 3 Charlotte incl.
				// Ochuko – the Ochuko quote appears in both source docs, so four
				// unique texts run here).
				'client_quotes' => [
					[ 'tag' => 'client', 'consultant' => 'Sade Kendall', 'attribution' => '', 'quote' => 'Sade has been fantastic to work with and has quickly established herself as a trusted recruitment partner. She has taken the time to understand our requirements and consistently provided a high level of service and communication throughout. We have been particularly impressed with her responsiveness, market knowledge, and commitment to achieving the right outcomes.' ],
					[ 'tag' => 'client', 'consultant' => 'Charlotte Northam', 'attribution' => 'Ochuko', 'quote' => "Charlotte listens to and understands the staffing needs of my office. She approaches the search with a sense of dogged commitment and responsiveness that matches the urgency. Charlotte's personable touch makes working with her a pleasure." ],
					[ 'tag' => 'client', 'consultant' => 'Charlotte Northam', 'attribution' => '', 'quote' => "Charlotte is an incredible partner. She is professional, efficient and attentive. We've worked together on several opportunities at more than one firm, and I often recommend her to friends and colleagues." ],
					[ 'tag' => 'client', 'consultant' => 'Sade Kendall', 'attribution' => '', 'quote' => "Sade is an absolute pleasure to work with. She's incredibly knowledgeable, approachable, and easy to talk to, which makes collaborating with her a genuinely enjoyable experience. I'd highly recommend Sade to anyone who wants to work with someone who combines professionalism with a truly warm and personable nature." ],
				],
				'candidate_quotes' => [
					[ 'tag' => 'candidate', 'consultant' => 'Charlotte Northam', 'attribution' => '', 'quote' => 'I cannot speak highly enough of my experience with Charlotte. She went above and beyond to help me find my dream job. From the very beginning, Charlotte really understood me, my level of experience and what I was searching for. She felt like a true friend and ally in this process.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Sade Kendall', 'attribution' => '', 'quote' => 'Sade took the time to get to know me and what I was looking for, and matched it with a strong fit for both sides.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Natasha Sykes', 'attribution' => '', 'quote' => 'Natasha is an extremely good communicator, very precise and responsive.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Charlotte Northam', 'attribution' => '', 'quote' => 'Charlotte actually came with real leads and real roles, which was refreshing. She came prepared, knew what I was looking for and was a real pleasure to work with.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Sade Kendall', 'attribution' => '', 'quote' => 'Sade is a head above the rest of the recruiters. Always kept me informed, very proactive and a pleasure talking to. She went above and beyond to assist me.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Natasha Sykes', 'attribution' => '', 'quote' => 'Natasha has kept me up to date every step of the way, from asking if I would be interested in a job right through to interviews and asking how they went. Anybody lucky enough to deal with Natasha is 100% in the right hands.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Monira Akter', 'attribution' => '', 'quote' => 'Monira has been actively looking to place me from inception, which is unusual for an agency – so attentive.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Forough Rezaei', 'attribution' => '', 'quote' => 'Overall a great experience. Forough and Charlotte were both very attentive, knowledgeable and straight to the point. I will gladly work with them both again when the right opportunity surfaces.' ],
				],
				'audiences' => [
					'company' => [
						'headline' => "Your next project is out there. It just isn't advertised.",
						'body'     => "Whether you're scaling a data centre programme or shaping a skyline, one conversation opens doors that don't exist yet. Considered introductions, never resumes into the void – and discretion as standard.",
						'bullets'  => "Curated introductions, not mass outreach\nConfidential and NDA-grade search handled as standard\nProject team builds – contract and permanent\nLong-game relationships across the project pipeline",
						'cta'      => 'Scale your project team',
					],
					'candidate' => [
						'headline' => 'The best projects are rarely advertised.',
						'body'     => 'The best talent is rarely searching. Modulr exists in that gap – making precise, considered introductions rather than firing resumes into the void, and protecting reputations on every engagement.',
						'bullets'  => "Data centre, colocation, architecture and MEP opportunities\nExclusive, often NDA-protected briefs\nCareer trajectory advice across the full project lifecycle\nDiscreet, considered, never transactional",
						'cta'      => 'Find your next project',
					],
				],
				'about_hero' => [ 'pre' => 'The projects that define', 'accent' => 'a generation.', 'post' => 'Built by the right people.' ],
				// Round 4, item 13: client-approved positioning.
				'positioning' => 'Modulr connects the very best talent in Data Centres and Architecture with the companies building the future. Covering both the USA and Europe.',
				'what_we_do' => [
					'headline'   => 'Embedded in the projects that define a generation.',
					'paragraphs' => "Data centres, colocation and edge, architecture, MEP engineering and the full concept-to-commissioning lifecycle – this is where our network runs deepest. Every consultant works one part of the built environment, not the whole map.\n\nFor project directors, developers and practice principals, we operate as a discreet extension of the leadership team – considered introductions rather than resumes into the void, with NDA-grade discretion as standard.",
				],
				'proof' => [
					'Trusted by global operators, developers and celebrated practices',
					'Active networks across the USA and European markets',
					'NDA-grade discretion on every sensitive and pre-announcement search',
					'Inclusion work championing women in architecture and EDI in technical built-environment roles',
				],
				'team_focus' => 'Built environment search.',
				// Oct 2026 (Alex): subpage heroes drop the repeated globe for
				// built-environment imagery (the EPC construction-at-dusk asset).
				'subhero_image' => 'insight_epc',
				'subhero_alt'   => 'Tower cranes over a steel-frame construction site at dusk',
				/* ── Oct 2026 ModulR design round (Alex) ── */
				// Team strip: no paragraph, whole desk in one line (leaders
				// excluded – the strip is the working desk).
				'team_body'    => '',
				'team_limit'   => 12,
				'team_no_leaders' => 'yes',
				// About hero: US & EU box enlarged with a gradient CTA (same
				// treatment Alex approved on Edison).
				'about_hero_stat_cta' => [ 'text' => 'Our solutions', 'url' => '/clients' ],
				// Candidates page: the candidate process replaces the services
				// band (mirrors Edison), plus the two sector panels below.
				'candidate_process' => [
					[ 'title' => 'A confidential conversation', 'body' => 'Tell us where you want to go – practice, project type, comp, relocation. Nothing is shared without your sign-off.' ],
					[ 'title' => 'Matched to live briefs',      'body' => 'Your consultant maps your experience against live searches across architecture and data centres in the USA and Europe – not a database blast.' ],
					[ 'title' => 'Presented properly',          'body' => 'Your portfolio and project history go directly to the hiring leader, and we prep you on the practice, the panel and the programme before every stage.' ],
					[ 'title' => 'Offer to onboarding',         'body' => 'Comp guidance, negotiation and resignation support through to your first day – and we stay in touch well after it.' ],
				],
				// Candidates page: what we cover per sector, for candidates.
				'candidate_sectors' => [
					[ 'image' => 'insight_architecture', 'alt' => 'An architecture studio at work', 'title' => 'Architecture',
					  'body' => 'In architecture we work with registered architects, project architects, directors, principals and partners – and place them into celebrated design studios, multidisciplinary practices and developer in-house teams across the USA and Europe.' ],
					[ 'image' => 'modulr_datacentre', 'alt' => 'Data centre corridor lined with server racks', 'title' => 'Data Centres',
					  'body' => 'In data centres we work with construction directors, project leadership, commissioning managers and MEP engineers – and place them with hyperscalers, colocation operators, developers and the contractors delivering their programmes.' ],
				],
				'insights'   => [
					[ 'title' => 'Case study: scaling a hyperscale build team across three regions in 90 days',
					  'excerpt' => 'How we delivered a 14-strong project leadership team for a Tier-1 operator under NDA, on schedule, with zero attrition.',
					  'image' => 'insight_datacentre' ],
					[ 'title' => 'US Architecture & AOR market update – Q2 2026',
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
					// Edison Lux copy doc (Oct 2026): US & Europe, Senior
					// Leadership phrasing, "One market. Done properly." dropped.
					'sub'    => 'Edison Lux delivers talent solutions for the US & European energy sector, from control room operators to Senior Leadership responsible for billion-dollar assets.',
					// Round 5, item 1: dam (hydro) removed – Edison does zero
					// hydro. Pylons are the interim hero until the client's
					// licensed CCGT/turbine photography arrives (CLIENT-NEEDS.md).
					'image'  => 'edison_pylon',
					'alt'    => 'Silhouetted electricity transmission pylons at dawn above low mist',
					'scale'  => 1.18,
					'offset' => 0,
					// Oct 2026 (Alex): the HOME hero plays the pylon dawn film
					// (mirrored, muted, looping); subpage heroes keep the still.
					'video_mp4'  => 'edison_hero_mp4',
					'video_webm' => 'edison_hero_webm',
					'poster'     => 'edison_hero_poster',
				],
				// Edison Lux copy doc (Oct 2026), page 1: all four tiles reworded.
				'features' => [
					[ 'icon' => 'users',       'title' => "USA & Europe\nEnergy",        'body' => "It's our speciality. No client brief sits outside our expertise." ],
					[ 'icon' => 'star',        'title' => "Control Room To\nBoardroom",  'body' => 'From Operators, Engineers to all levels of Technical & Sales Leadership.' ],
					[ 'icon' => 'handshake',   'title' => "World-Class\nNPS",            'body' => 'We capture feedback from every candidate and client interaction to hold ourselves to the highest standard.' ],
					[ 'icon' => 'trending-up', 'title' => "100% Engaged\nSuccess",       'body' => 'We recognize speed and accuracy are critical. Our approach ensures you do not have to compromise one for the other.' ],
				],
				'about'        => [
					'headline' => 'One market, known completely.',
					// landing_body = the home-page about split (copy doc page 2);
					// mission/vision/purpose = the about-page compass (page 8).
					'landing_body' => "Precision matters, and so does speed. When a plant is short-staffed or a deadline is near, we never sacrifice one for the other.\n\nWe deliver talent to US & European power projects, critical power organizations, and commercial teams supplying the industry.",
					'mission'  => 'Deliver talent solutions with the precision the industry demands, combining speed and accuracy. When a plant is short-staffed, or a COD is at risk, you never have to choose between them.',
					'vision'   => 'To be the staffing partner the US & European energy sector turns to first.',
					'purpose'  => 'Named for the man who lit up the world, Edison Lux brings visibility to the talent that powers the industry, bridging the energy skills gap before it becomes a crisis.',
				],
				'about_image'     => 'edison_pylon',
				'about_image_alt' => 'Silhouetted electricity transmission pylons at sunset with a substation on the horizon',
				'stats' => [
					[ 'value' => '100%',                     'label' => 'Success rate on engaged search' ],
					[ 'value' => 'Control room → Boardroom', 'label' => 'Operator to C-suite coverage' ],
					[ 'value' => 'USA & Europe',             'label' => 'Energy focus, both markets' ],
				],
				// Round 5, item 3: sector IMAGES on the cards (icons kept) –
				// interim crops of existing licensed energy assets; the client's
				// per-sector photography is on the CLIENT-NEEDS list.
				// Edison Lux copy doc (Oct 2026), page 3: tile 1 becomes Power
				// Generation (OEM/rental/service angle); all six descriptions
				// replaced with the client's fuller wording.
				'specialisms' => [
					// Oct 2026 (Alex): pylon swapped for the turbine-machinery close-up
				// (gensets/turbines fit the Power Generation copy, same palette).
				[ 'icon' => 'flame',     'image' => 'insight_fluidpower', 'title' => 'Power Generation',                'description' => 'Generator OEMs, rental fleets and service providers, covering gensets, engines, turbines, switchgear and controls, from standard packages to custom builds.' ],
					[ 'icon' => 'wind',      'image' => 'spec_edison_02', 'title' => 'Combined Cycle & Gas Generation',     'description' => 'Our experience spans combined cycle, simple cycle, reciprocating engines, and gas compression, supporting outages, upgrades, and ongoing operations.' ],
					[ 'icon' => 'hard-hat',  'image' => 'spec_edison_03', 'title' => 'Renewables & Energy Transition',      'description' => 'Solar, wind, battery storage, hydrogen, and RNG, supporting construction, commissioning, and operational phases.' ],
					// Oct 2026 (Alex): turbine-fan crop swapped for the dusk plant/
				// chimney shot (reads as an EFW plant, keeps the dark-gold palette).
				[ 'icon' => 'wrench',    'image' => 'insight_power',  'title' => 'Biomass, EFW & Waste-to-Energy',     'description' => 'Biomass, EFW and coal, our teams bring knowledge of the fuel, the plant, and the regulatory environment.' ],
					[ 'icon' => 'atom',      'image' => 'spec_edison_05', 'title' => 'Nuclear',                             'description' => 'For new build, SMR, fusion and decommissioning. We provide expertise in engineering, operations, and maintenance.' ],
					[ 'icon' => 'briefcase', 'image' => 'spec_edison_06', 'title' => 'EPC – Construction & Commissioning',  'description' => 'Our support covers FEED, construction, commissioning, and project delivery.' ],
				],
				/* ── Round 10: curated real feedback (client's Canva doc, Sep
				      2026), lightly cleaned only (typos/spacing, meaning
				      unchanged). Reviewers anonymous per the new convention;
				      'consultant' = full roster name for the avatar row.
				      home_testimonials = mixed highlight set; full curated sets
				      in client_quotes / candidate_quotes. ── */
				'home_testimonials' => [
					[ 'tag' => 'client',    'consultant' => 'Dan Bisset', 'attribution' => '', 'quote' => 'Dan has outpaced all other recruiters in response and candidate quality.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Noah Ward', 'attribution' => '', 'quote' => "Noah is a stud. He has been very helpful, he's taken the time to understand what's important to me in my search for a new home. I look forward to continuing to work with him." ],
					[ 'tag' => 'client',    'consultant' => 'Joe Williams', 'attribution' => '', 'quote' => 'Joe is extremely driven, responsive, and helpful and has brought us great candidates that fit the requirements we are looking for. Happy to recommend him to any friend or colleague.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Milly Compton', 'attribution' => '', 'quote' => "Milly Compton's industry experience shines through, and her ability to make the process move quickly and efficiently made a world of difference in landing me the job I wanted." ],
					[ 'tag' => 'candidate', 'consultant' => 'Matthew Pearce', 'attribution' => '', 'quote' => "Knowledge of the client's needs and candidate competency and qualifications are extremely good. I had great feedback and consistent communication with Matthew." ],
				],
				// All six client quotes that exist.
				'client_quotes' => [
					[ 'tag' => 'client', 'consultant' => 'Joe Williams', 'attribution' => '', 'quote' => 'Joe is extremely driven, responsive, and helpful and has brought us great candidates that fit the requirements we are looking for. Happy to recommend him to any friend or colleague.' ],
					[ 'tag' => 'client', 'consultant' => 'Milly Compton', 'attribution' => '', 'quote' => 'Good communication, and Milly understands our needs and goes out of the way to get us good candidates time and time again.' ],
					[ 'tag' => 'client', 'consultant' => 'Dan Bisset', 'attribution' => '', 'quote' => 'Dan has outpaced all other recruiters in response and candidate quality.' ],
					[ 'tag' => 'client', 'consultant' => 'Milly Compton', 'attribution' => '', 'quote' => 'They find candidates that meet our job description in very short order.' ],
					[ 'tag' => 'client', 'consultant' => 'Matthew Pearce', 'attribution' => '', 'quote' => 'Matthew Pearce has been great with his communication throughout recruiting me and vetting me to potential employers.' ],
					[ 'tag' => 'client', 'consultant' => 'Milly Compton', 'attribution' => '', 'quote' => 'Great collaboration. Regular updates, and proactive when needed.' ],
				],
				'candidate_quotes' => [
					[ 'tag' => 'candidate', 'consultant' => 'Noah Ward', 'attribution' => '', 'quote' => "Noah is a stud. He has been very helpful, he's taken the time to understand what's important to me in my search for a new home. I look forward to continuing to work with him." ],
					[ 'tag' => 'candidate', 'consultant' => 'Joe Williams', 'attribution' => '', 'quote' => "I enjoyed working with Joe. He listened to what my needs were and was in contact with me throughout the process, always keeping me informed. It showed me that I wasn't just a number; he really cared about doing a good job and making sure both me and the hiring company were happy." ],
					[ 'tag' => 'candidate', 'consultant' => 'Milly Compton', 'attribution' => '', 'quote' => "Milly Compton's industry experience shines through, and her ability to make the process move quickly and efficiently made a world of difference in landing me the job I wanted." ],
					[ 'tag' => 'candidate', 'consultant' => 'Dan Bisset', 'attribution' => '', 'quote' => 'Dan Bisset is a great communicator; easy to speak to and enthusiastic in his role. I fully trust that he is working to find the best roles for his candidates and the best candidates for the roles.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Matthew Pearce', 'attribution' => '', 'quote' => "Knowledge of the client's needs and candidate competency and qualifications are extremely good. I had great feedback and consistent communication with Matthew." ],
					[ 'tag' => 'candidate', 'consultant' => 'Ollie Hesmondhalgh', 'attribution' => '', 'quote' => 'Professional, timely and effective communication, and an all-round friendly and natural character. Based on my experience, I would say that Oliver is a credit to your organisation.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Milly Compton', 'attribution' => '', 'quote' => 'I dealt with Milly and she was great throughout the whole process. She kept me informed, understood my reasons for wanting a specific role and did a thoroughly good job.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Noah Ward', 'attribution' => '', 'quote' => 'Noah worked well with me and made sure I had the tools necessary to perform well during my screening call.' ],
				],
				/* ── Edison Lux copy doc (Oct 2026), pages 4/10/12: the home
				      audience cards and the Clients/Candidates page heroes now
				      carry DIFFERENT copy – headline/body/bullets feed the home
				      cards, hero_* feed the page heroes. ── */
				'audiences' => [
					'company' => [
						'headline' => 'Zero compromise on quality.',
						'body'     => 'Whether you are filling a single critical role or building a team for a new asset, one conversation connects you to our network. VPs of engineering and plant owners rely on us to manage the search process.',
						'bullets'  => "Engaged search – our flagship model, 100% success rate\nContract staffing solutions\nTeam builds for new plants, projects and regions",
						'cta'      => 'Staff your asset',
						'hero_eyebrow'  => '',
						'hero_headline' => 'The right people, zero compromise.',
						'hero_body'     => "Whether you are staffing a single critical seat or building a team, one conversation is all it takes. We run the search, so you don't have to.\n\nWe offer tailored solutions based on your business needs, process and exact requirements.",
					],
					'candidate' => [
						'headline' => 'Power your career.',
						'body'     => '',
						'bullets'  => "Confidential, partnership-led conversations\nIndustry insights and advice from Specialists in your industry\nMaking the search and application process simple",
						'cta'      => 'Power your career',
						'hero_eyebrow'  => '',
						'hero_headline' => "Power your career.\nOn your terms.",
						'hero_body'     => "We work with major organizations within the USA energy sector, across utilities, independent power producers and OEMs.\n\nBuild your career with Edison Lux and connect with leading employers across the Energy industry, supported by specialists that understand your market.",
					],
				],
				// Copy doc page 7: eyebrow gone, "Lived, not learned" gone, the
				// hero body reworded (Alex asked for a rewording), and the 100%
				// stat card enlarged with a gradient CTA through to the services.
				// Oct 2026 round: European wording, shorter hero (pad band), stat
				// card beside the copy (CSS).
				'about_hero' => [ 'pre' => 'One sector.', 'accent' => "\nUS & European Energy.", 'post' => '', 'eyebrow' => '', 'pad' => 'band' ],
				'about_hero_body' => 'Energy is all we do – and why clients and candidates across the US and European markets make Edison Lux their first call.',
				'about_hero_stat_cta' => [ 'text' => 'Our solutions', 'url' => '/clients' ],
				// Copy doc page 7: "The story behind" becomes From vision to
				// reality, with the client's four paragraphs; the story stat rail
				// carries the three facts from the doc's side panel.
				'about_story' => [
					'eyebrow'  => '',
					'headline' => 'From vision to',
					'italic'   => 'reality.',
					// Oct 2026: "was formed in 2025" removed.
					'body'     => "Edison Lux is quickly becoming one of the leading Energy recruitment brands that operates across the US and Europe.\n\nEdison Lux has a deep understanding of the Energy industry and every Consultant is a genuine industry expert, specialising in their own niche.\n\nThrough client partnerships, thorough candidate qualification and innovative recruitment tools, we deliver the right talent at pace, without compromising on quality.\n\nWe're building a recruitment brand that understands the industries we serve, the challenges our clients face and the people who drive them forward.",
				],
				// Oct 2026: Alex's wording – sentence case, no shouty caps (CSS
				// drops the uppercase on these labels), grey card behind (CSS).
				'about_stats' => [
					[ 'value' => '4 service options available', 'label' => 'Including Verto Engage' ],
					[ 'value' => 'Control room → Boardroom',    'label' => 'From Operators and Engineers to all levels of Technical & Commercial leadership' ],
					[ 'value' => 'US & European Energy',        'label' => 'Critical Power, Combined Cycle & Gas, Renewables, Biomass & EFW, Nuclear and EPC' ],
				],
				'positioning' => 'Edison Lux delivers talent solutions for the USA and European energy sector – from control room operators and shift supervisors to the directors and C-suite leaders responsible for billion-dollar assets.',
				// Copy doc page 8: eyebrow gone, softer second paragraph.
				'what_we_do' => [
					'eyebrow'    => '',
					'headline'   => 'Embedded in the industries that keep the lights on.',
					'paragraphs' => "Critical power, combined-cycle and gas generation, renewables and energy transition, biomass and EFW, nuclear, and EPC construction are the areas of USA and European energy we know inside out. Every consultant is embedded in the sector with dedicated niches.\n\nFor plant owners, developers, EPCs, and VPs of engineering, we operate as an extension of your leadership team: discreet, accountable, and focused on long-term partnership.",
				],
				// Copy doc page 9: Proof points → Why clients choose Edison Lux.
				'proof_eyebrow' => '',
				'proof_heading' => 'Why clients choose Edison Lux.',
				'proof' => [
					'100% success using Verto Engage, backed by an enhanced candidate guarantee',
					'World class reviews from within the Energy sector',
					'Feedback captured from every candidate and client interaction',
					'USA & Europe – no client brief sits outside our knowledge',
				],
				// Copy doc page 4: Meet the desk → Your market, our Specialists.
				'team_heading' => 'Your market, our Specialists.',
				'team_body'    => '',
				// Oct 2026 (Alex): full Edison desk on the strip – 7 people pad
				// out to two rows (4 + 3) instead of the default four cards.
				'team_limit'   => 12,
				// Copy doc page 6: no strapline under the testimonials title.
				'testimonials_body' => '',
				// Copy doc page 10: Partnerships, not one offs (+ a different
				// image for variety – the EPC construction asset, not more pylons).
				'clients_split' => [
					'headline' => 'Partnerships, not one offs.',
					'body'     => "We believe great recruitment is about building lasting partnerships, not simply filling vacancies.\n\nWe connect businesses with exceptional technical and commercial talent, including the hard-to-find professionals who aren't actively looking.\n\nOur approach combines internal processes built over years of refinement with specialist market knowledge and the individuality that makes each of our consultants stand out.\n\nWhen we represent your business, we do it like it's ours.",
				],
				// Copy doc page 12: eyebrow gone, US spellings, merit-led body.
				'candidates_split' => [
					'eyebrow' => '',
					'body'    => "A job posted on LinkedIn gets hundreds of resumes. Working with Edison Lux means your experience is put front and center, presented to the hiring manager to review.\n\nYou get the best representation to the best jobs and employers within USA energy.",
				],
				/* ── About/Candidates round (Oct 2026, Alex) ── */
				// Mission/Vision/Purpose: no eyebrow, columns in line (CSS).
				'compass_eyebrow' => '',
				// Why clients choose: no numbers (brand dashes instead).
				'proof_numbered'  => 'no',
				// Candidates page: no section eyebrows.
				'reviews_eyebrow' => '',
				// Candidates page: the candidate PROCESS replaces the services
				// band (Alex asked what should go there instead of solutions).
				'candidate_process' => [
					[ 'title' => 'A confidential conversation', 'body' => 'Tell us where you want to go – role, market, comp, relocation. Nothing moves without your say-so, and nothing is shared without your sign-off.' ],
					[ 'title' => 'Matched to live roles',       'body' => 'Your Specialist maps your experience against live briefs across USA & European energy – utilities, IPPs, OEMs and EPCs – not a database blast.' ],
					[ 'title' => 'Sold before the interview',   'body' => 'We present your experience directly to the hiring manager, then prep you properly – the company, the panel, the plant and the likely questions.' ],
					[ 'title' => 'Offer to onboarding',         'body' => 'Comp guidance, negotiation, resignation and relocation support, through to your first day – and we stay in touch well after it.' ],
				],
				'team_focus' => 'US power & energy search.',
				'insights'   => [
					[ 'title' => "The US Energy Skills Gap: who's hiring, who's leaving, and what it costs",
					  'excerpt' => "America is building more generating capacity than at any point in a generation. The talent pipeline isn't keeping up – here's what plant owners and EPCs need to know in 2026.",
					  'image' => 'insight_power' ],
					[ 'title' => 'CCGT Shift Supervisor Salary Guide – US, 2026',
					  'excerpt' => 'Base, shift premium, total comp and relocation packages benchmarked across PJM, ERCOT and CAISO.',
					  'image' => 'insight_power' ],
					[ 'title' => 'Reducing time-to-COD on large-scale EPC projects',
					  'excerpt' => "Why the bottleneck is rarely concrete, steel or turbines – and almost always the people you can't find fast enough.",
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
					// Round 4, item 12: US + Europe.
					'sub'    => 'Vertek recruits technical sales, service and engineering professionals for the manufacturers and distributors that keep industry moving – across the USA and Europe. Every consultant owns one product area.',
					'image'  => 'vertek_hero',
					'alt'    => 'Automated assembly machinery in red and blue machine light',
					'scale'  => 1.18,
					'offset' => 0,
					// Oct 2026: looping hero film, action bottom of frame.
					'video_mp4'       => 'vertek_hero_mp4',
					'video_webm'      => 'vertek_hero_webm',
					'poster'          => 'vertek_hero_poster',
					'object_position' => 'center bottom',
				],
				'features' => [
					[ 'icon' => 'crosshair',   'title' => 'Specialists, Not Generalists', 'body' => 'Every consultant specialises in a specific product area – Fluid Power, HVAC, Defense & Advanced Manufacturing, Industrial Automation, CNC & Metalworking and Compressors.' ],
					[ 'icon' => 'bar-chart-3', 'title' => 'Verto Engage',        'body' => 'Our flagship model with a 100% success rate, an extended candidate guarantee, and the initial fee taken off on a successful hire.' ],
					[ 'icon' => 'trending-up', 'title' => 'Sales Floor To Shop Floor', 'body' => 'Regional and national sales & service talent, plus the onsite manufacturing, engineering and internal sales teams who deliver.' ],
					[ 'icon' => 'handshake',   'title' => 'World-Class NPS',     'body' => 'We capture feedback from every candidate and client interaction to hold ourselves to the highest standard.' ],
				],
				'about'        => [
					'headline' => 'One product area, per consultant. Every time.',
					'mission'  => 'Deliver the right hire, first time – combining a deep specialist network with a process refined over years so speed never comes at the cost of quality.',
					'vision'   => 'To be the firm every VP of Sales, MD and founder in technical sales and engineering reaches out to first – on both sides of the Atlantic.',
					'purpose'  => "Product knowledge can't be faked. Neither can ours. We exist so that manufacturers and distributors never have to explain their own product to their recruiter – and so that engineers get sold on their merits.",
				],
				// No images.landingAbout override in the prototype – the hero
				// image doubles as the landing about visual (getBrandImage fallback).
				'about_image'     => 'vertek_hero',
				'about_image_alt' => 'Cable-stayed bridge at night with crimson motion light trails',
				'stats' => [
					[ 'value' => 'USA',     'label' => 'Coverage coast to coast' ],
					[ 'value' => '100%',    'label' => 'Success rate on Verto Engage' ],
					[ 'value' => '94%',     'label' => 'Of clients partner with us again' ],
				],
				// Round 4, item 12: the client's six named sectors.
				'specialisms' => [
					[ 'icon' => 'gauge',       'title' => 'Fluid Power',                      'description' => 'Pumps, seals, valves and hydraulics – plus pneumatics, actuation, filtration and flow control.' ],
					[ 'icon' => 'cog',         'title' => 'HVAC',                             'description' => 'Air handlers, ventilation, refrigeration, heat pumps, boilers, plumbing and aftermarket.' ],
					[ 'icon' => 'line-chart',  'title' => 'Defense & Advanced Manufacturing', 'description' => 'Defence, aerospace, space, semiconductor and robotics – ITAR and clearance handled.' ],
					[ 'icon' => 'cpu',         'title' => 'Industrial Automation',            'description' => 'Sensors, PLCs, HMI, connectors, automated machinery and conveyors.' ],
					[ 'icon' => 'factory',     'title' => 'CNC & Metalworking',               'description' => 'Cutting tools, workholding, toolholding, metrology, CMM and metalworking.' ],
					[ 'icon' => 'thermometer', 'title' => 'Compressors',                      'description' => 'Air and gas compression – rotary screw, centrifugal and reciprocating – sales, service and engineering.' ],
				],
				/* ── Round 10: curated real feedback (client's Canva doc, Sep
				      2026), lightly cleaned only (typos/spacing, meaning
				      unchanged). Reviewers anonymous per the new convention;
				      'consultant' = full roster name for the avatar row (Saman
				      Akbari would render the initials pill – no photo by client
				      instruction). home_testimonials = mixed highlight set; full
				      curated sets in client_quotes / candidate_quotes. ── */
				'home_testimonials' => [
					[ 'tag' => 'client',    'consultant' => 'Ben Cranston', 'attribution' => '', 'quote' => "Ben went above and beyond hunting down candidates and setting up interviews. Without his detailed knowledge of the Austin talent landscape we wouldn't have been able to make several key hires." ],
					[ 'tag' => 'candidate', 'consultant' => 'Harvey Earl', 'attribution' => '', 'quote' => 'Harvey is down to earth, transparent, and makes this whole process feel human. Never once did I feel as if I was just another resume he was presenting to a client. He got to know me, kept me in the loop, and made it feel like he was working for me just as much. He was in my corner.' ],
					[ 'tag' => 'client',    'consultant' => 'Gary Hunt', 'attribution' => '', 'quote' => 'Gary understands our business needs and only sent through candidates with relevant experience for the role advertised. All candidates were of a very good standard and resulted in a successful job offer.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Olivia Pinhorne', 'attribution' => '', 'quote' => 'Where some people like to really dress up a role, Olivia gave me the information I actually needed to decide if the role was for me. Come interview stage there were no surprises – the role aligned perfectly with my expectations.' ],
					[ 'tag' => 'client',    'consultant' => 'Alex Wright', 'attribution' => '', 'quote' => "Alex was a true professional to work with. We were provided with a consistent stream of quality leads without delays. When one candidate didn't work out, the next two or three were in our inbox the next morning." ],
				],
				'client_quotes' => [
					[ 'tag' => 'client', 'consultant' => 'Gary Hunt', 'attribution' => '', 'quote' => 'Gary understands our business needs and only sent through candidates with relevant experience for the role advertised. All candidates were of a very good standard and resulted in a successful job offer. Timely communication throughout ensured a smooth process from start to finish.' ],
					[ 'tag' => 'client', 'consultant' => 'Ben Cranston', 'attribution' => '', 'quote' => "Ben went above and beyond hunting down candidates and setting up interviews. Without his detailed knowledge of the Austin talent landscape we wouldn't have been able to make several key hires." ],
					[ 'tag' => 'client', 'consultant' => 'Sam Parnell', 'attribution' => '', 'quote' => "Sam has been fantastic across the board. We've filled two roles with him since April and are hoping to fill a third in the next few weeks. He's super responsive and has been able to adjust the search as needed. I've already shared his information with a colleague." ],
					[ 'tag' => 'client', 'consultant' => 'Olivia Pinhorne', 'attribution' => '', 'quote' => "Olivia sorted out some excellent resumes, perfectly matched to the role. She wasn't pushy or irritating like some other agencies. Available when you need her, very pleasant to deal with." ],
					[ 'tag' => 'client', 'consultant' => 'Alex Wright', 'attribution' => '', 'quote' => "Alex was a true professional to work with. We were provided with a consistent stream of quality leads without delays. When one candidate didn't work out, the next two or three were in our inbox the next morning." ],
					[ 'tag' => 'client', 'consultant' => 'Frank Warner', 'attribution' => '', 'quote' => 'Quite simply, it was the diligent way that Frank listened to our needs and sought out candidates that matched our profile. He made himself available, shortlisted and presented good candidates, and has been easy to work with to the point where we have developed a relationship of trust.' ],
					[ 'tag' => 'client', 'consultant' => 'Lewis Mason', 'attribution' => '', 'quote' => 'We had not worked with Verto before, but Lewis has provided a great service and managed our expectations in a courteous and professional manner. He fulfilled the brief, and we have now secured three candidates – a successful first year working together.' ],
					[ 'tag' => 'client', 'consultant' => 'Ben Cranston', 'attribution' => '', 'quote' => 'Ben has a great ability to turn a job description into qualified candidates. His attention to detail, responsiveness and energy level make hiring with him very efficient and effective.' ],
				],
				'candidate_quotes' => [
					[ 'tag' => 'candidate', 'consultant' => 'Harvey Earl', 'attribution' => '', 'quote' => 'Harvey is down to earth, transparent, and makes this whole process feel human. Never once did I feel as if I was just another resume he was presenting to a client. He got to know me, kept me in the loop, and made it feel like he was working for me just as much. He was in my corner.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Olivia Pinhorne', 'attribution' => '', 'quote' => 'Where some people like to really dress up a role, Olivia gave me the information I actually needed to decide if the role was for me. Come interview stage there were no surprises – the role aligned perfectly with my expectations and I was able to prepare correctly for the questions asked.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Lewis Mason', 'attribution' => '', 'quote' => 'The application was made by Verto to the company in question, and within two weeks I am very pleased to say I got the job. Lewis kept me informed throughout and handled the discussions with the recruiting company during the interview process.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Gary Hunt', 'attribution' => '', 'quote' => 'Gary has been excellent to work with. He made the effort to understand my situation and kept me well informed on how I could perform my best at every stage, so the process had no surprises.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Lewis Sullivan', 'attribution' => '', 'quote' => "Lewis reached out right when I was looking to make a change and it honestly couldn't have been better timing. He understood what I was aiming for and didn't waste my time with roles that didn't fit. Really appreciate the support he gave throughout." ],
					[ 'tag' => 'candidate', 'consultant' => 'Frank Warner', 'attribution' => '', 'quote' => "Frank has been an absolute pleasure to work with. He's been extremely attentive, there when I need him, and followed up on every request I made. He's a top-notch recruiter." ],
					[ 'tag' => 'candidate', 'consultant' => 'Rex Reavley', 'attribution' => '', 'quote' => 'The personal interaction with Rex was top class. No chasing was required. He was absolutely on the ball.' ],
					[ 'tag' => 'candidate', 'consultant' => "Harley O'Connell", 'attribution' => '', 'quote' => 'Harley was excellent – he called before meetings and after to make sure all was OK. Honestly, Harley is the best recruitment consultant I have worked with in a long time.' ],
					[ 'tag' => 'candidate', 'consultant' => 'Ben Cranston', 'attribution' => '', 'quote' => 'Ben was very professional in all the interactions I had with him. He was thorough in explaining the process, providing all of the relevant information and handling the scheduling.' ],
				],
				'audiences' => [
					'company' => [
						'headline' => 'Straightforward. No overpromising. Just the right hire.',
						'body'     => "Tell us the product, the patch and the profile. We'll tell you honestly whether we can deliver – and then we will. Every consultant specialises by product because our clients and candidates don't generalise either.",
						'bullets'  => "Verto Engage – our flagship model, 100% success rate\nDirect hire through deep product-specialist networks\nTeam builds – land one hire, then scale the function\nFrequent updates, structured briefings, no surprises",
						'cta'      => 'Build your team',
					],
					'candidate' => [
						'headline' => 'Options, not applications.',
						'body'     => 'Put a role on a job board and it gets hundreds of resumes. Work with us and it works the other way round – we put you and your experience front and centre, and we sell the opportunity before you sit in an interview.',
						'bullets'  => "US and European roles across the product landscape\nTotal comp, equity, progression and work-life on the table\nTime-served engineers and product specialists – spoken to as equals\nHonest feedback. No oversell. No fluff.",
						'cta'      => 'See live roles',
					],
				],
				'about_hero' => [ 'pre' => 'Product knowledge,', 'accent' => 'one desk at a time.', 'post' => 'Never generalist.' ],
				'positioning' => "Vertek recruits technical sales, service and engineering professionals for the manufacturers and distributors that keep industry moving – across the USA and Europe. Every consultant owns one product area. That's why it works.",
				'what_we_do' => [
					'headline'   => 'Embedded in the industries that build the world.',
					'paragraphs' => "Fluid power, HVAC, rotating equipment, industrial automation and US advanced manufacturing – these are the industries we know inside out. Every consultant specialises in a product area and stays close enough to add genuine insight to every conversation.\n\nFor VPs of Sales, Managing Directors and Founders, we operate as an extension of the leadership team – discreet, accountable and never transactional.",
				],
				'proof' => [
					'100% success rate on Verto Engage',
					'94% of clients return for a second hire',
					'Deep product-specialist networks across sales, service and engineering',
					'Feedback captured from every candidate and client interaction – then acted on',
				],
				'team_focus' => 'Technical sales & engineering search.',
				'insights'   => [
					[ 'title' => 'Fluid Power Sales Engineer Salary Guide – UK & US, 2026',
					  'excerpt' => 'Base, OTE, equity and benefit benchmarks across hydraulics, pneumatics and compressed air distributors.',
					  'image' => 'insight_fluidpower' ],
					[ 'title' => 'Advanced manufacturing talent trends: defence, semis and robotics',
					  'excerpt' => 'Where the next wave of US engineering and commercial talent will come from – and what founders are paying to secure it.',
					  'image' => 'insight_manufacturing' ],
					[ 'title' => 'The HVAC aftermarket hiring playbook',
					  'excerpt' => 'Service managers, aftermarket sales leads and field engineers – building the commercial muscle behind the install base.',
					  'image' => 'insight_hvac' ],
				],
				/* ── Vertek-only data (the prototype renders these sections
				      conditionally; modulr / edison-lux carry none of them) ── */
				'pillars' => [
					[ 'title' => 'Product-owned desks',
					  'body'  => "Every Vertek consultant owns a product area – fluid power, HVAC, rotating equipment, automation, advanced manufacturing. We don't generalise across engineering because our clients and candidates don't. Anyone can post a job spec; very few can tell the difference between a fluid power sales engineer with real product experience and one who just learned the words." ],
					[ 'title' => 'Partnership, not transaction',
					  'body'  => "94% of our clients come back. The other 6% haven't had a second role yet. We earn that by understanding the business properly, representing it well in the market and operating as an extension of the commercial team – not a vendor." ],
					[ 'title' => 'Process over chance',
					  'body'  => "Structured briefings, frequent updates at every stage and a methodology built over years to get it right first time. Recruitment isn't luck – and our 100% success rate on Verto Engage proves it." ],
				],
				'values' => [
					[ 'title' => 'Straightforward. No overpromising.',
					  'body'  => "We say what we mean and mean what we say. Candidates are sold on their merits, feedback is honest, and we never promise what we can't deliver." ],
					[ 'title' => 'Process over chance.',
					  'body'  => "Great recruitment isn't luck. Our methodology has been built over years to get it right first time – frequent updates, thorough briefings, structure that removes failure at every stage." ],
					[ 'title' => 'An extension of your team.',
					  'body'  => '94% of our clients work with us again. We understand the business properly, represent it well and build relationships that outlast a single hire.' ],
					[ 'title' => 'High-conviction introductions.',
					  'body'  => "We sell the opportunity as hard as we'd want someone to sell ours. The right candidates come energised, not just informed." ],
					[ 'title' => 'Product knowledge, non-negotiable.',
					  'body'  => "Every consultant specialises in a product area. We don't generalise across engineering because our clients and candidates don't – and neither should we." ],
				],
				'journey' => [
					[ 'year' => '2011', 'title' => 'Founded in technical sales – the roots of the Verto Group' ],
					[ 'year' => '2020', 'title' => 'Vertek brand established for sales, service and engineering search' ],
					[ 'year' => '2022', 'title' => 'US expansion into fluid power, HVAC and rotating equipment' ],
					[ 'year' => '2024', 'title' => 'Advanced manufacturing practice launched – defence, aerospace, semiconductor, robotics' ],
					[ 'year' => '2026', 'title' => 'Micro-niche desks across the USA and Europe – and counting' ],
				],
				// Round 10: the prototype's invented 'testimonials' array is GONE –
				// the real curated client_quotes / candidate_quotes above feed the
				// Clients and Candidates page bands for every brand now.
				'process' => [
					[ 'title' => 'Discover', 'body' => 'We sit down with the business, the product line and the territory – not just the job spec. Success at 90 days, 6 months and 12 months is mapped before we name a single candidate.' ],
					[ 'title' => 'Map',      'body' => 'We map the entire competitor, manufacturer and distributor landscape on patch. Time-served engineers, sales specialists, service leaders – visible and invisible.' ],
					[ 'title' => 'Engage',   'body' => 'Every approach is briefed properly – your product, your culture, the opportunity. No mass outreach. No resumes into the void.' ],
					[ 'title' => 'Deliver',  'body' => "Structured shortlist, candidate context resumes can't capture, offer management and post-placement check-ins. Frequent updates throughout. No surprises." ],
				],
				'case_study' => [
					'client'    => 'Global Fluid Power OEM',
					'sector'    => 'Hydraulics & motion control',
					'challenge' => 'A European fluid power manufacturer needed to build a US commercial team from scratch – Regional Sales Director, three product-specialist sales engineers and a service manager – in a market where time-served hydraulics talent is notoriously hard to find. Two previous contingent partners had stalled out.',
					'solution'  => "Vertek mapped every relevant hydraulics, motion control and pneumatics OEM and distributor across the target US regions. We worked exclusively on engaged terms, ran weekly market read-outs and represented the client's story end-to-end – including total comp, equity and relocation context the client had previously underplayed.",
					'result'    => "All five hires made within 10 months. 100% retention at 18 months. Vertek has since been engaged on a further 14 mandates including a VP of Sales appointment and the company's first US service leadership team.",
				],
				'candidate_process' => [
					[ 'title' => 'Confidential conversation', 'body' => 'We start by understanding what you actually want next – product area, market, total comp, relocation, work-life. No oversell. We only bring you roles that genuinely fit.' ],
					[ 'title' => 'Briefed representation',    'body' => 'We sell your experience before the first interview. Hiring managers see your context, your patch and your product knowledge – not just a resume.' ],
					[ 'title' => 'Interview preparation',     'body' => "Full briefing on the company, the panel, the product line and the likely lines of questioning. We've usually placed there before." ],
					[ 'title' => 'Offer & beyond',            'body' => "Honest comp guidance, equity context for US advanced manufacturing, counter-offer support and check-ins long after you've started." ],
				],
				// Round 4, item 12: mirrors the client's six sectors + the two
				// cross-cutting desks.
				'sectors_served' => [
					'Fluid power – pumps, seals, valves & hydraulics',
					'HVAC',
					'Defense & advanced manufacturing',
					'Industrial automation',
					'CNC & metalworking',
					'Compressors',
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
				'post_content'  => $post['excerpt'] . "\n\n⚠️ Placeholder insight from the prototype – replace with the real article.",
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

	/**
	 * The four Verto engagement models – Engage (flagship) / Exclusive /
	 * Contingent / Contract – as the full row4 process-rail section
	 * ("Four ways to work with us."). v0.22.4 (client): simplified – no
	 * "One standard." second line, no right-hand intro paragraph, plain
	 * (dash-less) eyebrow, and the cards drop their 01-04 numbers (the
	 * row4 render handles the card side). Shared by the brand Clients
	 * page, every brand HOME (replacing the round-6 compact services
	 * band there) and the GROUP home (brand-neutral: the group's gold
	 * --brand token drives the accents; light 'default' background).
	 * No fees or percentages on the site (CLIENT-NEEDS.md).
	 */
	private static function four_models_section( string $bg = 'muted' ): array {
		return self::section( [ self::widget( 'verto-process-rail', [
			'layout'    => 'row4',
			'bg'        => $bg,
			'eyebrow'   => 'Hiring solutions',
			'heading'   => "Four ways to work with\u{00A0}us.",
			'side_text' => '',
			'items'     => [
				[ '_id' => self::eid(), 'title' => 'Verto Engage', 'kicker' => 'A true partnership', 'badge' => 'Our flagship',
				  'body' => 'Engage puts your business in the spotlight: co-branded adverts promoted to our 35,000 LinkedIn followers, a fully structured and managed recruitment process tailored to your systems, and a six-month candidate guarantee with free replacement.',
				  'bullets' => "Maximum visibility\nBetter fill rates\nFewer offer rejections" ],
				[ '_id' => self::eid(), 'title' => 'Exclusive', 'kicker' => 'One search, one partner',
				  'body' => 'We commit senior resource to an exclusive brief and you get a faster, deeper shortlist without managing multiple agencies.',
				  'bullets' => "Senior resource on the brief\nFaster, deeper shortlists\nOne process to manage" ],
				[ '_id' => self::eid(), 'title' => 'Contingent', 'kicker' => 'The classic model',
				  'body' => 'We search, you interview, and a fee applies only when you hire. Backed by a free-replacement guarantee.',
				  'bullets' => "You only pay on a hire\nFree-replacement guarantee\nSimple to switch on" ],
				[ '_id' => self::eid(), 'title' => 'Contract', 'kicker' => 'Interim & project specialists',
				  'body' => 'Contractors and interim specialists, compliantly engaged and ready fast, for projects, cover and peaks in demand.',
				  'bullets' => "Compliantly engaged\nReady fast\nProjects, cover and peaks" ],
			],
		] ) ], 'verto-bs' );
	}

	/** Build the standalone brand site: Home, About, Clients, Candidates, Insights. */
	private static function create_brand_site_pages( string $brand, array $media ): void {
		$c = self::brand_content( $brand );
		if ( ! $c ) return;

		$name       = $c['name'];
		// Round 9, item 1: compact vacancies board – rows only (no filter
		// rail), scoped to this brand's seeded verto_job posts, rows linking
		// to the job detail pages with a working Apply pill. Rendered on HOME
		// (after the testimonials) and CANDIDATES (after the hero/split).
		$vacancies = self::section( [ self::widget( 'verto-jobs-board', [
			'layout'  => 'compact',
			'brand'   => $brand,
			'heading' => "Open roles at $name.",
			'intro'   => "Seats on the $name desk itself – live now, with more to come as the team grows.",
		] ) ], 'verto-bs vbs-vacancies' );
		// Round 9, item 2 → round 10: candidate reviews – now the full curated
		// CANDIDATE set per brand (real quotes, consultant avatar rows; the
		// headshots resolve from the verto_team posts at render time).
		$cand_quotes = $c['candidate_quotes'] ?? [];
		$reviews     = ! $cand_quotes ? null : self::section( [ self::widget( 'verto-quote-band', [
			'quotes_style'     => 'light',
			'quotes_layout'    => 'carousel',
			'pad'              => 'band',
			'image'            => [],
			'eyebrow'          => $c['reviews_eyebrow'] ?? 'Candidate reviews',
			'eyebrow_style'    => 'brand',
			'heading_pre'      => 'What candidates say.',
			'heading_accent'   => '',
			'heading_post'     => '',
			'body'             => 'Real feedback from the people we place – gathered from every engagement.',
			'stat_value'       => '',
			'stat_label'       => '',
			'consultant_brand' => $name,
			'quotes'           => array_map( fn( $q ) => [ '_id' => self::eid() ] + $q, $cand_quotes ),
		] ) ], 'verto-bs' );
		$logo_key   = [ 'modulr' => 'logo_modulr_png', 'vertek' => 'logo_vertek', 'edison-lux' => 'logo_edison' ][ $brand ] ?? '';
		$stats      = array_map( fn( $st ) => [ '_id' => self::eid() ] + $st, $c['stats'] );
		$team_strip = self::widget( 'verto-team-grid', [
			'mode'       => 'strip',
			'brand'      => $brand,
			'eyebrow'    => 'The team',
			// Edison copy doc (Oct 2026), page 4: "Your market, our Specialists",
			// nothing else – per-brand overrides, other brands keep the default.
			'heading'    => $c['team_heading'] ?? "Meet the $name desk.",
			'body'       => $c['team_body'] ?? "Operators, engineers and market specialists. The people you'll actually talk to when you engage $name.",
			'focus_text' => $c['team_focus'],
			// Oct 2026 (Alex): Edison shows the WHOLE desk – two rows of cards
			// instead of the default four. ModulR: whole desk in ONE line,
			// leadership excluded from the strip.
			'limit'      => $c['team_limit'] ?? 4,
			'no_leaders' => $c['team_no_leaders'] ?? '',
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
					'editor'       => '<div class="card-surface vbs-form-card">' . self::cf7_form_html() . '</div>',
				] ),
			], 'verto-bs vbs-contact', 45, [ '_element_id' => 'contact' ] );
		};

		/* ── HOME ── */
		$home = [
			self::section( [ self::widget( 'verto-brand-hero', [
				'line1'           => $c['hero']['line1'],
				'line2'           => $c['hero']['line2'],
				'sub'             => $c['hero']['sub'],
				// Oct 2026: optional looping hero film (Edison) – the poster
				// frame doubles as the fallback image.
				'image'           => self::media_setting( $media, $c['hero']['poster'] ?? $c['hero']['image'] ),
				'video_mp4'       => isset( $c['hero']['video_mp4'] ) ? self::media_setting( $media, $c['hero']['video_mp4'] ) : [],
				'video_webm'      => isset( $c['hero']['video_webm'] ) ? self::media_setting( $media, $c['hero']['video_webm'] ) : [],
				'object_position' => $c['hero']['object_position'] ?? 'center 35%',
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
			// Round 5, item 2 (Edison): symmetric split + "Talk to us" CTA in
			// the Edison gradient, straight to the clients contact form.
			// Round 9, item 21 (Vertek): the home about split no longer
			// repeats the hero's bridge image – the advanced-manufacturing
			// asset breaks the run of dark sections instead.
			self::section( [ self::widget( 'verto-about-split', array_merge( [
				'variant'   => 'landing',
				'eyebrow'   => "About $name",
				'headline'  => $c['about']['headline'],
				// Edison copy doc page 2: the landing split gets its own body,
				// decoupled from the Mission column on the about page.
				'body'      => $c['about']['landing_body'] ?? $c['about']['mission'],
				'cta_text'  => 'Learn more about us',
				'cta_link'  => [ 'url' => '/about' ],
				'image'     => self::media_setting( $media, 'vertek' === $brand ? 'insight_manufacturing' : $c['about_image'] ),
				'image_alt' => 'vertek' === $brand ? 'Precision machinery on an advanced manufacturing line' : $c['about_image_alt'],
				'stats'     => $stats,
			], 'edison-lux' === $brand ? [
				'cta_text'  => 'Talk to us',
				'cta_link'  => [ 'url' => '/clients#contact' ],
				'cta_style' => 'gradient',
			] : [] ) ) ], 'verto-bs' ),
			// Round 5, item 3: per-item card images (interim licensed crops).
			self::section( [ self::widget( 'verto-specialisms', [
				'items' => array_map( fn( $sp ) => [
					'_id'   => self::eid(),
					'image' => isset( $sp['image'] ) ? self::media_setting( $media, $sp['image'] ) : [],
				] + $sp, $c['specialisms'] ),
			] ) ], 'verto-bs' ),
		];
		// Round 5, item 4 (Edison) → Edison copy doc (Oct 2026), page 5:
		// "Two desks. One market." becomes "One industry. Energy." – the
		// Sales & Service desk and the On site staffing desk, side by side.
		if ( 'edison-lux' === $brand ) {
			$home[] = self::section( [
				self::widget( 'verto-section-intro', [
					'eyebrow' => 'Inside Edison Lux',
					'lines'   => [
						[ '_id' => self::eid(), 'line' => 'One industry.' ],
						[ '_id' => self::eid(), 'line' => 'Energy.' ],
					],
					'body'    => "Our Energy team supports the entire lifecycle, from commercial and winning the work, to the technical staff who deliver and manage the asset.\n\nYou'll work closely with a Specialist who's dedicated to supporting the jobs you're hiring for. Whether that's Sales, Operators, Plant Managers, Technicians, Engineers or contract staff.",
				] ),
				self::widget( 'verto-audience-cards', [ 'items' => [
					[ '_id' => self::eid(), 'style' => 'ink', 'kicker' => 'Sales & Service',
					  'headline' => 'The people who win the work.',
					  'body'     => 'We provide sales & service talent across power generation and critical power.',
					  'bullets'  => "VP sales\nRegional management\nOutside sales\nService techs & Engineers",
					  'cta_text' => 'Explore sales roles', 'cta_link' => [ 'url' => '/candidates' ] ],
					[ '_id' => self::eid(), 'style' => 'surface', 'kicker' => 'On site staffing',
					  'headline' => 'The people who keep it running.',
					  'body'     => 'Our operations and engineering talent supports the assets themselves, from control room staff to plant leadership.',
					  'bullets'  => "Control room operators & Shift Supervisors\nService Engineering\nIC&E\nO&M, commissioning & project management\nPlant management & engineering leadership\nContract staff",
					  'cta_text' => 'Explore technical roles', 'cta_link' => [ 'url' => '/candidates' ] ],
				] ] ),
			], 'verto-bs vbs-desks' );
		}
		// Round 5, items 6+7 (+ round 6): the "Trusted by" logo marquee is gone
		// everywhere; a testimonials carousel takes its slot. Round 10: every
		// brand now carries REAL quotes – home_testimonials is the curated
		// mixed (client + candidate) highlight set, with consultant avatars.
		$home = array_merge( $home, [
			self::section( [ self::widget( 'verto-quote-band', [
				'quotes_style'     => 'light',
				'quotes_layout'    => 'carousel',
				'pad'              => 'band',
				'image'            => [],
				'eyebrow'          => 'Testimonials',
				'eyebrow_style'    => 'brand',
				'heading_pre'      => 'What clients and candidates say.',
				'heading_accent'   => '',
				'heading_post'     => '',
				// Edison copy doc page 6: no strapline under the title.
				'body'             => $c['testimonials_body'] ?? 'Real feedback from the clients we staff for and the people we place – gathered from every engagement.',
				'stat_value'       => '',
				'stat_label'       => '',
				'consultant_brand' => $name,
				'quotes'           => array_map( fn( $q ) => [ '_id' => self::eid() ] + $q, $c['home_testimonials'] ?? [] ),
			] ) ], 'verto-bs' ),
			// Round 9, item 1: brand vacancies – directly after the
			// testimonials carousel.
			$vacancies,
			// v0.22.4 (client): the HOME page now carries the FULL four-models
			// section (replacing the round-6 compact services band here –
			// About / Candidates / Insights keep the compact band).
			self::four_models_section(),
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
		] );
		$home_id = self::upsert_page( 'home', 'Home', $home );

		/* ── ABOUT ── */
		$about_hero_body = $c['about_hero_body'] ?? ( $c['about']['headline'] . " – and it's how we've built $name into the firm clients and candidates in " . $c['focus_lower'] . ' reach out to first.' );
		// Edison copy doc page 7: the 100% stat card grows and carries a
		// gradient CTA through to the services; story split becomes the
		// client's "From vision to reality" copy with its own fact rail.
		$story = $c['about_story'] ?? [];
		$about = [
			self::section( [ self::widget( 'verto-quote-band', [
				'image'          => self::media_setting( $media, $c['subhero_image'] ?? $c['hero']['image'] ),
				'image_alt'      => $c['subhero_alt'] ?? $c['hero']['alt'],
				'overlay'        => 'hero-left',
				'pad'            => $c['about_hero']['pad'] ?? 'hero',
				'parallax_speed' => 0.3,
				'eyebrow'        => $c['about_hero']['eyebrow'] ?? "About $name",
				'eyebrow_style'  => 'brand',
				'heading_pre'    => $c['about_hero']['pre'],
				'heading_accent' => $c['about_hero']['accent'],
				'heading_post'   => $c['about_hero']['post'],
				'body'           => $about_hero_body,
				'stat_value'     => $c['stats'][0]['value'],
				'stat_label'     => $c['stats'][0]['label'],
				'stat_cta_text'  => $c['about_hero_stat_cta']['text'] ?? '',
				'stat_cta_link'  => [ 'url' => $c['about_hero_stat_cta']['url'] ?? '' ],
			] ) ], 'verto-bs' ),
			self::section( [ self::widget( 'verto-about-split', [
				'variant'         => 'story',
				'eyebrow'         => $story['eyebrow'] ?? "About $name",
				'headline'        => $story['headline'] ?? 'The story behind',
				'headline_italic' => $story['italic'] ?? "$name.",
				'body'            => $story['body'] ?? ( $c['positioning'] . "\n\nWe started in technical sales in 2011 – the roots of the Verto Group. $name is the brand built specifically for the part of the market we know best: the engineers, operators and commercial leaders our sector runs on." ),
				'logo'            => self::media_setting( $media, $logo_key ),
				'stats'           => isset( $c['about_stats'] ) ? array_map( fn( $st ) => [ '_id' => self::eid() ] + $st, $c['about_stats'] ) : $stats,
				'cta_text'        => '',
			] ) ], 'verto-bs' ),
		];
		// Pillars – staggered 3-up cards (prototype: only brands with `pillars`;
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
		// Values – sticky-intro accordion (Vertek-only in the prototype data).
		if ( ! empty( $c['values'] ) ) {
			$about[] = self::section( [ self::widget( 'verto-values-accordion', [
				'items' => array_map( fn( $v ) => [ '_id' => self::eid() ] + $v, $c['values'] ),
			] ) ], 'verto-bs' );
		}
		// Round 9, item 13: Edison's "What we do today" drops the stock
		// clouds/office photo for sector (plant) imagery from the licensed set.
		// Oct 2026 (Alex, ModulR round): ModulR's clouds photo → data centre.
		$wwd_img = [ 'edison-lux' => 'spec_edison_02', 'modulr' => 'modulr_datacentre' ][ $brand ] ?? 'about_image';
		$wwd_alt = [ 'edison-lux' => 'A combined-cycle gas plant – the market Edison Lux staffs', 'modulr' => 'Data centre corridor lined with server racks' ][ $brand ] ?? 'A specialist team at work';
		$about_tail = [
			self::section( [ self::widget( 'verto-about-split', [
				'variant'   => 'panel',
				'reverse'   => 'yes',
				'eyebrow'   => $c['what_we_do']['eyebrow'] ?? 'What we do today',
				'headline'  => $c['what_we_do']['headline'],
				'body'      => $c['what_we_do']['paragraphs'],
				'image'     => self::media_setting( $media, $wwd_img ),
				'image_alt' => $wwd_alt,
				'panel_bg'  => '#ffffff',
				'stats'     => [],
				'cta_text'  => '',
			] ) ], 'verto-bs vbs-wwd' ),
			self::section( [ self::widget( 'verto-quote-band', [
				'image'          => self::media_setting( $media, $c['subhero_image'] ?? $c['hero']['image'] ),
				'overlay'        => 'mission',
				'pad'            => 'band',
				'parallax_speed' => 0.35,
				// Oct 2026 (Alex): Edison drops the eyebrow; columns flatten via CSS.
				'eyebrow'        => $c['compass_eyebrow'] ?? 'The compass',
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
		// Journey – horizontal rail with brand-colour years (Vertek-only data).
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
			// Edison copy doc page 9: Proof points → Why clients choose Edison Lux.
			// Oct 2026: Edison also drops the 01-04 numbers (brand dashes instead).
			'eyebrow'  => $c['proof_eyebrow'] ?? 'Proof points',
			'heading'  => $c['proof_heading'] ?? 'Why clients hire us a second time.',
			'numbered' => $c['proof_numbered'] ?? 'yes',
			'items' => array_map( fn( $p ) => [ '_id' => self::eid(), 'text' => $p ], $c['proof'] ),
		] ) ], 'verto-bs' );
		// Round 5, item 5: compact services band on every brand-site page,
		// linking through to /clients. Edison copy doc page 9: Alex flagged the
		// home/about duplication – Edison's ABOUT drops the band (home keeps
		// the full four-models section; /clients is the services page itself).
		// Oct 2026 (Alex, ModulR round): ModulR's ABOUT carries the full
		// four-models section ("use what's on the home page services section").
		if ( 'modulr' === $brand ) {
			$about[] = self::four_models_section();
		} elseif ( 'edison-lux' !== $brand ) {
			$about[] = self::section( [ self::widget( 'html', [ 'html' => verto_services_band_html() ] ) ], 'verto-bs' );
		}
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
				'image'          => self::media_setting( $media, $c['subhero_image'] ?? $c['hero']['image'] ),
				'image_alt'      => $c['subhero_alt'] ?? $c['hero']['alt'],
				'overlay'        => 'hero-left',
				'pad'            => 'hero',
				'parallax_speed' => 0.3,
				// Edison copy doc page 10: no eyebrow, its own hero copy.
				'eyebrow'        => $co['hero_eyebrow'] ?? 'For companies',
				'eyebrow_style'  => 'dim',
				'heading_pre'    => $co['hero_headline'] ?? $co['headline'],
				'heading_accent' => '',
				'heading_post'   => '',
				'body'           => $co['hero_body'] ?? $co['body'],
				'cta1_text'      => 'Submit a vacancy',
				'cta1_link'      => [ 'url' => '#contact' ],
				'cta2_text'      => "How $name works",
				'cta2_link'      => [ 'url' => '/about' ],
				'stat_value'     => '',
				'stat_label'     => '',
			] ) ], 'verto-bs' ),
			// Round 9, item 16: the split no longer duplicates the page hero
			// image – each brand pulls a different licensed sector asset.
			self::section( [ self::widget( 'verto-about-split', [
				'variant'   => 'panel',
				'eyebrow'   => "About $name",
				// Edison copy doc page 10: Partnerships, not one offs – and the
				// EPC construction asset instead of a third run of pylons.
				'headline'  => $c['clients_split']['headline'] ?? 'A partnership, not a placement.',
				'body'      => $c['clients_split']['body'] ?? "We exist to find you the best technical commercial talent on the market – and we've earned that right by building trust with our partners over more than a decade.\n\nEvery consultant specialises in a product area. We recruit across the manufacturer and distributor landscape and represent your business as if it were our own.",
				'image'     => self::media_setting( $media, [ 'edison-lux' => 'spec_edison_06', 'vertek' => 'insight_fluidpower', 'modulr' => 'modulr_datacentre' ][ $brand ] ?? $c['hero']['image'] ),
				'image_alt' => [ 'edison-lux' => 'Cranes over an EPC construction site', 'vertek' => 'Hydraulic componentry on a fluid power line', 'modulr' => 'Data centre corridor lined with server racks' ][ $brand ] ?? $c['hero']['alt'],
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
			// The four Verto models – Engage (flagship) / Exclusive /
			// Contingent / Contract. Round 9, item 17: one flat row – equal
			// height, no stagger, accent bars in the brand gradient (row4
			// layout; --brand-gradient falls back to the flat brand colour).
			// v0.22.4 (client): simplified head + cards – see four_models_section().
			self::four_models_section(),
		];
		// Process – horizontal rail with connector line (Vertek-only data).
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
		// Case study – dark parallax band, Client/Sector meta + C/S/R grid.
		if ( ! empty( $c['case_study'] ) ) {
			$cs = $c['case_study'];
			$clients[] = self::section( [ self::widget( 'verto-quote-band', [
				'image'          => self::media_setting( $media, $c['subhero_image'] ?? $c['hero']['image'] ),
				'image_alt'      => $c['subhero_alt'] ?? $c['hero']['alt'],
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
		// Round 10: client testimonials – the curated CLIENT set for every
		// brand (real quotes, consultant avatar rows), carousel like the
		// candidate reviews band.
		if ( ! empty( $c['client_quotes'] ) ) {
			$clients[] = self::section( [ self::widget( 'verto-quote-band', [
				'quotes_style'     => 'light',
				'quotes_layout'    => 'carousel',
				'pad'              => 'band',
				'image'            => [],
				'eyebrow'          => 'In their words',
				'eyebrow_style'    => 'brand',
				'heading_pre'      => 'What clients say.',
				'heading_accent'   => '',
				'heading_post'     => '',
				'body'             => 'Real feedback from the businesses we hire for – gathered from every engagement.',
				'stat_value'       => '',
				'stat_label'       => '',
				'consultant_brand' => $name,
				'quotes'           => array_map( fn( $q ) => [ '_id' => self::eid() ] + $q, $c['client_quotes'] ),
			] ) ], 'verto-bs' );
		}
		$clients[] = self::section( [ self::widget( 'verto-feature-row', [
			'variant' => 'stats',
			'items'   => array_map( fn( $st ) => [ '_id' => self::eid(), 'icon' => '', 'title' => $st['value'], 'body' => $st['label'] ], $c['stats'] ),
		] ) ], 'verto-bs' );
		$clients[] = self::section( [ $team_strip ], 'verto-bs' );
		// Oct 2026 (Martin via Alex): "Speak to a specialist" – rotating
		// consultants + the newest live vacancy, filling out the quiet bottom
		// stretch of the clients page.
		$clients[] = self::section( [ self::widget( 'verto-specialist-band', [
			'brand'    => $brand,
			'cta_link' => [ 'url' => '#contact' ],
		] ) ], 'verto-bs' );
		$clients[] = $contact( 'Talk to us', 'Tell us what you need to build.', $co['bullets'] );
		self::upsert_page( 'clients', 'Clients', $clients );

		/* ── CANDIDATES (prototype for-candidates) ── */
		$ca         = $c['audiences']['candidate'];
		$candidates = [
			self::section( [ self::widget( 'verto-quote-band', [
				'image'          => self::media_setting( $media, $c['subhero_image'] ?? $c['hero']['image'] ),
				'image_alt'      => $c['subhero_alt'] ?? $c['hero']['alt'],
				'overlay'        => 'hero-right',
				'pad'            => 'hero',
				'align'          => 'right',
				'parallax_speed' => 0.3,
				'eyebrow'        => $ca['hero_eyebrow'] ?? 'For candidates',
				'eyebrow_style'  => 'brand',
				// Edison copy doc page 12: hero keeps "On your terms", new body.
				'heading_pre'    => $ca['hero_headline'] ?? $ca['headline'],
				'heading_accent' => '',
				'heading_post'   => '',
				'body'           => $ca['hero_body'] ?? $ca['body'],
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
				// Edison copy doc page 12: eyebrow gone, US-English merit copy.
				'eyebrow'   => $c['candidates_split']['eyebrow'] ?? 'Represented properly',
				'headline'  => 'Sold on your merits.',
				'body'      => $c['candidates_split']['body'] ?? "A job posted on LinkedIn gets hundreds of resumes. Working with $name means you and your experience are put front and centre – sold to the hiring manager before your first interview.\n\nWe only call when there's a role genuinely worth your time. Honest feedback, no fluff, no promises we can't deliver.",
				// Round 9, item 18: sector imagery (matching the What-we-cover
				// cards) instead of the repeated hero image.
				'image'     => self::media_setting( $media, [ 'edison-lux' => 'spec_edison_03', 'vertek' => 'insight_hvac', 'modulr' => 'insight_architecture' ][ $brand ] ?? $c['hero']['image'] ),
				'image_alt' => [ 'edison-lux' => 'A renewables site – solar and storage under construction', 'vertek' => 'Rooftop HVAC plant', 'modulr' => 'An architecture studio at work' ][ $brand ] ?? $c['hero']['alt'],
				'grayscale' => 'yes',
				'panel_bg'  => '#ffffff',
				'stats'     => [
					[ '_id' => self::eid(), 'value' => '72h',  'label' => 'First feedback after your intro call' ],
					[ '_id' => self::eid(), 'value' => '100%', 'label' => 'Confidential. Always' ],
					[ '_id' => self::eid(), 'value' => '1:1',  'label' => 'Same consultant, brief to offer' ],
				],
				'cta_text'  => "About $name",
				'cta_link'  => [ 'url' => '/about' ],
			] ) ], 'verto-bs' ),
			// Round 9, item 1: brand vacancies – straight after the hero/split.
			// Oct 2026 (Alex): Edison's and ModulR's CANDIDATES pages list the
			// jobs we're advertising FOR CLIENTS (Vincere-synced), not the
			// internal desk seats – those stay on the group Careers board.
			// Appears once brand-tagged client jobs exist in the sync.
			in_array( $brand, [ 'edison-lux', 'modulr' ], true ) ? self::section( [ self::widget( 'verto-jobs-board', [
				'layout'       => 'compact',
				'brand'        => $brand,
				'source'       => 'client',
				'list_eyebrow' => 'Live roles',
				'heading'      => 'edison-lux' === $brand ? 'Live roles in USA & European energy.' : 'Live roles in Architecture & Data Centres.',
				'intro'        => 'Open positions we are hiring for on behalf of our clients right now. Apply direct, or start a conversation below.',
			] ) ], 'verto-bs vbs-vacancies' ) : $vacancies,
		];
		// Round 9, item 2: candidate reviews carousel.
		if ( $reviews ) {
			$candidates[] = $reviews;
		}
		// Sectors served – dense chip grid (Vertek-only data).
		if ( ! empty( $c['sectors_served'] ) ) {
			$candidates[] = self::section( [ self::widget( 'verto-chip-grid', [
				'eyebrow' => 'Where we recruit',
				'heading' => 'Specialists across the industries we serve.',
				'items'   => array_map( fn( $sec ) => [ '_id' => self::eid(), 'label' => $sec ], $c['sectors_served'] ),
			] ) ], 'verto-bs' );
		}
		// Oct 2026 (Alex, ModulR round): per-sector candidate panels – who we
		// work with and where we place them, with sector imagery.
		if ( ! empty( $c['candidate_sectors'] ) ) {
			$candidates[] = self::section( [ self::widget( 'verto-specialisms', [
				'eyebrow' => '',
				'heading' => 'Who we work with.',
				'items'   => array_map( fn( $sec ) => [
					'_id'         => self::eid(),
					'icon'        => '',
					'image'       => self::media_setting( $media, $sec['image'] ),
					'title'       => $sec['title'],
					'description' => $sec['body'],
				], $c['candidate_sectors'] ),
			] ) ], 'verto-bs vbs-cand-sectors' );
		}
		// Candidate process – 4-up zigzag cards.
		// Oct 2026 (Alex): no dash in the heading.
		if ( ! empty( $c['candidate_process'] ) ) {
			$candidates[] = self::section( [ self::widget( 'verto-process-rail', [
				'layout'    => 'zigzag',
				'bg'        => 'default',
				'eyebrow'   => 'What to expect',
				'heading'   => 'From first call to first day and beyond.',
				'side_text' => '',
				'items'     => array_map( fn( $st ) => [ '_id' => self::eid(), 'kicker' => '', 'bullets' => '' ] + $st, $c['candidate_process'] ),
			] ) ], 'verto-bs' );
		}
		// Round 10: the old dark "Career moves that actually fit" band (fed by
		// the prototype's invented Vertek 'testimonials' slice) is GONE – the
		// curated candidate reviews carousel above carries the real quotes.
		// Round 5, item 5: compact services band on every brand-site page.
		// Oct 2026 (Alex): Edison's and ModulR's candidates pages swap the
		// (client-facing) services band for the candidate process above.
		if ( ! in_array( $brand, [ 'edison-lux', 'modulr' ], true ) ) {
			$candidates[] = self::section( [ self::widget( 'html', [ 'html' => verto_services_band_html() ] ) ], 'verto-bs' );
		}
		$candidates[] = self::section( [ $team_strip ], 'verto-bs' );
		// Oct 2026 (Alex): Edison gets a more consultative line.
		$candidates[] = $contact( 'Start a conversation', 'edison-lux' === $brand ? 'A confidential conversation about your next move.' : 'A confidential chat. No spam, no fluff.', $ca['bullets'] );
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
		// Oct 2026 (Alex): a way back to the group site from every brand site –
		// the logo was the only route before.
		wp_update_nav_menu_item( $menu_id, 0, [
			'menu-item-title'    => 'Verto Group',
			'menu-item-url'      => function_exists( 'verto_brand_url' ) ? verto_brand_url( 'verto' ) : 'https://verto-wp.on-forge.com',
			'menu-item-type'     => 'custom',
			'menu-item-status'   => 'publish',
			'menu-item-position' => $i++,
			'menu-item-classes'  => 'verto-nav-group',
		] );
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
		$brand_sites = [ 'edison-lux', 'modulr', 'vertek' ];
		$i = 1;
		foreach ( $order as $slug => [ $title, $class ] ) {
			if ( in_array( $slug, $brand_sites, true ) && function_exists( 'verto_brand_url' ) ) {
				// Brand entries link across to the brand's own site.
				wp_update_nav_menu_item( $menu_id, 0, [
					'menu-item-title'    => $title,
					'menu-item-type'     => 'custom',
					'menu-item-url'      => verto_brand_url( $slug ),
					'menu-item-status'   => 'publish',
					'menu-item-position' => $i++,
					'menu-item-classes'  => $class,
				] );
				continue;
			}
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

	/** One-time content sweep: earlier seeds wrote em dashes into DB content
	 *  (job titles/adverts, story excerpts). Replace with en dashes. */
	private static function sweep_em_dashes(): void {
		if ( get_option( 'verto_emdash_sweep' ) ) return;
		$posts = get_posts( [
			'post_type'      => [ 'post', 'verto_job', 'verto_team', 'page' ],
			'post_status'    => 'any',
			'posts_per_page' => -1,
		] );
		foreach ( $posts as $p ) {
			$t = str_replace( [ ' — ', '—' ], [ ' – ', '–' ], $p->post_title );
			$x = str_replace( [ ' — ', '—' ], [ ' – ', '–' ], $p->post_excerpt );
			$b = str_replace( [ ' — ', '—' ], [ ' – ', '–' ], $p->post_content );
			if ( $t !== $p->post_title || $x !== $p->post_excerpt || $b !== $p->post_content ) {
				wp_update_post( [ 'ID' => $p->ID, 'post_title' => $t, 'post_excerpt' => $x, 'post_content' => $b ] );
			}
		}
		update_option( 'verto_emdash_sweep', 1 );
	}
}

Verto_Installer::boot();
