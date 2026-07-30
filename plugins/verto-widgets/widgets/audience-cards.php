<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Audience Cards — "For companies" (ink card) + "For candidates"
 * (surface card) pair from the brand landing: rounded-3xl, p-10/12, kicker
 * at tracking 0.28em, 3xl-4xl display headline, bullets with brand dots,
 * btn-primary CTA with ArrowUpRight.
 */
class Verto_Widget_Audience_Cards extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-audience-cards'; }
	public function get_title() { return 'Verto Audience Cards'; }
	public function get_icon() { return 'eicon-call-to-action'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Audiences' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'style', [
			'label' => 'Card style', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'ink' => 'Ink (dark)', 'surface' => 'Surface' ], 'default' => 'ink',
		] );
		$rep->add_control( 'kicker', [ 'label' => 'Kicker', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'headline', [ 'label' => 'Headline', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 2 ] );
		$rep->add_control( 'body', [ 'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$rep->add_control( 'bullets', [ 'label' => 'Bullets (one per line)', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$rep->add_control( 'cta_text', [ 'label' => 'CTA text', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'cta_link', [ 'label' => 'CTA link', 'type' => \Elementor\Controls_Manager::URL ] );
		$this->add_control( 'items', [
			'label' => 'Cards', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ kicker }}}',
			'default' => [
				[
					'style'    => 'ink',
					'kicker'   => 'For companies',
					'headline' => "Your next project is out there. It just isn't advertised.",
					'body'     => "Whether you're scaling a data centre programme or shaping a skyline, one conversation opens doors that don't exist yet. Considered introductions, never CVs into the void — and discretion as standard.",
					'bullets'  => "Curated introductions, not mass outreach\nConfidential and NDA-grade search handled as standard\nProject team builds — contract and permanent\nLong-game relationships across the project pipeline",
					'cta_text' => 'Scale your project team',
					'cta_link' => [ 'url' => '/clients' ],
				],
				[
					'style'    => 'surface',
					'kicker'   => 'For candidates',
					'headline' => 'The best projects are rarely advertised.',
					'body'     => 'The best talent is rarely searching. Modulr exists in that gap — making precise, considered introductions rather than firing CVs into the void, and protecting reputations on every engagement.',
					'bullets'  => "Hyperscale, colo, US architecture and MEP opportunities\nExclusive, often NDA-protected briefs\nCareer trajectory advice across the full project lifecycle\nDiscreet, considered, never transactional",
					'cta_text' => 'Find your next project',
					'cta_link' => [ 'url' => '/candidates' ],
				],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="vbs-aud" style="background:var(--background);">
			<div class="container-wide vbs-aud__grid">
				<?php foreach ( $s['items'] as $it ) :
					$ink   = ( $it['style'] ?? 'ink' ) === 'ink';
					$style = $ink
						? 'background:var(--ink);color:var(--ink-foreground);'
						: 'background:var(--surface);color:var(--surface-foreground);';
					?>
					<article class="vbs-aud__card" style="<?php echo esc_attr( $style ); ?>">
						<div class="vbs-aud__kicker"><?php echo esc_html( $it['kicker'] ); ?></div>
						<h3 class="vbs-aud__headline"><?php echo nl2br( esc_html( $it['headline'] ) ); ?></h3>
						<p class="vbs-aud__body"><?php echo esc_html( $it['body'] ); ?></p>
						<ul class="vbs-aud__bullets">
							<?php foreach ( array_filter( array_map( 'trim', explode( "\n", (string) $it['bullets'] ) ) ) as $bp ) : ?>
								<li><span class="vbs-dot" style="background:var(--brand);"></span><span><?php echo esc_html( $bp ); ?></span></li>
							<?php endforeach; ?>
						</ul>
						<?php if ( $it['cta_text'] ) : ?>
							<a class="btn-base btn-primary vbs-aud__cta" href="<?php echo esc_url( $it['cta_link']['url'] ?? '#' ); ?>">
								<?php echo esc_html( $it['cta_text'] ); ?> <?php echo verto_icon( 'arrow-up-right', [ 'class' => 'vbs-icon-16' ] ); // phpcs:ignore ?>
							</a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
