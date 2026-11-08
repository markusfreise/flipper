#!/usr/bin/env bash
# Lokale Umgebung auf dem Mac (Valet + Postgres + optional rembg via Docker). Idempotent.
#   ./_bootstrap/bootstrap-local.sh
set -euo pipefail
cd "$(dirname "$0")/.."

# Vite 8 braucht Node >= 20.19. Ein altes /usr/local/bin/node (Installer-Paket) verdeckt
# sonst das Homebrew-Node; dann Homebrew vorziehen.
node_major() { node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0; }
if (( $(node_major) < 20 )) && [[ -x /opt/homebrew/bin/node ]]; then
  export PATH="/opt/homebrew/bin:$PATH"
  echo "Hinweis: $(command -v node) wird genutzt ($(node -v)); ein älteres Node steht im PATH davor."
fi
(( $(node_major) >= 20 )) || { echo "Node >= 20.19 nötig, gefunden: $(node -v 2>/dev/null)" >&2; exit 1; }

git submodule update --init --recursive

composer install

[[ -f .env ]] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate

# Datenbanken (lokale Postgres, z. B. Postgres.app oder brew)
if command -v psql >/dev/null; then
  psql -d postgres -tAc "SELECT 1 FROM pg_roles WHERE rolname='flipper_app'" | grep -q 1 \
    || psql -d postgres -c "CREATE ROLE flipper_app LOGIN PASSWORD 'secret' CREATEDB"
  for db in flipper_local flipper_test; do
    psql -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='$db'" | grep -q 1 \
      || psql -d postgres -c "CREATE DATABASE $db OWNER flipper_app ENCODING 'UTF8' TEMPLATE template0"
  done
  grep -q '^DB_PASSWORD=.\+' .env || sed -i '' 's/^DB_PASSWORD=$/DB_PASSWORD=secret/' .env
fi

npm install
php artisan migrate
npm run build

# Valet: http://flipper.test
# "valet link" ist nur ein Symlink in ~/.config/valet/Sites; direkt anlegen, damit kein sudo nötig ist.
if [[ -d "$HOME/.config/valet/Sites" ]]; then
  ln -sfn "$PWD" "$HOME/.config/valet/Sites/flipper" && echo "Valet: http://flipper.test"
fi

# rembg lokal (Docker Desktop). Host-Port 7010, weil der AirPlay-Empfänger von macOS 7000 belegt.
# Das Image gibt es nur für amd64, Docker Desktop emuliert es.
if command -v docker >/dev/null && ! docker info >/dev/null 2>&1; then
  echo "Docker läuft nicht, rembg wird übersprungen (Docker Desktop starten und Skript erneut ausführen)." >&2
elif command -v docker >/dev/null && ! docker ps --format '{{.Names}}' | grep -q '^flipper-rembg$'; then
  docker run -d --name flipper-rembg --platform linux/amd64 -p 7010:7000 -v flipper-rembg:/root/.u2net -e U2NET_HOME=/root/.u2net \
    --restart unless-stopped danielgatis/rembg:latest s --host 0.0.0.0 --port 7000 || true
fi

cat <<TXT

Fertig. Nächste Schritte:
  php artisan user:create "Markus" markus@freise.de --admin
  composer run dev            # Server, Queue-Worker und Vite zusammen
Ohne API-Key: FLIPPER_FAKE_AI=true in .env (Luna-Fixtures statt Claude).
TXT
