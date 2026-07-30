<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Quote Band — the prototype's dark parallax bands: a full-bleed
 * parallax image behind an ink gradient wash, with eyebrow + headline
 * (+ optional accent span), body, CTAs, floating stat card, staggered
 * label/body columns (Mission/Vision/Purpose) or pull quotes.
 */
class Verto_Widget_Quote_Band extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-quote-band'; }
	public function get_title() { return 'Verto Quote Band'; }
	public function get_icon() { return 'eicon-blockquote'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Band' ] );
		$this->add_control( 'image', [ 'label' => 'Background image', 'type' => \Elementor\Controls_Manager::MEDIA ] );
		$this->add_control( 'image_alt', [ 'label' => 'Image alt', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ] );
		$this->add_control( 'overlay', [
			'label' => 'Gradient wash', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [
				'hero-left'   => 'Hero — ink from left (100deg)',
				'hero-right'  => 'Hero — ink from right (260deg)',
				'mission'     => 'Mission band (135deg, heavy)',
				'testimonial' => 'Testimonial band (135deg)',
				'case'        => 'Case study band (115deg)',
			],
			'default' => 'hero-left',
		] );
		$this->add_control( 'pad', [
			'label' => 'Vertical rhythm', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'hero' => 'Hero (pt-24/32 pb-24/32)', 'band' => 'Band (py-28)' ], 'default' => 'hero',
		] );
		$this->add_control( 'align', [
			'label' => 'Copy alignment', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'left' => 'Left', 'right' => 'Right' ], 'default' => 'left',
		] );
		$this->add_control( 'parallax_speed', [ 'label' => 'Parallax speed', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 0.3, 'step' => 0.01 ] );
		$this->add_control( 'eyebrow', [ 'label' => 'Eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'About Modulr' ] );
		$this->add_control( 'eyebrow_style', [
			'label' => 'Eyebrow colour', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'brand' => 'Brand', 'dim' => 'Dimmed foreground' ], 'default' => 'brand',
		] );
		$this->add_control( 'heading_pre', [ 'label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => 'The projects that define' ] );
		$this->add_control( 'heading_accent', [ 'label' => 'Heading accent (brand colour)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'a generation.' ] );
		$this->add_control( 'heading_post', [ 'label' => 'Heading line after accent', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Built by the right people.' ] );
		$this->add_control( 'heading_size', [
			'label' => 'Heading size', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'display-2' => 'Display 2', 'display-3' => 'Display 3' ], 'default' => 'display-2',
		] );
		$this->add_control( 'body', [ 'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXTAREA,
			'default' => "Considered introductions, not CVs into the void. — and it's how we've built Modulr into the firm clients and candidates in architecture & data centres reach out to first." ] );
		$this->add_control( 'cta1_text', [ 'label' => 'CTA 1 (solid)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ] );
		$this->add_control( 'cta1_link', [ 'label' => 'CTA 1 link', 'type' => \Elementor\Controls_Manager::URL ] );
		$this->add_control( 'cta2_text', [ 'label' => 'CTA 2 (outline)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ] );
		$this->add_control( 'cta2_link', [ 'label' => 'CTA 2 link', 'type' => \Elementor\Controls_Manager::URL ] );
		$this->add_control( 'stat_value', [ 'label' => 'Floating stat — value', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '3 regions' ] );
		$this->add_control( 'stat_label', [ 'label' => 'Floating stat — label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'UK, EU and US coverage' ] );

		$cols = new \Elementor\Repeater();
		$cols->add_control( 'label', [ 'label' => 'Label', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$cols->add_control( 'body', [ 'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$this->add_control( 'columns', [
			'label' => 'Staggered columns (Mission / Vision / Purpose)', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $cols->get_controls(), 'title_field' => '{{{ label }}}', 'default' => [],
		] );

		$quotes = new \Elementor\Repeater();
		$quotes->add_control( 'quote', [ 'label' => 'Quote', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$quotes->add_control( 'attribution', [ 'label' => 'Attribution', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$this->add_control( 'quotes', [
			'label' => 'Quotes', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $quotes->get_controls(), 'title_field' => '{{{ attribution }}}', 'default' => [],
		] );
		$this->end_controls_section();
	}

	private function overlay_css( string $key ): string {
		$map = [
			'hero-left'   => 'linear-gradient(100deg, var(--ink) 0%, color-mix(in oklab, var(--ink) 88%, transparent) 45%, color-mix(in oklab, var(--ink) 40%, transparent) 78%, transparent 100%)',
			'hero-right'  => 'linear-gradient(260deg, var(--ink) 0%, color-mix(in oklab, var(--ink) 88%, transparent) 45%, color-mix(in oklab, var(--ink) 40%, transparent) 78%, transparent 100%)',
			'mission'     => 'linear-gradient(135deg, var(--ink) 0%, color-mix(in oklab, var(--ink) 90%, transparent) 55%, color-mix(in oklab, var(--ink) 75%, transparent) 100%)',
			'testimonial' => 'linear-gradient(135deg, var(--ink) 0%, color-mix(in oklab, var(--ink) 92%, transparent) 55%, color-mix(in oklab, var(--ink) 70%, transparent) 100%)',
			'case'        => 'linear-gradient(115deg, var(--ink) 0%, color-mix(in oklab, var(--ink) 92%, transparent) 60%, color-mix(in oklab, var(--ink) 65%, transparent) 100%)',
		];
		return $map[ $key ] ?? $map['hero-left'];
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$eyebrow_style = 'dim' === $s['eyebrow_style']
			? 'color:color-mix(in oklab, var(--ink-foreground) 65%, transparent);'
			: 'color:var(--brand);';
		?>
		<div class="vbs-band" style="color:var(--ink-foreground);background:var(--ink);">
			<?php if ( ! empty( $s['image']['url'] ) ) : ?>
				<div class="verto-parallax" data-parallax-speed="<?php echo esc_attr( $s['parallax_speed'] !== '' ? $s['parallax_speed'] : 0.3 ); ?>">
					<img src="<?php echo esc_url( $s['image']['url'] ); ?>" alt="<?php echo esc_attr( $s['image_alt'] ); ?>" loading="lazy" />
				</div>
			<?php endif; ?>
			<div class="vbs-band__wash" style="background:<?php echo esc_attr( $this->overlay_css( $s['overlay'] ) ); ?>;"></div>
			<div class="container-wide vbs-band__inner vbs-band__inner--<?php echo esc_attr( $s['pad'] ?: 'hero' ); ?>">
				<div class="vbs-band__copy<?php echo 'right' === $s['align'] ? ' vbs-band__copy--right' : ''; ?>">
					<?php if ( $s['eyebrow'] ) : ?>
						<span class="eyebrow" style="<?php echo esc_attr( $eyebrow_style ); ?>"><?php echo esc_html( $s['eyebrow'] ); ?></span>
					<?php endif; ?>
					<h2 class="<?php echo esc_attr( $s['heading_size'] ?: 'display-2' ); ?> vbs-band__heading" style="color:var(--ink-foreground);">
						<?php
						echo nl2br( esc_html( $s['heading_pre'] ) );
						if ( $s['heading_accent'] ) {
							printf( ' <span style="color:var(--brand);">%s</span>', nl2br( esc_html( $s['heading_accent'] ) ) );
						}
						if ( $s['heading_post'] ) {
							printf( '<br />%s', esc_html( $s['heading_post'] ) );
						}
						?>
					</h2>
					<?php if ( $s['body'] ) : ?>
						<p class="vbs-band__body" style="color:var(--ink-foreground);"><?php echo esc_html( $s['body'] ); ?></p>
					<?php endif; ?>
					<?php if ( $s['cta1_text'] || $s['cta2_text'] ) : ?>
						<div class="vbs-band__ctas">
							<?php if ( $s['cta1_text'] ) : ?>
								<a class="btn-base btn-primary" href="<?php echo esc_url( $s['cta1_link']['url'] ?? '#' ); ?>"><?php echo esc_html( $s['cta1_text'] ); ?></a>
							<?php endif; ?>
							<?php if ( $s['cta2_text'] ) : ?>
								<a class="btn-base btn-ghost-outline" style="color:var(--ink-foreground);border-color:color-mix(in oklab, var(--ink-foreground) 30%, transparent);" href="<?php echo esc_url( $s['cta2_link']['url'] ?? '#' ); ?>"><?php echo esc_html( $s['cta2_text'] ); ?></a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $s['stat_value'] ) : ?>
					<div class="vbs-band__stat" style="background:var(--ink);color:var(--ink-foreground);border-radius:4px;box-shadow:0 30px 80px -30px rgba(0,0,0,0.6);border:1px solid color-mix(in oklab, var(--brand) 30%, transparent);">
						<span class="vbs-statcard__dash" style="background:var(--brand);"></span>
						<div class="vbs-band__statvalue" style="color:var(--brand);"><?php echo esc_html( $s['stat_value'] ); ?></div>
						<div class="vbs-band__statlabel"><?php echo esc_html( $s['stat_label'] ); ?></div>
					</div>
				<?php endif; ?>

				<?php if ( $s['columns'] ) : ?>
					<div class="vbs-band__cols">
						<?php foreach ( $s['columns'] as $i => $col ) :
							$shift = 1 === $i ? 'transform:translateY(1.5rem);' : ( 2 === $i ? 'transform:translateY(3rem);' : '' );
							?>
							<div class="vbs-band__col" style="border-color:color-mix(in oklab, var(--brand) 60%, transparent);<?php echo esc_attr( $shift ); ?>">
								<div class="vbs-band__collabel" style="color:var(--brand);"><?php echo esc_html( $col['label'] ); ?></div>
								<p class="vbs-band__colbody"><?php echo esc_html( $col['body'] ); ?></p>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( $s['quotes'] ) : ?>
					<div class="vbs-band__quotes">
						<?php foreach ( $s['quotes'] as $i => $q ) : ?>
							<figure class="vbs-band__quote<?php echo 1 === $i % 2 ? ' vbs-band__quote--drop' : ''; ?>">
								<div class="vbs-band__mark" style="color:var(--brand);">&quot;</div>
								<blockquote><?php echo esc_html( $q['quote'] ); ?></blockquote>
								<figcaption>— <?php echo esc_html( $q['attribution'] ); ?></figcaption>
							</figure>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
