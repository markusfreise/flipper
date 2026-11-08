#!/usr/bin/env bash
# Lokale Umgebung auf dem Mac (Valet + Postgres + optional rembg via Docker). Idempotent.
#   ./_bootstrap/bootstrap-local.sh
set -euo pipefail
cd "$(dirname "$0")/.."

git submodule update --init --recursive

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

composer install
npm install
php artisan migrate
npm run build

# Valet: http://flipper.test
command -v valet >/dev/null && valet link flipper >/dev/null && echo "Valet: http://flipper.test"

# rembg lokal (Docker Desktop)
if command -v docker >/dev/null && ! docker ps --format '{{.Names}}' | grep -q '^flipper-rembg$'; then
  docker run -d --name flipper-rembg -p 7000:7000 -v flipper-rembg:/root/.u2net \
    --restart unless-stopped danielgatis/rembg:latest s --host 0.0.0.0 --port 7000 || true
fi

cat <<TXT

Fertig. Nächste Schritte:
  php artisan user:create "Markus" markus@freise.de --admin
  composer run dev            # Server, Queue-Worker und Vite zusammen
Ohne API-Key: FLIPPER_FAKE_AI=true in .env (Luna-Fixtures statt Claude).
TXT
