<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Process Rail — the prototype's numbered card/rail sections:
 *  - "zigzag": 4-up offset cards (candidate process) — alternate cards drop
 *    2rem, fg-6% tint, 3px brand top bar scale-x hover.
 *  - "cards3": 3-up staggered cards (hiring solutions / pillars) — middle
 *    card drops 2rem, optional kicker line + brand dash + bullet list.
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
			'options' => [ 'cards3' => '3-up staggered cards', 'zigzag' => '4-up zigzag cards', 'line' => 'Horizontal rail' ],
			'default' => 'cards3',
		] );
		$this->add_control( 'bg', [
			'label' => 'Section background', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'default' => 'Page background', 'muted' => 'Muted' ], 'default' => 'muted',
		] );
		$this->add_control( 'eyebrow', [ 'label' => 'Eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Hiring solutions' ] );
		$this->add_control( 'heading', [ 'label' => 'Heading (line breaks kept)', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => "Sized to the project.\nBuilt for the market." ] );
		$this->add_control( 'side_text', [ 'label' => 'Right-hand intro paragraph (optional)', 'type' => \Elementor\Controls_Manager::TEXTAREA,
			'default' => "We construct a tailored hiring plan to meet your requirements — whether you're filling one role or building an entire commercial team." ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'title', [ 'label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'kicker', [ 'label' => 'Kicker / tagline', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'body', [ 'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$rep->add_control( 'bullets', [ 'label' => 'Bullets (one per line)', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$this->add_control( 'items', [
			'label' => 'Steps', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ title }}}',
			'default' => [
				[
					'title'   => 'Engaged Search', 'kicker' => 'Our flagship model',
					'body'    => "A committed partnership with a structured process — market mapping, verified shortlists, offer management. Built to remove the chance of failure and get it right first time. 100% success rate on the Engage model.",
					'bullets' => "Exclusive partnership\nStructured milestones\nFrequent read-outs",
				],
				[
					'title'   => 'Retained Executive Search', 'kicker' => 'Director and C-suite mandates',
					'body'    => "Discreet, confidential search for VP, MD, director and C-suite appointments. Off-market approaches, NDA-protected mandates and full lifecycle stakeholder management for the roles that can't be advertised.",
					'bullets' => "Retained, fully confidential\nNDA-protected searches\nStakeholder & offer management",
				],
				[
					'title'   => 'Team Builds', 'kicker' => 'Partnerships, not placements',
					'body'    => 'When a new plant, project or region needs staffing from the ground up — we build the whole team. Proactively, against your timeline, reducing time-to-hire and the cost of the empty seat.',
					'bullets' => "Land-and-expand\nContract and permanent\nAgainst your project timeline",
				],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$layout = $s['layout'] ?: 'cards3';
		$bg     = 'muted' === $s['bg'] ? 'background:var(--muted);' : '';
		?>
		<div class="vbs-rail vbs-rail--<?php echo esc_attr( $layout ); ?>" style="<?php echo esc_attr( $bg ); ?>">
			<div class="container-wide">
				<div class="vbs-rail__head<?php echo $s['side_text'] ? ' vbs-rail__head--split' : ''; ?>">
					<div>
						<?php if ( $s['eyebrow'] ) : ?><span class="eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span><?php endif; ?>
						<h2 class="display-2 vbs-mt5"><?php echo nl2br( esc_html( $s['heading'] ) ); ?></h2>
					</div>
					<?php if ( $s['side_text'] ) : ?><p class="vbs-rail__side"><?php echo esc_html( $s['side_text'] ); ?></p><?php endif; ?>
				</div>

				<?php if ( 'line' === $layout ) : ?>
					<div class="vbs-rail__track">
						<div class="vbs-rail__connector" style="background:color-mix(in oklab, var(--brand) 30%, transparent);"></div>
						<div class="vbs-rail__cols">
							<?php foreach ( $s['items'] as $i => $it ) : ?>
								<div class="vbs-rail__stop">
									<span class="vbs-rail__dotmark" style="background:var(--brand);"></span>
									<div class="vbs-rail__stophead">
										<div class="vbs-rail__num" style="color:var(--brand);">0<?php echo (int) $i + 1; ?></div>
										<h3 class="vbs-rail__stoptitle"><?php echo esc_html( $it['title'] ); ?></h3>
									</div>
									<p class="vbs-rail__body"><?php echo esc_html( $it['body'] ); ?></p>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php else :
					$is3 = 'cards3' === $layout;
					?>
					<div class="<?php echo $is3 ? 'vbs-rail__grid3' : 'vbs-rail__grid4'; ?>">
						<?php foreach ( $s['items'] as $i => $it ) :
							$stagger = $is3 ? ( 1 === $i ) : ( 1 === $i % 2 );
							?>
							<div class="vbs-card<?php echo $is3 ? ' vbs-card--p8' : ' vbs-card--p7'; ?><?php echo $stagger ? ' vbs-card--drop' : ''; ?>" style="background:color-mix(in oklab, var(--foreground) 6%, var(--background));">
								<span class="vbs-card__bar" style="background:var(--brand);"></span>
								<?php if ( $is3 && $it['kicker'] ) : ?>
									<div class="vbs-rail__numrow">
										<div class="vbs-rail__num3" style="color:var(--brand);">0<?php echo (int) $i + 1; ?></div>
										<span class="vbs-dash" style="background:var(--brand);"></span>
									</div>
								<?php else : ?>
									<div class="<?php echo $is3 ? 'vbs-rail__num3' : 'vbs-rail__num'; ?>" style="color:var(--brand);">0<?php echo (int) $i + 1; ?></div>
								<?php endif; ?>
								<h3 class="<?php echo $is3 ? 'vbs-rail__title3' : 'vbs-rail__title4'; ?>"><?php echo esc_html( $it['title'] ); ?></h3>
								<?php if ( $is3 && $it['kicker'] ) : ?><p class="vbs-rail__kicker"><?php echo esc_html( $it['kicker'] ); ?></p><?php endif; ?>
								<p class="<?php echo $is3 ? 'vbs-rail__body3' : 'vbs-rail__body'; ?>"><?php echo esc_html( $it['body'] ); ?></p>
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
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
