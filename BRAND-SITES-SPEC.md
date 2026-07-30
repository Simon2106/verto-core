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
- [ ] Forge guide modulr-wp.on-forge.com (VERTO_BRAND in wp-config)
