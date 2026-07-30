# Deploying modulr-wp.on-forge.com

## 1. Push the code (Mac)
cd ~/Dropbox\ \(Personal\)/V/Verto\ logos/verto-core
find .git -name "*.lock" -delete; find .git/objects -name "tmp_obj_*" -delete 2>/dev/null
git add -A && git commit -m "v0.6.0 brand-aware installer + ModulR site" && git push

## 2. Forge: new site
Forge → your server (bold-sun) → New Site
- Root domain: modulr-wp.on-forge.com
- Project type: WordPress (same as verto-wp) → let Forge install WP + database

## 3. Server: pull repo + symlink (prompt must say forge@bold-sun)
ssh forge@165.232.37.154
cd /home/forge/verto-core && git pull
ln -s /home/forge/verto-core/themes/verto-core /home/forge/modulr-wp.on-forge.com/public/wp-content/themes/verto-core
ln -s /home/forge/verto-core/plugins/verto-widgets /home/forge/modulr-wp.on-forge.com/public/wp-content/plugins/verto-widgets

## 4. Set the brand (still on server)
nano /home/forge/modulr-wp.on-forge.com/public/wp-config.php
Add ABOVE the "/* That's all, stop editing! */" line:
define( 'VERTO_BRAND', 'modulr' );
Save (Ctrl+O, Enter) exit (Ctrl+X), then: exit

## 5. wp-admin (modulr-wp.on-forge.com/wp-admin)
- Complete the WP install wizard if shown (site title: ModulR)
- Plugins → activate Elementor, then verto-widgets
- Appearance → Themes → activate "Verto Core" (install Hello Elementor first if prompted)
- Verto Setup → Build the Verto site  ← builds the ModulR pages
- Settings → Permalinks → "Post name" → Save (needed for /about /clients etc.)

## 6. Check against the prototype
Compare side-by-side with https://verto.on-forge.com/brands/modulr
Rebuild is safe to re-run after any repo update that changes pages.

Note: verto-wp.on-forge.com also needs the same pull; its Rebuild is unaffected
(brand=verto keeps building the group site).
