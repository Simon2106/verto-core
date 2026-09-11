<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verto Applications – the "Apply" flow behind the Jobs Board widget.
 *
 * Front end: an inline modal on every live jobs-board row (markup rendered by
 * render_modal() below, opened by verto-effects.js; without JS the modal is a
 * plain :target-toggled element whose <form> POSTs to admin-post.php).
 *
 * Back end (this file):
 *   1. Validates the submission (nonce, honeypot, field + CV file checks).
 *   2. Stores it as a `verto_application` post (private, never public) with
 *      the CV as a private attachment – an application is NEVER lost, even
 *      when every later step fails.
 *   3. Pushes candidate → CV → application into Vincere (unless TEST MODE,
 *      the default – see test_mode() below).
 *   4. Emails the job owner (fallback: admin_email) a summary + the CV,
 *      whatever happened in step 3.
 *
 * ── Vincere API shapes used (and how solid the evidence is) ─────────────
 * The api.vincere.io reference is JS-gated, so each call is isolated in its
 * own small function with the best evidence available:
 *
 *   • POST /api/v2/candidate – VERIFIED. A Vincere-acknowledged support
 *     thread shows this exact endpoint with first_name / last_name / email /
 *     phone / candidate_source_id (github.com/vincere-io/restful-api-support
 *     issues/5 – their 500 came from form-encoding the body; JSON is
 *     required). The public docs additionally list registration_date
 *     (ISO-8601, milliseconds + Z) among the required fields, so we send it.
 *
 *   • POST /api/v2/candidate/{id}/file – BEST EVIDENCE. The docs' candidate
 *     file endpoint takes a JSON body with file_name + base_64_content +
 *     document_type_ids (1 = CV/Resume) rather than multipart. If that shape
 *     is rejected we retry once with a `url` body (Vincere fetches the file).
 *
 *   • POST /api/v2/position/{position_id}/candidate/{candidate_id} – BEST
 *     EVIDENCE. Creates the application/shortlist entry linking candidate to
 *     job (Cyclr's Vincere connector exposes this as "Add Application":
 *     cyclr.com/integrate/vincere). If it 404s we retry the mirrored
 *     /candidate/{cid}/position/{pid} path once.
 *
 * Every push failure degrades gracefully: the application post + CV are
 * already stored, the failure detail lands in the _vincere_status meta (shown
 * in the admin list table) and the owner email flags it for manual entry.
 *
 * ── TEST MODE (default ON) ──────────────────────────────────────────────
 * Until the client is ready for live data, nothing is pushed to Vincere:
 * applications are stored + emailed, and the status column reads "Test mode".
 * Go live by adding to wp-config.php:
 *
 *   define( 'VERTO_VINCERE_PUSH_LIVE', true );
 */
class Verto_Applications {

	const CPT          = 'verto_application';
	const ACTION       = 'verto_apply';
	const NONCE        = 'verto_apply';
	const MAX_BYTES    = 5242880; // 5 MB
	const OPT_SOURCE   = 'verto_vincere_source_id';

	/** CV types accepted, for both wp_check_filetype_and_ext and the input's accept=. */
	const MIMES = [
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	];

	/* ── Bootstrap ─────────────────────────────────────────────────────── */

	public static function boot() {
		add_action( 'init', [ self::class, 'register_cpt' ] );
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle_submit' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION, [ self::class, 'handle_submit' ] );
		add_filter( 'manage_' . self::CPT . '_posts_columns', [ self::class, 'admin_columns' ] );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', [ self::class, 'admin_column' ], 10, 2 );
	}

	/**
	 * Applications live in wp-admin only: not public, not queryable, not in
	 * search/REST. The CV attachment is additionally set to `private` status
	 * (see save_application) so its attachment page 404s for visitors.
	 */
	public static function register_cpt() {
		register_post_type( self::CPT, [
			'labels'              => [
				'name'          => 'Applications',
				'singular_name' => 'Application',
				'menu_name'     => 'Applications',
			],
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'show_ui'             => true,
			'menu_icon'           => 'dashicons-id-alt',
			'supports'            => [ 'title' ],
			'capabilities'        => [ 'create_posts' => 'do_not_allow' ], // created by the form only
			'map_meta_cap'        => true,
		] );
	}

