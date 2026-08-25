<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Jobs Board — LHi layout: jobs list left (2/3), sticky filter rail
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
	private const JOBS = [
		[ 'title' => 'Senior Recruitment Consultant — US Energy', 'brand' => 'edison-lux', 'location' => 'Austin, TX', 'level' => 'Senior', 'package' => '$60–80k base + 40% commission + share scheme' ],
		[ 'title' => 'Entry-Level Recruitment Consultant — Power & Energy', 'brand' => 'edison-lux', 'location' => 'Austin, TX', 'level' => 'Entry-level', 'package' => '$50–60k base + commission + share scheme' ],
		[ 'title' => 'Senior Recruitment Consultant — Technical Sales', 'brand' => 'vertek', 'location' => 'Solent, UK', 'level' => 'Senior', 'package' => '£35–45k base + 40% commission + share scheme' ],
		[ 'title' => 'Entry-Level Recruitment Consultant — Engineering', 'brand' => 'vertek', 'location' => 'Solent, UK', 'level' => 'Entry-level', 'package' => '£25–28k base + uncapped commission' ],
		[ 'title' => 'Recruitment Consultant — HVAC & Refrigeration', 'brand' => 'vertek', 'location' => 'Austin, TX', 'level' => 'Senior', 'package' => '$55–70k base + 40% commission + share scheme' ],
		[ 'title' => 'Recruitment Consultant — Data Centres & Critical Environments', 'brand' => 'modulr', 'location' => 'Miami, FL', 'level' => 'Senior', 'package' => '$60–80k base + 40% commission + share scheme' ],
		[ 'title' => 'Team Manager — ModulR US', 'brand' => 'modulr', 'location' => 'Miami, FL', 'level' => 'Manager', 'package' => '$90–120k base + override + equity' ],
		[ 'title' => 'Recruitment Consultant — Life Sciences', 'brand' => 'verto', 'location' => 'Solent, UK', 'level' => 'Senior', 'package' => '£35–45k base + 40% commission + share scheme' ],
		[ 'title' => 'Talent & Resourcing Partner — Group', 'brand' => 'verto', 'location' => 'Solent, UK', 'level' => 'Entry-level', 'package' => '£24–27k base + bonus' ],
	];

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Jobs' ] );
		$this->add_control( 'heading', [ 'label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => "Roles we're hiring now." ] );
		$this->add_control( 'intro', [ 'label' => 'Intro', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => "These are seats on our own desks — not client vacancies. We also always want to hear from experienced consultants, even if the exact desk isn't listed." ] );
		$this->add_control( 'apply_url', [ 'label' => 'Job click-through URL', 'type' => \Elementor\Controls_Manager::URL, 'default' => [ 'url' => '/contact' ] ] );
		$this->add_control( 'vincere_shortcode', [ 'label' => 'Vincere shortcode (optional)', 'type' => \Elementor\Controls_Manager::TEXT, 'description' => 'Once the Vincere plugin is installed, paste its shortcode — it replaces the placeholder roles.' ] );
		$this->end_controls_section();
	}

	/**
	 * Live jobs synced from Vincere (includes/vincere.php). Empty array when
	 * no sync has run yet — the placeholder JOBS const is the fallback, so
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
				'title'    => (string) $job['title'],
				'brand'    => $brand,
				'location' => (string) ( $job['location'] ?? 'Flexible' ),
				'level'    => (string) ( $job['level'] ?? 'Senior' ),
				'package'  => (string) ( $job['package'] ?? 'Competitive package' ),
				'url'      => (string) ( $job['url'] ?? '' ),
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
		?>
		<div class="verto-jobs" data-verto-jobs>
			<div class="verto-intro">
				<div class="verto-jobs__eyebrow">Join Verto — internal roles</div>
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
							$b = self::BRANDS[ $job['brand'] ]; ?>
							<a class="verto-jobs__row verto-jobs__row--<?php echo esc_attr( $job['brand'] ); ?>"
							   href="<?php echo esc_url( ! empty( $job['url'] ) ? $job['url'] : $apply ); ?>"
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
									<span class="arrow" aria-hidden="true">↗</span>
								</div>
							</a>
						<?php endforeach; ?>
						<p class="verto-jobs__empty" hidden>No open roles match those filters right now — but send us a note anyway; half our hires start that way.</p>
					<?php endif; ?>
				</div>

				<aside class="verto-jobs__filters">
					<div class="verto-jobs__panel">
						<div class="verto-jobs__paneltop">
							<span class="verto-jobs__panellabel">Filter roles</span>
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
					<p class="verto-jobs__count"><span data-jobs-count><?php echo count( $jobs ); ?></span> roles shown · Can't see your desk? <a href="<?php echo esc_url( $apply ); ?>">Write to us anyway →</a></p>
				</aside>
			</div>
		</div>
		<?php
	}
}
