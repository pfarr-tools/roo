# Störungsbehebung

## Anwendung liefert 502/503

```bash
./roo prod status
./roo prod logs --tail=200 web app
```

Ist `app` nicht healthy, PHP-FPM-Fehler, fehlende Umgebungsvariablen,
Berechtigungen und die Erreichbarkeit von PostgreSQL prüfen. Bei 502 zwischen
Proxy und App zuerst den internen Service-Namen und Port des Produktionsprofils
prüfen.

## Anmeldung oder Sessions funktionieren nicht

Redis-Erreichbarkeit, `SESSION_DRIVER`, `REDIS_HOST`, Passwort, `APP_URL` und
HTTPS-Proxy-Header prüfen. Danach den Cache kontrolliert leeren:

```bash
./roo prod exec app php artisan optimize:clear
./roo prod exec app php artisan config:cache
```

## Jobs bleiben liegen

```bash
./roo prod logs --tail=200 horizon
./roo prod exec app php artisan queue:failed
./roo prod exec app php artisan horizon:status
```

Redis-Erreichbarkeit, Horizon-Prozess, Queue-Namen und Speichergrenzen prüfen.
Fehlgeschlagene Jobs erst nach Ursachenanalyse mit `queue:retry` erneut starten;
nicht blind alle Jobs wiederholen.

## Suche ist leer oder veraltet

Meilisearch-Erreichbarkeit und Schlüssel prüfen. PostgreSQL bleibt die Quelle der
Wahrheit. Indexoperationen in einem Wartungsfenster und nach einem Backup der
Anwendungskonfiguration durchführen. Keine Schülerdaten zum Beheben der Suche
in einen Index aufnehmen.

## Seite bleibt leer oder Assets werden als HTTP geladen

Bei einem HTTPS-Aufruf müssen Stylesheets und JavaScript ebenfalls mit
`https://` beginnen. `APP_URL` muss auf die öffentliche HTTPS-Adresse zeigen.
Der Host-Reverse-Proxy muss `X-Forwarded-Proto: https` und
`X-Forwarded-Host` weitergeben; der interne Produktions-Caddy ist für private
Proxy-Netze konfiguriert. Nach Änderungen an `.env` oder der Proxy-Konfiguration
die App-Konfiguration neu bauen und den Webdienst neu erzeugen:

```bash
./roo prod exec app php artisan optimize:clear
./roo prod exec app php artisan config:cache
./roo prod up web
```

Der direkte Roo-Webdienst ist standardmäßig nur unter `127.0.0.1:8080`
erreichbar. Bei einem anderen `APP_PORT` muss das Ziel im Host-Caddyfile
entsprechend angepasst werden.

## Datei-Upload oder Download schlägt fehl

S3-Endpunkt, Bucket, Region, TLS, IAM-Rechte und Speicherplatz prüfen. Die
Anwendung darf private Objekte nicht durch eine öffentliche Bucket-Policy
"reparieren". Erst einen autorisierten Download mit einem Testkonto prüfen.

## Migration schlägt fehl

Wartungsmodus beibehalten, Fehlermeldung und Migration-ID sichern und keine
weiteren Releases starten. Datenbankstatus und Backup prüfen. Eine
Gegenmigration nur nach Codeprüfung ausführen; bei Unsicherheit Rollback in
Staging testen und erst dann produktiv wiederherstellen.

## Notfall-Checkliste

1. Zeitpunkt, Symptome und letzte Änderung festhalten.
2. Datenbank und Object Storage vor weiteren Eingriffen sichern.
3. Betroffene Dienste nur im notwendigen Umfang stoppen.
4. Keine destruktiven Befehle wie `migrate:fresh`, `db:wipe` oder ungezielte
   Volume-Löschungen ausführen.
5. Rollback oder Wiederherstellung zuerst in einer isolierten Umgebung prüfen.
6. Nach Behebung Datenschutz-, Berechtigungs- und Queue-Prüfungen wiederholen.