	/**
	 * TEST MODE – defaults ON so a mis-deploy can never pollute the client's
	 * live Vincere. Only `define( 'VERTO_VINCERE_PUSH_LIVE', true );` in
	 * wp-config.php (or the filter) turns real pushes on.
	 */
	private static function test_mode(): bool {
		$live = defined( 'VERTO_VINCERE_PUSH_LIVE' ) && VERTO_VINCERE_PUSH_LIVE;
		return ! apply_filters( 'verto/applications/push_live', $live );
	}

	/* ── Submission handler ────────────────────────────────────────────── */

	public static function handle_submit() {
		$async = ! empty( $_POST['verto_async'] );

		// Honeypot: bots that fill the hidden "website" field get a quiet
		// pretend-success (no post, no email) so they don't retune.
		if ( ! empty( $_POST['verto_website'] ) ) {
			self::respond( true, '', $async );
		}

		// Nonce. A long-cached page can serve an expired nonce, so the error
		// asks for a refresh rather than accusing anyone of anything.
		$nonce = isset( $_POST['verto_apply_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['verto_apply_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			self::respond( false, 'security', $async );
		}

		// Light rate limit: 5 submissions per IP per 10 minutes.
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$key = 'verto_apply_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= 5 ) {
			self::respond( false, 'ratelimit', $async );
		}
		set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );

