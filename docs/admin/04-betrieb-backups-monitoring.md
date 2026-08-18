# Regelbetrieb, Backups und Monitoring

## Tägliche Kontrollen

- Containerstatus und Healthchecks prüfen.
- Horizon auf wartende, fehlgeschlagene und ungewöhnlich lange Jobs prüfen.
- PostgreSQL-Speicher, Verbindungen und Backupalter prüfen.
- Redis-Speicher und Erreichbarkeit prüfen.
- S3-Speicher, Fehler und Lifecycle für temporäre Dateien prüfen.
- TLS-Ablauf, freien Plattenplatz und Server-Sicherheitsupdates prüfen.
- Fehlerlogs nur mit Zugriffsschutz und angemessener Aufbewahrung einsehen.

Beispiel:

```bash
./roo prod status
./roo prod logs --since=1h app horizon scheduler web
./roo prod exec app php artisan about
./roo prod exec app php artisan queue:failed
```

Es gibt derzeit keinen fest verdrahteten öffentlichen Health-Endpunkt. Für
Uptime-Monitoring die HTTPS-Startseite und zusätzlich die Container-Healthchecks
überwachen. Einen fehlgeschlagenen Login nicht als alleinigen technischen
Healthcheck verwenden.

## Backups

Backups müssen verschlüsselt, außerhalb des Produktionsservers gespeichert und
regelmäßig durch eine Testwiederherstellung verifiziert werden. Eine sinnvolle
Mindeststrategie ist:

- PostgreSQL: täglicher vollständiger Dump plus vom Provider verwaltete
  Point-in-Time-Recovery/WAL-Aufbewahrung, sofern verfügbar
- Object Storage: versionierter oder replizierter Bucket für `documents`,
  `generated`, `imports` und `exports`
- Redis: nur dann als Primärbackup behandeln, wenn Sitzungen/Jobs nach Verlust
  nicht verworfen werden dürfen; Cache kann neu aufgebaut werden
- Meilisearch: nicht als Quelle der Wahrheit sichern; Index bei Bedarf aus den
  Daten neu erzeugen
- `.env` und Schlüssel: verschlüsselt in einem Secret-Manager sichern, niemals
  im selben ungeschützten Verzeichnis wie der Datenbankdump

PostgreSQL-Dump (Zielpfad und Zugang bewusst festlegen):

```bash
umask 077
pg_dump --format=custom --file=/opt/roo/backups/roo-$(date +%F-%H%M).dump \
  --host=<db-host> --port=5432 --username=<db-user> <db-name>
```

Das Passwort über `.pgpass` oder einen Secret-Mechanismus zuführen, nicht als
Argument und nicht in der Shell-Historie. Aufbewahrung, Verschlüsselung und
Löschung nach dem Datenschutzkonzept der betreibenden Organisation festlegen.

## Wiederherstellungstest

Mindestens quartalsweise einen Dump in eine isolierte PostgreSQL-Instanz
importieren, eine Roo-App dagegen starten und Anmeldung, Datensätze,
Dateibeziehungen sowie einen privaten Dateidownload prüfen. Den Test mit Datum,
Backup-ID, Dauer und Ergebnis protokollieren.

## Wartung

Container-Images, Docker Engine, Host-Kernel und TLS-Komponenten regelmäßig
aktualisieren. Sicherheitsupdates zuerst in Staging testen. Alte Images und
temporäre Exporte erst löschen, wenn das aktive Release und die
Aufbewahrungsfristen geprüft sind.
