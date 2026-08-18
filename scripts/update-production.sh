#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT/compose.production.yaml"
BACKUP_CONFIRMED=0
RELEASE_REF=""

die() {
  echo "Fehler: $*" >&2
  exit 1
}

usage() {
  cat <<'EOF'
Verwendung:
  scripts/update-production.sh --backup-confirmed [--ref <tag|commit>]

Vor dem Update muss ein aktuelles, erfolgreich getestetes Backup der
Produktionsdatenbank und des Object Storages vorhanden sein.
EOF
}

while (($# > 0)); do
  case "$1" in
    --backup-confirmed)
      BACKUP_CONFIRMED=1
      ;;
    --ref)
      (($# >= 2)) || die "--ref benötigt einen Tag oder Commit."
      RELEASE_REF="$2"
      shift
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      usage >&2
      die "Unbekannte Option: $1"
      ;;
  esac
  shift
done

((BACKUP_CONFIRMED == 1)) \
  || die "Abbruch: zuerst ein aktuelles Backup erstellen und mit --backup-confirmed bestätigen."
command -v docker >/dev/null 2>&1 || die "Docker ist nicht installiert oder nicht im PATH."
command -v git >/dev/null 2>&1 || die "Git ist nicht installiert oder nicht im PATH."
[[ -f "$COMPOSE_FILE" ]] || die "Produktionsdatei fehlt: $COMPOSE_FILE"
[[ -f "$ROOT/.env" ]] || die "Produktionskonfiguration fehlt: $ROOT/.env"

if [[ -n "$RELEASE_REF" ]]; then
  echo "Hole Release-Referenzen ..."
  git -C "$ROOT" fetch --tags --prune
  echo "Wechsle auf Release: $RELEASE_REF"
  git -C "$ROOT" checkout --detach "$RELEASE_REF"
fi

grep -Eq '^APP_ENV=production([[:space:]]|$)' "$ROOT/.env" \
  || die "APP_ENV muss in .env auf production gesetzt sein."
grep -Eq '^APP_DEBUG=false([[:space:]]|$)' "$ROOT/.env" \
  || die "APP_DEBUG muss in .env auf false gesetzt sein."

cd "$ROOT"

compose() {
  docker compose --project-directory "$ROOT" -f "$COMPOSE_FILE" "$@"
}

maintenance_enabled=0

cleanup() {
  if ((maintenance_enabled == 1)); then
    echo "Versuche, den Wartungsmodus zu beenden ..." >&2
    compose exec app php artisan up >/dev/null 2>&1 || true
  fi
}

trap cleanup EXIT

echo "Prüfe Produktionskonfiguration ..."
compose config --quiet

echo "Aktiviere Wartungsmodus ..."
compose exec app php artisan down --render="errors::503" || true
maintenance_enabled=1

echo "Baue neues Produktionsimage ..."
compose build app horizon scheduler public-assets

echo "Stelle persistente Dienste sicher ..."
compose up -d postgres redis meilisearch object-storage
compose run --rm --no-deps create-buckets

echo "Führe Datenbankmigrationen aus ..."
compose run --rm --no-deps app php artisan migrate --force

echo "Erzeuge Laravel-Produktionscache ..."
compose run --rm --no-deps app php artisan optimize:clear
compose run --rm --no-deps app php artisan config:cache
compose run --rm --no-deps app php artisan route:cache
compose run --rm --no-deps app php artisan view:cache

echo "Beende alte Horizon-Worker ..."
compose exec app php artisan horizon:terminate || true

echo "Übertrage statische Produktionsdateien ..."
compose run --rm --no-deps public-assets

echo "Starte aktualisierte Dienste ..."
compose up -d app horizon scheduler web
compose exec app php artisan up
maintenance_enabled=0
compose ps

echo "Produktionsupdate abgeschlossen."
