# Vincere → WordPress setup

The `verto-widgets` plugin ships a Vincere CRM integration
(`plugins/verto-widgets/includes/vincere.php`) that pulls **open jobs** from the
client's Vincere tenant into the Jobs Board widget. No credentials live in this
repo — everything comes from `wp-config.php`.

## 1. wp-config.php

Add these three lines **above** `/* That's all, stop editing! */`, replacing the
placeholders with the real values (kept in the password manager — never commit
them):

```php
define( 'VINCERE_TENANT',    'vertopeople.vincere.io' );   // tenant host, no https://
define( 'VINCERE_CLIENT_ID', 'REPLACE_WITH_CLIENT_ID' );
define( 'VINCERE_API_KEY',   'REPLACE_WITH_API_KEY' );
```

> **Rotate the emailed password.** If any Vincere credential (login password,
> client id, API key) was ever sent over email, log in to Vincere and rotate it
> now, then update the values above. Email is not a secure channel.

## 2. Redirect URL registered with Vincere

The OAuth redirect URL registered in Vincere (Settings → App Store → API
Authentication & Throttling) is:

```
https://verto-wp.on-forge.com/vincere/callback/
```

The plugin implements exactly that path via a rewrite rule. If the connect
round-trip 404s, flush permalinks once: **wp-admin → Settings → Permalinks →
Save Changes** (the plugin also auto-flushes on activation/update).

## 3. Connect flow

1. In wp-admin go to **Verto Setup → Vincere**. The page shows whether the three
   constants are present.
2. Click **Connect to Vincere**. You are sent to
   `https://id.vincere.io/oauth2/authorize` (with a CSRF `state` param) and log
   in with the client's Vincere account.
3. Vincere redirects back to `/vincere/callback/?code=…&state=…`. The plugin
   verifies the state, exchanges the code at `https://id.vincere.io/oauth2/token`,
   stores the short-lived `id_token` (transient) and the long-lived
   `refresh_token` (option, autoload off), queues the first sync, and returns
   you to the admin page with a success notice.
4. Jobs then sync **hourly** via WP-cron (plus the **Sync now** button). Synced
   jobs land in the **Jobs (Vincere)** post type; jobs that disappear from the
   feed are automatically drafted/deactivated.

API calls go to `https://{VINCERE_TENANT}/api/v2/position/search/…` with the
`id-token` and `x-api-key` headers, filtered to open jobs
(`closed_date:isnull`).

## 4. Re-authorising

The `id_token` expires after ~30–60 minutes and is refreshed automatically with
the stored `refresh_token`. If the refresh token itself is revoked or expires,
the plugin shows a persistent admin error notice and emails the site admin —
fix it by clicking **Re-connect to Vincere** on Verto Setup → Vincere and
logging in again.

## 5. Brand mapping

The board colour-codes each job to one of the four site brands. On
**Verto Setup → Vincere**:

- **Vincere brand field** — which position field carries the Group/Brand
  (default `group`; the sync automatically falls back through `division`,
  `brand`, `functional_expertise`, `industry`, `company` if the configured
  field is empty).
- **Brand keyword map** — `keyword=slug` lines matched case-insensitively as
  substrings against that field's value. Defaults:

  ```
  edison=edison-lux
  lux=edison-lux
  vertek=vertek
  modulr=modulr
  verto=verto
  ```

  Unmatched jobs default to `verto`.
- **Internal-roles marker** — comma-separated markers (default
  `Internal, Verto Careers`). A job whose brand field, company or title
  contains a marker is flagged internal; with "Only internal roles" ticked
  (the default) the board shows just those, since it advertises seats on
  Verto's own desks, not client vacancies.
- **Apply URL base** — optional Vincere job-portal base URL; the Vincere job id
  is appended. Left blank, rows use the widget's click-through URL.

Until a first successful sync, the Jobs Board renders its built-in placeholder
roles, so the page never looks empty.
