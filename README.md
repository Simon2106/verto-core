# verto-core

Shared WordPress design system for the Verto Group site family:
**Verto Group · Edison Lux · Vertek · ModulR** — four separate WP installs
(deliberately NOT multisite, so any site can be split out later), kept
consistent by this one repo.

The React prototype (repo: `verto-site`, staging: verto.on-forge.com) is the
design spec this recreates.

## Contents

    themes/verto-core/        Child theme of Hello Elementor
      functions.php           Brand selection, fonts, asset loading
      assets/css/tokens-*.css One tokens file per brand (guideline hexes/fonts)
      assets/css/verto-ui.css Shared UI: reveals, title-reveal, V-mask,
                              tooltips, jobs-board skin, view transitions
      assets/js/verto-effects.js  Scroll reveals + headline reveals + autoplay
    plugins/verto-widgets/    Elementor widgets: Title Reveal, V-Mask Media,
                              Jobs Board (Vincere wrapper)

## Per-site requirements

- WordPress + **Hello Elementor** theme (parent) + **Elementor Pro**
  (licence tier must cover 4 sites)
- This repo's theme + plugin installed (see deploy below)
- Brand set per site: Settings → General → **Verto Brand**
  (or `define('VERTO_BRAND', 'vertek');` in wp-config.php to lock it)
- Vincere→WordPress integration (client to confirm plugin/licence) —
  its shortcode goes into the "Verto Jobs Board" widget

## Provisioning on Forge (once per site)

1. Forge → New Site → domain (e.g. `verto-wp.on-forge.com`) →
   project type **WordPress** — Forge installs WP + DB for you.
   Repeat for `edisonlux-wp`, `vertek-wp`, `modulr-wp`.
2. SSH once: clone this repo to a shared path:

       git clone git@github.com:Simon2106/verto-core.git /home/forge/verto-core

3. Symlink the theme + plugin into each site:

       ln -s /home/forge/verto-core/themes/verto-core \
             /home/forge/SITE/public/wp-content/themes/verto-core
       ln -s /home/forge/verto-core/plugins/verto-widgets \
             /home/forge/SITE/public/wp-content/plugins/verto-widgets

4. In each WP admin: install Hello Elementor + Elementor (Pro), activate
   the **Verto Core** theme and **Verto Widgets** plugin, set the brand.
5. Updating all four sites = `git pull` in `/home/forge/verto-core`.
   (Optional: a tiny Forge "site" or scheduled job that pulls on push.)

## Elementor conventions

- Global colours/fonts per site should mirror the tokens file for its brand
  (single source of truth is the CSS custom properties; Elementor globals are
  set to match so editors pick brand colours by name).
- Page structure comes from the prototype — use it side-by-side while
  building templates.
- Custom widgets appear under the **Verto** category in the Elementor panel.

## Still to decide (see PRODUCTION-BUILD-NOTES.md in the prototype repo)

- Vincere plugin choice + licence, field mapping, internal-vs-client tagging
- Elementor Pro licence tier
- Forms plugin for non-job contact forms
