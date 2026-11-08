#!/usr/bin/env bash
# Einmalige Einrichtung von Flipper auf der Batcave (PRD §15). Idempotent, fail-closed.
# Läuft auf dem Mac:
#
#   ./_bootstrap/bootstrap-batcave.sh
#
# Zugang (Stand 2026-11, so wie ~/.ssh/config auf dem Mac eingerichtet ist):
#   - 114 (web) direkt per SSH-Alias "web" (ProxyJump batcave)
#   - 110 (data), 111 (docker), 113 (edge) über den Proxmox-Host: ssh batcave "pct exec <id>"
#     (dort ist der Mac-Key nicht hinterlegt, pct exec braucht keinen)
#
# Einziger Handgriff danach: Cloudflare Public Hostname (wird am Ende angezeigt).
set -euo pipefail

PVE_HOST=${PVE_HOST:-batcave}
WEB_HOST=${WEB_HOST:-web}
CT_DB=${CT_DB:-110}
CT_DOCKER=${CT_DOCKER:-111}
CT_EDGE=${CT_EDGE:-113}
REMBG_DIR=${REMBG_DIR:-/opt/stacks/rembg}     # wie die übrigen Compose-Stacks auf 111
BASE=/var/www                                  # wie die übrigen Apps auf 114
DOMAIN=flipper.croeso.de
HERE="$(cd "$(dirname "$0")" && pwd)"
T="$HERE/templates"

# macOS hat timeout nur über coreutils
if ! command -v timeout >/dev/null; then
  command -v gtimeout >/dev/null || { echo "timeout fehlt (brew install coreutils)" >&2; exit 1; }
  timeout() { gtimeout "$@"; }
fi

SSH() { timeout 900 ssh -o BatchMode=yes -o ConnectTimeout=10 "$@"; }
# Skript von stdin in einem LXC ausführen: CT <id> <<'EOF' … EOF
CT() { SSH "$PVE_HOST" "pct exec $1 -- bash -s"; }
say() { printf '\n==> %s\n' "$*"; }

say "1/9 Verbindungen prüfen"
SSH "$WEB_HOST" true || { echo "Keine SSH-Verbindung zu $WEB_HOST" >&2; exit 1; }
for id in "$CT_DB" "$CT_DOCKER" "$CT_EDGE"; do
  echo true | CT "$id" || { echo "pct exec $id über $PVE_HOST geht nicht" >&2; exit 1; }
done
echo "ok"

say "2/9 Secrets in /opt/infra/.env auf 114 (nur fehlende werden ergänzt)"
SSH "$WEB_HOST" bash -s <<'REMOTE'
set -euo pipefail
f=/opt/infra/.env
install -d -m 755 /opt/infra
touch "$f"; chmod 600 "$f"
add() { grep -q "^$1=" "$f" || echo "$1=$2" >> "$f"; }
grep -q '^# Flipper' "$f" || printf '\n# Flipper (bootstrap-batcave.sh)\n' >> "$f"
add FLIPPER_DB_PASS "$(openssl rand -hex 24)"
add FLIPPER_DEPLOY_SECRET "$(openssl rand -hex 32)"
add FLIPPER_APP_KEY "base64:$(openssl rand -base64 32)"
# Anthropic-Key von lucius übernehmen (Fallback kachink), falls er hier noch fehlt. Wert wird nie ausgegeben.
if ! grep -q '^ANTHROPIC_API_KEY=.\+' "$f"; then
  key=""
  for src in /var/www/lucius-app/.env /var/www/kachink/api/.env /var/www/kachink/.env; do
    [[ -f "$src" ]] || continue
    key=$(grep -m1 -E '^ANTHROPIC_API_KEY=' "$src" | cut -d= -f2- | tr -d "\"' \t\r")
    if [[ -n "$key" ]]; then echo "ANTHROPIC_API_KEY von $src übernommen."; break; fi
  done
  [[ -n "$key" ]] || { echo "ANTHROPIC_API_KEY weder in $f noch bei lucius/kachink gefunden. Bitte in $f eintragen." >&2; exit 1; }
  echo "ANTHROPIC_API_KEY=$key" >> "$f"
fi
grep -q '^NTFY_URL=.\+' "$f" || echo "Hinweis: NTFY_URL fehlt in $f, Meldungen bleiben aus." >&2
echo "ok"
REMOTE
DB_PASS=$(SSH "$WEB_HOST" "grep '^FLIPPER_DB_PASS=' /opt/infra/.env | cut -d= -f2-")
[[ "$DB_PASS" =~ ^[0-9a-f]{48}$ ]] || { echo "FLIPPER_DB_PASS hat ein unerwartetes Format" >&2; exit 1; }

