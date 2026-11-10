#!/usr/bin/env bash
# Verto auto-deploy – run from cron every 2 minutes as the forge user:
#   */2 * * * * bash /home/forge/verto-core/scripts/auto-deploy.sh
#
# Pulls the shared verto-core repo; when (and only when) new commits land,
# runs `wp verto rebuild` on every site so content changes go live without
# any wp-admin clicks. CSS/JS-only pushes still deploy instantly via the
# pull; the rebuild after them is harmless (the installer is idempotent).
# Activity lands in /home/forge/verto-deploy.log.

set -u

REPO=/home/forge/verto-core
LOG=/home/forge/verto-deploy.log
SITES="verto-wp.on-forge.com edisonlux-wp.on-forge.com modulr-wp.on-forge.com vertek-wp.on-forge.com"

# Never run two deploys at once (a rebuild can outlast the 2-min cron gap).
exec 9>/tmp/verto-deploy.lock
flock -n 9 || exit 0

cd "$REPO" || exit 1
git fetch -q origin || exit 1

LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main)
[ "$LOCAL" = "$REMOTE" ] && exit 0

git merge -q --ff-only origin/main || { echo "$(date -u '+%F %T') merge FAILED at $REMOTE" >> "$LOG"; exit 1; }
echo "$(date -u '+%F %T') pulled $(git rev-parse --short HEAD) – rebuilding sites" >> "$LOG"

WP=$(command -v wp || echo /usr/local/bin/wp)
for SITE in $SITES; do
	if [ -d "/home/forge/$SITE/public" ]; then
		echo "--- $SITE" >> "$LOG"
		"$WP" --path="/home/forge/$SITE/public" verto rebuild >> "$LOG" 2>&1
	fi
done
echo "$(date -u '+%F %T') deploy complete" >> "$LOG"
