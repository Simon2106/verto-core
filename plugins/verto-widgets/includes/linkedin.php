<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto LinkedIn feed → Insights (Nov 2026, Alex/Simon: "linkedin posts
 * pulling through to insights").
 *
 * The consultants post from their PERSONAL LinkedIn profiles with a brand
 * hashtag; those posts appear on the brand's Insights page intertwined with
 * the blog articles, ordered by date (Alex: "Yes that works"). Rolling out
 * to EL / ModulR / Vertek; Edison Lux first.
 *
 * LinkedIn has no public API for hashtag feeds, so the pipeline consumes an
 * RSS bridge (rss.app or similar turns a hashtag into a feed URL). Per-site
 * setup, one line in wp-config.php above the "stop editing" line:
 *
 *   define( 'VERTO_LINKEDIN_FEED_URL', 'https://rss.app/feeds/XXXXXXXX.xml' );
 *
 * Hourly cron (or `wp verto linkedin-sync`):
 *   - fetch_feed() the URL (SimplePie, WP-cached)
 *   - upsert each item as a normal WP post in the site's brand category,
 *     keyed by the item guid in _verto_li_guid so nothing duplicates
 *   - the post carries _verto_external_url (the LinkedIn post URL) and
 *     _verto_li_author; the posts-grid card badges itself "LinkedIn" and
 *     links straight out, and single views redirect to LinkedIn
 *   - the item's image (enclosure/media) is sideloaded as featured image
 *
 * Because the items become real posts in the brand category, the Insights
 * grid interleaves them with articles by date with no further wiring.
 */
class Verto_LinkedIn {

	const CRON_HOOK = 'verto_linkedin_sync';

	public static function boot() {
		add_action( self::CRON_HOOK, [ self::class, 'sync' ] );
		add_action( 'init', function () {
			if ( self::feed_url() && ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + 2 * MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
			}
		} );
		// Opening a LinkedIn item's WP permalink sends the visitor to the
		// real post on LinkedIn – the WP copy is only grid fuel.
		add_action( 'template_redirect', function () {
			if ( is_singular( 'post' ) ) {
				$ext = (string) get_post_meta( get_queried_object_id(), '_verto_external_url', true );
				if ( '' !== $ext ) {
					wp_redirect( esc_url_raw( $ext ), 301 );
					exit;
				}
			}
		} );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'verto linkedin-sync', function () {
				if ( ! self::feed_url() ) {
					\WP_CLI::error( 'VERTO_LINKEDIN_FEED_URL is not defined in wp-config.php on this site.' );
				}
				$n = self::sync();
				\WP_CLI::success( "LinkedIn sync complete – {$n} new post(s) imported." );
			} );
		}
	}

	public static function feed_url(): string {
		return defined( 'VERTO_LINKEDIN_FEED_URL' ) ? trim( (string) VERTO_LINKEDIN_FEED_URL ) : '';
	}

	/** Fetch the feed and upsert items as brand-category posts. Returns new-post count. */
	public static function sync(): int {
		$url = self::feed_url();
		if ( '' === $url ) {
			return 0;
		}
		include_once ABSPATH . WPINC . '/feed.php';
		add_filter( 'wp_feed_cache_transient_lifetime', $fresh = static fn() => 5 * MINUTE_IN_SECONDS );
		$feed = fetch_feed( $url );
		remove_filter( 'wp_feed_cache_transient_lifetime', $fresh );
		if ( is_wp_error( $feed ) ) {
			return 0;
		}

		$brand  = function_exists( 'verto_current_brand' ) ? verto_current_brand() : 'verto';
		$cat    = get_category_by_slug( $brand );
		$cat_id = $cat ? (int) $cat->term_id : (int) wp_create_category( $brand );
		$new    = 0;

		foreach ( $feed->get_items( 0, 20 ) as $item ) {
			$guid = (string) $item->get_id();
			$link = (string) $item->get_permalink();
			if ( '' === $guid ) {
				$guid = $link;
			}
			if ( '' === $guid ) {
				continue;
			}
			$exists = get_posts( [
				'post_type'      => 'post',
				'post_status'    => 'any',
				'numberposts'    => 1,
				'fields'         => 'ids',
				'meta_key'       => '_verto_li_guid',
				'meta_value'     => $guid,
				'no_found_rows'  => true,
			] );
			if ( $exists ) {
				continue;
			}

			$text   = trim( wp_strip_all_tags( (string) $item->get_description() ) );
			$title  = trim( wp_strip_all_tags( (string) $item->get_title() ) );
			if ( '' === $title ) {
				$title = wp_trim_words( $text, 12 );
			}
			$author = $item->get_author() ? trim( (string) $item->get_author()->get_name() ) : '';
			$date   = $item->get_date( 'Y-m-d H:i:s' ) ?: current_time( 'mysql' );

			$post_id = wp_insert_post( [
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => wp_trim_words( $title, 16 ),
				'post_excerpt'  => wp_trim_words( $text, 40 ),
				'post_content'  => esc_html( $text ) . "\n\n" . '<p><a href="' . esc_url( $link ) . '" rel="noopener">View on LinkedIn &rarr;</a></p>',
				'post_date'     => $date,
				'post_category' => $cat_id ? [ $cat_id ] : [],
			] );
			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}
			update_post_meta( $post_id, '_verto_li_guid', $guid );
			update_post_meta( $post_id, '_verto_external_url', $link );
			update_post_meta( $post_id, '_verto_li_author', $author );

			// Item image → featured image (best effort; the card has a
			// branded LinkedIn placeholder when there is none).
			$img = '';
			$enc = $item->get_enclosure();
			if ( $enc && $enc->get_link() && ( ! $enc->get_type() || 0 === strpos( (string) $enc->get_type(), 'image' ) ) ) {
				$img = (string) $enc->get_link();
			}
			if ( '' === $img && preg_match( '/<img[^>]+src="([^"]+)"/i', (string) $item->get_description(), $mch ) ) {
				$img = $mch[1];
			}
			if ( '' !== $img ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$att = media_sideload_image( esc_url_raw( $img ), $post_id, null, 'id' );
				if ( ! is_wp_error( $att ) ) {
					set_post_thumbnail( $post_id, (int) $att );
				}
			}
			$new++;
		}
		return $new;
	}
}

Verto_LinkedIn::boot();
