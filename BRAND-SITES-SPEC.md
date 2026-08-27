# Brand sites build spec (ModulR first)

Goal: `VERTO_BRAND=modulr` install + Verto Setup click = ModulR site matching
verto.on-forge.com/brands/modulr exactly. Same repo, brand-aware installer.

## Pages & menu (BrandHeader)
Home `/` · About `/about` · Clients `/clients` (prototype for-companies) ·
Candidates `/candidates` (for-candidates) · Insights `/insights`.
Header CTA = "Our Solutions" style btn-primary → /clients. Brand logo replaces Verto logo.

## Sections per page (prototype refs in "Verto Site - brand aligned")
HOME (brands.$brand.index.tsx):
1. brand-hero: ink bg, parallax img right 78% w/ left gradient wash, uppercase display-2
   tagline + accent + brand-colour full stop, sub para, 2 CTAs (solid + outline).
   ModulR: tagline "Connecting talent." / "Powering progress." hero img = modulr hero (globe).
2. feature-row: white bg, 4 cols, divider borders, lucide icon (brand colour), title, body ≤22ch.
   ModulR: Globe2 UK EU & US / Compass Curated Introductions / Lock NDA-Grade / Handshake Long-Game.
3. about-split: #f3f3f5; copy left (dash+eyebrow brand colour, 4xl-5xl headline = about.headline,
   mission, outline btn) | image right w/ black stats card overlay top-right (3 stats,
   brand-colour values, 2px brand dash per stat). ModulR img = data centre corridor.
4. specialisms: 6 cards, bg fg-6%, hover 3px top bar scale-x, icon, 01-06 numbers.
   Icons modulr: Server Network Building2 Zap Layers HeartHandshake.
5. logo-marquee (placeholder logos).
6. audience cards ×2: company=ink card, candidate=surface card; headline/body/bullets(brand dot)/CTA.
7. team-strip (reuse verto-team CPT filtered by brand meta — needs _verto_brand meta).
8. insights trio (posts-grid, brand category) + "View all →".

ABOUT (brands.$brand.about.tsx): dark parallax hero (aboutHero line1/accent/line2) ·
story split: narrative left + stats col vertical rule · pillars staggered 3 ·
values bordered grid · what-we-do split img left · mission/vision/purpose dark band ·
journey horizontal rail · proof numbered list (muted bg) · CTA "Ready to talk?".

CANDIDATES: dark hero (audiences.candidate.headline) · intro split img left ·
sectors chip grid · process zigzag rail · testimonial dark band · contact CTA.
CLIENTS (296l): mirrors candidates w/ company content + engage/process blocks — reread file when building.
INSIGHTS: listing page = posts archive (home.php pattern per brand).

## Data source
All copy in src/lib/brands.ts BRANDS['modulr'] (+ FEATURES/HERO_SUB/SPECIALISM_ICONS
in brands.$brand.index.tsx). Port verbatim into installer defaults.

## Implementation conventions (existing)
- installer.php: section()/section2()/widget()/upsert_page(); pages option verto_installer_pages.
- Brand switch: verto_current_brand() — Verto_Installer::handle_build() branches:
  verto → existing create_pages(); modulr/vertek/edison-lux → create_brand_site_pages($brand).
