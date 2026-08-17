# Testdaten und lokale Entwicklungsdatenbank

`./roo test` läuft mit `APP_ENV=testing` und erzwingt SQLite `:memory:`. Die
Tests verwenden damit eine eigene, pro Testlauf aufgebaute Datenbank. Die
PostgreSQL-Datenbank des laufenden Roo-Containers wird durch den Testbefehl
nicht gelesen, verändert oder zurückgesetzt.

Feature-Tests mit `RefreshDatabase` setzen ihre isolierte Testdatenbank vor dem
Test zurück. Das ist beabsichtigt: Testdaten wie Benutzer, Schulen und
Schuljahre gehören nicht in die lokalen Entwicklungsdaten und werden nicht für
manuelle Browser-Tests verwendet.

Der Befehl `./roo fresh` ist dagegen destruktiv und betrifft die normale lokale
Datenbank. Er verlangt deshalb ausdrücklich:

```bash
./roo fresh --force
```

Für die normale Entwicklung ist `./roo artisan migrate` der sichere Befehl.
Damit bleiben bereits angelegte Benutzer, Schulen und Schuljahre erhalten.
