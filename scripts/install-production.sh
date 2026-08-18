#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT/compose.production.yaml"

die() {
  echo "Fehler: $*" >&2
  exit 1
}

command -v docker >/dev/null 2>&1 || die "Docker ist nicht installiert oder nicht im PATH."
[[ -f "$COMPOSE_FILE" ]] || die "Produktionsdatei fehlt: $COMPOSE_FILE"
[[ -f "$ROOT/.env" ]] || die "Produktionskonfiguration fehlt: $ROOT/.env"
[[ -d "$ROOT/data/bildungsplaene/plans" ]] || die "Bildungsplandaten fehlen: $ROOT/data/bildungsplaene/plans"
[[ -d "$ROOT/data/curricula/curricula" ]] || die "Curriculumdaten fehlen: $ROOT/data/curricula/curricula"

grep -Eq '^APP_ENV=production([[:space:]]|$)' "$ROOT/.env" \
  || die "APP_ENV muss in .env auf production gesetzt sein."
grep -Eq '^APP_DEBUG=false([[:space:]]|$)' "$ROOT/.env" \
  || die "APP_DEBUG muss in .env auf false gesetzt sein."

cd "$ROOT"

compose() {
  docker compose --project-directory "$ROOT" -f "$COMPOSE_FILE" "$@"
}

echo "Prüfe Produktionskonfiguration ..."
compose config --quiet

echo "Baue Produktionsimage ..."
compose build app horizon scheduler public-assets

echo "Starte persistente Dienste ..."
compose up -d postgres redis meilisearch object-storage

echo "Lege Object-Storage-Buckets an ..."
compose run --rm --no-deps create-buckets

echo "Starte App für Initialisierung ..."
compose up -d app

echo "Führe Datenbankmigrationen aus ..."
compose exec app php artisan migrate --force

echo "Importiere Bildungspläne aus data/ ..."
compose exec app php artisan education-plans:import /var/www/data/bildungsplaene/plans

echo "Importiere Curricula aus data/ ..."
compose exec app php artisan curricula:import /var/www/data/curricula/curricula

echo "Erzeuge Laravel-Produktionscache ..."
compose exec app php artisan optimize:clear
compose exec app php artisan config:cache
compose exec app php artisan route:cache
compose exec app php artisan view:cache

echo "Übertrage statische Produktionsdateien ..."
compose run --rm --no-deps public-assets

echo "Starte Roo ..."
compose up -d app horizon scheduler web
compose ps

echo "Produktionsinstallation abgeschlossen."