- Menu: brand installs get own menu (Home About Clients Candidates Insights).
- header.php: logo per brand — assets/img/{brand}-logo.(svg|png), CTA per brand.
- tokens-modulr.css already correct (Montserrat/Inter, #000A3B navy ink, #0464FA royal).
- New widgets needed: brand-hero, feature-row, about-split-stats, specialisms,
  logo-marquee, audience-cards, zigzag-process, chip-grid, quote-band, proof-list, cta-band.
  Reuse: team-grid (add brand filter), posts-grid, section-intro, timeline (journey rail).
- Media: assets/import/ needs modulr hero + data centre imgs (fetch from prototype src/assets).
- Lucide icons: inline the ~20 needed SVGs in a helper (no JS dep).

## Status
- [x] Audit (this file)
- [x] Widgets — brand-hero, feature-row (+stats variant), about-split
      (landing/panel/story), specialisms, logo-marquee, audience-cards,
      process-rail (cards3/zigzag/line), chip-grid, quote-band (hero/mission/
      testimonial modes), proof-list, cta-band + includes/icons.php (lucide
      inline). team-grid gained mode=strip + _verto_brand filter; posts-grid
      gained category filter + insights header row.
- [x] Brand-aware installer + modulr pages — handle_build() branches on
      verto_current_brand(); brand_content('modulr') holds all copy (vertek /
      edison-lux = fill their arrays); pages Home (front) / About / Clients /
      Candidates / Insights (page_for_posts); own Primary menu; modulr insight
      posts seeded under the "modulr" category; team seeding stamps _verto_brand.
- [x] Brand assets into assets/import/ — modulr-hero.png (modulr-hero-v2.png),
      modulr-datacentre.webp (data-centre-1.webp, original format kept),
      about-image.jpg, insight-datacentre.jpg, insight-architecture.jpg;
      import_media() keys modulr_hero / modulr_datacentre / about_image /
      insight_*.
- [x] header.php brand logo/CTA + menu — brand logo ({brand}-logo.svg|png in
      theme img, else imported media; modulr uses modulr-logo.png), sentence-
      case nav, "Our Solutions" → /clients CTA (gradient on modulr);
      footer.php ports BrandFooter (Explore / Other brands / Connect);
      home.php doubles as the brand Insights archive header.
- [x] Edison Lux brand data — brand_content('edison-lux') filled verbatim from
      BRANDS['edison-lux'] (+ FEATURES/HERO_SUB/SPECIALISM_ICONS); hero scale
      1.18 / offset 0 (non-modulr parallax); 3 edison insight posts seeded
      (category edison-lux, insight-power / insight-epc images); team_focus
      "US power & energy search."; icons.php gained flame / wind / hard-hat /
      wrench / atom / briefcase. Edison has no pillars / values / journey /
      process / testimonials / caseStudy / sectorsServed in the prototype —
      those sections are conditionally omitted there, so the ModulR-shaped
      pages are already exact.
- [x] Edison Lux assets into assets/import/ — edison-hero.webp (dam.webp),
      edison-pylon.webp (pylon-2.webp landingAbout), insight-power.jpg,
      insight-epc.jpg; import_media() keys edison_hero / edison_pylon /
      insight_power / insight_epc; header logo via imported edison-lux-logo.png
      (logo_edison); tokens-edison-lux.css already carries every var the
      widgets use; gradient stat-card override already in verto-ui.css.
- [x] Forge guide modulr-wp.on-forge.com (VERTO_BRAND in wp-config) —
      MODULR-DEPLOY.md; vertek-wp / edisonlux-wp steps in BRAND-DEPLOY.md.
- [x] Vertek brand data — brand_content('vertek') filled verbatim from
      BRANDS['vertek'] (+ FEATURES/HERO_SUB/SPECIALISM_ICONS); hero
      "Engineering / what's next", scale 1.18 / offset 0; about_image falls
      back to vertek_hero (no images.landingAbout in the prototype); 3
      vertek insight posts seeded (category vertek, insight-fluidpower /
      insight-manufacturing / insight-hvac images — 4th prototype insight
      "Interview prep" left out, three-per-brand convention); team_focus
      "Technical sales & engineering search."; icons.php gained gauge /
      thermometer / cog / factory / cpu / line-chart.
- [x] Vertek-ONLY sections wired (prototype renders these conditionally;
      modulr/edison have no data for them): About = pillars (process-rail
      cards3) + values (new verto-values-accordion widget) + journey
      (process-rail line_style=journey, brand-colour years); Clients =
      process (process-rail line) + caseStudy (quote-band case_client/
      case_sector + Challenge/Solution/Result grid) + testimonials
      (quote-band quotes_style=light); Candidates = sectorsServed
      (chip-grid) + candidateProcess (process-rail zigzag) + testimonials
      slice(2,4) (quote-band testimonial overlay). Page builders stay
      brand-agnostic — sections appear for any brand that adds the data.
- [x] Vertek assets into assets/import/ — vertek-hero.jpg (already present,
      now registered as vertek_hero) + insight-fluidpower/-manufacturing/
      -hvac.jpg; tokens-vertek.css gained --card/--card-foreground/
      --brand-steel (+ flat --brand-gradient alias — guidelines: Signal Red
      is a signal, not a field, so no real gradient); verto-ui.css gained
      the body.verto-brand-vertek scope (Saira SemiBold uppercase display,
      JetBrains Mono eyebrows, squared CTAs).
- [x] v0.9.0 group-site design round (both codebases): WGO magazine hub
      (featured newest story + category chips Trips/Wins/Community/News +
      Stories video placeholders + Instagram) in home.php / whats-going-on
      .tsx; About photo collage (verto-collage widget) + Community & DE&I
      placeholder cards; timeline auto-carousel (verto-effects.js §8 /
      TimelineCarousel in about.tsx). Rebuild required on existing installs.
- [x] v0.10.0 — the client's definitive team structure (Alex Hatfield,
      Aug 2026), both codebases. installer.php team_map() is the master map
      (name → brands[] / tier / role / photo); seed_team() now migrates in
      place per TEAM_STRUCTURE version: matches existing verto_team posts by
      post_title (renames: Monira Aktar→Akter, Oliver→Ollie Hesmondhalgh,
      Harley Oconnell→O'Connell), re-asserts _verto_brand (comma list,
      widget matches any via LIKE) + new _verto_tier + _verto_role +
      _verto_leader (trio), creates missing people, drafts (never deletes)
      people not in the official structure (Abi Ward, CJ Edwards, Chris J
      Simmons). team-grid gained a tier filter + leadership → management →
      team(+ops) ordering; group About renders Leadership / Management /
      The team sections; initials placeholder for people without photos
      (verto-team__initials CSS). Prototype: team.ts master map (brands[] /
      tier), /team + About three-tier order, TeamStrip per-brand splits.
      Rebuild required on ALL installs (group + brand sites).
      ⚠ NO HEADSHOT YET — client chase list (initials placeholder until
      supplied): Ben Cranston, Karabo Mothopeng, Angel Ndlovu, Alice Fryer,
      Megan Grant, Alfie Gray, Saman Akbari, Martyn Jamieson, Forough
      Rezaei.
      ⚠ Titles to confirm with client: George East / Ben Cranston / Sade
      Kendall are seeded with the placeholder role "Manager" (client gave
      the management list without titles); Martyn Jamieson seeded
      "Consultant" (Life Sciences desk, group site).
