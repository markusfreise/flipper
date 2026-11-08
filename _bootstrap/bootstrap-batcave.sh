#!/usr/bin/env bash
# Einmalige Einrichtung von Flohmarkt auf der Batcave (PRD §15). Idempotent, fail-closed.
# Läuft auf dem Mac (oder jedem Rechner mit SSH-Root-Zugang zu den LXCs):
#
#   ./_bootstrap/bootstrap-batcave.sh
#
# Einziger Handgriff danach: Cloudflare Public Hostname (Schritt 6, wird am Ende angezeigt).
set -euo pipefail

DB_HOST=${DB_HOST:-root@192.168.2.110}
DOCKER_HOST_SSH=${DOCKER_HOST_SSH:-root@192.168.2.111}
CADDY_HOST=${CADDY_HOST:-root@192.168.2.113}
WEB_HOST=${WEB_HOST:-root@192.168.2.114}
REMBG_DIR=${REMBG_DIR:-/opt/stacks/rembg}
# Snippet im Caddyfile, das TLS per DNS-01 (Cloudflare) einrichtet – wie bei opus/gotham/vicky.
CADDY_SNIPPET=${CADDY_SNIPPET:-cloudflare_tls}
ADMIN_NAME=${ADMIN_NAME:-Markus}
ADMIN_EMAIL=${ADMIN_EMAIL:-markus@freise.de}
DOMAIN=flohmarkt.croeso.de
HERE="$(cd "$(dirname "$0")" && pwd)"
T="$HERE/templates"

SSH() { timeout 900 ssh -o BatchMode=yes -o ConnectTimeout=10 "$@"; }
say() { printf '\n\033[1;35m==> %s\033[0m\n' "$*"; }

say "0/10 Verbindungen prüfen"
for h in "$DB_HOST" "$DOCKER_HOST_SSH" "$CADDY_HOST" "$WEB_HOST"; do
  SSH "$h" true || { echo "Keine SSH-Verbindung zu $h" >&2; exit 1; }
done

say "7/10 Secrets in /opt/infra/.env auf 114 (nur fehlende werden ergänzt)"
SSH "$WEB_HOST" bash -s <<'REMOTE'
set -euo pipefail
f=/opt/infra/.env
install -d -m 750 /opt/infra
touch "$f"; chmod 600 "$f"
add() { grep -q "^$1=" "$f" || echo "$1=$2" >> "$f"; }
add FLOHMARKT_DB_PASS "$(openssl rand -hex 24)"
add FLOHMARKT_DEPLOY_SECRET "$(openssl rand -hex 32)"
add FLOHMARKT_APP_KEY "base64:$(openssl rand -base64 32)"
# Anthropic-Key von kachink übernehmen, falls er hier noch fehlt (Wert wird nie ausgegeben).
if ! grep -q '^ANTHROPIC_API_KEY=.\+' "$f"; then
  key=""
  for src in /srv/www/kachink/.env /srv/www/kachink-shared/.env /srv/www/kaachink/.env /srv/www/kaching/.env \
             $(ls -d /srv/www/*kachink*/.env /srv/www/*kaching*/.env 2>/dev/null); do
    [[ -f "$src" ]] || continue
    key=$(grep -m1 -E '^ANTHROPIC_API_KEY=' "$src" | cut -d= -f2- | tr -d "\"' \t\r")
    if [[ -n "$key" ]]; then echo "ANTHROPIC_API_KEY von $src übernommen."; break; fi
  done
  [[ -n "$key" ]] || { echo "ANTHROPIC_API_KEY weder in $f noch bei kachink gefunden. Bitte in $f eintragen." >&2; exit 1; }
  echo "ANTHROPIC_API_KEY=$key" >> "$f"
fi
grep -q '^NTFY_URL=' "$f" || echo "Hinweis: NTFY_URL fehlt in $f, Meldungen bleiben aus." >&2
REMOTE
DB_PASS=$(SSH "$WEB_HOST" "grep '^FLOHMARKT_DB_PASS=' /opt/infra/.env | cut -d= -f2-")

say "2/10 Datenbank flohmarkt auf 110"
printf '%s' "$DB_PASS" | SSH "$DB_HOST" bash -c 'set -euo pipefail
pass=$(cat)
cd /tmp
q() { sudo -u postgres psql -v ON_ERROR_STOP=1 -tAc "$1"; }
if [[ -z "$(q "SELECT 1 FROM pg_roles WHERE rolname='"'"'flohmarkt_app'"'"'")" ]]; then
  sudo -u postgres psql -v ON_ERROR_STOP=1 -v pw="$pass" -c "CREATE ROLE flohmarkt_app LOGIN PASSWORD :'"'"'pw'"'"'"
else
  sudo -u postgres psql -v ON_ERROR_STOP=1 -v pw="$pass" -c "ALTER ROLE flohmarkt_app PASSWORD :'"'"'pw'"'"'"
fi
if [[ -z "$(q "SELECT 1 FROM pg_database WHERE datname='"'"'flohmarkt'"'"'")" ]]; then
  q "CREATE DATABASE flohmarkt OWNER flohmarkt_app ENCODING '"'"'UTF8'"'"' LC_COLLATE '"'"'de_DE.UTF-8'"'"' LC_CTYPE '"'"'de_DE.UTF-8'"'"' TEMPLATE template0"