say "3/9 Datenbank flipper auf $CT_DB"
# Passwort steckt im Skript auf stdin, nicht in einer Kommandozeile (ps). Hex, also quote-sicher.
CT "$CT_DB" <<REMOTE
set -euo pipefail
cd /tmp
q() { runuser -u postgres -- psql -v ON_ERROR_STOP=1 -tA "\$@"; }
if [[ -z "\$(q -c "SELECT 1 FROM pg_roles WHERE rolname='flipper_app'")" ]]; then
  echo "CREATE ROLE flipper_app LOGIN PASSWORD '$DB_PASS';" | q >/dev/null
else
  echo "ALTER ROLE flipper_app PASSWORD '$DB_PASS';" | q >/dev/null
fi
if [[ -z "\$(q -c "SELECT 1 FROM pg_database WHERE datname='flipper'")" ]]; then
  q -c "CREATE DATABASE flipper OWNER flipper_app ENCODING 'UTF8' LC_COLLATE 'de_DE.UTF-8' LC_CTYPE 'de_DE.UTF-8' TEMPLATE template0" >/dev/null
fi
# pg_hba: 114 muss per scram durchkommen (bisher über die Regel für 192.168.2.0/24)
hba=\$(q -c "SHOW hba_file")
grep -qE '^host\s+all\s+all\s+(192\.168\.2\.0/24|192\.168\.2\.114/32)\s+scram-sha-256' "\$hba" \
  || { echo "pg_hba.conf erlaubt 192.168.2.114 nicht, bitte prüfen: \$hba" >&2; exit 1; }
echo "DB ok"
REMOTE

say "4/9 rembg auf $CT_DOCKER ($REMBG_DIR)"
{
  echo "set -euo pipefail; mkdir -p $REMBG_DIR; cat > $REMBG_DIR/compose.yml <<'YML'"
  cat "$T/rembg-compose.yml"
  echo "YML"
  cat <<REMOTE
cd $REMBG_DIR
timeout 600 docker compose up -d
for i in \$(seq 1 60); do
  timeout 5 bash -c 'exec 3<>/dev/tcp/192.168.2.111/7000' 2>/dev/null && { echo "rembg ok"; exit 0; }
  sleep 3
done
echo "rembg antwortet nicht auf 192.168.2.111:7000" >&2; exit 1
REMOTE
} | CT "$CT_DOCKER"

say "5/9 Pakete, Verzeichnisse, systemd auf 114"
SSH "$WEB_HOST" bash -s <<REMOTE
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
need=""
for p in php8.3-fpm php8.3-cli php8.3-pgsql php8.3-gd php8.3-zip php8.3-mbstring php8.3-xml php8.3-curl php8.3-intl \
         libheif-examples gettext-base postgresql-client; do
  dpkg -s "\$p" >/dev/null 2>&1 || need="\$need \$p"
done
if [[ -n "\$need" ]]; then
  echo "Installiere:\$need"
  timeout 300 apt-get update -q >/dev/null
  timeout 600 apt-get install -y -q \$need >/dev/null
fi
command -v npm >/dev/null || { echo "npm fehlt auf 114" >&2; exit 1; }
command -v composer >/dev/null || { echo "composer fehlt auf 114" >&2; exit 1; }
# Lesende Deploy-Keys (je Repo einer), Aliasse in /root/.ssh/config
for r in flipper batcave-ui; do
  timeout 30 git ls-remote "github-\$r:markusfreise/\$r.git" HEAD >/dev/null \
    || { echo "Deploy-Key für \$r fehlt oder ist auf GitHub nicht hinterlegt (Alias github-\$r)" >&2; exit 1; }
done
install -d -o www-data -g www-data $BASE/flipper-releases $BASE/flipper-shared
install -d -m 750 /var/backups/flipper
echo "ok"
REMOTE
for f in flipper-queue.service flipper-schedule.service flipper-schedule.timer flipper-deploy.path flipper-deploy.service; do
  scp -q "$T/$f" "$WEB_HOST:/etc/systemd/system/$f"
done
scp -q "$HERE/flipper-deploy.sh" "$WEB_HOST:/usr/local/bin/flipper-deploy.sh"
SSH "$WEB_HOST" "chmod 755 /usr/local/bin/flipper-deploy.sh && systemctl daemon-reload"

