#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$ROOT/src"

cd "$ROOT"

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo ".env wurde aus .env.example erzeugt."
fi

mkdir -p "$SRC"

if [[ ! -f "$SRC/artisan" ]]; then
  if [[ -n "$(find "$SRC" -mindepth 1 -maxdepth 1 -print -quit)" ]]; then
    echo "Fehler: src/ ist nicht leer, enthält aber kein Laravel-Projekt." >&2
    exit 1
  fi

  echo "Erzeuge Laravel 13 ..."
  docker compose run --rm --no-deps app \
    composer create-project laravel/laravel:^13.0 /var/www/html
fi

echo "Starte Infrastruktur ..."
docker compose up -d postgres redis meilisearch object-storage mailpit
docker compose run --rm create-buckets

echo "Installiere Backend-Pakete ..."
docker compose run --rm --no-deps app composer require \
  inertiajs/inertia-laravel:^3.0 \
  laravel/fortify \
  laravel/horizon \
  laravel/scout \
  meilisearch/meilisearch-php \
  league/flysystem-aws-s3-v3

docker compose run --rm --no-deps app composer require --dev pestphp/pest pestphp/pest-plugin-laravel --with-all-dependencies

echo "Installiere Frontend-Pakete ..."
docker compose run --rm --no-deps vite npm install \
  vue \
  @vitejs/plugin-vue \
  @inertiajs/vue3 \
  bootstrap@^5.3 \
  bootstrap-icons \
  sass

echo "Kopiere Roo-Basiskonfiguration ..."
cp -n "$ROOT/scaffold/vite.config.js" "$SRC/vite.config.js" || true
mkdir -p "$SRC/resources/js/Pages" "$SRC/resources/js/Layouts" "$SRC/resources/js/Components/Ui" "$SRC/resources/scss"
cp -n "$ROOT/scaffold/resources/js/app.js" "$SRC/resources/js/app.js" || true
cp -n "$ROOT/scaffold/resources/js/Pages/Welcome.vue" "$SRC/resources/js/Pages/Welcome.vue" || true
cp -n "$ROOT/scaffold/resources/scss/app.scss" "$SRC/resources/scss/app.scss" || true
cp -n "$ROOT/scaffold/resources/views/app.blade.php" "$SRC/resources/views/app.blade.php" || true
cp -n "$ROOT/scaffold/routes/web.php" "$SRC/routes/web.php" || true

echo "Aktiviere Laravel-Pakete ..."
docker compose run --rm --no-deps app php artisan horizon:install
docker compose run --rm --no-deps app php artisan fortify:install
docker compose run --rm --no-deps app php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider" || true

echo "Setze Anwendungsschlüssel ..."
APP_KEY_VALUE="$(docker compose run --rm --no-deps app php artisan key:generate --show)"
docker compose run --rm --no-deps app php artisan key:generate --force
if grep -q '^APP_KEY=' .env; then
  sed -i "s#^APP_KEY=.*#APP_KEY=$APP_KEY_VALUE#" .env
else
  printf '\nAPP_KEY=%s\n' "$APP_KEY_VALUE" >> .env
fi

echo "Initialisiere Pest ..."
if [[ ! -f "$SRC/Pest.php" ]]; then
  docker compose run --rm --no-deps app ./vendor/bin/pest --init
fi

echo "Führe Migrationen aus ..."
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan scout:sync-index-settings --driver=meilisearch

echo "Starte Roo ..."
docker compose up -d

cat <<'EOF'

Roo wurde initialisiert.

Anwendung:              http://localhost:8080
Mailpit:                 http://localhost:8025
Meilisearch:             http://localhost:7700
Object-Storage-Konsole:  http://localhost:9001

Nächster Schritt für Codex:
  Lies AGENTS.md und build/masterplan.md und vervollständige Phase 0,
  insbesondere Fortify/Inertia-Authentifizierung, deutsche UI und Tests.
EOF
