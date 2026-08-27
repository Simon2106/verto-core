<?php
defined( 'ABSPATH' ) || exit;

/**
 * Vincere CRM integration — pulls OPEN jobs from the client's Vincere tenant
 * into the `verto_job` custom post type, which the Jobs Board widget renders
 * in place of its baked-in placeholder roles.
 *
 * ── Credentials ──────────────────────────────────────────────────────────
 * NO credentials live in this repo. Everything comes from wp-config.php:
 *
 *   define( 'VINCERE_TENANT',    'example.vincere.io' );  // no scheme
 *   define( 'VINCERE_CLIENT_ID', 'your-client-id-here' );
 *   define( 'VINCERE_API_KEY',   'your-api-key-here' );
 *
 * ── Auth flow (verified against github.com/vincere-io/vincere-identity) ──
 *   1. GET  https://id.vincere.io/oauth2/authorize
 *           ?client_id=…&state=…&redirect_uri=…&response_type=code
 *   2. Vincere redirects to {site}/vincere/callback/?code=…&state=…
 *      (the redirect URL registered with Vincere is
 *       https://verto-wp.on-forge.com/vincere/callback/ — this module
 *       implements exactly that path via a rewrite rule).
 *   3. POST https://id.vincere.io/oauth2/token?client_id=…
 *           body: grant_type=authorization_code&code=…
 *      → { id_token, refresh_token, access_token, expires_in }
 *   4. API calls: https://{tenant}/api/v2/… with headers
 *           id-token: {id_token}   x-api-key: {VINCERE_API_KEY}
 *   5. id_token is short-lived (~30–60 min); refresh with
 *           grant_type=refresh_token&refresh_token=… (no new refresh_token
 *      is issued on refresh — the original must be kept safe).
 */
class Verto_Vincere {

	const IDENTITY_BASE  = 'https://id.vincere.io';
	const CALLBACK_PATH  = 'vincere/callback';
	const QUERY_VAR      = 'verto_vincere_callback';
	const CPT            = 'verto_job';
	const CRON_HOOK      = 'verto_vincere_sync_event';
	const PAGE_SLUG      = 'verto-vincere';

	// Options / transients. Refresh token is autoload=no and never printed.
	const OPT_REFRESH    = 'verto_vincere_refresh_token';
	const OPT_SETTINGS   = 'verto_vincere_settings';
	const OPT_LAST_SYNC  = 'verto_vincere_last_sync';
	const OPT_ATTEMPTS   = 'verto_vincere_sync_attempts';
	const OPT_GOOD_SHAPE = 'verto_vincere_good_shape';
	const OPT_AUTH_FAIL  = 'verto_vincere_auth_failed';
	const OPT_CONNECTED  = 'verto_vincere_connected_at';
	const OPT_REWRITE_V  = 'verto_vincere_rewrite_ver';
	const OPT_CURSOR     = 'verto_vincere_sync_cursor';
	const RUN_HOOK       = 'verto_vincere_sync_run';
	const TR_ID_TOKEN    = 'verto_vincere_id_token';
	const TR_OAUTH_STATE = 'verto_vincere_oauth_state';
	const REWRITE_VER    = '1';

	// Chunked-sync tuning: one runner invocation stops after this many
	// pages or seconds, whichever comes first, then re-schedules itself.
	const CHUNK_MAX_PAGES   = 3;
	const CHUNK_MAX_SECONDS = 20;
	// A sync cursor untouched for this long is treated as a crashed run.
	const STALE_AFTER       = 600;

	/* ── Bootstrap ─────────────────────────────────────────────────────── */

	public static function boot() {
		add_action( 'init', [ self::class, 'register_cpt' ] );
		add_action( 'init', [ self::class, 'register_rewrite' ] );
		add_filter( 'query_vars', [ self::class, 'query_vars' ] );
		add_action( 'template_redirect', [ self::class, 'maybe_handle_callback' ], 0 );

		add_action( 'admin_menu', [ self::class, 'admin_menu' ], 20 );
		add_action( 'admin_notices', [ self::class, 'admin_notices' ] );
		add_action( 'admin_post_verto_vincere_connect', [ self::class, 'handle_connect' ] );
		add_action( 'admin_post_verto_vincere_sync', [ self::class, 'handle_manual_sync' ] );
		add_action( 'admin_post_verto_vincere_cancel', [ self::class, 'handle_cancel_sync' ] );
		add_action( 'admin_post_verto_vincere_save', [ self::class, 'handle_save_settings' ] );

		add_action( self::CRON_HOOK, [ self::class, 'cron_sync' ] );
		add_action( self::RUN_HOOK, [ self::class, 'run_chunk' ] );
		add_action( 'init', [ self::class, 'maybe_schedule_cron' ] );

		$main = dirname( __DIR__ ) . '/verto-widgets.php';
		register_activation_hook( $main, [ self::class, 'activate' ] );
		register_deactivation_hook( $main, [ self::class, 'deactivate' ] );
	}

