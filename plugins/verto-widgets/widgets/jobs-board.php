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
	private const LOCATIONS = [ 'Solent, UK', 'Austin, TX', 'Miami, FL' ];
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
		[ 'title' => 'Recruitment Consultant – Data Centres & Critical Environments', 'brand' => 'modulr', 'location' => 'Miami, FL', 'level' => 'Senior', 'package' => '$60–80k base + 40% commission + share scheme' ],
		[ 'title' => 'Team Manager – ModulR US', 'brand' => 'modulr', 'location' => 'Miami, FL', 'level' => 'Manager', 'package' => '$90–120k base + override + equity' ],
		[ 'title' => 'Recruitment Consultant – Life Sciences', 'brand' => 'verto', 'location' => 'Solent, UK', 'level' => 'Senior', 'package' => '£35–45k base + 40% commission + share scheme' ],
		[ 'title' => 'Talent & Resourcing Partner – Group', 'brand' => 'verto', 'location' => 'Solent, UK', 'level' => 'Entry-level', 'package' => '£24–27k base + bonus' ],
	];

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Jobs' ] );
		// Round 4, item 5: catchier heading, and no word "roles" in the section.
		$this->add_control( 'heading', [ 'label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Your next desk is here.' ] );
		$this->add_control( 'intro', [ 'label' => 'Intro', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => "These are seats on our own desks – not client vacancies. And if your desk isn't listed yet, we still want to hear from experienced consultants." ] );
		$this->add_control( 'apply_url', [ 'label' => 'Job click-through URL', 'type' => \Elementor\Controls_Manager::URL, 'default' => [ 'url' => '/contact' ] ] );
		$this->add_control( 'vincere_shortcode', [ 'label' => 'Vincere shortcode (optional)', 'type' => \Elementor\Controls_Manager::TEXT, 'description' => 'Once the Vincere plugin is installed, paste its shortcode – it replaces the placeholder roles.' ] );
		$this->end_controls_section();
	}

	/**
	 * Live jobs synced from Vincere (includes/vincere.php). Empty array when
	 * no sync has run yet – the placeholder JOBS const is the fallback, so
	 * the board never renders empty.
	 */
	private function live_jobs() {
		if ( ! function_exists( 'verto_vincere_get_jobs' ) ) {
			return [];
		}
		$jobs = verto_vincere_get_jobs();
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
			];
		}
		return $clean;
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$apply = $s['apply_url']['url'] ?? '/contact';

		$live      = $this->live_jobs();
		$jobs      = $live ? $live : self::JOBS;
		$locations = $live ? array_values( array_unique( array_column( $live, 'location' ) ) ) : self::LOCATIONS;
		$levels    = $live ? array_values( array_unique( array_column( $live, 'level' ) ) ) : self::LEVELS;

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
				<div class="verto-jobs__eyebrow">Join Verto</div>
				<h2 class="verto-title-reveal verto-display-2" style="margin-top:1.25rem;">
					<span class="line-mask"><span class="line-inner"><?php echo esc_html( $s['heading'] ); ?></span></span>
				</h2>
				<p class="verto-intro__body"><?php echo esc_html( $s['intro'] ); ?></p>
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
							   data-level="<?php echo esc_attr( $job['level'] ); ?>">
								<div class="verto-jobs__rowmain">
									<div class="verto-jobs__meta">
										<span class="verto-tip verto-jobs__brand" style="color:<?php echo esc_attr( $b['color'] ); ?>;"><?php echo esc_html( $b['label'] ); ?><span class="verto-tip__bubble"><?php echo esc_html( $b['sector'] ); ?></span></span>
										<span class="sep">·</span>
										<span class="lvl"><?php echo esc_html( $job['level'] ); ?></span>
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
						<div class="verto-jobs__group">
							<div class="verto-jobs__grouplabel">Brand</div>
							<div class="verto-jobs__chips" data-filter-group="brand">
								<button type="button" class="verto-chip is-active" data-value="all">All</button>
								<?php foreach ( self::BRANDS as $slug => $b ) : ?>
									<button type="button" class="verto-chip verto-tip" data-value="<?php echo esc_attr( $slug ); ?>"><span class="verto-chip__dot" style="background:<?php echo esc_attr( $b['color'] ); ?>;"></span><?php echo esc_html( $b['label'] ); ?><span class="verto-tip__bubble"><?php echo esc_html( $b['sector'] ); ?></span></button>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="verto-jobs__group">
							<div class="verto-jobs__grouplabel">Location</div>
							<div class="verto-jobs__chips" data-filter-group="location">
								<button type="button" class="verto-chip is-active" data-value="all">All</button>
								<?php foreach ( $locations as $loc ) : ?>
									<button type="button" class="verto-chip" data-value="<?php echo esc_attr( $loc ); ?>"><?php echo esc_html( $loc ); ?></button>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="verto-jobs__group">
							<div class="verto-jobs__grouplabel">Level</div>
							<div class="verto-jobs__chips" data-filter-group="level">
								<button type="button" class="verto-chip is-active" data-value="all">All</button>
								<?php foreach ( $levels as $lvl ) : ?>
									<button type="button" class="verto-chip" data-value="<?php echo esc_attr( $lvl ); ?>"><?php echo esc_html( $lvl ); ?></button>
								<?php endforeach; ?>
							</div>
						</div>
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
