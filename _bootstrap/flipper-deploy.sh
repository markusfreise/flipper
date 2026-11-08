#!/usr/bin/env bash
# Flipper-Deploy auf LXC 114 → /usr/local/bin/flipper-deploy.sh
#
# Fail-closed: Es wird in einem neuen Release-Verzeichnis gebaut und migriert.
# Erst wenn alles grün ist, zeigt der Symlink /var/www/flipper auf das neue Release.
# Bricht ein Schritt ab, läuft der alte Stand unverändert weiter.
#
#   flipper-deploy.sh            # deployt origin/main, wenn es etwas Neues gibt
#   flipper-deploy.sh --force    # baut auch ohne neuen Commit
set -euo pipefail

APP=flipper
# Lesende Deploy-Keys je Repo, Aliasse in /root/.ssh/config auf 114
REPO="github-flipper:markusfreise/flipper.git"
UI_REPO="github-batcave-ui:markusfreise/batcave-ui.git"
BRANCH=main
BASE=/var/www
LIVE="$BASE/$APP"                    # Symlink → aktuelles Release
RELEASES="$BASE/$APP-releases"
SHARED="$BASE/$APP-shared"           # storage/ und .env, über Releases hinweg
CACHE="$BASE/$APP-repo"              # Bare-Mirror für schnelle Fetches
BACKUPS=/var/backups/$APP
INFRA_ENV=/opt/infra/.env
PHP=php8.3
KEEP=5
LOCK=/run/lock/$APP-deploy.lock
FORCE=${1:-}

# Läuft auch aus systemd (flipper-deploy.service), dort fehlen HOME und ein brauchbarer PATH.
export HOME="${HOME:-/root}"
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

exec 9>"$LOCK"
flock -n 9 || { echo "Deploy läuft bereits."; exit 0; }

# Secrets nur aus /opt/infra/.env
set -a
# shellcheck disable=SC1090
source "$INFRA_ENV"
set +a
for v in FLIPPER_APP_KEY FLIPPER_DB_PASS ANTHROPIC_API_KEY FLIPPER_DEPLOY_SECRET; do
  [[ -n "${!v:-}" ]] || { echo "FEHLT in $INFRA_ENV: $v" >&2; exit 1; }
done
export NTFY_URL="${NTFY_URL:-}"

notify() { # $1 Titel, $2 Text, $3 Priorität
  [[ -n "$NTFY_URL" ]] || return 0
  timeout 10 curl -fsS -H "Title: $1" -H "Priority: ${3:-default}" -H "Tags: flipper" \
    -d "$2" "${NTFY_URL%/}/flipper" >/dev/null || true
}

STEP="Start"
on_error() {
  echo "Deploy FAILED bei: $STEP" >&2
  notify "Flipper: Deploy FAILED" "Schritt: $STEP. Alter Stand läuft weiter." high
  [[ -n "${NEW:-}" && -d "${NEW:-}" && "$(readlink -f "$LIVE" 2>/dev/null)" != "$NEW" ]] && rm -rf "$NEW"
}
trap on_error ERR

STEP="git fetch"
if [[ ! -d "$CACHE" ]]; then
  timeout 300 git clone --mirror "$REPO" "$CACHE"
fi
timeout 120 git -C "$CACHE" fetch --prune origin "+refs/heads/*:refs/heads/*"
SHA=$(git -C "$CACHE" rev-parse "$BRANCH")
CURRENT=$(basename "$(readlink -f "$LIVE" 2>/dev/null || echo none)")
if [[ "$CURRENT" == "$SHA" && "$FORCE" != "--force" ]]; then
  echo "Schon auf $SHA, nichts zu tun."
  exit 0
fi

NEW="$RELEASES/$SHA"
STEP="Checkout $SHA"
rm -rf "$NEW"
mkdir -p "$RELEASES"
timeout 120 git clone --quiet --shared "$CACHE" "$NEW"
git -C "$NEW" checkout --quiet "$SHA"
git -C "$NEW" submodule init
git -C "$NEW" config submodule.resources/batcave-ui.url "$UI_REPO"
timeout 120 git -C "$NEW" submodule update --recursive

STEP=".env rendern"
mkdir -p "$SHARED/storage"
TEMPLATE="$NEW/_bootstrap/templates/env.production"
envsubst '${FLIPPER_APP_KEY} ${FLIPPER_DB_PASS} ${ANTHROPIC_API_KEY} ${NTFY_URL} ${FLIPPER_DEPLOY_SECRET}' \
  < "$TEMPLATE" > "$SHARED/.env.new"
chmod 640 "$SHARED/.env.new"
chown root:www-data "$SHARED/.env.new"
mv "$SHARED/.env.new" "$SHARED/.env"

STEP="Shared-Verzeichnisse verlinken"
if [[ ! -d "$SHARED/storage/app" ]]; then
  cp -a "$NEW/storage/." "$SHARED/storage/"
fi
mkdir -p "$SHARED/storage/app/private/deploy" "$SHARED/storage/logs" \
  "$SHARED/storage/framework/"{cache,sessions,views}
rm -rf "$NEW/storage"
ln -s "$SHARED/storage" "$NEW/storage"
ln -s "$SHARED/.env" "$NEW/.env"
ln -sfn "$SHARED/_project-assets" "$NEW/_project-assets" 2>/dev/null || true

STEP="composer install"
cd "$NEW"
COMPOSER_ALLOW_SUPERUSER=1 timeout 600 $PHP /usr/local/bin/composer install \
  --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

STEP="npm ci + build"
timeout 600 npm ci --no-audit --no-fund
timeout 300 npm run build
rm -rf node_modules

STEP="Backup (pg_dump)"
mkdir -p "$BACKUPS"
PGPASSWORD="$FLIPPER_DB_PASS" timeout 300 pg_dump -h 192.168.2.110 -U flipper_app -d flipper \
  -Fc -f "$BACKUPS/pre-deploy-$(date +%Y%m%d-%H%M%S)-${SHA:0:7}.dump"
find "$BACKUPS" -name 'pre-deploy-*.dump' -mtime +30 -delete

STEP="migrate"
timeout 300 $PHP artisan migrate --force --no-interaction

STEP="Caches"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
chown -R www-data:www-data "$NEW" "$SHARED/storage"

STEP="Umschalten"
ln -sfn "$NEW" "$LIVE.tmp"
mv -Tf "$LIVE.tmp" "$LIVE"
systemctl reload php8.3-fpm
$PHP "$LIVE/artisan" queue:restart

STEP="Smoke-Test"
code=$(timeout 15 curl -s -o /dev/null -w '%{http_code}' -H 'Host: flipper.croeso.de' http://127.0.0.1/up || true)
if [[ "$code" != "200" ]]; then
  PREV=$(ls -1dt "$RELEASES"/*/ | sed -n 2p)
  if [[ -n "$PREV" ]]; then
    ln -sfn "${PREV%/}" "$LIVE.tmp" && mv -Tf "$LIVE.tmp" "$LIVE"
    systemctl reload php8.3-fpm
  fi
  false
fi

STEP="Aufräumen"
ls -1dt "$RELEASES"/*/ | tail -n +$((KEEP + 1)) | xargs -r rm -rf
trap - ERR
echo "Deploy OK: $SHA"
notify "Flipper: Deploy OK" "$(git -C "$CACHE" log -1 --format='%h %s' "$SHA")"