say "6/9 nginx-Vhost auf 114 (vor dem Deploy, dessen Smoke-Test läuft über nginx)"
scp -q "$T/nginx-flipper.conf" "$WEB_HOST:/etc/nginx/sites-available/flipper"
SSH "$WEB_HOST" "ln -sfn /etc/nginx/sites-available/flipper /etc/nginx/sites-enabled/flipper && nginx -t 2>&1 | tail -1 && systemctl reload nginx"

say "7/9 Erster Deploy und Dienste"
SSH "$WEB_HOST" "/usr/local/bin/flipper-deploy.sh --force"
SSH "$WEB_HOST" "systemctl enable --now flipper-queue.service flipper-schedule.timer flipper-deploy.path"
code=$(SSH "$WEB_HOST" "curl -s -o /dev/null -w '%{http_code}' -H 'Host: $DOMAIN' http://127.0.0.1/up")
[[ "$code" == 200 ]] || { echo "nginx liefert $code statt 200 für $DOMAIN/up" >&2; exit 1; }
echo "nginx ok"

say "8/9 Caddy auf $CT_EDGE"
# Der Wildcard-Block *.croeso.de (TLS per DNS-01, Cloudflare) reicht schon alles an 114:80 weiter.
# Dann kein eigener Block: Admin-API ist aus, jede Änderung hieße Restart für alle Seiten auf der Edge.
CADDY_BLOCK=$(cat "$T/caddy-flipper.caddy")
{
  echo "block=\$(cat <<'CADDY'"
  printf '%s\n' "$CADDY_BLOCK"
  echo "CADDY"
  echo ")"
  cat <<'REMOTE'
set -euo pipefail
f=/etc/caddy/Caddyfile
if grep -q "^flipper\.croeso\.de" "$f"; then echo "Caddy-Block existiert schon"; exit 0; fi
if awk '/^\*\.croeso\.de/{p=1} p&&/reverse_proxy/{print; exit}' "$f" | grep -q '192\.168\.2\.114:80'; then
  echo "Wildcard *.croeso.de deckt flipper ab, Caddyfile bleibt unverändert"; exit 0
fi
cp "$f" "$f.bak-flipper"
printf "\n%s\n" "$block" >> "$f"
set -a; . /etc/caddy/caddy.env; set +a
if ! caddy validate --config "$f" --adapter caddyfile >/dev/null 2>&1; then
  mv "$f.bak-flipper" "$f"
  echo "Caddyfile ungültig, zurückgerollt." >&2
  exit 1
fi
systemctl restart caddy   # restart, nicht reload: Admin-API ist aus
echo "Caddy ok"
REMOTE
} | CT "$CT_EDGE"

say "9/9 Konten und GitHub-Webhook"
# FamilySeeder: Konten aus _project-assets/family.json, sonst nur Markus. Bestehende bleiben unverändert.
SSH "$WEB_HOST" "cd $BASE/flipper && runuser -u www-data -- php8.3 artisan db:seed --class=FamilySeeder --force --no-interaction"

SECRET=$(SSH "$WEB_HOST" "grep '^FLIPPER_DEPLOY_SECRET=' /opt/infra/.env | cut -d= -f2-")
if ! gh api repos/markusfreise/flipper/hooks --jq '.[].config.url' 2>/dev/null | grep -q "$DOMAIN/deploy-hook"; then
  gh api repos/markusfreise/flipper/hooks -f name=web -F active=true -f 'events[]=push' \
    -f "config[url]=https://$DOMAIN/deploy-hook" -f config[content_type]=json -f "config[secret]=$SECRET" >/dev/null \
    && echo "Webhook angelegt." \
    || { echo "Webhook bitte von Hand anlegen (URL https://$DOMAIN/deploy-hook, Secret = FLIPPER_DEPLOY_SECRET aus /opt/infra/.env)." >&2; exit 1; }
else
  echo "Webhook existiert schon."
fi
unset SECRET DB_PASS

cat <<TXT

--------------------------------------------------------------------
 GATE (manuell, einmalig): Cloudflare Zero Trust -> Networks -> Tunnels
   -> Tunnel der Batcave -> Public Hostname hinzufügen
     Subdomain: flipper   Domain: croeso.de
     Service:   HTTPS 192.168.2.113:443
     Additional settings -> TLS -> Origin Server Name: $DOMAIN
 Danach prüfen:  curl -sI https://$DOMAIN/up   -> HTTP 200
--------------------------------------------------------------------
TXT