fi
echo "DB ok"'
echo "pg_hba.conf auf 110 muss 192.168.2.114 für flohmarkt/flohmarkt_app erlauben (wie bei den anderen Apps)."

say "1/10 rembg auf 111"
SSH "$DOCKER_HOST_SSH" "mkdir -p $REMBG_DIR"
scp -q "$T/rembg-compose.yml" "$DOCKER_HOST_SSH:$REMBG_DIR/compose.yml"
SSH "$DOCKER_HOST_SSH" "cd $REMBG_DIR && timeout 600 docker compose up -d && sleep 3 && curl -fsS -o /dev/null http://127.0.0.1:7000/api && echo 'rembg ok'"

say "3+8/10 Pakete, Checkout-Struktur, systemd auf 114"
SSH "$WEB_HOST" bash -s <<'REMOTE'
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
timeout 600 apt-get install -y -q php8.3-fpm php8.3-cli php8.3-pgsql php8.3-gd php8.3-zip php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-intl libheif-examples gettext-base postgresql-client >/dev/null
command -v npm >/dev/null || { echo "npm fehlt auf 114" >&2; exit 1; }
command -v composer >/dev/null || { echo "composer fehlt auf 114" >&2; exit 1; }
install -d -o www-data -g www-data /srv/www/flohmarkt-releases /srv/www/flohmarkt-shared
install -d /var/backups/flohmarkt
REMOTE
for f in flohmarkt-queue.service flohmarkt-schedule.service flohmarkt-schedule.timer flohmarkt-deploy.path flohmarkt-deploy.service; do
  scp -q "$T/$f" "$WEB_HOST:/etc/systemd/system/$f"
done
scp -q "$HERE/flohmarkt-deploy.sh" "$WEB_HOST:/usr/local/bin/flohmarkt-deploy.sh"
SSH "$WEB_HOST" "chmod 755 /usr/local/bin/flohmarkt-deploy.sh"

say "9/10 Erster Deploy"
SSH "$WEB_HOST" "/usr/local/bin/flohmarkt-deploy.sh --force"

say "4/10 nginx-Vhost auf 114"
scp -q "$T/nginx-flohmarkt.conf" "$WEB_HOST:/etc/nginx/sites-available/flohmarkt"
SSH "$WEB_HOST" "ln -sfn /etc/nginx/sites-available/flohmarkt /etc/nginx/sites-enabled/flohmarkt && nginx -t && systemctl reload nginx"
SSH "$WEB_HOST" "systemctl daemon-reload && systemctl enable --now flohmarkt-queue.service flohmarkt-schedule.timer flohmarkt-deploy.path"

say "5/10 Caddy auf 113"
CADDY_BLOCK=$(sed "s/\${CADDY_SNIPPET}/$CADDY_SNIPPET/" "$T/caddy-flohmarkt.caddy")
printf '%s\n' "$CADDY_BLOCK" | SSH "$CADDY_HOST" bash -c 'set -euo pipefail
f=/etc/caddy/Caddyfile
block=$(cat)
if grep -q "^flohmarkt.croeso.de" "$f"; then echo "Caddy-Block existiert schon"; exit 0; fi
cp "$f" "$f.bak-flohmarkt"
printf "\n%s\n" "$block" >> "$f"
if ! caddy validate --config "$f" --adapter caddyfile >/dev/null 2>&1; then
  mv "$f.bak-flohmarkt" "$f"
  echo "Caddyfile ungültig (Snippet-Name prüfen: CADDY_SNIPPET). Zurückgerollt." >&2
  exit 1
fi
systemctl restart caddy   # restart, nicht reload: Admin-API ist aus
echo "Caddy ok"'

say "10/10 Konten anlegen"
SSH "$WEB_HOST" "cd /srv/www/flohmarkt && sudo -u www-data php8.3 artisan db:seed --class=FamilySeeder --force --no-interaction"
SSH "$WEB_HOST" "cd /srv/www/flohmarkt && sudo -u www-data php8.3 artisan user:create '$ADMIN_NAME' '$ADMIN_EMAIL' --admin" || true

say "GitHub-Webhook"
SECRET=$(SSH "$WEB_HOST" "grep '^FLOHMARKT_DEPLOY_SECRET=' /opt/infra/.env | cut -d= -f2-")
if command -v gh >/dev/null && ! gh api repos/markusfreise/flohmarkt/hooks --jq '.[].config.url' 2>/dev/null | grep -q "$DOMAIN/deploy-hook"; then
  gh api repos/markusfreise/flohmarkt/hooks -f name=web -F active=true -f 'events[]=push' \
    -f config[url]="https://$DOMAIN/deploy-hook" -f config[content_type]=json -f config[secret]="$SECRET" >/dev/null \
    && echo "Webhook angelegt." || echo "Webhook bitte von Hand anlegen (URL https://$DOMAIN/deploy-hook, Secret = FLOHMARKT_DEPLOY_SECRET aus /opt/infra/.env)."
fi

cat <<TXT

────────────────────────────────────────────────────────────────────
 GATE (manuell, einmalig): Cloudflare Zero Trust → Networks → Tunnels
   → Tunnel der Batcave → Public Hostname hinzufügen
     Subdomain: flohmarkt   Domain: croeso.de
     Service:   HTTPS 192.168.2.113:443
     Additional settings → TLS → Origin Server Name: $DOMAIN
 Danach prüfen:  curl -sI https://$DOMAIN/up   → HTTP 200
────────────────────────────────────────────────────────────────────
TXT