		// ── Fields ──
		$name      = sanitize_text_field( wp_unslash( $_POST['verto_name'] ?? '' ) );
		$email     = sanitize_email( wp_unslash( $_POST['verto_email'] ?? '' ) );
		$phone     = sanitize_text_field( wp_unslash( $_POST['verto_phone'] ?? '' ) );
		$linkedin  = esc_url_raw( wp_unslash( $_POST['verto_linkedin'] ?? '' ), [ 'http', 'https' ] );
		$message   = sanitize_textarea_field( wp_unslash( $_POST['verto_message'] ?? '' ) );
		$message   = mb_substr( $message, 0, 5000 );
		$job_id    = preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['verto_job_id'] ?? '' ) );
		$job_title = sanitize_text_field( wp_unslash( $_POST['verto_job_title'] ?? '' ) );
		$consent   = ! empty( $_POST['verto_consent'] );

		if ( '' === $name || mb_strlen( $name ) > 120 || ! is_email( $email ) ) {
			self::respond( false, 'invalid', $async );
		}
		if ( ! $consent ) {
			self::respond( false, 'consent', $async );
		}
		if ( mb_strlen( $phone ) > 40 ) {
			$phone = mb_substr( $phone, 0, 40 );
		}

		// ── CV file (required, pdf/doc/docx, ≤ 5 MB) ──
		$file = $_FILES['verto_cv'] ?? null;
		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] )
			|| UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			self::respond( false, 'file', $async );
		}
		$size = (int) ( $file['size'] ?? 0 );
		if ( $size <= 0 || $size > self::MAX_BYTES ) {
			self::respond( false, 'file', $async );
		}
		// wp_check_filetype_and_ext inspects content, not just the name, and
		// the restricted mime map rejects anything but pdf/doc/docx outright.
		$check = wp_check_filetype_and_ext( $file['tmp_name'], (string) $file['name'], self::MIMES );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) || ! isset( self::MIMES[ $check['ext'] ] ) ) {
			self::respond( false, 'file', $async );
		}

		// ── Store the application (from here on, never lose it) ──
		$app_id = wp_insert_post( [
			'post_type'   => self::CPT,
			'post_status' => 'private',
			'post_title'  => $name . ' – ' . ( '' !== $job_title ? $job_title : 'General application' ),
		], true );
		if ( is_wp_error( $app_id ) || ! $app_id ) {
			self::respond( false, 'server', $async );
		}
		foreach ( [
			'_email'          => $email,
			'_phone'          => $phone,
			'_linkedin'       => $linkedin,
			'_message'        => $message,
			'_vincere_job_id' => $job_id,
			'_job_title'      => $job_title,
			'_status'         => 'new',
			'_consent'        => gmdate( 'c' ),
			'_consent_text'   => self::consent_text(),
		] as $meta_key => $value ) {
			update_post_meta( $app_id, $meta_key, $value );
		}

		// CV → private attachment on the application. A failure here is
		// recorded but does not abort (validation already passed; a transient
		// sideload failure must not eat the application).
		$cv_path = '';
		$att_id  = self::attach_cv( $app_id );
		if ( $att_id ) {
			update_post_meta( $app_id, '_cv_attachment', $att_id );
			$cv_path = (string) get_attached_file( $att_id );
		} else {
			update_post_meta( $app_id, '_status', 'cv-store-failed' );
		}

		$data = [
			'name'      => $name,
			'email'     => $email,
			'phone'     => $phone,
			'linkedin'  => $linkedin,
			'message'   => $message,
			'job_id'    => $job_id,
			'job_title' => $job_title,
		];

		// ── Vincere push (or test mode) ──
		if ( self::test_mode() ) {
			$push = [ 'ok' => true, 'status' => 'Test mode – not pushed to Vincere' ];
		} elseif ( ! class_exists( 'Verto_Vincere' ) || ! Verto_Vincere::configured() ) {
			$push = [ 'ok' => false, 'status' => 'Vincere not configured on this site – enter manually' ];
		} else {
			$push = self::push_to_vincere( $app_id, $data, $cv_path );
		}
		update_post_meta( $app_id, '_vincere_status', (string) $push['status'] );
		update_post_meta( $app_id, '_status', $push['ok'] ? 'processed' : 'needs-attention' );

		// ── Email the job owner either way ──
		self::email_owner( $app_id, $data, $cv_path, (string) $push['status'] );

		self::respond( true, '', $async );
	}

	/**
	 * Sideload the uploaded CV as an attachment of the application post.
	 * The filename gets a random slug (unguessable URL) and the attachment
	 * post is set to `private`. Returns attachment ID or 0.
	 */
	private static function attach_cv( int $app_id ): int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$att_id = media_handle_upload( 'verto_cv', $app_id, [ 'post_status' => 'private' ], [
			'test_form' => false,
			'mimes'     => self::MIMES,
			'unique_filename_callback' => function ( $dir, $filename, $ext ) {
				return 'cv-' . strtolower( wp_generate_password( 12, false, false ) ) . $ext;
			},
		] );
		if ( is_wp_error( $att_id ) || ! $att_id ) {
			return 0;
		}
		// media_handle_upload forces attachments to `inherit`; make it private
		// so the attachment page is only visible to logged-in editors.
		wp_update_post( [ 'ID' => (int) $att_id, 'post_status' => 'private' ] );
		return (int) $att_id;
	}

	/* ── Vincere push ──────────────────────────────────────────────────── */

	/**
	 * candidate → CV file → link to position. Returns
	 * [ 'ok' => bool, 'status' => human summary for the admin column/email ].
	 * Shapes + evidence: see the file header. Never throws; every step's
	 * outcome is folded into the summary.
	 */
	private static function push_to_vincere( int $app_id, array $a, string $cv_path ): array {
		// 1) Create the candidate (verified shape – JSON body, NOT form-encoded).
		$parts = preg_split( '/\s+/', trim( $a['name'] ), 2 );
		$body  = [
			'first_name'        => $parts[0],
			'last_name'         => isset( $parts[1] ) && '' !== $parts[1] ? $parts[1] : $parts[0],
			'email'             => $a['email'],
			// Docs list registration_date as required, ISO-8601 with ms + Z.
			'registration_date' => gmdate( 'Y-m-d\TH:i:s.000\Z' ),
		];
		if ( '' !== $a['phone'] ) {
			$body['phone'] = $a['phone'];
		}
		$source = self::candidate_source_id();
		if ( $source > 0 ) {
			$body['candidate_source_id'] = $source;
		}

		$res = Verto_Vincere::api_post( 'candidate', $body );
		if ( is_wp_error( $res ) ) {
			return [ 'ok' => false, 'status' => 'Push FAILED at candidate create: ' . $res->get_error_message() ];
		}
		// Response shape: { "id": 123, … } (defensively also check data.id).
		$cid = (int) ( $res['id'] ?? ( $res['data']['id'] ?? 0 ) );
		if ( ! $cid ) {
			return [ 'ok' => false, 'status' => 'Push FAILED: candidate create returned no id (' . mb_substr( wp_json_encode( $res ), 0, 120 ) . ')' ];
		}
		update_post_meta( $app_id, '_vincere_candidate_id', $cid );
		$notes = [ 'candidate #' . $cid . ' created' ];
		$ok    = true;

		// 2) CV upload – base64 JSON body first, `url` body as fallback.
		if ( '' !== $cv_path && is_readable( $cv_path ) && filesize( $cv_path ) <= self::MAX_BYTES ) {
			$payload = [
				'file_name'         => basename( $cv_path ),
				'base_64_content'   => base64_encode( (string) file_get_contents( $cv_path ) ),
				'document_type_ids' => [ 1 ], // 1 = CV/Resume in Vincere's document types
				'original_cv'       => true,
			];
			$up = Verto_Vincere::api_post( 'candidate/' . $cid . '/file', $payload );
			if ( is_wp_error( $up ) ) {
				$att = (int) get_post_meta( $app_id, '_cv_attachment', true );
				$url = $att ? (string) wp_get_attachment_url( $att ) : '';
				$up2 = $url ? Verto_Vincere::api_post( 'candidate/' . $cid . '/file', [ 'file_name' => basename( $cv_path ), 'url' => $url ] ) : $up;
				if ( is_wp_error( $up2 ) ) {
					$ok      = false;
					$notes[] = 'CV upload failed (' . $up->get_error_message() . ') – CV is on the email + in WordPress';
				} else {
					$notes[] = 'CV uploaded (via URL fallback)';
				}
			} else {
				$notes[] = 'CV uploaded';
			}
		} else {
			$notes[] = 'no CV file to upload';
		}

		// 3) Link candidate → position (the application itself).
		if ( '' !== $a['job_id'] ) {
			$link = Verto_Vincere::api_post( 'position/' . $a['job_id'] . '/candidate/' . $cid, new stdClass() );
			if ( is_wp_error( $link ) ) {
				$status = (int) ( $link->get_error_data()['status'] ?? 0 );
				$link2  = in_array( $status, [ 404, 405 ], true )
					? Verto_Vincere::api_post( 'candidate/' . $cid . '/position/' . $a['job_id'], new stdClass() )
					: $link;
				if ( is_wp_error( $link2 ) ) {
					$ok      = false;
					$notes[] = 'link to position #' . $a['job_id'] . ' failed (' . $link->get_error_message() . ') – shortlist manually';
				} else {
					$notes[] = 'linked to position #' . $a['job_id'] . ' (mirrored path)';
				}
			} else {
				$notes[] = 'linked to position #' . $a['job_id'];
			}
		} else {
			$notes[] = 'no position id (general application) – not linked';
		}

		return [ 'ok' => $ok, 'status' => ( $ok ? 'Pushed: ' : 'Partial push: ' ) . implode( '; ', $notes ) ];
	}

	/**
	 * Resolve the tenant's candidate_source_id ("Website" if one exists).
	 * Order: VINCERE_CANDIDATE_SOURCE_ID constant → cached lookup → live
	 * lookup against the sources list (both path spellings seen in the wild)
	 * → 0 (field omitted; Vincere then applies its default source, and if the
	 * tenant *requires* one the create error surfaces verbatim in the status
	 * column so Simon can pin the constant).
	 */
	private static function candidate_source_id(): int {
		if ( defined( 'VINCERE_CANDIDATE_SOURCE_ID' ) ) {
			return (int) VINCERE_CANDIDATE_SOURCE_ID;
		}
		$cached = get_option( self::OPT_SOURCE, null );
		if ( null !== $cached && is_numeric( $cached ) ) {
			return (int) $cached;
		}
		$id = 0;
		foreach ( [ 'candidatesources', 'candidate/sources' ] as $path ) {
			$res = Verto_Vincere::api_get( $path );
			if ( is_wp_error( $res ) ) {
				continue;
			}
			$items = [];
			foreach ( [ $res, $res['data'] ?? null, $res['items'] ?? null, $res['result']['items'] ?? null ] as $candidate_list ) {
				if ( is_array( $candidate_list ) && isset( $candidate_list[0] ) && is_array( $candidate_list[0] ) && isset( $candidate_list[0]['id'] ) ) {
					$items = $candidate_list;
					break;
				}
			}
			$first = 0;
			foreach ( $items as $item ) {
				$item_id = (int) ( $item['id'] ?? 0 );
				if ( ! $item_id ) {
					continue;
				}
				if ( ! $first ) {
					$first = $item_id;
				}
				$label = strtolower( (string) ( $item['name'] ?? ( $item['description'] ?? '' ) ) );
				if ( false !== strpos( $label, 'website' ) || false !== strpos( $label, 'web site' ) || false !== strpos( $label, 'wordpress' ) ) {
					$id = $item_id;
					break 2;
				}
			}
			if ( $first ) {
				$id = $first;
				break;
			}
		}
		update_option( self::OPT_SOURCE, $id, false );
		return $id;
	}

	/* ── Owner email ───────────────────────────────────────────────────── */

	private static function email_owner( int $app_id, array $a, string $cv_path, string $push_status ) {
		$owner = '';
		if ( '' !== $a['job_id'] && class_exists( 'Verto_Vincere' ) ) {
			$jobs = get_posts( [
				'post_type'      => Verto_Vincere::CPT,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_vincere_id',
				'meta_value'     => $a['job_id'],
				'no_found_rows'  => true,
			] );
			if ( $jobs ) {
				$owner = sanitize_email( (string) get_post_meta( (int) $jobs[0], '_owner_email', true ) );
			}
		}
		if ( ! is_email( $owner ) ) {
			$owner = (string) get_option( 'admin_email' );
		}

		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$role    = '' !== $a['job_title'] ? $a['job_title'] : 'General application';
		$subject = sprintf( '[%s] New application: %s – %s', $site, $a['name'], $role );
		$lines   = [
			'New application received via ' . home_url( '/' ),
			'',
			'Role:     ' . $role . ( '' !== $a['job_id'] ? ' (Vincere position #' . $a['job_id'] . ')' : '' ),
			'Name:     ' . $a['name'],
			'Email:    ' . $a['email'],
			'Phone:    ' . ( '' !== $a['phone'] ? $a['phone'] : '–' ),
			'LinkedIn: ' . ( '' !== $a['linkedin'] ? $a['linkedin'] : '–' ),
			'',
			'Message:',
			( '' !== $a['message'] ? $a['message'] : '–' ),
			'',
			'Vincere:  ' . $push_status,
			'',
			( '' !== $cv_path ? 'The CV is attached to this email.' : 'NOTE: the CV could not be stored – ask the candidate to resend it.' ),
			'View in WordPress: ' . admin_url( 'edit.php?post_type=' . self::CPT ),
			'',
			'GDPR: the candidate ticked the consent box (' . self::consent_text() . ')',
		];
		wp_mail( $owner, $subject, implode( "\n", $lines ), [], '' !== $cv_path ? [ $cv_path ] : [] );
	}

	/* ── Responses (async JSON or non-JS redirect) ─────────────────────── */

	/**
	 * Error codes map to friendly copy in both channels; the widget prints
	 * the same map for the non-JS redirect banner.
	 */
	public static function messages(): array {
		return [
			'ok'        => 'Thanks – your application is in. The consultant who owns this role will come back to you directly.',
			'security'  => 'That took a little too long and the security check expired – please refresh the page and try again.',
			'ratelimit' => 'Too many applications from this connection – please wait a few minutes and try again.',
			'invalid'   => 'Please check your name and email address and try again.',
			'consent'   => 'Please tick the consent box so we can process your application.',
			'file'      => 'Please attach your CV as a PDF or Word document (.pdf, .doc, .docx) no larger than 5 MB.',
			'server'    => 'Something went wrong on our side – please try again, or email your CV to us instead.',
		];
	}

	private static function respond( bool $ok, string $code, bool $async ) {
		$messages = self::messages();
		if ( $async ) {
			if ( $ok ) {
				wp_send_json_success( [ 'message' => $messages['ok'] ] );
			}
			wp_send_json_error( [ 'code' => $code, 'message' => $messages[ $code ] ?? $messages['server'] ], 400 );
		}
		// Non-JS: bounce back to the page the form was on with a flag the
		// jobs-board widget turns into a banner.
		$back = wp_get_referer();
		$back = $back ? $back : ( isset( $_POST['verto_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['verto_redirect'] ) ) : '' );
		$back = wp_validate_redirect( $back, home_url( '/' ) );
		$back = remove_query_arg( 'verto_apply', $back );
		$back = add_query_arg( 'verto_apply', $ok ? 'ok' : rawurlencode( $code ), $back );
		wp_safe_redirect( $back );
		exit;
	}

	/* ── Front end: modal markup ───────────────────────────────────────── */

	public static function consent_text(): string {
		$text = 'I consent to my details and CV being stored and shared with the hiring team so they can process this application.';
		return (string) apply_filters( 'verto/applications/consent_text', $text );
	}

	/**
	 * The Apply modal. Rendered once per page (static guard) after the jobs
	 * board. Progressive enhancement:
	 *   – no JS: rows link to #verto-apply-modal, CSS :target shows it and
	 *     the form is a plain multipart POST to admin-post.php (the role
	 *     field is visible + editable since JS can't prefill it);
	 *   – JS (verto-effects.js module 9): proper open/close, per-row job
	 *     prefill, client-side size check and async submit with inline
	 *     success / error states.
	 */
	public static function render_modal() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		?>
		<div class="verto-apply-modal" id="verto-apply-modal" data-verto-apply-modal>
			<a class="verto-apply-modal__overlay" href="#!" tabindex="-1" aria-hidden="true" data-apply-close></a>
			<div class="verto-apply-modal__card" role="dialog" aria-modal="true" aria-labelledby="verto-apply-title">
				<a class="verto-apply-modal__close" href="#!" data-apply-close aria-label="Close">&times;</a>
				<div class="verto-apply-modal__eyebrow">Apply</div>
				<h3 class="verto-apply-modal__title" id="verto-apply-title" data-apply-job-label>Join Verto</h3>

				<?php self::render_form( 'modal' ); ?>

				<div class="verto-apply-done" data-apply-done hidden>
					<div class="verto-apply-done__mark" aria-hidden="true">✓</div>
					<p><?php echo esc_html( self::messages()['ok'] ); ?></p>
					<a href="#!" class="btn-base btn-ghost-outline" data-apply-close>Close</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * The application <form> itself, shared by the modal ($context 'modal')
	 * and the inline apply card on the job detail page ($context 'inline').
	 * Inline gets the job prefilled server-side (no JS required) and its own
	 * data hook (data-verto-apply-inline) so verto-effects.js module 10 can
	 * async-submit it without colliding with the modal's module 9.
	 */
	public static function render_form( string $context = 'modal', string $job_id = '', string $job_title = '', string $redirect = '' ) {
		$inline = 'inline' === $context;
		$hook   = $inline ? 'data-verto-apply-inline' : 'data-verto-apply-form';
		if ( '' === $redirect ) {
			$redirect = home_url( add_query_arg( [] ) );
		}
		?>
		<form class="verto-apply-form verto-form" method="post" enctype="multipart/form-data"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" <?php echo esc_attr( $hook ); // phpcs:ignore -- bare boolean attribute ?>>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="verto_apply_nonce" value="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>" />
			<input type="hidden" name="verto_job_id" value="<?php echo esc_attr( preg_replace( '/\D+/', '', $job_id ) ); ?>" />
			<input type="hidden" name="verto_redirect" value="<?php echo esc_url( $redirect ); ?>" />
			<p class="verto-hp" aria-hidden="true">
				<label>Website <input type="text" name="verto_website" tabindex="-1" autocomplete="off" /></label>
			</p>

			<label class="verto-apply-field">Role you&rsquo;re applying for
				<input type="text" name="verto_job_title" value="<?php echo esc_attr( $job_title ); ?>" placeholder="e.g. Senior Consultant – or leave blank for a general application" />
			</label>
			<div class="verto-apply-form__grid">
				<label class="verto-apply-field">Name *
					<input type="text" name="verto_name" required maxlength="120" autocomplete="name" />
				</label>
				<label class="verto-apply-field">Email *
					<input type="email" name="verto_email" required autocomplete="email" />
				</label>
				<label class="verto-apply-field">Phone
					<input type="tel" name="verto_phone" maxlength="40" autocomplete="tel" />
				</label>
				<label class="verto-apply-field">LinkedIn
					<input type="url" name="verto_linkedin" placeholder="https://linkedin.com/in/…" />
				</label>
			</div>
			<label class="verto-apply-field">A short message
				<textarea name="verto_message" rows="4" maxlength="5000" placeholder="Current desk, billings, what you're looking for – whatever you'd tell us over coffee."></textarea>
			</label>
			<label class="verto-apply-field verto-apply-field--file">CV * <span class="verto-apply-field__hint">(PDF or Word, max 5&nbsp;MB)</span>
				<input type="file" name="verto_cv" required accept=".pdf,.doc,.docx" />
			</label>
			<label class="verto-apply-consent">
				<input type="checkbox" name="verto_consent" value="1" required />
				<span><?php echo esc_html( self::consent_text() ); ?></span>
			</label>

			<p class="verto-apply-error" data-apply-error hidden role="alert"></p>
			<button type="submit" class="btn-base btn-primary verto-apply-submit">Send application</button>
		</form>
		<?php
	}

	/**
	 * Inline apply card for the job detail page (single-verto_job.php) –
	 * same handler, same fields, no modal: the form sits at #apply with the
	 * job prefilled. Non-JS submissions bounce back to the job page with
	 * ?verto_apply=… (the banner below), JS submissions go async via
	 * verto-effects.js module 10.
	 */
	public static function render_inline( string $job_id, string $job_title, string $redirect = '' ) {
		// Non-JS flash from a previous submission (respond() redirect).
		$flash    = '';
		$flash_ok = false;
		if ( isset( $_GET['verto_apply'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$code     = sanitize_key( wp_unslash( $_GET['verto_apply'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages = self::messages();
			if ( isset( $messages[ $code ] ) ) {
				$flash    = $messages[ $code ];
				$flash_ok = ( 'ok' === $code );
			}
		}
		?>
		<div class="verto-apply-inline" data-verto-apply-card>
			<div class="verto-apply-modal__eyebrow">Apply</div>
			<h2 class="verto-apply-inline__title"><?php echo esc_html( '' !== $job_title ? 'Apply – ' . $job_title : 'Send us your application' ); ?></h2>
			<p class="verto-apply-inline__sub">Takes two minutes. The consultant who owns this desk reads every application personally.</p>
			<?php if ( '' !== $flash ) : ?>
				<div class="verto-apply-banner <?php echo $flash_ok ? 'is-ok' : 'is-error'; ?>" role="status" data-apply-flash><?php echo esc_html( $flash ); ?></div>
			<?php endif; ?>
			<?php if ( ! $flash_ok ) : ?>
				<?php self::render_form( 'inline', $job_id, $job_title, $redirect ); ?>
			<?php endif; ?>
			<div class="verto-apply-done" data-apply-done hidden>
				<div class="verto-apply-done__mark" aria-hidden="true">✓</div>
				<p><?php echo esc_html( self::messages()['ok'] ); ?></p>
			</div>
		</div>
		<?php
	}

	/* ── Admin list table ──────────────────────────────────────────────── */

	public static function admin_columns( $columns ) {
		return [
			'cb'             => $columns['cb'] ?? '<input type="checkbox" />',
			'title'          => 'Candidate – role',
			'verto_job'      => 'Job',
			'verto_vincere'  => 'Vincere push',
			'date'           => $columns['date'] ?? 'Date',
		];
	}

	public static function admin_column( $column, $post_id ) {
		if ( 'verto_job' === $column ) {
			$title = (string) get_post_meta( $post_id, '_job_title', true );
			$jid   = (string) get_post_meta( $post_id, '_vincere_job_id', true );
			echo esc_html( '' !== $title ? $title : 'General application' );
			if ( '' !== $jid ) {
				echo ' <span style="opacity:.6;">#' . esc_html( $jid ) . '</span>';
			}
		}
		if ( 'verto_vincere' === $column ) {
			$status = (string) get_post_meta( $post_id, '_vincere_status', true );
			if ( '' === $status ) {
				$status = '–';
			}
			$colour = '#666';
			if ( 0 === strpos( $status, 'Pushed' ) ) {
				$colour = 'green';
			} elseif ( 0 === strpos( $status, 'Test mode' ) ) {
				$colour = '#996800';
			} elseif ( '–' !== $status ) {
				$colour = '#c00';
			}
			printf( '<span style="color:%s;">%s</span>', esc_attr( $colour ), esc_html( $status ) );
		}
	}
}

Verto_Applications::boot();
