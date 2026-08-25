# Deploying the brand sites — vertek-wp.on-forge.com & edisonlux-wp.on-forge.com

Same pattern as MODULR-DEPLOY.md (modulr-wp.on-forge.com). One repo serves
every site; the `VERTO_BRAND` constant picks the brand.

## 1. Push the code (Mac)
cd ~/Dropbox\ \(Personal\)/V/Verto\ logos/verto-core
find .git -name "*.lock" -delete; find .git/objects -name "tmp_obj_*" -delete 2>/dev/null
git add -A && git commit -m "v0.9.0 WGO magazine + About revamp + timeline carousel + Vertek brand site" && git push

## 2. Forge: new sites
Forge → your server (bold-sun) → New Site (once per brand)
- Root domain: vertek-wp.on-forge.com   (then again: edisonlux-wp.on-forge.com)
- Project type: WordPress (same as verto-wp) → let Forge install WP + database

## 3. Server: pull repo + symlinks (prompt must say forge@bold-sun)
ssh forge@165.232.37.154
cd /home/forge/verto-core && git pull

# Vertek
ln -s /home/forge/verto-core/themes/verto-core /home/forge/vertek-wp.on-forge.com/public/wp-content/themes/verto-core
ln -s /home/forge/verto-core/plugins/verto-widgets /home/forge/vertek-wp.on-forge.com/public/wp-content/plugins/verto-widgets

# Edison Lux
ln -s /home/forge/verto-core/themes/verto-core /home/forge/edisonlux-wp.on-forge.com/public/wp-content/themes/verto-core
ln -s /home/forge/verto-core/plugins/verto-widgets /home/forge/edisonlux-wp.on-forge.com/public/wp-content/plugins/verto-widgets

## 4. Set the brand (still on server)
nano /home/forge/vertek-wp.on-forge.com/public/wp-config.php
Add ABOVE the "/* That's all, stop editing! */" line:
define( 'VERTO_BRAND', 'vertek' );

nano /home/forge/edisonlux-wp.on-forge.com/public/wp-config.php
Add ABOVE the "/* That's all, stop editing! */" line:
define( 'VERTO_BRAND', 'edison-lux' );

Save (Ctrl+O, Enter) exit (Ctrl+X), then: exit

## 5. wp-admin (per site: vertek-wp… / edisonlux-wp…/wp-admin)
- Complete the WP install wizard if shown (site title: Vertek / Edison Lux)
- Plugins → activate Elementor, then verto-widgets
- Appearance → Themes → activate "Verto Core" (install Hello Elementor first if prompted)
- Verto Setup → Build the Verto site  ← builds that brand's pages
- Settings → Permalinks → "Post name" → Save (needed for /about /clients etc.)

## 6. Check against the prototype
- Vertek:     compare with https://verto.on-forge.com/brands/vertek
  (Vertek also carries the sections the other brands don't have: About
  pillars + values accordion + journey rail; Clients process rail + case
  study band + testimonials; Candidates sectors chip grid + zigzag process
  + testimonial band.)
- Edison Lux: compare with https://verto.on-forge.com/brands/edison-lux

Rebuild is safe to re-run after any repo update that changes pages.
Note: verto-wp.on-forge.com and modulr-wp.on-forge.com need the same
`git pull`; their Rebuild keeps building their own brand.
