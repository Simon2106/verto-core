<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto About Split — the prototype's split sections:
 *  - "landing": brand-landing About block (#f3f3f5 bg, copy left with brand
 *    dash + eyebrow, 4xl-5xl headline, mission, outline CTA | image right with
 *    the black stats overlay card: #0a0a0a, radius 4px, 0 30px 80px -30px shadow).
 *  - "panel": SplitFeature port (white copy panel, parallax image with optional
 *    grayscale, same black stat card, reversible).
 *  - "story": About-page editorial split (narrative 7 cols, logo + stats column
 *    with vertical rule 5 cols).
 */
class Verto_Widget_About_Split extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-about-split'; }
	public function get_title() { return 'Verto About Split'; }
	public function get_icon() { return 'eicon-image-box'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Split' ] );
		$this->add_control( 'variant', [
			'label' => 'Variant', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'landing' => 'Landing about (#f3f3f5 + stats card)', 'panel' => 'Split feature (white panel)', 'story' => 'Story (narrative + stats column)' ],
			'default' => 'landing',
		] );
		$this->add_control( 'eyebrow', [ 'label' => 'Eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'About Modulr' ] );
		$this->add_control( 'headline', [ 'label' => 'Headline', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Considered introductions, not CVs into the void.' ] );
		$this->add_control( 'headline_italic', [ 'label' => 'Italic headline tail (story)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ] );
		$this->add_control( 'body', [ 'label' => 'Body (blank line = new paragraph)', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 8,
			'default' => "Build teams for the projects that will define a generation — staffing what others can't, working quickly and discreetly, and to the standard those projects demand." ] );
		$this->add_control( 'cta_text', [ 'label' => 'CTA text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Learn more about us' ] );
		$this->add_control( 'cta_link', [ 'label' => 'CTA link', 'type' => \Elementor\Controls_Manager::URL, 'default' => [ 'url' => '/about' ] ] );
		$this->add_control( 'image', [ 'label' => 'Image', 'type' => \Elementor\Controls_Manager::MEDIA ] );
		$this->add_control( 'image_alt', [ 'label' => 'Image alt', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Data centre corridor lined with server racks and glowing status lights' ] );
		$this->add_control( 'logo', [ 'label' => 'Brand logo (story variant)', 'type' => \Elementor\Controls_Manager::MEDIA ] );
		$this->add_control( 'reverse', [ 'label' => 'Reverse (image left)', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '' ] );
		$this->add_control( 'grayscale', [ 'label' => 'Grayscale image (panel)', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '' ] );
		$this->add_control( 'panel_bg', [ 'label' => 'Panel background (panel)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '#ffffff' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'value', [ 'label' => 'Value', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'label', [ 'label' => 'Label', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$this->add_control( 'stats', [
			'label' => 'Stats', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ value }}}',
			'default' => [
				[ 'value' => '3 regions',      'label' => 'UK, EU and US coverage' ],
				[ 'value' => 'Full lifecycle', 'label' => 'Concept design to commissioning' ],
				[ 'value' => 'NDA-grade',      'label' => 'Discretion on every search' ],
			],
		] );
		$this->end_controls_section();
	}

	private function paragraphs( string $body ): array {
		$parts = preg_split( '/\n\s*\n/', trim( (string) $body ) );
		return array_values( array_filter( array_map( 'trim', (array) $parts ) ) );
	}

	private function stat_card( array $stats ): void {
		if ( ! $stats ) return;
		echo '<div class="vbs-statcard">';
		foreach ( $stats as $i => $st ) {
			printf( '<div class="vbs-statcard__row%s">', $i > 0 ? ' vbs-statcard__row--sep' : '' );
			echo '<span class="vbs-statcard__dash" style="background:var(--brand);"></span>';
			printf( '<div class="vbs-statcard__value" style="color:var(--brand);">%s</div>', esc_html( $st['value'] ) );
			printf( '<div class="vbs-statcard__label">%s</div>', esc_html( $st['label'] ) );
			echo '</div>';
		}
		echo '</div>';
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$variant = $s['variant'] ?: 'landing';
		$img_url = $s['image']['url'] ?? '';

		if ( 'story' === $variant ) {
			?>
			<div class="vbs-story">
				<div class="container-wide">
					<div class="vbs-story__grid">
						<div class="vbs-story__narr">
							<header class="vbs-story__head">
								<span class="vbs-story__kick" style="color:color-mix(in oklab, var(--foreground) 50%, transparent);"><?php echo esc_html( $s['eyebrow'] ); ?></span>
								<h2 class="vbs-story__headline"><?php echo esc_html( $s['headline'] ); ?><?php if ( $s['headline_italic'] ) : ?> <span class="vbs-italic"><?php echo esc_html( $s['headline_italic'] ); ?></span><?php endif; ?></h2>
							</header>
							<div class="vbs-story__paras" style="color:color-mix(in oklab, var(--foreground) 80%, transparent);">
								<?php foreach ( $this->paragraphs( $s['body'] ) as $p ) : ?><p><?php echo esc_html( $p ); ?></p><?php endforeach; ?>
							</div>
						</div>
						<div class="vbs-story__side">
							<?php if ( ! empty( $s['logo']['url'] ) ) : ?>
								<img class="vbs-story__logo" src="<?php echo esc_url( $s['logo']['url'] ); ?>" alt="" loading="lazy" />
							<?php endif; ?>
							<div class="vbs-story__statcol" style="border-color:color-mix(in oklab, var(--foreground) 12%, transparent);">
								<?php foreach ( array_slice( $s['stats'], 0, 3 ) as $i => $st ) : ?>
									<div class="vbs-story__stat">
										<div class="vbs-story__statrow">
											<span class="vbs-story__statvalue<?php echo strlen( $st['value'] ) > 6 ? ' vbs-story__statvalue--sm' : ''; ?>"><?php echo esc_html( $st['value'] ); ?></span>
											<?php if ( 0 === $i ) : ?><span class="vbs-dot" style="background:var(--brand);margin-bottom:0.25rem;"></span><?php endif; ?>
										</div>
										<p class="vbs-story__statlabel" style="color:color-mix(in oklab, var(--foreground) 60%, transparent);"><?php echo esc_html( $st['label'] ); ?></p>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php
			return;
		}

		$is_panel = 'panel' === $variant;
		$copy     = function () use ( $s, $is_panel ) {
			?>
			<div class="vbs-asplit__copywrap"<?php echo $is_panel && $s['panel_bg'] ? ' style="background:' . esc_attr( $s['panel_bg'] ) . ';"' : ''; ?>>
				<div class="vbs-asplit__copy">
					<?php if ( $s['eyebrow'] ) : ?>
						<div class="vbs-asplit__kick">
							<span class="vbs-dash" style="background:var(--brand);"></span>
							<span class="vbs-kicker" style="color:var(--brand);"><?php echo esc_html( $s['eyebrow'] ); ?></span>
						</div>
					<?php endif; ?>
					<h2 class="vbs-asplit__headline"><?php echo esc_html( $s['headline'] ); ?></h2>
					<div class="vbs-asplit__body<?php echo $is_panel ? ' vbs-asplit__body--panel' : ''; ?>"<?php echo $is_panel ? '' : ' style="color:#3a3a3a;"'; ?>>
						<?php foreach ( $this->paragraphs( $s['body'] ) as $p ) : ?><p><?php echo esc_html( $p ); ?></p><?php endforeach; ?>
					</div>
					<?php if ( $s['cta_text'] ) : ?>
						<a class="vbs-outline-cta" style="border-color:var(--brand);color:var(--brand);" href="<?php echo esc_url( $s['cta_link']['url'] ?? '#' ); ?>"><?php echo esc_html( $s['cta_text'] ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php
		};
		$image    = function () use ( $s, $is_panel, $img_url ) {
			?>
			<div class="vbs-asplit__imgwrap">
				<?php if ( $img_url ) : ?>
					<?php if ( $is_panel ) : ?>
						<div class="verto-parallax" data-parallax-speed="0.2">
							<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $s['image_alt'] ); ?>" loading="lazy"<?php echo $s['grayscale'] ? ' style="filter:grayscale(100%);"' : ''; ?> />
						</div>
					<?php else : ?>
						<img class="vbs-asplit__img" src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $s['image_alt'] ); ?>" loading="lazy" />
					<?php endif; ?>
				<?php endif; ?>
				<?php $this->stat_card( 'landing' === ( $s['variant'] ?: 'landing' ) || $s['stats'] ? $s['stats'] : [] ); ?>
			</div>
			<?php
		};
		?>
		<div class="vbs-asplit<?php echo $s['reverse'] ? ' vbs-asplit--reverse' : ''; ?>"<?php echo $is_panel ? ' style="color:#0a0a0a;"' : ' style="background:#f3f3f5;color:#0a0a0a;"'; ?>>
			<div class="vbs-asplit__grid">
				<?php
				if ( $s['reverse'] ) { $image(); $copy(); }
				else { $copy(); $image(); }
				?>
			</div>
		</div>
		<?php
	}
}
