<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Jobs Board – LHi layout: jobs list left (2/3), sticky filter rail
 * right (1/3), brand colour-coding + sector tooltips, client-side filtering
 * (verto-effects.js).
 *
 * Job source, in priority order:
 *   1. the optional `vincere_shortcode` control (external plugin override)
 *   2. live jobs synced from Vincere (includes/vincere.php → verto_job CPT)
 *   3. the PLACEHOLDER roles baked in below (fallback until a sync has run)
 */
class Verto_Widget_Jobs_Board extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-jobs-board'; }
	public function get_title() { return 'Verto Jobs Board (Vincere)'; }
	public function get_icon() { return 'eicon-post-list'; }
	public function get_categories() { return [ 'verto' ]; }

	private const BRANDS = [
		'verto'      => [ 'label' => 'Verto Group', 'color' => 'var(--accent)', 'sector' => 'Life Sciences (group desk)' ],
		'edison-lux' => [ 'label' => 'Edison Lux', 'color' => '#3CC739', 'sector' => 'Power & Energy recruitment' ],
		'vertek'     => [ 'label' => 'Vertek', 'color' => '#F82B60', 'sector' => 'Engineering, Sales & Manufacturing recruitment' ],
		'modulr'     => [ 'label' => 'ModulR', 'color' => '#7FA8FC', 'sector' => 'Built Environment recruitment' ],
	];
	private const LOCATIONS = [ 'Solent, UK', 'Austin, TX', 'New York, NY' ];
	private const LEVELS    = [ 'Entry-level', 'Senior', 'Manager' ];
	// FALLBACK-ONLY since 0.14.0: the installer seeds the standing internal
	// vacancies as real verto_job posts (Verto_Installer::seed_internal_jobs),
	// so a built site always has live rows with proper detail pages. These
	// consts only render on a site that has never run the installer or sync.
	private const JOBS = [
		[ 'title' => 'Senior Recruitment Consultant – US Energy', 'brand' => 'edison-lux', 'location' => 'Austin, TX', 'level' => 'Senior', 'package' => '$60–80k base + 40% commission + share scheme' ],
		[ 'title' => 'Entry-Level Recruitment Consultant – Power & Energy', 'brand' => 'edison-lux', 'location' => 'Austin, TX', 'level' => 'Entry-level', 'package' => '$50–60k base + commission + share scheme' ],
		[ 'title' => 'Senior Recruitment Consultant – Technical Sales', 'brand' => 'vertek', 'location' => 'Solent, UK', 'level' => 'Senior', 'package' => '£35–45k base + 40% commission + share scheme' ],
		[ 'title' => 'Entry-Level Recruitment Consultant – Engineering', 'brand' => 'vertek', 'location' => 'Solent, UK', 'level' => 'Entry-level', 'package' => '£25–28k base + uncapped commission' ],
		[ 'title' => 'Recruitment Consultant – HVAC & Refrigeration', 'brand' => 'vertek', 'location' => 'Austin, TX', 'level' => 'Senior', 'package' => '$55–70k base + 40% commission + share scheme' ],
		[ 'title' => 'Recruitment Consultant – Data Centres & Critical Environments', 'brand' => 'modulr', 'location' => 'New York, NY', 'level' => 'Senior', 'package' => '$60–80k base + 40% commission + share scheme' ],
		[ 'title' => 'Team Manager – ModulR US', 'brand' => 'modulr', 'location' => 'New York, NY', 'level' => 'Manager', 'package' => '$90–120k base + override + equity' ],
		[ 'title' => 'Recruitment Consultant – Life Sciences', 'brand' => 'verto', 'location' => 'Solent, UK', 'level' => 'Senior', 'package' => '£35–45k base + 40% commission + share scheme' ],
		[ 'title' => 'Talent & Resourcing Partner – Group', 'brand' => 'verto', 'location' => 'Solent, UK', 'level' => 'Entry-level', 'package' => '£24–27k base + bonus' ],
	];

	/** Round 55 (Alex): fixed filter vocabularies per brand – the free-text
	 *  website_product_area maps onto these chips (first keyword hit wins),
	 *  so the filter rail can never sprawl to 50 options. */
	const PRODUCT_CHIPS = [
		'edison-lux' => [
			'Combined Cycle & Gas' => [ 'combined', 'ccgt', 'gas' ],
			'Renewables'           => [ 'renewab', 'solar', 'wind', 'battery', 'storage', 'transition' ],
			'Biomass & EFW'        => [ 'biomass', 'efw', 'waste' ],
			'Nuclear'              => [ 'nuclear' ],
			'EPC'                  => [ 'epc', 'construction', 'commissioning' ],
			'Power Generation'     => [ 'power', 'generation', 'genset', 'turbine', 'critical' ],
		],
		'modulr' => [
			'Data Centres' => [ 'data cent', 'datacent', 'dc', 'facilities', 'colocation' ],
			'Architecture' => [ 'architect', 'interior', 'design' ],
			'MEP'          => [ 'mep', 'mechanical', 'electrical', 'plumb', 'building services', 'structural' ],
		],
		'vertek' => [
			'Compressors'                     => [ 'compress' ],
			'CNC & Metalworking'              => [ 'cnc', 'metal', 'machin', 'cutting', 'tool' ],
			'Industrial Automation'           => [ 'automation', 'robot', 'plc', 'sensor' ],
			'Defense & Advanced Manufacturing'=> [ 'defen', 'aerospace', 'manufactur' ],
			'HVAC'                            => [ 'hvac', 'air', 'refriger', 'heat', 'vent' ],
			'Fluid Power'                     => [ 'fluid', 'pump', 'hydraul', 'seal', 'valve', 'pneumatic' ],
		],
	];

	/** Round 55: USA / Europe region from the advert's location string.
	 *  Unknown locations return '' and pass every region filter. */
	private static function region_of( string $loc ): string {
		$l = strtolower( $loc );
		if ( preg_match( '/\b(usa|u\.s\.|united states|texas|austin|new york|canada)\b/', $l ) || preg_match( '/,\s*[a-z]{2},\s*usa/', $l ) ) {
			return 'usa';
		}
		if ( preg_match( '/\b(uk|united kingdom|england|scotland|wales|ireland|germany|france|spain|italy|netherlands|belgium|poland|sweden|norway|denmark|switzerland|austria|portugal|europe)\b/', $l ) ) {
			return 'europe';
		}
		return '';
	}

	/** Round 55: free-text product area -> its fixed chip (or ''). */
	private static function product_chip( string $text, string $brand ): string {
		$map = self::PRODUCT_CHIPS[ $brand ] ?? [];
		$t   = strtolower( $text );
		if ( '' === trim( $t ) ) {
			return '';
		}
		foreach ( $map as $label => $keys ) {
			foreach ( $keys as $k ) {
				if ( false !== strpos( $t, $k ) ) {
					return $label;
				}
			}
		}
		return '';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Jobs' ] );
		// Round 9, item 1: "compact" renders a lean rows-only board (no
		// filter rail, no chips) for the brand-site HOME and CANDIDATES
		// pages; "brand" scopes the rows to one brand's jobs.
		$this->add_control( 'layout', [
			'label' => 'Layout', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'full' => 'Full board (list + filters)', 'compact' => 'Compact (rows only, brand sites)' ],
			'default' => 'full',
		] );
		$this->add_control( 'brand', [
			'label' => 'Brand filter (slug, optional)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '',
			'description' => 'e.g. edison-lux – shows only that brand\'s jobs.',
		] );
		// Oct 2026 (Edison candidates page): "client" lists the Vincere-synced
		// roles advertised FOR CLIENTS (never the internal desk seats, never
		// the placeholder fallback – renders nothing until tagged jobs exist).
		$this->add_control( 'source', [
			'label' => 'Job source', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'internal' => 'Internal (Join Verto seats)', 'client' => 'Client vacancies (Vincere)' ],
			'default' => 'internal',
		] );
		$this->add_control( 'list_eyebrow', [ 'label' => 'Compact eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Join the team' ] );
		// Round 4, item 5: catchier heading, and no word "roles" in the section.
		$this->add_control( 'heading', [ 'label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Your next desk is here.' ] );
		$this->add_control( 'intro', [ 'label' => 'Intro', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => "Explore opportunities to join our growing team across our specialist brands and locations.\nDon't see a role that matches your expertise? We're always keen to hear from ambitious, experienced recruitment consultants who want to make an impact." ] );
		// Round 22 (Nov 2026, Alex): dedicated brand jobs pages + capped
		// compact boards ("could have 30, 50 or even more at any one time").
		$this->add_control( 'full_eyebrow', [ 'label' => 'Full-board eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Join Verto' ] );
		$this->add_control( 'limit', [ 'label' => 'Compact: max rows (0 = all)', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 0, 'min' => 0, 'max' => 50 ] );
		$this->add_control( 'view_all_text', [ 'label' => 'Compact: view-all button text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ] );
		$this->add_control( 'view_all_link', [ 'label' => 'Compact: view-all link', 'type' => \Elementor\Controls_Manager::URL ] );
		$this->add_control( 'apply_url', [ 'label' => 'Job click-through URL', 'type' => \Elementor\Controls_Manager::URL, 'default' => [ 'url' => '/contact' ] ] );
		$this->add_control( 'vincere_shortcode', [ 'label' => 'Vincere shortcode (optional)', 'type' => \Elementor\Controls_Manager::TEXT, 'description' => 'Once the Vincere plugin is installed, paste its shortcode – it replaces the placeholder roles.' ] );
		$this->end_controls_section();
	}

	/**
	 * Live jobs synced from Vincere (includes/vincere.php). Empty array when
	 * no sync has run yet – the placeholder JOBS const is the fallback, so
	 * the board never renders empty.
	 */
	private function live_jobs( string $source = 'internal', string $brand = '' ) {
		$fn = 'client' === $source ? 'verto_vincere_get_client_jobs' : 'verto_vincere_get_jobs';
		if ( ! function_exists( $fn ) ) {
			return [];
		}
		// Nov 2026: client jobs are brand-scoped in the QUERY – the tenant
		// holds 2,000+ open client jobs, so filtering after a 100-row window
		// was silently dropping the tagged ones.
		$jobs = 'client' === $source ? $fn( $brand ) : $fn();
		if ( ! is_array( $jobs ) || ! $jobs ) {
			return [];
		}
		// Normalize defensively so unknown brands/missing keys can't break markup.
		$clean = [];
		foreach ( $jobs as $job ) {
			if ( empty( $job['title'] ) ) {
				continue;
			}
			$brand = isset( $job['brand'] ) && isset( self::BRANDS[ $job['brand'] ] ) ? $job['brand'] : 'verto';
			$clean[] = [
				'title'      => (string) $job['title'],
				'brand'      => $brand,
				'location'   => (string) ( $job['location'] ?? 'Flexible' ),
				'level'      => (string) ( $job['level'] ?? 'Senior' ),
				'package'    => (string) ( $job['package'] ?? 'Competitive package' ),
				'url'        => (string) ( $job['url'] ?? '' ),
				'permalink'  => (string) ( $job['permalink'] ?? '' ),
				'vincere_id' => (string) ( $job['vincere_id'] ?? '' ),
				'product'    => (string) ( $job['product'] ?? '' ),
			];
		}
		return $clean;
	}

	/** Round 9, item 1 – compact rows-only board for brand sites: each row
	 *  links to the job's detail page, with an Apply pill jumping straight
	 *  to the detail page's inline apply card (#apply). Token-driven, so it
	 *  sits correctly on light (Edison) and dark (Vertek/ModulR) grounds. */
	private function render_compact( array $s, array $jobs ): void {
		if ( ! $jobs ) return; // nothing seeded yet – render nothing
		// Round 22: cap the rows and offer "View all jobs" through to the
		// dedicated jobs page when more exist.
		$cap      = max( 0, (int) ( $s['limit'] ?? 0 ) );
		$total    = count( $jobs );
		$view_url = (string) ( $s['view_all_link']['url'] ?? '' );
		$view_txt = trim( (string) ( $s['view_all_text'] ?? '' ) );
		if ( $cap > 0 && $total > $cap ) {
			$jobs = array_slice( $jobs, 0, $cap );
		}
		$show_view_all = '' !== $view_txt && '' !== $view_url && ( 0 === $cap || $total > $cap );
		?>
		<div class="verto-jobs-compact">
			<div class="container-wide">
				<div class="verto-jobs-compact__head">
					<span class="eyebrow"><?php echo esc_html( $s['list_eyebrow'] ?: 'Join the team' ); ?></span>
					<h2 class="display-3 vbs-mt5"><?php echo esc_html( $s['heading'] ); ?></h2>
					<?php if ( ! empty( $s['intro'] ) ) : ?>
						<p class="verto-jobs-compact__intro"><?php echo nl2br( esc_html( $s['intro'] ) ); ?></p>
					<?php endif; ?>
				</div>
				<div class="verto-jobs-compact__list">
					<?php foreach ( $jobs as $job ) :
						$permalink = (string) ( $job['permalink'] ?? '' );
						$href      = '' !== $permalink ? $permalink : ( $job['url'] ?? ( $s['apply_url']['url'] ?? '/contact' ) );
						?>
						<div class="verto-jobs-compact__row">
							<?php // Nov 2026 (Alex): same layout as the jobs-page rows -
							// product kicker, title, package underneath; location sits on
							// the right beside Apply. ?>
							<a class="verto-jobs-compact__main" href="<?php echo esc_url( $href ); ?>">
								<?php if ( '' !== ( $job['product'] ?? '' ) ) : ?>
									<span class="verto-jobs-compact__kicker"><?php echo esc_html( $job['product'] ); ?></span>
								<?php endif; ?>
								<span class="verto-jobs-compact__title"><?php echo esc_html( $job['title'] ); ?></span>
								<span class="verto-jobs-compact__meta"><?php echo esc_html( $job['package'] ); ?></span>
							</a>
							<span class="verto-jobs-compact__side">
								<span class="verto-jobs-compact__loc"><?php echo esc_html( $job['location'] ); ?></span>
								<a class="verto-jobs-compact__apply" href="<?php echo esc_url( '' !== $permalink ? $permalink . '#apply' : $href ); ?>">Apply <span aria-hidden="true">&#8599;</span></a>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
				<?php if ( $show_view_all ) : ?>
					<div class="verto-jobs-compact__viewall">
						<a class="btn-base btn-primary" href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $view_txt ); ?><?php if ( $cap > 0 && $total > $cap ) : ?> (<?php echo (int) $total; ?>)<?php endif; ?></a>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$apply = $s['apply_url']['url'] ?? '/contact';

		$source = ( $s['source'] ?? 'internal' ) ?: 'internal';
		// Client mode never falls back to the internal placeholders – an empty
		// board renders nothing until Edison-tagged client jobs exist.
		$client = 'client' === $source;
		// Round 9, item 1: optional brand scope (live rows and the
		// placeholder fallback alike).
		$brand_scope = sanitize_key( (string) ( $s['brand'] ?? '' ) );
		$live        = $this->live_jobs( $source, $client ? $brand_scope : '' );
		if ( '' !== $brand_scope ) {
			$scope = fn( $rows ) => array_values( array_filter( $rows, fn( $j ) => $brand_scope === ( $j['brand'] ?? '' ) ) );
			$live  = $scope( $live );
			if ( 'compact' === ( $s['layout'] ?? 'full' ) ) {
				$this->render_compact( $s, $live ? $live : ( $client ? [] : $scope( self::JOBS ) ) );
				return;
			}
		} elseif ( 'compact' === ( $s['layout'] ?? 'full' ) ) {
			$this->render_compact( $s, $live ? $live : ( $client ? [] : self::JOBS ) );
			return;
		}
		// Round 22: the client-mode FULL board never shows placeholders – a
		// friendly empty state renders until tagged jobs sync in.
		if ( $client && ! $live ) {
			?>
			<div class="verto-jobs" data-verto-jobs>
				<div class="verto-intro">
					<div class="verto-jobs__eyebrow"><?php echo esc_html( ( $s['full_eyebrow'] ?? '' ) ?: 'Live roles' ); ?></div>
					<h2 class="verto-title-reveal verto-display-2" style="margin-top:1.25rem;">
						<span class="line-mask"><span class="line-inner"><?php echo esc_html( $s['heading'] ); ?></span></span>
					</h2>
					<p class="verto-intro__body">No live roles right now. New positions land here the moment they open – or start a conversation and we'll come to you first.</p>
					<p style="margin-top:2rem;"><a class="btn-base btn-primary" href="/clients#contact">Start a conversation</a></p>
				</div>
			</div>
			<?php
			return;
		}
		$jobs      = $live ? $live : self::JOBS;
		$locations = $live ? array_values( array_unique( array_column( $live, 'location' ) ) ) : self::LOCATIONS;
		$levels    = $live ? array_values( array_unique( array_column( $live, 'level' ) ) ) : self::LEVELS;
		sort( $locations );
		// Round 55 (Alex): client boards drop the per-location chips (too
		// many) for a USA / Europe region filter + fixed product chips; the
		// keyword search covers exact locations instead.
		$regions    = [];
		$prod_chips = [];
		if ( $client ) {
			foreach ( $jobs as $jk => $job ) {
				$jobs[ $jk ]['region']     = self::region_of( (string) $job['location'] );
				$jobs[ $jk ]['chip']       = self::product_chip( (string) ( $job['product'] ?? '' ), (string) $job['brand'] );
				if ( '' !== $jobs[ $jk ]['region'] ) {
					$regions[ $jobs[ $jk ]['region'] ] = true;
				}
				if ( '' !== $jobs[ $jk ]['chip'] ) {
					$prod_chips[ $jobs[ $jk ]['chip'] ] = true;
				}
			}
			// chips keep the vocabulary's order
			$ordered = [];
			foreach ( self::PRODUCT_CHIPS[ $brand_scope ] ?? [] as $label => $keys ) {
				if ( isset( $prod_chips[ $label ] ) ) {
					$ordered[] = $label;
				}
			}
			$prod_chips = $ordered ?: array_keys( $prod_chips );
		}
		$products = $client ? $prod_chips : ( $live ? array_values( array_filter( array_unique( array_column( $live, 'product' ) ) ) ) : [] );
		if ( ! $client ) {
			sort( $products );
		}

		// Live (Vincere-synced) rows open the inline Apply modal instead of the
		// click-through URL; placeholder rows keep the old behaviour. A row
		// with an explicit external apply URL (the optional Vincere portal
		// base) keeps that link.
		$has_modal = (bool) $live && class_exists( 'Verto_Applications' );

		// Non-JS submissions bounce back here with ?verto_apply=ok|<error>.
		$flash = '';
		$flash_ok = false;
		if ( isset( $_GET['verto_apply'] ) && class_exists( 'Verto_Applications' ) ) {
			$code     = sanitize_key( wp_unslash( $_GET['verto_apply'] ) );
			$messages = Verto_Applications::messages();
			if ( isset( $messages[ $code ] ) ) {
				$flash    = $messages[ $code ];
				$flash_ok = ( 'ok' === $code );
			}
		}
		?>
		<div class="verto-jobs" data-verto-jobs>
			<?php if ( '' !== $flash ) : ?>
				<div class="verto-apply-banner <?php echo $flash_ok ? 'is-ok' : 'is-error'; ?>" role="status"><?php echo esc_html( $flash ); ?></div>
			<?php endif; ?>
			<div class="verto-intro">
				<div class="verto-jobs__eyebrow"><?php echo esc_html( ( $s['full_eyebrow'] ?? '' ) ?: 'Join Verto' ); ?></div>
				<h2 class="verto-title-reveal verto-display-2" style="margin-top:1.25rem;">
					<span class="line-mask"><span class="line-inner"><?php echo esc_html( $s['heading'] ); ?></span></span>
				</h2>
				<p class="verto-intro__body"><?php echo nl2br( esc_html( $s['intro'] ) ); ?></p>
			</div>

			<div class="verto-jobs__layout" style="margin-top:3rem;">
				<div class="verto-jobs__list">
					<?php if ( ! empty( $s['vincere_shortcode'] ) ) : ?>
						<?php echo do_shortcode( wp_kses_post( $s['vincere_shortcode'] ) ); ?>
					<?php else : ?>
						<?php foreach ( $jobs as $job ) :
							$b = self::BRANDS[ $job['brand'] ];
							// Row click-through, in priority order:
							//   1. the job's own DETAIL PAGE (public verto_job
							//      permalink – brand hero, office photos, team,
							//      advert + inline apply) with a small
							//      "Apply ↗" affordance that still opens the
							//      modal directly (JS preventDefault stops the
							//      row navigation; without JS the row link to
							//      the detail page wins – the form is there);
							//   2. an explicit external apply URL (Vincere
							//      portal base, when configured);
							//   3. modal only (live row, no permalink – never
							//      the case once the CPT is public);
							//   4. placeholder rows: the widget's click-through.
							$permalink  = (string) ( $job['permalink'] ?? '' );
							$use_modal  = $has_modal && empty( $job['url'] );
							if ( '' !== $permalink ) {
								$href = $permalink;
							} elseif ( ! empty( $job['url'] ) ) {
								$href = $job['url'];
							} else {
								$href = $use_modal ? '#verto-apply-modal' : $apply;
							}
							$row_opens_modal = $use_modal && '' === $permalink;
							?>
							<a class="verto-jobs__row verto-jobs__row--<?php echo esc_attr( $job['brand'] ); ?>"
							   href="<?php echo esc_url( $href ); ?>"
							   <?php if ( $row_opens_modal ) : ?>
							   data-apply-open
							   data-job-id="<?php echo esc_attr( $job['vincere_id'] ?? '' ); ?>"
							   data-job-title="<?php echo esc_attr( $job['title'] ); ?>"
							   <?php endif; ?>
							   data-brand="<?php echo esc_attr( $job['brand'] ); ?>"
							   data-location="<?php echo esc_attr( $job['location'] ); ?>"
							   data-region="<?php echo esc_attr( $job['region'] ?? '' ); ?>"
							   data-level="<?php echo esc_attr( $job['level'] ); ?>"
							   data-product="<?php echo esc_attr( $client ? ( $job['chip'] ?? '' ) : ( $job['product'] ?? '' ) ); ?>"
							   data-producttext="<?php echo esc_attr( $job['product'] ?? '' ); ?>">
								<div class="verto-jobs__rowmain">
									<div class="verto-jobs__meta">
										<?php // Nov 2026 (Alex): kicker shows the brand only – the
									// guessed level comes off (Level filter still works via
									// the row's data-level attribute). ?>
									<span class="verto-tip verto-jobs__brand" style="color:<?php echo esc_attr( $b['color'] ); ?>;"><?php echo esc_html( $b['label'] ); ?><span class="verto-tip__bubble"><?php echo esc_html( $b['sector'] ); ?></span></span>
									<?php if ( '' !== ( $job['product'] ?? '' ) ) : // Nov 2026 (Alex): product area in the kicker ?>
									<span class="sep">·</span>
									<span class="lvl"><?php echo esc_html( $job['product'] ); ?></span>
									<?php endif; ?>
									</div>
									<div class="verto-jobs__title"><?php echo esc_html( $job['title'] ); ?></div>
									<div class="verto-jobs__package"><?php echo esc_html( $job['package'] ); ?></div>
								</div>
								<div class="verto-jobs__side">
									<span><?php echo esc_html( $job['location'] ); ?></span>
									<?php if ( $use_modal && '' !== $permalink ) : ?>
										<span class="verto-jobs__applylink" role="button" tabindex="0"
											data-apply-open
											data-job-id="<?php echo esc_attr( $job['vincere_id'] ?? '' ); ?>"
											data-job-title="<?php echo esc_attr( $job['title'] ); ?>">Apply <span class="arrow" aria-hidden="true">↗</span></span>
									<?php else : ?>
										<span class="arrow" aria-hidden="true">↗</span>
									<?php endif; ?>
								</div>
							</a>
						<?php endforeach; ?>
						<p class="verto-jobs__empty" hidden>Nothing matches those filters right now – but send us a note anyway; half our hires start that way.</p>
					<?php endif; ?>
				</div>

				<aside class="verto-jobs__filters">
					<div class="verto-jobs__panel">
						<div class="verto-jobs__paneltop">
							<span class="verto-jobs__panellabel">Filter</span>
							<button type="button" class="verto-jobs__clear" data-jobs-clear hidden>Clear</button>
						</div>
						<?php // Round 22: keyword search (title match, JS §5). ?>
						<div class="verto-jobs__group">
							<div class="verto-jobs__grouplabel">Search</div>
							<input type="search" class="verto-jobs__search" data-jobs-search placeholder="Search roles&hellip;" aria-label="Search roles" />
						</div>
						<?php if ( '' === $brand_scope ) : // single-brand boards skip the brand chips ?>
						<div class="verto-jobs__group">
							<div class="verto-jobs__grouplabel">Brand</div>
							<div class="verto-jobs__chips" data-filter-group="brand">
								<button type="button" class="verto-chip is-active" data-value="all">All</button>
								<?php foreach ( self::BRANDS as $slug => $b ) : ?>
									<button type="button" class="verto-chip verto-tip" data-value="<?php echo esc_attr( $slug ); ?>"><span class="verto-chip__dot" style="background:<?php echo esc_attr( $b['color'] ); ?>;"></span><?php echo esc_html( $b['label'] ); ?><span class="verto-tip__bubble"><?php echo esc_html( $b['sector'] ); ?></span></button>
								<?php endforeach; ?>
							</div>
						</div>
						<?php endif; ?>
						<?php if ( $client ) : ?>
							<?php if ( count( $regions ) >= 2 ) : // Round 55 (Alex): USA / Europe instead of every location ?>
							<div class="verto-jobs__group">
								<div class="verto-jobs__grouplabel">Region</div>
								<div class="verto-jobs__chips" data-filter-group="region">
									<button type="button" class="verto-chip is-active" data-value="all">All</button>
									<button type="button" class="verto-chip" data-value="usa">USA</button>
									<button type="button" class="verto-chip" data-value="europe">Europe</button>
								</div>
							</div>
							<?php endif; ?>
						<?php else : ?>
						<div class="verto-jobs__group">
							<div class="verto-jobs__grouplabel">Location</div>
							<div class="verto-jobs__chips" data-filter-group="location">
								<button type="button" class="verto-chip is-active" data-value="all">All</button>
								<?php foreach ( $locations as $loc ) : ?>
									<button type="button" class="verto-chip" data-value="<?php echo esc_attr( $loc ); ?>"><?php echo esc_html( $loc ); ?></button>
								<?php endforeach; ?>
							</div>
						</div>
						<?php endif; ?>
						<?php // Nov 2026 (Alex): no Senior/Manager chips – the product/
						// team filter takes the slot (renders once jobs carry the
						// website_product_area field). ?>
						<?php if ( $products ) : ?>
						<div class="verto-jobs__group">
							<div class="verto-jobs__grouplabel">Product / team</div>
							<div class="verto-jobs__chips" data-filter-group="product">
								<button type="button" class="verto-chip is-active" data-value="all">All</button>
								<?php foreach ( $products as $prod ) : ?>
									<button type="button" class="verto-chip" data-value="<?php echo esc_attr( $prod ); ?>"><?php echo esc_html( $prod ); ?></button>
								<?php endforeach; ?>
							</div>
						</div>
						<?php endif; ?>
					</div>
					<p class="verto-jobs__count"><span data-jobs-count><?php echo count( $jobs ); ?></span> openings shown · Can't see your desk? <?php if ( $has_modal ) : ?><a href="#verto-apply-modal" data-apply-open data-job-id="" data-job-title="">Send a general application →</a><?php else : ?><a href="<?php echo esc_url( $apply ); ?>">Write to us anyway →</a><?php endif; ?></p>
				</aside>
			</div>
		</div>
		<?php
		if ( $has_modal ) {
			Verto_Applications::render_modal();
		}
	}
}
