# Erstinstallation

Die Erstinstallation wird ausschließlich über den Produktionsbefehl von
`./roo` ausgeführt. Das lokale `compose.yaml` und die Entwicklungsbefehle sind
für diesen Vorgang nicht vorgesehen.

## Voraussetzungen

Vor dem Skriptlauf müssen:

- Docker Engine und das Compose-Plugin installiert sein,
- ein freigegebener Release-Checkout unter `/opt/roo/current` vorhanden sein,
- `/opt/roo/current/.env` auf `/opt/roo/shared/.env` zeigen,
- `APP_ENV=production` und `APP_DEBUG=false` gesetzt sein,
- die Produktionszugänge eingerichtet sein; `APP_KEY` und `REDIS_PASSWORD`
  dürfen leer sein und werden dann automatisch erzeugt,
- die Verzeichnisse `data/bildungsplaene/plans` und
  `data/curricula/curricula` im Release vorhanden sein.

Die Konfiguration und die Secret-Erzeugung sind in
[Voraussetzungen und Zielarchitektur](01-voraussetzungen-und-architektur.md)
beschrieben. Die `.env` muss mit `chmod 600` geschützt sein.

Beim Installationslauf erzeugt `./roo prod install` automatisch einen sicheren
`APP_KEY` und ein `REDIS_PASSWORD`, wenn die jeweiligen Einträge in `.env`
fehlen oder leer sind. Bereits gesetzte Werte werden nicht überschrieben. Die
generierten Werte werden nicht ausgegeben und die `.env` muss weiterhin mit
`chmod 600` geschützt bleiben.

## Installation ausführen

```bash
cd /opt/roo/current
./roo prod install
```

Der Befehl prüft die Produktionskonfiguration, erzeugt fehlende Secrets, baut
das Produktionsimage, startet die persistenten Dienste, legt die
Storage-Buckets an, führt die Migrationen aus, importiert Bildungspläne und
Curricula aus `data/`, baut die Laravel-Caches, überträgt die statischen
Dateien in ein vom Webdienst schreibgeschütztes Volume und startet App,
Horizon, Scheduler und Webdienst.

Das Installationsskript verwendet keine Seeder und keine destruktiven Befehle
wie `migrate:fresh` oder `db:wipe`. Bei einem Fehler endet der Befehl mit einem
Fehlercode. Die Ursache muss behoben werden, bevor der Befehl erneut ausgeführt
wird.

## Nachprüfung

Status und Logs werden ebenfalls über `./roo` abgefragt:

```bash
./roo prod status
./roo prod logs app horizon scheduler web
```

Danach Anmeldung, private Dateien, Suche, Queue, Mail sowie importierte
Bildungspläne und Curricula fachlich prüfen. Die vollständige Checkliste steht
unter [Produktionsskripte verwenden](07-produktionsskripte.md).
