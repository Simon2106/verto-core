<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Hero — dark ink hero: eyebrow, line-reveal headline, body, two CTAs,
 * V-mask media on the right, optional count-up stats strip beneath.
 */
class Verto_Widget_Hero extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-hero'; }
	public function get_title() { return 'Verto Hero'; }
	public function get_icon() { return 'eicon-single-page'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'copy', [ 'label' => 'Copy' ] );
		$this->add_control( 'eyebrow', [
			'label' => 'Eyebrow', 'type' => \Elementor\Controls_Manager::TEXT,
			'default' => 'The Verto Group · Precision talent, specialist brands',
		] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'line', [ 'label' => 'Line', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$this->add_control( 'lines', [
			'label' => 'Headline lines', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ line }}}',
			'default' => [
				[ 'line' => 'Precision talent.' ],
				[ 'line' => 'Specialist brands.' ],
				[ 'line' => 'One group.' ],
			],
		] );
		$this->add_control( 'body', [
			'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXTAREA,
			'default' => 'Verto builds high-performance teams for the industries that keep everything else running — energy, engineering and the built environment. Three focused brands. One process-driven standard.',
		] );
		$this->add_control( 'cta1_text', [ 'label' => 'Primary CTA text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Join us' ] );
		$this->add_control( 'cta1_link', [ 'label' => 'Primary CTA link', 'type' => \Elementor\Controls_Manager::URL, 'default' => [ 'url' => '/careers' ] ] );
		$this->add_control( 'cta2_text', [ 'label' => 'Secondary CTA text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Explore the brands' ] );
		$this->add_control( 'cta2_link', [ 'label' => 'Secondary CTA link', 'type' => \Elementor\Controls_Manager::URL, 'default' => [ 'url' => '/brands' ] ] );
		$this->add_control( 'pillars', [
			'label' => 'Pillars line (separated with |)', 'type' => \Elementor\Controls_Manager::TEXT,
			'default' => 'US Energy|Technical Sales & Engineering|Architecture & Data Centres',
		] );
		$this->end_controls_section();

		$this->start_controls_section( 'media', [ 'label' => 'V-Mask media' ] );
		$this->add_control( 'video', [ 'label' => 'Video (mp4)', 'type' => \Elementor\Controls_Manager::MEDIA, 'media_types' => [ 'video' ] ] );
		$this->add_control( 'poster', [ 'label' => 'Poster / image fallback', 'type' => \Elementor\Controls_Manager::MEDIA ] );
		$this->end_controls_section();

		$this->start_controls_section( 'stats', [ 'label' => 'Stats strip' ] );
		$srep = new \Elementor\Repeater();
		$srep->add_control( 'value', [ 'label' => 'Value (number counts up; text shows as-is)', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$srep->add_control( 'suffix', [ 'label' => 'Suffix (%, ×, +)', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$srep->add_control( 'label', [ 'label' => 'Label', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$this->add_control( 'stat_items', [
			'label' => 'Stats', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $srep->get_controls(), 'title_field' => '{{{ value }}}{{{ suffix }}} — {{{ label }}}',
			'default' => [
				[ 'value' => '40', 'suffix' => '%', 'label' => "Commission — one of the market's best splits" ],
				[ 'value' => 'Equity', 'suffix' => '', 'label' => 'Share scheme — everyone owns a piece' ],
				[ 'value' => '2', 'suffix' => '×', 'label' => 'Holiday incentives every year' ],
				[ 'value' => 'UK · US', 'suffix' => '', 'label' => 'International relocation opportunities' ],
			],
		] );
		$this->end_controls_section();
	}

	private function v_mask_url(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 81 80"><g fill="#fff">'
			. '<polygon points="48.81,66.34 43.18,76.08 1.64,4.14 12.9,4.14 34.11,40.89 37.08,46.04 48.81,66.34"/>'
			. '<polygon points="37.59,66.34 43.22,76.08 79.35,13.51 68.09,13.51 52.28,40.89 49.31,46.04 37.59,66.34"/>'
			. '<polygon points="48.81,39.2 43.2,48.91 43.18,48.94 22.72,13.51 33.97,13.51 34.11,13.75 37.08,18.9 43.2,29.49 48.81,39.2"/>'
			. '<polygon points="69.09,4.14 43.22,48.94 43.2,48.91 37.59,39.2 43.2,29.49 49.31,18.9 52.28,13.75 57.83,4.14 69.09,4.14"/>'
			. '</g></svg>';
		return "url('data:image/svg+xml," . rawurlencode( $svg ) . "')";
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$mask = $this->v_mask_url();
		?>
		<section class="verto-hero">
			<div class="verto-hero__inner">
				<div class="verto-hero__media" style="-webkit-mask-image:<?php echo $mask; ?>;mask-image:<?php echo $mask; ?>;-webkit-mask-size:contain;mask-size:contain;-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-position:center;mask-position:center;">
					<?php if ( ! empty( $s['video']['url'] ) ) : ?>
						<video class="verto-autoplay" src="<?php echo esc_url( $s['video']['url'] ); ?>"<?php echo ! empty( $s['poster']['url'] ) ? ' poster="' . esc_url( $s['poster']['url'] ) . '"' : ''; ?> autoplay muted loop playsinline preload="auto" style="width:100%;height:100%;object-fit:cover;"></video>
					<?php elseif ( ! empty( $s['poster']['url'] ) ) : ?>
						<img src="<?php echo esc_url( $s['poster']['url'] ); ?>" alt="" aria-hidden="true" style="width:100%;height:100%;object-fit:cover;" />
					<?php endif; ?>
					<div style="position:absolute;inset:0;background:color-mix(in oklab, var(--ink) 20%, transparent);"></div>
				</div>
				<div class="verto-container">
					<div class="verto-hero__copy">
						<div class="verto-hero__eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></div>
						<h1 class="verto-title-reveal verto-display-2" style="margin-top:2rem;">
							<?php foreach ( $s['lines'] as $i => $item ) : ?>
								<span class="line-mask"><span class="line-inner" style="transition-delay:<?php echo (int) $i * 110; ?>ms"><?php echo esc_html( $item['line'] ); ?></span></span>
							<?php endforeach; ?>
						</h1>
						<p class="verto-hero__body"><?php echo esc_html( $s['body'] ); ?></p>
						<div class="verto-hero__ctas">
							<?php if ( $s['cta1_text'] ) : ?>
								<a class="verto-btn verto-btn--primary" href="<?php echo esc_url( $s['cta1_link']['url'] ?? '#' ); ?>"><?php echo esc_html( $s['cta1_text'] ); ?></a>
							<?php endif; ?>
							<?php if ( $s['cta2_text'] ) : ?>
								<a class="verto-btn verto-btn--outline" href="<?php echo esc_url( $s['cta2_link']['url'] ?? '#' ); ?>"><?php echo esc_html( $s['cta2_text'] ); ?></a>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $s['pillars'] ) ) :
							$pillars = array_map( 'trim', explode( '|', $s['pillars'] ) ); ?>
							<p class="verto-hero__pillars">
								<?php foreach ( $pillars as $pi => $pillar ) : ?>
									<?php if ( $pi > 0 ) : ?><span class="dot">·</span><?php endif; ?>
									<span class="verto-pillar-glow" style="animation-delay:<?php echo esc_attr( $pi * 1.6 ); ?>s"><?php echo esc_html( $pillar ); ?></span>
								<?php endforeach; ?>
							</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php if ( ! empty( $s['stat_items'] ) ) : ?>
				<div class="verto-container">
					<div class="verto-stats">
						<?php foreach ( $s['stat_items'] as $stat ) : ?>
							<div class="verto-stats__item">
								<div class="verto-stats__value"<?php echo is_numeric( $stat['value'] ) ? ' data-countup="' . esc_attr( $stat['value'] ) . '" data-suffix="' . esc_attr( $stat['suffix'] ) . '"' : ''; ?>>
									<?php echo esc_html( $stat['value'] . $stat['suffix'] ); ?>
								</div>
								<div class="verto-stats__label"><?php echo esc_html( $stat['label'] ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}
}