	public static function activate() {
		self::add_rewrite_rule();
		flush_rewrite_rules();
		update_option( self::OPT_REWRITE_V, self::REWRITE_VER );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::RUN_HOOK );
		delete_option( self::OPT_CURSOR );
		flush_rewrite_rules();
	}

	/** All three wp-config constants present and non-empty? */
	public static function configured() {
		return defined( 'VINCERE_TENANT' ) && VINCERE_TENANT
			&& defined( 'VINCERE_CLIENT_ID' ) && VINCERE_CLIENT_ID
			&& defined( 'VINCERE_API_KEY' ) && VINCERE_API_KEY;
	}

	/** Tenant hostname, scheme/slashes stripped defensively. */
	private static function tenant() {
		$t = defined( 'VINCERE_TENANT' ) ? (string) VINCERE_TENANT : '';
		return trim( preg_replace( '#^https?://#i', '', $t ), "/ \t\n\r" );
	}

	public static function settings() {
		$defaults = [
			'brand_field'     => 'group',            // Vincere field that carries Group/Brand
			'brand_map'       => "edison=edison-lux\nlux=edison-lux\nvertek=vertek\nmodulr=modulr\nverto=verto",
			'internal_marker' => 'Internal, Verto Careers',
			'internal_only'   => '1',
			'apply_base'      => '',
		];
		$saved = get_option( self::OPT_SETTINGS );
		return wp_parse_args( is_array( $saved ) ? $saved : [], $defaults );
	}

	/* ── CPT ───────────────────────────────────────────────────────────── */

	public static function register_cpt() {
		register_post_type( self::CPT, [
			'labels'    => [ 'name' => 'Jobs (Vincere)', 'singular_name' => 'Job' ],
			'public'    => false,
			'show_ui'   => true,
			'menu_icon' => 'dashicons-portfolio',
			'supports'  => [ 'title', 'editor' ],
		] );
	}

	/* ── Rewrite endpoint /vincere/callback/ ───────────────────────────── */

	private static function add_rewrite_rule() {
		add_rewrite_rule( '^' . self::CALLBACK_PATH . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public static function register_rewrite() {
		self::add_rewrite_rule();
		// Soft flush once when this module first arrives on an already-active
		// install (activation hook won't re-fire on a plugin update).
		if ( get_option( self::OPT_REWRITE_V ) !== self::REWRITE_VER ) {
			flush_rewrite_rules();
			update_option( self::OPT_REWRITE_V, self::REWRITE_VER );
		}
	}

	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function maybe_handle_callback() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		self::handle_callback();
		exit;
	}

	private static function redirect_uri() {
		// Must match the URL registered with Vincere exactly
		// (production: https://verto-wp.on-forge.com/vincere/callback/).
		return home_url( '/' . self::CALLBACK_PATH . '/' );
	}

	private static function settings_url( $msg = '', $detail = '' ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		if ( $msg ) {
			$url = add_query_arg( 'vincere_msg', rawurlencode( $msg ), $url );
		}
		if ( $detail ) {
			$url = add_query_arg( 'vincere_detail', rawurlencode( mb_substr( $detail, 0, 160 ) ), $url );
		}
		return $url;
	}

	/** OAuth callback: verify state, exchange ?code= for tokens, redirect. */
	private static function handle_callback() {
		nocache_headers();

		if ( ! self::configured() ) {
			wp_die( 'Vincere integration is not configured on this site.', 'Vincere', [ 'response' => 400 ] );
		}

		// Vincere reported an error (user cancelled, bad client, …).
		if ( isset( $_GET['error'] ) ) {
			$detail = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			wp_safe_redirect( self::settings_url( 'oauth_error', $detail ) );
			return;
		}

		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		if ( '' === $code ) {
			wp_die( 'Missing authorization code.', 'Vincere', [ 'response' => 400 ] );
		}

		// CSRF: state must match the single-use value minted at Connect time.
		$expected = get_transient( self::TR_OAUTH_STATE );
		if ( ! $expected || ! $state || ! hash_equals( (string) $expected, $state ) ) {
			wp_safe_redirect( self::settings_url( 'state_mismatch' ) );
			return;
		}
		delete_transient( self::TR_OAUTH_STATE );

		$tokens = self::token_request( [ 'grant_type' => 'authorization_code', 'code' => $code ] );
		if ( is_wp_error( $tokens ) ) {
			wp_safe_redirect( self::settings_url( 'token_error', $tokens->get_error_message() ) );
			return;
		}

		// The refresh_token is only issued on this first exchange — keep it.
		if ( ! empty( $tokens['refresh_token'] ) ) {
			update_option( self::OPT_REFRESH, (string) $tokens['refresh_token'], false ); // autoload no
		}
		self::store_id_token( $tokens );
		update_option( self::OPT_CONNECTED, time(), false );
		delete_option( self::OPT_AUTH_FAIL );

		// Kick off a first sync straight away (best effort — errors surface
		// on the admin page, not to the browser).
		wp_schedule_single_event( time() + 5, self::CRON_HOOK );

		wp_safe_redirect( self::settings_url( 'connected' ) );
	}

	/* ── Tokens ────────────────────────────────────────────────────────── */

	/** POST /oauth2/token (client_id travels as a query param per the docs). */
	private static function token_request( array $body ) {
		$url      = self::IDENTITY_BASE . '/oauth2/token?client_id=' . rawurlencode( (string) VINCERE_CLIENT_ID );
		$response = wp_remote_post( $url, [
			'timeout' => 20,
			'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
			'body'    => $body,
		] );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$json   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || ! is_array( $json ) || empty( $json['id_token'] ) ) {
			$err = is_array( $json ) && ! empty( $json['error'] ) ? (string) $json['error'] : 'HTTP ' . $status;
			return new WP_Error( 'vincere_token', 'Token endpoint error: ' . $err );
		}
		return $json;
	}

	/** Cache the id_token for its lifetime minus a 5-minute safety margin. */
	private static function store_id_token( array $tokens ) {
		$ttl = isset( $tokens['expires_in'] ) ? (int) $tokens['expires_in'] : 1800;
		set_transient( self::TR_ID_TOKEN, (string) $tokens['id_token'], max( 300, $ttl - 300 ) );
	}

	/**
	 * Return a valid id_token, auto-refreshing via the stored refresh_token.
	 * On refresh failure flags the connection (admin notice + email) and
	 * returns WP_Error — the admin must click Connect again.
	 */
	public static function get_id_token( $force_refresh = false ) {
		if ( ! self::configured() ) {
			return new WP_Error( 'vincere_config', 'Vincere constants are missing from wp-config.php.' );
		}
		if ( ! $force_refresh ) {
			$cached = get_transient( self::TR_ID_TOKEN );
			if ( is_string( $cached ) && '' !== $cached ) {
				return $cached;
			}
		}
		$refresh = get_option( self::OPT_REFRESH );
		if ( ! is_string( $refresh ) || '' === $refresh ) {
			return new WP_Error( 'vincere_not_connected', 'Not connected to Vincere yet — click "Connect to Vincere".' );
		}
		$tokens = self::token_request( [ 'grant_type' => 'refresh_token', 'refresh_token' => $refresh ] );
		if ( is_wp_error( $tokens ) ) {
			self::flag_auth_failure( $tokens->get_error_message() );
			return $tokens;
		}
		self::store_id_token( $tokens );
		delete_option( self::OPT_AUTH_FAIL );
		return (string) $tokens['id_token'];
	}

	/** Persistent failure flag + at-most-every-6-hours email to the admin. */
	private static function flag_auth_failure( $message ) {
		$flag = get_option( self::OPT_AUTH_FAIL );
		$last_mail = is_array( $flag ) && isset( $flag['mailed'] ) ? (int) $flag['mailed'] : 0;
		$mailed    = $last_mail;
		if ( time() - $last_mail > 6 * HOUR_IN_SECONDS ) {
			$sent = wp_mail(
				get_option( 'admin_email' ),
				'[' . wp_specialchars_decode( get_bloginfo( 'name' ) ) . '] Vincere connection needs re-authorising',
				"The Vincere token refresh failed, so the jobs feed can no longer sync.\n\n"
				. "Error: {$message}\n\n"
				. 'Fix: log in to WordPress, go to Verto Setup → Vincere and click "Connect to Vincere" to re-authorise.'
				. "\n\n" . self::settings_url()
			);
			if ( $sent ) {
				$mailed = time();
			}
		}
		update_option( self::OPT_AUTH_FAIL, [ 'time' => time(), 'message' => (string) $message, 'mailed' => $mailed ], false );
	}

	/* ── API client ────────────────────────────────────────────────────── */

	/**
	 * Debug record of the most recent HTTP round-trip made by api_get():
	 * [ 'url' => …, 'status' => int, 'body' => first 400 chars, 'error' => transport error ].
	 * Consumed by the sync attempt log so wp-admin can show exactly what
	 * Vincere rejected. Never contains tokens/keys (headers are not stored).
	 */
	private static $last_request = [];

	public static function last_request() {
		return self::$last_request;
	}

	/**
	 * GET https://{tenant}/api/v2/{path} with id-token + x-api-key headers.
	 * Retries once with a forced token refresh on 401/403.
	 */
	public static function api_get( $path, $retry = true ) {
		$url = 'https://' . self::tenant() . '/api/v2/' . ltrim( $path, '/' );
		self::$last_request = [ 'url' => $url, 'status' => 0, 'body' => '', 'error' => '' ];

		$token = self::get_id_token();
		if ( is_wp_error( $token ) ) {
			self::$last_request['error'] = $token->get_error_message();
			return $token;
		}
		$response = wp_remote_get( $url, [
			'timeout' => 25,
			'headers' => [
				'id-token'  => $token,
				'x-api-key' => (string) VINCERE_API_KEY,
				'accept'    => 'application/json',
			],
		] );
		if ( is_wp_error( $response ) ) {
			self::$last_request['error'] = $response->get_error_message();
			return $response;
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = (string) wp_remote_retrieve_body( $response );
		self::$last_request['status'] = $status;
		self::$last_request['body']   = mb_substr( $body, 0, 400 );
		if ( in_array( $status, [ 401, 403 ], true ) && $retry ) {
			$token = self::get_id_token( true );
			if ( is_wp_error( $token ) ) {
				self::$last_request['error'] = $token->get_error_message();
				return $token;
			}
			return self::api_get( $path, false );
		}
		$json = json_decode( $body, true );
		if ( $status < 200 || $status >= 300 ) {
			$msg = is_array( $json ) && ! empty( $json['message'] ) ? (string) $json['message'] : 'HTTP ' . $status;
			return new WP_Error( 'vincere_api', $msg, [ 'status' => $status ] );
		}
		return is_array( $json ) ? $json : [];
	}

	/* ── Cron ──────────────────────────────────────────────────────────── */

	public static function maybe_schedule_cron() {
		if ( ! self::configured() ) {
			return;
		}
		if ( get_option( self::OPT_REFRESH ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 120, 'hourly', self::CRON_HOOK );
		}
	}

	public static function cron_sync() {
		// Hourly safety net. Skip when a chunked run is already in flight —
		// the runner keeps re-scheduling itself until it finishes.
		if ( self::run_active() ) {
			return;
		}
		self::start_sync();
	}

	/* ── Sync ──────────────────────────────────────────────────────────── */

	/*
	 * The sync is asynchronous and chunked so it can never time out a
	 * user-facing request (walking thousands of positions inside one
	 * admin-post request is what used to 504 at the gateway):
	 *
	 *   start_sync() — writes a cursor option and schedules RUN_HOOK.
	 *                  Instant; never talks to Vincere itself.
	 *   run_chunk()  — cron callback. Processes at most CHUNK_MAX_PAGES
	 *                  pages (~100 jobs each) or CHUNK_MAX_SECONDS seconds,
	 *                  persists progress back into the cursor, then either
	 *                  re-schedules itself (+1s) or finishes up.
	 *
	 * On the FIRST chunk only, pick_shape() walks the ordered ladder of
	 * request shapes, from the richest (full field list + server-side
	 * open-jobs query) down to a guaranteed-minimal probe. Every attempt —
	 * URL, HTTP status, first 400 chars of the response — is persisted to
	 * OPT_ATTEMPTS and shown on the admin page, so a tenant rejection
	 * ("Data is invalid" / QUERY_PARSE_FAIL) is visible verbatim in
	 * wp-admin. The winning shape is remembered in OPT_GOOD_SHAPE (tried
	 * first next time) and stored in the cursor, so every later chunk
	 * reuses it directly instead of re-walking the ladder. The final chunk
	 * deactivates jobs that vanished from the feed, writes the last-sync
	 * summary and clears the cursor.
	 */

	/**
	 * Is a chunked sync currently in flight? A cursor untouched for
	 * STALE_AFTER seconds is treated as a crashed run and ignored, so a
	 * wedged run can never block syncing for more than ten minutes.
	 */
	public static function run_active() {
		$cursor = get_option( self::OPT_CURSOR );
		if ( ! is_array( $cursor ) || empty( $cursor['updated_at'] ) ) {
			return false;
		}
		return ( time() - (int) $cursor['updated_at'] ) < self::STALE_AFTER;
	}

	/**
	 * Queue a fresh chunked sync. Only writes the cursor and schedules the
	 * runner, so callers return instantly. False when a run is already
	 * active (the stale-run guard in run_active() unblocks crashed runs).
	 */
	public static function start_sync() {
		if ( self::run_active() ) {
			return false;
		}
		$now = time();
		update_option( self::OPT_CURSOR, [
			'shape'      => null,   // request shape — picked by the ladder on chunk #1
			'start'      => 0,      // next `start=` offset to request
			'pages'      => 0,      // pages fetched so far (across all chunks)
			'seen'       => [],     // Vincere ids upserted so far (compact ints)
			'count'      => 0,      // successful upserts so far
			'total'      => null,   // result.total as reported by Vincere
			'capped'     => 0,      // 1 when the max-pages safety cap was hit
			'started_at' => $now,
			'updated_at' => $now,
		], false );
		wp_clear_scheduled_hook( self::RUN_HOOK );
		wp_schedule_single_event( time(), self::RUN_HOOK );
		spawn_cron();
		return true;
	}

	/** Drop the run state (cancel link, fatal errors, normal completion). */
	private static function clear_cursor() {
		delete_option( self::OPT_CURSOR );
		wp_clear_scheduled_hook( self::RUN_HOOK );
	}

	/**
	 * Cron callback: process one bounded batch of the sync, then either
	 * re-schedule itself or finish up. Never runs inside a user request.
	 */
	public static function run_chunk() {
		$cursor = get_option( self::OPT_CURSOR );
		if ( ! is_array( $cursor ) ) {
			return; // cancelled, or a stray event from an already-cleared run
		}
		if ( ! self::configured() ) {
			self::clear_cursor();
			self::record_sync( 'error', 'wp-config constants missing.', 0 );
			return;
		}

		$chunk_started = microtime( true );
		// Safety cap on the whole walk (not per chunk): 60 pages ≈ 6,000 jobs.
		$max_pages  = max( 1, (int) apply_filters( 'verto/vincere/max_pages', 60 ) );
		$pages_done = 0; // pages fetched by THIS invocation

		// Chunk #1: no shape chosen yet — run the attempt ladder once.
		if ( empty( $cursor['shape'] ) ) {
			$picked = self::pick_shape();
			if ( is_wp_error( $picked ) ) {
				self::clear_cursor();
				self::record_sync( 'error', $picked->get_error_message(), 0 );
				return;
			}
			$cursor['shape'] = $picked['shape'];
			$cursor          = self::ingest_batch( $cursor, $picked['items'] );
			$cursor['pages'] = 1;
			$cursor['start'] = count( $picked['items'] );
			$cursor['total'] = $picked['total'];
			$pages_done      = 1;
			if ( ! empty( $picked['shape']['single'] ) || self::walk_finished( $picked['items'], $cursor ) ) {
				self::finish_run( $cursor, $max_pages );
				return;
			}
			$cursor['updated_at'] = time();
			update_option( self::OPT_CURSOR, $cursor, false );
		}

		$shape = (array) $cursor['shape'];

		while ( $pages_done < self::CHUNK_MAX_PAGES
			&& ( microtime( true ) - $chunk_started ) < self::CHUNK_MAX_SECONDS ) {

			if ( (int) $cursor['pages'] >= $max_pages ) {
				$cursor['capped'] = 1;
				self::finish_run( $cursor, $max_pages );
				return;
			}

			$page = self::fetch_page( (string) $shape['fl'], (string) $shape['q'], (int) $shape['limit'], (int) $cursor['start'] );
			if ( is_wp_error( $page ) ) {
				self::clear_cursor();
				self::record_sync(
					'error',
					sprintf( 'Fetch failed at offset %d: %s', (int) $cursor['start'], $page->get_error_message() ),
					(int) $cursor['count']
				);
				return;
			}
			$pages_done++;
			$cursor['pages'] = (int) $cursor['pages'] + 1;
			$cursor          = self::ingest_batch( $cursor, $page['items'] );
			$cursor['start'] = (int) $cursor['start'] + count( $page['items'] );
			if ( null !== $page['total'] ) {
				$cursor['total'] = (int) $page['total'];
			}
			if ( self::walk_finished( $page['items'], $cursor ) ) {
				self::finish_run( $cursor, $max_pages );
				return;
			}
			$cursor['updated_at'] = time();
			update_option( self::OPT_CURSOR, $cursor, false );
		}

		// Budget spent with work remaining — hand over to the next chunk.
		wp_schedule_single_event( time() + 1, self::RUN_HOOK );
		spawn_cron();
	}

	/** Has the pagination walk reached the end of the result set? */
	private static function walk_finished( array $batch, array $cursor ) {
		if ( ! $batch ) {
			return true;
		}
		if ( isset( $cursor['total'] ) && null !== $cursor['total'] ) {
			return (int) $cursor['start'] >= (int) $cursor['total'];
		}
		// No total reported — a short page means the walk is done.
		return count( $batch ) < (int) ( $cursor['shape']['limit'] ?? 100 );
	}

	/**
	 * First chunk only: walk the attempt ladder until Vincere accepts a
	 * request shape, logging every attempt to OPT_ATTEMPTS (the admin
	 * "Last sync attempts" table). Each rung fetches only page one — the
	 * chunked runner does the rest of the pagination. Returns
	 * [ 'shape' => …, 'items' => first page, 'total' => int|null ]
	 * or WP_Error when every rung failed.
	 */
	private static function pick_shape() {
		// Field lists in decreasing richness — an unknown field name makes the
		// search endpoint 400 (QUERY_PARSE_FAIL), so thinner tiers follow.
		$field_tiers = apply_filters( 'verto/vincere/field_tiers', [
			'id,job_title,public_description,open_date,closed_date,job_type,employment_type,industry,functional_expertise,company,owners',
			'id,job_title,public_description,job_type,open_date,closed_date,company,location',
			'id,job_title,public_description,closed_date',
		] );
		// Optional server-side open-jobs query. NOTE: Vincere search is
		// Solr-backed and rejects unparseable queries with HTTP 400
		// "Data is invalid" — if this q fails, the ladder simply drops it
		// and open jobs are filtered locally on closed_date instead.
		$query = apply_filters( 'verto/vincere/search_query', 'closed_date:[NOW TO *]' );

		$tiers = array_values( array_filter( array_map( 'trim', array_map( 'strval', (array) $field_tiers ) ) ) );
		if ( ! $tiers ) {
			$tiers = [ 'id,job_title' ];
		}

		// The ladder: richest shape first, bare probe last.
		$attempts = [];
		if ( '' !== (string) $query ) {
			$attempts[] = [ 'label' => 'full fields + open-jobs query', 'fl' => $tiers[0], 'q' => (string) $query, 'limit' => 100, 'single' => false ];
		}
		foreach ( $tiers as $i => $fl ) {
			$attempts[] = [ 'label' => 'field tier ' . ( $i + 1 ) . ', no query (open jobs filtered locally)', 'fl' => $fl, 'q' => '', 'limit' => 100, 'single' => false ];
		}
		$attempts[] = [ 'label' => 'bare probe (fl=id,job_title, limit 25, no query)', 'fl' => 'id,job_title', 'q' => '', 'limit' => 25, 'single' => true ];

		// If a previous sync found a working shape, try that one first so the
		// steady state costs a single request instead of re-walking failures.
		$good = get_option( self::OPT_GOOD_SHAPE );
		// Ignore shapes remembered by an older plugin version — a new version
		// may carry a better default query that must get first crack.
		if ( is_array( $good ) && ( $good['ver'] ?? '' ) !== VERTO_WIDGETS_VERSION ) {
			$good = false;
		}
		if ( is_array( $good ) && isset( $good['fl'], $good['q'] ) ) {
			foreach ( $attempts as $i => $attempt ) {
				if ( $attempt['fl'] === $good['fl'] && $attempt['q'] === $good['q'] ) {
					if ( $i > 0 ) {
						$preferred = $attempts[ $i ];
						unset( $attempts[ $i ] );
						array_unshift( $attempts, $preferred );
						$attempts = array_values( $attempts );
					}
					break;
				}
			}
		}

		$winner = null;
		$first  = null;
		$log    = [];
		foreach ( $attempts as $attempt ) {
			$page    = self::fetch_page( $attempt['fl'], $attempt['q'], (int) $attempt['limit'], 0 );
			$request = self::last_request();
			$entry   = [
				'time'   => time(),
				'label'  => $attempt['label'],
				'url'    => (string) ( $request['url'] ?? '' ),
				'status' => (int) ( $request['status'] ?? 0 ),
				'body'   => (string) ( '' !== ( $request['error'] ?? '' ) ? 'WP error: ' . $request['error'] : ( $request['body'] ?? '' ) ),
			];
			if ( ! is_wp_error( $page ) ) {
				$entry['result'] = 'OK — ' . count( $page['items'] ) . ' item(s)';
				$log[]  = $entry;
				$winner = $attempt;
				$first  = $page;
				break;
			}
			$entry['result'] = 'FAILED — ' . $page->get_error_message();
			$log[] = $entry;
			// Auth / config problems can't be fixed by a thinner request —
			// bail out of the ladder. Everything else (400 bad query/field,
			// 5xx, transport hiccups) keeps degrading.
			if ( in_array( $page->get_error_code(), [ 'vincere_config', 'vincere_not_connected', 'vincere_token' ], true ) ) {
				break;
			}
		}
		update_option( self::OPT_ATTEMPTS, [ 'time' => time(), 'attempts' => array_slice( $log, 0, 10 ) ], false );

		if ( null === $winner ) {
			$last = end( $log );
			return new WP_Error( 'vincere_sync', $last ? $last['result'] : 'Unknown error.' );
		}
		update_option( self::OPT_GOOD_SHAPE, [ 'fl' => $winner['fl'], 'q' => $winner['q'], 'ver' => VERTO_WIDGETS_VERSION ], false );

		return [
			'shape' => [
				'label'  => (string) $winner['label'],
				'fl'     => (string) $winner['fl'],
				'q'      => (string) $winner['q'],
				'limit'  => (int) $winner['limit'],
				'single' => ! empty( $winner['single'] ),
			],
			'items' => $first['items'],
			'total' => $first['total'],
		];
	}

	/** Filter, map and upsert one page of raw items into the cursor. */
	private static function ingest_batch( array $cursor, array $items ) {
		$settings = self::settings();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}
			// Open = no closed_date, or closed_date still in the future
			// (this tenant sets closed_date as an expiry date on open jobs).
			if ( ! empty( $item['closed_date'] ) && strtotime( $item['closed_date'] ) <= time() ) {
				continue;
			}
			$job = self::map_position( $item, $settings );
			if ( ! $job ) {
				continue;
			}
			// Compact ints keep thousands of ids cheap inside one option row.
			$cursor['seen'][] = is_numeric( $job['vincere_id'] ) ? (int) $job['vincere_id'] : (string) $job['vincere_id'];
			if ( self::upsert_job( $job ) ) {
				$cursor['count'] = (int) $cursor['count'] + 1;
			}
		}
		return $cursor;
	}

	/**
	 * Final chunk: deactivate vanished jobs, write the last-sync summary,
	 * clear the cursor.
	 */
	private static function finish_run( array $cursor, $max_pages ) {
		$capped      = ! empty( $cursor['capped'] );
		$deactivated = 0;
		if ( ! $capped ) {
			// Only a complete walk may deactivate — a capped one hasn't seen
			// every open job, so drafting the unseen ones would be wrong.
			$deactivated = self::deactivate_missing( array_map( 'strval', (array) $cursor['seen'] ) );
		}
		$shape = (array) $cursor['shape'];

		$message = sprintf( '%d open job(s) synced, %d deactivated.', (int) $cursor['count'], $deactivated );
		if ( $capped ) {
			$message .= sprintf(
				' NOTE: stopped at the %d-page safety cap (deactivation pass skipped — the walk was incomplete); raise it with the verto/vincere/max_pages filter.',
				(int) $max_pages
			);
		}
		if ( '' === (string) ( $shape['q'] ?? '' ) ) {
			$message .= ' (degraded shape: ' . (string) ( $shape['label'] ?? '' ) . ' — see "Last sync attempts" below)';
		}

		self::record_sync( 'ok', $message, (int) $cursor['count'] );
		self::clear_cursor();
	}

	/**
	 * Fetch ONE page of the position search. Returns
	 * [ 'items' => raw item arrays, 'total' => int|null ] or WP_Error.
	 *
	 * URL shape verified against Vincere's own examples
	 * (github.com/vincere-io issue threads), e.g.:
	 *   /api/v2/position/search/fl=id,job_title,…?q=…&start=0&limit=100
	 * — `fl` is a matrix segment on the path, `q`/`start`/`limit` are query
	 * params, pagination is offset-based via `start`, and the response is
	 * { result: { start, total, items: [...] } }. A `;sort=field asc` matrix
	 * segment is also supported but deliberately not sent (one less thing a
	 * strict tenant can reject; the space would need %20-encoding anyway).
	 * The server may cap the page size below `limit`, so the walk advances
	 * by the actual batch size and trusts result.total when present.
	 */
	private static function fetch_page( $fl, $query, $limit = 100, $start = 0 ) {
		$limit = max( 1, min( 100, (int) $limit ) );
		$path  = 'position/search/fl=' . $fl . '?limit=' . $limit . '&start=' . max( 0, (int) $start );
		if ( '' !== (string) $query ) {
			$path .= '&q=' . rawurlencode( (string) $query );
		}
		$json = self::api_get( $path );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$items = [];
		if ( isset( $json['result']['items'] ) && is_array( $json['result']['items'] ) ) {
			$items = $json['result']['items'];
		} elseif ( isset( $json['items'] ) && is_array( $json['items'] ) ) {
			$items = $json['items'];
		}
		$total = isset( $json['result']['total'] ) ? (int) $json['result']['total'] : null;
		return [ 'items' => $items, 'total' => $total ];
	}

	/** Reduce a raw Vincere field value (string|array|object-ish) to text. */
	private static function field_text( $value ) {
		if ( is_string( $value ) || is_numeric( $value ) ) {
			return trim( (string) $value );
		}
		if ( is_array( $value ) ) {
			// Associative: try the usual label keys.
			foreach ( [ 'name', 'value', 'description', 'label', 'text' ] as $key ) {
				if ( isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ) {
					return trim( (string) $value[ $key ] );
				}
			}
			// List: join the reduced parts.
			$parts = [];
			foreach ( $value as $part ) {
				$text = self::field_text( $part );
				if ( '' !== $text ) {
					$parts[] = $text;
				}
				if ( count( $parts ) >= 3 ) {
					break;
				}
			}
			return implode( ', ', $parts );
		}
		return '';
	}

	/** Map one Vincere position to our normalized job array. */
	private static function map_position( array $item, array $settings ) {
		$title = self::field_text( $item['job_title'] ?? '' );
		if ( '' === $title ) {
			return null;
		}

		// Brand source: configured field first, then sensible fallbacks.
		$brand_raw = '';
		$candidates = array_unique( array_merge(
			[ (string) $settings['brand_field'] ],
			[ 'group', 'division', 'brand', 'functional_expertise', 'industry', 'company' ]
		) );
		foreach ( $candidates as $field ) {
			if ( '' !== $field && isset( $item[ $field ] ) ) {
				$brand_raw = self::field_text( $item[ $field ] );
				if ( '' !== $brand_raw ) {
					break;
				}
			}
		}

		$company  = self::field_text( $item['company'] ?? '' );
		$internal = self::is_internal( [ $brand_raw, $company, $title ], (string) $settings['internal_marker'] );

		// Location: whichever shape the tenant returns.
		$location = self::field_text( $item['location'] ?? '' );
		if ( '' === $location ) {
			$bits = [];
			foreach ( [ 'city', 'state', 'country' ] as $key ) {
				$text = self::field_text( $item[ $key ] ?? '' );
				if ( '' !== $text ) {
					$bits[] = $text;
				}
			}
			$location = implode( ', ', array_slice( $bits, 0, 2 ) );
		}
		if ( '' === $location ) {
			$location = 'Flexible';
		}

		// Job type / employment type → the "package" line on the board.
		$type_bits = [];
		foreach ( [ 'job_type', 'employment_type' ] as $key ) {
			$text = self::field_text( $item[ $key ] ?? '' );
			if ( '' !== $text ) {
				$type_bits[] = ucwords( strtolower( str_replace( '_', ' ', $text ) ) );
			}
		}

		$owner_email = '';
		if ( isset( $item['owners'] ) && is_array( $item['owners'] ) ) {
			$first = reset( $item['owners'] );
			if ( is_array( $first ) && ! empty( $first['email'] ) && is_string( $first['email'] ) ) {
				$owner_email = sanitize_email( $first['email'] );
			}
		}

		$apply_base = trim( (string) $settings['apply_base'] );
		$apply_url  = $apply_base ? trailingslashit( $apply_base ) . rawurlencode( (string) $item['id'] ) : '';

		return [
			'vincere_id'  => (string) $item['id'],
			'title'       => $title,
			'description' => wp_kses_post( (string) self::field_text( $item['public_description'] ?? '' ) ),
			'brand'       => self::map_brand( $brand_raw, (string) $settings['brand_map'], $internal ),
			'brand_raw'   => $brand_raw,
			'location'    => $location,
			'level'       => self::derive_level( $title ),
			'job_type'    => implode( ' · ', array_unique( $type_bits ) ),
			'apply_url'   => $apply_url,
			'owner_email' => $owner_email,
			'internal'    => $internal,
		];
	}

	/** Does any haystack contain one of the comma-separated markers? */
	private static function is_internal( array $haystacks, $markers ) {
		foreach ( array_filter( array_map( 'trim', explode( ',', $markers ) ) ) as $marker ) {
			foreach ( $haystacks as $haystack ) {
				if ( '' !== $haystack && false !== stripos( $haystack, $marker ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Keyword → site-brand-slug mapping ("keyword=slug" per line, first
	 * case-insensitive substring match wins). Unknown values fall back to
	 * the group brand 'verto'.
	 */
	private static function map_brand( $raw, $map_text, $internal ) {
		$raw = (string) $raw;
		if ( '' !== $raw ) {
			foreach ( preg_split( '/[\r\n]+/', (string) $map_text ) as $line ) {
				$line = trim( $line );
				if ( '' === $line || false === strpos( $line, '=' ) ) {
					continue;
				}
				list( $keyword, $slug ) = array_map( 'trim', explode( '=', $line, 2 ) );
				if ( '' !== $keyword && '' !== $slug && false !== stripos( $raw, $keyword ) ) {
					return sanitize_key( $slug );
				}
			}
		}
		return 'verto';
	}

	/** Seniority from the title — matches the board's three filter levels. */
	private static function derive_level( $title ) {
		if ( preg_match( '/\b(manager|head of|director|team lead)\b/i', $title ) ) {
			return 'Manager';
		}
		if ( preg_match( '/\b(entry|graduate|junior|trainee|associate|resourcer)\b/i', $title ) ) {
			return 'Entry-level';
		}
		return 'Senior';
	}

	/** Create/update the verto_job post for one mapped job. */
	private static function upsert_job( array $job ) {
		$existing = get_posts( [
			'post_type'      => self::CPT,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_vincere_id',
			'meta_value'     => $job['vincere_id'],
			'no_found_rows'  => true,
		] );

		$postarr = [
			'post_type'    => self::CPT,
			'post_status'  => 'publish',
			'post_title'   => $job['title'],
			'post_content' => $job['description'],
		];
		if ( $existing ) {
			$postarr['ID'] = (int) $existing[0];
			$post_id = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
		}
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

		$meta = [
			'_vincere_id'  => $job['vincere_id'],
			'_brand'       => $job['brand'],
			'_brand_raw'   => $job['brand_raw'],
			'_location'    => $job['location'],
			'_level'       => $job['level'],
			'_job_type'    => $job['job_type'],
			'_apply_url'   => $job['apply_url'],
			'_owner_email' => $job['owner_email'],
			'_internal'    => $job['internal'] ? '1' : '0',
			'_active'      => '1',
		];
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		return true;
	}

	/** Draft + flag jobs no longer present in the feed. Returns count. */
	private static function deactivate_missing( array $seen_ids ) {
		$lookup = array_fill_keys( array_map( 'strval', $seen_ids ), true );
		$posts  = get_posts( [
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );
		$deactivated = 0;
		foreach ( $posts as $post_id ) {
			$vid = (string) get_post_meta( $post_id, '_vincere_id', true );
			if ( '' === $vid ) {
				continue; // manually created job — leave alone
			}
			if ( ! isset( $lookup[ $vid ] ) ) {
				update_post_meta( $post_id, '_active', '0' );
				wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
				$deactivated++;
			}
		}
		return $deactivated;
	}

	private static function record_sync( $status, $message, $count ) {
		update_option( self::OPT_LAST_SYNC, [
			'time'    => time(),
			'status'  => $status,
			'message' => (string) $message,
			'count'   => (int) $count,
		], false );
		return 'ok' === $status ? (int) $count : new WP_Error( 'vincere_sync', (string) $message );
	}

	/* ── Frontend feed for the Jobs Board widget ───────────────────────── */

	/**
	 * Active synced jobs, shaped exactly like the widget's placeholder rows:
	 * [ title, brand, location, level, package, url ]. Empty array until a
	 * successful sync has run — the widget then falls back to placeholders.
	 */
	public static function get_jobs() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$cache    = [];
		$settings = self::settings();

		$meta_query = [ [ 'key' => '_active', 'value' => '1' ] ];
		if ( '1' === (string) $settings['internal_only'] ) {
			$meta_query[] = [ 'key' => '_internal', 'value' => '1' ];
		}
		$posts = get_posts( [
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => $meta_query,
			'no_found_rows'  => true,
		] );
		foreach ( $posts as $post ) {
			$package = (string) get_post_meta( $post->ID, '_job_type', true );
			$cache[] = [
				'title'    => get_the_title( $post ),
				'brand'    => (string) get_post_meta( $post->ID, '_brand', true ),
				'location' => (string) get_post_meta( $post->ID, '_location', true ),
				'level'    => (string) get_post_meta( $post->ID, '_level', true ),
				'package'  => $package ? $package : 'Competitive package',
				'url'      => (string) get_post_meta( $post->ID, '_apply_url', true ),
			];
		}
		return $cache;
	}

	/* ── Admin: page, actions, notices ─────────────────────────────────── */

	public static function admin_menu() {
		add_submenu_page(
			'verto-setup',
			'Vincere',
			'Vincere',
			'manage_options',
			self::PAGE_SLUG,
			[ self::class, 'render_page' ]
		);
	}

	public static function handle_connect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nope.' );
		}
		check_admin_referer( 'verto_vincere_connect' );
		if ( ! self::configured() ) {
			wp_safe_redirect( self::settings_url( 'not_configured' ) );
			exit;
		}
		$state = wp_generate_password( 24, false, false );
		set_transient( self::TR_OAUTH_STATE, $state, 15 * MINUTE_IN_SECONDS );
		$authorize = self::IDENTITY_BASE . '/oauth2/authorize?' . http_build_query( [
			'client_id'     => (string) VINCERE_CLIENT_ID,
			'state'         => $state,
			'redirect_uri'  => self::redirect_uri(),
			'response_type' => 'code',
		] );
		wp_redirect( $authorize ); // external — deliberately not wp_safe_redirect
		exit;
	}

	public static function handle_manual_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nope.' );
		}
		check_admin_referer( 'verto_vincere_sync' );
		if ( self::run_active() ) {
			wp_safe_redirect( self::settings_url( 'sync_running' ) );
			exit;
		}
		// Queue + kick the background runner. The walk itself NEVER runs in
		// this request — paging thousands of positions here is what used to
		// 504 at the gateway.
		self::start_sync();
		wp_safe_redirect( self::settings_url( 'sync_started' ) );
		exit;
	}

	/** Cancel link shown while a run is active — clears the cursor. */
	public static function handle_cancel_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nope.' );
		}
		check_admin_referer( 'verto_vincere_cancel' );
		self::clear_cursor();
		wp_safe_redirect( self::settings_url( 'sync_cancelled' ) );
		exit;
	}

	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nope.' );
		}
		check_admin_referer( 'verto_vincere_save' );
		$settings = self::settings();
		$settings['brand_field']     = sanitize_key( $_POST['brand_field'] ?? 'group' );
		$settings['brand_map']       = sanitize_textarea_field( wp_unslash( $_POST['brand_map'] ?? '' ) );
		$settings['internal_marker'] = sanitize_text_field( wp_unslash( $_POST['internal_marker'] ?? 'Internal' ) );
		$settings['internal_only']   = isset( $_POST['internal_only'] ) ? '1' : '0';
		$settings['apply_base']      = esc_url_raw( wp_unslash( $_POST['apply_base'] ?? '' ) );
		update_option( self::OPT_SETTINGS, $settings, false );
		wp_safe_redirect( self::settings_url( 'saved' ) );
		exit;
	}

	public static function admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$fail = get_option( self::OPT_AUTH_FAIL );
		if ( is_array( $fail ) && ! empty( $fail['message'] ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>Vincere:</strong> token refresh failed (%s). Jobs will stop syncing — <a href="%s">re-connect to Vincere</a>.</p></div>',
				esc_html( $fail['message'] ),
				esc_url( self::settings_url() )
			);
		}
	}

	private static function notice_for( $msg ) {
		$map = [
			'connected'      => [ 'success', 'Connected to Vincere. First sync has been queued — refresh this page in a minute.' ],
			'synced'         => [ 'success', 'Sync complete.' ],
			'sync_started'   => [ 'success', 'Sync started in the background — progress appears below and this page refreshes itself.' ],
			'sync_running'   => [ 'warning', 'A sync is already running — progress below.' ],
			'sync_cancelled' => [ 'success', 'Sync run cancelled.' ],
			'saved'          => [ 'success', 'Settings saved.' ],
			'sync_error'     => [ 'error', 'Sync failed.' ],
			'oauth_error'    => [ 'error', 'Vincere returned an OAuth error.' ],
			'token_error'    => [ 'error', 'Could not exchange the authorization code for tokens.' ],
			'state_mismatch' => [ 'error', 'Security check failed (state mismatch or expired). Please click Connect again.' ],
			'not_configured' => [ 'error', 'Add the VINCERE_* constants to wp-config.php first.' ],
		];
		return $map[ $msg ] ?? null;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings  = self::settings();
		$configured = self::configured();
		$refresh   = (string) get_option( self::OPT_REFRESH );
		$token     = get_transient( self::TR_ID_TOKEN );
		$connected_at = (int) get_option( self::OPT_CONNECTED );
		$last_sync = get_option( self::OPT_LAST_SYNC );
		$attempt_log = get_option( self::OPT_ATTEMPTS );
		$attempts  = is_array( $attempt_log ) && isset( $attempt_log['attempts'] ) && is_array( $attempt_log['attempts'] ) ? $attempt_log['attempts'] : [];
		$next_cron = wp_next_scheduled( self::CRON_HOOK );
		$cursor    = get_option( self::OPT_CURSOR );
		$running   = self::run_active() && is_array( $cursor );
		$jobs_total = 0;
		$counts = wp_count_posts( self::CPT );
		if ( $counts && isset( $counts->publish ) ) {
			$jobs_total = (int) $counts->publish;
		}

		$msg = isset( $_GET['vincere_msg'] ) ? sanitize_key( wp_unslash( $_GET['vincere_msg'] ) ) : '';
		$detail = isset( $_GET['vincere_detail'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['vincere_detail'] ) ) ) : '';
		$notice = self::notice_for( $msg );
		?>
		<div class="wrap">
			<h1>Vincere integration</h1>

			<?php if ( $running ) : ?>
				<script>setTimeout( function () { window.location.reload(); }, 5000 );</script>
			<?php endif; ?>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?> is-dismissible">
					<p><?php echo esc_html( $notice[1] ); ?><?php echo $detail ? ' <code>' . esc_html( $detail ) . '</code>' : ''; ?></p>
				</div>
			<?php endif; ?>

			<h2>Configuration (wp-config.php)</h2>
			<table class="widefat striped" style="max-width:720px;">
				<tbody>
					<tr>
						<td><code>VINCERE_TENANT</code></td>
						<td><?php echo defined( 'VINCERE_TENANT' ) && VINCERE_TENANT ? '<span style="color:green;">✔</span> ' . esc_html( self::tenant() ) : '<span style="color:#c00;">✘ missing</span>'; ?></td>
					</tr>
					<tr>
						<td><code>VINCERE_CLIENT_ID</code></td>
						<td><?php echo defined( 'VINCERE_CLIENT_ID' ) && VINCERE_CLIENT_ID ? '<span style="color:green;">✔</span> ' . esc_html( substr( (string) VINCERE_CLIENT_ID, 0, 4 ) . '…' ) : '<span style="color:#c00;">✘ missing</span>'; ?></td>
					</tr>
					<tr>
						<td><code>VINCERE_API_KEY</code></td>
						<td><?php echo defined( 'VINCERE_API_KEY' ) && VINCERE_API_KEY ? '<span style="color:green;">✔</span> set (hidden)' : '<span style="color:#c00;">✘ missing</span>'; ?></td>
					</tr>
					<tr>
						<td>OAuth redirect URL</td>
						<td><code><?php echo esc_html( self::redirect_uri() ); ?></code><br/>
						<em>Must match the redirect URL registered with Vincere exactly.</em></td>
					</tr>
				</tbody>
			</table>

			<h2>Connection</h2>
			<table class="widefat striped" style="max-width:720px;">
				<tbody>
					<tr>
						<td>Status</td>
						<td><?php
							if ( '' === $refresh ) {
								echo '<span style="color:#c00;">Not connected</span>';
							} elseif ( is_string( $token ) && '' !== $token ) {
								echo '<span style="color:green;">Connected</span> — id_token cached (auto-refreshes hourly)';
							} else {
								echo '<span style="color:green;">Connected</span> — id_token will refresh on next API call';
							}
						?></td>
					</tr>
					<?php if ( $connected_at ) : ?>
						<tr><td>Authorised</td><td><?php echo esc_html( human_time_diff( $connected_at ) ); ?> ago</td></tr>
					<?php endif; ?>
					<?php if ( $running ) : ?>
						<tr>
							<td>Sync in progress</td>
							<td><span style="color:#996800;">Running</span> — started <?php echo esc_html( human_time_diff( (int) $cursor['started_at'] ) ); ?> ago; <?php echo (int) $cursor['count']; ?> job(s) upserted over <?php echo (int) $cursor['pages']; ?> page(s)<?php echo isset( $cursor['total'] ) && null !== $cursor['total'] ? ' — ' . (int) $cursor['start'] . ' of ' . (int) $cursor['total'] . ' positions walked' : ''; ?>. This page refreshes every 5 seconds.</td>
						</tr>
					<?php endif; ?>
					<tr>
						<td>Last sync</td>
						<td><?php
							if ( is_array( $last_sync ) && ! empty( $last_sync['time'] ) ) {
								printf(
									'%s ago — %s <em>%s</em>',
									esc_html( human_time_diff( (int) $last_sync['time'] ) ),
									'ok' === ( $last_sync['status'] ?? '' ) ? '<span style="color:green;">OK</span>' : '<span style="color:#c00;">FAILED</span>',
									esc_html( (string) ( $last_sync['message'] ?? '' ) )
								);
							} else {
								echo 'Never';
							}
						?></td>
					</tr>
					<tr><td>Active jobs stored</td><td><?php echo (int) $jobs_total; ?> (see <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . self::CPT ) ); ?>">Jobs (Vincere)</a>)</td></tr>
					<tr><td>Next scheduled sync</td><td><?php echo $next_cron ? esc_html( human_time_diff( $next_cron ) ) . ' from now (hourly)' : 'Not scheduled (connect first)'; ?></td></tr>
				</tbody>
			</table>

			<h2>Last sync attempts</h2>
			<p class="description" style="max-width:720px;">
				Each sync tries progressively simpler request shapes until Vincere accepts one
				(full fields + open-jobs query → same fields without the query → thinner field
				lists → a bare <code>fl=id,job_title</code> probe). The exact request URL, HTTP
				status and start of the response body are recorded below — if the tenant rejects
				a query or field (e.g. <code>"Data is invalid" / QUERY_PARSE_FAIL</code>), the
				rejection is visible here verbatim. The winning shape is remembered and tried
				first next time.
			</p>
			<?php if ( $attempts ) : ?>
				<table class="widefat striped" style="max-width:1100px;">
					<thead>
						<tr>
							<th style="width:2em;">#</th>
							<th>Attempt</th>
							<th>Request URL</th>
							<th style="width:4em;">HTTP</th>
							<th>Result</th>
							<th>Response (first 400 chars)</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $attempts as $i => $entry ) : ?>
							<tr>
								<td><?php echo (int) $i + 1; ?></td>
								<td><?php echo esc_html( (string) ( $entry['label'] ?? '' ) ); ?></td>
								<td style="word-break:break-all;"><code><?php echo esc_html( (string) ( $entry['url'] ?? '' ) ); ?></code></td>
								<td><?php echo $entry['status'] ? (int) $entry['status'] : '—'; ?></td>
								<td><?php
									$result = (string) ( $entry['result'] ?? '' );
									$colour = 0 === strpos( $result, 'OK' ) ? 'green' : '#c00';
									printf( '<span style="color:%s;">%s</span>', esc_attr( $colour ), esc_html( $result ) );
								?></td>
								<td style="word-break:break-all;"><code><?php echo esc_html( (string) ( $entry['body'] ?? '' ) ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( is_array( $attempt_log ) && ! empty( $attempt_log['time'] ) ) : ?>
					<p class="description">Recorded <?php echo esc_html( human_time_diff( (int) $attempt_log['time'] ) ); ?> ago.</p>
				<?php endif; ?>
			<?php else : ?>
				<p><em>No sync attempted yet — click "Sync now" below and refresh.</em></p>
			<?php endif; ?>

			<div style="display:flex;gap:.5rem;align-items:center;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:.5rem;">
					<input type="hidden" name="action" value="verto_vincere_connect" />
					<?php wp_nonce_field( 'verto_vincere_connect' ); ?>
					<?php submit_button( $refresh ? 'Re-connect to Vincere' : 'Connect to Vincere', 'primary', 'submit', false, $configured ? [] : [ 'disabled' => 'disabled' ] ); ?>
				</form>
				<?php if ( $running ) : ?>
					<button type="button" class="button" disabled>Sync running… (started <?php echo esc_html( human_time_diff( (int) $cursor['started_at'] ) ); ?> ago, <?php echo (int) $cursor['count']; ?> jobs so far)</button>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=verto_vincere_cancel' ), 'verto_vincere_cancel' ) ); ?>">Cancel</a>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
						<input type="hidden" name="action" value="verto_vincere_sync" />
						<?php wp_nonce_field( 'verto_vincere_sync' ); ?>
						<?php submit_button( 'Sync now', 'secondary', 'submit', false, $refresh ? [] : [ 'disabled' => 'disabled' ] ); ?>
					</form>
				<?php endif; ?>
			</div>

			<h2>Mapping</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:720px;">
				<input type="hidden" name="action" value="verto_vincere_save" />
				<?php wp_nonce_field( 'verto_vincere_save' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="brand_field">Vincere brand field</label></th>
						<td>
							<input type="text" class="regular-text" id="brand_field" name="brand_field" value="<?php echo esc_attr( $settings['brand_field'] ); ?>" />
							<p class="description">Which Vincere position field carries the Group/Brand (default <code>group</code>; falls back through <code>division</code>, <code>brand</code>, <code>functional_expertise</code>, <code>industry</code>, <code>company</code> automatically).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="brand_map">Brand keyword map</label></th>
						<td>
							<textarea id="brand_map" name="brand_map" rows="5" class="large-text code"><?php echo esc_textarea( $settings['brand_map'] ); ?></textarea>
							<p class="description">One <code>keyword=slug</code> per line. First case-insensitive substring match of the brand field value wins. Slugs: <code>verto</code>, <code>edison-lux</code>, <code>vertek</code>, <code>modulr</code>. Unmatched jobs default to <code>verto</code>.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="internal_marker">Internal-roles marker</label></th>
						<td>
							<input type="text" class="regular-text" id="internal_marker" name="internal_marker" value="<?php echo esc_attr( $settings['internal_marker'] ); ?>" />
							<p class="description">Comma-separated markers. A job counts as an internal Verto role when its brand field, company or title contains one of these (e.g. <code>Internal, Verto Careers</code>).</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Board shows</th>
						<td>
							<label><input type="checkbox" name="internal_only" value="1" <?php checked( $settings['internal_only'], '1' ); ?> /> Only internal roles (recommended — the board is "Join Verto")</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="apply_base">Apply URL base</label></th>
						<td>
							<input type="url" class="regular-text" id="apply_base" name="apply_base" value="<?php echo esc_attr( $settings['apply_base'] ); ?>" placeholder="https://careers.example.com/job" />
							<p class="description">Optional Vincere job-portal base; the job ID is appended (<code>{base}/{id}</code>). Left blank, board rows use the widget's click-through URL.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Save mapping' ); ?>
			</form>
		</div>
		<?php
	}
}

/** Template tag used by the Jobs Board widget (safe if module removed). */
function verto_vincere_get_jobs() {
	return Verto_Vincere::get_jobs();
}

Verto_Vincere::boot();
