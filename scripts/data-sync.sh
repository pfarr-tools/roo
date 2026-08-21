#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ROO="$ROOT/roo"

usage() {
  cat <<'EOF'
Verwendung:
  ./roo data push <ssh-path>
  ./roo data pull <ssh-path>
  ./roo data push <ssh-path> --source-down
  ./roo data pull <ssh-path> --source-down

Der SSH-Pfad hat die Form [user@]host:/absoluter/pfad/zur/roo-installation.
Die Installationen müssen über dieselbe Compose-Struktur und dieselben
Datenbank-/Object-Storage-Dienste verfügen.
EOF
}

die() {
  echo "Fehler: $*" >&2
  exit 2
}

step() {
  echo
  echo "[roo data] $*"
}

if [[ "${1:-}" == help || "${1:-}" == --help || "${1:-}" == -h ]]; then
  usage
  exit 0
fi

[[ $# -ge 2 && $# -le 3 ]] || { usage >&2; exit 2; }
direction="$1"
ssh_path="$2"
[[ "$direction" == push || "$direction" == pull ]] || die "Unbekannte Richtung: $direction"
source_down=0
if [[ $# -eq 3 ]]; then
  [[ "$3" == --source-down ]] || die "Unbekannte Option: $3"
  source_down=1
fi

if [[ "$ssh_path" != *:* || "$ssh_path" == :* ]]; then
  die "SSH-Pfad muss [user@]host:/absoluter/pfad sein."
fi
ssh_host="${ssh_path%%:*}"
remote_root="${ssh_path#*:}"
[[ -n "$ssh_host" && "$remote_root" == /* ]] || die "SSH-Pfad muss einen Host und einen absoluten Pfad enthalten."

shell_quote() {
  printf '%q' "$1"
}

remote() {
  local command="$1"
  ssh "$ssh_host" "cd $(shell_quote "$remote_root") && $command"
}

remote_roo() {
  local command="$1"
  remote "./roo $command"
}

local_down=0
remote_down=0
cleanup() {
  local status=$?
  if (( local_down )); then
    "$ROO" artisan up || echo "Warnung: Lokale Anwendung ist weiterhin im Wartungsmodus." >&2
  fi
  if (( remote_down )); then
    remote_roo "artisan up" || echo "Warnung: Entfernte Anwendung ist weiterhin im Wartungsmodus." >&2
  fi
  exit "$status"
}
trap cleanup EXIT

if [[ "$direction" == push ]]; then
  if (( source_down )); then
    step "1/7 Lokale Quellanwendung in den Wartungsmodus versetzen"
    "$ROO" artisan down
    local_down=1
  else
    step "1/7 Lokale Quellanwendung bleibt online"
  fi
  step "2/7 Entfernte Empfängeranwendung in den Wartungsmodus versetzen"
  remote_roo "artisan down"
  remote_down=1

  # Migrationslauf auf dem Empfänger muss vor dem Datenbankimport erfolgen.
  step "3/7 Migrationen auf dem Empfänger ausführen"
  remote_roo "artisan migrate --force"

  # Der Datenbankimport ersetzt das öffentliche Schema vollständig. So werden
  # auch Tabellen entfernt, die nur auf dem Empfänger vorhanden waren.
  step "4/7 PostgreSQL-Datenbank übertragen"
  docker compose exec -T postgres sh -c 'pg_dump -Fc -U "$POSTGRES_USER" -d "$POSTGRES_DB"' \
    | remote "docker compose exec -T postgres sh -c 'psql -U \"\$POSTGRES_USER\" -d \"\$POSTGRES_DB\" -v ON_ERROR_STOP=1 -c \"DROP SCHEMA public CASCADE; CREATE SCHEMA public;\" && pg_restore --no-owner --no-acl -U \"\$POSTGRES_USER\" -d \"\$POSTGRES_DB\"'"

  # Das MinIO-Image enthält kein tar/find. Das Postgres-Alpine-Image wird als
  # temporärer Volume-Container verwendet und greift auf dasselbe /data zu.
  step "5/7 Object-Storage übertragen"
  docker run --rm -i --volumes-from "$(docker compose ps -q object-storage)" postgres:17-alpine \
    tar -C /data -cf - . \
    | remote "docker run --rm -i --volumes-from \"\$(docker compose ps -q object-storage)\" postgres:17-alpine sh -c 'find /data -mindepth 1 -maxdepth 1 -exec rm -rf -- {} + && tar -C /data -xf -'"
else
  if (( source_down )); then
    step "1/7 Entfernte Quellanwendung in den Wartungsmodus versetzen"
    remote_roo "artisan down"
    remote_down=1
  else
    step "1/7 Entfernte Quellanwendung bleibt online"
  fi
  step "2/7 Lokale Empfängeranwendung in den Wartungsmodus versetzen"
  "$ROO" artisan down
  local_down=1

  step "3/7 Migrationen auf dem Empfänger ausführen"
  "$ROO" artisan migrate --force

  step "4/7 PostgreSQL-Datenbank übertragen"
  remote "docker compose exec -T postgres sh -c 'pg_dump -Fc -U \"\$POSTGRES_USER\" -d \"\$POSTGRES_DB\"'" \
    | docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -v ON_ERROR_STOP=1 -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;" && pg_restore --no-owner --no-acl -U "$POSTGRES_USER" -d "$POSTGRES_DB"'

  step "5/7 Object-Storage übertragen"
  remote "docker run --rm -i --volumes-from \"\$(docker compose ps -q object-storage)\" postgres:17-alpine tar -C /data -cf - ." \
    | docker run --rm -i --volumes-from "$(docker compose ps -q object-storage)" postgres:17-alpine sh -c 'find /data -mindepth 1 -maxdepth 1 -exec rm -rf -- {} + && tar -C /data -xf -'
fi

# optimize:clear umfasst Konfiguration, Routen, Views und den Anwendungscache.
step "6/7 Caches auf dem Empfänger leeren"
if [[ "$direction" == push ]]; then
  remote_roo "artisan optimize:clear"
else
  "$ROO" artisan optimize:clear
fi

step "7/7 Wartungsmodus beenden"
if [[ "$direction" == push ]]; then
  "$ROO" artisan up
  local_down=0
  remote_roo "artisan up"
  remote_down=0
else
  remote_roo "artisan up"
  remote_down=0
  "$ROO" artisan up
  local_down=0
fi
echo "Daten erfolgreich übertragen ($direction)."
