<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Specialisms — "What we cover." grid (prototype brand landing):
 * 6 cards on foreground-6% tint, 3px brand top bar that scales in from the
 * left on hover (500ms), lucide icon, 01–06 numbering, display title, body.
 */
class Verto_Widget_Specialisms extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-specialisms'; }
	public function get_title() { return 'Verto Specialisms'; }
	public function get_icon() { return 'eicon-gallery-grid'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Specialisms' ] );
		$this->add_control( 'eyebrow', [ 'label' => 'Eyebrow', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Specialisms' ] );
		$this->add_control( 'heading', [ 'label' => 'Heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'What we cover.' ] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'icon', [ 'label' => 'Icon (lucide slug)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'server' ] );
		$rep->add_control( 'title', [ 'label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXT ] );
		$rep->add_control( 'description', [ 'label' => 'Description', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$this->add_control( 'items', [
			'label' => 'Items', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ title }}}',
			'default' => [
				[ 'icon' => 'server',          'title' => 'Hyperscale Data Centres', 'description' => 'Construction directors, regional heads and project leadership across operators, developers and contractors.' ],
				[ 'icon' => 'network',         'title' => 'Colocation & Edge',       'description' => 'Delivery and operations talent for colo and edge programmes at every stage.' ],
				[ 'icon' => 'building-2',      'title' => 'US Architecture',         'description' => 'Registered architects, project architects, directors, principals and partners.' ],
				[ 'icon' => 'zap',             'title' => 'MEP Engineering',         'description' => 'Mechanical, electrical and plumbing leadership across the US project landscape.' ],
				[ 'icon' => 'layers',          'title' => 'Project Lifecycle',       'description' => 'CD → SD → DD → CD → CA. Concept design through construction administration.' ],
				[ 'icon' => 'heart-handshake', 'title' => 'Inclusion & EDI',         'description' => 'Championing women in architecture and EDI across technical built-environment roles.' ],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="vbs-spec" style="background:var(--background);">
			<div class="container-wide">
				<div class="vbs-spec__head">
					<span class="eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span>
					<h2 class="display-2 vbs-mt5"><?php echo esc_html( $s['heading'] ); ?></h2>
				</div>
				<div class="vbs-spec__grid">
					<?php foreach ( $s['items'] as $i => $it ) : ?>
						<div class="vbs-card" style="background:color-mix(in oklab, var(--foreground) 6%, var(--background));">
							<span class="vbs-card__bar" style="background:var(--brand);"></span>
							<?php echo verto_icon( $it['icon'], [ 'class' => 'vbs-spec__icon', 'style' => 'color:var(--brand);' ] ); // phpcs:ignore ?>
							<div class="vbs-spec__num">0<?php echo (int) $i + 1; ?></div>
							<h3 class="vbs-spec__title"><?php echo esc_html( $it['title'] ); ?></h3>
							<p class="vbs-spec__body"><?php echo esc_html( $it['description'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