- [x] v0.11.0 — client media drop integrated (Aug 2026, both codebases).
      Sorted 3.2GB of event photography/film by matching each forwarded
      email's attachment filenames against the loose files: Barcelona
      incentive (10), charity gala (5), Ibiza (12), summer summit at
      Southsea Castle (9), plus a general pool (Prague / office life, 28).
      Curated + web-optimised (≤1600px q78 progressive; 800px card thumbs
      in the prototype) into assets/import/: barcelona-01..06, gala-01..04,
      ibiza-11..15 (continues ibiza8/9), summit-02..06, verto-01..05 — all
      registered in import_media(). Videos transcoded 720×1280 h264 CRF28
      + aac96k faststart, poster frame each:
        · milly-promotion.mp4 (13s, 3.3MB) — Milly Compton's promotion,
          confetti walk-in (identified against her bundled headshot);
        · sade-promotion.mp4 (16s, 4.4MB) — Sade Kendall's promotion
          announcement (matches her ModulR headshot);
        · sade-celebration.mp4 (15s, 3.0MB) — second clip of the same
          moment (the hug);
        · promotion-celebration.mp4 (12s, 3.1MB) — an (unnamed male
          colleague's) promotion landing, office applauding — client to
          confirm the name before captioning;
        · share-scheme.mp4 (58s, 6.6MB) — the "one word for the share
          scheme" / "what does it mean" interview film, picked from the
          Share scheme folder (the 108MB source; the 170MB/91s longer
          interview and the 10s duplicates were passed over).
      Wired: WGO seed_posts() now batch-versioned (verto_installer_posts_
      media option) so EXISTING installs gain the four new posts on
      Rebuild — Barcelona (Trips), Inside the summer summit (Community),
      Milly Compton promoted + Sade Kendall promoted (Wins, films embedded
      via [video] shortcode + poster); Prague/Ibiza posts' featured images
      upgraded to real Prague (Pilsner Urquell group) / Ibiza-sea shots.
      home.php Stories band now plays the three films (poster + controls,
      preload=none; dashed placeholder only if media missing). About:
      gala photos into the Community & DE&I gala cards (DE&I card stays
      placeholder), collage refreshed (ibiza-11 lead, summit letters,
      Barcelona W group). Careers: incentives section is now "Incentives
      & ownership" — Barcelona photo + click-to-play share-scheme film
      (verto-video-story CSS). Prototype mirrors all of it (insights.ts
      entries, whats-going-on Stories band, about collage/cards, careers
      ShareSchemeFilm + real Milly/Sade promotion cards).
      Rebuild required on the group install (media import + new posts).
