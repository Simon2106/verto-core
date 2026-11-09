<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Process Rail – the prototype's numbered card/rail sections:
 *  - "zigzag": 4-up offset cards (candidate process / the four hiring
 *    models) – alternate cards drop 2rem, fg-6% tint, 3px brand top bar
 *    scale-x hover, optional kicker + bullets + flagship badge.
 *  - "cards3": 3-up staggered cards (pillars) – middle card drops 2rem,
 *    optional kicker line + brand dash + bullet list.
 *  - "line": horizontal rail with connector line and brand dots
 *    (client process / journey).
 */
class Verto_Widget_Process_Rail extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-process-rail'; }
	public function get_title() { return 'Verto Process Rail'; }
	public function get_icon() { return 'eicon-time-line'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Process' ] );
		$this->add_control( 'layout', [
			'label' => 'Layout', 'type' => \Elementor\Controls_Manager::SELECT,
			// Round 9, item 17: "row4" – one flat 4-up row, equal height, no
			// stagger, accent bars in var(--brand-gradient, var(--brand)).
			'options' => [ 'cards3' => '3-up staggered cards', 'zigzag' => '4-up zigzag cards', 'row4' => '4-up flat row (equal height, gradient accents)', 'line' => 'Horizontal rail' ],
			'default' => 'cards3',
		] );
		$this->add_control( 'line_style', [
			'label' => 'Rail style (Horizontal rail only)', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [
				'process' => 'Process – 01 number + title',
				'journey' => 'Journey – brand-colour year, no number',
			],
			'default' => 'process',
		] );
		$this->add_control( 'bg', [
			'label' => 'Section background', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'default' => 'Page background', 'muted' => 'Muted' ], 'default' => 'muted',
		] );
		$this->add_control( 'eyebrow', [ 'label' => 'Eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Hiring solutions' ] );
		$this->add_control( 'heading', [ 'label' => 'Heading (line breaks kept)', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => "Sized to the project.\nBuilt for the market." ] );
		$this->add_control( 'cta_text', [ 'label' => 'CTA under the cards (row4, optional)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ] );
		$this->add_control( 'cta_link', [ 'label' => 'CTA link', 'type' => \Elementor\Controls_Manager::URL ] );
		$this->add_control( 'side_text', [ 'label' => 'Right-hand intro paragraph (optional)', 'type' => \Elementor\Controls_Manager::TEXTAREA,
			'default' => "We construct a tailored hiring plan to meet your requirements – whether you're filling one role or building an entire commercial team." ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'title', [ 'label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'kicker', [ 'label' => 'Kicker / tagline', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'badge', [ 'label' => 'Badge (e.g. "Our flagship" – marks the lead card)', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'body', [ 'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$rep->add_control( 'bullets', [ 'label' => 'Bullets (one per line)', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		// Round 17 (Nov 2026, Alex/Dan): headed USP pairs – "Label | text" per
		// line, rendered as a bold label with its own description underneath
		// (the Engage / Contract service-table breakdown).
		$rep->add_control( 'usps', [ 'label' => 'USPs ("Label | text" per line)', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$this->add_control( 'items', [
			'label' => 'Steps', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ title }}}',
			// Round 17 (Nov 2026, Alex/Dan): Engage carries the USP breakdown,
			// Contract the generic contractor copy, Contingent drops "The
			// classic model" and moves to the end.
			'default' => [
				[
					'title'   => 'Verto Engage', 'kicker' => 'A true partnership', 'badge' => 'Our flagship',
					'body'    => '',
					'bullets' => '',
					'usps'    => "Performance Guarantee | We commit to delivering qualified, interview-ready talent. 100% success rate – our most certain hiring option.\nReplacement Guarantee | 6 months of protection on every successful placement.\nShared Commitment | An upfront engagement secures dedicated resources, priority delivery and a committed search partnership.\nCo-Branded Adverts | Leverage our network with your branding. Wider reach with quality results.",
				],
				[
					'title'   => 'Exclusive', 'kicker' => 'One search, one partner',
					'body'    => 'We commit senior resource to an exclusive brief and you get a faster, deeper shortlist without managing multiple agencies.',
					'bullets' => "Senior resource on the brief\nFaster, deeper shortlists\nOne process to manage",
				],
				[
					'title'   => 'Contract', 'kicker' => 'Interim & project specialists',
					'body'    => 'Providing skilled contractors and interim professionals to support business-critical operations, project delivery and changing workforce demands.',
					'bullets' => '',
					'usps'    => "Rapid Deployment | Skilled professionals ready when you need them.\nSpecialist Expertise | Access to experienced technical, engineering and commercial talent.\nFlexible Solutions | Supporting projects, interim cover and peaks in demand.\nCompliant Engagement | Reliable contractor management and payroll solutions.",
				],
				[
					'title'   => 'Contingent', 'kicker' => '',
					'body'    => 'We search, you interview, and a fee applies only when you hire. Backed by a free-replacement guarantee.',
					'bullets' => "You only pay on a hire\nFree-replacement guarantee\nSimple to switch on",
				],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$layout = $s['layout'] ?: 'cards3';
		$bg     = 'muted' === $s['bg'] ? 'background:var(--muted);' : '';
		// v0.22.4 (client): the row4 four-models section is simplified – the
		// eyebrow loses its dash, the right-hand intro paragraph is gone
		// (simple single-column head), and the cards drop their 01-04
		// numbers. Other layouts keep the original treatment.
		$flat      = 'row4' === $layout;
		$side_text = $flat ? '' : $s['side_text'];
		?>
		<div class="vbs-rail vbs-rail--<?php echo esc_attr( $layout ); ?>" style="<?php echo esc_attr( $bg ); ?>">
			<div class="container-wide">
				<div class="vbs-rail__head<?php echo $side_text ? ' vbs-rail__head--split' : ''; ?>">
					<div>
						<?php if ( $s['eyebrow'] ) : ?><span class="eyebrow<?php echo $flat ? ' eyebrow--plain' : ''; ?>"><?php echo esc_html( $s['eyebrow'] ); ?></span><?php endif; ?>
						<h2 class="display-2 vbs-mt5"><?php echo nl2br( esc_html( $s['heading'] ) ); ?></h2>
					</div>
					<?php if ( $side_text ) : ?><p class="vbs-rail__side"><?php echo esc_html( $side_text ); ?></p><?php endif; ?>
				</div>

				<?php if ( 'line' === $layout ) :
					$journey = 'journey' === ( $s['line_style'] ?? 'process' );
					?>
					<div class="vbs-rail__track">
						<div class="vbs-rail__connector" style="background:color-mix(in oklab, var(--brand) 30%, transparent);"></div>
						<div class="vbs-rail__cols">
							<?php foreach ( $s['items'] as $i => $it ) : ?>
								<div class="vbs-rail__stop">
									<span class="vbs-rail__dotmark" style="background:var(--brand);"></span>
									<?php if ( $journey ) : // prototype journey rail: brand-colour year, no 01 index ?>
										<div class="vbs-rail__year" style="color:var(--brand);"><?php echo esc_html( $it['title'] ); ?></div>
									<?php else : ?>
										<div class="vbs-rail__stophead">
											<div class="vbs-rail__num" style="color:var(--brand);">0<?php echo (int) $i + 1; ?></div>
											<h3 class="vbs-rail__stoptitle"><?php echo esc_html( $it['title'] ); ?></h3>
										</div>
									<?php endif; ?>
									<p class="vbs-rail__body"><?php echo esc_html( $it['body'] ); ?></p>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php else :
					$is3  = 'cards3' === $layout;
					$row4 = 'row4' === $layout; // round 9, item 17: flat equal-height row
					?>
					<div class="<?php echo $is3 ? 'vbs-rail__grid3' : 'vbs-rail__grid4'; ?><?php echo $row4 ? ' vbs-rail__grid4--flat' : ''; ?>">
						<?php foreach ( $s['items'] as $i => $it ) :
							$stagger = $row4 ? false : ( $is3 ? ( 1 === $i ) : ( 1 === $i % 2 ) );
							$badge   = trim( (string) ( $it['badge'] ?? '' ) );
							$bar_bg  = $row4 ? 'var(--brand-gradient, var(--brand))' : 'var(--brand)';
							?>
							<div class="vbs-card<?php echo $is3 ? ' vbs-card--p8' : ' vbs-card--p7'; ?><?php echo $stagger ? ' vbs-card--drop' : ''; ?><?php echo $badge ? ' vbs-card--lead' : ''; ?>" style="background:color-mix(in oklab, var(--foreground) 6%, var(--background));">
								<span class="vbs-card__bar" style="background:<?php echo esc_attr( $bar_bg ); ?>;"></span>
								<?php // Oct 2026 (client): on row4 the badge moves BELOW the title so all four titles sit in line. ?>
								<?php if ( $badge && ! $row4 ) : ?><span class="vbs-card__badge"><?php echo esc_html( $badge ); ?></span><?php endif; ?>
								<?php // v0.22.4 (client): row4 cards carry no 01-04 number and no dash decoration – title / kicker / body / bullets only. ?>
								<?php if ( ! $row4 ) : ?>
									<?php if ( $is3 && $it['kicker'] ) : ?>
										<div class="vbs-rail__numrow">
											<div class="vbs-rail__num3" style="color:var(--brand);">0<?php echo (int) $i + 1; ?></div>
											<span class="vbs-dash" style="background:var(--brand);"></span>
										</div>
									<?php else : ?>
										<div class="<?php echo $is3 ? 'vbs-rail__num3' : 'vbs-rail__num'; ?>" style="color:var(--brand);">0<?php echo (int) $i + 1; ?></div>
									<?php endif; ?>
								<?php endif; ?>
								<?php // Round 17 (Nov 2026, Alex): row4 wraps the title/badge/kicker in a
								// fixed-height head so the paragraphs below all start in line. ?>
								<?php if ( $row4 ) : ?><div class="vbs-card__head"><?php endif; ?>
								<h3 class="<?php echo $is3 ? 'vbs-rail__title3' : 'vbs-rail__title4'; ?>"><?php echo esc_html( $it['title'] ); ?></h3>
								<?php if ( $badge && $row4 ) : ?><span class="vbs-card__badge vbs-card__badge--inline"><?php echo esc_html( $badge ); ?></span><?php endif; ?>
								<?php if ( $it['kicker'] ) : ?><p class="vbs-rail__kicker"><?php echo esc_html( $it['kicker'] ); ?></p><?php endif; ?>
								<?php if ( $row4 ) : ?></div><?php endif; ?>
								<?php if ( trim( (string) $it['body'] ) ) : ?><p class="<?php echo $is3 ? 'vbs-rail__body3' : 'vbs-rail__body'; ?>"><?php echo esc_html( $it['body'] ); ?></p><?php endif; ?>
								<?php
								// Round 17: headed USPs ("Label | text" per line).
								$usps = array_filter( array_map( 'trim', explode( "\n", (string) ( $it['usps'] ?? '' ) ) ) );
								if ( $usps ) : ?>
									<div class="vbs-rail__usps">
										<?php foreach ( $usps as $u ) :
											$parts = array_map( 'trim', explode( '|', $u, 2 ) ); ?>
											<div class="vbs-usp">
												<strong class="vbs-usp__label"><?php echo esc_html( $parts[0] ); ?></strong>
												<?php if ( ! empty( $parts[1] ) ) : ?><span class="vbs-usp__text"><?php echo esc_html( $parts[1] ); ?></span><?php endif; ?>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
								<?php
								$bullets = array_filter( array_map( 'trim', explode( "\n", (string) $it['bullets'] ) ) );
								if ( $bullets ) : ?>
									<ul class="vbs-rail__bullets">
										<?php foreach ( $bullets as $bp ) : ?>
											<li><span class="vbs-dot" style="background:var(--brand);"></span><span><?php echo esc_html( $bp ); ?></span></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
					<?php // Nov 2026 (Alex): optional CTA under the four models. ?>
					<?php if ( $flat && ! empty( $s['cta_text'] ) ) : ?>
						<div class="vbs-rail__cta"><a class="btn-base btn-primary" href="<?php echo esc_url( $s['cta_link']['url'] ?? '#' ); ?>"><?php echo esc_html( $s['cta_text'] ); ?></a></div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
