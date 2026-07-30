<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Feature Row — white 4-up strip under the brand hero (prototype
 * FEATURES): lucide icon in brand colour, display title, centred body
 * capped at 22ch, hairline #e6e6e6 dividers between columns.
 * Variant "stats": the clients-page stats strip (muted bg, 3 centred stats).
 */
class Verto_Widget_Feature_Row extends \Elementor\Widget_Base {

	public function get_name() { return 'verto-feature-row'; }
	public function get_title() { return 'Verto Feature Row'; }
	public function get_icon() { return 'eicon-columns'; }
	public function get_categories() { return [ 'verto' ]; }

	protected function register_controls() {
		$this->start_controls_section( 'content', [ 'label' => 'Features' ] );
		$this->add_control( 'variant', [
			'label' => 'Variant', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => [ 'features' => 'Feature row (white, icons)', 'stats' => 'Stats strip (muted, centred)' ],
			'default' => 'features',
		] );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'icon', [ 'label' => 'Icon (lucide slug)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'globe-2' ] );
		$rep->add_control( 'title', [ 'label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'rows' => 2 ] );
		$rep->add_control( 'body', [ 'label' => 'Body', 'type' => \Elementor\Controls_Manager::TEXTAREA ] );
		$this->add_control( 'items', [
			'label' => 'Items', 'type' => \Elementor\Controls_Manager::REPEATER,
			'fields' => $rep->get_controls(), 'title_field' => '{{{ title }}}',
			'default' => [
				[ 'icon' => 'globe-2',   'title' => 'UK, EU & US',              'body' => 'Hyperscale, colocation and celebrated US architecture — three regions, one network.' ],
				[ 'icon' => 'compass',   'title' => 'Curated Introductions',    'body' => 'Considered shortlists with real context. Never CVs into the void.' ],
				[ 'icon' => 'lock',      'title' => 'NDA-Grade Discretion',     'body' => 'Sensitive, pre-announcement and competitor-adjacent search handled as standard.' ],
				[ 'icon' => 'handshake', 'title' => 'Long-Game Relationships',  'body' => 'We track careers and project pipelines to add value before the urgent need arises.' ],
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		if ( ( $s['variant'] ?? 'features' ) === 'stats' ) {
			?>
			<div class="vbs-statstrip">
				<div class="container-wide vbs-statstrip__grid">
					<?php foreach ( $s['items'] as $it ) : ?>
						<div>
							<div class="vbs-statstrip__value" style="color:var(--brand);"><?php echo esc_html( $it['title'] ); ?></div>
							<div class="vbs-statstrip__label"><?php echo esc_html( $it['body'] ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
			return;
		}
		?>
		<div class="vbs-features" style="background:#ffffff;color:#0a0a0a;">
			<div class="container-wide vbs-features__pad">
				<div class="vbs-features__grid">
					<?php foreach ( $s['items'] as $i => $it ) : ?>
						<div class="vbs-feature"<?php echo $i > 0 ? ' style="border-left:1px solid #e6e6e6;"' : ''; ?>>
							<?php echo verto_icon( $it['icon'], [ 'class' => 'vbs-feature__icon', 'style' => 'color:var(--brand);' ] ); // phpcs:ignore ?>
							<h3 class="vbs-feature__title"><?php echo nl2br( esc_html( $it['title'] ) ); ?></h3>
							<p class="vbs-feature__body" style="color:#4a4a4a;max-width:22ch;"><?php echo esc_html( $it['body'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
