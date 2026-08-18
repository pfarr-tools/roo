# Produktionsskripte verwenden

Roo enthält zwei Bash-Skripte für den Betrieb des Produktionsprofils:

| Skript | Zweck |
| --- | --- |
| `scripts/install-production.sh` | Erstinstallation eines neuen Produktionssystems |
| `scripts/update-production.sh` | Ausrollen eines neuen Release-Stands |

Beide Skripte müssen aus einem Release-Checkout mit
`compose.production.yaml`, `data/` und der Produktionskonfiguration ausgeführt
werden. Die lokale Entwicklungsdatei `compose.yaml` wird nicht verwendet.

Für manuelle Produktionsbefehle kapselt `./roo prod` die Produktionsdatei:

```bash
./roo prod status
./roo prod logs web
./roo prod up web
./roo prod restart app
./roo prod exec app php artisan about
./roo prod compose config --quiet
```

`./roo prod compose ...` ist der allgemeine Durchgriff auf Docker Compose mit
`compose.production.yaml`; die Kurzbefehle verwenden zusätzlich sinnvolle
Standardoptionen wie `-d` bei `up` und `-f` bei `logs`.

## Voraussetzungen

- Docker Engine und Compose-Plugin sind installiert.
- Git ist für Updates installiert.
- Das aktuelle Release liegt beispielsweise unter `/opt/roo/current`.
- `/opt/roo/current/.env` ist ein Symlink auf `/opt/roo/shared/.env`.
- `.env` enthält mindestens `APP_ENV=production`, `APP_DEBUG=false`,
  Datenbank-, Meilisearch-, Storage- und Mail-Zugangsdaten. `APP_KEY` und
  `REDIS_PASSWORD` werden bei leeren oder fehlenden Einträgen während
  `./roo prod install` erzeugt.
- Das Produktionsprofil ist mit `./roo prod compose config --quiet` validiert.
- Der Docker-Account darf Images bauen und Container verwalten.

Die Skripte geben keine Secrets aus. Die `.env` muss mit restriktiven
Berechtigungen geschützt sein:

```bash
chmod 600 /opt/roo/shared/.env
```

## Erstinstallation

Nach dem Einrichten der `.env` und dem Checkout des freigegebenen Stands:

```bash
cd /opt/roo/current
./roo prod install
```

Das Skript führt in dieser Reihenfolge aus:

1. Docker-, Compose-, Produktions- und Konfigurationsprüfung einschließlich
   der Initialisierung leerer Secrets
2. Build des Produktionsimages inklusive Frontend-Assets
3. Start von PostgreSQL, Redis, Meilisearch und Object Storage
4. Anlage der fünf benötigten Storage-Buckets
5. Ausführung der Datenbankmigrationen
6. Import aller Bildungspläne aus `data/bildungsplaene/plans`
7. Import aller Curricula aus `data/curricula/curricula`
8. Aufbau von Konfigurations-, Routen- und View-Cache
9. Übertragung der statischen Dateien als einmaliger privilegierter
   Kopiervorgang in das Caddy-Volume; der laufende App-Container bleibt
   unprivilegiert
10. Start von App, Horizon, Scheduler und Webdienst

Die Skriptdatei verwendet bewusst keine Seeder und keine destruktiven Befehle
wie `migrate:fresh` oder `db:wipe`. Bei einem Fehler beendet sie sich mit einem
Fehlercode; die Fehlermeldung und die Containerlogs müssen geprüft werden.

## Update

Vor jedem Update:

1. PostgreSQL- und Object-Storage-Backup erstellen und prüfen.
2. Wartungsfenster und Rollback-Release festlegen.
3. Laufende Importe und Queue-Jobs kontrollieren.

Ein bestimmtes Release fetchen und ausrollen:

```bash
cd /opt/roo/current
./roo prod update \
  --backup-confirmed \
  --ref <freigegebener-tag-oder-commit>
```

Ohne `--ref` wird der bereits ausgecheckte Stand verwendet:

```bash
./roo prod update --backup-confirmed
```

`--backup-confirmed` ist verpflichtend. Das Skript erstellt selbst kein
Backup, weil Backupziel, Verschlüsselung und Aufbewahrung von der jeweiligen
Produktionsumgebung abhängen. Die Option bestätigt lediglich, dass die
betreibende Person ein aktuelles Backup erstellt und geprüft hat.

Das Updateskript aktiviert den Wartungsmodus, baut das neue Image, startet die
persistenten Dienste bei Bedarf, führt Migrationen und die Bucket-Erzeugung
aus, leert und baut die Laravel-Caches neu, beendet alte Horizon-Worker,
aktualisiert die statischen Dateien und startet die Produktionsdienste neu. Bei
einem Fehler versucht es, den Wartungsmodus automatisch zu beenden.

## Nach jedem Skriptlauf

```bash
./roo prod status
./roo prod logs app horizon scheduler web
```

Zusätzlich fachlich prüfen:

- Anmeldung und Zugriff auf erlaubte Daten
- Zugriffsschutz einer privaten Datei
- Suche mit nicht-personenbezogenen Daten
- Verarbeitung eines Queue-Jobs
- Mailversand
- importierte Bildungspläne und Curricula

## Fehler und Wiederholung

Ein fehlgeschlagener Installationslauf darf nach Ursachenbehebung wiederholt
werden. Die Importbefehle sind für vorhandene Datenbestände als erneuter Import
ausgelegt; trotzdem müssen die Importprotokolle und die Datenbank vor einer
Wiederholung geprüft werden.

Bei einem fehlgeschlagenen Update zunächst im Wartungsmodus bleiben, Logs und
Migration sichern und nicht mehrfach blind wiederholen. Danach das vorherige
Release nach dem [Rollback-Verfahren](03-updates-und-rollback.md) ausrollen
oder die Datenbank in einer isolierten Umgebung aus dem Backup prüfen.
